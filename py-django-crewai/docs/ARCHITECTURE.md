# Movie Chatbot Architecture

This document outlines the architecture of the Movie Chatbot application, a Django-based web application that utilizes CrewAI for intelligent movie recommendations and theater information.

## Table of Contents

- [System Overview](#system-overview)
- [Architectural Patterns](#architectural-patterns)
- [Component Architecture](#component-architecture)
- [Data Flow](#data-flow)
- [Technology Stack](#technology-stack)
- [Agent Orchestration](#agent-orchestration)
- [Integration Architecture](#integration-architecture)
- [Deployment Architecture](#deployment-architecture)
- [Error Handling & Resilience](#error-handling--resilience)
- [Security Considerations](#security-considerations)
- [References & Resources](#references--resources)

## System Overview

The Movie Chatbot is a web application that enables users to interact with an AI-powered chatbot to:

- Find movies based on their interests and preferences
- Get movie recommendations with detailed information
- Find nearby theaters showing those movies
- View available showtimes

The system provides two primary modes:

- **First Run Mode**: Focused on current movies playing in theaters with real showtimes
- **Casual Viewing Mode**: For exploring movies from any time period without theater information

The system leverages CrewAI to coordinate multiple AI agents working together, processing natural language queries, searching for movie information, making recommendations, and finding relevant theater and showtime data.

## Architectural Patterns

The application follows these architectural patterns:

1. **Model-View-Controller (MVC)** - Implemented through Django's MTV (Model-Template-View) pattern
2. **Microagent Architecture** - Using CrewAI to coordinate multiple specialized AI agents
3. **Service-Oriented Architecture** - Integration with external APIs via service adapters
4. **Repository Pattern** - Data access through Django's ORM
5. **Cloud-Native Design** - Built for deployment to Cloud Foundry with service binding support
6. **Feature Toggle Pattern** - Support for two distinct conversation modes (First Run/Casual Viewing)
7. **Graceful Degradation** - Multiple fallback mechanisms for location detection and theater data

## Component Architecture

```mermaid
graph TD
    User[User] --> WebUI[Web UI]
    WebUI --> Django[Django Web Framework]
    Django --> ChatbotController[Chatbot Controller]
    ChatbotController --> MovieCrewManager[Movie Crew Manager]

    subgraph "CrewAI Framework"
        MovieCrewManager --- ModularManager[Modular Implementation]
        ModularManager --> TaskSequencer[Task Sequencer]
        TaskSequencer --> Crew[Crew Object]
        Crew --> Agents[AI Agents]
        Crew --> Tasks[Task Definitions]

        Agents --> MovieFinder[Movie Finder Agent]
        Agents --> Recommender[Recommendation Agent]
        Agents --> TheaterFinder[Theater Finder Agent]

        Tasks --> SearchTask[Movie Search Task]
        Tasks --> RecommendTask[Recommendation Task]
        Tasks --> TheaterTask[Theater Finder Task]

        SearchTask --- SearchTool[SearchMoviesTool]
        RecommendTask --- AnalyzeTool[AnalyzePreferencesTool]
        TheaterTask --- TheaterTool[FindTheatersTool]

        SearchTool --> TMDb[TMDb API]
        TheaterTool --> LocationService[Theater/Location Services]
        TheaterTool --> SerpAPI[SerpAPI Showtimes]
    end

    ChatbotController --> DatabaseLayer[Database Layer]
    DatabaseLayer --> Models[Data Models]

    LocationService --> BrowserGeo[Browser Geolocation]
    LocationService --> IPApiGeo[ipapi.co Geolocation]
    LocationService --> Nominatim[Nominatim API]
    LocationService --> OverpassAPI[Overpass API]

    subgraph "Data Models"
        Conversation[Conversation]
        Message[Message]
        MovieRecommendation[Movie Recommendation]
        Theater[Theater]
        Showtime[Showtime]
    end
```

### Main Components

1. **Web UI Layer**
   - Django templates with Bootstrap styling
   - JavaScript for asynchronous chat interactions
   - AJAX for handling requests without page refresh
   - Interactive progress feedback with contextual messages
   - Automatic browser location detection
   - Tab-based interface for switching between conversation modes
   - Dynamic showtimes display with theater sorting

2. **Django Application**
   - URL routing and request handling
   - Session management for multiple conversations
   - CSRF protection
   - Template rendering
   - IP detection for geolocation services
   - Environment detection (Cloud Foundry vs. local)

3. **Chatbot Controller**
   - Manages conversation state for both modes
   - Processes user input with robust error handling
   - Delegates natural language processing to CrewAI
   - Formats responses for the UI
   - Handles theater and showtime data processing

4. **Movie Crew Manager**
   - Implements a wrapper around modular components
   - Coordinates AI agents via CrewAI
   - Processes query results with error recovery
   - Handles agent communication failures
   - Formats structured data
   - Classifies movies as current releases or older movies
   - Manages mode-specific behavior (First Run vs. Casual Viewing)

5. **CrewAI Agents**
   - **Movie Finder Agent**: Searches for movies based on user preferences
     - Handles genre detection, decade filtering, and keyword extraction
     - Prioritizes current releases for First Run mode
     - Performs historical searches for Casual Viewing mode
   - **Recommendation Agent**: Ranks and selects the best movie options
     - Analyzes user preferences against movie features
     - Provides justifications for each recommendation
     - Considers recency, popularity, and genre relevance
   - **Theater Finder Agent**: Locates theaters and showtimes for recommended movies
     - Integrates geolocation data
     - Searches for real theaters near the user
     - Retrieves and formats showtime information

6. **CrewAI Tools**
   - **SearchMoviesTool**: Pydantic-based tool for searching TMDb movies
     - Supports different search strategies based on conversation mode
     - Handles advanced filters like decades and genres
     - Enhanced image quality selection
   - **AnalyzePreferencesTool**: Tool for analyzing movie preferences
     - Extracts user preferences from natural language
     - Manages preference weighting and scoring
   - **FindTheatersTool**: Tool for finding theaters showing recommended movies
     - Integrates with geolocation services
     - Processes and validates theater data
     - Formats showtimes with timezone adjustment

7. **Geolocation Services**
   - **Browser Geolocation**: Uses browser's navigator.geolocation API
     - Requires user consent
     - Provides high accuracy when available
   - **ipapi.co Geolocation**: Free IP-based geolocation service
     - Used for automatic location detection
     - Provides city, state, and timezone information
     - US-location validation and formatting
   - **Nominatim**: OpenStreetMap geocoding service
     - Converts location text to coordinates
     - Provides reverse geocoding for user-friendly display
   - **Overpass API**: OpenStreetMap query service for finding theaters
     - Finds real theaters based on amenity tags
     - Supports radius-based searches

8. **External Service Integrations**
   - **The Movie Database (TMDb) API**: Movie information and metadata
     - Current releases and historical movie data
     - Enhanced image quality selection
     - Detailed movie information (cast, genres, ratings)
   - **SerpAPI Google Showtimes**: Real-time movie showtimes data
     - Theater information for current movies
     - Structured showtimes with formats (IMAX, 3D, etc.)
   - **OpenStreetMap APIs**: Location data and services
     - Geocoding and reverse geocoding
     - Theater location data
   - **ipapi.co API**: IP-based geolocation
     - City, state, country information
     - Timezone detection
     - US location validation

9. **Database Layer**
   - Conversation persistence with mode tracking
   - Message history
   - Movie recommendations with metadata
   - Theater and showtime information
   - Location caching

## Data Flow

### Conversation Flow

```mermaid
sequenceDiagram
    participant User
    participant UI as Web UI
    participant View as Django View
    participant Manager as Movie Crew Manager
    participant CrewAI as CrewAI Framework
    participant Agents as CrewAI Agents
    participant External as External APIs
    participant DB as Database

    User->>UI: Selects conversation mode
    User->>UI: Enters movie query
    UI->>UI: Detect browser location (if First Run mode)
    UI->>UI: Show progress indicator
    UI->>View: POST /send-message/ (JSON with mode flag)
    View->>View: Extract client IP
    View->>View: Get conversation based on mode
    View->>DB: Create user message

    Note over View,Manager: Mode-specific processing
    View->>Manager: process_query(query, history, first_run_mode)

    Manager->>CrewAI: Initialize LLM with credentials
    Manager->>CrewAI: Create appropriate agents
    Manager->>CrewAI: Configure tasks based on mode

    alt First Run Mode
        CrewAI->>Agents: Movie Finder task (prioritize current)
        CrewAI->>Agents: Recommendation task
        CrewAI->>Agents: Theater Finder task
    else Casual Viewing Mode
        CrewAI->>Agents: Movie Finder task (any time period)
        CrewAI->>Agents: Recommendation task
    end

    Agents->>External: Query TMDb API

    alt First Run Mode
        Agents->>External: Get location data
        Agents->>External: Find theaters (OpenStreetMap)
        Agents->>External: Get showtimes (SerpAPI)
    end

    External->>Agents: API responses
    Agents->>CrewAI: Task outputs
    CrewAI->>Manager: Crew execution result

    Manager->>Manager: Process data and handle errors
    Manager->>Manager: Classify movies (current/older)
    Manager->>Manager: Combine movies with theaters (First Run)
    Manager->>View: Return structured response

    View->>DB: Store bot message
    View->>DB: Store movie recommendations
    View->>DB: Store theaters & showtimes

    View->>UI: JSON response with recommendations
    UI->>UI: Update progress bar (100%)
    UI->>UI: Update conversation display

    alt First Run Mode
        UI->>UI: Show movies with theaters & showtimes
    else Casual Viewing Mode
        UI->>UI: Show historical movie recommendations
    end

    UI->>User: Display complete response
```

### Data Processing Flow

1. **User Input & Mode Selection**
   - User selects conversation mode (First Run or Casual Viewing)
   - User enters a text query in the selected mode's input field
   - If in First Run mode, browser attempts to get user's geolocation
   - Frontend validates and sends data to backend via AJAX with mode flag

2. **Request Processing**
   - Django view identifies the appropriate conversation based on mode
   - Message content, location, and client IP address are extracted
   - Conversation and message are stored in the database
   - Message and mode flag are passed to the Movie Crew Manager

3. **AI Agent Orchestration**
   - The Movie Crew Manager initializes the appropriate LLM
   - Different tasks are configured based on the conversation mode
   - CrewAI tasks are executed in sequence:
     1. Movie Finder Agent searches for relevant movies
        - In First Run mode: prioritizes current theatrical releases
        - In Casual Viewing mode: searches any time period
     2. Recommendation Agent selects and ranks the best options
     3. Theater Finder Agent locates theaters and showtimes (First Run mode only)
   - Each agent has specialized tools for its specific tasks
   - The CrewOutput object is processed to extract results from each agent

4. **Location & Theater Processing (First Run Mode)**
   - User location is determined through multi-level fallback:
     1. Explicitly provided location text (user entry)
     2. Browser geolocation API (requires permission)
     3. ipapi.co IP-based geolocation (automatic)
     4. Default location (when all else fails)
   - US-based validation ensures theater data compatibility
   - Geocoding converts location to coordinates
   - OpenStreetMap Overpass API finds real theaters near coordinates
   - SerpAPI provides real-time movie showtimes

5. **Movie Classification & Processing**
   - Movies are classified as current or older releases
   - Current releases: Movies from current year or previous year
   - Older releases: Movies more than one year old
   - Only current releases are shown with theater information
   - In First Run mode, current releases are prioritized
   - In Casual Viewing mode, all matching movies are shown

6. **Response Generation**
   - Results from agents are parsed and validated with error recovery
   - A natural language response is generated
   - Structured data (movies, theaters, showtimes) is prepared
   - Mode-specific formatting is applied

7. **Data Persistence**
   - Bot message is stored in the database
   - Movie recommendations are stored with metadata
   - Theater and showtime information is linked to recommendations (First Run mode)

8. **Response Delivery**
   - JSON response is sent back to the frontend
   - UI updates progress bar to 100% and hides it
   - In First Run mode:
     - UI filters recommendations to show only current movies with theaters
     - Date tabs are created for showtime navigation
   - In Casual Viewing mode:
     - Historical movies are displayed without theater information
   - UI displays the bot message and recommendations
   - Showtimes are formatted in 24-hour format

## Technology Stack

### Backend

- **Django 5.2**: Web framework for handling HTTP requests, routing, and templating
- **CrewAI 0.114.0**: Framework for coordinating multiple AI agents
- **LangChain 0.3.22**: Framework for LLM application development
- **LangChain-OpenAI 0.3.12**: OpenAI integration for LangChain
- **Pydantic 2.11.2**: Data validation and settings management
- **TMDbSimple 2.9.1**: Python wrapper for The Movie Database API
- **Google-Search-Results 2.4.2**: SerpAPI client for real showtimes
- **Geopy 2.4.1**: Geocoding and distance calculations
- **Requests 2.31.0**: HTTP client for API interactions
- **WhiteNoise 6.9.0**: Static file serving for production
- **Gunicorn 23.0.0**: WSGI HTTP server for production deployment
- **SQLite/PostgreSQL**: Database (configurable via DATABASE_URL)

### Frontend

- **HTML/CSS/JavaScript**: Standard web technologies
- **Bootstrap 5.3.0**: CSS framework for responsive design
- **Bootstrap Icons 1.11.3**: Icon library
- **Fetch API**: For asynchronous requests
- **Browser Geolocation API**: For user location detection

### External Services

- **LLM API**: Configurable LLM endpoint (compatible with OpenAI API)
- **TMDb API**: The Movie Database for movie information
- **SerpAPI Google Showtimes**: Real movie showtime data
- **ipapi.co API**: IP-based geolocation and timezone detection
- **OpenStreetMap APIs**:
  - **Nominatim**: Geocoding service
  - **Overpass API**: Theater location data
  - **Reverse Geocoding**: Converting coordinates to addresses

### DevOps & Deployment

- **Cloud Foundry**: Platform for deployment
- **cfenv 0.5.3**: Library for Cloud Foundry environment parsing
- **python-dotenv 1.1.0**: Environment variable management
- **dj-database-url 2.3.0**: Database URL configuration

## Agent Orchestration

The application uses CrewAI to implement a multiple-agent system, where each agent has a specialized role. This section details how these agents work together.

### CrewAI Implementation

```mermaid
graph TD
    User[User Query] --> Manager[Movie Crew Manager]
    Manager --> Crew[CrewAI Crew]

    Crew --> A1[Movie Finder Agent]
    Crew --> A2[Recommendation Agent]
    Crew --> A3[Theater Finder Agent]

    A1 --> T1[Search Movies Tool]
    A2 --> T2[Analyze Preferences Tool]
    A3 --> T3[Find Theaters Tool]

    Manager --> TaskSequence[Task Sequence Definition]
    TaskSequence --> Crew

    subgraph "Task Execution Flow"
        direction LR
        Task1[Find Movies] --> Task2[Recommend Top Movies]
        Task2 --> Task3[Find Theaters & Showtimes]
    end

    Manager --> ModeSelector[Mode Selector]
    ModeSelector --> FirstRun[First Run Mode]
    ModeSelector --> Casual[Casual Mode]

    FirstRun -.-> Task1
    FirstRun -.-> Task2
    FirstRun -.-> Task3

    Casual -.-> Task1
    Casual -.-> Task2
```

### Agent Definitions

Agents are defined using CrewAI's agent architecture, with specific roles, goals, and backstories:

```python
# Movie Finder Agent
movie_finder = Agent(
    role="Movie Finder",
    goal="Find movies that match the user's criteria",
    backstory="""You are an expert movie finder who knows everything about movies. Your job is to help users
              find movies that match their preferences, including genre, actors, directors, themes, and more.
              You use The Movie Database API to find the most relevant movies based on user queries.""",
    verbose=True,
    llm=llm
)

# Recommendation Agent
recommender = RecommendationAgent.create(llm)

# Theater Finder Agent
theater_finder = TheaterFinderAgent.create(llm)
```

### Task Definitions

Tasks are dynamically configured based on the conversation mode:

```python
# Create the tasks with the defined tools
find_movies_task = Task(
    description=f"Find movies that match the user's criteria: '{query}'",
    expected_output="A JSON list of relevant movies with title, overview, release date, and TMDb ID",
    agent=movie_finder,
    tools=[search_tool]
)

recommend_movies_task = Task(
    description=f"Recommend the top {max_recommendations} movies from the list that best match the user's preferences",
    expected_output=f"A JSON list of the top {max_recommendations} recommended movies with explanations",
    agent=recommender,
    tools=[analyze_tool]
)

find_theaters_task = Task(
    description="Find theaters showing the recommended movies near the user's location",
    expected_output="A JSON list of theaters showing the recommended movies with showtimes",
    agent=theater_finder,
    tools=[theater_finder_tool]
)
```

### Mode-Specific Crew Configuration

The application creates different crews based on the conversation mode:

```python
# For First Run mode (theater-based recommendations), include all agents and tasks
if first_run_mode:
    crew = Crew(
        agents=[movie_finder, recommender, theater_finder],
        tasks=[find_movies_task, recommend_movies_task, find_theaters_task],
        verbose=True
    )
else:
    # For Casual Viewing mode, skip the theater finder
    crew = Crew(
        agents=[movie_finder, recommender],
        tasks=[find_movies_task, recommend_movies_task],
        verbose=True
    )
```

### Tool Implementation

Each agent uses specialized Pydantic-based tools:

1. **SearchMoviesTool**:
   - Searches TMDb API with comprehensive filtering
   - Handles decade detection, genre filtering, and current release identification
   - Mode-aware searching (prioritizes current releases in First Run mode)

2. **AnalyzePreferencesTool**:
   - Extracts user preferences from natural language
   - Ranks movies based on preference matching
   - Generates explanations for recommendations

3. **FindTheatersTool**:
   - Integrates location services
   - Searches for real theaters using OpenStreetMap
   - Retrieves showtimes for recommended movies
   - Formats theater and showtime data for display

## Integration Architecture

### LLM Integration

The application is designed to integrate with any LLM service that provides an OpenAI-compatible API. It supports:

1. **Cloud Foundry Service Binding**: Automatically detects and uses credentials from bound GenAI services
2. **Manual Configuration**: Supports custom API keys and endpoints via environment variables
3. **Model Selection**: Configurable LLM model (default: gpt-4o-mini)

```python
# LLM Configuration from settings.py
def get_llm_config():
    # Check if running in Cloud Foundry with bound services
    if cf_env.get_service(label='genai') or cf_env.get_service(name='movie-chatbot-llm'):
        service = cf_env.get_service(label='genai') or cf_env.get_service(name='movie-chatbot-llm')
        credentials = service.credentials

        return {
            'api_key': credentials.get('api_key') or credentials.get('apiKey'),
            'base_url': credentials.get('url') or credentials.get('baseUrl'),
            'model': credentials.get('model') or 'gpt-4o-mini'
        }

    # Fallback to environment variables for local development
    return {
        'api_key': os.getenv('OPENAI_API_KEY'),
        'base_url': os.getenv('LLM_BASE_URL'),
        'model': os.getenv('LLM_MODEL', 'gpt-4o-mini')
    }
```

### TMDb API Integration

The application uses TMDbSimple to interact with The Movie Database API for movie information:

- Movie search functionality
- Detailed movie information
- Now playing movies
- Movie credits and genres
- Enhanced image quality selection

Code example for searching current movies:

```python
# Check for currently playing movies in TMDB
now_playing = tmdb.Movies()
response = now_playing.now_playing()

if response and 'results' in response and response['results']:
    # Filter by genre if specified
    results = response['results']
    if genres:
        results = [movie for movie in results if any(genre_id in movie.get('genre_ids', []) for genre_id in genres)]

    # Process limited number of results
    results_limit = getattr(settings, 'MOVIE_RESULTS_LIMIT', 5)
    results = results[:results_limit]
    for movie in results:
        movie_id = movie.get('id')
        title = movie.get('title', 'Unknown Title')
        overview = movie.get('overview', '')
        release_date = movie.get('release_date', '')
        poster_path = movie.get('poster_path', '')

        # Get full-size poster image
        poster_url = ""
        if poster_path:
            poster_url = f"https://image.tmdb.org/t/p/original{poster_path}"
```

### SerpAPI Integration

The application uses SerpAPI to fetch real movie showtime data:

```python
class SerpShowtimeService:
    """Service for fetching movie showtimes using SerpAPI."""

    def __init__(self, api_key: str):
        """Initialize the SerpAPI service."""
        self.api_key = api_key

    def search_showtimes(self, movie_title: str, location: str):
        """Search for movie showtimes for a specific movie in a location."""
        # Construct parameters for SerpAPI
        params = {
            "q": f"{movie_title} theater",
            "location": location,
            "hl": "en",
            "gl": "us",
            "api_key": self.api_key
        }

        # Execute the search
        search = GoogleSearch(params)
        results = search.get_dict()

        # Process and format the results
        theaters = self._parse_serp_results(results, movie_title)
        return theaters
```

### Geolocation Services

The application uses multiple approaches to determine user location:

1. **Browser Geolocation**

   ```javascript
   if (navigator.geolocation) {
       navigator.geolocation.getCurrentPosition(
           function(position) {
               const latitude = position.coords.latitude;
               const longitude = position.coords.longitude;

               // Use reverse geocoding to get readable location
               fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`)
                   .then(response => response.json())
                   .then(data => {
                       const locationName = data.display_name;
                       locationInput.value = locationName;
                   });
           }
       );
   }
   ```

2. **ipapi.co Geolocation**

   ```javascript
   // Function to gather location and timezone data from ipapi.co
   function gatherLocationDataFromIpApi() {
       console.log("Using ipapi.co for location and timezone detection");

       // Use ipapi.co - no API key needed
       fetch('https://ipapi.co/json/')
       .then(response => {
           if (!response.ok) {
               throw new Error(`HTTP error! status: ${response.status}`);
           }
           return response.json();
       })
       .then(data => {
           console.log("Received ipapi.co response:", data);

           // Check if location is in the US
           if (!isLocationInUS(data.country_code)) {
               console.log(`Detected non-US location: ${data.country_name || 'unknown'}`);
               handleNonUSLocation();
               return;
           }

           // Capture timezone information
           if (data.timezone) {
               window.userTimezone = data.timezone;
               console.log(`Captured user timezone: ${window.userTimezone}`);
           }

           // Extract city and state for US locations
           const city = data.city;
           const state = data.region;
           const country = data.country_name;

           // If we have all values, use the standard "City, State, Country" format
           if (city && state && country) {
               const locationName = `${city}, ${state}, ${country}`;
               console.log(`Setting location to: ${locationName}`);
               locationInput.value = locationName;
               return;
           }
       })
   }
   ```

3. **Server-side IP Geolocation**

   ```python
   def get_location_from_ip(self, ip_address: str):
       """Get user location from IP address."""
       response = requests.get(f"https://ipinfo.io/{ip_address}/json")

       if response.status_code == 200:
           data = response.json()
           if 'loc' in data:
               lat, lon = data['loc'].split(',')
               city = data.get('city', '')
               region = data.get('region', '')
               country = data.get('country', '')

               return {
                   "latitude": float(lat),
                   "longitude": float(lon),
                   "display_name": f"{city}, {region}, {country}"
               }
   ```

4. **Theater Search with OpenStreetMap**

   ```python
   def search_theaters(self, latitude: float, longitude: float, radius_miles: float = 20):
       """Search for movie theaters within a specified radius."""
       # Convert radius to meters for API
       radius_meters = radius_miles * 1609.34

       # Build Overpass API query for movie theaters
       overpass_query = f"""
       [out:json];
       (
           node["amenity"="cinema"](around:{radius_meters},{latitude},{longitude});
           way["amenity"="cinema"](around:{radius_meters},{latitude},{longitude});
           relation["amenity"="cinema"](around:{radius_meters},{latitude},{longitude});
       );
       out center;
       """

       # Execute query and process results
       response = requests.post("https://overpass-api.de/api/interpreter", data=overpass_query)
       data = response.json()

       # Process theaters...
   ```

## Deployment Architecture

The application is designed for deployment to Tanzu Platform for Cloud Foundry:

```mermaid
graph TD
    Internet[Internet] --> CF[Cloud Foundry Router]
    CF --> App[Movie Chatbot App]

    App --> DB[(Database)]
    App --> GenAI[GenAI Service]
    App --> TMDB[TMDb API]
    App --> SerpAPI[SerpAPI]
    App --> ipapi[ipapi.co API]
    App --> OSM[OpenStreetMap APIs]

    subgraph "Tanzu Platform"
        CF
        App
        DB
        GenAI
    end

    subgraph "External Services"
        TMDB
        SerpAPI
        ipapi
        OSM
    end
```

### Deployment Process

1. **Preparation**
   - Configure environment variables or create `.env` file
   - Collect static files: `python manage.py collectstatic --noinput`

2. **Cloud Foundry Deployment**
   - CF Push using manifest.yml
   - Bind to a GenAI service instance
   - Bind to a database service (if needed)
   - Restage the application

3. **Configuration**
   - The application auto-detects Cloud Foundry environment
   - Service credentials are automatically extracted from VCAP_SERVICES
   - Database connection is configured via DATABASE_URL

## Error Handling & Resilience

The application implements several error handling and resilience patterns:

1. **Location Detection Resilience**
   - Multi-tiered fallback system:
     1. Browser geolocation API (primary)
     2. ipapi.co geolocation (automatic fallback)
     3. User-provided location entry (manual fallback)
     4. Default location (last resort)
   - US location validation for theater compatibility
   - Robust geocoding error handling
   - Timezone detection for accurate showtimes

2. **Conversation Mode Separation**
   - Distinct conversations for First Run and Casual Viewing modes
   - Mode-specific error handling and response formatting
   - Graceful degradation when switching between modes

3. **Showtime Service Resilience**
   - SerpAPI integration with fallback to generated showtimes
   - Theater validation to ensure theaters show relevant movies
   - Time zone handling for correct showtime display
   - Sorting by distance for better user experience

4. **Movie Classification System**
   - Current vs. older release detection
   - Year-specific searches for historical movies
   - Decade detection for nostalgic requests
   - Conditional UI rendering based on movie type

5. **API Response Validation**
   - All external API responses are validated before processing
   - Default values provided for missing or invalid data
   - Retries with exponential backoff for transient failures

6. **JSON Parsing with Error Recovery**
   - Robust JSON parsing with multiple fallback mechanisms
   - Support for extracting JSON from text responses
   - String cleaning and repair for malformed JSON
   - Manual JSON extraction for complex responses

7. **Exception Boundary Pattern**
   - Top-level exception handling in Django views
   - Detailed logging with context for debugging
   - Graceful error responses to users
   - Recovery strategies for common failure scenarios

8. **Fallback Responses**
   - Default responses when AI services fail
   - Helpful error messages that maintain conversation flow
   - Alternative search strategies when primary methods fail

9. **Extensive Logging**
   - Detailed logging with contextual information
   - Structured logging with request IDs
   - Log levels appropriate for different environments
   - Timing information for performance monitoring

10. **Request Validation**
    - Comprehensive input sanitization
    - Multiple parsing strategies for request data
    - Robust key extraction with fallbacks
    - Request size limits and timeouts

## Security Considerations

1. **API Key Protection**
   - API keys stored in environment variables
   - No hardcoded credentials in source code
   - Service binding for secure credential management

2. **Web Security**
   - CSRF protection for form submissions
   - Content-Security-Policy headers
   - XSS prevention in templates
   - Input validation and sanitization

3. **Input Validation**
   - All user input validated before processing
   - Safe handling of JSON data
   - Request size limits
   - Character encoding validation

4. **Service Binding Security**
   - Secure credential handling from bound services
   - Automatic management of service credentials
   - No credential persistence in application code

5. **Geolocation Privacy**
   - Browser geolocation requires explicit user consent
   - IP geolocation as fallback only
   - Location data not stored permanently
   - US location validation for theater compatibility
   - Timezone information used only for showtime display

6. **Data Minimization**
   - Only essential data is collected and stored
   - Conversations and recommendations can be reset
   - No personal user profiles are maintained
   - Location data is used transiently for theater search

## References & Resources

### Core Technologies

- [Django](https://docs.djangoproject.com/)
- [CrewAI](https://docs.crewai.com/)
- [LangChain](https://python.langchain.com/docs/get_started/introduction)
- [TMDb API](https://developer.themoviedb.org/docs/getting-started)
- [SerpAPI](https://serpapi.com/showtimes-results)
- [OpenStreetMap API](https://wiki.openstreetmap.org/wiki/API)
- [Overpass API](https://wiki.openstreetmap.org/wiki/Overpass_API)
- [Nominatim API](https://nominatim.org/release-docs/latest/api/Overview/)
- [ipapi.co](https://ipapi.co/api/)
- [IPInfo.io](https://ipinfo.io/developers)

### Cloud Foundry

- [Cloud Foundry](https://docs.cloudfoundry.org/)
- [Tanzu Platform](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform/index.html)
- [GenAI Tile](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform/1.5/tap/services-genai.html)
- [Service Binding](https://docs.cloudfoundry.org/devguide/services/managing-services.html)

### Design Patterns

- [CrewAI Design Patterns](https://github.com/joaomdmoura/crewAI/tree/main/docs/examples)
- [LangChain Chain Patterns](https://python.langchain.com/docs/modules/chains/)
- [Django Design Patterns](https://djangopatterns.readthedocs.io/en/latest/)
- [Multi-agent AI Patterns](https://www.deeplearning.ai/short-courses/multi-agent-collaboration/)
