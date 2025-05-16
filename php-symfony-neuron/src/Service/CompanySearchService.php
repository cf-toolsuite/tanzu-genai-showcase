<?php

namespace App\Service;

use App\Repository\CompanyRepository;
use App\Service\StockDataService;
use Psr\Log\LoggerInterface;

class CompanySearchService
{
    private CompanyRepository $companyRepository;
    private StockDataService $stockDataService;
    private LoggerInterface $logger;

    public function __construct(
        CompanyRepository $companyRepository,
        StockDataService $stockDataService,
        LoggerInterface $logger
    ) {
        $this->companyRepository = $companyRepository;
        $this->stockDataService = $stockDataService;
        $this->logger = $logger;
    }

    public function searchCompanies(string $searchTerm): array
    {
        $dbResults = [];
        $apiResults = [];

        if (empty($searchTerm)) {
            return ['dbResults' => [], 'apiResults' => []];
        }

        // First search in local database
        $dbResults = $this->companyRepository->findBySearchCriteria($searchTerm);

        // If we have any database results, collect their ticker symbols and company names
        $existingSymbols = [];
        $existingNames = [];
        foreach ($dbResults as $company) {
            $existingSymbols[] = strtolower($company->getTickerSymbol() ?? '');
            $existingNames[] = strtolower($company->getName() ?? '');
        }

        // Get external API results
        try {
            $allApiResults = $this->stockDataService->searchCompanies($searchTerm);

            $filteredApiResults = array_filter($allApiResults, function($result) use ($existingSymbols, $existingNames) {
                if (in_array(strtolower($result['symbol'] ?? ''), $existingSymbols)) {
                    return false;
                }
                $normalizedName = strtolower($result['name'] ?? '');
                foreach ($existingNames as $existingName) {
                    if ($existingName === $normalizedName ||
                        strpos($existingName, $normalizedName) !== false ||
                        strpos($normalizedName, $existingName) !== false) {
                        return false;
                    }
                }
                return true;
            });

            $resultsByName = [];
            foreach ($filteredApiResults as $result) {
                $normalizedName = strtolower($result['name'] ?? 'Unknown');
                if (!isset($resultsByName[$normalizedName])) {
                    $resultsByName[$normalizedName] = [];
                }
                $resultsByName[$normalizedName][] = $result;
            }

            foreach ($resultsByName as $results) {
                if (!empty($results)) {
                    $apiResults[] = $results[0];
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Error fetching external search results: ' . $e->getMessage());
            // Optionally, rethrow a custom exception or return an error indicator
            // For now, it just logs and returns empty apiResults if error occurs
        }

        return ['dbResults' => $dbResults, 'apiResults' => $apiResults];
    }
}
