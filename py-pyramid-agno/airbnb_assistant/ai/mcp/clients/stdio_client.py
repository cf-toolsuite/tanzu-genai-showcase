"""
Stdio client implementation for MCP Airbnb
"""
import json
import logging
import os
import subprocess
import sys
import time
import threading
import queue
from typing import Dict, List, Any, Optional, Union

from .base import MCPAirbnbClientBase
from .mock_client import MCPAirbnbMockClient

log = logging.getLogger(__name__)

class MCPAirbnbStdioClient(MCPAirbnbClientBase):
    """
    Stdio client implementation for communicating with the MCP Airbnb server

    This implementation launches the MCP server as a subprocess and
    communicates with it through stdin/stdout pipes following the
    Model Context Protocol.
    """

    def __init__(self, mcp_command: str, mcp_args: Optional[List[str]] = None):
        """
        Initialize the stdio client

        Args:
            mcp_command: Command to run the MCP server (e.g., 'node', 'python')
            mcp_args: Arguments to pass to the command (e.g., ['path/to/server.js'])
        """
        self.mcp_command = mcp_command
        self.mcp_args = mcp_args or []

        # Always add the --ignore-robots-txt flag to bypass robots.txt restrictions
        if '--ignore-robots-txt' not in self.mcp_args:
            self.mcp_args.append('--ignore-robots-txt')

        # Message queues for communication with server
        self.request_queue = queue.Queue()
        self.response_queue = queue.Queue()

        # Initialize server process to None
        self.process = None
        self.is_running = False

        # Message ID counter for request/response matching
        self.next_id = 1

        # Start the server process
        self.start_server()

    def start_server(self):
        """Start the MCP server as a subprocess and set up communication threads"""
        try:
            log.info(f"Starting MCP server: {self.mcp_command} {' '.join(self.mcp_args)}")

            # Start the subprocess
            self.process = subprocess.Popen(
                [self.mcp_command] + self.mcp_args,
                stdin=subprocess.PIPE,
                stdout=subprocess.PIPE,
                stderr=subprocess.PIPE,
                text=True,
                bufsize=1  # Line buffered
            )

            # Mark the server as running
            self.is_running = True

            # Start reader thread
            self.reader_thread = threading.Thread(target=self._read_process_output, daemon=True)
            self.reader_thread.start()

            # Start writer thread
            self.writer_thread = threading.Thread(target=self._write_process_input, daemon=True)
            self.writer_thread.start()

            # Start error reader thread
            self.error_thread = threading.Thread(target=self._read_process_errors, daemon=True)
            self.error_thread.start()

            # Wait a moment for the server to initialize
            time.sleep(1)

            log.info("MCP server started successfully")

        except Exception as e:
            log.error(f"Failed to start MCP server: {e}")
            self.is_running = False
            raise

    def _read_process_output(self):
        """Read and process output from the MCP server"""
        while self.is_running and self.process and self.process.stdout:
            try:
                # Read a line from stdout
                line = self.process.stdout.readline()

                if not line:
                    if self.process.poll() is not None:
                        log.warning("MCP server process has terminated")
                        self.is_running = False
                    break

                # Try to parse as JSON
                try:
                    response = json.loads(line)
                    log.debug(f"Received response: {response}")

                    # Put the response in the queue
                    self.response_queue.put(response)

                except json.JSONDecodeError:
                    log.warning(f"Received non-JSON output: {line}")

            except Exception as e:
                log.error(f"Error reading from MCP server: {e}")
                if not self.is_running:
                    break

    def _read_process_errors(self):
        """Read error output from the MCP server"""
        while self.is_running and self.process and self.process.stderr:
            try:
                # Read a line from stderr
                line = self.process.stderr.readline()

                if not line:
                    if self.process.poll() is not None:
                        break
                    continue

                log.warning(f"MCP server stderr: {line.strip()}")

            except Exception as e:
                log.error(f"Error reading stderr from MCP server: {e}")
                if not self.is_running:
                    break

    def _write_process_input(self):
        """Write requests to the MCP server"""
        while self.is_running and self.process and self.process.stdin:
            try:
                # Get a request from the queue
                request = self.request_queue.get(timeout=0.5)

                # Convert to JSON and write to stdin
                json_request = json.dumps(request) + "\n"
                self.process.stdin.write(json_request)
                self.process.stdin.flush()

                # Mark the task as done
                self.request_queue.task_done()

            except queue.Empty:
                # No request available, keep waiting
                continue

            except Exception as e:
                log.error(f"Error writing to MCP server: {e}")
                if not self.is_running:
                    break

    def _send_request(self, method: str, params: Dict[str, Any] = None) -> Dict[str, Any]:
        """
        Send a request to the MCP server and wait for the response

        Args:
            method: RPC method to call
            params: Parameters for the method

        Returns:
            Response from the server
        """
        if not self.is_running or not self.process:
            raise RuntimeError("MCP server is not running")

        # Generate a request ID
        request_id = self.next_id
        self.next_id += 1

        # Create the request with the proper Schema format
        # Ensure we use the exact methods expected by the MCP server
        # Based on the SDK, the method name in CallToolRequestSchema must be exactly "tools/call"
        if method.lower() == "calltool":
            actual_method = "tools/call"
        elif method.lower() == "listtools":
            actual_method = "tools/list"
        else:
            actual_method = method

        request = {
            "jsonrpc": "2.0",
            "id": request_id,
            "method": actual_method,
            "params": params or {}
        }

        # For tool call methods, ensure the request has the proper format
        if method.lower() == "calltool":
            # The MCP server expects params to be { name: string, arguments: object }
            # Ensure we have this exact structure
            if not (isinstance(params, dict) and "name" in params and "arguments" in params):
                log.error("Invalid params structure for callTool. Expected {name, arguments} format.")
                if "name" in params:
                    # Extract the name and treat remaining params as arguments
                    tool_name = params.pop("name")
                    params = {
                        "name": tool_name,
                        "arguments": params
                    }
                else:
                    # If no proper structure, use a default
                    log.warning("Missing tool name in callTool params, defaulting to airbnb_search")
                    params = {
                        "name": "airbnb_search",
                        "arguments": params or {}
                    }
            log.info(f"Sending CallTool request with tool name: {params.get('name')}, arguments: {json.dumps(params.get('arguments', {}), indent=2)}")
            # Update the request with the reformatted params
            request['params'] = params

        # Log the actual request for debugging
        log.debug(f"Sending MCP request: {json.dumps(request, indent=2)}")

        # Put the request in the queue
        self.request_queue.put(request)

        # Wait for the response with matching ID
        timeout = 30  # 30 seconds timeout
        start_time = time.time()

        while True:
            try:
                # Check if we've timed out
                if time.time() - start_time > timeout:
                    raise TimeoutError(f"Timeout waiting for response to request {request_id}")

                # Try to get a response with a short timeout
                response = self.response_queue.get(timeout=0.5)

                # Check if this is the response we're waiting for
                if response.get("id") == request_id:
                    self.response_queue.task_done()

                    # Check for errors
                    if "error" in response:
                        error = response["error"]
                        log.error(f"MCP server error: {error}")

                        # Check for method not found error - this is likely a method/schema mismatch
                        if error.get("code") == -32601 and "Method not found" in error.get("message", ""):
                            error_msg = "The MCP server does not recognize the requested method. Please check server implementation."
                            log.error(error_msg)

                            # Log detailed information about what's happening
                            log.error("The MCP server expects requests to use the JSON-RPC format with correct method names")
                            log.error("For tool calls, the method should be 'tools/call' instead of 'CallTool'")
                            log.error(f"Request method was: {actual_method}")

                            # For method not found errors, raise an exception
                            log.error("Cannot proceed with incompatible MCP server")
                            raise RuntimeError(error_msg)

                        raise RuntimeError(f"MCP server error: {error}")

                    # Return the result
                    return response.get("result", {})
                else:
                    # Put the response back for another handler
                    self.response_queue.put(response)
                    self.response_queue.task_done()

            except queue.Empty:
                # No response available yet, keep waiting
                continue

            except Exception as e:
                log.error(f"Error processing response: {e}")
                raise

    def search_listings(self,
                      location: str,
                      check_in: Optional[str] = None,
                      check_out: Optional[str] = None,
                      guests: int = 1,
                      limit: int = 10) -> List[Dict[str, Any]]:
        """
        Search for Airbnb listings using the MCP server over stdio

        Args:
            location: Location to search for (city, neighborhood, etc.)
            check_in: Check-in date in YYYY-MM-DD format
            check_out: Check-out date in YYYY-MM-DD format
            guests: Number of guests
            limit: Maximum number of results to return

        Returns:
            List of listing dictionaries
        """
        try:
            # Log search parameters
            log.info(f"Stdio client: Searching for listings in {location} for {guests} guests")
            if check_in and check_out:
                log.info(f"Stdio client: Date range: {check_in} to {check_out}")

            # Prepare the arguments following the schema defined in the server's index.ts
            arguments = {
                "location": location,
                "adults": guests,
                "limit": limit,
                "ignoreRobotsText": True  # Add this parameter to bypass robots.txt restrictions
            }

            # Add date parameters if provided
            if check_in:
                arguments["checkin"] = check_in

            if check_out:
                arguments["checkout"] = check_out

            # Create the properly formatted params structure
            params = {
                "name": "airbnb_search",
                "arguments": arguments
            }

            # Send the request to the MCP server - use "callTool" here as it will be
            # converted to "CallTool" in the _send_request method
            try:
                response = self._send_request("callTool", params)
                log.info("Received response from MCP server")

                # Extract results from the response
                if response:
                    # Check for content in the response
                    if "content" in response:
                        for part in response["content"]:
                            if part.get("type") == "text":
                                try:
                                    # Parse the text content as JSON
                                    text_content = part.get("text", "{}")
                                    log.info(f"Raw text content from MCP server (first 200 chars): {text_content[:200]}...")
                                    result_data = json.loads(text_content)

                                    # Log the entire response for debugging
                                    log.info(f"Response structure keys: {list(result_data.keys()) if isinstance(result_data, dict) else 'Not a dict'}")

                                    # Log a sample search result for debugging
                                    if isinstance(result_data, dict) and "searchResults" in result_data and result_data["searchResults"]:
                                        log.info(f"Sample search result format: {json.dumps(result_data['searchResults'][0], indent=2)[:1000]}...")

                                    # The MCP Server returns a specific format with the search URL and searchResults fields
                                    # We need to extract the searchResults from this structure
                                    if isinstance(result_data, dict):
                                        # Standard format from the server
                                        if "searchResults" in result_data:
                                            results = result_data["searchResults"]
                                            log.info(f"Found {len(results)} search results")

                                            # Fix undefined URLs
                                            fixed_results = []
                                            for result in results:
                                                if "url" in result and "/undefined" in result["url"]:
                                                    # Try to extract a listing ID from other data
                                                    listing_id = None
                                                    try:
                                                        if "listing" in result and "id" in result["listing"]:
                                                            listing_id = result["listing"]["id"]
                                                    except (KeyError, TypeError):
                                                        pass

                                                    if listing_id:
                                                        # Replace undefined with the actual ID
                                                        result["url"] = f"https://www.airbnb.com/rooms/{listing_id}"

                                                fixed_results.append(result)

                                            return fixed_results

                                        # Format with a URL included
                                        if "searchUrl" in result_data and "searchResults" in result_data:
                                            results = result_data["searchResults"]
                                            log.info(f"Found {len(results)} search results with URL")

                                            # Fix undefined URLs
                                            fixed_results = []
                                            search_url = result_data.get("searchUrl", "https://www.airbnb.com")
                                            for result in results:
                                                # Ensure url exists
                                                if "url" not in result:
                                                    result["url"] = "https://www.airbnb.com/rooms/undefined"

                                                # Fix undefined URLs
                                                if "url" in result and "/undefined" in result["url"]:
                                                    # Try multiple ways to extract a listing ID
                                                    listing_id = None
                                                    photo_id = None
                                                    category_tag = None

                                                    # Method 1: Check listing.id
                                                    try:
                                                        if "listing" in result and isinstance(result["listing"], dict) and "id" in result["listing"]:
                                                            listing_id = result["listing"]["id"]
                                                    except (KeyError, TypeError):
                                                        pass

                                                    # Method 2: Check listingParamOverrides for photoId
                                                    if not listing_id and "listingParamOverrides" in result and isinstance(result["listingParamOverrides"], dict):
                                                        if "photoId" in result["listingParamOverrides"]:
                                                            photo_id = result["listingParamOverrides"]["photoId"]
                                                        if "categoryTag" in result["listingParamOverrides"]:
                                                            category_tag = result["listingParamOverrides"]["categoryTag"]

                                                    # Apply the best ID we found to create a valid URL
                                                    if listing_id:
                                                        result["url"] = f"https://www.airbnb.com/rooms/{listing_id}"
                                                    elif photo_id and photo_id != "undefined":
                                                        result["url"] = f"https://www.airbnb.com/rooms?photos={photo_id}"
                                                    elif category_tag and category_tag.startswith("Tag:"):
                                                        tag_id = category_tag.split(":")[1]
                                                        result["url"] = f"https://www.airbnb.com/s/homes?category_tag_id={tag_id}"
                                                    else:
                                                        # If all else fails, use the search URL
                                                        result["url"] = search_url

                                                # Ensure we have listing object
                                                if "listing" not in result:
                                                    result["listing"] = {}

                                                # Add structured content if missing
                                                if "structuredContent" not in result["listing"]:
                                                    result["listing"]["structuredContent"] = {}

                                                # Add primary line if missing (bed type)
                                                if "primaryLine" not in result["listing"]["structuredContent"]:
                                                    # Look for bed info in structuredDisplayPrice
                                                    bed_info = "Comfortable Room"
                                                    if "avgRatingA11yLabel" in result and "bed" in result["avgRatingA11yLabel"].lower():
                                                        bed_info = result["avgRatingA11yLabel"]
                                                    result["listing"]["structuredContent"]["primaryLine"] = bed_info

                                                # Ensure we have structured price data
                                                if "structuredDisplayPrice" not in result:
                                                    result["structuredDisplayPrice"] = {
                                                        "primaryLine": {"accessibilityLabel": "Price not available"},
                                                        "secondaryLine": {"accessibilityLabel": "Total price not available"}
                                                    }

                                                fixed_results.append(result)

                                            return fixed_results

                                        # Log whatever we did receive for debugging
                                        log.warning(f"Unexpected response format. Keys received: {list(result_data.keys())}")
                                        log.debug(f"Full response data: {json.dumps(result_data, indent=2)[:1000]}...")

                                        # Return an empty list if we don't have results
                                        # This is better than raising an error as the agent can still function
                                        return []
                                except json.JSONDecodeError as e:
                                    log.warning(f"Failed to parse response as JSON: {e}")

            except Exception as e:
                log.error(f"Error communicating with MCP server: {e}")

            # If we couldn't get results from the MCP server, raise an error
            error_msg = "No search results found in MCP server response"
            log.warning(error_msg)
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
        Get details for a specific listing using the MCP server over stdio

        Args:
            listing_id: ID of the listing to retrieve

        Returns:
            Dictionary containing listing details
        """
        try:
            # Log request
            log.info(f"Stdio client: Getting details for listing {listing_id}")

            # Create the properly formatted params structure
            params = {
                "name": "airbnb_listing_details",
                "arguments": {
                    "id": listing_id,
                    "ignoreRobotsText": True  # Add this parameter to bypass robots.txt restrictions
                }
            }

            # Send the request to the MCP server - use "callTool" here as it will be
            # converted to "CallTool" in the _send_request method
            try:
                response = self._send_request("callTool", params)
                log.info("Received response from MCP server")

                # Extract results from the response
                if response:
                    # Check for content in the response
                    if "content" in response:
                        for part in response["content"]:
                            if part.get("type") == "text":
                                try:
                                    # Parse the text content as JSON
                                    text_content = part.get("text", "{}")
                                    log.info(f"Raw text content from MCP server (first 200 chars): {text_content[:200]}...")
                                    result_data = json.loads(text_content)

                                    # Log the entire response for debugging
                                    log.info(f"Response structure keys: {list(result_data.keys()) if isinstance(result_data, dict) else 'Not a dict'}")

                                    # The MCP Server returns a specific format with the listingUrl and details fields
                                    # We need to extract the details from this structure
                                    if isinstance(result_data, dict):
                                        # Standard format from the server
                                        if "details" in result_data:
                                            details = result_data["details"]
                                            log.info(f"Found listing details")
                                            return details

                                        # Format with a URL included
                                        if "listingUrl" in result_data and "details" in result_data:
                                            details = result_data["details"]
                                            log.info(f"Found listing details with URL")
                                            return details

                                        # Log whatever we did receive for debugging
                                        log.warning(f"Unexpected response format. Keys received: {list(result_data.keys())}")
                                        log.debug(f"Full response data: {json.dumps(result_data, indent=2)[:1000]}...")

                                        # Return an empty list if we don't have details
                                        # This is better than raising an error as the agent can still function
                                        return []
                                except json.JSONDecodeError as e:
                                    log.warning(f"Failed to parse response as JSON: {e}")

            except Exception as e:
                log.error(f"Error communicating with MCP server: {e}")

            # If we couldn't get results from the MCP server, raise an error
            error_msg = "No listing details found in MCP server response"
            log.warning(error_msg)
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
        """Stop the MCP server and clean up resources"""
        if self.process and self.is_running:
            log.info("Stopping MCP server")
            self.is_running = False

            try:
                # Signal the process to terminate
                self.process.terminate()

                # Wait for the process to exit
                self.process.wait(timeout=5)

            except subprocess.TimeoutExpired:
                log.warning("MCP server did not exit gracefully, forcing termination")
                self.process.kill()

            # Close pipes
            if self.process.stdin:
                self.process.stdin.close()
            if self.process.stdout:
                self.process.stdout.close()
            if self.process.stderr:
                self.process.stderr.close()

            self.process = None
            log.info("MCP server stopped")

    def __del__(self):
        """Clean up resources when the object is destroyed"""
        self.cleanup()
