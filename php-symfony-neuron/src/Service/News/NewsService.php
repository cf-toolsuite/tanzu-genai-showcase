<?php

namespace App\Service\News;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for handling company news
 */
class NewsService
{
    private NewsApiClientInterface $newsApiClient;
    private CacheInterface $cache;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        NewsApiClientInterface $newsApiClient,
        CacheInterface $cache,
        LoggerInterface $logger
    ) {
        $this->newsApiClient = $newsApiClient;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * Get company news
     */
    public function getCompanyNews(string $symbol, int $limit = 5, bool $forceRefresh = false): array
    {
        $cacheKey = 'company_news_' . $symbol . '_' . $limit;

        // If force refresh is requested, delete the existing cache item
        if ($forceRefresh) {
            $this->cache->delete($cacheKey);
            $this->logger->info('Forced cache refresh for company news', ['symbol' => $symbol]);
        }

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol, $limit) {
            // Reduce cache time to 5 minutes to get more recent news throughout the day
            $item->expiresAfter(300);
            $this->logger->info('Cache miss for company news', ['symbol' => $symbol]);

            try {
                $news = $this->newsApiClient->getCompanyNews($symbol, $limit);

                // Process and validate each article
                $processedNews = [];
                foreach ($news as $article) {
                    $processedNews[] = $this->validateNewsArticle($article);
                }

                // Deduplicate news articles
                $dedupedNews = $this->deduplicateNewsArticles($processedNews);

                // Sort by published date (newest first)
                usort($dedupedNews, function($a, $b) {
                    $dateA = strtotime($a['publishedAt'] ?? 0);
                    $dateB = strtotime($b['publishedAt'] ?? 0);

                    // Check if either article is from today
                    $todayStart = strtotime('today midnight');
                    $isAToday = ($dateA >= $todayStart);
                    $isBToday = ($dateB >= $todayStart);

                    // Prioritize today's news
                    if ($isAToday && !$isBToday) {
                        return -1; // A is from today, B is not, so A comes first
                    } elseif (!$isAToday && $isBToday) {
                        return 1;  // B is from today, A is not, so B comes first
                    }

                    // If both are from today or both are older, sort by date (newest first)
                    return $dateB - $dateA;
                });

                // Return only the requested number of articles
                return array_slice($dedupedNews, 0, $limit);
            } catch (\Exception $e) {
                $this->logger->error('Error getting company news: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Validate and enhance a news article
     *
     * @param array $article The news article to validate
     * @return array The validated and enhanced article
     */
    private function validateNewsArticle(array $article): array
    {
        // Ensure all required fields exist
        $article['title'] = $article['title'] ?? 'Untitled Article';
        $article['description'] = $article['description'] ?? '';
        $article['url'] = $article['url'] ?? '';
        $article['source'] = $article['source'] ?? 'Unknown Source';
        $article['publishedAt'] = $article['publishedAt'] ?? date('Y-m-d H:i:s');

        // Validate image URL
        if (!empty($article['imageUrl'])) {
            // Check if URL is valid
            if (!filter_var($article['imageUrl'], FILTER_VALIDATE_URL)) {
                $article['imageUrl'] = null;
            }

            // Check if URL uses HTTPS (some news sites block HTTP requests)
            if ($article['imageUrl'] && strpos($article['imageUrl'], 'http://') === 0) {
                $article['imageUrl'] = 'https://' . substr($article['imageUrl'], 7);
            }
        }

        // If no image URL, try to generate one based on the source
        if (empty($article['imageUrl'])) {
            $article['imageUrl'] = $this->getDefaultImageForSource($article['source']);
        }

        return $article;
    }

    /**
     * Get a default image URL based on the news source
     *
     * @param string $source The news source name
     * @return string|null A default image URL or null
     */
    private function getDefaultImageForSource(string $source): ?string
    {
        // Map common news sources to their logos
        // In a real implementation, these would be stored in a configuration file or database
        $sourceLogos = [
            'Yahoo Finance' => 'https://s.yimg.com/cv/apiv2/social/images/yahoo_default_logo.png',
            'CNBC' => 'https://www.cnbc.com/favicon.ico',
            'Bloomberg' => 'https://assets.bwbx.io/s3/javelin/public/javelin/images/bloomberg-logo-black-f11ef4d4c2.svg',
            'Reuters' => 'https://www.reuters.com/pf/resources/images/reuters/logo-vertical-default.svg?d=116',
            'Financial Times' => 'https://www.ft.com/__origami/service/image/v2/images/raw/ftlogo-v1:brand-ft-logo-square-coloured?source=origami-build-service',
            'Wall Street Journal' => 'https://www.wsj.com/favicon.ico',
            'MarketWatch' => 'https://www.marketwatch.com/favicon.ico',
            'Seeking Alpha' => 'https://seekingalpha.com/favicon.ico',
            'Motley Fool' => 'https://www.fool.com/favicon.ico',
            'Investopedia' => 'https://www.investopedia.com/favicon.ico',
            'Business Insider' => 'https://www.businessinsider.com/favicon.ico',
            'Forbes' => 'https://www.forbes.com/favicon.ico',
        ];

        // Try to find an exact match
        if (isset($sourceLogos[$source])) {
            return $sourceLogos[$source];
        }

        // Try to find a partial match
        foreach ($sourceLogos as $sourceName => $logo) {
            if (stripos($source, $sourceName) !== false || stripos($sourceName, $source) !== false) {
                return $logo;
            }
        }

        // Default to a generic financial news icon from a public URL
        return 'https://cdn-icons-png.flaticon.com/512/2965/2965879.png';
    }

    /**
     * Deduplicate news articles based on title similarity
     *
     * @param array $articles Array of news articles
     * @return array Deduplicated array of news articles
     */
    private function deduplicateNewsArticles(array $articles): array
    {
        if (count($articles) <= 1) {
            return $articles;
        }

        $dedupedArticles = [];
        $processedTitles = [];

        foreach ($articles as $article) {
            $title = $article['title'] ?? '';
            if (empty($title)) {
                continue;
            }

            // Normalize the title for comparison
            $normalizedTitle = $this->normalizeTitle($title);

            // Check if we've already seen a similar title
            $isDuplicate = false;
            foreach ($processedTitles as $existingTitle) {
                if ($this->areTitlesSimilar($normalizedTitle, $existingTitle)) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $dedupedArticles[] = $article;
                $processedTitles[] = $normalizedTitle;
            }
        }

        return $dedupedArticles;
    }

    /**
     * Normalize a title for comparison
     *
     * @param string $title The title to normalize
     * @return string The normalized title
     */
    private function normalizeTitle(string $title): string
    {
        // Convert to lowercase
        $title = strtolower($title);

        // Remove common prefixes like "Breaking: ", "Exclusive: ", etc.
        $prefixes = ['breaking:', 'exclusive:', 'just in:', 'update:', 'alert:'];
        foreach ($prefixes as $prefix) {
            if (strpos($title, $prefix) === 0) {
                $title = trim(substr($title, strlen($prefix)));
            }
        }

        // Remove punctuation and special characters
        $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title);

        // Remove extra whitespace
        $title = preg_replace('/\s+/', ' ', $title);

        return trim($title);
    }

    /**
     * Check if two titles are similar
     *
     * @param string $title1 First normalized title
     * @param string $title2 Second normalized title
     * @return bool True if titles are similar, false otherwise
     */
    private function areTitlesSimilar(string $title1, string $title2): bool
    {
        // If one title contains the other, they're similar
        if (strpos($title1, $title2) !== false || strpos($title2, $title1) !== false) {
            return true;
        }

        // Calculate similarity using Levenshtein distance
        $maxLength = max(strlen($title1), strlen($title2));
        if ($maxLength === 0) {
            return true; // Both empty strings
        }

        $levenshtein = levenshtein($title1, $title2);
        $similarity = 1 - ($levenshtein / $maxLength);

        // Titles are similar if they're at least 80% similar
        return $similarity >= 0.8;
    }
}
