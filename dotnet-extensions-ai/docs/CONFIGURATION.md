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

1. **Cloud Foundry Service Bindings** - When running in Cloud Foundry (highest precedence)
2. **Environment Variables** - System-level or process-level environment variables
3. **User Secrets** - The .NET user secrets system for local development
4. **appsettings.json** - Default configuration values

The application loads configuration in the following order (later sources override earlier ones):

1. Default values from appsettings.json
2. User secrets (for local development)
3. Environment variables
4. Cloud Foundry service bindings (highest precedence)

## Environment Variable Naming

In ASP.NET Core, environment variables with double underscores (`__`) are automatically mapped to configuration sections. For example:

- `GENAI__APIKEY` maps to `GenAI:ApiKey` in the configuration
- `GOOGLEMAPS__APIKEY` maps to `GoogleMaps:ApiKey` in the configuration

## Using User Secrets for Local Development

For local development, use the .NET user secrets system to store sensitive configuration:

### Setup Instructions

1. Initialize user secrets for the Web project:

   ```bash
   cd src/TravelAdvisor.Web
   dotnet user-secrets init
   ```

2. Add your API keys to user secrets:

   ```bash
   dotnet user-secrets set "GenAI:ApiKey" "your_genai_api_key_here"
   dotnet user-secrets set "GoogleMaps:ApiKey" "your_googlemaps_api_key_here"
   ```

3. Run the application normally:

   ```bash
   dotnet run --project src/TravelAdvisor.Web
   ```

### Viewing User Secrets

To view your configured secrets:

```bash
dotnet user-secrets list
```

### Removing User Secrets

To remove a secret:

```bash
dotnet user-secrets remove "GenAI:ApiKey"
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

If you're experiencing issues with configuration:

1. **User Secrets Issues**:
   - Verify user secrets are initialized: `dotnet user-secrets list`
   - Check that you're in the correct project directory (`src/TravelAdvisor.Web`)
   - Ensure secrets are set with the correct key format (e.g., `GenAI:ApiKey`)

2. **Environment Variables**:
   - Verify that environment variables are formatted correctly (e.g., `GenAI__ApiKey` maps to `GenAI:ApiKey`)
   - Check for any console log messages about configuration loading
   - Try setting the environment variables directly in your terminal session

3. **Cloud Foundry Service Bindings**:
   - Verify services are bound to your application: `cf services`
   - Check that service credentials contain the required fields (`api_key`, `api_base`, etc.)
   - Review application logs for service binding detection messages

4. **General**:
   - Restart your application after making configuration changes
   - Use `dotnet user-secrets list` to verify your local configuration
   - Check the application logs for detailed configuration information
