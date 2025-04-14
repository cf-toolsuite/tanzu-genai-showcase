# Travel Advisor Extension Guide: Adding Custom Features

This guide explains how to extend the Transportation Recommendation Bot with custom features, alternative transportation providers, and additional AI capabilities.

## Overview

The application is designed with extensibility in mind, allowing developers to add new features or replace existing components without major architectural changes. The key extension points include:

1. **Transportation data providers**
2. **AI model providers**
3. **Recommendation algorithms**
4. **UI components**
5. **Additional transportation modes**

## Prerequisites

Before extending the application, ensure you have:

- A good understanding of .NET 9 development
- Familiarity with Microsoft.Extensions.AI and Semantic Kernel
- Basic knowledge of dependency injection in ASP.NET Core

## Adding a New Transportation Data Provider

The application uses Google Maps API by default, but you can implement alternative transportation data providers.

### Step 1: Implement IMapService Interface

Create a new class that implements the `IMapService` interface:

```csharp
using System.Collections.Generic;
using System.Threading.Tasks;
using TravelAdvisor.Core.Models;
using TravelAdvisor.Core.Services;

namespace YourNamespace.Services
{
    public class YourCustomMapService : IMapService
    {
        public Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
            string origin,
            string destination,
            TransportMode mode)
        {
            // Your implementation here
        }

        public Task<List<TravelStep>> GetTravelStepsAsync(
            string origin,
            string destination,
            TransportMode mode)
        {
            // Your implementation here
        }

        public bool IsModeReasonable(double distanceKm, TransportMode mode)
        {
            // Your implementation here
        }
    }
}
```

### Step 2: Register Your Service

Register your custom service in `DependencyInjection.cs`:

```csharp
// In DependencyInjection.cs of the Infrastructure project
public static IServiceCollection AddInfrastructureServices(this IServiceCollection services, IConfiguration configuration)
{
    // Add Cloud Foundry service bindings
    services.AddCloudFoundryServices(configuration);

    // Register your custom map service instead of GoogleMapsService
    services.AddSingleton<IMapService, YourCustomMapService>();

    // Rest of the method...
}
```

## Implementing a Different AI Provider

The application currently supports OpenAI and Azure OpenAI. You can add support for other providers like Anthropic Claude, Google Gemini, or Mistral AI.

### Step 1: Implement ChatClient for Your Provider

Create a custom implementation of IChatClient for your AI provider:

```csharp
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;

namespace YourNamespace.Services
{
    public class YourAiProviderChatClient : IChatClient
    {
        private readonly ILogger<YourAiProviderChatClient> _logger;
        private readonly string _apiKey;
        private readonly string _modelId;

        public YourAiProviderChatClient(
            string apiKey,
            string modelId,
            ILogger<YourAiProviderChatClient> logger)
        {
            _apiKey = apiKey ?? throw new ArgumentNullException(nameof(apiKey));
            _modelId = modelId ?? throw new ArgumentNullException(nameof(modelId));
            _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        }

        public async Task<ChatResponse> GetResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            // Your implementation to call your AI provider's API
        }

        public IAsyncEnumerable<ChatStreamingResponse> GetStreamingResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            // Your implementation for streaming responses
        }

        public ChatClientMetadata? Metadata => new ChatClientMetadata
        {
            ModelId = _modelId,
            ContextLength = 16384 // Adjust based on your AI provider's model
        };

        public void Dispose()
        {
            // Cleanup any resources
        }
    }
}
```

### Step 2: Register Your AI Provider

Register your AI provider in `DependencyInjection.cs`:

```csharp
private static void AddAIServices(IServiceCollection services, IConfiguration configuration)
{
    // Configure your AI provider options
    var aiOptions = new YourAiProviderOptions();
    configuration.GetSection("YourAiProvider").Bind(aiOptions);

    // Register your AI provider
    services.AddSingleton<IChatClient>(sp =>
    {
        var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
        var logger = loggerFactory.CreateLogger<YourAiProviderChatClient>();

        return new YourAiProviderChatClient(
            aiOptions.ApiKey,
            aiOptions.ModelId,
            logger
        );
    });

    // Register the PromptFactory
    services.AddSingleton<IPromptFactory, PromptFactory>();
}
```

## Adding New Transportation Modes

To add new transportation modes (e.g., scooter, ferry, helicopter):

### Step 1: Update TransportMode Enum

Add your new mode to the `TransportMode` enum in `TravelRecommendation.cs`:

```csharp
public enum TransportMode
{
    Walk,
    Bike,
    Bus,
    Car,
    Train,
    Plane,
    Scooter,  // New mode
    Ferry,    // New mode
    Helicopter // New mode
}
```

### Step 2: Update GoogleMapsService

Modify the `ConvertToGoogleMode` and `ParseGoogleMode` methods in `GoogleMapsService.cs` to handle your new modes:

```csharp
private string ConvertToGoogleMode(TransportMode mode)
{
    return mode switch
    {
        TransportMode.Walk => "walking",
        TransportMode.Bike => "bicycling",
        TransportMode.Bus => "transit",
        TransportMode.Car => "driving",
        TransportMode.Train => "transit",
        TransportMode.Plane => "transit",
        TransportMode.Scooter => "bicycling", // Map to closest Google mode
        TransportMode.Ferry => "transit",     // Map to closest Google mode
        TransportMode.Helicopter => "transit",// Map to closest Google mode
        _ => "driving" // Default to driving
    };
}
```

### Step 3: Update the TravelAdvisorService

Update the helper methods in `TravelAdvisorService.cs` to handle your new modes:

