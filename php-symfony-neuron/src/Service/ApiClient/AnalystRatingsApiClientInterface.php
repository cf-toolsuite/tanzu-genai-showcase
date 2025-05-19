<?php

namespace App\Service\ApiClient;

/**
 * Interface for API clients that provide analyst ratings data
 */
interface AnalystRatingsApiClientInterface
{
    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol The company symbol
     * @return array The analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array;

    /**
     * Get institutional ownership data for a company
     *
     * @param string $symbol The company symbol
     * @return array The institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol): array;
}
