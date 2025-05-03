# API Documentation

This document provides comprehensive information about the News Aggregator application's API endpoints, request/response formats, and integration details.

## API Overview

The News Aggregator application exposes a RESTful API that allows clients to search for news articles on specific topics and receive AI-generated summaries. The API is built using Express.js and is designed to be simple and intuitive.

## Base URL

When deployed to Cloud Foundry:
```
https://your-app-name.cfapps.io/api
```

For local development:
```
http://localhost:3001/api
```

## Authentication

The API does not require authentication from clients. However, the backend uses API keys to authenticate with external services:

1. **News API**: Requires an API key for fetching news articles
2. **LLM Service**: Requires authentication for generating summaries

These keys are configured through environment variables or service bindings and are not exposed to clients.

## Rate Limiting

The API implements rate limiting to prevent abuse:

```javascript
const rateLimit = require('express-rate-limit');

// Rate limiting middleware
const apiLimiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
  standardHeaders: true, // Return rate limit info in the `RateLimit-*` headers
  legacyHeaders: false, // Disable the `X-RateLimit-*` headers
});

// Apply rate limiting to API routes
app.use('/api', apiLimiter);
```

## Endpoints

### GET /api/news

Searches for news articles on a specific topic and returns them with AI-generated summaries.

#### Request Parameters

| Parameter | Type   | Required | Description                                |
|-----------|--------|----------|--------------------------------------------|
| topic     | string | Yes      | The topic to search for news articles about |

#### Example Request

```http
GET /api/news?topic=climate+change HTTP/1.1
Host: localhost:3001
```

#### Response Format

The response is a JSON object containing an array of article objects:

```json
{
  "articles": [
    {
      "title": "Article Title",
      "url": "https://example.com/article",
      "source": "News Source Name",
      "publishedAt": "2025-04-29T12:00:00Z",
      "summary": "AI-generated 25-word summary of the article content.",
      "imageUrl": "https://example.com/image.jpg"
    },
    // More articles...
  ]
}
```

#### Response Fields

| Field       | Type   | Description                                          |
|-------------|--------|------------------------------------------------------|
| title       | string | The title of the news article                        |
| url         | string | URL to the original article                          |
| source      | string | Name of the news source (e.g., "CNN", "BBC")         |
| publishedAt | string | ISO 8601 date string when the article was published  |
| summary     | string | AI-generated 25-word summary of the article          |
| imageUrl    | string | URL to the article's featured image (may be null)    |

#### Status Codes

| Status Code | Description                                                  |
|-------------|--------------------------------------------------------------|
| 200         | Success - Returns articles with summaries                    |
| 400         | Bad Request - Missing required parameters                    |
| 500         | Server Error - Error fetching news or generating summaries   |

#### Error Response

```json
{
  "error": "Error message",
  "message": "Additional error details (optional)"
}
```

## Implementation Details

### Backend Processing Flow

1. **Request Validation**: Checks if the topic parameter is provided
2. **News API Request**: Fetches articles from News API based on the topic
3. **Parallel Processing**: Processes articles in parallel for efficiency
4. **LLM Summarization**: Sends each article to the LLM for summarization
5. **Response Formatting**: Formats the data and returns the response

```javascript
// From server.js
app.get('/api/news', async (req, res) => {
  const { topic } = req.query;

  if (!topic) {
    return res.status(400).json({ error: 'Topic is required' });
  }

  try {
    console.log(`Searching for news about: ${topic}`);

    // Fetch news from News API
    const response = await axios.get(
      `https://newsapi.org/v2/everything?q=${encodeURIComponent(topic)}&sortBy=publishedAt&pageSize=10`,
      {
        headers: {
          'X-Api-Key': process.env.NEWS_API_KEY
        }
      }
    );

    const articles = response.data.articles || [];

    // Process the articles with LLM to create summaries
    const llm = createLLMClient();

    const articlesWithSummaries = await Promise.all(
      articles.map(async (article) => {
        // Create a 25-word summary using LLM
        try {
          const result = await llm.invoke([
            new SystemMessage(
              "You are a helpful assistant that summarizes news articles. Create a concise summary in exactly 25 words."
            ),
            new HumanMessage(
              `Summarize this article in exactly 25 words: ${article.title}. ${article.description || ''}`
            )
          ]);

          return {
            title: article.title,
            url: article.url,
            source: article.source.name,
            publishedAt: article.publishedAt,
            summary: result.content.trim(),
            imageUrl: article.urlToImage
          };
        } catch (err) {
          console.error('Error generating summary for article:', err);
          return {
            title: article.title,
            url: article.url,
            source: article.source.name,
            publishedAt: article.publishedAt,
            summary: article.description || 'No summary available.',
            imageUrl: article.urlToImage
          };
        }
      })
    );

    res.json({ articles: articlesWithSummaries });
  } catch (error) {
    console.error('Error fetching news:', error);
    res.status(500).json({ error: 'Failed to fetch news articles' });
  }
});
```

### Frontend Integration

The frontend uses the `newsService.js` module to communicate with the API:

```javascript
// From src/services/newsService.js
import axios from 'axios';

/**
 * Determine if we're running in development mode by checking
 * if we're accessing the app from localhost on the React dev server port
 */
function isLocalDevelopment() {
  return window.location.hostname === 'localhost' &&
         (window.location.port === '3000' || window.location.port === '3002');
}

// In development, use the full URL with the API port
// In production, use relative URLs (empty base path)
const API_BASE_URL = isLocalDevelopment()
  ? (process.env.REACT_APP_API_BASE_URL || 'http://localhost:3001')
  : '';

/**
 * Search for news articles on a specific topic
 * @param {string} topic - The topic to search for
 * @returns {Promise<{articles: Array}>} - Promise with array of article objects
 */
export const searchNews = async (topic) => {
  try {
    // In development: full URL with port (http://localhost:3001/api/news)
    // In production: relative URL (/api/news)
    const response = await axios.get(`${API_BASE_URL}/api/news`, {
      params: { topic }
    });

    return response.data;
  } catch (error) {
    console.error('Error in searchNews service:', error);
    throw error;
  }
};
```

## Error Handling

The API implements several error handling strategies:

1. **Request Validation**: Checks for required parameters
2. **Try-Catch Blocks**: Wraps external API calls in try-catch blocks
3. **Fallback Content**: Uses article description if LLM summarization fails
4. **Detailed Error Logging**: Logs errors with context for debugging
5. **User-Friendly Error Messages**: Returns appropriate error messages to clients

## Future API Enhancements

Potential future enhancements to the API include:

1. **Pagination**: Support for paginating through large result sets
2. **Filtering**: Additional parameters for filtering articles by date, source, etc.
3. **Caching**: Implementing Redis caching for frequent searches
4. **Authentication**: Optional API key authentication for higher rate limits
5. **Webhooks**: Notification system for new articles on subscribed topics
