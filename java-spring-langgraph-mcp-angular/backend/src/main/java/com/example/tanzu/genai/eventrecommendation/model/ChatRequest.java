package com.example.tanzu.genai.eventrecommendation.model;

import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;

import jakarta.validation.constraints.NotBlank;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ChatRequest {
    @NotBlank(message = "Session ID cannot be blank")
    private String sessionId;

    @NotBlank(message = "Message content cannot be blank")
    private String message;
}
