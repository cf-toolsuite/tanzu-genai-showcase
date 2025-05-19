<?php

namespace App\Service;

use App\Service\ApiClient\StockMarketDataApiClientInterface;
use App\Service\ApiClient\NewsApiClientInterface;
use App\Service\ApiClient\SecFilingsApiClientInterface;
use App\Service\ApiClient\AnalystRatingsApiClientInterface;
use App\Service\ApiClient\EsgDataApiClientInterface;
use App\Service\ApiClient\ExecutiveDataApiClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Financial Data Aggregator Service
 *
 * This service aggregates data from multiple specialized API clients,
 * providing a unified interface for accessing financial data.
 */
class FinancialDataAggregatorService implements FinancialDataServiceInterface
{
    private array $stockMarketDataClients;
    private array $newsClients;
    private array $secFilingsClients;
    private array $analystRatingsClients;
    private array $esgDataClients;
    private array $executiveDataClients;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->stockMarketDataClients = [];
        $this->newsClients = [];
        $this->secFilingsClients = [];
        $this->analystRatingsClients = [];
        $this->esgDataClients = [];
        $this->executiveDataClients = [];
        $this->logger = $logger;
    }

    /**
     * Add a stock market data client
     */
    public function addStockMarketDataClient(StockMarketDataApiClientInterface $client): void
    {
        $this->stockMarketDataClients[] = $client;
    }

    /**
     * Add a news client
     */
    public function addNewsClient(NewsApiClientInterface $client): void
    {
        $this->newsClients[] = $client;
    }

    /**
     * Add a SEC filings client
     */
    public function addSecFilingsClient(SecFilingsApiClientInterface $client): void
    {
        $this->secFilingsClients[] = $client;
    }

    /**
     * Add an analyst ratings client
     */
    public function addAnalystRatingsClient(AnalystRatingsApiClientInterface $client): void
    {
        $this->analystRatingsClients[] = $client;
    }

    /**
     * Add an ESG data client
     */
    public function addEsgDataClient(EsgDataApiClientInterface $client): void
    {
        $this->esgDataClients[] = $client;
    }

    /**
     * Add an executive data client
     */
    public function addExecutiveDataClient(ExecutiveDataApiClientInterface $client): void
    {
        $this->executiveDataClients[] = $client;
    }

    /**
     * Search for companies by name or ticker symbol
     *
     * @param string $query Search query
     * @return array List of matching companies
     */
    public function searchCompanies(string $query): array
    {
        foreach ($this->stockMarketDataClients as $client) {
            try {
                $results = $client->searchCompanies($query);
                if (!empty($results)) {
                    return $results;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error searching companies from client: ' . get_class($client), [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array ESG data
     */
    public function getESGData(string $symbol): array
    {
        foreach ($this->esgDataClients as $client) {
            try {
                $data = $client->getESGData($symbol);
                if (!empty($data) && isset($data['totalEsg']) && $data['totalEsg'] !== null) {
                    return $data;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting ESG data from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Return empty structure if no data found
        return [
            'totalEsg' => null,
            'environmentScore' => null,
            'socialScore' => null,
            'governanceScore' => null,
            'peerComparison' => [],
            'lastUpdated' => null
        ];
    }

    /**
     * Get SEC filings for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of filings to return
     * @param string|null $filingType Filter by filing type (10-K, 10-Q, 8-K, etc.)
     * @return array SEC filings
     */
    public function getSecFilings(string $symbol, int $limit = 20, ?string $filingType = null): array
    {
        foreach ($this->secFilingsClients as $client) {
            try {
                $filings = $client->getRecentSecFilings($symbol, $limit);

                // Filter by filing type if specified
                if (!empty($filings) && $filingType !== null) {
                    $filings = array_filter($filings, function($filing) use ($filingType) {
                        return isset($filing['formType']) && $filing['formType'] === $filingType;
                    });
                }

                if (!empty($filings)) {
                    return $filings;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting SEC filings from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get insider trading data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of transactions to return
     * @return array Insider trading data
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array
    {
        foreach ($this->executiveDataClients as $client) {
            try {
                $transactions = $client->getInsiderTrading($symbol, $limit);
                if (!empty($transactions)) {
                    return $transactions;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting insider trading data from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get institutional ownership data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of institutions to return
     * @return array Institutional ownership data
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        foreach ($this->executiveDataClients as $client) {
            try {
                $institutions = $client->getInstitutionalOwnership($symbol, $limit);
                if (!empty($institutions)) {
                    return $institutions;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting institutional ownership data from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of ratings to return
     * @return array Analyst ratings
     */
    public function getAnalystRatings(string $symbol, int $limit = 20): array
    {
        foreach ($this->analystRatingsClients as $client) {
            try {
                $ratings = $client->getAnalystRatings($symbol);

                // Limit the number of individual ratings if needed
                if (!empty($ratings) && !empty($ratings['ratings'])) {
                    if (count($ratings['ratings']) > $limit) {
                        $ratings['ratings'] = array_slice($ratings['ratings'], 0, $limit);
                    }
                    return $ratings;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting analyst ratings from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Return empty structure if no data found
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
            ]
        ];
    }

    /**
     * Get additional metrics summary for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Additional metrics summary
     */
    public function getAdditionalMetricsSummary(string $symbol): array
    {
        // Aggregate data from multiple sources
        $esgData = $this->getESGData($symbol);
        $secFilings = $this->getSecFilings($symbol, 3);
        $insiderTrading = $this->getInsiderTrading($symbol, 5);
        $institutionalOwnership = $this->getInstitutionalOwnership($symbol, 5);
        $analystRatings = $this->getAnalystRatings($symbol);

        // Calculate some summary metrics
        $insiderBuys = 0;
        $insiderSells = 0;
        foreach ($insiderTrading as $transaction) {
            if (stripos($transaction['transactionType'] ?? '', 'buy') !== false) {
                $insiderBuys++;
            } elseif (stripos($transaction['transactionType'] ?? '', 'sell') !== false) {
                $insiderSells++;
            }
        }

        $totalInstitutionalOwnership = 0;
        foreach ($institutionalOwnership as $institution) {
            $totalInstitutionalOwnership += $institution['percentOwnership'] ?? 0;
        }

        return [
            'esg' => [
                'totalScore' => $esgData['totalEsg'] ?? null,
                'environmentScore' => $esgData['environmentScore'] ?? null,
                'socialScore' => $esgData['socialScore'] ?? null,
                'governanceScore' => $esgData['governanceScore'] ?? null,
            ],
            'secFilings' => [
                'recentCount' => count($secFilings),
                'latestFiling' => !empty($secFilings) ? $secFilings[0] : null,
            ],
            'insiderActivity' => [
                'recentCount' => count($insiderTrading),
                'buys' => $insiderBuys,
                'sells' => $insiderSells,
                'netSentiment' => $insiderBuys - $insiderSells,
            ],
            'institutionalOwnership' => [
                'count' => count($institutionalOwnership),
                'totalPercentage' => $totalInstitutionalOwnership,
            ],
            'analystRatings' => [
                'consensusRating' => $analystRatings['consensus']['consensusRating'] ?? 'N/A',
                'averagePriceTarget' => $analystRatings['consensus']['averagePriceTarget'] ?? 0,
                'upside' => $analystRatings['consensus']['upside'] ?? 0,
                'buy' => $analystRatings['consensus']['buy'] ?? 0,
                'hold' => $analystRatings['consensus']['hold'] ?? 0,
                'sell' => $analystRatings['consensus']['sell'] ?? 0,
            ],
        ];
    }

    /**
     * Get company news
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of news items to return
     * @return array Company news
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        foreach ($this->newsClients as $client) {
            try {
                $news = $client->getCompanyNews($symbol, $limit);
                if (!empty($news)) {
                    return $news;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting company news from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get general market news
     *
     * @param int $limit Maximum number of news items to return
     * @return array Market news
     */
    public function getMarketNews(int $limit = 10): array
    {
        foreach ($this->newsClients as $client) {
            try {
                // Assuming NewsApiClientInterface has a getMarketNews method
                $news = $client->getMarketNews($limit);
                if (!empty($news)) {
                    return $news;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting market news from client: ' . get_class($client), [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get company profile
     *
     * @param string $symbol Company ticker symbol
     * @return array Company profile
     */
    public function getCompanyProfile(string $symbol): array
    {
        foreach ($this->stockMarketDataClients as $client) {
            try {
                $profile = $client->getCompanyProfile($symbol);
                if (!empty($profile) && !empty($profile['name'])) {
                    return $profile;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting company profile from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'symbol' => $symbol,
            'name' => 'Unknown Company',
            'description' => 'No company profile available',
        ];
    }

    /**
     * Get company executives
     *
     * @param string $symbol Company ticker symbol
     * @return array Company executives
     */
    public function getExecutives(string $symbol): array
    {
        foreach ($this->executiveDataClients as $client) {
            try {
                $executives = $client->getExecutives($symbol);
                if (!empty($executives)) {
                    return $executives;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting company executives from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get stock quote
     *
     * @param string $symbol Company ticker symbol
     * @return array Stock quote
     */
    public function getStockQuote(string $symbol): array
    {
        foreach ($this->stockMarketDataClients as $client) {
            try {
                $quote = $client->getQuote($symbol);
                if (!empty($quote) && isset($quote['price']) && $quote['price'] > 0) {
                    return $quote;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting stock quote from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'symbol' => $symbol,
            'price' => 0,
            'change' => 0,
            'changePercent' => 0,
            'volume' => 0,
            'latestTradingDay' => date('Y-m-d'),
        ];
    }

    /**
     * Get historical stock prices
     *
     * @param string $symbol Company ticker symbol
     * @param string $interval Time interval (daily, weekly, monthly)
     * @param string $outputSize Size of time series (compact = 100 data points, full = 20+ years)
     * @return array Historical stock prices
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        foreach ($this->stockMarketDataClients as $client) {
            try {
                $prices = $client->getHistoricalPrices($symbol, $interval, $outputSize);
                if (!empty($prices)) {
                    return $prices;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting historical prices from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }

    /**
     * Get financial data
     *
     * @param string $symbol Company ticker symbol
     * @param string $period Period (quarterly, annual)
     * @return array Financial data
     */
    public function getFinancialData(string $symbol, string $period = 'quarterly'): array
    {
        foreach ($this->stockMarketDataClients as $client) {
            try {
                $financials = $client->getFinancials($symbol, $period);
                if (!empty($financials)) {
                    return $financials;
                }
            } catch (\Exception $e) {
                $this->logger->warning('Error getting financial data from client: ' . get_class($client), [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [];
    }
}
