# External API Integration

This document provides detailed information about the external APIs used in the Movie Booking Chatbot application, including authentication methods, key endpoints, and implementation details.

## Table of Contents

- [LLM API Integration](#llm-api-integration)
- [The Movie Database (TMDb) API](#the-movie-database-tmdb-api)
- [SerpAPI Google Showtimes](#serpapi-google-showtimes)
- [Geolocation Services](#geolocation-services)
  - [Browser Geolocation API](#browser-geolocation-api)
  - [ipapi.co](#ipapico)
  - [OpenStreetMap Services](#openstreetmap-services)
- [Error Handling](#error-handling)
- [Testing API Integrations](#testing-api-integrations)

## LLM API Integration

The application is designed to work with any LLM service that provides an OpenAI-compatible API interface.

### Configuration

The application supports multiple ways to configure LLM integration:

1. **Cloud Foundry Service Binding**: Automatically detects and uses credentials from bound GenAI services
2. **Environment Variables**: Supports manual configuration via environment variables
3. **Model Selection**: Configurable LLM model (default: gpt-4o-mini)

### Implementation

```python
# LLM Configuration from settings.py
def get_llm_config():
    # Check if running in Cloud Foundry with bound services
    if cf_env.get_service(label='genai') or cf_env.get_service(name='my-llm-service'):
        service = cf_env.get_service(label='genai') or cf_env.get_service(name='my-llm-service')
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

### LLM Usage with CrewAI

The application initializes LLM models for CrewAI agents:

```python
def create_llm(self, temperature: float = 0.5) -> ChatOpenAI:
    """
    Create an LLM instance with the specified configuration.

    Args:
        temperature: Temperature parameter for the LLM

    Returns:
        Configured ChatOpenAI instance
    """
    config = {
        "openai_api_key": self.api_key,
        "model": self.model,
        "temperature": temperature,
    }

    if self.base_url:
        config["openai_api_base"] = self.base_url

    return ChatOpenAI(**config)
```

### Required Environment Variables

For local development:

```
OPENAI_API_KEY=your_api_key_here
LLM_BASE_URL=optional_custom_endpoint
LLM_MODEL=gpt-4o-mini
```

## The Movie Database (TMDb) API

The application uses [TMDb API](https://developer.themoviedb.org/reference/intro/getting-started) for movie information, including search, movie details, and images.

### Authentication

TMDb requires an API key for authentication:

```python
import tmdbsimple as tmdb

# Configure TMDb API
tmdb.API_KEY = settings.TMDB_API_KEY
```

### Key Endpoints Used

The application uses several TMDb endpoints through the tmdbsimple wrapper:

1. **Search Movies**:
   - Searches for movies based on query text
   - Supports filters for genres, years, etc.

2. **Now Playing**:
   - Retrieves movies currently in theaters
   - Used for First Run mode

3. **Movie Details**:
   - Gets detailed information about a specific movie
   - Retrieves genres, cast, ratings, etc.

4. **Movie Images**:
   - Retrieves high-quality poster images

### Implementation Example

```python
# Search for movies matching query
search = tmdb.Search()
response = search.movie(query=search_query, include_adult=False, language="en-US")

# Get now playing movies
now_playing = tmdb.Movies()
response = now_playing.now_playing()

# Get movie details
movie_details = tmdb.Movies(movie_id)
details = movie_details.info()
images = movie_details.images()
```

### Example Response (Movie Search)

```json
{
  "page": 1,
  "results": [
    {
      "adult": false,
      "backdrop_path": "/backdrop.jpg",
      "genre_ids": [28, 12, 878],
      "id": 603,
      "original_language": "en",
      "original_title": "The Matrix",
      "overview": "Set in the 22nd century, The Matrix tells the story of a computer hacker who joins a group of underground insurgents fighting the vast and powerful computers who now rule the earth.",
      "popularity": 73.481,
      "poster_path": "/poster.jpg",
      "release_date": "1999-03-30",
      "title": "The Matrix",
      "video": false,
      "vote_average": 8.1,
      "vote_count": 23500
    }
  ],
  "total_pages": 1,
  "total_results": 1
}
```

## SerpAPI Google Showtimes

The application uses [SerpAPI](https://serpapi.com/showtimes-results) to retrieve real-time movie showtimes information.

### Authentication

SerpAPI requires an API key:

```python
from serpapi import GoogleSearch

# Initialize search with API key
params = {
    "engine": "google_showtimes",
    "q": movie_title,
    "location": location,
    "api_key": settings.SERPAPI_API_KEY
}

search = GoogleSearch(params)
```

### Integration

```python
class SerpShowtimeService:
    """Service for fetching movie showtimes using SerpAPI."""

    def __init__(self, api_key: str):
        """Initialize the SerpAPI service."""
        self.api_key = api_key

    def search_showtimes(self, movie_title: str, location: str, radius_miles: int = 20):
        """Search for movie showtimes for a specific movie in a location."""
        # Construct parameters for SerpAPI
        params = {
            "engine": "google_showtimes",
            "q": movie_title,
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

### Example Response

```json
{
  "search_metadata": {
    "id": "example_search_id",
    "status": "Success",
    "json_endpoint": "https://serpapi.com/searches/example_search_id/json",
    "created_at": "2025-04-17 12:00:00 UTC",
    "processed_at": "2025-04-17 12:00:01 UTC",
    "google_showtimes_url": "https://www.google.com/search?q=Dune+showtimes&hl=en&gl=us&uule=...",
    "raw_html_file": "https://serpapi.com/searches/example_search_id/raw_html",
    "total_time_taken": 1.31
  },
  "search_parameters": {
    "engine": "google_showtimes",
    "q": "Dune",
    "location": "Seattle, Washington, United States",
    "hl": "en",
    "gl": "us"
  },
  "showtimes": [
    {
      "theater": {
        "name": "AMC Pacific Place 11",
        "address": "600 Pine St Suite 400, Seattle, WA 98101"
      },
      "movie": "Dune: Part Two",
      "thumbnail": "https://example.com/thumbnail.jpg",
      "showtimes": [
        {
          "datetime": "2025-04-17T14:30:00-07:00",
          "theatre_format": "Standard"
        },
        {
          "datetime": "2025-04-17T18:00:00-07:00",
          "theatre_format": "IMAX"
        }
      ]
    }
  ]
}
```

## Geolocation Services

The application uses multiple geolocation services to determine user location and find nearby theaters.

### Browser Geolocation API

Used to get precise user coordinates directly from the browser:

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

### ipapi.co

Used as a fallback when browser geolocation is unavailable or denied:

```javascript
// Function to gather location and timezone data from ipapi.co
function gatherLocationDataFromIpApi() {
    // Use ipapi.co - no API key needed
    fetch('https://ipapi.co/json/')
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // Check if location is in the US
        if (!isLocationInUS(data.country_code)) {
            console.log(`Detected non-US location: ${data.country_name || 'unknown'}`);
            handleNonUSLocation();
            return;
        }

        // Extract city and state for US locations
        const city = data.city;
        const state = data.region;
        const country = data.country_name;

        // Capture timezone information
        if (data.timezone) {
            window.userTimezone = data.timezone;
        }

        // Format location
        if (city && state && country) {
            const locationName = `${city}, ${state}, ${country}`;
            locationInput.value = locationName;
        }
    })
}
```

#### ipapi.co Response Format

```json
{
  "ip": "8.8.8.8",
  "version": "IPv4",
  "city": "Mountain View",
  "region": "California",
  "region_code": "CA",
  "country": "US",
  "country_name": "United States",
  "country_code": "US",
  "country_code_iso3": "USA",
  "country_capital": "Washington",
  "country_tld": ".us",
  "continent_code": "NA",
  "in_eu": false,
  "postal": "94035",
  "latitude": 37.386,
  "longitude": -122.0838,
  "timezone": "America/Los_Angeles",
  "utc_offset": "-0700",
  "country_calling_code": "+1",
  "currency": "USD",
  "currency_name": "Dollar",
  "languages": "en-US,es-US,haw,fr",
  "asn": "AS15169",
  "org": "Google LLC"
}
```

### OpenStreetMap Services

#### Nominatim API

Used for geocoding and reverse geocoding:

```python
def geocode_location(location_str: str):
    """Convert location string to coordinates using Nominatim."""
    try:
        geolocator = Nominatim(user_agent="movie_booking_chatbot")
        location = geolocator.geocode(location_str)
        
        if location:
            return {
                "latitude": location.latitude,
                "longitude": location.longitude,
                "display_name": location.address
            }
    except Exception as e:
        logger.error(f"Geocoding error: {str(e)}")
    
    return None
```

#### Overpass API

Used to find actual theaters near coordinates:

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

## Error Handling

The application implements robust error handling for all API interactions:

### API Error Handling

```python
try:
    # API call
    response = requests.get(api_url, params=params, timeout=settings.API_REQUEST_TIMEOUT_SECONDS)
    response.raise_for_status()  # Raise exception for 4XX/5XX errors
    
    # Process response
    data = response.json()
    # ...
except requests.exceptions.RequestException as e:
    logger.error(f"API request error: {str(e)}")
    # Implement fallback strategy
except json.JSONDecodeError as e:
    logger.error(f"JSON parsing error: {str(e)}")
    # Handle invalid JSON
except Exception as e:
    logger.error(f"Unexpected error: {str(e)}")
    # General error handling
```

### Retry Mechanism

For transient API failures:

```python
from requests.adapters import HTTPAdapter
from requests.packages.urllib3.util.retry import Retry

# Configure retry strategy
retry_strategy = Retry(
    total=settings.API_MAX_RETRIES,
    backoff_factor=settings.API_RETRY_BACKOFF_FACTOR,
    status_forcelist=[429, 500, 502, 503, 504]
)

# Create session with retry
session = requests.Session()
adapter = HTTPAdapter(max_retries=retry_strategy)
session.mount("http://", adapter)
session.mount("https://", adapter)

# Make request with retry logic
response = session.get(api_url, timeout=settings.API_REQUEST_TIMEOUT_SECONDS)
```

## Testing API Integrations

The application includes comprehensive testing for API integrations:

### Mock Responses

```python
@patch('requests.get')
def test_tmdb_api_search(mock_get):
    # Setup mock response
    mock_response = MagicMock()
    mock_response.status_code = 200
    mock_response.json.return_value = {"results": [{"id": 123, "title": "Test Movie"}]}
    mock_get.return_value = mock_response
    
    # Test API call
    result = search_movies("test query")
    
    # Assertions
    assert len(result) == 1
    assert result[0]["title"] == "Test Movie"
```

### Configuration for Testing

For testing, use mock APIs or test API keys:

```bash
# .env.test
TMDB_API_KEY=test_key
SERPAPI_API_KEY=test_key
USE_MOCK_APIS=True
```

### Sandbox Environments

For SerpAPI and TMDb, use sandbox/test environments:

- **TMDb API**: Use the v3 API sandbox environment
- **SerpAPI**: Use test credits for integration testing
