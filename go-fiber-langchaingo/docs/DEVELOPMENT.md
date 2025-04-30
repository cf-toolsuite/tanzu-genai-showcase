# Development Guide

This document provides information for developers who want to contribute to or modify the Congress.gov API Chatbot application.

## Table of Contents

- [Development Environment Setup](#development-environment-setup)
- [Project Structure](#project-structure)
- [Development Workflow](#development-workflow)
- [Code Style and Standards](#code-style-and-standards)
- [Adding New Features](#adding-new-features)
- [Testing](#testing)
- [Debugging](#debugging)
- [Performance Optimization](#performance-optimization)

## Development Environment Setup

### Prerequisites

- Go 1.18+ installed
- Git
- Access to Congress.gov API (get your API key at https://api.congress.gov/sign-up/)
- Access to a GenAI LLM service (for development, you can use OpenAI or another compatible service)

### Setting Up the Development Environment

1. Clone the repository:

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
cd tanzu-genai-showcase/go-fiber-langchaingo
```

2. Create a `.env` file for local development:

```bash
cp .env.example .env
```

3. Edit the `.env` file to include your API keys:

```
CONGRESS_API_KEY=your_congress_api_key
GENAI_API_KEY=your_GENAI_API_KEY
GENAI_API_BASE_URL=your_GENAI_API_BASE_URL
LLM=gpt-4o-mini
```

4. Install dependencies:

```bash
go mod tidy
```

5. Run the application:

```bash
go run cmd/server/main.go
```

The application will be available at http://localhost:8080

## Project Structure

The project follows a clean architecture approach with the following structure:

```
go-fiber-langchaingo/
├── api/                  # API clients (Congress.gov)
│   └── congress_client.go
├── cmd/                  # Application entry points
│   └── server/
│       └── main.go
├── config/               # Configuration handling
│   └── config.go
├── docs/                 # Documentation
│   ├── ARCHITECTURE.md
│   ├── API.md
│   ├── DEVELOPMENT.md
│   ├── FEATURES.md
│   ├── IMPLEMENTATION.md
│   └── LOGGING.md
├── internal/             # Private application code
│   ├── handler/
│   │   └── handler.go
│   └── service/
│       ├── chatbot.go
│       └── chatbot_tools.go
├── pkg/                  # Public libraries
│   ├── llm/
│   │   └── llm.go
│   └── logger/
│       └── logger.go
├── logs/                 # Log files
├── public/               # Static files
│   └── index.html
├── .env.example          # Example environment variables
├── go.mod                # Go module definition
├── go.sum                # Go module checksums
├── Makefile              # Build and development commands
├── manifest.yml          # Cloud Foundry manifest
└── README.md             # Project overview
```

## Development Workflow

### Making Changes

1. Create a new branch for your changes:

```bash
git checkout -b feature/your-feature-name
```

2. Make your changes to the codebase.

3. Run the application locally to test your changes:

```bash
go run cmd/server/main.go
```

4. Build the application to ensure it compiles correctly:

```bash
go build -o congress-chatbot cmd/server/main.go
```

5. Commit your changes:

```bash
git add .
git commit -m "Add your feature description"
```

6. Push your changes to the remote repository:

```bash
git push origin feature/your-feature-name
```

7. Create a pull request for your changes.

### Using the Makefile

The project includes a Makefile with common development tasks:

- `make build`: Build the application
- `make run`: Run the application locally
- `make test`: Run tests
- `make lint`: Run linters
- `make clean`: Clean build artifacts

Example:

```bash
make build
make run
```

## Code Style and Standards

### Go Code Style

- Follow the [Go Code Review Comments](https://github.com/golang/go/wiki/CodeReviewComments) for style guidance.
- Use `gofmt` or `goimports` to format your code.
- Follow the [Effective Go](https://golang.org/doc/effective_go) guidelines.

### Naming Conventions

- Use camelCase for variable and function names.
- Use PascalCase for exported functions, types, and variables.
- Use snake_case for file names.

### Error Handling

- Always check errors and return them to the caller.
- Use descriptive error messages.
- Wrap errors with context using `fmt.Errorf("failed to do something: %w", err)`.

### Comments

- Add comments to exported functions, types, and variables.
- Use complete sentences with proper punctuation.
- Explain the "why" rather than the "what" when possible.

## Adding New Features

### Adding a New Congress.gov API Endpoint

1. Add a new method to the `CongressClient` in `api/congress_client.go`:

```go
// SearchNewEndpoint searches for new data in the Congress.gov API
func (c *CongressClient) SearchNewEndpoint(query string, offset, limit int) (map[string]interface{}, error) {
    endpoint := fmt.Sprintf("%s/new-endpoint", c.baseURL)

    params := url.Values{}
    params.Add("api_key", c.apiKey)
    if query != "" {
        params.Add("query", query)
    }
    params.Add("offset", fmt.Sprintf("%d", offset))
    params.Add("limit", fmt.Sprintf("%d", limit))

    return c.makeRequest(endpoint, params)
}
```

2. Add a new case to the `executeCongressTool` method in `internal/service/chatbot_tools.go`:

```go
case "search_new_endpoint":
    var params struct {
        Query string `json:"query"`
    }
    if err := json.Unmarshal([]byte(args), &params); err != nil {
        return "", fmt.Errorf("failed to parse search_new_endpoint args: %w", err)
    }
    result, err = s.congressClient.SearchNewEndpoint(params.Query, 0, 5)
```

3. Add a new tool to the `createCongressTools` method in `internal/service/chatbot_tools.go`:

```go
searchNewEndpointTool := llms.Tool{
    Type: "function",
    Function: &llms.FunctionDefinition{
        Name:        "search_new_endpoint",
        Description: "Search for new data in the Congress.gov API. Use this when the user asks about new data.",
        Parameters: map[string]any{
            "type": "object",
            "properties": map[string]any{
                "query": map[string]any{
                    "type":        "string",
                    "description": "Search query for new data",
                },
            },
            "required": []string{"query"},
        },
    },
}
```

4. Add the new tool to the list of tools returned by `createCongressTools`:

```go
return []llms.Tool{
    // Existing tools...
    searchNewEndpointTool,
}
```

5. Update the system prompt in `Initialize` method in `internal/service/chatbot.go` to include the new capability.

### Adding a New API Endpoint to the Application

1. Add a new handler method to the `Handler` struct in `internal/handler/handler.go`:

```go
// HandleNewEndpoint handles requests to the new endpoint
func (h *Handler) HandleNewEndpoint(c *fiber.Ctx) error {
    // Implementation
    return c.Status(fiber.StatusOK).JSON(fiber.Map{
        "status": "ok",
    })
}
```

2. Register the new endpoint in the `RegisterRoutes` method in `internal/handler/handler.go`:

```go
api.Get("/new-endpoint", h.HandleNewEndpoint)
```

## Testing

### Running Tests

Run all tests:

```bash
go test ./...
```

Run tests for a specific package:

```bash
go test ./internal/service
```

Run tests with coverage:

```bash
go test -cover ./...
```

### Writing Tests

- Place test files in the same package as the code they test.
- Name test files with a `_test.go` suffix.
- Name test functions with a `Test` prefix followed by the name of the function being tested.

Example:

```go
// chatbot_test.go
package service

import (
    "context"
    "testing"
)

func TestProcessUserQuery(t *testing.T) {
    // Test implementation
}
```

## Debugging

### Logging

The application uses a comprehensive logging system that writes logs to both the console and a log file (`logs/http.log`). You can use the logger to add debug information:

```go
import "github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/logger"

// Log an informational message
logger.InfoLogger.Printf("Processing request: %s", requestID)

// Log an error
logger.ErrorLogger.Printf("Failed to process request: %v", err)
```

### Debugging HTTP Requests

You can use the `curl` command to debug HTTP requests:

```bash
# Test the chat endpoint
curl -X POST http://localhost:8080/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Who are the current senators from Washington state?"}'

# Test the health endpoint
curl -X GET http://localhost:8080/api/health
```

## Performance Optimization

### Caching

The application implements a caching mechanism to improve performance and reduce the number of API calls to the Congress.gov API. The cache is implemented in the `CongressClient` struct in `api/congress_client.go`.

To modify the cache behavior:

1. Adjust the cache TTL (time-to-live) in the `makeRequest` method:

```go
// Cache the response for 10 minutes
c.cache.Set(cacheKey, result, 10*time.Minute)
```

2. Add cache invalidation for specific endpoints if needed:

```go
// Invalidate cache for a specific key
c.cache.mutex.Lock()
delete(c.cache.data, cacheKey)
c.cache.mutex.Unlock()
```

### Optimizing LLM Calls

LLM calls can be expensive and slow. To optimize LLM usage:

1. Adjust the temperature parameter in the LLM client to balance creativity and consistency:

```go
opts := []llms.CallOption{
    llms.WithModel(model),
    llms.WithTemperature(0.3), // Lower for more consistent responses
    llms.WithMaxTokens(8192),
}
```

2. Use more specific prompts to get better responses:

```go
systemPrompt := `
You are a helpful assistant that provides information about the U.S. Congress.
Be precise and factual, focusing on providing accurate and current information.
`
```

3. Implement streaming responses for a more interactive experience:

```go
// TODO: Implement streaming responses
```
