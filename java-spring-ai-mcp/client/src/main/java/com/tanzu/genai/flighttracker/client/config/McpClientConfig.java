package com.tanzu.genai.flighttracker.client.config;

import org.springframework.beans.factory.annotation.Value;
import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.ai.chat.ChatClient;
import org.springframework.ai.chat.generation.LLMChatClient;
import org.springframework.ai.chat.model.ChatModel;
import org.springframework.ai.model.function.FunctionCallback;
import org.springframework.ai.model.function.FunctionCallbackContext;
import org.springframework.context.ApplicationEventPublisher;

/**
 * Configuration for the MCP client.
 */
@Configuration
public class McpClientConfig {

    @Value("${spring.ai.mcp.client.sse.connections.flight-tracker.url}")
    private String flightTrackerMcpServerUrl;

    /**
     * Configures the ChatClient with MCP capabilities.
     *
     * @param chatModel The ChatModel bean
     * @param functionCallbackContext The FunctionCallbackContext
     * @param applicationEventPublisher The ApplicationEventPublisher
     * @return A configured ChatClient
     */
    @Bean
    public ChatClient chatClient(
            ChatModel chatModel,
            FunctionCallbackContext functionCallbackContext,
            ApplicationEventPublisher applicationEventPublisher) {

        return new LLMChatClient(chatModel, functionCallbackContext, applicationEventPublisher);
    }
}
