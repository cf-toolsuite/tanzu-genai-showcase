"""
Performance settings for the movie chatbot.
This module provides configurable settings for performance optimizations.
"""

# Default timeout for theater search operations (in seconds)
# This limits how long the system will wait for theater data before returning results
THEATER_SEARCH_TIMEOUT = 30  # 30 seconds timeout

# Whether to generate fallback theater data when real theaters can't be found
# When enabled, the system will generate synthetic theater data if it can't find real data
THEATER_FALLBACK_ENABLED = True

# Maximum number of movie recommendations to process
# Higher values increase quality but reduce performance
MAX_RECOMMENDATIONS = 3

# Maximum number of theaters to return per movie
# Higher values provide more options but increase response size and processing time
MAX_THEATERS_PER_MOVIE = 3

# Maximum number of showtimes to return per theater
# Higher values provide more options but increase response size
MAX_SHOWTIMES_PER_THEATER = 5

# LLM configuration
# Lower temperature values increase determinism but may reduce creativity
LLM_TEMPERATURE = 0.2

# Maximum retries for API operations
API_MAX_RETRIES = 1  # Reduced from 2 to improve performance

# LLM request timeout in seconds
LLM_REQUEST_TIMEOUT = 20.0

# Thread pool size for parallel operations
# Too many threads can cause resource contention
MAX_WORKER_THREADS = 4

# Memory cache size (number of items)
MAX_CACHE_SIZE = 1000

# Cache expiration time in seconds (1 hour)
CACHE_EXPIRATION_SECONDS = 3600

# Enable LLM caching
ENABLE_LLM_CACHING = True

# Enable results caching
ENABLE_RESULTS_CACHING = True

# Enable theater caching
ENABLE_THEATER_CACHING = True
