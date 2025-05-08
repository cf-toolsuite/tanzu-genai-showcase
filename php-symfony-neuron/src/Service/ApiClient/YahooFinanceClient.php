<?php

namespace App\Service\ApiClient;

// No need for ParameterBagInterface here if only used for API key which is now handled in AbstractApiClient
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface; // Keep for constructor signature

/**
 * Yahoo Finance API client (via RapidAPI) - REAL Implementation
 */
class YahooFinanceClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://yahoo-finance-real-time1.p.rapidapi.com';
        $this->apiKey = $this->params->get('yahoo_finance.api_key', '');
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        // Auth is via headers, handled in request override
        return [];
    }

    /**
     * Override request to add RapidAPI headers.
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        // Ensure RapidAPI headers are added for real calls
        if (empty($this->apiKey)) {
            throw new \LogicException("Yahoo Finance (RapidAPI) Key is required but missing.");
        }
        $rapidApiHeaders = [
            'headers' => [
                'X-RapidAPI-Key' => $this->apiKey,
                'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
            ]
        ];
        // Merge RapidAPI headers with any other options provided
        $options = array_merge_recursive($options, $rapidApiHeaders);

        // Call parent request, passing the combined options
        // Parent request handles base URL, method, parameters, and final execution
        return parent::request($method, $endpoint, $params, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        $endpoint = '/search';
        $params = ['query' => $term, 'region' => 'US'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $results = [];
        if (isset($data['quotes']) && is_array($data['quotes'])) {
            foreach ($data['quotes'] as $quote) {
                if (isset($quote['quoteType']) && $quote['quoteType'] === 'EQUITY') {
                    // Ensure all fields expected by the standard format are present
                    $results[] = [
                        'symbol' => $quote['symbol'] ?? '',
                        'name' => $quote['shortname'] ?? $quote['longname'] ?? '',
                        'type' => $quote['quoteType'] ?? '',
                        'exchange' => $quote['exchange'] ?? '',
                        // Include additional fields if available in new API
                        'region' => $quote['region'] ?? '',
                        'currency' => $quote['currency'] ?? '',
                        'description' => '',
                        'sector' => $quote['sector'] ?? '',
                        'industry' => $quote['industry'] ?? '',
                    ];
                }
            }
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyProfile(string $symbol): array
    {
        // Get basic company profile data
        $profileEndpoint = '/stock/get-profile';
        $profileParams = ['symbol' => $symbol, 'region' => 'US'];

        // Get financial summary data
        $summaryEndpoint = '/stock/get-summary';
        $summaryParams = ['symbol' => $symbol, 'region' => 'US'];

        try {
            // Get profile and summary data
            $profileData = $this->request('GET', $profileEndpoint, $profileParams);
            $summaryData = $this->request('GET', $summaryEndpoint, $summaryParams);

            // Extract relevant data
            $summaryProfile = $profileData['summaryProfile'] ?? [];
            $summaryDetail = $summaryData['summaryDetail'] ?? [];
            $financialData = $summaryData['financialData'] ?? [];
            $price = $summaryData['price'] ?? [];
            $keyStats = $summaryData['defaultKeyStatistics'] ?? [];

            // Check for essential data
            if (empty($summaryProfile['longName']) && empty($price['shortName'])) {
                throw new \RuntimeException("Failed to retrieve valid company name for {$symbol} from Yahoo Finance.");
            }

            // Get ESG data if available
            $esgData = $this->getESGData($symbol);

            return [
                'symbol' => $symbol,
                'name' => $summaryProfile['longName'] ?? ($price['shortName'] ?? ''),
                'description' => $summaryProfile['longBusinessSummary'] ?? '',
                'exchange' => $price['exchangeName'] ?? ($summaryDetail['exchange'] ?? ''),
                'currency' => $price['currency'] ?? ($summaryDetail['currency'] ?? 'USD'),
                'country' => $summaryProfile['country'] ?? '',
                'sector' => $summaryProfile['sector'] ?? '',
                'industry' => $summaryProfile['industry'] ?? '',
                'address' => $summaryProfile['address1'] ?? '',
                'city' => $summaryProfile['city'] ?? '',
                'state' => $summaryProfile['state'] ?? '',
                'zip' => $summaryProfile['zip'] ?? '',
                'website' => $summaryProfile['website'] ?? '',
                'employees' => (int)($summaryProfile['fullTimeEmployees'] ?? 0),
                'officers' => $this->getExecutives($symbol), // Use dedicated executives method
                'marketCap' => (float)($summaryDetail['marketCap'] ?? 0),
                'peRatio' => (float)($summaryDetail['trailingPE'] ?? 0),
                'pegRatio' => (float)($summaryDetail['pegRatio'] ?? 0),
                'bookValue' => (float)($summaryDetail['bookValue'] ?? 0),
                'dividendYield' => (float)($summaryDetail['dividendYield'] ?? 0),
                'dividendRate' => (float)($summaryDetail['dividendRate'] ?? 0),
                'beta' => (float)($summaryDetail['beta'] ?? 0),
                'currentPrice' => (float)($price['regularMarketPrice'] ?? 0),
                'targetHighPrice' => (float)($financialData['targetHighPrice'] ?? 0),
                'targetLowPrice' => (float)($financialData['targetLowPrice'] ?? 0),
                'targetMeanPrice' => (float)($financialData['targetMeanPrice'] ?? 0),
                'returnOnEquity' => (float)($financialData['returnOnEquity'] ?? 0),
                'debtToEquity' => (float)($financialData['debtToEquity'] ?? 0),
                'revenueGrowth' => (float)($financialData['revenueGrowth'] ?? 0),
                'grossMargins' => (float)($financialData['grossMargins'] ?? 0),
                'operatingMargins' => (float)($financialData['operatingMargins'] ?? 0),
                'profitMargins' => (float)($financialData['profitMargins'] ?? 0),
                // New data points available in updated API
                'esgScore' => $esgData['totalEsg'] ?? null,
                'environmentScore' => $esgData['environmentScore'] ?? null,
                'socialScore' => $esgData['socialScore'] ?? null,
                'governanceScore' => $esgData['governanceScore'] ?? null,
                'recentSecFilings' => $this->getRecentSecFilings($symbol, 3),
            ];
        } catch (\Exception $e) {
            $this->logger->error("Error retrieving company profile for {$symbol}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        $endpoint = '/stock/get-summary';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        if (isset($data['price'])) {
            $price = $data['price'];
            $summary = $data['summaryDetail'] ?? [];

            if (empty($price['regularMarketPrice'])) {
                throw new \RuntimeException("Received invalid quote data (zero price) for {$symbol} from Yahoo Finance.");
            }

            $changePercent = (float)($price['regularMarketChangePercent'] ?? 0) * 100;

            return [
                'symbol' => $price['symbol'] ?? $symbol,
                'price' => (float)($price['regularMarketPrice'] ?? 0),
                'change' => (float)($price['regularMarketChange'] ?? 0),
                'changePercent' => $changePercent,
                'volume' => (int)($price['regularMarketVolume'] ?? 0),
                'latestTradingDay' => date('Y-m-d H:i:s', $price['regularMarketTime'] ?? time()),
                'previousClose' => (float)($price['regularMarketPreviousClose'] ?? 0),
                'open' => (float)($price['regularMarketOpen'] ?? 0),
                'high' => (float)($price['regularMarketDayHigh'] ?? 0),
                'low' => (float)($price['regularMarketDayLow'] ?? 0),
                'marketCap' => (float)($summary['marketCap'] ?? 0),
                'sharesOutstanding' => (float)($price['sharesOutstanding'] ?? 0),
                // Add new fields available in the API
                'fiftyTwoWeekHigh' => (float)($summary['fiftyTwoWeekHigh'] ?? 0),
                'fiftyTwoWeekLow' => (float)($summary['fiftyTwoWeekLow'] ?? 0),
                'averageVolume' => (int)($summary['averageVolume'] ?? 0),
                'marketState' => $price['marketState'] ?? 'REGULAR',
            ];
        }
        $this->logger->error("Invalid response structure from Yahoo Finance get-summary for quote.", ['symbol' => $symbol]);
        throw new \RuntimeException("Invalid quote response structure for {$symbol} from Yahoo Finance.");
    }

    /**
     * {@inheritdoc}
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $endpoint = '/stock/get-financials';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        $financials = [];
        $key = $period === 'quarterly' ? 'incomeStatementHistoryQuarterly' : 'incomeStatementHistory';

        if (isset($data[$key])) {
            foreach ($data[$key] as $report) {
                $date = $report['endDate']['fmt'] ?? null;
                if (!$date) continue;

                $financialData = [
                    'symbol' => $symbol,
                    'fiscalDate' => $date,
                    'reportDate' => $date,
                    'currency' => 'USD',
                    'revenue' => (float)($report['totalRevenue']['raw'] ?? 0),
                    'netIncome' => (float)($report['netIncome']['raw'] ?? 0),
                    'eps' => (float)($report['dilutedEPS']['raw'] ?? 0),
                    'operatingIncome' => (float)($report['operatingIncome']['raw'] ?? 0),
                    'grossProfit' => (float)($report['grossProfit']['raw'] ?? 0),
                    'costOfRevenue' => (float)($report['costOfRevenue']['raw'] ?? 0),
                    'researchDevelopment' => (float)($report['researchDevelopment']['raw'] ?? 0),
                    'sellingGeneralAdmin' => (float)($report['sellingGeneralAdministrative']['raw'] ?? 0),
                    'totalOperatingExpenses' => (float)($report['totalOperatingExpenses']['raw'] ?? 0),
                    'interestExpense' => (float)($report['interestExpense']['raw'] ?? 0),
                    'incomeTaxExpense' => (float)($report['incomeTaxExpense']['raw'] ?? 0),
                ];

                // Add quarterly/yearly metadata
                try {
                    $dateObj = new \DateTime($date);
                    $year = $dateObj->format('Y');
                    $month = (int)$dateObj->format('m');
                    if ($month <= 3) $q = 'Q1';
                    elseif ($month <= 6) $q = 'Q2';
                    elseif ($month <= 9) $q = 'Q3';
                    else $q = 'Q4';

                    $financialData['fiscalQuarter'] = $period === 'quarterly' ? $q : null;
                    $financialData['fiscalYear'] = $year;
                    $financialData['reportType'] = $period === 'quarterly' ? '10-Q' : '10-K';
                } catch (\Exception $e) {
                    $this->logger->error("Error processing financial date: {$date}", ['error' => $e->getMessage()]);
                }

                $financials[] = $financialData;
            }
        } else {
            $this->logger->warning("Missing financial data for {$symbol} ({$period}) in Yahoo response");
        }

        // Sort by date (newest first)
        usort($financials, fn($a, $b) => strtotime($b['reportDate']) - strtotime($a['reportDate']));
        return array_slice($financials, 0, 4); // Return last 4 periods
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $endpoint = '/news/get-list';
        $params = [
            'query' => $symbol,
            'region' => 'US',
            'count' => $limit
        ];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $news = [];
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as $item) {
                $news[] = [
                    'title' => $item['title'] ?? '',
                    'description' => $item['summary'] ?? '',
                    'url' => $item['link'] ?? '',
                    'imageUrl' => isset($item['thumbnail']['resolutions'][0]['url']) ? $item['thumbnail']['resolutions'][0]['url'] : null,
                    'source' => $item['publisher'] ?? 'Yahoo Finance',
                    'author' => $item['author'] ?? '',
                    'publishedAt' => date('Y-m-d H:i:s', strtotime($item['pubDate'] ?? 'now')),
                    'sentiment' => 0, // Could implement sentiment analysis here
                    'content' => $item['content'] ?? '',
                ];

                if (count($news) >= $limit) break;
            }
        } else {
            $this->logger->warning("No 'items' key found in Yahoo Finance news response.", ['symbol' => $symbol]);
        }
        return $news;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        $endpoint = '/stock/get-insider-roster';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        try {
            $data = $this->request('GET', $endpoint, $params);

            $executives = [];
            if (isset($data['insiderHolders']['holders']) && is_array($data['insiderHolders']['holders'])) {
                foreach ($data['insiderHolders']['holders'] as $holder) {
                    $executives[] = [
                        'name' => $holder['name'] ?? 'Unknown',
                        'title' => $holder['relation'] ?? 'Unknown',
                        'age' => null,
                        'yearJoined' => null,
                        'bio' => '',
                        'compensation' => 0, // Not directly available in this endpoint
                        'education' => '',
                        'previousCompanies' => '',
                        // New data available in this endpoint
                        'sharesHeld' => (int)($holder['positionDirect']['raw'] ?? 0),
                        'latestTransaction' => $holder['latestTransDate'] ?? '',
                        'position' => $holder['position'] ?? '',
                    ];
                }
            }

            return $executives;
        } catch (\Exception $e) {
            $this->logger->error("Error getting executives: " . $e->getMessage(), ['symbol' => $symbol]);
            return [];
        }
    }

    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array
    {
        // Get the data from the summary endpoint which includes recommendations
        $endpoint = '/stock/get-summary';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        try {
            // Real API call
            $data = $this->request('GET', $endpoint, $params);

            // Process the response data
            $ratings = [];
            $consensus = [
                'consensusRating' => 'Hold', // Default
                'averagePriceTarget' => 0,
                'lowPriceTarget' => 0,
                'highPriceTarget' => 0,
                'buy' => 0,
                'hold' => 0,
                'sell' => 0,
                'upside' => 0,
            ];

            // Get recommendation trend data
            if (isset($data['recommendationTrend']['trend'])) {
                $trendData = $data['recommendationTrend']['trend'][0] ?? null;

                if ($trendData) {
                    $consensus['buy'] = (int)($trendData['strongBuy'] ?? 0) + (int)($trendData['buy'] ?? 0);
                    $consensus['hold'] = (int)($trendData['hold'] ?? 0);
                    $consensus['sell'] = (int)($trendData['sell'] ?? 0) + (int)($trendData['strongSell'] ?? 0);

                    // Determine consensus rating based on the count
                    if ($consensus['buy'] > $consensus['hold'] && $consensus['buy'] > $consensus['sell']) {
                        $consensus['consensusRating'] = 'Buy';
                    } elseif ($consensus['sell'] > $consensus['hold'] && $consensus['sell'] > $consensus['buy']) {
                        $consensus['consensusRating'] = 'Sell';
                    } else {
                        $consensus['consensusRating'] = 'Hold';
                    }
                }
            }

            // Get price targets
            if (isset($data['financialData'])) {
                $financialData = $data['financialData'];

                $consensus['averagePriceTarget'] = (float)($financialData['targetMeanPrice'] ?? 0);
                $consensus['lowPriceTarget'] = (float)($financialData['targetLowPrice'] ?? 0);
                $consensus['highPriceTarget'] = (float)($financialData['targetHighPrice'] ?? 0);

                // Calculate upside
                $currentPrice = (float)($data['price']['regularMarketPrice'] ?? 0);
                if ($currentPrice > 0 && $consensus['averagePriceTarget'] > 0) {
                    $consensus['upside'] = (($consensus['averagePriceTarget'] - $currentPrice) / $currentPrice) * 100;
                }
            }

            // Get individual analyst ratings if available using the insights endpoint
            try {
                $insightsEndpoint = '/stock/get-insights';
                $insightsParams = ['symbol' => $symbol, 'region' => 'US'];
                $insightsData = $this->request('GET', $insightsEndpoint, $insightsParams);

                if (isset($insightsData['finance']['result']['instrumentInfo']['recommendations'])) {
                    $recommendations = $insightsData['finance']['result']['instrumentInfo']['recommendations'];
                    foreach ($recommendations as $item) {
                        $ratings[] = [
                            'firm' => $item['provider'] ?? 'Unknown Firm',
                            'toGrade' => $item['rating'] ?? 'N/A',
                            'fromGrade' => '',
                            'action' => $item['type'] ?? 'N/A',
                            'date' => $item['dateCreated'] ?? date('Y-m-d'),
                            'priceTarget' => $item['targetPrice'] ?? null
                        ];
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning("Error getting detailed analyst ratings from insights endpoint: " . $e->getMessage(), ['symbol' => $symbol]);
                // Continue with the basic data we have
            }

            return [
                'ratings' => $ratings,
                'consensus' => $consensus
            ];

        } catch (\Exception $e) {
            $this->logger->error("Error getting analyst ratings: " . $e->getMessage(), ['symbol' => $symbol]);
            throw $e;
        }
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
        $transactionsEndpoint = '/stock/get-insider-transactions';
        $rosterEndpoint = '/stock/get-insider-roster';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        try {
            // Get both transaction data and insider roster data
            $transactionData = $this->request('GET', $transactionsEndpoint, $params);
            $rosterData = $this->request('GET', $rosterEndpoint, $params);

            // Create a mapping of insider names to their current share positions
            $insiderPositions = [];
            if (isset($rosterData['insiderHolders']['holders']) && is_array($rosterData['insiderHolders']['holders'])) {
                foreach ($rosterData['insiderHolders']['holders'] as $holder) {
                    if (isset($holder['name']) && isset($holder['positionDirect']['raw'])) {
                        $insiderPositions[$holder['name']] = (int)$holder['positionDirect']['raw'];
                    }
                }
            }

            $transactions = [];

            if (isset($transactionData['insiderTransactions']['transactions']) && is_array($transactionData['insiderTransactions']['transactions'])) {
                foreach ($transactionData['insiderTransactions']['transactions'] as $transaction) {
                    $filerName = $transaction['filerName'] ?? 'Unknown';

                    $transactionData = [
                        'ownerName' => $filerName, // Map to ownerName for template compatibility
                        'insider' => $filerName,
                        'title' => $transaction['filerRelation'] ?? 'Unknown',
                        'transactionType' => $transaction['transactionText'] ?? 'Unknown',
                        'ownership' => $transaction['ownership'] ?? 'Direct',
                    ];

                    // Date handling - extract from nested structure
                    if (isset($transaction['startDate']['fmt'])) {
                        $transactionData['date'] = $transaction['startDate']['fmt'];
                    } elseif (isset($transaction['startDate']['raw'])) {
                        $transactionData['date'] = date('Y-m-d', $transaction['startDate']['raw']);
                    } else {
                        $transactionData['date'] = date('Y-m-d');
                    }

                    // Shares handling
                    if (isset($transaction['shares']['raw'])) {
                        $transactionData['shares'] = (int)$transaction['shares']['raw'];
                    } else {
                        $transactionData['shares'] = 0;
                    }

                    // Value handling
                    if (isset($transaction['value']['raw'])) {
                        $transactionData['value'] = (float)$transaction['value']['raw'];

                        // Calculate price per share if we have both shares and value
                        if (isset($transaction['shares']['raw']) && $transaction['shares']['raw'] > 0) {
                            $transactionData['price'] = (float)$transaction['value']['raw'] / (int)$transaction['shares']['raw'];
                        } else {
                            $transactionData['price'] = 0;
                        }
                    } else {
                        $transactionData['value'] = 0;
                        $transactionData['price'] = 0;
                    }

                    // Look up shares owned from the insider roster data
                    $transactionData['sharesOwned'] = $insiderPositions[$filerName] ?? 0;

                    $transactions[] = $transactionData;

                    if (count($transactions) >= $limit) {
                        break;
                    }
                }
            }

            // If we got aggregate data and no transactions, create a placeholder
            if (empty($transactions) && isset($transactionData['netSharePurchaseActivity'])) {
                $netActivity = $transactionData['netSharePurchaseActivity'];
                $summary = [
                    'ownerName' => 'All Insiders (Summary)',
                    'insider' => 'All Insiders',
                    'title' => 'Company Insiders',
                    'date' => date('Y-m-d'),
                    'transactionType' => 'Summary',
                    'shares' => isset($netActivity['netInfoShares']) ? (int)$netActivity['netInfoShares'] : 0,
                    'sharesOwned' => isset($netActivity['totalInsiderShares']) ? (int)$netActivity['totalInsiderShares'] : 0,
                    'price' => 0, // Not applicable for summary
                    'value' => 0, // Not applicable for summary
                    'ownership' => 'Direct'
                ];
                $transactions[] = $summary;
            }

            return $transactions;

        } catch (\Exception $e) {
            $this->logger->error("Error getting insider trading data: " . $e->getMessage(), ['symbol' => $symbol]);
            throw $e;
        }
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
        $endpoint = '/stock/get-holders';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        try {
            // Real API call
            $data = $this->request('GET', $endpoint, $params);

            $institutions = [];

            // Get institutional holders
            if (isset($data['institutionOwnership']['ownershipList'])) {
                $owners = $data['institutionOwnership']['ownershipList'];

                foreach ($owners as $owner) {
                    $institutions[] = [
                        'name' => $owner['organization'] ?? 'Unknown Institution',
                        'sharesHeld' => (int)($owner['position'] ?? 0),
                        'value' => (float)($owner['value'] ?? 0),
                        'percentOwnership' => (float)($owner['pctHeld'] ?? 0) * 100,
                        'change' => (float)($owner['pctChange'] ?? 0) * 100,
                        'dateReported' => $owner['reportDate'] ?? date('Y-m-d')
                    ];

                    if (count($institutions) >= $limit) {
                        break;
                    }
                }
            }

            // If no institutions found, try fund ownership
            if (empty($institutions) && isset($data['fundOwnership']['ownershipList'])) {
                $owners = $data['fundOwnership']['ownershipList'];

                foreach ($owners as $owner) {
                    $institutions[] = [
                        'name' => $owner['organization'] ?? 'Unknown Fund',
                        'sharesHeld' => (int)($owner['position'] ?? 0),
                        'value' => (float)($owner['value'] ?? 0),
                        'percentOwnership' => (float)($owner['pctHeld'] ?? 0) * 100,
                        'change' => (float)($owner['pctChange'] ?? 0) * 100,
                        'dateReported' => $owner['reportDate'] ?? date('Y-m-d'),
                        'type' => 'Fund'
                    ];

                    if (count($institutions) >= $limit) {
                        break;
                    }
                }
            }

            return $institutions;

        } catch (\Exception $e) {
            $this->logger->error("Error getting institutional ownership data: " . $e->getMessage(), ['symbol' => $symbol]);
            throw $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array ESG data with scores
     */
    public function getESGData(string $symbol): array
    {
        $endpoint = '/stock/get-esg-chart';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        try {
            $data = $this->request('GET', $endpoint, $params);

            $esgData = [];

            if (isset($data['esgChart']['result'][0])) {
                $result = $data['esgChart']['result'][0];

                // Find the most recent ESG score
                $symbolSeries = $result['symbolSeries'] ?? [];
                if (count($symbolSeries) > 0) {
                    $latestIndex = count($symbolSeries['timestamp']) - 1;

                    $esgData = [
                        'totalEsg' => $symbolSeries['esgScore'][$latestIndex] ?? null,
                        'environmentScore' => $symbolSeries['environmentScore'][$latestIndex] ?? null,
                        'socialScore' => $symbolSeries['socialScore'][$latestIndex] ?? null,
                        'governanceScore' => $symbolSeries['governanceScore'][$latestIndex] ?? null,
                        'peerComparison' => isset($result['peerSeries']) ? [
                            'totalEsg' => $result['peerSeries']['esgScore'][count($result['peerSeries']['esgScore']) - 1] ?? null,
                            'environmentScore' => $result['peerSeries']['environmentScore'][count($result['peerSeries']['environmentScore']) - 1] ?? null,
                            'socialScore' => $result['peerSeries']['socialScore'][count($result['peerSeries']['socialScore']) - 1] ?? null,
                            'governanceScore' => $result['peerSeries']['governanceScore'][count($result['peerSeries']['governanceScore']) - 1] ?? null,
                        ] : [],
                        'lastUpdated' => isset($symbolSeries['timestamp'][$latestIndex]) ?
                            date('Y-m-d', $symbolSeries['timestamp'][$latestIndex]) : null
                    ];
                }
            }

            return $esgData;

        } catch (\Exception $e) {
            $this->logger->error("Error getting ESG data: " . $e->getMessage(), ['symbol' => $symbol]);
            return []; // Return empty array on error
        }
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
        $endpoint = '/stock/get-sec-filings';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        try {
            $data = $this->request('GET', $endpoint, $params);

            $filings = [];

            if (isset($data['secFilings']['filings']) && is_array($data['secFilings']['filings'])) {
                foreach ($data['secFilings']['filings'] as $filing) {
                    $filings[] = [
                        'date' => $filing['date'] ?? '',
                        'type' => $filing['type'] ?? '',
                        'title' => $filing['title'] ?? '',
                        'url' => $filing['edgarUrl'] ?? '',
                        'exhibits' => $filing['exhibits'] ?? []
                    ];

                    if (count($filings) >= $limit) {
                        break;
                    }
                }
            }

            return $filings;

        } catch (\Exception $e) {
            $this->logger->error("Error getting SEC filings: " . $e->getMessage(), ['symbol' => $symbol]);
            return []; // Return empty array on error
        }
    }

    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $endpoint = '/stock/get-chart';
        $yahooInterval = match ($interval) {
            'daily' => '1d',
            'weekly' => '1wk',
            'monthly' => '1mo',
            default => '1d'
        };
        $range = match ($outputSize) {
            'compact' => '3mo',
            'full' => 'max',
            default => '1y'
        };
        if ($outputSize === 'full') {
            if ($interval === 'weekly') $range = '10y';
            if ($interval === 'monthly') $range = 'max';
        }
        $params = [
            'symbol' => $symbol,
            'range' => $range,
            'region' => 'US',
            'interval' => $yahooInterval,
            'includePrePost' => 'false',
            'events' => 'div,split',
            'includeAdjustedClose' => 'true'
        ];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $prices = [];
        if (isset($data['chart']['result'][0])) {
            $result = $data['chart']['result'][0];
            $timestamps = $result['timestamp'] ?? [];
            $indicators = $result['indicators'] ?? [];
            $quoteData = $indicators['quote'][0] ?? [];
            $adjCloseData = $indicators['adjclose'][0]['adjclose'] ?? [];
            $count = count($timestamps);

            if (empty($timestamps) || !isset($quoteData['open']) || count($quoteData['open']) !== $count) {
                $this->logger->error("Inconsistent data arrays in Yahoo Finance chart response.", ['symbol' => $symbol]);
                return [];
            }

            $timeZone = new \DateTimeZone('UTC');
            if (!empty($result['meta']['exchangeTimezoneName'])) {
                try {
                    $timeZone = new \DateTimeZone($result['meta']['exchangeTimezoneName']);
                } catch (\Exception $e) {
                    // Use default UTC timezone if exchange timezone is invalid
                }
            }

            $previousClose = null;
            for ($i = 0; $i < $count; $i++) {
                if ($quoteData['open'][$i] === null || $quoteData['close'][$i] === null) continue;

                $date = new \DateTime();
                $date->setTimestamp($timestamps[$i]);
                $date->setTimezone($timeZone);

                $currentClose = (float)($quoteData['close'][$i] ?? 0);
                $change = null;
                $changePercent = null;

                if ($previousClose !== null) {
                    $change = $currentClose - $previousClose;
                    $changePercent = ($previousClose != 0) ? ($change / $previousClose) * 100 : 0;
                }

                // Process dividend and split events if available
                $dividend = 0;
                $split = 1;
                if (isset($result['events'])) {
                    if (isset($result['events']['dividends'][$timestamps[$i]])) {
                        $dividend = (float)($result['events']['dividends'][$timestamps[$i]]['amount'] ?? 0);
                    }
                    if (isset($result['events']['splits'][$timestamps[$i]])) {
                        $split = (float)($result['events']['splits'][$timestamps[$i]]['splitRatio'] ?? 1);
                    }
                }

                $prices[] = [
                    'date' => $date->format('Y-m-d'),
                    'open' => (float)($quoteData['open'][$i] ?? 0),
                    'high' => (float)($quoteData['high'][$i] ?? 0),
                    'low' => (float)($quoteData['low'][$i] ?? 0),
                    'close' => $currentClose,
                    'adjustedClose' => (float)($adjCloseData[$i] ?? $currentClose),
                    'volume' => (int)($quoteData['volume'][$i] ?? 0),
                    'change' => $change,
                    'changePercent' => $changePercent,
                    'dividend' => $dividend,
                    'split' => $split
                ];

                $previousClose = $currentClose;
            }
        } else {
            $this->logger->warning("No chart results found in Yahoo Finance response.", ['symbol' => $symbol, 'interval' => $interval]);
        }

        return $prices;
    }
}
