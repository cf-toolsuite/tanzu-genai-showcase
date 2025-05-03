package com.tanzu.genai.flighttracker.server.config;

import org.springframework.context.annotation.Bean;
import org.springframework.context.annotation.Configuration;
import org.springframework.ai.mcp.server.prompt.PromptTemplate;
import org.springframework.ai.mcp.server.webflux.McpServerController;
import org.springframework.ai.mcp.server.tool.ToolSpecification;
import org.springframework.ai.mcp.server.ServerInfo;
import org.springframework.ai.mcp.server.webflux.resource.ResourceApiController;

import java.util.List;

/**
 * Configuration for the MCP server.
 */
@Configuration
public class McpServerConfig {

    /**
     * Configures a custom ServerInfo bean with details about the flight tracker MCP server.
     *
     * @return The ServerInfo instance
     */
    @Bean
    public ServerInfo serverInfo() {
        return new ServerInfo(
            "Flight Tracker",
            "MCP server for the AviationStack API",
            "1.0.0"
        );
    }

    /**
     * Configures a list of prompt templates for the MCP server.
     *
     * @return List of PromptTemplate instances
     */
    @Bean
    public List<PromptTemplate> promptTemplates() {
        PromptTemplate flightInfoPrompt = new PromptTemplate(
            "flight-info",
            "Get information about a specific flight",
            "You are a helpful flight information assistant. " +
            "Use the available tools to retrieve information about {flight_number} " +
            "and provide a concise summary of the flight details including airline, " +
            "departure, arrival, status, and any delays."
        );

        PromptTemplate airportInfoPrompt = new PromptTemplate(
            "airport-info",
            "Get information about an airport",
            "You are a helpful airport information assistant. " +
            "Use the available tools to retrieve information about {airport_code} " +
            "and provide a concise summary of the airport details."
        );

        PromptTemplate airlineInfoPrompt = new PromptTemplate(
            "airline-info",
            "Get information about an airline",
            "You are a helpful airline information assistant. " +
            "Use the available tools to retrieve information about {airline_name} " +
            "and provide a concise summary of the airline details."
        );

        PromptTemplate routeInfoPrompt = new PromptTemplate(
            "route-info",
            "Get information about routes between airports",
            "You are a helpful flight route information assistant. " +
            "Use the available tools to retrieve routes from {departure_airport} " +
            "to {arrival_airport} and provide a concise summary of the available routes."
        );

        return List.of(
            flightInfoPrompt,
            airportInfoPrompt,
            airlineInfoPrompt,
            routeInfoPrompt
        );
    }
}
