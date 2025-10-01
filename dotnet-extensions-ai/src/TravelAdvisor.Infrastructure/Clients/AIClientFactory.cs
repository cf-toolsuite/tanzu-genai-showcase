using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using OpenAI;
using TravelAdvisor.Infrastructure.Options;

namespace TravelAdvisor.Infrastructure.Clients
{
    /// <summary>
    /// Interface for factory creating AI clients
    /// </summary>
    public interface IAIClientFactory
    {
        /// <summary>
        /// Creates an appropriate IChatClient based on the provided options
        /// </summary>
        IChatClient CreateClient(GenAIOptions options, ILogger logger);
    }

    /// <summary>
    /// Factory for creating the appropriate AI client based on configuration
    /// </summary>
    public class AIClientFactory : IAIClientFactory
    {
        /// <summary>
        /// Creates an appropriate IChatClient based on the provided options
        /// </summary>
        public IChatClient CreateClient(GenAIOptions options, ILogger logger)
        {
            // Validate essential options
            ValidateOptions(options);

            // Log configuration details (masking sensitive info)
            LogConfiguration(options, logger);

            // Create appropriate client based on API URL
            if (IsAzureOpenAI(options.ApiUrl))
            {
                return CreateAzureOpenAIClient(options, logger);
            }
            else if (IsOfficialOpenAIEndpoint(options.ApiUrl))
            {
                return CreateOfficialOpenAIClient(options, logger);
            }
            else
            {
                return CreateCustomEndpointClient(options, logger);
            }
        }

        /// <summary>
        /// Validates that the required options are present
        /// </summary>
        private void ValidateOptions(GenAIOptions options)
        {
            if (string.IsNullOrEmpty(options.ApiKey) || string.IsNullOrEmpty(options.ApiUrl))
            {
                throw new InvalidOperationException(
                    "GenAI API key and URL are required. " +
                    "Please configure credentials using one of the following methods:\n" +
                    "1. Bind a GenAI service instance to this application\n" +
                    "2. Set GENAI__APIKEY and GENAI__APIURL environment variables\n" +
                    "3. Configure GenAI:ApiKey and GenAI:ApiUrl in appsettings.json");
            }

            if (string.IsNullOrEmpty(options.Model))
            {
                throw new InvalidOperationException(
                    "GenAI model name is required. " +
                    "Please specify the model using GenAI:Model configuration.");
            }
        }

        /// <summary>
        /// Logs the configuration (without sensitive data)
        /// </summary>
        private void LogConfiguration(GenAIOptions options, ILogger logger)
        {
            logger.LogInformation("Configuring AI client with:");
            // Mask API key for security
            logger.LogInformation($"API Key: {MaskApiKey(options.ApiKey)}");
            logger.LogInformation($"API URL: {options.ApiUrl}");
            logger.LogInformation($"Model: {options.Model}");

            if (!string.IsNullOrEmpty(options.ServiceName))
            {
                logger.LogInformation($"Service Name: {options.ServiceName}");
            }
        }

        /// <summary>
        /// Creates an Azure OpenAI client
        /// </summary>
        private IChatClient CreateAzureOpenAIClient(GenAIOptions options, ILogger logger)
        {
            logger.LogInformation("Creating Azure OpenAI client");

            try
            {
                // Try to create the client directly using the Azure OpenAI SDK
                var client = new AzureOpenAIClientAdapter(
                    options.ApiKey,
                    options.ApiUrl,
                    options.Model);

                logger.LogInformation("Successfully created Azure OpenAI client");
                return client;
            }
            catch (Exception ex)
            {
                logger.LogError(ex, "Error creating Azure OpenAI client");
                throw new InvalidOperationException($"Failed to create Azure OpenAI client: {ex.Message}", ex);
            }
        }

        /// <summary>
        /// Creates an OpenAI client using the official SDK
        /// </summary>
        private IChatClient CreateOfficialOpenAIClient(GenAIOptions options, ILogger logger)
        {
            logger.LogInformation("Creating standard OpenAI client");

            try
            {
                // Create a direct OpenAI client with the API key
                var openAIClient = new OpenAIClient(options.ApiKey);

                // Get the chat client with the specified model
                var chatClient = openAIClient.GetChatClient(options.Model);
                logger.LogInformation($"Successfully created OpenAI client with model: {options.Model}");

                return chatClient.AsIChatClient();
            }
            catch (Exception ex)
            {
                logger.LogError(ex, "Error creating standard OpenAI client");
                throw new InvalidOperationException($"Failed to create OpenAI client: {ex.Message}", ex);
            }
        }

        /// <summary>
        /// Creates a client for custom OpenAI-compatible endpoints
        /// </summary>
        private IChatClient CreateCustomEndpointClient(GenAIOptions options, ILogger logger)
        {
            logger.LogInformation($"Creating client for custom endpoint: {options.ApiUrl}");

            try
            {
                // Create a HttpClient with the base address set to our custom endpoint
                var httpClient = new HttpClient
                {
                    BaseAddress = new Uri(options.ApiUrl)
                };

                // Add standard Authorization header
                httpClient.DefaultRequestHeaders.Add("Authorization", $"Bearer {options.ApiKey}");

                // Create the custom client implementation
                return new CustomEndpointChatClient(httpClient, options.Model, options.ApiKey, logger);
            }
            catch (Exception ex)
            {
                logger.LogError(ex, "Error creating custom endpoint client");
                throw new InvalidOperationException($"Failed to create custom endpoint client: {ex.Message}", ex);
            }
        }

        /// <summary>
        /// Checks if the API URL is for Azure OpenAI
        /// </summary>
        private bool IsAzureOpenAI(string apiUrl)
        {
            return !string.IsNullOrEmpty(apiUrl) && apiUrl.Contains("openai.azure.com");
        }

        /// <summary>
        /// Checks if the API URL is for the official OpenAI service
        /// </summary>
        private bool IsOfficialOpenAIEndpoint(string apiUrl)
        {
            return string.IsNullOrEmpty(apiUrl) || apiUrl.Contains("api.openai.com");
        }

        /// <summary>
        /// Masks API key for logging purposes
        /// </summary>
        private string MaskApiKey(string apiKey)
        {
            if (string.IsNullOrEmpty(apiKey))
            {
                return "Not provided";
            }

            if (apiKey.Length <= 8)
            {
                return "***" + apiKey.Substring(apiKey.Length - 3);
            }

            return apiKey.Substring(0, 3) + "..." + apiKey.Substring(apiKey.Length - 3);
        }
    }
}