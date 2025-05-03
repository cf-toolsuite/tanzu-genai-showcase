#!/usr/bin/env python
"""
Debug script for SerpAPI integration.

This script makes a direct call to SerpAPI and logs all details to help debug
issues with the days, theaters, and showtimes structure.
"""

import os
import sys
import json
import logging
import argparse
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
        logging.FileHandler(os.path.join(script_dir, 'debug_serp.log'))
    ]
)
logger = logging.getLogger('debug_serp')

def setup_django():
    """Set up Django environment."""
    import django
    os.environ.setdefault("DJANGO_SETTINGS_MODULE", "movie_chatbot.settings")
    django.setup()

    # Return Django settings
    from django.conf import settings
    return settings

def get_api_key():
    """Get the SerpAPI key from environment variables or Django settings."""
    # First check environment variables
    api_key = os.getenv('SERPAPI_API_KEY')

    # If not found, try Django settings
    if not api_key:
        try:
            settings = setup_django()
            api_key = getattr(settings, 'SERPAPI_API_KEY', None)
        except Exception as e:
            logger.error(f"Error accessing Django settings: {str(e)}")

    return api_key

def debug_serp_api(api_key, movie_title="Avengers", location="New York, NY"):
    """
    Make a direct call to SerpAPI and log all details.

    This function bypasses our application's parsing logic to directly
    examine the raw response from SerpAPI.
    """
    logger.info(f"=== Debugging SerpAPI for movie: {movie_title}, location: {location} ===")

    # Import SerpAPI directly
    try:
        from serpapi import GoogleSearch
        logger.info("Successfully imported serpapi")
    except ImportError as e:
        logger.error(f"Failed to import serpapi: {str(e)}")
        logger.error("Please install the SerpAPI library: pip install google-search-results")
        return None

    # Construct parameters for SerpAPI request
    params = {
        "engine": "google_movies",
        "q": f"{movie_title} theater",
        "location": location,
        "hl": "en",
        "gl": "us",
        "api_key": api_key
    }

    logger.info(f"Sending request to SerpAPI with parameters: {params}")

    try:
        # Make the API request
        search = GoogleSearch(params)
        results = search.get_dict()

        # Check if we got an error
        if 'error' in results:
            logger.error(f"SerpAPI returned an error: {results['error']}")
            return None

        # Log the top-level structure
        logger.info(f"Response has {len(results)} top-level keys")
        logger.info(f"Top-level keys: {', '.join(results.keys())}")

        # Check for the showtimes data
        if 'showtimes' in results:
            showtimes = results['showtimes']
            logger.info(f"Found 'showtimes' array with {len(showtimes)} days")

            # Log each day's structure
            for i, day in enumerate(showtimes):
                day_name = day.get('day', 'Unknown day')
                logger.info(f"Day {i+1}: {day_name}")

                if 'theaters' in day:
                    theaters = day['theaters']
                    logger.info(f"  Found {len(theaters)} theaters for {day_name}")

                    # Log a sample theater
                    if theaters:
                        sample_theater = theaters[0]
                        theater_name = sample_theater.get('name', 'Unknown theater')
                        logger.info(f"  Sample theater: {theater_name}")

                        # Check for showing array
                        if 'showing' in sample_theater:
                            showing = sample_theater['showing']
                            logger.info(f"    Found 'showing' array with {len(showing)} entries")

                            # Log a sample showing
                            if showing:
                                sample_showing = showing[0]
                                logger.info(f"    Sample showing: {sample_showing}")

                                # Check for time array
                                if 'time' in sample_showing:
                                    times = sample_showing['time']
                                    logger.info(f"      Found {len(times)} times in showing")
                                    if times:
                                        logger.info(f"      Sample times: {', '.join(times[:3])}")
                                else:
                                    logger.warning("      'time' key not found in showing!")
                        else:
                            logger.warning("    'showing' key not found in theater!")
                else:
                    logger.warning(f"  No 'theaters' key found for day {day_name}!")
        else:
            logger.warning("No 'showtimes' array found in the response!")

            # Check if 'movies_results' exists instead (different API structure)
            if 'movies_results' in results:
                movies = results['movies_results']
                logger.info(f"Found 'movies_results' array with {len(movies)} movies")
                logger.info("This is not the expected structure for showtimes data.")

        # Save the raw response for analysis
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        output_file = os.path.join(script_dir, f'debug_serp_raw_{timestamp}.json')
        with open(output_file, 'w') as f:
            json.dump(results, f, indent=2)
        logger.info(f"Saved raw response to {output_file}")

        return results

    except Exception as e:
        logger.error(f"Error making SerpAPI request: {str(e)}", exc_info=True)
        return None

