"""
Integration test for SerpAPI showtimes functionality.
"""
import os
import logging
import unittest
from django.test import TestCase
from django.conf import settings

from chatbot.services.serp_service import SerpShowtimeService

# Set up test logger
logger = logging.getLogger('test.serp_integration')

class SerpAPIIntegrationTest(TestCase):
    """Test the SerpAPI integration for movie showtimes."""

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
        self.movie_title = "The Avengers"  # Using a popular movie that might be playing
        self.location = "New York, NY"     # Major city for better chances of results

    def test_search_showtimes(self):
        """Test searching for showtimes."""
        logger.info(f"Testing showtime search for '{self.movie_title}' in {self.location}")

        # Search for showtimes
        theaters = self.serp_service.search_showtimes(
            movie_title=self.movie_title,
            location=self.location
        )

        # Log the response structure
        logger.info(f"Got response with {len(theaters)} theaters")
        if theaters:
            logger.info(f"First theater: {theaters[0].get('name')}")
            logger.info(f"Showtimes count: {len(theaters[0].get('showtimes', []))}")

        # Even if no theaters found, the call should complete without errors
        self.assertIsInstance(theaters, list, "Response should be a list")

    def test_error_handling(self):
        """Test error handling with invalid parameters."""
        # Test with empty location
        empty_location_theaters = self.serp_service.search_showtimes(
            movie_title=self.movie_title,
            location=""
        )
        self.assertIsInstance(empty_location_theaters, list, "Empty location should return a list")

        # Test with empty movie title
        empty_title_theaters = self.serp_service.search_showtimes(
            movie_title="",
            location=self.location
        )
        self.assertIsInstance(empty_title_theaters, list, "Empty title should return a list")

        # Test with invalid location
        invalid_location_theaters = self.serp_service.search_showtimes(
            movie_title=self.movie_title,
            location="ThisIsDefinitelyNotARealLocation12345XYZ"
        )
        self.assertIsInstance(invalid_location_theaters, list, "Invalid location should return a list")

if __name__ == '__main__':
    unittest.main()
