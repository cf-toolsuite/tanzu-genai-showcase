# Travel Advisor API Documentation

This document provides detailed information about the Travel Advisor application's APIs, interfaces, and data models.

## Core Interfaces

### ITravelAdvisorService

The main service interface for generating travel recommendations.

```csharp
public interface ITravelAdvisorService
{
    Task<TravelQuery> ProcessNaturalLanguageQueryAsync(string query);
    Task<List<TravelRecommendation>> GenerateRecommendationsAsync(TravelQuery query);
    Task<string> GenerateExplanationAsync(TravelRecommendation recommendation, TravelQuery query);
    Task<string> AnswerFollowUpQuestionAsync(string question, TravelRecommendation recommendation, TravelQuery query);
}
```

#### Methods

| Method | Description | Parameters | Returns |
|--------|-------------|------------|---------|
| `ProcessNaturalLanguageQueryAsync` | Processes a natural language travel query and extracts structured data | `query`: Natural language query from the user | `TravelQuery`: Structured travel query |
| `GenerateRecommendationsAsync` | Generates travel recommendations based on a structured query | `query`: Structured travel query | `List<TravelRecommendation>`: List of travel recommendations |
| `GenerateExplanationAsync` | Generates a natural language explanation for a recommendation | `recommendation`: Travel recommendation<br>`query`: Original travel query | `string`: Natural language explanation |
| `AnswerFollowUpQuestionAsync` | Answers a follow-up question about a recommendation | `question`: User's follow-up question<br>`recommendation`: The recommendation being asked about<br>`query`: Original travel query | `string`: Natural language answer |

### IMapService

Interface for retrieving travel data from mapping services.

```csharp
public interface IMapService
{
    Task<(double distanceKm, int durationMinutes)> CalculateDistanceAndDurationAsync(
        string origin,
        string destination,
        TransportMode mode);

    Task<List<TravelStep>> GetTravelStepsAsync(
        string origin,
        string destination,
        TransportMode mode);

    bool IsModeReasonable(double distanceKm, TransportMode mode);
}
```

#### Methods

| Method | Description | Parameters | Returns |
|--------|-------------|------------|---------|
| `CalculateDistanceAndDurationAsync` | Calculates the distance and duration between two locations | `origin`: Origin location<br>`destination`: Destination location<br>`mode`: Transportation mode | `(double distanceKm, int durationMinutes)`: Distance in kilometers and duration in minutes |
| `GetTravelStepsAsync` | Gets detailed steps for a journey | `origin`: Origin location<br>`destination`: Destination location<br>`mode`: Transportation mode | `List<TravelStep>`: List of travel steps |
| `IsModeReasonable` | Determines if a transportation mode is reasonable for a given distance | `distanceKm`: Distance in kilometers<br>`mode`: Transportation mode | `bool`: Whether the mode is reasonable |

### IPromptFactory

Interface for creating prompts for the LLM.

```csharp
public interface IPromptFactory
{
    string CreateTravelQueryPrompt(string userQuery);
    string CreateRecommendationPrompt(TravelQuery query, List<TravelRecommendation> recommendations);
    string CreateExplanationPrompt(TravelRecommendation recommendation, TravelQuery query);
    string CreateFollowUpPrompt(string question, TravelRecommendation recommendation, TravelQuery query);
}
```

## Data Models

### TravelQuery

Represents a user query for travel recommendations.

```csharp
public class TravelQuery
{
    public string Origin { get; set; } = string.Empty;
    public string Destination { get; set; } = string.Empty;
    public TravelTime TravelTime { get; set; } = new TravelTime();
    public TravelPreferences Preferences { get; set; } = new TravelPreferences();
    public string AdditionalContext { get; set; } = string.Empty;
    public bool HasError { get; set; } = false;
    public string ErrorMessage { get; set; } = string.Empty;
}
```

#### Properties

