"""
Mock client implementation for MCP Airbnb
"""
import logging
import datetime
from typing import Dict, List, Any, Optional

from airbnb_assistant.dto.airbnb import AirbnbListing

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
        mock_listings = self._get_mock_listings(location=location, limit=limit)

        # Process the listings through our DTO model to ensure consistency
        processed_listings = []
        valid_listings = []

        for listing_data in mock_listings:
            try:
                # Create a proper DTO
                listing = AirbnbListing.from_dict(listing_data)

                # Make sure URL is valid
                if not listing.url or "undefined" in listing.url:
                    listing_data["url"] = listing.create_valid_url()

                # Only add listings with valid URLs
                if listing.create_valid_url() != "https://www.airbnb.com":
                    # Enhance the data with cleaned-up information
                    enhanced_data = listing_data.copy()
                    # Ensure the URL is valid
                    enhanced_data["url"] = listing.create_valid_url()
                    valid_listings.append(enhanced_data)

            except Exception as e:
                log.error(f"Error formatting listing: {e}")

        # Return only valid listings (up to the limit)
        results = valid_listings[:limit] if valid_listings else []

        # Return mock results
        return results

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
        listing_details = self._get_mock_listing_details(listing_id)

        # Make sure we have all necessary data for consistent presentation
        if listing_details:
            # Enhance the details with a valid URL
            listing_details["url"] = f"https://www.airbnb.com/rooms/{listing_id}"

        return listing_details

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

        # Create structured mock listings that match the format from real API
        mock_listings = [
            {
                "url": f"https://www.airbnb.com/rooms/1001",
                "listing": {
                    "id": "1001",
                    "structuredContent": {
                        "primaryLine": "1 Queen bed",
                        "secondaryLine": f"Downtown {location}",
                        "mapCategoryInfo": "Cozy apartment"
                    }
                },
                "avgRatingA11yLabel": "4.8 out of 5 average rating, 120 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$120 for 3 nights"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$40 x 3 nights: $120"
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1002",
                "listing": {
                    "id": "1002",
                    "structuredContent": {
                        "primaryLine": "2 beds",
                        "secondaryLine": f"Beachfront, {location}",
                        "mapCategoryInfo": "Luxury condo"
                    }
                },
                "avgRatingA11yLabel": "4.9 out of 5 average rating, 85 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$250 for 3 nights"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$83 x 3 nights: $250"
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1003",
                "listing": {
                    "id": "1003",
                    "structuredContent": {
                        "primaryLine": "1 bed",
                        "secondaryLine": f"Countryside, {location}",
                        "mapCategoryInfo": "Charming cottage"
                    }
                },
                "avgRatingA11yLabel": "4.7 out of 5 average rating, 65 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$150 for 3 nights"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$50 x 3 nights: $150"
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1004",
                "listing": {
                    "id": "1004",
                    "structuredContent": {
                        "primaryLine": "1 king bed",
                        "secondaryLine": f"City center, {location}",
                        "mapCategoryInfo": "Modern apartment with city views"
                    }
                },
                "avgRatingA11yLabel": "4.95 out of 5 average rating, 391 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$544 for 3 nights"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$181 x 3 nights: $544"
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1005",
                "listing": {
                    "id": "1005",
                    "structuredContent": {
                        "primaryLine": "1 king bed",
                        "secondaryLine": f"Waterfront, {location}",
                        "mapCategoryInfo": "Luxury waterfront suite"
                    }
                },
                "avgRatingA11yLabel": "4.94 out of 5 average rating, 585 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$688 for 3 nights"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$229 x 3 nights: $688"
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1006",
                "listing": {
                    "id": "1006",
                    "structuredContent": {
                        "primaryLine": "1 double bed",
                        "secondaryLine": f"Historic district, {location}",
                        "mapCategoryInfo": "Charming room in historic building"
                    }
                },
                "avgRatingA11yLabel": "4.75 out of 5 average rating, 449 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$338 for 3 nights"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$113 x 3 nights: $338"
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1007",
                "listing": {
                    "id": "1007",
                    "structuredContent": {
                        "primaryLine": "2 beds",
                        "secondaryLine": f"Trendy neighborhood, {location}",
                        "mapCategoryInfo": "Spacious loft in trendy area"
                    }
                },
                "avgRatingA11yLabel": "4.81 out of 5 average rating, 124 reviews",
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$418 for 3 nights, originally $490"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$139 x 3 nights: $418, "
                    }
                }
            },
            {
                "url": f"https://www.airbnb.com/rooms/1008",
                "listing": {
                    "id": "1008",
                    "structuredContent": {
                        "mapCategoryInfo": "Stay with Guo Jun"
                    }
                },
                "avgRatingA11yLabel": "4.78 out of 5 average rating, 88 reviews",
                "listingParamOverrides": {
                    "categoryTag": "Tag:8678",
                    "photoId": "1671312700",
                    "amenities": ""
                },
                "structuredDisplayPrice": {
                    "primaryLine": {"accessibilityLabel": "$250 for 3 nights, originally $312"},
                    "explanationData": {
                        "title": "Price details",
                        "priceDetails": "$83 x 3 nights: $250, "
                    }
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
        location_map = {
            "1001": "New York, NY",
            "1002": "Miami, FL",
            "1003": "Vermont",
            "1004": "San Francisco, CA",
            "1005": "San Francisco, CA",
            "1006": "San Francisco, CA",
            "1007": "San Francisco, CA",
            "1008": "San Francisco, CA"
        }

        # Generate base details
        location = location_map.get(listing_id, "San Francisco, CA")

        # Create basic details
        basic_details = {
            "id": listing_id,
            "title": f"Comfortable stay in {location}",
            "location": location,
            "url": f"https://www.airbnb.com/rooms/{listing_id}",
        }

        # Add specific details based on listing_id
        if listing_id == "1001":
            basic_details.update({
                "location_details": "Downtown area, near subway",
                "price_per_night": 120,
                "rating": 4.8,
                "reviews_count": 120,
                "superhost": True,
                "amenities": [
                    "WiFi", "Kitchen", "Air conditioning", "Washer/Dryer",
                    "TV", "Hair dryer", "Iron", "Essentials"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "description": "A beautiful cozy apartment located in the heart of downtown. Perfect for couples or solo travelers looking to explore the city.",
            })
        elif listing_id == "1002":
            basic_details.update({
                "location_details": "Beachfront property with direct beach access",
                "price_per_night": 250,
                "rating": 4.9,
                "reviews_count": 85,
                "superhost": True,
                "amenities": [
                    "WiFi", "Pool", "Kitchen", "Gym", "Air conditioning",
                    "Washer/Dryer", "TV", "Hair dryer", "Iron", "Essentials",
                    "Parking", "Hot tub", "Beach access"
                ],
                "bedrooms": 2,
                "bathrooms": 2,
                "max_guests": 4,
                "description": "A luxurious condo with breathtaking ocean views. Enjoy the sunset from your private balcony or take a dip in the infinity pool.",
            })
        elif listing_id == "1003":
            basic_details.update({
                "location_details": "Countryside, 10 minute drive to town",
                "price_per_night": 150,
                "rating": 4.7,
                "reviews_count": 65,
                "superhost": False,
                "amenities": [
                    "WiFi", "Kitchen", "Backyard", "Fireplace",
                    "TV", "BBQ grill", "Parking", "Essentials"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 3,
                "description": "A charming cottage nestled in the beautiful countryside. Perfect for a peaceful getaway from city life.",
            })
        elif listing_id == "1004":
            basic_details.update({
                "location_details": "City center with panoramic views",
                "price_per_night": 181,
                "rating": 4.95,
                "reviews_count": 391,
                "superhost": True,
                "amenities": [
                    "WiFi", "Kitchen", "Air conditioning", "Washer/Dryer",
                    "TV", "Hair dryer", "Iron", "Essentials", "Elevator",
                    "Gym access", "24-hour concierge"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "description": "Modern apartment with stunning city views. Located in the heart of San Francisco, walking distance to top attractions and dining.",
            })
        elif listing_id == "1005":
            basic_details.update({
                "location_details": "Waterfront property with bay views",
                "price_per_night": 229,
                "rating": 4.94,
                "reviews_count": 585,
                "superhost": True,
                "amenities": [
                    "WiFi", "Kitchen", "Air conditioning", "Washer/Dryer",
                    "TV", "Hair dryer", "Iron", "Essentials", "Terrace",
                    "Parking", "Waterfront access"
                ],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "description": "Luxury suite with spectacular bay views. Enjoy watching the sunset over the water from your private terrace.",
            })
        else:
            # Default details for unknown listings
            basic_details.update({
                "location_details": f"Near downtown {location}",
                "price_per_night": 120,
                "rating": 4.5,
                "reviews_count": 50,
                "superhost": False,
                "amenities": ["WiFi", "Kitchen", "TV", "Essentials"],
                "bedrooms": 1,
                "bathrooms": 1,
                "max_guests": 2,
                "description": f"Comfortable accommodation in {location}. Great for exploring the area.",
            })

        log.info(f"Generated mock details for listing ID: {listing_id}")
        return basic_details
