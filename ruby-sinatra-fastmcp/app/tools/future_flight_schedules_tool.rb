require_relative '../aviation_stack_client'

class FutureFlightSchedulesTool < FastMcp::Tool
  description "Get future flight schedules for a specific date"

  arguments do
    required(:date).filled(:string).description("Flight date in YYYY-MM-DD format")
    optional(:iataCode).maybe(:string).description("Airport IATA code")
    optional(:type).maybe(:string).description("Type of flights to retrieve (arrival or departure)")
    optional(:limit).maybe(:integer).description("Limit the number of results (default: 10)")
  end

  def call(date:, **params)
    # Set default limit if not provided
    params[:limit] ||= 10
    params[:date] = date

    # Create client and fetch data
    client = AviationStackClient.new
    result = client.get_future_flight_schedules(params)

    # Extract relevant data and format response
    flights = result['data']

    if flights.empty?
      return "No future flight schedules found for #{date}."
    end

    format_future_flight_schedules(flights, date)
  end

  private

  def format_future_flight_schedules(flights, date)
    response = "Found #{flights.size} future flight schedule(s) for #{date}:\n\n"

    flights.each_with_index do |flight, index|
      response += "Flight #{index + 1}:\n"

      # Add airline and flight number information
      airline_name = flight['airline']['name'].to_s.empty? ? flight['airline']['iataCode'] : flight['airline']['name']
      response += "Airline: #{airline_name} (#{flight['airline']['iataCode']})\n"
      response += "Flight: #{flight['flight']['iataNumber']}\n"

      # Add departure information
      response += "From: #{flight['departure']['iataCode']}"
      response += " (#{flight['departure']['icaoCode']})" if flight['departure']['icaoCode']
      response += "\n"

      if flight['departure']['terminal']
        response += "Departure terminal: #{flight['departure']['terminal']}\n"
      end

      if flight['departure']['gate']
        response += "Departure gate: #{flight['departure']['gate']}\n"
      end

      if flight['departure']['scheduledTime']
        response += "Scheduled departure: #{flight['departure']['scheduledTime']}\n"
      end

      # Add arrival information
      response += "To: #{flight['arrival']['iataCode']}"
      response += " (#{flight['arrival']['icaoCode']})" if flight['arrival']['icaoCode']
      response += "\n"

      if flight['arrival']['terminal']
        response += "Arrival terminal: #{flight['arrival']['terminal']}\n"
      end

      if flight['arrival']['gate']
        response += "Arrival gate: #{flight['arrival']['gate']}\n"
      end

      if flight['arrival']['scheduledTime']
        response += "Scheduled arrival: #{flight['arrival']['scheduledTime']}\n"
      end

      # Add aircraft information if available
      if flight['aircraft'] && flight['aircraft']['modelText']
        response += "Aircraft: #{flight['aircraft']['modelText']}\n"
      end

      # Add weekday information if available
      if flight['weekday']
        weekdays = {
          '1' => 'Monday',
          '2' => 'Tuesday',
          '3' => 'Wednesday',
          '4' => 'Thursday',
          '5' => 'Friday',
          '6' => 'Saturday',
          '7' => 'Sunday'
        }
        weekday = weekdays[flight['weekday']] || flight['weekday']
        response += "Day of week: #{weekday}\n"
      end

      # Add codeshare information if available
      if flight['codeshared'] && flight['codeshared']['airline']
        response += "Codeshared with: #{flight['codeshared']['airline']['name']} "
        response += "(#{flight['codeshared']['airline']['iataCode']}), "
        response += "Flight #{flight['codeshared']['flight']['iataNumber']}\n"
      end

      response += "\n"
    end

    response
  end
end
