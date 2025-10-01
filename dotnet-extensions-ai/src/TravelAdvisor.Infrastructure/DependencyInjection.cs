using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Infrastructure.CloudFoundry;
using TravelAdvisor.Infrastructure.Services;
using TravelAdvisor.Infrastructure.Options;
using TravelAdvisor.Infrastructure.Clients;

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
        bool useMockData = IsMockDataEnabled();

        // IMPORTANT: Configure GenAIOptions from configuration BEFORE adding Cloud Foundry services
        // This ensures that the default values from appsettings.json are loaded first
        services.Configure<GenAIOptions>(configuration.GetSection("GenAI"));

        // Add Cloud Foundry service bindings - this will override the GenAIOptions if service bindings exist
        services.AddCloudFoundryServices(configuration);

        // Register services based on environment configuration (real or mock)
        RegisterServices(services, configuration, useMockData);

        return services;
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

        // Register the Maps services
        services.AddSingleton<IMapService>(sp =>
        {
            if (useMockData)
            {
                var mockLogger = sp.GetRequiredService<ILoggerFactory>().CreateLogger<MockGoogleMapsService>();
                return new MockGoogleMapsService(mockLogger);
            }
            else
            {
                var serviceLogger = sp.GetRequiredService<ILoggerFactory>().CreateLogger<GoogleMapsService>();
                var options = sp.GetRequiredService<IOptions<GoogleMapsOptions>>();
                return new GoogleMapsService(options, serviceLogger);
            }
        });

        // Add AI services
        RegisterAIServices(services, configuration, useMockData);

        // Register the Travel Advisor services
        services.AddSingleton<ITravelAdvisorService>(sp =>
        {
            var mapService = sp.GetRequiredService<IMapService>();

            if (useMockData)
            {
                var mockLogger = sp.GetRequiredService<ILoggerFactory>().CreateLogger<MockTravelAdvisorService>();
                return new MockTravelAdvisorService(mapService, mockLogger);
            }
            else
            {
                var serviceLogger = sp.GetRequiredService<ILoggerFactory>().CreateLogger<TravelAdvisorService>();
                var chatClient = sp.GetRequiredService<IChatClient>();
                var promptFactory = sp.GetRequiredService<IPromptFactory>();
                return new TravelAdvisorService(chatClient, promptFactory, mapService, serviceLogger);
            }
        });
    }

    /// <summary>
    /// Adds AI services to the service collection
    /// </summary>
    private static void RegisterAIServices(
        IServiceCollection services,
        IConfiguration configuration,
        bool useMockData)
    {
        // Register mock services if mock data is enabled
        if (useMockData)
        {
            RegisterMockAIServices(services);
            return;
        }

        try
        {
            // Get a logger for debugging
            ILogger? logger = null;
            var serviceProvider = services.BuildServiceProvider();
            try
            {
                var loggerFactory = serviceProvider.GetService<ILoggerFactory>();
                if (loggerFactory != null)
                {
                    logger = loggerFactory.CreateLogger("DependencyInjection");
                }
            }
            catch
            {
                // If we can't get a logger, just continue without it
            }

            // Log the current configuration values for debugging
            if (logger != null)
            {
                var apiKey = configuration["GenAI:ApiKey"];
                var apiUrl = configuration["GenAI:ApiUrl"];
                var model = configuration["GenAI:Model"];

                logger.LogInformation("Configuration values from appsettings.json:");
                logger.LogInformation($"GenAI:ApiKey: {(string.IsNullOrEmpty(apiKey) ? "Not set" : apiKey.Substring(0, Math.Min(5, apiKey.Length)) + "...")}");
                logger.LogInformation($"GenAI:ApiUrl: {apiUrl}");
                logger.LogInformation($"GenAI:Model: {model}");

                // Also log environment variables
                var envApiKey = Environment.GetEnvironmentVariable("GENAI__APIKEY");
                var envApiUrl = Environment.GetEnvironmentVariable("GENAI__APIURL");
                var envModel = Environment.GetEnvironmentVariable("GENAI__MODEL");

                logger.LogInformation("Environment variables:");
                logger.LogInformation($"GENAI__APIKEY: {(string.IsNullOrEmpty(envApiKey) ? "Not set" : "Set (value hidden)")}");
                logger.LogInformation($"GENAI__APIURL: {envApiUrl}");
                logger.LogInformation($"GENAI__MODEL: {envModel}");

                // Get the current GenAIOptions from the service provider to see what was actually configured
                try {
                    var optionsSnapshot = serviceProvider.GetService<IOptionsSnapshot<GenAIOptions>>();
                    if (optionsSnapshot != null)
                    {
                        var options = optionsSnapshot.Value;
                        logger.LogInformation("Actual GenAIOptions after service binding:");
                        logger.LogInformation($"ApiKey: {(string.IsNullOrEmpty(options.ApiKey) ? "Not set" : options.ApiKey.Substring(0, Math.Min(5, options.ApiKey.Length)) + "...")}");
                        logger.LogInformation($"ApiUrl: {options.ApiUrl}");
                        logger.LogInformation($"Model: {options.Model}");
                        logger.LogInformation($"ServiceName: {options.ServiceName}");
                    }
                }
                catch (Exception ex) {
                    logger.LogWarning($"Could not retrieve configured GenAIOptions: {ex.Message}");
                }
            }

            // Register the AI client factory
            services.AddSingleton<IAIClientFactory, AIClientFactory>();

            // Register the ChatClient with dependency injection
            services.AddSingleton<IChatClient>(sp => {
                var factory = sp.GetRequiredService<IAIClientFactory>();
                var options = sp.GetRequiredService<IOptions<GenAIOptions>>().Value;
                var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                var clientLogger = loggerFactory.CreateLogger("AIClient");

                // Log the options that will be used to create the client
                clientLogger.LogInformation("Creating AI client with options:");
                clientLogger.LogInformation($"ApiKey: {(string.IsNullOrEmpty(options.ApiKey) ? "Not set" : options.ApiKey.Substring(0, Math.Min(5, options.ApiKey.Length)) + "...")}");
                clientLogger.LogInformation($"ApiUrl: {options.ApiUrl}");
                clientLogger.LogInformation($"Model: {options.Model}");
                clientLogger.LogInformation($"ServiceName: {options.ServiceName}");

                return factory.CreateClient(options, clientLogger);
            });

            // Register the PromptFactory
            services.AddSingleton<IPromptFactory, PromptFactory>();
        }
        catch (Exception ex)
        {
            // If there's an error setting up the client, provide a detailed error message
            throw new InvalidOperationException(
                $"Failed to initialize AI client: {ex.Message}\n" +
                "Please check your GenAI credentials and ensure they are correctly configured.", ex);
        }
    }

    /// <summary>
    /// Registers mock AI services when mock data is enabled
    /// </summary>
    private static void RegisterMockAIServices(IServiceCollection services)
    {
        services.AddSingleton<IChatClient>(sp =>
        {
            var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
            var clientLogger = loggerFactory.CreateLogger<MockChatClient>();
            return new MockChatClient(clientLogger, true);
        });

        services.AddSingleton<IPromptFactory, PromptFactory>();
    }

    /// <summary>
    /// Checks if mock data is enabled via environment variable
    /// </summary>
    private static bool IsMockDataEnabled()
    {
        string useMockDataStr = Environment.GetEnvironmentVariable("USE_MOCK_DATA") ?? "false";
        return useMockDataStr.ToLowerInvariant() == "true" || useMockDataStr == "1";
    }
}