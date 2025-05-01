# Movie Chatbot Configuration Guide

This document explains how the Movie Chatbot application loads and manages configuration settings, including API keys and other parameters.

## Configuration Priority

The application uses a priority system for loading configuration values:

1. **Service Bindings** (highest priority): When deployed to Cloud Foundry, the application checks for service bindings first.
2. **Environment Variables**: If a value is not found in service bindings, environment variables are checked.
3. **config.json**: If a value is not found in environment variables, the application looks for it in the `config.json` file.
4. **Default Values** (lowest priority): If a value is not found in any of the above sources, a default value is used if available.

## Required Configuration

The following configuration values are required for the application to function properly:

| Name | Description | Required | Default |
|------|-------------|----------|---------|
| `DJANGO_SECRET_KEY` | Secret key for Django | Yes | None |
| `OPENAI_API_KEY` or `LLM_API_KEY` | API key for the LLM service | Yes | None |
| `TMDB_API_KEY` | API key for The Movie Database | Yes | None |
| `SERPAPI_API_KEY` | API key for SerpAPI (for theater data) | Recommended | None |

## Configuration Sources

### Service Bindings (Cloud Foundry)

When deployed to Cloud Foundry, the application can use service bindings to provide configuration values. This is the recommended approach for production deployments.

Example of creating and binding a service:

```bash
# Create a user-provided service with configuration values
cf create-user-provided-service movie-chatbot-config -p '{"TMDB_API_KEY":"your-api-key"}'

# Bind the service to the application
cf bind-service movie-chatbot movie-chatbot-config
```

### Environment Variables

Environment variables can be set in various ways:

1. In a `.env` file for local development
2. Using `cf set-env` for Cloud Foundry deployments
3. In the `manifest.yml` file for Cloud Foundry deployments

Example `.env` file:

```
DJANGO_SECRET_KEY=your-secret-key
OPENAI_API_KEY=your-openai-api-key
TMDB_API_KEY=your-tmdb-api-key
SERPAPI_API_KEY=your-serpapi-api-key
```

Example of setting environment variables in Cloud Foundry:

```bash
cf set-env movie-chatbot TMDB_API_KEY your-tmdb-api-key
```

### config.json

The `config.json` file can be used to provide configuration values. This is useful for local development or when deploying to environments that don't support environment variables or service bindings.

Example `config.json` file:

```json
{
  "DJANGO_SECRET_KEY": "your-secret-key",
  "OPENAI_API_KEY": "your-openai-api-key",
  "TMDB_API_KEY": "your-tmdb-api-key",
  "SERPAPI_API_KEY": "your-serpapi-api-key"
}
```

## Configuration Validation

The application validates required configuration values during startup. If any required values are missing, error messages will be logged.

You can check the logs to see if all required configuration values are present:

```bash
# For local development
python manage.py runserver

# For Cloud Foundry deployments
cf logs movie-chatbot --recent
```

## Troubleshooting

If you encounter configuration-related issues, check the following:

1. **Missing API Keys**: Ensure that all required API keys are provided through one of the configuration sources.
2. **Service Binding Issues**: If using Cloud Foundry, check that the service bindings are correctly set up.
3. **Environment Variables**: Verify that environment variables are correctly set.
4. **config.json**: Check that the `config.json` file exists and contains the correct values.

### Common Error Messages

- `CRITICAL: TMDB API key is missing. Movie image enhancement will be unavailable.`: The TMDB API key is missing. Provide it through one of the configuration sources.
- `CRITICAL: LLM API key is missing. The application will not function correctly.`: The LLM API key is missing. Provide it through one of the configuration sources.
- `Error in process_query: 1 validation error for EnhanceMovieImagesTool`: This error occurs when the TMDB API key is missing or invalid.

## Adding New Configuration Values

When adding new configuration values to the application, follow these guidelines:

1. Use the `config_loader.py` functions to load the values:
   - `get_config(key, default=None)`: Get a configuration value with fallback to default
   - `get_required_config(key)`: Get a required configuration value (raises an error if not found)
   - `get_int_config(key, default=None)`: Get a configuration value as an integer
   - `get_float_config(key, default=None)`: Get a configuration value as a float
   - `get_bool_config(key, default=None)`: Get a configuration value as a boolean

2. Add the new configuration value to the validation function in `validation.py` if it's required.

3. Document the new configuration value in this guide.

Example of adding a new configuration value:

```python
# In a settings file
from . import config_loader

# Get a configuration value with a default
MY_CONFIG_VALUE = config_loader.get_config('MY_CONFIG_VALUE', 'default-value')

# Get a required configuration value
MY_REQUIRED_VALUE = config_loader.get_required_config('MY_REQUIRED_VALUE')

# Get a configuration value as an integer
MY_INT_VALUE = config_loader.get_int_config('MY_INT_VALUE', 10)