| Property | Type | Description |
|----------|------|-------------|
| `Origin` | `string` | Origin location (address, city, etc.) |
| `Destination` | `string` | Destination location (address, city, etc.) |
| `TravelTime` | `TravelTime` | When the user wants to travel |
| `Preferences` | `TravelPreferences` | User preferences for the journey |
| `AdditionalContext` | `string` | Optional additional context or requirements from the user |
| `HasError` | `bool` | Indicates if there was an error processing the query |
| `ErrorMessage` | `string` | Error message if HasError is true |

### TravelTime

Represents when the user wants to travel.

```csharp
public class TravelTime
{
    public DateTime? DepartureTime { get; set; }
    public DateTime? ArrivalTime { get; set; }
    public bool HasSpecificSchedule => DepartureTime.HasValue || ArrivalTime.HasValue;
    public bool IsFlexible { get; set; }
}
```

### TravelPreferences

Represents user preferences for the journey.

```csharp
public class TravelPreferences
{
    public string Priority { get; set; } = string.Empty;
    public bool ConsiderWalking { get; set; } = true;
    public bool ConsiderBiking { get; set; } = true;
    public bool ConsiderPublicTransport { get; set; } = true;
    public bool ConsiderDriving { get; set; } = true;
    public bool ConsiderTrain { get; set; } = true;
    public bool ConsiderFlying { get; set; } = true;
    public double? MaxWalkingDistance { get; set; }
    public double? MaxBikingDistance { get; set; }
    public int? MaxTravelTime { get; set; }
    public decimal? MaxCost { get; set; }
}
```

### TravelRecommendation

Represents a travel recommendation for a specific transportation mode.

```csharp
public class TravelRecommendation
{
    public TransportMode Mode { get; set; }
    public string Summary { get; set; } = string.Empty;
    public string Description { get; set; } = string.Empty;
    public int DurationMinutes { get; set; }
    public decimal? EstimatedCost { get; set; }
    public double DistanceKm { get; set; }
    public List<TravelStep> Steps { get; set; } = new List<TravelStep>();
    public List<string> Pros { get; set; } = new List<string>();
    public List<string> Cons { get; set; } = new List<string>();
    public int EnvironmentalScore { get; set; }
    public int ConvenienceScore { get; set; }
    public int PreferenceMatchScore { get; set; }
    public int OverallScore { get; set; }
    public bool IsRecommended { get; set; }
}
```

### TravelStep

Represents a single step in a journey.

```csharp
public class TravelStep
{
    public string Description { get; set; } = string.Empty;
    public TransportMode Mode { get; set; }
    public int DurationMinutes { get; set; }
    public double DistanceKm { get; set; }
}
```

### TransportMode

Enumeration of transportation modes.

```csharp
public enum TransportMode
{
    Walk,
    Bike,
    Bus,
    Car,
    Train,
    Plane,
    Mixed
}
```

## Configuration Models

### GenAIOptions

Configuration options for the GenAI service.

```csharp
public class GenAIOptions
{
    public string ApiKey { get; set; } = string.Empty;
    public string ApiUrl { get; set; } = "https://api.openai.com/v1";
    public string Model { get; set; } = "gpt-4o-mini";
    public string ServiceName { get; set; } = "travel-advisor-llm";
}
```

### GoogleMapsOptions

Configuration options for the Google Maps service.

```csharp
public class GoogleMapsOptions
{
    public string ApiKey { get; set; } = string.Empty;
}
```

## AI Client Interfaces

### IChatClient

Interface from Microsoft.Extensions.AI for chat completions.

```csharp
public interface IChatClient : IDisposable
{
    Task<ChatResponse> GetResponseAsync(
        IEnumerable<ChatMessage> messages,
        ChatOptions? options = null,
        CancellationToken cancellationToken = default);

    IAsyncEnumerable<ChatStreamingResponse> GetStreamingResponseAsync(
        IEnumerable<ChatMessage> messages,
        ChatOptions? options = null,
        CancellationToken cancellationToken = default);

    ChatClientMetadata? Metadata { get; }
}
```

### IAIClientFactory

Factory interface for creating AI clients.

```csharp
public interface IAIClientFactory
{
    IChatClient CreateClient(GenAIOptions options, ILogger logger);
}
```

## Request/Response Formats

### Natural Language Query

