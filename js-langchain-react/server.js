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
const net = require('net');

const app = express();
const DEFAULT_PORT = process.env.PORT || 3001;

// Function to check if a port is in use
function isPortInUse(port) {
  return new Promise((resolve) => {
    const server = net.createServer()
      .once('error', () => {
        // Port is in use
        resolve(true);
      })
      .once('listening', () => {
        // Port is free
        server.close();
        resolve(false);
      })
      .listen(port);
  });
}

// Function to find an available port
async function findAvailablePort(startPort) {
  let port = startPort;
  while (await isPortInUse(port)) {
    port++;
    if (port > startPort + 100) {
      // Avoid infinite loop, limit to 100 ports
      throw new Error('Could not find an available port');
    }
  }
  return port;
}

// Middleware
app.use(cors());
app.use(express.json());
app.use(express.static(path.join(__dirname, 'build')));


/**
 * Get LLM credentials from Cloud Foundry VCAP_SERVICES or environment variables
 *
 * Environment Variable Fallbacks:
 * The service will check for credentials in this order:
 * 1. Cloud Foundry VCAP_SERVICES (GenAI service binding)
 * 2. Environment variables (multiple options for compatibility):
 *    - API Key: API_KEY, LLM_API_KEY, GENAI_API_KEY
 *    - API Base URL: API_BASE_URL, LLM_API_BASE, GENAI_API_BASE_URL
 *    - Model: MODEL_NAME, LLM_MODEL, GENAI_MODEL
 * 3. Default values where appropriate
 */
function getLLMConfig() {
  // Check if running in Cloud Foundry with bound services
  if (process.env.VCAP_SERVICES) {
    try {
      const vcapServices = JSON.parse(process.env.VCAP_SERVICES);

      // Iterate through all services to find GenAI services
      for (const [serviceName, instances] of Object.entries(vcapServices)) {
        for (const instance of instances) {
          // Check for genai tag
          const hasGenAITag = instance.tags &&
            instance.tags.some(tag => tag.toLowerCase().includes('genai'));

          // Check for genai label
          const hasGenAILabel = instance.label &&
            instance.label.toLowerCase().includes('genai');

          // Check service name
          const hasGenAIName = serviceName.toLowerCase().includes('genai') ||
            serviceName.toLowerCase().includes('llm');

          if (hasGenAITag || hasGenAILabel || hasGenAIName) {
            // Found a potential GenAI service, check for chat capability
            const credentials = instance.credentials;

            if (!credentials) continue;

            // Check for model_capabilities
            const hasChatCapability = credentials.model_capabilities &&
              credentials.model_capabilities.some(cap => cap.toLowerCase() === 'chat');

            // If no capabilities specified or has chat capability
            if (!credentials.model_capabilities || hasChatCapability) {
              // Extract credentials with proper field mapping
              const config = {
                apiKey: credentials.api_key || credentials.apiKey,
                baseUrl: credentials.api_base || credentials.url || credentials.baseUrl || credentials.base_url,
                modelName: credentials.model_name || credentials.model || 'gpt-4o-mini'
              };

              // If model_provider is available, prefix the model name
              if (credentials.model_provider && config.modelName) {
                config.modelName = `${credentials.model_provider}/${config.modelName}`;
              }

              console.log('Using LLM configuration from VCAP_SERVICES');
              return config;
            }
          }
        }
      }
    } catch (error) {
      console.error('Error parsing VCAP_SERVICES:', error);
    }
  }

  // Fallback to environment variables for local development
  return {
    apiKey: process.env.API_KEY || process.env.LLM_API_KEY || process.env.GENAI_API_KEY,
    baseUrl: process.env.API_BASE_URL || process.env.LLM_API_BASE || process.env.GENAI_API_BASE_URL,
    modelName: process.env.MODEL_NAME || process.env.LLM_MODEL || process.env.GENAI_MODEL || 'gpt-4o-mini'
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

// Start the server with port detection
(async () => {
  try {
    // Check if default port is available
    const port = await findAvailablePort(DEFAULT_PORT);

    // If we're using a different port than the default, update the environment variable
    if (port !== DEFAULT_PORT) {
      console.log(`Port ${DEFAULT_PORT} is in use, using port ${port} instead`);
      process.env.PORT = port;
    }

    app.listen(port, () => {
      console.log(`Server running on port ${port}`);
      console.log(`API available at http://localhost:${port}/api/news`);

      // If we're using a different port, provide instructions for the frontend
      if (port !== DEFAULT_PORT) {
        console.log('\nIMPORTANT: The server is running on a different port than expected.');
        console.log('You may need to update your frontend configuration:');
        console.log(`1. Set REACT_APP_API_BASE_URL=http://localhost:${port} in your .env file`);
        console.log('2. Restart the React development server');
      }
    });
  } catch (error) {
    console.error('Failed to start server:', error);
    process.exit(1);
  }
})();
