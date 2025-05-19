<?php

namespace App\Service\News;

/**
 * Interface for news API clients
 */
interface NewsApiClientInterface
{
    /**
     * Get latest news for a company
     *
     * @param string $symbol Company ticker symbol or query
     * @param int $limit Number of news items to return
     * @return array Company news
     * @throws \Exception If the API request fails
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array;
}
