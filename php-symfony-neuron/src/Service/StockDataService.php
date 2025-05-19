<?php

// src/Service/StockDataService.php
namespace App\Service;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Entity\FinancialData;
use App\Entity\StockPrice;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use App\Repository\InstitutionalOwnershipRepository; // Import the repository

class StockDataService
{
    private FinancialDataServiceInterface $financialDataService;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private InstitutionalOwnershipRepository $institutionalOwnershipRepository; // Add the property

    /**
     * Constructor
     */
    public function __construct(
        FinancialDataServiceInterface $financialDataService,
        EntityManagerInterface $entityManager,
        CacheInterface $cache,
        LoggerInterface $logger,
        InstitutionalOwnershipRepository $institutionalOwnershipRepository // Inject the repository
    ) {
        $this->financialDataService = $financialDataService;
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
        $this->institutionalOwnershipRepository = $institutionalOwnershipRepository; // Assign the repository
    }

    /**
     * Search for companies by name or ticker symbol
     * Implements case-insensitive contains search and deduplication
     */
    public function searchCompanies(string $term, int $limit = 25): array
    {
        $cacheKey = 'company_search_' . md5($term);

        // Add debug logging before cache call
        $this->logger->debug('Searching for companies with term', [
            'term' => $term,
            'cache_key' => $cacheKey,
            'limit' => $limit
        ]);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($term, $limit) {
            // Set a shorter cache time for failed searches
            $item->expiresAfter(300); // Cache for 5 minutes initially
            $this->logger->info('Cache miss for company search', ['term' => $term]);

            $results = [];
            $hasError = false;

            try {
                // Detailed logging of the API call about to be made
                $this->logger->debug('About to call financialDataService->searchCompanies', [
                    'term' => $term,
                    'service_class' => get_class($this->financialDataService)
                ]);

                // We need to use the APIs differently since searchCompanies is not in the interface
                // Try to find the company by symbol first (direct match)
                try {
                    $profile = $this->financialDataService->getCompanyProfile($term);

                    // If we found a profile, create a result in the expected format
                    if (is_array($profile) && !empty($profile['symbol'])) {
                        $results = [[
                            'symbol' => $profile['symbol'],
                            'name' => $profile['name'] ?? 'Unknown Company',
                            'exchange' => $profile['exchange'] ?? '',
                            'type' => 'EQUITY',
                            'sector' => $profile['sector'] ?? '',
                            'industry' => $profile['industry'] ?? '',
                            'currency' => $profile['currency'] ?? 'USD',
                            'description' => $profile['description'] ?? '',
                            'provider' => 'Profile API'
                        ]];

                        $this->logger->info('Found company profile for exact symbol match', [
                            'term' => $term,
                            'symbol' => $profile['symbol']
                        ]);
                    }
                } catch (\Exception $e) {
                    // Log but continue - this means it's not a direct symbol match
                    $this->logger->debug('No direct symbol match found', [
                        'term' => $term,
                        'error' => $e->getMessage()
                    ]);
                }

                // If we still don't have results, try our best to find similar matches
                // This part will need to be implemented by the actual API clients
                if (empty($results)) {
                    $this->logger->debug('No API search results found, no search method available', [
                        'term' => $term
                    ]);
                    $results = [];
                }

                // Log the results we found
                $this->logger->debug('External API search results', [
                    'is_array' => is_array($results),
                    'count' => is_array($results) ? count($results) : 'not_array',
                    'result_type' => gettype($results),
                    'sample' => is_array($results) && !empty($results) ? json_encode(array_slice($results, 0, 1)) : 'empty'
                ]);

                if (!empty($results)) {
                    $this->logger->info('Found external search results', ['count' => count($results)]);

                    // Set a longer cache time for successful results
                    $item->expiresAfter(3600); // Cache successful results for 1 hour

                    return $results;
                } else {
                    $this->logger->info('External API returned zero results', ['term' => $term]);
                    // Keep the shorter cache time for empty results
                }
            } catch (\Exception $e) {
                $hasError = true;
                $this->logger->error('Error searching companies via API', [
                    'term' => $term,
                    'error' => $e->getMessage(),
                    'exception_type' => get_class($e),
                    'trace' => $e->getTraceAsString()
                ]);

                // Set a very short cache time for errors
                $item->expiresAfter(60); // Only cache errors for 1 minute
            }

            // If we got here, either we had an error or we got empty results
            if ($hasError) {
                // For errors, add a flag indicating the error
                return [
                    'error' => true,
                    'message' => 'External search service temporarily unavailable',
                    'results' => []
                ];
            }

            // For legitimate empty results
            return $results;
        });
    }

    /**
     * Get a company profile by ticker symbol
     */
    public function getCompanyProfile(string $symbol): ?array
    {
        $cacheKey = 'company_profile_' . $symbol;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol) {
            $item->expiresAfter(86400); // Cache for 24 hours
            $this->logger->info('Cache miss for company profile', ['symbol' => $symbol]);
            try {
                $profile = $this->financialDataService->getCompanyProfile($symbol);
                return $profile;
            } catch (\Exception $e) {
                $this->logger->error('Error getting company profile: ' . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get current stock quote
     */
    public function getStockQuote(string $symbol): ?array
    {
        $cacheKey = 'stock_quote_' . $symbol;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol) {
            $item->expiresAfter(300); // Cache for 5 minutes
            $this->logger->info('Cache miss for stock quote', ['symbol' => $symbol]);
            try {
                $rawQuote = $this->financialDataService->getStockQuote($symbol);

                // Define a standard quote structure with default values
                $standardQuote = [
                    'symbol' => $symbol,
                    'price' => 0.0,
                    'change' => 0.0,
                    'changePercent' => 0.0,
                    'volume' => 0,
                    'latestTradingDay' => date('Y-m-d'),
                    'previousClose' => 0.0,
                    'open' => 0.0,
                    'high' => 0.0,
                    'low' => 0.0,
                    'marketCap' => 0.0,
                    'sharesOutstanding' => 0.0,
                    'fiftyTwoWeekHigh' => 0.0,
                    'fiftyTwoWeekLow' => 0.0,
                    'averageVolume' => 0,
                    'peRatio' => null, // Use null for N/A
                    'eps' => null,
                    'dividendYield' => null,
                    'beta' => null,
                    'timestamp' => time(), // Default to current time
                    'marketState' => 'UNKNOWN',
                ];

                // Merge raw quote data into the standard structure,
                // overwriting defaults with actual data if available.
                // Use array_merge to preserve numeric keys if any, though unlikely here.
                // Use a loop for more control over type casting and specific mapping if needed.
                $quote = $standardQuote;
                if (is_array($rawQuote)) {
                    foreach ($standardQuote as $key => $defaultValue) {
                        if (isset($rawQuote[$key])) {
                            // Attempt to cast to the default value's type
                            if (is_float($defaultValue)) {
                                $quote[$key] = (float) $rawQuote[$key];
                            } elseif (is_int($defaultValue)) {
                                $quote[$key] = (int) $rawQuote[$key];
                            } elseif (is_string($defaultValue) || is_null($defaultValue)) {
                                $quote[$key] = (string) $rawQuote[$key];
                            } else {
                                $quote[$key] = $rawQuote[$key]; // Fallback
                            }
                        }
                    }
                     // Ensure timestamp is an integer if provided as such
                    if (isset($rawQuote['timestamp'])) {
                         $quote['timestamp'] = (int) $rawQuote['timestamp'];
                    }
                     // Ensure latestTradingDay is a string
                    if (isset($rawQuote['latestTradingDay'])) {
                         $quote['latestTradingDay'] = (string) $rawQuote['latestTradingDay'];
                    }
                }


                // --- Step 3 Enhancement: Fetch and merge data from other endpoints ---
                // This part implements the optional enhancement to get more complete data.
                // It adds calls to getCompanyProfile and getFinancialData and merges them.

                try {
                    $profileData = $this->financialDataService->getCompanyProfile($symbol);
                    if (is_array($profileData)) {
                        // Merge relevant fields from profile data
                        $quote['marketCap'] = (float)($profileData['marketCap'] ?? $quote['marketCap']);
                        $quote['peRatio'] = isset($profileData['peRatio']) && $profileData['peRatio'] !== 0.0 ? (float)$profileData['peRatio'] : $quote['peRatio'];
                        $quote['dividendYield'] = isset($profileData['dividendYield']) && $profileData['dividendYield'] !== 0.0 ? (float)$profileData['dividendYield'] : $quote['dividendYield'];
                        $quote['beta'] = isset($profileData['beta']) && $profileData['beta'] !== 0.0 ? (float)$profileData['beta'] : $quote['beta'];
                        $quote['sharesOutstanding'] = (float)($profileData['sharesOutstanding'] ?? $quote['sharesOutstanding']); // Profile might have shares outstanding
                         // Yahoo Finance Client's getQuote already provides 52-week high/low and average volume
                         // If using AlphaVantage, these would still be defaults unless fetched separately.
                         // For now, rely on the primary getStockQuote client providing these or defaulting.
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Error merging profile data into quote: ' . $e->getMessage(), ['symbol' => $symbol]);
                    // Continue without profile data
                }

                try {
                    // Fetch recent financial data (e.g., quarterly) to get latest EPS
                    $financialData = $this->financialDataService->getFinancialData($symbol, 'quarterly');
                    if (!empty($financialData) && is_array($financialData[0])) {
                        // Use EPS from the most recent financial report
                        $quote['eps'] = isset($financialData[0]['eps']) && $financialData[0]['eps'] !== 0.0 ? (float)$financialData[0]['eps'] : $quote['eps'];
                         // Financials might also have marketCap, PE, etc. - prioritize quote/profile
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('Error merging financial data (EPS) into quote: ' . $e->getMessage(), ['symbol' => $symbol]);
                    // Continue without financial data
                }

                // End Step 3 Enhancement

                // Ensure essential fields are not null if they should be numeric
                $quote['price'] = (float)($quote['price'] ?? 0.0);
                $quote['change'] = (float)($quote['change'] ?? 0.0);
                $quote['changePercent'] = (float)($quote['changePercent'] ?? 0.0);
                $quote['volume'] = (int)($quote['volume'] ?? 0);
                $quote['marketCap'] = (float)($quote['marketCap'] ?? 0.0);
                $quote['sharesOutstanding'] = (float)($quote['sharesOutstanding'] ?? 0.0);
                $quote['fiftyTwoWeekHigh'] = (float)($quote['fiftyTwoWeekHigh'] ?? 0.0);
                $quote['fiftyTwoWeekLow'] = (float)($quote['fiftyTwoWeekLow'] ?? 0.0);
                $quote['averageVolume'] = (int)($quote['averageVolume'] ?? 0);
                 // PE, EPS, Dividend Yield, Beta can be null if data is not available

                return $quote;

            } catch (\Exception $e) {
                $this->logger->error('Error getting stock quote: ' . $e->getMessage());
                // Return a standardized empty quote structure on error
                 return [
                    'symbol' => $symbol,
                    'price' => 0.0,
                    'change' => 0.0,
                    'changePercent' => 0.0,
                    'volume' => 0,
                    'latestTradingDay' => date('Y-m-d'),
                    'previousClose' => 0.0,
                    'open' => 0.0,
                    'high' => 0.0,
                    'low' => 0.0,
                    'marketCap' => 0.0,
                    'sharesOutstanding' => 0.0,
                    'fiftyTwoWeekHigh' => 0.0,
                    'fiftyTwoWeekLow' => 0.0,
                    'averageVolume' => 0,
                    'peRatio' => null,
                    'eps' => null,
                    'dividendYield' => null,
                    'beta' => null,
                    'timestamp' => time(),
                    'marketState' => 'ERROR', // Indicate error state
                    'message' => 'Could not retrieve stock quote data.' // Add an error message
                ];
            }
        });
    }

    /**
     * Get financial data
     */
    public function getFinancialData(string $symbol, string $period = 'quarterly'): array
    {
        $cacheKey = 'financial_data_' . $symbol . '_' . $period;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol, $period) {
            $item->expiresAfter(86400); // Cache for 24 hours
            $this->logger->info('Cache miss for financial data', ['symbol' => $symbol, 'period' => $period]);
            try {
                $financials = $this->financialDataService->getFinancialData($symbol, $period);
                return $financials;
            } catch (\Exception $e) {
                $this->logger->error('Error getting financial data: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get general market news
     *
     * @param int $limit Maximum number of news items to return
     * @param bool $forceRefresh Whether to force a cache refresh
     * @return array Market news
     */
    public function getMarketNews(int $limit = 10, bool $forceRefresh = false): array
    {
        $cacheKey = 'market_news_' . $limit;

        // If force refresh is requested, delete the existing cache item
        if ($forceRefresh) {
            $this->cache->delete($cacheKey);
            $this->logger->info('Forced cache refresh for market news');
        }

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($limit) {
            // Cache market news for 5 minutes
            $item->expiresAfter(300);
            $this->logger->info('Cache miss for market news');

            try {
                // Call the new method in FinancialDataAggregatorService
                $news = $this->financialDataService->getMarketNews($limit);

                // Process and validate each article (reusing the existing logic)
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
                $this->logger->error('Error getting market news: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get company news
     *
     * @param string $symbol The stock symbol
     * @param int $limit Maximum number of news items to return
     * @return array Company news
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $cacheKey = 'company_news_' . $symbol . '_' . $limit;

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol, $limit) {
            // Set cache time to 10 minutes (600 seconds)
            $item->expiresAfter(600);
            $this->logger->info('Cache miss or expired for company news', ['symbol' => $symbol]);

            try {
                $news = $this->financialDataService->getCompanyNews($symbol, $limit);

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

    /**
     * Get company executives
     */
    public function getExecutives(string $symbol): array
    {
        $cacheKey = 'company_executives_' . $symbol;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol) {
            $item->expiresAfter(86400); // Cache for 24 hours
            $this->logger->info('Cache miss for executives', ['symbol' => $symbol]);
            try {
                $executives = $this->financialDataService->getExecutives($symbol);
                return $executives;
            } catch (\Exception $e) {
                $this->logger->error('Error getting company executives: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get historical stock prices
     *
     * @param string $symbol The stock symbol
     * @param string $interval The interval (daily, weekly, monthly)
     * @param string $outputSize The output size (compact, full)
     * @param bool $forceRefresh Whether to force a cache refresh
     * @return array Array of historical price data
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact', bool $forceRefresh = false): array
    {
        $cacheKey = 'historical_prices_' . $symbol . '_' . $interval . '_' . $outputSize;

        // If force refresh is requested, delete the existing cache item
        if ($forceRefresh) {
            $this->cache->delete($cacheKey);
            $this->logger->info('Forced cache refresh for historical prices', ['symbol' => $symbol]);
        }

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol, $interval, $outputSize) {
            // Reduce cache TTL significantly for more frequent data refreshes
            // Daily data: 5 minutes (300 seconds) during market hours, 1 hour otherwise
            // Weekly/Monthly: 1 hour and 6 hours respectively
            $now = new \DateTime();
            $isMarketHours = $this->isMarketHours($now);

            $cacheTtl = match($interval) {
                'daily' => $isMarketHours ? 300 : 3600,
                'weekly' => 3600,
                'monthly' => 21600,
                default => 300,
            };
            $item->expiresAfter($cacheTtl);
            $this->logger->info('Cache miss for historical prices', ['symbol' => $symbol, 'interval' => $interval]);
            try {
                $prices = $this->financialDataService->getHistoricalPrices($symbol, $interval, $outputSize);

                // Handle case where we get empty or invalid data
                if (empty($prices)) {
                    $this->logger->warning('Empty historical price data returned for ' . $symbol);

                    // Set a shorter cache time for empty results
                    $item->expiresAfter(60); // Only cache empty results for 1 minute

                    return [];
                }

                // Process the prices to ensure all required data points are present and valid
                $processedPrices = [];
                foreach ($prices as $price) {
                    // Skip any entries without a date or price
                    if (!isset($price['date']) || !isset($price['close'])) {
                        continue;
                    }

                    // Ensure all required fields are present, with fallbacks
                    $processedPrices[] = [
                        'date' => $price['date'],
                        'open' => isset($price['open']) ? (float)$price['open'] : (float)$price['close'],
                        'high' => isset($price['high']) ? (float)$price['high'] : (float)$price['close'],
                        'low' => isset($price['low']) ? (float)$price['low'] : (float)$price['close'],
                        'close' => (float)$price['close'],
                        'adjustedClose' => isset($price['adjustedClose']) ? (float)$price['adjustedClose'] : (float)$price['close'],
                        'volume' => isset($price['volume']) ? (int)$price['volume'] : 0,
                        'change' => isset($price['change']) ? (float)$price['change'] : 0,
                        'changePercent' => isset($price['changePercent']) ? (float)$price['changePercent'] : 0,
                        'dividend' => isset($price['dividend']) ? (float)$price['dividend'] : 0,
                        'split' => isset($price['split']) ? (float)$price['split'] : 1.0
                    ];
                }

                if (empty($processedPrices)) {
                    $this->logger->warning('No valid price data points after processing for ' . $symbol);
                    $item->expiresAfter(60); // Short cache time for invalid results
                    return [];
                }

                // Ensure consistent date ordering (oldest to newest) for all interval types
                usort($processedPrices, function($a, $b) {
                    return strtotime($a['date']) - strtotime($b['date']);
                });

                // Normalize historical prices based on splits
                $normalizedPrices = $this->normalizeHistoricalPrices($processedPrices);

                $this->logger->info('Returning historical prices', [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'count' => count($normalizedPrices)
                ]);

                return $normalizedPrices;
            } catch (\Exception $e) {
                $this->logger->error('Error getting historical prices: ' . $e->getMessage(), [
                    'symbol' => $symbol,
                    'interval' => $interval,
                    'exception' => get_class($e)
                ]);

                // Set a very short cache time for errors
                $item->expiresAfter(30);

                return [];
            }
        });
    }

    /**
     * Import a company and its data into the database
     */
    public function importCompany(string $symbol): Company
    {
        $profile = $this->getCompanyProfile($symbol);
        if (!$profile) {
            throw new \Exception('Could not find company profile for ' . $symbol);
        }

        $existingCompany = $this->entityManager->getRepository(Company::class)
            ->findOneBy(['tickerSymbol' => $symbol]);

        if ($existingCompany) {
            $this->logger->info('Company already exists, updating: ' . $symbol);
            $company = $existingCompany;
        } else {
            $this->logger->info('Creating new company: ' . $symbol);
            $company = new Company();
            $company->setTickerSymbol($symbol);
            $company->setCreatedAt(new \DateTimeImmutable());
        }

        $company->setName($profile['name'] ?? $symbol);
        $company->setDescription($profile['description'] ?? null);
        $company->setSector($profile['sector'] ?? null);
        $company->setIndustry($profile['industry'] ?? null);
        $company->setHeadquarters(
            ($profile['address'] ?? '') . ', ' .
            ($profile['city'] ?? '') . ', ' .
            ($profile['state'] ?? '') . ', ' .
            ($profile['country'] ?? '')
        );
        $company->setWebsite($profile['website'] ?? null);
        $company->setUpdatedAt(new \DateTimeImmutable());

        // First persist the company to ensure it has an ID before creating related entities
        $this->entityManager->persist($company);
        $this->entityManager->flush();

        // Now import related data
        $this->importFinancialData($company);
        $this->importExecutiveProfiles($company);

        // Persist these changes before moving to historical prices (which can be more numerous)
        $this->entityManager->flush();

        // Import historical price data
        $this->importHistoricalPrices($company, 'daily', 100);
        $this->importHistoricalPrices($company, 'weekly', 52);

        // Final flush to ensure all data is saved
        $this->entityManager->flush();

        return $company;
    }

    /**
     * Import financial data for a company
     */
    public function importFinancialData(Company $company): void
    {
        $financials = $this->getFinancialData($company->getTickerSymbol());
        if (empty($financials)) {
            $this->logger->warning('No financial data available for ' . $company->getTickerSymbol());
            return;
        }

        $quote = $this->getStockQuote($company->getTickerSymbol());

        $existingPeriods = [];
        foreach ($company->getFinancialData() as $data) {
            $key = ($data->getFiscalQuarter() ?? 'A') . '-' . $data->getFiscalYear();
            $existingPeriods[$key] = $data;
        }

        foreach ($financials as $financial) {
            $quarter = $financial['fiscalQuarter'] ?? 'A'; // Use 'A' for Annual if no quarter
            $year = $financial['fiscalYear'] ?? null;
            if (!$year) continue; // Skip if no year
            $key = $quarter . '-' . $year;

            $data = $existingPeriods[$key] ?? null;
            if (!$data) {
                $data = new FinancialData();
                $data->setCompany($company);
                $data->setFiscalQuarter($quarter !== 'A' ? $quarter : null);
                $data->setFiscalYear($year);
                $data->setCreatedAt(new \DateTimeImmutable());
                $this->logger->info('Creating financial data for ' . $company->getTickerSymbol() . ': ' . $key);
            } else {
                 $this->logger->info('Updating financial data for ' . $company->getTickerSymbol() . ': ' . $key);
            }

            $data->setReportType($quarter !== 'A' ? '10-Q' : '10-K');
            if (isset($financial['reportDate'])) {
                try { $data->setReportDate(new \DateTime($financial['reportDate'])); } catch (\Exception $e) {}
            }

            $data->setRevenue($financial['revenue'] ?? 0);
            $data->setNetIncome($financial['netIncome'] ?? 0);
            $data->setEps($financial['eps'] ?? 0);
            $data->setEbitda($financial['ebitda'] ?? 0);
            // Add other fields if available in $financial array
            // $data->setTotalAssets($financial['totalAssets'] ?? 0);
            // $data->setTotalLiabilities($financial['totalLiabilities'] ?? 0);

            $revenue = $data->getRevenue();
            $netIncome = $data->getNetIncome();
            if ($revenue && $revenue != 0) {
                $data->setProfitMargin($netIncome / $revenue);
            } else {
                $data->setProfitMargin(0);
            }

            if ($quote && $key === ($financials[0]['fiscalQuarter'] ?? 'A') . '-' . ($financials[0]['fiscalYear'] ?? null)) {
                // $data->setMarketCap($quote['marketCap'] ?? 0);
                // if (isset($quote['price']) && $data->getEps() != 0) {
                //     $data->setPeRatio($quote['price'] / $data->getEps());
                // } else {
                //     $data->setPeRatio(0);
                // }
            }
            $data->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($data);
        }
    }

    /**
     * Import executive profiles for a company
     */
    public function importExecutiveProfiles(Company $company): void
    {
        $executives = $this->getExecutives($company->getTickerSymbol());
        if (empty($executives)) {
            $this->logger->warning('No executive data available for ' . $company->getTickerSymbol());
            return;
        }

        $existingExecutives = [];
        foreach ($company->getExecutiveProfiles() as $profile) {
            $existingExecutives[$profile->getName()] = $profile;
        }

        foreach ($executives as $executive) {
            if (!isset($executive['name'])) continue; // Skip if no name

            $profile = $existingExecutives[$executive['name']] ?? null;
            if (!$profile) {
                $profile = new ExecutiveProfile();
                $profile->setCompany($company);
                $profile->setName($executive['name']);
                $profile->setCreatedAt(new \DateTimeImmutable());
                $this->logger->info('Creating executive profile for ' . $executive['name']);
            } else {
                 $this->logger->info('Updating executive profile for ' . $executive['name']);
            }

            $profile->setTitle($executive['title'] ?? null);
            $profile->setBiography($executive['bio'] ?? null);
            $profile->setEducation($executive['education'] ?? null);
            $profile->setPreviousCompanies($executive['previousCompanies'] ?? null);
            $profile->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($profile);
        }
    }

    /**
     * Get analyst ratings
     */
    public function getAnalystRatings(string $symbol): array
    {
        $cacheKey = 'analyst_ratings_' . $symbol;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol) {
            $item->expiresAfter(21600); // Cache for 6 hours
            $this->logger->info('Cache miss for analyst ratings', ['symbol' => $symbol]);

            try {
                $ratings = $this->financialDataService->getAnalystRatings($symbol);
                return $ratings;
            } catch (\Exception $e) {
                $this->logger->error('Error getting analyst ratings: ' . $e->getMessage());

                // Return an empty structure with a generic message
                return [
                    'ratings' => [],
                    'consensus' => [
                        'consensusRating' => 'N/A',
                        'averagePriceTarget' => 0,
                        'lowPriceTarget' => 0,
                        'highPriceTarget' => 0,
                        'buy' => 0,
                        'hold' => 0,
                        'sell' => 0,
                        'upside' => 0
                    ],
                    'message' => 'Analyst ratings are currently unavailable. Please try again later.'
                ];
            }
        });
    }

    /**
     * Get insider trading data
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array
    {
        $cacheKey = 'insider_trading_' . $symbol . '_' . $limit;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol, $limit) {
            $item->expiresAfter(86400); // Cache for 1 day
            $this->logger->info('Cache miss for insider trading', ['symbol' => $symbol]);

            try {
                $insiderData = $this->financialDataService->getInsiderTrading($symbol, $limit);
                return $insiderData;
            } catch (\Exception $e) {
                $this->logger->error('Error getting insider trading data: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Get institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        $cacheKey = 'institutional_ownership_' . $symbol . '_' . $limit;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol, $limit) {
            $item->expiresAfter(604800); // Cache for 1 week
            $this->logger->info('Cache miss for institutional ownership', ['symbol' => $symbol]);
            try {
                return $this->financialDataService->getInstitutionalOwnership($symbol, $limit);
            } catch (\Exception $e) {
                $this->logger->error('Error getting institutional ownership data: ' . $e->getMessage());
                return [];
            }
        });
    }

    /**
     * Calculate consensus data from analyst ratings
     */
    public function getAnalystConsensus(string $symbol): array
    {
        $ratingsData = $this->getAnalystRatings($symbol);

        if (empty($ratingsData) || empty($ratingsData['ratings'])) {
            return [
                'symbol' => $symbol,
                'consensus' => 'N/A',
                'averagePriceTarget' => 0,
                'lowPriceTarget' => 0,
                'highPriceTarget' => 0,
                'ratings' => ['buy' => 0, 'hold' => 0, 'sell' => 0],
                'upside' => 0,
                'ratings_count' => 0,
                'dataAvailable' => false
            ];
        }

        return [
            'symbol' => $symbol,
            'consensus' => $ratingsData['consensus']['consensusRating'] ?? 'N/A',
            'averagePriceTarget' => $ratingsData['consensus']['averagePriceTarget'] ?? 0,
            'lowPriceTarget' => $ratingsData['consensus']['lowPriceTarget'] ?? 0,
            'highPriceTarget' => $ratingsData['consensus']['highPriceTarget'] ?? 0,
            'ratings' => [
                'buy' => $ratingsData['consensus']['buy'] ?? 0,
                'hold' => $ratingsData['consensus']['hold'] ?? 0,
                'sell' => $ratingsData['consensus']['sell'] ?? 0
            ],
            'upside' => $ratingsData['consensus']['upside'] ?? 0,
            'ratings_count' => count($ratingsData['ratings'] ?? []),
            'dataAvailable' => true
        ];
    }

    /**
     * Calculate total institutional ownership percentage
     *
     * @param Company $company The company entity
     * @return float Total institutional ownership percentage
     */
    public function calculateTotalInstitutionalOwnership(Company $company): float
    {
        $owners = $this->institutionalOwnershipRepository->findByCompany($company);

        $totalPercentage = 0;
        foreach ($owners as $owner) {
            $totalPercentage += $owner->getPercentageOwned();
        }

        // Cap at 100% (sometimes there can be double counting in the data)
        return min($totalPercentage, 100);
    }

    /**
     * Check if current time is during market hours (9:30 AM - 4:00 PM ET, Monday-Friday)
     */
    private function isMarketHours(\DateTime $now): bool
    {
        // Convert to Eastern Time (ET) where US markets operate
        $easternTz = new \DateTimeZone('America/New_York');
        $nowEastern = clone $now;
        $nowEastern->setTimezone($easternTz);

        // Get day of week (0 = Sunday, 6 = Saturday)
        $dayOfWeek = (int)$nowEastern->format('w');

        // Check if it's a weekday
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return false;
        }

        // Get current hour and minute in ET
        $hour = (int)$nowEastern->format('G');
        $minute = (int)$nowEastern->format('i');
        $timeInMinutes = ($hour * 60) + $minute;

        // Market hours: 9:30 AM - 4:00 PM ET
        $marketOpen = (9 * 60) + 30;  // 9:30 AM
        $marketClose = (16 * 60);     // 4:00 PM

        return $timeInMinutes >= $marketOpen && $timeInMinutes <= $marketClose;
    }

    /**
     * Normalize historical prices based on stock splits
     *
     * This method adjusts historical prices to account for stock splits,
     * ensuring that price charts are correctly displayed over long time periods.
     *
     * @param array $prices Array of price data with split coefficients
     * @return array Normalized price data
     */
    private function normalizeHistoricalPrices(array $prices): array
    {
        // Sort prices chronologically (oldest first)
        usort($prices, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        // Collect all split events
        $splitEvents = [];
        foreach ($prices as $index => $price) {
            if (isset($price['split']) && $price['split'] !== 1.0) {
                $splitEvents[] = [
                    'date' => $price['date'],
                    'index' => $index,
                    'ratio' => $price['split']
                ];
            }
        }

        // Apply splits
        $normalizedPrices = $prices;
        foreach ($splitEvents as $split) {
            $splitRatio = $split['ratio'];
            for ($i = 0; $i < $split['index']; $i++) {
                $normalizedPrices[$i]['open'] *= $splitRatio;
                $normalizedPrices[$i]['high'] *= $splitRatio;
                $normalizedPrices[$i]['low'] *= $splitRatio;
                $normalizedPrices[$i]['close'] *= $splitRatio;
                $normalizedPrices[$i]['adjustedClose'] *= $splitRatio;
                $normalizedPrices[$i]['volume'] /= $splitRatio;
            }
        }

        return $normalizedPrices;
    }

    /**
     * Import historical price data for a company
     *
     * @param Company $company The company entity
     * @param string $interval The interval (daily, weekly, monthly)
     * @param int $limit The maximum number of data points to import
     */
    public function importHistoricalPrices(Company $company, string $interval = 'daily', int $limit = 100): void
    {
        $prices = $this->getHistoricalPrices($company->getTickerSymbol(), $interval, 'full'); // Fetch full history to ensure accurate normalization

        if (empty($prices)) {
            $this->logger->warning('No historical price data available for ' . $company->getTickerSymbol() . ' (' . $interval . ')');
            return;
        }

        // Normalize prices based on splits
        $normalizedPrices = $this->normalizeHistoricalPrices($prices);

        // Get existing prices for this company and interval
        $existingPrices = $this->entityManager->getRepository(StockPrice::class)
            ->findBy(['company' => $company, 'interval' => $interval]);

        $existingDates = [];
        foreach ($existingPrices as $price) {
            $existingDates[$price->getDate()->format('Y-m-d')] = $price;
        }

        $importedCount = 0;
        // Iterate through normalized prices (newest first for easier limiting)
        $normalizedPrices = array_reverse($normalizedPrices);
        foreach ($normalizedPrices as $priceData) {
            if ($importedCount >= $limit) break;

            $dateString = $priceData['date'];
            $date = new \DateTime($dateString);

            $price = $existingDates[$dateString] ?? null;
            if (!$price) {
                $price = new StockPrice();
                $price->setCompany($company);
                $price->setPeriod($interval);
                $price->setDate($date);
                $price->setCreatedAt(new \DateTimeImmutable());
                $this->logger->info('Creating stock price for ' . $company->getTickerSymbol() . ' (' . $interval . '): ' . $dateString);
            } else {
                 $this->logger->info('Updating stock price for ' . $company->getTickerSymbol() . ' (' . $interval . '): ' . $dateString);
            }

            $price->setOpen($priceData['open'] ?? 0);
            $price->setHigh($priceData['high'] ?? 0);
            $price->setLow($priceData['low'] ?? 0);
            $price->setClose($priceData['close'] ?? 0);
            $price->setAdjustedClose($priceData['adjustedClose'] ?? $priceData['close'] ?? 0);
            $price->setVolume($priceData['volume'] ?? 0);
            $price->setChangeValue($priceData['change'] ?? 0);
            $price->setChangePercent($priceData['changePercent'] ?? 0);
            $price->setDividend($priceData['dividend'] ?? 0);
            $price->setSplit($priceData['split'] ?? 1.0);
            $price->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($price);
            $importedCount++;
        }
    }
}
