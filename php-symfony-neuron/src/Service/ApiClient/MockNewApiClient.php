<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;

/**
 * Mock Implementation of News API client (newsapi.org)
 */
class MockNewsApiClient implements ApiClientInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockNewsApiClient instantiated.");
    }

    public function getCompanyNews(string $query, int $limit = 5, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $this->logger->info("MockNewsApiClient::getCompanyNews called", ['query' => $query, 'limit' => $limit]);
        return $this->getMockNewsArticles($query, $limit);
    }

    public function getTopHeadlines(string $category = 'business', string $country = 'us', int $limit = 10): array
    {
        $this->logger->info("MockNewsApiClient::getTopHeadlines called", ['category' => $category, 'limit' => $limit]);
        return $this->getMockNewsArticles($category, $limit);
    }

    // --- Stubs for other ApiClientInterface methods ---
    public function searchCompanies(string $term): array
    {
        return [];
    }
    public function getCompanyProfile(string $symbol): array
    {
        return ['symbol' => $symbol, 'name' => 'Mock Profile (News)'];
    }
    public function getQuote(string $symbol): array
    {
        return ['symbol' => $symbol, 'price' => 0];
    }
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        return [];
    }
    public function getExecutives(string $symbol): array
    {
        return [];
    }
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        return [];
    }

    // --- Mock Data Generation ---
    private function getMockNewsArticles(string $topic, int $limit): array
    {
        $articles = [];
        for ($i = 0; $i < $limit; $i++) {
            $articles[] = [
                'title' => "Mock Article {$i} about {$topic}",
                'description' => 'This is a mock description.',
                'content' => 'Mock content goes here.',
                'url' => 'https://mock-news.example.com/article-' . $i,
                'imageUrl' => 'https://via.placeholder.com/150?text=News+' . $i,
                'source' => 'Mock News Source',
                'author' => 'Mock Author',
                'publishedAt' => date('Y-m-d H:i:s', strtotime("-{$i} hour")),
                'sentiment' => 0,
            ];
        }
        return $articles;
    }
}
