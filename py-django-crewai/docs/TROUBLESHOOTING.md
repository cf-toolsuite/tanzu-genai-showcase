# Troubleshooting Guide

This document provides guidance on troubleshooting common issues with the Movie Chatbot application.

## Table of Contents

- [Application Startup Issues](#application-startup-issues)
- [Authentication and API Key Issues](#authentication-and-api-key-issues)
- [Location Detection Issues](#location-detection-issues)
- [Movie Search Problems](#movie-search-problems)
- [Theater and Showtime Issues](#theater-and-showtime-issues)
- [React Frontend Issues](#react-frontend-issues)
- [CrewAI and Agent Problems](#crewai-and-agent-problems)
- [Database and Persistence Issues](#database-and-persistence-issues)
- [Performance Optimization](#performance-optimization)
- [Deployment-Specific Issues](#deployment-specific-issues)
- [Logging and Monitoring](#logging-and-monitoring)
- [First Run Mode Issues](#first-run-mode-issues)

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

### React Frontend Fails to Start

**Symptoms**:

- Webpack dev server fails to start
- "Module not found" errors
- Blank page in browser

**Solutions**:

1. **Check Node.js and npm versions**:

   ```bash
   node -v  # Should be 18+
   npm -v   # Should be compatible with Node version
   ```

2. **Reinstall dependencies**:

   ```bash
   cd frontend
   rm -rf node_modules package-lock.json
   npm install
   ```

3. **Check for syntax errors in React code**:

   ```bash
   cd frontend
   npm run lint
   ```

4. **Examine webpack configuration**:

   ```bash
   # Check webpack.config.js for errors
   # Verify proxy settings for API requests
   ```

5. **Check browser console for errors**:
   - Open browser developer tools (F12)
   - Look for errors in the Console tab

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
   cf service movie-chatbot-llm  # Check LLM service details
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

### Configuration Loading Issues

**Symptoms**:

- "1 validation error for EnhanceMovieImagesTool" in logs
- "tmdb_api_key: Input should be a valid string [type=string_type, input_value=None, input_type=NoneType]" error
- Movie image enhancement not working

**Solutions**:

1. **Check configuration loading**:

   - Verify that API keys are loaded using the proper configuration loader functions
   - Check that `external_apis.py` uses `config_loader.get_config()` or `config_loader.get_required_config()`

   ```python
   # Correct implementation in movie_chatbot/settings/external_apis.py
   from . import config_loader

   # The Movie Database API Key (for movie data)
   TMDB_API_KEY = config_loader.get_required_config('TMDB_API_KEY')
   ```

2. **Verify configuration sources**:

   - Check that the API key is present in one of the configuration sources:
     1. Service bindings (highest priority)
     2. Environment variables
     3. config.json file

   ```bash
   # Check environment variable
   echo $TMDB_API_KEY

   # Check config.json
   cat config.json | grep TMDB_API_KEY

   # Check service bindings in Cloud Foundry
   cf env movie-chatbot
   ```

3. **Run configuration validation**:

   - The application includes a validation function that checks for required configuration values
   - Check the logs for validation results

   ```bash
   # Enable debug logging to see validation results
   LOG_LEVEL=DEBUG
   python manage.py runserver
   ```

4. **Create a user-provided service with the API key**:

   ```bash
   # For Cloud Foundry deployments
   cf create-user-provided-service movie-chatbot-config -p '{"TMDB_API_KEY":"your-tmdb-api-key"}'
   cf bind-service movie-chatbot movie-chatbot-config
   cf restage movie-chatbot
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

5. **Check React location hook**:
   - Verify the `useLocation` hook is working correctly
   - Check for errors in the hook implementation

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

5. **Check React state updates**:
   - Verify that movie data is being properly stored in React context
   - Check that components are re-rendering when data changes

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

4. **Check React component rendering**:
   - Verify that MovieSection component is receiving correct data
   - Check that movie cards are displaying the right information

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
     "q": "The Matrix theater",
     "location": "New York, NY, United States",
     "hl": "en",
      "gl": "us",
     "api_key": "your_api_key"
   }

   search = GoogleSearch(params)
   results = search.get_dict()
   print(results)
   ```

4. **Verify OpenStreetMap integration**:
   - Check Overpass API is accessible
   - Test theater search with explicit coordinates

5. **Check React theater components**:
   - Verify TheaterSection component is receiving data
   - Check that theater data is being passed correctly to child components

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

4. **Check React showtime formatting**:
   - Verify ShowtimeDisplay component is formatting times correctly
   - Check that date filtering is working properly

## React Frontend Issues

### Component Rendering Problems

**Symptoms**:
- Blank or incomplete UI
- Components not updating when data changes
- React error messages in console

**Solutions**:

1. **Check React component hierarchy**:
   - Verify parent-child relationships
   - Ensure props are passed correctly

2. **Inspect React state**:
   - Use React DevTools to examine component state
   - Verify context values are correct

3. **Check for React key errors**:
   - Ensure list items have unique keys
   - Fix any "key" warnings in console

4. **Test component isolation**:
   - Render components in isolation to identify issues
   - Use React Testing Library for component testing

5. **Check for React hooks errors**:
   - Verify hooks are used according to rules (only at top level)
   - Check for dependency array issues in useEffect

### Context API Issues

**Symptoms**:
- Components not receiving updated state
- "Cannot read property of undefined" errors
- Unexpected component behavior

**Solutions**:

1. **Verify context provider wrapping**:
   - Ensure AppProvider wraps all components that need context
   - Check for nested providers that might shadow values

2. **Debug context values**:
   ```jsx
   // Add debugging in component
   const context = useAppContext();
   console.log('Context values:', context);
   ```

3. **Check context updates**:
   - Verify state update functions are called correctly
   - Ensure state updates trigger re-renders

4. **Test with simplified context**:
   - Create a minimal reproduction of the issue
   - Isolate the specific context value causing problems

### API Integration Issues

**Symptoms**:
- Network errors in console
- "Failed to fetch" errors
- Empty data despite successful API calls

**Solutions**:

1. **Check API service implementation**:
   - Verify endpoint URLs are correct
   - Ensure proper error handling

2. **Inspect network requests**:
   - Use browser Network tab to examine requests
   - Check request/response format

3. **Test CSRF token handling**:
   - Verify CSRF token is included in requests
   - Check for CSRF validation errors

4. **Implement better error feedback**:
   - Add user-friendly error messages
   - Provide retry options for failed requests

### Lazy Loading Issues

**Symptoms**:
- Components fail to load
- "Loading chunk failed" errors
- Blank areas where components should be

**Solutions**:

1. **Check Suspense implementation**:
   - Verify Suspense components wrap lazy-loaded components
   - Ensure fallback UI is provided

2. **Debug chunk loading**:
   - Check network tab for chunk loading errors
   - Verify webpack configuration

3. **Implement error boundaries**:
   ```jsx
   class ErrorBoundary extends React.Component {
     state = { hasError: false };

     static getDerivedStateFromError(error) {
       return { hasError: true };
     }

     render() {
       if (this.state.hasError) {
         return <div>Something went wrong. Please try again.</div>;
       }
       return this.props.children;
     }
   }

   // Usage
   <ErrorBoundary>
     <Suspense fallback={<LoadingFallback />}>
       <LazyComponent />
     </Suspense>
   </ErrorBoundary>
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

5. **Check CrewAI version compatibility**:
   - Verify compatibility with LangChain version
   - Check for breaking changes in CrewAI 0.114.0

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

4. **Implement more resilient parsing**:
   ```python
   def parse_json_output(output_str: str) -> Any:
       """Parse JSON output with multiple fallback strategies."""
       # Try direct JSON parsing
       try:
           return json.loads(output_str)
       except json.JSONDecodeError:
           pass

       # Try to extract JSON from markdown code blocks
       json_match = re.search(r'```(?:json)?\s*([\s\S]*?)\s*```', output_str)
       if json_match:
           try:
               return json.loads(json_match.group(1))
           except json.JSONDecodeError:
               pass

       # Try to find JSON array/object patterns
       json_pattern = re.search(r'(\[.*\]|\{.*\})', output_str, re.DOTALL)
       if json_pattern:
           try:
               return json.loads(json_pattern.group(1))
           except json.JSONDecodeError:
               pass

       # Return empty list as fallback
       return []
   ```

### Tool Registration Issues

**Symptoms**:
- "KeyError: 'tool_name'" in logs
- "Tool not found" errors
- Agent fails to use tools

**Solutions**:

1. **Check tool registration**:
   - Verify tool names are consistent
   - Ensure tools are properly registered with agents

2. **Implement tool compatibility patch**:
   ```python
   def _ensure_tool_compatibility(tools: List[Any]) -> None:
       """Ensure tools have all necessary attributes for CrewAI compatibility."""
       for tool in tools:
           # Make sure the tool has a name attribute
           if not hasattr(tool, 'name'):
               tool_class_name = tool.__class__.__name__
               derived_name = tool_class_name.lower().replace('tool', '_tool')
               setattr(tool, 'name', derived_name)

           # Pre-register tool with CrewAI's event tracking system
           from crewai.utilities.events.utils.console_formatter import ConsoleFormatter
           if hasattr(ConsoleFormatter, 'tool_usage_counts') and tool.name not in ConsoleFormatter.tool_usage_counts:
               ConsoleFormatter.tool_usage_counts[tool.name] = 0
   ```

3. **Update tool implementation**:
   - Ensure tools follow CrewAI 0.114.0 specifications
   - Check for deprecated tool methods

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

4. **Implement local storage backup**:
   ```javascript
   // In React context
   useEffect(() => {
     // Save conversation state to localStorage
     localStorage.setItem('firstRunMessages', JSON.stringify(firstRunMessages));
   }, [firstRunMessages]);

   // On initialization
   useEffect(() => {
     // Restore from localStorage if available
     const savedMessages = localStorage.getItem('firstRunMessages');
     if (savedMessages) {
       setFirstRunMessages(JSON.parse(savedMessages));
     }
   }, []);
   ```

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

1. **Adjust API request configuration**:

   ```bash
   # Increase API timeout and optimize retry settings
   API_REQUEST_TIMEOUT_SECONDS=180  # Increased from default 60 seconds
   API_MAX_RETRIES=10               # Reasonable number of retries
   API_RETRY_BACKOFF_FACTOR=1.3     # Gentler backoff factor

   # Optimize SerpAPI request settings
   SERPAPI_REQUEST_BASE_DELAY=5.0   # Reduced delay between requests
   SERPAPI_PER_MOVIE_DELAY=2.0      # Reduced delay per movie
   SERPAPI_MAX_RETRIES=2            # Fewer retries for theater searches
   SERPAPI_BASE_RETRY_DELAY=3.0     # Shorter base delay
   SERPAPI_RETRY_MULTIPLIER=1.5     # Gentler multiplier
   ```

2. **Profile LLM requests**:

   - Add timing to LLM API calls
   - Consider model size vs. speed tradeoffs

3. **Implement caching for common queries**:

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

4. **Optimize database queries**:
   - Add appropriate indexes
   - Use select_related or prefetch_related for related data

5. **Add progress feedback**:
   - Implement more granular progress updates
   - Display intermediate results when available

6. **Optimize React rendering**:
   - Use React.memo for expensive components
   - Implement useMemo and useCallback for optimizations
   - Use virtualized lists for long content

7. **Use parallel processing where possible**:
   - Enable parallel movie enhancement with ThreadPoolExecutor
   - Implement caching for theater results to avoid redundant searches

### React Performance Issues

**Symptoms**:
- Slow UI updates
- Laggy interactions
- High CPU usage

**Solutions**:

1. **Identify performance bottlenecks**:
   - Use React DevTools Profiler
   - Check for unnecessary re-renders

2. **Optimize component rendering**:
   ```jsx
   // Memoize expensive components
   const MovieCard = React.memo(function MovieCard({ movie, onSelect }) {
     return (
       <div onClick={() => onSelect(movie.id)}>
         {movie.title}
       </div>
     );
   });

   // Memoize callback functions
   const handleSelect = useCallback((id) => {
     selectMovie(id);
   }, [selectMovie]);

   // Memoize derived data
   const sortedMovies = useMemo(() => {
     return [...movies].sort((a, b) => a.title.localeCompare(b.title));
   }, [movies]);
   ```

3. **Implement virtualization for long lists**:
   - Use react-window or react-virtualized
   - Only render visible items in long lists

4. **Optimize context usage**:
   - Split context into smaller, focused contexts
   - Use context selectors to prevent unnecessary re-renders

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

4. **Implement React cleanup**:
   ```jsx
   useEffect(() => {
     // Effect code here

     // Cleanup function
     return () => {
       // Release resources, cancel subscriptions, etc.
     };
   }, [dependencies]);
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

### Worker Timeout Issues

**Symptoms**:

- "WORKER TIMEOUT (pid:XXX)" errors in logs
- Requests taking longer than 30 seconds fail
- LLM API calls get interrupted
- Error stack traces ending with SSL read operations

**Solutions**:

1. **Increase Gunicorn worker timeout**:

   The default Gunicorn worker timeout is 30 seconds, which may not be enough for LLM API calls that can take longer to complete.

   ```bash
   # In Procfile
   web: gunicorn movie_chatbot.wsgi --log-file - --timeout 600

   # In manifest.yml
   command: python manage.py makemigrations chatbot && python manage.py migrate && gunicorn movie_chatbot.wsgi --log-file - --timeout 600
   ```

   This increases the timeout from 30 seconds to 600 seconds, giving the LLM API more time to respond.

2. **Optimize LLM API calls**:

   - Use smaller models with faster response times
   - Reduce the complexity of prompts
   - Set explicit timeouts on API calls

3. **Implement asynchronous processing**:

   - Use Celery or Django Channels for background processing
   - Implement a polling mechanism for long-running tasks
   - Return immediate responses and update results asynchronously

### Service Binding Issues

**Symptoms**:

- "Service binding failed" errors
- "Could not find bound service" in application logs
- LLM connection errors after binding

**Solutions**:

1. **Verify service instance**:

   ```bash
   cf service movie-chatbot-llm
   ```

2. **Check binding details**:

   ```bash
   cf service-keys movie-chatbot-llm
   ```

3. **Rebind service**:

   ```bash
   cf unbind-service movie-chatbot movie-chatbot-llm
   cf bind-service movie-chatbot movie-chatbot-llm
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

3. **View logs**:

   ```bash
   cf logs movie-chatbot --recent
   ```

### React Debugging

1. **Enable React Developer Tools**:
   - Install React Developer Tools browser extension
   - Use Components tab to inspect component hierarchy
   - Use Profiler tab to identify performance issues

2. **Add React Query DevTools**:
   ```jsx
   // In development mode
   import { ReactQueryDevtools } from '@tanstack/react-query-devtools'

   function App() {
     return (
       <QueryClientProvider client={queryClient}>
         {/* Your app components */}
         <ReactQueryDevtools initialIsOpen={false} />
       </QueryClientProvider>
     )
   }
   ```

3. **Implement component-level logging**:
   ```jsx
   // Add logging to key components
   function MovieSection({ movies }) {
     console.log('MovieSection rendering with', movies.length, 'movies');

     // Component implementation
   }
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

## First Run Mode Issues

### Only One Movie Gets Theater Data

**Symptoms**:
- Only one movie (typically the first one) shows theaters and showtimes
- Other movies show "No theaters found" even when they should be in theaters

**Explanation**:
- The Theater Finder agent may only be processing the first movie in the recommendations
- SerpAPI may only return results for certain movies that are currently in theaters
- Some movies might be classified as current releases but not actually playing in theaters

**Solutions**:

1. **Check movie classification**:
   - Verify that movies are correctly classified as current releases
   - Check the `_process_current_releases` method in `MovieCrewManager`

2. **Review theater search logs**:
   ```bash
   LOG_LEVEL=DEBUG
   # Look for logs like "Found X theaters for movie: [Movie Title]"
   ```

3. **Examine SerpAPI responses**:
   - Check if SerpAPI is returning results for all movies
   - Verify that the search queries are correctly formatted

4. **Improve UI feedback**:
   - Update the MovieCard component to show "Checking theaters..." while loading
   - Display "No theaters found" when a movie has no theaters
   - Ensure the UI doesn't get stuck in a loading state

5. **Implement parallel theater searches**:
   - Use ThreadPoolExecutor to search for theaters for multiple movies in parallel
   - Add caching for theater results to avoid redundant searches

### UI Gets Stuck When Switching Between Movies

**Symptoms**:
- UI becomes unresponsive when clicking on different movie tiles
- Endless polling for theaters that don't exist
- Theater section shows perpetual loading state

**Solutions**:

1. **Implement timeout for theater polling**:
   - Reduce the maximum polling time from 2 minutes to 30 seconds
   - Set empty theaters array after timeout instead of showing an error
   ```javascript
   // In TheaterSection.jsx
   setTimeout(() => {
     if (pollingIntervalRef.current) {
       clearInterval(pollingIntervalRef.current);
       // Instead of showing an error, just set empty theaters array
       setFirstRunMovies(prevMovies =>
         prevMovies.map(m =>
           m.id === selectedMovieId
             ? { ...m, theaters: [] }
             : m
         )
       );
       setIsLoadingTheaters(false);
     }
   }, 30 * 1000); // 30 seconds max polling
   ```

2. **Add theater status indicator in MovieCard**:
   ```jsx
   // In MovieCard.jsx
   const theaterStatus = isFirstRun ? (
     movie.theaters === undefined ? 'checking' :
     movie.theaters && movie.theaters.length === 0 ? 'none' :
     'found'
   ) : 'not-applicable';

   // Then in the render:
   {isFirstRun && (
     <div className="mt-2 small text-muted">
       {theaterStatus === 'found' && theaterCount > 0 ? (
         `Available at ${theaterCount} theater${theaterCount === 1 ? '' : 's'}`
       ) : theaterStatus === 'checking' ? (
         <span><i className="bi bi-hourglass-split me-1"></i>Checking theaters...</span>
       ) : (
         <span><i className="bi bi-x-circle me-1"></i>No theaters found nearby</span>
       )}
     </div>
   )}
   ```

3. **Skip theater fetching for movies with empty theaters**:
