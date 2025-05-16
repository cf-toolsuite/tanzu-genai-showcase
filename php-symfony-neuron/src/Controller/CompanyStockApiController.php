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
            return $this->json(['success' => false, 'message' => 'Could not fetch latest price data'], 500);
        }
    }

    #[Route('/historical-prices', name: 'api_company_historical_prices', methods: ['GET'])]
    public function getHistoricalPrices(Company $company, Request $request): JsonResponse
    {
        try {
            $interval = $request->query->get('interval', 'daily');
            $timeRange = $request->query->get('range', '1M');
            $forceRefresh = $request->query->getBoolean('refresh', false);

            $endDate = new \DateTime();
            $startDate = $this->stockPriceDateHelper->calculateStartDate($endDate, $timeRange);

            $prices = $this->stockDataService->getHistoricalPrices(
                $company->getTickerSymbol(),
                $interval,
                $this->stockPriceDateHelper->getOutputSizeForTimeRange($timeRange),
                $forceRefresh
            );

            $filteredPrices = array_filter($prices, function($price) use ($startDate) {
                $priceDate = new \DateTime($price['date']);
                return $priceDate >= $startDate;
            });
            $filteredPrices = array_values($filteredPrices);
            usort($filteredPrices, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));

            return $this->json([
                'success' => true,
                'symbol' => $company->getTickerSymbol(),
                'interval' => $interval,
                'timeRange' => $timeRange,
                'prices' => $filteredPrices,
                'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('API Error fetching historical prices for ' . $company->getTickerSymbol() . ': ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Could not fetch historical price data'], 500);
        }
    }
}
