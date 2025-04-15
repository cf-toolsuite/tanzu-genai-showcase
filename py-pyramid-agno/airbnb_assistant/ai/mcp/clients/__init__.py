"""
Client implementations for communicating with the MCP server
This package provides different implementations for MCP clients
"""

# Import the base class and implementations for easier access
from .base import MCPAirbnbClientBase
from .mock_client import MCPAirbnbMockClient
from .http_client import MCPAirbnbHTTPClient
from .stdio_client import MCPAirbnbStdioClient

# Factory function to create the appropriate client
def create_mcp_client(
    use_mock=False,
    use_stdio=False,
    mcp_url=None,
    mcp_command=None,
    mcp_args=None
):
    """
    Factory function to create the appropriate MCP client based on configuration

    Args:
        use_mock: Whether to use the mock client
        use_stdio: Whether to use stdio communication
        mcp_url: URL for the HTTP client
        mcp_command: Command for the stdio client
        mcp_args: Arguments for the stdio client

    Returns:
        An instance of the appropriate MCP client
    """
    if use_mock:
        return MCPAirbnbMockClient()
    elif use_stdio and mcp_command:
        return MCPAirbnbStdioClient(mcp_command=mcp_command, mcp_args=mcp_args or [])
    else:
        return MCPAirbnbHTTPClient(mcp_url=mcp_url)
