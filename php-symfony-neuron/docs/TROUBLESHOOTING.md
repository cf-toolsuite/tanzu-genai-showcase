# Troubleshooting Guide

## Common Issues and Solutions

### HTTP Client Configuration

If you encounter issues with HTTP client services not being found or properly configured:

1. Ensure the HTTP client is properly configured in `config/packages/framework.yaml`:
   ```yaml
   framework:
       http_client:
           default_options:
               headers:
                   'User-Agent': 'Symfony/NeuronAI Financial Research Application'
   ```

2. Make sure services are injecting the correct HTTP client service:
   ```yaml
   # In services.yaml
   services:
       App\Service\ApiClient\SomeApiClient:
           arguments:
               $httpClient: '@http_client'  # Use the default HTTP client
   ```

3. Clear the cache after making configuration changes:
   ```bash
   php bin/console cache:clear
   ```

### SEC EDGAR API Integration

The SEC EDGAR API doesn't require an API key, but it does require a proper User-Agent header:

1. Set the `SEC_EDGAR_USER_AGENT` environment variable in your `.env` file:
   ```
   SEC_EDGAR_USER_AGENT="YourAppName your_email@example.com"
   ```

2. Make sure the `edgar_api.user_agent` parameter is properly configured in `services.yaml`:
   ```yaml
   parameters:
       edgar_api.user_agent: '%env(SEC_EDGAR_USER_AGENT)%'
   ```

### Symfony Version Compatibility

If you encounter issues with command options not being recognized:

1. Use the version-compatible wrapper scripts in the `bin/` directory:
   ```bash
   # Instead of:
   php bin/console debug:container --show-private | grep http_client

   # Use:
   bin/debug-container.sh http_client
   ```

2. Check your Symfony version before using specific command options:
   ```bash
   php bin/console --version
   ```

### Cache Issues

If changes to configuration files aren't being reflected:

1. Clear the cache completely:
   ```bash
   rm -rf var/cache/*
   php bin/console cache:clear
   ```

2. Check for file permission issues:
   ```bash
   chmod -R 777 var/cache var/log
   ```

## Debugging Tools

### Service Container Debugging

Use the `debug-container.sh` script to inspect services in a version-compatible way:

```bash
# List all services
bin/debug-container.sh

# Filter services by name
bin/debug-container.sh http_client

# Show service details
bin/debug-container.sh --tag=container.service_subscriber
```

### Environment Variables

Check that environment variables are properly loaded:

```bash
php bin/console debug:container --env-vars
```

### Configuration Debugging

Validate your Symfony configuration:

```bash
php bin/console debug:config framework http_client
```

## Getting Help

If you continue to experience issues:

1. Check the Symfony documentation for your specific version
2. Review the application logs in `var/log/`
3. Contact the development team with specific error messages and steps to reproduce
