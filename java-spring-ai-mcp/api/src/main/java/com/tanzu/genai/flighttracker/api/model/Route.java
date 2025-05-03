package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Model class for Route information.
 */
@Data
public class Route {
    private String airline;

    @JsonProperty("airline_iata")
    private String airlineIata;

    @JsonProperty("airline_icao")
    private String airlineIcao;

    @JsonProperty("flight_number")
    private String flightNumber;

    @JsonProperty("flight_iata")
    private String flightIata;

    @JsonProperty("flight_icao")
    private String flightIcao;

    @JsonProperty("departure_airport")
    private String departureAirport;

    @JsonProperty("departure_iata")
    private String departureIata;

    @JsonProperty("departure_icao")
    private String departureIcao;

    @JsonProperty("departure_terminal")
    private String departureTerminal;

    @JsonProperty("departure_time")
    private String departureTime;

    @JsonProperty("arrival_airport")
    private String arrivalAirport;

    @JsonProperty("arrival_iata")
    private String arrivalIata;

    @JsonProperty("arrival_icao")
    private String arrivalIcao;

    @JsonProperty("arrival_terminal")
    private String arrivalTerminal;

    @JsonProperty("arrival_time")
    private String arrivalTime;
}
