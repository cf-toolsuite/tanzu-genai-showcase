"""
Views package for the chatbot application.
This package contains all the Django views organized by functionality.
"""

# Import all views for Django to discover them
from .movie_views import (
    get_movie_recommendations,
    poll_movie_recommendations,
    poll_first_run_recommendations
)

from .theater_views import (
    get_movies_theaters_and_showtimes,
    get_theaters,
    theater_status
)

from .api_views import (
    get_api_config
)

from .common_views import (
    index,
    reset_conversation,
    get_client_ip
)

# Expose all views at the package level
__all__ = [
    # Movie views
    'get_movie_recommendations',
    'poll_movie_recommendations',
    'poll_first_run_recommendations',

    # Theater views
    'get_movies_theaters_and_showtimes',
    'get_theaters',
    'theater_status',

    # API views
    'get_api_config',

    # Common views
    'index',
    'reset_conversation',
    'get_client_ip'
]
