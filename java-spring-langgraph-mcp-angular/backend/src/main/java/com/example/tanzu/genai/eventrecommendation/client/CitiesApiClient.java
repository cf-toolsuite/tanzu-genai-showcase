package com.example.tanzu.genai.eventrecommendation.client;

import com.example.tanzu.genai.eventrecommendation.model.CityInfo;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.core.ParameterizedTypeReference;
import org.springframework.stereotype.Component;
import org.springframework.web.reactive.function.client.WebClient;
import reactor.core.publisher.Mono;

import java.util.List;

@Slf4j
@Component
public class CitiesApiClient {

    private final WebClient webClient;
    private final String apiKey;

    public CitiesApiClient(
            WebClient.Builder webClientBuilder,
            @Value("${cities.api.url}") String apiUrl,
            @Value("${cities.api.key}") String apiKey) {
        this.webClient = webClientBuilder
                .baseUrl(apiUrl)
                .build();
        this.apiKey = apiKey;
    }

    public Mono<List<CityInfo>> getCityByName(String name) {
        return webClient.get()
                .uri(uriBuilder -> uriBuilder.queryParam("name", name).build())
                .header("X-Api-Key", apiKey)
                .retrieve()
                .bodyToMono(new ParameterizedTypeReference<List<CityInfo>>() {})
                .doOnError(e -> log.error("Error fetching city data for {}: {}", name, e.getMessage()));
    }

    public Mono<List<CityInfo>> getCityByNameAndCountry(String name, String country) {
        return webClient.get()
                .uri(uriBuilder -> uriBuilder
                        .queryParam("name", name)
                        .queryParam("country", country)
                        .build())
                .header("X-Api-Key", apiKey)
                .retrieve()
                .bodyToMono(new ParameterizedTypeReference<List<CityInfo>>() {})
                .doOnError(e -> log.error("Error fetching city data for {} in {}: {}", name, country, e.getMessage()));
    }
}
