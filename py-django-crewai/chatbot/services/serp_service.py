"""
SerpAPI service for the movie chatbot.
This module provides integration with SerpAPI for fetching real movie showtimes.
"""

import logging
import json
import functools
from typing import List, Dict, Any, Optional
from serpapi import GoogleSearch
from datetime import datetime, timedelta
import zoneinfo

from .api_utils import APIRequestHandler

# Configure logger
logger = logging.getLogger('chatbot.serp_service')

class SerpShowtimeService:
    """Service for fetching movie showtimes using SerpAPI."""

    @staticmethod
    def _sanitize_params(params: Dict[str, Any], sensitive_keys: List[str]) -> Dict[str, Any]:
        """
        Redact sensitive fields in a dictionary, including nested dictionaries.

        Args:
            params: Dictionary containing parameters to sanitize.
            sensitive_keys: List of keys to redact in the dictionary.

        Returns:
            A sanitized copy of the dictionary with sensitive fields redacted.
        """
        def redact(value):
            if isinstance(value, dict):
                return {k: redact(v) for k, v in value.items()}
            return '[REDACTED]' if value in sensitive_keys else value

        return {k: redact(v) if k in sensitive_keys else v for k, v in params.items()}

    def __init__(self, api_key: str):
        """Initialize the SerpAPI service.

        Args:
            api_key: SerpAPI API key
        """
        self.api_key = api_key

    def search_movies_in_theaters(self, query: str = "", location: str = "United States") -> List[Dict[str, Any]]:
        """Search for movies currently playing in theaters matching the query.

        Args:
            query: Query to search for (e.g., "family movies", "action")
            location: Location to search in (default: "United States")

        Returns:
            List of movies currently playing in theaters
        """
        try:
            logger.info(f"Searching for movies in theaters matching '{query}' in {location}")

            # Construct parameters for SerpAPI
            params = {
                "engine": "google_movies",
                "q": query if query else "movies in theaters now",
                "location": location,
                "hl": "en",
                "gl": "us",
                "api_key": self.api_key
            }

            # Create the GoogleSearch object
            search = GoogleSearch(params)

            # Execute the search with retry mechanism
            # Wrap in lambda to properly handle the timeout parameter
            results = APIRequestHandler.make_request(
                lambda *args, **kwargs: search.get_dict()
            )

            # Process and format the results
            movies = []

            # Check if we have any results
            if 'movies_results' not in results:
                logger.warning("No movies found in SerpAPI results")
                return []

            # Process each movie in the results
            for movie_data in results.get('movies_results', []):
                movie = {
                    "title": movie_data.get('title', 'Unknown Title'),
                    "description": movie_data.get('description', ''),
                    "thumbnail": movie_data.get('thumbnail', ''),
                    "rating": movie_data.get('rating', 0),
                    "year": movie_data.get('year'),
                    "release_date": movie_data.get('release_date', ''),
                    "genres": movie_data.get('genres', []),
                    "id": None  # We'll need to look up the TMDB ID separately
                }

                # If we have a year, construct a release date if it's missing
                if not movie.get('release_date') and movie.get('year'):
                    movie['release_date'] = f"{movie.get('year')}-01-01"

                movies.append(movie)

            logger.info(f"Found {len(movies)} movies matching '{query}' in theaters")
            return movies

        except Exception as e:
            logger.error(f"Error searching for movies in theaters: {str(e)}")
            return []

    def search_showtimes(self, movie_title: str, location: str, radius_miles: int = 25, timezone: str = None) -> List[Dict[str, Any]]:
        """Search for movie showtimes for a specific movie in a location.

        Args:
            movie_title: Title of the movie to search showtimes for
            location: Location to search showtimes in (city name or zip code)
            radius_miles: Search radius in miles (default: 25)
            timezone: User's timezone string (e.g., 'America/Los_Angeles')

        Returns:
            List of theater dictionaries with showtimes
        """
        # Store timezone for use in processing
        self.user_timezone = timezone
        # Add performance measurement
        import time
        start_time = time.time()
        try:
            logger.info(f"Searching showtimes for '{movie_title}' in {location}")

            # Determine date range (today and the next 3 days)
            today = datetime.now()
            date_range = []
            for i in range(3):  # Today + next 2 days
                date = today + timedelta(days=i)
                # Format as YYYY-MM-DD
                date_range.append(date.strftime("%Y-%m-%d"))

            # Construct parameters for SerpAPI following documented format
            params = {
                "q": f"{movie_title} theater",  # Format required by SerpAPI for theater results
                "location": location,
                "hl": "en",
                "gl": "us",
                "api_key": self.api_key
            }

            # Create the GoogleSearch object
            search = GoogleSearch(params)

            # Execute the search with retry mechanism
            results = APIRequestHandler.make_request(
                lambda *args, **kwargs: search.get_dict()
            )

            # Log complete error message if available
            if 'error' in results:
                error_message = results.get('error', 'Unknown error')
                # Log the detailed error and complete parameters for debugging
                logger.error(f"SerpAPI returned error: {error_message}")
                sanitized_params = self._sanitize_params(params, sensitive_keys=['api_key', 'location'])
                logger.error("Request parameters contain sensitive data and have been redacted.")
                return []

            # Process and format the results
            theaters = self._parse_serp_results(results, movie_title)

            logger.info(f"Found {len(theaters)} theaters with showtimes for '{movie_title}' in {location}")
            return theaters

        except Exception as e:
            logger.error(f"Error searching showtimes: {str(e)}")
            return []

    def _parse_serp_results(self, results: Dict[str, Any], movie_title: str) -> List[Dict[str, Any]]:
        """Parse the SerpAPI results and format them for our application.

        Args:
            results: SerpAPI results dictionary
            movie_title: Title of the movie used for filtering results

        Returns:
            List of theater dictionaries with formatted showtimes
        """

        # Log the top-level keys to understand the structure
        result_keys = list(results.keys()) if isinstance(results, dict) else []
        logger.info(f"SerpAPI result keys: {result_keys}")

        # Check for showtimes data
        has_showtimes = 'showtimes' in results
        logger.info(f"Results contain showtimes data: {has_showtimes}")

        theaters = []

        try:
            # Check if we have showtimes data
            if not has_showtimes or not results.get('showtimes'):
                logger.warning(f"No showtimes found in SerpAPI results for '{movie_title}'")
                return []

            # Get the maximum search radius in miles from Django settings
            # We'll use this to filter theaters that are too far away
            try:
                from django.conf import settings
                max_radius_miles = getattr(settings, 'THEATER_SEARCH_RADIUS_MILES', 15)
                logger.info(f"Using maximum theater search radius: {max_radius_miles} miles")
            except Exception as e:
                logger.warning(f"Could not get THEATER_SEARCH_RADIUS_MILES from settings: {str(e)}")
                max_radius_miles = 15  # Default to 15 miles

            # Track unique theaters across all days
            theater_map = {}

            # Process each day in the showtimes data
            for day_data in results['showtimes']:
                day_name = day_data.get('day', 'Today')
                logger.info(f"Processing showtimes for day: {day_name}")

                # Extract date information from day_name string
                day_date = self._extract_date_from_day_string(day_name)

                # Process each theater for this day
                for theater_data in day_data.get('theaters', []):
                    theater_name = theater_data.get('name', 'Unknown Theater')
                    theater_address = theater_data.get('address', '')
                    theater_distance_str = theater_data.get('distance', '')
                    theater_link = theater_data.get('link', '')

                    # Parse distance (e.g., "21.0 mi" -> 21.0)
                    distance_miles = self._parse_distance(theater_distance_str)

                    # Skip theaters beyond our search radius
                    if distance_miles is not None and distance_miles > max_radius_miles:
                        logger.info(f"Skipping theater '{theater_name}' - distance {distance_miles} miles exceeds maximum radius of {max_radius_miles} miles")
                        continue

                    logger.info(f"Processing theater: {theater_name} (Distance: {distance_miles} miles)")

                    # Process showing array - this should be a list of showing objects
                    showing_array = theater_data.get('showing', [])
                    if not showing_array:
                        logger.warning(f"Theater '{theater_name}' has no showing data")
                        continue

                    # Track showtimes for this theater on this day
                    theater_showtimes = []

                    # Process each showing entry in the array
                    for showing in showing_array:
                        # The 'time' field should be an array of time strings (e.g., ["1:30pm", "4:00pm"])
                        time_array = showing.get('time', [])

                        if not time_array:
                            logger.warning(f"Showing for theater '{theater_name}' has no time data")
                            continue

                        logger.info(f"Processing {len(time_array)} showtimes for theater '{theater_name}'")

                        # Process each time string
                        for time_str in time_array:
                            try:
                                # Parse time string (e.g., "5:00pm")
                                time_obj = datetime.strptime(time_str, "%I:%M%p")

                                # Combine the date with the time
                                start_time = datetime.combine(day_date, time_obj.time())

                                # If we have timezone info, make datetime timezone-aware
                                timezone_info = getattr(self, 'user_timezone', None)
                                if timezone_info:
                                    try:
                                        tz = zoneinfo.ZoneInfo(timezone_info)
                                        start_time = start_time.replace(tzinfo=tz)
                                        logger.info(f"Applied timezone {timezone_info} to showtime")
                                    except Exception as tz_error:
                                        logger.warning(f"Could not apply timezone {timezone_info}: {str(tz_error)}")

                                # Create a standardized showtime object with timezone info
                                showtime_info = {
                                    "start_time": start_time.isoformat(),  # Will include TZ info if available
                                    "timezone": timezone_info,  # Store timezone string for reference
                                    "format": "Standard"  # Default format since API doesn't provide format info
                                }
                                theater_showtimes.append(showtime_info)
                                logger.debug(f"Added showtime: {start_time.isoformat()} for '{theater_name}'")
                            except (ValueError, TypeError) as e:
                                logger.warning(f"Error parsing time '{time_str}': {str(e)}")

                    # If we found any valid showtimes for this theater
                    if theater_showtimes:
                        # Create or update theater in our map
                        if theater_name not in theater_map:
                            theater_map[theater_name] = {
                                "name": theater_name,
                                "address": theater_address,
                                "link": theater_link,
                                "distance_miles": distance_miles,
                                "showtimes": []
                            }

                        # Add new showtimes to existing theater
                        theater_map[theater_name]["showtimes"].extend(theater_showtimes)
                        logger.info(f"Added {len(theater_showtimes)} showtimes for '{theater_name}' on {day_name}")

            # Convert theater map to list for return
            for theater_name, theater_info in theater_map.items():
                if theater_info["showtimes"]:
                    # Sort showtimes by datetime
                    theater_info["showtimes"].sort(key=lambda x: x["start_time"])
                    theaters.append(theater_info)
                    logger.info(f"Finalized theater '{theater_name}' with {len(theater_info['showtimes'])} total showtimes")

        except Exception as e:
            logger.error(f"Error parsing SerpAPI results: {str(e)}")
            logger.exception(e)  # Log full traceback for better debugging

        # Return the list of theaters with showtimes
        logger.info(f"Returning {len(theaters)} theaters with showtimes for '{movie_title}'")
        return theaters

    def _extract_date_from_day_string(self, day_string: str) -> datetime.date:
        """Extract date from a day string like 'TodayApr 16' or 'FriApr 18'.

        Args:
            day_string: Day string from SerpAPI

        Returns:
            Datetime date object
        """
        today = datetime.now().date()

        # If it's today, return today's date
        if day_string.startswith('Today'):
            return today

        try:
            # Map of month abbreviations to month numbers
            month_map = {
                'Jan': 1, 'Feb': 2, 'Mar': 3, 'Apr': 4,
                'May': 5, 'Jun': 6, 'Jul': 7, 'Aug': 8,
                'Sep': 9, 'Oct': 10, 'Nov': 11, 'Dec': 12
            }

            # Look for month abbreviation in the string
            month_num = None
            for month_abbr, num in month_map.items():
                if month_abbr in day_string:
                    month_num = num
                    break

            if month_num is None:
                logger.warning(f"Could not extract month from day string: '{day_string}'")
                return today

            # Extract day number using regex to find any digits
            import re
            day_match = re.search(r'\d+', day_string)
            if not day_match:
                logger.warning(f"Could not extract day from day string: '{day_string}'")
                return today

            day_num = int(day_match.group())

            # Create date object with current year
            date_obj = datetime(datetime.now().year, month_num, day_num).date()
            logger.debug(f"Extracted date {date_obj.isoformat()} from '{day_string}'")
            return date_obj

        except Exception as e:
            logger.warning(f"Error extracting date from '{day_string}': {str(e)}")
            return today

    def _parse_distance(self, distance_str: str) -> Optional[float]:
        """Parse distance string like '21.0 mi' to extract the numeric value.

        Args:
            distance_str: Distance string from SerpAPI

        Returns:
            Distance in miles as a float, or None if parsing fails
        """
        if not distance_str:
            return None

        try:
            # Split on spaces and take the first part as the numeric value
            parts = distance_str.split()
            if parts:
                return float(parts[0])
        except (ValueError, IndexError) as e:
            logger.warning(f"Could not parse distance from '{distance_str}': {str(e)}")

        return None

    def _process_theater_data(self, theater_data: Dict[str, Any], movie_title: str) -> Dict[str, Any]:
        """Process theater data from SerpAPI results.

        Args:
            theater_data: Theater data from SerpAPI
            movie_title: Movie title to match

        Returns:
            Processed theater information
        """
        # Extract theater info
        name = theater_data.get('name', 'Unknown Theater')
        address = theater_data.get('address', '')

        # Get coordinates if available
        latitude = None
        longitude = None
        if 'gps_coordinates' in theater_data:
            coords = theater_data.get('gps_coordinates', {})
            latitude = coords.get('latitude')
            longitude = coords.get('longitude')

        # Get distance if available
        distance_miles = None
        if 'distance' in theater_data:
            distance_text = theater_data.get('distance', '')
            # Extract numeric distance (e.g., "5.2 mi" -> 5.2)
            try:
                distance_miles = float(distance_text.split()[0])
            except (ValueError, IndexError):
                pass

        # Process movie showtimes
        showtimes = []

        # Handle the case where movies are within the theater data
        if 'movies' in theater_data:
            for movie in theater_data.get('movies', []):
                if self._normalize_title(movie.get('name', '')) == self._normalize_title(movie_title):
                    # Process showtimes for this movie
                    for showtime_data in movie.get('showtimes', []):
                        # Extract datetime
                        datetime_str = showtime_data.get('datetime')
                        if not datetime_str:
                            continue

                        # Extract format (e.g., "IMAX", "3D")
                        format_type = "Standard"
                        if 'amenities' in showtime_data:
                            amenities = showtime_data.get('amenities', [])
                            if amenities:
                                if any('IMAX' in amenity for amenity in amenities):
                                    format_type = "IMAX"
                                elif any('3D' in amenity for amenity in amenities):
                                    format_type = "3D"

                        showtime_info = {
                            "start_time": datetime_str,
                            "format": format_type
                        }
                        showtimes.append(showtime_info)

        # Handle the case where showing data is directly in the theater
        elif 'showing' in theater_data:
            showing_data = theater_data.get('showing', [])
            for showing in showing_data:
                for time in showing.get('time', []):
                    # Convert time format (e.g., "6:00pm") to datetime
                    try:
                        # Format time into start_time
                        time_obj = datetime.strptime(time, "%I:%M%p")
                        today = datetime.now()
                        start_time = datetime.combine(today.date(), time_obj.time())

                        showtime_info = {
                            "start_time": start_time.isoformat(),
                            "format": showing.get('type', 'Standard')
                        }
                        showtimes.append(showtime_info)
                    except (ValueError, TypeError) as e:
                        logger.warning(f"Error parsing time '{time}': {str(e)}")

        # Only return theaters that have showtimes
        if showtimes:
            return {
                "name": name,
                "address": address,
                "latitude": latitude,
                "longitude": longitude,
                "distance_miles": distance_miles,
                "showtimes": showtimes
            }
        return None

    def _theater_has_movie(self, theater_data: Dict[str, Any], movie_title: str) -> bool:
        """Check if a theater has the movie we're looking for."""
        if 'movies' not in theater_data:
            return False

        movie_titles = [self._normalize_title(movie.get('name', '')) for movie in theater_data.get('movies', [])]
        return self._normalize_title(movie_title) in movie_titles

    def _normalize_title(self, title: str) -> str:
        """Normalize movie title for comparison by removing special characters and lowercasing."""
        # Remove special characters, spaces, and convert to lowercase
        import re
        normalized = re.sub(r'[^\w\s]', '', title.lower())
        normalized = normalized.replace(' ', '')
        return normalized
