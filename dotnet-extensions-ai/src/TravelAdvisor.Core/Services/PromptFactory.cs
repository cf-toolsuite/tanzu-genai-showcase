using System.Reflection;
using Microsoft.Extensions.AI;

namespace TravelAdvisor.Core.Services
{
    /// <summary>
    /// Implementation of IPromptFactory using Microsoft.Extensions.AI
    /// </summary>
    public class PromptFactory : IPromptFactory
    {
        private readonly IChatClient _chatClient;

        /// <summary>
        /// Constructor
        /// </summary>
        /// <param name="chatClient">Chat client to use for completions</param>
        public PromptFactory(IChatClient chatClient)
        {
            _chatClient = chatClient ?? throw new ArgumentNullException(nameof(chatClient));
        }

        /// <summary>
        /// Creates a chat prompt with the given system message template
        /// </summary>
        /// <param name="systemMessage">The system message template</param>
        /// <returns>A ChatMessageContentBuilder object</returns>
        public ChatMessageContentBuilder Create(string systemMessage)
        {
            return new ChatMessageContentBuilder(_chatClient, systemMessage);
        }
    }

    /// <summary>
    /// A builder for chat messages with parameter support
    /// </summary>
    public class ChatMessageContentBuilder
    {
        private readonly IChatClient _chatClient;
        private readonly string _systemMessage;
        private readonly Dictionary<string, string> _parameters = new Dictionary<string, string>();

        /// <summary>
        /// Constructor
        /// </summary>
        /// <param name="chatClient">Chat client</param>
        /// <param name="systemMessage">System message template</param>
        public ChatMessageContentBuilder(IChatClient chatClient, string systemMessage)
        {
            _chatClient = chatClient ?? throw new ArgumentNullException(nameof(chatClient));
            _systemMessage = systemMessage ?? throw new ArgumentNullException(nameof(systemMessage));
        }

        /// <summary>
        /// Adds a parameter to the prompt
        /// </summary>
        /// <param name="key">Parameter name</param>
        /// <param name="value">Parameter value</param>
        /// <returns>This builder</returns>
        public ChatMessageContentBuilder AddParameter(string key, string value)
        {
            _parameters[key] = value;
            return this;
        }

        /// <summary>
        /// Gets the chat message content
        /// </summary>
        /// <returns>Chat message content</returns>
        public async Task<ChatResponse> GetChatMessageContentAsync()
        {
            // Format the system message with parameters
            var formattedSystemMessage = FormatWithParameters(_systemMessage);

            // Create a chat history with only a system message and get completion
            var history = new List<ChatMessage>
            {
                new ChatMessage(ChatRole.System, formattedSystemMessage)
            };

            return await _chatClient.GetResponseAsync(history, new ChatOptions { Temperature = 0 });
        }

        /// <summary>
        /// Gets the content from a chat response using reflection
        /// </summary>
        /// <param name="response">Chat response</param>
        /// <returns>Content as string or empty if not found</returns>
        public static string GetContentFromResponse(ChatResponse response)
        {
            if (response == null)
                return string.Empty;

            try
            {
                // Debug: Log the response type
                Console.WriteLine($"Response type: {response.GetType().FullName}");

                // Try to get the Content property via reflection
                var content = GetPropertyValue<string>(response, "Content");
                if (!string.IsNullOrEmpty(content))
                    return content;

                // Try to get the content property using case-insensitive property access
                var property = response.GetType().GetProperty("content", BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
                if (property != null)
                {
                    var value = property.GetValue(response);
                    return value?.ToString() ?? string.Empty;
                }

                // Try to get the content from a dictionary of properties (used in newer versions)
                // Check if there's a method to get properties
                var getPropertiesMethod = response.GetType().GetMethod("GetProperties");
                if (getPropertiesMethod != null)
                {
                    var properties = getPropertiesMethod.Invoke(response, null) as IDictionary<string, object>;
                    if (properties != null)
                    {
                        Console.WriteLine($"Properties found: {string.Join(", ", properties.Keys)}");
                        if (properties.TryGetValue("content", out var contentValue))
                        {
                            return contentValue?.ToString() ?? string.Empty;
                        }
                    }
                }

                // Try to access properties through indexer
                var indexerProperty = response.GetType().GetProperty("Item", new[] { typeof(string) });
                if (indexerProperty != null)
                {
                    var contentValue = indexerProperty.GetValue(response, new object[] { "content" });
                    if (contentValue != null)
                    {
                        return contentValue.ToString() ?? string.Empty;
                    }
                }

                // Try to access choices collection (common in OpenAI responses)
                var choicesProperty = response.GetType().GetProperty("Choices", BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
                if (choicesProperty != null)
                {
                    var choices = choicesProperty.GetValue(response) as System.Collections.IEnumerable;
                    if (choices != null)
                    {
                        foreach (var choice in choices)
                        {
                            // Try to get message from choice
                            var messageProperty = choice.GetType().GetProperty("Message", BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
                            if (messageProperty != null)
                            {
                                var message = messageProperty.GetValue(choice);
                                if (message != null)
                                {
                                    // Try to get content from message
                                    var messageContentProperty = message.GetType().GetProperty("Content", BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
                                    if (messageContentProperty != null)
                                    {
                                        var messageContent = messageContentProperty.GetValue(message)?.ToString();
                                        if (!string.IsNullOrEmpty(messageContent))
                                            return messageContent;
                                    }
                                }
                            }

                            // Try to get text directly from choice
                            var textProperty = choice.GetType().GetProperty("Text", BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
                            if (textProperty != null)
                            {
                                var text = textProperty.GetValue(choice)?.ToString();
                                if (!string.IsNullOrEmpty(text))
                                    return text;
                            }
                        }
                    }
                }

                // Last resort: try to convert the entire response to string
                var responseString = response.ToString();
                if (!string.IsNullOrEmpty(responseString) && responseString != response.GetType().FullName)
                {
                    return responseString;
                }

                // If we get here, log all properties for debugging
                Console.WriteLine("Failed to extract content. Dumping all properties:");
                foreach (var prop in response.GetType().GetProperties(BindingFlags.Public | BindingFlags.Instance))
                {
                    try
                    {
                        var value = prop.GetValue(response);
                        Console.WriteLine($"Property: {prop.Name}, Value: {value}");
                    }
                    catch (Exception ex)
                    {
                        Console.WriteLine($"Error accessing property {prop.Name}: {ex.Message}");
                    }
                }
            }
            catch (Exception ex)
            {
                Console.WriteLine($"Error extracting content from response: {ex.Message}");
            }

            return string.Empty;
        }

        /// <summary>
        /// Gets a property value from an object using reflection
        /// </summary>
        private static T? GetPropertyValue<T>(object obj, string propertyName)
        {
            if (obj == null)
                return default;

            var property = obj.GetType().GetProperty(propertyName, BindingFlags.Public | BindingFlags.Instance | BindingFlags.IgnoreCase);
            if (property != null)
                return (T?)property.GetValue(obj);
            return default;
        }

        private string FormatWithParameters(string template)
        {
            var result = template;

            // Replace parameters using {{$paramName}} syntax
            foreach (var param in _parameters)
            {
                result = result.Replace($"{{{{${param.Key}}}}}", param.Value);
            }

            return result;
        }
    }
}
