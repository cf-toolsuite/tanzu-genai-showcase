package com.example.tanzu.genai.eventrecommendation.config;

import com.fasterxml.jackson.databind.ObjectMapper;
import jakarta.annotation.PostConstruct;
import lombok.extern.slf4j.Slf4j;
import org.springframework.ai.chat.messages.Message;
import org.springframework.ai.chat.messages.SystemMessage;
import org.springframework.ai.chat.messages.UserMessage;
import org.springframework.ai.chat.model.ChatModel;
import org.springframework.ai.chat.model.ChatResponse;
import org.springframework.ai.chat.prompt.Prompt;
import org.springframework.ai.chat.prompt.PromptTemplate;
import org.springframework.ai.mcp.client.MimetypeContext;
import org.springframework.ai.mcp.client.SyncMcpToolCallbackProvider;
import org.springframework.ai.mcp.client.SyncToolCallback;
import org.springframework.ai.mcp.client.SyncToolCallbackFactory;
import org.springframework.ai.mcp.client.exchange.McpSyncExchange;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.util.MimeTypeUtils;

import java.util.List;
import java.util.Map;

@Slf4j
@Configuration
public class McpConfig {

    private final ObjectMapper objectMapper;
    private final ChatModel chatModel;

    @Autowired
    public McpConfig(ObjectMapper objectMapper, ChatModel chatModel) {
        this.objectMapper = objectMapper;
        this.chatModel = chatModel;
    }

    @PostConstruct
    public void init() {
        log.info("MCP Configuration initialized");
    }

    @Bean
    public SyncMcpToolCallbackProvider syncMcpToolCallbackProvider() {
        return new SyncMcpToolCallbackProvider(List.of(
                chatAssistantTool()
        ));
    }

    private SyncToolCallback chatAssistantTool() {
        return SyncToolCallbackFactory.builder(objectMapper)
                .name("chat_assistant")
                .description("Chat assistant for conversational responses")
                .mimetypeContexts(List.of(new MimetypeContext("text/plain", MimeTypeUtils.TEXT_PLAIN_VALUE)))
                .function((McpSyncExchange exchange, Map<String, Object> params) -> {
                    String userMessage = (String) params.get("message");
                    PromptTemplate promptTemplate = new PromptTemplate("""
                            You are a helpful assistant providing information about events and activities.
                            When users ask about events or activities in specific cities, suggest they try the search_events functionality.

                            User message: {message}
                            """);

                    Message systemMessage = new SystemMessage(promptTemplate.render(Map.of("message", userMessage)));
                    Message userMessageObj = new UserMessage(userMessage);

                    Prompt prompt = new Prompt(List.of(systemMessage, userMessageObj));
                    ChatResponse response = chatModel.call(prompt);

                    return response.getResult().getOutput().getContent();
                })
                .build();
    }
}
