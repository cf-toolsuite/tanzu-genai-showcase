"""
Data transfer objects (DTOs) for Airbnb listings
"""
import re
from dataclasses import dataclass
from typing import Dict, List, Any, Optional

from airbnb_assistant.dto.templates import AirbnbListingTemplate


@dataclass
class AirbnbListingStructuredContent:
    """Structured content of an Airbnb listing"""
    primary_line: Optional[str] = None
    secondary_line: Optional[str] = None
    map_category_info: Optional[str] = None


@dataclass
class AirbnbPriceLine:
    """Price information for a listing"""
    accessibility_label: Optional[str] = None


@dataclass
class AirbnbPriceExplanation:
    """Explanation of price details"""
    title: Optional[str] = None
    price_details: Optional[str] = None


@dataclass
class AirbnbStructuredDisplayPrice:
    """Structured display price for a listing"""
    primary_line: Optional[AirbnbPriceLine] = None
    secondary_line: Optional[AirbnbPriceLine] = None
    explanation_data: Optional[AirbnbPriceExplanation] = None


@dataclass
class AirbnbListing:
    """
    Data transfer object for an Airbnb listing

    This class represents the structured data of an Airbnb listing as
    received from the MCP server, with proper typing and attribute names.
    """
    # Basic listing info
    id: Optional[str] = None
    url: Optional[str] = None
    title: Optional[str] = None
    avg_rating_a11y_label: Optional[str] = None

    # Structured content
    listing: Optional[Dict[str, Any]] = None
    structured_content: Optional[AirbnbListingStructuredContent] = None
    structured_display_price: Optional[AirbnbStructuredDisplayPrice] = None

    # Overrides
    listing_param_overrides: Optional[Dict[str, Any]] = None

    # Enhanced data fields
    address: Optional[Dict[str, Any]] = None
    host: Optional[Dict[str, Any]] = None
    amenities: Optional[List[str]] = None
    description: Optional[str] = None
    images: Optional[List[Dict[str, Any]]] = None
    
    # Photo ID (useful for URL generation when room ID is not available)
    photo_id: Optional[str] = None
    category_tag: Optional[str] = None
    search_url: Optional[str] = None

    @classmethod
    def from_dict(cls, data: Dict[str, Any]) -> 'AirbnbListing':
        """
        Create an AirbnbListing instance from a dictionary

        Args:
            data: Dictionary containing listing data from the MCP server

        Returns:
            AirbnbListing instance
        """
        listing = cls()

        # Store search URL if available
        if "searchUrl" in data:
            listing.search_url = data["searchUrl"]

        # Extract photo ID and category tag if available in listingParamOverrides
        if "listingParamOverrides" in data and isinstance(data["listingParamOverrides"], dict):
            if "photoId" in data["listingParamOverrides"]:
                photo_id = data["listingParamOverrides"]["photoId"]
                if photo_id and photo_id != "undefined":
                    listing.photo_id = photo_id
            
            if "categoryTag" in data["listingParamOverrides"]:
                category_tag = data["listingParamOverrides"]["categoryTag"]
                if category_tag and category_tag != "undefined":
                    listing.category_tag = category_tag

        # Extract listing ID and create proper URL
        if "url" in data:
            listing.url = data["url"]
            # Extract ID from URL if available
            if listing.url and "/rooms/" in listing.url and "undefined" not in listing.url:
                try:
                    listing.id = listing.url.split("/rooms/")[1].split("?")[0]
                except (IndexError, AttributeError):
                    pass
        
        # If URL contains "undefined" and we have a photo ID, create a photo-based URL
        if (not listing.url or "undefined" in listing.url) and listing.photo_id:
            listing.url = f"https://www.airbnb.com/rooms?photos={listing.photo_id}"

        # Extract listing info
        if "listing" in data:
            listing.listing = data["listing"]

            # Extract listing ID from the listing object if available
            if listing.id is None and isinstance(listing.listing, dict) and "id" in listing.listing:
                listing.id = listing.listing["id"]

            # Extract structured content
            if "structuredContent" in listing.listing:
                structured_content = AirbnbListingStructuredContent()

                if "primaryLine" in listing.listing["structuredContent"]:
                    if isinstance(listing.listing["structuredContent"]["primaryLine"], dict):
                        structured_content.primary_line = listing.listing["structuredContent"]["primaryLine"].get("body",
                            listing.listing["structuredContent"]["primaryLine"].get("title", ""))
                    else:
                        structured_content.primary_line = listing.listing["structuredContent"]["primaryLine"]

                if "secondaryLine" in listing.listing["structuredContent"]:
                    if isinstance(listing.listing["structuredContent"]["secondaryLine"], dict):
                        structured_content.secondary_line = listing.listing["structuredContent"]["secondaryLine"].get("body", "")
                    else:
                        structured_content.secondary_line = listing.listing["structuredContent"]["secondaryLine"]

                if "mapCategoryInfo" in listing.listing["structuredContent"]:
                    if isinstance(listing.listing["structuredContent"]["mapCategoryInfo"], dict):
                        structured_content.map_category_info = listing.listing["structuredContent"]["mapCategoryInfo"].get("body", "")
                    else:
                        structured_content.map_category_info = listing.listing["structuredContent"]["mapCategoryInfo"]

                listing.structured_content = structured_content

        # Extract rating
        if "avgRatingA11yLabel" in data:
            listing.avg_rating_a11y_label = data["avgRatingA11yLabel"]

        # Extract price
        if "structuredDisplayPrice" in data:
            price_data = data["structuredDisplayPrice"]
            structured_price = AirbnbStructuredDisplayPrice()

            if "primaryLine" in price_data and "accessibilityLabel" in price_data["primaryLine"]:
                structured_price.primary_line = AirbnbPriceLine(
                    accessibility_label=price_data["primaryLine"]["accessibilityLabel"]
                )

            if "secondaryLine" in price_data and "accessibilityLabel" in price_data["secondaryLine"]:
                structured_price.secondary_line = AirbnbPriceLine(
                    accessibility_label=price_data["secondaryLine"]["accessibilityLabel"]
                )

            if "explanationData" in price_data:
                explanation = AirbnbPriceExplanation(
                    title=price_data["explanationData"].get("title"),
                    price_details=price_data["explanationData"].get("priceDetails")
                )
                structured_price.explanation_data = explanation

            listing.structured_display_price = structured_price

        # Extract listing param overrides
        if "listingParamOverrides" in data:
            listing.listing_param_overrides = data["listingParamOverrides"]

        # Extract enhanced details if available
        if "address" in data:
            listing.address = data["address"]

        if "host" in data:
            listing.host = data["host"]

        if "amenities" in data:
            listing.amenities = data["amenities"]

        if "description" in data:
            listing.description = data["description"]

        if "images" in data:
            listing.images = data["images"]

        return listing

    def get_price_details(self) -> str:
        """
        Get formatted price details

        Returns:
            String containing formatted price information
        """
        if not self.structured_display_price or not self.structured_display_price.primary_line:
            return "Price not available"

        price_label = self.structured_display_price.primary_line.accessibility_label or ""
        return price_label

    def get_bed_type(self) -> str:
        """
        Get the bed type information

        Returns:
            String containing bed type information
        """
        if (self.structured_content and self.structured_content.primary_line):
            return self.structured_content.primary_line
        return "Bed type not available"

    def get_rating(self) -> str:
        """
        Get formatted rating information

        Returns:
            String containing rating information
        """
        return self.avg_rating_a11y_label or "Rating not available"

    def get_host_info(self) -> str:
        """
        Get formatted host information if available

        Returns:
            String containing host information
        """
        if self.host and isinstance(self.host, dict):
            host_name = self.host.get("name", "")
            host_type = ""

            if "isSuperhost" in self.host and self.host["isSuperhost"]:
                host_type = "Superhost"

            if host_name and host_type:
                return f"{host_name} - {host_type}"
            elif host_name:
                return host_name

        # Try to get host info from structured content
        if self.structured_content and self.structured_content.map_category_info:
            host_info = self.structured_content.map_category_info
            if "stay with" in host_info.lower() or "hosting for" in host_info.lower():
                return host_info

        return "Host information not available"

    def get_amenities_text(self) -> str:
        """
        Get a formatted string of amenities if available

        Returns:
            String representation of amenities
        """
        if self.amenities and isinstance(self.amenities, list):
            # Limit to top 5 amenities
            top_amenities = self.amenities[:5]
            if top_amenities:
                return ", ".join(top_amenities)

        return "Amenities information not available"

    def get_location_text(self) -> str:
        """
        Get formatted location information if available

        Returns:
            String containing location information
        """
        # Try to get from address field first (detailed data)
        if self.address and isinstance(self.address, dict):
            location_parts = []

            if "city" in self.address:
                location_parts.append(self.address["city"])

            if "state" in self.address:
                location_parts.append(self.address["state"])

            if "neighborhood" in self.address:
                location_parts.append(self.address["neighborhood"])

            if location_parts:
                return ", ".join(location_parts)

        # Fallback to structured content
        if self.structured_content and self.structured_content.secondary_line:
            return self.structured_content.secondary_line

        return "Location information not available"

    def get_description_preview(self) -> str:
        """
        Get a preview of the description if available

        Returns:
            Preview of description (first 100 chars)
        """
        if self.description and isinstance(self.description, str):
            # Get first 100 chars of description
            preview = self.description[:100].strip()
            if len(self.description) > 100:
                preview += "..."
            return preview

        return "Description not available"

    def create_valid_url(self) -> str:
        """
        Create a valid URL for the listing

        Returns:
            Valid Airbnb URL with a real listing ID or general search URL
        """
        # First try to get the ID from the listing data
        listing_id = None

        # Check if we have a listing object with an ID
        if self.listing and isinstance(self.listing, dict) and "id" in self.listing:
            listing_id = self.listing["id"]

        # Use the ID from URL parsing as fallback
        if not listing_id and self.id:
            listing_id = self.id

        # If we have an ID, construct a valid URL
        if listing_id:
            return f"https://www.airbnb.com/rooms/{listing_id}"

        # If we have a URL that doesn't contain undefined, use it
        if self.url and "undefined" not in self.url:
            return self.url
            
        # If we have a photo ID, use it to create a URL
        if self.photo_id:
            return f"https://www.airbnb.com/rooms?photos={self.photo_id}"

        # Check if this listing has param overrides which might contain an ID
        if self.listing_param_overrides and isinstance(self.listing_param_overrides, dict):
            # Check for photo ID which can be used to search
            if "photoId" in self.listing_param_overrides:
                photo_id = self.listing_param_overrides["photoId"]
                if photo_id and photo_id != "undefined":
                    # We can use the photo ID in a search URL which is better than nothing
                    return f"https://www.airbnb.com/rooms?photos={photo_id}"
            # Check for category tag which can be used in a search URL
            if "categoryTag" in self.listing_param_overrides:
                category_tag = self.listing_param_overrides["categoryTag"]
                if category_tag and category_tag.startswith("Tag:"):
                    tag_id = category_tag.split(":")[1]
                    return f"https://www.airbnb.com/s/homes?category_tag_id={tag_id}"

        # If we have a search URL, use that as it's better than the generic homepage
        if self.search_url:
            return self.search_url

        # Default to the Airbnb home page as fallback
        return "https://www.airbnb.com"

    def get_best_title(self) -> str:
        """
        Generate the best possible title for the listing based on available data

        Returns:
            A descriptive title string
        """
        # If we have host information from detailed data, use it
        if self.host and isinstance(self.host, dict) and "name" in self.host:
            host_name = self.host.get("name", "")
            if host_name:
                # Get location
                location = self.get_location_text()
                if location and location != "Location information not available":
                    return f"Stay hosted by {host_name} in {location}"
                else:
                    return f"Stay hosted by {host_name}"

        # Check for map category info (usually has host name and property type)
        if self.structured_content and self.structured_content.map_category_info:
            return self.structured_content.map_category_info

        # Check for primary line (usually contains bed information)
        bed_info = self.get_bed_type()

        # If we have bed info, create a descriptive title
        if bed_info and bed_info != "Bed type not available":
            if "queen" in bed_info.lower():
                return f"Comfortable Queen Bed Accommodation"
            elif "king" in bed_info.lower():
                return f"Spacious King Bed Accommodation"
            elif "double" in bed_info.lower():
                return f"Cozy Double Bed Accommodation"
            elif re.search(r'(\d+)\s+beds?', bed_info.lower()):
                # Extract number of beds and create appropriate title
                match = re.search(r'(\d+)\s+beds?', bed_info.lower())
                if match and match.group(1):
                    num_beds = match.group(1)
                    if num_beds == "1":
                        return "Private Room Accommodation"
                    elif num_beds == "2":
                        return "Two-Bed Accommodation"
                    else:
                        return f"Spacious {num_beds}-Bed Accommodation"
            else:
                return f"Comfortable {bed_info} Accommodation"

        # Fallback to generic title
        return "Comfortable Stay"

    def to_markdown(self) -> str:
        """
        Format the listing as Markdown

        Returns:
            Markdown formatted listing information
        """
        url = self.create_valid_url()
        price = self.get_price_details()
        bed_type = self.get_bed_type()
        rating = self.get_rating()
        title = self.get_best_title()

        # Get additional fields if available
        host_info = self.get_host_info()
        amenities = self.get_amenities_text()
        location = self.get_location_text()
        description = self.get_description_preview()

        # Build markdown
        markdown = ""
        markdown += f"**{title}**\n"
        markdown += f"- **Price:** {price}\n"
        markdown += f"- **Bed Type:** {bed_type}\n"
        markdown += f"- **Rating:** {rating}\n"

        # Add enhanced fields if available
        if host_info and host_info != "Host information not available":
            markdown += f"- **Host:** {host_info}\n"

        if location and location != "Location information not available":
            markdown += f"- **Location:** {location}\n"

        if amenities and amenities != "Amenities information not available":
            markdown += f"- **Amenities:** {amenities}\n"

        if description and description != "Description not available":
            markdown += f"- **Description:** {description}\n"

        markdown += f"- [View on Airbnb]({url})"

        return markdown

    def to_template(self) -> AirbnbListingTemplate:
        """
        Convert to a template object for consistent formatting

        Returns:
            AirbnbListingTemplate object with listing data
        """
        # Create valid URL first - this is important for filtering
        valid_url = self.create_valid_url()

        # Extract any secondary location data from structured content
        location = self.get_location_text()

        # Get enhanced information
        host_info = self.get_host_info()
        amenities = self.get_amenities_text()
        description = self.get_description_preview()

        return AirbnbListingTemplate(
            title=self.get_best_title(),
            price=self.get_price_details(),
            bed_type=self.get_bed_type(),
            rating=self.get_rating(),
            url=valid_url,
            location=location,
            host=host_info,
            amenities=amenities,
            description=description
        )
