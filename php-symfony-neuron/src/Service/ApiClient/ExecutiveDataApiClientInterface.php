<?php

namespace App\Service\ApiClient;

/**
 * Interface for API clients that provide executive data
 */
interface ExecutiveDataApiClientInterface
{
    /**
     * Get executives for a company
     *
     * @param string $symbol The company symbol
     * @return array The executives data
     */
    public function getExecutives(string $symbol): array;
}
