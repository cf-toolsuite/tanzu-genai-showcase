using Microsoft.Extensions.AI;
using Microsoft.Extensions.Logging;
using System;
using System.Collections.Generic;
using System.Threading;
using System.Threading.Tasks;
using TravelAdvisor.Infrastructure.Utilities;

namespace TravelAdvisor.Infrastructure.Clients
{
    /// <summary>
    /// A mock implementation of IChatClient for development/testing
    /// </summary>
    public class MockChatClient : IChatClient
    {
        private readonly ILogger<MockChatClient>? _logger;
        private readonly bool _useMockData;

        /// <summary>
        /// Creates a new mock chat client
        /// </summary>
        public MockChatClient(ILogger<MockChatClient>? logger = null)
            : this(logger, true)
        {
        }

        /// <summary>
        /// Creates a new mock chat client with configuration
        /// </summary>
        public MockChatClient(ILogger<MockChatClient>? logger, bool useMockData)
        {
            _logger = logger;
            _useMockData = useMockData;
            _logger?.LogInformation($"MockChatClient initialized with useMockData={useMockData}");
        }

        /// <summary>
        /// Gets a mock response
        /// </summary>
        public Task<ChatResponse> GetResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            var chatResponse = new ChatResponse();

            // If mock data is explicitly disabled, return an error message
            if (!_useMockData)
            {
                _logger?.LogError("Mock data is disabled but GenAI service is not configured properly");

                string errorMessage = "GenAI service credentials not found or not properly configured. " +
                                    "Please configure credentials using one of the following methods:\n" +
                                    "1. Bind a GenAI service instance to this application\n" +
                                    "2. Set GENAI__APIKEY and GENAI__APIURL environment variables\n" +
                                    "3. Configure GenAI:ApiKey and GenAI:ApiUrl in appsettings.json\n\n" +
                                    "Alternatively, you can set USE_MOCK_DATA=true for development.";

                chatResponse.Messages.Add(new ChatMessage(ChatRole.Assistant, errorMessage));
                return Task.FromResult(chatResponse);
            }

            _logger?.LogInformation("Using mock AI service");

            // Provide a mock travel response
            chatResponse.Messages.Add(new ChatMessage(ChatRole.Assistant, GetMockTravelResponse()));
            return Task.FromResult(chatResponse);
        }

        /// <summary>
        /// Gets a mock streaming response
        /// </summary>
        public IAsyncEnumerable<ChatResponseUpdate> GetStreamingResponseAsync(
            IEnumerable<ChatMessage> messages,
            ChatOptions? options = null,
            CancellationToken cancellationToken = default)
        {
            // Return an empty stream
            return AsyncEnumerableUtilities.EmptyAsyncEnumerable<ChatResponseUpdate>.Instance;
        }

        /// <summary>
        /// Get client metadata
        /// </summary>
        public ChatClientMetadata Metadata => new ChatClientMetadata();

        /// <summary>
        /// Get a service (not implemented)
        /// </summary>
        public object? GetService(Type serviceType, object? key = null)
        {
            return null;
        }

        /// <summary>
        /// Dispose resources
        /// </summary>
        public void Dispose() { }

        /// <summary>
        /// Gets a mock travel response
        /// </summary>
        private string GetMockTravelResponse()
        {
            return @"{
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
            }";
        }
    }
}
