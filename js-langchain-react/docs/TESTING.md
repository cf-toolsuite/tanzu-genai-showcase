# Testing Guide

This document outlines the testing strategies, methodologies, and procedures for the News Aggregator application. It provides guidance for developers on how to effectively test the application to ensure reliability and quality.

## Testing Overview

The News Aggregator application employs a comprehensive testing strategy that includes:

1. **Unit Testing**: Testing individual components and functions in isolation
2. **Integration Testing**: Testing interactions between components
3. **End-to-End Testing**: Testing the complete application flow
4. **Manual Testing**: Verifying functionality through manual interaction

## Testing Tools

The application uses the following testing tools and libraries:

- **Jest**: JavaScript testing framework
- **React Testing Library**: For testing React components
- **Axios Mock Adapter**: For mocking API requests
- **MSW (Mock Service Worker)**: For mocking API endpoints

## Test Directory Structure

```
js-langchain-react/
├── src/
│   ├── __tests__/              # Test files for components and services
│   │   ├── components/         # Component tests
│   │   │   ├── NewsItem.test.js
│   │   │   ├── NewsList.test.js
│   │   │   └── NewsSearch.test.js
│   │   ├── services/           # Service tests
│   │   │   └── newsService.test.js
│   │   └── App.test.js         # Tests for the main App component
│   └── __mocks__/              # Mock files for testing
│       └── axios.js            # Axios mock
└── server.test.js              # Tests for the Express backend
```

## Unit Testing

### Frontend Component Testing

Unit tests for React components focus on:

1. **Rendering**: Ensuring components render correctly
2. **User Interactions**: Testing component behavior when users interact with them
3. **State Changes**: Verifying state updates correctly
4. **Props Handling**: Testing how components handle different props

#### Example: Testing the NewsSearch Component

```javascript
// src/__tests__/components/NewsSearch.test.js
import React from 'react';
import { render, screen, fireEvent } from '@testing-library/react';
import NewsSearch from '../../components/NewsSearch';

describe('NewsSearch Component', () => {
  test('renders search input and button', () => {
    render(<NewsSearch onSearch={() => {}} />);

    expect(screen.getByPlaceholderText(/enter a topic/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /search/i })).toBeInTheDocument();
  });

  test('calls onSearch with input value when form is submitted', () => {
    const mockOnSearch = jest.fn();
    render(<NewsSearch onSearch={mockOnSearch} />);

    const input = screen.getByPlaceholderText(/enter a topic/i);
    fireEvent.change(input, { target: { value: 'climate change' } });

    const button = screen.getByRole('button', { name: /search/i });
    fireEvent.click(button);

    expect(mockOnSearch).toHaveBeenCalledWith('climate change');
  });

  test('does not call onSearch when input is empty', () => {
    const mockOnSearch = jest.fn();
    render(<NewsSearch onSearch={mockOnSearch} />);

    const button = screen.getByRole('button', { name: /search/i });
    fireEvent.click(button);

    expect(mockOnSearch).not.toHaveBeenCalled();
  });
});
```

### Service Testing

Tests for service modules focus on:

1. **API Calls**: Verifying correct API endpoints are called
2. **Parameter Handling**: Testing how parameters are passed to APIs
3. **Response Handling**: Ensuring responses are processed correctly
4. **Error Handling**: Testing error scenarios

#### Example: Testing the newsService

```javascript
// src/__tests__/services/newsService.test.js
import axios from 'axios';
import MockAdapter from 'axios-mock-adapter';
import { searchNews } from '../../services/newsService';

describe('newsService', () => {
  let mock;

  beforeEach(() => {
    mock = new MockAdapter(axios);
    // Mock the window.location for environment detection
    Object.defineProperty(window, 'location', {
      value: {
        hostname: 'localhost',
        port: '3000'
      },
      writable: true
    });
  });

  afterEach(() => {
    mock.restore();
  });

  test('searchNews calls the correct API endpoint with topic parameter', async () => {
    const mockData = {
      articles: [
        {
          title: 'Test Article',
          url: 'https://example.com',
          source: { name: 'Test Source' },
          publishedAt: '2025-04-29T12:00:00Z',
          summary: 'Test summary',
          imageUrl: 'https://example.com/image.jpg'
        }
      ]
    };

    mock.onGet('http://localhost:3001/api/news').reply(200, mockData);

    const result = await searchNews('test topic');

    expect(mock.history.get[0].params).toEqual({ topic: 'test topic' });
    expect(result).toEqual(mockData);
  });

  test('searchNews handles API errors correctly', async () => {
    mock.onGet('http://localhost:3001/api/news').reply(500);

    await expect(searchNews('test topic')).rejects.toThrow();
  });
});
```

