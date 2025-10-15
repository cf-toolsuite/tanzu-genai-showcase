using System.Net.Http.Headers;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using Steeltoe.Configuration.CloudFoundry;
using System.Text.Json;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Infrastructure.Clients;
using TravelAdvisor.Infrastructure.Options;
using TravelAdvisor.Infrastructure.Services;

namespace TravelAdvisor.Infrastructure;

/// <summary>
/// Extension methods for setting up infrastructure services in an IServiceCollection
/// </summary>
public static class DependencyInjection
{
    /// <summary>
    /// Adds infrastructure services to the specified IServiceCollection
    /// </summary>
    public static IServiceCollection AddInfrastructureServices(
        this IServiceCollection services,
        IConfiguration configuration)
    {
        // Configure HTTP client
        services.AddHttpClient();

        // Check if mock data is enabled
        var useMockData = IsMockDataEnabled(configuration.GetValue<string>("Use_Mock_Data") ?? "false");

        services.AddOptions<GenAIOptions>()
            .BindConfiguration("GenAI")
            .PostConfigure<IOptions<CloudFoundryServicesOptions>>((options, vcapServices) =>
            {
                var genAIService = vcapServices.Value.GetServicesOfType("genai").FirstOrDefault();
                if (genAIService == null)
                {
                    return;
                }

                var endpointCredentials = genAIService.Credentials["endpoint"];
                options.ApiKey = endpointCredentials["api_key"].Value ?? options.ApiKey;
                options.ApiUrl = endpointCredentials["api_base"].Value ?? options.ApiUrl;
                var configUrl = endpointCredentials["config_url"].Value;
                if (!string.IsNullOrEmpty(configUrl))
                {
                    options.ConfigUrl = configUrl;
                    using var httpClient = new HttpClient();
                    httpClient.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", options.ApiKey);

                    // TODO: Async code (like running an HTTP request) here is not ideal
                    var response = httpClient.GetAsync(options.ConfigUrl).GetAwaiter().GetResult();
                    if (response.IsSuccessStatusCode)
                    {
                        options.Model = FindChatModel(response.Content.ReadAsStringAsync().GetAwaiter().GetResult());
                    }
                }
            });

        // Register services based on environment configuration (real or mock)
        RegisterServices(services, configuration, useMockData);

        return services;
    }

    private static string FindChatModel(string response)
    {
        try
        {
            var config = JsonSerializer.Deserialize<JsonElement>(response);

            string? chatModel = null;

            if (!config.TryGetProperty("advertisedModels", out var models))
            {
                return chatModel ?? throw new InvalidOperationException("No chat model found in config");
            }

            foreach (var model in models.EnumerateArray())
            {
                if (model.TryGetProperty("name", out var nameElement) &&
                    model.TryGetProperty("capabilities", out var capabilitiesElement))
                {
                    var modelName = nameElement.GetString();
                    var capabilities = capabilitiesElement.EnumerateArray()
                        .Select(c => c.GetString())
                        .ToList();

                    if (capabilities.Contains("CHAT"))
                    {
                        return modelName!;
                    }
                }
            }

            return chatModel ?? throw new InvalidOperationException("No chat model found in config");
        }
        catch (Exception ex)
        {
            throw new InvalidOperationException($"Failed to parse model configuration: {ex.Message}", ex);
        }
    }

    /// <summary>
    /// Registers all application services
    /// </summary>
    private static void RegisterServices(
        IServiceCollection services,
        IConfiguration configuration,
        bool useMockData)
    {
        // Configure options
        services.Configure<GoogleMapsOptions>(configuration.GetSection("GoogleMaps"));

        // Add AI services
        RegisterAIServices(services, useMockData);

        // Register the Travel Advisor services
        if (useMockData)
        {
            services.AddSingleton<IMapService, MockGoogleMapsService>();
            services.AddSingleton<ITravelAdvisorService, MockTravelAdvisorService>();
        }
        else
        {
            services.AddSingleton<IMapService, GoogleMapsService>();
            services.AddSingleton<ITravelAdvisorService, TravelAdvisorService>();
        }
    }

    /// <summary>
    /// Adds AI services to the service collection
    /// </summary>
    private static void RegisterAIServices(
        IServiceCollection services,
        bool useMockData)
    {
        // Register mock services if mock data is enabled
        if (useMockData)
        {
            services.AddSingleton<IChatClient, MockChatClient>();
            return;
        }

        // Register the AI client factory
        services.AddSingleton<IAIClientFactory, AIClientFactory>();

        // Register the ChatClient with dependency injection
        services.AddSingleton<IChatClient>(sp =>
        {
            var factory = sp.GetRequiredService<IAIClientFactory>();
            var options = sp.GetRequiredService<IOptions<GenAIOptions>>().Value;
            var logger = sp.GetRequiredService<ILogger<AIClientFactory>>();

            return factory.CreateClient(options, logger);
        });
    }

    /// <summary>
    /// Checks if mock data is enabled via environment variable
    /// </summary>
    private static bool IsMockDataEnabled(string useMockData)
    {
        return useMockData.Equals("true", StringComparison.InvariantCultureIgnoreCase) || useMockData == "1";
    }
}