```csharp
private List<string> GeneratePros(TransportMode mode, int durationMinutes, double distanceKm)
{
    var pros = new List<string>();

    switch (mode)
    {
        // Existing modes...

        case TransportMode.Scooter:
            pros.Add("Fun and flexible");
            pros.Add("Can navigate through traffic");
            pros.Add("Easy to park");
            pros.Add("Faster than walking");
            pros.Add("More economical than a car");
            break;

        case TransportMode.Ferry:
            pros.Add("Scenic views");
            pros.Add("No traffic congestion");
            pros.Add("Can be relaxing");
            pros.Add("Often has amenities onboard");
            pros.Add("Avoids road networks entirely");
            break;

        // Add other new modes...
    }

    return pros;
}
```

Do the same for `GenerateCons`, `CalculateEnvironmentalScore`, and other relevant methods.

## Enhancing the UI

You can enhance the Blazor UI to improve user experience or add new features.

### Adding a Map View

To add a visual map for recommended routes:

1. Add a new component in `src/TravelAdvisor.Web/Components`:

```csharp
@page "/map"
@using TravelAdvisor.Core.Models
@inject ITravelAdvisorService TravelAdvisorService
@inject IJSRuntime JSRuntime

<div id="map" style="height: 500px; width: 100%;"></div>

@code {
    [Parameter]
    public TravelRecommendation Recommendation { get; set; }

    protected override async Task OnAfterRenderAsync(bool firstRender)
    {
        if (firstRender && Recommendation != null)
        {
            await JSRuntime.InvokeVoidAsync("initMap",
                Recommendation.Steps.Select(s => new {
                    Lat = s.Latitude,
                    Lng = s.Longitude,
                    Description = s.Description
                }).ToArray());
        }
    }
}
```

2. Add the necessary JavaScript for Google Maps in `wwwroot/js/maps.js`

3. Reference the script in `_Host.cshtml`

## Adding New Recommendation Criteria

To add new criteria for recommendations (e.g., accessibility, comfort level):

1. Update the `TravelPreferences` class in `TravelQuery.cs`:

```csharp
public class TravelPreferences
{
    // Existing properties...

    /// <summary>
    /// Whether accessibility is a priority
    /// </summary>
    public bool RequiresAccessibility { get; set; }

    /// <summary>
    /// Minimum comfort level required (1-5)
    /// </summary>
    public int? MinimumComfortLevel { get; set; }
}
```

2. Update the `CalculatePreferenceMatchScore` method in `TravelAdvisorService.cs` to consider these new criteria.

## Implementing Caching

For better performance, you can implement caching for API calls:

1. Add a cache service in `TravelAdvisor.Infrastructure`:

```csharp
public class CacheService : ICacheService
{
    private readonly IMemoryCache _cache;

    public CacheService(IMemoryCache cache)
    {
        _cache = cache;
    }

    public async Task<T> GetOrCreateAsync<T>(string key, Func<Task<T>> factory, TimeSpan expiration)
    {
        if (!_cache.TryGetValue(key, out T result))
        {
            result = await factory();
            _cache.Set(key, result, expiration);
        }

        return result;
    }
}
```

2. Register the cache service in `DependencyInjection.cs`:

```csharp
services.AddMemoryCache();
services.AddSingleton<ICacheService, CacheService>();
```

3. Use the cache service in your implementations.

## Adding Real-time Transit Information

To enhance recommendations with real-time transit data:

1. Implement a service that fetches real-time transit information
2. Integrate it with the `TravelAdvisorService`
3. Update the UI to display real-time information

## Adding Multi-language Support

To support multiple languages:

1. Add resource files for different languages in `TravelAdvisor.Web/Resources`
2. Configure localization in `Program.cs`
3. Inject `IStringLocalizer` in your components

## Creating Semantic Kernel Plugins

Semantic Kernel allows you to create plugins that can be used by the LLM:

1. Create a new plugin class:

```csharp
using Microsoft.SemanticKernel;
using System.ComponentModel;

namespace YourNamespace.Plugins
{
    public class WeatherPlugin
    {
        [KernelFunction("get_weather")]
        [Description("Gets the current weather for a location")]
        public async Task<string> GetWeatherAsync(string location)
        {
            // Implement weather retrieval
        }
    }
}
```

2. Register the plugin with Semantic Kernel:

```csharp
// Add plugin to the kernel
kernel.Plugins.AddFromType<WeatherPlugin>();
```

## Implementing Analytics

To track user interactions and recommendations:

1. Create an analytics service interface:

```csharp
public interface IAnalyticsService
{
    Task TrackQueryAsync(TravelQuery query);
    Task TrackRecommendationSelectedAsync(TravelRecommendation recommendation);
    Task TrackFollowUpQuestionAsync(string question);
}
```

2. Implement the interface for your preferred analytics platform

3. Register the service and inject it where needed

## Deploying on Multiple Environments

For managing different environments (development, testing, production):

1. Create environment-specific configurations:
   - `appsettings.Development.json`
   - `appsettings.Staging.json`
   - `appsettings.Production.json`

2. Use environment-specific service bindings in Cloud Foundry

## Conclusion

This extension guide provides a starting point for adding new features and capabilities to the Transportation Recommendation Bot. By following these patterns, you can create a highly customized application that meets your specific requirements while maintaining the clean architecture and best practices established in the base implementation.

When extending the application, remember to:

1. Follow the existing architectural patterns
2. Write tests for your new components
3. Update documentation to reflect your changes
4. Consider security implications of any external API integrations

For additional guidance, refer to the implementation documentation or create an issue in the project repository.
