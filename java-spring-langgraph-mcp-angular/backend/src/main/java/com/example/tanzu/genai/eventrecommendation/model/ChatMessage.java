package com.example.tanzu.genai.eventrecommendation.model;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import java.time.LocalDateTime;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ChatMessage {
    private String id;
    private String role; // "user" or "assistant"
    private String content;
    private LocalDateTime timestamp;

    @Builder.Default
    private MessageType type = MessageType.TEXT;

    public enum MessageType {
        TEXT,
        EVENT_RECOMMENDATION,
        CITY_INFO
    }
}
