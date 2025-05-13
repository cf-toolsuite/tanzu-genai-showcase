<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ResearchReport;
use App\Form\CompanySearchType;
use App\Form\CompanyType;
use App\Form\ResearchReportType;
use App\Repository\CompanyRepository;
use App\Repository\ResearchReportRepository;
use App\Service\HunterService;
use App\Service\NeuronAiService;
use App\Service\ReportExportService;
use App\Service\StockDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/company')]
class CompanyController extends AbstractController
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    #[Route('/', name: 'company_index', methods: ['GET'])]
    public function index(CompanyRepository $companyRepository): Response
    {
        return $this->render('company/index.html.twig', [
            'companies' => $companyRepository->findAll(),
        ]);
    }

    #[Route('/search', name: 'company_search', methods: ['GET', 'POST'])]
    public function search(Request $request, CompanyRepository $companyRepository, StockDataService $stockDataService): Response
    {
        $dbResults = [];
        $apiResults = [];
        $searchTerm = $request->query->get('searchTerm');

        if ($searchTerm) {
            // First search in local database
            $dbResults = $companyRepository->findBySearchCriteria($searchTerm);

            // If we have any database results, collect their ticker symbols and company names
            $existingSymbols = [];
            $existingNames = [];
            foreach ($dbResults as $company) {
                $existingSymbols[] = strtolower($company->getTickerSymbol() ?? '');
                $existingNames[] = strtolower($company->getName() ?? '');
            }

            // Get external API results
            $apiResults = [];
            try {
                // Search in external APIs
                $allApiResults = $stockDataService->searchCompanies($searchTerm);

                // Filter out companies that already exist in DB results by ticker symbol or similar name
                $filteredApiResults = array_filter($allApiResults, function($result) use ($existingSymbols, $existingNames) {
                    // Check if ticker symbol already exists (case-insensitive)
                    if (in_array(strtolower($result['symbol'] ?? ''), $existingSymbols)) {
                        return false;
                    }

                    // Check for similar company names (case-insensitive)
                    $normalizedName = strtolower($result['name'] ?? '');
                    foreach ($existingNames as $existingName) {
                        // If the name is very similar or contained within each other
                        if ($existingName === $normalizedName ||
                            strpos($existingName, $normalizedName) !== false ||
                            strpos($normalizedName, $existingName) !== false) {
                            return false;
                        }
                    }

                    return true;
                });

                // Group results by company name to avoid duplicates across providers
                $resultsByName = [];
                foreach ($filteredApiResults as $result) {
                    $normalizedName = strtolower($result['name'] ?? 'Unknown');

                    if (!isset($resultsByName[$normalizedName])) {
                        $resultsByName[$normalizedName] = [];
                    }
                    $resultsByName[$normalizedName][] = $result;
                }

                // Take only the first result for each unique company name
                foreach ($resultsByName as $normalizedName => $results) {
                    if (!empty($results)) {
                        $apiResults[] = $results[0]; // Add only the first result for each unique company name
                    }
                }

            } catch (\Exception $e) {
                $this->logger->error('Error fetching external search results: ' . $e->getMessage());
                // Log error but continue with DB results
                $this->addFlash('warning', 'Could not fetch additional results from external sources');
            }
        }

        return $this->render('company/search.html.twig', [
            'dbResults' => $dbResults,
            'apiResults' => $apiResults,
            'searchTerm' => $searchTerm,
        ]);
    }

    #[Route('/import/{symbol}', name: 'company_import', methods: ['POST'])]
    public function importFromApi(string $symbol, StockDataService $stockDataService): Response
    {
        try {
            $company = $stockDataService->importCompany($symbol);
            $this->addFlash('success', 'Company successfully imported: ' . $company->getName());

            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error importing company: ' . $e->getMessage());

            return $this->redirectToRoute('company_search');
        }
    }

    #[Route('/new', name: 'company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, NeuronAiService $neuronAiService): Response
    {
        $company = new Company();
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $companyName = $company->getName();

            // Auto-generate company details using Neuron AI
            try {
                if ($request->request->get('use_ai') === 'yes') {
                    $companyInfo = $neuronAiService->generateCompanyInfo($companyName);

                    if (!isset($companyInfo['error'])) {
                        $company->setIndustry($companyInfo['industry'] ?? $company->getIndustry());
                        $company->setSector($companyInfo['sector'] ?? $company->getSector());
                        $company->setHeadquarters($companyInfo['headquarters'] ?? $company->getHeadquarters());
                        $company->setDescription($companyInfo['description'] ?? $company->getDescription());
                    }
                }
            } catch (\Exception $e) {
                $this->addFlash('warning', 'AI enhancement failed, but company was created with provided information.');
            }

            $company->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->persist($company);
            $entityManager->flush();

            $this->addFlash('success', 'Company created successfully.');

            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/new.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'company_show', methods: ['GET'])]
    public function show(Company $company): Response
    {
        return $this->render('company/show.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/edit', name: 'company_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Company $company, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $company->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Company updated successfully.');

            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/edit.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/financial', name: 'company_financial', methods: ['GET'])]
    public function financial(Company $company): Response
    {
        return $this->render('company/financial.html.twig', [
            'company' => $company,
            'financialData' => $company->getFinancialData(),
        ]);
    }

    #[Route('/{id}/news', name: 'company_news', methods: ['GET'])]
    public function news(
        Company $company,
        Request $request,
        StockDataService $stockDataService,
        \App\Service\ApiClient\StockClientsFactory $clientsFactory
    ): Response
    {
        $limit = $request->query->getInt('limit', 10);
        $days = $request->query->getInt('days', 30);

        // Force cache refresh if requested
        $refresh = $request->query->getBoolean('refresh', false);

        // Get news from the service, force refresh if requested
        $companyNews = $stockDataService->getCompanyNews($company->getTickerSymbol(), $limit, $refresh);

        // Get business headlines for comparison/context
        $marketNews = [];
        try {
            $newsApiClient = $clientsFactory->getNewsApiClient();
            if ($newsApiClient) {
                $marketNews = $newsApiClient->getTopHeadlines('business', 'us', 5);
            }
        } catch (\Exception $e) {
            $this->addFlash('warning', 'Could not retrieve market headlines: ' . $e->getMessage());
        }

        // Check for duplicate titles and add a note if found
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

        // If there were duplicates before our deduplication logic, show a message
        if ($duplicateCount > 0) {
            $this->addFlash('info', 'Duplicate articles were detected and filtered out.');
        }

        return $this->render('company/news.html.twig', [
            'company' => $company,
            'news' => $companyNews,
            'marketNews' => $marketNews,
            'limit' => $limit,
            'days' => $days,
            'refresh' => $refresh,
        ]);
    }

    #[Route('/{id}/additional-metrics', name: 'company_additional_metrics', methods: ['GET'])]
    public function additionalMetrics(Company $company, Request $request, StockDataService $stockDataService): Response
    {
        // Get ESG data
        $esgData = []; // This would come from an ESG data service

        // Get analyst ratings
        $consensusData = $stockDataService->getAnalystConsensus($company->getTickerSymbol());
        $ratingsData = [];
        if ($consensusData['dataAvailable']) {
            try {
                $ratingsData = $stockDataService->getAnalystRatings($company->getTickerSymbol());
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Could not retrieve detailed analyst ratings data.');
            }
        }

        // Get SEC filings
        $secFilings = []; // This would come from a SEC filings service

        // Get insider transactions
        $insiderTransactions = [];
        try {
            $insiderTransactions = $stockDataService->getInsiderTrading($company->getTickerSymbol(), 5);
        } catch (\Exception $e) {
            // Log but continue
        }

        // Get institutional ownership
        $institutionalOwners = [];
        $totalInstitutionalOwnership = 0;
        try {
            $institutionalOwners = $stockDataService->getInstitutionalOwnership($company->getTickerSymbol(), 5);
            // Calculate total institutional ownership percentage
            $quote = $stockDataService->getStockQuote($company->getTickerSymbol());
            $sharesOutstanding = $quote['sharesOutstanding'] ?? 0;
            $totalShares = 0;
            foreach ($institutionalOwners as $institution) {
                $totalShares += $institution['sharesHeld'] ?? 0;
            }
            if ($sharesOutstanding > 0) {
                $totalInstitutionalOwnership = ($totalShares / $sharesOutstanding) * 100;
            }
        } catch (\Exception $e) {
            // Log but continue
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

    #[Route('/{id}/esg', name: 'company_esg_dashboard', methods: ['GET'])]
    public function esg(Company $company, Request $request, StockDataService $stockDataService): Response
    {
        // Get ESG data
        $esgData = []; // This would come from an ESG data service

        // Get analyst ratings
        $consensusData = $stockDataService->getAnalystConsensus($company->getTickerSymbol());
        $ratingsData = [];
        if ($consensusData['dataAvailable']) {
            try {
                $ratingsData = $stockDataService->getAnalystRatings($company->getTickerSymbol());
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Could not retrieve detailed analyst ratings data.');
            }
        }

        // Get SEC filings
        $secFilings = []; // This would come from a SEC filings service

        // Get insider transactions
        $insiderTransactions = [];
        try {
            $insiderTransactions = $stockDataService->getInsiderTrading($company->getTickerSymbol(), 5);
        } catch (\Exception $e) {
            // Log but continue
        }

        // Get institutional ownership
        $institutionalOwners = [];
        $totalInstitutionalOwnership = 0;
        try {
            $institutionalOwners = $stockDataService->getInstitutionalOwnership($company->getTickerSymbol(), 5);
            // Calculate total institutional ownership percentage
            $quote = $stockDataService->getStockQuote($company->getTickerSymbol());
            $sharesOutstanding = $quote['sharesOutstanding'] ?? 0;
            $totalShares = 0;
            foreach ($institutionalOwners as $institution) {
                $totalShares += $institution['sharesHeld'] ?? 0;
            }
            if ($sharesOutstanding > 0) {
                $totalInstitutionalOwnership = ($totalShares / $sharesOutstanding) * 100;
            }
        } catch (\Exception $e) {
            // Log but continue
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

    #[Route('/{id}/analyst-ratings', name: 'company_analyst_ratings', methods: ['GET'])]
    public function analystRatings(Company $company, Request $request, StockDataService $stockDataService): Response
    {
        // Get consensus data which includes dataAvailable flag
        $consensusData = $stockDataService->getAnalystConsensus($company->getTickerSymbol());

        // Get current stock price for context
        $quote = $stockDataService->getStockQuote($company->getTickerSymbol());

        // Only fetch detailed ratings if data is available
        $ratings = [];
        if ($consensusData['dataAvailable']) {
            try {
                $ratingsData = $stockDataService->getAnalystRatings($company->getTickerSymbol());
                $ratings = $ratingsData['ratings'] ?? [];
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Could not retrieve detailed analyst ratings data.');
            }
        } else {
            $this->addFlash('info', 'Analyst ratings data is not available for this company through the current data provider.');
        }

        return $this->render('company/analyst_ratings.html.twig', [
            'company' => $company,
            'ratings' => $ratings,
            'consensus' => $consensusData,
            'currentPrice' => $quote['price'] ?? 0,
        ]);
    }

    #[Route('/{id}/insider-trading', name: 'company_insider_trading', methods: ['GET'])]
    #[Route('/{id}/insider-activity', name: 'company_insider_activity', methods: ['GET'])]
    public function insiderTrading(Company $company, Request $request, StockDataService $stockDataService): Response
    {
        $limit = $request->query->getInt('limit', 20);
        $dataAvailable = true;
        $insiderTransactions = [];

        // Get filter parameters from request
        $currentType = $request->query->get('type');
        $currentInsider = $request->query->get('insider');

        try {
            // Get insider trading data
            $insiderTransactions = $stockDataService->getInsiderTrading($company->getTickerSymbol(), $limit);

            // Apply filters if specified
            if ($currentType || $currentInsider) {
                $insiderTransactions = array_filter($insiderTransactions, function($transaction) use ($currentType, $currentInsider) {
                    $matchesType = !$currentType ||
                        (isset($transaction['transactions'][0]['transactionType']) &&
                         $this->matchesTransactionType($transaction['transactions'][0]['transactionType'], $currentType));

                    $matchesInsider = !$currentInsider ||
                        (isset($transaction['ownerName']) &&
                         stripos($transaction['ownerName'], $currentInsider) !== false);

                    return $matchesType && $matchesInsider;
                });

                // Re-index array after filtering
                $insiderTransactions = array_values($insiderTransactions);
            }

            // Extract unique transaction types for filter dropdown
            $transactionTypes = [];
            foreach ($insiderTransactions as $transaction) {
                if (isset($transaction['transactions'][0]['transactionType'])) {
                    $type = $this->getTransactionTypeLabel($transaction['transactions'][0]['transactionType']);
                    $transactionTypes[$transaction['transactions'][0]['transactionType']] = $type;
                }
            }

            // Extract unique insider names for filter dropdown
            $insiderNames = [];
            foreach ($insiderTransactions as $transaction) {
                if (isset($transaction['ownerName'])) {
                    $insiderNames[$transaction['ownerName']] = $transaction['ownerName'];
                }
            }

        } catch (\Exception $e) {
            $dataAvailable = false;
            $this->addFlash('info', 'Insider trading data is not available for this company through the current data provider.');
        }

        // Get current stock quote for context
        $quote = $stockDataService->getStockQuote($company->getTickerSymbol());

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

    #[Route('/{id}/sec-filings', name: 'company_sec_filings', methods: ['GET'])]
    public function secFilings(Company $company): Response
    {
        return $this->render('company/sec_filings.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/analyst-coverage', name: 'company_analyst_coverage', methods: ['GET'])]
    public function analystCoverage(Company $company, Request $request, StockDataService $stockDataService): Response
    {
        // Get consensus data which includes dataAvailable flag
        $consensusData = $stockDataService->getAnalystConsensus($company->getTickerSymbol());

        // Get current stock price for context
        $quote = $stockDataService->getStockQuote($company->getTickerSymbol());

        // Only fetch detailed ratings if data is available
        $ratings = [];
        if ($consensusData['dataAvailable']) {
            try {
                $ratingsData = $stockDataService->getAnalystRatings($company->getTickerSymbol());
                $ratings = $ratingsData['ratings'] ?? [];
            } catch (\Exception $e) {
                $this->addFlash('warning', 'Could not retrieve detailed analyst ratings data.');
            }
        } else {
            $this->addFlash('info', 'Analyst ratings data is not available for this company through the current data provider.');
        }

        return $this->render('company/analyst_coverage.html.twig', [
            'company' => $company,
            'ratings' => $ratings,
            'consensus' => $consensusData,
            'currentPrice' => $quote['price'] ?? 0,
        ]);
    }

    #[Route('/{id}/leadership', name: 'company_leadership', methods: ['GET'])]
    public function leadership(Company $company): Response
    {
        return $this->render('company/leadership.html.twig', [
            'company' => $company,
            'executives' => $company->getExecutiveProfiles(),
        ]);
    }

    /**
     * Helper method to get a human-readable transaction type label
     */
    private function getTransactionTypeLabel(string $typeCode): string
    {
        return match($typeCode) {
            'P' => 'Purchase',
            'S' => 'Sale',
            'A' => 'Award/Grant',
            'D' => 'Disposition',
            'O' => 'Other',
            default => ucfirst(strtolower($typeCode))
        };
    }

    /**
     * Helper method to check if a transaction type matches a filter
     */
    private function matchesTransactionType(string $typeCode, string $filter): bool
    {
        $label = $this->getTransactionTypeLabel($typeCode);
        return stripos($label, $filter) !== false;
    }

    #[Route('/{id}/stockprices', name: 'company_stockprices', methods: ['GET'])]
    public function stockprices(Company $company, Request $request, StockDataService $stockDataService): Response
    {
        $interval = $request->query->get('interval', 'daily');
        $timeRange = $request->query->get('range', '1M'); // Default to 1 month
        $forceRefresh = $request->query->getBoolean('refresh', false);

        // Get current stock quote for real-time price display
        $quote = $stockDataService->getStockQuote($company->getTickerSymbol());

        // Calculate date range based on selected time range
        $endDate = new \DateTime();
        $startDate = $this->calculateStartDate($endDate, $timeRange);

        // Get historical prices with appropriate caching
        $prices = $stockDataService->getHistoricalPrices(
            $company->getTickerSymbol(),
            $interval,
            $this->getOutputSizeForTimeRange($timeRange),
            $forceRefresh
        );

        // Filter prices based on the selected time range
        $filteredPrices = array_filter($prices, function($price) use ($startDate) {
            $priceDate = new \DateTime($price['date']);
            return $priceDate >= $startDate;
        });

        // For intraday data during market hours, set up for real-time updates
        $enableRealTimeUpdates = $interval === 'daily' &&
                                $timeRange === '1D' &&
                                $this->isMarketHours();

        return $this->render('company/stockprices.html.twig', [
            'company' => $company,
            'prices' => array_values($filteredPrices), // Re-index array after filtering
            'quote' => $quote,
            'interval' => $interval,
            'timeRange' => $timeRange,
            'enableRealTimeUpdates' => $enableRealTimeUpdates,
            'lastUpdated' => new \DateTime(),
        ]);
    }

    /**
     * Calculate start date based on time range
     */
    private function calculateStartDate(\DateTime $endDate, string $timeRange): \DateTime
    {
        $startDate = clone $endDate;

        switch ($timeRange) {
            case '1D':
                $startDate->modify('-1 day');
                break;
            case '5D':
                $startDate->modify('-5 days');
                break;
            case '1M':
                $startDate->modify('-1 month');
                break;
            case '3M':
                $startDate->modify('-3 months');
                break;
            case '6M':
                $startDate->modify('-6 months');
                break;
            case '1Y':
                $startDate->modify('-1 year');
                break;
            case '5Y':
                $startDate->modify('-5 years');
                break;
            case 'MAX':
                $startDate->modify('-10 years'); // Use 10 years as "MAX"
                break;
            default:
                $startDate->modify('-1 month'); // Default to 1 month
        }

        return $startDate;
    }

    /**
     * Get output size parameter for API calls based on time range
     */
    private function getOutputSizeForTimeRange(string $timeRange): string
    {
        // For longer time ranges, use 'full' output size
        return in_array($timeRange, ['1Y', '5Y', 'MAX']) ? 'full' : 'compact';
    }

    /**
     * Check if current time is during market hours (9:30 AM - 4:00 PM ET, Monday-Friday)
     */
    private function isMarketHours(): bool
    {
        // Convert to Eastern Time (ET) where US markets operate
        $easternTz = new \DateTimeZone('America/New_York');
        $now = new \DateTime();
        $now->setTimezone($easternTz);

        // Get day of week (0 = Sunday, 6 = Saturday)
        $dayOfWeek = (int)$now->format('w');

        // Check if it's a weekday
        if ($dayOfWeek === 0 || $dayOfWeek === 6) {
            return false;
        }

        // Get current hour and minute in ET
        $hour = (int)$now->format('G');
        $minute = (int)$now->format('i');
        $timeInMinutes = ($hour * 60) + $minute;

        // Market hours: 9:30 AM - 4:00 PM ET
        $marketOpen = (9 * 60) + 30;  // 9:30 AM
        $marketClose = (16 * 60);     // 4:00 PM

        return $timeInMinutes >= $marketOpen && $timeInMinutes <= $marketClose;
    }

    /**
     * API endpoint to get the latest stock price for real-time updates
     */
    #[Route('/api/company/{id}/latest-price', name: 'api_company_latest_price', methods: ['GET'])]
    public function getLatestPrice(Company $company, StockDataService $stockDataService): JsonResponse
    {
        try {
            // Use a very short cache (30 seconds) for the latest price
            $quote = $stockDataService->getStockQuote($company->getTickerSymbol());

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
            $this->logger->error('Error fetching latest price data: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Could not fetch latest price data'
            ], 500);
        }
    }

    /**
     * API endpoint to get historical price data for interactive charts
     */
    #[Route('/api/company/{id}/historical-prices', name: 'api_company_historical_prices', methods: ['GET'])]
    public function getHistoricalPrices(
        Company $company,
        Request $request,
        StockDataService $stockDataService
    ): JsonResponse
    {
        try {
            $interval = $request->query->get('interval', 'daily');
            $timeRange = $request->query->get('range', '1M');
            $forceRefresh = $request->query->getBoolean('refresh', false);

            // Calculate date range based on selected time range
            $endDate = new \DateTime();
            $startDate = $this->calculateStartDate($endDate, $timeRange);

            // Get historical prices with appropriate caching
            $prices = $stockDataService->getHistoricalPrices(
                $company->getTickerSymbol(),
                $interval,
                $this->getOutputSizeForTimeRange($timeRange),
                $forceRefresh
            );

            // Filter prices based on the selected time range
            $filteredPrices = array_filter($prices, function($price) use ($startDate) {
                $priceDate = new \DateTime($price['date']);
                return $priceDate >= $startDate;
            });

            return $this->json([
                'success' => true,
                'symbol' => $company->getTickerSymbol(),
                'interval' => $interval,
                'timeRange' => $timeRange,
                'prices' => array_values($filteredPrices), // Re-index array after filtering
                'lastUpdated' => (new \DateTime())->format('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching historical price data: ' . $e->getMessage());
            return $this->json([
                'success' => false,
                'message' => 'Could not fetch historical price data'
            ], 500);
        }
    }

    #[Route('/{id}/institutional-ownership', name: 'company_institutional_ownership', methods: ['GET'])]
    public function institutionalOwnership(Company $company): Response
    {
        return $this->render('company/institutional_ownership.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/competitors', name: 'company_competitors', methods: ['GET'])]
    public function competitors(Company $company): Response
    {
        return $this->render('company/competitors.html.twig', [
            'company' => $company,
            'competitorAnalyses' => $company->getCompetitorAnalyses(),
        ]);
    }

    #[Route('/{id}/reports', name: 'company_reports', methods: ['GET'])]
    public function reports(Company $company): Response
    {
        return $this->render('company/reports.html.twig', [
            'company' => $company,
            'reports' => $company->getResearchReports(),
        ]);
    }

    #[Route('/{id}/delete', name: 'company_delete', methods: ['POST'])]
    public function delete(Request $request, Company $company, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $company->getId(), $request->request->get('_token'))) {
            $entityManager->remove($company);
            $entityManager->flush();
            $this->addFlash('success', 'Company deleted successfully.');
        }

        return $this->redirectToRoute('company_index');
    }

    #[Route('/{id}/generate-leadership', name: 'company_generate_leadership', methods: ['POST'])]
    public function generateLeadership(Company $company, Request $request, HunterService $hunterService): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('company_leadership', ['id' => $company->id]);
        }

        try {
            // Use the HunterService to find company executives
            $executivesGenerated = $hunterService->findCompanyExecutives($company);

            return $this->json([
                'success' => true,
                'message' => 'Successfully generated ' . $executivesGenerated . ' executive profiles.',
                'count' => $executivesGenerated
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error generating executive profiles: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/generate-competitors', name: 'company_generate_competitors', methods: ['POST'])]
    public function generateCompetitors(Company $company, Request $request, NeuronAiService $neuronAiService, EntityManagerInterface $entityManager): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('company_competitors', ['id' => $company->id]);
        }

        try {
            // Generate competitor analysis using NeuronAiService
            $result = $neuronAiService->generateCompetitorAnalysis($company);

            return $this->json([
                'success' => true,
                'message' => 'Successfully generated competitor analysis.'
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error generating competitor analysis: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/generate-reports', name: 'company_generate_reports', methods: ['POST'])]
    public function generateReports(Company $company, Request $request, NeuronAiService $neuronAiService, EntityManagerInterface $entityManager): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('company_reports', ['id' => $company->id]);
        }

        try {
            // Generate research reports using NeuronAiService
            $reportsGenerated = $neuronAiService->generateResearchReports($company);

            return $this->json([
                'success' => true,
                'message' => 'Successfully generated ' . $reportsGenerated . ' research reports.',
                'count' => $reportsGenerated
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error generating research reports: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/{id}/generate-financial', name: 'company_generate_financial', methods: ['POST'])]
    public function generateFinancial(Company $company, Request $request, NeuronAiService $neuronAiService, EntityManagerInterface $entityManager): Response
    {
        if (!$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('company_financial', ['id' => $company->id]);
        }

        try {
            // Before generating data, check if we need to set up the entity manager
            // This is needed because our generateFinancialData method in NeuronAiService
            // doesn't have direct access to the entity manager
            $reflection = new \ReflectionClass($company);
            try {
                // Try to add a method to the company object to get the entity manager
                $method = $reflection->getMethod('getEntityManager');
            } catch (\ReflectionException $e) {
                // If the method doesn't exist, dynamically add it
                $company->getEntityManager = function() use ($entityManager) {
                    return $entityManager;
                };
            }

            // Generate financial data using NeuronAiService
            $dataGenerated = $neuronAiService->generateFinancialData($company);

            // Iterate through financial data and set the reportType field
            if ($dataGenerated > 0) {
                foreach ($company->getFinancialData() as $financialData) {
                    if ($financialData->getReportType() === null) {
                        $financialData->setReportType('Quarterly');
                    }
                }
                $entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Successfully generated financial data.',
                'count' => $dataGenerated
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error generating financial data: ' . $e->getMessage()
            ], 500);
        }
    }
}