def debug_our_parsing(api_key, movie_title="Avengers", location="New York, NY"):
    """
    Debug our application's parsing logic for SerpAPI responses.

    This function tests our SerpShowtimeService and logs how it processes
    the SerpAPI response.
    """
    logger.info(f"=== Debugging our parsing logic for movie: {movie_title}, location: {location} ===")

    try:
        # Import our service
        from chatbot.services.serp_service import SerpShowtimeService

        # Initialize the service
        serp_service = SerpShowtimeService(api_key=api_key)

        # First, get the raw response from SerpAPI
        from serpapi import GoogleSearch

        # Construct parameters for SerpAPI
        params = {
            "q": f"{movie_title} theater",
            "location": location,
            "hl": "en",
            "gl": "us",
            "api_key": api_key
        }

        # Make the API request
        search = GoogleSearch(params)
        raw_results = search.get_dict()

        # Save the raw response
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        raw_output_file = os.path.join(script_dir, f'debug_raw_{timestamp}.json')
        with open(raw_output_file, 'w') as f:
            json.dump(raw_results, f, indent=2)
        logger.info(f"Saved raw response to {raw_output_file}")

        # Now process the raw results with our parsing logic
        logger.info("Processing raw results with our parsing logic...")
        parsed_theaters = serp_service._parse_serp_results(raw_results, movie_title)

        # Log the parsed result
        logger.info(f"Parsing returned {len(parsed_theaters)} theaters")

        # Check if we have any theaters
        if parsed_theaters:
            # Log the first theater and its showtimes
            first_theater = parsed_theaters[0]
            logger.info(f"First theater: {first_theater.get('name')}")

            showtimes = first_theater.get('showtimes', [])
            logger.info(f"Found {len(showtimes)} showtimes for this theater")

            # Log a few sample showtimes
            for i, showtime in enumerate(showtimes[:3]):
                logger.info(f"  Showtime {i+1}: {showtime.get('start_time')}, Format: {showtime.get('format')}")
        else:
            logger.warning("No theaters returned from parsing!")

            # Check the raw results to see if there are showtimes
            if 'showtimes' in raw_results:
                logger.error(
                    "Raw results contain showtimes data, but our parsing logic did not extract any theaters. "
                    "This suggests a problem with the parsing logic!"
                )
            else:
                logger.info(
                    "Raw results do not contain a 'showtimes' key. "
                    "This suggests the API response structure may have changed or our query is invalid."
                )

        # Save the parsed results
        parsed_output_file = os.path.join(script_dir, f'debug_parsed_{timestamp}.json')
        with open(parsed_output_file, 'w') as f:
            json.dump(parsed_theaters, f, indent=2)
        logger.info(f"Saved parsed results to {parsed_output_file}")

        return raw_results, parsed_theaters

    except Exception as e:
        logger.error(f"Error in parsing debug: {str(e)}", exc_info=True)
        return None, None

def main():
    """Main function to parse arguments and run the debug script."""
    parser = argparse.ArgumentParser(description='Debug SerpAPI integration')
    parser.add_argument('--api-key', help='SerpAPI API Key (optional, will use env var or settings if not provided)')
    parser.add_argument('--movie', default='Avengers', help='Movie title to search for')
    parser.add_argument('--location', default='New York, NY', help='Location for search')
    parser.add_argument('--mode', choices=['api', 'parse', 'both'], default='both',
                       help='Debug mode: api (raw API call only), parse (our parsing logic), both (default)')

    args = parser.parse_args()

    # Get API key
    api_key = args.api_key or get_api_key()

    if not api_key:
        logger.error("No SerpAPI API key found. Please provide one with --api-key or set the SERPAPI_API_KEY environment variable.")
        return 1

    logger.info(f"Starting SerpAPI debug script with movie='{args.movie}', location='{args.location}'")

    try:
        if args.mode in ('api', 'both'):
            # Debug the raw API response
            debug_serp_api(api_key, args.movie, args.location)

        if args.mode in ('parse', 'both'):
            # Debug our parsing logic
            debug_our_parsing(api_key, args.movie, args.location)

        logger.info("Debug completed successfully.")
        return 0

    except Exception as e:
        logger.error(f"Debug failed with error: {str(e)}", exc_info=True)
        return 1

if __name__ == '__main__':
    sys.exit(main())
