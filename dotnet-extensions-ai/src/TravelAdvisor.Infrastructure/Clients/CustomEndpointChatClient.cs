using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using System;
using System.Collections.Generic;
using System.Linq;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text.Json;
using System.Threading;
using System.Threading.Tasks;
using TravelAdvisor.Infrastructure.Utilities;

namespace TravelAdvisor.Infrastructure.Clients
{
    /// <summary>
    /// Custom implementation of IChatClient that works with custom OpenAI-compatible endpoints
    /// </summary>
    public class CustomEndpointChatClient : IChatClient
    {
        private readonly HttpClient _httpClient;
        private readonly string _model;
        private readonly string _apiKey;
        private readonly ILogger _logger;

        /// <summary>
        /// Creates a new custom endpoint client
        /// </summary>
        public CustomEndpointChatClient(HttpClient httpClient, string model, string apiKey, ILogger logger)
        {
            _httpClient = httpClient ?? throw new ArgumentNullException(nameof(httpClient));
            _model = model ?? throw new ArgumentNullException(nameof(model));
            _apiKey = apiKey ?? throw new ArgumentNullException(nameof(apiKey));
            _logger = logger ?? throw new ArgumentNullException(nameof(logger));
        }

        /// <summary>
        /// Gets a response from the custom chat endpoint
        /// </summary>
        public async Task<ChatResponse> GetResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            _logger.LogInformation($"Processing {messages.Count()} messages");

            try
            {
                // Convert messages to the format expected by OpenAI API
                var messagesList = messages.Select(m => new
                {
                    role = m.Role.ToString().ToLower(),
                    content = GetMessageContent(m)
                }).ToList();

                // Create the request body
                var requestBody = new
                {
                    model = _model,
                    messages = messagesList,
                    temperature = options?.Temperature ?? 0.7,
                    max_tokens = options?.MaxOutputTokens ?? 8192
                };

                // Serialize the request
                var jsonRequest = JsonSerializer.Serialize(requestBody);
                _logger.LogDebug($"Request body: {jsonRequest}");

                // Create a new HttpClient to avoid header conflicts
                using var client = new HttpClient
                {
                    BaseAddress = _httpClient.BaseAddress
                };

                // Set up headers
                client.DefaultRequestHeaders.Authorization = new AuthenticationHeaderValue("Bearer", _apiKey);
                client.DefaultRequestHeaders.Accept.Add(new MediaTypeWithQualityHeaderValue("application/json"));

                // Create the request content
                var content = new StringContent(jsonRequest, System.Text.Encoding.UTF8, "application/json");

                // Send the request
                string relativePath = "v1/chat/completions";
                var response = await client.PostAsync(relativePath, content, cancellationToken);

                // Read the response
                var responseContent = await response.Content.ReadAsStringAsync(cancellationToken);

                // Process the response
                if (response.IsSuccessStatusCode)
                {
                    return ProcessSuccessfulResponse(responseContent);
                }
                else
                {
                    // Log the error and throw
                    _logger.LogError($"Request failed: {response.StatusCode} - {responseContent}");
                    return CreateErrorResponse($"HTTP {(int)response.StatusCode}: {responseContent}");
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error calling custom endpoint");
                return CreateErrorResponse($"Error calling custom endpoint: {ex.Message}");
            }
        }

        /// <summary>
        /// Gets a streaming response from the custom chat endpoint (not implemented)
        /// </summary>
        public IAsyncEnumerable<ChatResponseUpdate> GetStreamingResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            _logger.LogWarning("Streaming is not supported in CustomEndpointChatClient");
            return AsyncEnumerableUtilities.EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
        }

        /// <summary>
        /// Get the metadata for this client
        /// </summary>
        public ChatClientMetadata Metadata => new ChatClientMetadata();

        /// <summary>
        /// Get a service from this client (not implemented)
        /// </summary>
        public object? GetService(Type serviceType, object? key = null)
        {
            return null;
        }

        /// <summary>
        /// Dispose of resources
        /// </summary>
        public void Dispose()
        {
            // Don't dispose the HttpClient as it might be shared
        }

        /// <summary>
        /// Process a successful API response
        /// </summary>
        private ChatResponse ProcessSuccessfulResponse(string responseContent)
        {
            var chatResponse = new ChatResponse();

            try
            {
                using var jsonDoc = JsonDocument.Parse(responseContent);
                var root = jsonDoc.RootElement;

                // Extract content from the choices array
                if (root.TryGetProperty("choices", out var choices) &&
                    choices.GetArrayLength() > 0 &&
                    choices[0].TryGetProperty("message", out var message) &&
                    message.TryGetProperty("content", out var contentElement))
                {
                    var content = contentElement.GetString() ?? "";
                    var role = ChatRole.Assistant;

                    // Try to get the role
                    if (message.TryGetProperty("role", out var roleElement))
                    {
                        var roleStr = roleElement.GetString();
                        if (!string.IsNullOrEmpty(roleStr) &&
                            Enum.TryParse<ChatRole>(roleStr, true, out var parsedRole))
                        {
                            role = parsedRole;
                        }
                    }

                    // Add the message to the response
                    chatResponse.Messages.Add(new ChatMessage(role, content));
                    return chatResponse;
                }
                else
                {
                    // If we can't find the expected structure, use the raw response
                    _logger.LogWarning("Could not extract content from standard response structure");
                    chatResponse.Messages.Add(new ChatMessage(ChatRole.Assistant, responseContent));
                    return chatResponse;
                }
            }
            catch (Exception ex)
            {
                _logger.LogError(ex, "Error parsing API response");
                chatResponse.Messages.Add(new ChatMessage(ChatRole.Assistant,
                    $"Error parsing API response: {ex.Message}"));
                return chatResponse;
            }
        }

        /// <summary>
        /// Create an error response
        /// </summary>
        private ChatResponse CreateErrorResponse(string errorMessage)
        {
            var response = new ChatResponse();
            response.Messages.Add(new ChatMessage(ChatRole.Assistant, errorMessage));
            return response;
        }

        /// <summary>
        /// Get the content from a chat message
        /// </summary>
        private string GetMessageContent(ChatMessage message)
        {
            // The newer versions of Microsoft.Extensions.AI properly expose the Text property
            return message.Text ?? string.Empty;
        }
    }

}
