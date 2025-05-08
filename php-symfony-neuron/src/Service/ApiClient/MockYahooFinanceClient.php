<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;

/**
 * Mock Implementation of Yahoo Finance API client
 * Returns predefined mock data.
 */
class MockYahooFinanceClient implements ApiClientInterface
{
    private LoggerInterface $logger;

    // Simulate structure for profile/quote mock data
    private array $mockDataStore = [];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockYahooFinanceClient instantiated. Will return mock data.");
        // Pre-populate some mock data
        $this->mockDataStore['AAPL'] = $this->getMockSummaryData('AAPL');
        $this->mockDataStore['AVGO'] = $this->getMockSummaryData('AVGO');
        $this->mockDataStore['MSFT'] = $this->getMockSummaryData('MSFT');
    }

    public function searchCompanies(string $term): array
    {
        $this->logger->info("MockYahooFinanceClient::searchCompanies called", ['term' => $term]);
        $mockRaw = $this->getMockSearchResults($term);
        // Process mock data to match expected output format
        $results = [];
        foreach ($mockRaw['quotes'] ?? [] as $quote) {
            if ($quote['quoteType'] === 'EQUITY') {
                $results[] = ['symbol' => $quote['symbol'], 'name' => $quote['shortname'], 'type' => $quote['quoteType'], 'exchange' => $quote['exchange'], 'region' => '', 'currency' => '', 'description' => '', 'sector' => '', 'industry' => ''];
            }
        }
        return $results;
    }

    public function getCompanyProfile(string $symbol): array
    {
        $this->logger->info("MockYahooFinanceClient::getCompanyProfile called", ['symbol' => $symbol]);
        $mockSummary = $this->mockDataStore[strtoupper($symbol)] ?? $this->getMockSummaryData($symbol);
        // Process mock data to match expected output format
        $result = $mockSummary['quoteSummary']['result'][0] ?? [];
        $assetProfile = $result['assetProfile'] ?? [];
        $summaryDetail = $result['summaryDetail'] ?? [];
        $financialData = $result['financialData'] ?? [];
        $price = $result['price'] ?? [];
        $esgData = $this->getESGData($symbol);

        return [
            'symbol' => $symbol,
            'name' => $assetProfile['longName'] ?? ($price['shortName'] ?? ''),
            'description' => $assetProfile['longBusinessSummary'] ?? '',
            'exchange' => $price['exchangeName'] ?? ($summaryDetail['exchange'] ?? ''),
            'currency' => $price['currency'] ?? ($summaryDetail['currency'] ?? 'USD'),
            'country' => $assetProfile['country'] ?? '',
            'sector' => $assetProfile['sector'] ?? '',
            'industry' => $assetProfile['industry'] ?? '',
            'address' => $assetProfile['address1'] ?? '',
            'city' => $assetProfile['city'] ?? '',
            'state' => $assetProfile['state'] ?? '',
            'zip' => $assetProfile['zip'] ?? '',
            'website' => $assetProfile['website'] ?? '',
            'employees' => (int)($assetProfile['fullTimeEmployees'] ?? 0),
            'officers' => $this->getExecutives($symbol),
            'marketCap' => (float)($summaryDetail['marketCap']['raw'] ?? 0),
            'peRatio' => (float)($summaryDetail['trailingPE']['raw'] ?? 0),
            'pegRatio' => (float)($summaryDetail['pegRatio']['raw'] ?? 0),
            'bookValue' => (float)($summaryDetail['bookValue']['raw'] ?? 0),
            'dividendYield' => (float)($summaryDetail['dividendYield']['raw'] ?? 0),
            'dividendRate' => (float)($summaryDetail['dividendRate']['raw'] ?? 0),
            'beta' => (float)($summaryDetail['beta']['raw'] ?? 0),
            'currentPrice' => (float)($financialData['currentPrice']['raw'] ?? 0),
            'targetHighPrice' => (float)($financialData['targetHighPrice']['raw'] ?? 0),
            'targetLowPrice' => (float)($financialData['targetLowPrice']['raw'] ?? 0),
            'targetMeanPrice' => (float)($financialData['targetMeanPrice']['raw'] ?? 0),
            'returnOnEquity' => (float)($financialData['returnOnEquity']['raw'] ?? 0),
            'debtToEquity' => (float)($financialData['debtToEquity']['raw'] ?? 0),
            'revenueGrowth' => (float)($financialData['revenueGrowth']['raw'] ?? 0),
            'grossMargins' => (float)($financialData['grossMargins']['raw'] ?? 0),
            'operatingMargins' => (float)($financialData['operatingMargins']['raw'] ?? 0),
            'profitMargins' => (float)($financialData['profitMargins']['raw'] ?? 0),
            // New fields to match updated client
            'esgScore' => $esgData['totalEsg'] ?? null,
            'environmentScore' => $esgData['environmentScore'] ?? null,
            'socialScore' => $esgData['socialScore'] ?? null,
            'governanceScore' => $esgData['governanceScore'] ?? null,
            'recentSecFilings' => $this->getRecentSecFilings($symbol, 3),
        ];
    }

    public function getQuote(string $symbol): array
    {
        $this->logger->info("MockYahooFinanceClient::getQuote called", ['symbol' => $symbol]);
        $mockSummary = $this->mockDataStore[strtoupper($symbol)] ?? $this->getMockSummaryData($symbol);
        // Process mock data to match expected output format
        $result = $mockSummary['quoteSummary']['result'][0] ?? [];
        $price = $result['price'] ?? [];
        $summary = $result['summaryDetail'] ?? [];
        $changePercentRaw = $price['regularMarketChangePercent']['raw'] ?? 0;
        $changePercent = $changePercentRaw * 100;
        return [
            'symbol' => $price['symbol'] ?? $symbol,
            'price' => (float)($price['regularMarketPrice']['raw'] ?? 0),
            'change' => (float)($price['regularMarketChange']['raw'] ?? 0),
            'changePercent' => $changePercent,
            'volume' => (int)($price['regularMarketVolume']['raw'] ?? 0),
            'latestTradingDay' => $price['regularMarketTime']['fmt'] ?? date('Y-m-d H:i:s'),
            'previousClose' => (float)($price['regularMarketPreviousClose']['raw'] ?? 0),
            'open' => (float)($price['regularMarketOpen']['raw'] ?? 0),
            'high' => (float)($price['regularMarketDayHigh']['raw'] ?? 0),
            'low' => (float)($price['regularMarketDayLow']['raw'] ?? 0),
            'marketCap' => (float)($summary['marketCap']['raw'] ?? 0),
            'sharesOutstanding' => (float)($price['sharesOutstanding']['raw'] ?? 0),
            // Add new fields to match the real client
            'fiftyTwoWeekHigh' => (float)($summary['fiftyTwoWeekHigh']['raw'] ?? 0),
            'fiftyTwoWeekLow' => (float)($summary['fiftyTwoWeekLow']['raw'] ?? 0),
            'averageVolume' => (int)($summary['averageVolume']['raw'] ?? 0),
            'marketState' => $price['marketState'] ?? 'REGULAR',
        ];
    }

    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $this->logger->info("MockYahooFinanceClient::getFinancials called", ['symbol' => $symbol, 'period' => $period]);
        $mockRaw = $this->getMockFinancialsData($symbol);
        // Process mock data to match expected output format
        $financials = [];
        $seriesKey = $period === 'quarterly' ? 'quarterly' : 'annual';
        $metricsMap = ['TotalRevenue' => 'revenue', /* ... */ 'NetIncome' => 'netIncome', 'DilutedEPS' => 'eps'];
        $reportsByDate = [];
        $statementKey = 'incomeStatementHistory';
        $historyKey = $seriesKey . 'IncomeStatementHistory';

        if (isset($mockRaw[$statementKey][$historyKey])) {
            foreach ($mockRaw[$statementKey][$historyKey] as $report) {
                $date = $report['endDate']['fmt'] ?? null;
                if (!$date) continue;
                foreach ($metricsMap as $yahooKey => $ourKey) {
                    if (isset($report[$yahooKey]['raw'])) $reportsByDate[$date][$ourKey] = (float)$report[$yahooKey]['raw'];
                }
                $reportsByDate[$date]['reportDate'] = $date;
            }
        }
        foreach ($reportsByDate as $date => $metrics) {
            try {
                $dateObj = new \DateTime($date);
                $year = $dateObj->format('Y');
                $month = (int)$dateObj->format('m');
                if ($month <= 3) $q = 'Q1';
                elseif ($month <= 6) $q = 'Q2';
                elseif ($month <= 9) $q = 'Q3';
                else $q = 'Q4';
                $financials[] = array_merge(['symbol' => $symbol, 'fiscalDate' => $date, 'fiscalQuarter' => $period === 'quarterly' ? $q : null, 'fiscalYear' => $year, 'reportDate' => $date, 'reportType' => $period === 'quarterly' ? '10-Q' : '10-K', 'currency' => 'USD', 'revenue' => 0.0, 'netIncome' => 0.0, 'eps' => 0.0], $metrics);
            } catch (\Exception $e) {
            }
        }
        usort($financials, fn($a, $b) => strtotime($b['reportDate']) - strtotime($a['reportDate']));
        return array_slice($financials, 0, 4);
    }

    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $this->logger->info("MockYahooFinanceClient::getCompanyNews called", ['symbol' => $symbol]);
        $mockRaw = $this->getMockNewsData($symbol);
        // Process mock data to match expected output format
        $news = [];
        foreach ($mockRaw['news'] ?? [] as $item) {
            $publishedTime = $item['provider_publish_time'] ?? null;
            $news[] = [
                'title' => $item['title'] ?? '',
                'description' => $item['summary'] ?? '',
                'url' => $item['link'] ?? '',
                'imageUrl' => $item['thumbnail']['resolutions'][0]['url'] ?? null,
                'source' => $item['publisher'] ?? 'Yahoo Mock',
                'author' => $item['author'] ?? '',
                'publishedAt' => $publishedTime ? date('Y-m-d H:i:s', $publishedTime) : date('Y-m-d H:i:s'),
                'sentiment' => 0,
                'content' => $item['content'] ?? ''
            ];
            if (count($news) >= $limit) break;
        }
        return $news;
    }

    public function getExecutives(string $symbol): array
    {
        $this->logger->info("MockYahooFinanceClient::getExecutives called", ['symbol' => $symbol]);
        $mockExecs = $this->getMockExecutives($symbol);

        $executives = [];
        foreach ($mockExecs as $exec) {
            $executives[] = [
                'name' => $exec['name'] ?? 'Unknown',
                'title' => $exec['title'] ?? 'Unknown',
                'age' => null,
                'yearJoined' => null,
                'bio' => '',
                'compensation' => 0,
                'education' => '',
                'previousCompanies' => '',
                // New fields to match real client
                'sharesHeld' => mt_rand(1000, 100000),
                'latestTransaction' => date('Y-m-d', strtotime('-' . mt_rand(1, 30) . ' days')),
                'position' => $exec['title'] ?? 'Officer',
            ];
        }
        return $executives;
    }

    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $this->logger->info("MockYahooFinanceClient::getHistoricalPrices called", ['symbol' => $symbol, 'interval' => $interval]);
        $mockRaw = $this->getMockChartData($symbol);
        // Process mock data to match expected output format
        $prices = [];
        $result = $mockRaw['chart']['result'][0] ?? null;
        if ($result) {
            $timestamps = $result['timestamp'] ?? [];
            $indicators = $result['indicators'] ?? [];
            $quoteData = $indicators['quote'][0] ?? [];
            $adjCloseData = $indicators['adjclose'][0]['adjclose'] ?? [];
            $count = count($timestamps);
            if ($count > 0 && isset($quoteData['open']) && count($quoteData['open']) === $count) { // Basic check
                $previousClose = null;
                for ($i = 0; $i < $count; $i++) {
                    if ($quoteData['open'][$i] === null) continue;
                    $date = new \DateTime();
                    $date->setTimestamp($timestamps[$i]);
                    $currentClose = (float)($quoteData['close'][$i] ?? 0);
                    $change = null;
                    $changePercent = null;
                    if ($previousClose !== null) {
                        $change = $currentClose - $previousClose;
                        $changePercent = ($previousClose != 0) ? ($change / $previousClose) * 100 : 0;
                    }
                    $prices[] = ['date' => $date->format('Y-m-d'), 'open' => (float)($quoteData['open'][$i] ?? 0), 'high' => (float)($quoteData['high'][$i] ?? 0), 'low' => (float)($quoteData['low'][$i] ?? 0), 'close' => $currentClose, 'adjustedClose' => (float)($adjCloseData[$i] ?? $currentClose), 'volume' => (int)($quoteData['volume'][$i] ?? 0), 'change' => $change, 'changePercent' => $changePercent, 'dividend' => 0, 'split' => 1];
                    $previousClose = $currentClose;
                }
            }
        }
        return $prices;
    }

    // --- Mock Data Generation Methods (Copied from original YahooFinanceClient) ---
    private function getMockSearchResults(string $term): array
    { /* ... */
        $term = strtoupper($term);
        $quotes = [];
        if (strpos($term, 'AVGO') !== false || strpos($term, 'BROADCOM') !== false) {
            $quotes[] = ['symbol' => 'AVGO', 'shortname' => 'Broadcom Inc.', 'quoteType' => 'EQUITY', 'exchange' => 'NMS'];
        }
        if (strpos($term, 'AAPL') !== false || strpos($term, 'APPLE') !== false) {
            $quotes[] = ['symbol' => 'AAPL', 'shortname' => 'Apple Inc.', 'quoteType' => 'EQUITY', 'exchange' => 'NMS'];
        }
        if (strpos($term, 'MSFT') !== false || strpos($term, 'MICROSOFT') !== false) {
            $quotes[] = ['symbol' => 'MSFT', 'shortname' => 'Microsoft Corp.', 'quoteType' => 'EQUITY', 'exchange' => 'NMS'];
        }
        return ['quotes' => $quotes, 'news' => []];
    }
    private function getMockSummaryData(string $symbol): array
    { /* Returns full summary structure */
        // This needs to return the complex structure expected by getQuote/getProfile
        // It combines assetProfile, summaryDetail, financialData, price mocks
        $profileMock = $this->getMockCompanyProfileData_Internal($symbol); // Internal helper for profile data
        $quoteMock = $this->getMockQuoteData_Internal($symbol); // Internal helper for price data

        return [
            'quoteSummary' => ['result' => [[
                'assetProfile' => $profileMock['assetProfile'],
                'summaryDetail' => $profileMock['summaryDetail'],
                'financialData' => $profileMock['financialData'],
                'price' => $quoteMock // Use the price part from quote mock
            ]]]
        ];
    }
    // Internal helper to generate profile parts for getMockSummaryData
    private function getMockCompanyProfileData_Internal(string $symbol): array
    {
        // Simplified mock data generation logic for profile parts
        $isAAPL = strtoupper($symbol) === 'AAPL';
        return [
            'assetProfile' => ['longName' => $isAAPL ? 'Apple Inc.' : $symbol . ' Mock Corp.', 'longBusinessSummary' => 'Mock business summary for ' . $symbol, 'country' => 'US', 'sector' => 'Technology', 'industry' => $isAAPL ? 'Consumer Electronics' : 'Mock Industry', 'website' => 'https://mock.com', 'fullTimeEmployees' => $isAAPL ? 150000 : 10000, 'companyOfficers' => [['name' => 'Mock CEO', 'title' => 'CEO'], ['name' => 'Mock CFO', 'title' => 'CFO']]],
            'summaryDetail' => ['exchange' => 'MOCK', 'currency' => 'USD', 'marketCap' => ['raw' => $isAAPL ? 2.5e12 : 1e10], 'trailingPE' => ['raw' => $isAAPL ? 28 : 18], 'dividendYield' => ['raw' => $isAAPL ? 0.005 : 0.015]],
            'financialData' => ['currentPrice' => ['raw' => $isAAPL ? 175 : 100], 'targetMeanPrice' => ['raw' => $isAAPL ? 200 : 120], 'returnOnEquity' => ['raw' => $isAAPL ? 0.45 : 0.15], 'revenueGrowth' => ['raw' => $isAAPL ? 0.05 : 0.10]]
        ];
    }
    // Internal helper to generate price part for getMockSummaryData
    private function getMockQuoteData_Internal(string $symbol): array
    {
        $price = strtoupper($symbol) === 'AAPL' ? 175 : 100;
        $change = strtoupper($symbol) === 'AAPL' ? 2.5 : -1.0;
        return ['symbol' => $symbol, 'regularMarketPrice' => ['raw' => $price], 'regularMarketChange' => ['raw' => $change], 'regularMarketChangePercent' => ['raw' => $change / ($price - $change)], 'regularMarketVolume' => ['raw' => mt_rand(1e6, 5e7)], 'regularMarketTime' => ['raw' => time(), 'fmt' => date('Y-m-d H:i:s')], 'regularMarketPreviousClose' => ['raw' => $price - $change], 'regularMarketOpen' => ['raw' => $price + mt_rand(-1, 1)], 'regularMarketDayHigh' => ['raw' => $price + mt_rand(1, 3)], 'regularMarketDayLow' => ['raw' => $price - mt_rand(1, 3)]];
    }
    private function getMockFinancialsData(string $symbol): array
    { /* ... Same as before, returns incomeStatementHistory structure ... */
        $qReports = [];
        $aReports = [];
        for ($i = 0; $i < 4; $i++) {
            $qReports[] = ['endDate' => ['fmt' => date('Y-m-d', strtotime("-{$i} quarter"))], 'totalRevenue' => ['raw' => mt_rand(10, 15) * 1e9], 'netIncome' => ['raw' => mt_rand(1, 4) * 1e9], 'dilutedEPS' => ['raw' => mt_rand(1, 5)]];
        }
        for ($i = 0; $i < 3; $i++) {
            $aReports[] = ['endDate' => ['fmt' => date('Y-m-d', strtotime("-{$i} year"))], 'totalRevenue' => ['raw' => mt_rand(40, 60) * 1e9], 'netIncome' => ['raw' => mt_rand(5, 15) * 1e9], 'dilutedEPS' => ['raw' => mt_rand(5, 20)]];
        }
        return ['incomeStatementHistory' => ['quarterlyIncomeStatementHistory' => $qReports, 'annualIncomeStatementHistory' => $aReports]];
    }
    private function getMockNewsData(string $symbol): array
    {
        $news = [];
        $limit = 5;
        for ($i = 0; $i < $limit; $i++) {
            $news[] = [
                'uuid' => 'mock-' . $i,
                'title' => "Mock News {$i} for {$symbol}",
                'summary' => "This is a mock summary for news article {$i} about {$symbol}.",
                'publisher' => 'Mock Source',
                'author' => 'Mock Author',
                'link' => 'https://example.com',
                'provider_publish_time' => strtotime("-{$i} day"),
                'thumbnail' => ['resolutions' => [['url' => 'https://via.placeholder.com/80?text=Nws']]],
                'content' => "This is the full content of mock news article {$i} about {$symbol}. It contains detailed information that would be relevant to investors."
            ];
        }
        return ['news' => $news];
    }
    private function getMockExecutives(string $symbol): array
    { /* ... Same as before, returns array of officers ... */
        return [['name' => 'Mock CEO', 'title' => 'Chief Executive Officer'], ['name' => 'Mock CFO', 'title' => 'Chief Financial Officer']];
    }

    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array ESG data with scores
     */
    public function getESGData(string $symbol): array
    {
        $this->logger->info("MockYahooFinanceClient::getESGData called", ['symbol' => $symbol]);

        $mockEsgData = [
            'totalEsg' => mt_rand(50, 90) / 10,  // Random score between 5.0 and 9.0
            'environmentScore' => mt_rand(40, 95) / 10,
            'socialScore' => mt_rand(40, 95) / 10,
            'governanceScore' => mt_rand(40, 95) / 10,
            'peerComparison' => [
                'totalEsg' => mt_rand(50, 90) / 10,
                'environmentScore' => mt_rand(40, 95) / 10,
                'socialScore' => mt_rand(40, 95) / 10,
                'governanceScore' => mt_rand(40, 95) / 10,
            ],
            'lastUpdated' => date('Y-m-d', strtotime('-' . mt_rand(1, 60) . ' days'))
        ];

        return $mockEsgData;
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
        $this->logger->info("MockYahooFinanceClient::getRecentSecFilings called", ['symbol' => $symbol]);

        $filingTypes = ['10-K', '10-Q', '8-K', 'S-1', '424B2'];
        $filings = [];

        for ($i = 0; $i < $limit; $i++) {
            $type = $filingTypes[array_rand($filingTypes)];
            $filings[] = [
                'date' => date('Y-m-d', strtotime('-' . ($i * 30 + mt_rand(1, 15)) . ' days')),
                'type' => $type,
                'title' => "{$type}: " . ucwords(strtolower($symbol)) . " " . match($type) {
                    '10-K' => 'Annual Report',
                    '10-Q' => 'Quarterly Report',
                    '8-K' => 'Current Report',
                    'S-1' => 'Registration Statement',
                    default => 'Filing'
                },
                'url' => "https://www.sec.gov/mock/{$symbol}/{$type}-" . date('Ymd', strtotime('-' . ($i * 30) . ' days')),
                'exhibits' => []
            ];
        }

        return $filings;
    }

    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array
    {
        $this->logger->info("MockYahooFinanceClient::getAnalystRatings called", ['symbol' => $symbol]);

        $ratingTypes = ['Buy', 'Hold', 'Sell', 'Outperform', 'Underperform', 'Neutral', 'Overweight', 'Equal-Weight'];
        $firms = ['Morgan Stanley', 'Goldman Sachs', 'JP Morgan', 'Bank of America', 'Citigroup', 'Wells Fargo', 'UBS', 'Barclays'];

        $buy = mt_rand(3, 15);
        $hold = mt_rand(2, 10);
        $sell = mt_rand(0, 5);
        $total = $buy + $hold + $sell;

        // Determine consensus based on distribution
        $consensusRating = "Hold";
        if ($buy > ($hold + $sell)) {
            $consensusRating = "Buy";
        } elseif ($sell > ($buy + $hold)) {
            $consensusRating = "Sell";
        }

        $currentPrice = mt_rand(50, 500);
        $meanTarget = $currentPrice * (1 + (mt_rand(-20, 40) / 100));
        $highTarget = $meanTarget * (1 + (mt_rand(5, 20) / 100));
        $lowTarget = $meanTarget * (1 - (mt_rand(5, 20) / 100));
        $upside = (($meanTarget - $currentPrice) / $currentPrice) * 100;

        $consensus = [
            'consensusRating' => $consensusRating,
            'averagePriceTarget' => $meanTarget,
            'lowPriceTarget' => $lowTarget,
            'highPriceTarget' => $highTarget,
            'buy' => $buy,
            'hold' => $hold,
            'sell' => $sell,
            'upside' => $upside,
        ];

        $ratings = [];
        $numRatings = min($total, 10); // Cap at 10 individual ratings

        for ($i = 0; $i < $numRatings; $i++) {
            $firm = $firms[array_rand($firms)];
            $toGrade = $ratingTypes[array_rand($ratingTypes)];
            $fromGrade = $ratingTypes[array_rand($ratingTypes)];
            $action = ($toGrade === $fromGrade) ? 'Reiterated' : ($toGrade > $fromGrade ? 'Upgraded' : 'Downgraded');

            $ratings[] = [
                'firm' => $firm,
                'toGrade' => $toGrade,
                'fromGrade' => $fromGrade,
                'action' => $action,
                'date' => date('Y-m-d', strtotime('-' . mt_rand(1, 90) . ' days')),
                'priceTarget' => $currentPrice * (1 + (mt_rand(-30, 50) / 100))
            ];
        }

        return [
            'ratings' => $ratings,
            'consensus' => $consensus
        ];
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
        $this->logger->info("MockYahooFinanceClient::getInsiderTrading called", ['symbol' => $symbol]);

        $insiders = $this->getMockExecutives($symbol); // Reuse executives as insiders
        $transactionTypes = ['Purchase', 'Sale', 'Option Exercise', 'Grant', 'Sale+OE'];

        $transactions = [];
        for ($i = 0; $i < min(count($insiders) * 3, $limit); $i++) {
            $insider = $insiders[$i % count($insiders)];
            $shares = mt_rand(100, 10000);
            $price = mt_rand(50, 500);

            $transactions[] = [
                'date' => date('Y-m-d', strtotime('-' . mt_rand(1, 180) . ' days')),
                'insider' => $insider['name'],
                'title' => $insider['title'],
                'transactionType' => $transactionTypes[array_rand($transactionTypes)],
                'shares' => $shares,
                'sharesOwned' => mt_rand(10000, 1000000),
                'price' => $price,
                'value' => $shares * $price
            ];
        }

        return $transactions;
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
        $this->logger->info("MockYahooFinanceClient::getInstitutionalOwnership called", ['symbol' => $symbol]);

        $institutions = [
            'BlackRock Inc.', 'Vanguard Group Inc.', 'State Street Corporation',
            'Fidelity Management & Research', 'T. Rowe Price Associates',
            'Capital Research Global Investors', 'JPMorgan Chase & Co.',
            'Bank of America Corporation', 'Berkshire Hathaway Inc.',
            'Morgan Stanley', 'Goldman Sachs Group Inc.', 'Wellington Management Group',
            'TIAA-CREF', 'Northern Trust Corporation', 'Dimensional Fund Advisors'
        ];

        $institutionalHolders = [];
        $totalShares = mt_rand(500000000, 2000000000); // Total outstanding shares
        $sharesAllocated = 0;

        for ($i = 0; $i < min(count($institutions), $limit); $i++) {
            $percentOwnership = mt_rand(1, 15) / 100; // 0.01 to 0.15 (1% to 15%)
            $sharesHeld = (int)($totalShares * $percentOwnership);
            $sharesAllocated += $sharesHeld;

            // Adjust if we're allocating too many shares
            if ($sharesAllocated > ($totalShares * 0.8)) {
                $percentOwnership = mt_rand(1, 5) / 1000; // Much smaller for remaining holders
                $sharesHeld = (int)($totalShares * $percentOwnership);
            }

            $value = $sharesHeld * mt_rand(50, 500); // Share price between $50-$500

            $institutionalHolders[] = [
                'name' => $institutions[$i],
                'sharesHeld' => $sharesHeld,
                'value' => $value,
                'percentOwnership' => $percentOwnership * 100, // Convert to percentage
                'change' => mt_rand(-500, 500) / 100, // -5% to +5%
                'dateReported' => date('Y-m-d', strtotime('-' . mt_rand(1, 90) . ' days')),
                'type' => ($i < 10) ? 'Institution' : 'Fund'
            ];
        }

        return $institutionalHolders;
    }

    private function getMockChartData(string $symbol): array
    { /* ... Same as before, returns chart structure ... */
        $timestamps = [];
        $open = [];
        $high = [];
        $low = [];
        $close = [];
        $volume = [];
        $adjclose = [];
        $days = 100;
        $price = mt_rand(100, 1000);
        for ($i = 0; $i < $days; $i++) {
            $date = strtotime("-{$i} day");
            $o = $price;
            $c = $o + mt_rand(-5, 5);
            $h = max($o, $c) + mt_rand(0, 2);
            $l = min($o, $c) - mt_rand(0, 2);
            $v = mt_rand(500000, 10000000);
            $ac = $c * (1 + (mt_rand(-1, 1) / 100));
            $timestamps[] = $date;
            $open[] = $o;
            $high[] = $h;
            $low[] = $l;
            $close[] = $c;
            $volume[] = $v;
            $adjclose[] = $ac;
            $price = $c;
        }
        return ['chart' => ['result' => [['meta' => ['currency' => 'USD', 'symbol' => $symbol, 'timezone' => 'UTC'], 'timestamp' => array_reverse($timestamps), 'indicators' => ['quote' => [['open' => array_reverse($open), 'high' => array_reverse($high), 'low' => array_reverse($low), 'close' => array_reverse($close), 'volume' => array_reverse($volume)]], 'adjclose' => [['adjclose' => array_reverse($adjclose)]]]]]]];
    }
}
