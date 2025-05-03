# Troubleshooting Guide

This document provides solutions to common issues you might encounter when working with the Flight Tracking Chatbot.

## Table of Contents

- [Application Startup Issues](#application-startup-issues)
- [API Key Issues](#api-key-issues)
- [MCP Server Issues](#mcp-server-issues)
- [Claude Desktop Integration Issues](#claude-desktop-integration-issues)
- [Cloud Foundry Deployment Issues](#cloud-foundry-deployment-issues)
- [Web UI Issues](#web-ui-issues)
- [Performance Issues](#performance-issues)
- [Common Error Messages](#common-error-messages)
- [Getting Help](#getting-help)

## Application Startup Issues

### Application Fails to Start

**Symptoms:**

- Error messages when running `./scripts/start-dev.sh` or `bundle exec rackup`
- Application crashes immediately after starting

**Possible Causes and Solutions:**

1. **Missing Dependencies**

   **Error:** `cannot load such file -- [gem_name] (LoadError)`

   **Solution:** Install the missing gem:

   ```bash
   bundle install
   ```

2. **Port Already in Use**

   **Error:** `Address already in use - bind(2) for "127.0.0.1" port 4567 (Errno::EADDRINUSE)`

   **Solution:** Either stop the process using port 4567 or use a different port:

   ```bash
   bundle exec rackup -p 4568
   ```

3. **Ruby Version Mismatch**

   **Error:** `Your Ruby version is X.X.X, but your Gemfile specified Y.Y.Y`

   **Solution:** Install the required Ruby version using the provided scripts:

   ```bash
   ./scripts/install-ruby-[your-os].sh Y.Y.Y
   ```

4. **Bundler Version Issues**

   **Error:** `Bundler version X.X.X is required >= Y.Y.Y`

   **Solution:** Update Bundler:

   ```bash
   gem install bundler -v "Y.Y.Y"
   ```

## API Key Issues

### AviationStack API Key Not Found

**Symptoms:**

- Error messages about missing API key
- Empty results when searching for flights

**Possible Causes and Solutions:**

1. **Missing .env File**

   **Solution:** Create a `.env` file based on the example:

   ```bash
   cp .env.example .env
   ```

   Then edit the file to add your API key.

2. **Invalid API Key Format**

   **Solution:** Ensure your API key is correctly formatted in the `.env` file:

   ```
   AVIATIONSTACK_API_KEY=your_api_key_here
   ```

   No quotes are needed around the API key.

3. **API Key Not Loaded**

   **Solution:** Verify that the dotenv gem is correctly loading the `.env` file. Check that `require 'dotenv/load'` is present in your application.

### AviationStack API Rate Limit Exceeded

**Symptoms:**

- Error messages about rate limits
- HTTP 429 status codes

**Solution:**

- Free tier accounts have limited requests per month
- Consider upgrading to a paid plan if you need more requests
- Implement caching to reduce API calls

## MCP Server Issues

### MCP Server Not Starting

**Symptoms:**

- Error messages when running `mcp_server.rb`
- Claude cannot connect to the MCP server

**Possible Causes and Solutions:**

1. **Fast-MCP Gem Issues**

   **Error:** `cannot load such file -- fast_mcp (LoadError)`

   **Solution:** Ensure the Fast-MCP gem is installed:

   ```bash
   bundle install
   ```

2. **Method Not Found**

   **Error:** `undefined method 'run_with_stdio' for #<FastMcp::Server:...>`

   **Solution:** Update your code to use the correct method:

   ```ruby
   # Change from
   server.run_with_stdio
   # To
   server.start
   ```

3. **Permission Issues**

   **Error:** `Permission denied -- mcp_server.rb (Errno::EACCES)`

   **Solution:** Make the script executable:

   ```bash
   chmod +x mcp_server.rb
   ```

### MCP Tools Not Registered

**Symptoms:**

- Claude reports that tools are not available
- Error messages about undefined tools

**Solution:**

- Check that all tools are properly registered in both `app.rb` and `mcp_server.rb`
- Verify that tool files are correctly loaded with `require` statements

## Claude Desktop Integration Issues

### Claude Cannot Find MCP Server

**Symptoms:**

- Claude reports that it cannot connect to the MCP server
- Error messages in Claude about missing tools

**Possible Causes and Solutions:**

1. **Incorrect Configuration**

   **Solution:** Run the setup script to configure Claude Desktop:

   ```bash
   ./scripts/setup-claude-desktop.sh
   ```

2. **Path Issues**

   **Solution:** Ensure the path to `mcp_server_wrapper.rb` in the Claude Desktop configuration is absolute, not relative:

   ```json
   {
     "mcpServers": {
       "flight-tracking-bot": {
         "command": "ruby",
         "args": [
           "/full/path/to/scripts/mcp_server_wrapper.rb"
         ]
       }
     }
   }
   ```

3. **Claude Desktop Not Restarted**

   **Solution:** Completely close and restart Claude Desktop after making configuration changes.

### Wrapper Script Issues

**Symptoms:**

- Error messages in Claude about the wrapper script
- Claude cannot start the MCP server

**Solution:**

- Ensure the wrapper script is executable
- Check that the wrapper script correctly changes to the project directory
- Verify that Bundler is properly set up in the wrapper script

## Cloud Foundry Deployment Issues

### Application Fails to Deploy

**Symptoms:**

- Error messages during `cf push`
- Application shows as "crashed" in `cf apps`

**Possible Causes and Solutions:**

1. **Missing Environment Variables**

   **Solution:** Set the required environment variables:

   ```bash
   cf set-env flight-tracking-bot AVIATIONSTACK_API_KEY your_api_key_here
   cf restage flight-tracking-bot
   ```

2. **Buildpack Issues**

   **Solution:** Specify the Ruby buildpack explicitly:

   ```bash
   cf push flight-tracking-bot -b ruby_buildpack
   ```

3. **Memory Limits**

   **Solution:** Increase the memory allocation:

   ```bash
   cf push flight-tracking-bot -m 512M
   ```

### Service Binding Issues

**Symptoms:**

- Error messages about missing services
- Application crashes after deployment

**Solution:**

- Ensure the required services exist and are properly bound:

  ```bash
  cf create-service genai standard genai-service
  cf bind-service flight-tracking-bot genai-service
  cf restage flight-tracking-bot
  ```

## Web UI Issues

### Styling Issues

**Symptoms:**

- Missing CSS styles
- Layout problems
- Unstyled elements

**Solution:**

- Check that the CSS file is being served correctly
- Verify that the path to the CSS file in the layout is correct
- Clear your browser cache

### JavaScript Errors

**Symptoms:**

- Interactive features not working
- Console errors in the browser developer tools

**Solution:**

- Check the browser console for specific error messages
- Verify that Feather Icons is loaded correctly
- Ensure JavaScript event listeners are properly set up

### Form Submission Issues

**Symptoms:**

- Search forms not returning results
- AJAX requests failing

**Solution:**

- Check the browser console for network errors
- Verify that the API endpoints are correctly defined
- Ensure form parameters match what the API expects

## Performance Issues

### Slow API Responses

**Symptoms:**

- Long loading times when searching for flights
- Timeouts when making API requests

**Possible Causes and Solutions:**

1. **External API Latency**

   **Solution:** Consider implementing caching for API responses:

   ```ruby
   # Simple in-memory cache example
   @cache = {}

   def get_with_cache(key, expiry = 300)
     if @cache[key] && @cache[key][:expires_at] > Time.now
       return @cache[key][:data]
     end

     data = yield
     @cache[key] = { data: data, expires_at: Time.now + expiry }
     data
   end
   ```

2. **Large Response Payloads**

   **Solution:** Limit the amount of data requested:

   ```ruby
   # Reduce the limit parameter
   params[:limit] = 5
   ```

3. **Inefficient Processing**

   **Solution:** Optimize data processing code, especially in MCP tools where formatting large responses.

### Memory Usage Issues

**Symptoms:**

- Application crashes with out-of-memory errors
- Slow performance over time

**Solution:**

- Monitor memory usage with tools like `top` or `htop`
- Implement garbage collection optimizations
- Consider increasing memory allocation in production

## Common Error Messages

### "AviationStack API Error: 101 - Invalid API key"

**Cause:** The API key provided is invalid or has expired.

**Solution:**

- Verify your API key at the AviationStack website
- Ensure the key is correctly set in your `.env` file or environment variables

### "AviationStack API Error: 104 - Usage Limit Reached"

**Cause:** You've exceeded your monthly API request quota.

**Solution:**

- Implement caching to reduce API calls
- Consider upgrading to a paid plan
- Wait until your quota resets (typically at the start of the month)

### "No flights found matching your criteria"

**Cause:** The search parameters didn't match any flights in the database.

**Solution:**

- Try broader search criteria
- Check that airport codes are valid
- For future flights, ensure the date is within the allowed range

### "Cannot connect to MCP server"

**Cause:** Claude Desktop cannot establish a connection to the MCP server.

**Solution:**

- Ensure the MCP server is running
- Check the Claude Desktop configuration
- Verify that the wrapper script is working correctly

## Getting Help

If you're still experiencing issues after trying the solutions in this guide:

1. **Check the Logs**

   Application logs can provide valuable information about errors:

   ```bash
   # For local development
   tail -f logs/development.log

   # For Cloud Foundry deployments
   cf logs flight-tracking-bot --recent
   ```

2. **Review Documentation**

   Check other documentation files for specific information:
   - [README.md](../README.md) - General overview
   - [DEVELOPMENT.md](DEVELOPMENT.md) - Development setup
   - [DEPLOYMENT.md](DEPLOYMENT.md) - Deployment instructions
   - [API.md](API.md) - API documentation

3. **Open an Issue**

   If you believe you've found a bug or have a feature request, open an issue on the GitHub repository with:
   - A clear description of the problem
   - Steps to reproduce the issue
   - Expected vs. actual behavior
   - Environment details (OS, Ruby version, etc.)

4. **Community Resources**

   - [Ruby Community](https://www.ruby-lang.org/en/community/) - General Ruby help
   - [Sinatra Documentation](https://sinatrarb.com/documentation.html) - Sinatra framework help
   - [Cloud Foundry Documentation](https://docs.cloudfoundry.org/) - Cloud Foundry deployment help
