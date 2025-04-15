"""
MCP Client implementation for Airbnb
"""
import logging
import os
from typing import Dict, List, Any, Optional

from .clients import (
    create_mcp_client,
    MCPAirbnbClientBase,
    MCPAirbnbMockClient,
    MCPAirbnbHTTPClient,
    MCPAirbnbStdioClient
)

log = logging.getLogger(__name__)

class MCPAirbnbClient(MCPAirbnbClientBase):
    """
    Client for interacting with the MCP Airbnb server

    This implementation follows the Model Context Protocol (MCP) patterns
    to provide external data and actions to the Agno agent.

    It uses a factory pattern to create the appropriate client implementation
    based on configuration and environment variables.
    """

    def __init__(self, mcp_url: Optional[str] = None):
        """
        Initialize the MCP Airbnb client

        Args:
            mcp_url: URL of the MCP server (defaults to environment variable or localhost)
        """
        # Check environment variables to determine client type
        self.use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
        self.use_stdio = os.environ.get('MCP_USE_STDIO', 'false').lower() == 'true'

        # Get MCP server path from environment if using stdio
        mcp_server_path = os.environ.get('MCP_SERVER_PATH')

        # Determine command and args for stdio client
        mcp_command = None
        mcp_args = None

        if self.use_stdio and mcp_server_path:
            # Determine the command and args based on file extension
            if mcp_server_path.endswith('.js'):
                mcp_command = 'node'
                mcp_args = [mcp_server_path]
            elif mcp_server_path.endswith('.py'):
                mcp_command = 'python'
                mcp_args = [mcp_server_path]
            else:
                # Default to just executing the file directly (assuming it's executable)
                mcp_command = mcp_server_path
                mcp_args = []

        # Create the appropriate client implementation
        self.client = create_mcp_client(
            use_mock=self.use_mock,
            use_stdio=self.use_stdio,
            mcp_url=mcp_url,
            mcp_command=mcp_command,
            mcp_args=mcp_args
        )

        # Log the client type
        client_type = type(self.client).__name__
        log.info(f"Created MCP client of type: {client_type}")

    def search_listings(self,
                       location: str,
                       check_in: Optional[str] = None,
                       check_out: Optional[str] = None,
                       guests: int = 1,
                       limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for Airbnb listings

        Args:
            location: Location to search for listings
            check_in: Check-in date (YYYY-MM-DD)
            check_out: Check-out date (YYYY-MM-DD)
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            List of listing dictionaries
        """
        return self.client.search_listings(
            location=location,
            check_in=check_in,
            check_out=check_out,
            guests=guests,
            limit=limit
        )

    def get_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Get details for a specific listing

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing listing details
        """
        return self.client.get_listing_details(listing_id)

    def cleanup(self):
        """Clean up resources used by the client"""
        if hasattr(self, 'client') and self.client:
            self.client.cleanup()

    def __del__(self):
        """Clean up resources when the object is destroyed"""
        self.cleanup()
