using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using OpenAI;
using OpenAI.Chat;
using System.ClientModel;
using TravelAdvisor.Infrastructure.Options;

namespace TravelAdvisor.Infrastructure.Clients;

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

/// <inheritdoc/>
public class AIClientFactory : IAIClientFactory
{
    /// <inheritdoc/>
    public IChatClient CreateClient(GenAIOptions options, ILogger logger)
    {
        // Validate essential options
        ValidateOptions(options);

        // Log configuration details (masking sensitive info)
        LogConfiguration(options, logger);

        // Create appropriate client based on API URL
        return IsAzureOpenAI(options.ApiUrl)
            ? CreateAzureOpenAIClient(options, logger)
            : CreateOpenAIClient(options, logger);
    }

    /// <summary>
    /// Validates that the required options are present
    /// </summary>
    private static void ValidateOptions(GenAIOptions options)
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
    private static void LogConfiguration(GenAIOptions options, ILogger logger)
    {
        logger.LogInformation("Configuring AI client with:");
        // Mask API key for security
        logger.LogInformation("API Key: {MaskApiKey}", MaskApiKey(options.ApiKey));
        logger.LogInformation("API URL: {OptionsApiUrl}", options.ApiUrl);
        logger.LogInformation("Model: {OptionsModel}", options.Model);

        if (!string.IsNullOrEmpty(options.ServiceName))
        {
            logger.LogInformation("Service Name: {OptionsServiceName}", options.ServiceName);
        }
    }

    /// <summary>
    /// Creates an Azure OpenAI client
    /// </summary>
    private static IChatClient CreateAzureOpenAIClient(GenAIOptions options, ILogger logger)
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
    private static IChatClient CreateOpenAIClient(GenAIOptions options, ILogger logger)
    {
        logger.LogInformation("Creating standard OpenAI client");

        try
        {
            var openAiClientOptions = new OpenAIClientOptions { Endpoint = new Uri($"{options.ApiUrl}/openai") };
            var apiKeyCredential = new ApiKeyCredential(options.ApiKey);

            return new ChatClient(options.Model, apiKeyCredential, openAiClientOptions).AsIChatClient();
        }
        catch (Exception ex)
        {
            logger.LogError(ex, "Error creating standard OpenAI client");
            throw new InvalidOperationException($"Failed to create OpenAI client: {ex.Message}", ex);
        }
    }


    /// <summary>
    /// Checks if the API URL is for Azure OpenAI
    /// </summary>
    private static bool IsAzureOpenAI(string apiUrl)
    {
        return !string.IsNullOrEmpty(apiUrl) && apiUrl.Contains("openai.azure.com");
    }

    /// <summary>
    /// Masks API key for logging purposes
    /// </summary>
    private static string MaskApiKey(string apiKey)
    {
        if (string.IsNullOrEmpty(apiKey))
        {
            return "Not provided";
        }

        if (apiKey.Length <= 8)
        {
            return $"***{apiKey[^3..]}";
        }

        return apiKey[..3] + "..." + apiKey[^3..];
    }
}
