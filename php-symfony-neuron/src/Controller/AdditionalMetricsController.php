<?php

namespace App\Controller;

use App\Entity\Company;
use App\Repository\AnalystRatingRepository;
use App\Repository\ESGDataRepository;
use App\Repository\InstitutionalOwnershipRepository;
use App\Repository\InsiderTransactionRepository;
use App\Repository\SecFilingRepository;
use App\Service\YahooFinanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for handling additional financial metrics and data visualizations
 */
class AdditionalMetricsController extends AbstractController
{
    private YahooFinanceService $yahooFinanceService;
    private ESGDataRepository $esgDataRepository;
    private SecFilingRepository $secFilingRepository;
    private InsiderTransactionRepository $insiderTransactionRepository;
    private InstitutionalOwnershipRepository $institutionalOwnershipRepository;
    private AnalystRatingRepository $analystRatingRepository;

    public function __construct(
        YahooFinanceService $yahooFinanceService,
        ESGDataRepository $esgDataRepository,
        SecFilingRepository $secFilingRepository,
        InsiderTransactionRepository $insiderTransactionRepository,
        InstitutionalOwnershipRepository $institutionalOwnershipRepository,
        AnalystRatingRepository $analystRatingRepository
    ) {
        $this->yahooFinanceService = $yahooFinanceService;
        $this->esgDataRepository = $esgDataRepository;
        $this->secFilingRepository = $secFilingRepository;
        $this->insiderTransactionRepository = $insiderTransactionRepository;
        $this->institutionalOwnershipRepository = $institutionalOwnershipRepository;
        $this->analystRatingRepository = $analystRatingRepository;
    }

    /**
     * Main dashboard for additional metrics
     *
     * @Route("/company/{id}/additional-metrics", name="company_additional_metrics")
     */
    public function additionalMetrics(Company $company): Response
    {
        // Get summary data for each section
        $esgData = $this->esgDataRepository->findLatestByCompany($company);
        $secFilings = $this->secFilingRepository->findRecentByCompany($company, 5);
        $insiderTransactions = $this->insiderTransactionRepository->findRecentByCompany($company, 5);
        $institutionalOwners = $this->institutionalOwnershipRepository->findTopByCompany($company, 5);
        $analystRatings = $this->analystRatingRepository->findRecentByCompany($company, 5);

        // Calculate analyst consensus
        $analystConsensus = $this->calculateAnalystConsensus($company);

        // Calculate total institutional ownership
        $totalInstitutionalOwnership = $this->calculateTotalInstitutionalOwnership($company);

        return $this->render('company/additional_metrics.html.twig', [
            'company' => $company,
            'esgData' => $esgData,
            'secFilings' => $secFilings,
            'insiderTransactions' => $insiderTransactions,
            'institutionalOwners' => $institutionalOwners,
            'analystRatings' => $analystRatings,
            'analystConsensus' => $analystConsensus,
            'totalInstitutionalOwnership' => $totalInstitutionalOwnership
        ]);
    }

    /**
     * ESG Dashboard
     *
     * @Route("/company/{id}/esg", name="company_esg_dashboard")
     */
    public function esgDashboard(Company $company, Request $request): Response
    {
        // Get ESG data for the company
        $esgData = $this->esgDataRepository->findByCompany($company);

        // Get industry peers for comparison
        $industryPeers = $this->yahooFinanceService->getIndustryPeers($company);

        // Get historical ESG data for trends
        $historicalEsgData = $this->esgDataRepository->findHistoricalByCompany($company);

        return $this->render('company/esg_dashboard.html.twig', [
            'company' => $company,
            'esgData' => $esgData,
            'industryPeers' => $industryPeers,
            'historicalEsgData' => $historicalEsgData
        ]);
    }

    /**
     * SEC Filings
     *
     * @Route("/company/{id}/sec-filings", name="company_sec_filings")
     */
    public function secFilings(Company $company, Request $request): Response
    {
        // Get filter parameters
        $type = $request->query->get('type');
        $limit = $request->query->getInt('limit', 20);

        // Get SEC filings based on filters
        $secFilings = $this->secFilingRepository->findByCompanyAndType($company, $type, $limit);

        // Get filing types for filter dropdown
        $filingTypes = $this->secFilingRepository->getFilingTypes();

        return $this->render('company/sec_filings.html.twig', [
            'company' => $company,
            'secFilings' => $secFilings,
            'filingTypes' => $filingTypes,
            'currentType' => $type
        ]);
    }

    /**
     * Insider Activity
     *
     * @Route("/company/{id}/insider-activity", name="company_insider_activity")
     */
    public function insiderActivity(Company $company, Request $request): Response
    {
        // Get filter parameters
        $type = $request->query->get('type');
        $insider = $request->query->get('insider');

        // Get insider transactions based on filters
        $insiderTransactions = $this->insiderTransactionRepository->findByCompanyAndFilters(
            $company,
            $type,
            $insider
        );

        // Get transaction types and insider names for filter dropdowns
        $transactionTypes = $this->insiderTransactionRepository->getTransactionTypes();
        $insiderNames = $this->insiderTransactionRepository->getInsiderNames($company);

        return $this->render('company/insider_activity.html.twig', [
            'company' => $company,
            'insiderTransactions' => $insiderTransactions,
            'transactionTypes' => $transactionTypes,
            'insiderNames' => $insiderNames,
            'currentType' => $type,
            'currentInsider' => $insider
        ]);
    }