## Integration Testing

Integration tests focus on how components work together:

1. **Component Interactions**: Testing how components communicate
2. **Data Flow**: Verifying data flows correctly between components
3. **State Management**: Testing application-wide state changes

#### Example: Testing the App Component with NewsSearch and NewsList

```javascript
// src/__tests__/App.test.js
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import App from '../App';
import { searchNews } from '../services/newsService';

// Mock the newsService module
jest.mock('../services/newsService');

describe('App Integration', () => {
  test('displays loading state and then articles when search is performed', async () => {
    // Mock the searchNews function
    searchNews.mockResolvedValueOnce({
      articles: [
        {
          title: 'Test Article',
          url: 'https://example.com',
          source: 'Test Source',
          publishedAt: '2025-04-29T12:00:00Z',
          summary: 'Test summary',
          imageUrl: 'https://example.com/image.jpg'
        }
      ]
    });

    render(<App />);

    // Find and fill the search input
    const input = screen.getByPlaceholderText(/enter a topic/i);
    fireEvent.change(input, { target: { value: 'climate change' } });

    // Submit the search
    const button = screen.getByRole('button', { name: /search/i });
    fireEvent.click(button);

    // Check loading state
    expect(screen.getByText(/loading news articles/i)).toBeInTheDocument();

    // Wait for articles to load
    await waitFor(() => {
      expect(screen.getByText('Test Article')).toBeInTheDocument();
    });

    // Verify article details are displayed
    expect(screen.getByText('Test Source')).toBeInTheDocument();
    expect(screen.getByText('Test summary')).toBeInTheDocument();
  });

  test('displays error message when search fails', async () => {
    // Mock the searchNews function to reject
    searchNews.mockRejectedValueOnce(new Error('API error'));

    render(<App />);

    // Find and fill the search input
    const input = screen.getByPlaceholderText(/enter a topic/i);
    fireEvent.change(input, { target: { value: 'climate change' } });

    // Submit the search
    const button = screen.getByRole('button', { name: /search/i });
    fireEvent.click(button);

    // Wait for error message
    await waitFor(() => {
      expect(screen.getByText(/failed to fetch news/i)).toBeInTheDocument();
    });
  });
});
```

## Backend Testing

Tests for the Express backend focus on:

1. **API Endpoints**: Testing endpoint behavior
2. **Request Handling**: Verifying request parameters are processed correctly
3. **Response Formatting**: Ensuring responses match expected format
4. **Error Handling**: Testing error scenarios

#### Example: Testing the /api/news Endpoint

```javascript
// server.test.js
const request = require('supertest');
const express = require('express');
const axios = require('axios');
const { ChatOpenAI } = require('@langchain/openai');

// Mock external dependencies
jest.mock('axios');
jest.mock('@langchain/openai');

// Import the app creation function (assuming server.js exports the app)
const createApp = require('./server');

describe('Express Server API', () => {
  let app;

  beforeEach(() => {
    // Reset environment variables for testing
    process.env.NEWS_API_KEY = 'test-news-api-key';
    process.env.API_KEY = 'test-llm-api-key';

    // Create a fresh app instance for each test
    app = createApp();

    // Mock the ChatOpenAI class
    ChatOpenAI.mockImplementation(() => ({
      invoke: jest.fn().mockResolvedValue({ content: 'Mocked 25-word summary of the article.' })
    }));
  });

  test('GET /api/news returns articles with summaries', async () => {
    // Mock the News API response
    axios.get.mockResolvedValueOnce({
      data: {
        articles: [
          {
            title: 'Test Article',
            url: 'https://example.com',
            source: { name: 'Test Source' },
            publishedAt: '2025-04-29T12:00:00Z',
            description: 'Test description',
            urlToImage: 'https://example.com/image.jpg'
          }
        ]
      }
    });

    const response = await request(app)
      .get('/api/news')
      .query({ topic: 'test topic' });

    expect(response.status).toBe(200);
    expect(response.body).toHaveProperty('articles');
    expect(response.body.articles).toHaveLength(1);
    expect(response.body.articles[0]).toHaveProperty('title', 'Test Article');
    expect(response.body.articles[0]).toHaveProperty('summary', 'Mocked 25-word summary of the article.');

    // Verify News API was called with correct parameters
    expect(axios.get).toHaveBeenCalledWith(
      expect.stringContaining('https://newsapi.org/v2/everything'),
      expect.objectContaining({
        headers: { 'X-Api-Key': 'test-news-api-key' }
      })
    );
  });

  test('GET /api/news returns 400 when topic is missing', async () => {
    const response = await request(app).get('/api/news');

    expect(response.status).toBe(400);
    expect(response.body).toHaveProperty('error', 'Topic is required');
  });

  test('GET /api/news handles News API errors', async () => {
    // Mock the News API to throw an error
    axios.get.mockRejectedValueOnce(new Error('News API error'));

    const response = await request(app)
      .get('/api/news')
      .query({ topic: 'test topic' });

    expect(response.status).toBe(500);
    expect(response.body).toHaveProperty('error', 'Failed to fetch news articles');
  });
});
```

