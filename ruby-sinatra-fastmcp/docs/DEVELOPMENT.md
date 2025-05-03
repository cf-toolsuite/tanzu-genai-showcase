# Development Guide

This document provides comprehensive information for developers working on the Flight Tracking Chatbot project.

## Development Environment Setup

### Prerequisites

Before you begin, ensure you have the following installed:

- **Ruby 3.3+**: The application is built with Ruby
- **Bundler**: For managing Ruby dependencies
- **Git**: For version control
- **AviationStack API Key**: Required for accessing flight data

### Ruby Installation

We provide installation scripts for different operating systems in the `scripts` directory:

#### macOS

```bash
# Install Ruby 3.3.6 (default)
./scripts/install-ruby-mac.sh

# Or specify a different version
./scripts/install-ruby-mac.sh 3.3.4
```

#### Linux

```bash
# Install Ruby 3.3.6 (default)
./scripts/install-ruby-linux.sh

# Or specify a different version
./scripts/install-ruby-linux.sh 3.3.4
```

#### Windows

```powershell
# Install Ruby 3.3.6 (default)
.\scripts\install-ruby-windows.ps1

# Or specify a different version
.\scripts\install-ruby-windows.ps1 3.3.4
```

### Project Setup

1. Clone the repository:

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
cd tanzu-genai-showcase/ruby-sinatra-fastmcp
```

2. Install dependencies:

```bash
bundle config set --local path 'vendor/bundle'
bundle install
```

3. Create a `.env` file based on the provided `.env.example`:

```bash
cp .env.example .env
```

4. Edit the `.env` file and add your AviationStack API key:

```
AVIATIONSTACK_API_KEY=your_api_key_here
```

## Project Structure

```
.
├── app/                  # Application code
│   ├── tools/            # MCP tools
│   │   ├── airline_info_tool.rb
│   │   ├── airport_info_tool.rb
│   │   ├── flight_schedules_tool.rb
│   │   ├── flight_search_tool.rb
│   │   ├── flight_status_tool.rb
│   │   └── future_flight_schedules_tool.rb
│   ├── resources/        # MCP resources (if any)
│   └── aviation_stack_client.rb  # API client
├── config/               # Configuration files
│   └── vcap_services.rb  # Service binding configuration
├── public/               # Static assets
│   └── css/              # CSS files
│       └── styles.css    # Main stylesheet
├── scripts/              # Utility scripts
│   ├── deploy-to-tanzu.sh        # Deployment script
│   ├── install-ruby-linux.sh     # Ruby installation for Linux
│   ├── install-ruby-mac.sh       # Ruby installation for macOS
│   ├── install-ruby-windows.ps1  # Ruby installation for Windows
│   ├── mcp_server_wrapper.rb     # Wrapper for MCP server
│   ├── setup-claude-desktop.sh   # Claude Desktop setup
│   └── start-dev.sh              # Development server script
├── test/                 # Test files
│   ├── aviation_stack_client_test.rb
│   ├── flight_search_tool_test.rb
│   └── test_helper.rb
├── views/                # ERB templates
│   ├── error.erb         # Error page template
│   ├── index.erb         # Main page template
│   └── layout.erb        # Layout template
├── .env.example          # Example environment variables
├── app.rb                # Main application entry point
├── config.ru             # Rack configuration
├── mcp_server.rb         # Standalone MCP server
├── Gemfile               # Ruby dependencies
└── manifest.yml          # Cloud Foundry deployment configuration
```

## Development Workflow

### Running the Application Locally

Start the development server with:

```bash
./scripts/start-dev.sh
```

This script uses `rerun` to automatically restart the server when files change.

Alternatively, you can start the server manually:

```bash
bundle exec rackup -p 4567
```

The application will be available at `http://localhost:4567`.

### Development Server Features

- **Auto-reload**: The development server automatically reloads when files change
- **Error pages**: Detailed error pages are shown in development mode
- **Logging**: Request logs are output to the console

### Testing with Claude Desktop

To test the MCP functionality with Claude Desktop:

1. Run the setup script:

```bash
./scripts/setup-claude-desktop.sh
```

2. Restart Claude Desktop

3. Open Claude Desktop and start a conversation that includes flight-related questions

### Making Changes

#### Adding a New MCP Tool

1. Create a new tool file in `app/tools/` directory:

