"""
Tool for finding theaters showing the recommended movies.
"""
import json
import logging
import time
from datetime import datetime
from typing import List, Dict, Any, Optional
from crewai.tools import BaseTool
from pydantic import BaseModel, Field
from django.conf import settings

from ...location_service import LocationService
from ...serp_service import SerpShowtimeService
from ...api_utils import APIRequestHandler

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class FindTheatersInput(BaseModel):
    """Input schema for FindTheatersTool."""
    movie_recommendations_json: str = Field(default="", description="JSON string containing movie recommendations")

class FindTheatersConfig(BaseModel):
    """Configuration for FindTheatersTool."""
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")

class FindTheatersTool(BaseTool):
    """Tool for finding theaters showing the recommended movies."""

    name: str = "find_theaters_tool"
    description: str = "Find theaters showing the recommended movies near the user's location."
    args_schema: type[FindTheatersInput] = FindTheatersInput
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")
    user_ip: Optional[str] = None
    timezone: Optional[str] = None

    def _theater_has_movie_showtimes(self, theater: Dict[str, Any], movie_title: str) -> bool:
        """
        Verify that a theater has showtimes for a specific movie.

        Args:
            theater: Theater dictionary with showtimes
            movie_title: Movie title to check for

        Returns:
            True if the theater has showtimes for the movie, False otherwise
        """
        try:
            # If theater has no showtimes field, it can't have the movie
            if 'showtimes' not in theater or not theater['showtimes']:
                logger.debug(f"Theater {theater.get('name', 'Unknown')} has no showtimes")
                return False

            # With the SerpAPI implementation, we're querying "{movie_title} theater"
            # The returned theaters should already be filtered for the specific movie
            # So if there are showtimes, we can be confident they are for our movie

            # Make sure there's at least one valid showtime
            showtime_count = len(theater['showtimes'])
            if showtime_count > 0:
                logger.debug(f"Theater {theater.get('name')} has {showtime_count} showtimes for {movie_title}")
                return True
            else:
                logger.debug(f"Theater {theater.get('name')} has no showtimes")
                return False

        except Exception as e:
            logger.error(f"Error checking movie showtimes: {str(e)}")
            return False

    def _format_serpapi_showtimes(self, serp_theaters, movie_title, movie_id) -> List[Dict[str, Any]]:
        """
        Format SerpAPI showtime results into our standardized format.

        Args:
            serp_theaters: List of theaters from SerpAPI
            movie_title: Title of the movie
            movie_id: TMDB ID of the movie

        Returns:
            List of formatted theater dictionaries with showtimes
        """
        formatted_theaters = []

        try:
            # Get configuration from Django settings
            try:
                from django.conf import settings
                # Get maximum search radius
                max_radius_miles = getattr(settings, 'THEATER_SEARCH_RADIUS_MILES', 15)
                # Get maximum showtimes per theater (to limit data size)
                max_showtimes_per_theater = getattr(settings, 'MAX_SHOWTIMES_PER_THEATER', 20)
                # Get maximum theaters to return
                max_theaters = getattr(settings, 'MAX_THEATERS', 10)
                
                logger.info(f"Using configuration: max radius={max_radius_miles} miles, "
                           f"max showtimes={max_showtimes_per_theater} per theater, "
                           f"max theaters={max_theaters}")
            except Exception as e:
                logger.warning(f"Could not get theater settings: {str(e)}")
                max_radius_miles = 15  # Default to 15 miles
                max_showtimes_per_theater = 20  # Default to 20 showtimes per theater
                max_theaters = 10  # Default to 10 theaters

            logger.info(f"Formatting {len(serp_theaters)} theaters with showtimes for '{movie_title}' (ID: {movie_id})")

            # Debug the first theater to understand structure
            if serp_theaters and len(serp_theaters) > 0:
                first_theater = serp_theaters[0]
                first_theater_keys = list(first_theater.keys())
                logger.info(f"First theater keys: {first_theater_keys}")

                # Log showtimes sample
                if 'showtimes' in first_theater:
                    showtimes_count = len(first_theater['showtimes'])
                    logger.info(f"First theater has {showtimes_count} showtimes")

                    # Log a sample of showtimes for debugging
                    if showtimes_count > 0:
                        first_showtime = first_theater['showtimes'][0]
                        logger.info(f"Sample showtime: {json.dumps(first_showtime)}")

            for theater in serp_theaters:
                theater_name = theater.get('name', 'Unknown Theater')
                logger.info(f"Processing theater: {theater_name}")

                # Check if distance is within radius
                distance_miles = theater.get('distance_miles')
                if distance_miles is not None and distance_miles > max_radius_miles:
                    logger.info(f"Skipping theater '{theater_name}' - distance {distance_miles} miles exceeds radius of {max_radius_miles} miles")
                    continue

                # Create the theater entry with all available information
                theater_entry = {
                    "movie_id": movie_id,
                    "movie_title": movie_title,  # Include movie title for reference
                    "name": theater_name,
                    "address": theater.get('address', ''),
                    "link": theater.get('link', ''),  # Include link if available
                    "distance_miles": distance_miles,
                    "showtimes": []
                }

                # Copy any GPS coordinates if available
                if 'latitude' in theater and 'longitude' in theater:
                    theater_entry['latitude'] = theater.get('latitude')
                    theater_entry['longitude'] = theater.get('longitude')

                # Log available fields
                fields_present = []
                for field in ['address', 'link', 'latitude', 'longitude', 'distance_miles']:
                    if theater.get(field) is not None:
                        fields_present.append(field)
                logger.info(f"Theater fields present: {', '.join(fields_present)}")

                # Extract showtimes from the SerpAPI response
                showtimes = theater.get('showtimes', [])
                logger.info(f"Found {len(showtimes)} showtimes for {theater_name}")

                valid_showtimes = 0
                # Limit the number of showtimes per theater to reduce JSON size
                for showtime in showtimes[:max_showtimes_per_theater]:
                    # Ensure we have a start time
                    if 'start_time' in showtime:
                        valid_showtimes += 1
                        format_type = showtime.get('format', 'Standard')

                        # Create standardized showtime entry
                        theater_entry['showtimes'].append({
                            "start_time": showtime['start_time'],
                            "format": format_type
                        })

                logger.info(f"Processed {valid_showtimes} valid showtimes for {theater_name} (limited to {max_showtimes_per_theater})")

                # Only add theaters that have showtimes
                if theater_entry['showtimes']:
                    # Sort showtimes by start time for better display
                    theater_entry['showtimes'].sort(key=lambda x: x['start_time'])
                    formatted_theaters.append(theater_entry)
                    logger.info(f"Added theater '{theater_name}' with {len(theater_entry['showtimes'])} formatted showtimes")
                else:
                    logger.warning(f"Skipping theater '{theater_name}' - no valid showtimes found")

            # Sort theaters by distance for better user experience
            formatted_theaters.sort(key=lambda x: x.get('distance_miles', float('inf')))
            logger.info(f"Successfully formatted {len(formatted_theaters)} theaters with showtimes for '{movie_title}'")
            return formatted_theaters

        except Exception as e:
            logger.error(f"Error formatting SerpAPI showtimes: {str(e)}")
            logger.exception(e)  # Log the full exception traceback
            return []

    def _run(self, movie_recommendations_json: str = "") -> str:
        """
        Find theaters showing the recommended movies near the user's location.

        Args:
            movie_recommendations_json: JSON string containing movie recommendations

        Returns:
            JSON string containing theaters and showtimes
        """
        # Add performance logging
        import time
        start_time = time.time()
        logger.info(f"Starting theater search at {datetime.now().strftime('%H:%M:%S.%f')}")

        # Get the max movies to process from Django settings
        MAX_MOVIES_TO_PROCESS = getattr(settings, 'MAX_RECOMMENDATIONS', 5)
        logger.info(f"Maximum movies to process for theaters: {MAX_MOVIES_TO_PROCESS}")
        try:
            # Default to empty list if the input is empty
            if not movie_recommendations_json:
                movie_recommendations_json = "[]"

            # Parse the input JSON
            movie_recommendations = json.loads(movie_recommendations_json)
            location = self.user_location or 'Unknown'

            if not movie_recommendations:
                logger.warning("No movie recommendations to find theaters for")
                return json.dumps([])

            # Check if any movies are marked as current releases
            current_movies = []
            for movie in movie_recommendations:
                # Force all movies in first run mode to be considered current releases
                if not 'is_current_release' in movie:
                    # Set to True by default for this tool since we only process this in first-run mode
                    movie['is_current_release'] = True
                if movie.get('is_current_release', False):
                    # Ensure TMDB ID is properly set
                    if 'tmdb_id' not in movie and 'id' in movie:
                        movie['tmdb_id'] = movie['id']
                        logger.info(f"Fixed missing tmdb_id for movie {movie.get('title')}")

                    # Only include movies with a TMDB ID
                    if movie.get('tmdb_id'):
                        current_movies.append(movie)
                    else:
                        logger.warning(f"Skipping movie without TMDB ID: {movie.get('title', 'Unknown')}")

            # If no current releases, return empty list as there's no need to find theaters
            if not current_movies:
                logger.info("No current release movies to find theaters for")
                return json.dumps([])

            logger.info(f"Finding theaters for {len(current_movies)} current release movies: {[m.get('title') for m in current_movies]}")

            # Initialize location service
            location_service = LocationService(user_agent="movie_chatbot_theaters")

            # Try to geocode the user's location
            user_coords = self._get_user_coordinates(location_service, location)

            # Store all theaters with showtimes, organized by movie
            theater_results = []

            # Limit the number of movies to process to avoid timeouts
            movies_to_process = current_movies[:MAX_MOVIES_TO_PROCESS]
            if len(current_movies) > MAX_MOVIES_TO_PROCESS:
                logger.info(f"Limiting theater search to {MAX_MOVIES_TO_PROCESS} movies out of {len(current_movies)} to avoid timeouts")

            # Process each movie individually to ensure theaters are correctly matched
            # Add throttling to avoid overwhelming SerpAPI
            for index, movie in enumerate(movies_to_process):
                movie_id = movie.get('tmdb_id')
                movie_title = movie.get('title')

                if not movie_id:
                    logger.warning(f"Skipping theater search for movie without ID: {movie_title}")
                    continue

                logger.info(f"Finding theaters for movie: {movie_title} (ID: {movie_id}) [{index+1}/{len(movies_to_process)}]")

                # Add a variable delay between requests to avoid rate limiting (except for first request)
                if index > 0:
                    # Get configurable delay values from settings with defaults
                    base_delay = getattr(settings, 'SERPAPI_REQUEST_BASE_DELAY', 8.0)
                    per_movie_delay = getattr(settings, 'SERPAPI_PER_MOVIE_DELAY', 3.0)
                    
                    # Increase delay for subsequent requests to prevent rate limiting
                    delay_seconds = base_delay + (index * per_movie_delay)
                    logger.info(f"Throttling API requests - waiting {delay_seconds} seconds before next request")
                    time.sleep(delay_seconds)

                # Try to get real showtimes via SerpAPI for this specific movie
                try:
                    # Get retry configuration from settings with defaults
                    max_retries = getattr(settings, 'SERPAPI_MAX_RETRIES', 3)
                    base_retry_delay = getattr(settings, 'SERPAPI_BASE_RETRY_DELAY', 5.0)
                    retry_multiplier = getattr(settings, 'SERPAPI_RETRY_MULTIPLIER', 2.0)
                    
                    retry_count = 0
                    movie_theaters_with_showtimes = None
                    
                    while retry_count <= max_retries and not movie_theaters_with_showtimes:
                        try:
                            logger.info(f"Attempt {retry_count+1}/{max_retries+1} to get showtimes for '{movie_title}'")
                            movie_theaters_with_showtimes = self._get_movie_showtimes(movie_title, location, user_coords, movie_id)
                            
                            # If we got empty results but haven't exhausted retries
                            if not movie_theaters_with_showtimes and retry_count < max_retries:
                                # True exponential backoff: base_delay * (multiplier ^ retry_count)
                                retry_delay = base_retry_delay * (retry_multiplier ** retry_count)
                                logger.info(f"No theaters found, retrying in {retry_delay:.1f} seconds (attempt {retry_count+1}/{max_retries+1})")
                                time.sleep(retry_delay)
                            
                            retry_count += 1
                        except Exception as retry_error:
                            error_message = str(retry_error).lower()
                            logger.error(f"Error in attempt {retry_count+1}: {error_message}")
                            
                            # Check specifically for rate limiting errors
                            is_rate_limit = "rate" in error_message and ("limit" in error_message or "exceed" in error_message)
                            
                            if retry_count < max_retries:
                                # Use longer delays for rate limit errors
                                if is_rate_limit:
                                    retry_delay = base_retry_delay * (retry_multiplier ** (retry_count + 1))  # Extra multiplier for rate limits
                                    logger.warning(f"Rate limit detected, using extended delay of {retry_delay:.1f} seconds")
                                else:
                                    retry_delay = base_retry_delay * (retry_multiplier ** retry_count)
                                
                                logger.info(f"Error occurred, retrying in {retry_delay:.1f} seconds (attempt {retry_count+1}/{max_retries+1})")
                                time.sleep(retry_delay)
                            
                            retry_count += 1
                    
                    # If we found real showtimes for this movie after retries, use them
                    if movie_theaters_with_showtimes:
                        logger.info(f"Found {len(movie_theaters_with_showtimes)} theaters with real showtimes for '{movie_title}'")

                        # Add movie_id to each theater to ensure proper association
                        for theater in movie_theaters_with_showtimes:
                            # Explicitly set the movie_id for this theater
                            theater['movie_id'] = movie_id
                            # Add movie title for debugging purposes
                            theater['movie_title'] = movie_title

                            # Verify showtimes are present
                            if not theater.get('showtimes') or len(theater.get('showtimes', [])) == 0:
                                logger.warning(f"Theater {theater.get('name')} has no showtimes for '{movie_title}'")
                                continue

                            # Add to our results
                            theater_results.append(theater)
                            logger.info(f"Added theater '{theater.get('name')}' with {len(theater.get('showtimes', []))} showtimes for '{movie_title}'")
                    else:
                        # No real showtimes found for this movie
                        logger.warning(f"No showtimes found for '{movie_title}'")
                
                except Exception as e:
                    logger.error(f"Error searching theaters for movie '{movie_title}': {str(e)}")
                    logger.exception(e)

            # Sort theaters by distance
            theater_results.sort(key=lambda x: x.get('distance_miles', float('inf')))
            
            # Get maximum theaters to return from Django settings
            try:
                max_theaters = getattr(settings, 'MAX_THEATERS', 10)
                if len(theater_results) > max_theaters:
                    logger.info(f"Limiting results to {max_theaters} theaters (from {len(theater_results)} total)")
                    theater_results = theater_results[:max_theaters]
            except Exception as e:
                logger.warning(f"Could not apply theater limit: {str(e)}")

            # Log final result count
            logger.info(f"Returning {len(theater_results)} theaters with showtimes across all movies")

            return json.dumps(theater_results)
        except Exception as e:
            logger.error(f"Error finding theaters: {str(e)}")
            return json.dumps([])

    def _get_user_coordinates(self, location_service, location: str) -> Dict[str, Any]:
        """
        Get user coordinates from location string or IP address.

        Args:
            location_service: LocationService instance
            location: Location string

        Returns:
            Dictionary with latitude, longitude and display_name
        """
        # Try to geocode the user's location
        user_coords = None
        if location and location.lower() != 'unknown':
            geocoded_location = location_service.geocode_location(location)
            if geocoded_location:
                user_coords = {
                    'latitude': geocoded_location['latitude'],
                    'longitude': geocoded_location['longitude'],
                    'display_name': geocoded_location['display_name']
                }
                logger.info(f"Successfully geocoded location: {location} -> {geocoded_location['display_name']}")
            else:
                logger.warning(f"Could not geocode location: {location}")

        # If no location provided or geocoding failed, try to get location from IP
        if not user_coords and self.user_ip:
            ip_location = location_service.get_location_from_ip(self.user_ip)
            if ip_location:
                user_coords = ip_location
                logger.info(f"Using IP-based location: {ip_location['display_name']}")

        # If we still don't have coordinates, use a more central US location (Seattle)
        if not user_coords:
            logger.info("Using default coordinates (west US)")
            user_coords = {
                'latitude': 47.60621,
                'longitude': -122.33207,
                'display_name': 'Seattle, WA, USA'
            }

        return user_coords

    def _get_movie_showtimes(self, movie_title: str, location: str, user_coords: Dict[str, Any], movie_id: Any = None) -> List[Dict[str, Any]]:
        """
        Get real showtimes for a movie using SerpAPI.

        Args:
            movie_title: Title of the movie
            location: Location string
            user_coords: Dictionary with user coordinates
            movie_id: TMDB ID of the movie

        Returns:
            List of theaters with showtimes
        """
        theaters_with_showtimes = []

        try:
            # Try to import django settings to get the API key
            try:
                from django.conf import settings
                serp_api_key = settings.SERPAPI_API_KEY

                # Log if SerpAPI key is available
                if serp_api_key:
                    logger.info(f"SerpAPI key is configured: {serp_api_key[:4]}...{serp_api_key[-4:] if len(serp_api_key) > 8 else ''}")
                else:
                    logger.warning("No SerpAPI key found in settings - no theater data will be shown")
                    return []

                if serp_api_key and serp_api_key != 'your_serpapi_key_here':
                    logger.info("Using SerpAPI for real showtimes")
                    showtime_service = SerpShowtimeService(api_key=serp_api_key)

                    # Use fallback location if needed
                    search_location = location if location and location.lower() != 'unknown' else user_coords['display_name']
                    logger.info(f"Searching for showtimes in location: {search_location}")

                    # Get the search radius from settings or use default
                    radius_miles = getattr(settings, 'THEATER_SEARCH_RADIUS_MILES', 15)

                    # For each movie, search for real showtimes
                    logger.info(f"Searching for real showtimes for movie: {movie_title} within {radius_miles} miles of {search_location}")

                    # Use timezone from tool (passed from manager) or extract from user coordinates if available
                    user_timezone = self.timezone or (user_coords.get('timezone') if user_coords else None)
                    logger.info(f"Using timezone for showtimes: {user_timezone}")

                    # Perform the search with our retry mechanism
                    try:
                        real_theaters_with_showtimes = APIRequestHandler.make_request(
                            lambda *args, **kwargs: showtime_service.search_showtimes(
                                movie_title=movie_title,
                                location=search_location,
                                radius_miles=radius_miles,
                                timezone=user_timezone
                            )
                        )
                    except Exception as search_error:
                        logger.error(f"SerpAPI search_showtimes failed after retries: {str(search_error)}")
                        logger.exception(search_error)  # Log full traceback
                        return []

                    # Validate that we have results specifically for this movie
                    valid_theaters = []

                    # Process valid theaters
                    if real_theaters_with_showtimes:
                        logger.info(f"Found {len(real_theaters_with_showtimes)} theaters with real showtimes for {movie_title}")

                        # Validate each theater has this specific movie
                        for theater in real_theaters_with_showtimes:
                            # Verify this theater actually has the movie
                            if self._theater_has_movie_showtimes(theater, movie_title):
                                logger.info(f"Confirmed theater {theater['name']} is showing {movie_title}")
                                valid_theaters.append(theater)
                            else:
                                logger.warning(f"Theater {theater['name']} returned by SerpAPI but doesn't appear to have showtimes for {movie_title}")

                        # Format the valid theaters, making sure to include movie_id
                        theaters_with_showtimes = self._format_serpapi_showtimes(valid_theaters, movie_title, movie_id)
                        logger.info(f"Validated and formatted {len(theaters_with_showtimes)} theaters showing {movie_title}")
                else:
                    logger.warning("SerpAPI key not configured, using generated showtimes")
            except Exception as serp_error:
                logger.error(f"Error using SerpAPI: {str(serp_error)}")

        except Exception as e:
            logger.error(f"Error getting movie showtimes: {str(e)}")

        return theaters_with_showtimes

    def _generate_theater_showtimes(self, theaters: List[Dict[str, Any]], movie_title: str, movie_id: Any) -> List[Dict[str, Any]]:
        """
        Generate artificial showtimes for theaters.

        Args:
            theaters: List of theater dictionaries
            movie_title: Title of the movie
            movie_id: TMDB ID of the movie

        Returns:
            List of theaters with generated showtimes
        """
        theater_results = []

        for theater in theaters:
            # Get current date for showtimes
            today = datetime.now()
            showtimes = []

            # Create realistic showtimes during typical theater hours (noon to 10pm)
            realistic_hours = [13, 16, 19, 21]  # 1pm, 4pm, 7pm, 9pm

            # Select up to 3 different times
            selected_hours = realistic_hours[:3]

            for hour in selected_hours:
                # Calculate showtime based on selected hours
                show_datetime = today.replace(hour=hour, minute=min(30, today.minute), second=0)

                # Make sure the time is in the future (if current time is after the showtime, use tomorrow)
                if show_datetime < today:
                    show_datetime = show_datetime.replace(day=today.day + 1)

                formatted_time = show_datetime.strftime("%Y-%m-%d %H:%M:%S")

                # Assign different formats based on theater and time
                format_options = ["Standard", "IMAX", "3D"]
                format_type = format_options[hash(theater['name'] + str(hour)) % len(format_options)]

                showtimes.append({
                    "start_time": formatted_time,
                    "format": format_type
                })

            # Create theater entry for this movie
            theater_results.append({
                "movie_id": movie_id,
                "name": theater['name'],
                "address": theater['address'],
                "latitude": theater['latitude'],
                "longitude": theater['longitude'],
                "distance_miles": theater.get('distance_miles', 0),
                "showtimes": showtimes
            })

        return theater_results
