using System.Text.Json;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using TravelAdvisor.Core.Models;
using TravelAdvisor.Core.Services;

namespace TravelAdvisor.Infrastructure.Services
{
    /// <summary>
    /// Implementation of ITravelAdvisorService using Microsoft.Extensions.AI and Semantic Kernel
    /// </summary>
    public class TravelAdvisorService : ITravelAdvisorService
    {
        private readonly ILogger<TravelAdvisorService> _logger;
        private readonly IMapService _mapService;
        private readonly IChatClient _chatClient;
        private readonly IPromptFactory _promptFactory;

        public TravelAdvisorService(
            IChatClient chatClient,
            IPromptFactory promptFactory,
            IMapService mapService,
            ILogger<TravelAdvisorService> logger)
        {
            _chatClient = chatClient ?? throw new ArgumentNullException(nameof(chatClient));
            _promptFactory = promptFactory ?? throw new ArgumentNullException(nameof(promptFactory));
            _mapService = mapService ?? throw new ArgumentNullException(nameof(mapService));
            _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        }

        /// <inheritdoc />
        public async Task<TravelQuery> ProcessNaturalLanguageQueryAsync(string query)
        {
            try
            {
                _logger.LogInformation("Processing natural language query: {Query}", query);

                // Create a conversation history with the system prompt and user query
                var history = new List<ChatMessage>
                {
                    new ChatMessage(ChatRole.System, @"
                        You are a travel advisor assistant. Your primary task is to CAREFULLY extract the origin and destination locations from the user's query.

                        IMPORTANT: Your most critical task is to identify the specific origin and destination locations mentioned in the query.
                        - Look for locations that follow phrases like 'from', 'starting at', 'in', 'near', etc. for the origin.
                        - Look for locations that follow phrases like 'to', 'heading to', 'going to', 'destination', etc. for the destination.
                        - Always include the full location name, including city, state, and country if provided.
                        - Never leave Origin or Destination empty or as 'Unknown' if they are mentioned in the query.

                        Extract origin, destination, travel time information, and any preferences mentioned.

                        Return a JSON object with the following structure:
                        {
                            ""Origin"": ""extracted origin location with full details"",
                            ""Destination"": ""extracted destination location with full details"",
                            ""TravelTime"": {
                                ""DepartureTime"": ""extracted departure time or null"",
                                ""ArrivalTime"": ""extracted arrival time or null"",
                                ""IsFlexible"": true/false
                            },
                            ""Preferences"": {
                                ""Priority"": ""extracted priority (e.g., faster, cheaper, etc.)"",
                                ""ConsiderWalking"": true/false,
                                ""ConsiderBiking"": true/false,
                                ""ConsiderPublicTransport"": true/false,
                                ""ConsiderDriving"": true/false,
                                ""ConsiderTrain"": true/false,
                                ""ConsiderFlying"": true/false,
                                ""MaxWalkingDistance"": number or null,
                                ""MaxBikingDistance"": number or null,
                                ""MaxTravelTime"": number or null,
                                ""MaxCost"": number or null
                            },
                            ""AdditionalContext"": ""any other relevant information""
                        }

                        Always use true for transportation modes unless the user specifically rules them out.

                        IMPORTANT: Your response must ONLY contain valid JSON - no explanations, no prefixes, no additional text.

                        Examples:
                        1. For 'What's the best way to get from Seattle to Portland?', you should extract 'Seattle' as Origin and 'Portland' as Destination.
                        2. For 'How can I travel cheaply from Mill Creek, WA to Lynnwood, WA?', you should extract 'Mill Creek, WA' as Origin and 'Lynnwood, WA' as Destination.
                    "),
                    new ChatMessage(ChatRole.User, query)
                };

                // Get response from AI
                var response = await _chatClient.GetResponseAsync(history, new ChatOptions { Temperature = 0 });

                // Check if the response contains an error (e.g., service unavailable)
                var errorProperty = response.GetType().GetProperty("Error");
                if (errorProperty != null)
                {
                    var errorValue = errorProperty.GetValue(response) as string;
                    if (!string.IsNullOrEmpty(errorValue))
                    {
                        _logger.LogWarning("Error received from chat client: {Error}", errorValue);
                        return CreateDefaultQuery(query, true, "Service temporarily unavailable. Please try again later.");
                    }
                }

                // Extract content from the response
                var jsonResponse = GetContentFromResponse(response);

                // Parse the JSON response
                if (string.IsNullOrEmpty(jsonResponse))
                {
                    _logger.LogWarning("Empty response received from chat client");
                    // Try parsing directly from the query instead of using default values
                    return ExtractQueryFromText(query) ?? CreateDefaultQuery(query);
                }

                // Check if the response is an error message
                if (jsonResponse.Contains("Service temporarily unavailable"))
                {
                    _logger.LogWarning("Service unavailable message received from chat client");
                    return CreateDefaultQuery(query, true, jsonResponse);
                }

                try
                {
                    // Attempt to clean the response in case it has extra text before or after the JSON
                    // or if it contains markdown code blocks
                    jsonResponse = CleanJsonResponse(jsonResponse);
                    jsonResponse = CleanMarkdownCodeBlocks(jsonResponse);

                    // Log the cleaned JSON for debugging
                    _logger.LogDebug($"Cleaned JSON response: {jsonResponse}");

                    // Try to deserialize the JSON response
                    var travelQuery = JsonSerializer.Deserialize<TravelQuery>(jsonResponse,
                        new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

                    if (travelQuery == null)
                    {
                        _logger.LogWarning("Failed to parse travel query from response: {Response}", jsonResponse);
                        return ExtractQueryFromText(query) ?? CreateDefaultQuery(query);
                    }

                    // Validate the extracted origin and destination - if they're empty or "Unknown", try a fallback extraction
                    if (string.IsNullOrWhiteSpace(travelQuery.Origin) || travelQuery.Origin == "Unknown" ||
                        string.IsNullOrWhiteSpace(travelQuery.Destination) || travelQuery.Destination == "Unknown")
                    {
                        _logger.LogWarning("Origin or destination missing in parsed response. Attempting fallback extraction.");
                        var fallbackQuery = ExtractQueryFromText(query);
                        if (fallbackQuery != null)
                        {
                            // Keep any valid data from the original parsed query
                            if (string.IsNullOrWhiteSpace(travelQuery.Origin) || travelQuery.Origin == "Unknown")
                                travelQuery.Origin = fallbackQuery.Origin;
                            if (string.IsNullOrWhiteSpace(travelQuery.Destination) || travelQuery.Destination == "Unknown")
                                travelQuery.Destination = fallbackQuery.Destination;
                        }
                    }

                    return travelQuery;
                }
                catch (JsonException ex)
                {
                    _logger.LogError(ex, "Error parsing JSON response: {Response}", jsonResponse);
                    return ExtractQueryFromText(query) ?? CreateDefaultQuery(query);
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error processing natural language query");
                return CreateDefaultQuery(query);
            }
        }

        /// <inheritdoc />
        public async Task<List<TravelRecommendation>> GenerateRecommendationsAsync(TravelQuery query)
        {
            try
            {
                var recommendations = new List<TravelRecommendation>();

                // Check if we have sufficient information
                if (string.IsNullOrWhiteSpace(query.Origin) || string.IsNullOrWhiteSpace(query.Destination))
                {
                    _logger.LogWarning("Insufficient information in query. Origin or destination missing.");
                    return recommendations;
                }

                // Generate recommendations for each transportation mode
                var transportModes = new[]
                {
                    TransportMode.Walk,
                    TransportMode.Bike,
                    TransportMode.Bus,
                    TransportMode.Car,
                    TransportMode.Train,
                    TransportMode.Plane
                };

                // Get base distance/duration for driving to determine reasonable modes
                var (baseDistanceKm, _) = await _mapService.CalculateDistanceAndDurationAsync(
                    query.Origin, query.Destination, TransportMode.Car);

                foreach (var mode in transportModes)
                {
                    // Skip modes that are unreasonable for the distance
                    if (!_mapService.IsModeReasonable(baseDistanceKm, mode))
                    {
                        continue;
                    }

                    // Skip modes that the user explicitly doesn't want to consider
                    if (!ShouldConsiderMode(mode, query.Preferences))
                    {
                        continue;
                    }

                    try
                    {
                        // Get distance and duration for this mode
                        var (distanceKm, durationMinutes) = await _mapService.CalculateDistanceAndDurationAsync(
                            query.Origin, query.Destination, mode);

                        // Get travel steps
                        var steps = await _mapService.GetTravelStepsAsync(
                            query.Origin, query.Destination, mode);

                        // Create the recommendation
                        var recommendation = new TravelRecommendation
                        {
                            Mode = mode,
                            Summary = $"Travel by {mode} from {query.Origin} to {query.Destination}",
                            DurationMinutes = durationMinutes,
                            DistanceKm = distanceKm,
                            Steps = steps,
                            EstimatedCost = EstimateCost(mode, distanceKm),
                            EnvironmentalScore = CalculateEnvironmentalScore(mode),
                            ConvenienceScore = CalculateConvenienceScore(mode, durationMinutes),
                            Pros = GeneratePros(mode, durationMinutes, distanceKm),
                            Cons = GenerateCons(mode, durationMinutes, distanceKm)
                        };

                        // Calculate preference match score
                        recommendation.PreferenceMatchScore = CalculatePreferenceMatchScore(recommendation, query);

                        // Calculate overall score (weighted average)
                        recommendation.OverallScore = CalculateOverallScore(recommendation);

                        recommendations.Add(recommendation);
                    }
                    catch (Exception ex)
                    {
                        _logger.LogError(ex, "Error generating recommendation for mode {Mode}", mode);
                    }
                }

                // Sort recommendations by overall score (descending)
                recommendations = recommendations.OrderByDescending(r => r.OverallScore).ToList();

                // Mark the top recommendation as recommended
                if (recommendations.Any())
                {
                    recommendations[0].IsRecommended = true;
                }

                return recommendations;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error generating recommendations");
                return new List<TravelRecommendation>();
            }
        }

        /// <inheritdoc />
        public async Task<string> GenerateExplanationAsync(
            TravelRecommendation recommendation,
            TravelQuery query)
        {
            try
            {
                // Create message history for the explanation
                var history = new List<ChatMessage>
                {
                    new ChatMessage(ChatRole.System, @"
                        You are a travel advisor assistant. Generate a detailed explanation for the recommended travel mode.
                        Provide a natural, conversational explanation that covers:
                        1. Why this mode is suitable for the distance
                        2. How it aligns with the user's preferences
                        3. Trade-offs between time, cost, convenience, and environmental impact
                        4. Any special considerations for this journey

                        Keep the explanation conversational and friendly. Avoid using technical jargon.
                    "),
                    new ChatMessage(ChatRole.User, $@"
                        Origin: {query.Origin}
                        Destination: {query.Destination}
                        Recommended Mode: {recommendation.Mode}
                        Travel Distance: {recommendation.DistanceKm:F1} km
                        Travel Duration: {recommendation.DurationMinutes} minutes
                        Estimated Cost: {(recommendation.EstimatedCost.HasValue ? $"${recommendation.EstimatedCost.Value:F2}" : "Unavailable")}

                        Pros:
                        {string.Join("\n", recommendation.Pros.Select(p => $"- {p}"))}

                        Cons:
                        {string.Join("\n", recommendation.Cons.Select(c => $"- {c}"))}

                        User Preferences:
                        {JsonSerializer.Serialize(query.Preferences, new JsonSerializerOptions { WriteIndented = true })}

                        Generate a detailed explanation for why this travel mode is recommended.
                    ")
                };

                // Get response from AI
                var response = await _chatClient.GetResponseAsync(history, new ChatOptions { Temperature = 0.7f });

                return GetContentFromResponse(response) ??
                       "I'm sorry, I couldn't generate a detailed explanation for this recommendation.";
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error generating explanation");
                return "I'm sorry, I couldn't generate a detailed explanation for this recommendation.";
            }
        }

        /// <inheritdoc />
        public async Task<string> AnswerFollowUpQuestionAsync(
            string question,
            TravelRecommendation recommendation,
            TravelQuery query)
        {
            try
            {
                _logger.LogInformation("Processing follow-up question: {Question}", question);

                // Create message history for answering the follow-up question
                var history = new List<ChatMessage>
                {
                    new ChatMessage(ChatRole.System, @"
                        You are a travel advisor assistant. Answer the user's follow-up question about the recommended travel mode.
                        Provide a helpful, accurate, and concise answer to the user's question in plain text format.

                        IMPORTANT:
                        - Respond with natural language only, not with function calls or JSON.
                        - Do not use code blocks, markdown formatting, or structured data formats.
                        - Provide a direct, conversational response as if you were speaking to the user.

                        If you don't have specific information to answer the question, acknowledge that and suggest what information might be helpful.
                    "),
                    new ChatMessage(ChatRole.User, $@"
                        Original Query:
                        Origin: {query.Origin}
                        Destination: {query.Destination}

                        Recommended Mode: {recommendation.Mode}
                        Travel Distance: {recommendation.DistanceKm:F1} km
                        Travel Duration: {recommendation.DurationMinutes} minutes
                        Estimated Cost: {(recommendation.EstimatedCost.HasValue ? $"${recommendation.EstimatedCost.Value:F2}" : "Unavailable")}

                        Details:
                        Environmental Score: {recommendation.EnvironmentalScore}/100
                        Convenience Score: {recommendation.ConvenienceScore}/100
                        Preference Match Score: {recommendation.PreferenceMatchScore}/100
                        Overall Score: {recommendation.OverallScore}/100

                        Pros:
                        {string.Join("\n", recommendation.Pros.Select(p => $"- {p}"))}

                        Cons:
                        {string.Join("\n", recommendation.Cons.Select(c => $"- {c}"))}

                        Steps:
                        {string.Join("\n", recommendation.Steps.Select(step => $"- {step.Description} ({step.DistanceKm:F1} km, {step.DurationMinutes} mins)"))}

                        User's Follow-up Question: {question}
                    ")
                };

                // Get response from AI
                var response = await _chatClient.GetResponseAsync(history, new ChatOptions { Temperature = 0.7f });

                // Check if the response contains an error (e.g., service unavailable)
                var errorProperty = response.GetType().GetProperty("Error");
                if (errorProperty != null)
                {
                    var errorValue = errorProperty.GetValue(response) as string;
                    if (!string.IsNullOrEmpty(errorValue))
                    {
                        _logger.LogWarning("Error received from chat client when answering follow-up question: {Error}", errorValue);
                        return $"I'm sorry, I couldn't answer your follow-up question due to a service issue. Error: {errorValue}";
                    }
                }

                var content = GetContentFromResponse(response);

                // Check if the response is empty or contains an error message
                if (string.IsNullOrEmpty(content))
                {
                    _logger.LogWarning("Empty response received from chat client when answering follow-up question");
                    return "I'm sorry, I couldn't answer your follow-up question due to a service issue. Please try again later.";
                }

                // Check if the response contains a service unavailable message
                if (content.Contains("Service temporarily unavailable"))
                {
                    _logger.LogWarning("Service unavailable message received from chat client when answering follow-up question");
                    return "I'm sorry, the AI service is temporarily unavailable. Please try again later.";
                }

                return content;
            }
            catch (InvalidOperationException ex) when (ex.Message.Contains("USE_MOCK_DATA is false"))
            {
                // Special handling for the case when USE_MOCK_DATA is false but we're trying to use mock data
                _logger.LogError(ex, "AI service configuration error when answering follow-up question");
                return "I'm sorry, there's an issue with the AI service configuration. Please contact the administrator to verify the API key and settings.";
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error answering follow-up question");
                return "I'm sorry, I couldn't answer your follow-up question due to a technical issue. Please try again later.";
            }
        }

        #region Helper Methods

        /// <summary>
        /// Cleans up a JSON response by removing any non-JSON text before or after the JSON object
        /// </summary>
        private string CleanJsonResponse(string response)
        {
            try
            {
                // Try to find a JSON object in the response
                int startIndex = response.IndexOf('{');
                int endIndex = response.LastIndexOf('}');

                if (startIndex >= 0 && endIndex > startIndex)
                {
                    return response.Substring(startIndex, endIndex - startIndex + 1);
                }

                return response;
            }
            catch
            {
                // If any error occurs during cleaning, return the original response
                return response;
            }
        }

        /// <summary>
        /// Attempts to extract origin and destination directly from the text query
        /// This is a fallback method when the LLM fails to extract properly
        /// </summary>
        private TravelQuery? ExtractQueryFromText(string query)
        {
            try
            {
                string origin = "Unknown";
                string destination = "Unknown";
                string priority = string.Empty;

                // Simple pattern matching for common travel query formats
                // Look for "from X to Y" pattern
                var fromToMatch = System.Text.RegularExpressions.Regex.Match(query,
                    @"from\s+([^,\.;]+(?:,[^,\.;]+)*)\s+to\s+([^,\.;]+(?:,[^,\.;]+)*)",
                    System.Text.RegularExpressions.RegexOptions.IgnoreCase);

                if (fromToMatch.Success)
                {
                    origin = fromToMatch.Groups[1].Value.Trim();
                    destination = fromToMatch.Groups[2].Value.Trim();
                }

                // If not found, look for "between X and Y" pattern
                if (origin == "Unknown" || destination == "Unknown")
                {
                    var betweenMatch = System.Text.RegularExpressions.Regex.Match(query,
                        @"between\s+([^,\.;]+(?:,[^,\.;]+)*)\s+and\s+([^,\.;]+(?:,[^,\.;]+)*)",
                        System.Text.RegularExpressions.RegexOptions.IgnoreCase);

                    if (betweenMatch.Success)
                    {
                        origin = betweenMatch.Groups[1].Value.Trim();
                        destination = betweenMatch.Groups[2].Value.Trim();
                    }
                }

                // Check for priority keywords
                if (query.Contains("fast", StringComparison.OrdinalIgnoreCase) ||
                    query.Contains("quick", StringComparison.OrdinalIgnoreCase) ||
                    query.Contains("speed", StringComparison.OrdinalIgnoreCase))
                {
                    priority = "faster";
                }
                else if (query.Contains("cheap", StringComparison.OrdinalIgnoreCase) ||
                         query.Contains("inexpensive", StringComparison.OrdinalIgnoreCase) ||
                         query.Contains("economical", StringComparison.OrdinalIgnoreCase) ||
                         query.Contains("save money", StringComparison.OrdinalIgnoreCase))
                {
                    priority = "cheaper";
                }
                else if (query.Contains("eco", StringComparison.OrdinalIgnoreCase) ||
                         query.Contains("environment", StringComparison.OrdinalIgnoreCase) ||
                         query.Contains("green", StringComparison.OrdinalIgnoreCase))
                {
                    priority = "environmental";
                }

                // Only return a query if we successfully extracted both origin and destination
                if (origin != "Unknown" && destination != "Unknown")
                {
                    _logger.LogInformation("Extracted query directly from text: From {Origin} to {Destination}",
                        origin, destination);

                    var travelQuery = new TravelQuery
                    {
                        Origin = origin,
                        Destination = destination,
                        AdditionalContext = query
                    };

                    if (!string.IsNullOrEmpty(priority))
                    {
                        travelQuery.Preferences.Priority = priority;
                    }

                    return travelQuery;
                }

                return null;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error in fallback extraction from text");
                return null;
            }
        }

        private TravelQuery CreateDefaultQuery(string originalQuery, bool isError = false, string errorMessage = "")
        {
            var query = new TravelQuery
            {
                Origin = "Unknown",
                Destination = "Unknown",
                AdditionalContext = isError ? "" : originalQuery,
                HasError = isError,
                ErrorMessage = errorMessage
            };

            return query;
        }

        private bool ShouldConsiderMode(TransportMode mode, TravelPreferences preferences)
        {
            return mode switch
            {
                TransportMode.Walk => preferences.ConsiderWalking,
                TransportMode.Bike => preferences.ConsiderBiking,
                TransportMode.Bus => preferences.ConsiderPublicTransport,
                TransportMode.Car => preferences.ConsiderDriving,
                TransportMode.Train => preferences.ConsiderTrain,
                TransportMode.Plane => preferences.ConsiderFlying,
                _ => true
            };
        }

        private decimal? EstimateCost(TransportMode mode, double distanceKm)
        {
            // Simple cost estimation model based on mode and distance
            decimal distanceDecimal = (decimal)distanceKm;
            return mode switch
            {
                TransportMode.Walk => 0, // Walking is free
                TransportMode.Bike => 0, // Assuming user has a bike
                TransportMode.Bus => 2.5m + (distanceDecimal * 0.05m), // Base fare + per km
                TransportMode.Car => 0.25m * distanceDecimal, // $0.25 per km (gas, wear, etc.)
                TransportMode.Train => 10m + (distanceDecimal * 0.15m), // Base fare + per km
                TransportMode.Plane => 50m + (distanceDecimal * 0.10m), // Base fare + per km
                _ => null
            };
        }

        private int CalculateEnvironmentalScore(TransportMode mode)
        {
            // Simple environmental impact scoring (0-100, higher is better/greener)
            return mode switch
            {
                TransportMode.Walk => 100, // Walking has no environmental impact
                TransportMode.Bike => 95, // Biking has minimal environmental impact
                TransportMode.Bus => 75, // Public transit is relatively green
                TransportMode.Train => 80, // Trains are usually efficient
                TransportMode.Car => 30, // Cars have significant environmental impact
                TransportMode.Plane => 10, // Planes have the highest environmental impact
                _ => 50
            };
        }

        private int CalculateConvenienceScore(TransportMode mode, int durationMinutes)
        {
            // Base convenience score by mode
            int baseScore = mode switch
            {
                TransportMode.Walk => 60, // Walking requires effort
                TransportMode.Bike => 65, // Biking requires effort but faster than walking
                TransportMode.Bus => 70, // Public transit can be inconvenient (waiting, transfers)
                TransportMode.Car => 90, // Cars are generally very convenient
                TransportMode.Train => 75, // Trains are convenient but fixed schedule
                TransportMode.Plane => 60, // Planes require airport hassle, security
                _ => 50
            };

            // Adjust for duration (longer trips are less convenient)
            int durationPenalty = 0;
            if (durationMinutes > 30) durationPenalty = 5;
            if (durationMinutes > 60) durationPenalty = 10;
            if (durationMinutes > 120) durationPenalty = 20;
            if (durationMinutes > 240) durationPenalty = 30;

            return Math.Max(0, baseScore - durationPenalty);
        }

        private List<string> GeneratePros(TransportMode mode, int durationMinutes, double distanceKm)
        {
            var pros = new List<string>();

            switch (mode)
            {
                case TransportMode.Walk:
                    pros.Add("Free of cost");
                    pros.Add("Environmentally friendly");
                    pros.Add("Good for health and fitness");
                    pros.Add("No parking hassles");
                    pros.Add("Enjoyable in good weather");
                    break;

                case TransportMode.Bike:
                    pros.Add("Inexpensive");
                    pros.Add("Environmentally friendly");
                    pros.Add("Good for health and fitness");
                    pros.Add("Can be faster than walking or driving in congested areas");
                    pros.Add("No parking hassles");
                    break;

                case TransportMode.Bus:
                    pros.Add("Affordable");
                    pros.Add("No need to worry about parking");
                    pros.Add("Can use travel time for reading or other activities");
                    pros.Add("More environmentally friendly than driving alone");
                    pros.Add("Reduces traffic congestion");
                    break;

                case TransportMode.Car:
                    pros.Add("Convenient and flexible");
                    pros.Add("Direct door-to-door travel");
                    pros.Add("Can carry luggage and other items easily");
                    pros.Add("Comfortable in any weather");
                    pros.Add("Can make stops along the way");
                    break;

                case TransportMode.Train:
                    pros.Add("Can use travel time productively");
                    pros.Add("More comfortable than bus for longer journeys");
                    pros.Add("More environmentally friendly than driving or flying");
                    pros.Add("No traffic congestion");
                    pros.Add("Often has amenities like WiFi and food service");
                    break;

                case TransportMode.Plane:
                    pros.Add("Fastest option for long distances");
                    pros.Add("Most practical for very long journeys");
                    pros.Add("Can cover vast distances in a relatively short time");
                    pros.Add("Often has in-flight entertainment");
                    pros.Add("Maximizes time at destination");
                    break;
            }

            return pros;
        }

        private List<string> GenerateCons(TransportMode mode, int durationMinutes, double distanceKm)
        {
            var cons = new List<string>();

            switch (mode)
            {
                case TransportMode.Walk:
                    cons.Add("Slow for anything but short distances");
                    cons.Add("Tiring for longer distances");
                    cons.Add("Weather dependent");
                    cons.Add("Difficult with heavy luggage");
                    cons.Add("Limited carrying capacity");
                    break;

                case TransportMode.Bike:
                    cons.Add("Weather dependent");
                    cons.Add("Requires physical exertion");
                    cons.Add("Limited carrying capacity");
                    cons.Add("Safety concerns in heavy traffic");
                    cons.Add("Requires secure bike parking at destination");
                    break;

                case TransportMode.Bus:
                    cons.Add("Fixed schedule and routes");
                    cons.Add("Can be crowded during peak hours");
                    cons.Add("Multiple stops may extend travel time");
                    cons.Add("May require transfers");
                    cons.Add("Limited space for luggage");
                    break;

                case TransportMode.Car:
                    cons.Add("Parking can be expensive or difficult to find");
                    cons.Add("Traffic congestion can extend travel time");
                    cons.Add("Higher environmental impact");
                    cons.Add("Gas and maintenance costs");
                    cons.Add("Driver cannot rest or work during journey");
                    break;

                case TransportMode.Train:
                    cons.Add("Fixed schedule and limited routes");
                    cons.Add("May not go directly to your destination");
                    cons.Add("Can be expensive for short notice booking");
                    cons.Add("May require getting to/from stations");
                    cons.Add("Potential for delays");
                    break;

                case TransportMode.Plane:
                    cons.Add("High environmental impact");
                    cons.Add("Expensive for short distances");
                    cons.Add("Airport security and check-in procedures add time");
                    cons.Add("Airports often located far from city centers");
                    cons.Add("Restrictions on luggage");
                    cons.Add("Weather and technical issues can cause delays");
                    break;
            }

            return cons;
        }

        private int CalculatePreferenceMatchScore(TravelRecommendation recommendation, TravelQuery query)
        {
            int score = 70; // Start with a baseline score

            // If user specified a priority, adjust score accordingly
            if (!string.IsNullOrWhiteSpace(query.Preferences.Priority))
            {
                var priority = query.Preferences.Priority.ToLower();

                // Adjust for "faster" priority
                if (priority.Contains("fast") || priority.Contains("quick") || priority.Contains("time"))
                {
                    // Higher score for faster modes (plane for long distances, car for medium, bike/walk for very short)
                    if (recommendation.Mode == TransportMode.Plane && recommendation.DistanceKm > 300)
                        score += 20;
                    else if (recommendation.Mode == TransportMode.Car && recommendation.DistanceKm is > 10 and <= 300)
                        score += 15;
                    else if ((recommendation.Mode == TransportMode.Bike || recommendation.Mode == TransportMode.Walk) && recommendation.DistanceKm <= 5)
                        score += 10;
                    else
                        score -= 10; // Penalize slower modes
                }

                // Adjust for "cheaper" priority
                else if (priority.Contains("cheap") || priority.Contains("cost") || priority.Contains("inexpensive") || priority.Contains("affordable"))
                {
                    // Higher score for cheaper modes
                    if (recommendation.Mode == TransportMode.Walk || recommendation.Mode == TransportMode.Bike)
                        score += 20; // Free modes
                    else if (recommendation.Mode == TransportMode.Bus)
                        score += 15; // Public transport is usually affordable
                    else if (recommendation.Mode == TransportMode.Car && recommendation.DistanceKm <= 100)
                        score += 10; // Car can be cost-effective for shorter trips with multiple people
                    else if (recommendation.Mode == TransportMode.Plane)
                        score -= 15; // Planes are usually expensive
                }

                // Adjust for "environmental" or "green" priority
                else if (priority.Contains("environment") || priority.Contains("green") || priority.Contains("eco"))
                {
                    // Higher score for more environmentally friendly modes
                    score += (recommendation.EnvironmentalScore - 50) / 5; // Up to +10 for most green, down to -8 for least green
                }

                // Adjust for "comfortable" priority
                else if (priority.Contains("comfort") || priority.Contains("convenient") || priority.Contains("easy"))
                {
                    // Higher score for more comfortable modes for the distance
                    if (recommendation.Mode == TransportMode.Car)
                        score += 15; // Cars are generally comfortable
                    else if (recommendation.Mode == TransportMode.Train && recommendation.DistanceKm > 50)
                        score += 10; // Trains are comfortable for longer journeys
                    else if (recommendation.Mode == TransportMode.Plane && recommendation.DistanceKm > 500)
                        score += 5; // Planes can be comfortable for very long distances
                    else if (recommendation.Mode == TransportMode.Walk && recommendation.DistanceKm > 3)
                        score -= 15; // Walking long distances isn't comfortable
                }
            }

            // Adjust for distance preferences
            if (recommendation.Mode == TransportMode.Walk && query.Preferences.MaxWalkingDistance.HasValue)
            {
                if (recommendation.DistanceKm > query.Preferences.MaxWalkingDistance.Value)
                    score -= 30; // Severely penalize if exceeding max walking distance
            }

            if (recommendation.Mode == TransportMode.Bike && query.Preferences.MaxBikingDistance.HasValue)
            {
                if (recommendation.DistanceKm > query.Preferences.MaxBikingDistance.Value)
                    score -= 30; // Severely penalize if exceeding max biking distance
            }

            // Adjust for travel time preferences
            if (query.Preferences.MaxTravelTime.HasValue)
            {
                if (recommendation.DurationMinutes > query.Preferences.MaxTravelTime.Value)
                    score -= 20; // Penalize if exceeding max travel time
            }

            // Adjust for cost preferences
            if (query.Preferences.MaxCost.HasValue && recommendation.EstimatedCost.HasValue)
            {
                if (recommendation.EstimatedCost.Value > query.Preferences.MaxCost.Value)
                    score -= 25; // Penalize if exceeding max cost
            }

            // Cap the score between 0 and 100
            return Math.Clamp(score, 0, 100);
        }

        private int CalculateOverallScore(TravelRecommendation recommendation)
        {
            // Calculate a weighted average of the different scores
            // Weighting depends on what we consider most important

            // Weight for each score component (totals to 100%)
            const double environmentalWeight = 0.25;
            const double convenienceWeight = 0.35;
            const double preferenceWeight = 0.40;

            // Calculate the weighted score
            double weightedScore =
                (recommendation.EnvironmentalScore * environmentalWeight) +
                (recommendation.ConvenienceScore * convenienceWeight) +
                (recommendation.PreferenceMatchScore * preferenceWeight);

            return (int)Math.Round(weightedScore);
        }

        #endregion

        /// <summary>
        /// Helper method to extract content from a ChatResponse
        /// </summary>
        private string? GetContentFromResponse(ChatResponse response)
        {
            try
            {
                // Check if the response has any messages
                if (response.Messages.Count == 0)
                {
                    _logger.LogWarning("Response contains no messages");
                    return null;
                }

                // Get the last message (usually the assistant's response)
                var lastMessage = response.Messages[response.Messages.Count - 1];
                string? textContent = lastMessage.Text;

                // If the content is null or empty, return null
                if (string.IsNullOrEmpty(textContent))
                {
                    _logger.LogWarning("Response message has empty text content");
                    return null;
                }

                // Check if the content appears to be JSON (function call format)
                if (textContent.TrimStart().StartsWith("{") && textContent.TrimEnd().EndsWith("}"))
                {
                    _logger.LogInformation("Response appears to be in JSON format, attempting to parse");

                    try
                    {
                        // Try to parse the JSON
                        using var jsonDoc = JsonDocument.Parse(textContent);
                        var root = jsonDoc.RootElement;

                        // Check if this is a function call format (has "name" and "arguments" fields)
                        if (root.TryGetProperty("name", out var nameElement))
                        {
                            string functionName = nameElement.GetString() ?? "";
                            _logger.LogInformation($"Detected function call format with function name: {functionName}");

                            // Handle different function types
                            return HandleFunctionCall(functionName, root);
                        }
                    }
                    catch (JsonException jsonEx)
                    {
                        // If JSON parsing fails, log the error but continue with the original text
                        _logger.LogWarning(jsonEx, "Failed to parse response as JSON, will use original text");
                    }
                }

                // Clean up the text content by removing markdown code blocks if present
                textContent = CleanMarkdownCodeBlocks(textContent);

                // Replace verbose function call messages with minimal emoji
                return ReplaceVerboseFunctionCallMessages(textContent);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error extracting content from response");
                return null;
            }
        }

        /// <summary>
        /// Handles function call format responses by converting them to natural language
        /// </summary>
        private string HandleFunctionCall(string functionName, JsonElement root)
        {
            try
            {
                // Extract arguments if available
                JsonElement arguments = root.TryGetProperty("arguments", out var args) ? args : default;

                // Handle different function types
                switch (functionName)
                {
                    case "getTravelSafetyTips":
                        return GenerateTravelSafetyTips(arguments);

                    case "getWeatherInfo":
                        return "When traveling at night, check the weather forecast before departing as conditions can change and affect visibility and road safety.";

                    case "getTrafficInfo":
                        return "Traffic conditions are typically lighter at night, but be aware that some roads may be closed for maintenance during late hours.";

                    default:
                        // For unknown functions, provide a generic response
                        return $"I found some information about {functionName}, but I need to present it in a more readable format. Please ask a more specific question.";
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, $"Error handling function call {functionName}");
                return "I apologize, but I encountered an error processing your question. Could you please rephrase it?";
            }
        }

        /// <summary>
        /// Generates travel safety tips for night travel
        /// </summary>
        private string GenerateTravelSafetyTips(JsonElement arguments)
        {
            // Create a comprehensive response about night travel safety
            return @"If you're making this trip at night, here are some important considerations:

1. Visibility: Nighttime reduces visibility significantly. If biking, ensure you have proper front and rear lights, reflective clothing, and consider routes with good street lighting.

2. Safety: Some areas may be less safe at night. Stick to well-lit, populated routes when possible.

3. Public Transportation: Bus schedules often reduce frequency at night, so check the schedule beforehand to avoid long waits.

4. Driving Considerations:
   - Be extra cautious of wildlife that may be more active at night
   - Reduce your speed slightly to account for reduced visibility
   - Ensure your headlights are working properly
   - Be aware of drowsiness if driving late

5. Ride-sharing options like Uber or Lyft might be good alternatives if you're concerned about night travel.

6. Weather: Night temperatures can drop significantly, so dress appropriately if walking or biking.

7. Phone battery: Ensure your phone is fully charged for emergencies.

Would you like more specific information about any of these considerations?";
        }

        /// <summary>
        /// Replaces verbose function call messages with minimal emoji
        /// </summary>
        private string ReplaceVerboseFunctionCallMessages(string text)
        {
            if (string.IsNullOrEmpty(text))
                return text;

            // Pattern for "no function calls were necessary" message
            if (text.Contains("No function calls were necessary") ||
                text.Contains("all information provided was internally generated"))
            {
                // Remove the entire note
                text = System.Text.RegularExpressions.Regex.Replace(
                    text,
                    @"\s*\(Note:.*?function calls.*?data\.\)\s*$",
                    " 🧠");
            }
            // Pattern for "function calls were used" message
            else if (text.Contains("function call") ||
                     text.Contains("tool was used"))
            {
                // Remove the entire note
                text = System.Text.RegularExpressions.Regex.Replace(
                    text,
                    @"\s*\(Note:.*?function.*?used.*?\)\s*$",
                    " 🛠️");
            }

            return text;
        }

        /// <summary>
        /// Cleans markdown code blocks from text
        /// </summary>
        private string CleanMarkdownCodeBlocks(string text)
        {
            // Check if the text contains markdown code blocks
            if (text.Contains("```"))
            {
                try
                {
                    // Remove code block markers and any language specifier
                    var lines = text.Split('\n');
                    var cleanedLines = new List<string>();
                    bool insideCodeBlock = false;

                    foreach (var line in lines)
                    {
                        if (line.Trim().StartsWith("```"))
                        {
                            insideCodeBlock = !insideCodeBlock;
                            // Skip the code block marker line
                            continue;
                        }

                        // Skip language specifier line if it's right after opening code block
                        if (insideCodeBlock && line.Trim() == "json")
                        {
                            continue;
                        }

                        cleanedLines.Add(line);
                    }

                    return string.Join("\n", cleanedLines).Trim();
                }
                catch (Exception ex)
                {
                    _logger.LogWarning(ex, "Error cleaning markdown code blocks, returning original text");
                    return text;
                }
            }

            return text;
        }
    }
}
