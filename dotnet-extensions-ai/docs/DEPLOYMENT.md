# Deploying Travel Advisor to Tanzu Platform for Cloud Foundry

This document provides detailed instructions for deploying the Travel Advisor application to Tanzu Platform for Cloud Foundry.

## Prerequisites

- [.NET 9 SDK](https://dotnet.microsoft.com/download/dotnet/9.0) installed on your local machine
- [CF CLI](https://docs.cloudfoundry.org/cf-cli/install-go-cli.html) installed on your local machine
- Access to a Tanzu Platform for Cloud Foundry environment
- Proper permissions to create and bind services
- Access to the GenAI tile in your Tanzu Platform environment
- Google Maps API key

## Building for Deployment

1. Build the application in Release mode:

```bash
dotnet publish -c Release -r linux-x64 --self-contained false
```

## Creating Required Services in Tanzu Platform

1. Log in to your Tanzu Platform for Cloud Foundry environment:

```bash
cf login -a <API_ENDPOINT> -u <USERNAME> -p <PASSWORD> -o <ORG> -s <SPACE>
```

2. Create a service instance for the LLM from the GenAI tile:

```bash
# List GenAI tile service offering plans
cf marketplace -e genai

# Create a service instance from one of the available plans
cf create-service genai PLAN_NAME travel-advisor-llm
```

> [!IMPORTANT]
> Replace `PLAN_NAME` above with an available plan from the GenAI tile service offering

## Deployment Steps

1. Push the application to Cloud Foundry:

```bash
cf push --no-start
```

2. Bind the application to the service instance:

```bash
cf bind-service travel-advisor travel-advisor-llm
```

3. You'll need to set an environment variable for the Google Maps API key:

```bash
cf set-env travel-advisor GOOGLEMAPS__APIKEY your_google_maps_api_key
```

> [!IMPORTANT]
> Replace `your_google_maps_api_key` above with a valid Google Maps API key with appropriate permissions.

4. Start the application:

```bash
cf start travel-advisor
```

## Verifying Deployment

1. After deployment, verify the application is running:

```bash
cf apps
```

2. Find the route where the application is deployed:

```bash
cf app travel-advisor
```

3. Open the application URL in a browser to test it.

## Troubleshooting

### Service Binding Issues

If there are issues with service bindings, check the logs:

```bash
cf logs travel-advisor --recent
```

### Viewing Application Health

To view the health of the application using Steeltoe actuators:

```bash
curl https://<APP_URL>/health
```

### Restarting the Application

If you need to restart the application:

```bash
cf restart travel-advisor
```

## Updating the Application

1. Make your changes to the application
2. Rebuild in Release mode
3. Push the updated application:

```bash
cf push
```

## Scaling the Application

To scale the application vertically (change memory):

```bash
cf scale travel-advisor -m 1G
```

To scale the application horizontally (change instance count):

```bash
cf scale travel-advisor -i 3
```

## Rollback

If a deployment goes wrong, you can rollback to the previous state:

```bash
cf rollback travel-advisor
```

## Additional Resources

- [Steeltoe Documentation](https://docs.steeltoe.io)
- [Cloud Foundry Documentation](https://docs.cloudfoundry.org)
- [.NET Core Buildpack Documentation](https://docs.cloudfoundry.org/buildpacks/dotnet-core/index.html)
