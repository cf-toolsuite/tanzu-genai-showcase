"""
AI agent implementation using Agno for the Airbnb Assistant
"""
import logging
import os
import shutil
import uuid
import json
import re
from typing import Dict, Any, Optional, List, Union
from urllib.parse import urlparse

from airbnb_assistant.dto.airbnb import AirbnbListing
from airbnb_assistant.dto.templates import AirbnbListingTemplate

# Import Agno components with error handling
try:
    from agno.agent import Agent
    from agno.models.openai import OpenAIChat
    from agno.models.openai.like import OpenAILike
    # Use proper import locations for Agno components
    # RunResponse is likely part of the agent module
    AGNO_IMPORTED = True
except (ImportError, AttributeError) as e:
    logging.warning(f"Could not import Agno components: {e}. Using mock implementation.")
    AGNO_IMPORTED = False

from .tools import AirbnbTools
from .mcp.client import MCPAirbnbClient
from .mcp.clients import create_mcp_client

log = logging.getLogger(__name__)

# System prompt for the Airbnb Assistant
SYSTEM_PROMPT = """
You are an Airbnb search assistant that helps users find accommodations.
Always follow these guidelines:

1. Return a MAXIMUM of 5 listings unless the user explicitly asks for more.

2. For each listing, provide as much of the following information as is available:
   - A descriptive title that highlights key features
   - Pricing information with total and per-night cost
   - Rating information with review count
   - Bed/room configuration
   - Host information if available (this may be limited for some listings)
   - Basic amenities (note: these may be general assumptions for some listings)
   - Brief description (may be generated based on available data)
   - A WORKING LINK to the listing on Airbnb

3. Format listings consistently using this template:
   ### [NUMBER]. [PROPERTY_TYPE] in [LOCATION]
   - **Price:** $X total ($Y per night)
   - **Rating:** [RATING]/5 ([NUM] reviews)
   - **Beds:** [DETAILS]
   - **Host:** [HOST_NAME] - [SUPERHOST_STATUS]
   - **Amenities:** [TOP_5_AMENITIES]
   - **Description:** [BRIEF_DESCRIPTION]
   - **[View on Airbnb](https://www.airbnb.com/rooms/[LISTING_ID])**

4. IMPORTANT: Always use the search_listings tool with fetch_details=true parameter to get comprehensive listing information. The fetch_details parameter ensures that the system will try to enhance basic listings with additional details when possible.

5. Present information clearly and concisely without empty headings or sections.

6. If the user asks for additional listings beyond the initial 5, provide them in the same format.

7. When formatting search results, make each listing detailed and informative:
   - Highlight unique features of each property
   - Emphasize amenities that match the user's preferences if mentioned
   - Call attention to Superhosts when available
   - Note any special deals or discounts in pricing

8. When NO VALID LISTINGS are found, provide helpful alternatives:
   - Suggest trying a different date range
   - Recommend nearby neighborhoods or areas
   - Offer to search with different criteria
   - Provide the general Airbnb search URL for manual searching

9. IMPORTANT: Some listings might have limited information available. In those cases, focus on presenting the information that is available rather than skipping the listing entirely.
"""

