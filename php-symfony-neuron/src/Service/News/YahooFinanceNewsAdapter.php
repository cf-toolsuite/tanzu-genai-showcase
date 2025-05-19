<?php

namespace App\Service\News;

use App\Service\ApiClient\YahooFinanceClient;
use Psr\Log\LoggerInterface;

/**
 * Adapter for YahooFinanceClient to implement NewsApiClientInterface
 */
class YahooFinanceNewsAdapter implements NewsApiClientInterface
{
    private YahooFinanceClient $yahooFinanceClient;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        YahooFinanceClient $yahooFinanceClient,
        LoggerInterface $logger
    ) {
        $this->yahooFinanceClient = $yahooFinanceClient;
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
            return $this->yahooFinanceClient->getCompanyNews($symbol, $limit);
        } catch (\Exception $e) {
            $this->logger->error('Error getting company news from YahooFinanceClient: ' . $e->getMessage());
            throw $e;
        }
    }
}
