# Deploying the CrewAI + Django Movie Chatbot

This document provides comprehensive deployment instructions for the Movie Chatbot application, with a focus on Cloud Foundry deployment options with the Tanzu Platform.

## Table of Contents

- [Deployment Prerequisites](#deployment-prerequisites)
- [Deployment Options](#deployment-options)
  - [Standard Deployment](#standard-deployment)
  - [Vendor Dependency Approach](#vendor-dependency-approach)
- [GenAI Service Integration](#genai-service-integration)
- [Database Service Integration](#database-service-integration)
- [High Availability Configuration](#high-availability-configuration)
- [Custom Domain Configuration](#custom-domain-configuration)
- [Scaling Considerations](#scaling-considerations)
- [Common Issues & Troubleshooting](#common-issues--troubleshooting)
- [Deployment Checklist](#deployment-checklist)

## Deployment Prerequisites

Before deploying the application, ensure you have:

- Cloud Foundry CLI installed (`cf` command available)
- Access to a Tanzu Platform for Cloud Foundry environment
- Access to the GenAI tile in your foundation
- Proper permissions to create and bind services
- The application code from the repository

Additional requirements:

- API keys for external services if not using bound services:
  - TMDB API key (required)
  - SerpAPI key (optional, for theater showtimes)
- Understanding of your organization's network policies
- Knowledge of resource constraints in your environment

## Deployment Options

### Standard Deployment

1. Login to your Cloud Foundry instance:

   ```bash
   cf login -a API_ENDPOINT -o YOUR_ORG -s YOUR_SPACE
   ```

2. Review and modify the `manifest.yml` file if needed:

   ```yaml
   applications:
   - name: movie-chatbot
     memory: 512M
     disk_quota: 1G
     instances: 1
     buildpacks:
       - python_buildpack
     command: gunicorn movie_chatbot.wsgi --log-file -
     env:
       PYTHONUNBUFFERED: true
       DISABLE_COLLECTSTATIC: 1
       DEBUG: false
       LOG_LEVEL: info
   ```

3. Build static assets locally:

   ```bash
   cd frontend
   npm run build
   cd ..
   python manage.py collectstatic --noinput
   ```

4. Push the application:

   ```bash
   cf push --no-start
   ```

5. Configure environment variables and services (see sections below)

6. Start the application:

   ```bash
   cf start movie-chatbot
   ```

### Vendor Dependency Approach

The vendor approach pre-packages the essential dependencies for deployment, which can be useful in environments with limited internet access during staging.

1. Run the deployment script:

   ```bash
   ./deploy-on-tp4cf.sh
   ```

   This script will:
   - Set up the vendor directory with dependencies
   - Collect static files
   - Stage the app artifact on Cloud Foundry

2. If you prefer to run the steps manually:

   ```bash
   # Set up the vendor directory
   ./setup-vendor.sh

   # Deploy to Cloud Foundry
   cf push
   ```

3. Configure environment variables and services (see sections below)

4. Start the application:

   ```bash
   cf start movie-chatbot
   ```

## GenAI Service Integration

The application is designed to work with the GenAI tile, which provides LLM capabilities.

### Creating a GenAI Service Instance

1. Check available service plans:

   ```bash
   cf marketplace -e genai
   ```

2. Create a service instance:

   ```bash
   cf create-service genai PLAN_NAME movie-chatbot-llm
   ```

   Where `PLAN_NAME` is one of the available service plans.

3. Check the service creation status:

   ```bash
   cf service movie-chatbot-llm
   ```

### Binding the GenAI Service

1. Bind the service to your application:

   ```bash
   cf bind-service movie-chatbot movie-chatbot-llm
   ```

2. Restage the application to apply the binding:

   ```bash
   cf restage movie-chatbot
   ```

3. Verify the binding:

   ```bash
   cf env movie-chatbot
   ```

   Look for the `VCAP_SERVICES` section in the output to confirm the service binding.

### Understanding Service Binding

When bound to a GenAI service, the application:

1. Automatically detects the service credentials from `VCAP_SERVICES` environment variable
2. Extracts API key, base URL, and model name from `credentials.credhub_ref`
3. Configures CrewAI agents to use these credentials
4. No manual API key configuration is needed

Example `VCAP_SERVICES` structure:

```json
VCAP_SERVICES: {
  "genai": [
    {
      "binding_guid": "da6d7b6c-f6a4-4352-8735-55c8f9d498e3",
      "binding_name": null,
      "credentials": {
        "credhub-ref": "/c/a0aae238-99b5-11ee-8608-5ab361936c3e/0b5ee21a-f281-4d56-a3fb-a1d76b07ce7e/da6d7b6c-f6a4-4352-8735-55c8f9d498e3/credentials"
      },
      "instance_guid": "0b5ee21a-f281-4d56-a3fb-a1d76b07ce7e",
      "instance_name": "movie-chatbot-llm",
      "label": "genai",
      "name": "movie-chatbot-llm",
      "plan": "prod-chat-tools-phi4-mini",
      "provider": null,
      "syslog_drain_url": null,
      "tags": [
        "genai",
        "llm"
      ],
      "volume_mounts": []
    }
  ]
}
```

## Database Service Integration

By default, the application uses SQLite for local development. For production deployments, a managed database service is recommended.

### Creating a Database Service

1. Check available database services:

   ```bash
   cf marketplace -e postgres
   ```

2. Create a database service instance:

   ```bash
   cf create-service postgresql PLAN_NAME movie-chatbot-db
   ```

3. Bind the database service:

   ```bash
   cf bind-service movie-chatbot movie-chatbot-db
   ```

4. Configure the application to use the database:

   ```bash
   cf set-env movie-chatbot DATABASE_URL_ENV_NAME VCAP_SERVICES_POSTGRESQL_0_CREDENTIALS_URI
   cf restage movie-chatbot
   ```

### Database Migration

When switching to a new database service, you need to run migrations:

1. Open an SSH connection to the application:

   ```bash
   cf ssh movie-chatbot
   ```

2. Navigate to the application directory:

   ```bash
   cd app
   ```

3. Run migrations:

   ```bash
   python manage.py migrate
   ```

## High Availability Configuration

For production deployments, configure high availability to ensure application resilience.

### Increasing Instance Count

1. Update the manifest with multiple instances:

   ```yaml
   applications:
   - name: movie-chatbot
     memory: 512M
     instances: 3
     # other settings...
   ```

   Or use the CF CLI:

   ```bash
   cf scale movie-chatbot -i 3
   ```

### Session Persistence

When running multiple instances, ensure session persistence:

1. Configure Redis service for session storage:

   ```bash
   cf create-service redis PLAN_NAME movie-chatbot-sessions
   cf bind-service movie-chatbot movie-chatbot-sessions
   ```

2. Set environment variables for session configuration:

   ```bash
   cf set-env movie-chatbot SESSION_ENGINE django.contrib.sessions.backends.cache
   cf set-env movie-chatbot SESSION_CACHE_ALIAS default
   cf restage movie-chatbot
   ```

### Health Checks

Configure custom health checks for better monitoring:

```bash
cf set-health-check movie-chatbot http --endpoint /health
cf set-health-check-timeout movie-chatbot 30
```

Create a health endpoint in your application at `/health`.

## Custom Domain Configuration

To use a custom domain for your application:

1. Register the domain with your Cloud Foundry instance:

   ```bash
   cf create-domain ORG_NAME example.com
   ```

2. Map the route to your application:

   ```bash
   cf map-route movie-chatbot example.com --hostname booking
   ```

   This would make your app available at `booking.example.com`.

## Scaling Considerations

### Memory Sizing

The application memory requirements depend on several factors:

- **Base Django application**: ~128MB
- **CrewAI agent processing**: +256MB
- **Number of concurrent users**: +64MB per 10 users

Recommended configurations:

- **Development/Testing**: 512MB
- **Production (low traffic)**: 1GB
- **Production (high traffic)**: 2GB+ with multiple instances

### Disk Space

Disk requirements:

- **Application code**: ~50MB
- **Dependencies**: ~150MB
- **Logs & temporary files**: ~200MB
- **SQLite database (if used)**: Variable (use managed DB service instead)

Recommended disk quota: 1GB minimum

### CPU Considerations

CPU usage spikes during:

- CrewAI agent processing
- Complex movie searches
- Theater/showtime retrieval

Consider using performance-optimized instance types for production deployments.

## Common Issues & Troubleshooting

### Application Crashes on Startup

**Symptoms**: Application crashes immediately after deployment

**Possible causes and solutions**:

1. **Missing environment variables**:

   Check logs for missing configuration:

   ```bash
   cf logs movie-chatbot --recent
   ```

   Solution: Add missing environment variables:

   ```bash
   cf set-env movie-chatbot MISSING_VARIABLE value
   cf restage movie-chatbot
   ```

2. **Database migration issues**:

   Check logs for migration errors:

   ```bash
   cf logs movie-chatbot --recent | grep -i migration
   ```

   Solution: Connect via SSH and run migrations manually:

   ```bash
   cf ssh movie-chatbot -c "cd app && python manage.py migrate --no-input"
   ```

3. **Memory limits exceeded**:

   Check if the application is hitting memory limits:

   ```bash
   cf app movie-chatbot
   ```

   Solution: Increase memory allocation:

   ```bash
   cf scale movie-chatbot -m 1G
   ```

### GenAI Service Integration Issues

**Symptoms**: "Technical difficulties with the language model" error message

**Possible causes and solutions**:

1. **Incorrect service binding**:

   Verify service binding:

   ```bash
   cf services
   cf env movie-chatbot
   ```

   Solution: Rebind the service:

   ```bash
   cf unbind-service movie-chatbot movie-chatbot-llm
   cf bind-service movie-chatbot movie-chatbot-llm
   cf restage movie-chatbot
   ```

2. **Incompatible API format**:

   Check if the GenAI service provides an OpenAI-compatible API.

   Solution: Set a custom base URL if needed:

   ```bash
   cf set-env movie-chatbot LLM_BASE_URL custom_endpoint_url
   cf restage movie-chatbot
   ```

### Network Connectivity Issues

**Symptoms**: Application unable to reach external APIs

**Possible causes and solutions**:

1. **Outbound network restrictions**:

   Check if outbound network requests are blocked by network policies.

   Solution: Configure network policies to allow outbound connections:

   ```bash
   cf add-network-policy movie-chatbot --destination-app internet --protocol tcp --port 443
   ```

2. **API rate limiting**:

   Check logs for rate limit errors from TMDb or SerpAPI.

   Solution: Implement request throttling or upgrade API plans.

## Deployment Checklist

Use this checklist before deployment to production:

### Pre-Deployment Checklist

- [ ] Configure all required API keys
- [ ] Set `DEBUG=false` in environment
- [ ] Set appropriate logging configuration
- [ ] Run tests locally to verify application functionality
- [ ] Build and collect static assets
- [ ] Review memory and disk allocations in manifest.yml
- [ ] Ensure proper buildpack configuration

### Post-Deployment Verification

- [ ] Verify application is running (`cf app movie-chatbot`)
- [ ] Check logs for startup errors (`cf logs movie-chatbot --recent`)
- [ ] Test basic application functionality via browser
- [ ] Verify service bindings are working properly
- [ ] Test conversation mode switching
- [ ] Test movie search and recommendation features
- [ ] Test location detection functionality
- [ ] Monitor resource usage during testing

### Security Checklist

- [ ] Ensure no API keys are hardcoded in source code
- [ ] Verify service binding credentials are properly secured
- [ ] Check that DEBUG mode is disabled in production
- [ ] Verify CSRF protection is enabled
- [ ] Ensure sensitive environment variables are properly set
- [ ] Check that database connections are secured (if applicable)

## Additional Resources

For more detailed information, refer to:

- [Cloud Foundry Documentation](https://docs.cloudfoundry.org/)
- [Tanzu Platform Documentation](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform/)
- [Django Deployment Guide](https://docs.djangoproject.com/en/stable/howto/deployment/)
- [CrewAI Documentation](https://docs.crewai.com/)
