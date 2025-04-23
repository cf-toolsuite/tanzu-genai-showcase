# Testing Guide

This document provides comprehensive testing strategies and approaches for the Movie Chatbot application, covering unit testing, integration testing, frontend testing, and end-to-end testing.

## Table of Contents

- [Testing Overview](#testing-overview)
- [Unit Testing](#unit-testing)
  - [Backend Unit Tests](#backend-unit-tests)
  - [Frontend Unit Tests](#frontend-unit-tests)
- [Integration Testing](#integration-testing)
  - [API Integration Tests](#api-integration-tests)
  - [Database Integration Tests](#database-integration-tests)
  - [CrewAI Integration Tests](#crewai-integration-tests)
- [Frontend Testing](#frontend-testing)
  - [Component Testing](#component-testing)
  - [State Management Testing](#state-management-testing)
  - [UI Interaction Testing](#ui-interaction-testing)
- [End-to-End Testing](#end-to-end-testing)
- [Performance Testing](#performance-testing)
- [Test Coverage](#test-coverage)
- [Continuous Integration](#continuous-integration)
- [API Error Handling and Testing](#api-error-handling-and-testing)

## Testing Overview

The Movie Chatbot application employs a comprehensive testing strategy that includes:

- **Unit Tests**: Testing individual components in isolation
- **Integration Tests**: Testing interactions between components
- **Frontend Tests**: Testing React components and user interactions
- **End-to-End Tests**: Testing the complete application flow
- **Performance Tests**: Testing application performance under load

## Unit Testing

### Backend Unit Tests

Backend unit tests focus on testing individual Python functions and classes in isolation.

#### Setting Up Backend Tests

```bash
# Install pytest and related packages
pip install pytest pytest-django pytest-cov

# Run tests
pytest
```

#### Example Model Test

```python
# test_models.py
import pytest
from django.test import TestCase
from chatbot.models import Conversation, Message, MovieRecommendation

class TestConversationModel(TestCase):
    def test_conversation_creation(self):
        # Create a conversation
        conversation = Conversation.objects.create(mode='first_run')

        # Verify the conversation was created
        self.assertEqual(conversation.mode, 'first_run')
        self.assertIsNotNone(conversation.created_at)

    def test_conversation_with_messages(self):
        # Create a conversation with messages
        conversation = Conversation.objects.create(mode='casual')
        Message.objects.create(
            conversation=conversation,
            sender='user',
            content='Test message'
        )

        # Verify the message was associated with the conversation
        self.assertEqual(conversation.messages.count(), 1)
        self.assertEqual(conversation.messages.first().content, 'Test message')
```

#### Example Service Test

```python
# test_services.py
import pytest
from unittest.mock import patch, MagicMock
from chatbot.services.tmdb_service import TMDbService

@patch('tmdbsimple.Search')
def test_search_movies(mock_search):
    # Configure the mock
    mock_instance = MagicMock()
    mock_search.return_value = mock_instance
    mock_instance.movie.return_value = {
        'results': [
            {
                'id': 123,
                'title': 'Test Movie',
                'overview': 'Test overview',
                'release_date': '2025-01-01'
            }
        ]
    }

    # Create the service and call the method
    service = TMDbService(api_key='test_key')
    results = service.search_movies('test query')

    # Verify the results
    assert len(results) == 1
    assert results[0]['title'] == 'Test Movie'

    # Verify the mock was called correctly
    mock_instance.movie.assert_called_once_with(
        query='test query',
        include_adult=False,
        language='en-US'
    )
```

#### Example Utility Test

```python
# test_utils.py
import pytest
from chatbot.utils.location_utils import format_location, validate_us_location

def test_format_location():
    # Test various location formats
    assert format_location('Seattle, WA') == 'Seattle, Washington, United States'
    assert format_location('New York, NY') == 'New York, New York, United States'
    assert format_location('Los Angeles, California') == 'Los Angeles, California, United States'

def test_validate_us_location():
    # Test valid US locations
    assert validate_us_location('Seattle, WA, United States') is True
    assert validate_us_location('New York, NY, USA') is True

    # Test invalid locations
    assert validate_us_location('London, UK') is False
    assert validate_us_location('') is False
```

### Frontend Unit Tests

Frontend unit tests focus on testing individual React components and utility functions.

#### Setting Up Frontend Tests

```bash
# Navigate to frontend directory
cd frontend

# Install testing libraries
npm install --save-dev jest @testing-library/react @testing-library/jest-dom @testing-library/user-event

# Run tests
npm test
```

#### Example Component Test

```javascript
// MovieCard.test.js
import React from 'react';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import MovieCard from '../components/Movies/MovieCard';

describe('MovieCard', () => {
  const mockMovie = {
    id: 123,
    title: 'Test Movie',
    overview: 'Test overview',
    release_date: '2025-01-01',
    poster_url: 'https://example.com/poster.jpg'
  };

  const mockOnSelect = jest.fn();

  test('renders movie information correctly', () => {
    render(<MovieCard movie={mockMovie} onSelect={mockOnSelect} />);

    // Check that movie information is displayed
    expect(screen.getByText('Test Movie')).toBeInTheDocument();
    expect(screen.getByText('2025')).toBeInTheDocument();
    expect(screen.getByAltText('Test Movie poster')).toHaveAttribute('src', 'https://example.com/poster.jpg');
  });

  test('calls onSelect when clicked', () => {
    render(<MovieCard movie={mockMovie} onSelect={mockOnSelect} />);

    // Click the movie card
    userEvent.click(screen.getByText('Test Movie'));

    // Check that onSelect was called with the correct movie ID
    expect(mockOnSelect).toHaveBeenCalledWith(123);
  });
});
```

#### Example Hook Test

```javascript
// useLocation.test.js
import { renderHook, act } from '@testing-library/react-hooks';
import { useLocation } from '../hooks/useLocation';

// Mock the fetch API
global.fetch = jest.fn(() =>
  Promise.resolve({
    ok: true,
    json: () => Promise.resolve({
      city: 'Seattle',
      region: 'Washington',
      country_name: 'United States',
      country_code: 'US'
    })
  })
);

// Mock the geolocation API
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

describe('useLocation', () => {
  test('initializes with empty location', () => {
    const { result } = renderHook(() => useLocation());

    expect(result.current.location).toBe('');
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });

  test('detects location using browser geolocation', async () => {
    const { result, waitForNextUpdate } = renderHook(() => useLocation());

    // Call detectLocation
    act(() => {
      result.current.detectLocation();
    });

    // Check loading state
    expect(result.current.isLoading).toBe(true);

    // Wait for async operations to complete
    await waitForNextUpdate();

    // Check final state
    expect(result.current.location).toBe('Seattle, Washington, United States');
    expect(result.current.isLoading).toBe(false);
    expect(result.current.error).toBeNull();
  });
});
```

#### Example Utility Test

```javascript
// formatUtils.test.js
import { formatDate, formatShowtime } from '../utils/formatUtils';

describe('formatDate', () => {
  test('formats date correctly', () => {
    expect(formatDate('2025-01-01')).toBe('January 1, 2025');
    expect(formatDate('2025-12-31')).toBe('December 31, 2025');
  });

  test('handles invalid dates', () => {
    expect(formatDate('')).toBe('Unknown');
    expect(formatDate(null)).toBe('Unknown');
    expect(formatDate('invalid-date')).toBe('Unknown');
  });
});

describe('formatShowtime', () => {
  test('formats showtime correctly', () => {
    expect(formatShowtime('2025-01-01T19:30:00-07:00')).toBe('7:30 PM');
    expect(formatShowtime('2025-01-01T14:00:00-07:00')).toBe('2:00 PM');
  });

  test('handles invalid showtimes', () => {
    expect(formatShowtime('')).toBe('');
    expect(formatShowtime(null)).toBe('');
    expect(formatShowtime('invalid-time')).toBe('');
  });
});
```

## Integration Testing

Integration tests verify that different parts of the application work together correctly.

### API Integration Tests

API integration tests verify that the API endpoints work correctly.

```python
# test_api.py
import json
from django.test import TestCase, Client
from django.urls import reverse
from chatbot.models import Conversation, Message

class TestChatAPI(TestCase):
    def setUp(self):
        self.client = Client()

    def test_first_run_endpoint(self):
        # Prepare test data
        data = {
            'message': 'I want to see an action movie',
            'location': 'Seattle, WA, USA'
        }

        # Make request to the endpoint
        response = self.client.post(
            reverse('first_run_message'),
            data=json.dumps(data),
            content_type='application/json'
        )

        # Verify response
        self.assertEqual(response.status_code, 200)
        response_data = json.loads(response.content)
        self.assertEqual(response_data['status'], 'success')

        # Verify database changes
        self.assertEqual(Conversation.objects.count(), 1)
        conversation = Conversation.objects.first()
        self.assertEqual(conversation.mode, 'first_run')
        self.assertEqual(conversation.messages.count(), 2)  # User message + bot response

    def test_casual_endpoint(self):
        # Prepare test data
        data = {
            'message': 'I want to see a classic sci-fi movie'
        }

        # Make request to the endpoint
        response = self.client.post(
            reverse('casual_message'),
            data=json.dumps(data),
            content_type='application/json'
        )

        # Verify response
        self.assertEqual(response.status_code, 200)
        response_data = json.loads(response.content)
        self.assertEqual(response_data['status'], 'success')

        # Verify database changes
        self.assertEqual(Conversation.objects.count(), 1)
        conversation = Conversation.objects.first()
        self.assertEqual(conversation.mode, 'casual')
        self.assertEqual(conversation.messages.count(), 2)  # User message + bot response
```

### Database Integration Tests

Database integration tests verify that the application interacts correctly with the database.

```python
# test_database.py
from django.test import TestCase
from django.utils import timezone
from chatbot.models import Conversation, Message, MovieRecommendation, Theater, Showtime

class TestDatabaseIntegration(TestCase):
    def test_conversation_with_recommendations(self):
        # Create a conversation
        conversation = Conversation.objects.create(mode='first_run')

        # Add messages
        Message.objects.create(
            conversation=conversation,
            sender='user',
            content='I want to see an action movie'
        )
        Message.objects.create(
            conversation=conversation,
            sender='bot',
            content='Here are some action movies playing now'
        )

        # Add movie recommendations
        movie = MovieRecommendation.objects.create(
            conversation=conversation,
            title='Test Movie',
            overview='Test overview',
            release_date=timezone.now().date(),
            tmdb_id=123456,
            rating=8.5
        )

        # Add theaters and showtimes
        theater = Theater.objects.create(
            name='Test Theater',
            address='123 Test St, Seattle, WA',
            latitude=47.6062,
            longitude=-122.3321
        )

        Showtime.objects.create(
            movie=movie,
            theater=theater,
            start_time=timezone.now() + timezone.timedelta(days=1),
            format='Standard'
        )

        # Verify relationships
        self.assertEqual(conversation.messages.count(), 2)
        self.assertEqual(conversation.recommendations.count(), 1)
        self.assertEqual(movie.showtimes.count(), 1)
        self.assertEqual(theater.showtimes.count(), 1)

        # Verify cascade deletion
        conversation.delete()
        self.assertEqual(Message.objects.count(), 0)
        self.assertEqual(MovieRecommendation.objects.count(), 0)
        self.assertEqual(Showtime.objects.count(), 0)
        self.assertEqual(Theater.objects.count(), 1)  # Theaters are not deleted with conversations
```

### CrewAI Integration Tests

CrewAI integration tests verify that the CrewAI framework works correctly with the application.

```python
# test_crewai.py
import pytest
from unittest.mock import patch, MagicMock
from chatbot.services.movie_crew import MovieCrewManager

@pytest.fixture
def mock_llm():
    with patch('langchain_openai.ChatOpenAI') as mock:
        mock_instance = MagicMock()
        mock.return_value = mock_instance
        yield mock

@pytest.fixture
def mock_tmdb():
    with patch('tmdbsimple.Search') as mock:
        mock_instance = MagicMock()
        mock.return_value = mock_instance
        mock_instance.movie.return_value = {
            'results': [
                {
                    'id': 123,
                    'title': 'Test Movie',
                    'overview': 'Test overview',
                    'release_date': '2025-01-01'
                }
            ]
        }
        yield mock

@pytest.fixture
def mock_crew():
    with patch('crewai.Crew') as mock:
        mock_instance = MagicMock()
        mock.return_value = mock_instance
        mock_instance.kickoff.return_value = "Test crew result"
        yield mock

def test_movie_crew_manager(mock_llm, mock_tmdb, mock_crew):
    # Create the manager
    manager = MovieCrewManager(
        api_key='test_key',
        tmdb_api_key='test_tmdb_key',
        user_location='Seattle, WA, USA'
    )

    # Process a query
    result = manager.process_query(
        query='I want to see an action movie',
        conversation_history=[],
        first_run_mode=True
    )

    # Verify the result
    assert 'response' in result
    assert 'movies' in result

    # Verify the mocks were called
    mock_llm.assert_called_once()
    mock_crew.assert_called_once()
    mock_crew.return_value.kickoff.assert_called_once()
```

## Frontend Testing

Frontend testing focuses on testing React components, state management, and user interactions.

### Component Testing

Component tests verify that React components render correctly and respond to user interactions.

```javascript
// ChatInterface.test.js
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AppProvider } from '../context/AppContext';
import ChatInterface from '../components/Chat/ChatInterface';

// Mock the API service
jest.mock('../services/api', () => ({
  chatApi: {
    getMoviesTheatersAndShowtimes: jest.fn().mockResolvedValue({
      status: 'success',
      message: 'Here are some movies for you',
      recommendations: [
        {
          id: 123,
          title: 'Test Movie',
          overview: 'Test overview',
          release_date: '2025-01-01'
        }
      ]
    })
  }
}));

describe('ChatInterface', () => {
  test('renders chat interface correctly', () => {
    render(
      <AppProvider>
        <ChatInterface />
      </AppProvider>
    );

    // Check that the chat interface is rendered
    expect(screen.getByPlaceholderText('Type your message...')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /send/i })).toBeInTheDocument();
  });

  test('sends message and displays response', async () => {
    render(
      <AppProvider>
        <ChatInterface />
      </AppProvider>
    );

    // Type a message
    const input = screen.getByPlaceholderText('Type your message...');
    userEvent.type(input, 'I want to see an action movie');

    // Click the send button
    const sendButton = screen.getByRole('button', { name: /send/i });
    userEvent.click(sendButton);

    // Wait for the response
    await waitFor(() => {
      expect(screen.getByText('Here are some movies for you')).toBeInTheDocument();
    });
  });
});
```

### State Management Testing

State management tests verify that the React Context API works correctly.

```javascript
// AppContext.test.js
import React from 'react';
import { render, screen, act } from '@testing-library/react';
import { AppProvider, useAppContext } from '../context/AppContext';

// Test component that uses the context
const TestComponent = () => {
  const { activeTab, switchTab, firstRunMovies, setFirstRunMovies } = useAppContext();

  return (
    <div>
      <div data-testid="active-tab">{activeTab}</div>
      <button onClick={() => switchTab('casual')}>Switch to Casual</button>
      <div data-testid="movie-count">{firstRunMovies.length}</div>
      <button onClick={() => setFirstRunMovies([{ id: 123, title: 'Test Movie' }])}>
        Add Movie
      </button>
    </div>
  );
};

describe('AppContext', () => {
  test('provides context values and updates state', () => {
    render(
      <AppProvider>
        <TestComponent />
      </AppProvider>
    );

    // Check initial state
    expect(screen.getByTestId('active-tab')).toHaveTextContent('first-run');
    expect(screen.getByTestId('movie-count')).toHaveTextContent('0');

    // Update state
    act(() => {
      screen.getByText('Switch to Casual').click();
    });
    expect(screen.getByTestId('active-tab')).toHaveTextContent('casual');

    act(() => {
      screen.getByText('Add Movie').click();
    });
    expect(screen.getByTestId('movie-count')).toHaveTextContent('1');
  });
});
```

### UI Interaction Testing

UI interaction tests verify that the user interface responds correctly to user interactions.

```javascript
// MovieSection.test.js
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import { AppProvider } from '../context/AppContext';
import MovieSection from '../components/Movies/MovieSection';

describe('MovieSection', () => {
  const mockMovies = [
    {
      id: 123,
      title: 'Test Movie 1',
      overview: 'Test overview 1',
      release_date: '2025-01-01',
      poster_url: 'https://example.com/poster1.jpg'
    },
    {
      id: 456,
      title: 'Test Movie 2',
      overview: 'Test overview 2',
      release_date: '2025-02-01',
      poster_url: 'https://example.com/poster2.jpg'
    }
  ];

  test('renders movies and handles selection', () => {
    render(
      <AppProvider>
        <MovieSection movies={mockMovies} />
      </AppProvider>
    );

    // Check that movies are rendered
    expect(screen.getByText('Test Movie 1')).toBeInTheDocument();
    expect(screen.getByText('Test Movie 2')).toBeInTheDocument();

    // Click on a movie
    fireEvent.click(screen.getByText('Test Movie 1'));

    // Check that movie details are displayed
    expect(screen.getByText('Test overview 1')).toBeInTheDocument();
  });
});
```

## End-to-End Testing

End-to-end tests verify that the entire application works correctly from the user's perspective.

```javascript
// e2e.test.js
import { test, expect } from '@playwright/test';

test('complete user journey', async ({ page }) => {
  // Navigate to the application
  await page.goto('http://localhost:8000');

  // Check that the page loaded
  await expect(page.getByText('Movie Chatbot')).toBeVisible();

  // Enter a message in First Run mode
  await page.getByPlaceholderText('Type your message...').fill('I want to see an action movie');
  await page.getByRole('button', { name: 'Send' }).click();

  // Wait for the response
  await expect(page.getByText('Here are some action movies')).toBeVisible({ timeout: 10000 });

  // Check that movies are displayed
  await expect(page.locator('.movie-card')).toHaveCount.greaterThan(0);

  // Click on a movie
  await page.locator('.movie-card').first().click();

  // Check that movie details are displayed
  await expect(page.locator('.movie-details')).toBeVisible();

  // Check that theaters are displayed (in First Run mode)
  await expect(page.locator('.theater-section')).toBeVisible();

  // Switch to Casual Viewing mode
  await page.getByText('Casual Viewing').click();

  // Enter a message in Casual Viewing mode
  await page.getByPlaceholderText('Type your message...').fill('I want to see a classic sci-fi movie');
  await page.getByRole('button', { name: 'Send' }).click();

  // Wait for the response
  await expect(page.getByText('Here are some classic sci-fi movies')).toBeVisible({ timeout: 10000 });

  // Check that movies are displayed
  await expect(page.locator('.movie-card')).toHaveCount.greaterThan(0);

  // Click on a movie
  await page.locator('.movie-card').first().click();

  // Check that movie details are displayed
  await expect(page.locator('.movie-details')).toBeVisible();

  // Check that theaters are NOT displayed (in Casual Viewing mode)
  await expect(page.locator('.theater-section')).not.toBeVisible();
});
```

## Performance Testing

Performance tests verify that the application performs well under load.

```python
# test_performance.py
import time
import pytest
from django.test import Client
from django.urls import reverse
import json

@pytest.mark.performance
def test_api_response_time():
    client = Client()

    # Prepare test data
    data = {
        'message': 'I want to see an action movie',
        'location': 'Seattle, WA, USA'
    }

    # Measure response time
    start_time = time.time()
    response = client.post(
        reverse('first_run_message'),
        data=json.dumps(data),
        content_type='application/json'
    )
    end_time = time.time()

    # Verify response time is acceptable
    response_time = end_time - start_time
    assert response_time < 5.0  # Response should be under 5 seconds

    # Verify response is successful
    assert response.status_code == 200
    response_data = json.loads(response.content)
    assert response_data['status'] == 'success'
```

## Test Coverage

Test coverage measures how much of the codebase is covered by tests.

```bash
# Backend test coverage
pytest --cov=chatbot

# Frontend test coverage
cd frontend
npm test -- --coverage
```

## Continuous Integration

The application uses GitHub Actions for continuous integration:

```yaml
# .github/workflows/test.yml
name: Test

on:
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v2

    - name: Set up Python
      uses: actions/setup-python@v2
      with:
        python-version: '3.12'

    - name: Install dependencies
      run: |
        python -m pip install --upgrade pip
        pip install -r requirements.txt
        pip install pytest pytest-django pytest-cov

    - name: Run backend tests
      run: |
        pytest --cov=chatbot

    - name: Set up Node.js
      uses: actions/setup-node@v4
      with:
        node-version: '18'

    - name: Install frontend dependencies
      run: |
        cd frontend
        npm install

    - name: Run frontend tests
      run: |
        cd frontend
        npm test -- --coverage
```

## API Error Handling and Testing

For detailed information about API error handling and testing, see [API Error Handling and Testing](API_ERROR_HANDLING_AND_TESTING.md).
