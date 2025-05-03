# Travel Advisor Testing Guide

This document provides information on testing strategies and procedures for the Travel Advisor application.

## Testing Overview

The Travel Advisor application employs multiple testing approaches to ensure reliability, functionality, and performance:

1. **Unit Testing**: Testing individual components in isolation
2. **Integration Testing**: Testing interactions between components
3. **End-to-End Testing**: Testing the complete application flow
4. **Mock Testing**: Using mock implementations for external dependencies

## Test Structure

The tests are organized in the `tests` directory with the following structure:

```bash
tests/
├── TravelAdvisor.Core.Tests/       # Unit tests for Core project
├── TravelAdvisor.Infrastructure.Tests/  # Tests for Infrastructure project
└── TravelAdvisor.Web.Tests/        # Tests for Web project and E2E tests
```

## Unit Testing

Unit tests focus on testing individual components in isolation, using mocks or stubs for dependencies.

### Core Project Testing

Core project tests focus on domain models, business logic, and interfaces:

```csharp
[Fact]
public void TravelQuery_WithValidData_ShouldBeValid()
{
    // Arrange
    var query = new TravelQuery
    {
        Origin = "Seattle",
        Destination = "Portland",
        TravelTime = new TravelTime
        {
            DepartureTime = DateTime.Now.AddDays(1)
        }
    };

    // Act & Assert
    Assert.False(query.HasError);
    Assert.Equal("Seattle", query.Origin);
    Assert.Equal("Portland", query.Destination);
    Assert.True(query.TravelTime.HasSpecificSchedule);
}
```

### Testing Services

Service tests verify that service implementations behave as expected:

```csharp
[Fact]
public async Task ProcessNaturalLanguageQueryAsync_WithValidQuery_ShouldExtractData()
{
    // Arrange
    var mockChatClient = new MockChatClient(_loggerFactory.CreateLogger<MockChatClient>(), true);
    var mockMapService = new MockGoogleMapsService(_loggerFactory.CreateLogger<MockGoogleMapsService>());
    var promptFactory = new PromptFactory();

    var service = new TravelAdvisorService(
        mockChatClient,
        promptFactory,
        mockMapService,
        _loggerFactory.CreateLogger<TravelAdvisorService>());

    // Act
    var result = await service.ProcessNaturalLanguageQueryAsync(
        "What's the best way to get from Seattle to Portland tomorrow morning?");

    // Assert
    Assert.NotNull(result);
    Assert.Equal("Seattle", result.Origin);
    Assert.Equal("Portland", result.Destination);
    Assert.False(result.HasError);
}
```

## Integration Testing

Integration tests verify that different components work together correctly.

### Testing AI Integration

```csharp
[Fact]
public async Task AIClientFactory_ShouldCreateCorrectClient()
{
    // Arrange
    var options = new GenAIOptions
    {
        ApiKey = "test-key",
        ApiUrl = "https://api.openai.com/v1",
        Model = "gpt-4o-mini"
    };

    var factory = new AIClientFactory();

    // Act
    var client = factory.CreateClient(options, _loggerFactory.CreateLogger("Test"));

    // Assert
    Assert.NotNull(client);
    Assert.IsType<OpenAIChatClient>(client);
}
```

### Testing Map Service Integration

```csharp
[Fact]
public async Task GoogleMapsService_ShouldCalculateDistance()
{
    // Skip if no API key is available
    if (string.IsNullOrEmpty(Environment.GetEnvironmentVariable("GOOGLEMAPS__APIKEY")))
    {
        Skip.If(true, "No Google Maps API key available");
    }

    // Arrange
    var options = Options.Create(new GoogleMapsOptions
    {
        ApiKey = Environment.GetEnvironmentVariable("GOOGLEMAPS__APIKEY")
    });

    var service = new GoogleMapsService(
        options,
        _loggerFactory.CreateLogger<GoogleMapsService>());

    // Act
    var (distance, duration) = await service.CalculateDistanceAndDurationAsync(
        "Seattle, WA",
        "Portland, OR",
        TransportMode.Car);

    // Assert
    Assert.True(distance > 0);
    Assert.True(duration > 0);
}
```

## End-to-End Testing

End-to-end tests verify the complete application flow from user input to output.

### Testing Web UI

