"""
AI agent implementation using Agno for the Airbnb Assistant
"""
import logging
import os
import shutil
import uuid
from typing import Dict, Any, Optional, List, Union
from urllib.parse import urlparse

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

    def _format_response_as_markdown(self, text: str) -> str:
        """
        Format the response text as proper markdown to improve readability

        Args:
            text: Raw response text from the LLM

        Returns:
            Formatted text with proper markdown
        """
        if not text:
            return ""

        import re
        import json

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

        # Auto-format responses with listing data that isn't already well-formatted
        # If USE_MOCK_DATA is true and the AI response doesn't have good formatting,
        # we can enhance it with structured mock data
        use_mock = os.environ.get('USE_MOCK_DATA', 'false').lower() == 'true'
        if use_mock and not any(listing_marker in text.lower() for listing_marker in ["### 1.", "### 2.", "# airbnb", "property details"]):
            # If we have a generic response that doesn't contain specific listing information,
            # let's check if there are any commands about finding places
            find_phrases = ["find", "looking for", "search", "stay", "book", "accommodations", "listing", "place"]
            location_phrases = ["san francisco", "near", "downtown", "waterfront", "location", "in the", "area"]

            # If this looks like a location request but doesn't have proper listing data
            if (any(phrase in text.lower() for phrase in find_phrases) and
                any(phrase in text.lower() for phrase in location_phrases)):

                # Let's assume the response should be a listing but isn't formatted correctly
                if "one moment" in text.lower() or "looking" in text.lower():
                    # Generate a nicely formatted mock response with listings
                    location = "San Francisco"
                    for loc in ["san francisco", "new york", "chicago", "miami", "los angeles", "boston"]:
                        if loc in text.lower():
                            location = loc.title()
                            break

                    # Extract location details if possible
                    location_detail = "downtown"
                    if "waterfront" in text.lower():
                        location_detail = "waterfront"
                    elif "downtown" in text.lower():
                        location_detail = "downtown"
                    elif "near" in text.lower():
                        parts = text.lower().split("near")
                        if len(parts) > 1:
                            location_detail = parts[1].split(".")[0].strip()

                    # Create a mock response with nicely formatted listings
                    mock_response = f"# Airbnb Listings Search Results\n\nHere are some great places to stay in {location} near the {location_detail} area:\n\n"

                    mock_response += "### 1. **Luxury Waterfront Apartment**\n\n"
                    mock_response += "* **Location:** {location} - {location_detail} area, walking distance to attractions\n"
                    mock_response += "* **Price:** $235 per night\n"
                    mock_response += "* **Rating:** 4.92 (125 reviews)\n"
                    mock_response += "* **Bedrooms:** 2\n"
                    mock_response += "* **Bathrooms:** 2\n"
                    mock_response += "* **Guests:** Up to 4\n"
                    mock_response += "* **Amenities:** WiFi, Full kitchen, Washer/dryer, Air conditioning, Dedicated workspace, Free parking\n"
                    mock_response += "* **Listing URL:** [View on Airbnb](https://www.airbnb.com/rooms/123456)\n\n"
                    mock_response += "![Luxury Apartment](https://a0.muscache.com/im/pictures/miso/Hosting-51809333/original/0da70267-d9da-4efb-9123-2714b651c9cd.jpeg)\n\n"

                    mock_response += "### 2. **Modern Downtown Loft**\n\n"
                    mock_response += "* **Location:** {location} - Central downtown location\n"
                    mock_response += "* **Price:** $189 per night\n"
                    mock_response += "* **Rating:** 4.87 (94 reviews)\n"
                    mock_response += "* **Bedrooms:** 1\n"
                    mock_response += "* **Bathrooms:** 1\n"
                    mock_response += "* **Guests:** Up to 3\n"
                    mock_response += "* **Amenities:** WiFi, Kitchen, Gym access, Smart TV, Washing machine\n"
                    mock_response += "* **Listing URL:** [View on Airbnb](https://www.airbnb.com/rooms/234567)\n\n"
                    mock_response += "![Downtown Loft](https://a0.muscache.com/im/pictures/miso/Hosting-717134404264905813/original/dfe9c1ff-b70c-4566-a1ef-5bc733dbb705.jpeg)\n\n"

                    mock_response += "### 3. **Charming {location_detail} Condo**\n\n"
                    mock_response += "* **Location:** {location} - {location_detail} neighborhood\n"
                    mock_response += "* **Price:** $165 per night\n"
                    mock_response += "* **Rating:** 4.79 (108 reviews)\n"
                    mock_response += "* **Bedrooms:** 1\n"
                    mock_response += "* **Bathrooms:** 1\n"
                    mock_response += "* **Guests:** Up to 2\n"
                    mock_response += "* **Amenities:** WiFi, Kitchen, Patio, Cable TV, Air conditioning\n"
                    mock_response += "* **Listing URL:** [View on Airbnb](https://www.airbnb.com/rooms/345678)\n\n"
                    mock_response += "![Charming Condo](https://a0.muscache.com/im/pictures/miso/Hosting-807995199727408777/original/9225d584-7aa4-4990-af06-339bd1339686.jpeg)\n\n"

                    mock_response = mock_response.replace("{location}", location)
                    mock_response = mock_response.replace("{location_detail}", location_detail.title())

                    # Replace the original text with our nicely formatted mock response
                    text = mock_response

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

            # Configure the agent with tools
            agent = Agent(
                model=model,
                description="""
                You are an Airbnb search assistant that helps users find accommodations.
                You can search for listings and provide detailed information about them.
                Be friendly, helpful, and concise in your responses.
                """,
                instructions=[
                    "Always ask for all the information you need before making a search, such as location, dates, and number of guests.",
                    "When displaying listing results, format them in a clear, organized way.",
                    "When showing listing details, highlight the key information like price, amenities, and host details.",
                    "Use headings to organize your responses.",
                    "Be concise and focused on relevant information.",
                    "Always use proper markdown formatting for listings and organize information in a clean, readable way."
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
