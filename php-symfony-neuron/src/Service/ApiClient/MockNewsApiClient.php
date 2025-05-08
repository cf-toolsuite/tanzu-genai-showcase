<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;

/**
 * Mock Implementation of News API client
 * Returns predefined mock data.
 */
class MockNewsApiClient implements ApiClientInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockNewsApiClient instantiated. Will return mock data.");
    }

    /**
     * Get company news from mock data
     */
    public function getCompanyNews(string $query, int $limit = 10): array
    {
        $this->logger->info("MockNewsApiClient::getCompanyNews called", ['query' => $query, 'limit' => $limit]);

        $articles = [];
        for ($i = 0; $i < $limit; $i++) {
            $articles[] = [
                'title' => "Mock News Article #{$i} about {$query}",
                'description' => "This is a mock description for news article #{$i} about {$query}.",
                'content' => "This is the full mock content for news article #{$i} about {$query}. It contains detailed information that would be relevant to investors interested in {$query}.",
                'url' => "https://example.com/news/{$i}",
                'imageUrl' => "https://via.placeholder.com/300x200?text=News+{$i}",
                'source' => "Mock News Source " . ($i % 3 + 1),
                'author' => "Mock Author " . ($i % 5 + 1),
                'publishedAt' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
                'sentiment' => mt_rand(-10, 10) / 10, // Random sentiment between -1.0 and 1.0
            ];
        }

        return $articles;
    }

    /**
     * Search for top headlines in a specific category
     */
    public function getTopHeadlines(string $category = 'business', string $country = 'us', int $limit = 10): array
    {
        $this->logger->info("MockNewsApiClient::getTopHeadlines called", [
            'category' => $category,
            'country' => $country,
            'limit' => $limit
        ]);

        $articles = [];
        for ($i = 0; $i < $limit; $i++) {
            $articles[] = [
                'title' => "Mock {$category} Headline #{$i} from {$country}",
                'description' => "This is a mock description for a {$category} headline #{$i} from {$country}.",
                'content' => "This is the full mock content for a {$category} headline #{$i} from {$country}. It contains detailed information about this topic.",
                'url' => "https://example.com/{$category}/{$i}",
                'imageUrl' => "https://via.placeholder.com/300x200?text={$category}+{$i}",
                'source' => "Mock {$country} Source " . ($i % 3 + 1),
                'author' => "Mock {$country} Author " . ($i % 5 + 1),
                'publishedAt' => date('Y-m-d H:i:s', strtotime("-{$i} hours")),
                'sentiment' => mt_rand(-10, 10) / 10, // Random sentiment between -1.0 and 1.0
            ];
        }

        return $articles;
    }

    // --- Stubs for other ApiClientInterface methods ---

    public function searchCompanies(string $term): array
    {
        $this->logger->info("MockNewsApiClient::searchCompanies called", ['term' => $term]);
        throw new \BadMethodCallException('News API does not support company search');
    }

    public function getCompanyProfile(string $symbol): array
    {
        $this->logger->info("MockNewsApiClient::getCompanyProfile called", ['symbol' => $symbol]);
        throw new \BadMethodCallException('News API does not support company profiles');
    }

    public function getQuote(string $symbol): array
    {
        $this->logger->info("MockNewsApiClient::getQuote called", ['symbol' => $symbol]);
        throw new \BadMethodCallException('News API does not support stock quotes');
    }

    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $this->logger->info("MockNewsApiClient::getFinancials called", ['symbol' => $symbol, 'period' => $period]);
        throw new \BadMethodCallException('News API does not support financial data');
    }

    public function getExecutives(string $symbol): array
    {
        $this->logger->info("MockNewsApiClient::getExecutives called", ['symbol' => $symbol]);
        throw new \BadMethodCallException('News API does not support executive data');
    }

    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $this->logger->info("MockNewsApiClient::getHistoricalPrices called", ['symbol' => $symbol, 'interval' => $interval]);
        throw new \BadMethodCallException('News API does not support historical prices');
    }

    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array ESG data with scores
     */
    public function getESGData(string $symbol): array
    {
        $this->logger->info("MockNewsApiClient::getESGData called", ['symbol' => $symbol]);
        throw new \BadMethodCallException('News API does not support ESG data');
    }

    /**
     * Get recent SEC filings for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of filings to return
     * @return array SEC filings data
     */
    public function getRecentSecFilings(string $symbol, int $limit = 5): array
    {
        $this->logger->info("MockNewsApiClient::getRecentSecFilings called", ['symbol' => $symbol, 'limit' => $limit]);
        throw new \BadMethodCallException('News API does not support SEC filings data');
    }

    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array
    {
        $this->logger->info("MockNewsApiClient::getAnalystRatings called", ['symbol' => $symbol]);
        throw new \BadMethodCallException('News API does not support analyst ratings');
    }

    /**
     * Get insider trading data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of records to return
     * @return array Insider trading data
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array
    {
        $this->logger->info("MockNewsApiClient::getInsiderTrading called", ['symbol' => $symbol, 'limit' => $limit]);
        throw new \BadMethodCallException('News API does not support insider trading data');
    }

    /**
     * Get institutional ownership data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of records to return
     * @return array Institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        $this->logger->info("MockNewsApiClient::getInstitutionalOwnership called", ['symbol' => $symbol, 'limit' => $limit]);
        throw new \BadMethodCallException('News API does not support institutional ownership data');
    }
}
