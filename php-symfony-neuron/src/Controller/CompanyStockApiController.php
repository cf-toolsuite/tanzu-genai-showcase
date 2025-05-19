<?php

namespace App\Controller;

use App\Entity\Company;
use App\Service\StockDataService;
use App\Service\StockPriceDateHelper; // New service
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/api/company/{id}')] // ParamConverter will fetch Company by id
class CompanyStockApiController extends AbstractController
{
    private LoggerInterface $logger;
    private StockDataService $stockDataService;
    private StockPriceDateHelper $stockPriceDateHelper;

    public function __construct(
        LoggerInterface $logger,
        StockDataService $stockDataService,
        StockPriceDateHelper $stockPriceDateHelper
    ) {
        $this->logger = $logger;
        $this->stockDataService = $stockDataService;
        $this->stockPriceDateHelper = $stockPriceDateHelper;
    }

    #[Route('/latest-price', name: 'api_company_latest_price', methods: ['GET'])]
    public function getLatestPrice(Company $company): JsonResponse
    {
        try {
            $this->logger->info('API request for latest price', ['symbol' => $company->getTickerSymbol()]);
            $quote = $this->stockDataService->getStockQuote($company->getTickerSymbol());
            return $this->json([
                'success' => true,
                'price' => [
                    'symbol' => $quote['symbol'] ?? $company->getTickerSymbol(),
                    'price' => $quote['price'] ?? 0,
                    'change' => $quote['change'] ?? 0,
                    'changePercent' => $quote['changePercent'] ?? 0,
                    'volume' => $quote['volume'] ?? 0,
                    'open' => $quote['open'] ?? 0,
                    'high' => $quote['high'] ?? 0,
                    'low' => $quote['low'] ?? 0,
                    'previousClose' => $quote['previousClose'] ?? 0,
                    'timestamp' => (new \DateTime())->format('Y-m-d H:i:s')
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('API Error fetching latest price for ' . $company->getTickerSymbol() . ': ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Could not fetch latest price data: ' . $e->getMessage(),
                'errorCode' => 'API_ERROR'
            ], 500);
        }
    }

    #[Route('/historical-prices', name: 'api_company_historical_prices', methods: ['GET'])]
    public function getHistoricalPrices(Company $company, Request $request): JsonResponse
    {
        try {
            $interval = $request->query->get('interval', 'daily');
            $timeRange = $request->query->get('range', '1M');
            $forceRefresh = $request->query->getBoolean('refresh', false);

            $this->logger->info('API request for historical prices', [
                'symbol' => $company->getTickerSymbol(),
                'interval' => $interval,
                'range' => $timeRange,
                'refresh' => $forceRefresh ? 'yes' : 'no'
            ]);

            $endDate = new \DateTime();
            $startDate = $this->stockPriceDateHelper->calculateStartDate($endDate, $timeRange);

            $prices = $this->stockDataService->getHistoricalPrices(
                $company->getTickerSymbol(),
                $interval,
                $this->stockPriceDateHelper->getOutputSizeForTimeRange($timeRange),
                $forceRefresh
            );

            // If we got no data back, return an empty success response with a message
            if (empty($prices)) {
                $this->logger->warning('No price data returned from service for ' . $company->getTickerSymbol());
                return $this->json([
                    'success' => true,
                    'symbol' => $company->getTickerSymbol(),
                    'interval' => $interval,
                    'timeRange' => $timeRange,
                    'prices' => [],
                    'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s'),
                    'message' => 'No price data available for the selected range and interval.',
                    'status' => 'NO_DATA'
                ]);
            }

            $filteredPrices = array_filter($prices, function($price) use ($startDate) {
                if (!isset($price['date'])) return false;
                try {
                    $priceDate = new \DateTime($price['date']);
                    return $priceDate >= $startDate;
                } catch (\Exception $e) {
                    return false;
                }
            });
            $filteredPrices = array_values($filteredPrices);
            usort($filteredPrices, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));

            $this->logger->info('Returning historical prices', [
                'symbol' => $company->getTickerSymbol(),
                'totalPrices' => count($prices),
                'filteredPrices' => count($filteredPrices),
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d')
            ]);

            return $this->json([
                'success' => true,
                'symbol' => $company->getTickerSymbol(),
                'interval' => $interval,
                'timeRange' => $timeRange,
                'prices' => $filteredPrices,
                'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\LogicException $e) {
            // Special handling for configuration errors like missing API keys
            $this->logger->error('API configuration error: ' . $e->getMessage(), [
                'symbol' => $company->getTickerSymbol()
            ]);
            return $this->json([
                'success' => false,
                'message' => 'Stock data API is not properly configured. Please check API keys and settings.',
                'errorCode' => 'API_CONFIG_ERROR',
                'symbol' => $company->getTickerSymbol()
            ], 500);
        } catch (\RuntimeException $e) {
            // Special handling for connection/timeout errors
            if (strpos($e->getMessage(), 'timed out') !== false) {
                $this->logger->error('API timeout error: ' . $e->getMessage(), [
                    'symbol' => $company->getTickerSymbol()
                ]);
                return $this->json([
                    'success' => false,
                    'message' => 'Stock data request timed out. Please try again later.',
                    'errorCode' => 'API_TIMEOUT',
                    'symbol' => $company->getTickerSymbol()
                ], 504); // Gateway Timeout status
            }

            if (strpos($e->getMessage(), 'connect') !== false) {
                $this->logger->error('API connection error: ' . $e->getMessage(), [
                    'symbol' => $company->getTickerSymbol()
                ]);
                return $this->json([
                    'success' => false,
                    'message' => 'Could not connect to stock data provider. Please check your internet connection or try again later.',
                    'errorCode' => 'API_CONNECTION_ERROR',
                    'symbol' => $company->getTickerSymbol()
                ], 503); // Service Unavailable status
            }

            // Fall through for other runtime exceptions
            $this->logger->error('API runtime error: ' . $e->getMessage(), [
                'symbol' => $company->getTickerSymbol(),
                'exception' => get_class($e)
            ]);
            return $this->json([
                'success' => false,
                'message' => 'Stock data API encountered an error: ' . $e->getMessage(),
                'errorCode' => 'API_RUNTIME_ERROR',
                'symbol' => $company->getTickerSymbol()
            ], 500);
        } catch (\Exception $e) {
            // Generic fallback for all other exceptions
            $this->logger->error('API Error fetching historical prices: ' . $e->getMessage(), [
                'symbol' => $company->getTickerSymbol(),
                'exception' => get_class($e),
                'interval' => $interval ?? 'unknown',
                'timeRange' => $timeRange ?? 'unknown'
            ]);
            return $this->json([
                'success' => false,
                'message' => 'Could not fetch historical price data: ' . $e->getMessage(),
                'errorCode' => 'API_ERROR',
                'symbol' => $company->getTickerSymbol()
            ], 500);
        }
    }
}
