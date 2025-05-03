# Cloud Foundry Deployment Guide

This guide provides detailed instructions for deploying the Movie Chatbot application to Cloud Foundry, with a focus on configuration options and service bindings.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Configuration Options](#configuration-options)
- [Deployment Scenarios](#deployment-scenarios)
  - [Scenario 1: With GenAI Tile Service Binding](#scenario-1-with-genai-tile-service-binding)
  - [Scenario 2: With User-Defined Services](#scenario-2-with-user-defined-services)
  - [Scenario 3: With Environment Variables](#scenario-3-with-environment-variables)
  - [Scenario 4: With config.json](#scenario-4-with-configjson)
- [Required vs. Optional Configuration](#required-vs-optional-configuration)
- [Service Binding Details](#service-binding-details)
- [Troubleshooting](#troubleshooting)

## Prerequisites

Before deploying the application, ensure you have:

- Cloud Foundry CLI installed (`cf` command available)
- Access to a Tanzu Platform for Cloud Foundry environment
- Proper permissions to create and bind services
- The application code from the repository

## Configuration Options

The application supports multiple configuration sources, with the following priority order:

1. **Service Bindings** (highest priority)
   - GenAI tile service bindings for LLM configuration
   - User-defined services for other required/optional configuration

2. **Environment Variables** (second priority)
   - Set via `cf set-env` or in manifest.yml
   - Used when service bindings are not available

3. **config.json** (third priority)
   - Used as a fallback when neither service bindings nor environment variables are available
   - Particularly useful for local development or simplified deployment

## Deployment Scenarios

### Scenario 1: With GenAI Tile Service Binding

This is the recommended approach for production deployments when the GenAI tile is available.

1. Deploy the application:

   ```bash
   # Clone the repository if you haven't already
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/py-django-crewai

   # Deploy to Cloud Foundry
   ./deploy-on-tp4cf.sh
   ```

2. Create and bind a GenAI service instance:

   ```bash
   # Discover available GenAI tile service offering plans
   cf marketplace -e genai

   # Create a GenAI service instance
   cf create-service genai PLAN_NAME movie-chatbot-llm

   # Bind the application to the service
   cf bind-service movie-chatbot movie-chatbot-llm
   ```

3. Set required non-LLM configuration:

   ```bash
   cf set-env movie-chatbot DJANGO_SECRET_KEY "your_secret_key"
   cf set-env movie-chatbot TMDB_API_KEY "your_tmdb_api_key"
   ```

4. Start the application:

   ```bash
   cf start movie-chatbot
   ```

### Scenario 2: With User-Defined Services

This approach is useful when the GenAI tile is not available, but you still want to use service bindings for configuration.

1. Deploy the application:

   ```bash
   ./deploy-on-tp4cf.sh
   ```

2. Create a user-defined service for LLM configuration:

   ```bash
   cf create-user-provided-service movie-chatbot-llm -p '{
     "api_key": "your_llm_api_key",
     "api_base": "your_llm_base_url",
     "model_name": "your_model_name",
     "model_provider": "openai"
   }'
   ```

3. Create a user-defined service for other required configuration:

   ```bash
   cf create-user-provided-service movie-chatbot-config -p '{
     "DJANGO_SECRET_KEY": "your_secret_key",
     "TMDB_API_KEY": "your_tmdb_api_key",
     "SERPAPI_API_KEY": "your_serpapi_key"
   }'
   ```

4. Bind the services to the application:

   ```bash
   cf bind-service movie-chatbot movie-chatbot-llm
   cf bind-service movie-chatbot movie-chatbot-config
   ```

5. Start the application:

   ```bash
   cf start movie-chatbot
   ```

### Scenario 3: With Environment Variables

This approach uses environment variables for all configuration.

1. Deploy the application:

   ```bash
   ./deploy-on-tp4cf.sh
   ```

2. Set LLM configuration:

   ```bash
   cf set-env movie-chatbot OPENAI_API_KEY "your_llm_api_key"
   cf set-env movie-chatbot LLM_BASE_URL "your_llm_base_url"
   cf set-env movie-chatbot LLM_MODEL "your_model_name"
   ```

3. Set other required configuration:

   ```bash
   cf set-env movie-chatbot DJANGO_SECRET_KEY "your_secret_key"
   cf set-env movie-chatbot TMDB_API_KEY "your_tmdb_api_key"
   ```

4. Set optional configuration (if needed):

   ```bash
   cf set-env movie-chatbot SERPAPI_API_KEY "your_serpapi_key"
   cf set-env movie-chatbot MOVIE_RESULTS_LIMIT "5"
   cf set-env movie-chatbot MAX_RECOMMENDATIONS "3"
   ```

5. Start the application:

   ```bash
   cf start movie-chatbot
   ```

### Scenario 4: With config.json

This approach uses a config.json file for configuration, which is included in the deployment.

1. Create a config.json file:

   ```json
   {
     "DJANGO_SECRET_KEY": "your_secret_key",
     "TMDB_API_KEY": "your_tmdb_api_key",
     "OPENAI_API_KEY": "your_llm_api_key",
     "LLM_BASE_URL": "your_llm_base_url",
     "LLM_MODEL": "your_model_name",
     "SERPAPI_API_KEY": "your_serpapi_key",
     "MOVIE_RESULTS_LIMIT": "5",
     "MAX_RECOMMENDATIONS": "3"
   }
   ```

2. Deploy the application with the config.json file:

   ```bash
   ./deploy-on-tp4cf.sh
   ```

3. Start the application:

   ```bash
   cf start movie-chatbot
   ```

## Required vs. Optional Configuration

### Required Configuration

These settings are essential for the application to function:

- **DJANGO_SECRET_KEY**: Required for Django security features
- **TMDB_API_KEY**: Required for movie data retrieval

### LLM Configuration (Required for AI functionality)

These settings are specifically for the LLM service:

- **LLM API Key**: Required for LLM access (via `OPENAI_API_KEY` or `LLM_API_KEY`)
- **LLM Base URL**: Optional, defaults to OpenAI's API endpoint
- **LLM Model**: Optional, defaults to 'gpt-4o-mini'

### Optional Configuration

These settings have defaults but can be overridden:

- **SERPAPI_API_KEY**: Optional, for theater showtimes (enhances functionality)
- **MOVIE_RESULTS_LIMIT**: Optional, defaults to 5
- **MAX_RECOMMENDATIONS**: Optional, defaults to 3
- **THEATER_SEARCH_RADIUS_MILES**: Optional, defaults to 15
- **DEFAULT_SEARCH_START_YEAR**: Optional, defaults to 1900
- Other API request configuration parameters (timeouts, retries, etc.)

## Service Binding Details

### GenAI Tile Service Binding

When using the GenAI tile, the application automatically extracts the following from the service binding:

- **api_key**: The API key for the LLM service
- **api_base**: The base URL for the LLM service
- **model_name**: The name of the model to use
- **model_provider**: The provider of the model (e.g., 'openai')

The application looks for services with the 'genai' tag or label, or specifically named 'movie-chatbot-llm'.

### User-Defined Services

When using user-defined services, you can create two types of services:

1. **LLM Service** (named 'movie-chatbot-llm'):
   - Contains LLM-specific configuration (api_key, api_base, model_name, model_provider)

2. **Config Service** (named 'movie-chatbot-config'):
   - Contains other required and optional configuration (DJANGO_SECRET_KEY, TMDB_API_KEY, etc.)

## Troubleshooting

### Common Issues

1. **Missing Required Configuration**:
   - Check that DJANGO_SECRET_KEY and TMDB_API_KEY are set
   - Verify that LLM API key is available (via service binding or environment variable)

2. **Service Binding Issues**:
   - Verify that services are correctly bound to the application
   - Check service binding credentials with `cf env movie-chatbot`

3. **Configuration Priority**:
   - Remember that service bindings take precedence over environment variables
   - Environment variables take precedence over config.json

### Debugging

To enable debug logging:

```bash
cf set-env movie-chatbot DEBUG True
cf set-env movie-chatbot LOG_LEVEL DEBUG
cf restage movie-chatbot
```

View logs:

```bash
cf logs movie-chatbot --recent
```
