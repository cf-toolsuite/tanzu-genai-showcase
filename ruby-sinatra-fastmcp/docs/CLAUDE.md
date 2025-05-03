# Using This App with Claude Desktop

This document explains how to integrate this Flight Tracking MCP server with Claude Desktop and troubleshooting steps for common issues.

## Integration with Claude Desktop

The Flight Tracking Chatbot implements the Model Context Protocol (MCP), which allows AI assistants like Claude to interact with it. Claude Desktop serves as an MCP client that can connect to our server.

### Setup Instructions

1. Run the setup script to configure Claude Desktop:

   ```bash
   ./scripts/setup-claude-desktop.sh
   ```

2. Restart Claude Desktop completely

3. When you open Claude Desktop, you should see "flight-tracking-bot" available in the chat interface

## Understanding the Architecture

This application consists of two main components:

1. **Web Application**: A Sinatra-based web server that provides API endpoints and a web UI
2. **MCP Server**: A standalone server component that Claude can connect to

Claude Desktop doesn't connect to the web application directly. Instead, it launches the `mcp_server_wrapper.rb` script, which starts a separate MCP server process.

## Troubleshooting Common Issues

### Missing Dependencies

Error: `cannot load such file -- fast_mcp (LoadError)`

**Solution**: This occurs because Claude Desktop is using the system Ruby installation without access to your project's dependencies. We solved this by:

1. Creating a wrapper script (`scripts/mcp_server_wrapper.rb`) that:
   - Changes to the project directory
   - Sets up the Bundler environment
   - Loads the actual MCP server script

2. Updating the Claude Desktop configuration to use this wrapper script instead of directly calling `mcp_server.rb`

### Method Not Found

Error: `undefined method 'run_with_stdio' for #<FastMcp::Server:...>`

**Solution**: This occurs when there's a mismatch between the methods used in your script and what the gem actually supports. We fixed this by:

1. Looking at the Fast-MCP gem's source code to find the correct method
2. Updating `mcp_server.rb` to use `server.start` instead of `server.run_with_stdio`

The `start` method automatically sets up the stdio transport and starts it, making it the correct choice for standalone MCP servers.

## Advanced: Debugging MCP Communication

To debug the communication between Claude Desktop and your MCP server:

1. Run your MCP server manually:

   ```bash
   ./scripts/mcp_server_wrapper.rb
   ```

2. The terminal will show messages exchanged between Claude and your server, helping you identify any issues

## Implementation Details

### Wrapper Script

The wrapper script (`mcp_server_wrapper.rb`) solves the dependency issues by:

```ruby
#!/usr/bin/env ruby

# Get the directory where this script is located
script_dir = File.expand_path(File.dirname(__FILE__))
# Get the project root directory (one level up from scripts)
project_dir = File.expand_path('..', script_dir)

# Change to the project directory
Dir.chdir(project_dir)

# Use Bundler to set up the environment
require 'bundler/setup'

# Execute the actual MCP server script
load File.join(project_dir, 'mcp_server.rb')
```

### MCP Server Implementation

The MCP server script (`mcp_server.rb`) creates an MCP server and registers all the tools:

```ruby
#!/usr/bin/env ruby
require 'fast_mcp'
require 'dotenv/load'

# Load tools and resources
Dir.glob('./app/tools/*.rb').sort.each { |file| require file }
Dir.glob('./app/resources/*.rb').sort.each { |file| require file }

# Create an MCP server
server = FastMcp::Server.new(
  name: 'flight-tracking-bot',
  version: '1.0.0'
)

# Register all tools
[
  FlightSearchTool,
  FlightStatusTool,
  AirportInfoTool,
  AirlineInfoTool,
  FlightSchedulesTool,
  FutureFlightSchedulesTool
].each do |tool_class|
  server.register_tool(tool_class)
end

# Run the server with stdio transport
server.start
```

## Limitations

- Claude Desktop must be able to access your project directory
- Any changes to the MCP tools require a restart of Claude Desktop
- Only one MCP server can be active in a Claude chat at a time
- The MCP server can only use tools registered in the `mcp_server.rb` file, not any additional tools from the web application

## Next Steps

If you want to expand this functionality:

1. **Add more tools**: Create new tool classes in the `app/tools` directory
2. **Add resources**: Create resource classes in the `app/resources` directory
3. **Implement an MCP client**: Build your own chat interface directly in the web application for users who don't have Claude Desktop
