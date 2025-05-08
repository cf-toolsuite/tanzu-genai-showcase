<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Mock implementation of the TradeFeeds API Client for development/testing
 */
class MockTradeFeedsApiClient extends TradeFeedsApiClient
{
    /**
     * Override to disable real API initialization
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://mock.tradefeeds.local';
        $this->apiKey = 'mock-api-key';

        if ($this->logger) {
            $this->logger->info('Initialized MOCK TradeFeeds API client');
        }
    }

    /**
     * No auth parameters needed for mock
     */
    protected function getAuthParams(): array
    {
        return [];
    }

    /**
     * Override the request method to prevent real API calls
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        $this->logger->info("MOCK TradeFeeds API {$method} request to {$endpoint}", ['params' => $params]);

        // Return empty data for most endpoints
        if ($endpoint !== '/company_ratings') {
            return ['status' => ['code' => '200', 'message' => 'OK']];
        }

        // For ratings endpoint, return mock data based on the symbol
        $symbol = $params['ticker'] ?? 'UNKNOWN';
        return $this->getMockRatingsData($symbol);
    }

    /**
     * Get mock ratings data for a symbol
     *
     * @param string $symbol The ticker symbol to get mock data for
     * @param bool $includeMessage Whether to include a message (to test UI display)
     * @return array The mock ratings data
     */
    private function getMockRatingsData(string $symbol, bool $includeMessage = false): array
    {
        // Generate slightly different data for different symbols
        $buyCount = 5 + ord(substr($symbol, 0, 1)) % 5;  // 5-9 based on first letter
        $holdCount = 3 + ord(substr($symbol, -1)) % 4;   // 3-6 based on last letter
        $sellCount = max(1, ord(substr($symbol, 1, 1)) % 3); // 0-2 based on second letter

        $totalAnalysts = $buyCount + $holdCount + $sellCount;
        $avgPrice = 100 + (ord(substr($symbol, 0, 1)) * 2); // Base price derived from symbol

        // Mock consensus data
        $consensus = [
            'ratingconsensus' => $buyCount > ($holdCount + $sellCount) ? 'Buy' : ($sellCount > $buyCount ? 'Sell' : 'Hold'),
            'average_pricetarget' => number_format($avgPrice, 2),
            'high_pricetarget' => number_format($avgPrice * 1.2, 2),
            'low_pricetarget' => number_format($avgPrice * 0.8, 2),
            'number_pricetarget' => (string)$totalAnalysts,
            'buy' => (string)$buyCount,
            'hold' => (string)$holdCount,
            'sell' => (string)$sellCount,
            'date' => date('Y-m-d')
        ];

        // Generate mock analysts (at least one)
        $analysts = [];
        $firms = [
            'Morgan Stanley', 'Goldman Sachs', 'JPMorgan', 'Bank of America',
            'Wells Fargo', 'Citigroup', 'UBS', 'Deutsche Bank', 'Barclays',
            'Credit Suisse', 'Jefferies', 'Piper Sandler', 'Wedbush'
        ];

        $ratings = ['Buy', 'Overweight', 'Outperform', 'Hold', 'Neutral', 'Equal-Weight', 'Underperform', 'Sell'];
        $changes = ['maintained', 'upgraded', 'downgraded', 'initiated'];

        // Add 3-5 analysts
        $analystCount = 3 + (ord(substr($symbol, 0, 1)) % 3);
        for ($i = 0; $i < $analystCount; $i++) {
            $firm = $firms[array_rand($firms)];
            $analystLastName = substr(md5($firm . $i), 0, 6); // Generate a pseudorandom analyst name

            $ratingIndex = array_rand($ratings);
            $rating = $ratings[$ratingIndex];

            // Higher price targets for buy ratings, lower for sell
            $priceTarget = $avgPrice;
            if (in_array($rating, ['Buy', 'Overweight', 'Outperform'])) {
                $priceTarget += rand(5, 15);
            } elseif (in_array($rating, ['Underperform', 'Sell'])) {
                $priceTarget -= rand(5, 15);
            }

            $analysts[] = [
                'name_analyst' => 'John ' . ucfirst($analystLastName),
                'firm' => $firm,
                'analyst_role' => 'analyst',
                'rating' => [
                    'date_rating' => date('Y-m-d', strtotime('-' . rand(1, 30) . ' days')),
                    'target_date' => date('Y-m-d', strtotime('+1 year')),
                    'price_target' => (string)$priceTarget,
                    'rating' => $rating,
                    'change' => $changes[array_rand($changes)]
                ]
            ];
        }

        // Return structured mock data
        $result = [
            'status' => [
                'code' => '200',
                'message' => 'OK',
                'details' => ''
            ],
            'result' => [
                'basics' => [
                    'company' => $this->getCompanyNameForSymbol($symbol),
                    'ticker' => $symbol
                ],
                'output' => [
                    'consensus' => [
                        'analyst_consensus' => $consensus,
                        'analysts' => $analysts
                    ]
                ]
            ]
        ];

        // Optional: include a test message to verify UI handling
        if ($includeMessage) {
            $result['status']['message'] = 'This is a mock message for testing';
        }

        return $result;
    }

