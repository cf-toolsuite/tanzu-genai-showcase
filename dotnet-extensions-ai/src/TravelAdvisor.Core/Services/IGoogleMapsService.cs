namespace TravelAdvisor.Core.Services;

/// <summary>
/// Interface for Google Maps services
/// </summary>
public interface IGoogleMapsService : IMapService
{
    // This interface extends IMapService without adding additional methods
    // It serves as a specific contract for Google Maps implementations
}