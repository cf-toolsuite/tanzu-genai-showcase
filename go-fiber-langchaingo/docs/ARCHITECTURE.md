# Architecture

## System Overview

The Congress.gov API Chatbot is a web-based application that allows users to interact with the Congress.gov API using natural language. The application is built using Go, Fiber, and LangChainGo, and is designed to be deployed on Tanzu Platform for Cloud Foundry.

## Component Architecture

The application follows a clean architecture approach with the following components:

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│                 │     │                 │     │                 │     │                 │
│  Web Interface  │────▶│  Handler Layer  │────▶│  Service Layer  │────▶│    API Layer    │
│                 │     │                 │     │                 │     │                 │
└─────────────────┘     └─────────────────┘     └─────────────────┘     └─────────────────┘
                                                        │
                                                        ▼
                                               ┌─────────────────┐
                                               │                 │
                                               │  LLM Integration│
                                               │                 │
                                               └─────────────────┘
```

### 1. API Layer

The API layer handles communication with the Congress.gov API. It is implemented in the `api/congress_client.go` file and provides a clean interface to the following Congress.gov API endpoints:

- Search Bills
- Get Bill Details
- Get Bill Summaries
- Get Bill Actions
- Get Bill Cosponsors
- Get Bill Related Bills
- Search Members
- Get Member Details
- Get Member Sponsorship
- Get Senators by State
- Get Representatives by State
- Search Amendments
- Search Committees
- Get Committee Details
- Search Congressional Record
- Search Nominations
- Search Hearings

The API layer also implements a caching mechanism to improve performance and reduce the number of API calls to the Congress.gov API.

### 2. LLM Integration

The LLM integration layer uses LangChainGo to interact with the GenAI LLM service. It is responsible for:

- Managing conversation history
- Generating responses from the LLM
- Adding system, user, and assistant messages
- Implementing tool calling for more structured interactions with the Congress.gov API

### 3. Service Layer

The service layer orchestrates the interaction between the user, LLM, and Congress.gov API. It is implemented in the `internal/service/chatbot.go` and `internal/service/chatbot_tools.go` files and provides the following functionality:

- Processing user queries
- Determining which Congress.gov API to call
- Making API calls to fetch relevant information
- Interpreting API responses to generate helpful answers
- Managing conversation history
- Implementing tool calling for more structured interactions

The service layer implements two approaches for processing user queries:

1. **Standard Approach**: The LLM analyzes the user query, determines which API to call, makes the API call, and then interprets the API response to generate a helpful answer.

2. **Tool-Based Approach**: The LLM is provided with a set of tools that correspond to the Congress.gov API endpoints. The LLM can then call these tools directly to fetch information and generate a response.

### 4. Handler Layer

The handler layer manages HTTP requests and responses using the Fiber web framework. It is implemented in the `internal/handler/handler.go` file and provides the following endpoints:

- `/api/chat`: Process user messages
- `/api/history`: Get conversation history
- `/api/clear`: Clear conversation history
- `/api/health`: Health check endpoint

### 5. Web Interface

The web interface is a simple single-page application using HTML, CSS, and JavaScript. It provides a chat interface for users to interact with the chatbot and includes a toggle switch to enable or disable the use of API tools.

## Data Flow

### Standard Approach

1. User sends a query through the web interface
2. Handler layer receives the query and passes it to the service layer
3. Service layer adds the user query to the conversation history
4. Service layer creates a planning prompt for the LLM to determine which API to call
5. LLM analyzes the query and returns a JSON object with the API endpoint and parameters
6. Service layer calls the appropriate API endpoint with the provided parameters
7. API layer makes the HTTP request to the Congress.gov API and returns the response
8. Service layer creates an interpretation prompt for the LLM with the API response
9. LLM interprets the API response and generates a helpful answer
10. Service layer adds the LLM response to the conversation history
11. Handler layer returns the response to the web interface
12. Web interface displays the response to the user

### Tool-Based Approach

1. User sends a query through the web interface with the "Use API Tools" toggle enabled
2. Handler layer receives the query and passes it to the service layer with the `useTools` parameter set to `true`
3. Service layer adds the user query to the conversation history
4. Service layer creates a set of tools that correspond to the Congress.gov API endpoints
5. Service layer calls the LLM with the tools and the user query
6. LLM analyzes the query and determines which tool(s) to call
7. LLM calls the appropriate tool(s) with the necessary parameters
8. Service layer executes the tool call(s) by making the appropriate API call(s)
9. Service layer returns the tool response(s) to the LLM
10. LLM generates a helpful answer based on the tool response(s)
11. Service layer adds the LLM response to the conversation history
12. Handler layer returns the response to the web interface
13. Web interface displays the response to the user with a "🔧 Response generated using API tools" indicator

## Technology Stack

- **Go**: Programming language
- **Fiber**: Web framework for building the API and serving the web interface
- **LangChainGo**: Framework for building applications with large language models
- **Congress.gov API**: External API for fetching legislative data
- **GenAI LLM Service**: Large language model service provided by Tanzu Platform for Cloud Foundry

## Configuration

The application is configured using environment variables and service bindings. The configuration is loaded in the `config/config.go` file and includes:

- Port
- Congress.gov API Key
- LLM API Key
- LLM API URL
- LLM Model
- Environment

When deployed on Tanzu Platform for Cloud Foundry, the application can be bound to a GenAI LLM service instance, which will automatically configure the LLM API Key, URL, and Model.

## Logging

The application implements a comprehensive logging system that provides detailed information about:

- HTTP requests and responses
- API calls to the Congress.gov API
- LLM interactions
- Tool calls and responses
- Errors and panics

Logs are written to both the console and a log file (`logs/http.log`).

## Error Handling

The application implements comprehensive error handling to ensure that it can recover from failures and provide helpful error messages to the user. This includes:

- Automatic recovery from panics
- Detailed error reporting
- Fallback responses when API calls fail
- Timeout handling for LLM calls

## Security

API keys and service credentials are securely handled through environment variables and service bindings. The application does not expose any sensitive information to the user.
