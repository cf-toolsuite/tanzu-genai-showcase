# Travel Advisor

![Status](https://img.shields.io/badge/status-ready-darkgreen) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/dotnet-extensions-ai.yml/badge.svg)

A .NET 9 Blazor application showcasing the integration of LLMs with the Tanzu Platform for Cloud Foundry. This application recommends transportation options based on origin, destination, and user preferences.

## Features

- Natural language processing for travel queries
- Integration with Google Maps API for real travel data
- AI-powered analysis and recommendations
- Interactive UI with follow-up question capability
- Deployed as a Cloud Foundry application utilizing Tanzu GenAI services

## Tech Stack

- **.NET 9**: The latest version of the .NET framework
- **Blazor Server**: For interactive web UI
- **Microsoft.Extensions.AI**: Microsoft's framework for LLM integration
- **Steeltoe**: Libraries for Cloud Foundry integration
- **Tailwind CSS**: Utility-first CSS framework for modern UI

## Getting Started

### Prerequisites

- .NET 9 SDK
- Google Maps API key
- Access to an LLM service compatible with the OpenAI API format
- (Optional) Access to Tanzu Platform for Cloud Foundry with GenAI tile

### Quick Start

1. Clone this repository
2. Configure credentials for Google Maps and an AI service (see [Configuration Guide](docs/CONFIGURATION.md))
3. Build and run the application:

```bash
# Build the solution
dotnet build

# Run the web project
dotnet run --project src/TravelAdvisor.Web
```

4. Open your browser to `https://localhost:5000`

For more detailed instructions, see:

- [Developer Guide](docs/DEVELOPER.md)
- [User Guide](docs/USER.md)
- [Deployment Guide](docs/DEPLOYMENT.md)

## Architecture

The application follows a clean architecture pattern with three main projects:

- **TravelAdvisor.Core**: Domain models, interfaces, and core business logic
- **TravelAdvisor.Infrastructure**: Implementation of services defined in Core
- **TravelAdvisor.Web**: Blazor Server web application

## Cloud Foundry Deployment

The application is designed to be deployed to Tanzu Platform for Cloud Foundry:

- Integrates with the GenAI tile for access to managed LLM services
- Uses Steeltoe for service bindings and health monitoring
- Includes a manifest for easy deployment with `cf push`

See the [Deployment Guide](docs/DEPLOYMENT.md) for detailed instructions.

## Documentation

For detailed information about the application, refer to the following documentation:

- [Architecture Overview](docs/ARCHITECTURE.md): System architecture, components, and data flow
- [API Documentation](docs/API.md): Detailed API reference and usage examples
- [Configuration Guide](docs/CONFIGURATION.md): Environment variables, .env files, and configuration options
- [Developer Guide](docs/DEVELOPER.md): Setup, development workflow, and best practices
- [Deployment Guide](docs/DEPLOYMENT.md): Deployment instructions for Tanzu Platform
- [User Guide](docs/USER.md): End-user instructions for using the application
- [Extension Guide](docs/EXTENSION.md): How to extend the application with custom features

## Acknowledgments

- Microsoft for Microsoft.Extensions.AI
- Steeltoe team for Cloud Foundry integration libraries
- Tailwind CSS team for the UI framework