**Request Format:**
```
What's the best way to get from Seattle to Portland tomorrow morning?
```

**Response Format (TravelQuery):**

```json
{
  "Origin": "Seattle",
  "Destination": "Portland",
  "TravelTime": {
    "DepartureTime": "2025-04-30T08:00:00",
    "ArrivalTime": null,
    "IsFlexible": false
  },
  "Preferences": {
    "Priority": "",
    "ConsiderWalking": true,
    "ConsiderBiking": true,
    "ConsiderPublicTransport": true,
    "ConsiderDriving": true,
    "ConsiderTrain": true,
    "ConsiderFlying": true,
    "MaxWalkingDistance": null,
    "MaxBikingDistance": null,
    "MaxTravelTime": null,
    "MaxCost": null
  },
  "AdditionalContext": "tomorrow morning",
  "HasError": false,
  "ErrorMessage": ""
}
```

### Travel Recommendations

**Request Format (TravelQuery):**

```json
{
  "Origin": "Seattle",
  "Destination": "Portland",
  "TravelTime": {
    "DepartureTime": "2025-04-30T08:00:00",
    "ArrivalTime": null,
    "IsFlexible": false
  },
  "Preferences": {
    "Priority": "",
    "ConsiderWalking": true,
    "ConsiderBiking": true,
    "ConsiderPublicTransport": true,
    "ConsiderDriving": true,
    "ConsiderTrain": true,
    "ConsiderFlying": true,
    "MaxWalkingDistance": null,
    "MaxBikingDistance": null,
    "MaxTravelTime": null,
    "MaxCost": null
  },
  "AdditionalContext": "tomorrow morning",
  "HasError": false,
  "ErrorMessage": ""
}
```

**Response Format (List<TravelRecommendation>):**

```json
[
  {
    "Mode": "Car",
    "Summary": "Travel by Car from Seattle to Portland",
    "Description": "",
    "DurationMinutes": 180,
    "EstimatedCost": 45.00,
    "DistanceKm": 280.0,
    "Steps": [
      {
        "Description": "Drive from Seattle to Portland via I-5 S",
        "Mode": "Car",
        "DurationMinutes": 180,
        "DistanceKm": 280.0
      }
    ],
    "Pros": [
      "Convenient and flexible",
      "Direct door-to-door travel",
      "Can carry luggage and other items easily",
      "Comfortable in any weather",
      "Can make stops along the way"
    ],
    "Cons": [
      "Parking can be expensive or difficult to find",
      "Traffic congestion can extend travel time",
      "Higher environmental impact",
      "Gas and maintenance costs",
      "Driver cannot rest or work during journey"
    ],
    "EnvironmentalScore": 30,
    "ConvenienceScore": 90,
    "PreferenceMatchScore": 70,
    "OverallScore": 65,
    "IsRecommended": true
  },
  {
    "Mode": "Train",
    "Summary": "Travel by Train from Seattle to Portland",
    "Description": "",
    "DurationMinutes": 210,
    "EstimatedCost": 52.00,
    "DistanceKm": 280.0,
    "Steps": [
      {
        "Description": "Take Amtrak Cascades from Seattle King Street Station to Portland Union Station",
        "Mode": "Train",
        "DurationMinutes": 210,
        "DistanceKm": 280.0
      }
    ],
    "Pros": [
      "Can use travel time productively",
      "More comfortable than bus for longer journeys",
      "More environmentally friendly than driving or flying",
      "No traffic congestion",
      "Often has amenities like WiFi and food service"
    ],
    "Cons": [
      "Fixed schedule and limited routes",
      "May not go directly to your destination",
      "Can be expensive for short notice booking",
      "May require getting to/from stations",
      "Potential for delays"
    ],
    "EnvironmentalScore": 80,
    "ConvenienceScore": 75,
    "PreferenceMatchScore": 70,
    "OverallScore": 60,
    "IsRecommended": false
  }
]
```

### Explanation

**Request Format:**

```
TravelRecommendation (Car mode) + TravelQuery
```

**Response Format (string):**

