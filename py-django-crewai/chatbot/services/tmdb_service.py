"""
TMDb API service for fetching movie data.
"""
import logging
import tmdbsimple as tmdb
from typing import Dict, Any, List, Optional
import concurrent.futures
import time
import requests
from urllib.parse import urljoin

from .api_utils import APIRequestHandler

logger = logging.getLogger('chatbot.tmdb_service')

class TMDBService:
    """Service for interacting with The Movie Database (TMDb) API."""

    # TMDB API base URL
    BASE_URL = "https://api.themoviedb.org/3/"

    # Image base URLs for different sizes
    IMAGE_BASE_URL = "https://image.tmdb.org/t/p/"
    POSTER_SIZES = {
        'small': 'w200',
        'medium': 'w500',
        'large': 'w780',
        'original': 'original'
    }

    def __init__(self, api_key: str):
        """Initialize the TMDb API service.

        Args:
            api_key: TMDb API key
        """
        self.api_key = api_key
        tmdb.API_KEY = api_key
        self.session = requests.Session()

    def enhance_movies_sequential(self, movies: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """
        Enhance multiple movies sequentially to ensure consistent processing order.

        Args:
            movies: List of movie dictionaries to enhance

        Returns:
            List of enhanced movie dictionaries
        """
        start_time = time.time()
        logger.info(f"Starting sequential enhancement of {len(movies)} movies")

        # Handle empty list case
        if not movies:
            return []

        # Create a copy to avoid modifying the original
        result_movies = []

        # Process each movie sequentially
        for idx, movie in enumerate(movies):
            try:
                logger.info(f"Enhancing movie {idx+1}/{len(movies)}: {movie.get('title', 'Unknown')}")
                enhanced_movie = self.enhance_movie_data(movie)
                result_movies.append(enhanced_movie)
                logger.info(f"Successfully enhanced movie {enhanced_movie.get('title', 'Unknown')}")
            except Exception as e:
                logger.error(f"Error enhancing movie at index {idx}: {str(e)}")
                # Add the original movie on error
                result_movies.append(movie)
                logger.info(f"Added original movie data for {movie.get('title', 'Unknown')} due to enhancement error")

        elapsed_time = time.time() - start_time
        logger.info(f"Sequential enhancement completed in {elapsed_time:.2f} seconds")
        return result_movies
    
    # Keeping this for backward compatibility, but it redirects to sequential processing
    def enhance_movies_parallel(self, movies: List[Dict[str, Any]], max_workers: int = 3) -> List[Dict[str, Any]]:
        """
        Legacy method - now redirects to sequential processing to avoid race conditions.
        
        Args:
            movies: List of movie dictionaries to enhance
            max_workers: No longer used

        Returns:
            List of enhanced movie dictionaries
        """
        logger.warning("enhance_movies_parallel is deprecated - using sequential processing instead")
        return self.enhance_movies_sequential(movies)

    def _make_request(self, endpoint: str, params: Dict[str, Any] = None) -> Dict[str, Any]:
        """
        Make a request to the TMDb API with retry and timeout handling.

        Args:
            endpoint: API endpoint to request
            params: Optional parameters for the request

        Returns:
            Response data as dictionary

        Raises:
            Exception: If the request fails after retries
        """
        # Ensure endpoint doesn't start with a slash
        if endpoint.startswith('/'):
            endpoint = endpoint[1:]

        # Build full URL
        url = urljoin(self.BASE_URL, endpoint)

        # Prepare parameters with API key
        request_params = {'api_key': self.api_key}
        if params:
            request_params.update(params)

        def make_tmdb_request(*args, **kwargs):
            response = self.session.get(url, params=request_params)
            response.raise_for_status()  # Raise exception for 4XX/5XX status codes
            return response.json()

        try:
            # Use our retry mechanism to make the request
            return APIRequestHandler.make_request(make_tmdb_request)
        except requests.exceptions.RequestException as e:
            logger.error(f"Error making request to TMDB API after retries: {str(e)}")
            logger.exception(e)
            # Return empty dict instead of raising to avoid breaking the application
            return {}

    def get_movie_details(self, movie_id) -> Dict[str, Any]:
        """
        Get detailed information about a movie.

        Args:
            movie_id: TMDB movie ID

        Returns:
            Movie details as dictionary
        """
        try:
            # Use tmdbsimple library for this
            movie = tmdb.Movies(movie_id)
            details = movie.info()
            return details
        except Exception as e:
            logger.error(f"Error getting movie details for ID {movie_id}: {str(e)}")
            return {}

    def get_movie_images(self, movie_id) -> Dict[str, Any]:
        """
        Get images for a movie, filtering for English language when possible.

        Args:
            movie_id: TMDB movie ID

        Returns:
            Dictionary containing posters, backdrops, and logos
        """
        try:
            # Use TMDb API to get images with language parameter
            movie = tmdb.Movies(movie_id)

            # First try to get English-language images specifically
            images_en = movie.images(include_image_language="en")

            # If we got English images with posters, use them
            if images_en and 'posters' in images_en and images_en['posters']:
                logger.info(f"Using English language images for movie ID {movie_id}")
                return images_en

            # Otherwise, get all images as fallback
            images = movie.images()

            # If we have multiple posters, try to filter for English ones
            if 'posters' in images and images['posters']:
                # Try to filter to only English language posters
                en_posters = [p for p in images['posters'] if p.get('iso_639_1') == 'en']

                # If we found English posters, replace the full list with just those
                if en_posters:
                    logger.info(f"Filtered {len(images['posters'])} posters to {len(en_posters)} English ones for movie ID {movie_id}")
                    images['posters'] = en_posters

            return images
        except Exception as e:
            logger.error(f"Error getting movie images for ID {movie_id}: {str(e)}")
            return {'posters': [], 'backdrops': [], 'logos': []}

    def search_movies(self, query: str, page: int = 1, include_adult: bool = False) -> Dict[str, Any]:
        """
        Search for movies.

        Args:
            query: Search query
            page: Page number for pagination
            include_adult: Whether to include adult content

        Returns:
            Search results
        """
        endpoint = "search/movie"
        params = {
            'query': query,
            'page': page,
            'include_adult': include_adult,
            'language': 'en-US'
        }

        try:
            return self._make_request(endpoint, params)
        except Exception as e:
            logger.error(f"Error searching for movies with query '{query}': {str(e)}")
            return {'results': []}

    def get_now_playing(self, page: int = 1) -> Dict[str, Any]:
        """
        Get movies currently playing in theaters.

        Args:
            page: Page number for pagination

        Returns:
            List of movies now playing
        """
        endpoint = "movie/now_playing"
        params = {
            'page': page,
            'language': 'en-US'
        }

        try:
            return self._make_request(endpoint, params)
        except Exception as e:
            logger.error(f"Error getting now playing movies: {str(e)}")
            return {'results': []}

    def get_best_poster_url(self, movie_id, size: str = 'original') -> str:
        """
        Get the best available poster URL for a movie.

        Args:
            movie_id: TMDB movie ID
            size: Desired poster size ('small', 'medium', 'large', 'original')

        Returns:
            URL to the best poster image, or empty string if none found
        """
        # Validate size parameter
        if size not in self.POSTER_SIZES:
            size = 'original'

        size_path = self.POSTER_SIZES[size]

        try:
            # Get movie images
            images = self.get_movie_images(movie_id)

            # If we have posters, return the first one (usually the primary poster)
            if images and 'posters' in images and images['posters']:
                poster = images['posters'][0]  # Use the first poster
                return f"{self.IMAGE_BASE_URL}{size_path}{poster['file_path']}"

            # If no posters in images response, try the movie details which should have a poster_path
            movie = self.get_movie_details(movie_id)
            if movie and 'poster_path' in movie and movie['poster_path']:
                return f"{self.IMAGE_BASE_URL}{size_path}{movie['poster_path']}"

            # No poster found
            return ""

        except Exception as e:
            logger.error(f"Error getting best poster URL for movie ID {movie_id}: {str(e)}")
            return ""

    def enhance_movie_data(self, movie_data: Dict[str, Any]) -> Dict[str, Any]:
        """
        Enhance movie data with additional information and high-quality images.

        Args:
            movie_data: Movie data dictionary with at least a tmdb_id field

        Returns:
            Enhanced movie data with additional fields
        """
        # Make a copy of the input data
        enhanced_data = dict(movie_data)

        # If no TMDB ID, we can't enhance
        if 'tmdb_id' not in enhanced_data or not enhanced_data['tmdb_id']:
            logger.warning(f"Movie {enhanced_data.get('title', 'Unknown')} has no TMDB ID, skipping enhancement")
            return enhanced_data

        movie_id = enhanced_data['tmdb_id']

        try:
            # Get movie details for additional information
            movie_details = self.get_movie_details(movie_id)

            # Add additional fields if available
            if movie_details:
                # Update poster with high-quality version
                if 'poster_path' in movie_details and movie_details['poster_path']:
                    enhanced_data['poster_url'] = f"{self.IMAGE_BASE_URL}original{movie_details['poster_path']}"

                # Add backdrop if available
                if 'backdrop_path' in movie_details and movie_details['backdrop_path']:
                    enhanced_data['backdrop_url'] = f"{self.IMAGE_BASE_URL}original{movie_details['backdrop_path']}"

                # Add genres if available
                if 'genres' in movie_details and movie_details['genres']:
                    enhanced_data['genres'] = [genre['name'] for genre in movie_details['genres']]

                # Add rating if available
                if 'vote_average' in movie_details:
                    enhanced_data['rating'] = movie_details['vote_average']

                # Add more detailed information
                for field in ['tagline', 'runtime', 'vote_count', 'status', 'homepage']:
                    if field in movie_details and movie_details[field]:
                        enhanced_data[field] = movie_details[field]

            # Try to get better poster from images endpoint
            images = self.get_movie_images(movie_id)
            if images and 'posters' in images and images['posters']:
                # Use the first poster (usually the primary one)
                enhanced_data['poster_url'] = f"{self.IMAGE_BASE_URL}original{images['posters'][0]['file_path']}"

                # Add additional poster URLs at different sizes
                enhanced_data['poster_urls'] = {
                    size: f"{self.IMAGE_BASE_URL}{self.POSTER_SIZES[size]}{images['posters'][0]['file_path']}"
                    for size in self.POSTER_SIZES
                }

                # Add a list of all poster URLs if more than one is available
                if len(images['posters']) > 1:
                    enhanced_data['all_posters'] = [
                        f"{self.IMAGE_BASE_URL}original{poster['file_path']}"
                        for poster in images['posters'][:5]  # Limit to first 5 posters
                    ]

            # Ensure is_current_release flag is preserved
            if 'is_current_release' in movie_data:
                enhanced_data['is_current_release'] = movie_data['is_current_release']

            logger.info(f"Enhanced movie data for {enhanced_data.get('title', 'Unknown')} with TMDB data")
            return enhanced_data

        except Exception as e:
            logger.error(f"Error enhancing movie data for ID {movie_id}: {str(e)}")
            return enhanced_data
