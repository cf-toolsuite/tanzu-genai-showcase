<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * TradeFeeds API client - REAL Implementation
 * Provides access to company analyst ratings data
 */
class TradeFeedsApiClient extends AbstractApiClient implements AnalystRatingsApiClientInterface
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://data.tradefeeds.com/api/v1';

        // Check if the parameter exists at all (environment variable defined)
        if ($this->params->has('tradefeeds.api_key')) {
            $this->apiKey = $this->params->get('tradefeeds.api_key', '');

            if ($this->logger && empty($this->apiKey)) {
                $this->logger->warning('TradeFeedsApiClient initialized with empty API key');
            } else if ($this->logger) {
                $this->logger->info('TradeFeedsApiClient initialized with API Key');
            }
        } else {
            $this->apiKey = '';
            if ($this->logger) {
                $this->logger->info('TradeFeedsApiClient initialized without API key (environment variable not defined)');
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return ['key' => $this->apiKey];
    }


    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     */
    public function getAnalystRatings(string $symbol): array
    {
        // If no API key is available, return empty ratings with a specific message
        if (empty($this->apiKey)) {
            $this->logger->info("Cannot fetch analyst ratings: TradeFeeds API key not configured", ['symbol' => $symbol]);
            return $this->getEmptyRatingsStructure(
                "Analyst ratings are currently unavailable. To access this premium feature, please consider subscribing to TradeFeeds API services. Analyst ratings provide valuable insights into market sentiment and stock performance projections from financial experts."
            );
        }

        $endpoint = '/company_ratings';
        $params = ['ticker' => $symbol];

        try {
            // Make the API request
            $data = $this->request('GET', $endpoint, $params);

            // Check for successful response
            if (!isset($data['status']) || $data['status']['code'] !== '200') {
                $message = $data['status']['message'] ?? 'Unknown error';

                // Check if this is an authentication failure
                if (strpos(strtolower($message), 'auth') !== false ||
                    strpos(strtolower($message), 'key') !== false ||
                    strpos(strtolower($message), 'invalid') !== false) {
                    $this->logger->error("TradeFeeds API authentication error: {$message}", ['symbol' => $symbol]);
                    return $this->getEmptyRatingsStructure(
                        "Analyst ratings are currently unavailable due to an API authentication issue. Please verify your TradeFeeds API key is valid and active."
                    );
                }

                $this->logger->error("TradeFeeds API error: {$message}", ['symbol' => $symbol]);
                return $this->getEmptyRatingsStructure(
                    "Analyst ratings data is not available for this company at the moment. Please try again later."
                );
            }

            // Check if we have the expected output structure
            if (!isset($data['result']['output']['consensus'])) {
                $this->logger->warning("TradeFeeds API missing consensus data", ['symbol' => $symbol]);
                return $this->getEmptyRatingsStructure(
                    "No analyst ratings are currently available for this company."
                );
            }

            // Extract consensus data
            $consensusData = $data['result']['output']['consensus']['analyst_consensus'] ?? [];
            $consensus = [
                'consensusRating' => $consensusData['ratingconsensus'] ?? 'N/A',
                'averagePriceTarget' => (float)($consensusData['average_pricetarget'] ?? 0),
                'lowPriceTarget' => (float)($consensusData['low_pricetarget'] ?? 0),
                'highPriceTarget' => (float)($consensusData['high_pricetarget'] ?? 0),
                'buy' => (int)($consensusData['buy'] ?? 0),
                'hold' => (int)($consensusData['hold'] ?? 0),
                'sell' => (int)($consensusData['sell'] ?? 0),
                'upside' => 0 // We'll calculate this if we have price data
            ];

            // Extract individual analyst ratings
            $ratings = [];
            if (isset($data['result']['output']['consensus']['analysts'])) {
                $analysts = $data['result']['output']['consensus']['analysts'];

                // Handle both single analyst and array of analysts
                if (!isset($analysts[0]) && isset($analysts['name_analyst'])) {
                    $analysts = [$analysts];
                }

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
            }

            // Calculate upside percentage if we can
            if ($consensus['averagePriceTarget'] > 0) {
                try {
                    // Try to get current price from another service if available
                    // For now, we'll leave upside at 0 as it's calculated elsewhere
                } catch (\Exception $e) {
                    $this->logger->notice("Could not calculate price target upside: " . $e->getMessage());
                }
            }

            $result = [
                'ratings' => $ratings,
                'consensus' => $consensus
            ];

            $this->logger->info("Successfully retrieved analyst ratings from TradeFeeds", [
                'symbol' => $symbol,
                'ratings_count' => count($ratings)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logger->error("Error getting analyst ratings from TradeFeeds API: " . $e->getMessage(), ['symbol' => $symbol]);
            return $this->getEmptyRatingsStructure();
        }
    }

    /**
     * Get institutional ownership data for a company
     *
     * TradeFeeds API does not provide institutional ownership data, so this method returns an empty structure
     *
     * @param string $symbol The company symbol
     * @return array Empty institutional ownership data structure
     */
    public function getInstitutionalOwnership(string $symbol): array
    {
        $this->logger->info("TradeFeeds API does not provide institutional ownership data", ['symbol' => $symbol]);

        return [
            'message' => 'Institutional ownership data is not available through TradeFeeds API. Please use another data provider for this information.',
            'holders' => [],
            'totalPercentHeld' => 0,
            'totalHolders' => 0
        ];
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
}