```
Based on your travel needs from Seattle to Portland tomorrow morning, I recommend driving. The 180-minute journey covers about 280 km and offers the most flexibility for your schedule. While trains are available, driving gives you door-to-door convenience without worrying about fixed schedules. You can leave exactly when you want, make stops along the way, and carry whatever luggage you need. The estimated cost of $45 is primarily for gas, which is economical if you're traveling with others. The environmental impact is higher than public transportation, but the convenience score of 90/100 reflects the significant advantages in terms of flexibility and comfort.
```

### Follow-up Question

**Request Format:**

```
Question: "How would the duration change during rush hour?"
TravelRecommendation (Car mode) + TravelQuery
```

**Response Format (string):**

```
During rush hour, your driving time from Seattle to Portland could increase significantly. The normal 180-minute (3-hour) journey could extend to 4-5 hours, especially if you're leaving Seattle during morning rush hour (7-9 AM) or arriving in Portland during evening rush hour (4-6 PM). The I-5 corridor between these cities can experience heavy congestion, particularly around Tacoma, Olympia, and the Portland metro area. If you're traveling tomorrow morning, consider leaving either before 7 AM to get ahead of traffic or after 9 AM when the morning rush has subsided. Traffic apps like Google Maps or Waze can provide real-time updates to help you navigate around the worst congestion.
```

## Error Handling

The API uses standard HTTP status codes and returns error messages in the following format:

```json
{
  "HasError": true,
  "ErrorMessage": "Detailed error message"
}
```

Common error scenarios:

| Scenario | Error Message |
|----------|---------------|
| Missing API keys | "GenAI API key and URL are required. Please configure credentials..." |
| Invalid origin/destination | "Could not determine valid origin and destination from your query." |
| Service unavailable | "Service temporarily unavailable. Please try again later." |

## Rate Limiting

The application is subject to rate limits imposed by the underlying APIs:

1. **LLM API**: Typically limited by tokens per minute based on your subscription
2. **Google Maps API**: Limited by queries per day based on your API key's quota

## Examples

### Example 1: Processing a Natural Language Query

```csharp
// Get the travel advisor service
var travelAdvisorService = serviceProvider.GetRequiredService<ITravelAdvisorService>();

// Process the natural language query
string userQuery = "What's the best way to get from Seattle to Portland tomorrow morning?";
var travelQuery = await travelAdvisorService.ProcessNaturalLanguageQueryAsync(userQuery);

// Check for errors
if (travelQuery.HasError)
{
    Console.WriteLine($"Error: {travelQuery.ErrorMessage}");
    return;
}

// Display the extracted information
Console.WriteLine($"Origin: {travelQuery.Origin}");
Console.WriteLine($"Destination: {travelQuery.Destination}");
```

### Example 2: Generating Recommendations

```csharp
// Generate recommendations based on the query
var recommendations = await travelAdvisorService.GenerateRecommendationsAsync(travelQuery);

// Display the recommendations
foreach (var recommendation in recommendations)
{
    Console.WriteLine($"Mode: {recommendation.Mode}");
    Console.WriteLine($"Duration: {recommendation.DurationMinutes} minutes");
    Console.WriteLine($"Distance: {recommendation.DistanceKm} km");
    Console.WriteLine($"Overall Score: {recommendation.OverallScore}/100");
    Console.WriteLine();
}
```

### Example 3: Generating an Explanation

```csharp
// Get the top recommendation
var topRecommendation = recommendations.FirstOrDefault(r => r.IsRecommended);
if (topRecommendation != null)
{
    // Generate an explanation
    string explanation = await travelAdvisorService.GenerateExplanationAsync(
        topRecommendation, travelQuery);

    Console.WriteLine("Explanation:");
    Console.WriteLine(explanation);
}
```

### Example 4: Answering a Follow-up Question

```csharp
// Answer a follow-up question
string followUpQuestion = "How would the duration change during rush hour?";
string answer = await travelAdvisorService.AnswerFollowUpQuestionAsync(
    followUpQuestion, topRecommendation, travelQuery);

Console.WriteLine("Answer to follow-up question:");
Console.WriteLine(answer);
