"""
Template objects for formatting Airbnb listings
"""
from dataclasses import dataclass
from typing import Optional


@dataclass
class AirbnbListingTemplate:
    """Template for consistently formatting Airbnb listings"""
    title: str
    price: str
    bed_type: str
    rating: str
    url: str
    location: Optional[str] = None
    host: Optional[str] = None
    amenities: Optional[str] = None
    description: Optional[str] = None

    def to_markdown(self, index: int) -> Optional[str]:
        """
        Format the listing as markdown with consistent structure

        Args:
            index: The index number for this listing (1-based)

        Returns:
            Formatted markdown string or None if URL is invalid
        """
        # Check for completely unusable URLs - only filter out if it's the generic homepage
        if not self.url or self.url == "https://www.airbnb.com":
            return None

        markdown = f"### {index}. **{self.title}**\n"
        markdown += f"- **Price:** {self.price}\n"
        markdown += f"- **Bed Type:** {self.bed_type}\n"
        markdown += f"- **Rating:** {self.rating}\n"

        if self.location and self.location != "Location information not available":
            markdown += f"- **Location:** {self.location}\n"

        # Add enhanced details if available
        if self.host and self.host != "Host information not available":
            markdown += f"- **Host:** {self.host}\n"

        if self.amenities and self.amenities != "Amenities information not available":
            markdown += f"- **Amenities:** {self.amenities}\n"

        if self.description and self.description != "Description not available":
            markdown += f"- **Description:** {self.description}\n"

        # If URL contains 'undefined', try to replace it with a valid domain
        url = self.url
        if "undefined" in url:
            url = url.replace("/rooms/undefined", "")
            # Make sure the URL is valid
            if not url.startswith("https://"):
                url = "https://www.airbnb.com"

        # Create a nice link with clear text
        if "/rooms?photos=" in url:
            # This is a photo ID link
            markdown += f"- [View Listing on Airbnb]({url})\n\n"
        elif "/s/homes?category_tag_id=" in url:
            # This is a category tag link
            markdown += f"- [View Property on Airbnb]({url})\n\n"
        elif "/s/" in url:
            # This is a search URL
            markdown += f"- [Browse Airbnb Listings]({url})\n\n"
        else:
            # This is a standard listing URL
            markdown += f"- [View on Airbnb]({url})\n\n"

        return markdown
