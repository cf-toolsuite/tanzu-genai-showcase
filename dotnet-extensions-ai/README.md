# Travel Advisor - Tanzu GenAI Showcase

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
- **Semantic Kernel**: Microsoft's framework for LLM integration
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
2. Configure environment variables in `src/.env`
3. Build and run the application:

```bash
# Build the solution
dotnet build

# Run the web project
dotnet run --project cd src/TravelAdvisor.Web
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

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is licensed under the Apache License 2.0 - see the LICENSE file for details.

## Acknowledgments

- Microsoft for Semantic Kernel
- Steeltoe team for Cloud Foundry integration libraries
- Tailwind CSS team for the UI framework