## End-to-End Testing

End-to-end tests verify the complete application flow:

1. **User Journeys**: Testing common user paths through the application
2. **Integration Points**: Verifying all parts of the system work together
3. **Real-world Scenarios**: Testing under conditions similar to production

For end-to-end testing, consider using tools like:

- **Cypress**: For browser-based end-to-end testing
- **Playwright**: For cross-browser testing
- **Puppeteer**: For headless browser testing

## Test Coverage

The project aims for high test coverage:

- **Unit Tests**: 80%+ coverage of components and services
- **Integration Tests**: Coverage of all major user flows
- **Backend Tests**: 80%+ coverage of API endpoints and logic

To run tests with coverage reporting:

```bash
npm test -- --coverage
```

## Mocking External Services

When testing, it's important to mock external services:

1. **News API**: Mock responses to avoid hitting rate limits
2. **LLM Service**: Mock to avoid incurring costs and for deterministic tests

### Example: Mocking the LLM Service

```javascript
// Mock for LangChain's ChatOpenAI
jest.mock('@langchain/openai', () => {
  return {
    ChatOpenAI: jest.fn().mockImplementation(() => ({
      invoke: jest.fn().mockResolvedValue({
        content: 'This is a mocked 25-word summary generated for testing purposes.'
      })
    })),
    HumanMessage: jest.fn(),
    SystemMessage: jest.fn()
  };
});
```

## Continuous Integration

The application uses GitHub Actions for continuous integration:

1. **Automated Testing**: Tests run on every push and pull request
2. **Coverage Reports**: Test coverage is reported and tracked
3. **Linting**: Code quality checks are performed

## Manual Testing Checklist

Before releasing new versions, perform these manual tests:

1. **Search Functionality**:
   - [ ] Search with various topics
   - [ ] Verify articles load correctly
   - [ ] Check summaries are generated

2. **UI/UX**:
   - [ ] Test responsive design on different screen sizes
   - [ ] Verify loading states display correctly
   - [ ] Check error messages are user-friendly

3. **Performance**:
   - [ ] Verify search response times are acceptable
   - [ ] Check memory usage during extended use

4. **Error Handling**:
   - [ ] Test behavior when News API is unavailable
   - [ ] Test behavior when LLM service is unavailable
   - [ ] Verify graceful degradation when services fail

## Testing Best Practices

1. **Write Tests First**: Consider test-driven development (TDD)
2. **Keep Tests Independent**: Each test should run in isolation
3. **Use Realistic Data**: Test with data that resembles production
4. **Test Edge Cases**: Include boundary conditions and error scenarios
5. **Maintain Tests**: Update tests when requirements change
6. **Run Tests Regularly**: Don't wait until deployment to run tests

## Troubleshooting Tests

Common testing issues and solutions:

1. **Async Testing Issues**:
   - Use `async/await` with `waitFor` for asynchronous operations
   - Ensure promises are properly resolved before assertions

2. **Component Rendering Issues**:
   - Check that components have required props
   - Verify the component tree is correctly structured

3. **Mock Issues**:
   - Ensure mocks are reset between tests
   - Verify mock implementations match expected behavior

4. **Environment Issues**:
   - Set up a consistent test environment
   - Use environment variables for configuration