```ruby
# app/tools/new_tool.rb
require_relative '../aviation_stack_client'

class NewTool < FastMcp::Tool
  description "Description of what this tool does"

  arguments do
    # Define arguments schema
    optional(:param1).maybe(:string).description("Parameter description")
    # Add more parameters as needed
  end

  def call(**params)
    # Implement tool functionality
    client = AviationStackClient.new
    # Call appropriate client methods
    # Format and return response
  end

  private

  def format_response(data)
    # Format the response data
  end
end
```

2. Register the tool in both `app.rb` and `mcp_server.rb`:

```ruby
# Add to the array of tools
[
  FlightSearchTool,
  FlightStatusTool,
  AirportInfoTool,
  AirlineInfoTool,
  FlightSchedulesTool,
  FutureFlightSchedulesTool,
  NewTool  # Add your new tool here
].each do |tool_class|
  mcp_server.register_tool(tool_class)
end
```

#### Adding a New API Endpoint

1. Add a new route in `app.rb`:

```ruby
get '/api/new-endpoint' do
  content_type :json

  # Get params from query string
  params_to_pass = {}
  %w[param1 param2].each do |param|
    params_to_pass[param.to_sym] = params[param] if params[param] && !params[param].empty?
  end

  # Set default limit
  params_to_pass[:limit] ||= 10

  # Call the API
  client = AviationStackClient.new
  result = client.new_method(params_to_pass)

  # Return the result
  result['data'].to_json
end
```

2. Add the corresponding method to `AviationStackClient` in `app/aviation_stack_client.rb`:

```ruby
def new_method(params = {})
  params = { access_key: @api_key }.merge(params)
  response = @conn.get('endpoint_path', params)
  handle_response(response)
end
```

#### Modifying the Web UI

1. Edit the appropriate ERB template in the `views` directory
2. Update CSS styles in `public/css/styles.css` if needed
3. Add or modify JavaScript in the ERB templates

## Testing

### Running Tests

Run the test suite with:

```bash
bundle exec rake test
```

### Writing Tests

Tests are written using Minitest. Add new test files to the `test` directory.

Example test file structure:

```ruby
require_relative 'test_helper'

class NewToolTest < Minitest::Test
  def setup
    # Setup code
    @client = AviationStackClient.new
    # Mock external API calls
    stub_request(:get, "https://api.aviationstack.com/v1/endpoint")
      .to_return(status: 200, body: fixture_file("response.json"))
  end

  def test_new_tool_functionality
    # Test code
    tool = NewTool.new
    result = tool.call(param1: "value1")
    assert_includes result, "Expected output"
  end

  private

  def fixture_file(filename)
    File.read(File.join(File.dirname(__FILE__), 'fixtures', filename))
  end
end
```

## Debugging

### Common Issues and Solutions

#### API Key Issues

If you encounter errors related to the AviationStack API key:

1. Check that your `.env` file contains the correct API key
2. Verify the API key is valid by testing it directly with the AviationStack API
3. Check the `VcapServices` module is correctly loading the API key

#### MCP Server Issues

If the MCP server is not working correctly:

1. Check that the Fast-MCP gem is installed correctly
2. Verify that all tools are properly registered
3. Check the Claude Desktop configuration

### Logging

The application uses standard Ruby logging. To enable more verbose logging, you can modify the log level in `app.rb`:

```ruby
set :logging, Logger::DEBUG
```

### Using the Ruby Debugger

You can use the Ruby debugger to debug issues:

1. Add the `debug` gem to the Gemfile:

```ruby
gem 'debug', group: :development
```

2. Install the gem:

```bash
bundle install
```

3. Add a breakpoint in your code:

```ruby
require 'debug'
debugger
```

4. Run the application and it will stop at the breakpoint

## Performance Optimization

### Best Practices

1. **Minimize API Calls**: Cache responses when appropriate to reduce calls to the AviationStack API
2. **Optimize Response Formatting**: Format responses efficiently, especially for large datasets
3. **Use Proper Error Handling**: Implement robust error handling to prevent application crashes

## Deployment

For deployment instructions, see [DEPLOYMENT.md](DEPLOYMENT.md).

## Additional Resources

- [Ruby Documentation](https://ruby-doc.org/)
- [Sinatra Documentation](https://sinatrarb.com/documentation.html)
- [Fast-MCP Documentation](https://github.com/yjacquin/fast-mcp)
- [AviationStack API Documentation](https://aviationstack.com/documentation)
- [Model Context Protocol Specification](https://modelcontextprotocol.io/)
