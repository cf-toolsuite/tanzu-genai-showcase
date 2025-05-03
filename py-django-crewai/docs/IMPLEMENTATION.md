# Implementation Details

This document provides detailed information about the implementation of the Movie Chatbot application, focusing on its architecture and performance features.

## Table of Contents

- [Architecture Overview](#architecture-overview)
- [Movie Crew Manager](#movie-crew-manager)
- [Theater Search](#theater-search)
- [Performance Optimizations](#performance-optimizations)
- [Caching System](#caching-system)
- [Parallel Processing](#parallel-processing)
- [Error Handling and Recovery](#error-handling-and-recovery)
- [Configuration Options](#configuration-options)
- [JSON Parsing and Repair](#json-parsing-and-repair)
- [Timeout Handling](#timeout-handling)

## Architecture Overview

The Movie Chatbot application is built with a modular architecture designed for performance and reliability:

1. **Movie Crew Manager**: Core component that coordinates AI agents for movie recommendations and theater searches
2. **Theater Search System**: Tools for finding theaters showing recommended movies
3. **JSON Processing**: Robust parsing with error handling strategies
4. **Logging System**: Performance-focused logging with minimal overhead
5. **Event Tracking**: Optimized event tracking for CrewAI compatibility

## Movie Crew Manager

The Movie Crew Manager is responsible for coordinating AI agents to generate movie recommendations and find theaters:

### Key Features

- **LLM Integration**: Connects with language models through a standardized interface
- **Agent Orchestration**: Coordinates specialized AI agents for different tasks
- **Result Processing**: Formats and structures agent outputs for the frontend

### CrewAI Integration

The Movie Crew Manager uses CrewAI to orchestrate multiple specialized agents:

1. **Movie Finder Agent**: Searches for movies matching user criteria using TMDb API
2. **Recommendation Agent**: Analyzes movie options and recommends the best choices
3. **Theater Finder Agent**: Locates theaters showing the recommended movies

The agents work in sequence, with each agent building on the results of the previous agent.

### Conversation Modes

The manager supports two distinct conversation modes:

1. **First Run Mode**: For current movies in theaters
   - Uses all three agents
   - Includes theater and showtime information
   - Focuses on movies currently playing

2. **Casual Viewing Mode**: For discovering movies from any time period
   - Uses only Movie Finder and Recommendation agents
   - No theater information is provided
   - Allows broader historical movie exploration

## Theater Search

The Theater Search system finds theaters showing recommended movies:

### Key Features

- **Location-Based Search**: Uses user location to find nearby theaters
- **Showtime Retrieval**: Gets up-to-date showtimes for movies playing in theaters
- **Distance Calculation**: Shows theaters sorted by distance from user location

### Theater Search Process

1. **Location Determination**: Identify user location through browser geolocation, IP-based geolocation, or manual entry
2. **Theater Search**: Find theaters within a configurable radius showing the recommended movies
3. **Showtime Retrieval**: Get showtimes for each theater and movie combination
4. **Result Sorting**: Sort theaters by distance and group showtimes by date

### External API Integration

The Theater Search system integrates with external APIs:

- **SerpAPI**: For retrieving real-time theater and showtime data
- **Geolocation Services**: For converting coordinates to addresses and finding nearby locations

## Performance Optimizations

The application implements several performance optimizations:

### Caching Strategies

1. **LLM Instance Caching**: Avoids recreating language model instances with the same configuration
2. **Result Caching**: Stores processed query results to avoid redundant processing
3. **Theater Data Caching**: Caches theater and showtime data with appropriate TTL (Time-To-Live)

### JSON Processing

Robust JSON handling with multiple approaches:

1. **Pattern Matching**: Regular expressions to extract structured data from responses
2. **Repair Mechanisms**: Smart fixes for common JSON formatting issues
3. **Fallback Methods**: Multiple parsing attempts with different strategies

### Output Extraction

The application safely extracts output with multiple fallback mechanisms:

1. **Attribute Detection**: Tries different output attribute patterns
2. **Format Conversion**: Handles varying output formats
3. **Default Values**: Provides sensible defaults when extraction fails

## Caching System

The application implements multiple caching mechanisms to improve performance:

### Query Result Cache

- **Purpose**: Stores processed query results to avoid redundant processing
- **Implementation**: In-memory cache with TTL expiry
- **Key Features**:
  - Conversation context-aware caching
  - Separate caches for different conversation modes
  - Automatic cache invalidation for time-sensitive data

### Theater Data Cache

- **Purpose**: Reduces theater API calls for the same movie/location
- **Implementation**: In-memory cache with location-specific entries
- **Key Features**:
  - Movie ID and title-based indexing
  - Location-specific subcaching
  - Short TTL to ensure data freshness

### Frontend Caching

- **Purpose**: Reduces API calls from the React frontend
- **Implementation**: React Query with configurable staleness
- **Key Features**:
  - Automatic background refetching
  - Stale-while-revalidate pattern
  - Query deduplication

## Parallel Processing

The application uses parallel processing to improve performance:

### Theater Search Parallelization

- **Purpose**: Process theaters for multiple movies simultaneously
- **Implementation**: Thread pools with dynamic worker allocation
- **Key Features**:
  - Concurrent API requests for different movies
  - Resource-aware scaling of worker threads
  - Timeout coordination across parallel tasks

### Parallel Movie Processing

- **Purpose**: Process multiple aspects of movie data simultaneously
- **Implementation**: Task-based parallelism
- **Key Features**:
  - Concurrent enhancement of movie metadata
  - Parallel image URL resolution
  - Dynamic worker allocation based on system resources

## Error Handling and Recovery

The application implements robust error handling and recovery:

### Retry Mechanisms

- **Purpose**: Recover from transient failures in external APIs
- **Implementation**: Exponential backoff with configurable parameters
- **Key Features**:
  - Configurable maximum retry attempts
  - Progressive delay between retries
  - Error-specific retry strategies

### Graceful Degradation

- **Purpose**: Maintain functionality when components fail
- **Implementation**: Fallback mechanisms for each critical function
- **Key Features**:
  - Theater search graceful fallback
  - API timeout handling
  - Partial result processing

### User Feedback

- **Purpose**: Provide clear feedback during error conditions
- **Implementation**: Progressive UI updates
- **Key Features**:
  - Real-time error notifications
  - Retry options for user-initiated recovery
  - Transparent processing status updates

## Configuration Options

The application supports the following configuration options:

### API Request Configuration

- **Purpose**: Configure API request behavior
- **Implementation**: Environment variables with reasonable defaults
- **Key Options**:
  - `API_REQUEST_TIMEOUT_SECONDS`: Maximum wait time for API responses (default: 180)
  - `API_MAX_RETRIES`: Maximum retry attempts for failed requests (default: 10)
  - `API_RETRY_BACKOFF_FACTOR`: Exponential backoff multiplier (default: 1.3)

### Theater Search Configuration

- **Purpose**: Configure theater search behavior
- **Implementation**: Environment variables with reasonable defaults
- **Key Options**:
  - `THEATER_SEARCH_RADIUS_MILES`: Search radius in miles (default: 15)
  - `MAX_THEATERS`: Maximum theaters to display (default: 10)
  - `MAX_SHOWTIMES_PER_THEATER`: Showtime limit per theater (default: 20)

### SerpAPI Configuration

- **Purpose**: Configure SerpAPI request behavior
- **Implementation**: Environment variables with reasonable defaults
- **Key Options**:
  - `SERPAPI_REQUEST_BASE_DELAY`: Base delay between requests (default: 5.0)
  - `SERPAPI_MAX_RETRIES`: Maximum retries for SerpAPI (default: 2)
  - `SERPAPI_RETRY_MULTIPLIER`: Backoff multiplier for retries (default: 1.5)

## JSON Parsing and Repair

The application implements robust JSON parsing:

### Parsing Strategies

- **Purpose**: Extract structured data from LLM outputs
- **Implementation**: Multiple parsing approaches with fallbacks
- **Key Features**:
  - Regular expression-based extraction
  - Format detection and normalization
  - Structure validation

### Repair Strategies

- **Purpose**: Fix common JSON formatting issues
- **Implementation**: Pattern-based corrections
- **Key Features**:
  - Trailing comma removal
  - Quote fixing
  - Bracket balancing
  - Default value substitution

## Timeout Handling

The application implements comprehensive timeout handling:

### Request Timeouts

- **Purpose**: Prevent hanging on slow external APIs
- **Implementation**: Configurable timeout thresholds
- **Key Features**:
  - Per-request timeout configuration
  - Timeout monitoring and logging
  - Partial result handling

### Dynamic Timeout Management

- **Purpose**: Coordinate timeouts across dependent operations
- **Implementation**: Time-remaining calculations
- **Key Features**:
  - Global operation timeout
  - Remaining time distribution to sub-operations
  - Minimum time guarantees for critical operations

### Timeout Recovery

- **Purpose**: Gracefully handle timeout conditions
- **Implementation**: Partial result processing with user feedback
- **Key Features**:
  - Timeout-specific error messages
  - Partial data display
  - Retry options for user-initiated recovery
