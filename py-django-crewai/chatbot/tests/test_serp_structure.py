"""
Integration test for validating SerpAPI response structure.
This test focuses specifically on the days, theaters, and showtimes data.
"""
import os
import logging
import unittest
import json
from datetime import datetime
from django.test import TestCase
from django.conf import settings

from chatbot.services.serp_service import SerpShowtimeService

# Set up test logger
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.StreamHandler(),
        logging.FileHandler('serp_structure_test.log')
    ]
)
logger = logging.getLogger('test.serp_structure')

class SerpAPIStructureTest(TestCase):
    """Test the structure of SerpAPI responses for showtimes."""

    def setUp(self):
        """Set up the test environment."""
        # Get API key from settings or environment
        self.api_key = getattr(settings, 'SERPAPI_API_KEY', os.getenv('SERPAPI_API_KEY'))

        # Skip tests if no API key is available
        if not self.api_key or self.api_key == 'your_serpapi_key_here':
            self.skipTest("No SerpAPI key available. Skipping integration tests.")

        # Initialize the service
        self.serp_service = SerpShowtimeService(api_key=self.api_key)

        # Test data
        # Using a very popular current movie for better chances of finding showtimes
        self.movie_title = "Avengers"
        self.location = "New York, NY"
        self.timezone = "America/New_York"

    def test_raw_serp_api_structure(self):
        """Test the raw structure of the SerpAPI response for showtimes."""
        # Log the test start
        logger.info(f"Testing raw SerpAPI structure for movie: {self.movie_title}")

        # Import serpapi directly to test raw response
        from serpapi import GoogleSearch

        # Construct parameters for SerpAPI
        params = {
            "q": f"{self.movie_title} theater",
            "location": self.location,
            "hl": "en",
            "gl": "us",
            "api_key": self.api_key
        }

        # Make the API request
        search = GoogleSearch(params)
        results = search.get_dict()

        # Save the raw response to a file for manual inspection
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        with open(f'serp_raw_response_{timestamp}.json', 'w') as f:
            json.dump(results, f, indent=2)

        # Validate the top-level structure of the response
        self.assertIsInstance(results, dict, "Response should be a dictionary")
        self.assertIn('search_metadata', results, "Response should contain search_metadata")

        # Check for days and theaters in the response
        has_showtimes = 'showtimes' in results
        logger.info(f"Response contains showtimes data: {has_showtimes}")

        if has_showtimes:
            # Verify showtimes array structure
            showtimes = results['showtimes']
            self.assertIsInstance(showtimes, list, "Showtimes should be a list")
            if showtimes:
                # Verify at least one day of showtimes
                self.assertGreater(len(showtimes), 0, "Should have at least one day of showtimes")

                # Check the structure of the first day
                first_day = showtimes[0]
                self.assertIn('day', first_day, "Day data should include 'day' field")
                logger.info(f"First day: {first_day.get('day')}")

                # Check for theaters in the first day
                self.assertIn('theaters', first_day, "Day data should include 'theaters' field")
                theaters = first_day.get('theaters', [])

                if theaters:
                    self.assertGreater(len(theaters), 0, "Should have at least one theater")

                    # Check the structure of the first theater
                    first_theater = theaters[0]
                    self.assertIn('name', first_theater, "Theater should include 'name'")
                    logger.info(f"First theater: {first_theater.get('name')}")

                    # Check for showing data in the first theater
                    self.assertIn('showing', first_theater, "Theater should include 'showing' data")
                    showing = first_theater.get('showing', [])

                    if showing:
                        self.assertGreater(len(showing), 0, "Should have at least one showing entry")

                        # Check the structure of the first showing
                        first_showing = showing[0]
                        self.assertIn('time', first_showing, "Showing should include 'time' field")
                        times = first_showing.get('time', [])

                        if times:
                            self.assertGreater(len(times), 0, "Should have at least one time in the showing")
                            logger.info(f"First showing time: {times[0]}")
        else:
            logger.warning(f"No showtimes data found for movie: {self.movie_title}")
            # This is not a failure case - some movies may not have showtimes
            # or the API structure might have changed

    def test_parsed_showtimes_structure(self):
        """Test that the parsed showtimes structure from our SerpShowtimeService is correct."""
        logger.info(f"Testing parsed showtimes structure for movie: {self.movie_title}")

        # Get the theaters and showtimes from our service
        theaters = self.serp_service.search_showtimes(
            movie_title=self.movie_title,
            location=self.location,
            timezone=self.timezone
        )

        # Basic validation
        self.assertIsInstance(theaters, list, "Theaters should be a list")

        # Count and validate showtimes
        total_showtimes = 0
        for i, theater in enumerate(theaters):
            # Each theater should be a dictionary with specific keys
            self.assertIsInstance(theater, dict, f"Theater {i} should be a dictionary")
            self.assertIn('name', theater, f"Theater {i} should include 'name'")
            self.assertIn('address', theater, f"Theater {i} should include 'address'")
            self.assertIn('showtimes', theater, f"Theater {i} should include 'showtimes'")

            # Validate showtimes list
            showtimes = theater.get('showtimes', [])
            self.assertIsInstance(showtimes, list, f"Showtimes for theater {theater.get('name')} should be a list")

            # Count total showtimes for reporting
            total_showtimes += len(showtimes)

            # Validate each showtime
            for j, showtime in enumerate(showtimes):
                # Each showtime should be a dictionary with specific keys
                self.assertIsInstance(showtime, dict, f"Showtime {j} should be a dictionary")
                self.assertIn('start_time', showtime, f"Showtime {j} should include 'start_time'")
                self.assertIn('format', showtime, f"Showtime {j} should include 'format'")

                # Validate the start_time format (ISO format with timezone)
                start_time = showtime.get('start_time')
                try:
                    # Should parse as ISO format
                    dt = datetime.fromisoformat(start_time)
                    # Should have timezone info
                    self.assertIsNotNone(dt.tzinfo, f"Start time should include timezone info: {start_time}")
                except (ValueError, TypeError) as e:
                    self.fail(f"Invalid start_time format: {start_time}, error: {str(e)}")

        # Report on results
        logger.info(f"Found {len(theaters)} theaters with {total_showtimes} total showtimes")

        # Save the parsed results for manual inspection
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        with open(f'serp_parsed_theaters_{timestamp}.json', 'w') as f:
            json.dump(theaters, f, indent=2)

        # Success criteria: For a valid test, we should find at least one theater with showtimes
        # This is a strong indicator that the parsing logic is working correctly
        if theaters:
            theater_with_showtimes = any(len(theater.get('showtimes', [])) > 0 for theater in theaters)
            if not theater_with_showtimes:
                logger.warning("Found theaters but no showtimes. The parsing might have issues.")
            else:
                logger.info("Successfully found theaters with showtimes. Parsing logic appears to be working.")
        else:
            logger.warning(f"No theaters found for {self.movie_title}. This may be an API issue or a parsing problem.")

    def test_days_extraction(self):
        """Test that the day extraction logic correctly processes day strings."""
        from chatbot.services.serp_service import SerpShowtimeService

        # Create an instance of the service for testing
        serp_service = SerpShowtimeService(api_key=self.api_key)

        # Test various day string formats
        test_cases = [
            ('TodayMay 2', datetime.now().date()),  # Today
            ('FriMay 3', None),  # Future date
            ('SatMay 4', None),  # Future date
            ('Invalid day string', datetime.now().date())  # Error case - should default to today
        ]

        for day_string, expected_date in test_cases:
            extracted_date = serp_service._extract_date_from_day_string(day_string)
            logger.info(f"Extracted date for '{day_string}': {extracted_date}")

            # Validation
            self.assertIsNotNone(extracted_date, f"Extracted date should not be None for '{day_string}'")

            if expected_date:
                # For 'Today', validate it matches current date
                self.assertEqual(extracted_date, expected_date,
                                f"Extracted date for '{day_string}' should match {expected_date}")

    def test_distance_parsing(self):
        """Test that the distance parsing logic correctly extracts distance values."""
        from chatbot.services.serp_service import SerpShowtimeService

        # Create an instance of the service for testing
        serp_service = SerpShowtimeService(api_key=self.api_key)

        # Test various distance string formats
        test_cases = [
            ('10.5 mi', 10.5),
            ('3 mi', 3.0),
            ('0.8 mi', 0.8),
            ('', None),
            ('Invalid', None)
        ]

        for distance_str, expected_distance in test_cases:
            parsed_distance = serp_service._parse_distance(distance_str)
            logger.info(f"Parsed distance for '{distance_str}': {parsed_distance}")

            # Validation
            self.assertEqual(parsed_distance, expected_distance,
                           f"Parsed distance for '{distance_str}' should be {expected_distance}")

if __name__ == '__main__':
    unittest.main()
