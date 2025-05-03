require_relative '../aviation_stack_client'

class FlightSchedulesTool < FastMcp::Tool
  description "Get flight schedules for the current date"

  arguments do
    optional(:flight_iata).maybe(:string).description("Flight IATA code (e.g., AA123)")
    optional(:flight_icao).maybe(:string).description("Flight ICAO code")
    optional(:dep_iata).maybe(:string).description("Departure airport IATA code")
    optional(:arr_iata).maybe(:string).description("Arrival airport IATA code")
    optional(:airline_name).maybe(:string).description("Airline name")
    optional(:airline_iata).maybe(:string).description("Airline IATA code")
    optional(:flight_status).maybe(:string).description("Flight status (scheduled, active, landed, cancelled, etc.)")
    optional(:limit).maybe(:integer).description("Limit the number of results (default: 10)")
  end

  def call(**params)
    # Set default limit if not provided
    params[:limit] ||= 10

    # Create client and fetch data
    client = AviationStackClient.new
    result = client.get_flight_schedules(params)

    # Extract relevant data and format response
    flights = result['data']

    if flights.empty?
      return "No flight schedules found matching your criteria."
    end

    format_flight_schedules(flights)
  end

  private

  def format_flight_schedules(flights)
    response = "Found #{flights.size} flight schedule(s):\n\n"

    flights.each_with_index do |flight, index|
      response += "Flight #{index + 1}:\n"
      response += "#{flight['airline']['name']} #{flight['flight']['iata']}\n"
      response += "From: #{flight['departure']['airport']} (#{flight['departure']['iata']})\n"
      response += "To: #{flight['arrival']['airport']} (#{flight['arrival']['iata']})\n"
      response += "Status: #{flight['flight_status']}\n"

      # Add scheduled times with timezone information
      dep_time = flight['departure']['scheduled']
      arr_time = flight['arrival']['scheduled']
      dep_timezone = flight['departure']['timezone']
      arr_timezone = flight['arrival']['timezone']

      response += "Scheduled departure: #{dep_time} (#{dep_timezone})\n" if dep_time
      response += "Scheduled arrival: #{arr_time} (#{arr_timezone})\n" if arr_time

      # Add terminal and gate information if available
      if flight['departure']['terminal']
        response += "Departure terminal: #{flight['departure']['terminal']}\n"
      end

      if flight['departure']['gate']
        response += "Departure gate: #{flight['departure']['gate']}\n"
      end

      if flight['arrival']['terminal']
        response += "Arrival terminal: #{flight['arrival']['terminal']}\n"
      end

      if flight['arrival']['gate']
        response += "Arrival gate: #{flight['arrival']['gate']}\n"
      end

      # Add aircraft information if available
      if flight['aircraft'] && flight['aircraft']['registration']
        response += "Aircraft: #{flight['aircraft']['registration']}\n"
      end

      response += "\n"
    end

    response
  end
end
