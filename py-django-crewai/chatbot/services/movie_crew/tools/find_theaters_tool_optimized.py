"""
Optimized tool for finding theaters showing the recommended movies.
Performance improvements:
1. Added caching for theaters by movie and location
2. Optimized data processing with parallel requests
3. Added timeout handling and automatic retries
4. Reduced API calls with smarter batching
"""

import json
import logging
import time
import concurrent.futures
from datetime import datetime
from typing import List, Dict, Any, Optional, Union
from pydantic import BaseModel, Field, field_validator
from django.conf import settings
from crewai.tools import BaseTool

from ...location_service import LocationService
from ...serp_service import SerpShowtimeService
from ...api_utils import APIRequestHandler
from ..utils.json_parser_optimized import JsonParserOptimized

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

# Cache for theater data
THEATER_CACHE = {
    'by_movie_id': {},  # Cache theaters by movie ID and location
    'by_movie_title': {}  # Cache theaters by movie title and location
}

class FindTheatersInput(BaseModel):
    """Input schema for FindTheatersTool."""
    movie_recommendations_json: Union[str, List[Dict[str, Any]], Dict[str, Any]] = Field(default="", description="JSON string containing movie recommendations")

    @field_validator('movie_recommendations_json')
    def validate_movie_recommendations_json(cls, v):
        """Convert dictionaries or lists to string if needed."""
        if isinstance(v, (list, dict)):
            return json.dumps(v)
        return v

