package com.example.tanzu.genai.eventrecommendation.controller;

import com.example.tanzu.genai.eventrecommendation.model.ChatRequest;
import com.example.tanzu.genai.eventrecommendation.model.ChatResponse;
import com.example.tanzu.genai.eventrecommendation.model.ChatSession;
import com.example.tanzu.genai.eventrecommendation.service.ChatService;
import jakarta.validation.Valid;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Autowired;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import reactor.core.publisher.Mono;

@Slf4j
@RestController
@RequestMapping("/api/chat")
public class ChatController {

    private final ChatService chatService;

    @Autowired
    public ChatController(ChatService chatService) {
        this.chatService = chatService;
    }

    @PostMapping("/sessions")
    public ResponseEntity<ChatSession> createSession() {
        log.info("Creating new chat session");
        ChatSession session = chatService.createSession();
        return ResponseEntity.ok(session);
    }

    @GetMapping("/sessions/{sessionId}")
    public ResponseEntity<ChatSession> getSession(@PathVariable String sessionId) {
        log.info("Getting chat session with ID: {}", sessionId);
        ChatSession session = chatService.getSession(sessionId);
        if (session == null) {
            return ResponseEntity.notFound().build();
        }
        return ResponseEntity.ok(session);
    }

    @PostMapping("/messages")
    public Mono<ResponseEntity<ChatResponse>> sendMessage(@Valid @RequestBody ChatRequest request) {
        log.info("Processing message for session ID: {}", request.getSessionId());
        return chatService.processMessage(request.getSessionId(), request.getMessage())
                .map(response -> ResponseEntity.ok(response))
                .onErrorResume(e -> {
                    log.error("Error processing message: {}", e.getMessage(), e);
                    return Mono.just(ResponseEntity.internalServerError().build());
                });
    }
}
