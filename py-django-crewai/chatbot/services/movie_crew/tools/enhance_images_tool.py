"""
Tool for enhancing movie images using TMDB API.
"""
import json
import logging
from typing import Dict, List, Any
import tmdbsimple as tmdb
from crewai.tools import BaseTool
from pydantic import BaseModel, Field

from ...tmdb_service import TMDBService

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class EnhanceMoviesInput(BaseModel):
    """Input schema for EnhanceMovieImagesTool."""
    movies_json: str = Field(default="", description="JSON string containing movies to enhance with better images")

class EnhanceMovieImagesTool(BaseTool):
    """Tool for enhancing movie images with high-quality versions from TMDB."""

    name: str = "enhance_movie_images_tool"
    description: str = "Enhance movies with high-quality poster images and additional metadata from TMDB."
    args_schema: type[EnhanceMoviesInput] = EnhanceMoviesInput
    tmdb_api_key: str = Field(default="", description="TMDB API key")

    def _run(self, movies_json: str = "") -> str:
        """
        Enhance movie data with high-quality images.

        Args:
            movies_json: JSON string containing movie recommendations

        Returns:
            JSON string containing enhanced movie data
        """
        # Start time for performance monitoring
        import time
        start_time = time.time()

        try:
            # Parse input JSON
            movies = json.loads(movies_json)
            if not movies:
                logger.warning("No movies to enhance")
                return "[]"

            logger.info(f"Enhancing {len(movies)} movies with parallel processing")

            # Ensure every movie has a tmdb_id field for proper enhancement
            # This is critical because sometimes the field may be 'id' instead of 'tmdb_id'
            for movie in movies:
                if not movie.get('tmdb_id') and movie.get('id'):
                    movie['tmdb_id'] = movie['id']
                    logger.info(f"Fixed TMDB ID for movie {movie.get('title')} - copied from 'id' field")
                elif not movie.get('tmdb_id') and not movie.get('id'):
                    # If neither field exists, log a warning
                    logger.warning(f"Movie {movie.get('title')} has no TMDB ID and cannot be enhanced")

            # Initialize TMDB service if needed
            if not self.tmdb_api_key:
                logger.warning("No TMDB API key provided, skipping enhancement")
                return json.dumps(movies)

            tmdb_service = TMDBService(api_key=self.tmdb_api_key)

            # Enhance movies in parallel for better performance
            enhanced_movies = tmdb_service.enhance_movies_parallel(movies)

            # Ensure all enhanced movies retain their TMDB ID and poster info
            for i, movie in enumerate(enhanced_movies):
                # Triple-check that we have a tmdb_id field
                if not movie.get('tmdb_id') and movie.get('id'):
                    movie['tmdb_id'] = movie['id']
                    logger.info(f"Post-enhancement: Fixed TMDB ID for movie {movie.get('title')}")

                # Ensure we have a poster URL, even if it's a placeholder
                if not movie.get('poster_url'):
                    logger.warning(f"Missing poster URL for {movie.get('title')} after enhancement")
                    movie['poster_url'] = f"https://via.placeholder.com/300x450?text={movie.get('title', 'Movie')}"

            # End time
            end_time = time.time()
            elapsed_time = end_time - start_time
            logger.info(f"Enhanced {len(enhanced_movies)} movies in {elapsed_time:.2f} seconds")

            return json.dumps(enhanced_movies)
        except Exception as e:
            logger.error(f"Error enhancing movies with images: {str(e)}")
            logger.exception(e)
            return movies_json  # Return original data on error
