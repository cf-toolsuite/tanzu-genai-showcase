"""
Enhanced Movie Chatbot CrewAI implementation wrapper.
This module improves on the optimized version with more advanced performance techniques:
1. Enhanced parallel processing with asyncio
2. Improved caching with TTL and intelligent invalidation
3. Better error handling and circuit breaker pattern
4. More efficient agent coordination
"""

import logging
import json
import re
import asyncio
import concurrent.futures
import hashlib
import time
import traceback
from datetime import datetime, timedelta
from typing import List, Dict, Any, Optional, Union, Callable
from functools import lru_cache, wraps
import tmdbsimple as tmdb
from crewai import Task, Crew
from django.conf import settings
from langchain_openai import ChatOpenAI

from .movie_crew.agents.movie_finder_agent import MovieFinderAgent
from .movie_crew.agents.recommendation_agent import RecommendationAgent
from .movie_crew.agents.theater_finder_agent import TheaterFinderAgent
from .movie_crew.tools.search_movies_tool import SearchMoviesTool
from .movie_crew.tools.analyze_preferences_tool import AnalyzePreferencesTool
from .movie_crew.tools.find_theaters_tool_optimized import FindTheatersToolOptimized
from .movie_crew.tools.enhance_images_tool import EnhanceMovieImagesTool
from .movie_crew.utils.logging_middleware import LoggingMiddleware
from .movie_crew.utils.json_parser_optimized import JsonParserOptimized
from .movie_crew.utils.response_formatter import ResponseFormatter
from .movie_crew.utils.custom_event_listener import CustomEventListener

# Configure logger
logger = logging.getLogger('chatbot.movie_crew')

# Enhanced cache with TTL support
class TTLCache:
    """Cache with time-to-live support"""

    def __init__(self, max_size=1000, default_ttl=3600):
        """
        Initialize the TTL Cache

        Args:
            max_size: Maximum number of items to store in cache
            default_ttl: Default time-to-live in seconds
        """
        self.cache = {}
        self.expiry = {}
        self.max_size = max_size
        self.default_ttl = default_ttl

    def get(self, key):
        """Get value from cache if it exists and is not expired"""
        if key not in self.cache:
            return None

        # Check if expired
        if self.expiry[key] < datetime.now():
            # Remove expired item
            del self.cache[key]
            del self.expiry[key]
            return None

        return self.cache[key]

    def set(self, key, value, ttl=None):
        """Set value in cache with specified TTL"""
        if ttl is None:
            ttl = self.default_ttl

        # Clean up if cache is full
        if len(self.cache) >= self.max_size:
            self._cleanup()

        # If still full after cleanup, remove oldest item
        if len(self.cache) >= self.max_size:
            oldest_key = min(self.expiry, key=self.expiry.get)
            del self.cache[oldest_key]
            del self.expiry[oldest_key]

        # Add new item
        self.cache[key] = value
        self.expiry[key] = datetime.now() + timedelta(seconds=ttl)

    def _cleanup(self):
        """Remove expired items"""
        now = datetime.now()
        expired_keys = [k for k, v in self.expiry.items() if v < now]
        for key in expired_keys:
            del self.cache[key]
            del self.expiry[key]

    def clear(self):
        """Clear all items in cache"""
        self.cache.clear()
        self.expiry.clear()

# Create caches for different types of data
LLM_CACHE = TTLCache(max_size=20, default_ttl=3600)  # 1 hour TTL for LLM instances
RESULT_CACHE = {
    'theaters': TTLCache(max_size=100, default_ttl=7200),  # 2 hours TTL for theaters
    'recommendations': TTLCache(max_size=100, default_ttl=7200)  # 2 hours TTL for recommendations
}

