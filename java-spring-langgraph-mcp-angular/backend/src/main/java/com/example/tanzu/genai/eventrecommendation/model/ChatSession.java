package com.example.tanzu.genai.eventrecommendation.model;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.time.LocalDateTime;
import java.util.ArrayList;
import java.util.List;
import java.util.UUID;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ChatSession {
    @Builder.Default
    private String id = UUID.randomUUID().toString();

    @Builder.Default
    private LocalDateTime createdAt = LocalDateTime.now();

    private LocalDateTime lastUpdatedAt;

    @Builder.Default
    private List<ChatMessage> messages = new ArrayList<>();

    private CityInfo currentCity;

    @Builder.Default
    private List<EventInfo> recommendedEvents = new ArrayList<>();

    public void addMessage(ChatMessage message) {
        messages.add(message);
        this.lastUpdatedAt = LocalDateTime.now();
    }
}
