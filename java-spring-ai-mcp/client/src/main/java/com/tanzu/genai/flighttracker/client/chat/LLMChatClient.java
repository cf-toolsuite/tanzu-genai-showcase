package com.tanzu.genai.flighttracker.client.chat;

import org.springframework.ai.chat.ChatClient;
import org.springframework.ai.chat.ChatResponse;
import org.springframework.ai.chat.StreamingChatClient;
import org.springframework.ai.chat.model.ChatModel;
import org.springframework.ai.chat.model.StreamingChatModel;
import org.springframework.ai.chat.prompt.Prompt;
import org.springframework.ai.model.function.FunctionCallbackContext;
import org.springframework.context.ApplicationEventPublisher;
import reactor.core.publisher.Flux;

import java.util.concurrent.CompletableFuture;

/**
 * Chat client implementation that uses a chat model to generate responses.
 */
public class LLMChatClient implements ChatClient, StreamingChatClient {

    private final ChatModel chatModel;
    private final StreamingChatModel streamingChatModel;
    private final FunctionCallbackContext functionCallbackContext;
    private final ApplicationEventPublisher applicationEventPublisher;

    /**
     * Creates a new LLMChatClient.
     *
     * @param chatModel The chat model to use
     * @param functionCallbackContext The function callback context
     * @param applicationEventPublisher The application event publisher
     */
    public LLMChatClient(ChatModel chatModel, FunctionCallbackContext functionCallbackContext, ApplicationEventPublisher applicationEventPublisher) {
        this.chatModel = chatModel;
        this.streamingChatModel = chatModel instanceof StreamingChatModel ? (StreamingChatModel) chatModel : null;
        this.functionCallbackContext = functionCallbackContext;
        this.applicationEventPublisher = applicationEventPublisher;
    }

    /**
     * Calls the chat model with the given prompt.
     *
     * @param prompt The prompt to send to the chat model
     * @return The response from the chat model
     */
    @Override
    public ChatResponse call(Prompt prompt) {
        return chatModel.call(prompt);
    }

    /**
     * Calls the chat model with the given prompt and returns a future.
     *
     * @param prompt The prompt to send to the chat model
     * @return A future that will complete with the response from the chat model
     */
    @Override
    public CompletableFuture<ChatResponse> callAsync(Prompt prompt) {
        return CompletableFuture.supplyAsync(() -> call(prompt));
    }

    /**
     * Streams the response from the chat model.
     *
     * @param prompt The prompt to send to the chat model
     * @return A flux of chat responses
     */
    @Override
    public Flux<ChatResponse> stream(Prompt prompt) {
        if (streamingChatModel == null) {
            return Flux.error(new UnsupportedOperationException("Streaming is not supported by the underlying model"));
        }
        return streamingChatModel.stream(prompt);
    }
}
