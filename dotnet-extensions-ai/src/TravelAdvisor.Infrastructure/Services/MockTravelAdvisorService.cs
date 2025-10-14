using System.Text.Json;
using System.Text.RegularExpressions;
using Microsoft.Extensions.Logging;
using TravelAdvisor.Core.Models;
using TravelAdvisor.Core.Services;

namespace TravelAdvisor.Infrastructure.Services;

/// <summary>
/// Mock implementation of ITravelAdvisorService for development/testing
/// </summary>
public class MockTravelAdvisorService(
    IMapService mapService,
    ILogger<MockTravelAdvisorService> logger)
    : ITravelAdvisorService
{
    private readonly ILogger<MockTravelAdvisorService> _logger = logger ?? throw new ArgumentNullException(nameof(logger));
    private readonly IMapService _mapService = mapService ?? throw new ArgumentNullException(nameof(mapService));
    private static JsonSerializerOptions _serializerOptions = new() { PropertyNameCaseInsensitive = true };

    /// <inheritdoc />
    public Task<TravelQuery> ProcessNaturalLanguageQueryAsync(string query)
    {
        try
        {
            _logger.LogInformation("Processing natural language query with mock service: {Query}", query);

            // Provide a substantial mock response with example travel data
            const string jsonResponse = """
                                        {
                                          "Origin": "Mill Creek, WA",
                                          "Destination": "Ballard, WA",
                                          "TravelTime": {
                                            "DepartureTime": null,
                                            "ArrivalTime": null,
                                            "IsFlexible": true
                                          },
                                          "Preferences": {
                                            "Priority": "faster",
                                            "ConsiderWalking": true,
                                            "ConsiderBiking": true,
                                            "ConsiderPublicTransport": true,
                                            "ConsiderDriving": true,
                                            "ConsiderTrain": true,
                                            "ConsiderFlying": false,
                                            "MaxWalkingDistance": null,
                                            "MaxBikingDistance": null,
                                            "MaxTravelTime": null,
                                            "MaxCost": null
                                          },
                                          "AdditionalContext": "Travel from Mill Creek to Ballard"
                                        }
                                        """;

            try
            {
                var travelQuery = JsonSerializer.Deserialize<TravelQuery>(jsonResponse, _serializerOptions);

                if (travelQuery == null)
                {
                    _logger.LogWarning("Failed to parse mock travel query from response");
                    var fallbackQuery = ExtractQueryFromText(query) ?? CreateDefaultQuery(query);
                    return Task.FromResult(fallbackQuery);
                }

                // If we can extract something from the actual query text, use it
                var extractedQuery = ExtractQueryFromText(query);
                if (extractedQuery != null)
                {
                    travelQuery.Origin = extractedQuery.Origin;
                    travelQuery.Destination = extractedQuery.Destination;
                    travelQuery.AdditionalContext = query;
                }

                return Task.FromResult(travelQuery);
            }
            catch (JsonException ex)
            {
                _logger.LogError(ex, "Error parsing mock JSON response");
                var fallbackQuery = ExtractQueryFromText(query) ?? CreateDefaultQuery(query);
                return Task.FromResult(fallbackQuery);
            }
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error processing natural language query with mock service");
            return Task.FromResult(CreateDefaultQuery(query));
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
            if (recommendations.Count != 0)
            {
                recommendations[0].IsRecommended = true;
            }

            return recommendations;
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error generating recommendations with mock service");
            return [];
        }
    }

    /// <inheritdoc />
    public Task<string> GenerateExplanationAsync(
        TravelRecommendation recommendation,
        TravelQuery query)
    {
        try
        {
            _logger.LogInformation("Generating mock explanation for {Mode} recommendation from {Origin} to {Destination}",
                recommendation.Mode, query.Origin, query.Destination);

            // Generate a standard explanation based on mode
            string explanation = recommendation.Mode switch
            {
                TransportMode.Walk => $"Walking from {query.Origin} to {query.Destination} is a great option for this distance " +
                                      $"of {recommendation.DistanceKm:F1} km. It will take approximately {recommendation.DurationMinutes} minutes, " +
                                      $"but it's healthy, environmentally friendly, and completely free. The route is straightforward and pleasant for walking.",

                TransportMode.Bike => $"Biking from {query.Origin} to {query.Destination} is an excellent choice. " +
                                      $"The {recommendation.DistanceKm:F1} km route will take about {recommendation.DurationMinutes} minutes. " +
                                      $"Biking offers a good balance of speed and flexibility while being environmentally friendly and good exercise.",

                TransportMode.Bus => $"Taking public transportation from {query.Origin} to {query.Destination} is convenient " +
                                     $"and affordable. The journey of {recommendation.DistanceKm:F1} km will take approximately {recommendation.DurationMinutes} minutes. " +
                                     $"While there may be a few transfers, you'll be able to rest or use your time productively during the trip.",

                TransportMode.Car => $"Driving from {query.Origin} to {query.Destination} is the most flexible option. " +
                                     $"The {recommendation.DistanceKm:F1} km journey will take about {recommendation.DurationMinutes} minutes. " +
                                     $"While not the most environmentally friendly choice, it offers the most convenience and direct route.",

                TransportMode.Train => $"Taking the train from {query.Origin} to {query.Destination} is comfortable " +
                                       $"for this {recommendation.DistanceKm:F1} km journey. It will take approximately {recommendation.DurationMinutes} minutes. " +
                                       $"The train offers a good balance of comfort, environmental friendliness, and the ability to use your time productively.",

                TransportMode.Plane => $"Flying from {query.Origin} to {query.Destination} is the fastest option " +
                                       $"for this long-distance journey of {recommendation.DistanceKm:F1} km. The flight itself will take about " +
                                       $"{recommendation.DurationMinutes - 120} minutes, with additional time for airport procedures. " +
                                       $"While not the most environmentally friendly option, it saves significant time over other modes.",

                _ => $"The recommended mode of transport from {query.Origin} to {query.Destination} " +
                     $"will take approximately {recommendation.DurationMinutes} minutes for the {recommendation.DistanceKm:F1} km journey."
            };

            return Task.FromResult(explanation);
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error generating explanation with mock service");
            return Task.FromResult("I'm sorry, I couldn't generate a detailed explanation for this recommendation.");
        }
    }

    /// <inheritdoc />
    public Task<string> AnswerFollowUpQuestionAsync(
        string question,
        TravelRecommendation recommendation,
        TravelQuery query)
    {
        try
        {
            _logger.LogInformation("Answering follow-up question with mock service: {Question}", question);

            // Generate responses based on keywords in the question
            if (question.Contains("time", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("long", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("duration", StringComparison.OrdinalIgnoreCase))
            {
                return Task.FromResult($"The journey from {query.Origin} to {query.Destination} by {recommendation.Mode} will take approximately {recommendation.DurationMinutes} minutes.");
            }

            if (question.Contains("cost", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("price", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("expensive", StringComparison.OrdinalIgnoreCase))
            {
                string response = recommendation.EstimatedCost.HasValue
                    ? $"The estimated cost for traveling from {query.Origin} to {query.Destination} by {recommendation.Mode} is approximately ${recommendation.EstimatedCost.Value:F2}."
                    : $"I don't have exact cost information for traveling from {query.Origin} to {query.Destination} by {recommendation.Mode}, as it can vary based on factors like fuel prices, transit fares, and other variables.";
                return Task.FromResult(response);
            }

            if (question.Contains("distance", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("far", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("miles", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("kilometers", StringComparison.OrdinalIgnoreCase))
            {
                return Task.FromResult($"The distance from {query.Origin} to {query.Destination} is approximately {recommendation.DistanceKm:F1} kilometers.");
            }

            if (question.Contains("route", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("path", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("directions", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("steps", StringComparison.OrdinalIgnoreCase))
            {
                var stepsDescription = string.Join("\n", recommendation.Steps.Select(step =>
                    $"- {step.Description} ({step.DistanceKm:F1} km, {step.DurationMinutes} mins)"));

                return Task.FromResult($"Here's the route from {query.Origin} to {query.Destination} by {recommendation.Mode}:\n{stepsDescription}");
            }

            if (question.Contains("environment", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("eco", StringComparison.OrdinalIgnoreCase) ||
                question.Contains("green", StringComparison.OrdinalIgnoreCase))
            {
                var environmentalRating = recommendation.EnvironmentalScore switch
                {
                    >= 90 => "extremely environmentally friendly",
                    >= 70 => "very environmentally friendly",
                    >= 50 => "moderately environmentally friendly",
                    >= 30 => "not very environmentally friendly",
                    _ => "one of the least environmentally friendly options"
                };

                return Task.FromResult($"Traveling by {recommendation.Mode} from {query.Origin} to {query.Destination} is {environmentalRating}. It has an environmental score of {recommendation.EnvironmentalScore}/100.");
            }

            return Task.FromResult($"I don't have specific information to answer that question about traveling from {query.Origin} to {query.Destination} by {recommendation.Mode}. Please ask about travel time, cost, distance, route directions, or environmental impact.");
        }
        catch (Exception ex)
        {
            _logger.LogError(ex, "Error answering follow-up question with mock service");
            return Task.FromResult("I'm sorry, I couldn't answer your follow-up question. Could you try rephrasing it?");
        }
    }

    #region Helper Methods

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
            var fromToMatch = Regex.Match(query,
                @"from\s+([^,\.;]+(?:,[^,\.;]+)*)\s+to\s+([^,\.;]+(?:,[^,\.;]+)*)",
                RegexOptions.IgnoreCase);

            if (fromToMatch.Success)
            {
                origin = fromToMatch.Groups[1].Value.Trim();
                destination = fromToMatch.Groups[2].Value.Trim();
            }

            // If not found, look for "between X and Y" pattern
            if (origin == "Unknown" || destination == "Unknown")
            {
                var betweenMatch = Regex.Match(query,
                    @"between\s+([^,\.;]+(?:,[^,\.;]+)*)\s+and\s+([^,\.;]+(?:,[^,\.;]+)*)",
                    RegexOptions.IgnoreCase);

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
                if (recommendation is { Mode: TransportMode.Plane, DistanceKm: > 300 })
                    score += 20;
                else if (recommendation is { Mode: TransportMode.Car, DistanceKm: > 10 and <= 300 })
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
                if (recommendation.Mode is TransportMode.Walk or TransportMode.Bike)
                    score += 20; // Free modes
                else if (recommendation.Mode == TransportMode.Bus)
                    score += 15; // Public transport is usually affordable
                else if (recommendation is { Mode: TransportMode.Car, DistanceKm: <= 100 })
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
                else if (recommendation is { Mode: TransportMode.Train, DistanceKm: > 50 })
                    score += 10; // Trains are comfortable for longer journeys
                else if (recommendation is { Mode: TransportMode.Plane, DistanceKm: > 500 })
                    score += 5; // Planes can be comfortable for very long distances
                else if (recommendation is { Mode: TransportMode.Walk, DistanceKm: > 3 })
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
}