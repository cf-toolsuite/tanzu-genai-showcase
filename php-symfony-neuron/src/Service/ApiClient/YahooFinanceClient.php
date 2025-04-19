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
        $this->baseUrl = 'https://apidojo-yahoo-finance-v1.p.rapidapi.com';
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
                'X-RapidAPI-Host' => 'apidojo-yahoo-finance-v1.p.rapidapi.com'
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
        $endpoint = '/auto-complete';
        $params = ['q' => $term, 'quotesCount' => 10, 'newsCount' => 0];

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
                        // Add defaults for missing common fields
                        'region' => '',
                        'currency' => '',
                        'description' => '',
                        'sector' => '',
                        'industry' => '',
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
        $endpoint = '/stock/v2/get-summary';
        $params = ['symbol' => $symbol, 'region' => 'US', 'modules' => 'assetProfile,summaryDetail,financialData,price'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Format the data
        if (isset($data['quoteSummary']['result'][0])) {
            $result = $data['quoteSummary']['result'][0];
            $assetProfile = $result['assetProfile'] ?? [];
            $summaryDetail = $result['summaryDetail'] ?? [];
            $financialData = $result['financialData'] ?? [];
            $price = $result['price'] ?? [];

            // Check for essential data like name
            if (empty($assetProfile['longName']) && empty($price['shortName'])) {
                throw new \RuntimeException("Failed to retrieve valid company name for {$symbol} from Yahoo Finance.");
            }

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
                'officers' => $assetProfile['companyOfficers'] ?? [],
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
            ];
        }
        $this->logger->error("Invalid response structure from Yahoo Finance get-summary.", ['symbol' => $symbol]);
        throw new \RuntimeException("Invalid summary response structure for {$symbol} from Yahoo Finance.");
    }

    /**
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        $endpoint = '/stock/v2/get-summary';
        $params = ['symbol' => $symbol, 'region' => 'US', 'modules' => 'price,summaryDetail'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        if (isset($data['quoteSummary']['result'][0]['price'])) {
            $price = $data['quoteSummary']['result'][0]['price'];
            $summary = $data['quoteSummary']['result'][0]['summaryDetail'] ?? [];

            if (empty($price['regularMarketPrice']['raw'])) {
                throw new \RuntimeException("Received invalid quote data (zero price) for {$symbol} from Yahoo Finance.");
            }
            $changePercentRaw = $price['regularMarketChangePercent']['raw'] ?? 0;
            $changePercent = $changePercentRaw * 100;

            return [
                'symbol' => $price['symbol'] ?? $symbol,
                'price' => (float)($price['regularMarketPrice']['raw']),
                'change' => (float)($price['regularMarketChange']['raw'] ?? 0),
                'changePercent' => $changePercent,
                'volume' => (int)($price['regularMarketVolume']['raw'] ?? 0),
                'latestTradingDay' => $price['regularMarketTime']['fmt'] ?? date('Y-m-d H:i:s', $price['regularMarketTime']['raw'] ?? time()),
                'previousClose' => (float)($price['regularMarketPreviousClose']['raw'] ?? 0),
                'open' => (float)($price['regularMarketOpen']['raw'] ?? 0),
                'high' => (float)($price['regularMarketDayHigh']['raw'] ?? 0),
                'low' => (float)($price['regularMarketDayLow']['raw'] ?? 0),
                'marketCap' => (float)($summary['marketCap']['raw'] ?? 0),
                'sharesOutstanding' => (float)($price['sharesOutstanding']['raw'] ?? 0), // Note: Might be in different modules
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
        $endpoint = '/stock/v2/get-financials';
        $params = ['symbol' => $symbol, 'region' => 'US'];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing - same complex logic as before
        $financials = [];
        $seriesKey = $period === 'quarterly' ? 'quarterly' : 'annual';
        $metricsMap = ['TotalRevenue' => 'revenue', 'CostOfRevenue' => 'costOfRevenue', /* ... other mappings ... */ 'NetIncome' => 'netIncome', 'DilutedEPS' => 'eps'];
        $reportsByDate = [];
        // Determine which statement history to parse based on period (or fetch all needed)
        $statementKey = 'incomeStatementHistory'; // Could also be balanceSheetHistory, cashflowStatementHistory
        $historyKey = $seriesKey . 'IncomeStatementHistory'; // Adjust if parsing balance sheet etc.

        if (isset($data[$statementKey][$historyKey])) {
            foreach ($data[$statementKey][$historyKey] as $report) {
                $date = $report['endDate']['fmt'] ?? null;
                if (!$date) continue;
                foreach ($metricsMap as $yahooKey => $ourKey) {
                    if (isset($report[$yahooKey]['raw'])) $reportsByDate[$date][$ourKey] = (float)$report[$yahooKey]['raw'];
                }
                $reportsByDate[$date]['reportDate'] = $date;
            }
        } else {
            $this->logger->warning("Missing {$historyKey} data for {$symbol} ({$period}) in Yahoo response");
        }
        // Convert to final format - same logic as before
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
                $this->logger->error("Error processing financial date: {$date}", ['error' => $e->getMessage()]);
            }
        }
        usort($financials, fn($a, $b) => strtotime($b['reportDate']) - strtotime($a['reportDate']));
        return array_slice($financials, 0, 4);
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $endpoint = '/auto-complete'; // Using auto-complete for news seems more reliable
        $params = ['q' => $symbol, 'newsCount' => $limit, 'quotesCount' => 0];

        // Real API call
        $data = $this->request('GET', $endpoint, $params);

        // Processing
        $news = [];
        if (isset($data['news']) && is_array($data['news'])) {
            foreach ($data['news'] as $item) {
                $publishedTime = $item['provider_publish_time'] ?? null;
                $news[] = [
                    'title' => $item['title'] ?? '',
                    'description' => '', // Often missing in auto-complete
                    'url' => $item['link'] ?? '',
                    'imageUrl' => $item['thumbnail']['resolutions'][0]['url'] ?? null,
                    'source' => $item['publisher'] ?? 'Yahoo Finance',
                    'author' => '', // Not provided
                    'publishedAt' => $publishedTime ? date('Y-m-d H:i:s', $publishedTime) : date('Y-m-d H:i:s'),
                    'sentiment' => 0,
                    'content' => '',
                ];
                if (count($news) >= $limit) break;
            }
        } else {
            $this->logger->warning("No 'news' key found in Yahoo Finance auto-complete response.", ['symbol' => $symbol]);
        }
        return $news;
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        // Relies on getCompanyProfile to fetch the data
        $profileData = $this->getCompanyProfile($symbol); // This handles the real API call

        $executives = [];
        if (isset($profileData['officers']) && is_array($profileData['officers'])) {
            foreach ($profileData['officers'] as $officer) {
                $executives[] = [
                    'name' => $officer['name'] ?? 'Unknown',
                    'title' => $officer['title'] ?? 'Unknown',
                    // Add defaults for fields not directly available in Yahoo officer data
                    'age' => null,
                    'yearJoined' => null,
                    'bio' => '',
                    'compensation' => $officer['totalPay']['raw'] ?? 0,
                    'education' => '',
                    'previousCompanies' => '',
                ];
            }
        } else {
            $this->logger->warning("No 'officers' data found in Yahoo Finance profile for {$symbol}.");
        }
        return $executives;
    }

    /**
     * {@inheritdoc}
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $endpoint = '/stock/v3/get-chart';
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
        $params = ['symbol' => $symbol, 'range' => $range, 'region' => 'US', 'interval' => $yahooInterval, 'includePrePost' => 'false', 'events' => 'div|split'];

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
            if (empty($timestamps) || !isset($quoteData['open']) || count($quoteData['open']) !== $count || !isset($adjCloseData) || count($adjCloseData) !== $count) {
                $this->logger->error("Inconsistent data arrays in Yahoo Finance chart response.", ['symbol' => $symbol]);
                return [];
            }
            $timeZone = new \DateTimeZone('UTC');
            if (!empty($result['meta']['exchangeTimezoneName'])) {
                try {
                    $timeZone = new \DateTimeZone($result['meta']['exchangeTimezoneName']);
                } catch (\Exception $e) {
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
                $prices[] = ['date' => $date->format('Y-m-d'), 'open' => (float)($quoteData['open'][$i] ?? 0), 'high' => (float)($quoteData['high'][$i] ?? 0), 'low' => (float)($quoteData['low'][$i] ?? 0), 'close' => $currentClose, 'adjustedClose' => (float)($adjCloseData[$i] ?? $currentClose), 'volume' => (int)($quoteData['volume'][$i] ?? 0), 'change' => $change, 'changePercent' => $changePercent, 'dividend' => 0, 'split' => 1]; // Add dividend/split parsing if needed from 'events'
                $previousClose = $currentClose;
            }
        } else {
            $this->logger->warning("No chart results found in Yahoo Finance response.", ['symbol' => $symbol, 'interval' => $interval]);
        }
        return $prices;
    }
}
