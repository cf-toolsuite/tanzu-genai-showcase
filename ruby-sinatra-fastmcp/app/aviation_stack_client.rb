require 'faraday'
require 'json'
require_relative '../config/vcap_services'

class AviationStackClient
  BASE_URL = 'https://api.aviationstack.com/v1'.freeze

  def initialize(api_key = VcapServices.get_api_key('AVIATIONSTACK_API_KEY'))
    @api_key = api_key

    # Raise a clear error if no API key is found
    if @api_key.nil?
      raise "AviationStack API key not found. Please ensure AVIATIONSTACK_API_KEY is set in your environment or provided through a service binding."
    end

    @conn = Faraday.new(url: BASE_URL) do |f|
      f.request :url_encoded
      f.adapter Faraday.default_adapter
    end
  end

  def search_flights(params = {})
    params = { access_key: @api_key }.merge(params)
    response = @conn.get('flights', params)
    handle_response(response)
  end

  def get_flight_status(flight_iata, params = {})
    params = {
      access_key: @api_key,
      flight_iata: flight_iata
    }.merge(params)

    response = @conn.get('flights', params)
    handle_response(response)
  end

  def search_airports(params = {})
    params = { access_key: @api_key }.merge(params)
    response = @conn.get('airports', params)
    handle_response(response)
  end

  def search_airlines(params = {})
    params = { access_key: @api_key }.merge(params)
    response = @conn.get('airlines', params)
    handle_response(response)
  end

  def search_cities(params = {})
    params = { access_key: @api_key }.merge(params)
    response = @conn.get('cities', params)
    handle_response(response)
  end

  def get_flight_schedules(params = {})
    params = { access_key: @api_key }.merge(params)
    response = @conn.get('flights', params)
    handle_response(response)
  end

  def get_future_flight_schedules(params = {})
    params = { access_key: @api_key }.merge(params)
    response = @conn.get('flightsFuture', params)
    handle_response(response)
  end

  private

  def handle_response(response)
    result = JSON.parse(response.body)

    if result['error']
      raise "AviationStack API Error: #{result['error']['code']} - #{result['error']['message']}"
    end

    result
  end
end
