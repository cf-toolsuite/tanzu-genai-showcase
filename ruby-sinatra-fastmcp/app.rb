require 'sinatra'
require 'fast_mcp'
require 'json'
require 'faraday'
require 'dotenv/load'
require 'erb'

# Set Sinatra settings
set :views, File.join(File.dirname(__FILE__), 'views')
set :public_folder, File.join(File.dirname(__FILE__), 'public')
set :erb, :layout => :layout
set :show_exceptions, :after_handler # Enable custom error handling

# Load tools and resources
Dir.glob('./app/tools/*.rb').sort.each { |file| require file }
Dir.glob('./app/resources/*.rb').sort.each { |file| require file }

# Initialize the MCP server
mcp_server = FastMcp::Server.new(
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
  mcp_server.register_tool(tool_class)
end

# Mount MCP in Sinatra with optional SSE support
# Define a middleware class that wraps FastMcp::Transports::RackTransport
class FastMcpMiddleware
  def initialize(app, server, options = {})
    @app = app
    @server = server
    @options = {
      path_prefix: '/mcp',
      logger: @server.logger,
      enable_sse: ENV['ENABLE_MCP_SSE'] == 'true'
    }.merge(options)
    @transport = nil
  end

  def call(env)
    # Lazy initialize the transport
    if @transport.nil?
      @transport = FastMcp::Transports::RackTransport.new(@app, @server, {
        path_prefix: @options[:path_prefix],
        logger: @options[:logger],
        # Enable SSE when the environment variable is set
        enable_sse: @options[:enable_sse],
        # Additional config for SSE
        sse_route: 'sse',
        messages_route: 'messages',
        localhost_only: false, # Allow external connections on CF
        allowed_origins: ['*'] # Allow all origins for CF deployment
      })
      # Make sure to start the transport
      @transport.start
      # Set the server's transport
      @server.transport = @transport

      # Log the transport configuration
      @options[:logger].info("FastMCP Server initialized")
      @options[:logger].info("SSE enabled: #{@options[:enable_sse]}")
      if @options[:enable_sse]
        @options[:logger].info("SSE endpoint: #{@options[:path_prefix]}/sse")
        @options[:logger].info("Messages endpoint: #{@options[:path_prefix]}/messages")
      end
    end

    # Forward the call to the transport
    @transport.call(env)
  end
end

# Use the middleware class with SSE configuration from environment
use FastMcpMiddleware, mcp_server

# Error handling
not_found do
  @error_title = '404 - Page Not Found'
  @error_message = 'The page you are looking for does not exist or has been moved.'
  erb :error
end

error do
  @error_title = 'An Error Occurred'
  @error_message = env['sinatra.error'].message

  if request.path.start_with?('/api')
    content_type :json
    status 500
    { error: env['sinatra.error'].message }.to_json
  else
    erb :error
  end
end

# Web UI routes
get '/' do
  erb :index
end

# API routes
get '/api/search' do
  content_type :json

  # Get params from query string
  params_to_pass = {}
  %w[flight_iata flight_icao dep_iata arr_iata airline_name airline_iata flight_status limit flight_date].each do |param|
    params_to_pass[param.to_sym] = params[param] if params[param] && !params[param].empty?
  end

  # Set default limit
  params_to_pass[:limit] ||= 10

  # Call the API
  client = AviationStackClient.new
  result = client.search_flights(params_to_pass)

  # Return the result
  result['data'].to_json
end

get '/api/airports' do
  content_type :json

  # Get params from query string
  params_to_pass = {}
  %w[iata_code icao_code airport_name city country limit].each do |param|
    params_to_pass[param.to_sym] = params[param] if params[param] && !params[param].empty?
  end

  # Set default limit
  params_to_pass[:limit] ||= 10

  # Call the API
  client = AviationStackClient.new
  result = client.search_airports(params_to_pass)

  # Return the result
  result['data'].to_json
end

get '/api/schedules' do
  content_type :json

  # Get params from query string
  params_to_pass = {}
  %w[flight_iata flight_icao dep_iata arr_iata airline_name airline_iata flight_status limit].each do |param|
    params_to_pass[param.to_sym] = params[param] if params[param] && !params[param].empty?
  end

  # Set default limit
  params_to_pass[:limit] ||= 10

  # Call the API
  client = AviationStackClient.new
  result = client.get_flight_schedules(params_to_pass)

  # Return the result
  result['data'].to_json
end

get '/api/future-schedules' do
  content_type :json

  # Get params from query string
  params_to_pass = {}
  %w[date iataCode type limit].each do |param|
    params_to_pass[param.to_sym] = params[param] if params[param] && !params[param].empty?
  end

  # Set default limit
  params_to_pass[:limit] ||= 10

  # Call the API
  client = AviationStackClient.new
  result = client.get_future_flight_schedules(params_to_pass)

  # Return the result
  result['data'].to_json
end

# Default route for JSON API
get '/api' do
  content_type :json
  {
    message: 'Flight Tracking Chatbot API is running',
    mcp_endpoint: '/mcp',
    api_endpoints: [
      '/api/search',
      '/api/airports',
      '/api/schedules',
      '/api/future-schedules'
    ],
    version: mcp_server.version
  }.to_json
end
