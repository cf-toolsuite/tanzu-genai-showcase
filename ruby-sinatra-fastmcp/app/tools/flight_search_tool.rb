require_relative '../aviation_stack_client'

class FlightSearchTool < FastMcp::Tool
  description "Search for flights based on various criteria"

  arguments do
    optional(:flight_iata).maybe(:string).description("Flight IATA code (e.g., AA123)")
    optional(:flight_icao).maybe(:string).description("Flight ICAO code")
    optional(:flight_number).maybe(:string).description("Flight number (e.g., 123)")
    optional(:dep_iata).maybe(:string).description("Departure airport IATA code")
    optional(:arr_iata).maybe(:string).description("Arrival airport IATA code")
    optional(:airline_name).maybe(:string).description("Airline name")
    optional(:airline_iata).maybe(:string).description("Airline IATA code")
    optional(:flight_date).maybe(:string).description("Flight date in YYYY-MM-DD format")
    optional(:flight_status).maybe(:string).description("Flight status (scheduled, active, landed, cancelled, etc.)")
    optional(:limit).maybe(:integer).description("Limit the number of results (default: 10)")
  end

  def call(**params)
    # Set default limit if not provided
    params[:limit] ||= 10

    # Create client and fetch data
    client = AviationStackClient.new
    result = client.search_flights(params)

    # Extract relevant data and format response
    flights = result['data']

    if flights.empty?
      return "No flights found matching your criteria."
    end

    format_flights(flights)
  end

  private

  def format_flights(flights)
    response = "Found #{flights.size} flight(s):\n\n"

    flights.each_with_index do |flight, index|
      response += "Flight #{index + 1}:\n"
      response += "#{flight['airline']['name']} #{flight['flight']['iata']}\n"
      response += "From: #{flight['departure']['airport']} (#{flight['departure']['iata']})\n"
      response += "To: #{flight['arrival']['airport']} (#{flight['arrival']['iata']})\n"
      response += "Status: #{flight['flight_status']}\n"

      # Add scheduled times
      dep_time = flight['departure']['scheduled']
      arr_time = flight['arrival']['scheduled']
      response += "Scheduled departure: #{dep_time}\n" if dep_time
      response += "Scheduled arrival: #{arr_time}\n" if arr_time

      # Add delay information if available
      if flight['departure']['delay']
        response += "Departure delay: #{flight['departure']['delay']} minutes\n"
      end

      if flight['arrival']['delay']
        response += "Arrival delay: #{flight['arrival']['delay']} minutes\n"
      end

      response += "\n"
    end

    response
  end
end
