require('dotenv').config();
const express = require('express');
const rateLimit = require('express-rate-limit');
const cors = require('cors');
const path = require('path');
const { ChatOpenAI } = require('langchain/chat_models/openai');
const { HumanMessage, SystemMessage } = require('langchain/schema');
const axios = require('axios');

const app = express();

// Set up rate limiter: maximum of 100 requests per 15 minutes
const limiter = rateLimit({
  windowMs: 15 * 60 * 1000, // 15 minutes
  max: 100, // limit each IP to 100 requests per windowMs
});
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
        modelName: credentials.model || 'gpt-3.5-turbo' // default fallback
      };
    }
  }
  
  // Fallback to environment variables for local development
  return {
    apiKey: process.env.API_KEY,
    baseUrl: process.env.API_BASE_URL,
    modelName: process.env.MODEL_NAME || 'gpt-3.5-turbo'
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
  
  try {
    // Fetch news from a public API (example uses newsapi.org)
    // In production, you would use a proper news API with an API key
    const response = await axios.get(
      `https://newsapi.org/v2/everything?q=${encodeURIComponent(topic)}&sortBy=publishedAt&pageSize=10`,
      {
        headers: {
          'X-Api-Key': process.env.NEWS_API_KEY || 'demo-api-key' // Use your actual API key here
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
          const result = await llm.call([
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
app.get('*', limiter, (req, res) => {
  res.sendFile(path.join(__dirname, 'build', 'index.html'));
});

// Start the server
app.listen(PORT, () => {
  console.log(`Server running on port ${PORT}`);
});
