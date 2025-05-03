require_relative 'test_helper'
require_relative '../app/tools/flight_search_tool'

class FlightSearchToolTest < Minitest::Test
  def setup
    @tool = FlightSearchTool.new

    # Mock the aviation stack client
    @mock_client = mock
    AviationStackClient.stubs(:new).returns(@mock_client)
  end

  def test_search_flights_with_results
    # Sample flight data
    flight_data = {
      'data' => [
        {
          'flight_date' => '2023-04-01',
          'flight_status' => 'active',
          'departure' => {
            'airport' => 'New York JFK',
            'iata' => 'JFK',
            'scheduled' => '2023-04-01T20:00:00+00:00'
          },
          'arrival' => {
            'airport' => 'London Heathrow',
            'iata' => 'LHR',
            'scheduled' => '2023-04-02T08:30:00+00:00'
          },
          'airline' => {
            'name' => 'British Airways',
            'iata' => 'BA'
          },
          'flight' => {
            'number' => '112',
            'iata' => 'BA112'
          }
        }
      ]
    }

    # Set expectations for the mock client
    @mock_client.expects(:search_flights).with(flight_iata: 'BA112', limit: 5).returns(flight_data)

    # Call the tool with the test parameters
    result = @tool.call(flight_iata: 'BA112', limit: 5)

    # Check result contains expected flight information
    assert_includes result, 'British Airways BA112'
    assert_includes result, 'New York JFK (JFK)'
    assert_includes result, 'London Heathrow (LHR)'
    assert_includes result, 'Status: active'
  end

  def test_search_flights_with_no_results
    # Empty flight data
    empty_data = { 'data' => [] }

    # Set expectations for the mock client
    @mock_client.expects(:search_flights).with(dep_iata: 'XYZ', limit: 10).returns(empty_data)

    # Call the tool with parameters that should return no results
    result = @tool.call(dep_iata: 'XYZ')

    # Check result indicates no flights found
    assert_equal "No flights found matching your criteria.", result
  end

  def test_flight_with_delay_information
    # Sample flight data with delay information
    flight_data = {
      'data' => [
        {
          'flight_date' => '2023-04-01',
          'flight_status' => 'delayed',
          'departure' => {
            'airport' => 'New York JFK',
            'iata' => 'JFK',
            'scheduled' => '2023-04-01T20:00:00+00:00',
            'delay' => 45
          },
          'arrival' => {
            'airport' => 'London Heathrow',
            'iata' => 'LHR',
            'scheduled' => '2023-04-02T08:30:00+00:00',
            'delay' => 30
          },
          'airline' => {
            'name' => 'British Airways',
            'iata' => 'BA'
          },
          'flight' => {
            'number' => '112',
            'iata' => 'BA112'
          }
        }
      ]
    }

    # Set expectations for the mock client
    @mock_client.expects(:search_flights).with(flight_status: 'delayed', limit: 10).returns(flight_data)

    # Call the tool with the test parameters
    result = @tool.call(flight_status: 'delayed')

    # Check result contains delay information
    assert_includes result, 'Departure delay: 45 minutes'
    assert_includes result, 'Arrival delay: 30 minutes'
  end
end
