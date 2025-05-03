package com.example.tanzu.genai.eventrecommendation.graph;

import com.example.tanzu.genai.eventrecommendation.client.CitiesApiClient;
import com.example.tanzu.genai.eventrecommendation.model.ChatMessage;
import com.example.tanzu.genai.eventrecommendation.model.CityInfo;
import lombok.extern.slf4j.Slf4j;
import org.bsc.langgraph4j.NodeAction;
import org.bsc.langgraph4j.NodeEdges;
import org.bsc.langgraph4j.StatefulGraph;
import org.springframework.ai.chat.ChatClient;
import org.springframework.ai.chat.messages.Message;
import org.springframework.ai.chat.messages.SystemMessage;
import org.springframework.ai.chat.messages.UserMessage;
import org.springframework.ai.chat.prompt.Prompt;
import org.springframework.ai.mcp.client.SyncMcpToolCallbackProvider;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;

import java.time.LocalDateTime;
import java.util.*;
import java.util.function.Function;

@Slf4j
@Configuration
public class ChatbotGraph {

    private final ChatClient chatClient;
    private final CitiesApiClient citiesApiClient;
    private final SyncMcpToolCallbackProvider toolCallbackProvider;

    @Autowired
    public ChatbotGraph(
            ChatClient chatClient,
            CitiesApiClient citiesApiClient,
            SyncMcpToolCallbackProvider toolCallbackProvider) {
        this.chatClient = chatClient;
        this.citiesApiClient = citiesApiClient;
        this.toolCallbackProvider = toolCallbackProvider;
    }

    @Bean
    public StatefulGraph<ChatbotState> chatbotGraph() {
        StatefulGraph<ChatbotState> graph = StatefulGraph.builder(ChatbotState.class)
                .addNode("understand_user_intent", understandUserIntentNode())
                .addNode("check_for_city", checkForCityNode())
                .addNode("search_events", searchEventsNode())
                .addNode("generate_response", generateResponseNode())
                .addEdge("understand_user_intent", routeToNextNode())
                .addEdge("check_for_city", "search_events")
                .addEdge("search_events", "generate_response")
                .setEntryPoint("understand_user_intent")
                .build();

        return graph;
    }

    private NodeAction<ChatbotState> understandUserIntentNode() {
        return (state) -> {
            log.info("Understanding user intent");

            List<ChatMessage> messages = state.getMessages();
            ChatMessage latestUserMessage = findLatestUserMessage(messages);

            if (latestUserMessage == null) {
                return Map.of("userIntent", "greeting", "nextAction", "generate_response");
            }

            // Use AI to understand user intent
            String userContent = latestUserMessage.getContent();

            SystemMessage systemMessage = new SystemMessage("""
                    Analyze the user message and determine their primary intent.
                    Respond with one of the following intents:
                    - event_search: User is looking for events or activities
                    - city_information: User is asking about a specific city
                    - event_details: User is asking for details about specific events
                    - greeting: User is just saying hello or starting a conversation
                    - other: Any other intent

                    Include the city name if detected in JSON format: {"intent": "intent_type", "city": "city_name"}
                    If no city is mentioned, return: {"intent": "intent_type", "city": null}
                    """);

            UserMessage userMessage = new UserMessage(userContent);
            Prompt prompt = new Prompt(List.of(systemMessage, userMessage));
            String intentAnalysis = chatClient.call(prompt).getResult().getOutput().getContent();

            log.debug("Intent analysis: {}", intentAnalysis);

            // This would be better handled with proper JSON parsing
            String intent = "other";
            String city = null;

            if (intentAnalysis.contains("event_search")) {
                intent = "event_search";
            } else if (intentAnalysis.contains("city_information")) {
                intent = "city_information";
            } else if (intentAnalysis.contains("event_details")) {
                intent = "event_details";
            } else if (intentAnalysis.contains("greeting")) {
                intent = "greeting";
            }

            // Extract city name (simple implementation)
            if (intentAnalysis.contains("\"city\":")) {
                int cityStart = intentAnalysis.indexOf("\"city\":") + 8;
                int cityEnd = intentAnalysis.indexOf("\"", cityStart);
                if (cityEnd > cityStart) {
                    city = intentAnalysis.substring(cityStart, cityEnd);
                    if (city.equals("null")) {
                        city = null;
                    }
                }
            }

            // Set intent and city in state
            Map<String, Object> stateUpdates = new HashMap<>();
            stateUpdates.put("userIntent", intent);

            // Determine next action based on intent
            String nextAction = "generate_response";
            if ((intent.equals("event_search") || intent.equals("city_information")) && city != null) {
                stateUpdates.put("nextAction", "check_for_city");
                stateUpdates.put("cityName", city);
            } else {
                stateUpdates.put("nextAction", "generate_response");
            }

            return stateUpdates;
        };
    }

