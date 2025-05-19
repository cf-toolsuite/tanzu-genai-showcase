<?php

namespace App\Service\ApiClient;

/**
 * Interface for API clients that provide SEC filings data
 */
interface SecFilingsApiClientInterface
{
    /**
     * Get recent SEC filings for a company
     *
     * @param string $symbol The company symbol
     * @param int $limit The maximum number of filings to return
     * @return array The SEC filings
     */
    public function getRecentSecFilings(string $symbol, int $limit = 10): array;

    /**
     * Get insider trading information for a company
     *
     * @param string $symbol The company symbol
     * @param int $limit The maximum number of transactions to return
     * @return array The insider trading data
     */
    public function getInsiderTrading(string $symbol, int $limit = 10): array;
}