# Circuit breaker for external APIs
class CircuitBreaker:
    """Circuit breaker pattern implementation for external APIs"""

    def __init__(self, failure_threshold=5, recovery_timeout=30, name="default"):
        """
        Initialize circuit breaker

        Args:
            failure_threshold: Number of failures before opening circuit
            recovery_timeout: Seconds to wait before trying to close circuit
            name: Name for this circuit breaker
        """
        self.name = name
        self.failure_threshold = failure_threshold
        self.recovery_timeout = recovery_timeout
        self.failures = 0
        self.state = "CLOSED"  # CLOSED, OPEN, HALF-OPEN
        self.last_failure_time = None

    def __call__(self, func):
        """Decorator to wrap function with circuit breaker"""
        @wraps(func)
        def wrapper(*args, **kwargs):
            return self.call(func, *args, **kwargs)
        return wrapper

    def call(self, func, *args, **kwargs):
        """Call the function with circuit breaker logic"""
        if self.state == "OPEN":
            # Check if recovery timeout has elapsed
            if (datetime.now() - self.last_failure_time).total_seconds() > self.recovery_timeout:
                logger.info(f"Circuit {self.name} transitioning from OPEN to HALF-OPEN")
                self.state = "HALF-OPEN"
            else:
                logger.warning(f"Circuit {self.name} is OPEN - fast failing")
                raise Exception(f"Circuit breaker {self.name} is open")

        try:
            result = func(*args, **kwargs)

            # If successful and in HALF-OPEN, close the circuit
            if self.state == "HALF-OPEN":
                logger.info(f"Circuit {self.name} transitioning from HALF-OPEN to CLOSED")
                self.state = "CLOSED"
                self.failures = 0

            return result

        except Exception as e:
            # Record failure
            self.failures += 1
            self.last_failure_time = datetime.now()

            # If we've hit threshold, open the circuit
            if self.failures >= self.failure_threshold:
                logger.warning(f"Circuit {self.name} transitioning to OPEN after {self.failures} failures")
                self.state = "OPEN"

            # Re-raise the exception
            raise

# Create circuit breakers for different services
THEATER_CIRCUIT = CircuitBreaker(name="theater_service", failure_threshold=5, recovery_timeout=300)
LLM_CIRCUIT = CircuitBreaker(name="llm_service", failure_threshold=3, recovery_timeout=180)
TMDB_CIRCUIT = CircuitBreaker(name="tmdb_service", failure_threshold=3, recovery_timeout=180)

def query_hash(query, conversation_history=None):
    """Generate a deterministic hash for a query to use as cache key"""
    if conversation_history:
        # Only use the last 2 messages for context
        context = [msg.get('content', '') for msg in conversation_history[-2:] if msg.get('content')]
        query_with_context = query + ''.join(context)
        return hashlib.md5(query_with_context.encode('utf-8')).hexdigest()
    return hashlib.md5(query.encode('utf-8')).hexdigest()

