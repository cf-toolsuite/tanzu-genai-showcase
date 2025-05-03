package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Model class for Airline information.
 */
@Data
public class Airline {
    private String name;
    private String iata;
    private String icao;

    @JsonProperty("iata_code")
    private String iataCode;

    @JsonProperty("icao_code")
    private String icaoCode;

    private String callsign;
    private String country;
}
