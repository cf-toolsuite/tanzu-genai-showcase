<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface; // Keep logger for consistency

/**
 * Mock Implementation of Alpha Vantage API client
 * Returns predefined mock data.
 */
class MockAlphaVantageClient implements ApiClientInterface // Directly implements interface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockAlphaVantageClient instantiated. Will return mock data.");
    }

    // --- Implementation of ApiClientInterface methods ---

    public function searchCompanies(string $term): array
    {
        $this->logger->info("MockAlphaVantageClient::searchCompanies called", ['term' => $term]);
        $data = $this->getMockSearchResults($term);
        // Process mock data to match expected output format
        $results = [];
        foreach ($data['bestMatches'] ?? [] as $match) {
            $results[] = [
                'symbol' => $match['1. symbol'] ?? '',
                'name' => $match['2. name'] ?? '',
                'type' => $match['3. type'] ?? '',
                'region' => $match['4. region'] ?? '',
                'currency' => $match['8. currency'] ?? '',
                'matchScore' => $match['9. matchScore'] ?? '',
                'description' => '',
                'sector' => '',
                'industry' => '', // Add default empty fields
            ];
        }
        return $results;
    }

    public function getCompanyProfile(string $symbol): array
    {
        $this->logger->info("MockAlphaVantageClient::getCompanyProfile called", ['symbol' => $symbol]);
        $data = $this->getMockCompanyProfileData($symbol);
        // Process mock data to match expected output format
        return [
            'symbol' => $data['Symbol'] ?? $symbol,
            'name' => $data['Name'] ?? 'Mock ' . $symbol,
            'description' => $data['Description'] ?? '',
            'exchange' => $data['Exchange'] ?? '',
            'currency' => $data['Currency'] ?? 'USD',
            'country' => $data['Country'] ?? '',
            'sector' => $data['Sector'] ?? '',
            'industry' => $data['Industry'] ?? '',
            'address' => $data['Address'] ?? '',
            'employees' => (int)($data['FullTimeEmployees'] ?? 0),
            'marketCap' => (float)($data['MarketCapitalization'] ?? 0),
            'peRatio' => (float)($data['PERatio'] ?? 0),
            'dividendYield' => (float)($data['DividendYield'] ?? 0),
            'eps' => (float)($data['EPS'] ?? 0),
            'beta' => (float)($data['Beta'] ?? 0),
            'pegRatio' => (float)($data['PEGRatio'] ?? 0),
            'bookValue' => (float)($data['BookValue'] ?? 0),
            'dividendRate' => (float)($data['DividendPerShare'] ?? 0),
            'returnOnEquity' => (float)($data['ReturnOnEquityTTM'] ?? 0),
            'revenueGrowth' => (float)($data['QuarterlyRevenueGrowthYOY'] ?? 0),
            'operatingMargins' => (float)($data['OperatingMarginTTM'] ?? 0),
            'profitMargins' => (float)($data['ProfitMargin'] ?? 0),
            'currentPrice' => 0,
            'targetMeanPrice' => (float)($data['AnalystTargetPrice'] ?? 0),
            'website' => '',
            'officers' => [], // Add defaults for fields not in mock AV profile
        ];
    }

    public function getQuote(string $symbol): array
    {
        $this->logger->info("MockAlphaVantageClient::getQuote called", ['symbol' => $symbol]);
        $data = $this->getMockQuoteData($symbol);
        // Process mock data to match expected output format
        if (isset($data['Global Quote'])) {
            $quote = $data['Global Quote'];
            return [
                'symbol' => $quote['01. symbol'] ?? $symbol,
                'price' => (float)($quote['05. price'] ?? 0),
                'change' => (float)($quote['09. change'] ?? 0),
                'changePercent' => (float)str_replace('%', '', $quote['10. change percent'] ?? '0'),
                'volume' => (int)($quote['06. volume'] ?? 0),
                'latestTradingDay' => $quote['07. latest trading day'] ?? date('Y-m-d'),
                'previousClose' => (float)($quote['08. previous close'] ?? 0),
                'open' => (float)($quote['02. open'] ?? 0),
                'high' => (float)($quote['03. high'] ?? 0),
                'low' => (float)($quote['04. low'] ?? 0),
                'marketCap' => 0,
                'sharesOutstanding' => 0 // Add defaults
            ];
        }
        return ['symbol' => $symbol, 'price' => 0, 'change' => 0, 'changePercent' => 0, 'volume' => 0, 'latestTradingDay' => date('Y-m-d'), 'previousClose' => 0, 'open' => 0, 'high' => 0, 'low' => 0, 'marketCap' => 0, 'sharesOutstanding' => 0]; // Default empty structure
    }

    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $this->logger->info("MockAlphaVantageClient::getFinancials called", ['symbol' => $symbol, 'period' => $period]);
        $mockRawData = $this->getMockIncomeStatementData($symbol);
        $reportKey = $period === 'quarterly' ? 'quarterlyReports' : 'annualReports';
        $limit = $period === 'quarterly' ? 4 : 3;
        $financials = [];

        if (isset($mockRawData[$reportKey]) && is_array($mockRawData[$reportKey])) {
            $reports = array_slice($mockRawData[$reportKey], 0, $limit);
            foreach ($reports as $report) {
                $formatted = $this->formatFinancialReport($report, $symbol); // Reuse formatting logic if needed, or simplify
                if ($formatted) $financials[] = $formatted;
            }
        }
        return $financials;
    }

    // Internal mock formatter (can be simplified version of the real one)
    private function formatFinancialReport(array $report, string $symbol): ?array
    {
        $fiscalDate = $report['fiscalDateEnding'] ?? null;
        if (!$fiscalDate) return null;
        try {
            $date = new \DateTime($fiscalDate);
            $year = $date->format('Y');
            $month = (int)$date->format('m');
        } catch (\Exception $e) {
            return null;
        }
        if ($month <= 3) $quarter = 'Q1';
        elseif ($month <= 6) $quarter = 'Q2';
        elseif ($month <= 9) $quarter = 'Q3';
        else $quarter = 'Q4';
        $getFloat = fn($key) => isset($report[$key]) ? (float)$report[$key] : 0.0;
        $revenue = $getFloat('totalRevenue');
        $netIncome = $getFloat('netIncome');
        return ['symbol' => $symbol, 'fiscalDate' => $fiscalDate, 'fiscalQuarter' => $quarter, 'fiscalYear' => $year, 'reportDate' => $fiscalDate, 'reportType' => 'Income Statement', 'currency' => $report['reportedCurrency'] ?? 'USD', 'revenue' => $revenue, 'netIncome' => $netIncome, 'eps' => $getFloat('reportedEPS'), /* ... add other needed fields with default 0 ... */ 'profitMargin' => ($revenue != 0) ? ($netIncome / $revenue) : 0];
    }


    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $this->logger->info("MockAlphaVantageClient::getCompanyNews called", ['symbol' => $symbol]);
        $mockRawData = $this->getMockNewsFeed($symbol);
        // Process mock data to match expected output format
        $news = [];
        foreach (array_slice($mockRawData ?? [], 0, $limit) as $item) {
            $news[] = [
                'title' => $item['title'] ?? '',
                'description' => $item['summary'] ?? '',
                'url' => $item['url'] ?? '',
                'imageUrl' => $item['banner_image'] ?? null,
                'source' => $item['source'] ?? 'Unknown',
                'author' => $item['authors'][0] ?? 'Unknown',
                'publishedAt' => $item['time_published'] ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', substr($item['time_published'], 0, 15)))) : date('Y-m-d H:i:s'), // Basic format conversion
                'sentiment' => (float)($item['overall_sentiment_score'] ?? 0),
                'content' => '',
            ];
        }
        return $news;
    }

    public function getExecutives(string $symbol): array
    {
        $this->logger->info("MockAlphaVantageClient::getExecutives called", ['symbol' => $symbol]);
        // AV doesn't support this, return mock data directly
        return $this->getMockExecutivesData($symbol);
    }

    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $this->logger->info("MockAlphaVantageClient::getHistoricalPrices called", ['symbol' => $symbol, 'interval' => $interval]);
        $key = match ($interval) {
            'daily' => 'Time Series (Daily)',
            'weekly' => 'Weekly Adjusted Time Series',
            'monthly' => 'Monthly Adjusted Time Series',
        };
        $mockRawData = $this->getMockTimeSeries($interval);
        // Process mock data to match expected output format
        $prices = [];
        $previousClose = null;
        foreach ($mockRawData as $date => $priceData) {
            $currentClose = (float)($priceData['4. close'] ?? 0);
            $change = null;
            $changePercent = null;
            if ($previousClose !== null) {
                $change = $currentClose - $previousClose;
                $changePercent = ($previousClose != 0) ? ($change / $previousClose) * 100 : 0;
            }
            $prices[] = [
                'date' => $date,
                'open' => (float)($priceData['1. open'] ?? 0),
                'high' => (float)($priceData['2. high'] ?? 0),
                'low' => (float)($priceData['3. low'] ?? 0),
                'close' => $currentClose,
                'adjustedClose' => (float)($priceData['5. adjusted close'] ?? 0),
                'volume' => (int)($priceData['6. volume'] ?? 0),
                'dividend' => (float)($priceData['7. dividend amount'] ?? 0),
                'split' => (float)($priceData['8. split coefficient'] ?? 1),
                'change' => $change,
                'changePercent' => $changePercent
            ];
            $previousClose = $currentClose;
        }
        return $prices;
    }

    // --- Mock Data Generation Methods (Copied from original AlphaVantageClient) ---
    private function getMockSearchResults(string $term): array
    { /* ... */
        $term = strtoupper($term);
        $matches = [];
        if (strpos($term, 'AVGO') !== false || strpos($term, 'BROADCOM') !== false) {
            $matches[] = ['1. symbol' => 'AVGO', '2. name' => 'Broadcom Inc', '3. type' => 'Equity', '4. region' => 'United States', '8. currency' => 'USD', '9. matchScore' => '0.9000'];
        }
        if (strpos($term, 'AAPL') !== false || strpos($term, 'APPLE') !== false) {
            $matches[] = ['1. symbol' => 'AAPL', '2. name' => 'Apple Inc', '3. type' => 'Equity', '4. region' => 'United States', '8. currency' => 'USD', '9. matchScore' => '0.9500'];
        }
        if (strpos($term, 'MSFT') !== false || strpos($term, 'MICROSOFT') !== false) {
            $matches[] = ['1. symbol' => 'MSFT', '2. name' => 'Microsoft Corporation', '3. type' => 'Equity', '4. region' => 'United States', '8. currency' => 'USD', '9. matchScore' => '0.9500'];
        }
        return ['bestMatches' => $matches];
    }
    private function getMockCompanyProfileData(string $symbol): array
    { /* ... */
        switch (strtoupper($symbol)) {
            case 'AVGO':
                return ['Symbol' => 'AVGO', 'Name' => 'Broadcom Inc', 'Description' => 'Mock AVGO description', 'Exchange' => 'NASDAQ', 'Currency' => 'USD', 'Country' => 'USA', 'Sector' => 'Technology', 'Industry' => 'Semiconductors', 'Address' => '1 Mock Way', 'FullTimeEmployees' => '20000', 'MarketCapitalization' => '515000000000', 'PERatio' => '35.6', 'DividendYield' => '0.0155', 'EPS' => '32.50', 'Beta' => '1.1'];
            case 'AAPL':
                return ['Symbol' => 'AAPL', 'Name' => 'Apple Inc', 'Description' => 'Mock AAPL description', 'Exchange' => 'NASDAQ', 'Currency' => 'USD', 'Country' => 'USA', 'Sector' => 'Technology', 'Industry' => 'Consumer Electronics', 'Address' => '1 Infinite Loop', 'FullTimeEmployees' => '154000', 'MarketCapitalization' => '2750000000000', 'PERatio' => '29.8', 'DividendYield' => '0.0054', 'EPS' => '6.14', 'Beta' => '1.3'];
            default:
                return ['Symbol' => $symbol, 'Name' => 'Mock Company', 'Description' => 'Mock Default description', 'Exchange' => 'MOCK', 'Currency' => 'USD', 'Country' => 'USA', 'Sector' => 'Mock Sector', 'Industry' => 'Mock Industry', 'Address' => '456 Default Dr', 'FullTimeEmployees' => '1000', 'MarketCapitalization' => '1000000000', 'PERatio' => '15.0', 'DividendYield' => '0.02', 'EPS' => '5.00', 'Beta' => '1.0'];
        }
    }
    private function getMockQuoteData(string $symbol): array
    { /* ... */
        $price = mt_rand(100, 1500);
        $change = mt_rand(-10, 10);
        return ['Global Quote' => ['01. symbol' => $symbol, '05. price' => $price, '09. change' => $change, '10. change percent' => ($price - $change != 0 ? ($change / ($price - $change) * 100) : 0) . '%', '06. volume' => mt_rand(1000000, 5000000), '07. latest trading day' => date('Y-m-d'), '08. previous close' => $price - $change, '02. open' => $price + mt_rand(-1, 1), '03. high' => $price + mt_rand(0, 2), '04. low' => $price - mt_rand(0, 2)]];
    }
    private function getMockIncomeStatementData(string $symbol): array
    { /* ... */
        $qReports = [];
        $aReports = [];
        for ($i = 0; $i < 4; $i++) {
            $qReports[] = ['fiscalDateEnding' => date('Y-m-d', strtotime("-{$i} quarter")), 'totalRevenue' => mt_rand(10, 15) * 1e9, 'netIncome' => mt_rand(1, 4) * 1e9, 'reportedEPS' => mt_rand(1, 5), 'reportedCurrency' => 'USD'];
        }
        for ($i = 0; $i < 3; $i++) {
            $aReports[] = ['fiscalDateEnding' => date('Y-m-d', strtotime("-{$i} year")), 'totalRevenue' => mt_rand(40, 60) * 1e9, 'netIncome' => mt_rand(5, 15) * 1e9, 'reportedEPS' => mt_rand(5, 20), 'reportedCurrency' => 'USD'];
        }
        return ['quarterlyReports' => $qReports, 'annualReports' => $aReports];
    }
    private function getMockNewsFeed(string $symbol): array
    { /* ... */
        $feed = [];
        for ($i = 0; $i < 5; $i++) {
            $feed[] = ['title' => "Mock News {$i} for {$symbol}", 'summary' => 'This is mock news content.', 'url' => 'https://example.com', 'time_published' => date('Ymd\THis', strtotime("-{$i} day")), 'authors' => ['Mock Author'], 'source' => 'Mock Source', 'banner_image' => '', 'overall_sentiment_score' => (mt_rand(-50, 50) / 100)];
        }
        return $feed;
    }
    private function getMockExecutivesData(string $symbol): array
    { /* Provides default mock executive data */
        return [['name' => 'Mock CEO', 'title' => 'Chief Executive Officer'], ['name' => 'Mock CFO', 'title' => 'Chief Financial Officer']];
    }
    private function getMockTimeSeries(string $interval): array
    { /* ... */
        $series = [];
        $days = $interval === 'daily' ? 100 : ($interval === 'weekly' ? 52 : 24);
        $step = $interval === 'daily' ? '-1 day' : ($interval === 'weekly' ? '-1 week' : '-1 month');
        $price = mt_rand(100, 1000);
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("{$i} {$step}"));
            $open = $price;
            $close = $open + mt_rand(-5, 5);
            $high = max($open, $close) + mt_rand(0, 2);
            $low = min($open, $close) - mt_rand(0, 2);
            $volume = mt_rand(500000, 10000000);
            $adjustedClose = $close * (1 + (mt_rand(-1, 1) / 100));
            $dividend = (mt_rand(0, 10) == 0) ? mt_rand(1, 5) / 10 : 0;
            $split = 1;
            $series[$date] = ['1. open' => (string)$open, '2. high' => (string)$high, '3. low' => (string)$low, '4. close' => (string)$close, '5. adjusted close' => (string)$adjustedClose, '6. volume' => (string)$volume, '7. dividend amount' => (string)$dividend, '8. split coefficient' => (string)$split];
            $price = $close;
        }
        return $series;
    }
}
