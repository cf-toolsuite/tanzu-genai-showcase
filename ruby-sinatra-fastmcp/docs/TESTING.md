# Testing Guide

This document provides comprehensive information about testing strategies and procedures for the Flight Tracking Chatbot project.

## Testing Overview

The Flight Tracking Chatbot uses a multi-layered testing approach to ensure reliability and functionality:

1. **Unit Tests**: Testing individual components in isolation
2. **Integration Tests**: Testing interactions between components
3. **End-to-End Tests**: Testing the complete application flow

The project uses [Minitest](https://github.com/minitest/minitest) as the testing framework, along with supporting libraries for mocking and stubbing external dependencies.

## Testing Stack

- **Minitest**: Core testing framework
- **Rack::Test**: For testing Sinatra routes and HTTP interactions
- **WebMock**: For stubbing external HTTP requests
- **Mocha**: For mocking and stubbing Ruby objects

## Test Directory Structure

```
test/
├── fixtures/                # Test data fixtures
├── aviation_stack_client_test.rb  # Tests for the API client
├── flight_search_tool_test.rb     # Tests for the flight search tool
└── test_helper.rb           # Test configuration and helper methods
```

## Running Tests

### Running the Full Test Suite

To run all tests:

```bash
bundle exec rake test
```

### Running Specific Tests

To run a specific test file:

```bash
bundle exec ruby -Ilib:test test/aviation_stack_client_test.rb
```

To run a specific test method:

```bash
bundle exec ruby -Ilib:test test/aviation_stack_client_test.rb -n test_search_flights
```

## Writing Tests

### Test Helper

The `test_helper.rb` file sets up the test environment and provides common functionality for all tests:

```ruby
require 'minitest/autorun'
require 'rack/test'
require 'webmock/minitest'
require 'mocha/minitest'

# Set test environment
ENV['RACK_ENV'] = 'test'

# Load the application
require_relative '../app'

# Disable external HTTP requests
WebMock.disable_net_connect!

# Helper methods for tests
module TestHelpers
  def fixture_file(filename)
    File.read(File.join(File.dirname(__FILE__), 'fixtures', filename))
  end

  def stub_aviation_stack_request(endpoint, params = {}, fixture_name)
    query_params = { access_key: 'test_key' }.merge(params)
    stub_request(:get, "https://api.aviationstack.com/v1/#{endpoint}")
      .with(query: query_params)
      .to_return(status: 200, body: fixture_file(fixture_name))
  end
end
```

### Unit Test Example

Here's an example of a unit test for the `AviationStackClient`:

```ruby
require_relative 'test_helper'

class AviationStackClientTest < Minitest::Test
  include TestHelpers

  def setup
    @client = AviationStackClient.new('test_key')
  end

  def test_search_flights
    # Stub the external API request
    stub_aviation_stack_request('flights', { flight_iata: 'BA123' }, 'flight_response.json')

    # Call the method being tested
    result = @client.search_flights(flight_iata: 'BA123')

    # Assert the expected results
    assert_equal 1, result['data'].size
    assert_equal 'BA123', result['data'][0]['flight']['iata']
  end

  def test_handles_api_error
    # Stub an error response
    stub_request(:get, "https://api.aviationstack.com/v1/flights")
      .with(query: { access_key: 'test_key', flight_iata: 'BA123' })
      .to_return(status: 200, body: '{"error":{"code":101,"message":"Invalid API key"}}')

    # Assert that an error is raised
    error = assert_raises(RuntimeError) do
      @client.search_flights(flight_iata: 'BA123')
    end

    assert_match /AviationStack API Error: 101 - Invalid API key/, error.message
  end
end
```

### Integration Test Example

Here's an example of an integration test for a Sinatra route:

```ruby
require_relative 'test_helper'

class ApiRoutesTest < Minitest::Test
  include Rack::Test::Methods
  include TestHelpers

  def app
    Sinatra::Application
  end

  def test_search_flights_endpoint
    # Stub the external API request
    stub_aviation_stack_request('flights', { flight_iata: 'BA123' }, 'flight_response.json')

    # Make a request to the endpoint
    get '/api/search?flight_iata=BA123'

    # Assert the response
    assert_equal 200, last_response.status
    assert_equal 'application/json', last_response.content_type

    # Parse and check the response body
    response_data = JSON.parse(last_response.body)
    assert_equal 1, response_data.size
    assert_equal 'BA123', response_data[0]['flight']['iata']
  end
end
```

### MCP Tool Test Example

Here's an example of a test for an MCP tool:

```ruby
require_relative 'test_helper'

class FlightSearchToolTest < Minitest::Test
  include TestHelpers

  def setup
    # Create a mock client
    @mock_client = mock('AviationStackClient')
    AviationStackClient.stubs(:new).returns(@mock_client)

    # Create the tool instance
    @tool = FlightSearchTool.new
  end

  def test_call_with_flight_iata
    # Set up the mock response
    mock_response = {
      'data' => [
        {
          'flight' => { 'iata' => 'BA123' },
          'airline' => { 'name' => 'British Airways' },
          'departure' => { 'airport' => 'London Heathrow', 'iata' => 'LHR' },
          'arrival' => { 'airport' => 'New York JFK', 'iata' => 'JFK' },
          'flight_status' => 'scheduled'
        }
      ]
    }

    # Set up the mock expectation
    @mock_client.expects(:search_flights).with(flight_iata: 'BA123', limit: 10).returns(mock_response)

    # Call the tool
    result = @tool.call(flight_iata: 'BA123')

    # Assert the result contains the expected formatted output
    assert_includes result, 'British Airways BA123'
    assert_includes result, 'From: London Heathrow (LHR)'
    assert_includes result, 'To: New York JFK (JFK)'
    assert_includes result, 'Status: scheduled'
  end

  def test_call_with_no_results
    # Set up the mock response with no data
    @mock_client.expects(:search_flights).returns({ 'data' => [] })

    # Call the tool
    result = @tool.call(flight_iata: 'INVALID')

    # Assert the result contains the expected message
    assert_equal "No flights found matching your criteria.", result
  end
end
```

## Test Fixtures

Test fixtures are sample data files used in tests to simulate API responses. They should be stored in the `test/fixtures` directory.

Example fixture file (`test/fixtures/flight_response.json`):

```json
{
  "pagination": {
    "limit": 10,
    "offset": 0,
    "count": 1,
    "total": 1
  },
  "data": [
    {
      "flight_date": "2025-04-30",
      "flight_status": "scheduled",
      "departure": {
        "airport": "London Heathrow Airport",
        "timezone": "Europe/London",
        "iata": "LHR",
        "icao": "EGLL",
        "terminal": "5",
        "gate": "B22",
        "delay": null,
        "scheduled": "2025-04-30T10:00:00+00:00",
        "estimated": "2025-04-30T10:00:00+00:00",
        "actual": null
      },
      "arrival": {
        "airport": "John F. Kennedy International Airport",
        "timezone": "America/New_York",
        "iata": "JFK",
        "icao": "KJFK",
        "terminal": "7",
        "gate": "A12",
        "delay": null,
        "scheduled": "2025-04-30T13:00:00+00:00",
        "estimated": "2025-04-30T13:00:00+00:00",
        "actual": null
      },
      "airline": {
        "name": "British Airways",
        "iata": "BA",
        "icao": "BAW"
      },
      "flight": {
        "number": "123",
        "iata": "BA123",
        "icao": "BAW123"
      }
    }
  ]
}
```

## Mocking and Stubbing

### Mocking External API Calls

Use WebMock to stub external API calls:

```ruby
stub_request(:get, "https://api.aviationstack.com/v1/flights")
  .with(query: { access_key: 'test_key', flight_iata: 'BA123' })
  .to_return(status: 200, body: fixture_file('flight_response.json'))
```

### Mocking Ruby Objects

Use Mocha to mock Ruby objects:

```ruby
mock_client = mock('AviationStackClient')
mock_client.expects(:search_flights).returns({ 'data' => [] })
```

## Test Coverage

To measure test coverage, add the `simplecov` gem to your Gemfile:

```ruby
gem 'simplecov', require: false, group: :test
```

Then, add this to the top of your `test_helper.rb`:

```ruby
require 'simplecov'
SimpleCov.start

# Rest of your test_helper.rb code
```

Run your tests, and SimpleCov will generate a coverage report in the `coverage` directory.

## Continuous Integration

### GitHub Actions

The project uses GitHub Actions for continuous integration. The workflow is defined in `.github/workflows/ruby-sinatra-fastmcp.yml`:

```yaml
name: Ruby Sinatra FastMCP Tests

on:
  push:
    branches: [ main ]
    paths:
      - 'ruby-sinatra-fastmcp/**'
  pull_request:
    branches: [ main ]
    paths:
      - 'ruby-sinatra-fastmcp/**'

jobs:
  test:
    runs-on: ubuntu-latest

    steps:
    - uses: actions/checkout@v3

    - name: Set up Ruby
      uses: ruby/setup-ruby@v1
      with:
        ruby-version: 3.3.6
        bundler-cache: true
        working-directory: ruby-sinatra-fastmcp

    - name: Install dependencies
      run: |
        cd ruby-sinatra-fastmcp
        bundle install

    - name: Run tests
      run: |
        cd ruby-sinatra-fastmcp
        bundle exec rake test
```

## Testing Best Practices

### General Guidelines

1. **Test in Isolation**: Each test should focus on a specific piece of functionality
2. **Avoid External Dependencies**: Use mocks and stubs to avoid external API calls
3. **Test Edge Cases**: Include tests for error conditions and edge cases
4. **Keep Tests Fast**: Tests should run quickly to encourage frequent testing
5. **Test Public Interfaces**: Focus on testing the public interface of your classes

### Testing MCP Tools

1. **Mock the AviationStackClient**: Avoid making real API calls in tests
2. **Test Different Parameter Combinations**: Ensure tools handle various input parameters correctly
3. **Test Formatting Logic**: Verify that the tool formats responses correctly
4. **Test Error Handling**: Ensure tools handle API errors gracefully

### Testing API Routes

1. **Use Rack::Test**: For simulating HTTP requests
2. **Test Response Status**: Verify the correct HTTP status code is returned
3. **Test Response Content Type**: Verify the correct content type is set
4. **Test Response Body**: Verify the response body contains the expected data
5. **Test Query Parameters**: Verify that query parameters are handled correctly

## Troubleshooting Tests

### Common Issues

#### Tests Failing Due to External API Calls

If your tests are attempting to make real API calls:

1. Ensure WebMock is properly configured in your `test_helper.rb`:
   ```ruby
   WebMock.disable_net_connect!
   ```

2. Check that all API calls are properly stubbed:
   ```ruby
   stub_request(:get, "https://api.aviationstack.com/v1/flights")
     .with(query: hash_including({ access_key: 'test_key' }))
     .to_return(status: 200, body: fixture_file('flight_response.json'))
   ```

#### Inconsistent Test Results

If tests pass sometimes and fail other times:

1. Check for test interdependencies
2. Ensure proper setup and teardown in your tests
3. Look for global state that might be affecting tests

#### Slow Tests

If your tests are running slowly:

1. Identify slow tests with the `minitest-reporters` gem
2. Optimize test fixtures to be smaller
3. Use more focused tests instead of large integration tests

## Additional Resources

- [Minitest Documentation](https://github.com/minitest/minitest)
- [Rack::Test Documentation](https://github.com/rack/rack-test)
- [WebMock Documentation](https://github.com/bblimke/webmock)
- [Mocha Documentation](https://github.com/freerange/mocha)
- [SimpleCov Documentation](https://github.com/simplecov-ruby/simplecov)
