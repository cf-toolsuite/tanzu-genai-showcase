# Troubleshooting Guide

This document provides solutions for common issues that may arise when working with the Congress.gov API Chatbot application.

## Table of Contents

- [Application Startup Issues](#application-startup-issues)
- [API Connection Issues](#api-connection-issues)
- [LLM Integration Issues](#llm-integration-issues)
- [Deployment Issues](#deployment-issues)
- [Performance Issues](#performance-issues)
- [Common Error Messages](#common-error-messages)
- [Logging and Debugging](#logging-and-debugging)

## Application Startup Issues

### Application Fails to Start

**Symptoms:**
- Error message: `Failed to load configuration`
- Application exits immediately after starting

**Possible Causes and Solutions:**

1. **Missing Environment Variables**

   The application requires several environment variables to be set. Check if all required environment variables are set:

   ```bash
   # Required environment variables
   CONGRESS_API_KEY=your_congress_api_key
   GENAI_API_KEY=your_GENAI_API_KEY
   GENAI_API_BASE_URL=your_GENAI_API_BASE_URL
   LLM=gpt-4o-mini
   ```

   Solution: Create a `.env` file in the project root with the required environment variables or set them directly in your environment.

2. **Invalid API Keys**

   The API keys provided may be invalid or expired.

   Solution: Verify that your Congress.gov API key and GenAI API key are valid and active.

3. **Port Already in Use**

   The default port (8080) may already be in use by another application.

   Solution: Set a different port using the `PORT` environment variable:

   ```bash
   PORT=8081 go run cmd/server/main.go
   ```

### Public Directory Not Found

**Symptoms:**
- Error message: `Failed to create public directory`
- Application exits after starting

**Possible Causes and Solutions:**

1. **Permissions Issue**

   The application may not have permission to create the public directory.

   Solution: Check the permissions of the directory where the application is running and ensure the user running the application has write permissions.

2. **Disk Space Issue**

   The disk may be full, preventing the creation of new files and directories.

   Solution: Free up disk space or run the application in a location with sufficient disk space.

## API Connection Issues

### Congress.gov API Connection Failures

**Symptoms:**
- Error message: `API request failed with status code: 401`
- Error message: `API request failed with status code: 403`
- Error message: `API request failed with status code: 429`

**Possible Causes and Solutions:**

1. **Invalid API Key**

   The Congress.gov API key may be invalid or expired.

   Solution: Verify that your Congress.gov API key is valid and active. You can get a new API key at https://api.congress.gov/sign-up/.

2. **Rate Limiting**

   The Congress.gov API has rate limits that may be exceeded.

   Solution: Implement exponential backoff and retry logic for API calls. The application already includes a caching mechanism to reduce the number of API calls.

3. **Network Issues**

   The application may not be able to connect to the Congress.gov API due to network issues.

   Solution: Check your network connection and ensure that the application can reach the Congress.gov API. You can test this with a simple curl command:

   ```bash
   curl -H "X-API-Key: your_congress_api_key" https://api.congress.gov/v3/bill
   ```

### GenAI LLM Service Connection Failures

**Symptoms:**
- Error message: `Failed to create LLM client`
- Error message: `Failed to generate response: context deadline exceeded`

**Possible Causes and Solutions:**

1. **Invalid API Key or URL**

   The GenAI API key or URL may be invalid or expired.

   Solution: Verify that your GenAI API key and URL are valid and active.

2. **Rate Limiting or Quota Exceeded**

   The GenAI LLM service may have rate limits or quotas that are exceeded.

   Solution: Check your usage and quotas for the GenAI LLM service. Consider upgrading your plan if necessary.

3. **Network Issues**

   The application may not be able to connect to the GenAI LLM service due to network issues.

   Solution: Check your network connection and ensure that the application can reach the GenAI LLM service.

## LLM Integration Issues

### LLM Generates Incorrect or Irrelevant Responses

**Symptoms:**
- LLM responses are not relevant to the user's query
- LLM responses contain incorrect information
- LLM responses are too generic or vague

**Possible Causes and Solutions:**

1. **Insufficient Context**

   The LLM may not have enough context to generate a relevant response.

   Solution: Ensure that the system prompt provides sufficient context about the application's purpose and capabilities. You can modify the system prompt in the `Initialize` method in `internal/service/chatbot.go`.

2. **Temperature Setting Too High**

   The temperature parameter controls the randomness of the LLM's responses. A higher temperature results in more creative but potentially less accurate responses.

   Solution: Lower the temperature parameter in the LLM client. The application currently uses a temperature of 0.3, which is a good balance between creativity and accuracy.

3. **Incorrect API Planning**

   The LLM may not correctly determine which Congress.gov API to call based on the user's query.

   Solution: Improve the API planning prompt in the `ProcessUserQuery` method in `internal/service/chatbot.go` to provide more guidance to the LLM.

### LLM Responses Are Slow

**Symptoms:**
- LLM responses take a long time to generate
- Application appears to hang when processing user queries

**Possible Causes and Solutions:**

1. **Large Context Window**

   The conversation history may be too large, resulting in a large context window for the LLM.

   Solution: Implement a mechanism to truncate the conversation history when it becomes too large. You can modify the `GetConversationHistory` method in `internal/service/chatbot.go` to limit the number of messages returned.

2. **Complex Queries**

   Some queries may require multiple API calls and complex processing.

   Solution: Implement a timeout for LLM calls and provide feedback to the user when a query is taking a long time to process. The application already includes a timeout for LLM calls in the `HandleChat` method in `internal/handler/handler.go`.

3. **Network Latency**

   Network latency may contribute to slow LLM responses.

   Solution: Consider deploying the application closer to the GenAI LLM service to reduce network latency.

## Deployment Issues

### Cloud Foundry Deployment Failures

**Symptoms:**
- Error message: `Failed to push application`
- Error message: `Failed to bind service`
- Error message: `Failed to start application`

**Possible Causes and Solutions:**

1. **Missing Environment Variables**

   The application requires several environment variables to be set.

   Solution: Set the required environment variables using the `cf set-env` command:

   ```bash
   cf set-env congress-chatbot CONGRESS_API_KEY your_congress_api_key
   ```

2. **Service Binding Issues**

   The application may fail to bind to the GenAI LLM service.

   Solution: Check if the service exists and is available:

   ```bash
   cf services
   ```

   If the service doesn't exist, create it:

   ```bash
   cf create-service genai PLAN_NAME congress-llm
   ```

   Then bind the service to the application:

   ```bash
   cf bind-service congress-chatbot congress-llm
   ```

3. **Memory or Disk Space Issues**

   The application may require more memory or disk space than allocated.

   Solution: Increase the memory or disk space allocation in the `manifest.yml` file:

   ```yaml
   applications:
   - name: congress-chatbot
     memory: 512M
     disk_quota: 512M
   ```

   Or use the `cf scale` command:

   ```bash
   cf scale congress-chatbot -m 512M -k 512M
   ```

## Performance Issues

### High Memory Usage

**Symptoms:**
- Application uses a lot of memory
- Application crashes with out-of-memory errors

**Possible Causes and Solutions:**

1. **Large Conversation History**

   The conversation history may be stored in memory and grow too large.

   Solution: Implement a mechanism to truncate the conversation history when it becomes too large. You can modify the `GetConversationHistory` method in `internal/service/chatbot.go` to limit the number of messages returned.

2. **Memory Leaks**

   The application may have memory leaks.

   Solution: Use a memory profiler to identify and fix memory leaks. You can use the Go built-in memory profiler:

   ```bash
   go tool pprof -http=:8081 http://localhost:8080/debug/pprof/heap
   ```

   Note: You'll need to add the pprof middleware to the Fiber app in `cmd/server/main.go`:

   ```go
   import "github.com/gofiber/fiber/v2/middleware/pprof"

   // ...

   app.Use(pprof.New())
   ```

### Slow Response Times

**Symptoms:**
- Application takes a long time to respond to user queries
- Application appears to hang when processing user queries

**Possible Causes and Solutions:**

1. **Inefficient API Calls**

   The application may make too many API calls or inefficient API calls.

   Solution: Implement caching for API responses to reduce the number of API calls. The application already includes a caching mechanism in the `CongressClient` struct in `api/congress_client.go`.

2. **Inefficient LLM Usage**

   The application may use the LLM inefficiently.

   Solution: Optimize the prompts and context provided to the LLM to reduce the amount of processing required. You can modify the system prompt in the `Initialize` method in `internal/service/chatbot.go`.

3. **Insufficient Resources**

   The application may not have enough resources (CPU, memory) to handle the load.

   Solution: Increase the resources allocated to the application or scale the application horizontally by adding more instances.

## Common Error Messages

### "Failed to load configuration"

**Cause:** The application failed to load the configuration from environment variables or service bindings.

**Solution:** Check if all required environment variables are set and valid. See [Application Startup Issues](#application-startup-issues) for more details.

### "API request failed with status code: XXX"

**Cause:** The application failed to make a request to the Congress.gov API.

**Solution:** Check if the Congress.gov API key is valid and active. See [API Connection Issues](#api-connection-issues) for more details.

### "Failed to create LLM client"

**Cause:** The application failed to create the LLM client.

**Solution:** Check if the GenAI API key and URL are valid and active. See [LLM Integration Issues](#llm-integration-issues) for more details.

### "Failed to generate response: context deadline exceeded"

**Cause:** The LLM took too long to generate a response and the context deadline was exceeded.

**Solution:** Increase the timeout for LLM calls or optimize the prompts and context provided to the LLM. See [LLM Integration Issues](#llm-integration-issues) for more details.

## Logging and Debugging

### Viewing Logs

The application logs to both the console and a log file (`logs/http.log`). You can view the logs to diagnose issues:

```bash
# View the log file
cat logs/http.log

# Follow the log file in real-time
tail -f logs/http.log

# Filter the log file for errors
grep ERROR logs/http.log
```

When deployed to Cloud Foundry, you can view the logs using the `cf logs` command:

```bash
# View recent logs
cf logs congress-chatbot --recent

# Stream logs in real-time
cf logs congress-chatbot
```

### Enabling Debug Logging

To enable more detailed logging, you can set the `DEBUG` environment variable:

```bash
DEBUG=true go run cmd/server/main.go
```

This will enable more verbose logging, including detailed information about API calls and LLM interactions.

### Using the Health Check Endpoint

The application provides a health check endpoint at `/api/health` that can be used to verify that the application is running correctly:

```bash
curl http://localhost:8080/api/health
```

The response should be:

```json
{
  "status": "ok",
  "time": "2025-04-29T16:57:00Z"
}
```

If the application is not running correctly, the health check endpoint may return an error or be unavailable.

### Debugging API Calls

To debug API calls, you can use the `curl` command to make requests directly to the Congress.gov API:

```bash
curl -H "X-API-Key: your_congress_api_key" https://api.congress.gov/v3/bill
```

This can help determine if the issue is with the application or the API.

### Debugging LLM Calls

To debug LLM calls, you can use the `curl` command to make requests directly to the GenAI LLM service:

```bash
curl -X POST \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer your_GENAI_API_KEY" \
  -d '{
    "model": "gpt-4o-mini",
    "messages": [
      {"role": "system", "content": "You are a helpful assistant."},
      {"role": "user", "content": "Hello, world!"}
    ]
  }' \
  your_GENAI_API_BASE_URL/chat/completions
```

This can help determine if the issue is with the application or the LLM service.
