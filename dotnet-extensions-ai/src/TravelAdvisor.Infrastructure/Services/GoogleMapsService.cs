using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Net.Http.Json;
using System.Text.Json;
using System.Text.Json.Serialization;
using System.Text.RegularExpressions;
using System.Threading.Tasks;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using TravelAdvisor.Core.Models;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Infrastructure.Options;

namespace TravelAdvisor.Infrastructure.Services
{
    /// <summary>
    /// Implementation of IMapService using Google Maps API
    /// </summary>
    public class GoogleMapsService : IMapService
    {
        private readonly ILogger<GoogleMapsService> _logger;
        private readonly GoogleMapsOptions _options;
        private readonly HttpClient _httpClient;
        private readonly bool _useMockData;
        private const string BaseUrl = "https://maps.googleapis.com/maps/api";

        public GoogleMapsService(
            IOptions<GoogleMapsOptions> options,
            ILogger<GoogleMapsService> logger,
            HttpClient? httpClient = null)
        {
            _options = options.Value ?? throw new ArgumentNullException(nameof(options));
            _logger = logger ?? throw new ArgumentNullException(nameof(logger));
            _httpClient = httpClient ?? new HttpClient();

            // Check if mock data is enabled via environment variable
            _useMockData = false;
            bool.TryParse(Environment.GetEnvironmentVariable("USE_MOCK_DATA"), out _useMockData);
        }