    /**
     * Get a mock company name for a symbol
     */
    private function getCompanyNameForSymbol(string $symbol): string
    {
        $companyNames = [
            'AAPL' => 'Apple Inc.',
            'MSFT' => 'Microsoft Corporation',
            'GOOGL' => 'Alphabet Inc.',
            'AMZN' => 'Amazon.com, Inc.',
            'META' => 'Meta Platforms, Inc.',
            'TSLA' => 'Tesla, Inc.',
            'NVDA' => 'NVIDIA Corporation',
            'AVGO' => 'Broadcom Inc.',
            'ADBE' => 'Adobe Inc.',
            'NFLX' => 'Netflix, Inc.'
        ];

        return $companyNames[$symbol] ?? $symbol . ' Corporation';
    }

    /**
     * Get empty ratings structure for error handling
     *
     * @param string|null $message Specific message explaining why ratings are unavailable
     * @return array Empty ratings structure with optional message
     */
    private function getEmptyRatingsStructure(?string $message = null): array
    {
        $result = [
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

        if ($message) {
            $result['message'] = $message;
        }

        return $result;
    }

    /**
     * Override to return mock analyst ratings
     */
    public function getAnalystRatings(string $symbol): array
    {
        $this->logger->info("Getting MOCK analyst ratings for {$symbol}");

        // Simulate API key not set in 15% of cases to test error handling
        $requestId = rand(1, 100);
        if ($requestId <= 15) {
            // Simulate missing API key
            $this->logger->info("Simulating missing API key for MOCK TradeFeeds API");
            return $this->getEmptyRatingsStructure(
                "Analyst ratings are currently unavailable. To access this premium feature, please consider subscribing to TradeFeeds API services. Analyst ratings provide valuable insights into market sentiment and stock performance projections from financial experts."
            );
        } else if ($requestId <= 30) {
            // Simulate authentication failure with invalid key
            $this->logger->info("Simulating authentication failure for MOCK TradeFeeds API");
            return $this->getEmptyRatingsStructure(
                "Analyst ratings are currently unavailable due to an API authentication issue. Please verify your TradeFeeds API key is valid and active."
            );
        } else if ($requestId <= 40) {
            // Simulate no ratings available for this symbol
            $this->logger->info("Simulating no ratings available for {$symbol}");
            return $this->getEmptyRatingsStructure(
                "No analyst ratings are currently available for this company."
            );
        }

        // Create mock data response
        $response = $this->getMockRatingsData($symbol);

        // Transform to the format expected by the UI, like the parent would do
        $analysts = $response['result']['output']['consensus']['analysts'] ?? [];
        $consensusData = $response['result']['output']['consensus']['analyst_consensus'] ?? [];

        // Handle both single analyst and array of analysts
        if (!isset($analysts[0]) && isset($analysts['name_analyst'])) {
            $analysts = [$analysts];
        }

        // Extract individual analyst ratings
        $ratings = [];
        foreach ($analysts as $analyst) {
            if (!isset($analyst['rating'])) continue;

            $ratingData = $analyst['rating'];
            $change = $ratingData['change'] ?? '';

            $ratings[] = [
                'firm' => $analyst['firm'] ?? 'Unknown Firm',
                'analystName' => $analyst['name_analyst'] ?? '',
                'rating' => $ratingData['rating'] ?? 'N/A',
                'previousRating' => $change === 'maintained' ? null : 'N/A', // Previous rating not directly provided
                'date' => $ratingData['date_rating'] ?? date('Y-m-d'),
                'priceTarget' => (float)($ratingData['price_target'] ?? 0)
            ];
        }

        // Transform consensus data
        $consensus = [
            'consensusRating' => $consensusData['ratingconsensus'] ?? 'N/A',
            'averagePriceTarget' => (float)($consensusData['average_pricetarget'] ?? 0),
            'lowPriceTarget' => (float)($consensusData['low_pricetarget'] ?? 0),
            'highPriceTarget' => (float)($consensusData['high_pricetarget'] ?? 0),
            'buy' => (int)($consensusData['buy'] ?? 0),
            'hold' => (int)($consensusData['hold'] ?? 0),
            'sell' => (int)($consensusData['sell'] ?? 0),
            'upside' => 0 // Could calculate this if we had current price
        ];

        return [
            'ratings' => $ratings,
            'consensus' => $consensus
        ];
    }
}
