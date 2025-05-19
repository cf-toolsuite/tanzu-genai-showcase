<?php

namespace App\Service\ApiClient;

/**
 * Interface for API clients that provide ESG (Environmental, Social, Governance) data
 */
interface EsgDataApiClientInterface
{
    /**
     * Get ESG data for a company
     *
     * @param string $symbol The company symbol
     * @return array The ESG data
     */
    public function getESGData(string $symbol): array;
}
