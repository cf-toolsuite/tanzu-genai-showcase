using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using Microsoft.SemanticKernel;
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Core.Utilities;
using TravelAdvisor.Infrastructure.CloudFoundry;
using TravelAdvisor.Infrastructure.Services;
using TravelAdvisor.Infrastructure.Options;
using System.Reflection;

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
        public static IServiceCollection AddInfrastructureServices(this IServiceCollection services, IConfiguration configuration)
        {
            // Add Cloud Foundry service bindings
            services.AddCloudFoundryServices(configuration);

            // Configure HTTP client
            services.AddHttpClient();

            // Check if mock data is enabled
            bool useMockData = false;
            bool.TryParse(Environment.GetEnvironmentVariable("USE_MOCK_DATA"), out useMockData);

            // Register the Maps services
            if (useMockData)
            {
                services.AddSingleton<IGoogleMapsService, MockGoogleMapsService>();
                services.AddSingleton<IMapService>(sp => sp.GetRequiredService<IGoogleMapsService>());
            }
            else
            {
                services.AddSingleton<IGoogleMapsService, GoogleMapsService>();
                services.AddSingleton<IMapService>(sp => sp.GetRequiredService<IGoogleMapsService>());
            }

            // Add AI services
            AddAIServices(services, configuration);

            // Register the Travel Advisor services
            if (useMockData)
            {
                services.AddSingleton<ITravelAdvisorService, MockTravelAdvisorService>();
            }
            else
            {
                services.AddSingleton<ITravelAdvisorService, TravelAdvisorService>();
            }

            return services;
        }

        private static void AddAIServices(IServiceCollection services, IConfiguration configuration)
        {
            // Configure GenAI options
            var genAIOptions = new GenAIOptions();
            configuration.GetSection("GenAI").Bind(genAIOptions);

            // Add IChatClient using Microsoft.Extensions.AI
            if (!string.IsNullOrEmpty(genAIOptions.ApiUrl) && !string.IsNullOrEmpty(genAIOptions.ApiKey))
            {
                // Use reflection to get the appropriate AI client factory
                // This is a workaround for the API not being stable yet
                try
                {
                    // Check if Azure OpenAI is needed
                    if (genAIOptions.ApiUrl.Contains("openai.azure.com"))
                    {
                        services.AddSingleton<IChatClient>(sp =>
                        {
                            var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                            var logger = loggerFactory.CreateLogger("AzureOpenAIChatClient");

                            // Create a factory for Azure OpenAI client
                            return CreateAzureOpenAIChatClient(genAIOptions.ApiKey, genAIOptions.ApiUrl, genAIOptions.Model);
                        });
                    }
                    else
                    {
                        services.AddSingleton<IChatClient>(sp =>
                        {
                            var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                            var logger = loggerFactory.CreateLogger("OpenAIChatClient");

                            // Create a factory for OpenAI client
                            return CreateOpenAIChatClient(genAIOptions.ApiKey, genAIOptions.Model);
                        });
                    }
                }
                catch (Exception ex)
                {
                    Console.WriteLine($"Error setting up AI client: {ex.Message}");

                    // Fallback to mock implementation
                    services.AddSingleton<IChatClient>(sp =>
                    {
                        var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                        var logger = loggerFactory.CreateLogger<MockChatClient>();

                        return new MockChatClient(logger);
                    });
                }
            }
            // Fallback to a mock implementation
            else
            {
                services.AddSingleton<IChatClient>(sp =>
                {
                    var loggerFactory = sp.GetRequiredService<ILoggerFactory>();
                    var logger = loggerFactory.CreateLogger<MockChatClient>();

                    // Force a direct environment variable check to bypass any caching
                    string useMockDataStr = Environment.GetEnvironmentVariable("USE_MOCK_DATA") ?? "false";
                    bool useMockData = useMockDataStr.ToLowerInvariant() == "true" || useMockDataStr == "1";

                    // Also check through the utility to maintain logging and for debugging
                    bool useMockDataFromUtils = EnvironmentVariables.GetBool("USE_MOCK_DATA", false);

                    logger.LogInformation($"Raw environment variable USE_MOCK_DATA = '{useMockDataStr}', parsed as {useMockData}");
                    logger.LogInformation($"EnvironmentVariables.GetBool(\"USE_MOCK_DATA\") = {useMockDataFromUtils}");

                    if (useMockData)
                    {
                        logger.LogInformation("Mock data is ENABLED via environment variable USE_MOCK_DATA");
                    }
                    else
                    {
                        logger.LogInformation("Mock data is DISABLED via environment variable USE_MOCK_DATA");
                    }

                    return new MockChatClient(logger, useMockData);
                });
            }

            // Register the PromptFactory
            services.AddSingleton<IPromptFactory, PromptFactory>();
        }

        /// <summary>
        /// Create Azure OpenAI chat client using reflection to handle API changes
        /// </summary>
        private static IChatClient CreateAzureOpenAIChatClient(string apiKey, string endpoint, string deploymentName)
        {
            // Try to find the Azure OpenAI client type using reflection with different possible namespaces and assemblies
            var azureOpenAIClientType =
                Type.GetType("Microsoft.Extensions.AI.AzureOpenAIChatClient, Microsoft.Extensions.AI") ??
                Type.GetType("Microsoft.Extensions.AI.AzureOpenAIChatClient, Microsoft.Extensions.AI.Abstractions") ??
                Type.GetType("Microsoft.Extensions.AI.OpenAI.AzureOpenAIChatClient, Microsoft.Extensions.AI.OpenAI");

            if (azureOpenAIClientType == null)
            {
                // If we still can't find it, look through all loaded assemblies
                foreach (var assembly in AppDomain.CurrentDomain.GetAssemblies())
                {
                    try
                    {
                        // Try to find any class that ends with "AzureOpenAIChatClient"
                        var types = assembly.GetTypes().Where(t => t.Name.EndsWith("AzureOpenAIChatClient") &&
                                                                  t.Namespace?.StartsWith("Microsoft.Extensions.AI") == true);

                        foreach (var type in types)
                        {
                            Console.WriteLine($"Found potential AzureOpenAIChatClient: {type.FullName} in {assembly.FullName}");
                            azureOpenAIClientType = type;
                            if (azureOpenAIClientType != null) break;
                        }
                    }
                    catch (Exception ex)
                    {
                        // If we can't load types from an assembly (e.g., due to missing dependencies), just skip it
                        Console.WriteLine($"Skipping assembly {assembly.FullName}: {ex.Message}");
                    }

                    if (azureOpenAIClientType != null) break;
                }
            }

            if (azureOpenAIClientType == null)
            {
                // Fallback to creating a mock client if we can't find the Azure OpenAI client
                Console.WriteLine("Could not find AzureOpenAIChatClient type in the loaded assemblies. Using MockChatClient as fallback.");
                return new MockChatClient(null, true);
            }

            try
            {
                // Create an instance using the constructor - try different constructor signatures
                var constructor = azureOpenAIClientType.GetConstructor(new[] { typeof(string), typeof(string), typeof(string) });

                if (constructor != null)
                {
                    return (IChatClient)constructor.Invoke(new object[] { apiKey, endpoint, deploymentName });
                }

                // Try with just API key and endpoint
                constructor = azureOpenAIClientType.GetConstructor(new[] { typeof(string), typeof(string) });
                if (constructor != null)
                {
                    var client = (IChatClient)constructor.Invoke(new object[] { apiKey, endpoint });

                    // Try to set the deployment name via a property
                    var deploymentProperty = azureOpenAIClientType.GetProperty("DeploymentName");
                    if (deploymentProperty != null && deploymentProperty.CanWrite)
                    {
                        deploymentProperty.SetValue(client, deploymentName);
                    }

                    return client;
                }

                // If we can't create the client, fall back to mock
                return new MockChatClient(null, true);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Failed to create AzureOpenAIChatClient: {ex.Message}. Using MockChatClient as fallback.");
                return new MockChatClient(null, true);
            }
        }

        /// <summary>
        /// Create OpenAI chat client using reflection to handle API changes
        /// </summary>
        private static IChatClient CreateOpenAIChatClient(string apiKey, string modelId)
        {
            // Try to find the OpenAI client type using reflection with different possible namespaces and assemblies
            var openAIClientType =
                Type.GetType("Microsoft.Extensions.AI.OpenAIChatClient, Microsoft.Extensions.AI") ??
                Type.GetType("Microsoft.Extensions.AI.OpenAIChatClient, Microsoft.Extensions.AI.Abstractions") ??
                Type.GetType("Microsoft.Extensions.AI.OpenAI.OpenAIChatClient, Microsoft.Extensions.AI.OpenAI");

            if (openAIClientType == null)
            {
                // If we still can't find it, look through all loaded assemblies
                foreach (var assembly in AppDomain.CurrentDomain.GetAssemblies())
                {
                    try
                    {
                        // Try to find any class that ends with "OpenAIChatClient"
                        var types = assembly.GetTypes().Where(t => t.Name.EndsWith("OpenAIChatClient") &&
                                                                  t.Namespace?.StartsWith("Microsoft.Extensions.AI") == true);

                        foreach (var type in types)
                        {
                            Console.WriteLine($"Found potential OpenAIChatClient: {type.FullName} in {assembly.FullName}");
                            openAIClientType = type;
                            if (openAIClientType != null) break;
                        }
                    }
                    catch (Exception ex)
                    {
                        // If we can't load types from an assembly (e.g., due to missing dependencies), just skip it
                        Console.WriteLine($"Skipping assembly {assembly.FullName}: {ex.Message}");
                    }

                    if (openAIClientType != null) break;
                }
            }

            if (openAIClientType == null)
            {
                // Fallback to creating a mock client if we can't find the OpenAI client
                Console.WriteLine("Could not find OpenAIChatClient type in the loaded assemblies. Using MockChatClient as fallback.");
                return new MockChatClient(null, true);
            }

            try
            {
                // Create an instance using the constructor - try different constructor signatures
                var constructor = openAIClientType.GetConstructor(new[] { typeof(string), typeof(string) });

                if (constructor != null)
                {
                    return (IChatClient)constructor.Invoke(new object[] { apiKey, modelId });
                }

                // Try with just the API key
                constructor = openAIClientType.GetConstructor(new[] { typeof(string) });
                if (constructor != null)
                {
                    var client = (IChatClient)constructor.Invoke(new object[] { apiKey });

                    // Try to set the model via a property or method
                    var modelProperty = openAIClientType.GetProperty("Model");
                    if (modelProperty != null && modelProperty.CanWrite)
                    {
                        modelProperty.SetValue(client, modelId);
                    }

                    return client;
                }

                // If we can't create the client, fall back to mock
                return new MockChatClient(null, true);
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Failed to create OpenAIChatClient: {ex.Message}. Using MockChatClient as fallback.");
                return new MockChatClient(null, true);
            }
        }
    }

    /// <summary>
    /// A mock implementation of IChatClient for development/testing
    /// </summary>
    internal class MockChatClient : IChatClient
    {
        private readonly ILogger<MockChatClient>? _logger;
        private readonly bool _useMockData;

        public MockChatClient(ILogger<MockChatClient>? logger = null)
            : this(logger, false)
        {
        }

        public MockChatClient(ILogger<MockChatClient>? logger, bool useMockData)
        {
            _logger = logger;
            _useMockData = useMockData;
            _logger?.LogInformation($"MockChatClient initialized with useMockData={useMockData}");
        }

        public Task<ChatResponse> GetResponseAsync(IEnumerable<ChatMessage> messages, ChatOptions? options = null, CancellationToken cancellationToken = default)
        {
            var chatResponse = new ChatResponse();

            // If mock data is explicitly disabled, return an appropriate error message
            if (!_useMockData)
            {
                _logger?.LogError("Mock data is disabled (USE_MOCK_DATA=false). Service temporarily unavailable.");

                // Return a standard error message instead of mock data
                chatResponse.AddProperty("content", "Service temporarily unavailable. Please try again later.");
                chatResponse.AddProperty("role", ChatRole.Assistant.ToString());
                chatResponse.AddProperty("error", "SERVICE_UNAVAILABLE");

                return Task.FromResult(chatResponse);
            }

            _logger?.LogWarning("Using mock AI service. Configure GenAI:ApiKey and GenAI:ApiUrl for a real service.");

            // Provide a more substantial mock response with example travel data
            chatResponse.AddProperty("content",
                @"{
                  ""Origin"": ""Mill Creek, WA"",
                  ""Destination"": ""Ballard, WA"",
                  ""TravelTime"": {
                    ""DepartureTime"": null,
                    ""ArrivalTime"": null,
                    ""IsFlexible"": true
                  },
                  ""Preferences"": {
                    ""Priority"": ""faster"",
                    ""ConsiderWalking"": true,
                    ""ConsiderBiking"": true,
                    ""ConsiderPublicTransport"": true,
                    ""ConsiderDriving"": true,
                    ""ConsiderTrain"": true,
                    ""ConsiderFlying"": false,
                    ""MaxWalkingDistance"": null,
                    ""MaxBikingDistance"": null,
                    ""MaxTravelTime"": null,
                    ""MaxCost"": null
                  },
                  ""AdditionalContext"": ""Travel from Mill Creek to Ballard""
                }");

            chatResponse.AddProperty("role", ChatRole.Assistant.ToString());

            return Task.FromResult(chatResponse);
        }

        public IAsyncEnumerable<ChatResponseUpdate> GetStreamingResponseAsync(IEnumerable<ChatMessage> messages, ChatOptions? options = null, CancellationToken cancellationToken = default)
        {
            // If mock data is explicitly disabled, log an error but return an empty stream
            // We don't throw an exception to maintain consistency with GetResponseAsync
            if (!_useMockData)
            {
                _logger?.LogError("Mock data is disabled (USE_MOCK_DATA=false). Service temporarily unavailable.");

                // In streaming mode, we can't really send an error message
                // so we just return an empty stream
            }
            else
            {
                _logger?.LogWarning("Using mock AI service. Configure GenAI:ApiKey and GenAI:ApiUrl for a real service.");
            }

            // Return an empty stream in both cases
            return EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
        }

        public ChatClientMetadata Metadata => new ChatClientMetadata();

        public object? GetService(Type serviceType, object? key = null)
        {
            return null;
        }

        public void Dispose() { }
    }

    /// <summary>
    /// Helper class for empty async enumerable
    /// </summary>
    internal class EmptyAsyncEnumerable<T> : IAsyncEnumerable<T>
    {
        public static readonly EmptyAsyncEnumerable<T> Instance = new();

        private EmptyAsyncEnumerable() { }

        public IAsyncEnumerator<T> GetAsyncEnumerator(CancellationToken cancellationToken = default)
        {
            return new EmptyAsyncEnumerator();
        }

        private class EmptyAsyncEnumerator : IAsyncEnumerator<T>
        {
            public T Current => default!;

            public ValueTask<bool> MoveNextAsync()
            {
                return new ValueTask<bool>(false);
            }

            public ValueTask DisposeAsync()
            {
                return ValueTask.CompletedTask;
            }
        }
    }
}

/// <summary>
/// Extension methods for dynamic property handling
/// </summary>
public static class DynamicObjectExtensions
{
    /// <summary>
    /// Add a property to an object dynamically
    /// </summary>
    public static void AddProperty(this object obj, string propertyName, object value)
    {
        // Try to find an existing property
        var property = obj.GetType().GetProperty(propertyName,
            BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);

        if (property != null && property.CanWrite)
        {
            // If property exists and is writable, set its value
            property.SetValue(obj, value);
        }
        else
        {
            // Otherwise, we can't add a property dynamically in C#
            // This is a no-op, but we could log it if needed
        }
    }
}
