# Using MCP Server Over HTTP/SSE

This document explains how to use the FastMCP server over HTTP/SSE, which is essential when deploying to Cloud Foundry or other environments where you need remote access to the MCP server.

## Overview

The Model Context Protocol (MCP) supports two main transport methods:

1. **STDIO Transport**: Used by default for local development and Claude Desktop integration
2. **HTTP/SSE Transport**: Enables remote access to the MCP server over HTTP using Server-Sent Events (SSE)

When deployed to Cloud Foundry, we need to use the HTTP/SSE transport to allow remote clients to communicate with the MCP server.

## Configuration

The HTTP/SSE transport can be enabled by setting the `ENABLE_MCP_SSE` environment variable to `true`. This is already configured in the `manifest.yml` file for Cloud Foundry deployments.

```yaml
env:
  RACK_ENV: production
  ENABLE_MCP_SSE: true
```

## Endpoints

When HTTP/SSE transport is enabled, the following endpoints become available:

- **SSE Endpoint**: `/mcp/sse` - Used to establish an SSE connection
- **Messages Endpoint**: `/mcp/messages` - Used for sending messages to the MCP server

## Connecting to the MCP Server

### From Claude Desktop

Claude Desktop doesn't natively support HTTP/SSE connections. To connect Claude Desktop to a remote MCP server using HTTP/SSE, you need to use an MCP proxy tool like [mcp-proxy](https://github.com/sparfenyuk/mcp-proxy).

1. Install the proxy:

   ```bash
   pipx install mcp-proxy
   ```

2. Update the Claude Desktop configuration to use the proxy:

   ```json
   {
     "mcpServers": {
       "flight-tracking-bot": {
         "command": "mcp-proxy",
         "args": ["https://your-cf-app-url.cf-app.com/mcp/sse"]
       }
     }
   }
   ```

## Testing

You can test the HTTP/SSE transport using the MCP Inspector tool:

1. Install the MCP Inspector:

   ```bash
   npm install -g @modelcontextprotocol/inspector
   ```

2. Run the Inspector in browser mode to test the deployed application:

   ```bash
   npx @modelcontextprotocol/inspector browser
   ```

3. In the Inspector UI, select "SSE" as the transport and enter the SSE URL:

   `https://your-cf-app-url.cf-app.com/mcp/sse`

## Troubleshooting

- Ensure the `ENABLE_MCP_SSE` environment variable is set to `true` in your deployment
- Check the logs for any transport initialization errors
- Verify CORS settings if connecting from a different domain
- Make sure the application has network access to the MCP server

For more information, see the [FastMCP documentation](https://github.com/yjacquin/fast-mcp) and [MCP specification](https://modelcontextprotocol.io/).
