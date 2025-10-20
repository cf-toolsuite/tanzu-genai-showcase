namespace TravelAdvisor.Infrastructure.Options;

/// <summary>
/// Configuration options for Google Maps API
/// </summary>
public class GoogleMapsOptions
{
    /// <summary>
    /// Google Maps API key
    /// </summary>
    public string ApiKey { get; set; } = string.Empty;
}
