# movie_chatbot/settings/external_apis.py

from . import config_loader

# --- External API Keys ---

# The Movie Database API Key (for movie data)
TMDB_API_KEY = config_loader.get_required_config('TMDB_API_KEY')

# SerpAPI Configuration for movie showtimes
SERPAPI_API_KEY = config_loader.get_required_config('SERPAPI_API_KEY')
