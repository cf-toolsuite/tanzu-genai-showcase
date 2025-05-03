package com.tanzu.genai.flighttracker.api.model;

import lombok.Data;
import com.fasterxml.jackson.annotation.JsonProperty;

/**
 * Model class for Aircraft Type information.
 */
@Data
public class AircraftType {

    @JsonProperty("aircraft_name")
    private String aircraftName;

    @JsonProperty("aircraft_iata")
    private String aircraftIata;

    @JsonProperty("aircraft_icao")
    private String aircraftIcao;

    @JsonProperty("plane_type_id")
    private Integer planeTypeId;
}