    private NodeAction<ChatbotState> checkForCityNode() {
        return (state) -> {
            log.info("Checking for city information");

            String cityName = (String) state.getData().get("cityName");
            if (cityName == null) {
                return Map.of();
            }

            // In a real implementation, this would use the CitiesApiClient
            // For simplicity, we're creating a mock city info
            CityInfo cityInfo = CityInfo.builder()
                    .name(cityName)
                    .country("United States")
                    .latitude(40.7128)
                    .longitude(-74.0060)
                    .population(8399000L)
                    .isCapital(false)
                    .build();

            Map<String, Object> stateUpdates = new HashMap<>();
            stateUpdates.put("cityInfo", cityInfo);

            return stateUpdates;
        };
    }

    private NodeAction<ChatbotState> searchEventsNode() {
        return (state) -> {
            log.info("Searching for events");

            CityInfo cityInfo = (CityInfo) state.getData().get("cityInfo");
            if (cityInfo == null) {
                return Map.of();
            }

            // In a real implementation, this would use the Ticketmaster MCP server
            // For simplicity, we're creating mock events

            // Mock events list - would normally come from MCP tool callback
            List<Map<String, Object>> mockEvents = new ArrayList<>();
            mockEvents.add(Map.of(
                "name", "Summer Music Festival",
                "venue", "Central Park",
                "date", "2025-04-10",
                "type", "Music"
            ));

            mockEvents.add(Map.of(
                "name", "Broadway Show: Hamilton",
                "venue", "Richard Rodgers Theatre",
                "date", "2025-04-15",
                "type", "Arts & Theatre"
            ));

            Map<String, Object> stateUpdates = new HashMap<>();
            stateUpdates.put("events", mockEvents);

            return stateUpdates;
        };
    }

    private NodeAction<ChatbotState> generateResponseNode() {
        return (state) -> {
            log.info("Generating response");

            List<ChatMessage> messages = state.getMessages();
            ChatMessage latestUserMessage = findLatestUserMessage(messages);

            if (latestUserMessage == null) {
                return Map.of();
            }

            // Build context for response generation
            String userIntent = (String) state.getData().getOrDefault("userIntent", "other");
            CityInfo cityInfo = (CityInfo) state.getData().get("cityInfo");
            List<Map<String, Object>> events = (List<Map<String, Object>>) state.getData().get("events");

            // Generate response with AI
            List<Message> promptMessages = new ArrayList<>();

            String systemPrompt = """
                    You are a helpful event recommendation assistant. Your goal is to help users find events and activities they might enjoy.
                    Be conversational, friendly, and helpful.

                    If the user is asking about events in a specific city, provide information about events that are happening there.
                    If the user is asking for information about a city, provide general information about the city.

                    Keep your responses concise and focused on the user's request.
                    """;

            promptMessages.add(new SystemMessage(systemPrompt));

            // Add conversation history
            for (ChatMessage message : messages) {
                if ("user".equals(message.getRole())) {
                    promptMessages.add(new UserMessage(message.getContent()));
                }
            }

            // Add context about city and events if available
            StringBuilder contextBuilder = new StringBuilder();
            if (cityInfo != null) {
                contextBuilder.append("City Information:\n");
                contextBuilder.append("Name: ").append(cityInfo.getName()).append("\n");
                contextBuilder.append("Country: ").append(cityInfo.getCountry()).append("\n");
                contextBuilder.append("Population: ").append(cityInfo.getPopulation()).append("\n\n");
            }

            if (events != null && !events.isEmpty()) {
                contextBuilder.append("Events in ").append(cityInfo != null ? cityInfo.getName() : "this city").append(":\n");
                for (Map<String, Object> event : events) {
                    contextBuilder.append("- ").append(event.get("name")).append(" at ")
                            .append(event.get("venue")).append(" on ")
                            .append(event.get("date")).append(" (").append(event.get("type")).append(")\n");
                }
            }

            if (contextBuilder.length() > 0) {
                promptMessages.add(new SystemMessage("Context information:\n" + contextBuilder.toString()));
            }

            // Add the most recent user message again for focus
            promptMessages.add(new UserMessage(latestUserMessage.getContent()));

            Prompt prompt = new Prompt(promptMessages);
            String responseContent = chatClient.call(prompt).getResult().getOutput().getContent();

            // Create a response message
            ChatMessage responseMessage = ChatMessage.builder()
                    .id(UUID.randomUUID().toString())
                    .role("assistant")
                    .content(responseContent)
                    .timestamp(LocalDateTime.now())
                    .build();

            Map<String, Object> stateUpdates = new HashMap<>();
            stateUpdates.put("responseMessage", responseMessage);

            return stateUpdates;
        };
    }

    private Function<ChatbotState, String> routeToNextNode() {
        return (state) -> {
            String nextAction = (String) state.getData().get("nextAction");
            if (nextAction == null) {
                return "generate_response";
            }
            return nextAction;
        };
    }

    private ChatMessage findLatestUserMessage(List<ChatMessage> messages) {
        if (messages == null || messages.isEmpty()) {
            return null;
        }

        for (int i = messages.size() - 1; i >= 0; i--) {
            ChatMessage message = messages.get(i);
            if ("user".equals(message.getRole())) {
                return message;
            }
        }

        return null;
    }
}
