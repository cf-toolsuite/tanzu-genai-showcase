require_relative '../aviation_stack_client'

class AirlineInfoTool < FastMcp::Tool
  description "Get information about airlines"

  arguments do
    optional(:airline_name).maybe(:string).description("Airline name (e.g., American Airlines)")
    optional(:iata_code).maybe(:string).description("Airline IATA code (e.g., AA)")
    optional(:icao_code).maybe(:string).description("Airline ICAO code (e.g., AAL)")
    optional(:country).maybe(:string).description("Country name")
    optional(:limit).maybe(:integer).description("Limit the number of results (default: 10)")
  end

  def call(**params)
    # Set default limit if not provided
    params[:limit] ||= 10

    # Create client and fetch data
    client = AviationStackClient.new
    result = client.search_airlines(params)

    # Extract and format data
    airlines = result['data']

    if airlines.empty?
      return "No airlines found matching your criteria."
    end

    format_airlines(airlines)
  end

  private

  def format_airlines(airlines)
    response = "Found #{airlines.size} airline(s):\n\n"

    airlines.each_with_index do |airline, index|
      response += "Airline #{index + 1}:\n"
      response += "Name: #{airline['airline_name']}\n"
      response += "IATA: #{airline['iata_code']}\n"
      response += "ICAO: #{airline['icao_code']}\n"
      response += "Country: #{airline['country_name']}\n"
      response += "Fleet Size: #{airline['fleet_size'] || 'Unknown'}\n" if airline['fleet_size']
      response += "Call Sign: #{airline['callsign'] || 'Unknown'}\n" if airline['callsign']
      response += "Hub Code: #{airline['hub_code'] || 'Unknown'}\n" if airline['hub_code']
      response += "\n"
    end

    response
  end
end
