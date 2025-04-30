# movie_chatbot/settings/app_config.py

from . import config_loader

# --- Movie Recommendation App Configuration ---

# Number of movie results to return from search/discover APIs
MOVIE_RESULTS_LIMIT = config_loader.get_int_config('MOVIE_RESULTS_LIMIT', 5)
# Maximum number of recommended movies to return to the user
MAX_RECOMMENDATIONS = config_loader.get_int_config('MAX_RECOMMENDATIONS', 3)
# Radius in miles to search for theaters
THEATER_SEARCH_RADIUS_MILES = config_loader.get_int_config('THEATER_SEARCH_RADIUS_MILES', 15)
# Maximum showtimes per theater to limit data size
MAX_SHOWTIMES_PER_THEATER = config_loader.get_int_config('MAX_SHOWTIMES_PER_THEATER', 10)
# Maximum theaters to return in total
MAX_THEATERS = config_loader.get_int_config('MAX_THEATERS', 5)
# Default starting year for historical movie searches ("before X" queries)
DEFAULT_SEARCH_START_YEAR = config_loader.get_int_config('DEFAULT_SEARCH_START_YEAR', 1900)


# --- API Request Configuration ---

# Maximum seconds to wait for API responses
API_REQUEST_TIMEOUT = config_loader.get_int_config('API_REQUEST_TIMEOUT_SECONDS', 180)  # Increased from 120 to 180
# Maximum number of retry attempts for failed API requests
API_MAX_RETRIES = config_loader.get_int_config('API_MAX_RETRIES', 10)  # Reduced from 15 to 10
# Exponential backoff factor between retries (in seconds)
API_RETRY_BACKOFF_FACTOR = config_loader.get_float_config('API_RETRY_BACKOFF_FACTOR', 1.3)  # Reduced from 1.5 to 1.3


# --- SerpAPI Request Configuration ---

# Base delay between theater requests for different movies (seconds)
SERPAPI_REQUEST_BASE_DELAY = config_loader.get_float_config('SERPAPI_REQUEST_BASE_DELAY', 5.0)  # Reduced from 10.0 to 5.0
# Additional delay per movie processed (seconds)
SERPAPI_PER_MOVIE_DELAY = config_loader.get_float_config('SERPAPI_PER_MOVIE_DELAY', 2.0)  # Reduced from 3.0 to 2.0
# Maximum retries for SerpAPI requests
SERPAPI_MAX_RETRIES = config_loader.get_int_config('SERPAPI_MAX_RETRIES', 2)  # Reduced from 3 to 2
# Base delay for exponential backoff during retries (seconds)
SERPAPI_BASE_RETRY_DELAY = config_loader.get_float_config('SERPAPI_BASE_RETRY_DELAY', 3.0)  # Reduced from 5.0 to 3.0
# Multiplier for exponential backoff during retries
SERPAPI_RETRY_MULTIPLIER = config_loader.get_float_config('SERPAPI_RETRY_MULTIPLIER', 1.5)  # Reduced from 2.0 to 1.5
