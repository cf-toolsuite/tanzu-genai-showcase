# Troubleshooting Guide

This document provides guidance on troubleshooting common issues with the Movie Booking Chatbot application.

## Table of Contents

- [Application Startup Issues](#application-startup-issues)
- [Authentication and API Key Issues](#authentication-and-api-key-issues)
- [Location Detection Issues](#location-detection-issues)
- [Movie Search Problems](#movie-search-problems)
- [Theater and Showtime Issues](#theater-and-showtime-issues)
- [CrewAI and Agent Problems](#crewai-and-agent-problems)
- [Database and Persistence Issues](#database-and-persistence-issues)
- [Performance Optimization](#performance-optimization)
- [Deployment-Specific Issues](#deployment-specific-issues)
- [Logging and Monitoring](#logging-and-monitoring)

## Application Startup Issues

### Django Application Fails to Start

**Symptoms**:
- Error message: "Error loading application"
- Application crashes immediately after startup

**Solutions**:

1. **Check for syntax errors**:
   ```bash
   python -m compileall .
   ```

2. **Verify virtual environment**:
   ```bash
   pip list  # Check installed packages
   pip install -r requirements.txt  # Reinstall dependencies
   ```

3. **Check Django configuration**:
   ```bash
   python manage.py check  # Run Django system check
   python manage.py validate  # Validate models
   ```

4. **Examine error logs**:
   ```bash
   # Local development
   python manage.py runserver --traceback
   
   # Cloud Foundry
   cf logs movie-chatbot --recent
   ```

### Missing Dependencies

**Symptoms**:
- ImportError or ModuleNotFoundError
- "No module named X" errors

**Solutions**:

1. **Reinstall dependencies**:
   ```bash
   pip install -r requirements.txt
   ```

2. **Check for conflicts**:
   ```bash
   pip check
   ```

3. **For deployment issues**:
   ```bash
   # Cloud Foundry
   cf ssh movie-chatbot -c "cd app && pip list"
   ```

## Authentication and API Key Issues

### LLM API Authentication Failures

**Symptoms**:
- "Authentication error" in logs
- "Error initializing language model" message to users
- "Technical difficulties with the language model" error messages

**Solutions**:

1. **Check API key configuration**:
   ```bash
   # Local development
   echo $OPENAI_API_KEY  # Verify key is set
   
   # Cloud Foundry
   cf env movie-chatbot  # Check environment variables
   ```

2. **Verify service binding**:
   ```bash
   cf services  # List services
   cf service movie-booking-llm  # Check LLM service details
   ```

3. **Inspect code that handles credentials**:
   - Check `movie_chatbot/settings.py` for credential handling
   - Review `get_llm_config()` for proper service detection

4. **Test API key directly**:
   ```python
   import openai
   
   openai.api_key = "your_api_key_here"
   openai.base_url = "your_base_url_here"  # If using custom endpoint
   
   try:
       response = openai.chat.completions.create(
           model="gpt-4o-mini",
           messages=[{"role": "user", "content": "Hello"}]
       )
       print("API key works!")
   except Exception as e:
       print(f"API key error: {str(e)}")
   ```

### TMDb API Issues

**Symptoms**:
- No movie results
- "Error searching for movies" message

**Solutions**:

1. **Verify TMDb API key**:
   ```bash
   # Check environment variable
   echo $TMDB_API_KEY
   ```

2. **Inspect request logs**:
   ```bash
   # Enable debug logging
   export LOG_LEVEL=DEBUG
   python manage.py runserver
   ```

3. **Test TMDb API key directly**:
   ```python
   import requests
   
   api_key = "your_tmdb_api_key_here"
   url = f"https://api.themoviedb.org/3/movie/550?api_key={api_key}"
   
   response = requests.get(url)
   print(f"Status: {response.status_code}")
   print(response.json())
   ```

## Location Detection Issues

### Browser Geolocation Not Working

**Symptoms**:
- Location field remains empty
- "Please enter a US city and state" prompt
- No theaters showing in results

**Solutions**:

1. **Check browser permissions**:
   - Ensure location permissions are granted for the site
   - Check browser console for geolocation errors

2. **Test geolocation manually**:
   ```javascript
   // Run in browser console
   navigator.geolocation.getCurrentPosition(
       position => console.log("Success:", position.coords),
       error => console.error("Error:", error)
   );
   ```

3. **Verify HTTPS**:
   - Geolocation requires HTTPS in modern browsers
   - Use a secure connection or development exceptions

4. **Implement clear user instructions**:
   - Display a prompt explaining the need for location access
   - Provide visual cues for granting permissions

### ipapi.co Fallback Issues

**Symptoms**:
- Location detection fails when browser geolocation is denied
- Incorrect location displayed
- "Location not determined" errors

**Solutions**:

1. **Check network connectivity to ipapi.co**:
   ```javascript
   // Run in browser console
   fetch('https://ipapi.co/json/')
     .then(res => res.json())
     .then(data => console.log(data))
     .catch(err => console.error(err));
   ```

2. **Verify request is not rate-limited**:
   - ipapi.co has rate limits for free tier
   - Check response for rate limit messages

3. **Test alternative IP geolocation services**:
   - IPInfo.io
   - IPData.co
   - GeoJS

4. **Implement more robust fallbacks**:
   - Server-side IP detection as additional fallback
   - Default to major cities if all else fails

### Non-US Location Issues

**Symptoms**:
- Theater search doesn't work outside the US
- "Please enter a US city and state" message persists

**Explanation**:
- The application relies on US-based theater data
- SerpAPI primarily provides reliable theater data for US locations

**Solutions**:

1. **Implement clearer messaging**:
   - Explain US-only limitation to users
   - Suggest entering a US city even if outside the US

2. **Allow casual mode globally**:
   - Emphasize that Casual Viewing mode works globally
   - Provide instructions for switching modes

3. **Improve location validation**:
   - Validate US locations more accurately
   - Provide examples of valid location formats

## Movie Search Problems

### No Movies Found

**Symptoms**:
- "No movies found matching your criteria" message
- Empty search results

**Solutions**:

1. **Check search query processing**:
   - Enable debug logging to see how queries are processed
   - Review logs for the exact queries sent to TMDb

2. **Test search with simple queries**:
   - Try very simple searches like "action" or "comedy"
   - Check if specific movie titles work

3. **Verify TMDb API response**:
   - Make direct API calls to TMDb with the same query
   - Compare response with what's expected

4. **Inspect search implementation**:
   ```bash
   # Enable debug logging
   LOG_LEVEL=DEBUG
   
   # Check logs for SearchMoviesTool execution
   ```

### Incorrect Movie Recommendations

**Symptoms**:
- Movies don't match user preferences
- Irrelevant recommendations

**Solutions**:

1. **Enhance user query understanding**:
   - Improve preference extraction in AnalyzePreferencesTool
   - Add more context in the prompt to the Recommendation Agent

2. **Add preference weighting**:
   - Weight movie features differently (genre, release date, etc.)
   - Adjust scoring mechanism for better matches

3. **Implement feedback mechanism**:
   - Allow users to rate recommendations
   - Use feedback to improve future recommendations

## Theater and Showtime Issues

### No Theaters Found

**Symptoms**:
- "No theaters found showing these movies" message
- Movie recommendations appear but without theater information

**Solutions**:

1. **Check location accuracy**:
   - Verify the detected location is correct
   - Try entering a major city name manually

2. **Review theater search radius**:
   ```bash
   # Increase search radius
   cf set-env movie-chatbot THEATER_SEARCH_RADIUS_MILES 30
   cf restage movie-chatbot
   ```

3. **Test SerpAPI directly**:
   ```python
   from serpapi import GoogleSearch
   
   params = {
     "engine": "google_showtimes",
     "q": "The Matrix",
     "location": "New York, NY",
     "api_key": "your_api_key"
   }
   
   search = GoogleSearch(params)
   results = search.get_dict()
   print(results)
   ```

4. **Verify OpenStreetMap integration**:
   - Check Overpass API is accessible
   - Test theater search with explicit coordinates

### Incorrect Showtimes

**Symptoms**:
- Showtimes display at wrong times
- Missing showtimes
- "Format showtimes function" errors in logs

**Solutions**:

1. **Check timezone handling**:
   - Verify the detected timezone is correct
   - Check showtimes conversion to local time

2. **Review date selection**:
   - Test different date tabs in the UI
   - Verify showtime filtering by date

3. **Inspect raw showtime data**:
   ```python
   # Enable debug logging
   LOG_LEVEL=DEBUG
   
   # Check logs for raw showtime data vs. displayed data
   ```

## CrewAI and Agent Problems

### Agent Execution Failures

**Symptoms**:
- "Error executing crew" in logs
- "Failed to initialize one or more agents" messages
- Blank or default responses to queries

**Solutions**:

1. **Debug CrewAI execution**:
   ```python
   # Enable verbose mode for agents
   agent = Agent(
       role="Role",
       goal="Goal",
       backstory="Backstory",
       verbose=True,  # Enable verbose
       llm=llm
   )
   
   # Enable verbose mode for crew
   crew = Crew(
       agents=[agent1, agent2],
       tasks=[task1, task2],
       verbose=True  # Enable verbose
   )
   ```

2. **Test agents individually**:
   - Run each agent independently to isolate issues
   - Check task outputs separately

3. **Examine CrewAI logs**:
   ```bash
   LOG_LEVEL=DEBUG
   # Check output for CrewAI-specific logs
   ```

4. **Verify tool implementations**:
   - Test tools directly outside of agent context
   - Check for exceptions in tool execution

### Response Parsing Issues

**Symptoms**:
- "Error parsing agent response" in logs
- "JSONDecodeError" or similar parsing errors
- Malformed responses

**Solutions**:

1. **Improve robust parsing**:
   - Review JSON extraction in JsonParser class
   - Add more fallback parsing strategies

2. **Verify LLM output formats**:
   - Check that LLM responses match expected formats
   - Update prompts to enforce specific formats

3. **Test with simple queries**:
   - Use basic test queries to validate JSON parsing
   - Check for patterns in failing queries

## Database and Persistence Issues

### Session Management Issues

**Symptoms**:
- User needs to re-enter preferences frequently
- Conversation history lost between page refreshes
- "Conversation not found" errors

**Solutions**:

1. **Check session configuration**:
   - Verify session middleware is enabled
   - Check session backend configuration

2. **Examine database connections**:
   ```bash
   # Run Django checks
   python manage.py check
   
   # For Cloud Foundry
   cf ssh movie-chatbot -c "cd app && python manage.py dbshell"
   ```

3. **Review conversation persistence**:
   - Check that Conversation model is properly saved
   - Verify session ID to conversation mapping

### Database Migration Failures

**Symptoms**:
- "Migration failed" errors
- Database inconsistency errors
- "Table already exists" messages

**Solutions**:

1. **Check migration history**:
   ```bash
   python manage.py showmigrations
   ```

2. **Fake initial migrations if needed**:
   ```bash
   python manage.py migrate --fake-initial
   ```

3. **For Cloud Foundry deployments**:
   ```bash
   cf ssh movie-chatbot -c "cd app && python manage.py migrate --no-input"
   ```

## Performance Optimization

### Slow Query Responses

**Symptoms**:
- Long waiting times for movie recommendations
- Timeout errors
- UI progress bar stays at high percentage for long time

**Solutions**:

1. **Profile LLM requests**:
   - Add timing to LLM API calls
   - Consider model size vs. speed tradeoffs

2. **Implement caching for common queries**:
   ```python
   # Add caching for TMDb responses
   from django.core.cache import cache
   
   cache_key = f"tmdb_search_{query_hash}"
   cached_result = cache.get(cache_key)
   
   if cached_result:
       return cached_result
   else:
       # Make API call
       result = api_call()
       # Cache for 1 hour
       cache.set(cache_key, result, 3600)
       return result
   ```

3. **Optimize database queries**:
   - Add appropriate indexes
   - Use select_related or prefetch_related for related data

4. **Add progress feedback**:
   - Implement more granular progress updates
   - Display intermediate results when available

### Memory Usage Issues

**Symptoms**:
- "Memory limit exceeded" errors in Cloud Foundry
- Application crashes under load
- "Out of memory" errors

**Solutions**:

1. **Monitor memory usage**:
   ```bash
   # For Cloud Foundry
   cf app movie-chatbot
   ```

2. **Optimize CrewAI resource usage**:
   - Limit context sizes for LLM calls
   - Process responses in chunks

3. **Scale application appropriately**:
   ```bash
   # Increase memory allocation
   cf scale movie-chatbot -m 1G
   ```

## Deployment-Specific Issues

### Cloud Foundry Deployment Failures

**Symptoms**:
- "Failed to stage application" errors
- "Buildpack compilation error" messages
- "Start command failed" errors

**Solutions**:

1. **Check buildpack compatibility**:
   - Verify Python version compatibility
   - Check buildpack logs for errors

2. **Review manifest.yml**:
   - Ensure memory and disk settings are adequate
   - Verify environment variables are properly set

3. **Examine staging logs**:
   ```bash
   cf logs movie-chatbot --recent
   ```

4. **Verify application dependencies**:
   - Check for platform-specific dependencies
   - Use compatible package versions

### Service Binding Issues

**Symptoms**:
- "Service binding failed" errors
- "Could not find bound service" in application logs
- LLM connection errors after binding

**Solutions**:

1. **Verify service instance**:
   ```bash
   cf service movie-booking-llm
   ```

2. **Check binding details**:
   ```bash
   cf service-keys movie-booking-llm
   ```

3. **Rebind service**:
   ```bash
   cf unbind-service movie-chatbot movie-booking-llm
   cf bind-service movie-chatbot movie-booking-llm
   cf restage movie-chatbot
   ```

4. **Examine VCAP_SERVICES format**:
   ```bash
   cf env movie-chatbot
   # Check structure of VCAP_SERVICES for expected fields
   ```

## Logging and Monitoring

### Enabling Debug Logging

Production systems should use appropriate logging levels, but for troubleshooting, enable debug logging:

1. **Local development**:
   ```bash
   # In .env file
   DEBUG=True
   LOG_LEVEL=DEBUG
   ```

2. **Cloud Foundry deployment**:
   ```bash
   cf set-env movie-chatbot DEBUG True
   cf set-env movie-chatbot LOG_LEVEL DEBUG
   cf restage movie-chatbot
   ```

### Monitoring Key Metrics

For ongoing monitoring, track these key metrics:

1. **Response times**:
   - Time from query to response
   - LLM API call duration
   - Theater search duration

2. **Error rates**:
   - Failed LLM calls
   - API integration errors
   - Database errors

3. **Resource usage**:
   - Memory consumption
   - CPU utilization
   - Database connections

### Log Analysis Patterns

When examining logs, look for these patterns:

1. **Sequential agent execution**:
   - Movie Finder → Recommendation → Theater Finder
   - Check for breaks in this sequence

2. **Data transformation**:
   - JSON parsing issues
   - Data format conversions

3. **External API patterns**:
   - Rate limiting
   - Authentication failures
   - Timeout errors

4. **User interaction patterns**:
   - Repeated queries
   - Abandoned conversations
   - Error-triggering queries
