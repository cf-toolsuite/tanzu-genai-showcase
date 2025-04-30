# API Documentation

This document provides detailed information about the API endpoints exposed by the Congress.gov API Chatbot application, as well as the Congress.gov API endpoints that the application interacts with.

## Table of Contents

- [Application API Endpoints](#application-api-endpoints)
  - [Chat API](#chat-api)
  - [History API](#history-api)
  - [Clear API](#clear-api)
  - [Health API](#health-api)
- [Congress.gov API Integration](#congressgov-api-integration)
  - [Bill-Related Endpoints](#bill-related-endpoints)
  - [Member-Related Endpoints](#member-related-endpoints)
  - [Committee-Related Endpoints](#committee-related-endpoints)
  - [Amendment-Related Endpoints](#amendment-related-endpoints)
  - [Congressional Record Endpoints](#congressional-record-endpoints)
  - [Nomination Endpoints](#nomination-endpoints)
  - [Hearing Endpoints](#hearing-endpoints)

## Application API Endpoints

The application exposes the following API endpoints:

### Chat API

Process user messages and generate responses.

**Endpoint:** `POST /api/chat`

**Query Parameters:**

- `useTools` (optional): Boolean flag to enable or disable the use of API tools. Default is `false`.

**Request Body:**

```json
{
  "message": "string"
}
```

**Response:**

```json
{
  "response": "string"
}
```

**Example Request:**

```bash
curl -X POST http://localhost:8080/api/chat?useTools=true \
  -H "Content-Type: application/json" \
  -d '{"message": "Who are the current senators from Washington state?"}'
```

**Example Response:**

```json
{
  "response": "The current senators from Washington state are Patty Murray and Maria Cantwell, both Democrats. Patty Murray has been serving since 1993 and Maria Cantwell since 2001. They are both members of the 119th Congress (2025-2026)."
}
```

### History API

Get the conversation history.

**Endpoint:** `GET /api/history`

**Response:**

```json
{
  "history": [
    {
      "role": "string",
      "content": "string"
    }
  ]
}
```

**Example Request:**

```bash
curl -X GET http://localhost:8080/api/history
```

**Example Response:**

```json
{
  "history": [
    {
      "role": "system",
      "content": "You are a helpful assistant that provides information about the U.S. Congress, bills, amendments, and legislation."
    },
    {
      "role": "user",
      "content": "Who are the current senators from Washington state?"
    },
    {
      "role": "assistant",
      "content": "The current senators from Washington state are Patty Murray and Maria Cantwell, both Democrats. Patty Murray has been serving since 1993 and Maria Cantwell since 2001. They are both members of the 119th Congress (2025-2026)."
    }
  ]
}
```

### Clear API

Clear the conversation history.

**Endpoint:** `POST /api/clear`

**Response:**

```json
{
  "status": "string"
}
```

**Example Request:**

```bash
curl -X POST http://localhost:8080/api/clear
```

**Example Response:**

```json
{
  "status": "Conversation history cleared"
}
```

### Health API

Check the health of the application.

**Endpoint:** `GET /api/health`

**Response:**

```json
{
  "status": "string",
  "time": "string"
}
```

**Example Request:**

```bash
curl -X GET http://localhost:8080/api/health
```

**Example Response:**

```json
{
  "status": "ok",
  "time": "2025-04-29T16:57:00Z"
}
```

## Congress.gov API Integration

The application integrates with the Congress.gov API to fetch information about bills, amendments, summaries, and members. The following sections describe the Congress.gov API endpoints that the application interacts with.

### Bill-Related Endpoints

#### Search Bills

Search for bills in the Congress.gov API.

**Method:** `SearchBills(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for bills (e.g., "infrastructure", "healthcare", "education")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchBills("infrastructure", 0, 5)
```

#### Get Bill

Retrieve a specific bill by congress and bill number.

**Method:** `GetBill(congress, billNumber string) (map[string]interface{}, error)`

**Parameters:**

- `congress`: Congress number (e.g., "119" for current congress, "118" for previous congress)
- `billNumber`: Bill number including type prefix (e.g., "hr1", "s2043", "hjres43")

**Example Usage:**

```go
result, err := congressClient.GetBill("119", "hr1")
```

#### Get Bill Summary

Retrieve the summary of a specific bill.

**Method:** `GetBillSummary(congress, billNumber string) (map[string]interface{}, error)`

**Parameters:**

- `congress`: Congress number
- `billNumber`: Bill number including type prefix

**Example Usage:**

```go
result, err := congressClient.GetBillSummary("119", "hr1")
```

#### Get Bill Actions

Retrieve the actions taken on a specific bill.

**Method:** `GetBillActions(congress, billNumber string) (map[string]interface{}, error)`

**Parameters:**

- `congress`: Congress number
- `billNumber`: Bill number including type prefix

**Example Usage:**

```go
result, err := congressClient.GetBillActions("119", "hr1")
```

#### Get Bill Cosponsors

Retrieve the cosponsors of a specific bill.

**Method:** `GetBillCosponsors(congress, billNumber string) (map[string]interface{}, error)`

**Parameters:**

- `congress`: Congress number
- `billNumber`: Bill number including type prefix

**Example Usage:**

```go
result, err := congressClient.GetBillCosponsors("119", "hr1")
```

#### Get Bill Related Bills

Retrieve bills related to a specific bill.

**Method:** `GetBillRelatedBills(congress, billNumber string) (map[string]interface{}, error)`

**Parameters:**

- `congress`: Congress number
- `billNumber`: Bill number including type prefix

**Example Usage:**

```go
result, err := congressClient.GetBillRelatedBills("119", "hr1")
```

### Member-Related Endpoints

#### Search Members

Search for members of Congress.

**Method:** `SearchMembers(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for members (e.g., "Washington state senators", "Maria Cantwell", "Texas representatives")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchMembers("Washington state senators", 0, 5)
```

#### Get Member

Retrieve a specific member of Congress by bioguideId.

**Method:** `GetMember(bioguideId string) (map[string]interface{}, error)`

**Parameters:**

- `bioguideId`: The bioguide ID of the member (e.g., "C001075" for Maria Cantwell)

**Example Usage:**

```go
result, err := congressClient.GetMember("C001075")
```

#### Get Member Sponsorship

Retrieve sponsorship information for a specific member.

**Method:** `GetMemberSponsorship(bioguideId string) (map[string]interface{}, error)`

**Parameters:**

- `bioguideId`: The bioguide ID of the member

**Example Usage:**

```go
result, err := congressClient.GetMemberSponsorship("C001075")
```

#### Get Senators By State

Retrieve senators from a specific state.

**Method:** `GetSenatorsByState(stateCode string) (map[string]interface{}, error)`

**Parameters:**

- `stateCode`: Two-letter state code (e.g., "WA" for Washington, "TX" for Texas)

**Example Usage:**

```go
result, err := congressClient.GetSenatorsByState("WA")
```

#### Get Representatives By State

Retrieve representatives from a specific state.

**Method:** `GetRepresentativesByState(stateCode string) (map[string]interface{}, error)`

**Parameters:**

- `stateCode`: Two-letter state code (e.g., "WA" for Washington, "TX" for Texas)

**Example Usage:**

```go
result, err := congressClient.GetRepresentativesByState("WA")
```

### Committee-Related Endpoints

#### Search Committees

Search for congressional committees.

**Method:** `SearchCommittees(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for committees (e.g., "judiciary", "armed services", "finance")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchCommittees("judiciary", 0, 5)
```

#### Get Committee

Retrieve a specific committee by ID.

**Method:** `GetCommittee(committeeId string) (map[string]interface{}, error)`

**Parameters:**

- `committeeId`: The ID of the committee (e.g., "SSAP" for Senate Committee on Appropriations)

**Example Usage:**

```go
result, err := congressClient.GetCommittee("SSAP")
```

### Amendment-Related Endpoints

#### Search Amendments

Search for amendments in Congress.

**Method:** `SearchAmendments(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for amendments (e.g., "infrastructure", "healthcare")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchAmendments("infrastructure", 0, 5)
```

### Congressional Record Endpoints

#### Search Congressional Record

Search the congressional record for debates, proceedings, or speeches.

**Method:** `SearchCongressionalRecord(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for the congressional record (e.g., "climate change debate", "infrastructure speech")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchCongressionalRecord("climate change debate", 0, 5)
```

### Nomination Endpoints

#### Search Nominations

Search for presidential nominations requiring Senate confirmation.

**Method:** `SearchNominations(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for nominations (e.g., "Supreme Court", "Cabinet", "Federal Reserve")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchNominations("Supreme Court", 0, 5)
```

### Hearing Endpoints

#### Search Hearings

Search for congressional hearings.

**Method:** `SearchHearings(query string, offset, limit int) (map[string]interface{}, error)`

**Parameters:**

- `query`: Search query for hearings (e.g., "climate change", "tech regulation", "healthcare")
- `offset`: Offset for pagination
- `limit`: Limit for pagination

**Example Usage:**

```go
result, err := congressClient.SearchHearings("climate change", 0, 5)
```
