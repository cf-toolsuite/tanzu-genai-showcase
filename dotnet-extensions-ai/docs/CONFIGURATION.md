# Environment Variables in Travel Advisor

This document provides information on using environment variables with the TravelAdvisor application.

## Overview

TravelAdvisor supports environment variables for configuring various API keys and settings. This approach allows you to:

- Keep sensitive configuration out of your codebase
- Easily change configuration without modifying code
- Support different environments (development, testing, production)
- Simplify deployment to Cloud Foundry or other platforms

## Supported Configuration Methods

TravelAdvisor supports multiple ways to configure the application:

1. **Environment Variables** - System-level or process-level environment variables
2. **`.env` Files** - Local environment variable files for development
3. **User Secrets** - The .NET user secrets system for local development
4. **Cloud Foundry Service Bindings** - When running in Cloud Foundry

The application loads configuration in the following order (later sources override earlier ones):

1. Default values
2. `.env` file values
3. User secrets
4. System environment variables
5. Cloud Foundry service bindings

## Environment Variable Naming

In ASP.NET Core, environment variables with double underscores (`__`) are automatically mapped to configuration sections. For example:

- `GENAI__APIKEY` maps to `GenAI:ApiKey` in the configuration
- `GOOGLEMAPS__APIKEY` maps to `GoogleMaps:ApiKey` in the configuration

## Using .env Files

For local development, you can create a `.env` file in the project root. This file should contain your environment variables in the format:

```
KEY=VALUE
```

Example `.env` file:

```
# GenAI Configuration
GENAI__APIKEY=your_genai_api_key_here
GENAI__APIURL=https://api.openai.com/v1
GENAI__MODEL=gpt-4o-mini
GENAI__SERVICENAME=travel-advisor-llm

# GoogleMaps Configuration
GOOGLEMAPS__APIKEY=your_googlemaps_api_key_here
```

The application will automatically load this file at startup.

### Setup Instructions

1. Copy the example file:

   ```bash
   cp .env.example .env
   ```

2. Edit the `.env` file and replace the placeholder values with your actual API keys.

3. Run the application normally:

   ```bash
   dotnet run --project src/TravelAdvisor.Web
   ```

## Available Environment Variables

### GenAI Configuration

| Environment Variable | Configuration Key | Description | Default |
|---------------------|-------------------|-------------|---------|
| `GENAI__APIKEY` | `GenAI:ApiKey` | API key for the GenAI service | _(required)_ |
| `GENAI__APIURL` | `GenAI:ApiUrl` | API URL for the GenAI service | `https://api.openai.com/v1` |
| `GENAI__MODEL` | `GenAI:Model` | Model name to use | `gpt-4o-mini` |
| `GENAI__SERVICENAME` | `GenAI:ServiceName` | Service name for Cloud Foundry binding | `travel-advisor-llm` |

### Google Maps Configuration

| Environment Variable | Configuration Key | Description | Default |
|---------------------|-------------------|-------------|---------|
| `GOOGLEMAPS__APIKEY` | `GoogleMaps:ApiKey` | API key for Google Maps | _(required)_ |

## Cloud Foundry Service Bindings

When deploying to Cloud Foundry, you can bind your application to services. TravelAdvisor will detect services with names containing "genai" or tagged with "genai" and automatically use their credentials.

Example service binding:

```yaml
applications:
- name: travel-advisor
  services:
  - travel-advisor-llm
```

### User-Provided Services

You can create a user-provided service with your credentials:

```bash
cf create-user-provided-service travel-advisor-llm -p '{"api_key":"your_api_key", "api_url":"https://api.openai.com/v1", "model":"gpt-4o-mini"}'
```

Then bind it to your application:

```bash
cf bind-service travel-advisor travel-advisor-llm
```

## Troubleshooting

If you're experiencing issues with environment variables:

1. Check that your `.env` file is in the correct location (project root)
2. Verify that the environment variables are formatted correctly (e.g., `GENAI__APIKEY` not `GENAI_APIKEY`)
3. Check for any console log messages about environment variable loading
4. Try setting the environment variables directly in your terminal session
5. Restart your application after making changes to environment variables