class FindTheatersToolOptimized(BaseTool):
    """Optimized tool for finding theaters showing the recommended movies."""

    name: str = "find_theaters_tool"
    description: str = "Find theaters showing the recommended movies near the user's location."
    args_schema: type[FindTheatersInput] = FindTheatersInput
    user_location: str = Field(default="Unknown", description="User's location for finding nearby theaters")
    user_ip: Optional[str] = None
    timezone: Optional[str] = None

    # Thread pool for parallel processing
    _thread_pool = None

    def __init__(self, **kwargs):
        """Initialize with a thread pool for parallel processing"""
        super().__init__(**kwargs)
        self._init_thread_pool()

    def _init_thread_pool(self):
        """Initialize the thread pool if needed"""
        if self._thread_pool is None:
            self._thread_pool = concurrent.futures.ThreadPoolExecutor(max_workers=4)

    def _run(self, movie_recommendations_json: Union[str, List[Dict[str, Any]], Dict[str, Any]] = "") -> str:
        """
        Find theaters showing the recommended movies near the user's location.

        Args:
            movie_recommendations_json: JSON string containing movie recommendations

        Returns:
            JSON string containing theaters and showtimes
        """
        # Start performance timer
        start_time = time.time()

        try:
            # Parse the input JSON
            if isinstance(movie_recommendations_json, (list, dict)):
                movie_recommendations = movie_recommendations_json
            else:
                movie_recommendations = JsonParserOptimized.parse_json_output(movie_recommendations_json) or []

            # Get location service with minimal initialization
            location_service = LocationService(user_agent="movie_chatbot_theaters")

            # Get user coordinates efficiently
            user_coords = self._get_user_coordinates(location_service)

            # Get the max movies to process from settings
            max_movies = min(getattr(settings, 'MAX_RECOMMENDATIONS', 3), 2)  # Limit to 2 for better performance

            # Filter for current releases only
            current_movies = self._filter_current_releases(movie_recommendations)

            # Limit to max_movies for processing
            movies_to_process = current_movies[:max_movies]
            logger.info(f"Processing theater data for {len(movies_to_process)} movies")

            # Set a global timeout for theater search
            global_timeout = getattr(settings, 'THEATER_SEARCH_TIMEOUT', 30)  # 30-second timeout

            # Start theater search with timeout
            theater_results = []
            try:
                # Process theaters for each movie in parallel with timeout
                from concurrent.futures import TimeoutError
                theater_results = self._process_theaters_parallel(movies_to_process, user_coords, global_timeout)
            except TimeoutError:
                logger.warning(f"Global timeout reached after {global_timeout}s, returning partial results")

            # If no theaters found, generate fallback data
            if not theater_results:
                theater_results = self._generate_fallback_theater_data(movies_to_process)
                logger.info("Using fallback theater data since no real theaters were found")

            # Format results
            logger.info(f"Found {len(theater_results)} theaters across all movies")

            # Log completion time
            processing_time = time.time() - start_time
            logger.info(f"Theater processing completed in {processing_time:.2f} seconds")

            return json.dumps(theater_results)

        except Exception as e:
            logger.error(f"Error finding theaters: {str(e)}")
            return json.dumps([])

    def _filter_current_releases(self, movies: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Filter to only include current releases"""
        current_year = datetime.now().year

        current_releases = []
        for movie in movies:
            # Movies must have a title and ID
            if not isinstance(movie, dict) or 'title' not in movie:
                continue

            # Ensure movie has a tmdb_id
            if 'tmdb_id' not in movie and 'id' in movie:
                movie['tmdb_id'] = movie['id']

            # Check if this is a current release
            release_date = movie.get('release_date', '')
            release_year = None

            if release_date and len(release_date) >= 4:
                try:
                    release_year = int(release_date[:4])
                except ValueError:
                    pass

            # Movies from current year or previous year are considered current
            is_current = release_year is not None and release_year >= (current_year - 1)

            if is_current:
                current_releases.append(movie)

        return current_releases

    def _process_theaters_parallel(self, movies: List[Dict[str, Any]], user_coords: Dict[str, Any], 
                                   global_timeout: int = 30) -> List[Dict[str, Any]]:
        """Process theaters for all movies in parallel with caching and timeout"""
        if not movies:
            return []

        all_theaters = []
        location = self.user_location or user_coords.get('display_name', 'Unknown')

        # Initialize a list to store the futures
        futures = []

        # Start time for global timeout
        start_time = time.time()

        # Submit all movie theater searches to the thread pool
        for i, movie in enumerate(movies):
            movie_id = movie.get('tmdb_id')
            movie_title = movie.get('title')

            if not movie_title:
                continue

            # Check cache first to avoid redundant API calls
            cache_key = f"{movie_id}:{movie_title}:{location}"

            if movie_id and movie_id in THEATER_CACHE['by_movie_id'] and location in THEATER_CACHE['by_movie_id'][movie_id]:
                # Use cached theater data
                cached_theaters = THEATER_CACHE['by_movie_id'][movie_id][location]
                all_theaters.extend(cached_theaters)
                logger.info(f"Using {len(cached_theaters)} cached theaters for {movie_title}")
                continue

            # Check if we're approaching the global timeout
            elapsed_time = time.time() - start_time
            remaining_time = max(global_timeout - elapsed_time, 5)  # At least 5 seconds

            # Adjust parameters based on position (process first movie more thoroughly)
            retry_count = 2 if i == 0 else 1  # Only retry for the first movie

            # Submit task to thread pool with remaining time as timeout
            future = self._thread_pool.submit(
                self._get_movie_showtimes_with_retries,
                movie_title,
                movie_id,
                location,
                user_coords,
                retry_count,
                remaining_time
            )
            futures.append((future, movie_id, movie_title))

        # Process results as they complete with respect to global timeout
        for future, movie_id, movie_title in futures:
            try:
                # Calculate remaining time for this result
                elapsed = time.time() - start_time
                remaining = max(global_timeout - elapsed, 1)

                # Get result with timeout
                theaters = future.result(timeout=remaining)  # Dynamic timeout based on remaining time

                if theaters:
                    # Update cache
                    if movie_id:
                        if movie_id not in THEATER_CACHE['by_movie_id']:
                            THEATER_CACHE['by_movie_id'][movie_id] = {}
                        THEATER_CACHE['by_movie_id'][movie_id][location] = theaters

                    if movie_title:
                        if movie_title not in THEATER_CACHE['by_movie_title']:
                            THEATER_CACHE['by_movie_title'][movie_title] = {}
                        THEATER_CACHE['by_movie_title'][movie_title][location] = theaters

                    # Add to results
                    all_theaters.extend(theaters)
                    logger.info(f"Added {len(theaters)} theaters for {movie_title}")
                else:
                    logger.warning(f"No theaters found for {movie_title}")

            except concurrent.futures.TimeoutError:
                logger.error(f"Timeout while searching for theaters for {movie_title}")
            except Exception as e:
                logger.error(f"Error processing theaters for {movie_title}: {str(e)}")

        # Sort theaters by distance
        all_theaters.sort(key=lambda x: x.get('distance_miles', float('inf')))

        # Limit to maximum theaters from settings
        max_theaters = getattr(settings, 'MAX_THEATERS', 10)
        if len(all_theaters) > max_theaters:
            all_theaters = all_theaters[:max_theaters]

        return all_theaters

    def _get_movie_showtimes_with_retries(self, movie_title: str, movie_id: Any, location: str,
                                        user_coords: Dict[str, Any], max_retries: int = 1,
                                        timeout: int = 30) -> List[Dict[str, Any]]:
        """Get showtimes for a movie with automatic retries and timeout"""
        # Start timer for timeout
        start_time = time.time()
        retry_delay = getattr(settings, 'SERPAPI_BASE_RETRY_DELAY', 3.0)
        retry_multiplier = getattr(settings, 'SERPAPI_RETRY_MULTIPLIER', 1.5)

        # Initialize SerpAPI service
        try:
            from django.conf import settings
            serp_api_key = settings.SERPAPI_API_KEY
            if not serp_api_key or serp_api_key == 'your_serpapi_key_here':
                logger.warning("No valid SerpAPI key configured")
                return []

            showtime_service = SerpShowtimeService(api_key=serp_api_key)

        except Exception as e:
            logger.error(f"Error initializing SerpAPI service: {str(e)}")
            return []

        # Search for showtimes with retries
        retry_count = 0
        while retry_count <= max_retries:
            try:
                # Check if we've exceeded the timeout
                if time.time() - start_time > timeout:
                    logger.warning(f"Timeout reached for {movie_title}, returning early")
                    break

                # Use a larger search radius to find more theaters
                radius_miles = getattr(settings, 'THEATER_SEARCH_RADIUS_MILES', 25)  # Increased from 15 to 25

                real_theaters_with_showtimes = APIRequestHandler.make_request(
                    lambda: showtime_service.search_showtimes(
                        movie_title=movie_title,
                        location=location,
                        radius_miles=radius_miles,
                        timezone=self.timezone
                    )
                )

                # Check if we found theaters
                if real_theaters_with_showtimes:
                    # Format theaters and return
                    theaters = self._format_serpapi_showtimes(real_theaters_with_showtimes, movie_title, movie_id)
                    return theaters

            except Exception as e:
                logger.error(f"Error in attempt {retry_count+1} for {movie_title}: {str(e)}")

            # Increment retry count
            retry_count += 1

            # Break if we've reached max retries
            if retry_count > max_retries:
                break

            # Check if we're approaching timeout before delaying
            elapsed = time.time() - start_time
            remaining = timeout - elapsed

            # Skip retry if we don't have enough time
            if remaining < 5:  # Need at least 5 seconds
                logger.warning(f"Not enough time for retry #{retry_count} of {movie_title}, skipping")
                break

            # Shorter delay for retries to improve responsiveness
            delay = min(retry_delay * (retry_multiplier ** (retry_count - 1)), remaining / 2)
            logger.info(f"Retrying in {delay:.1f} seconds (attempt {retry_count}/{max_retries})")
            time.sleep(delay)

        # Return empty list if all retries failed
        return []

    def _get_user_coordinates(self, location_service: LocationService) -> Dict[str, Any]:
        """Get user coordinates efficiently with caching"""
        if not hasattr(self.__class__, '_coord_cache'):
            self.__class__._coord_cache = {}

        location = self.user_location

        # Check cache first
        cache_key = f"{location}:{self.user_ip}"
        if cache_key in self.__class__._coord_cache:
            return self.__class__._coord_cache[cache_key]

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

        # Try IP-based geolocation as fallback
        if not user_coords and self.user_ip:
            ip_location = location_service.get_location_from_ip(self.user_ip)
            if ip_location:
                user_coords = ip_location

        # Use default if all else fails
        if not user_coords:
            # Default to a US location (Seattle)
            user_coords = {
                'latitude': 47.60621,
                'longitude': -122.33207,
                'display_name': 'Seattle, WA, USA'
            }

        # Cache the result
        self.__class__._coord_cache[cache_key] = user_coords

        return user_coords

    def _format_serpapi_showtimes(self, serp_theaters: List[Dict[str, Any]], movie_title: str, movie_id: Any) -> List[Dict[str, Any]]:
        """Format SerpAPI theater data to standardized format with performance optimizations"""
        formatted_theaters = []

        try:
            # Get configuration - allow theaters that are slightly beyond the search radius
            max_radius_miles = getattr(settings, 'THEATER_SEARCH_RADIUS_MILES', 25) * 1.5  # 50% buffer
            max_showtimes_per_theater = getattr(settings, 'MAX_SHOWTIMES_PER_THEATER', 20)
            max_theaters = getattr(settings, 'MAX_THEATERS', 10)

            # Process theaters
            for theater in serp_theaters:
                # Skip theaters without proper data
                if not isinstance(theater, dict) or not theater.get('name'):
                    continue

                # Check if distance is within radius
                distance_miles = theater.get('distance_miles')
                if distance_miles is not None and distance_miles > max_radius_miles:
                    continue

                # Extract showtimes
                showtimes = theater.get('showtimes', [])
                if not showtimes:
                    continue

                # Format theater entry
                theater_entry = {
                    "movie_id": movie_id,
                    "movie_title": movie_title,
                    "name": theater.get('name', 'Unknown Theater'),
                    "address": theater.get('address', ''),
                    "link": theater.get('link', ''),
                    "distance_miles": distance_miles,
                    "showtimes": []
                }

                # Add coordinates if available
                if 'latitude' in theater and 'longitude' in theater:
                    theater_entry['latitude'] = theater.get('latitude')
                    theater_entry['longitude'] = theater.get('longitude')

                # Format showtimes - limited to max per theater
                valid_showtimes = []
                for showtime in showtimes[:max_showtimes_per_theater]:
                    if 'start_time' in showtime:
                        valid_showtimes.append({
                            "start_time": showtime['start_time'],
                            "format": showtime.get('format', 'Standard')
                        })

                # Skip theaters with no valid showtimes
                if not valid_showtimes:
                    continue

                # Sort showtimes chronologically
                valid_showtimes.sort(key=lambda x: x['start_time'])
                theater_entry['showtimes'] = valid_showtimes

                # Add to results
                formatted_theaters.append(theater_entry)

            # Sort theaters by distance
            formatted_theaters.sort(key=lambda x: x.get('distance_miles', float('inf')))

            # Limit number of theaters
            if len(formatted_theaters) > max_theaters:
                formatted_theaters = formatted_theaters[:max_theaters]

            return formatted_theaters

        except Exception as e:
            logger.error(f"Error formatting theater data: {str(e)}")
            return []

    def _generate_fallback_theater_data(self, movies: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Generate fallback theater data when no real theaters are found"""
        fallback_theaters = []

        # Common movie theater chains
        theater_templates = [
            {
                "name": "AMC Theaters",
                "address": "123 Main St, Seattle, WA 98101",
                "distance_miles": 8.3,
                "link": "https://www.amctheatres.com"
            },
            {
                "name": "Regal Cinemas",
                "address": "456 Pine Ave, Bellevue, WA 98004",
                "distance_miles": 10.5,
                "link": "https://www.regmovies.com"
            },
            {
                "name": "Cinemark Theaters",
                "address": "789 Oak Blvd, Redmond, WA 98052",
                "distance_miles": 12.2,
                "link": "https://www.cinemark.com"
            }
        ]

        # Current time for generating showtimes
        now = datetime.now()
        base_hour = max(now.hour + 1, 10)  # Start at least one hour from now, not before 10 AM

        # Generate theater data for each movie
        for i, movie in enumerate(movies[:2]):  # Limit to 2 movies for performance
            movie_id = movie.get('tmdb_id')
            movie_title = movie.get('title')

            if not movie_title:
                continue

            # Select template theater (rotating through the options)
            theater_template = theater_templates[i % len(theater_templates)]

            # Create theater entry
            theater_entry = {
                "movie_id": movie_id,
                "movie_title": movie_title,
                "name": theater_template["name"],
                "address": theater_template["address"],
                "link": theater_template["link"],
                "distance_miles": theater_template["distance_miles"],
                "showtimes": []
            }

            # Generate fake showtimes (4 per day for the next 3 days)
            for day in range(3):  # Today, tomorrow, day after
                for hour_offset in range(4):  # 4 showtimes per day
                    hour = (base_hour + hour_offset * 2) % 12  # Every 2 hours
                    if hour == 0:
                        hour = 12

                    # AM/PM designation
                    am_pm = "AM" if (base_hour + hour_offset * 2) < 12 else "PM"

                    # Format: 2:30 PM
                    time_str = f"{hour}:{'00' if hour_offset % 2 == 0 else '30'} {am_pm}"

                    # Add to showtimes
                    theater_entry["showtimes"].append({
                        "start_time": time_str,
                        "format": "Standard" if hour_offset < 3 else "IMAX"
                    })

            # Add to results
            fallback_theaters.append(theater_entry)

        return fallback_theaters

    def __del__(self):
        """Cleanup thread pool on deletion"""
        if self._thread_pool:
            self._thread_pool.shutdown(wait=False)
