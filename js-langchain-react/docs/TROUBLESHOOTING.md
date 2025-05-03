# Troubleshooting Guide

This document provides solutions for common issues you might encounter when developing, deploying, or using the News Aggregator application.

## Table of Contents

- [Frontend Issues](#frontend-issues)
- [Backend Issues](#backend-issues)
- [API Integration Issues](#api-integration-issues)
- [Deployment Issues](#deployment-issues)
- [Performance Issues](#performance-issues)
- [Environment Setup Issues](#environment-setup-issues)
- [Getting Help](#getting-help)

## Frontend Issues

### Articles Not Loading

**Symptoms:**

- Search button works, but no articles appear
- No error messages in the UI
- Loading indicator disappears without showing results

**Possible Causes and Solutions:**

1. **Backend Connection Issue**
   - Check browser console for network errors
   - Verify the API base URL is correct in `newsService.js`
   - Ensure the backend server is running

   ```javascript
   // Check this configuration in src/services/newsService.js
   const API_BASE_URL = isLocalDevelopment()
     ? (process.env.REACT_APP_API_BASE_URL || 'http://localhost:3001')
     : '';
   ```

2. **CORS Issues**
   - Look for CORS errors in browser console
   - Verify the backend has CORS properly configured

   ```javascript
   // Ensure this is properly set up in server.js
   app.use(cors());
   ```

3. **Empty Response from API**
   - Try a different search term
   - Check if the News API is returning results for that topic
   - Verify the News API key is valid and has not reached its request limit

### Error Messages Appearing

**Symptoms:**

- "Failed to fetch news" error message
- Other error messages in the UI

**Possible Causes and Solutions:**

1. **Backend Server Not Running**
   - Start the backend server with `npm run server`
   - Check terminal for any errors in the backend server

2. **API Key Issues**
   - Verify your News API key in the `.env` file
   - Check if you've reached the API request limit

3. **Network Issues**
   - Check your internet connection
   - Try disabling VPNs or proxies that might interfere

### UI Rendering Issues

**Symptoms:**

- Components not displaying correctly
- Styling issues
- Layout problems on certain screen sizes

**Possible Causes and Solutions:**

1. **CSS Issues**
   - Check browser console for CSS errors
   - Verify that all CSS files are being loaded
   - Test on different browsers to isolate browser-specific issues

2. **Responsive Design Issues**
   - Use browser developer tools to test different screen sizes
   - Check media queries in CSS files
   - Ensure viewport meta tag is properly set in `public/index.html`

3. **React Rendering Issues**
   - Check for React errors in the console
   - Verify component props are being passed correctly
   - Check for missing keys in list rendering

## Backend Issues

### Server Won't Start

**Symptoms:**
- Error messages when running `npm run server`
- Server crashes immediately after starting

**Possible Causes and Solutions:**

1. **Port Already in Use**
   - Error message: `EADDRINUSE: address already in use :::3001`

   **Solution 1: Use the start-app.sh script (Recommended)**
   ```bash
   # This script will automatically handle port conflicts
   ./start-app.sh
   ```

   The script will:
   - Detect if port 3001 is in use
   - Ask if you want to kill the process using that port
   - If you choose not to kill the process, it will:
     - Find an available port
     - Update the .env file with the correct API base URL
     - Set the PORT environment variable for the server
     - Start the application with the new configuration

   This ensures that the frontend and backend are always configured to use the same port.

   **Solution 2: Manually free the port**
   ```bash
   # On Linux/Mac
   lsof -i :3001
   kill -9 <PID>

   # On Windows
   netstat -ano | findstr :3001
   taskkill /PID <PID> /F
   ```

2. **Missing Dependencies**
   - Error messages about missing modules
   - Run `npm install` to install dependencies
   - If specific packages are mentioned, install them:
     ```bash
     npm install <package-name>
     ```

3. **Environment Variables Not Set**
   - Error messages about missing API keys
   - Check that you've created a `.env` file with required variables
   - Verify the variables match those expected in the code

### API Endpoint Errors

**Symptoms:**
- 500 errors when calling the `/api/news` endpoint
- Error messages in the server console

**Possible Causes and Solutions:**

1. **News API Issues**
   - Check if the News API key is valid
   - Verify the News API is operational by testing directly:
     ```bash
     curl -H "X-Api-Key: your_api_key" "https://newsapi.org/v2/everything?q=test&pageSize=1"
     ```
   - Check if you've reached the API request limit

2. **LLM Service Issues**
   - Verify the LLM API key is valid
   - Check if the LLM service is operational
   - Look for specific error messages related to the LLM service

3. **Code Errors**
   - Check the server logs for stack traces
   - Debug the specific function causing the error
   - Verify request parameters are being processed correctly

### Memory Issues

**Symptoms:**
- Server crashes with "JavaScript heap out of memory" error
- High CPU usage
- Slow response times

**Possible Causes and Solutions:**

1. **Memory Leaks**
   - Check for unresolved promises or unclosed connections
   - Look for large objects being stored in memory
   - Consider implementing garbage collection hints

2. **Insufficient Memory Allocation**
   - Increase Node.js memory limit:
     ```bash
     export NODE_OPTIONS="--max-old-space-size=4096"
     ```
   - Or add it to your npm script in package.json:
     ```json
     "scripts": {
       "server": "cross-env NODE_OPTIONS='--max-old-space-size=4096' node server.js"
     }
     ```

3. **Too Many Concurrent Requests**
   - Implement request queuing
   - Add rate limiting to prevent overload
   - Consider scaling horizontally with multiple instances

## API Integration Issues

### News API Problems

**Symptoms:**
- No articles returned from searches
- Error messages about the News API

**Possible Causes and Solutions:**

1. **API Key Issues**
   - Verify your News API key is correct
   - Check if your key has the necessary permissions
   - Ensure the key is properly set in the environment variables

2. **Request Formatting**
   - Check that the request URL is correctly formatted
   - Verify query parameters are properly encoded
   - Test the API directly to confirm it works:
     ```bash
     curl -H "X-Api-Key: your_api_key" "https://newsapi.org/v2/everything?q=test&pageSize=1"
     ```

3. **Rate Limiting**
   - Check if you've hit the News API rate limits
   - Implement caching to reduce API calls
   - Consider upgrading to a higher tier if needed

### LLM Service Issues

**Symptoms:**
- Articles load but without summaries
- Error messages about the LLM service
- Fallback to article descriptions instead of summaries

**Possible Causes and Solutions:**

1. **API Key Issues**
   - Verify your LLM API key is correct
   - Check if your key has the necessary permissions
   - Ensure the key is properly set in the environment variables

2. **Service Configuration**
   - Check that the LLM service is properly configured
   - Verify the model name is correct
   - Ensure the base URL is set correctly if using a custom endpoint

3. **Prompt Engineering**
   - Review the system and human messages sent to the LLM
   - Adjust the prompts to improve summary quality
   - Test different prompt formats to find what works best

## Deployment Issues

### Cloud Foundry Deployment Failures

**Symptoms:**
- `cf push` command fails
- Application crashes after deployment
- Application starts but features don't work

**Possible Causes and Solutions:**

1. **Manifest Issues**
   - Check your `manifest.yml` file for errors
   - Verify memory allocation is sufficient
   - Ensure all required environment variables are set

2. **Buildpack Problems**
   - Specify the correct buildpack version
   - Check buildpack compatibility with your Node.js version
   - Look for buildpack-specific error messages

3. **Service Binding Issues**
   - Verify the GenAI service is properly created and bound
   - Check service instance status with `cf service <service-name>`
   - Rebind the service if necessary:
     ```bash
     cf unbind-service news-aggregator news-aggregator-llm
     cf bind-service news-aggregator news-aggregator-llm
     cf restart news-aggregator
     ```

### Environment Variable Issues

**Symptoms:**
- Application starts but features don't work
- Error messages about missing configuration
- Unexpected behavior compared to local environment

**Possible Causes and Solutions:**

1. **Missing Variables**
   - Check that all required environment variables are set
   - Verify variable names match what the code expects
   - Use `cf env <app-name>` to view current environment variables

2. **Variable Format Issues**
   - Ensure API keys don't have extra spaces or quotes
   - Check for special characters that might need escaping
   - Verify JSON-formatted variables are valid JSON

3. **Service Credentials**
   - Verify VCAP_SERVICES contains the expected credentials
   - Check that the credential extraction logic works correctly
   - Test with hardcoded values temporarily to isolate the issue

## Performance Issues

### Slow Response Times

**Symptoms:**
- Searches take a long time to complete
- UI feels sluggish
- Timeouts or performance warnings in the console

**Possible Causes and Solutions:**

1. **LLM Processing Delays**
   - Implement caching for common searches
   - Consider reducing the number of articles processed
   - Optimize the prompts sent to the LLM

2. **Network Latency**
   - Check network performance in the browser dev tools
   - Minimize the size of API responses
   - Consider implementing compression

3. **Client-Side Performance**
   - Optimize React rendering (use React.memo, useMemo, etc.)
   - Reduce unnecessary re-renders
   - Implement virtualization for long lists

### Memory Usage

**Symptoms:**
- Browser tab using excessive memory
- Application slows down over time
- Browser warnings about high memory usage

**Possible Causes and Solutions:**

1. **Memory Leaks**
   - Check for event listeners that aren't being cleaned up
   - Verify useEffect cleanup functions are implemented
   - Use React DevTools to profile component renders

2. **Large Responses**
   - Limit the number of articles returned
   - Implement pagination
   - Consider lazy loading images

3. **Inefficient Rendering**
   - Avoid rendering large lists all at once
   - Implement windowing/virtualization for long lists
   - Use React.memo to prevent unnecessary re-renders

## Environment Setup Issues

### Missing Build Directory

**Symptoms:**
- Error: `ENOENT: no such file or directory, stat '.../build/index.html'`
- Server starts but website doesn't load
- 404 errors when accessing the application

**Possible Causes and Solutions:**

1. **React App Not Built**
   - The Express server is configured to serve the React app from the `build` directory
   - This directory is created when you run `npm run build`
   - If you haven't built the app, the server can't find the files to serve

   **Solution 1: Build the React app**
   ```bash
   npm run build
   ```
   Then start the server again.

   **Solution 2: Use the start-app.sh script**
   ```bash
   ./start-app.sh
   ```
   The updated script automatically checks if the build directory exists and runs the build command if needed.

2. **Incorrect Working Directory**
   - Make sure you're running the commands from the project root directory
   - Check that the build directory is in the expected location

3. **Build Process Failed**
   - Check for errors during the build process
   - Verify that all required dependencies are installed
   - Look for syntax errors or other issues in the React code

### Node.js Version Conflicts

**Symptoms:**
- Unexpected errors when running npm scripts
- Warnings about unsupported Node.js versions
- Dependencies failing to install

**Possible Causes and Solutions:**

1. **Outdated Node.js**
   - Check your Node.js version: `node --version`
   - Update to the required version (18.0.0+)
   - Consider using nvm to manage multiple Node.js versions:
     ```bash
     nvm install 18
     nvm use 18
     ```

2. **Package Compatibility**
   - Check for warnings about peer dependencies
   - Update packages to versions compatible with your Node.js version
   - Use the `overrides` field in package.json for problematic dependencies

### File Watcher Limits (Linux)

**Symptoms:**
- Error: `ENOSPC: System limit for number of file watchers reached`
- React development server crashes
- Changes not being detected during development

**Solution:**

Run the included script to increase the file watcher limit:

```bash
./fix-watchers.sh
```

Or manually increase the limit:

```bash
echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

### Dependency Installation Issues

**Symptoms:**
- npm install fails with errors
- Missing dependencies errors when starting the application
- Warnings about conflicting dependencies

**Possible Causes and Solutions:**

1. **Package Lock Conflicts**
   - Delete package-lock.json and node_modules, then reinstall:
     ```bash
     rm -rf node_modules package-lock.json
     npm install
     ```

2. **Peer Dependency Issues**
   - Check for peer dependency warnings
   - Use the `--legacy-peer-deps` flag if necessary:
     ```bash
     npm install --legacy-peer-deps
     ```

3. **Node Version Issues**
   - Ensure you're using the correct Node.js version
   - Check the "engines" field in package.json

### Dependency Update Issues

**Symptoms:**
- Warnings about deprecated packages
- Security vulnerabilities in dependencies
- Compatibility issues after updates

**Solution:**

Use the provided update-dependencies.sh script:

```bash
./update-dependencies.sh
```

This script will:
- Configure npm to use secure TLS
- Remove existing node_modules and package-lock.json
- Install the updated dependencies
- Run npm audit fix to address any remaining issues

## Getting Help

If you've tried the solutions above and are still experiencing issues:

1. **Check Logs**
   - Review browser console logs for frontend issues
   - Check server logs for backend issues
   - Use `cf logs news-aggregator --recent` for Cloud Foundry logs

2. **Search for Known Issues**
   - Check the project's GitHub issues
   - Search for similar problems in Stack Overflow
   - Look for relevant discussions in React and Express forums

3. **Create Detailed Reports**
   - Include the exact error message
   - Describe the steps to reproduce the issue
   - Specify your environment (OS, Node.js version, browser, etc.)
   - Include relevant code snippets or screenshots
