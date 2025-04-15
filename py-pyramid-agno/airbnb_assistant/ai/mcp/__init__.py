"""
MCP (Model Context Protocol) integration package

This package provides client implementations for communicating with the MCP server
for Airbnb search functionality.
"""

# Import the client from clients package to maintain backward compatibility
from .client import MCPAirbnbClient

# Also expose the factory function for direct use
from .clients import create_mcp_client
