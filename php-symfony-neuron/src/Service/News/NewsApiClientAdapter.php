<?php

namespace App\Service\News;

use App\Service\ApiClient\NewsApiClient;
use Psr\Log\LoggerInterface;

/**
 * Adapter for NewsApiClient to implement NewsApiClientInterface
 */
class NewsApiClientAdapter implements NewsApiClientInterface
{
    private NewsApiClient $newsApiClient;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        NewsApiClient $newsApiClient,
        LoggerInterface $logger
    ) {
        $this->newsApiClient = $newsApiClient;
        $this->logger = $logger;
    }

    /**
     * Get latest news for a company
     *
     * @param string $symbol Company ticker symbol or query
     * @param int $limit Number of news items to return
     * @return array Company news
     * @throws \Exception If the API request fails
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        try {
            return $this->newsApiClient->getCompanyNews($symbol, $limit);
        } catch (\Exception $e) {
            $this->logger->error('Error getting company news from NewsApiClient: ' . $e->getMessage());
            throw $e;
        }
    }
}
