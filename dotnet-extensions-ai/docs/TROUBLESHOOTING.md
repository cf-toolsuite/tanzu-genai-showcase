# Travel Advisor Troubleshooting Guide

This document provides solutions for common issues you might encounter when working with the Travel Advisor application.

## Table of Contents

1. [Environment Setup Issues](#environment-setup-issues)
2. [API Key Issues](#api-key-issues)
3. [Build and Runtime Errors](#build-and-runtime-errors)
4. [LLM Integration Issues](#llm-integration-issues)
5. [Google Maps Integration Issues](#google-maps-integration-issues)
6. [Cloud Foundry Deployment Issues](#cloud-foundry-deployment-issues)
7. [Performance Issues](#performance-issues)
8. [UI Issues](#ui-issues)
9. [Logging and Debugging](#logging-and-debugging)
10. [Support Contact Information](#support-contact-information)

## Environment Setup Issues

### .NET SDK Version Mismatch

**Issue**: Application fails to build with SDK version errors.

**Solution**:

1. Verify your .NET SDK version:

   ```bash
   dotnet --version
   ```

2. Ensure you have .NET 9 SDK installed:

   ```bash
   dotnet --list-sdks
   ```

3. If needed, download and install the correct SDK from [dotnet.microsoft.com](https://dotnet.microsoft.com/download/dotnet/9.0).
4. Check the `global.json` file to ensure it specifies the correct SDK version.

### Environment Variables Not Loading

**Issue**: Application cannot find environment variables.

**Solution**:

1. Verify your user secrets are properly configured:

   ```bash
   cd src/TravelAdvisor.Web
   dotnet user-secrets list
   ```

2. Check the format of your configuration keys (use colons for nested settings):

   ```bash
   GENAI__APIKEY=your_api_key
   ```

3. Try setting environment variables directly in your terminal:

   ```bash
   export GENAI__APIKEY=your_api_key
   ```

4. Check the application logs for configuration loading errors.
5. Ensure user secrets are initialized: `dotnet user-secrets init`

## API Key Issues

### Missing or Invalid API Keys

**Issue**: Application fails with API key errors.

**Solution**:

1. Verify your API keys are correctly set in user secrets:

   ```bash
   dotnet user-secrets set "GenAI:ApiKey" "your_api_key"
   dotnet user-secrets set "GoogleMaps:ApiKey" "your_api_key"
   ```

2. Check that the API keys have the necessary permissions.
3. For Google Maps, ensure the API key has the required APIs enabled:
   - Directions API
   - Distance Matrix API
   - Geocoding API
4. For LLM services, verify the API key is active and has sufficient quota.
5. Check for any whitespace or special characters in your API keys.

### API Key Security

**Issue**: Concerns about API key security.

**Solution**:

1. Never commit API keys to version control.
2. Use environment variables or Cloud Foundry service bindings.
3. Consider using a secrets manager for production deployments.
4. Rotate API keys periodically.
5. Set appropriate restrictions on API keys (IP restrictions, API limitations).

## Build and Runtime Errors

### Build Failures

**Issue**: Application fails to build.

**Solution**:

1. Clean the solution and rebuild:

   ```bash
   dotnet clean
   dotnet build
   ```

2. Check for NuGet package restore issues:

   ```bash
   dotnet restore --force
   ```

3. Verify all project references are correct.
4. Check for syntax errors or missing dependencies.
5. Ensure the `nuget.config` file is correctly configured.

### Runtime Exceptions

**Issue**: Application throws exceptions during execution.

**Solution**:

1. Check the application logs for detailed error information.
2. Verify all required services are available and configured.
3. Ensure environment variables are correctly set.
4. Check for null reference exceptions in the code.
5. Verify API endpoints are accessible.

## LLM Integration Issues

### LLM Service Connection Failures

**Issue**: Cannot connect to the LLM service.

**Solution**:

1. Verify the API key and URL are correct.
2. Check network connectivity to the LLM service.
3. Ensure the service is available and not experiencing downtime.
4. Check for rate limiting or quota issues.
5. Try using the mock implementation for testing:

   ```bash
   export USE_MOCK_DATA=true
   ```

### Poor Quality LLM Responses

**Issue**: LLM responses are inaccurate or unhelpful.

**Solution**:

1. Check the system prompts in `TravelAdvisorService.cs`.
2. Verify you're using an appropriate model (e.g., `gpt-4o-mini` or better).
3. Adjust the temperature parameter for more deterministic responses.
4. Consider adding more context or examples to the prompts.
5. Implement better error handling for edge cases.

### Custom Endpoint Issues

**Issue**: Problems with custom LLM endpoints.

**Solution**:

1. Verify the endpoint URL is correct and accessible.
2. Check that the endpoint follows the OpenAI API format.
3. Ensure the authentication method is correctly implemented.
4. Check the logs for detailed error messages.
5. Try using the official OpenAI endpoint for comparison.

## Google Maps Integration Issues

### Distance Calculation Errors

**Issue**: Incorrect or missing distance/duration information.

**Solution**:

1. Verify the Google Maps API key has the necessary permissions.
2. Check that the origin and destination are valid, recognizable locations.
3. Ensure the API key has the Directions API and Distance Matrix API enabled.
4. Check for quota limitations or billing issues.
5. Try using the mock implementation for testing:

   ```bash
   export USE_MOCK_DATA=true
   ```

### Invalid Locations

**Issue**: Application cannot recognize locations.

**Solution**:

1. Ensure locations are properly formatted (city, state, country).
2. Check for typos or special characters in location names.
3. Verify the Geocoding API is enabled for your Google Maps API key.
4. Try using more specific location information.
5. Check the logs for detailed error messages from the Google Maps API.

## Cloud Foundry Deployment Issues

### Deployment Failures

**Issue**: Application fails to deploy to Cloud Foundry.

**Solution**:

1. Verify your Cloud Foundry credentials and target:

   ```bash
   cf target
   ```

2. Check the manifest.yml file for errors.
3. Ensure all required services are created and available.
4. Check the deployment logs:

   ```bash
   cf logs travel-advisor --recent
   ```

5. Verify the buildpack is correctly specified and available.

### Service Binding Issues

**Issue**: Problems with service bindings in Cloud Foundry.

**Solution**:

1. Verify the service instance exists:

   ```bash
   cf services
   ```

2. Check that the service name matches what's expected in the code.
3. Ensure the service has the necessary credentials.
4. Verify the service binding was successful:

   ```bash
   cf service travel-advisor-llm
   ```

5. Check the `ServiceBindingConfiguration.cs` file for correct service detection.

### Application Crashes in Cloud Foundry

**Issue**: Application starts but then crashes.

**Solution**:

1. Check the application logs:

   ```bash
   cf logs travel-advisor --recent
   ```

2. Verify memory and disk quotas are sufficient.
3. Ensure all required environment variables are set.
4. Check for differences between local and Cloud Foundry environments.
5. Try increasing the memory allocation:

   ```bash
   cf scale travel-advisor -m 1G
   ```

## Performance Issues

### Slow Response Times

**Issue**: Application responds slowly to queries.

**Solution**:

1. Check the LLM service response times.
2. Verify Google Maps API response times.
3. Consider implementing caching for frequent queries.
4. Check for memory leaks or resource exhaustion.
5. Monitor CPU and memory usage during operation.

### High Resource Usage

**Issue**: Application uses excessive CPU or memory.

**Solution**:

1. Check for infinite loops or recursive calls.
2. Verify proper disposal of resources (especially HTTP clients).
3. Implement pagination for large result sets.
4. Consider using more efficient data structures.
5. Profile the application to identify bottlenecks.

## UI Issues

### Blazor Rendering Problems

**Issue**: UI elements don't render correctly.

**Solution**:

1. Check browser console for JavaScript errors.
2. Verify Blazor SignalR connection is established.
3. Clear browser cache and cookies.
4. Try a different browser.
5. Check for CSS conflicts or missing styles.

### Responsive Design Issues

**Issue**: UI doesn't adapt well to different screen sizes.

**Solution**:

1. Verify Tailwind CSS is correctly loaded.
2. Check responsive design classes in the Razor files.
3. Test on various devices and screen sizes.
4. Use browser developer tools to simulate different devices.
5. Implement additional media queries if needed.

## Logging and Debugging

### Enabling Detailed Logging

To enable more detailed logging:

1. Modify the logging level in `appsettings.json`:

   ```json
   "Logging": {
     "LogLevel": {
       "Default": "Information",
       "Microsoft": "Warning",
       "Microsoft.Hosting.Lifetime": "Information",
       "TravelAdvisor": "Debug"
     }
   }
   ```

2. For Cloud Foundry deployments, set the logging level via environment variable:

   ```bash
   cf set-env travel-advisor Logging__LogLevel__TravelAdvisor Debug
   cf restart travel-advisor
   ```

### Debugging Techniques

1. **Local Debugging**:
   - Use Visual Studio or VS Code debugging tools.
   - Set breakpoints at key points in the code.
   - Inspect variables and call stacks.

2. **Remote Debugging**:
   - For Cloud Foundry, use SSH tunneling:

     ```bash
     cf ssh -N -T -L 5000:localhost:8080 travel-advisor
     ```

   - Attach a remote debugger.

3. **Log Analysis**:
   - Use log aggregation tools.
   - Search for error patterns.
   - Correlate timestamps with user reports.

## Support Contact Information

If you encounter issues that aren't covered in this guide:

1. **GitHub Issues**: Create an issue in the [GitHub repository](https://github.com/cf-toolsuite/tanzu-genai-showcase).
2. **Community Support**: Join the [Tanzu Community](https://tanzu.vmware.com/community) for assistance.
3. **Documentation**: Check the latest documentation for updates.

## Common Error Messages and Solutions

| Error Message | Possible Cause | Solution |
|---------------|----------------|----------|
| "GenAI API key and URL are required" | Missing API credentials | Configure environment variables or service bindings |
| "Failed to create AI client" | Incorrect API URL or key | Verify credentials and endpoint URL |
| "Could not determine valid origin and destination" | LLM extraction failed | Provide more specific location information |
| "Service temporarily unavailable" | LLM service downtime | Try again later or use mock implementation |
| "Google Maps API request failed" | API key or quota issues | Check API key permissions and quota |
| "No recommendations found" | No suitable transportation modes | Try different locations or preferences |
| "Failed to bind to service" | Cloud Foundry service issues | Verify service exists and has correct credentials |

## Diagnostic Commands

### System Information

```bash
# Check .NET version
dotnet --version

# List installed SDKs
dotnet --list-sdks

# Check environment variables
env | grep GENAI
env | grep GOOGLEMAPS
```

### Cloud Foundry Diagnostics

```bash
# Check application status
cf app travel-advisor

# View recent logs
cf logs travel-advisor --recent

# Check bound services
cf services

# View application environment variables
cf env travel-advisor

# Check application health
curl https://<app-url>/health
```

### Network Diagnostics

```bash
# Test connectivity to OpenAI API
curl -I https://api.openai.com/v1

# Test connectivity to Azure OpenAI
curl -I https://<your-resource>.openai.azure.com

# Test connectivity to Google Maps API
curl -I https://maps.googleapis.com/maps/api/directions/json
```

Remember to replace placeholder values with your actual configuration details when using these commands.
