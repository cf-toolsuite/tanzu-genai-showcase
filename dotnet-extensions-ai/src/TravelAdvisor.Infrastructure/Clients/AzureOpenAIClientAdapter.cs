using Microsoft.Extensions.AI;
using System.Reflection;
using TravelAdvisor.Infrastructure.Utilities;

namespace TravelAdvisor.Infrastructure.Clients
{
    /// <summary>
    /// Adapter for Azure OpenAI client that implements IChatClient
    /// </summary>
    public class AzureOpenAIClientAdapter : IChatClient
    {
        private readonly object _azureClient;
        private readonly MethodInfo? _getResponseAsyncMethod;
        private readonly MethodInfo? _getStreamingResponseAsyncMethod;
        private readonly MethodInfo? _getServiceMethod;

        /// <summary>
        /// Creates a new Azure OpenAI client adapter using reflection to handle multiple SDK versions
        /// </summary>
        public AzureOpenAIClientAdapter(string apiKey, string endpoint, string deploymentName)
        {
            if (string.IsNullOrEmpty(apiKey))
                throw new ArgumentNullException(nameof(apiKey));

            if (string.IsNullOrEmpty(endpoint))
                throw new ArgumentNullException(nameof(endpoint));

            if (string.IsNullOrEmpty(deploymentName))
                throw new ArgumentNullException(nameof(deploymentName));

            // Find the Azure client type
            Type? azureClientType = FindAzureOpenAIClientType();
            if (azureClientType == null)
            {
                throw new InvalidOperationException("Azure OpenAI client type not found in loaded assemblies");
            }

            // Create an instance of the client
            _azureClient = CreateClientInstance(azureClientType, apiKey, endpoint, deploymentName);

            // Get methods needed to call the client - may throw if methods aren't found
            _getResponseAsyncMethod = GetMethodOrDefault(azureClientType, "GetResponseAsync");
            _getStreamingResponseAsyncMethod = GetMethodOrDefault(azureClientType, "GetStreamingResponseAsync");

            // Try to get the GetService method but don't fail if it doesn't exist
            _getServiceMethod = azureClientType.GetMethod("GetService",
                new[] { typeof(Type), typeof(object) }) ??
                azureClientType.GetMethod("GetService", new[] { typeof(Type) });
        }

        /// <summary>
        /// Implements IChatClient.GetResponseAsync using reflection to call the actual client
        /// </summary>
        public Task<ChatResponse> GetResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            if (_getResponseAsyncMethod == null)
            {
                throw new InvalidOperationException("GetResponseAsync method not found on Azure OpenAI client");
            }

            if (messages == null)
            {
                throw new ArgumentNullException(nameof(messages));
            }

            try
            {
                // Invoke the method using reflection
                var result = _getResponseAsyncMethod.Invoke(
                    _azureClient,
                    new object?[] { messages, options, cancellationToken });

                if (result == null)
                {
                    throw new InvalidOperationException("Null result from Azure OpenAI client GetResponseAsync");
                }

                return (Task<ChatResponse>)result;
            }
            catch (TargetInvocationException ex)
            {
                // Unwrap and rethrow the inner exception
                throw ex.InnerException ?? ex;
            }
        }

        /// <summary>
        /// Implements IChatClient.GetStreamingResponseAsync using reflection to call the actual client
        /// </summary>
        public IAsyncEnumerable<ChatResponseUpdate> GetStreamingResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            if (_getStreamingResponseAsyncMethod == null)
            {
                // Return empty result if the method is not available
                return AsyncEnumerableUtilities.EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
            }

            if (messages == null)
            {
                throw new ArgumentNullException(nameof(messages));
            }

