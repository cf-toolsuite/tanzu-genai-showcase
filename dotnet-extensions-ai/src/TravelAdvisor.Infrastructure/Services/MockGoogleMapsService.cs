using Microsoft.Extensions.Logging;
using TravelAdvisor.Core.Models;
using TravelAdvisor.Core.Services;

namespace TravelAdvisor.Infrastructure.Services;

/// <summary>
/// Mock implementation of IGoogleMapsService for development/testing
/// </summary>
public class MockGoogleMapsService(ILogger<MockGoogleMapsService> logger) : IGoogleMapsService
{
    private readonly ILogger<MockGoogleMapsService> _logger = logger ?? throw new ArgumentNullException(nameof(logger));

    /// <inheritdoc />
    public Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
        string origin,
        string destination,
        TransportMode mode)
    {
        _logger.LogInformation("Using mock data for distance calculation from {Origin} to {Destination} via {Mode}",
            origin, destination, mode);
        var result = GetMockDistanceAndDuration(origin, destination, mode);
        return Task.FromResult(result);
    }

    /// <inheritdoc />
    public Task<List<TravelStep>> GetTravelStepsAsync(
        string origin,
        string destination,
        TransportMode mode)
    {
        _logger.LogInformation("Using mock data for travel steps from {Origin} to {Destination} via {Mode}",
            origin, destination, mode);
        var result = GetMockTravelSteps(origin, destination, mode);
        return Task.FromResult(result);
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

    /// <summary>
    /// Provides realistic mock distance and duration data for the given origin and destination
    /// </summary>
    private (double distanceKm, int durationMinutes) GetMockDistanceAndDuration(
        string origin,
        string destination,
        TransportMode mode)
    {
        // For Mill Creek, WA to Ballard, WA - approximately 35km and realistic travel times
        if ((origin.Contains("Mill Creek", StringComparison.OrdinalIgnoreCase) &&
             destination.Contains("Ballard", StringComparison.OrdinalIgnoreCase)) ||
            (destination.Contains("Mill Creek", StringComparison.OrdinalIgnoreCase) &&
             origin.Contains("Ballard", StringComparison.OrdinalIgnoreCase)))
        {
            const double distanceKm = 35.0;
            int durationMinutes = mode switch
            {
                TransportMode.Walk => 420, // 7 hours
                TransportMode.Bike => 130, // ~2.2 hours
                TransportMode.Bus => 110, // ~1.8 hours with transfers
                TransportMode.Car => 50, // ~50 minutes by car
                TransportMode.Train => 90, // ~1.5 hours including transit to/from stations
                TransportMode.Plane => 0, // Not applicable for this distance
                _ => 50 // Default to car time
            };

            return (distanceKm, durationMinutes);
        }

        // For other origins/destinations, use the estimation method
        return EstimateDistanceAndDuration(origin, destination, mode);
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
            (double distanceKm, int durationMinutes) = GetMockDistanceAndDuration(origin, destination, mode);
            var steps = new List<TravelStep>();

            switch (mode)
            {
                case TransportMode.Car:
                    steps.Add(new TravelStep
                    {
                        Description = "Head south on WA-527 S from Mill Creek",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Take I-405 S and I-5 S to 15th Ave NW in Seattle",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Follow 15th Ave NW to Ballard",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    break;

                case TransportMode.Bus:
                    steps.Add(new TravelStep
                    {
                        Description = "Take Bus 105 from Mill Creek Park & Ride",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Transfer to Bus 512 at Lynnwood Transit Center",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Take Bus 512 to Northgate Station",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Transfer to Bus 40 to Ballard",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    break;

                case TransportMode.Bike:
                    steps.Add(new TravelStep
                    {
                        Description = "Take Interurban Trail south from Mill Creek",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Continue on Burke-Gilman Trail west",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Take 8th Ave NW north to Ballard",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    break;

                case TransportMode.Walk:
                    steps.Add(new TravelStep
                    {
                        Description = "Walk south on Bothell-Everett Highway",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Continue on Lake City Way NE",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
                    });
                    steps.Add(new TravelStep
                    {
                        Description = "Follow N 45th St west to Ballard",
                        Mode = mode,
                        DurationMinutes = durationMinutes,
                        DistanceKm = distanceKm
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

    #region Fallback Methods

    private (double distanceKm, int durationMinutes) EstimateDistanceAndDuration(
        string origin,
        string destination,
        TransportMode mode)
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
            TransportMode.Bike => (int)(distanceKm * 4), // Approx. 15 km/h = 4 min/km
            TransportMode.Bus => (int)(distanceKm * 2), // Approx. 30 km/h = 2 min/km
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
            new()
            {
                Description = $"Start from {origin}",
                Mode = mode,
                DurationMinutes = stepDuration,
                DistanceKm = stepDistance
            },
            new()
            {
                Description = $"Continue towards {destination}",
                Mode = mode,
                DurationMinutes = stepDuration,
                DistanceKm = stepDistance
            },
            new()
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
}
