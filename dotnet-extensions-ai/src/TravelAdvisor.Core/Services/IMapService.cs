using TravelAdvisor.Core.Models;

namespace TravelAdvisor.Core.Services;

/// <summary>
/// Interface for mapping and distance services
/// </summary>
public interface IMapService
{
    /// <summary>
    /// Calculate the distance and duration between two locations for a specific transportation mode
    /// </summary>
    /// <param name="origin">Origin location</param>
    /// <param name="destination">Destination location</param>
    /// <param name="mode">Transportation mode</param>
    /// <returns>Distance in kilometers and duration in minutes</returns>
    Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
        string origin,
        string destination,
        TransportMode mode);

    /// <summary>
    /// Get detailed travel steps between two locations for a specific transportation mode
    /// </summary>
    /// <param name="origin">Origin location</param>
    /// <param name="destination">Destination location</param>
    /// <param name="mode">Transportation mode</param>
    /// <returns>List of travel steps</returns>
    Task<List<TravelStep>> GetTravelStepsAsync(
        string origin,
        string destination,
        TransportMode mode);

    /// <summary>
    /// Determine if a transportation mode is feasible for a given distance
    /// </summary>
    /// <param name="distanceKm">Distance in kilometers</param>
    /// <param name="mode">Transportation mode</param>
    /// <returns>True if the mode is feasible, otherwise false</returns>
    bool IsModeReasonable(double distanceKm, TransportMode mode);
}
