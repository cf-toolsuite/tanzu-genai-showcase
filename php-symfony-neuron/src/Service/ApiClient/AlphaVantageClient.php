<?php

namespace App\Service\ApiClient;

// No need for ParameterBagInterface here if only used for API key which is now handled in AbstractApiClient
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface; // Keep for constructor signature if extending abstract

/**
 * Alpha Vantage API client - REAL Implementation
 */
class AlphaVantageClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://www.alphavantage.co'; // Base URL without /query
        // API Key is read from params via AbstractApiClient constructor
        $this->apiKey = $this->params->get('alpha_vantage.api_key', '');
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return ['apikey' => $this->apiKey];
    }

    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        $endpoint = '/query';
        $params = [
            'function' => 'SYMBOL_SEARCH',
            'keywords' => $term
        ];

        // Real API call - throws exception on failure
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $results = [];
        if (isset($data['bestMatches']) && is_array($data['bestMatches'])) {
            foreach ($data['bestMatches'] as $match) {
                $results[] = [
                    'symbol' => $match['1. symbol'] ?? '',
                    'name' => $match['2. name'] ?? '',
                    'type' => $match['3. type'] ?? '',
                    'region' => $match['4. region'] ?? '',
                    'currency' => $match['8. currency'] ?? '',
                    'matchScore' => $match['9. matchScore'] ?? '',
                    // Add required fields from interface/standardized format even if null/empty
                    'description' => '',
                    'sector' => '',
                    'industry' => '',
                ];
            }
        }
        return $results;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyProfile(string $symbol): array
    {
        $endpoint = '/query';
        $params = [
            'function' => 'OVERVIEW',
            'symbol' => $symbol
        ];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Format the data (ensure keys match standardized format)
        // Throw exception if essential info like name is missing
        if (empty($data['Name']) || $data['Name'] === 'None') {
             $this->logger->error("Invalid profile data received from AlphaVantage for {$symbol}", ['response' => $data]);
             throw new \RuntimeException("Failed to retrieve valid company profile for {$symbol} from AlphaVantage.");
        }

         return [
            'symbol' => $data['Symbol'] ?? $symbol,
            'name' => $data['Name'], // Assume Name is essential
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
             // Add other fields as needed, defaulting to null/0 if not present
              'pegRatio' => (float)($data['PEGRatio'] ?? 0),
              'bookValue' => (float)($data['BookValue'] ?? 0),
              'dividendRate' => (float)($data['DividendPerShare'] ?? 0),
              'returnOnEquity' => (float)($data['ReturnOnEquityTTM'] ?? 0),
              'revenueGrowth' => (float)($data['QuarterlyRevenueGrowthYOY'] ?? 0),
              'operatingMargins' => (float)($data['OperatingMarginTTM'] ?? 0),
              'profitMargins' => (float)($data['ProfitMargin'] ?? 0),
              'currentPrice' => 0, // Get from getQuote ideally
              'targetMeanPrice' => (float)($data['AnalystTargetPrice'] ?? 0),
              'website' => '', // Not usually in AV overview
              'officers' => [], // Not available in AV overview
         ];
    }

    /**
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        $endpoint = '/query';
        $params = [
            'function' => 'GLOBAL_QUOTE',
            'symbol' => $symbol
        ];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        if (isset($data['Global Quote']) && !empty($data['Global Quote'])) {
            $quote = $data['Global Quote'];
             // Check if essential price data is present and not zero/empty
             if (empty($quote['05. price']) || $quote['05. price'] == 0) {
                  $this->logger->warning("Received empty or zero price in AlphaVantage quote for {$symbol}.", ['response' => $quote]);
                   // Decide: throw error or return structure with zeros? Let's throw for quote.
                   throw new \RuntimeException("Received invalid quote data (zero price) for {$symbol} from AlphaVantage.");
             }
            return [
                'symbol' => $quote['01. symbol'] ?? $symbol,
                'price' => (float)($quote['05. price']), // Assume price is essential
                'change' => (float)($quote['09. change'] ?? 0),
                'changePercent' => (float) str_replace('%', '', $quote['10. change percent'] ?? '0'),
                'volume' => (int)($quote['06. volume'] ?? 0),
                'latestTradingDay' => $quote['07. latest trading day'] ?? date('Y-m-d'),
                'previousClose' => (float)($quote['08. previous close'] ?? 0),
                'open' => (float)($quote['02. open'] ?? 0),
                'high' => (float)($quote['03. high'] ?? 0),
                'low' => (float)($quote['04. low'] ?? 0),
                 // Add required fields defaulting to 0/null
                 'marketCap' => 0,
                 'sharesOutstanding' => 0,
            ];
        }
         $this->logger->error("Invalid response structure from AlphaVantage GLOBAL_QUOTE", ['symbol' => $symbol, 'response' => $data]);
         throw new \RuntimeException("Invalid quote response structure for {$symbol} from AlphaVantage.");
    }

    /**
     * {@inheritdoc}
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $endpoint = '/query';
        $function = 'INCOME_STATEMENT'; // AV only has Income Statement easily accessible
        $params = ['function' => $function, 'symbol' => $symbol];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $financials = [];
        $reportKey = $period === 'quarterly' ? 'quarterlyReports' : 'annualReports';
        $limit = $period === 'quarterly' ? 4 : 3;

        if (isset($data[$reportKey]) && is_array($data[$reportKey])) {
            $reports = array_slice($data[$reportKey], 0, $limit);
            foreach ($reports as $report) {
                if (count(array_filter($report, fn($v) => $v !== 'None')) < 3) continue; // Skip mostly empty reports
                 $formattedReport = $this->formatFinancialReport($report, $symbol);
                 if ($formattedReport) {
                      $financials[] = $formattedReport;
                 }
            }
        } else {
            $this->logger->warning("No '{$reportKey}' found in AlphaVantage financial data response.", ['symbol' => $symbol, 'period' => $period]);
            // Don't throw, just return empty array if no reports found
        }
        return $financials;
    }

    /**
     * Format a financial report from Alpha Vantage data - unchanged from previous version
     */
    private function formatFinancialReport(array $report, string $symbol): ?array { /* ... same as before ... */
        $fiscalDate = $report['fiscalDateEnding'] ?? null; if (!$fiscalDate || $fiscalDate === 'None') return null;
        try { $date = new \DateTime($fiscalDate); $year = $date->format('Y'); $month = (int)$date->format('m'); } catch (\Exception $e) { return null; }
        if ($month <= 3) $quarter = 'Q1'; elseif ($month <= 6) $quarter = 'Q2'; elseif ($month <= 9) $quarter = 'Q3'; else $quarter = 'Q4';
        $getFloat = fn($key) => isset($report[$key]) && $report[$key] !== 'None' ? (float)$report[$key] : 0.0; $revenue = $getFloat('totalRevenue'); $netIncome = $getFloat('netIncome');
        return ['symbol' => $symbol, 'fiscalDate' => $fiscalDate, 'fiscalQuarter' => $quarter, 'fiscalYear' => $year, 'reportDate' => $fiscalDate, 'reportType' => 'Income Statement', 'currency' => $report['reportedCurrency'] ?? 'USD', 'revenue' => $revenue, 'costOfRevenue' => $getFloat('costOfRevenue'), 'grossProfit' => $getFloat('grossProfit'), 'netIncome' => $netIncome, 'operatingIncome' => $getFloat('operatingIncome'), 'ebitda' => $getFloat('ebitda'), 'eps' => $getFloat('reportedEPS'), 'researchAndDevelopment' => $getFloat('researchAndDevelopment'), 'sellingGeneralAdministrative' => $getFloat('sellingGeneralAndAdministrative'), 'operatingExpenses' => $getFloat('operatingExpenses'), 'interestExpense' => $getFloat('interestAndDebtExpense'), 'incomeTaxExpense' => $getFloat('incomeTaxExpense'), 'netIncomeFromContinuingOperations' => $getFloat('netIncomeFromContinuingOperations'), 'profitMargin' => ($revenue != 0) ? ($netIncome / $revenue) : 0, 'totalAssets' => 0, 'totalLiabilities' => 0, 'marketCap' => 0, 'peRatio' => 0, 'dividendYield' => 0];
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $endpoint = '/query';
        $params = ['function' => 'NEWS_SENTIMENT', 'tickers' => $symbol, 'limit' => $limit];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $news = [];
        if (isset($data['feed']) && is_array($data['feed'])) {
            foreach ($data['feed'] as $item) {
                $overallSentiment = isset($item['overall_sentiment_score']) ? (float) $item['overall_sentiment_score'] : 0;
                $news[] = [
                    'title' => $item['title'] ?? '',
                    'description' => $item['summary'] ?? '', // Use summary as description
                    'url' => $item['url'] ?? '',
                    'imageUrl' => $item['banner_image'] ?? null,
                    'source' => $item['source'] ?? 'Unknown',
                    'author' => $item['authors'][0] ?? 'Unknown',
                    'publishedAt' => isset($item['time_published']) ? $this->formatAvDate($item['time_published']) : date('Y-m-d H:i:s'),
                     'sentiment' => $overallSentiment,
                     'content' => '', // Not provided
                ];
            }
        }
        return $news;
    }

    /**
     * Format Alpha Vantage date string - unchanged
     */
    private function formatAvDate(string $avDate): string { /* ... same as before ... */
        try { $dateTime = \DateTime::createFromFormat('Ymd\THis', $avDate); return $dateTime ? $dateTime->format('Y-m-d H:i:s') : date('Y-m-d H:i:s'); } catch (\Exception $e) { return date('Y-m-d H:i:s'); }
     }

    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        $this->logger->warning('AlphaVantageClient does not support getExecutives method.');
        // throw new \BadMethodCallException('AlphaVantageClient does not support getExecutives method.');
         return []; // Return empty as no real call possible
    }

    /**
     * {@inheritdoc}
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $endpoint = '/query';
        $function = match ($interval) {
            'daily' => 'TIME_SERIES_DAILY_ADJUSTED',
            'weekly' => 'TIME_SERIES_WEEKLY_ADJUSTED',
            'monthly' => 'TIME_SERIES_MONTHLY_ADJUSTED',
            default => 'TIME_SERIES_DAILY_ADJUSTED',
        };
        $params = ['function' => $function, 'symbol' => $symbol, 'outputsize' => $outputSize, 'datatype' => 'json'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $timeSeriesKey = match ($interval) {
            'daily' => 'Time Series (Daily)',
            'weekly' => 'Weekly Adjusted Time Series',
            'monthly' => 'Monthly Adjusted Time Series',
            default => 'Time Series (Daily)',
        };
        $prices = [];
        if (isset($data[$timeSeriesKey]) && is_array($data[$timeSeriesKey])) {
             $previousClose = null;
            foreach ($data[$timeSeriesKey] as $date => $priceData) {
                  $currentClose = (float)($priceData['4. close'] ?? 0); $change = null; $changePercent = null;
                  if ($previousClose !== null) { $change = $currentClose - $previousClose; $changePercent = ($previousClose != 0) ? ($change / $previousClose) * 100 : 0; }
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
        } else {
             $this->logger->warning("No '{$timeSeriesKey}' found in AlphaVantage historical data response.", ['symbol' => $symbol, 'interval' => $interval]);
             // Don't throw, just return empty array if no data
        }
        return $prices;
    }
}
