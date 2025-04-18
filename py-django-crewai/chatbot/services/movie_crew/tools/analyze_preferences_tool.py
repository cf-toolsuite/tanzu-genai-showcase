"""
Tool for analyzing user preferences and recommending movies.
"""
import json
import logging
from datetime import datetime
from typing import List, Dict, Any
from crewai.tools import BaseTool
from pydantic import BaseModel, Field

# Get the logger
logger = logging.getLogger('chatbot.movie_crew')

class AnalyzePreferencesInput(BaseModel):
    """Input schema for AnalyzePreferencesTool."""
    movies_json: str = Field(default="", description="JSON string containing movies to analyze")

class AnalyzePreferencesTool(BaseTool):
    """Tool for analyzing user preferences and recommending the best movies."""

    name: str = "analyze_preferences_tool"
    description: str = "Analyze user preferences and recommend the best movies from the provided list."
    args_schema: type[AnalyzePreferencesInput] = AnalyzePreferencesInput

    def _run(self, movies_json: str = "") -> str:
        """
        Analyze user preferences and recommend the best movies from the provided list.

        Args:
            movies_json: JSON string containing movies to analyze

        Returns:
            JSON string containing recommended movies
        """
        try:
            # Default to empty list if the input is empty
            if not movies_json:
                movies_json = "[]"

            # Parse the input JSON
            movies = json.loads(movies_json)

            if not movies:
                logger.warning("No movies to recommend")
                return json.dumps([])  # Return empty list if no movies to recommend

            # Create better explanations based on movie details
            recommendations = []
            max_recommendations = min(3, len(movies))

            # Sort movies by recency and rating
            # First try to prioritize movies with both high rating and recent release date
            today = datetime.now()
            scored_movies = []

            for movie in movies:
                # Calculate score based on multiple factors
                score = self._calculate_movie_score(movie, today)
                scored_movies.append((movie, score))

            # Sort by score in descending order
            scored_movies.sort(key=lambda x: x[1], reverse=True)

            # Get top movies
            for movie, score in scored_movies[:max_recommendations]:
                # Ensure movie has both tmdb_id and id for compatibility
                if 'id' in movie and not 'tmdb_id' in movie:
                    movie['tmdb_id'] = movie['id']
                    logger.info(f"Set tmdb_id from id for movie: {movie.get('title')}")
                elif 'tmdb_id' in movie and not 'id' in movie:
                    movie['id'] = movie['tmdb_id']
                    logger.info(f"Set id from tmdb_id for movie: {movie.get('title')}")

                # Add TMDB URL if missing
                if 'tmdb_id' in movie and not 'tmdb_url' in movie:
                    movie['tmdb_url'] = f"https://www.themoviedb.org/movie/{movie['tmdb_id']}"
                    logger.info(f"Added TMDB URL for movie: {movie.get('title')}")

                # Include all original movie details in the recommendation
                recommendations.append(movie)

            return json.dumps(recommendations)
        except Exception as e:
            logger.error(f"Error analyzing user preferences: {str(e)}")
            return json.dumps([])

    def _calculate_movie_score(self, movie: Dict[str, Any], today: datetime) -> float:
        """
        Calculate a score for a movie based on various factors.

        Args:
            movie: Movie dictionary with details
            today: Current datetime for age calculations

        Returns:
            Calculated score (higher is better)
        """
        # Get release year if available
        release_year = None
        release_date = movie.get('release_date', '')
        if release_date and len(release_date) >= 4:
            try:
                release_year = int(release_date[:4])
            except ValueError:
                pass

        # Get rating if available
        rating = movie.get('rating', 0) or 0

        # Calculate a score based on recency and rating
        recency_score = 0
        if release_year:
            years_old = today.year - release_year
            # Recent movies get higher scores
            if years_old <= 1:
                recency_score = 5
            elif years_old <= 3:
                recency_score = 4
            elif years_old <= 5:
                recency_score = 3
            elif years_old <= 10:
                recency_score = 2
            else:
                recency_score = 1

        # Calculate total score (rating * recency)
        total_score = (rating / 2) + recency_score  # Normalize rating to 0-5 range

        # Add a bonus for current releases
        if movie.get('is_current_release', False):
            total_score += 2

        # Add a bonus for movies with good overviews
        overview = movie.get('overview', '')
        if overview and len(overview) > 100:
            total_score += 0.5

        return total_score
