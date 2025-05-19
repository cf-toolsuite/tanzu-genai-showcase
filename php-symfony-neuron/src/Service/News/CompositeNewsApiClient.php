<?php

namespace App\Service\News;

use Psr\Log\LoggerInterface;

/**
 * Composite news API client that aggregates news from multiple sources
 */
class CompositeNewsApiClient implements NewsApiClientInterface
{
    /** @var NewsApiClientInterface[] */
    private array $newsApiClients;
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param iterable $newsApiClients Array of news API clients
     * @param LoggerInterface $logger
     */
    public function __construct(
        iterable $newsApiClients,
        LoggerInterface $logger
    ) {
        $this->newsApiClients = is_array($newsApiClients) ? $newsApiClients : iterator_to_array($newsApiClients);
        $this->logger = $logger;
    }

    /**
     * Get latest news for a company from multiple sources
     *
     * @param string $symbol Company ticker symbol or query
     * @param int $limit Number of news items to return
     * @return array Company news
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $allNews = [];
        $errors = [];

        foreach ($this->newsApiClients as $index => $client) {
            try {
                $news = $client->getCompanyNews($symbol, $limit);
                $allNews = array_merge($allNews, $news);
            } catch (\Exception $e) {
                $errors[] = "Client $index: " . $e->getMessage();
                $this->logger->warning("Error getting news from client $index: " . $e->getMessage());
                // Continue with other clients even if one fails
            }
        }

        if (empty($allNews) && !empty($errors)) {
            $this->logger->error('All news clients failed: ' . implode('; ', $errors));
            // Return empty array instead of throwing exception to be more resilient
        }

        // Sort by published date (newest first)
        usort($allNews, function($a, $b) {
            $dateA = strtotime($a['publishedAt'] ?? 0);
            $dateB = strtotime($b['publishedAt'] ?? 0);
            return $dateB - $dateA;
        });

        // Return only the requested number of articles
        return array_slice($allNews, 0, $limit);
    }

    /**
     * Add a news API client to the composite
     *
     * @param NewsApiClientInterface $client
     * @return self
     */
    public function addNewsApiClient(NewsApiClientInterface $client): self
    {
        $this->newsApiClients[] = $client;
        return $this;
    }
}