        /// <inheritdoc />
        public async Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
            string origin,
            string destination,
            TransportMode mode)
        {
            // If mock data is enabled, return mock data directly
            if (_useMockData)
            {
                _logger.LogInformation("Using mock data for distance calculation from {Origin} to {Destination} via {Mode}",
                                      origin, destination, mode);
                return GetMockDistanceAndDuration(origin, destination, mode);
            }

            try
            {
                _logger.LogInformation("Calculating distance from {Origin} to {Destination} via {Mode}",
                                      origin, destination, mode);

                // Convert our transport mode to Google Maps API mode
                string googleMode = ConvertToGoogleMode(mode);

                // Log the API key for debugging (first few characters)
                string keyPrefix = string.IsNullOrEmpty(_options.ApiKey)
                    ? "empty"
                    : (_options.ApiKey.Length > 5 ? _options.ApiKey.Substring(0, 5) + "..." : _options.ApiKey);
                _logger.LogInformation("Using Google Maps API key starting with: {KeyPrefix}", keyPrefix);

                // Add more detailed logging to debug the API key issue
                _logger.LogDebug("API Key length: {Length}, API Key is null or empty: {IsEmpty}",
                    _options.ApiKey?.Length ?? 0, string.IsNullOrEmpty(_options.ApiKey));

                // Verify we're not using a dummy key when mock data is disabled
                if (!_useMockData && (string.IsNullOrEmpty(_options.ApiKey) || _options.ApiKey.StartsWith("dummy")))
                {
                    _logger.LogError("Google Maps API key is empty or using a dummy value but USE_MOCK_DATA is false!");
                }

                // Build the request URL - don't use dummy API keys when mock data is disabled
                string requestUrl;
                if (!_useMockData && (string.IsNullOrEmpty(_options.ApiKey) || _options.ApiKey.StartsWith("dummy")))
                {
                    throw new Exception("Google Maps API key is not configured properly. Please set a valid API key in configuration.");
                }
                else
                {
                    requestUrl = $"{BaseUrl}/directions/json?origin={Uri.EscapeDataString(origin)}" +
                        $"&destination={Uri.EscapeDataString(destination)}" +
                        $"&mode={googleMode}" +
                        $"&key={_options.ApiKey}";
                }

                _logger.LogDebug("Google Maps API request URL: {RequestUrl}",
                    requestUrl.Replace(_options.ApiKey ?? "", "[API_KEY]")); // Log URL without exposing full API key

                // Make the request with explicit HttpRequestMessage to ensure headers are set correctly
                var request = new HttpRequestMessage(HttpMethod.Get, requestUrl);

                // Add standard headers that might help with authorization
                request.Headers.Add("Accept", "application/json");
                request.Headers.Add("User-Agent", "TravelAdvisorBot/1.0");

                // Execute the request
                var httpResponse = await _httpClient.SendAsync(request);
                httpResponse.EnsureSuccessStatusCode(); // This will throw if HTTP status is not 2xx

                // Deserialize the response
                var responseContent = await httpResponse.Content.ReadAsStringAsync();
                _logger.LogDebug("Google Maps API response: {Response}", responseContent);

                var response = JsonSerializer.Deserialize<DirectionsResponse>(responseContent,
                    new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

                if (response == null ||
                    response.Status != "OK" ||
                    response.Routes.Count == 0 ||
                    response.Routes[0].Legs.Count == 0)
                {
                    _logger.LogWarning("Failed to get directions. Status: {Status}", response?.Status);

                    // Only use mock data if explicitly allowed
                    if (_useMockData)
                    {
                        return GetMockDistanceAndDuration(origin, destination, mode);
                    }
                    else
                    {
                        // Throw an exception to inform the calling code that the API request failed
                        throw new Exception($"Google Maps API request failed with status: {response?.Status}. Please check your API key configuration.");
                    }
                }

                // Extract the information we need
                var leg = response.Routes[0].Legs[0];
                double distanceKm = leg.Distance.Value / 1000.0; // Convert meters to km
                int durationMinutes = (int)Math.Ceiling(leg.Duration.Value / 60.0); // Convert seconds to minutes

                return (distanceKm, durationMinutes);
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error calculating distance and duration");

                // Only use mock data if explicitly allowed
                if (_useMockData)
                {
                    _logger.LogInformation("Falling back to mock data due to error");
                    return GetMockDistanceAndDuration(origin, destination, mode);
                }
                else
                {
                    // Re-throw with more context
                    throw new Exception("Failed to get directions from Google Maps API. Please check your API key and network connection.", ex);
                }
            }
        }

        /// <summary>
        /// Provides realistic mock distance and duration data for the given origin and destination
        /// </summary>
        private (double distanceKm, int durationMinutes) GetMockDistanceAndDuration(
            string origin, string destination, TransportMode mode)
        {
            // For Mill Creek, WA to Ballard, WA - approximately 35km and realistic travel times
            if ((origin.Contains("Mill Creek", StringComparison.OrdinalIgnoreCase) &&
                 destination.Contains("Ballard", StringComparison.OrdinalIgnoreCase)) ||
                (destination.Contains("Mill Creek", StringComparison.OrdinalIgnoreCase) &&
                 origin.Contains("Ballard", StringComparison.OrdinalIgnoreCase)))
            {
                double distanceKm = 35.0;
                int durationMinutes = mode switch
                {
                    TransportMode.Walk => 420,     // 7 hours
                    TransportMode.Bike => 130,     // ~2.2 hours
                    TransportMode.Bus => 110,      // ~1.8 hours with transfers
                    TransportMode.Car => 50,       // ~50 minutes by car
                    TransportMode.Train => 90,     // ~1.5 hours including transit to/from stations
                    TransportMode.Plane => 0,      // Not applicable for this distance
                    _ => 50                        // Default to car time
                };

                return (distanceKm, durationMinutes);
            }

            // For other origins/destinations, use the estimation method
            return EstimateDistanceAndDuration(origin, destination, mode);
        }

        /// <inheritdoc />
        public async Task<List<TravelStep>> GetTravelStepsAsync(
            string origin,
            string destination,
            TransportMode mode)
        {
            // If mock data is enabled, return mock data directly
            if (_useMockData)
            {
                _logger.LogInformation("Using mock data for travel steps from {Origin} to {Destination} via {Mode}",
                                      origin, destination, mode);
                return GetMockTravelSteps(origin, destination, mode);
            }

            try
            {
                _logger.LogInformation("Getting travel steps from {Origin} to {Destination} via {Mode}",
                                      origin, destination, mode);

                // Convert our transport mode to Google Maps API mode
                string googleMode = ConvertToGoogleMode(mode);

                // Log the API key for debugging (first few characters)
                string keyPrefix = string.IsNullOrEmpty(_options.ApiKey)
                    ? "empty"
                    : (_options.ApiKey.Length > 5 ? _options.ApiKey.Substring(0, 5) + "..." : _options.ApiKey);
                _logger.LogInformation("Using Google Maps API key starting with: {KeyPrefix}", keyPrefix);

                // Add more detailed logging to debug the API key issue
                _logger.LogDebug("API Key length: {Length}, API Key is null or empty: {IsEmpty}",
                    _options.ApiKey?.Length ?? 0, string.IsNullOrEmpty(_options.ApiKey));

                // Verify we're not using a dummy key when mock data is disabled
                if (!_useMockData && (string.IsNullOrEmpty(_options.ApiKey) || _options.ApiKey.StartsWith("dummy")))
                {
                    _logger.LogError("Google Maps API key is empty or using a dummy value but USE_MOCK_DATA is false!");
                }

                // Build the request URL - don't use dummy API keys when mock data is disabled
                string requestUrl;
                if (!_useMockData && (string.IsNullOrEmpty(_options.ApiKey) || _options.ApiKey.StartsWith("dummy")))
                {
                    throw new Exception("Google Maps API key is not configured properly. Please set a valid API key in configuration.");
                }
                else
                {
                    requestUrl = $"{BaseUrl}/directions/json?origin={Uri.EscapeDataString(origin)}" +
                        $"&destination={Uri.EscapeDataString(destination)}" +
                        $"&mode={googleMode}" +
                        $"&key={_options.ApiKey}";
                }

                _logger.LogDebug("Google Maps API request URL: {RequestUrl}",
                    requestUrl.Replace(_options.ApiKey ?? "", "[API_KEY]")); // Log URL without exposing full API key

                // Make the request with explicit HttpRequestMessage to ensure headers are set correctly
                var request = new HttpRequestMessage(HttpMethod.Get, requestUrl);

                // Add standard headers that might help with authorization
                request.Headers.Add("Accept", "application/json");
                request.Headers.Add("User-Agent", "TravelAdvisorBot/1.0");

                // Execute the request
                var httpResponse = await _httpClient.SendAsync(request);
                httpResponse.EnsureSuccessStatusCode(); // This will throw if HTTP status is not 2xx

                // Deserialize the response
                var responseContent = await httpResponse.Content.ReadAsStringAsync();
                _logger.LogDebug("Google Maps API response: {Response}", responseContent);

                var response = JsonSerializer.Deserialize<DirectionsResponse>(responseContent,
                    new JsonSerializerOptions { PropertyNameCaseInsensitive = true });

                if (response == null ||
                    response.Status != "OK" ||
                    response.Routes.Count == 0 ||
                    response.Routes[0].Legs.Count == 0)
                {
                    _logger.LogWarning("Failed to get directions. Status: {Status}", response?.Status);

                    // Only use mock data if explicitly allowed
                    if (_useMockData)
                    {
                        return GetMockTravelSteps(origin, destination, mode);
                    }
                    else
                    {
                        // Throw an exception to inform the calling code that the API request failed
                        throw new Exception($"Google Maps API request failed with status: {response?.Status}. Please check your API key configuration.");
                    }
                }

                // Extract the steps
                var steps = new List<TravelStep>();
                var leg = response.Routes[0].Legs[0];

                foreach (var googleStep in leg.Steps)
                {
                    var step = new TravelStep
                    {
                        // Use Regex.Replace instead of string.Replace for pattern matching
                        Description = googleStep.HtmlInstructions != null 
                            ? Regex.Replace(googleStep.HtmlInstructions, "<[^>]+>", "") 
                            : string.Empty,
                        Mode = ParseGoogleMode(googleStep.TravelMode),
                        DurationMinutes = (int)Math.Ceiling(googleStep.Duration.Value / 60.0), // Convert seconds to minutes
                        DistanceKm = googleStep.Distance.Value / 1000.0 // Convert meters to km
                    };

                    steps.Add(step);
                }

                return steps;
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error getting travel steps");

                // Only use mock data if explicitly allowed
                if (_useMockData)
                {
                    _logger.LogInformation("Falling back to mock data due to error");
                    return GetMockTravelSteps(origin, destination, mode);
                }
                else
                {
                    // Re-throw with more context
                    throw new Exception("Failed to get travel steps from Google Maps API. Please check your API key and network connection.", ex);
                }
            }
        }

        /// <summary>
        /// Provides realistic mock travel steps for the given origin and destination
        /// </summary>
        private List<TravelStep> GetMockTravelSteps(string origin, string destination, TransportMode mode)
        {
            // For Mill Creek, WA to Ballard, WA - create realistic steps based on mode
            if ((origin.Contains("Mill Creek", StringComparison.OrdinalIgnoreCase) &&
                 destination.Contains("Ballard", StringComparison.OrdinalIgnoreCase)) ||
                (destination.Contains("Mill Creek", StringComparison.OrdinalIgnoreCase) &&
                 origin.Contains("Ballard", StringComparison.OrdinalIgnoreCase)))
            {
                var (distanceKm, durationMinutes) = GetMockDistanceAndDuration(origin, destination, mode);
                var steps = new List<TravelStep>();

                switch (mode)
                {
                    case TransportMode.Car:
                        steps.Add(new TravelStep
                        {
                            Description = $"Head south on WA-527 S from Mill Creek",
                            Mode = mode,
                            DurationMinutes = 10,
                            DistanceKm = 7.5
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Take I-405 S and I-5 S to 15th Ave NW in Seattle",
                            Mode = mode,
                            DurationMinutes = 25,
                            DistanceKm = 20.0
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Follow 15th Ave NW to Ballard",
                            Mode = mode,
                            DurationMinutes = 15,
                            DistanceKm = 7.5
                        });
                        break;

                    case TransportMode.Bus:
                        steps.Add(new TravelStep
                        {
                            Description = $"Take Bus 105 from Mill Creek Park & Ride",
                            Mode = mode,
                            DurationMinutes = 35,
                            DistanceKm = 15.0
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Transfer to Bus 512 at Lynnwood Transit Center",
                            Mode = mode,
                            DurationMinutes = 10,
                            DistanceKm = 0.2
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Take Bus 512 to Northgate Station",
                            Mode = mode,
                            DurationMinutes = 25,
                            DistanceKm = 12.0
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Transfer to Bus 40 to Ballard",
                            Mode = mode,
                            DurationMinutes = 40,
                            DistanceKm = 7.8
                        });
                        break;

                    case TransportMode.Bike:
                        steps.Add(new TravelStep
                        {
                            Description = $"Take Interurban Trail south from Mill Creek",
                            Mode = mode,
                            DurationMinutes = 45,
                            DistanceKm = 12.5
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Continue on Burke-Gilman Trail west",
                            Mode = mode,
                            DurationMinutes = 50,
                            DistanceKm = 15.0
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Take 8th Ave NW north to Ballard",
                            Mode = mode,
                            DurationMinutes = 35,
                            DistanceKm = 7.5
                        });
                        break;

                    case TransportMode.Walk:
                        steps.Add(new TravelStep
                        {
                            Description = $"Walk south on Bothell-Everett Highway",
                            Mode = mode,
                            DurationMinutes = 120,
                            DistanceKm = 10.0
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Continue on Lake City Way NE",
                            Mode = mode,
                            DurationMinutes = 150,
                            DistanceKm = 12.5
                        });
                        steps.Add(new TravelStep
                        {
                            Description = $"Follow N 45th St west to Ballard",
                            Mode = mode,
                            DurationMinutes = 150,
                            DistanceKm = 12.5
                        });
                        break;

                    default:
                        // For other modes or if we don't have specific steps, use the simulation method
                        return SimulateTravelSteps(origin, destination, mode);
                }

                return steps;
            }

            // For other cases, use the simulation method
            return SimulateTravelSteps(origin, destination, mode);
        }

        /// <inheritdoc />
        public bool IsModeReasonable(double distanceKm, TransportMode mode)
        {
            return mode switch
            {
                TransportMode.Walk => distanceKm <= 10, // Walking reasonable for up to 10 km
                TransportMode.Bike => distanceKm <= 30, // Biking reasonable for up to 30 km
                TransportMode.Bus => distanceKm <= 100, // Bus reasonable for up to 100 km
                TransportMode.Car => distanceKm <= 800, // Car reasonable for up to 800 km
                TransportMode.Train => distanceKm is > 30 and <= 1500, // Train reasonable for 30-1500 km
                TransportMode.Plane => distanceKm > 300, // Plane reasonable for over 300 km
                _ => true
            };
        }

        #region Fallback Methods

        private (double distanceKm, int durationMinutes) EstimateDistanceAndDuration(
            string origin, string destination, TransportMode mode)
        {
            // This is a simplistic distance estimation for when the API call fails

            // Just return a reasonable value based on string length for testing
            double distanceKm = (origin.Length + destination.Length) * 0.5;
            int durationMinutes = EstimateDuration(distanceKm, mode);

            return (distanceKm, durationMinutes);
        }

        private int EstimateDuration(double distanceKm, TransportMode mode)
        {
            // Simplified estimation based on mode and distance

            return mode switch
            {
                TransportMode.Walk => (int)(distanceKm * 12), // Approx. 5 km/h = 12 min/km
                TransportMode.Bike => (int)(distanceKm * 4),  // Approx. 15 km/h = 4 min/km
                TransportMode.Bus => (int)(distanceKm * 2),   // Approx. 30 km/h = 2 min/km
                TransportMode.Car => (int)(distanceKm * 0.8), // Approx. 75 km/h = 0.8 min/km
                TransportMode.Train => (int)(distanceKm * 0.6), // Approx. 100 km/h = 0.6 min/km
                TransportMode.Plane => (int)(distanceKm * 0.2) + 120, // Approx. 300 km/h + 2h for airport procedures
                _ => (int)(distanceKm * 1) // Default to 1 min/km
            };
        }

        private List<TravelStep> SimulateTravelSteps(string origin, string destination, TransportMode mode)
        {
            // Create simulated travel steps when API call fails

            var (distanceKm, durationMinutes) = EstimateDistanceAndDuration(origin, destination, mode);

            // Split into 3 logical steps
            double stepDistance = distanceKm / 3;
            int stepDuration = durationMinutes / 3;

            var steps = new List<TravelStep>
            {
                new TravelStep
                {
                    Description = $"Start from {origin}",
                    Mode = mode,
                    DurationMinutes = stepDuration,
                    DistanceKm = stepDistance
                },
                new TravelStep
                {
                    Description = $"Continue towards {destination}",
                    Mode = mode,
                    DurationMinutes = stepDuration,
                    DistanceKm = stepDistance
                },
                new TravelStep
                {
                    Description = $"Arrive at {destination}",
                    Mode = mode,
                    DurationMinutes = stepDuration,
                    DistanceKm = stepDistance
                }
            };

            return steps;
        }

        #endregion

        #region Helper Methods

        private string ConvertToGoogleMode(TransportMode mode)
        {
            return mode switch
            {
                TransportMode.Walk => "walking",
                TransportMode.Bike => "bicycling",
                TransportMode.Bus => "transit",
                TransportMode.Car => "driving",
                TransportMode.Train => "transit",
                TransportMode.Plane => "transit", // There's no direct 'plane' mode in Google Maps API
                _ => "driving" // Default to driving
            };
        }

        private TransportMode ParseGoogleMode(string googleMode)
        {
            return googleMode?.ToLower() switch
            {
                "walking" => TransportMode.Walk,
                "bicycling" => TransportMode.Bike,
                "transit" => TransportMode.Bus, // Could be bus or train, we default to bus
                "driving" => TransportMode.Car,
                _ => TransportMode.Car // Default to car
            };
        }

        #endregion
    }