```csharp
[Fact]
public async Task AdvisorPage_ShouldProcessQuery()
{
    // Arrange
    var ctx = new TestContext();
    ctx.Services.AddSingleton<ITravelAdvisorService>(new MockTravelAdvisorService(
        new MockGoogleMapsService(_loggerFactory.CreateLogger<MockGoogleMapsService>()),
        _loggerFactory.CreateLogger<MockTravelAdvisorService>()));

    var cut = ctx.RenderComponent<Advisor>();

    // Act
    cut.Find("textarea").Change("What's the best way to get from Seattle to Portland?");
    cut.Find("button").Click();

    // Wait for async operations
    await Task.Delay(100);

    // Assert
    cut.WaitForState(() => cut.FindAll(".recommendation-card").Count > 0);
    Assert.Contains("Seattle", cut.Markup);
    Assert.Contains("Portland", cut.Markup);
}
```

## Mock Testing

The application includes mock implementations for external dependencies to facilitate testing without requiring actual API access.

### Mock Chat Client

The `MockChatClient` class provides a simulated LLM response for testing:

```csharp
public class MockChatClient : IChatClient
{
    private readonly ILogger<MockChatClient> _logger;
    private readonly bool _returnValidData;

    public MockChatClient(ILogger<MockChatClient> logger, bool returnValidData = true)
    {
        _logger = logger;
        _returnValidData = returnValidData;
    }

    public Task<ChatResponse> GetResponseAsync(
        IEnumerable<ChatMessage> messages,
        ChatOptions? options = null,
        CancellationToken cancellationToken = default)
    {
        // Return mock data based on the messages
        return Task.FromResult(CreateMockResponse(messages));
    }

    // Implementation details...
}
```

### Mock Google Maps Service

The `MockGoogleMapsService` class provides simulated travel data:

```csharp
public class MockGoogleMapsService : IMapService
{
    private readonly ILogger<MockGoogleMapsService> _logger;

    public MockGoogleMapsService(ILogger<MockGoogleMapsService> logger)
    {
        _logger = logger;
    }

    public Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
        string origin,
        string destination,
        TransportMode mode)
    {
        // Return mock distance and duration based on the locations
        return Task.FromResult(GetMockDistanceAndDuration(origin, destination, mode));
    }

    // Implementation details...
}
```

## Test Coverage Requirements

The project aims for the following test coverage targets:

- **Core Project**: 90% code coverage
- **Infrastructure Project**: 80% code coverage
- **Web Project**: 70% code coverage

## Running Tests

### Running All Tests

```bash
dotnet test
```

### Running Tests for a Specific Project

```bash
dotnet test tests/TravelAdvisor.Core.Tests
```

### Running Tests with Coverage

```bash
dotnet test /p:CollectCoverage=true /p:CoverletOutputFormat=opencover
```

## Continuous Integration

Tests are automatically run as part of the CI pipeline:

1. **Pull Request Validation**: All tests are run when a PR is created or updated
2. **Main Branch Builds**: Tests are run on every commit to the main branch
3. **Scheduled Builds**: Full test suite is run on a daily schedule

## Performance Testing

Performance tests focus on response times and resource usage:

1. **Response Time Testing**: Measures the time taken to process queries and generate recommendations
2. **Load Testing**: Simulates multiple concurrent users to test system behavior under load
3. **Memory Usage Testing**: Monitors memory consumption during extended operation

## Security Testing

Security tests focus on protecting sensitive information and preventing vulnerabilities:

1. **API Key Protection**: Verifies that API keys are properly secured
2. **Input Validation**: Tests handling of malicious or malformed input
3. **Dependency Scanning**: Checks for vulnerabilities in dependencies

## Troubleshooting Tests

If tests are failing, consider the following:

1. **Environment Variables**: Ensure required environment variables are set
2. **API Access**: Verify that mock implementations are used when actual APIs are unavailable
3. **Test Isolation**: Ensure tests don't interfere with each other
4. **Async Operations**: Add appropriate delays or use async test patterns

## Adding New Tests

When adding new features, follow these guidelines for testing:

1. **Write Tests First**: Consider a test-driven development approach
2. **Test Edge Cases**: Include tests for boundary conditions and error scenarios
3. **Mock External Dependencies**: Use mock implementations for external services
4. **Keep Tests Fast**: Optimize tests to run quickly to support rapid development

## Test Data Management

The application uses the following approaches for test data:

1. **Static Test Data**: Predefined data for consistent test results
2. **Generated Test Data**: Dynamically generated data for edge cases
3. **Anonymized Production Data**: Sanitized copies of real data for realistic testing

## Conclusion

A comprehensive testing strategy ensures that the Travel Advisor application remains reliable, performant, and secure. By combining unit tests, integration tests, and end-to-end tests with appropriate mocking, we can verify the application's behavior without depending on external services.
