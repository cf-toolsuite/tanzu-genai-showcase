"""
SerpAPI Test Harness

This script provides a direct interface to SerpAPI for testing and debugging purposes.
It allows verifying responses and validating the structure expected by the application.
"""

import os
import json
import logging
import argparse
import sys
from datetime import datetime

# Setup path to include the Django app
script_dir = os.path.dirname(os.path.abspath(__file__))
project_dir = os.path.abspath(os.path.join(script_dir, '../..'))
sys.path.insert(0, project_dir)

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler(os.path.join(script_dir, 'serp_api_test.log'))
    ]
)
logger = logging.getLogger('serp_api_harness')

def setup_django_environment():
    """Set up Django environment so we can access settings and models."""
    import django
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "movie_chatbot.settings")
    django.setup()

    # Now we can import Django settings
    from django.conf import settings
    return settings

def get_api_key():
    """Get SerpAPI key from environment variables or Django settings."""
    # First try environment variable
    api_key = os.getenv('SERPAPI_API_KEY')

    # If not in env vars, try Django settings
    if not api_key:
        try:
            settings = setup_django_environment()
            api_key = getattr(settings, 'SERPAPI_API_KEY', None)
        except Exception as e:
            logger.error(f"Error accessing Django settings: {str(e)}")

    return api_key

def pretty_print_json(data, indent=2):
    """Pretty print JSON data."""
    if isinstance(data, str):
        try:
            data = json.loads(data)
        except json.JSONDecodeError:
            print(data)
            return

    print(json.dumps(data, indent=indent, sort_keys=True))

def inspect_response_structure(response, prefix=''):
    """Recursively inspect and log the structure of a response."""
    if isinstance(response, dict):
        logger.info(f"{prefix}Dict with {len(response)} keys: {', '.join(response.keys())}")
        for key, value in response.items():
            inspect_response_structure(value, prefix=f"{prefix}{key}.")
    elif isinstance(response, list):
        logger.info(f"{prefix}List with {len(response)} items")
        if response and len(response) > 0:
            # Inspect first item as a sample
            inspect_response_structure(response[0], prefix=f"{prefix}[0].")
    else:
        # For simple types, just log the type
        logger.info(f"{prefix}Value of type {type(response).__name__}")

def test_search_movies(api_key, query="", location="New York, NY"):
    """Test searching for movies in theaters."""
    logger.info(f"=== Testing search_movies_in_theaters with query='{query}', location='{location}' ===")

    # Import SerpAPI service
    from chatbot.services.serp_service import SerpShowtimeService

    # Initialize the service
    serp_service = SerpShowtimeService(api_key=api_key)

    # Search for movies
    movies = serp_service.search_movies_in_theaters(query=query, location=location)

    logger.info(f"Found {len(movies)} movies")

    # Inspect the structure of the response
    inspect_response_structure(movies)

    # Pretty print the first movie if available
    if movies:
        logger.info("First movie details:")
        pretty_print_json(movies[0])

    return movies

def test_search_showtimes(api_key, movie_title, location="New York, NY", radius_miles=25, timezone="America/New_York"):
    """Test searching for movie showtimes."""
    logger.info(f"=== Testing search_showtimes with movie='{movie_title}', location='{location}' ===")

    # Import SerpAPI service
    from chatbot.services.serp_service import SerpShowtimeService

    # Initialize the service
    serp_service = SerpShowtimeService(api_key=api_key)

    # Search for showtimes
    theaters = serp_service.search_showtimes(
        movie_title=movie_title,
        location=location,
        radius_miles=radius_miles,
        timezone=timezone
    )

    logger.info(f"Found {len(theaters)} theaters")

    # Inspect the structure of the response
    inspect_response_structure(theaters)

    # Count and log showtimes
    total_showtimes = 0
    for theater in theaters:
        theater_showtimes = len(theater.get('showtimes', []))
        total_showtimes += theater_showtimes
        logger.info(f"Theater '{theater.get('name', 'Unknown')}' has {theater_showtimes} showtimes")

    logger.info(f"Total showtimes across all theaters: {total_showtimes}")

    # Pretty print the first theater if available
    if theaters:
        logger.info("First theater details:")
        pretty_print_json(theaters[0])

        # Log the first few showtimes for debugging
        showtimes = theaters[0].get('showtimes', [])
        if showtimes:
            logger.info("Sample showtimes:")
            for i, showtime in enumerate(showtimes[:5]):  # Log up to 5 showtimes
                logger.info(f"  Showtime {i+1}: {showtime.get('start_time')}, Format: {showtime.get('format')}")

    # Save the full response to a JSON file for deeper analysis
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    output_file = os.path.join(script_dir, f'serp_showtimes_response_{timestamp}.json')
    with open(output_file, 'w') as f:
        json.dump(theaters, f, indent=2)
    logger.info(f"Full response saved to {output_file}")

    return theaters

