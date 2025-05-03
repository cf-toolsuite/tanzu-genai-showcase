require_relative '../aviation_stack_client'

class AirportInfoTool < FastMcp::Tool
  description "Get information about airports"

  arguments do
    optional(:iata_code).maybe(:string).description("Airport IATA code (e.g., LAX)")
    optional(:icao_code).maybe(:string).description("Airport ICAO code (e.g., KLAX)")
    optional(:airport_name).maybe(:string).description("Airport name (e.g., Los Angeles International)")
    optional(:city).maybe(:string).description("City name")
    optional(:country).maybe(:string).description("Country name")
    optional(:limit).maybe(:integer).description("Limit the number of results (default: 10)")
  end

  def call(**params)
    # Set default limit if not provided
    params[:limit] ||= 10

    # Create client and fetch data
    client = AviationStackClient.new
    result = client.search_airports(params)

    # Extract and format data
    airports = result['data']

    if airports.empty?
      return "No airports found matching your criteria."
    end

    format_airports(airports)
  end

  private

  def format_airports(airports)
    response = "Found #{airports.size} airport(s):\n\n"

    airports.each_with_index do |airport, index|
      response += "Airport #{index + 1}:\n"
      response += "Name: #{airport['airport_name']}\n"
      response += "IATA: #{airport['iata_code']}\n"
      response += "ICAO: #{airport['icao_code']}\n"
      response += "Location: #{airport['city']}, #{airport['country_name']}\n"
      response += "Latitude: #{airport['latitude']}, Longitude: #{airport['longitude']}\n"
      response += "Timezone: #{airport['timezone']}\n"
      response += "\n"
    end

    response
  end
end
