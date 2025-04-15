// Suppress the punycode deprecation warning
process.removeAllListeners('warning');
process.on('warning', (warning) => {
  if (warning.name === 'DeprecationWarning' && warning.message.includes('punycode')) {
    return;
  }
  console.warn(warning.name, warning.message);
});

require('dotenv').config();
const express = require('express');
const rateLimit = require('express-rate-limit');
const cors = require('cors');
const path = require('path');
const { ChatOpenAI } = require('@langchain/openai');
const { HumanMessage, SystemMessage } = require('@langchain/core/messages');
const axios = require('axios');

const app = express();
const PORT = process.env.PORT || 3001;

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, 'build')));


// Get LLM credentials from Cloud Foundry VCAP_SERVICES or environment variables
function getLLMConfig() {
  // Check if running in Cloud Foundry with bound services
  if (process.env.VCAP_SERVICES) {
    const vcapServices = JSON.parse(process.env.VCAP_SERVICES);

    // Look for GenAI service instance
    const genaiServices = Object.keys(vcapServices).find(
      (service) => service.includes('genai') || service.includes('llm')
    );

    if (genaiServices && vcapServices[genaiServices] && vcapServices[genaiServices][0]) {
      const credentials = vcapServices[genaiServices][0].credentials;

      return {
        apiKey: credentials.api_key || credentials.apiKey,
        baseUrl: credentials.url || credentials.baseUrl,
        modelName: credentials.model || 'gpt-4o-mini' // default fallback
      };
    }
  }

  // Fallback to environment variables for local development
  return {
    apiKey: process.env.API_KEY,
    baseUrl: process.env.API_BASE_URL,
    modelName: process.env.MODEL_NAME || 'gpt-4o-mini'
  };
}

// Initialize LLM client
function createLLMClient() {
  const config = getLLMConfig();

  return new ChatOpenAI({
    openAIApiKey: config.apiKey,
    modelName: config.modelName,
    temperature: 0,
    // If using a custom endpoint
    ...(config.baseUrl && { configuration: { baseURL: config.baseUrl } })
  });
}

// API route to search for news
app.get('/api/news', async (req, res) => {
  const { topic } = req.query;

  if (!topic) {
    return res.status(400).json({ error: 'Topic is required' });
  }

  // Check if NEWS_API_KEY is available
  if (!process.env.NEWS_API_KEY) {
    console.error('NEWS_API_KEY is missing. Please set it in your environment variables or bound services.');
    return res.status(500).json({
      error: 'News API configuration missing',
      message: 'The server is not properly configured with a News API key.'
    });
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

// Serve React app for all other routes
app.get('*', (req, res) => {
  res.sendFile(path.join(__dirname, 'build', 'index.html'));
});

// Start the server
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});