class MovieCrewOptimizedEnhanced:
    """Enhanced Manager for the movie recommendation crew."""

    def __init__(
        self,
        api_key: str,
        base_url: Optional[str] = None,
        model: str = "gpt-4o-mini",
        tmdb_api_key: Optional[str] = None,
        user_location: Optional[str] = None,
        user_ip: Optional[str] = None,
        timezone: Optional[str] = None,
        llm_provider: Optional[str] = None
    ):
        """
        Initialize the Enhanced MovieCrew Manager.

        Args:
            api_key: LLM API key
            base_url: Optional custom endpoint URL for the LLM API
            model: Model name to use
            tmdb_api_key: API key for The Movie Database (TMDb)
            user_location: Optional user location string
            user_ip: Optional user IP address for geolocation
            timezone: Optional user timezone string (e.g., 'America/Los_Angeles')
            llm_provider: Optional LLM provider name to use with the model (e.g., 'openai')
        """
        self.api_key = api_key
        self.base_url = base_url
        self.model = model
        self.tmdb_api_key = tmdb_api_key
        self.user_location = user_location
        self.user_ip = user_ip
        self.timezone = timezone
        self.llm_provider = llm_provider
        self.llm_instance = None

        # Create thread pool executor
        self.executor = concurrent.futures.ThreadPoolExecutor(max_workers=10)

        # Configure TMDb API if key is provided
        if tmdb_api_key:
            tmdb.API_KEY = tmdb_api_key

        # Get config values with better defaults
        self.timeout_seconds = getattr(settings, 'API_REQUEST_TIMEOUT', 180)
        self.max_retries = getattr(settings, 'API_MAX_RETRIES', 5)
        self.backoff_factor = getattr(settings, 'API_RETRY_BACKOFF_FACTOR', 1.3)

        # Create asyncio event loop for async operations
        self.loop = None

    def __del__(self):
        """Clean up resources when object is destroyed"""
        if self.executor:
            self.executor.shutdown(wait=False)

    @LoggingMiddleware.log_method_call
    def create_llm(self, temperature: float = 0.7) -> ChatOpenAI:
        """
        Create an LLM instance with the specified configuration.
        Uses caching to avoid recreating instances.

        Args:
            temperature: Temperature parameter for the LLM

        Returns:
            Configured ChatOpenAI instance
        """
        # Create a cache key based on parameters
        cache_key = f"{self.model}|{self.base_url}|{temperature}|{self.llm_provider}"

        # Check if we already have this LLM in cache
        cached_llm = LLM_CACHE.get(cache_key)
        if cached_llm:
            logger.info(f"Using cached LLM instance for {self.model}")
            return cached_llm

        # Log configuration details
        logger.info(f"Creating new LLM with model: {self.model}")

        # Extract model name and provider info
        model_name = self.model
        provider = self.llm_provider  # May be None if not specified

        # Process provider/model format if present
        if '/' in model_name:
            parts = model_name.split('/', 1)
            provider_from_name, model_without_prefix = parts

            # If explicit provider was given, it overrides the prefix in the name
            if not provider:
                provider = provider_from_name
                logger.info(f"Using provider from model name: {provider}")

            model_name = model_without_prefix
            logger.info(f"Extracted model name without prefix: {model_name}")

        # If no provider specified yet, default to openai
        if not provider:
            provider = "openai"
            logger.info(f"No provider specified, defaulting to: {provider}")

        try:
            # Set up model_kwargs for configuration
            model_kwargs = {}

            # Create a single try block for the entire LLM creation process
            try:
                # Check if we should use litellm or direct configuration
                try:
                    import litellm
                    logger.info("Using litellm for model configuration")

                    # Base configuration with the key as a parameter
                    litellm_config = {
                        "model": model_name,
                        "api_key": self.api_key,
                        "api_base": self.base_url
                    }

                    # Apply LiteLLM's configuration
                    litellm.set_verbose = False

                    # Create configuration for langchain
                    config = {
                        "openai_api_key": self.api_key,
                        "model": model_name,
                        "temperature": temperature,
                        "request_timeout": self.timeout_seconds,
                        "model_kwargs": model_kwargs
                    }

                    # Add base URL if provided
                    if self.base_url:
                        config["openai_api_base"] = self.base_url

                except ImportError:
                    logger.info("litellm not available, using standard configuration")
                    # Use standard configuration
                    config = {
                        "openai_api_key": self.api_key,
                        "model": model_name,
                        "temperature": temperature,
                        "request_timeout": self.timeout_seconds
                    }

                    # Add base URL if provided
                    if self.base_url:
                        config["openai_api_base"] = self.base_url

                # We'll create the LLM instance directly and monitor with circuit breaker
                # This avoids triggering the LangChain deprecation warning on __call__
                try:
                    # This is the proper way to instantiate the LLM without triggering the __call__ method
                    logger.info("Creating LLM instance directly with proper initialization")

                    # Circuit breaker logic manually implemented to avoid decorator issues
                    if LLM_CIRCUIT.state == "OPEN":
                        # Check if recovery timeout has elapsed
                        if (LLM_CIRCUIT.last_failure_time is not None and
                            (datetime.now() - LLM_CIRCUIT.last_failure_time).total_seconds() > LLM_CIRCUIT.recovery_timeout):
                            logger.info(f"Circuit {LLM_CIRCUIT.name} transitioning from OPEN to HALF-OPEN")
                            LLM_CIRCUIT.state = "HALF-OPEN"
                        else:
                            logger.warning(f"Circuit {LLM_CIRCUIT.name} is OPEN - fast failing")
                            raise Exception(f"Circuit breaker {LLM_CIRCUIT.name} is open")

                    # Direct instantiation without function call that triggers deprecation
                    llm = ChatOpenAI(**config)

                    # Verify the LLM instance was created correctly (without calling methods)
                    if not hasattr(llm, 'invoke'):
                        logger.warning("LLM instance doesn't have 'invoke' method, using alternative initialization")
                        config['temperature'] = temperature  # Ensure temperature is set
                        llm = ChatOpenAI(**config)

                    # Reset circuit breaker if in HALF-OPEN state
                    if LLM_CIRCUIT.state == "HALF-OPEN":
                        logger.info(f"Circuit {LLM_CIRCUIT.name} transitioning from HALF-OPEN to CLOSED")
                        LLM_CIRCUIT.state = "CLOSED"
                        LLM_CIRCUIT.failures = 0

                    # Cache the instance with TTL
                    LLM_CACHE.set(cache_key, llm)

                    return llm

                except Exception as e:
                    # Record failure for circuit breaker
                    LLM_CIRCUIT.failures += 1
                    LLM_CIRCUIT.last_failure_time = datetime.now()

                    # If we've hit threshold, open the circuit
                    if LLM_CIRCUIT.failures >= LLM_CIRCUIT.failure_threshold:
                        logger.warning(f"Circuit {LLM_CIRCUIT.name} transitioning to OPEN after {LLM_CIRCUIT.failures} failures")
                        LLM_CIRCUIT.state = "OPEN"

                    # Re-raise for outer exception handler
                    raise

            except Exception as e:
                logger.error(f"Error creating LLM instance: {str(e)}")
                logger.error(traceback.format_exc())
                raise

        except Exception as e:
            logger.error(f"Error creating LLM instance: {str(e)}")
            logger.error(traceback.format_exc())
            raise

    def process_query(self, query: str, conversation_history: List[Dict[str, str]], first_run_mode: bool = True) -> Dict[str, Any]:
        """
        Process a user query and return movie recommendations.
        Enhanced with better caching, parallel processing, and error handling.

        Args:
            query: The user's query
            conversation_history: List of previous messages in the conversation
            first_run_mode: Whether to operate in first run mode (with theaters)

        Returns:
            Dict with response text and movie recommendations
        """
        start_time = time.time()
        query_key = query_hash(query, conversation_history)
        logger.info(f"Processing query with hash {query_key} (first_run_mode={first_run_mode})")

        # Check cache first for identical queries (with context)
        # Only use cache in casual mode as theaters/showtimes could change
        if not first_run_mode:
            cached_result = RESULT_CACHE['recommendations'].get(query_key)
            if cached_result:
                logger.info(f"Using cached recommendation for query: {query}")
                return cached_result

        try:
            # Create or get LLM from cache with error handling
            if not self.llm_instance:
                self.llm_instance = self.create_llm()
            llm = self.llm_instance

            # Create the event loop if needed
            if self.loop is None:
                try:
                    self.loop = asyncio.get_event_loop()
                except RuntimeError:
                    self.loop = asyncio.new_event_loop()
                    asyncio.set_event_loop(self.loop)

            # Use asyncio to run the crew workflow
            result = self.loop.run_until_complete(
                self._process_query_async(query, conversation_history, first_run_mode, llm)
            )

            # Log performance metrics
            elapsed_time = time.time() - start_time
            logger.info(f"Query processing completed in {elapsed_time:.2f} seconds")

            return result

        except Exception as e:
            logger.error(f"Error processing query: {str(e)}")
            logger.error(traceback.format_exc())

            # Provide a graceful fallback response
            return {
                "response": f"I apologize, but I encountered an error while searching for movies related to '{query}'. Please try again with a different request.",
                "movies": []
            }

    async def _process_query_async(self, query: str, conversation_history: List[Dict[str, str]], first_run_mode: bool, llm) -> Dict[str, Any]:
        """
        Process a query asynchronously with better parallelization.

        Args:
            query: The user's query
            conversation_history: List of previous messages in the conversation
            first_run_mode: Whether to operate in first run mode (with theaters)
            llm: LLM instance to use

        Returns:
            Dict with response text and movie recommendations
        """
        logger.info(f"Processing query async: {query[:50]}...")

        try:
            # Create tools and agents
            search_tool, analyze_tool, theater_finder_tool = self._create_tools(first_run_mode)
            movie_finder, recommender, theater_finder = self._create_agents(
                llm, search_tool, analyze_tool, theater_finder_tool
            )

            # Create tasks
            tasks = self._create_tasks(movie_finder, recommender, theater_finder, query)

            # Create crew
            crew = self._create_crew(
                movie_finder, recommender, theater_finder,
                tasks, first_run_mode
            )

            # Execute crew with better timeout handling
            # We'll use the executor to run this with a timeout
            crew_task = self.loop.run_in_executor(
                self.executor,
                self._execute_crew_with_timeout,
                crew,
                180  # 3 minutes timeout
            )

            # Wait for crew execution
            await crew_task

            # Process recommendations in parallel
            recommendations_task = self.loop.run_in_executor(
                self.executor,
                self._process_recommendations,
                tasks[1]  # recommend_movies_task
            )

            # Process theaters in parallel if in first run mode
            theaters_task = None
            if first_run_mode:
                theaters_task = self.loop.run_in_executor(
                    self.executor,
                    self._process_theaters,
                    tasks[2],  # find_theaters_task
                    []  # Empty recommendations until we get the result
                )

            # Wait for recommendations
            recommendations = await recommendations_task

            # Now process theaters if needed
            theaters_data = []
            if first_run_mode and theaters_task:
                # Update theaters task with the recommendations
                theaters_task = self.loop.run_in_executor(
                    self.executor,
                    self._process_theaters,
                    tasks[2],  # find_theaters_task
                    recommendations
                )
                theaters_data = await theaters_task

            # Enhance and prepare final results
            enhanced_recommendations = await self.loop.run_in_executor(
                self.executor,
                self._enhance_recommendations,
                recommendations
            )

            movies_with_theaters = await self.loop.run_in_executor(
                self.executor,
                self._prepare_final_movies,
                enhanced_recommendations,
                theaters_data,
                first_run_mode
            )

            # Generate response
            if not movies_with_theaters:
                response = {
                    "response": f"I'm sorry, I couldn't find any movies matching '{query}'. Could you try a different request?",
                    "movies": []
                }
            else:
                # Format response
                response_message = ResponseFormatter.format_response(movies_with_theaters, query)
                response = {
                    "response": response_message,
                    "movies": movies_with_theaters
                }

            # Cache result for casual mode
            query_key = query_hash(query, conversation_history)
            if not first_run_mode:
                RESULT_CACHE['recommendations'].set(query_key, response)

            return response

        except asyncio.TimeoutError:
            logger.error(f"Timeout processing query: {query[:50]}...")
            return {
                "response": f"I apologize, but it's taking longer than expected to process your request for '{query}'. Please try a more specific query.",
                "movies": []
            }

        except Exception as e:
            logger.error(f"Error in async query processing: {str(e)}")
            logger.error(traceback.format_exc())
            return {
                "response": f"I apologize, but I encountered an error while searching for movies related to '{query}'. Please try again with a different request.",
                "movies": []
            }

    def _execute_crew_with_timeout(self, crew, timeout_seconds):
        """Execute crew with timeout and better error handling"""
        try:
            # Create a future to allow timeout
            future = concurrent.futures.ThreadPoolExecutor().submit(crew.kickoff)
            return future.result(timeout=timeout_seconds)
        except concurrent.futures.TimeoutError:
            logger.error(f"Crew execution timed out after {timeout_seconds} seconds")
            raise asyncio.TimeoutError(f"Crew execution timed out after {timeout_seconds} seconds")
        except Exception as e:
            logger.error(f"Error executing crew: {str(e)}")
            logger.error(traceback.format_exc())
            raise

    def _create_tools(self, first_run_mode):
        """Create and configure tools with optimized settings"""
        # Create search tool with mode setting
        search_tool = SearchMoviesTool()
        search_tool.first_run_mode = first_run_mode

        # Create analyze tool
        analyze_tool = AnalyzePreferencesTool()

        # Create enhanced theater finder tool only in First Run mode
        if first_run_mode:
            # Only create and configure theater tool when needed
            theater_finder_tool = FindTheatersToolOptimized(user_location=self.user_location or "Unknown")
            theater_finder_tool.user_ip = self.user_ip
            theater_finder_tool.timezone = self.timezone

            # Ensure tool compatibility for all tools
            self._ensure_tool_compatibility([search_tool, analyze_tool, theater_finder_tool])
        else:
            # In Casual Viewing mode, there's no need for the theater tool
            theater_finder_tool = None

            # Ensure tool compatibility only for relevant tools
            self._ensure_tool_compatibility([search_tool, analyze_tool])

        return search_tool, analyze_tool, theater_finder_tool

    def _create_agents(self, llm, search_tool, analyze_tool, theater_finder_tool):
        """Create and configure agents with optimized settings"""
        # Create tool lists for different agent types with minimal duplication
        movie_finder_tools = [search_tool, analyze_tool]
        recommender_tools = [search_tool, analyze_tool]

        # Create main agents that are always needed
        movie_finder = MovieFinderAgent.create(llm, tools=movie_finder_tools)
        recommender = RecommendationAgent.create(llm, tools=recommender_tools)

        # Only create theater finder agent if the tool is available (First Run mode)
        if theater_finder_tool is not None:
            theater_finder_tools = [theater_finder_tool]
            theater_finder = TheaterFinderAgent.create(llm, tools=theater_finder_tools)
        else:
            # In Casual Viewing mode, create a placeholder theater finder
            # This won't be used but prevents None access errors
            theater_finder = None

        return movie_finder, recommender, theater_finder

    def _create_tasks(self, movie_finder, recommender, theater_finder, query):
        """Create tasks with optimized descriptions and expectations"""
        # Simplify and clarify task descriptions for better agent focus
        find_movies_task = Task(
            description=f"Find movies matching: '{query}'",
            expected_output="JSON list of relevant movies with title, overview, release date, TMDb ID",
            agent=movie_finder
        )

        # Get max recommendations count from settings with default
        max_recommendations = getattr(settings, 'MAX_RECOMMENDATIONS', 3)

        recommend_movies_task = Task(
            description=f"Recommend top {max_recommendations} movies that best match preferences",
            expected_output="JSON list of recommended movies with explanations",
            agent=recommender
        )

        # Only create theater task if theater_finder is available (First Run mode)
        if theater_finder is not None:
            find_theaters_task = Task(
                description="Find theaters showing these movies near user location",
                expected_output="JSON list of theaters with showtimes",
                agent=theater_finder
            )
            return [find_movies_task, recommend_movies_task, find_theaters_task]
        else:
            # In Casual Viewing mode, don't create theater task
            return [find_movies_task, recommend_movies_task, None]

    def _create_crew(self, movie_finder, recommender, theater_finder, tasks, first_run_mode):
        """Create and configure the crew based on mode"""
        find_movies_task, recommend_movies_task, find_theaters_task = tasks

        # Create custom event listener with optimized logging
        event_listener = CustomEventListener()

        # Optimize task dependencies for better performance
        find_movies_task.context = []
        recommend_movies_task.context = [find_movies_task]

        if first_run_mode:
            # Fine-tune task dependencies
            find_theaters_task.context = [recommend_movies_task]

            # For First Run mode, include all agents and tasks
            crew = Crew(
                agents=[movie_finder, recommender, theater_finder],
                tasks=[find_movies_task, recommend_movies_task, find_theaters_task],
                verbose=False,  # Reduce verbosity for improved performance
                event_listeners=[event_listener]
            )
        else:
            # For Casual Viewing mode, skip the theater finder
            crew = Crew(
                agents=[movie_finder, recommender],
                tasks=[find_movies_task, recommend_movies_task],
                verbose=False,  # Reduce verbosity for improved performance
                event_listeners=[event_listener]
            )

        return crew

    def _process_recommendations(self, recommend_task):
        """Process and parse recommendation output with better error handling"""
        # Extract and parse recommendation output
        try:
            recommend_output = self._safe_extract_task_output(recommend_task, "Recommendation")
            recommendations = JsonParserOptimized.parse_json_output(recommend_output)
            return recommendations if recommendations else []
        except Exception as e:
            logger.error(f"Error processing recommendations: {str(e)}")
            return []

    def _process_theaters(self, theater_task, recommendations):
        """Process theater data with parallel processing and caching"""
        try:
            # Extract theater output
            theater_output = self._safe_extract_task_output(theater_task, "Theater")
            theaters_data = JsonParserOptimized.parse_json_output(theater_output)

            # Apply manual JSON repair if parsing failed
            if not theaters_data and theater_output.startswith('[') and theater_output.endswith(']'):
                theaters_data = self._repair_json(theater_output)

            # Cache theaters by movie ID for future requests
            if theaters_data:
                for theater in theaters_data:
                    if isinstance(theater, dict) and 'movie_id' in theater:
                        movie_id = str(theater['movie_id'])
                        RESULT_CACHE['theaters'].set(movie_id, theater)

            return theaters_data if theaters_data else []
        except Exception as e:
            logger.error(f"Error processing theater data: {str(e)}")
            return []

    def _enhance_recommendations(self, recommendations):
        """Enhance movie data with optimized image loading"""
        if not recommendations or not self.tmdb_api_key:
            return recommendations

        try:
            # Define the enhancement function without the decorator
            def _enhance_movies_internal(recs):
                # Set up image enhancement tool
                enhance_images_tool = EnhanceMovieImagesTool(tmdb_api_key=self.tmdb_api_key)

                # Fix TMDB IDs to ensure consistency
                for movie in recs:
                    if 'tmdb_id' not in movie and 'id' in movie:
                        movie['tmdb_id'] = movie['id']

                # Use the tool to enhance images (with timeout)
                recommendations_json = json.dumps(recs)
                enhanced_json = enhance_images_tool._run(recommendations_json)

                # Parse and return enhanced data
                enhanced_recommendations = JsonParserOptimized.parse_json_output(enhanced_json)
                return enhanced_recommendations if enhanced_recommendations else recs

            # Use circuit breaker pattern correctly - apply to the function call, not the definition
            # This ensures the recommendations parameter is properly passed
            try:
                # Check circuit state manually
                if TMDB_CIRCUIT.state == "OPEN":
                    # Check if recovery timeout has elapsed
                    if (TMDB_CIRCUIT.last_failure_time is not None and
                        (datetime.now() - TMDB_CIRCUIT.last_failure_time).total_seconds() > TMDB_CIRCUIT.recovery_timeout):
                        logger.info(f"Circuit {TMDB_CIRCUIT.name} transitioning from OPEN to HALF-OPEN")
                        TMDB_CIRCUIT.state = "HALF-OPEN"
                    else:
                        logger.warning(f"Circuit {TMDB_CIRCUIT.name} is OPEN - fast failing")
                        raise Exception(f"Circuit breaker {TMDB_CIRCUIT.name} is open")

                # Call the function with the recommendations parameter
                result = _enhance_movies_internal(recommendations)

                # Reset circuit breaker if in HALF-OPEN state
                if TMDB_CIRCUIT.state == "HALF-OPEN":
                    logger.info(f"Circuit {TMDB_CIRCUIT.name} transitioning from HALF-OPEN to CLOSED")
                    TMDB_CIRCUIT.state = "CLOSED"
                    TMDB_CIRCUIT.failures = 0

                return result
            except Exception as e:
                # Record failure for circuit breaker
                TMDB_CIRCUIT.failures += 1
                TMDB_CIRCUIT.last_failure_time = datetime.now()

                # If we've hit threshold, open the circuit
                if TMDB_CIRCUIT.failures >= TMDB_CIRCUIT.failure_threshold:
                    logger.warning(f"Circuit {TMDB_CIRCUIT.name} transitioning to OPEN after {TMDB_CIRCUIT.failures} failures")
                    TMDB_CIRCUIT.state = "OPEN"

                # Re-raise for outer exception handler
                raise
        except Exception as e:
            logger.error(f"Error enhancing recommendations: {str(e)}")
            return recommendations

    def _prepare_final_movies(self, recommendations, theaters_data, first_run_mode):
        """Prepare final movie data with theaters for rendering"""
        # Process current releases
        self._process_current_releases(recommendations)

        # Filter for current releases in first run mode
        if first_run_mode:
            current_movies = [m for m in recommendations if m.get('is_current_release', False)]
            recommendations_to_use = current_movies if current_movies else recommendations

            # Set mode flag
            for movie in recommendations_to_use:
                movie['conversation_mode'] = 'first_run'
        else:
            recommendations_to_use = recommendations
            # Set mode flag
            for movie in recommendations_to_use:
                movie['conversation_mode'] = 'casual'

        # Combine recommendations with theater data
        movies_with_theaters = self._combine_movies_and_theaters(
            recommendations_to_use, theaters_data
        )

        return movies_with_theaters

    def _safe_extract_task_output(self, task, task_name):
        """Safely extract task output with better error handling"""
        logger.debug(f"Extracting output from {task_name} task")

        if not hasattr(task, 'output'):
            logger.error(f"{task_name} task has no 'output' attribute")
            return "[]"

        if task.output is None:
            logger.error(f"{task_name} task output is None")
            return "[]"

        # Try different approaches to get the raw output
        if hasattr(task.output, 'raw'):
            output = task.output.raw
        elif hasattr(task.output, 'result'):
            output = task.output.result
        elif hasattr(task.output, 'output'):
            output = task.output.output
        else:
            output = str(task.output)

        # Validate output is a string and has content
        if not isinstance(output, str):
            output = str(output)

        return output.strip() or "[]"

    def _repair_json(self, json_str):
        """Manually repair common JSON issues"""
        try:
            # Try more aggressive JSON repair methods
            # 1. Replace trailing commas before closing brackets
            fixed_json = re.sub(r',\s*}', '}', json_str)
            fixed_json = re.sub(r',\s*]', ']', fixed_json)

            # 2. Fix unquoted property names
            fixed_json = re.sub(r'(\s*})(\s*),(\s*)([a-zA-Z0-9_]+)(\s*:)', r'\1\2,\3"\4"\5', fixed_json)

            # 3. Fix missing quotes around string values
            fixed_json = re.sub(r':\s*([a-zA-Z][a-zA-Z0-9_]*)\s*([,}])', r': "\1"\2', fixed_json)

            # Try parsing the fixed JSON
            return json.loads(fixed_json)
        except Exception as e:
            logger.error(f"JSON repair failed: {str(e)}")
            # Return empty list if repair fails
            return []

    def _process_current_releases(self, recommendations):
        """Determine which movies are current releases"""
        current_year = datetime.now().year

        for movie in recommendations:
            if not isinstance(movie, dict):
                continue

            # Parse release date to determine if it's a current movie
            release_date = movie.get('release_date', '')
            release_year = None

            if release_date and len(release_date) >= 4:
                try:
                    release_year = int(release_date[:4])
                except ValueError:
                    pass

            # Movies from current year or previous year are considered "current"
            is_current = release_year is not None and (release_year >= current_year - 1)
            movie['is_current_release'] = is_current

            # For older movies, set an empty theaters list to skip theater lookup
            if not is_current:
                movie['theaters'] = []

    def _combine_movies_and_theaters(self, recommendations, theaters_data):
        """Combine movie recommendations with theater data efficiently"""
        # Create lookup dictionaries for faster matching
        theaters_by_movie_id = {}
        theaters_by_movie_title = {}

        # First check cache for any known theaters
        for movie in recommendations:
            if not isinstance(movie, dict):
                continue

            # Check if we have cached theaters for this movie
            if 'tmdb_id' in movie:
                movie_id = str(movie['tmdb_id'])
                cached_theater = RESULT_CACHE['theaters'].get(movie_id)
                if cached_theater:
                    # Use cached theaters
                    if not 'theaters' in movie:
                        movie['theaters'] = []
                    movie['theaters'].append(cached_theater)
                    logger.info(f"Using cached theater for movie {movie.get('title')}")

        # Process theaters from this request
        for theater in theaters_data:
            if not isinstance(theater, dict):
                continue

            # Skip theaters without proper identification or showtimes
            movie_id = theater.get("movie_id")
            movie_title = theater.get("movie_title")

            if movie_id is None and movie_title is None:
                continue

            # Skip theaters without proper showtimes
            if not theater.get("showtimes") or len(theater.get("showtimes", [])) == 0:
                continue

            # Add to ID-based lookup
            if movie_id is not None:
                movie_id_str = str(movie_id)
                if movie_id_str not in theaters_by_movie_id:
                    theaters_by_movie_id[movie_id_str] = []
                theaters_by_movie_id[movie_id_str].append(theater)

            # Add to title-based lookup
            if movie_title is not None:
                if movie_title not in theaters_by_movie_title:
                    theaters_by_movie_title[movie_title] = []
                theaters_by_movie_title[movie_title].append(theater)

        # Process each movie and add theaters
        movies_with_theaters = []

        for movie in recommendations:
            if not isinstance(movie, dict):
                continue

            # Get movie's TMDB ID and title
            movie_tmdb_id = movie.get("tmdb_id") or movie.get("id")
            movie_title = movie.get("title")

            # Skip movies without ID or title
            if not movie_tmdb_id and not movie_title:
                continue

            # If the movie already has theaters assigned, use those
            if 'theaters' in movie and movie['theaters']:
                movies_with_theaters.append(movie)
                continue

            # Look up theaters by ID first
            movie_theaters = []
            if movie_tmdb_id:
                movie_id_str = str(movie_tmdb_id)
                movie_theaters = theaters_by_movie_id.get(movie_id_str, [])

            # If no theaters found by ID, try matching by title
            if not movie_theaters and movie_title:
                movie_theaters = theaters_by_movie_title.get(movie_title, [])

                # Update theater data with the movie ID for future reference
                if movie_theaters and movie_tmdb_id:
                    for theater in movie_theaters:
                        theater["movie_id"] = movie_tmdb_id

            # Add theaters to the movie
            movie_with_theaters = {**movie, "theaters": movie_theaters}
            movies_with_theaters.append(movie_with_theaters)

        return movies_with_theaters

    def _ensure_tool_compatibility(self, tools):
        """Ensure tools have necessary attributes for CrewAI compatibility"""
        for tool in tools:
            try:
                # Make sure each tool has a name attribute
                if not hasattr(tool, 'name'):
                    tool_class_name = tool.__class__.__name__
                    derived_name = tool_class_name.lower().replace('tool', '_tool')
                    setattr(tool, 'name', derived_name)

                # Pre-register tool with CrewAI event tracking
                try:
                    from crewai.utilities.events.utils.console_formatter import ConsoleFormatter
                    if hasattr(ConsoleFormatter, 'tool_usage_counts') and tool.name not in ConsoleFormatter.tool_usage_counts:
                        ConsoleFormatter.tool_usage_counts[tool.name] = 0
                except (ImportError, AttributeError):
                    pass
            except Exception:
                pass  # Continue even if tool compatibility check fails
