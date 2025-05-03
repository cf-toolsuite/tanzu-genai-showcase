package com.example.tanzu.genai.eventrecommendation.graph;

import com.example.tanzu.genai.eventrecommendation.model.ChatMessage;
import com.example.tanzu.genai.eventrecommendation.model.CityInfo;
import com.example.tanzu.genai.eventrecommendation.model.EventInfo;
import lombok.AllArgsConstructor;
import lombok.Builder;
import lombok.Data;
import lombok.NoArgsConstructor;
import org.bsc.langgraph4j.AgentState;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

@Data
@Builder
@NoArgsConstructor
@AllArgsConstructor
public class ChatbotState extends AgentState {
    @Builder.Default
    private List<ChatMessage> messages = new ArrayList<>();

    @Builder.Default
    private Map<String, Object> data = new HashMap<>();

    @Builder.Default
    private List<EventInfo> recommendedEvents = new ArrayList<>();

    private CityInfo cityInfo;

    private String userIntent;

    private String nextAction;

    public ChatbotState(Map<String, Object> initData) {
        super(initData);
        this.data = initData;
    }

    public void addMessage(ChatMessage message) {
        messages.add(message);
    }

    public void addEvents(List<EventInfo> events) {
        recommendedEvents.addAll(events);
    }

    public void setCityInfo(CityInfo cityInfo) {
        this.cityInfo = cityInfo;
    }
}