class AirbnbAssistantAgent:
    """
    Airbnb Assistant Agent implementation

    This class provides a wrapper around the Agno Agent to handle the Airbnb Assistant
    specific functionality. It creates and configures the agent and handles the
    processing of queries.
    """

    def __init__(self,
                api_key: str,
                model: str = "gpt-4o-mini",
                api_url: str = "https://api.openai.com/v1",
                mcp_url: Optional[str] = None,
                mcp_use_stdio: bool = False,
                mcp_command: Optional[str] = None,
                mcp_args: Optional[List[str]] = None):
        """
        Initialize the Airbnb Assistant Agent

        The agent can use environment variables with GENAI_ prefix to override settings:
        - GENAI_API_KEY: API key for the GenAI provider
        - GENAI_MODEL: Model ID to use
        - GENAI_API_URL: API URL for the GenAI provider
        - GENAI_PROVIDER: Provider type (openai, azure, anthropic, etc.)
        """
        # Use environment variables with GENAI_ prefix if available
        self.api_key = os.environ.get("GENAI_API_KEY", api_key)
        self.model = os.environ.get("GENAI_MODEL", model)
        self.api_url = os.environ.get("GENAI_API_URL", api_url)
        self.provider = os.environ.get("GENAI_PROVIDER", "").lower()
        self.mcp_url = mcp_url
        self.mcp_use_stdio = mcp_use_stdio
        self.mcp_command = mcp_command
        self.mcp_args = mcp_args
        self.agent = None
        self.mcp_client = None

        # Session management
        self.sessions = {}

        # If Agno is imported, create a real agent, otherwise use mock implementation
        if AGNO_IMPORTED:
            try:
                self.agent = self._create_agent()
            except Exception as e:
                log.error(f"Failed to create Agno agent: {e}")
                self.agent = None

    def _format_template_listing(self, listing, index):
        """
        Format a listing using a consistent template

        Args:
            listing: AirbnbListing object
            index: Index number to display (1-based)

        Returns:
            Formatted markdown string or None if URL is invalid
        """
        # Get basic info
        title = listing.get_best_title()
        price = listing.get_price_details()
        bed_type = listing.get_bed_type()
        rating = listing.get_rating()
        url = listing.create_valid_url()
        host_info = listing.get_host_info()
        location_info = listing.get_location_text()

        # Skip listings with invalid URLs
        if url == "https://www.airbnb.com":
            return None

        # Create consistent template
        markdown = f"### {index}. **{title}**\n"
        markdown += f"- **Price:** {price}\n"
        markdown += f"- **Bed Type:** {bed_type}\n"
        markdown += f"- **Rating:** {rating}\n"

        # Add host info if available
        if host_info and host_info != "Host information not available":
            markdown += f"- **Host:** {host_info}\n"

        # Add location info if available
        if location_info and location_info != "Location information not available":
            markdown += f"- **Location:** {location_info}\n"

        # Create appropriate link text based on URL type
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

    def _extract_listings_from_response(self, text):
        """
        Extract listings from JSON in response text

        Args:
            text: Response text that may contain JSON

        Returns:
            List of AirbnbListing objects
        """
        listings = []

        # Try to find JSON in the response with multiple patterns
        try:
            # Pattern 1: Look for standard JSON results pattern
            json_matches = re.findall(r'\{\s*"results"\s*:\s*\[.*?\]\s*\}', text, re.DOTALL)

            # Pattern 2: Look for searchResults pattern (from MCP response)
            if not json_matches:
                json_matches = re.findall(r'\{\s*"searchResults"\s*:\s*\[.*?\]\s*\}', text, re.DOTALL)

            # Pattern 3: Look for any array of listings
            if not json_matches:
                json_matches = re.findall(r'\[\s*\{.*?"url".*?\}\s*\]', text, re.DOTALL)

            # Process the matches if found
            if json_matches:
                for match in json_matches:
                    try:
                        # Pattern 1 & 2: Parse with appropriate key
                        if '"results"' in match or '"searchResults"' in match:
                            json_data = json.loads(match)
                            # Use the appropriate key based on what we found
                            results_key = 'results' if 'results' in json_data else 'searchResults'
                            if results_key in json_data and isinstance(json_data[results_key], list):
                                for item in json_data[results_key]:
                                    try:
                                        listing = AirbnbListing.from_dict(item)
                                        listings.append(listing)
                                    except Exception as e:
                                        log.warning(f"Error creating listing from JSON: {e}")
                        # Pattern 3: Parse direct array
                        else:
                            items = json.loads(match)
                            if isinstance(items, list):
                                for item in items:
                                    try:
                                        listing = AirbnbListing.from_dict(item)
                                        listings.append(listing)
                                    except Exception as e:
                                        log.warning(f"Error creating listing from array JSON: {e}")
                    except json.JSONDecodeError as je:
                        log.warning(f"Failed to parse JSON match: {je}")
                        continue
        except Exception as e:
            log.warning(f"Error extracting listings from response: {e}")

        # If we found listings, log how many
        if listings:
            log.info(f"Extracted {len(listings)} listings from response text")

        return listings

    def _format_response_as_markdown(self, text: str, max_listings=5) -> str:
        """
        Format the response text as proper markdown to improve readability

        Args:
            text: Raw response text from the LLM
            max_listings: Maximum number of listings to show (default: 5)

        Returns:
            Formatted text with proper markdown
        """
        if not text:
            return ""

        # Check if the response text might contain JSON for listing data
        listings = self._extract_listings_from_response(text)

        if listings:
            # Log how many listings we found
            log.info(f"Found {len(listings)} listings in response text to format as markdown")

            # Limit to max_listings unless explicitly requested more
            # (We'll handle this based on the text content)
            show_more = "more" in text.lower() or "additional" in text.lower()
            actual_listings = listings if show_more else listings[:max_listings]

            # Format the listings as markdown
            formatted_text = "# Airbnb Listings Search Results\n\n"

            # Add each listing using the template
            valid_listings = 0
            for i, listing in enumerate(actual_listings):
                try:
                    # Convert to template for consistent formatting
                    template = listing.to_template()
                    listing_markdown = template.to_markdown(valid_listings + 1)

                    # If template formatting fails, try direct formatting
                    if not listing_markdown:
                        listing_markdown = self._format_template_listing(listing, valid_listings + 1)

                    if listing_markdown:
                        formatted_text += listing_markdown
                        valid_listings += 1
                except Exception as e:
                    log.warning(f"Error formatting listing {i}: {e}")
                    # Try alternate formatting method as fallback
                    try:
                        listing_markdown = self._format_template_listing(listing, valid_listings + 1)
                        if listing_markdown:
                            formatted_text += listing_markdown
                            valid_listings += 1
                    except Exception as e2:
                        log.error(f"Fallback formatting also failed: {e2}")

            # Add more info if available
            if len(listings) > max_listings and not show_more:
                formatted_text += "\n### Additional Options\n\n"
                formatted_text += f"There are {len(listings) - max_listings} more listings available. Would you like to see more options?\n"

            # If we didn't successfully format any listings, provide a helpful message
            if valid_listings == 0:
                formatted_text = "# Airbnb Listings Search Results\n\n"
                formatted_text += "I found several listings matching your search criteria, but I'm having trouble displaying them properly.\n\n"
                formatted_text += "You can visit [Airbnb directly](https://www.airbnb.com) to search for listings in your desired location.\n\n"
                formatted_text += "Alternatively, please try your search again with more specific details like neighborhood, dates, or number of guests."

            return formatted_text

        # If we didn't find listings JSON or couldn't format it, use general formatting
        import re

        # Check if the response contains structured listings data (either directly or in mentions)
        # and format it in a more readable way
        listings_data = []

        # First, handle the common case where listings are mentioned directly
        # Pattern could be "Here are some listings in..." or similar phrases
        if any(phrase in text.lower() for phrase in ["listing", "here are", "found these"]):
            # Look for listing patterns
            listing_blocks = re.findall(r'(\*\*[\w\s]+ [\w\s]+\*\*.*?)(?=\*\*[\w\s]+ [\w\s]+\*\*|\Z)', text, re.DOTALL)
            if not listing_blocks:
                # Try another pattern for numbered listings
                listing_blocks = re.findall(r'(\d+\.\s+[\w\s]+ [\w\s]+.*?)(?=\d+\.\s+|\Z)', text, re.DOTALL)

            if listing_blocks:
                # Enhanced formatting for listing blocks
                formatted_blocks = []
                for i, block in enumerate(listing_blocks):
                    # Clean up the block and add better spacing
                    block = block.strip()

                    # Make sure title is bold and on its own line
                    if not block.startswith('**'):
                        title_match = re.match(r'(?:\d+\.\s+)?(.+?)(?:\n|$)', block)
                        if title_match:
                            title = title_match.group(1).strip()
                            block = f"**{title}**\n" + block[len(title_match.group(0)):]

                    # Ensure bullet points for key features
                    features = ['Location', 'Price', 'Rating', 'Bedroom', 'Bathroom', 'Guest', 'Amenities']
                    for feature in features:
                        block = re.sub(
                            fr'(\n|^)(\s*)({feature}s?:?)(\s+)',
                            fr'\1\2* **{feature}s:** ',
                            block
                        )

                    # Ensure consistent formatting for amenities lists
                    block = re.sub(
                        r'(Amenities:.*?)((?:WiFi|Kitchen|Pool|Gym|Air conditioning|[\w\s]+)(?:,\s*[\w\s]+)*)',
                        lambda m: m.group(1) + "\n  * " + m.group(2).replace(", ", "\n  * "),
                        block,
                        flags=re.DOTALL
                    )

                    # Add divider between listings for clarity
                    formatted_blocks.append(f"### {i+1}. {block}\n")

                # Replace all listings with formatted ones and add a top-level heading
                text = "# Airbnb Listings Search Results\n\n" + "\n".join(formatted_blocks)

        # Handle URLs and images
        urls = {}
        url_pattern = r'\[(.*?)\]\((https?://[^\s)]+)\)'

        # Find all markdown links first
        for match in re.finditer(url_pattern, text):
            display_text = match.group(1)
            url = match.group(2)
            placeholder = f"__URL_PLACEHOLDER_{len(urls)}"
            urls[placeholder] = (display_text, url)
            text = text.replace(match.group(0), placeholder)

        # Fix Airbnb image URLs
        airbnb_image_url_pattern = r'(?<!\()(?<!\[)(https?://a0\.muscache\.com/[^\s)\]]+)(?!\))(?!\])'
        for match in re.finditer(airbnb_image_url_pattern, text):
            url = match.group(0)
            # Extract just the filename for display text
            filename = url.split('/')[-1].split('.')[0][:10] + '...'
            placeholder = f"__URL_PLACEHOLDER_{len(urls)}"
            urls[placeholder] = (f"Listing Image", url)
            text = text.replace(url, placeholder)

        # Fix any other raw URLs
        raw_url_pattern = r'(?<!\()(?<!\[)(https?://(?!a0\.muscache\.com)[^\s)\]]+)(?!\))(?!\])'
        for match in re.finditer(raw_url_pattern, text):
            url = match.group(0)
            placeholder = f"__URL_PLACEHOLDER_{len(urls)}"
            urls[placeholder] = (url, url)
            text = text.replace(url, placeholder)

        # Fix general markdown formatting issues
        text = re.sub(r'(^|\n)(\d+\.)(\s*\w)', r'\1\2 \3', text)  # Numbered lists
        text = re.sub(r'(^|\n)(\*)(\s*\w)', r'\1\2 \3', text)      # Bullet points
        text = re.sub(r'(^|\n)(#{1,6})([^\s#])', r'\1\2 \3', text) # Headers

        # Replace the URL placeholders back
        for placeholder, (display_text, url) in urls.items():
            # Check if this is an image URL from Airbnb
            parsed_url = urlparse(url)
            if parsed_url.hostname == 'a0.muscache.com' and not url.endswith(('.pdf', '.doc', '.txt')):
                text = text.replace(placeholder, f"\n![{display_text}]({url})\n")
            else:
                text = text.replace(placeholder, f"[{display_text}]({url})")

        # Final cleanup - fix any duplicate blank lines
        text = re.sub(r'\n{3,}', '\n\n', text)

        # Replace any "undefined" URLs with a general Airbnb link
        text = re.sub(r'https://www\.airbnb\.com/rooms/undefined', 'https://www.airbnb.com', text)
        # Fix any URLs with photo IDs to use proper format
        text = re.sub(r'\[([^\]]+)\]\(https://www\.airbnb\.com/rooms\?photos=([0-9]+)\)', r'[View Listing with Photos](https://www.airbnb.com/rooms?photos=\2)', text)

        # Log the formatted text for debugging
        log.debug(f"Formatted response text:\n{text}")

        return text

    def _create_agent(self):
        """
        Create and configure an Agno agent for the Airbnb Assistant

        Returns:
            Agent: Configured Agno agent
        """
        # Try to get provider-specific API key if GENAI_API_KEY is not set
        if self.api_key in [None, "", "your_api_key_here"] and self.provider:
            provider_env_key = f"{self.provider.upper()}_API_KEY"
            self.api_key = os.environ.get(provider_env_key, "")
            if self.api_key:
                log.info(f"Using API key from {provider_env_key} environment variable")

        if not self.api_key or self.api_key in ["your_api_key_here"]:
            log.warning("No API key provided for AI agent")
            return None

        try:
            # Determine if using OpenRouter or another OpenAI-compatible API
            is_openrouter = "openrouter.ai" in self.api_url
            is_compatible_api = self.provider in ["azure", "anyscale", "together", "groq", "cohere"] or \
                              any(name in self.api_url for name in ["azure", "anyscale", "together", "groq", "cohere"])

            # Log what provider we're using
            # Configure client parameters based on provider type
            client_params = {
                "id": self.model,
                "api_key": self.api_key
            }

            # Add any provider-specific headers if needed
            if self.provider == "openrouter" or "openrouter.ai" in self.api_url:
                client_params["default_headers"] = {
                    "HTTP-Referer": "https://airbnb-assistant.example.com",
                    "X-Title": "Airbnb Assistant"
                }

            # Add base_url if specified
            if self.api_url:
                client_params["base_url"] = self.api_url

            # Create appropriate model instance based on provider type
            if is_openrouter:
                log.info(f"Using OpenRouter with model: {self.model}")
                # Use OpenAILike for OpenRouter
                model = OpenAILike(**client_params)
            elif is_compatible_api:
                log.info(f"Using OpenAI-compatible provider ({self.provider or 'custom'}) with model: {self.model}")
                # Use OpenAILike for other compatible APIs
                model = OpenAILike(**client_params)
            else:
                log.info(f"Using OpenAI with model: {self.model}")
                # Create standard OpenAI model
                model = OpenAIChat(**client_params)

            # Create the appropriate MCP client based on configuration
            # We should only create one client based on the configuration
            use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
            if use_mock:
                log.info("Using mock MCP client as configured by USE_MOCK_DATA")
                self.mcp_client = create_mcp_client(
                    use_mock=True,
                    use_stdio=False,
                    mcp_url=None,
                    mcp_command=None,
                    mcp_args=None
                )
            elif self.mcp_use_stdio and self.mcp_command:
                log.info(f"Using stdio MCP client with command: {self.mcp_command}")
                self.mcp_client = create_mcp_client(
                    use_mock=False,
                    use_stdio=True,
                    mcp_url=None,
                    mcp_command=self.mcp_command,
                    mcp_args=self.mcp_args
                )
            else:
                log.info(f"Using HTTP MCP client with URL: {self.mcp_url}")
                self.mcp_client = create_mcp_client(
                    use_mock=False,
                    use_stdio=False,
                    mcp_url=self.mcp_url,
                    mcp_command=None,
                    mcp_args=None
                )

            client_type = type(self.mcp_client).__name__
            log.info(f"Created MCP client of type: {client_type}")

            # Create the toolkit with the selected MCP client
            airbnb_toolkit = AirbnbTools(mcp_client=self.mcp_client)

            # Create the agent with tools
            agent = Agent(
                model=model,
                description="""
                You are an Airbnb search assistant that helps users find accommodations.
                You can search for listings and provide detailed information about them.
                Be friendly, helpful, and concise in your responses.
                Always provide detailed listing information including amenities, host details, and descriptions when available.
                """,
                instructions=[
                    "Always ask for all the information you need before making a search, such as location, dates, and number of guests.",
                    "When displaying listing results, format them in a clear, organized way with detailed information.",
                    "When showing listing details, highlight the key information like price, amenities, host details, and property features.",
                    "Use headings to organize your responses and put the important information first.",
                    "Be concise and focused on the most relevant information for the user's needs.",
                    "Always use proper markdown formatting for listings and organize information in a clean, readable way.",
                    "Only return a maximum of 5 listings unless explicitly asked for more.",
                    "Always present whatever listing information is available even if some details are missing. It's better to show partial listings than no listings at all.",
                    "Always provide context about the neighborhood or area when recommending listings.",
                    "Always use the search_listings tool with fetch_details=true parameter. The fetch_details parameter is essential for retrieving complete listing details including amenities and host information."
                ],
                tools=[airbnb_toolkit],
                show_tool_calls=True,
                markdown=True
            )
            return agent
        except Exception as e:
            log.error(f"Error creating Agno agent: {e}")
            return None

    def process_query(self, message: str, session_id: Optional[str] = None) -> Dict[str, Any]:
        """
        Process a user query and return a response

        Args:
            message (str): User's message
            session_id (str): Session ID for continuity (optional)

        Returns:
            Dict: Dictionary containing the response and any context
        """
        try:
            log.info(f"Processing query: {message}")

            # Create a new session ID if none was provided
            if not session_id:
                session_id = str(uuid.uuid4())

            # Initialize session if it doesn't exist
            if session_id not in self.sessions:
                self.sessions[session_id] = {
                    "messages": []
                }

            # Add user message to session history
            self.sessions[session_id]["messages"].append({"role": "user", "content": message})

            # If no valid agent is available or no API key, return error
            if self.agent is None or not self.api_key or self.api_key == "your_api_key_here":
                log.warning("No valid agent is available - missing API key or agent initialization failed")
                error_msg = "I'm sorry, but I'm not properly configured. Please check that you have set a valid API key."
                raise RuntimeError(error_msg)

            # Run the agent to get a response using the proper Agno agent.run() method
            try:
                # Use the agent.run method
                # Get the session history
                session_messages = self.sessions[session_id].get("messages", [])

                # If this is the first message, we just run the agent with the message
                if len(session_messages) <= 1:
                    response = self.agent.run(message)
                else:
                    # For subsequent messages, we need to pass the whole history
                    # Convert to the format Agno expects for history
                    formatted_history = [
                        msg for msg in session_messages[:-1]  # All messages except the current one
                    ]

                    # Run the agent with history and current message
                    response = self.agent.run(
                        message,
                        history=formatted_history
                    )
                log.info(f"Agent generated a response of type: {type(response)}")

                # Get the response content - RunResponse object or string
                if isinstance(response, str):
                    # Direct string response
                    response_text = response
                    log.info("Response is a string type")
                else:
                    # RunResponse object
                    log.info(f"Response is an object with attributes: {dir(response)}")
                    response_text = response.content if hasattr(response, 'content') else str(response)
                    log.info(f"Extracted content: {response_text[:100]}...")

                # Format the response in markdown for better readability
                formatted_text = self._format_response_as_markdown(response_text)
                log.info(f"Formatted response to improve markdown readability")

                # Extract any context or metadata
                context = {}

                # Improved detection of listing data with multiple patterns
                if formatted_text and isinstance(formatted_text, str):
                    listing_markers = [
                        "listing", "accommodations", "property", "airbnb", "stay", "found",
                        "results", "### 1.", "### 2.", "* **Location", "* **Price", "* **Rating",
                        "* **Bedrooms", "search results"
                    ]

                    if any(marker in formatted_text.lower() for marker in listing_markers):
                        context["type"] = "listing"
                        log.info("Detected listing data in response")

                        # Check if the response has the necessary formatting
                        if not ("###" in formatted_text or "##" in formatted_text):
                            log.info("Response doesn't have proper heading formatting, applying additional formatting")
                            formatted_text = self._format_response_as_markdown(formatted_text)

                # Make sure we have a string response for the frontend
                if not isinstance(formatted_text, str):
                    # Try to convert complex objects to string
                    try:
                        if hasattr(response, 'get_content_as_string'):
                            formatted_text = response.get_content_as_string()
                            log.info("Used get_content_as_string to convert response")
                        else:
                            import json
                            formatted_text = json.dumps(formatted_text) if formatted_text is not None else ""
                            log.info("Used json.dumps to convert response")
                    except Exception as e:
                        log.warning(f"Error converting response to string: {e}")
                        formatted_text = str(formatted_text)
                        log.info("Used str() to convert response after error")

                # Add the assistant's response to the session history
                self.sessions[session_id]["messages"].append({"role": "assistant", "content": formatted_text})

                return {
                    "response": formatted_text,
                    "context": context
                }
            except Exception as agent_error:
                error_msg = str(agent_error)
                log.error(f"Agent error: {error_msg}")

                # Provide more specific feedback for known error types
                if "invalid_api_key" in error_msg.lower() or "incorrect api key" in error_msg.lower():
                    log.error("API key validation failed. Check that you've set the correct API key for your provider.")
                    if self.provider:
                        expected_env = f"GENAI_API_KEY or {self.provider.upper()}_API_KEY"
                        log.error(f"For {self.provider} provider, ensure {expected_env} is set correctly.")
                    error_msg = "API key validation failed. Please check your API key configuration."

                # Raise the error so it can be handled by the view
                raise RuntimeError(error_msg)

        except Exception as e:
            log.error(f"Error processing query: {e}")

            # Check if it's a RuntimeError we've already created
            if isinstance(e, RuntimeError):
                # Just re-raise the RuntimeError
                raise

            # For other exceptions, create a new RuntimeError with a friendly message
            error_msg = "I apologize, but I encountered an error while processing your request."
            raise RuntimeError(error_msg)

    def cleanup(self):
        """Clean up resources when the agent is finished"""
        if hasattr(self, 'mcp_client') and self.mcp_client:
            try:
                if hasattr(self.mcp_client, 'cleanup'):
                    self.mcp_client.cleanup()
            except Exception as e:
                log.error(f"Error cleaning up MCP client in agent: {e}")

    def __del__(self):
        """Clean up resources when the agent is destroyed"""
        self.cleanup()
