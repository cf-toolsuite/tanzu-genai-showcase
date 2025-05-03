"""
Movie Crew Integration Service
This module provides a unified interface to the movie crew functionality,
delegating to the optimized enhanced implementation.
"""

from django.conf import settings
from .movie_crew_optimized_enhanced import MovieCrewOptimizedEnhanced

# Create a service class that delegates to the optimized implementation
class MovieCrewService:
    """Service class for movie crew operations that delegates to the optimized implementation."""

    @staticmethod
    def process_query(query, conversation_history, first_run_mode=True, user_location=None, user_ip=None, timezone=None):
        """
        Process a user query and return movie recommendations.

        Args:
            query: The user's query
            conversation_history: Previous conversation messages
            first_run_mode: Whether to operate in first run mode (with theaters)
            user_location: Optional user location for theater search
            user_ip: Optional user IP address
            timezone: Optional timezone string

        Returns:
            Dict with response text and movie recommendations
        """
        # Check if First Run mode is disabled via feature flag
        if first_run_mode and not settings.FEATURES.get('ENABLE_FIRST_RUN_MODE', True):
            # Force casual mode if First Run mode is disabled
            first_run_mode = False
        # Get configuration from settings
        api_key = settings.LLM_CONFIG['api_key']
        base_url = settings.LLM_CONFIG.get('base_url')
        model = settings.LLM_CONFIG.get('model', 'gpt-4o-mini')
        tmdb_api_key = settings.TMDB_API_KEY

        # Create the optimized manager
        manager = MovieCrewOptimizedEnhanced(
            api_key=api_key,
            base_url=base_url,
            model=model,
            tmdb_api_key=tmdb_api_key,
            user_location=user_location,
            user_ip=user_ip,
            timezone=timezone
        )

        # Process the query using the enhanced implementation
        return manager.process_query(
            query=query,
            conversation_history=conversation_history,
            first_run_mode=first_run_mode
        )
