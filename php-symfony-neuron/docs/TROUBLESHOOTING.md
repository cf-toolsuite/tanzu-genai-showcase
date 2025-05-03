# Troubleshooting Guide

This document provides solutions to common issues that may arise when working with the Neuron AI + Symfony Company Research Application.

## Table of Contents

- [Installation Issues](#installation-issues)
- [Configuration Problems](#configuration-problems)
- [Runtime Errors](#runtime-errors)
- [Database Issues](#database-issues)
- [Neuron AI Integration Problems](#neuron-ai-integration-problems)
- [Deployment Challenges](#deployment-challenges)
- [Performance Optimization](#performance-optimization)
- [Common Error Messages](#common-error-messages)

## Installation Issues

### PHP Extension Requirements

**Problem**: Error messages about missing PHP extensions during installation or runtime.

**Solution**:
1. Check which extensions are required in the [SETUP.md](./SETUP.md) document
2. Install missing extensions using your package manager:

   ```bash
   # Ubuntu
   sudo apt install php8.3-intl php8.3-curl php8.3-mbstring php8.3-xml php8.3-zip

   # macOS
   brew install php-intl php-curl php-mbstring php-xml php-zip
   ```

3. Verify extensions are properly installed:

   ```bash
   php -m | grep extension_name
   ```

### Composer Dependency Issues

**Problem**: Dependency conflicts or version constraints preventing installation.

**Solution**:
1. Make sure you have the latest Composer version:

   ```bash
   composer self-update
   ```

2. Clear Composer cache:

   ```bash
   composer clear-cache
   ```

3. Try running with higher memory limit:

   ```bash
   php -d memory_limit=-1 /usr/local/bin/composer install
   ```

4. Update dependencies if possible:

   ```bash
   composer update
   ```

### Permission Issues

**Problem**: Permission denied errors when writing to var/cache, var/log, etc.

**Solution**:
1. Set proper permissions for Symfony directories:

   ```bash
   # Development permissions (not for production)
   sudo chmod -R 777 var/cache var/log

   # Better approach
   sudo setfacl -R -m u:www-data:rwX -m u:$(whoami):rwX var/cache var/log
   sudo setfacl -dR -m u:www-data:rwX -m u:$(whoami):rwX var/cache var/log
   ```

2. Check file ownership:

   ```bash
   sudo chown -R $(whoami):www-data .
   ```

## Configuration Problems

### Environment Variables Not Loaded

**Problem**: Application cannot find environment variables.

**Solution**:
1. Check if your `.env.local` file exists and has proper permissions
2. Ensure syntax is correct with no spaces around `=` signs:

   ```
   VARIABLE_NAME=value
   ```

3. Make sure you're not overriding variables in other .env files
4. For shell environments, check if variables are exported properly:

   ```bash
   export GENAI_API_KEY=your_key_here
   ```

### Symfony Environment Configuration

**Problem**: Application running in wrong environment (prod vs. dev).

**Solution**:
1. Check APP_ENV setting in your .env file:

   ```
   APP_ENV=dev
   ```

2. For production deployment, ensure it's set to:

   ```
   APP_ENV=prod
   APP_DEBUG=0
   ```

3. Clear cache after changing environments:

   ```bash
   php bin/console cache:clear
   ```

## Runtime Errors

### 500 Internal Server Error

**Problem**: Application returns 500 error with no specific error message in browser.

**Solution**:
1. Check logs for detailed error messages:

   ```bash
   tail -f var/log/dev.log
   # or for production
   tail -f var/log/prod.log
   ```

2. Temporarily enable more verbose errors by setting in index.php:

   ```php
   ini_set('display_errors', 1);
   ini_set('display_startup_errors', 1);
   error_reporting(E_ALL);
   ```

3. Check for server configuration issues in Apache/Nginx logs

### Route Not Found (404)

**Problem**: URLs that should be valid return 404 Not Found errors.

**Solution**:
1. Check route definitions in controllers and config/routes.yaml
2. Verify route cache is updated:

   ```bash
   php bin/console cache:clear
   php bin/console router:match /your/path
   ```

3. Check for typos in route names or path parameters
4. Ensure annotations/attributes are processed correctly

### Form Submission Issues

**Problem**: Form submission fails or doesn't process correctly.

**Solution**:
1. Check CSRF protection (ensure token is present and valid)
2. Verify form field names match entity properties
3. Validate form constraints in code
4. Check browser console for JavaScript errors
5. Clear browser cache and cookies

## Database Issues

### Migration Errors

**Problem**: Database migrations fail to execute.

**Solution**:
1. Check database connection parameters in .env.local
2. Ensure database exists and user has proper permissions
3. Run migrations in verbose mode to see detailed errors:

   ```bash
   php bin/console doctrine:migrations:migrate -v
   ```

4. Check for SQL syntax errors in migration files
5. For fresh start (development only), drop schema and recreate:

   ```bash
   php bin/console doctrine:schema:drop --force
   php bin/console doctrine:schema:create
   ```

### Entity Mapping Errors

**Problem**: Doctrine cannot map entities to database tables.

**Solution**:
1. Validate entity mappings:

   ```bash
   php bin/console doctrine:schema:validate
   ```

2. Check for inconsistencies between entity definitions and actual database
3. Update getter/setter methods to match property names
4. Ensure relationship mappings are correct (OneToMany, ManyToOne, etc.)

### Query Performance Issues

**Problem**: Database queries are slow, especially with larger datasets.

**Solution**:
1. Check for missing indexes on frequently queried fields:

   ```php
   #[ORM\Index(columns: ["ticker_symbol"])]
   ```

2. Review repository methods for inefficient queries
3. Use Symfony Profiler to identify slow queries
4. Consider implementing pagination for large result sets
5. Add database-specific optimizations (MySQL, PostgreSQL, etc.)

## Neuron AI Integration Problems

### API Key Authentication Failed

**Problem**: Cannot authenticate with the LLM service.

**Solution**:
1. Verify API key is correct and not expired
2. Check if key has proper permissions for required operations
3. Ensure environment variables are properly loaded:

   ```bash
   php bin/console debug:container --env-vars
   ```

4. Try regenerating API key from the provider's dashboard

### Malformed AI Responses

**Problem**: AI responses are incomplete, malformed, or not usable.

**Solution**:
1. Check prompt engineering in NeuronAiService
2. Ensure API response parsing handles errors gracefully
3. Implement retry logic with exponential backoff
4. Adjust parameters like temperature or max_tokens
5. Verify model compatibility with your prompts

### Rate Limit Exceeded

**Problem**: Hitting rate limits from the LLM service.

**Solution**:
1. Implement request throttling in the NeuronAiService
2. Add caching for common or repetitive queries
3. Use bulk operations where possible instead of many small requests
4. Consider upgrading to a higher tier service plan
5. Add fallback mechanisms for when rate limits are reached

## Deployment Challenges

### Cloud Foundry Deployment Issues

**Problem**: Application fails to deploy to Cloud Foundry.

**Solution**:
1. Check manifest.yml for correct settings
2. Verify buildpack compatibility with PHP version
3. Look at staging logs for detailed error messages:

   ```bash
   cf logs company-research --recent
   ```

4. Ensure application size doesn't exceed platform limits
5. Check memory allocation and increase if necessary:

   ```bash
   cf scale company-research -m 1G
   ```

### Service Binding Issues

**Problem**: Cannot bind or use bound services in Cloud Foundry.

**Solution**:
1. Verify service instance exists and is available:

   ```bash
   cf services
   ```

2. Check service binding status:

   ```bash
   cf service company-research-llm
   ```

3. Ensure application code correctly accesses VCAP_SERVICES environment variables
4. Restart/restage application after binding services:

   ```bash
   cf restage company-research
   ```

5. Check service-specific documentation for binding requirements

## Performance Optimization

### Slow Page Loads

**Problem**: Web pages load slowly, especially with complex data.

**Solution**:
1. Implement HTTP caching where appropriate:

   ```php
   $response->setPublic();
   $response->setMaxAge(3600);
   ```

2. Use Symfony's cache component for expensive operations
3. Optimize database queries (add indexes, join optimization)
4. Implement pagination for large result sets
5. Consider using AJAX for loading data asynchronously

### High Memory Usage

**Problem**: Application consumes excessive memory, especially during AI operations.

**Solution**:
1. Optimize Doctrine entity manager (clear after batch operations)
2. Break large operations into smaller chunks
3. Implement garbage collection for long-running processes
4. Use streaming responses for large datasets
5. Increase memory allocation if necessary, but address root causes

### Slow AI Processing

**Problem**: AI-related features take too long to respond.

**Solution**:
1. Implement background processing for non-interactive AI tasks
2. Use Symfony Messenger for asynchronous operations
3. Cache common AI responses where appropriate
4. Optimize prompt length and complexity
5. Consider implementing progress indicators for long-running operations

## Common Error Messages

### "Failed to connect to database server"

**Possible causes**:
- Incorrect database credentials
- Database server not running
- Network connectivity issues
- Firewall blocking connection

**Solutions**:
1. Verify database credentials in .env.local
2. Check database server status
3. Test connection using command-line tools:

   ```bash
   mysql -u username -p -h hostname
   ```

4. Check for network/firewall issues

### "Class 'App\Entity\SomeEntity' not found"

**Possible causes**:
- Namespace issues
- Autoloading problems
- Typos in class names or file paths

**Solutions**:
1. Check namespace declaration matches directory structure
2. Verify PSR-4 autoloading configuration in composer.json
3. Run composer dump-autoload
4. Check for case sensitivity issues in filenames

### "Unable to generate a URL for the named route"

**Possible causes**:
- Route doesn't exist
- Missing required parameters
- Typo in route name

**Solutions**:
1. List all available routes:

   ```bash
   php bin/console debug:router
   ```

2. Check route definition in controller annotations/attributes
3. Verify all required parameters are provided
4. Clear router cache:

   ```bash
   php bin/console cache:clear
   ```

### "Technical difficulties with the language model"

**Possible causes**:
- AI service connectivity issues
- Invalid API key
- Rate limiting
- Incompatible API formats

**Solutions**:
1. Check API connectivity and credentials
2. Look for more specific error messages in logs
3. Implement better error handling in the NeuronAiService
4. Add proper fallback mechanisms for AI service failures

---

If you encounter an issue not covered in this document, please:

1. Check the application logs in var/log/
2. Review Symfony's error pages for detailed information
3. Search for similar issues in our issue tracker
4. Report the issue with detailed reproduction steps