    /**
     * Institutional Ownership
     *
     * @Route("/company/{id}/institutional-ownership", name="company_institutional_ownership")
     */
    public function institutionalOwnership(Company $company, Request $request): Response
    {
        // Get filter parameters
        $showChanges = $request->query->getBoolean('changes', false);
        $limit = $request->query->getInt('limit', 20);

        // Get institutional owners based on filters
        if ($showChanges) {
            $institutionalOwners = $this->institutionalOwnershipRepository->findSignificantChangesByCompany($company, $limit);
        } else {
            $institutionalOwners = $this->institutionalOwnershipRepository->findByCompany($company, $limit);
        }

        // Calculate total institutional ownership
        $totalInstitutionalOwnership = $this->calculateTotalInstitutionalOwnership($company);

        return $this->render('company/institutional_ownership.html.twig', [
            'company' => $company,
            'institutionalOwners' => $institutionalOwners,
            'totalInstitutionalOwnership' => $totalInstitutionalOwnership,
            'showChanges' => $showChanges
        ]);
    }

    /**
     * Analyst Coverage
     *
     * @Route("/company/{id}/analyst-coverage", name="company_analyst_coverage")
     */
    public function analystCoverage(Company $company, Request $request): Response
    {
        // Get filter parameters
        $firm = $request->query->get('firm');
        $rating = $request->query->get('rating');

        // Get analyst ratings based on filters
        $analystRatings = $this->analystRatingRepository->findByCompanyAndFilters(
            $company,
            $firm,
            $rating
        );

        // Get firm names and rating types for filter dropdowns
        $firmNames = $this->analystRatingRepository->getFirmNames($company);
        $ratingTypes = $this->analystRatingRepository->getRatingTypes();

        // Calculate analyst consensus
        $analystConsensus = $this->calculateAnalystConsensus($company);

        return $this->render('company/analyst_coverage.html.twig', [
            'company' => $company,
            'analystRatings' => $analystRatings,
            'firmNames' => $firmNames,
            'ratingTypes' => $ratingTypes,
            'currentFirm' => $firm,
            'currentRating' => $rating,
            'analystConsensus' => $analystConsensus
        ]);
    }

    /**
     * Calculate analyst consensus metrics
     */
    private function calculateAnalystConsensus(Company $company): array
    {
        $ratings = $this->analystRatingRepository->findByCompany($company);

        $buy = 0;
        $hold = 0;
        $sell = 0;
        $priceTargets = [];

        foreach ($ratings as $rating) {
            $ratingText = strtolower($rating->getRating());

            // Categorize ratings
            if (preg_match('/(buy|outperform|overweight|strong buy)/i', $ratingText)) {
                $buy++;
            } elseif (preg_match('/(sell|underperform|underweight|reduce)/i', $ratingText)) {
                $sell++;
            } elseif (preg_match('/(hold|neutral|market perform|equal weight)/i', $ratingText)) {
                $hold++;
            }

            // Collect price targets
            if ($rating->getPriceTarget() > 0) {
                $priceTargets[] = $rating->getPriceTarget();
            }
        }

        // Calculate price target statistics
        $averagePriceTarget = count($priceTargets) > 0 ? array_sum($priceTargets) / count($priceTargets) : 0;
        $highPriceTarget = count($priceTargets) > 0 ? max($priceTargets) : 0;
        $lowPriceTarget = count($priceTargets) > 0 ? min($priceTargets) : 0;

        // Calculate median price target
        if (count($priceTargets) > 0) {
            sort($priceTargets);
            $middle = floor(count($priceTargets) / 2);
            if (count($priceTargets) % 2 === 0) {
                $medianPriceTarget = ($priceTargets[$middle - 1] + $priceTargets[$middle]) / 2;
            } else {
                $medianPriceTarget = $priceTargets[$middle];
            }
        } else {
            $medianPriceTarget = 0;
        }

        // Calculate standard deviation
        $standardDeviation = 0;
        if (count($priceTargets) > 1) {
            $variance = 0;
            foreach ($priceTargets as $target) {
                $variance += pow($target - $averagePriceTarget, 2);
            }
            $standardDeviation = sqrt($variance / count($priceTargets));
        }

        return [
            'buy' => $buy,
            'hold' => $hold,
            'sell' => $sell,
            'total' => $buy + $hold + $sell,
            'averagePriceTarget' => $averagePriceTarget,
            'medianPriceTarget' => $medianPriceTarget,
            'highPriceTarget' => $highPriceTarget,
            'lowPriceTarget' => $lowPriceTarget,
            'standardDeviation' => $standardDeviation
        ];
    }

    /**
     * Calculate total institutional ownership percentage
     */
    private function calculateTotalInstitutionalOwnership(Company $company): float
    {
        $owners = $this->institutionalOwnershipRepository->findByCompany($company);

        $totalPercentage = 0;
        foreach ($owners as $owner) {
            $totalPercentage += $owner->getPercentageOwned();
        }

        // Cap at 100% (sometimes there can be double counting in the data)
        return min($totalPercentage, 100);
    }
}
