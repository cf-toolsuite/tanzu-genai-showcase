# movie_chatbot/settings/external_apis.py

import os

# --- External API Keys ---

# The Movie Database API Key (for movie data)
TMDB_API_KEY = os.getenv('TMDB_API_KEY')

# SerpAPI Configuration for movie showtimes
SERPAPI_API_KEY = os.getenv('SERPAPI_API_KEY')
