namespace TravelAdvisor.Core.Models;

/// <summary>
/// Represents a travel recommendation for a specific transportation mode
/// </summary>
public class TravelRecommendation
{
    /// <summary>
    /// Transportation mode (walk, bike, bus, car, train, plane)
    /// </summary>
    public TransportMode Mode { get; set; }

    /// <summary>
    /// Summary of the recommendation
    /// </summary>
    public string Summary { get; set; } = string.Empty;

    /// <summary>
    /// Detailed description of the journey
    /// </summary>
    public string Description { get; set; } = string.Empty;

    /// <summary>
    /// Estimated duration of the journey in minutes
    /// </summary>
    public int DurationMinutes { get; set; }

    /// <summary>
    /// Estimated cost of the journey
    /// </summary>
    public decimal? EstimatedCost { get; set; }

    /// <summary>
    /// Distance of the journey in kilometers
    /// </summary>
    public double DistanceKm { get; set; }

    /// <summary>
    /// List of steps in the journey
    /// </summary>
    public List<TravelStep> Steps { get; set; } = [];

    /// <summary>
    /// Pros of this transportation mode
    /// </summary>
    public List<string> Pros { get; set; } = [];

    /// <summary>
    /// Cons of this transportation mode
    /// </summary>
    public List<string> Cons { get; set; } = [];

    /// <summary>
    /// Environmental impact score (0-100, higher is better/greener)
    /// </summary>
    public int EnvironmentalScore { get; set; }

    /// <summary>
    /// Convenience score (0-100, higher is more convenient)
    /// </summary>
    public int ConvenienceScore { get; set; }

    /// <summary>
    /// Score based on user preferences (0-100, higher is better match)
    /// </summary>
    public int PreferenceMatchScore { get; set; }

    /// <summary>
    /// Overall recommendation score (0-100, higher is more recommended)
    /// </summary>
    public int OverallScore { get; set; }

    /// <summary>
    /// Whether this mode is recommended as the best option
    /// </summary>
    public bool IsRecommended { get; set; }
}

/// <summary>
/// Represents a single step in a journey
/// </summary>
public class TravelStep
{
    /// <summary>
    /// Description of the step
    /// </summary>
    public string Description { get; set; } = string.Empty;

    /// <summary>
    /// Transportation mode for this step
    /// </summary>
    public TransportMode Mode { get; set; }

    /// <summary>
    /// Duration of this step in minutes
    /// </summary>
    public int DurationMinutes { get; set; }

    /// <summary>
    /// Distance of this step in kilometers
    /// </summary>
    public double DistanceKm { get; set; }
}

/// <summary>
/// Transportation modes
/// </summary>
public enum TransportMode
{
    Walk,
    Bike,
    Bus,
    Car,
    Train,
    Plane,
    Mixed
}
