"""
Custom Agno toolkit for the Airbnb Assistant
"""
import json
import logging
from typing import Dict, List, Any, Optional

try:
    from agno.tools import Toolkit
    AGNO_IMPORTED = True
except (ImportError, AttributeError) as e:
    logging.warning(f"Could not import Agno tools: {e}. Using mock implementation.")
    AGNO_IMPORTED = False
    # Create a simple base class for mock implementation
    class Toolkit:
        def __init__(self, name="mock_toolkit"):
            self.name = name

        def register(self, func):
            # Mock register method
            return func

from .mcp.client import MCPAirbnbClient
from .mcp.clients import MCPAirbnbClientBase

log = logging.getLogger(__name__)

class AirbnbTools(Toolkit):
    """
    Agno toolkit for Airbnb search operations
    """

    def __init__(self, mcp_client=None):
        """
        Initialize the Airbnb toolkit

        Args:
            mcp_client: MCPAirbnbClient or MCPAirbnbClientBase instance (optional)
        """
        super().__init__(name="airbnb_tools")

        # Initialize MCP client
        self.mcp_client = mcp_client or MCPAirbnbClient()

        # Register toolkit functions
        self.register(self.search_listings)
        self.register(self.get_listing_details)

    def search_listings(self,
                      location: str,
                      check_in: Optional[str] = None,
                      check_out: Optional[str] = None,
                      guests: int = 1,
                      limit: int = 5) -> str:
        """
        Search for Airbnb listings based on location and dates

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            JSON string containing search results
        """
        try:
            log.info(f"Searching for listings in {location}")

            # Validate inputs
            if guests < 1:
                return json.dumps({"error": "Number of guests must be at least 1"})

            # Call MCP client to search listings
            log.info(f"Using MCP client to search for listings in {location}")
            listings = self.mcp_client.search_listings(
                location=location,
                check_in=check_in,
                check_out=check_out,
                guests=guests,
                limit=limit
            )
            log.info(f"Received {len(listings)} listings from MCP client")

            if not listings:
                return json.dumps({"results": [], "message": f"No listings found in {location} for your search criteria."})

            return json.dumps({"results": listings})

        except Exception as e:
            log.error(f"Error in search_listings: {e}")
            return json.dumps({"error": str(e)})

    def get_listing_details(self, listing_id: str) -> str:
        """
        Get detailed information about a specific listing

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            JSON string containing listing details
        """
        try:
            log.info(f"Getting details for listing {listing_id}")

            # Call MCP client to get listing details
            details = self.mcp_client.get_listing_details(listing_id)
            log.info(f"Received listing details with {len(details) if details else 0} fields")

            if not details:
                return json.dumps({"error": f"Listing not found: {listing_id}"})

            return json.dumps({"listing": details})

        except Exception as e:
            log.error(f"Error in get_listing_details: {e}")
            return json.dumps({"error": str(e)})
