package com.example.tanzu.genai.eventrecommendation.service;

import com.example.tanzu.genai.eventrecommendation.client.CitiesApiClient;
import com.example.tanzu.genai.eventrecommendation.graph.ChatbotState;
import com.example.tanzu.genai.eventrecommendation.model.*;
import lombok.extern.slf4j.Slf4j;
import org.bsc.langgraph4j.StatefulGraph;
import org.springframework.ai.chat.model.ChatModel;
import org.springframework.ai.chat.prompt.Prompt;
import org.springframework.ai.mcp.client.SyncMcpToolCallbackProvider;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.stereotype.Service;
import reactor.core.publisher.Mono;

import java.time.LocalDateTime;
import java.util.*;
import java.util.concurrent.ConcurrentHashMap;

@Slf4j
@Service
public class ChatService {

    private final Map<String, ChatSession> chatSessions = new ConcurrentHashMap<>();
    private final ChatModel chatModel;
    private final CitiesApiClient citiesApiClient;
    private final SyncMcpToolCallbackProvider toolCallbackProvider;
    private final StatefulGraph<ChatbotState> chatbotGraph;

    @Autowired
    public ChatService(
            ChatModel chatModel,
            CitiesApiClient citiesApiClient,
            SyncMcpToolCallbackProvider toolCallbackProvider,
            StatefulGraph<ChatbotState> chatbotGraph) {
        this.chatModel = chatModel;
        this.citiesApiClient = citiesApiClient;
        this.toolCallbackProvider = toolCallbackProvider;
        this.chatbotGraph = chatbotGraph;
    }

    public ChatSession createSession() {
        ChatSession session = ChatSession.builder()
                .createdAt(LocalDateTime.now())
                .lastUpdatedAt(LocalDateTime.now())
                .build();

        // Add initial welcome message
        ChatMessage welcomeMessage = ChatMessage.builder()
                .id(UUID.randomUUID().toString())
                .role("assistant")
                .content("Welcome! I can help you find events and activities. Ask me about events in any city, or tell me what kind of events you're interested in.")
                .timestamp(LocalDateTime.now())
                .build();

        session.addMessage(welcomeMessage);
        chatSessions.put(session.getId(), session);

        return session;
    }

    public ChatSession getSession(String sessionId) {
        return chatSessions.get(sessionId);
    }

    public Mono<ChatResponse> processMessage(String sessionId, String messageText) {
        ChatSession session = chatSessions.get(sessionId);

        if (session == null) {
            session = createSession();
        }

        // Create user message
        ChatMessage userMessage = ChatMessage.builder()
                .id(UUID.randomUUID().toString())
                .role("user")
                .content(messageText)
                .timestamp(LocalDateTime.now())
                .build();

        session.addMessage(userMessage);

        // Initialize chatbot state
        Map<String, Object> initData = new HashMap<>();
        initData.put("messages", session.getMessages());
        initData.put("events", session.getRecommendedEvents());
        if (session.getCurrentCity() != null) {
            initData.put("city", session.getCurrentCity());
        }

        ChatbotState initialState = new ChatbotState(initData);

        // Here you would process through your LangGraph workflow
        // This is a placeholder for actual graph processing

        // For now, create a simple response
        return createSimpleResponse(session, messageText);
    }

    private Mono<ChatResponse> createSimpleResponse(ChatSession session, String userMessage) {
        // Check if message contains city name (very basic implementation)
        if (userMessage.toLowerCase().contains("events in ")) {
            String cityName = extractCityName(userMessage);
            if (cityName != null) {
                return citiesApiClient.getCityByName(cityName)
                        .flatMap(cityInfoList -> {
                            if (cityInfoList.isEmpty()) {
                                return createTextResponse(session,
                                        "I couldn't find information about " + cityName + ". Could you provide more details or try another city?");
                            }

                            CityInfo cityInfo = cityInfoList.get(0);
                            session.setCurrentCity(cityInfo);

                            // Here you would normally call Ticketmaster MCP server
                            // For now, create some mock event data
                            List<EventInfo> mockEvents = createMockEvents(cityInfo);
                            session.setRecommendedEvents(mockEvents);

                            ChatMessage assistantMessage = ChatMessage.builder()
                                    .id(UUID.randomUUID().toString())
                                    .role("assistant")
                                    .content("I found some events in " + cityInfo.getName() + "! Here are some recommendations.")
                                    .timestamp(LocalDateTime.now())
                                    .type(ChatMessage.MessageType.EVENT_RECOMMENDATION)
                                    .build();

                            session.addMessage(assistantMessage);

                            return Mono.just(ChatResponse.builder()
                                    .sessionId(session.getId())
                                    .message(assistantMessage)
                                    .recommendedEvents(mockEvents)
                                    .cityInfo(cityInfo)
                                    .build());
                        });
            }
        }

        // Default text response
        return createTextResponse(session,
                "I'm your event recommendation assistant. Ask me about events in a specific city, like 'Show me events in New York'.");
    }

    private Mono<ChatResponse> createTextResponse(ChatSession session, String content) {
        ChatMessage assistantMessage = ChatMessage.builder()
                .id(UUID.randomUUID().toString())
                .role("assistant")
                .content(content)
                .timestamp(LocalDateTime.now())
                .build();

        session.addMessage(assistantMessage);

        return Mono.just(ChatResponse.builder()
                .sessionId(session.getId())
                .message(assistantMessage)
                .build());
    }

    private String extractCityName(String message) {
        message = message.toLowerCase();
        int index = message.indexOf("events in ");
        if (index != -1) {
            return message.substring(index + 10).trim();
        }
        return null;
    }

    private List<EventInfo> createMockEvents(CityInfo cityInfo) {
        // Create some mock events for demo purposes
        List<EventInfo> events = new ArrayList<>();

        events.add(EventInfo.builder()
                .id("e1")
                .name("Summer Music Festival")
                .type("Music")
                .url("https://example.com/events/summer-music-festival")
                .startDateTime(LocalDateTime.now().plusDays(5))
                .venue("Central Park")
                .city(cityInfo.getName())
                .country(cityInfo.getCountry())
                .priceRange("$45 - $120")
                .genre("Music")
                .subGenre("Festival")
                .familyFriendly(true)
                .build());

        events.add(EventInfo.builder()
                .id("e2")
                .name("International Film Showcase")
                .type("Arts & Theatre")
                .url("https://example.com/events/film-showcase")
                .startDateTime(LocalDateTime.now().plusDays(10))
                .venue("Downtown Cinema")
                .city(cityInfo.getName())
                .country(cityInfo.getCountry())
                .priceRange("$15 - $30")
                .genre("Film")
                .subGenre("Festival")
                .familyFriendly(true)
                .build());

        events.add(EventInfo.builder()
                .id("e3")
                .name("Tech Conference 2025")
                .type("Conference")
                .url("https://example.com/events/tech-conference")
                .startDateTime(LocalDateTime.now().plusDays(14))
                .venue("Convention Center")
                .city(cityInfo.getName())
                .country(cityInfo.getCountry())
                .priceRange("$199 - $499")
                .genre("Technology")
                .subGenre("Conference")
                .familyFriendly(false)
                .build());

        return events;
    }
}
