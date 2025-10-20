# Configuration in Travel Advisor

This document provides information on configuring the TravelAdvisor application using modern .NET practices.

## Overview

TravelAdvisor uses a clean configuration approach that follows .NET best practices:

- Keep sensitive configuration out of your codebase
- Easily change configuration without modifying code
- Support different environments (development, testing, production)
- Simplify deployment to Cloud Foundry or other platforms

## Supported Configuration Methods

TravelAdvisor supports multiple ways to configure the application:

1. **Cloud Foundry Service Bindings** - When running in Cloud Foundry
1. **Environment Variables** - System-level or process-level environment variables
1. **.env Files** - Local development configuration files
1. **User Secrets** - The .NET user secrets system for local development
1. **appsettings.json** - Default configuration values

The application loads configuration in the following order (later sources override earlier ones):

1. Default values from appsettings.json
1. .env files (if present)
1. Environment variables
1. User secrets (for local development)
1. Cloud Foundry service bindings (highest precedence)

For more information on .NET configuration patterns, see the [Microsoft Configuration Documentation](https://learn.microsoft.com/dotnet/core/extensions/configuration).

## Environment Variable Naming

In ASP.NET Core, environment variables with double underscores (`__`) are automatically mapped to configuration sections. For example:

- `GENAI__APIKEY` maps to `GenAI:ApiKey` in the configuration
- `GOOGLEMAPS__APIKEY` maps to `GoogleMaps:ApiKey` in the configuration

## Local Development Configuration

For local development, you have two main options for storing sensitive configuration:

### Option 1: .env Files

Create a `.env` file in the project root with your configuration:

```bash
# GenAI Configuration
GENAI__APIKEY=your-openai-api-key-here
GENAI__APIURL=https://api.openai.com/v1
GENAI__MODEL=gpt-4o-mini

# Google Maps Configuration
GOOGLEMAPS__APIKEY=your-google-maps-api-key-here
```

The application automatically loads `.env` files using the `dotenv.net` library.

### Option 2: User Secrets

Alternatively, use the .NET user secrets system:

1. Initialize user secrets:

   ```bash
   cd src/TravelAdvisor.Web
   dotnet user-secrets init
   ```

2. Add your API keys:

   ```bash
   dotnet user-secrets set "GenAI:ApiKey" "your_genai_api_key_here"
   dotnet user-secrets set "GoogleMaps:ApiKey" "your_googlemaps_api_key_here"
   ```

For more information on User Secrets, see the [Microsoft User Secrets Documentation](https://learn.microsoft.com/aspnet/core/security/app-secrets).

## Configuration Reference

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

### Application Settings

| Environment Variable | Configuration Key | Description | Default |
|---------------------|-------------------|-------------|---------|
| `USE_MOCK_DATA` | `Use_Mock_Data` | Use mock data instead of real APIs | `false` |

For logging configuration, see the [Microsoft Logging Documentation](https://learn.microsoft.com/dotnet/core/extensions/logging).

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

If you're experiencing issues with configuration:

1. **.env File Issues**:
   - Ensure the `.env` file is in the project root directory
   - Verify the file format uses `KEY=value` syntax
   - Check that environment variables use double underscores (`__`) for hierarchical configuration
   - Make sure the `.env` file is not empty and contains valid key-value pairs

2. **User Secrets Issues**:
   - Verify user secrets are initialized: `dotnet user-secrets list`
   - Check that you're in the correct project directory (`src/TravelAdvisor.Web`)
   - Ensure secrets are set with the correct key format (e.g., `GenAI:ApiKey`)

3. **Environment Variables**:
   - Verify that environment variables are formatted correctly (e.g., `GENAI__APIKEY` maps to `GenAI:ApiKey`)
   - Check for any console log messages about configuration loading
   - Try setting the environment variables directly in your terminal session

4. **Cloud Foundry Service Bindings**:
   - Verify services are bound to your application: `cf services`
   - Check that service credentials contain the required fields (`api_key`, `api_base`, etc.)
   - Review application logs for service binding detection messages

5. **General**:
   - Restart your application after making configuration changes
   - Use `dotnet user-secrets list` to verify your local configuration
   - Check the application logs for detailed configuration information
