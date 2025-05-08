<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * News API client (newsapi.org) - REAL Implementation
 */
class NewsApiClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://newsapi.org/v2';
        $this->apiKey = $this->params->get('news_api.api_key', '');
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        // Uses apiKey query parameter
        return ['apiKey' => $this->apiKey];
    }

    /**
     * Get company news from News API (uses /everything endpoint)
     */
    public function getCompanyNews(string $query, int $limit = 10, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $endpoint = '/everything';
        if (!$from) $from = new \DateTime('30 days ago');
        if (!$to) $to = new \DateTime();
        $params = [
            'q' => $query,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
            'language' => 'en',
            'sortBy' => 'publishedAt',
            'pageSize' => $limit
        ];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $articles = [];
        if (isset($data['articles']) && is_array($data['articles'])) {
            foreach ($data['articles'] as $article) {
                $articles[] = [
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'content' => $article['content'] ?? '',
                    'url' => $article['url'] ?? '',
                    'imageUrl' => $article['urlToImage'] ?? null,
                    'source' => $article['source']['name'] ?? 'Unknown',
                    'author' => $article['author'] ?? 'Unknown',
                    'publishedAt' => $article['publishedAt'] ?? date('Y-m-d H:i:s'),
                    'sentiment' => 0, // Not provided by NewsAPI
                ];
            }
        }
        return $articles;
    }

    /**
     * Search for top headlines in a specific category
     */
    public function getTopHeadlines(string $category = 'business', string $country = 'us', int $limit = 10): array
    {
        $endpoint = '/top-headlines';
        $params = ['category' => $category, 'country' => $country, 'pageSize' => $limit];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing (same as getCompanyNews)
        $articles = [];
        if (isset($data['articles']) && is_array($data['articles'])) {
            foreach ($data['articles'] as $article) {
                $articles[] = [
                    'title' => $article['title'] ?? '',
                    'description' => $article['description'] ?? '',
                    'content' => $article['content'] ?? '',
                    'url' => $article['url'] ?? '',
                    'imageUrl' => $article['urlToImage'] ?? null,
                    'source' => $article['source']['name'] ?? 'Unknown',
                    'author' => $article['author'] ?? 'Unknown',
                    'publishedAt' => $article['publishedAt'] ?? date('Y-m-d H:i:s'),
                    'sentiment' => 0,
                ];
            }
        }
        return $articles;
    }

    // --- Stubs for other ApiClientInterface methods ---
    public function searchCompanies(string $term): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
    public function getCompanyProfile(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
    public function getQuote(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
    public function getExecutives(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array ESG data with scores
     * @throws \Exception If the API request fails
     */
    public function getESGData(string $symbol): array
    {
        throw new \BadMethodCallException('News API does not support ESG data');
    }

    /**
     * Get recent SEC filings for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of filings to return
     * @return array SEC filings data
     * @throws \Exception If the API request fails
     */
    public function getRecentSecFilings(string $symbol, int $limit = 5): array
    {
        throw new \BadMethodCallException('News API does not support SEC filings data');
    }

    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     * @throws \Exception If the API request fails
     */
    public function getAnalystRatings(string $symbol): array
    {
        throw new \BadMethodCallException('News API does not support analyst ratings');
    }

    /**
     * Get insider trading data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of records to return
     * @return array Insider trading data
     * @throws \Exception If the API request fails
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array
    {
        throw new \BadMethodCallException('News API does not support insider trading data');
    }

    /**
     * Get institutional ownership data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of records to return
     * @return array Institutional ownership data
     * @throws \Exception If the API request fails
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        throw new \BadMethodCallException('News API does not support institutional ownership data');
    }
}
