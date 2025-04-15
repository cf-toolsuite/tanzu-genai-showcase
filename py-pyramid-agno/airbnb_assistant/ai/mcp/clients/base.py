"""
Base class for all MCP client implementations
"""
import abc
import logging
from typing import Dict, List, Any, Optional, Union

log = logging.getLogger(__name__)

class MCPAirbnbClientBase(abc.ABC):
    """
    Abstract base class defining the interface for MCP Airbnb clients

    All client implementations should inherit from this class
    and implement the abstract methods defined here.
    """

    @abc.abstractmethod
    def search_listings(self,
                       location: str,
                       check_in: Optional[str] = None,
                       check_out: Optional[str] = None,
                       guests: int = 1,
                       limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for Airbnb listings based on location and dates

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            List of listing dictionaries
        """
        pass

    @abc.abstractmethod
    def get_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Get detailed information about a specific listing

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing listing details
        """
        pass

    def __enter__(self):
        """Support for context manager protocol"""
        return self

    def __exit__(self, exc_type, exc_val, exc_tb):
        """Clean up resources when used as a context manager"""
        self.cleanup()

    def cleanup(self):
        """
        Clean up any resources used by the client

        This method should be overridden by implementations
        that need to perform cleanup operations.
        """
        pass
