<?php

namespace App\Service\ApiClient;

/**
 * Interface for API clients that provide news data
 */
interface NewsApiClientInterface
{
    /**
     * Get news articles for a company
     *
     * @param string $symbol The company symbol
     * @param int $limit The maximum number of news articles to return
     * @return array The news articles
     */
    public function getCompanyNews(string $symbol, int $limit = 10): array;
}
