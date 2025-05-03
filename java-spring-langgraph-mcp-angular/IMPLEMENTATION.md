# Implementation Details: Java Spring Boot, LangGraph, MCP, and Angular

This document provides a detailed overview of the implementation of the Event Recommendation Chatbot using Java Spring Boot, LangGraph, MCP, and Angular.

## Architecture Overview

The application follows a client-server architecture:

1. **Frontend**: Angular application that provides the user interface for the chatbot
2. **Backend**: Spring Boot application that handles the AI logic, API calls, and serves the Angular frontend
3. **External Services**:
   - Ticketmaster MCP server for event data
   - API Ninjas Cities API for city information

### System Components

![Architecture Diagram](architecture-diagram.png)

#### Backend Components

1. **MCP Client Integration**
   - Spring AI MCP client for connecting to the Ticketmaster MCP server
   - MCP tool callbacks for handling responses from the MCP server

2. **LangGraph Agent**
   - Stateful conversation graph using LangGraph4j
   - Nodes for understanding user intent, fetching city information, searching events, and generating responses

3. **REST API**
   - Chat session management
   - Message processing

#### Frontend Components

1. **Chat Interface**
   - Real-time message display
   - Input area for user messages

2. **Event Display**
   - Card layout for event recommendations
   - Details about each event

3. **City Information**
   - Display of city information when a city is mentioned

## Key Implementation Features

### MCP Integration

The application uses the Model Context Protocol (MCP) to integrate with the Ticketmaster API. This allows the application to leverage the standardized MCP interface for tool calling, which simplifies integration and provides a consistent pattern for AI model interactions.

Key files:
- `backend/src/main/java/com/example/tanzu/genai/eventrecommendation/config/McpConfig.java`

### LangGraph4j for Agent Orchestration

The chatbot agent is implemented using LangGraph4j, which provides a framework for creating stateful, multi-step conversation flows. This enables the chatbot to maintain context, understand user intent, and break down complex tasks into manageable steps.

Key files:
- `backend/src/main/java/com/example/tanzu/genai/eventrecommendation/graph/ChatbotGraph.java`
- `backend/src/main/java/com/example/tanzu/genai/eventrecommendation/graph/ChatbotState.java`

### Angular Frontend with Standalone Components

The frontend is built using Angular 17 with standalone components, providing a modern, modular architecture. This approach simplifies the component structure and reduces bundle size.

Key files:
- `frontend/src/app/components/chat/chat.component.ts`
- `frontend/src/app/components/event-card/event-card.component.ts`
- `frontend/src/app/components/city-info/city-info.component.ts`

### Maven Integration with Frontend Build

The project uses Maven to build both the backend and frontend, with the frontend-maven-plugin handling the Node.js and npm setup. This ensures that the frontend is built and included in the Spring Boot JAR file, simplifying deployment.

Key files:
- `pom.xml`
- `backend/pom.xml`

## Deployment

The application is designed to be deployed to Tanzu Platform for Cloud Foundry. The deployment script handles:

1. Building the application
2. Creating necessary services
3. Deploying the application
4. Binding the application to services

Key files:
- `scripts/deploy-to-tanzu.sh`
- `manifest.yml`

## Future Enhancements

1. **Improved Error Handling**: Add more robust error handling and retry logic for external API calls
2. **User Authentication**: Add user authentication to persist chat sessions
3. **Event Filtering**: Allow users to filter events by date, type, price, etc.
4. **Multiple LLM Support**: Add support for different LLM providers through the GenAI tile
5. **Enhanced UI**: Add animations, images, and more interactive elements to the frontend
