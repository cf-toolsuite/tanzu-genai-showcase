using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using Microsoft.Extensions.Options;
using OpenAI;
using OpenAI.Chat;
using System;
using System.Collections.Generic;
using System.Net.Http;
using System.Threading;
using System.Threading.Tasks;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Core.Utilities;
using TravelAdvisor.Infrastructure.CloudFoundry;
using TravelAdvisor.Infrastructure.Services;
using TravelAdvisor.Infrastructure.Options;
using TravelAdvisor.Infrastructure.Clients;
using System.Linq;

namespace TravelAdvisor.Infrastructure
{
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
            // Add Cloud Foundry service bindings
            services.AddCloudFoundryServices(configuration);

            // Configure HTTP client
            services.AddHttpClient();

            // Check if mock data is enabled
            bool useMockData = IsMockDataEnabled();

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
                // Configure GenAIOptions from configuration
                services.Configure<GenAIOptions>(configuration.GetSection("GenAI"));

                // Register the AI client factory
                services.AddSingleton<IAIClientFactory, AIClientFactory>();

                // Register the ChatClient with dependency injection
                services.AddSingleton<IChatClient>(sp => {
                    var factory = sp.GetRequiredService<IAIClientFactory>();
                    var options = sp.GetRequiredService<IOptions<GenAIOptions>>().Value;
                    var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                    var logger = loggerFactory.CreateLogger("AIClient");

                    return factory.CreateClient(options, logger);
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
}
