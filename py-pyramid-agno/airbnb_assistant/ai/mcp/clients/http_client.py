"""
HTTP client implementation for MCP Airbnb
"""
import json
import logging
import os
import requests
from typing import Dict, List, Any, Optional

from airbnb_assistant.dto.airbnb import AirbnbListing

from .base import MCPAirbnbClientBase
from .mock_client import MCPAirbnbMockClient

log = logging.getLogger(__name__)

class MCPAirbnbHTTPClient(MCPAirbnbClientBase):
    """
    HTTP client implementation for communicating with the MCP Airbnb server

    This implementation sends HTTP requests to the MCP server following
    the JSON-RPC protocol defined by the Model Context Protocol.
    """

    def __init__(self, mcp_url: Optional[str] = None):
        """
        Initialize the HTTP client

        Args:
            mcp_url: URL of the MCP server (defaults to environment variable or localhost)
        """
        self.mcp_url = mcp_url or os.environ.get('MCP_AIRBNB_URL', 'http://localhost:3000')
        log.info(f"Initializing HTTP MCP client with URL: {self.mcp_url}")
        self.session = requests.Session()

    def search_listings(self,
                       location: str,
                       check_in: Optional[str] = None,
                       check_out: Optional[str] = None,
                       guests: int = 1,
                       limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for Airbnb listings using the MCP server over HTTP

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            List of listing dictionaries

        Raises:
            RuntimeError: If there's an error communicating with the MCP server
        """
        try:
            # Log search parameters
            log.info(f"HTTP client: Searching for listings in {location} for {guests} guests")
            if check_in and check_out:
                log.info(f"HTTP client: Date range: {check_in} to {check_out}")

            # Prepare the arguments for the request
            arguments = {
                "location": location,
                "adults": guests,
                "limit": limit
            }

            # Add date parameters if provided
            if check_in:
                arguments["checkin"] = check_in

            if check_out:
                arguments["checkout"] = check_out

            # Create the JSON-RPC request following the CallToolRequestSchema
            rpc_request = {
                "jsonrpc": "2.0",
                "id": 1,
                "method": "callTool",
                "params": {
                    "name": "airbnb_search",
                    "arguments": arguments
                }
            }

            # Log the request for debugging
            log.debug(f"Sending HTTP request to MCP server: {json.dumps(rpc_request, indent=2)}")

            # Send the request to the MCP server
            response = self.session.post(
                self.mcp_url,
                json=rpc_request,
                timeout=10
            )
            response.raise_for_status()

            # Parse the response
            data = response.json()

            # Check for errors in the response
            if "error" in data:
                error = data["error"]
                error_msg = f"MCP server error: {error}"
                log.error(error_msg)
                raise RuntimeError(error_msg)

            # Extract the results from the response
            if "result" in data:
                result = data["result"]

                # Check for content in the result
                if "content" in result:
                    for part in result["content"]:
                        if part.get("type") == "text":
                            try:
                                # Parse the text content as JSON
                                text_content = part.get("text", "{}")
                                result_data = json.loads(text_content)

                                # Look for search results in different possible locations
                                if "searchResults" in result_data:
                                    search_results = result_data["searchResults"]
                                    log.info(f"Found {len(search_results)} search results")
                                    
                                    # Process results using the DTO model
                                    processed_results = []
                                    for result in search_results:
                                        try:
                                            # Skip listings that don't have necessary data
                                            if not result:
                                                continue
                                                
                                            # Create AirbnbListing object
                                            listing = AirbnbListing.from_dict(result)
                                            
                                            # Fix URL if undefined
                                            if "url" in result and "undefined" in result["url"]:
                                                # Try to extract listing ID
                                                listing_id = None
                                                if "listing" in result and "id" in result["listing"]:
                                                    listing_id = result["listing"]["id"]
                                                    # Fix the URL with the real ID
                                                    if listing_id:
                                                        result["url"] = f"https://www.airbnb.com/rooms/{listing_id}"
                                                else:
                                                    # Skip listings with undefined URLs if we can't fix them
                                                    continue
                                            
                                            # Enhance with valid URL
                                            valid_url = listing.create_valid_url()
                                            if valid_url != "https://www.airbnb.com":
                                                # Only add listings with valid URLs
                                                result["url"] = valid_url
                                                processed_results.append(result)
                                        except Exception as e:
                                            log.warning(f"Error processing listing: {e}")
                                    
                                    # Only return valid listings
                                    return processed_results

                                if "searchUrl" in result_data and "searchResults" in result_data:
                                    search_results = result_data["searchResults"]
                                    log.info(f"Found {len(search_results)} search results with URL")
                                    
                                    # Process results using the DTO model
                                    processed_results = []
                                    for result in search_results:
                                        try:
                                            # Skip listings that don't have necessary data
                                            if not result:
                                                continue
                                                
                                            # Create AirbnbListing object
                                            listing = AirbnbListing.from_dict(result)
                                            
                                            # Fix URL if undefined
                                            if "url" in result and "undefined" in result["url"]:
                                                # Try to extract listing ID
                                                listing_id = None
                                                if "listing" in result and "id" in result["listing"]:
                                                    listing_id = result["listing"]["id"]
                                                    # Fix the URL with the real ID
                                                    if listing_id:
                                                        result["url"] = f"https://www.airbnb.com/rooms/{listing_id}"
                                                else:
                                                    # Skip listings with undefined URLs if we can't fix them
                                                    continue
                                            
                                            # Enhance with valid URL
                                            valid_url = listing.create_valid_url()
                                            if valid_url != "https://www.airbnb.com":
                                                # Only add listings with valid URLs
                                                result["url"] = valid_url
                                                processed_results.append(result)
                                        except Exception as e:
                                            log.warning(f"Error processing listing: {e}")
                                    
                                    # Only return valid listings
                                    return processed_results
                            except json.JSONDecodeError as e:
                                log.warning(f"Failed to parse response as JSON: {e}")

            # If we couldn't find any results, log a warning and raise an error
            error_msg = "No search results found in MCP server response"
            log.warning(error_msg)
            raise RuntimeError(error_msg)

        except requests.RequestException as e:
            # Handle HTTP request errors
            error_msg = f"HTTP error communicating with MCP server: {e}"
            log.error(error_msg)
            raise RuntimeError(error_msg)

        except Exception as e:
            # Handle any other errors
            if isinstance(e, RuntimeError):
                # Rethrow RuntimeError exceptions we created
                raise

            # For any other exceptions, create a new RuntimeError
            error_msg = f"Error searching for listings: {e}"
            log.error(error_msg)
            raise RuntimeError(error_msg)

    def get_listing_details(self, listing_id: str) -> Dict[str, Any]:
        """
        Get details for a specific listing using the MCP server over HTTP

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing listing details

        Raises:
            RuntimeError: If there's an error communicating with the MCP server
        """
        try:
            # Log request
            log.info(f"HTTP client: Getting details for listing {listing_id}")

            # Create the JSON-RPC request following the CallToolRequestSchema
            rpc_request = {
                "jsonrpc": "2.0",
                "id": 1,
                "method": "callTool",
                "params": {
                    "name": "airbnb_listing_details",
                    "arguments": {
                        "id": listing_id
                    }
                }
            }

            # Log the request for debugging
            log.debug(f"Sending HTTP request to MCP server: {json.dumps(rpc_request, indent=2)}")

            # Send the request to the MCP server
            response = self.session.post(
                self.mcp_url,
                json=rpc_request,
                timeout=10
            )
            response.raise_for_status()

            # Parse the response
            data = response.json()

            # Check for errors in the response
            if "error" in data:
                error = data["error"]
                error_msg = f"MCP server error: {error}"
                log.error(error_msg)
                raise RuntimeError(error_msg)

            # Extract the results from the response
            if "result" in data:
                result = data["result"]

                # Check for content in the result
                if "content" in result:
                    for part in result["content"]:
                        if part.get("type") == "text":
                            try:
                                # Parse the text content as JSON
                                text_content = part.get("text", "{}")
                                result_data = json.loads(text_content)

                                # Look for listing details in different possible locations
                                if "details" in result_data:
                                    log.info(f"Found listing details")
                                    return result_data["details"]

                                if "listingUrl" in result_data and "details" in result_data:
                                    log.info(f"Found listing details with URL")
                                    return result_data["details"]
                            except json.JSONDecodeError as e:
                                log.warning(f"Failed to parse response as JSON: {e}")

            # If we couldn't find any details, log a warning and raise an error
            error_msg = "No listing details found in MCP server response"
            log.warning(error_msg)
            raise RuntimeError(error_msg)

        except requests.RequestException as e:
            # Handle HTTP request errors
            error_msg = f"HTTP error communicating with MCP server: {e}"
            log.error(error_msg)
            raise RuntimeError(error_msg)

        except Exception as e:
            # Handle any other errors
            if isinstance(e, RuntimeError):
                # Rethrow RuntimeError exceptions we created
                raise

            # For any other exceptions, create a new RuntimeError
            error_msg = f"Error getting listing details: {e}"
            log.error(error_msg)
            raise RuntimeError(error_msg)

    def cleanup(self):
        """Clean up the HTTP session"""
        if self.session:
            try:
                self.session.close()
            except Exception as e:
                log.error(f"Error closing HTTP session: {e}")
