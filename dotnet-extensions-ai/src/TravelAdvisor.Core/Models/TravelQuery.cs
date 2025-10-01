namespace TravelAdvisor.Core.Models;

/// <summary>
/// Represents a user query for travel recommendations
/// </summary>
public class TravelQuery
{
    /// <summary>
    /// Origin location (address, city, etc.)
    /// </summary>
    public string Origin { get; set; } = string.Empty;

    /// <summary>
    /// Destination location (address, city, etc.)
    /// </summary>
    public string Destination { get; set; } = string.Empty;

    /// <summary>
    /// When the user wants to travel
    /// </summary>
    public TravelTime TravelTime { get; set; } = new TravelTime();

    /// <summary>
    /// User preferences for the journey
    /// </summary>
    public TravelPreferences Preferences { get; set; } = new TravelPreferences();

    /// <summary>
    /// Optional additional context or requirements from the user
    /// </summary>
    public string AdditionalContext { get; set; } = string.Empty;

    /// <summary>
    /// Indicates if there was an error processing the query
    /// </summary>
    public bool HasError { get; set; } = false;

    /// <summary>
    /// Error message if HasError is true
    /// </summary>
    public string ErrorMessage { get; set; } = string.Empty;
}

/// <summary>
/// Represents when the user wants to travel
/// </summary>
public class TravelTime
{
    /// <summary>
    /// Date and time for departure
    /// </summary>
    public DateTime? DepartureTime { get; set; }

    /// <summary>
    /// Date and time for arrival
    /// </summary>
    public DateTime? ArrivalTime { get; set; }

    /// <summary>
    /// Whether the user has a specific schedule
    /// </summary>
    public bool HasSpecificSchedule => DepartureTime.HasValue || ArrivalTime.HasValue;

    /// <summary>
    /// Whether the time is flexible
    /// </summary>
    public bool IsFlexible { get; set; }
}

/// <summary>
/// Represents user preferences for the journey
/// </summary>
public class TravelPreferences
{
    /// <summary>
    /// Priority for journey (faster, cheaper, more comfortable, etc.)
    /// </summary>
    public string Priority { get; set; } = string.Empty;

    /// <summary>
    /// Whether the user is willing to walk
    /// </summary>
    public bool ConsiderWalking { get; set; } = true;

    /// <summary>
    /// Whether the user is willing to bike
    /// </summary>
    public bool ConsiderBiking { get; set; } = true;

    /// <summary>
    /// Whether the user is willing to take public transport
    /// </summary>
    public bool ConsiderPublicTransport { get; set; } = true;

    /// <summary>
    /// Whether the user is willing to drive
    /// </summary>
    public bool ConsiderDriving { get; set; } = true;

    /// <summary>
    /// Whether the user is willing to take a train
    /// </summary>
    public bool ConsiderTrain { get; set; } = true;

    /// <summary>
    /// Whether the user is willing to fly
    /// </summary>
    public bool ConsiderFlying { get; set; } = true;

    /// <summary>
    /// Maximum distance the user is willing to walk (in kilometers)
    /// </summary>
    public double? MaxWalkingDistance { get; set; }

    /// <summary>
    /// Maximum distance the user is willing to bike (in kilometers)
    /// </summary>
    public double? MaxBikingDistance { get; set; }

    /// <summary>
    /// Maximum time the user is willing to spend on the journey (in minutes)
    /// </summary>
    public int? MaxTravelTime { get; set; }

    /// <summary>
    /// Maximum cost the user is willing to spend on the journey
    /// </summary>
    public decimal? MaxCost { get; set; }
}