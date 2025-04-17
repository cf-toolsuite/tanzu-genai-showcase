"""
Movie Chatbot CrewAI implementation - V2 Wrapper.
This module is a thin wrapper around the modular components in movie_crew/ subfolder.
"""

import logging
from typing import List, Dict, Any, Optional

from .movie_crew.manager import MovieCrewManager as ModularMovieCrewManager

# Configure logging
logger = logging.getLogger('chatbot.movie_crew')

class MovieCrewManager:
    """
    Wrapper for the movie recommendation crew.
    This class delegates to the modular implementation in movie_crew/manager.py.
    """

    def __init__(
        self,
        api_key: str,
        base_url: Optional[str] = None,
        model: str = "gpt-4o-mini",
        tmdb_api_key: Optional[str] = None,
        user_location: Optional[str] = None,
        user_ip: Optional[str] = None,
        timezone: Optional[str] = None
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
        """
        logger.info("Initializing MovieCrewManager V2 wrapper")

        # Initialize the underlying modular manager
        self.manager = ModularMovieCrewManager(
            api_key=api_key,
            base_url=base_url,
            model=model,
            tmdb_api_key=tmdb_api_key,
            user_location=user_location,
            user_ip=user_ip,
            timezone=timezone
        )

    def process_query(self, query: str, conversation_history: List[Dict[str, str]], first_run_mode: bool = True) -> Dict[str, Any]:
        """
        Process a user query and return movie recommendations.

        Args:
            query: The user's query
            conversation_history: List of previous messages in the conversation
            first_run_mode: Whether to operate in first run mode (with theaters) or casual viewing mode

        Returns:
            Dict with response text and movie recommendations
        """
        logger.info(f"Processing query in MovieCrewManager V2 wrapper (first_run_mode={first_run_mode})")

        # Delegate to the modular implementation
        return self.manager.process_query(
            query=query,
            conversation_history=conversation_history,
            first_run_mode=first_run_mode
        )
