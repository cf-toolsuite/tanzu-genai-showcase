# External API Integration

This document provides detailed information about the external APIs used in the Movie Chatbot application, including authentication methods, key endpoints, and implementation details.

## Table of Contents

- [Django API Endpoints](#django-api-endpoints)
- [LLM API Integration](#llm-api-integration)
- [The Movie Database (TMDb) API](#the-movie-database-tmdb-api)
- [SerpAPI Google Showtimes](#serpapi-google-showtimes)
- [Geolocation Services](#geolocation-services)
  - [Browser Geolocation API](#browser-geolocation-api)
  - [ipapi.co](#ipapico)
  - [OpenStreetMap Services](#openstreetmap-services)

## Django API Endpoints

The application exposes several RESTful API endpoints for the React frontend to interact with:

### Chat Endpoints

#### First Run Mode Endpoint

```
POST /get-movies-theaters-and-showtimes/
```

**Purpose**: Process user messages in First Run mode (current movies in theaters)

**Request Body**:
```json
{
  "message": "I want to see an action movie this weekend",
  "location": "Seattle, WA, USA",
  "timezone": "America/Los_Angeles",
  "mode": "first_run"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Based on your interest in action movies, I recommend...",
  "recommendations": [
    {
      "id": 123456,
      "tmdb_id": 123456,
      "title": "Action Movie Title",
      "overview": "Movie description...",
      "release_date": "2025-04-01",
      "poster_url": "https://image.tmdb.org/t/p/original/poster_path.jpg",
      "rating": 8.5,
      "theaters": [
        {
          "name": "AMC Theater",
          "address": "123 Main St, Seattle, WA",
          "distance_miles": 2.5,
          "showtimes": [
            {
              "start_time": "2025-04-23T19:30:00-07:00",
              "format": "Standard"
            },
            {
              "start_time": "2025-04-23T22:00:00-07:00",
              "format": "IMAX"
            }
          ]
        }
      ]
    }
  ]
}
```

**Alternative Response** (when processing):
```json
{
  "status": "processing",
  "message": "Your movie recommendations are being processed. Please wait a moment.",
  "conversation_id": 123
}
```

#### Poll First Run Recommendations Endpoint

```
GET /poll-first-run-recommendations/
```

**Purpose**: Poll for First Run mode movie recommendations, theaters, and showtimes that are being processed

**Response**:
```json
{
  "status": "success",
  "message": "Based on your interest in action movies, I recommend...",
  "recommendations": [
    {
      "id": 123456,
      "tmdb_id": 123456,
      "title": "Action Movie Title",
      "overview": "Movie description...",
      "release_date": "2025-04-01",
      "poster_url": "https://image.tmdb.org/t/p/original/poster_path.jpg",
      "rating": 8.5,
      "theaters": [
        {
          "name": "AMC Theater",
          "address": "123 Main St, Seattle, WA",
          "distance_miles": 2.5,
          "showtimes": [
            {
              "start_time": "2025-04-23T19:30:00-07:00",
              "format": "Standard"
            }
          ]
        }
      ]
    }
  ]
}
```

**Alternative Response** (when still processing):
```json
{
  "status": "processing",
  "message": "Your movie recommendations are still being processed. Please wait a moment.",
  "conversation_id": 123
}
```

#### Casual Viewing Mode Endpoint

```
POST /get-movie-recommendations/
```

**Purpose**: Process user messages in Casual Viewing mode (historical movies)

**Request Body**:
```json
{
  "message": "I'm looking for classic sci-fi movies from the 80s",
  "timezone": "America/Los_Angeles",
  "mode": "casual"
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Here are some great sci-fi classics from the 1980s...",
  "recommendations": [
    {
      "id": 789012,
      "tmdb_id": 789012,
      "title": "Classic Sci-Fi Movie",
      "overview": "Movie description...",
      "release_date": "1982-06-11",
      "poster_url": "https://image.tmdb.org/t/p/original/poster_path.jpg",
      "rating": 8.9,
      "theaters": []
    }
  ]
}
```

**Alternative Response** (when processing):
```json
{
  "status": "processing",
  "message": "Your movie recommendations are being processed. Please wait a moment.",
  "conversation_id": 123
}
```

#### Poll Movie Recommendations Endpoint

```
GET /poll-movie-recommendations/
```

**Purpose**: Poll for Casual Viewing mode movie recommendations that are being processed

**Response**:
```json
{
  "status": "success",
  "message": "Here are some great sci-fi classics from the 1980s...",
  "recommendations": [
    {
      "id": 789012,
      "tmdb_id": 789012,
      "title": "Classic Sci-Fi Movie",
      "overview": "Movie description...",
      "release_date": "1982-06-11",
      "poster_url": "https://image.tmdb.org/t/p/original/poster_path.jpg",
      "rating": 8.9,
      "theaters": []
    }
  ]
}
```

**Alternative Response** (when still processing):
```json
{
  "status": "processing",
  "message": "Your movie recommendations are still being processed. Please wait a moment.",
  "conversation_id": 123
}
```

#### Get Theaters Endpoint

```
GET /get-theaters/{movie_id}/
```

**Purpose**: Fetch theaters and showtimes for a specific movie

**Response**:
```json
{
  "status": "success",
  "movie_id": 123456,
  "movie_title": "Action Movie Title",
  "theaters": [
    {
      "name": "AMC Theater",
      "address": "123 Main St, Seattle, WA",
      "distance_miles": 2.5,
      "showtimes": [
        {
          "start_time": "2025-04-23T19:30:00-07:00",
          "format": "Standard"
        }
      ]
    }
  ]
}
```

**Alternative Response** (when processing):
```json
{
  "status": "processing",
  "message": "Processing theater data for Action Movie Title. Please check back in a moment."
}
```

#### Theater Status Endpoint

```
GET /theater-status/{movie_id}/
```

**Purpose**: Poll for theater data status (used when initial request returns "processing")

**Response**: Same as Get Theaters endpoint

#### Reset Conversations Endpoint

```
GET /reset/
```

**Purpose**: Reset all conversation history

**Response**: Redirects to index page

#### API Configuration Endpoint

```
GET /api-config/
```

**Purpose**: Get API configuration settings for the frontend

**Response**:
```json
{
  "api_timeout_seconds": 30,
  "api_max_retries": 5,
  "api_retry_backoff_factor": 1.5
}
```

### Frontend API Service

The React frontend uses a centralized API service with axios to interact with these endpoints:

```javascript
// api.js
import axios from 'axios';

// Create an axios instance with CSRF token handling
const api = axios.create({
  headers: {
    'Content-Type': 'application/json',
  },
  // Add timeout to prevent hanging requests
  timeout: 30000, // 30 seconds
});

// Add CSRF token to requests
api.interceptors.request.use(config => {
  const csrfToken = getCookie('csrftoken');
  if (csrfToken) {
    config.headers['X-CSRFToken'] = csrfToken;
  }
  return config;
});

// Simple in-memory cache
const cache = {
  theaters: new Map(),
  // Cache expiration time (5 minutes)
  expirationTime: 5 * 60 * 1000,

  // Get item from cache
  get(key) {
    const item = this.theaters.get(key);
    if (!item) return null;

    // Check if item is expired
    if (Date.now() > item.expiry) {
      this.theaters.delete(key);
      return null;
    }

    return item.value;
  },

  // Set item in cache
  set(key, value) {
    const expiry = Date.now() + this.expirationTime;
    this.theaters.set(key, { value, expiry });
  },

  // Clear entire cache
  clear() {
    this.theaters.clear();
  }
};

// API service functions
export const chatApi = {
  getMoviesTheatersAndShowtimes: async (message, location = '') => {
    try {
      console.log(`[First Run Mode] Getting movies, theaters, and showtimes for: "${message}" (Location: ${location})`);

      const response = await api.post('/get-movies-theaters-and-showtimes/', {
        message: message,
        location,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        mode: 'first_run' // Explicitly set mode to first_run
      });

      if (!response.data || response.data.status !== 'success') {
        throw new Error(response.data?.message || 'Failed to get movies and theaters');
      }

      // Cache theaters for each movie if they exist
      if (response.data.status === 'success' &&
          response.data.recommendations &&
          response.data.recommendations.length > 0) {
        response.data.recommendations.forEach(movie => {
          if (movie.theaters && movie.theaters.length > 0) {
            cache.set(movie.id, {
              status: 'success',
              theaters: movie.theaters
            });
          }
        });
      }

      return response.data;
    } catch (error) {
      console.error('Error getting movies and theaters:', error);
      throw error;
    }
  },

  getMovieRecommendations: async (message) => {
    try {
      console.log(`[Casual Mode] Getting movie recommendations for: "${message}"`);

      const response = await api.post('/get-movie-recommendations/', {
        message: message,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        mode: 'casual' // Explicitly set mode to casual
      });

      if (!response.data || response.data.status !== 'success') {
        throw new Error(response.data?.message || 'Failed to get movie recommendations');
      }

      return response.data;
    } catch (error) {
      console.error('Error getting movie recommendations:', error);
      throw error;
    }
  },

  // Method for polling first run recommendations
  pollFirstRunRecommendations: async () => {
    try {
      const response = await api.get('/poll-first-run-recommendations/');
      return response.data;
    } catch (error) {
      console.error('Error polling first run recommendations:', error);
      throw error;
    }
  },

  // Method for polling casual mode recommendations
  pollMovieRecommendations: async () => {
    try {
      const response = await api.get('/poll-movie-recommendations/');
      return response.data;
    } catch (error) {
      console.error('Error polling movie recommendations:', error);
      throw error;
    }
  },

  getTheaters: async (movieId) => {
    try {
      console.log(`Fetching theaters for movie ID: ${movieId}`);

      // Check cache first
      const cachedData = cache.get(movieId);
      if (cachedData) {
        console.log('Using cached theater data');
        return cachedData;
      }

      // If not in cache, make initial request to fetch or start processing
      const response = await api.get(`/get-theaters/${movieId}/`);

      // If response contains a status of "processing", start polling
      if (response.data.status === 'processing') {
        console.log('Theaters are being processed, will start polling...');
        return {
          status: 'processing',
          message: 'Fetching theaters and showtimes...'
        };
      }

      // If we got a direct success response, cache it
      if (response.data.status === 'success' && response.data.theaters) {
        cache.set(movieId, response.data);
      }

      return response.data;
    } catch (error) {
      console.error('Error fetching theaters:', error);
      throw error;
    }
  },

  // Method for polling theater status
  pollTheaterStatus: async (movieId) => {
    try {
      const response = await api.get(`/theater-status/${movieId}/`);

      // If the processing is complete, cache the results
      if (response.data.status === 'success' && response.data.theaters) {
        cache.set(movieId, response.data);
      }

      return response.data;
    } catch (error) {
      console.error('Error polling theater status:', error);
      throw error;
    }
  },

  resetConversation: async () => {
    try {
      console.log('Resetting conversation');

      // Clear cache when resetting conversation
      cache.clear();

      await api.get('/reset/');
      return { status: 'success' };
    } catch (error) {
      console.error('Error resetting conversation:', error);
      throw error;
    }
  }
};
```

### Caching and Polling Mechanism

The frontend implements:

1. **In-memory Caching**:
   - Caches theater data for 5 minutes
   - Prevents redundant API calls for the same movie
   - Automatically expires old data

2. **Polling for Recommendations**:
   - Initial requests to `/get-movies-theaters-and-showtimes/` and `/get-movie-recommendations/` return "processing" status
   - Frontend then polls `/poll-first-run-recommendations/` or `/poll-movie-recommendations/` until data is ready
   - Provides better user experience for long-running operations
   - Implements exponential backoff for polling with configurable retry limits

3. **Polling for Theater Data**:
   - Initial request to `/get-theaters/{movie_id}/` may return "processing" status
   - Frontend then polls `/theater-status/{movie_id}/` until data is ready
   - Implements timeout mechanism to prevent endless polling (30 seconds max)
   - Sets empty theaters array after timeout instead of showing an error

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
    # Extract model name and provider info
    model_name = self.model
    provider = self.llm_provider  # May be None if not specified

    # Process provider/model format if present
    if '/' in model_name:
        parts = model_name.split('/', 1)
        provider_from_name, model_without_prefix = parts

        # If explicit provider was given, it overrides the prefix in the name
        if not provider:
            provider = provider_from_name

        model_name = model_without_prefix

    # If no provider specified yet, default to openai
    if not provider:
        provider = "openai"

    # Ensure model always has provider prefix
    full_model_name = f"{provider}/{model_name}"

    # Create model mapping for LiteLLM
    litellm_mapping = {model_name: provider}

    # Set up model_kwargs with LiteLLM configuration
    model_kwargs = {
        "model_name_map": json.dumps(litellm_mapping)
    }

    # Base configuration
    config = {
        "openai_api_key": self.api_key,
        "model": full_model_name,
        "temperature": temperature,
        "model_kwargs": model_kwargs
    }

    # Add base URL if provided
    if self.base_url:
        config["openai_api_base"] = self.base_url

    # Create the model instance with proper configuration
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

### Enhanced Image Quality Selection

The application includes an image enhancement tool that selects high-quality images:

```python
class EnhanceMovieImagesTool(BaseTool):
    """Tool for enhancing movie images with high-quality URLs."""

    name: str = "enhance_movie_images_tool"
    description: str = "Enhances movie data with high-quality image URLs."
    tmdb_api_key: str = None

    def _run(self, movies_json: str) -> str:
        """
        Enhance movie data with high-quality image URLs.

        Args:
            movies_json: JSON string containing movie data

        Returns:
            JSON string with enhanced movie data
        """
        try:
            # Parse input JSON
            movies = json.loads(movies_json)

            # Configure TMDb API if key is provided
            if self.tmdb_api_key:
                tmdb.API_KEY = self.tmdb_api_key

            # Process each movie
            for movie in movies:
                # Get movie ID
                movie_id = movie.get('tmdb_id') or movie.get('id')

                if not movie_id:
                    continue

                # Get movie details including images
                movie_details = tmdb.Movies(movie_id)
                images = movie_details.images()

                # Get poster path
                poster_path = movie.get('poster_path')
                if poster_path:
                    movie['poster_url'] = f"https://image.tmdb.org/t/p/original{poster_path}"
                elif images and images.get('posters') and len(images['posters']) > 0:
                    poster_path = images['posters'][0].get('file_path')
                    if poster_path:
                        movie['poster_url'] = f"https://image.tmdb.org/t/p/original{poster_path}"

                # Get backdrop path
                backdrop_path = movie.get('backdrop_path')
                if backdrop_path:
                    movie['backdrop_url'] = f"https://image.tmdb.org/t/p/original{backdrop_path}"
                elif images and images.get('backdrops') and len(images['backdrops']) > 0:
                    backdrop_path = images['backdrops'][0].get('file_path')
                    if backdrop_path:
                        movie['backdrop_url'] = f"https://image.tmdb.org/t/p/original{backdrop_path}"

            # Return enhanced movies as JSON string
            return json.dumps(movies)
        except Exception as e:
            logger.error(f"Error enhancing movie images: {str(e)}")
            return movies_json
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

    def _parse_serp_results(self, results, movie_title):
        """Parse SerpAPI results into structured theater data."""
        theaters = []

        # Extract showtimes data
        showtimes_data = results.get('showtimes', [])

        for theater_data in showtimes_data:
            theater_info = theater_data.get('theater', {})

            # Create theater object
            theater = {
                "name": theater_info.get('name', 'Unknown Theater'),
                "address": theater_info.get('address', ''),
                "movie_title": movie_title,
                "showtimes": []
            }

            # Process showtimes
            raw_showtimes = theater_data.get('showtimes', [])
            for showtime in raw_showtimes:
                datetime_str = showtime.get('datetime')
                format_str = showtime.get('theatre_format', 'Standard')

                if datetime_str:
                    theater['showtimes'].append({
                        "datetime": datetime_str,
                        "format": format_str
                    })

            # Only add theaters with showtimes
            if theater['showtimes']:
                theaters.append(theater)

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
    "q": "Dune theater",
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
// useLocation.js custom hook
import { useState, useEffect } from 'react';

export function useLocation() {
  const [location, setLocation] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState(null);

  // Function to detect location using browser geolocation
  const detectLocation = () => {
    setIsLoading(true);
    setError(null);

    if (navigator.geolocation) {
      navigator.geolocation.getCurrentPosition(
        // Success callback
        async (position) => {
          try {
            const { latitude, longitude } = position.coords;

            // Use reverse geocoding to get readable location
            const response = await fetch(
              `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`
            );

            if (!response.ok) {
              throw new Error('Geocoding failed');
            }

            const data = await response.json();
            const locationName = data.display_name;

            setLocation(locationName);
            setIsLoading(false);
          } catch (err) {
            setError('Failed to convert coordinates to address');
            setIsLoading(false);
          }
        },
        // Error callback
        (error) => {
          console.error('Geolocation error:', error);
          setError('Unable to get your location. Please enter it manually.');
          setIsLoading(false);

          // Fall back to IP-based geolocation
          gatherLocationDataFromIpApi();
        }
      );
    } else {
      setError('Geolocation is not supported by your browser');
      setIsLoading(false);

      // Fall back to IP-based geolocation
      gatherLocationDataFromIpApi();
    }
  };

  // Function to gather location data from ipapi.co
  const gatherLocationDataFromIpApi = async () => {
    try {
      const response = await fetch('https://ipapi.co/json/');

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();

      // Check if location is in the US
      if (data.country_code !== 'US') {
        setError('Theater search requires a US location. Please enter a US city and state.');
        return;
      }

      // Extract city and state for US locations
      const { city, region, country_name } = data;

      // Format location
      if (city && region && country_name) {
        const locationName = `${city}, ${region}, ${country_name}`;
        setLocation(locationName);
      }
    } catch (err) {
      console.error('Error fetching location from IP:', err);
      setError('Could not detect your location. Please enter it manually.');
    } finally {
      setIsLoading(false);
    }
  };

  return { location, setLocation, detectLocation, isLoading, error };
}
```

### ipapi.co

Used as a fallback when browser geolocation is unavailable or denied:

```javascript
// Function to gather location and timezone data from ipapi.co
async function gatherLocationDataFromIpApi() {
  try {
    // Use ipapi.co - no API key needed
    const response = await fetch('https://ipapi.co/json/');

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const data = await response.json();

    // Check if location is in the US
    if (data.country_code !== 'US') {
      console.log(`Detected non-US location: ${data.country_name || 'unknown'}`);
      return null;
    }

    // Capture timezone information
    const timezone = data.timezone;

    // Extract city and state for US locations
    const city = data.city;
    const state = data.region;
    const country = data.country_name;

    // If we have all values, use the standard "City, State, Country" format
    if (city && state && country) {
      return {
        locationName: `${city}, ${state}, ${country}`,
        timezone
      };
    }

    return null;
  } catch (error) {
    console.error('Error fetching location from IP:', error);
    return null;
  }
}
```

#### Example Response

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

The application uses OpenStreetMap services for geocoding and finding theaters:

#### Nominatim API

Used for reverse geocoding (converting coordinates to addresses):

```javascript
// Reverse geocoding with Nominatim
async function reverseGeocode(latitude, longitude) {
  try {
    const response = await fetch(
      `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}`
    );

    if (!response.ok) {
      throw new Error('Geocoding failed');
    }

    const data = await response.json();
    return data.display_name;
  } catch (err) {
    console.error('Reverse geocoding error:', err);
    return null;
  }
}
```

#### Overpass API

Used to find movie theaters near a location:

```python
def search_theaters(latitude: float, longitude: float, radius_miles: float = 20):
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

    # Execute query
    response = requests.post("https://overpass-api.de/api/interpreter", data=overpass_query)
    data = response.json()

    # Process results
    theaters = []
    for element in data.get('elements', []):
        if element.get('tags'):
            theater = {
                "name": element['tags'].get('name', 'Unknown Theater'),
                "address": _format_address(element['tags']),
                "latitude": element.get('lat') or element.get('center', {}).get('lat'),
                "longitude": element.get('lon') or element.get('center', {}).get('lon'),
                "distance_miles": _calculate_distance(
                    latitude, longitude,
                    element.get('lat') or element.get('center', {}).get('lat'),
                    element.get('lon') or element.get('center', {}).get('lon')
                )
            }
            theaters.append(theater)

    return theaters
```

#### Distance Calculation

The application calculates distances between user location and theaters:

```python
def _calculate_distance(lat1, lon1, lat2, lon2):
    """Calculate distance between two points in miles using Haversine formula."""
    # Convert latitude and longitude from degrees to radians
    lat1_rad = math.radians(lat1)
    lon1_rad = math.radians(lon1)
    lat2_rad = math.radians(lat2)
    lon2_rad = math.radians(lon2)

    # Haversine formula
    dlon = lon2_rad - lon1_rad
    dlat = lat2_rad - lat1_rad
    a = math.sin(dlat/2)**2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(dlon/2)**2
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1-a))

    # Radius of Earth in miles
    radius = 3959

    # Calculate distance
    distance = radius * c

    return round(distance, 1)
```

#### Example Response (Overpass API)

```json
{
  "version": 0.6,
  "generator": "Overpass API",
  "osm3s": {
    "timestamp_osm_base": "2025-04-17T00:00:00Z",
    "copyright": "The data included in this document is from www.openstreetmap.org. The data is made available under ODbL."
  },
  "elements": [
    {
      "type": "node",
      "id": 123456789,
      "lat": 47.6101,
      "lon": -122.3420,
      "tags": {
        "amenity": "cinema",
        "name": "AMC Pacific Place 11",
        "addr:housenumber": "600",
        "addr:street": "Pine Street",
        "addr:city": "Seattle",
        "addr:state": "WA",
        "addr:postcode": "98101",
        "website": "https://www.amctheatres.com/movie-theatres/seattle-tacoma/amc-pacific-place-11",
        "phone": "+1-206-652-2404",
        "opening_hours": "10:00-23:00"
      }
    }
  ]
}
```
