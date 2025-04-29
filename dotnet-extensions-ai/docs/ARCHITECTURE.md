# Travel Advisor Architecture

This document provides a detailed overview of the Travel Advisor application architecture, components, and data flow.

## System Overview

Travel Advisor is a .NET 9 Blazor Server application that helps users find the best transportation options for their journeys. It combines natural language processing with real-world travel data to provide personalized recommendations based on user preferences.

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                      TravelAdvisor.Web                          │
│                                                                 │
│  ┌───────────────┐    ┌───────────────┐    ┌───────────────┐   │
│  │  Blazor Pages │    │ Shared Layout │    │   Services    │   │
│  └───────┬───────┘    └───────────────┘    └───────────────┘   │
│          │                                                      │
└──────────┼──────────────────────────────────────────────────────┘
           │
           ▼
┌─────────────────────────────────────────────────────────────────┐
│                   TravelAdvisor.Infrastructure                  │
│                                                                 │
│  ┌───────────────┐    ┌───────────────┐    ┌───────────────┐   │
│  │ AI Integration│    │  Map Services │    │Cloud Foundry  │   │
│  │   Services    │    │               │    │  Integration  │   │
│  └───────┬───────┘    └───────┬───────┘    └───────────────┘   │
│          │                    │                                 │
└──────────┼────────────────────┼─────────────────────────────────┘
           │                    │
           ▼                    ▼
┌─────────────────────┐  ┌─────────────────────┐
│   LLM Service       │  │   Google Maps API   │
│  (OpenAI/Azure)     │  │                     │
└─────────────────────┘  └─────────────────────┘
           ▲                    ▲
           │                    │
┌──────────┴────────────────────┴─────────────────────────────────┐
│                      TravelAdvisor.Core                         │
│                                                                 │
│  ┌───────────────┐    ┌───────────────┐    ┌───────────────┐   │
│  │Domain Models  │    │  Interfaces   │    │   Utilities   │   │
│  └───────────────┘    └───────────────┘    └───────────────┘   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

## Component Architecture

The application follows a clean architecture pattern with three main projects:

### 1. TravelAdvisor.Core

Contains the domain models, interfaces, and core business logic.

Key components:
- **Models**: Domain entities like `TravelQuery` and `TravelRecommendation`
- **Interfaces**: Service contracts like `ITravelAdvisorService` and `IMapService`
- **Utilities**: Helper classes for environment variable loading and other utilities

### 2. TravelAdvisor.Infrastructure

Implements the services defined in the Core project and handles external integrations.

Key components:
- **Services**: Implementation of core interfaces
  - `TravelAdvisorService`: Processes queries and generates recommendations using LLMs
  - `GoogleMapsService`: Retrieves travel data from Google Maps API
- **Clients**: AI client implementations
  - `AIClientFactory`: Creates appropriate AI clients based on configuration
  - `AzureOpenAIClientAdapter`: Adapter for Azure OpenAI
  - `CustomEndpointChatClient`: Client for custom OpenAI-compatible endpoints
- **CloudFoundry**: Integration with Tanzu Platform for Cloud Foundry
  - `ServiceBindingConfiguration`: Handles service bindings for GenAI services

### 3. TravelAdvisor.Web

The Blazor Server web application that provides the user interface.

Key components:
- **Pages**: Blazor pages for user interaction
  - `Advisor.razor`: Main page for travel recommendations
  - `Index.razor`: Landing page
  - `About.razor`: Information about the application
- **Shared**: Shared UI components
  - `MainLayout.razor`: Main layout template
  - `NavMenu.razor`: Navigation menu
- **Utilities**: Web-specific utilities
  - `MarkdownRenderer.cs`: Renders markdown content in the UI

## Data Flow

1. **User Query Processing**:
   - User enters a natural language query on the Advisor page
   - `TravelAdvisorService.ProcessNaturalLanguageQueryAsync` sends the query to the LLM
   - LLM extracts structured data (origin, destination, preferences)
   - Result is returned as a `TravelQuery` object

2. **Recommendation Generation**:
   - `TravelAdvisorService.GenerateRecommendationsAsync` processes the structured query
   - For each transportation mode, it calls `GoogleMapsService` to get distance and duration
   - It calculates scores for environmental impact, convenience, and preference match
   - It returns a list of `TravelRecommendation` objects sorted by overall score

3. **Explanation Generation**:
   - `TravelAdvisorService.GenerateExplanationAsync` creates a natural language explanation
   - It sends the recommendation details to the LLM
   - LLM generates a conversational explanation of why the mode is recommended

4. **Follow-up Questions**:
   - User can ask follow-up questions about a recommendation
   - `TravelAdvisorService.AnswerFollowUpQuestionAsync` processes the question
   - It sends the question, recommendation details, and original query to the LLM
   - LLM generates a natural language answer

## Technology Stack

- **.NET 9**: The latest version of the .NET framework
- **Blazor Server**: For interactive web UI
- **Microsoft.Extensions.AI**: Microsoft's framework for LLM integration
- **Steeltoe**: Libraries for Cloud Foundry integration
- **Tailwind CSS**: Utility-first CSS framework for modern UI

## Integration Points

### LLM Integration

The application can integrate with various LLM providers:

1. **OpenAI**: Using the official OpenAI SDK
2. **Azure OpenAI**: Using the Azure OpenAI SDK
3. **Custom Endpoints**: Using a custom implementation for OpenAI-compatible endpoints

The integration is handled through the `IChatClient` interface from Microsoft.Extensions.AI.

### Google Maps Integration

The application integrates with Google Maps API to get real travel data:

1. **Distance and Duration**: For different transportation modes
2. **Travel Steps**: Detailed breakdown of the journey
3. **Mode Reasonability**: Determining if a mode is reasonable for a given distance

### Cloud Foundry Integration

The application uses Steeltoe for Cloud Foundry integration:

1. **Service Bindings**: Automatically detects and uses GenAI services
2. **Actuators**: Health monitoring and management endpoints
3. **Configuration**: Cloud Foundry-specific configuration

## Security Considerations

1. **API Key Management**: API keys are stored in environment variables or service bindings
2. **Logging**: Sensitive information is masked in logs
3. **Input Validation**: User input is validated before processing

## Performance Considerations

1. **Caching**: Responses can be cached to reduce API calls
2. **Async Processing**: All operations are asynchronous for better responsiveness
3. **Error Handling**: Comprehensive error handling to ensure reliability

## Extensibility

The application is designed for extensibility:

1. **New Transportation Modes**: Can be added by extending the `TransportMode` enum
2. **Alternative Map Services**: Can be implemented by creating a new `IMapService` implementation
3. **Different AI Providers**: Can be added by implementing the `IChatClient` interface
