<?php

namespace App\Controller;

use App\Entity\Company;
use App\Service\StockDataService;
use App\Service\StockPriceDateHelper; // New service
use App\Service\ApiClient\StockClientsFactory; // Keep if used directly
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/company/{id}')] // Base route for company-specific data
class CompanyMarketDataController extends AbstractController
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

    #[Route('/news', name: 'company_news', methods: ['GET'])]
    public function news(
        Company $company,
        Request $request,
        StockClientsFactory $clientsFactory // Injected directly if only used here
    ): Response {
        $limit = $request->query->getInt('limit', 10);
        $refresh = $request->query->getBoolean('refresh', false);
        $companyNews = $this->stockDataService->getCompanyNews($company->getTickerSymbol(), $limit, $refresh);
        $marketNews = $this->stockDataService->getMarketNews(10, $refresh); // Fetch market news, limit to 10, allow refresh

        $titles = [];
        $duplicateCount = 0;
        foreach ($companyNews as $article) {
            $normalizedTitle = strtolower(trim($article['title']));
            if (in_array($normalizedTitle, $titles)) {
                $duplicateCount++;
            } else {
                $titles[] = $normalizedTitle;
            }
        }
        if ($duplicateCount > 0) {
            $this->addFlash('info', 'Duplicate articles were detected and filtered out.');
        }

        return $this->render('company/news.html.twig', [
            'company' => $company,
            'news' => $companyNews,
            'marketNews' => $marketNews, // Pass market news to the template
            'limit' => $limit,
            'days' => $request->query->getInt('days', 30), // 'days' seems unused in logic
            'refresh' => $refresh,
        ]);
    }

    #[Route('/additional-metrics', name: 'company_additional_metrics', methods: ['GET'])]
    #[Route('/esg', name: 'company_esg_dashboard', methods: ['GET'])] // Assuming ESG is part of additional metrics
    public function additionalMetrics(Company $company): Response
    {
        $esgData = []; // Placeholder
        $secFilings = []; // Placeholder

        $consensusData = $this->stockDataService->getAnalystConsensus($company->getTickerSymbol());
        $ratingsData = [];
        if ($consensusData['dataAvailable']) {
            try {
                $ratingsData = $this->stockDataService->getAnalystRatings($company->getTickerSymbol());
            } catch (\Exception $e) {
                $this->logger->warning('Could not retrieve detailed analyst ratings for additional metrics: ' . $e->getMessage());
                $this->addFlash('warning', 'Could not retrieve detailed analyst ratings data.');
            }
        }

        $insiderTransactions = [];
        try {
            $insiderTransactions = $this->stockDataService->getInsiderTrading($company->getTickerSymbol(), 5);
        } catch (\Exception $e) {
             $this->logger->info('Could not retrieve insider trading for additional metrics: ' . $e->getMessage());
        }

        $institutionalOwners = [];
        $totalInstitutionalOwnership = 0; // Initialize with default value
        try {
            $institutionalOwners = $this->stockDataService->getInstitutionalOwnership($company->getTickerSymbol(), 5);
            // Use the new method from StockDataService to calculate total institutional ownership
            $totalInstitutionalOwnership = $this->stockDataService->calculateTotalInstitutionalOwnership($company);
        } catch (\Exception $e) {
            $this->logger->info('Could not retrieve institutional ownership for additional metrics: ' . $e->getMessage());
        }

        return $this->render('company/additional_metrics.html.twig', [
            'company' => $company,
            'esgData' => $esgData,
            'analystRatings' => $ratingsData['ratings'] ?? [],
            'analystConsensus' => $consensusData,
            'secFilings' => $secFilings,
            'insiderTransactions' => $insiderTransactions,
            'institutionalOwners' => $institutionalOwners,
            'totalInstitutionalOwnership' => $totalInstitutionalOwnership
        ]);
    }

    #[Route('/analyst-ratings', name: 'company_analyst_ratings', methods: ['GET'])]
    #[Route('/analyst-coverage', name: 'company_analyst_coverage', methods: ['GET'])]
    public function analystRatings(Company $company): Response
    {
        $consensusData = $this->stockDataService->getAnalystConsensus($company->getTickerSymbol());
        $quote = $this->stockDataService->getStockQuote($company->getTickerSymbol());
        $ratings = [];

        if ($consensusData['dataAvailable']) {
            try {
                $ratingsData = $this->stockDataService->getAnalystRatings($company->getTickerSymbol());
                $ratings = $ratingsData['ratings'] ?? [];
            } catch (\Exception $e) {
                $this->logger->warning('Could not retrieve detailed analyst ratings data: ' . $e->getMessage());
                $this->addFlash('warning', 'Could not retrieve detailed analyst ratings data.');
            }
        } else {
            $this->addFlash('info', 'Analyst ratings data is not available for this company.');
        }

        return $this->render('company/analyst_ratings.html.twig', [ // or analyst_coverage.html.twig
            'company' => $company,
            'ratings' => $ratings,
            'consensus' => $consensusData,
            'currentPrice' => $quote['price'] ?? 0,
        ]);
    }

    #[Route('/insider-trading', name: 'company_insider_trading', methods: ['GET'])]
    #[Route('/insider-activity', name: 'company_insider_activity', methods: ['GET'])]
    public function insiderTrading(Company $company, Request $request): Response
    {
        $limit = $request->query->getInt('limit', 20);
        $dataAvailable = true;
        $insiderTransactions = [];
        $transactionTypes = [];
        $insiderNames = [];
        $currentType = $request->query->get('type');
        $currentInsider = $request->query->get('insider');

        try {
            $allTransactions = $this->stockDataService->getInsiderTrading($company->getTickerSymbol(), $limit); // Fetch more initially if filtering

            // Extract unique types and names *before* filtering for dropdowns
            foreach ($allTransactions as $transaction) {
                if (isset($transaction['transactions'][0]['transactionType'])) {
                    $typeCode = $transaction['transactions'][0]['transactionType'];
                    $transactionTypes[$typeCode] = $this->getTransactionTypeLabel($typeCode);
                }
                if (isset($transaction['ownerName'])) {
                    $insiderNames[$transaction['ownerName']] = $transaction['ownerName'];
                }
            }

            if ($currentType || $currentInsider) {
                $insiderTransactions = array_filter($allTransactions, function($transaction) use ($currentType, $currentInsider) {
                    $matchesType = !$currentType ||
                        (isset($transaction['transactions'][0]['transactionType']) &&
                         $this->matchesTransactionType($transaction['transactions'][0]['transactionType'], $currentType));
                    $matchesInsider = !$currentInsider ||
                        (isset($transaction['ownerName']) &&
                         stripos($transaction['ownerName'], $currentInsider) !== false);
                    return $matchesType && $matchesInsider;
                });
                $insiderTransactions = array_values($insiderTransactions);
            } else {
                $insiderTransactions = $allTransactions;
            }

        } catch (\Exception $e) {
            $this->logger->info('Insider trading data not available for ' . $company->getTickerSymbol() . ': ' . $e->getMessage());
            $dataAvailable = false;
            $this->addFlash('info', 'Insider trading data is not available for this company.');
        }

        $quote = $this->stockDataService->getStockQuote($company->getTickerSymbol());

        return $this->render('company/insider_activity.html.twig', [
            'company' => $company,
            'insiderTransactions' => $insiderTransactions,
            'currentPrice' => $quote['price'] ?? 0,
            'limit' => $limit,
            'dataAvailable' => $dataAvailable,
            'transactionTypes' => $transactionTypes,
            'insiderNames' => $insiderNames,
            'currentType' => $currentType,
            'currentInsider' => $currentInsider
        ]);
    }

    private function getTransactionTypeLabel(string $typeCode): string
    {
        return match(strtoupper($typeCode)) { // Ensure case-insensitivity for matching
            'P' => 'Purchase', 'S' => 'Sale', 'A' => 'Award/Grant',
            'D' => 'Disposition', 'O' => 'Other',
            default => ucfirst(strtolower($typeCode))
        };
    }

    private function matchesTransactionType(string $typeCode, string $filter): bool
    {
        // The filter might be the code 'P' or the label 'Purchase'
        if (strcasecmp($typeCode, $filter) === 0) return true;
        $label = $this->getTransactionTypeLabel($typeCode);
        return stripos($label, $filter) !== false;
    }

    #[Route('/stockprices', name: 'company_stockprices', methods: ['GET'])]
    public function stockprices(Company $company, Request $request): Response
    {
        $interval = $request->query->get('interval', 'daily');
        $timeRange = $request->query->get('range', '1M');
        $forceRefresh = $request->query->getBoolean('refresh', false);

        try {
            // If refresh param is set, show a flash message
            if ($forceRefresh) {
                $this->addFlash('info', 'Data refresh requested. Fetching latest stock prices...');
            }

            // Get quote data
            $quote = $this->stockDataService->getStockQuote($company->getTickerSymbol());

            // Check if we need to warn about missing API key
            if (isset($quote['marketState']) && $quote['marketState'] === 'ERROR') {
                $this->addFlash('warning', 'Unable to fetch current stock data. The API connection may not be configured correctly.');
            }

            $endDate = new \DateTime();
            $startDate = $this->stockPriceDateHelper->calculateStartDate($endDate, $timeRange);

            // Get historical price data
            $prices = $this->stockDataService->getHistoricalPrices(
                $company->getTickerSymbol(),
                $interval,
                $this->stockPriceDateHelper->getOutputSizeForTimeRange($timeRange),
                $forceRefresh
            );

            // Filter prices by date range
            $filteredPrices = [];
            if (!empty($prices)) {
                $filteredPrices = array_filter($prices, function($price) use ($startDate) {
                    try {
                        $priceDate = new \DateTime($price['date']);
                        return $priceDate >= $startDate;
                    } catch (\Exception $e) {
                        $this->logger->warning('Invalid date in price data: ' . ($price['date'] ?? 'N/A'));
                        return false;
                    }
                });
                $filteredPrices = array_values($filteredPrices); // Re-index
                usort($filteredPrices, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));
            }

            // Show a warning if we got no price data
            if (empty($filteredPrices)) {
                $this->addFlash(
                    'warning',
                    'No stock price data is available for ' . $company->getTickerSymbol() . ' with the selected settings.' .
                    ' Try a different time range or interval.'
                );
            }

            $enableRealTimeUpdates = $interval === 'daily' &&
                                    $timeRange === '1D' &&
                                    $this->stockPriceDateHelper->isMarketHours();

            return $this->render('company/stock_prices.html.twig', [
                'company' => $company,
                'prices' => $filteredPrices,
                'quote' => $quote,
                'interval' => $interval,
                'timeRange' => $timeRange,
                'enableRealTimeUpdates' => $enableRealTimeUpdates,
                'lastUpdated' => new \DateTime(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Error in stock prices view: ' . $e->getMessage(), [
                'symbol' => $company->getTickerSymbol(),
                'exception' => get_class($e)
            ]);

            $this->addFlash('danger', 'An error occurred while loading stock price data. Please try again later.');

            // Return the view with empty data
            return $this->render('company/stock_prices.html.twig', [
                'company' => $company,
                'prices' => [],
                'quote' => [
                    'price' => 0,
                    'change' => 0,
                    'changePercent' => 0,
                    'marketState' => 'ERROR'
                ],
                'interval' => $interval,
                'timeRange' => $timeRange,
                'enableRealTimeUpdates' => false,
                'lastUpdated' => new \DateTime(),
            ]);
        }
    }
}