def test_parse_serp_results(api_key, movie_title, location="New York, NY"):
    """Test the internal _parse_serp_results method directly."""
    logger.info(f"=== Testing _parse_serp_results with movie='{movie_title}', location='{location}' ===")

    # Import necessary modules
    from chatbot.services.serp_service import SerpShowtimeService
    from serpapi import GoogleSearch

    # Initialize the service
    serp_service = SerpShowtimeService(api_key=api_key)

    # Construct parameters for SerpAPI
    params = {
        "q": f"{movie_title} theater",
        "location": location,
        "hl": "en",
        "gl": "us",
        "api_key": api_key
    }

    # Make the API request
    logger.info("Making direct SerpAPI request...")
    search = GoogleSearch(params)
    results = search.get_dict()

    # Log the top-level keys in the raw response
    logger.info(f"Raw SerpAPI response contains keys: {', '.join(results.keys())}")

    # Check for showtimes data
    has_showtimes = 'showtimes' in results
    logger.info(f"Raw response contains showtimes data: {has_showtimes}")

    if has_showtimes:
        # Log the structure of the showtimes data
        logger.info(f"Showtimes array length: {len(results['showtimes'])}")
        for day_idx, day_data in enumerate(results['showtimes']):
            logger.info(f"Day {day_idx+1}: {day_data.get('day', 'Unknown')}")
            theaters = day_data.get('theaters', [])
            logger.info(f"  Contains {len(theaters)} theaters")

            # Log sample theater data
            if theaters:
                sample_theater = theaters[0]
                logger.info(f"  Sample theater: {sample_theater.get('name', 'Unknown')}")
                showing_data = sample_theater.get('showing', [])
                logger.info(f"    Contains {len(showing_data)} showing entries")

                # Log sample showing data
                if showing_data:
                    sample_showing = showing_data[0]
                    logger.info(f"    Sample showing - Times: {sample_showing.get('time', [])}")

    # Now parse the results
    logger.info("Parsing SerpAPI results using application logic...")
    parsed_theaters = serp_service._parse_serp_results(results, movie_title)

    logger.info(f"Parsing returned {len(parsed_theaters)} theaters")

    # Save both raw and parsed results for comparison
    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")

    # Save raw results
    raw_output_file = os.path.join(script_dir, f'serp_raw_response_{timestamp}.json')
    with open(raw_output_file, 'w') as f:
        json.dump(results, f, indent=2)
    logger.info(f"Raw response saved to {raw_output_file}")

    # Save parsed results
    parsed_output_file = os.path.join(script_dir, f'serp_parsed_response_{timestamp}.json')
    with open(parsed_output_file, 'w') as f:
        json.dump(parsed_theaters, f, indent=2)
    logger.info(f"Parsed response saved to {parsed_output_file}")

    return results, parsed_theaters

def main():
    """Main function to handle command-line arguments and run tests."""
    parser = argparse.ArgumentParser(description='SerpAPI Test Harness')
    parser.add_argument('--api-key', help='SerpAPI API Key (optional, will use env var or settings if not provided)')
    parser.add_argument('--test', choices=['movies', 'showtimes', 'parse'], default='showtimes',
                        help='Test to run (movies, showtimes, or parse)')
    parser.add_argument('--movie', default='Avengers', help='Movie title to search for')
    parser.add_argument('--query', default='action movies', help='Query for movie search')
    parser.add_argument('--location', default='New York, NY', help='Location for search')
    parser.add_argument('--radius', type=int, default=25, help='Search radius in miles')
    parser.add_argument('--timezone', default='America/New_York', help='Timezone for showtimes')

    args = parser.parse_args()

    # Get API key
    api_key = args.api_key or get_api_key()

    if not api_key:
        logger.error("No SerpAPI API key found. Please provide one with --api-key or set the SERPAPI_API_KEY environment variable.")
        return 1

    try:
        if args.test == 'movies':
            test_search_movies(api_key, args.query, args.location)
        elif args.test == 'showtimes':
            test_search_showtimes(api_key, args.movie, args.location, args.radius, args.timezone)
        elif args.test == 'parse':
            test_parse_serp_results(api_key, args.movie, args.location)

        logger.info("Test completed successfully.")
        return 0

    except Exception as e:
        logger.error(f"Test failed with error: {str(e)}", exc_info=True)
        return 1

if __name__ == '__main__':
    sys.exit(main())
