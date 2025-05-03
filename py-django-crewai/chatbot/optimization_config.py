"""
Optimized configuration for the movie chatbot views.
This module provides optimized versions of the view functions.
"""
# Import all necessary view functions from the views module
from .views import (
    index,
    get_movies_theaters_and_showtimes,
    get_movie_recommendations,
    poll_movie_recommendations,
    poll_first_run_recommendations,
    get_theaters,
    theater_status,
    reset_conversation,
    get_api_config
)
