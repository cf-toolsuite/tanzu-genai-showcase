# Testing Guide

This document outlines the testing strategies and procedures for the Congress.gov API Chatbot application.

## Table of Contents

- [Testing Overview](#testing-overview)
- [Unit Testing](#unit-testing)
- [Integration Testing](#integration-testing)
- [End-to-End Testing](#end-to-end-testing)
- [Performance Testing](#performance-testing)
- [Security Testing](#security-testing)
- [Test Coverage](#test-coverage)
- [Continuous Integration](#continuous-integration)
- [Mocking External Dependencies](#mocking-external-dependencies)

## Testing Overview

The Congress.gov API Chatbot application employs a comprehensive testing strategy that includes:

- **Unit Testing**: Testing individual components in isolation
- **Integration Testing**: Testing interactions between components
- **End-to-End Testing**: Testing the complete application flow
- **Performance Testing**: Testing the application's performance under load
- **Security Testing**: Testing the application for security vulnerabilities

## Unit Testing

Unit tests focus on testing individual components in isolation, with dependencies mocked or stubbed.

### Running Unit Tests

To run all unit tests:

```bash
go test ./...
```

To run tests for a specific package:

```bash
go test ./internal/service
```

To run tests with verbose output:

```bash
go test -v ./...
```

### Writing Unit Tests

Unit tests should be placed in the same package as the code they test, with a `_test.go` suffix.

Example unit test for the `ProcessUserQuery` method in the `ChatbotService`:

```go
// chatbot_test.go
package service

import (
    "context"
    "testing"
    "time"

    "github.com/stretchr/testify/assert"
    "github.com/stretchr/testify/mock"
)

// MockCongressClient is a mock implementation of the Congress.gov API client
type MockCongressClient struct {
    mock.Mock
}

// SearchBills is a mock implementation of the SearchBills method
func (m *MockCongressClient) SearchBills(query string, offset, limit int) (map[string]interface{}, error) {
    args := m.Called(query, offset, limit)
    return args.Get(0).(map[string]interface{}), args.Error(1)
}

// Additional mock methods would be implemented here...

// MockLLMClient is a mock implementation of the LLM client
type MockLLMClient struct {
    mock.Mock
}

// GenerateResponse is a mock implementation of the GenerateResponse method
func (m *MockLLMClient) GenerateResponse(ctx context.Context) (string, error) {
    args := m.Called(ctx)
    return args.String(0), args.Error(1)
}

// Additional mock methods would be implemented here...

func TestProcessUserQuery(t *testing.T) {
    // Create mock clients
    mockCongressClient := new(MockCongressClient)
    mockLLMClient := new(MockLLMClient)

    // Create test service with mock clients
    service := NewChatbotService(mockCongressClient, mockLLMClient)

    // Set up expectations
    mockLLMClient.On("AddUserMessage", "Who are the senators from Washington?").Return()
    mockLLMClient.On("ClearMessages").Return()
    mockLLMClient.On("AddSystemMessage", mock.Anything).Return()

    // Mock the API planning response
    apiPlanJSON := `{
        "endpoint": "search_members",
        "parameters": {
            "query": "Washington state senators"
        }
    }`
    mockLLMClient.On("GenerateResponse", mock.Anything).Return(apiPlanJSON, nil).Once()

    // Mock the API response
    apiResponse := map[string]interface{}{
        "members": []interface{}{
            map[string]interface{}{
                "name": "Maria Cantwell",
                "party": "D",
                "state": "WA",
                "chamber": "Senate",
            },
            map[string]interface{}{
                "name": "Patty Murray",
                "party": "D",
                "state": "WA",
                "chamber": "Senate",
            },
        },
    }
    mockCongressClient.On("SearchMembers", "Washington state senators", 0, 5).Return(apiResponse, nil)

    // Mock the final response
    finalResponse := "The senators from Washington state are Maria Cantwell and Patty Murray, both Democrats."
    mockLLMClient.On("AddUserMessage", mock.Anything).Return()
    mockLLMClient.On("GenerateResponse", mock.Anything).Return(finalResponse, nil).Once()

    // Create a context with timeout
    ctx, cancel := context.WithTimeout(context.Background(), 5*time.Second)
    defer cancel()

    // Call the method being tested
    response, err := service.ProcessUserQuery(ctx, "Who are the senators from Washington?")

    // Assert expectations
    assert.NoError(t, err)
    assert.Equal(t, finalResponse, response)
    mockCongressClient.AssertExpectations(t)
    mockLLMClient.AssertExpectations(t)
}
```

## Integration Testing

Integration tests focus on testing the interactions between components, such as the service layer and the API client.

### Running Integration Tests

Integration tests are run alongside unit tests:

```bash
go test ./...
```

To run only integration tests, you can use a build tag:

```bash
go test -tags=integration ./...
```

### Writing Integration Tests

Integration tests should be placed in the same package as the code they test, with a `_integration_test.go` suffix and a build tag.

Example integration test for the `ProcessUserQuery` method in the `ChatbotService`:

```go
// chatbot_integration_test.go
//go:build integration
// +build integration

package service

import (
    "context"
    "os"
    "testing"
    "time"

    "github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/api"
    "github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/pkg/llm"
    "github.com/stretchr/testify/assert"
)

func TestProcessUserQueryIntegration(t *testing.T) {
    // Skip if not running integration tests
    if os.Getenv("INTEGRATION_TESTS") != "true" {
        t.Skip("Skipping integration test")
    }

    // Create real clients
    congressClient := api.NewCongressClient(os.Getenv("CONGRESS_API_KEY"))
    llmClient, err := llm.NewLLMClient(
        os.Getenv("GENAI_API_KEY"),
        os.Getenv("GENAI_API_BASE_URL"),
        os.Getenv("LLM"),
    )
    if err != nil {
        t.Fatalf("Failed to create LLM client: %v", err)
    }

    // Create test service with real clients
    service := NewChatbotService(congressClient, llmClient)
    service.Initialize()

    // Create a context with timeout
    ctx, cancel := context.WithTimeout(context.Background(), 30*time.Second)
    defer cancel()

    // Call the method being tested
    response, err := service.ProcessUserQuery(ctx, "Who are the senators from Washington?")

    // Assert expectations
    assert.NoError(t, err)
    assert.Contains(t, response, "Washington")
    assert.Contains(t, response, "senator")
}
```

## End-to-End Testing

End-to-end tests focus on testing the complete application flow, from the HTTP request to the response.

### Running End-to-End Tests

End-to-end tests are typically run manually or as part of a CI/CD pipeline:

```bash
go test -tags=e2e ./...
```

### Writing End-to-End Tests

End-to-end tests should be placed in a separate package, such as `e2e`, with a build tag.

Example end-to-end test for the chat endpoint:

```go
// chat_e2e_test.go
//go:build e2e
// +build e2e

package e2e

import (
    "bytes"
    "encoding/json"
    "net/http"
    "os"
    "testing"
    "time"

    "github.com/stretchr/testify/assert"
)

func TestChatEndpoint(t *testing.T) {
    // Skip if not running e2e tests
    if os.Getenv("E2E_TESTS") != "true" {
        t.Skip("Skipping e2e test")
    }

    // Define the request
    requestBody, _ := json.Marshal(map[string]string{
        "message": "Who are the senators from Washington?",
    })

    // Send the request
    resp, err := http.Post("http://localhost:8080/api/chat", "application/json", bytes.NewBuffer(requestBody))
    if err != nil {
        t.Fatalf("Failed to send request: %v", err)
    }
    defer resp.Body.Close()

    // Check the response status code
    assert.Equal(t, http.StatusOK, resp.StatusCode)

    // Parse the response
    var response struct {
        Response string `json:"response"`
    }
    err = json.NewDecoder(resp.Body).Decode(&response)
    if err != nil {
        t.Fatalf("Failed to parse response: %v", err)
    }

    // Assert expectations
    assert.Contains(t, response.Response, "Washington")
    assert.Contains(t, response.Response, "senator")
}
```

## Performance Testing

Performance tests focus on testing the application's performance under load.

### Running Performance Tests

Performance tests are typically run manually or as part of a CI/CD pipeline:

```bash
go test -tags=performance ./...
```

### Writing Performance Tests

Performance tests should be placed in a separate package, such as `performance`, with a build tag.

Example performance test for the chat endpoint:

```go
// chat_performance_test.go
//go:build performance
// +build performance

package performance

import (
    "bytes"
    "encoding/json"
    "net/http"
    "os"
    "testing"
    "time"

    "github.com/stretchr/testify/assert"
)

func BenchmarkChatEndpoint(b *testing.B) {
    // Skip if not running performance tests
    if os.Getenv("PERFORMANCE_TESTS") != "true" {
        b.Skip("Skipping performance test")
    }

    // Define the request
    requestBody, _ := json.Marshal(map[string]string{
        "message": "Who are the senators from Washington?",
    })

    // Reset the timer
    b.ResetTimer()

    // Run the benchmark
    for i := 0; i < b.N; i++ {
        // Send the request
        resp, err := http.Post("http://localhost:8080/api/chat", "application/json", bytes.NewBuffer(requestBody))
        if err != nil {
            b.Fatalf("Failed to send request: %v", err)
        }
        resp.Body.Close()
    }
}
```

## Security Testing

Security tests focus on testing the application for security vulnerabilities.

### Running Security Tests

Security tests are typically run manually or as part of a CI/CD pipeline:

```bash
go test -tags=security ./...
```

### Writing Security Tests

Security tests should be placed in a separate package, such as `security`, with a build tag.

Example security test for the chat endpoint:

```go
// chat_security_test.go
//go:build security
// +build security

package security

import (
    "bytes"
    "encoding/json"
    "net/http"
    "os"
    "testing"

    "github.com/stretchr/testify/assert"
)

func TestChatEndpointSecurity(t *testing.T) {
    // Skip if not running security tests
    if os.Getenv("SECURITY_TESTS") != "true" {
        t.Skip("Skipping security test")
    }

    // Test for SQL injection
    requestBody, _ := json.Marshal(map[string]string{
        "message": "'; DROP TABLE users; --",
    })

    // Send the request
    resp, err := http.Post("http://localhost:8080/api/chat", "application/json", bytes.NewBuffer(requestBody))
    if err != nil {
        t.Fatalf("Failed to send request: %v", err)
    }
    defer resp.Body.Close()

    // Check the response status code
    assert.Equal(t, http.StatusOK, resp.StatusCode)

    // Parse the response
    var response struct {
        Response string `json:"response"`
    }
    err = json.NewDecoder(resp.Body).Decode(&response)
    if err != nil {
        t.Fatalf("Failed to parse response: %v", err)
    }

    // Assert expectations
    assert.NotContains(t, response.Response, "SQL syntax")
    assert.NotContains(t, response.Response, "error")
}
```

## Test Coverage

Test coverage measures the percentage of code that is covered by tests.

### Running Test Coverage

To run tests with coverage:

```bash
go test -cover ./...
```

To generate a coverage report:

```bash
go test -coverprofile=coverage.out ./...
go tool cover -html=coverage.out
```

## Continuous Integration

The project includes CI/CD configurations for various platforms that run tests as part of the pipeline:

### Jenkins

The `ci/jenkins/Jenkinsfile` contains a pipeline configuration for Jenkins that runs tests:

```groovy
stage('Test') {
    steps {
        sh 'go test ./...'
    }
}
```

### GitLab CI

The `ci/gitlab/.gitlab-ci.yml` contains a pipeline configuration for GitLab CI that runs tests:

```yaml
test:
  stage: test
  image: golang:1.18
  script:
    - go test ./...
```

### Bitbucket Pipelines

The `ci/bitbucket/bitbucket-pipelines.yml` contains a pipeline configuration for Bitbucket Pipelines that runs tests:

```yaml
- step:
    name: Test
    script:
      - go test ./...
```

## Mocking External Dependencies

The application uses external dependencies such as the Congress.gov API and the GenAI LLM service. These dependencies should be mocked in unit tests to ensure tests are fast, reliable, and independent of external services.

### Mocking the Congress.gov API

The `CongressClient` interface can be mocked using a mock implementation:

```go
// MockCongressClient is a mock implementation of the Congress.gov API client
type MockCongressClient struct {
    mock.Mock
}

// SearchBills is a mock implementation of the SearchBills method
func (m *MockCongressClient) SearchBills(query string, offset, limit int) (map[string]interface{}, error) {
    args := m.Called(query, offset, limit)
    return args.Get(0).(map[string]interface{}), args.Error(1)
}

// Additional mock methods would be implemented here...
```

### Mocking the GenAI LLM Service

The `LLMClient` interface can be mocked using a mock implementation:

```go
// MockLLMClient is a mock implementation of the LLM client
type MockLLMClient struct {
    mock.Mock
}

// GenerateResponse is a mock implementation of the GenerateResponse method
func (m *MockLLMClient) GenerateResponse(ctx context.Context) (string, error) {
    args := m.Called(ctx)
    return args.String(0), args.Error(1)
}

// Additional mock methods would be implemented here...
```

### Using Mocks in Tests

Mocks can be used in tests to isolate the component being tested:

```go
// Create mock clients
mockCongressClient := new(MockCongressClient)
mockLLMClient := new(MockLLMClient)

// Create test service with mock clients
service := NewChatbotService(mockCongressClient, mockLLMClient)

// Set up expectations
mockLLMClient.On("AddUserMessage", "Who are the senators from Washington?").Return()
mockLLMClient.On("GenerateResponse", mock.Anything).Return("Response", nil)

// Call the method being tested
response, err := service.ProcessUserQuery(ctx, "Who are the senators from Washington?")

// Assert expectations
assert.NoError(t, err)
assert.Equal(t, "Response", response)
mockLLMClient.AssertExpectations(t)
```