    #region Google Maps API Response Models

    public class DirectionsResponse
    {
        [JsonPropertyName("status")]
        public string Status { get; set; } = string.Empty;

        [JsonPropertyName("error_message")]
        public string? ErrorMessage { get; set; }

        [JsonPropertyName("routes")]
        public List<Route> Routes { get; set; } = new List<Route>();
    }

    public class Route
    {
        [JsonPropertyName("legs")]
        public List<Leg> Legs { get; set; } = new List<Leg>();
    }

    public class Leg
    {
        [JsonPropertyName("distance")]
        public ValueText Distance { get; set; } = new ValueText();

        [JsonPropertyName("duration")]
        public ValueText Duration { get; set; } = new ValueText();

        [JsonPropertyName("steps")]
        public List<Step> Steps { get; set; } = new List<Step>();
    }

    public class Step
    {
        [JsonPropertyName("distance")]
        public ValueText Distance { get; set; } = new ValueText();

        [JsonPropertyName("duration")]
        public ValueText Duration { get; set; } = new ValueText();

        [JsonPropertyName("html_instructions")]
        public string HtmlInstructions { get; set; } = string.Empty;

        [JsonPropertyName("travel_mode")]
        public string TravelMode { get; set; } = string.Empty;
    }

    public class ValueText
    {
        [JsonPropertyName("value")]
        public double Value { get; set; }

        [JsonPropertyName("text")]
        public string Text { get; set; } = string.Empty;
    }

    #endregion
}