            try
            {
                // Invoke the method using reflection
                var result = _getStreamingResponseAsyncMethod.Invoke(
                    _azureClient,
                    new object?[] { messages, options, cancellationToken });

                if (result == null)
                {
                    return AsyncEnumerableUtilities.EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
                }

                return (IAsyncEnumerable<ChatResponseUpdate>)result;
            }
            catch (TargetInvocationException ex)
            {
                // Log exception and return empty result
                Console.WriteLine($"Error in GetStreamingResponseAsync: {ex.InnerException?.Message ?? ex.Message}");
                return AsyncEnumerableUtilities.EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
            }
            catch (Exception ex)
            {
                // Log exception and return empty result
                Console.WriteLine($"Error in GetStreamingResponseAsync: {ex.Message}");
                return AsyncEnumerableUtilities.EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
            }
        }

        /// <summary>
        /// Implements IChatClient.Metadata to provide client metadata
        /// </summary>
        public ChatClientMetadata Metadata => new ChatClientMetadata();

        /// <summary>
        /// Implements IChatClient.GetService for service resolution
        /// </summary>
        public object? GetService(Type serviceType, object? key = null)
        {
            if (serviceType == null)
            {
                throw new ArgumentNullException(nameof(serviceType));
            }

            // If our client has a GetService method, try to use it via reflection
            if (_getServiceMethod != null)
            {
                try
                {
                    var parameters = _getServiceMethod.GetParameters();
                    if (parameters.Length == 2)
                    {
                        return _getServiceMethod.Invoke(_azureClient, new object?[] { serviceType, key });
                    }
                    else if (parameters.Length == 1)
                    {
                        return _getServiceMethod.Invoke(_azureClient, new object?[] { serviceType });
                    }
                }
                catch (Exception ex)
                {
                    // Log the error but don't throw
                    Console.WriteLine($"Error calling GetService: {ex.Message}");
                }
            }

            // Otherwise return null
            return null;
        }

        /// <summary>
        /// Finds the Azure OpenAI client type using reflection
        /// </summary>
        private static Type? FindAzureOpenAIClientType()
        {
            // Try known type locations
            Type? clientType = Type.GetType("Microsoft.Extensions.AI.AzureOpenAIChatClient, Microsoft.Extensions.AI") ??
                Type.GetType("Microsoft.Extensions.AI.AzureOpenAIChatClient, Microsoft.Extensions.AI.Abstractions") ??
                Type.GetType("Microsoft.Extensions.AI.OpenAI.AzureOpenAIChatClient, Microsoft.Extensions.AI.OpenAI");

            if (clientType != null)
            {
                return clientType;
            }

            // Search through loaded assemblies
            foreach (var assembly in AppDomain.CurrentDomain.GetAssemblies())
            {
                try
                {
                    foreach (var type in assembly.GetTypes())
                    {
                        if (type.Name.EndsWith("AzureOpenAIChatClient") &&
                            type.Namespace?.StartsWith("Microsoft.Extensions.AI") == true)
                        {
                            return type;
                        }
                    }
                }
                catch
                {
                    // Skip assemblies that can't be loaded
                }
            }

            return null;
        }

        /// <summary>
        /// Creates an instance of the Azure OpenAI client using reflection
        /// </summary>
        private static object CreateClientInstance(Type clientType, string apiKey, string endpoint, string deploymentName)
        {
            if (clientType == null)
            {
                throw new ArgumentNullException(nameof(clientType));
            }

            // Try with (apiKey, endpoint, deploymentName) constructor
            var constructor = clientType.GetConstructor(new[] { typeof(string), typeof(string), typeof(string) });
            if (constructor != null)
            {
                var instance = constructor.Invoke(new object[] { apiKey, endpoint, deploymentName });
                if (instance == null)
                {
                    throw new InvalidOperationException("Failed to create Azure OpenAI client instance");
                }
                return instance;
            }

            // Try with (apiKey, endpoint) constructor
            constructor = clientType.GetConstructor(new[] { typeof(string), typeof(string) });
            if (constructor != null)
            {
                var client = constructor.Invoke(new object[] { apiKey, endpoint });
                if (client == null)
                {
                    throw new InvalidOperationException("Failed to create Azure OpenAI client instance");
                }

                // Try to set deployment name property
                var deploymentProperty = clientType.GetProperty("DeploymentName");
                if (deploymentProperty != null && deploymentProperty.CanWrite)
                {
                    deploymentProperty.SetValue(client, deploymentName);
                }

                return client;
            }

            throw new InvalidOperationException("Could not find a suitable constructor for the Azure OpenAI client");
        }

        /// <summary>
        /// Gets a method from a type with proper error handling
        /// </summary>
        private static MethodInfo GetMethod(Type type, string methodName)
        {
            if (type == null)
            {
                throw new ArgumentNullException(nameof(type));
            }

            if (string.IsNullOrEmpty(methodName))
            {
                throw new ArgumentNullException(nameof(methodName));
            }

            var method = type.GetMethod(methodName);
            if (method == null)
            {
                throw new InvalidOperationException($"Method {methodName} not found on type {type.FullName}");
            }
            return method;
        }

        /// <summary>
        /// Gets a method from a type or returns null if not found
        /// </summary>
        private static MethodInfo? GetMethodOrDefault(Type? type, string methodName)
        {
            if (type == null || string.IsNullOrEmpty(methodName))
            {
                return null;
            }

            return type.GetMethod(methodName);
        }

        /// <summary>
        /// Implements IDisposable to clean up resources
        /// </summary>
        public void Dispose()
        {
            // Dispose the client if it implements IDisposable
            if (_azureClient is IDisposable disposable)
            {
                disposable.Dispose();
            }
        }
    }

}
