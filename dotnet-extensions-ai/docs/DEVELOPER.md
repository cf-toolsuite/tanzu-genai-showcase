# Travel Advisor Developer Guide

This document provides detailed instructions for setting up and developing the Travel Advisor application.

## Project Structure

The Travel Advisor application follows a clean architecture pattern with three main projects:

- **TravelAdvisor.Core**: Contains the domain models, interfaces, and core business logic
- **TravelAdvisor.Infrastructure**: Implements services defined in the Core project
- **TravelAdvisor.Web**: The Blazor Server web application

## Prerequisites

- [.NET 9 SDK](https://dotnet.microsoft.com/download/dotnet/9.0)
- [Visual Studio 2025](https://visualstudio.microsoft.com/) or [VS Code](https://code.visualstudio.com/) with C# extensions
- [Google Maps API key](https://developers.google.com/maps/documentation/javascript/get-api-key)
- Access to an LLM service compatible with the OpenAI API format

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
cd tanzu-genai-showcase/dotnet-extensions-ai
```

### 2. Configure Environment Variables

Create a `.env` file in the `src` directory:

```bash
cp src/.env.example src/.env
```

Edit the `.env` file to include:

```
GENAI__APIKEY=your_llm_api_key
GENAI__APIURL=your_llm_api_url
GENAI__MODEL=your_llm_model_name
GOOGLEMAPS__APIKEY=your_google_maps_api_key
```

### 3. Build and Run

#### Using dotnet CLI

```bash
# Restore dependencies
dotnet restore

# Build the solution
dotnet build

# Run the web project
dotnet run --project src/TravelAdvisor.Web
```

#### Using Visual Studio

1. Open the `TravelAdvisor.sln` file in Visual Studio
2. Right-click on the `TravelAdvisor.Web` project and select "Set as Startup Project"
3. Press F5 to build and run the application

## Key Components

### GenAI Integration

The application uses the Semantic Kernel library to interact with LLMs. The main integration is in:

- `TravelAdvisorService.cs`: Handles communication with the LLM to process queries and generate recommendations

### Google Maps Integration

The application uses the Google Maps API to get real travel data:

- `GoogleMapsService.cs`: Provides methods to get distance, duration, and other travel information

### Steeltoe Integration

The application uses Steeltoe for Cloud Foundry integration:

- Service bindings are configured in `ServiceBindingConfiguration.cs`
- Actuators for health monitoring and management are configured in `Program.cs`

## Development Workflow

### Adding New Features

1. Start by defining interfaces in the Core project
2. Implement the interfaces in the Infrastructure project
3. Wire up dependencies in the `DependencyInjection.cs` file
4. Add UI components in the Web project

### Testing

The application supports different testing approaches:

- Unit tests for core business logic
- Integration tests for services
- End-to-end tests for UI workflows

To run tests:

```bash
dotnet test
```

### UI Development

The application uses Tailwind CSS for styling. When making UI changes:

1. Edit the Razor files in the `TravelAdvisor.Web/Pages` and `TravelAdvisor.Web/Shared` folders
2. The application uses the Tailwind CDN, so styles will update automatically
3. For production, consider setting up a build process to optimize Tailwind CSS

## Troubleshooting

### Common Issues

1. **Missing Environment Variables**: Ensure the `.env` file is properly configured and loaded
2. **API Key Issues**: Verify your API keys are valid and have the necessary permissions
3. **Build Errors**: Run `dotnet clean` followed by `dotnet build` to resolve build issues

### Debugging

1. Use `Console.WriteLine` statements for simple logging
2. For more advanced logging, configure Steeltoe's dynamic logger
3. Use breakpoints in Visual Studio or VS Code to step through code

## Best Practices

1. **Separation of Concerns**: Keep business logic in the Core project
2. **Dependency Injection**: Use interfaces and DI for testable components
3. **Error Handling**: Use try-catch blocks and provide user-friendly error messages
4. **Environment Configuration**: Use environment variables for all configuration
5. **Security**: Never commit API keys or secrets to version control

## Contributing

1. Create a feature branch for your changes
2. Make your changes following the coding standards
3. Write/update tests for your changes
4. Ensure all tests pass
5. Submit a pull request

## Further Resources

- [.NET 9 Documentation](https://learn.microsoft.com/en-us/dotnet)
- [Blazor Documentation](https://learn.microsoft.com/en-us/aspnet/core/blazor)
- [Semantic Kernel Documentation](https://learn.microsoft.com/en-us/semantic-kernel/overview/)
- [Steeltoe Documentation](https://docs.steeltoe.io)
- [Tailwind CSS Documentation](https://tailwindcss.com/docs)
