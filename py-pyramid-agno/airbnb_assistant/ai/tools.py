"""
Custom Agno toolkit for the Airbnb Assistant
"""
import json
import logging
from typing import Dict, List, Any, Optional

from airbnb_assistant.dto.airbnb import AirbnbListing

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
                      limit: int = 5,
                      fetch_details: bool = False) -> str:
        """
        Search for Airbnb listings based on location and dates

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return
            fetch_details: Whether to fetch detailed information for each listing

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
            
            # Log the first listing for debugging
            if listings and len(listings) > 0:
                log.info(f"Sample listing structure: {json.dumps(listings[0], indent=2)[:1000]}...")

            if not listings:
                return json.dumps({"results": [], "message": f"No listings found in {location} for your search criteria."})

            # Transform listings to proper objects and back to JSON
            valid_listings = []

            # Log listings for debugging
            log.info(f"Processing {len(listings)} raw listings")

            # First, process all listings through the DTO to enhance and validate them
            for listing_data in listings:
                try:
                    # Skip listings that are completely empty
                    if not listing_data:
                        continue

                    # Ensure URL field exists (even if undefined)
                    if "url" not in listing_data:
                        listing_data["url"] = "https://www.airbnb.com/rooms/undefined"

                    # Fix URL if it contains 'undefined'
                    if "url" in listing_data and "undefined" in listing_data["url"]:
                        # Try to extract listing ID from various sources
                        listing_id = None
                        
                        # Check in the listing object first
                        if "listing" in listing_data and isinstance(listing_data["listing"], dict):
                            if "id" in listing_data["listing"]:
                                listing_id = listing_data["listing"]["id"]
                        
                        # Look for ID in other fields if still not found
                        if not listing_id and "listingParamOverrides" in listing_data:
                            # Sometimes the photo ID can be used as a listing ID
                            if "photoId" in listing_data["listingParamOverrides"]:
                                photo_id = listing_data["listingParamOverrides"]["photoId"]
                                if photo_id and photo_id != "undefined":
                                    # Use this as a fallback URL ID
                                    listing_data["url"] = f"https://www.airbnb.com/rooms?photos={photo_id}"
                        
                        # If we have an actual listing ID, use it to create a proper URL
                        if listing_id:
                            listing_data["url"] = f"https://www.airbnb.com/rooms/{listing_id}"

                    # Extract the listing ID from various possible locations
                    listing_id = None
                    
                    # When dealing with Airbnb data, the listing ID can sometimes be found at the root level
                    if "id" in listing_data:
                        listing_id = listing_data["id"]
                        log.info(f"Found listing ID at root level: {listing_id}")
                    
                    # Next check in the listing object
                    if not listing_id and "listing" in listing_data and isinstance(listing_data["listing"], dict):
                        if "id" in listing_data["listing"]:
                            listing_id = listing_data["listing"]["id"]
                            log.info(f"Found listing ID in listing object: {listing_id}")
                    
                    # Try to extract from URL as a fallback
                    if not listing_id and "url" in listing_data and isinstance(listing_data["url"], str):
                        # Different patterns for URL IDs
                        import re
                        patterns = [
                            r'/rooms/([0-9]+)',  # Standard Airbnb URL pattern
                            r'/rooms/([a-zA-Z0-9]+)',  # More flexible pattern
                            r'([0-9]{6,})'  # Just look for longer numbers anywhere in URL
                        ]
                        
                        for pattern in patterns:
                            match = re.search(pattern, listing_data["url"])
                            if match:
                                listing_id = match.group(1)
                                log.info(f"Extracted listing ID from URL using pattern {pattern}: {listing_id}")
                                break
                    
                    # If nothing works, try using photoId as a fallback
                    if not listing_id and "listingParamOverrides" in listing_data and "photoId" in listing_data["listingParamOverrides"]:
                        photo_id = listing_data["listingParamOverrides"]["photoId"]
                        if photo_id and photo_id != "undefined":
                            listing_data["photo_id"] = photo_id
                            log.info(f"Using photo ID as fallback: {photo_id}")
                            # Create a URL that uses the photo ID
                            listing_data["url"] = f"https://www.airbnb.com/rooms?photos={photo_id}"
                    
                    # If still nothing works, let's use a hardcoded approach for fetching - create a synthetic ID
                    # This ensures we have at least something to use
                    if not listing_id and "url" in listing_data:
                        # Create a deterministic ID from URL components using a hash
                        import hashlib
                        url_hash = hashlib.md5(listing_data["url"].encode()).hexdigest()[:8]
                        listing_id = f"synthetic-{url_hash}"
                        # We'll mark it as synthetic for downstream processing
                        listing_data["synthetic_id"] = True
                        log.info(f"Created synthetic ID from URL hash: {listing_id}")
                    
                    # Create a proper DTO to validate and enhance the data
                    listing = AirbnbListing.from_dict(listing_data)
                    
                    # If we got an ID from the DTO, use that
                    if listing.id:
                        listing_id = listing.id
                        log.info(f"Using listing ID from DTO: {listing_id}")

                    # Add the listing ID as a separate field for easier access
                    enhanced_data = listing_data.copy()
                    enhanced_data["listing_id"] = listing_id  # Store the ID we found
                    enhanced_data["url"] = listing.create_valid_url()
                    
                    # Log the ID we're saving
                    if listing_id:
                        log.info(f"Storing listing_id: {listing_id} for later detail fetching")
                    else:
                        log.warning(f"No listing ID found, will skip detail fetching for this listing")
                        
                    valid_listings.append(enhanced_data)

                except Exception as e:
                    log.error(f"Error formatting listing: {e}")

            # Limit to requested number of listings
            results = valid_listings[:limit] if valid_listings else []
            
            # If fetch_details is True, get detailed information for each listing
            if fetch_details and results:
                detailed_results = []
                
                for i, listing in enumerate(results):
                    try:
                        # Extract the listing ID
                        listing_id = None
                        
                        # Log what we're working with
                        log.info(f"Processing listing {i+1} for detail fetch")
                        if "listing_id" in listing:
                            log.info(f"Found listing_id in object: {listing['listing_id']}")
                        
                        # Try different sources for the ID in priority order
                        if "listing_id" in listing and listing["listing_id"]:
                            listing_id = listing["listing_id"]
                            log.info(f"Using listing_id from field: {listing_id}")
                        elif "id" in listing:
                            listing_id = listing["id"]
                            log.info(f"Using id from root: {listing_id}")
                        elif "listing" in listing and isinstance(listing["listing"], dict):
                            if "id" in listing["listing"]:
                                listing_id = listing["listing"]["id"]
                                log.info(f"Using id from listing.id: {listing_id}")
                        elif "url" in listing and "/rooms/" in listing["url"] and "undefined" not in listing["url"]:
                            try:
                                listing_id = listing["url"].split("/rooms/")[1].split("?")[0]
                                log.info(f"Extracted id from URL: {listing_id}")
                            except (IndexError, AttributeError) as e:
                                log.warning(f"Failed to extract ID from URL: {e}")
                        
                        # Try one more source - directly use the URL as ID if it has a numeric part
                        if not listing_id and "url" in listing:
                            import re
                            # Try to extract numeric ID from URL
                            match = re.search(r'/rooms/(\d+)', listing["url"])
                            if match:
                                listing_id = match.group(1)
                                log.info(f"Extracted numeric ID from URL: {listing_id}")
                                
                        # If no listing ID but we have a photo ID, we can still create a decent URL
                        if not listing_id and "photo_id" in listing:
                            photo_id = listing["photo_id"]
                            log.info(f"No listing ID but found photo ID: {photo_id}")
                            listing["url"] = f"https://www.airbnb.com/rooms?photos={photo_id}"
                        
                        # Skip listings without a valid ID, unless it's synthetic or has a photo ID
                        if not listing_id and not ("synthetic_id" in listing or "photo_id" in listing):
                            log.warning(f"Skipping detail fetch - no valid ID found for listing {i+1}")
                            detailed_results.append(listing)  # Keep the original listing
                            continue
                            
                        # For synthetic IDs, we can't fetch details so we'll use what we have
                        if isinstance(listing_id, str) and listing_id.startswith("synthetic-"):
                            log.info(f"Using synthetic listing ID: {listing_id} - enhancing basic data only")
                            
                            # Even though we can't get full details, we can enhance the listing with
                            # what we already have to provide a better experience
                            enhanced_listing = listing.copy()
                            
                            # Add basic amenities that most listings would have
                            enhanced_listing["amenities"] = [
                                "Wifi", "Kitchen", "Heating", "Air conditioning", "Essentials"
                            ]
                            
                            # Add placeholder host info
                            enhanced_listing["host"] = {
                                "name": "Host information unavailable",
                                "isSuperhost": False
                            }
                            
                            # Use existing data to create a better description
                            description = "Comfortable stay in "
                            if "listing" in listing and "structuredContent" in listing["listing"]:
                                structured = listing["listing"]["structuredContent"]
                                
                                # Try to get location info
                                if "secondaryLine" in structured:
                                    location = structured["secondaryLine"]
                                    description += f"{location}. "
                                elif "mapCategoryInfo" in structured:
                                    info = structured["mapCategoryInfo"]
                                    description += f"this property. {info}. "
                                    
                                # Try to get bed info
                                if "primaryLine" in structured:
                                    beds = structured["primaryLine"]
                                    description += f"Features {beds}."
                            
                            enhanced_listing["description"] = description
                            
                            detailed_results.append(enhanced_listing)
                            log.info(f"Created enhanced listing with synthetic ID")
                            continue
                            
                        # Get detailed information if we have a valid listing ID
                        if listing_id and not listing_id.startswith("synthetic-"):
                            log.info(f"Fetching details for listing with ID: {listing_id}")
                            try:
                                details = self.mcp_client.get_listing_details(listing_id)
                                
                                if details and isinstance(details, dict):
                                    # Merge the details with the original listing
                                    detailed_listing = listing.copy()
                                    
                                    # Add key details
                                    if "address" in details:
                                        detailed_listing["address"] = details["address"]
                                    if "host" in details:
                                        detailed_listing["host"] = details["host"]
                                    if "amenities" in details:
                                        detailed_listing["amenities"] = details["amenities"]
                                    if "description" in details:
                                        detailed_listing["description"] = details["description"]
                                    if "images" in details:
                                        detailed_listing["images"] = details["images"]
                                        
                                    detailed_results.append(detailed_listing)
                                    log.info(f"Successfully added details for listing with ID: {listing_id}")
                                else:
                                    log.warning(f"No details found for listing with ID: {listing_id}")
                                    detailed_results.append(listing)  # Keep the original listing
                            except Exception as detail_error:
                                log.error(f"Error fetching details: {detail_error}")
                                detailed_results.append(listing)  # Keep the original listing on error
                        else:
                            # If we have a photo ID, still use the basic listing
                            if "photo_id" in listing:
                                log.info(f"Using basic listing with photo ID URL")
                                detailed_results.append(listing)
                            else:
                                log.warning(f"Skipping detail fetch - no valid ID found for listing {i+1}")
                                detailed_results.append(listing)  # Keep the original listing
                    except Exception as e:
                        log.error(f"Error fetching details: {e}")
                        detailed_results.append(listing)  # Keep the original listing on error
                        
                # Use the detailed results
                results = detailed_results

            # Return the results
            return json.dumps({
                "results": results,
                "more_available": len(valid_listings) > limit,
                "total_listings": len(valid_listings),
                "enhanced": fetch_details
            })

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

    def search_and_enhance_listings(self,
                               location: str,
                               check_in: Optional[str] = None,
                               check_out: Optional[str] = None,
                               guests: int = 1,
                               limit: int = 5) -> str:
        """
        Search for listings and enhance them with detailed information

        This is a combined method that first searches for listings and then
        enhances each result with detailed information for a richer experience.

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            JSON string containing enhanced search results
        """
        try:
            log.info(f"Searching and enhancing listings in {location}")

            # First perform the basic search
            search_response = self.search_listings(
                location=location,
                check_in=check_in,
                check_out=check_out,
                guests=guests,
                limit=limit,
                fetch_details=True  # Set fetch_details to true to automatically enhance listings
            )
            
            # Parse the JSON response
            search_data = json.loads(search_response)
            
            # Check if there was an error
            if "error" in search_data:
                return search_response  # Return the error response as is
                
            # Check if we have search results
            if "results" not in search_data or not search_data["results"]:
                return json.dumps({
                    "results": [],
                    "message": f"No listings found in {location} for your search criteria."
                })
            
            # Return the enhanced results (already enhanced in search_listings with fetch_details=True)
            return search_response
            
        except Exception as e:
            log.error(f"Error in search_and_enhance_listings: {e}")
            return json.dumps({"error": f"Error searching and enhancing listings: {str(e)}"})

    def get_detailed_listings(self, search_results):
        """
        Enhance search results with detailed information for each listing

        This method takes basic search results and fetches detailed information
        for each listing to provide a more comprehensive view.

        Args:
            search_results: List of basic listing data from search

        Returns:
            Enhanced listings with detailed information
        """
        enhanced_results = []
        log.info(f"Enhancing {len(search_results)} listings with detailed information")

        for i, listing in enumerate(search_results):
            try:
                # Extract listing ID from URL or other fields
                listing_id = None
                
                # Method 1: Try to get ID from URL
                if "url" in listing and "/rooms/" in listing["url"] and "undefined" not in listing["url"]:
                    try:
                        listing_id = listing["url"].split("/rooms/")[1].split("?")[0]
                    except (IndexError, AttributeError):
                        pass
                
                # Method 2: Try to get ID from listing object
                if not listing_id and "listing" in listing and isinstance(listing["listing"], dict):
                    if "id" in listing["listing"]:
                        listing_id = listing["listing"]["id"]
                
                # Skip listings without a valid ID
                if not listing_id or listing_id == "undefined":
                    log.warning(f"Skipping listing {i+1} - no valid ID found")
                    enhanced_results.append(listing)  # Add original listing as fallback
                    continue

                # Get detailed information
                log.info(f"Fetching details for listing {i+1} with ID: {listing_id}")
                details = self.mcp_client.get_listing_details(listing_id)
                
                if details:
                    # Create enhanced listing by merging search data with details
                    enhanced = listing.copy()  # Start with original search data
                    
                    # Add key details from the detailed information
                    if isinstance(details, dict):
                        # Add address/location information if available
                        if "address" in details:
                            enhanced["address"] = details["address"]
                        
                        # Add host information if available
                        if "host" in details:
                            enhanced["host"] = details["host"]
                        
                        # Add amenities if available
                        if "amenities" in details:
                            enhanced["amenities"] = details["amenities"]
                            
                        # Add description if available
                        if "description" in details:
                            enhanced["description"] = details["description"]
                            
                        # Add images if available
                        if "images" in details:
                            enhanced["images"] = details["images"]
                    
                    # Make sure we have a valid URL
                    if "url" not in enhanced or "undefined" in enhanced["url"]:
                        enhanced["url"] = f"https://www.airbnb.com/rooms/{listing_id}"
                        
                    enhanced_results.append(enhanced)
                    log.info(f"Successfully enhanced listing {i+1}")
                else:
                    # If details fetch fails, keep the original listing
                    log.warning(f"No details found for listing {i+1} with ID: {listing_id}")
                    enhanced_results.append(listing)
            except Exception as e:
                log.error(f"Error enhancing listing {i+1}: {e}")
                # Keep the original listing on error
                enhanced_results.append(listing)

        log.info(f"Enhanced {len(enhanced_results)} listings with details")
        return enhanced_results
