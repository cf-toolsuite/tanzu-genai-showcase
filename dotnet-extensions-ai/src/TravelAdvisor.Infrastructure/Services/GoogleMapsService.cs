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
    /// Implementation of IGoogleMapsService using Google Maps API
    /// </summary>
    public class GoogleMapsService : IGoogleMapsService
    {
        private readonly ILogger<GoogleMapsService> _logger;
        private readonly GoogleMapsOptions _options;
        private readonly HttpClient _httpClient;
        private const string BaseUrl = "https://maps.googleapis.com/maps/api";

        public GoogleMapsService(
            IOptions<GoogleMapsOptions> options,
            ILogger<GoogleMapsService> logger,
            HttpClient? httpClient = null)
        {
            _options = options.Value ?? throw new ArgumentNullException(nameof(options));
            _logger = logger ?? throw new ArgumentNullException(nameof(logger));
            _httpClient = httpClient ?? new HttpClient();
        }

        /// <inheritdoc />
        public async Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
            string origin,
            string destination,
            TransportMode mode)
        {
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

                // Verify we're not using a dummy key
                if (string.IsNullOrEmpty(_options.ApiKey) || _options.ApiKey.StartsWith("dummy"))
                {
                    _logger.LogError("Google Maps API key is empty or using a dummy value!");
                    throw new Exception("Google Maps API key is not configured properly. Please set a valid API key in configuration.");
                }

                // Build the request URL
                string requestUrl = $"{BaseUrl}/directions/json?origin={Uri.EscapeDataString(origin)}" +
                    $"&destination={Uri.EscapeDataString(destination)}" +
                    $"&mode={googleMode}" +
                    $"&key={_options.ApiKey}";

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
                    // Throw an exception to inform the calling code that the API request failed
                    throw new Exception($"Google Maps API request failed with status: {response?.Status}. Please check your API key configuration.");
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
                // Re-throw with more context
                throw new Exception("Failed to get directions from Google Maps API. Please check your API key and network connection.", ex);
            }
        }

        /// <inheritdoc />
        public async Task<List<TravelStep>> GetTravelStepsAsync(
            string origin,
            string destination,
            TransportMode mode)
        {
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

                // Verify we're not using a dummy key
                if (string.IsNullOrEmpty(_options.ApiKey) || _options.ApiKey.StartsWith("dummy"))
                {
                    _logger.LogError("Google Maps API key is empty or using a dummy value!");
                    throw new Exception("Google Maps API key is not configured properly. Please set a valid API key in configuration.");
                }

                // Build the request URL
                string requestUrl = $"{BaseUrl}/directions/json?origin={Uri.EscapeDataString(origin)}" +
                    $"&destination={Uri.EscapeDataString(destination)}" +
                    $"&mode={googleMode}" +
                    $"&key={_options.ApiKey}";

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
                    // Throw an exception to inform the calling code that the API request failed
                    throw new Exception($"Google Maps API request failed with status: {response?.Status}. Please check your API key configuration.");
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
                // Re-throw with more context
                throw new Exception("Failed to get travel steps from Google Maps API. Please check your API key and network connection.", ex);
            }
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
