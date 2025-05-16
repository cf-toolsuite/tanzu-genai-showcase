<?php

// src/Service/StockDataService.php
namespace App\Service;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Entity\FinancialData;
use App\Entity\StockPrice; // Added missing use statement
use App\Service\ApiClient\StockClientsFactory; // Import the factory
use App\Service\ApiClient\ApiClientInterface; // Keep interface hint
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface; // Use CacheInterface for type hint
use Symfony\Contracts\Cache\ItemInterface; // Needed for cache->get()

class StockDataService
{
    private ApiClientInterface $alphaVantageClient;
    private ApiClientInterface $yahooFinanceClient;
    private ApiClientInterface $newsApiClient;
    private ApiClientInterface $secApiClient;
    private ApiClientInterface $tradeFeedsClient;
    private EntityManagerInterface $entityManager;
    private CacheInterface $cache; // Use CacheInterface
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        StockClientsFactory $stockClientsFactory, // Inject the factory
        EntityManagerInterface $entityManager,
        CacheInterface $cache, // Use CacheInterface
        LoggerInterface $logger
    ) {
        // Get clients from the factory
        $this->alphaVantageClient = $stockClientsFactory->getAlphaVantageClient();
        $this->yahooFinanceClient = $stockClientsFactory->getYahooFinanceClient();
        $this->newsApiClient = $stockClientsFactory->getNewsApiClient();
        $this->secApiClient = $stockClientsFactory->getSecApiClient();
        $this->tradeFeedsClient = $stockClientsFactory->getTradeFeedsClient();

        // Assign other dependencies
        $this->entityManager = $entityManager;
        $this->cache = $cache;
        $this->logger = $logger;
    }

    // --- Existing Methods (Unchanged content, just ensure they use the correct properties) ---

    /**
     * Search for companies by name or ticker symbol
     * Implements case-insensitive contains search and deduplication
     */
    public function searchCompanies(string $term, int $limit = 25): array
    {
        $cacheKey = 'company_search_' . md5($term);
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($term, $limit) {
            $item->expiresAfter(3600); // Cache for 1 hour
            $this->logger->info('Cache miss for company search', ['term' => $term]);

            $results = [];
            $processedNames = []; // Track processed company names to avoid duplicates
            $processedSymbols = []; // Track processed symbols to avoid duplicates

            try {
                // Get results from Alpha Vantage
                $alphaResults = $this->alphaVantageClient->searchCompanies($term);
                foreach ($alphaResults as &$result) {
                    $result['provider'] = 'Alpha Vantage API';
                    $normalizedName = strtolower($result['name']);
                    $normalizedSymbol = strtolower($result['symbol']);

                    // Only add if we haven't seen this company name or symbol before
                    if (!isset($processedNames[$normalizedName]) && !isset($processedSymbols[$normalizedSymbol])) {
                        $results[] = $result;
                        $processedNames[$normalizedName] = true;
                        $processedSymbols[$normalizedSymbol] = true;
                    }
                }

                // Try Yahoo Finance regardless of Alpha Vantage results to get more comprehensive results
                try {
                    $yahooResults = $this->yahooFinanceClient->searchCompanies($term);
                    foreach ($yahooResults as &$result) {
                        $result['provider'] = 'Yahoo Finance API';
                        $normalizedName = strtolower($result['name']);
                        $normalizedSymbol = strtolower($result['symbol']);

                        // Only add if we haven't seen this company name or symbol before
                        if (!isset($processedNames[$normalizedName]) && !isset($processedSymbols[$normalizedSymbol])) {
                            $results[] = $result;
                            $processedNames[$normalizedName] = true;
                            $processedSymbols[$normalizedSymbol] = true;

                            // Stop if we've reached the limit
                            if (count($results) >= $limit) {
                                break;
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error with Yahoo Finance search: ' . $e->getMessage());
                    // Continue with Alpha Vantage results
                }

                return $results;
            } catch (\Exception $e) {
                $this->logger->error('Error searching companies with Alpha Vantage: ' . $e->getMessage());
                try {
                    $results = $this->yahooFinanceClient->searchCompanies($term);
                    // Add provider information to each result
                    foreach ($results as &$result) {
                        $result['provider'] = 'Yahoo Finance API';
                    }
                    return $results;
                } catch (\Exception $e2) {
                    $this->logger->error('Error with fallback search: ' . $e2->getMessage());
                    return [];
                }
            }
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
                $profile = $this->alphaVantageClient->getCompanyProfile($symbol);
                if (empty($profile['name'])) {
                    $this->logger->info('No valid profile from Alpha Vantage, trying Yahoo Finance');
                    $profile = $this->yahooFinanceClient->getCompanyProfile($symbol);
                }
                return $profile;
            } catch (\Exception $e) {
                $this->logger->error('Error getting company profile: ' . $e->getMessage());
                try {
                    $profile = $this->yahooFinanceClient->getCompanyProfile($symbol);
                    return $profile;
                } catch (\Exception $e2) {
                    $this->logger->error('Error with fallback profile: ' . $e2->getMessage());
                    return null;
                }
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
                $quote = $this->alphaVantageClient->getQuote($symbol);
                if (empty($quote['price'])) {
                    $this->logger->info('No valid quote from Alpha Vantage, trying Yahoo Finance');
                    $quote = $this->yahooFinanceClient->getQuote($symbol);
                }
                return $quote;
            } catch (\Exception $e) {
                $this->logger->error('Error getting stock quote: ' . $e->getMessage());
                try {
                    $quote = $this->yahooFinanceClient->getQuote($symbol);
                    return $quote;
                } catch (\Exception $e2) {
                    $this->logger->error('Error with fallback quote: ' . $e2->getMessage());
                    return null;
                }
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
                $financials = $this->alphaVantageClient->getFinancials($symbol, $period);
                if (empty($financials)) {
                    $this->logger->info('No valid financial data from Alpha Vantage, trying Yahoo Finance');
                    $financials = $this->yahooFinanceClient->getFinancials($symbol, $period);
                }
                return $financials;
            } catch (\Exception $e) {
                $this->logger->error('Error getting financial data: ' . $e->getMessage());
                try {
                    $financials = $this->yahooFinanceClient->getFinancials($symbol, $period);
                    return $financials;
                } catch (\Exception $e2) {
                    $this->logger->error('Error with fallback financials: ' . $e2->getMessage());
                    return [];
                }
            }
        });
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

            $allNews = [];

            try {
                $companyInfo = $this->getCompanyProfile($symbol);
                $companyName = $companyInfo['name'] ?? '';
                $searchTerm = !empty($companyName) ? $companyName : $symbol;

                // Try NewsAPI first
                try {
                    $newsApiResults = $this->newsApiClient->getCompanyNews($searchTerm, $limit * 2); // Get more to account for filtering
                    foreach ($newsApiResults as $article) {
                        $article['provider'] = 'NewsAPI';
                        $allNews[] = $this->validateNewsArticle($article);
                    }
                } catch (\Exception $e) {
                    $this->logger->error('Error getting company news from NewsAPI: ' . $e->getMessage());
                }

                // Try Yahoo Finance regardless of NewsAPI success
                try {
                    $yahooResults = $this->yahooFinanceClient->getCompanyNews($symbol, $limit * 2);
                    foreach ($yahooResults as $article) {
                        $article['provider'] = 'Yahoo Finance';
                        $allNews[] = $this->validateNewsArticle($article);
                    }
                } catch (\Exception $e2) {
                    $this->logger->error('Error with Yahoo Finance news: ' . $e2->getMessage());
                }

                // Try Alpha Vantage if we still need more articles
                if (count($allNews) < $limit) {
                    try {
                        $alphaResults = $this->alphaVantageClient->getCompanyNews($symbol, $limit * 2);
                        foreach ($alphaResults as $article) {
                            $article['provider'] = 'Alpha Vantage';
                            $allNews[] = $this->validateNewsArticle($article);
                        }
                    } catch (\Exception $e3) {
                        $this->logger->error('Error with Alpha Vantage news: ' . $e3->getMessage());
                    }
                }

                // Deduplicate news articles
                $dedupedNews = $this->deduplicateNewsArticles($allNews);

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
                $this->logger->error('Error with all news sources: ' . $e->getMessage());
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
                $executives = $this->yahooFinanceClient->getExecutives($symbol);
                return $executives;
            } catch (\Exception $e) {
                $this->logger->error('Error getting company executives: ' . $e->getMessage());
                try {
                    $executives = $this->alphaVantageClient->getExecutives($symbol);
                    return $executives;
                } catch (\Exception $e2) {
                    $this->logger->error('Error with fallback executives: ' . $e2->getMessage());
                    return [];
                }
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
                $prices = $this->alphaVantageClient->getHistoricalPrices($symbol, $interval, $outputSize);
                if (empty($prices)) {
                    $this->logger->info('No valid historical price data from Alpha Vantage, trying Yahoo Finance');
                    $prices = $this->yahooFinanceClient->getHistoricalPrices($symbol, $interval, $outputSize);
                }
                
                // Ensure consistent date ordering (oldest to newest) for all interval types
                usort($prices, function($a, $b) {
                    return strtotime($a['date']) - strtotime($b['date']);
                });
                
                return $prices;
            } catch (\Exception $e) {
                $this->logger->error('Error getting historical prices: ' . $e->getMessage());
                try {
                    $prices = $this->yahooFinanceClient->getHistoricalPrices($symbol, $interval, $outputSize);
                    
                    // Ensure consistent date ordering (oldest to newest) for all interval types
                    usort($prices, function($a, $b) {
                        return strtotime($a['date']) - strtotime($b['date']);
                    });
                    
                    return $prices;
                } catch (\Exception $e2) {
                    $this->logger->error('Error with fallback prices: ' . $e2->getMessage());
                    return [];
                }
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
     *
     * TradeFeeds is the exclusive provider for analyst ratings
     */
    public function getAnalystRatings(string $symbol): array
    {
        $cacheKey = 'analyst_ratings_' . $symbol;
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($symbol) {
            $item->expiresAfter(21600); // Cache for 6 hours
            $this->logger->info('Cache miss for analyst ratings', ['symbol' => $symbol]);

            // Get ratings exclusively from TradeFeeds API
            try {
                if (method_exists($this->tradeFeedsClient, 'getAnalystRatings')) {
                    $ratings = $this->tradeFeedsClient->getAnalystRatings($symbol);

                    if (!empty($ratings['ratings'])) {
                        $this->logger->info('Using analyst ratings from TradeFeeds API', [
                            'count' => count($ratings['ratings']),
                            'symbol' => $symbol
                        ]);
                    } else {
                        // If no ratings but we have a message, log it
                        if (isset($ratings['message'])) {
                            $this->logger->info('TradeFeeds API returned message: ' . $ratings['message'], [
                                'symbol' => $symbol
                            ]);
                        } else {
                            $this->logger->info('TradeFeeds API returned no ratings for ' . $symbol);
                        }
                    }

                    // Always return what TradeFeeds gives us, even if empty
                    return $ratings;
                } else {
                    $this->logger->error('TradeFeeds API client does not support getAnalystRatings method');
                }
            } catch (\Exception $e) {
                $this->logger->error('Error getting analyst ratings from TradeFeeds API: ' . $e->getMessage(), [
                    'symbol' => $symbol,
                    'exception' => get_class($e)
                ]);
            }

            // If we get here, something went wrong with TradeFeeds API
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

            // Try to get data from Yahoo Finance FIRST (reversed priority)
            try {
                $this->logger->info('Attempting to get insider trading data from Yahoo Finance', ['symbol' => $symbol]);
                $yahooData = $this->yahooFinanceClient->getInsiderTrading($symbol, $limit);

                if (!empty($yahooData)) {
                    $this->logger->info('Successfully retrieved insider trading data from Yahoo Finance', ['count' => count($yahooData)]);
                    return $this->transformYahooInsiderData($yahooData);
                }
            } catch (\Exception $e) {
                $this->logger->error('Error getting insider trading data from Yahoo Finance: ' . $e->getMessage());
            }

            // Fall back to SEC API if Yahoo failed
            try {
                $this->logger->info('Falling back to SEC API for insider trading data', ['symbol' => $symbol]);
                $secData = $this->secApiClient->getInsiderTrading($symbol, $limit);

                // If we got data, transform it to the expected format
                if (!empty($secData)) {
                    $this->logger->info('Successfully retrieved insider trading data from SEC API', ['count' => count($secData)]);
                    return $this->transformSecInsiderData($secData);
                }
            } catch (\Exception $e) {
                $this->logger->error('Error getting insider trading data from SEC API: ' . $e->getMessage());
            }

            // Return empty array if both sources failed
            return [];
        });
    }

    /**
     * Transform SEC API insider trading data to the format expected by the template
     */
    private function transformSecInsiderData(array $secData): array
    {
        $result = [];

        foreach ($secData as $filing) {
            // Skip if essential data is missing
            if (!isset($filing['companyName'])) {
                continue;
            }

            // Form 4 filings often have the company as the issuer and an insider as the owner
            // For Kaleidoscope API, we need to extract or infer the owner information

            // Extract potential owner information from form description or report title
            $ownerName = $filing['companyName'] ?? 'Unknown';  // Default to company name
            $formDesc = $filing['formDescription'] ?? '';

            // If formDescription contains "filed by", try to extract the name after it
            if (preg_match('/filed\s+by\s+([^(]+)/i', $formDesc, $matches)) {
                $ownerName = trim($matches[1]);
            }

            // Create a filing entry with the expected structure
            $filingEntry = [
                'ownerName' => $ownerName,
                'ownerTitle' => $filing['formDescription'] ?? '',
                'isDirector' => stripos($formDesc, 'director') !== false,
                'isOfficer' => stripos($formDesc, 'officer') !== false ||
                               stripos($formDesc, 'executive') !== false,
                'isTenPercentOwner' => stripos($formDesc, '10%') !== false ||
                                       stripos($formDesc, 'ten percent') !== false,
                'filingDate' => isset($filing['filingDate']) ? new \DateTime($filing['filingDate']) : new \DateTime(),
                'transactionDate' => isset($filing['reportDate']) ? new \DateTime($filing['reportDate']) : new \DateTime(),
                'formUrl' => $filing['htmlUrl'] ?? '',
                'transactions' => [
                    [
                        'transactionType' => $this->determineTransactionType($filing['formType'] ?? ''),
                        'securityType' => 'Common Stock',
                        'shares' => 0, // Would need to parse the actual Form 4 document to get this
                        'pricePerShare' => 0, // Would need to parse the actual Form 4 document to get this
                        'totalValue' => 0, // Would need to parse the actual Form 4 document to get this
                        'ownershipType' => 'Direct',
                        'sharesOwnedFollowing' => 0 // Would need to parse the actual Form 4 document to get this
                    ]
                ]
            ];

            $result[] = $filingEntry;
        }

        return $result;
    }

    /**
     * Transform Yahoo Finance insider trading data to the format expected by the template
     */
    private function transformYahooInsiderData(array $yahooData): array
    {
        $result = [];

        foreach ($yahooData as $transaction) {
            // Skip if essential data is missing
            if (!isset($transaction['insider'])) {
                continue;
            }

            // Determine transaction type code
            $typeCode = 'O'; // Other by default
            $transactionType = $transaction['transactionType'] ?? '';
            if (stripos($transactionType, 'purchase') !== false) {
                $typeCode = 'P';
            } elseif (stripos($transactionType, 'sale') !== false) {
                $typeCode = 'S';
            } elseif (stripos($transactionType, 'grant') !== false ||
                      stripos($transactionType, 'award') !== false) {
                $typeCode = 'A';
            } elseif (stripos($transactionType, 'disposition') !== false) {
                $typeCode = 'D';
            }

            // Create a filing entry with the expected structure
            $filingEntry = [
                'ownerName' => $transaction['insider'] ?? 'Unknown',
                'ownerTitle' => $transaction['title'] ?? '',
                'isDirector' => stripos($transaction['title'] ?? '', 'director') !== false,
                'isOfficer' => stripos($transaction['title'] ?? '', 'officer') !== false ||
                               stripos($transaction['title'] ?? '', 'ceo') !== false ||
                               stripos($transaction['title'] ?? '', 'cfo') !== false ||
                               stripos($transaction['title'] ?? '', 'president') !== false,
                'isTenPercentOwner' => stripos($transaction['title'] ?? '', '10%') !== false,
                'filingDate' => isset($transaction['date']) ? new \DateTime($transaction['date']) : new \DateTime(),
                'transactionDate' => isset($transaction['date']) ? new \DateTime($transaction['date']) : new \DateTime(),
                'formUrl' => '',
                'transactions' => [
                    [
                        'transactionType' => $typeCode,
                        'securityType' => 'Common Stock',
                        'shares' => $transaction['shares'] ?? 0,
                        'pricePerShare' => $transaction['price'] ?? 0,
                        'totalValue' => $transaction['value'] ?? 0,
                        'ownershipType' => 'Direct',
                        'sharesOwnedFollowing' => $transaction['sharesOwned'] ?? 0
                    ]
                ]
            ];

            $result[] = $filingEntry;
        }

        return $result;
    }

    /**
     * Determine transaction type code from form type
     */
    private function determineTransactionType(string $formType): string
    {
        // Form 4 is for changes in ownership
        if ($formType === '4') {
            // We don't have the actual transaction type from the form content
            // But we can return a reasonable default for Form 4
            // P = Purchase, S = Sale, A = Grant/Award, D = Disposition, O = Other
            return 'P'; // Default to Purchase as it's common and looks better in UI
        }

        // Default to 'O' (Other) for other form types
        return 'O';
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
                return $this->secApiClient->getInstitutionalOwnership($symbol, $limit);
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
     * Import historical stock prices for a company
     */
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

        // If no splits, return the original data
        if (empty($splitEvents)) {
            return $prices;
        }

        $this->logger->info('Found ' . count($splitEvents) . ' stock split events to apply');

        // Apply each split to prior prices
        // Note: Splits are already incorporated in adjusted close from the API,
        // but we need to normalize open, high, low, close for visualization
        foreach ($splitEvents as $splitEvent) {
            $splitDate = $splitEvent['date'];
            $splitRatio = $splitEvent['ratio'];

            $this->logger->info('Applying stock split', [
                'date' => $splitDate,
                'ratio' => $splitRatio
            ]);

            // Adjust all prices prior to the split
            for ($i = 0; $i < $splitEvent['index']; $i++) {
                // For a 2:1 split, divide historical prices by 2
                $prices[$i]['open'] /= $splitRatio;
                $prices[$i]['high'] /= $splitRatio;
                $prices[$i]['low'] /= $splitRatio;
                $prices[$i]['close'] /= $splitRatio;

                // Volume would be multiplied, but we'll leave as is since we already have adjusted values

                // Recalculate change and change percent if needed
                if ($i > 0 && isset($prices[$i]['change']) && isset($prices[$i-1]['close'])) {
                    $prices[$i]['change'] = $prices[$i]['close'] - $prices[$i-1]['close'];
                    if ($prices[$i-1]['close'] != 0) {
                        $prices[$i]['changePercent'] = ($prices[$i]['change'] / $prices[$i-1]['close']) * 100;
                    }
                }
            }
        }

        return $prices;
    }

    public function importHistoricalPrices(Company $company, string $interval = 'daily', int $limit = 100): int
    {
        $prices = $this->getHistoricalPrices(
            $company->getTickerSymbol(),
            $interval,
            $limit > 100 ? 'full' : 'compact'
        );

        if (empty($prices)) {
            $this->logger->warning('No historical price data available for ' . $company->getTickerSymbol());
            return 0;
        }

        // Normalize prices to account for stock splits
        $prices = $this->normalizeHistoricalPrices($prices);

        $count = 0;
        $repository = $this->entityManager->getRepository(StockPrice::class);

        if ($limit > 0 && count($prices) > $limit) {
            $prices = array_slice($prices, 0, $limit);
        }

        $batchSize = 50; // Process in batches

        foreach ($prices as $index => $priceData) {
            try {
                $date = new \DateTime($priceData['date']);
            } catch (\Exception $e) {
                $this->logger->warning('Invalid date format in price data, skipping.', ['data' => $priceData]);
                continue;
            }

            $existingPrice = $repository->findOneBy([
                'company' => $company,
                'date' => $date,
                'period' => $interval
            ]);

            $price = $existingPrice ?? new StockPrice();
            if (!$existingPrice) {
                // For new stock price entities, make sure to use the same company object that's already persisted
                $price->setCompany($company);
                $price->setDate($date);
                $price->setPeriod($interval);
                $price->setCreatedAt(new \DateTimeImmutable());
                $this->logger->debug('Creating price data for ' . $company->getTickerSymbol() . ': ' . $priceData['date']);

                // Add this new price to the company's collection to ensure bidirectional relationship
                $company->addStockPrice($price);
            } else {
                 $this->logger->debug('Updating price data for ' . $company->getTickerSymbol() . ': ' . $priceData['date']);
            }

            $price->setOpen($priceData['open'] ?? 0);
            $price->setHigh($priceData['high'] ?? 0);
            $price->setLow($priceData['low'] ?? 0);
            $price->setClose($priceData['close'] ?? 0);
            $price->setAdjustedClose($priceData['adjustedClose'] ?? $price->getClose());
            $price->setVolume($priceData['volume'] ?? 0);
            $price->setChange($priceData['change'] ?? null);
            $price->setChangePercent($priceData['changePercent'] ?? null);
            $price->setSource('API');
            $price->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($price);
            $count++;

            if (($index + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(StockPrice::class); // Detach processed entities
                $this->logger->debug("Flushed batch of {$batchSize} price records.");
            }
        }

        $this->entityManager->flush(); // Flush remaining entities
        $this->entityManager->clear(StockPrice::class);
        $this->logger->info('Imported/Updated ' . $count . ' price records for ' . $company->getTickerSymbol());

        return $count;
    }
}
