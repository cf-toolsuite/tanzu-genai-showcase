"""
Manager for the movie recommendation crew.
"""
import logging
import json
import re
from datetime import datetime
from typing import List, Dict, Any, Optional, Union

import tmdbsimple as tmdb
from crewai import Task, Crew
from django.conf import settings
from langchain_openai import ChatOpenAI

from .agents.movie_finder_agent import MovieFinderAgent
from .agents.recommendation_agent import RecommendationAgent
from .agents.theater_finder_agent import TheaterFinderAgent
from .tools.search_movies_tool import SearchMoviesTool
from .tools.analyze_preferences_tool import AnalyzePreferencesTool
from .tools.find_theaters_tool import FindTheatersTool
from .tools.enhance_images_tool import EnhanceMovieImagesTool
from .utils.logging_middleware import LoggingMiddleware
from .utils.json_parser import JsonParser
from .utils.response_formatter import ResponseFormatter

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class MovieCrewManager:
    """Manager for the movie recommendation crew."""

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
        Initialize the MovieCrewManager.

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

        # Configure TMDb API if key is provided
        if tmdb_api_key:
            tmdb.API_KEY = tmdb_api_key

    @LoggingMiddleware.log_method_call
    def create_llm(self, temperature: float = 0.5) -> ChatOpenAI:
        """
        Create an LLM instance with the specified configuration.

        Args:
            temperature: Temperature parameter for the LLM

        Returns:
            Configured ChatOpenAI instance
        """
        # Log configuration details
        logger.info(f"Creating LLM with model: {self.model}")
        logger.info(f"API base URL: {self.base_url if self.base_url else 'default'}")

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

        # Ensure model always has provider prefix
        full_model_name = f"{provider}/{model_name}"
        logger.info(f"Using model with provider prefix: {full_model_name}")

        # Create model mapping for LiteLLM - place it in model_kwargs
        litellm_mapping = {model_name: provider}

        # Set up model_kwargs with LiteLLM configuration
        model_kwargs = {
            "model_name_map": json.dumps(litellm_mapping)
        }

        # Explicitly set the API key in the environment for LiteLLM's underlying libraries
        # This is a safer approach that ensures the key is properly propagated
        import os
        os.environ["OPENAI_API_KEY"] = self.api_key
        if self.base_url:
            os.environ["OPENAI_API_BASE"] = self.base_url

        # Base configuration with the key as a parameter (belt and suspenders approach)
        config = {
            "openai_api_key": self.api_key,
            "model": full_model_name,
            "temperature": temperature,
            "model_kwargs": model_kwargs
        }

        # Add base URL if provided
        if self.base_url:
            config["openai_api_base"] = self.base_url

        # Log detailed configuration for debugging
        logger.info(f"LiteLLM mapping: {litellm_mapping}")
        logger.info(f"Using model: {full_model_name} with provider: {provider}")

        # Create the model instance with proper configuration
        return ChatOpenAI(**config)

    @LoggingMiddleware.log_method_call
    def process_query(self, query: str, conversation_history: List[Dict[str, str]], first_run_mode: bool = True) -> Dict[str, Any]:
        """
        Process a user query and return movie recommendations.

        Args:
            query: The user's query
            conversation_history: List of previous messages in the conversation

        Returns:
            Dict with response text and movie recommendations
        """
        # Create the LLM with extra error handling
        try:
            llm = self.create_llm()
            logger.info(f"Created LLM instance: {self.model}")
        except Exception as llm_error:
            logger.error(f"Failed to create LLM instance: {str(llm_error)}")
            logger.exception(llm_error)
            # Provide a fallback response
            return {
                "response": "I'm experiencing technical difficulties with the language model. Please try again later.",
                "movies": []
            }

        # Create the agents with detailed logging
        try:
            logger.debug("Creating movie finder agent")
            movie_finder = MovieFinderAgent.create(llm)

            logger.debug("Creating recommendation agent")
            recommender = RecommendationAgent.create(llm)

            logger.debug("Creating theater finder agent")
            theater_finder = TheaterFinderAgent.create(llm)

            logger.info("All agents created successfully")
        except Exception as agent_error:
            logger.error(f"Failed to initialize one or more agents: {str(agent_error)}")
            logger.exception(agent_error)
            # Provide a fallback response
            return {
                "response": "I encountered a technical issue while setting up the movie recommendation system. Please try again later.",
                "movies": []
            }

        # Create tools for each task, passing the mode
        search_tool = SearchMoviesTool()
        search_tool.first_run_mode = first_run_mode  # Set the mode
        analyze_tool = AnalyzePreferencesTool()

        # Set up the theater finder tool with location and timezone
        theater_finder_tool = FindTheatersTool(user_location=self.user_location)
        theater_finder_tool.user_ip = self.user_ip
        theater_finder_tool.timezone = self.timezone

        logger.info(f"Created SearchMoviesTool with first_run_mode: {first_run_mode}")
        
        # Ensure tool compatibility with CrewAI 0.114.0
        self._ensure_tool_compatibility([search_tool, analyze_tool, theater_finder_tool])

        # Set up the image enhancement tool
        enhance_images_tool = EnhanceMovieImagesTool(tmdb_api_key=self.tmdb_api_key)

        # Create the tasks with the defined tools
        find_movies_task = Task(
            description=f"Find movies that match the user's criteria: '{query}'",
            expected_output="A JSON list of relevant movies with title, overview, release date, and TMDb ID",
            agent=movie_finder,
            tools=[search_tool]
        )

        # Get max recommendations count from settings
        max_recommendations = getattr(settings, 'MAX_RECOMMENDATIONS', 3)

        recommend_movies_task = Task(
            description=f"Recommend the top {max_recommendations} movies from the list that best match the user's preferences",
            expected_output=f"A JSON list of the top {max_recommendations} recommended movies with explanations",
            agent=recommender,
            tools=[analyze_tool]
        )

        find_theaters_task = Task(
            description="Find theaters showing the recommended movies near the user's location",
            expected_output="A JSON list of theaters showing the recommended movies with showtimes",
            agent=theater_finder,
            tools=[theater_finder_tool]
        )

        # Create the crew based on the mode
        if first_run_mode:
            # For First Run mode (theater-based recommendations), include all agents and tasks
            crew = Crew(
                agents=[movie_finder, recommender, theater_finder],
                tasks=[find_movies_task, recommend_movies_task, find_theaters_task],
                verbose=True
            )
            logger.info("Created crew for First Run mode (including theater search)")
        else:
            # For Casual Viewing mode, skip the theater finder
            crew = Crew(
                agents=[movie_finder, recommender],
                tasks=[find_movies_task, recommend_movies_task],
                verbose=True
            )
            logger.info("Created crew for Casual Viewing mode (skipping theater search)")

        # Debug log the task structure
        logger.info(f"Task definitions: {[t.description for t in crew.tasks]}")

        # Execute the crew with enhanced error handling
        try:
            logger.info("Starting crew execution with query: %s", query)
            logger.debug(f"Crew tasks: {[t.description for t in crew.tasks]}")
            logger.debug(f"Crew agents: {[a.role for a in crew.agents]}")

            # Set execution timeout and execute with detailed logging
            logger.info("Initiating crew kickoff")

            # Add patch for CrewAI tool event tracking issue
            self._patch_crewai_event_tracking()
            
            start_time = datetime.now()
            result = crew.kickoff()
            end_time = datetime.now()
            execution_time = (end_time - start_time).total_seconds()

            logger.info(f"Crew execution completed in {execution_time:.2f} seconds")
            logger.info(f"Result type: {type(result)}")

            # Save successful execution stats
            logger.info({
                "event": "crew_execution_success",
                "execution_time": execution_time,
                "query_length": len(query) if query else 0,
                "result_length": len(str(result)) if result else 0
            })

            # Additional check if result is None
            if result is None:
                logger.warning(f"Crew execution returned None")
                return {
                    "response": f"I found some movie options for '{query}' but couldn't retrieve all the details. Here's what I can tell you.",
                    "movies": []
                }

            # Helper function to safely extract task output
            def safe_extract_task_output(task, task_name):
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
                    logger.debug(f"Using 'raw' attribute from {task_name} task output")
                elif hasattr(task.output, 'result'):
                    output = task.output.result
                    logger.debug(f"Using 'result' attribute from {task_name} task output")
                elif hasattr(task.output, 'output'):
                    output = task.output.output
                    logger.debug(f"Using 'output' attribute from {task_name} task output")
                else:
                    output = str(task.output)
                    logger.debug(f"Using string conversion for {task_name} task output")

                # Validate output is a string and has content
                if not isinstance(output, str):
                    logger.warning(f"{task_name} task output is not a string, converting")
                    output = str(output)

                if not output.strip():
                    logger.error(f"{task_name} task output is empty after processing")
                    return "[]"

                # Log output characteristics for debugging
                logger.debug(f"{task_name} output length: {len(output)}")
                logger.debug(f"{task_name} output preview: {output[:100]}{'...' if len(output) > 100 else ''}")

                return output

            # Extract recommendation output
            recommend_movies_output = safe_extract_task_output(recommend_movies_task, "Recommendation")
            recommendations = JsonParser.parse_json_output(recommend_movies_output)

            # Process theaters data only in First Run mode
            theaters_data = []
            if first_run_mode:
                try:
                    # Extract theater output in First Run mode
                    find_theaters_output = safe_extract_task_output(find_theaters_task, "Theater")

                    # Add extra logging for debugging
                    logger.debug(f"Raw theater data preview: {find_theaters_output[:1000]}")

                    # Attempt to parse the JSON with our improved error handling
                    theaters_data = JsonParser.parse_json_output(find_theaters_output)

                    # Check if we got valid data
                    if theaters_data:
                        logger.info(f"Processed theater data in First Run mode: {len(theaters_data)} theaters found")
                    else:
                        # If no theaters were parsed, try a more aggressive approach
                        logger.warning("Initial JSON parsing failed, attempting manual JSON repair")

                        # Check if the output looks like an array with an obvious syntax error
                        if find_theaters_output.startswith('[') and find_theaters_output.endswith(']'):
                            try:
                                # Try to manually correct some common JSON issues
                                fixed_json = find_theaters_output
                                # Replace any trailing commas before closing brackets
                                fixed_json = re.sub(r',\s*}', '}', fixed_json)
                                fixed_json = re.sub(r',\s*]', ']', fixed_json)

                                # Try parsing with the fixed JSON
                                theaters_data = json.loads(fixed_json)
                                logger.info(f"Successfully recovered theater data through manual JSON repair: {len(theaters_data)} theaters")
                            except Exception as repair_error:
                                logger.error(f"Manual JSON repair failed: {str(repair_error)}")
                                theaters_data = []
                        else:
                            logger.warning("Theater data doesn't appear to be a valid JSON array")
                            theaters_data = []
                except Exception as theater_error:
                    logger.error(f"Error processing theater data: {str(theater_error)}")
                    logger.exception(theater_error)
                    theaters_data = []
            else:
                logger.info("Skipping theater data processing in Casual Viewing mode")

            # Ensure proper TMDB IDs are set on all recommendations
            for movie in recommendations:
                if 'tmdb_id' not in movie and 'id' in movie:
                    movie['tmdb_id'] = movie['id']
                    logger.info(f"Fixed TMDB ID for movie {movie.get('title')}")

            # Enhance movie data with high-quality images from TMDB
            if recommendations and self.tmdb_api_key:
                try:
                    import time
                    enhancement_start = time.time()

                    # Convert recommendations to JSON string for the tool
                    recommendations_json = json.dumps(recommendations)

                    # Run the enhancement tool
                    enhanced_json = enhance_images_tool._run(recommendations_json)

                    # Parse the enhanced data
                    enhanced_recommendations = JsonParser.parse_json_output(enhanced_json)

                    enhancement_duration = time.time() - enhancement_start
                    if enhanced_recommendations:
                        logger.info(f"Successfully enhanced {len(enhanced_recommendations)} movies with images in {enhancement_duration:.2f} seconds")
                        recommendations = enhanced_recommendations
                    else:
                        logger.warning("Movie image enhancement returned empty results")
                except Exception as enhancement_error:
                    logger.error(f"Error enhancing movie images: {str(enhancement_error)}")
                    logger.exception(enhancement_error)
                    # Continue with unenhanced recommendations
            else:
                logger.info("Skipping image enhancement: No recommendations or TMDB API key")

            # Process and filter recommendations for current releases
            self._process_current_releases(recommendations)

            # For First Run mode, only include current releases
            if first_run_mode:
                current_movies = [movie for movie in recommendations if movie.get('is_current_release', False)]
                logger.info(f"Filtered to {len(current_movies)} current release movies for First Run mode")

                if not current_movies and recommendations:
                    # If we have recommendations but none are current releases, add a note
                    logger.warning("No current release movies found, but have other recommendations")
                    if len(recommendations) > 0:
                        # Add a note to the first movie explaining the situation
                        recommendations[0]['note'] = "Note: This movie is not currently playing in theaters, but matches your preferences."

                # Use the filtered list for First Run mode
                recommendations_to_use = current_movies if current_movies else recommendations
                # Set conversation mode to first_run for each movie
                for movie in recommendations_to_use:
                    movie['conversation_mode'] = 'first_run'
            else:
                # For Casual Viewing mode, use all recommendations
                recommendations_to_use = recommendations
                # Set conversation mode to casual for each movie
                for movie in recommendations_to_use:
                    movie['conversation_mode'] = 'casual'

            # Combine recommendations with theater data
            movies_with_theaters = self._combine_movies_and_theaters(recommendations_to_use, theaters_data)

            # Generate response
            if not movies_with_theaters:
                return {
                    "response": f"I'm sorry, I couldn't find any movies matching '{query}'. Could you try a different request? For example, you could ask for action movies, family films, or movies starring a specific actor.",
                    "movies": []
                }
            else:
                # Format response using the ResponseFormatter utility
                response_message = ResponseFormatter.format_response(movies_with_theaters, query)

                return {
                    "response": response_message,
                    "movies": movies_with_theaters
                }

        except Exception as e:
            logger.error(f"Error executing crew: {str(e)}")
            logger.exception(e)
            return {
                "response": "I apologize, but I encountered an error while searching for movies. Please try again with a different request.",
                "movies": []
            }

    def _ensure_tool_compatibility(self, tools: List[Any]) -> None:
        """
        Ensure tools have all necessary attributes for CrewAI 0.114.0 compatibility.
        
        Args:
            tools: List of tools to check and enhance
        """
        for tool in tools:
            try:
                # Make sure the tool has a name attribute
                if not hasattr(tool, 'name'):
                    tool_class_name = tool.__class__.__name__
                    # Derive a name from the class name if needed
                    derived_name = tool_class_name.lower().replace('tool', '_tool')
                    setattr(tool, 'name', derived_name)
                    logger.info(f"Added missing name '{derived_name}' to tool of type {tool_class_name}")
                
                # Ensure tool name is registered with CrewAI's event tracking system
                # This prevents the KeyError: 'search_movies_tool' issue
                from crewai.utilities.events.utils.console_formatter import ConsoleFormatter
                if hasattr(ConsoleFormatter, 'tool_usage_counts') and tool.name not in ConsoleFormatter.tool_usage_counts:
                    ConsoleFormatter.tool_usage_counts[tool.name] = 0
                    logger.info(f"Pre-registered tool '{tool.name}' with CrewAI event tracking")
            except Exception as e:
                logger.warning(f"Error ensuring tool compatibility for {tool.__class__.__name__}: {e}")
                
    def _process_current_releases(self, recommendations: List[Dict[str, Any]]) -> None:
        """
        Process recommendations to identify current releases.

        Args:
            recommendations: List of movie recommendations
        """
        # Check which movies are current/first-run vs older movies
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
            is_current = False
            if release_year and (release_year >= current_year - 1):
                is_current = True

            # Set a flag on each movie
            movie['is_current_release'] = is_current

            # For older movies, set an empty theaters list to prevent showtimes lookup
            if not is_current:
                movie['theaters'] = []
                logger.info(f"Movie '{movie.get('title')}' is an older release ({release_year}), skipping theater lookup")

    def _patch_crewai_event_tracking(self):
        """
        Patch CrewAI's event tracking system to handle missing tools gracefully.
        This addresses the KeyError: 'search_movies_tool' issue in newer CrewAI versions.
        """
        try:
            # Import necessary modules from CrewAI
            from crewai.utilities.events.utils.console_formatter import ConsoleFormatter
            from crewai.utilities.events.event_listener import CrewAgentEventListener
            
            # Store original handle_tool_usage_finished method
            original_handle = ConsoleFormatter.handle_tool_usage_finished
            
            # Define patched method with error handling
            def patched_handle_tool_usage_finished(self, event):
                try:
                    # First try to initialize tool_usage_counts if it doesn't exist
                    if not hasattr(self, 'tool_usage_counts'):
                        self.tool_usage_counts = {}
                    
                    # Get tool name safely
                    tool_name = getattr(event, 'tool_name', 'unknown_tool')
                    
                    # Initialize counter for this tool if not already done
                    if tool_name not in self.tool_usage_counts:
                        self.tool_usage_counts[tool_name] = 0
                    
                    # Now call the original method which should work
                    return original_handle(self, event)
                except KeyError as e:
                    # Log the error but continue execution
                    logger.warning(f"CrewAI event tracking KeyError handled: {e}")
                    # Initialize the missing key
                    if hasattr(event, 'tool_name'):
                        self.tool_usage_counts[event.tool_name] = 1
                    return None
                except Exception as e:
                    # Log other errors but don't crash
                    logger.error(f"Error in CrewAI event tracking: {e}")
                    return None
            
            # Apply the patch
            ConsoleFormatter.handle_tool_usage_finished = patched_handle_tool_usage_finished
            logger.info("Successfully patched CrewAI event tracking")
            
        except Exception as e:
            # If patching fails, log but continue
            logger.warning(f"Failed to patch CrewAI event tracking: {e}")
    
    def _combine_movies_and_theaters(self,
                                    recommendations: List[Dict[str, Any]],
                                    theaters_data: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Combine movie recommendations with theater data.

        Args:
            recommendations: List of movie recommendations
            theaters_data: List of theaters with showtimes

        Returns:
            Combined list of movies with theater information
        """
        movies_with_theaters = []
        # Start with debug info
        logger.info(f"Combining {len(recommendations)} movies with {len(theaters_data)} theaters")

        # Improved theater mapping - ensures each movie gets only its relevant theaters
        theaters_by_movie_id = {}
        theaters_by_movie_title = {}

        # Debug log the theaters data for inspection
        theater_with_movie_ids = []
        if theaters_data:
            for idx, theater in enumerate(theaters_data[:3]):  # Log first 3 for brevity
                if isinstance(theater, dict):
                    movie_id = theater.get("movie_id")
                    movie_title = theater.get("movie_title")
                    theater_with_movie_ids.append(f"{theater.get('name', 'Unknown')}: movie_id={movie_id}, movie_title={movie_title}")

            logger.info(f"Theater data sample: {theater_with_movie_ids}")

        # First pass: Process theater data and organize by both movie ID and title
        for theater in theaters_data:
            if not isinstance(theater, dict):
                logger.warning(f"Skipping invalid theater entry (not a dictionary)")
                continue

            # Get movie ID and title from theater data
            movie_id = theater.get("movie_id")
            movie_title = theater.get("movie_title")

            # Skip theaters without proper identification
            if movie_id is None and movie_title is None:
                logger.warning(f"Theater missing both movie_id and movie_title: {theater.get('name', 'Unknown')}")
                continue

            # Validate theater has a name
            if not theater.get("name"):
                logger.warning(f"Theater missing name for movie_id {movie_id}")
                continue

            # Ensure showtimes are valid
            if not theater.get("showtimes") or not isinstance(theater.get("showtimes"), list):
                logger.warning(f"Theater {theater.get('name')} has no valid showtimes for movie_id {movie_id}")
                continue

            # Only add theaters with actual showtimes
            if len(theater.get("showtimes", [])) == 0:
                logger.warning(f"Theater {theater.get('name')} has empty showtimes list for movie_id {movie_id}")
                continue

            # Add to ID-based lookup if we have a movie_id
            if movie_id is not None:
                movie_id_str = str(movie_id)  # Convert to string for consistent lookup
                if movie_id_str not in theaters_by_movie_id:
                    theaters_by_movie_id[movie_id_str] = []
                theaters_by_movie_id[movie_id_str].append(theater)
                logger.info(f"Added theater '{theater.get('name')}' with {len(theater.get('showtimes', []))} showtimes to movie_id {movie_id}")

            # Also add to title-based lookup for backup matching
            if movie_title is not None:
                if movie_title not in theaters_by_movie_title:
                    theaters_by_movie_title[movie_title] = []
                theaters_by_movie_title[movie_title].append(theater)
                logger.info(f"Added theater '{theater.get('name')}' with {len(theater.get('showtimes', []))} showtimes to movie '{movie_title}'")

        # Debug log all IDs in theaters data vs. recommendations
        theater_movie_ids = set(str(t.get("movie_id")) for t in theaters_data if isinstance(t, dict) and t.get("movie_id") is not None)
        recommendation_ids = set(str(m.get("tmdb_id") or m.get("id", "")) for m in recommendations if isinstance(m, dict))
        logger.info(f"Theater movie IDs found: {theater_movie_ids}")
        logger.info(f"Recommendation movie IDs: {recommendation_ids}")

        # Log theater distribution for debugging
        for movie_id, theaters in theaters_by_movie_id.items():
            logger.info(f"Movie ID {movie_id} has {len(theaters)} theaters with showtimes")

        for movie_title, theaters in theaters_by_movie_title.items():
            logger.info(f"Movie title '{movie_title}' has {len(theaters)} theaters with showtimes")

        # Process each movie
        for movie in recommendations:
            if not isinstance(movie, dict):
                logger.error(f"Movie is not a dictionary: {movie}")
                continue

            # Get movie's TMDB ID - checking both possible field names
            movie_tmdb_id = movie.get("tmdb_id")
            if movie_tmdb_id is None and "id" in movie:
                movie_tmdb_id = movie.get("id")
                # Update the movie's tmdb_id field for consistency
                movie["tmdb_id"] = movie_tmdb_id
                logger.info(f"Set tmdb_id from id for movie: {movie.get('title')}")

            movie_title = movie.get("title")
            movie_theaters = []

            # First try to get theaters by movie ID (ensure string comparisons)
            if movie_tmdb_id is not None:
                movie_id_str = str(movie_tmdb_id)
                movie_theaters = theaters_by_movie_id.get(movie_id_str, [])
                if movie_theaters:
                    logger.info(f"Found {len(movie_theaters)} theaters for movie ID {movie_tmdb_id}")
                else:
                    # Try looking up by integer movie ID if string matching failed
                    # This handles cases where IDs might be stored as integers in one place and strings in another
                    for id_key, theaters in theaters_by_movie_id.items():
                        try:
                            if int(id_key) == int(movie_tmdb_id):
                                movie_theaters = theaters
                                logger.info(f"Found {len(theaters)} theaters for movie ID {movie_tmdb_id} using integer comparison")
                                break
                        except (ValueError, TypeError):
                            continue

            # If no theaters found by ID, try matching by title
            if not movie_theaters and movie_title:
                title_theaters = theaters_by_movie_title.get(movie_title, [])
                if title_theaters:
                    logger.info(f"Found {len(title_theaters)} theaters for movie title '{movie_title}'")
                    movie_theaters = title_theaters

                    # Update the theater data with the correct movie_id for future reference
                    if movie_tmdb_id:
                        for theater in movie_theaters:
                            theater["movie_id"] = movie_tmdb_id
                            logger.info(f"Updated theater {theater.get('name')} with movie_id {movie_tmdb_id}")

            # Log theater assignment results
            if movie_theaters:
                logger.info(f"Movie '{movie.get('title')}' (ID: {movie_tmdb_id}) assigned {len(movie_theaters)} theaters with showtimes")
            else:
                logger.info(f"Movie '{movie.get('title')}' (ID: {movie_tmdb_id}) has no theaters with showtimes")

            # Create movie with theaters
            movie_with_theaters = {**movie, "theaters": movie_theaters}
            movies_with_theaters.append(movie_with_theaters)

            # Log the number of theaters assigned to this movie for clarity
            theater_count = len(movie_theaters)
            if theater_count > 0:
                logger.info(f"Successfully assigned {theater_count} theaters to movie '{movie_title}' (ID: {movie_tmdb_id})")

        return movies_with_theaters
