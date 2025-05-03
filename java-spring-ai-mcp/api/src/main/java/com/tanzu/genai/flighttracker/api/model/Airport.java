package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Model class for Airport information.
 */
@Data
public class Airport {
    private String name;
    private String iata;
    private String icao;

    @JsonProperty("iata_code")
    private String iataCode;

    @JsonProperty("icao_code")
    private String icaoCode;

    private Double latitude;
    private Double longitude;
    private String timezone;
    private String country;
    private String city;
}
