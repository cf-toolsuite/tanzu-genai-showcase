# API Error Handling and Testing

This document covers error handling strategies and testing approaches for the external APIs used in the Movie Chatbot application.

## Table of Contents

- [Error Handling](#error-handling)
  - [API Error Handling](#api-error-handling)
  - [Frontend Error Handling](#frontend-error-handling)
  - [Retry Mechanism](#retry-mechanism)
- [Testing API Integrations](#testing-api-integrations)
  - [Mock Responses](#mock-responses)
  - [Frontend API Testing](#frontend-api-testing)
  - [Configuration for Testing](#configuration-for-testing)

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

### Frontend Error Handling

The frontend uses axios interceptors for centralized error handling:

```javascript
// Add response interceptor for error handling
api.interceptors.response.use(
  response => response,
  error => {
    // Handle network errors
    if (!error.response) {
      console.error('Network error:', error);
      return Promise.reject({
        status: 'error',
        message: 'Network error. Please check your connection and try again.'
      });
    }

    // Handle server errors
    if (error.response.status >= 500) {
      console.error('Server error:', error);
      return Promise.reject({
        status: 'error',
        message: 'Server error. Please try again later.'
      });
    }

    // Handle client errors
    console.error('Request error:', error);
    return Promise.reject({
      status: 'error',
      message: error.response.data.message || 'An error occurred. Please try again.'
    });
  }
);
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

### Frontend API Testing

```javascript
// Using Jest and React Testing Library
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { rest } from 'msw';
import { setupServer } from 'msw/node';
import ChatInterface from '../components/Chat/ChatInterface';
import { AppProvider } from '../context/AppContext';

// Mock server
const server = setupServer(
  rest.post('/get-movies-theaters-and-showtimes/', (req, res, ctx) => {
    return res(
      ctx.json({
        status: 'success',
        message: 'Test response',
        recommendations: [
          {
            id: 123,
            title: 'Test Movie',
            overview: 'Test overview',
            release_date: '2025-01-01'
          }
        ]
      })
    );
  })
);

beforeAll(() => server.listen());
afterEach(() => server.resetHandlers());
afterAll(() => server.close());

test('sends message and displays response', async () => {
  render(
    <AppProvider>
      <ChatInterface />
    </AppProvider>
  );

  // Type message
  const input = screen.getByPlaceholderText('Type your message...');
  userEvent.type(input, 'Test message');

  // Click send button
  const sendButton = screen.getByRole('button', { name: /send/i });
  userEvent.click(sendButton);

  // Wait for response
  await waitFor(() => {
    expect(screen.getByText('Test response')).toBeInTheDocument();
  });
});
```

### Configuration for Testing

For testing, use mock APIs or test API keys:

```bash
# .env.test
TMDB_API_KEY=test_key
OPENAI_API_KEY=test_key
SERPAPI_API_KEY=test_key
```

#### Test Environment Setup

```python
# conftest.py
import pytest
from unittest.mock import patch

@pytest.fixture
def mock_tmdb_api():
    with patch('tmdbsimple.Movies') as mock_movies:
        # Configure mock responses
        mock_instance = mock_movies.return_value
        mock_instance.info.return_value = {
            "id": 123,
            "title": "Test Movie",
            "overview": "Test overview",
            "release_date": "2025-01-01",
            "poster_path": "/test_poster.jpg",
            "backdrop_path": "/test_backdrop.jpg"
        }
        mock_instance.images.return_value = {
            "posters": [{"file_path": "/test_poster.jpg"}],
            "backdrops": [{"file_path": "/test_backdrop.jpg"}]
        }
        yield mock_movies

@pytest.fixture
def mock_serp_api():
    with patch('serpapi.GoogleSearch') as mock_search:
        # Configure mock responses
        mock_instance = mock_search.return_value
        mock_instance.get_dict.return_value = {
            "showtimes": [
                {
                    "theater": {
                        "name": "Test Theater",
                        "address": "123 Test St"
                    },
                    "showtimes": [
                        {
                            "datetime": "2025-04-23T19:30:00-07:00",
                            "theatre_format": "Standard"
                        }
                    ]
                }
            ]
        }
        yield mock_search
```

#### Integration Test Example

```python
def test_movie_recommendation_flow(mock_tmdb_api, mock_serp_api, client):
    # Test the full recommendation flow
    response = client.post('/get-movie-recommendations/', {
        'message': 'I want to see an action movie',
        'timezone': 'America/Los_Angeles',
        'mode': 'casual'
    })

    # Verify response
    assert response.status_code == 200
    data = response.json()
    assert data['status'] == 'success'
    assert len(data['recommendations']) > 0
    assert 'Test Movie' in [movie['title'] for movie in data['recommendations']]
```

### Mocking External APIs

For comprehensive testing, the application mocks all external API calls:

1. **LLM API Mocking**:
   ```python
   @pytest.fixture
   def mock_llm_api():
       with patch('langchain_openai.ChatOpenAI') as mock_chat:
           # Configure mock responses
           mock_instance = mock_chat.return_value
           mock_instance.invoke.return_value = {
               "content": "This is a test response from the LLM"
           }
           yield mock_chat
   ```

2. **Geolocation API Mocking**:
   ```javascript
   // Mock browser geolocation
   const mockGeolocation = {
     getCurrentPosition: jest.fn().mockImplementation(success =>
       success({
         coords: {
           latitude: 47.6062,
           longitude: -122.3321
         }
       })
     )
   };

   global.navigator.geolocation = mockGeolocation;

   // Mock fetch for ipapi.co
   global.fetch = jest.fn().mockImplementation((url) => {
     if (url.includes('ipapi.co')) {
       return Promise.resolve({
         ok: true,
         json: () => Promise.resolve({
           city: 'Seattle',
           region: 'Washington',
           country_name: 'United States',
           country_code: 'US',
           timezone: 'America/Los_Angeles'
         })
       });
     }

     // Handle other fetch calls
     return Promise.resolve({
       ok: true,
       json: () => Promise.resolve({})
     });
   });
   ```

### Testing Resilience

The application includes tests for error scenarios and recovery:

```python
def test_api_retry_mechanism():
    # Test that the retry mechanism works for transient errors
    with requests_mock.Mocker() as m:
        # Mock a 503 error followed by a successful response
        m.get('https://api.themoviedb.org/3/movie/123',
              [
                  {'status_code': 503, 'text': 'Service Unavailable'},
                  {'status_code': 200, 'json': {'id': 123, 'title': 'Test Movie'}}
              ])

        # Create service with retry
        service = TMDbService()
        result = service.get_movie_details(123)

        # Verify result
        assert result['title'] == 'Test Movie'
        assert m.call_count == 2  # Verify retry happened
```

### Performance Testing

The application includes tests for API performance:

```python
def test_api_performance():
    # Test API response time
    start_time = time.time()

    # Make API call
    service = TMDbService()
    result = service.search_movies("action")

    # Verify response time
    elapsed_time = time.time() - start_time
    assert elapsed_time < 2.0  # API call should complete in under 2 seconds
```
