"""
Mock client implementation for MCP Airbnb
"""
import logging
import datetime
from typing import Dict, List, Any, Optional

from .base import MCPAirbnbClientBase

log = logging.getLogger(__name__)

class MCPAirbnbMockClient(MCPAirbnbClientBase):
    """
    Mock implementation of the MCP Airbnb client

    This implementation provides mock data for development and testing
    without requiring a running MCP server.
    """

    def __init__(self):
        """Initialize the mock client"""
        log.info("Initializing mock MCP client")

    def search_listings(self,
                       location: str,
                       check_in: Optional[str] = None,
                       check_out: Optional[str] = None,
                       guests: int = 1,
                       limit: int = 10) -> List[Dict[str, Any]]:
        """
        Provide mock listings data for the given search parameters

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            List of mock listing dictionaries
        """
        log.info(f"Mock client: Searching for listings in {location} for {guests} guests")
        if check_in and check_out:
            log.info(f"Mock client: Date range: {check_in} to {check_out}")

        # Generate mock listings data
        return self._get_mock_listings(location=location, limit=limit)

    def get_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Provide mock details for a specific listing ID

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing mock listing details
        """
        log.info(f"Mock client: Getting details for listing {listing_id}")

        # Return mock listing details for the given ID
        return self._get_mock_listing_details(listing_id)

    def _get_mock_listings(self, location: str, limit: int = 10) -> List[Dict[str, Any]]:
        """
        Generate mock listings for the given location

        Args:
            location: Location to search for
            limit: Maximum number of results to return

        Returns:
            List of mock listing dictionaries
        """
        # Generate today and tomorrow's dates for mock data
        from datetime import datetime, timedelta
        today = datetime.now().strftime("%Y-%m-%d")
        tomorrow = (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%d")

        mock_listings = [
            {
                "id": "1001",
                "title": f"Cozy apartment in {location}",
                "location": location,
                "location_details": "Downtown area",
                "price_per_night": 120,
                "rating": 4.8,
                "reviews_count": 120,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-717134404264905813/original/dfe9c1ff-b70c-4566-a1ef-5bc733dbb705.jpeg",
                "amenities": ["WiFi", "Kitchen", "Air conditioning"],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "url": f"https://www.airbnb.com/rooms/1001",
                "listing": {
                    "id": "1001",
                    "name": f"Cozy apartment in {location}",
                    "coordinate": {"latitude": 37.7749, "longitude": -122.4194}
                },
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$120 per night"}
                }
            },
            {
                "id": "1002",
                "title": f"Luxury condo in {location}",
                "location": location,
                "location_details": "Beachfront",
                "price_per_night": 250,
                "rating": 4.9,
                "reviews_count": 85,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-51809333/original/0da70267-d9da-4efb-9123-2714b651c9cd.jpeg",
                "amenities": ["WiFi", "Pool", "Kitchen", "Gym"],
                "bedrooms": 2,
                "bathrooms": 2,
                "max_guests": 4,
                "url": f"https://www.airbnb.com/rooms/1002",
                "listing": {
                    "id": "1002",
                    "name": f"Luxury condo in {location}",
                    "coordinate": {"latitude": 37.7833, "longitude": -122.4167}
                },
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$250 per night"}
                }
            },
            {
                "id": "1003",
                "title": f"Charming cottage in {location}",
                "location": location,
                "location_details": "Countryside",
                "price_per_night": 150,
                "rating": 4.7,
                "reviews_count": 65,
                "superhost": False,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-807995199727408777/original/9225d584-7aa4-4990-af06-339bd1339686.jpeg",
                "amenities": ["WiFi", "Kitchen", "Backyard"],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 3,
                "url": f"https://www.airbnb.com/rooms/1003",
                "listing": {
                    "id": "1003",
                    "name": f"Charming cottage in {location}",
                    "coordinate": {"latitude": 37.7694, "longitude": -122.4862}
                },
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$150 per night"}
                }
            }
        ][:limit]

        log.info(f"Generated {len(mock_listings)} mock listings for location: {location}")
        return mock_listings

    def _get_mock_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Generate mock details for a specific listing ID

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing mock listing details
        """
        listing_data = {
            "1001": {
                "id": "1001",
                "title": "Cozy apartment in Downtown",
                "location": "New York, NY",
                "location_details": "Downtown area, near subway",
                "price_per_night": 120,
                "rating": 4.8,
                "reviews_count": 120,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-717134404264905813/original/dfe9c1ff-b70c-4566-a1ef-5bc733dbb705.jpeg",
                "amenities": [
                    "WiFi", "Kitchen", "Air conditioning", "Washer/Dryer",
                    "TV", "Hair dryer", "Iron", "Essentials"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "description": "A beautiful cozy apartment located in the heart of downtown. Perfect for couples or solo travelers looking to explore the city.",
                "host": {
                    "id": "host1",
                    "name": "John",
                    "image_url": "https://a0.muscache.com/im/pictures/user/User-380443802/original/9c8d56be-b77a-4f35-be1b-93ef032192c2.jpeg",
                    "superhost": True,
                    "response_rate": 98,
                    "joined_date": "2018-01-01"
                },
                "availability": {
                    "min_nights": 2,
                    "max_nights": 30,
                    "availability_30": 15,
                    "availability_60": 30,
                    "availability_90": 45
                },
                "reviews": [
                    {
                        "id": "rev1",
                        "author": "Alice",
                        "date": "2023-02-15",
                        "rating": 5,
                        "comment": "Great place, would stay again!"
                    },
                    {
                        "id": "rev2",
                        "author": "Bob",
                        "date": "2023-01-20",
                        "rating": 4,
                        "comment": "Nice apartment, good location."
                    }
                ]
            },
            "1002": {
                "id": "1002",
                "title": "Luxury condo with ocean view",
                "location": "Miami, FL",
                "location_details": "Beachfront property with direct beach access",
                "price_per_night": 250,
                "rating": 4.9,
                "reviews_count": 85,
                "superhost": True,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-51809333/original/0da70267-d9da-4efb-9123-2714b651c9cd.jpeg",
                "amenities": [
                    "WiFi", "Pool", "Kitchen", "Gym", "Air conditioning",
                    "Washer/Dryer", "TV", "Hair dryer", "Iron", "Essentials",
                    "Parking", "Hot tub", "Beach access"
                ],
                "bedrooms": 2,
                "bathrooms": 2,
                "max_guests": 4,
                "description": "A luxurious condo with breathtaking ocean views. Enjoy the sunset from your private balcony or take a dip in the infinity pool.",
                "host": {
                    "id": "host2",
                    "name": "Sarah",
                    "image_url": "https://a0.muscache.com/im/pictures/user/User-35458447/original/e9c212d2-aa25-4f5c-bbc1-65b521a92fa4.jpeg",
                    "superhost": True,
                    "response_rate": 100,
                    "joined_date": "2016-05-15"
                },
                "availability": {
                    "min_nights": 3,
                    "max_nights": 60,
                    "availability_30": 10,
                    "availability_60": 25,
                    "availability_90": 40
                },
                "reviews": [
                    {
                        "id": "rev3",
                        "author": "Charlie",
                        "date": "2023-03-10",
                        "rating": 5,
                        "comment": "Absolutely amazing! The view is stunning."
                    },
                    {
                        "id": "rev4",
                        "author": "Diana",
                        "date": "2023-02-28",
                        "rating": 5,
                        "comment": "Perfect location, beautiful condo, great host!"
                    }
                ]
            },
            "1003": {
                "id": "1003",
                "title": "Charming cottage in countryside",
                "location": "Vermont",
                "location_details": "Countryside, 10 minute drive to town",
                "price_per_night": 150,
                "rating": 4.7,
                "reviews_count": 65,
                "superhost": False,
                "image_url": "https://a0.muscache.com/im/pictures/miso/Hosting-807995199727408777/original/9225d584-7aa4-4990-af06-339bd1339686.jpeg",
                "amenities": [
                    "WiFi", "Kitchen", "Backyard", "Fireplace",
                    "TV", "BBQ grill", "Parking", "Essentials"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 3,
                "description": "A charming cottage nestled in the beautiful countryside. Perfect for a peaceful getaway from city life.",
                "host": {
                    "id": "host3",
                    "name": "Michael",
                    "image_url": "https://a0.muscache.com/im/pictures/user/de3724d8-155c-4ce1-b480-7a8cd1c42211.jpg",
                    "superhost": False,
                    "response_rate": 95,
                    "joined_date": "2019-07-20"
                },
                "availability": {
                    "min_nights": 2,
                    "max_nights": 14,
                    "availability_30": 20,
                    "availability_60": 40,
                    "availability_90": 60
                },
                "reviews": [
                    {
                        "id": "rev5",
                        "author": "Emma",
                        "date": "2023-01-05",
                        "rating": 5,
                        "comment": "Such a peaceful place! Loved every minute."
                    },
                    {
                        "id": "rev6",
                        "author": "Frank",
                        "date": "2022-12-18",
                        "rating": 4,
                        "comment": "Great cottage, a bit hard to find at night."
                    }
                ]
            }
        }

        result = listing_data.get(listing_id, {})
        log.info(f"Generated mock details for listing ID: {listing_id}")
        return result
