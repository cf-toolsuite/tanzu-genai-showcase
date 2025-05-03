require_relative 'test_helper'
require_relative '../app/aviation_stack_client'

class AviationStackClientTest < Minitest::Test
  def setup
    @client = AviationStackClient.new('test_api_key')

    # Set up base URL for all stubs
    @base_url = 'https://api.aviationstack.com/v1'
  end

  def test_search_flights
    # Stub the HTTP request
    stub_request(:get, "#{@base_url}/flights")
      .with(query: { access_key: 'test_api_key', limit: 2 })
      .to_return(
        status: 200,
        body: {
          data: [
            {
              flight_date: '2023-04-01',
              flight_status: 'active',
              departure: { airport: 'New York JFK', iata: 'JFK' },
              arrival: { airport: 'London Heathrow', iata: 'LHR' },
              airline: { name: 'British Airways', iata: 'BA' },
              flight: { number: '112', iata: 'BA112' }
            }
          ]
        }.to_json,
        headers: { 'Content-Type' => 'application/json' }
      )

    # Make the request
    response = @client.search_flights(limit: 2)

    # Assert request was made
    assert_requested :get, "#{@base_url}/flights", query: { access_key: 'test_api_key', limit: 2 }

    # Assert response is correctly parsed
    assert_instance_of Hash, response
    assert_instance_of Array, response['data']
    assert_equal 1, response['data'].length
    assert_equal 'British Airways', response['data'][0]['airline']['name']
  end

  def test_get_flight_status
    # Stub the HTTP request
    stub_request(:get, "#{@base_url}/flights")
      .with(query: { access_key: 'test_api_key', flight_iata: 'BA112' })
      .to_return(
        status: 200,
        body: {
          data: [
            {
              flight_date: '2023-04-01',
              flight_status: 'active',
              departure: { airport: 'New York JFK', iata: 'JFK' },
              arrival: { airport: 'London Heathrow', iata: 'LHR' },
              airline: { name: 'British Airways', iata: 'BA' },
              flight: { number: '112', iata: 'BA112' }
            }
          ]
        }.to_json,
        headers: { 'Content-Type' => 'application/json' }
      )

    # Make the request
    response = @client.get_flight_status('BA112')

    # Assert request was made
    assert_requested :get, "#{@base_url}/flights", query: { access_key: 'test_api_key', flight_iata: 'BA112' }

    # Assert response is correctly parsed
    assert_instance_of Hash, response
    assert_instance_of Array, response['data']
    assert_equal 1, response['data'].length
    assert_equal 'BA112', response['data'][0]['flight']['iata']
  end

  def test_search_airports
    # Stub the HTTP request
    stub_request(:get, "#{@base_url}/airports")
      .with(query: { access_key: 'test_api_key', iata_code: 'LHR' })
      .to_return(
        status: 200,
        body: {
          data: [
            {
              airport_name: 'London Heathrow',
              iata_code: 'LHR',
              icao_code: 'EGLL',
              city: 'London',
              country_name: 'United Kingdom'
            }
          ]
        }.to_json,
        headers: { 'Content-Type' => 'application/json' }
      )

    # Make the request
    response = @client.search_airports(iata_code: 'LHR')

    # Assert request was made
    assert_requested :get, "#{@base_url}/airports", query: { access_key: 'test_api_key', iata_code: 'LHR' }

    # Assert response is correctly parsed
    assert_instance_of Hash, response
    assert_instance_of Array, response['data']
    assert_equal 1, response['data'].length
    assert_equal 'London Heathrow', response['data'][0]['airport_name']
  end

  def test_search_airlines
    # Stub the HTTP request
    stub_request(:get, "#{@base_url}/airlines")
      .with(query: { access_key: 'test_api_key', iata_code: 'BA' })
      .to_return(
        status: 200,
        body: {
          data: [
            {
              airline_name: 'British Airways',
              iata_code: 'BA',
              icao_code: 'BAW',
              country_name: 'United Kingdom'
            }
          ]
        }.to_json,
        headers: { 'Content-Type' => 'application/json' }
      )

    # Make the request
    response = @client.search_airlines(iata_code: 'BA')

    # Assert request was made
    assert_requested :get, "#{@base_url}/airlines", query: { access_key: 'test_api_key', iata_code: 'BA' }

    # Assert response is correctly parsed
    assert_instance_of Hash, response
    assert_instance_of Array, response['data']
    assert_equal 1, response['data'].length
    assert_equal 'British Airways', response['data'][0]['airline_name']
  end

  def test_api_error_handling
    # Stub the HTTP request to return an error
    stub_request(:get, "#{@base_url}/flights")
      .with(query: { access_key: 'invalid_key' })
      .to_return(
        status: 401,
        body: {
          error: {
            code: 401,
            message: 'Invalid API key'
          }
        }.to_json,
        headers: { 'Content-Type' => 'application/json' }
      )

    # Create a client with invalid key
    client = AviationStackClient.new('invalid_key')

    # Make the request and expect an error
    assert_raises RuntimeError do
      client.search_flights
    end
  end
end
