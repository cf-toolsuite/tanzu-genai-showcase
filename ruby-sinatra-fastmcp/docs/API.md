# API Documentation

This document provides comprehensive documentation for the Flight Tracking Chatbot's API endpoints.

## API Overview

The Flight Tracking Chatbot exposes a RESTful API that allows you to search for flights, airports, and flight schedules. All endpoints return JSON responses and accept query parameters.

### Base URL

When running locally:

```
http://localhost:4567/api
```

When deployed to Cloud Foundry:

```
https://your-app-name.your-cf-domain.com/api
```

## Authentication

Currently, the API does not require authentication for requests. However, the application requires an AviationStack API key to be configured via environment variables or service bindings.

## Rate Limiting

The API is subject to rate limiting imposed by the underlying AviationStack API. Free tier accounts typically have a limit of 500 requests per month.

## API Endpoints

### API Information

Returns basic information about the API.

```python
GET /api
```

#### Response

```json
{
  "message": "Flight Tracking Chatbot API is running",
  "mcp_endpoint": "/mcp",
  "api_endpoints": [
    "/api/search",
    "/api/airports",
    "/api/schedules",
    "/api/future-schedules"
  ],
  "version": "1.0.0"
}
```

### Search Flights

Search for flights based on various criteria.

```python
GET /api/search
```

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| flight_iata | string | Flight IATA code | BA123 |
| flight_icao | string | Flight ICAO code | BAW123 |
| dep_iata | string | Departure airport IATA code | LHR |
| arr_iata | string | Arrival airport IATA code | JFK |
| airline_name | string | Airline name | British Airways |
| airline_iata | string | Airline IATA code | BA |
| flight_status | string | Flight status (scheduled, active, landed, cancelled) | active |
| limit | integer | Maximum number of results to return (default: 10) | 5 |
| flight_date | string | Flight date in YYYY-MM-DD format | 2025-04-30 |

#### Response

```json
[
  {
    "flight_date": "2025-04-30",
    "flight_status": "active",
    "departure": {
      "airport": "London Heathrow Airport",
      "timezone": "Europe/London",
      "iata": "LHR",
      "icao": "EGLL",
      "terminal": "5",
      "gate": "B22",
      "delay": 15,
      "scheduled": "2025-04-30T10:00:00+00:00",
      "estimated": "2025-04-30T10:00:00+00:00",
      "actual": "2025-04-30T10:15:00+00:00"
    },
    "arrival": {
      "airport": "John F. Kennedy International Airport",
      "timezone": "America/New_York",
      "iata": "JFK",
      "icao": "KJFK",
      "terminal": "7",
      "gate": "A12",
      "delay": null,
      "scheduled": "2025-04-30T13:00:00+00:00",
      "estimated": "2025-04-30T13:15:00+00:00",
      "actual": null
    },
    "airline": {
      "name": "British Airways",
      "iata": "BA",
      "icao": "BAW"
    },
    "flight": {
      "number": "123",
      "iata": "BA123",
      "icao": "BAW123"
    },
    "aircraft": {
      "registration": "G-XWBA",
      "iata": "B77W",
      "icao": "B77W",
      "icao24": "400802"
    },
    "live": {
      "updated": "2025-04-30T11:00:00+00:00",
      "latitude": 51.28,
      "longitude": -30.45,
      "altitude": 38000,
      "direction": 270,
      "speed_horizontal": 540,
      "speed_vertical": 0,
      "is_ground": false
    }
  }
]
```

### Search Airports

Search for airports based on various criteria.

```python
GET /api/airports
```

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| iata_code | string | Airport IATA code | LHR |
| icao_code | string | Airport ICAO code | EGLL |
| airport_name | string | Airport name | Heathrow |
| city | string | City name | London |
| country | string | Country name | United Kingdom |
| limit | integer | Maximum number of results to return (default: 10) | 5 |

#### Response

```json
[
  {
    "airport_name": "London Heathrow Airport",
    "iata_code": "LHR",
    "icao_code": "EGLL",
    "latitude": 51.4706,
    "longitude": -0.461941,
    "geoname_id": "2647216",
    "timezone": "Europe/London",
    "gmt": "1",
    "phone_number": "+44 844 335 1801",
    "country_name": "United Kingdom",
    "country_iso2": "GB",
    "city_iata_code": "LON"
  }
]
```

### Get Current Flight Schedules

Get current flight schedules based on various criteria.

```python
GET /api/schedules
```

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| flight_iata | string | Flight IATA code | BA123 |
| flight_icao | string | Flight ICAO code | BAW123 |
| dep_iata | string | Departure airport IATA code | LHR |
| arr_iata | string | Arrival airport IATA code | JFK |
| airline_name | string | Airline name | British Airways |
| airline_iata | string | Airline IATA code | BA |
| flight_status | string | Flight status (scheduled, active, landed, cancelled) | scheduled |
| limit | integer | Maximum number of results to return (default: 10) | 5 |

#### Response

The response format is the same as the `/api/search` endpoint.

### Get Future Flight Schedules

Get future flight schedules for specific dates.

```python
GET /api/future-schedules
```

#### Query Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| date | string | Date in YYYY-MM-DD format | 2025-05-15 |
| iataCode | string | Airport IATA code | LHR |
| type | string | Schedule type (departure, arrival) | departure |
| limit | integer | Maximum number of results to return (default: 10) | 5 |

#### Response

```json
[
  {
    "airline": {
      "name": "British Airways",
      "iataCode": "BA"
    },
    "flight": {
      "iataNumber": "BA123",
      "icaoNumber": "BAW123"
    },
    "departure": {
      "iataCode": "LHR",
      "scheduledTime": "2025-05-15T10:00:00.000Z",
      "terminal": "5",
      "gate": "B22"
    },
    "arrival": {
      "iataCode": "JFK",
      "scheduledTime": "2025-05-15T13:00:00.000Z",
      "terminal": "7",
      "gate": "A12"
    },
    "aircraft": {
      "modelCode": "77W",
      "modelText": "Boeing 777-300ER"
    },
    "weekday": 4
  }
]
```

## Error Handling

The API returns appropriate HTTP status codes along with error messages in JSON format:

### Example Error Response

```json
{
  "error": "AviationStack API Error: 101 - Invalid API key"
}
```

### Common Error Codes

| Status Code | Description |
|-------------|-------------|
| 400 | Bad Request - Invalid parameters |
| 401 | Unauthorized - Invalid API key |
| 404 | Not Found - Resource not found |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error - Server-side error |

## Using the API with cURL

### Example: Search for a Flight

```bash
curl "http://localhost:4567/api/search?flight_iata=BA123"
```

### Example: Get Airport Information

```bash
curl "http://localhost:4567/api/airports?iata_code=LHR"
```

### Example: Get Current Flight Schedules

```bash
curl "http://localhost:4567/api/schedules?dep_iata=LHR&arr_iata=JFK"
```

### Example: Get Future Flight Schedules

```bash
curl "http://localhost:4567/api/future-schedules?date=2025-05-15&iataCode=LHR&type=departure"
```

## MCP Integration

The API also exposes an MCP endpoint at `/mcp` that allows AI models like Claude to interact with the application. See the [CLAUDE.md](CLAUDE.md) document for more information on MCP integration.

## API Implementation

The API is implemented in the `app.rb` file using Sinatra routes. Each endpoint calls the appropriate method on the `AviationStackClient` class, which handles the communication with the AviationStack API.

For more details on the implementation, see the [ARCHITECTURE.md](ARCHITECTURE.md) document.
