require_relative '../aviation_stack_client'

class FlightStatusTool < FastMcp::Tool
  description "Get detailed status information for a specific flight"

  arguments do
    required(:flight_iata).filled(:string).description("Flight IATA code (e.g., AA123)")
    optional(:flight_date).maybe(:string).description("Flight date in YYYY-MM-DD format")
  end

  def call(flight_iata:, flight_date: nil)
    # Create client and fetch data
    client = AviationStackClient.new
    params = { flight_iata: flight_iata }
    params[:flight_date] = flight_date if flight_date

    result = client.get_flight_status(flight_iata, params)
    flights = result['data']

    if flights.empty?
      return "No flight found with IATA code #{flight_iata}."
    end

    # Get the first matching flight (should be the only one)
    flight = flights.first

    format_flight_status(flight)
  end

  private

  def format_flight_status(flight)
    response = "Flight Status Information:\n\n"

    # Flight information
    response += "Flight: #{flight['airline']['name']} #{flight['flight']['iata']}\n"
    response += "Aircraft: #{flight['aircraft']['registration'] || 'Unknown'}\n" if flight['aircraft']
    response += "Status: #{flight['flight_status']}\n\n"

    # Departure information
    response += "Departure:\n"
    response += "  Airport: #{flight['departure']['airport']} (#{flight['departure']['iata']})\n"
    response += "  Terminal: #{flight['departure']['terminal']}\n" if flight['departure']['terminal']
    response += "  Gate: #{flight['departure']['gate']}\n" if flight['departure']['gate']

    # Add scheduled, estimated, and actual times
    dep_scheduled = flight['departure']['scheduled']
    dep_estimated = flight['departure']['estimated']
    dep_actual = flight['departure']['actual']

    response += "  Scheduled: #{dep_scheduled}\n" if dep_scheduled
    response += "  Estimated: #{dep_estimated}\n" if dep_estimated && dep_estimated != dep_scheduled
    response += "  Actual: #{dep_actual}\n" if dep_actual && dep_actual != dep_estimated

    # Add delay information
    response += "  Delay: #{flight['departure']['delay']} minutes\n" if flight['departure']['delay']
    response += "\n"

    # Arrival information
    response += "Arrival:\n"
    response += "  Airport: #{flight['arrival']['airport']} (#{flight['arrival']['iata']})\n"
    response += "  Terminal: #{flight['arrival']['terminal']}\n" if flight['arrival']['terminal']
    response += "  Gate: #{flight['arrival']['gate']}\n" if flight['arrival']['gate']
    response += "  Baggage: #{flight['arrival']['baggage']}\n" if flight['arrival']['baggage']

    # Add scheduled, estimated, and actual times
    arr_scheduled = flight['arrival']['scheduled']
    arr_estimated = flight['arrival']['estimated']
    arr_actual = flight['arrival']['actual']

    response += "  Scheduled: #{arr_scheduled}\n" if arr_scheduled
    response += "  Estimated: #{arr_estimated}\n" if arr_estimated && arr_estimated != arr_scheduled
    response += "  Actual: #{arr_actual}\n" if arr_actual && arr_actual != arr_estimated

    # Add delay information
    response += "  Delay: #{flight['arrival']['delay']} minutes\n" if flight['arrival']['delay']

    # Add live data if available
    if flight['live']
      response += "\nLive Data:\n"
      response += "  Updated: #{flight['live']['updated']}\n"
      response += "  Latitude: #{flight['live']['latitude']}\n"
      response += "  Longitude: #{flight['live']['longitude']}\n"
      response += "  Altitude: #{flight['live']['altitude']} ft\n"
      response += "  Direction: #{flight['live']['direction']}°\n"
      response += "  Speed: #{flight['live']['speed_horizontal']} km/h\n"
      response += "  On Ground: #{flight['live']['is_ground'] ? 'Yes' : 'No'}\n"
    end

    response
  end
end
