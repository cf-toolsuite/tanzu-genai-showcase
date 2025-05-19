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

        $this->logger->info('Searching for companies with term: ' . $searchTerm);

        // First search in local database with improved search criteria
        $dbResults = $this->companyRepository->findBySearchCriteria($searchTerm);
        $this->logger->info('Database search results count: ' . count($dbResults));

        // Always search in external API to ensure we get comprehensive results
        // Collect ticker symbols and company names from database results to prevent duplicates
        $existingSymbols = [];
        $existingNames = [];
        foreach ($dbResults as $company) {
            $existingSymbols[] = strtolower($company->getTickerSymbol() ?? '');
            $existingNames[] = strtolower($company->getName() ?? '');
        }

        // Get external API results
        try {
            // Call the external API search
            $this->logger->info('Searching for companies in external API: ' . $searchTerm);
            $apiSearchResults = $this->stockDataService->searchCompanies($searchTerm);

            // Check if we got an error response
            if (isset($apiSearchResults['error']) && $apiSearchResults['error'] === true) {
                $this->logger->warning('External API search returned an error', [
                    'message' => $apiSearchResults['message'] ?? 'Unknown error'
                ]);
                return ['dbResults' => $dbResults, 'apiResults' => []];
            }

            // Process the results array
            $allApiResults = is_array($apiSearchResults) ? $apiSearchResults : [];
            $this->logger->info('API search raw results count: ' . count($allApiResults));

            // Filter out API results that already exist in the database
            $filteredApiResults = array_filter($allApiResults, function($result) use ($existingSymbols, $existingNames) {
                // Skip invalid results
                if (!is_array($result) || empty($result['symbol'])) {
                    return false;
                }

                // Skip if the symbol already exists in our database
                $resultSymbol = strtolower($result['symbol'] ?? '');
                if (!empty($resultSymbol) && in_array($resultSymbol, $existingSymbols)) {
                    return false;
                }

                // Skip if the company name is already in our database (or very similar)
                $normalizedName = strtolower($result['name'] ?? '');
                if (!empty($normalizedName)) {
                    foreach ($existingNames as $existingName) {
                        if (!empty($existingName) && (
                            $existingName === $normalizedName ||
                            strpos($existingName, $normalizedName) !== false ||
                            strpos($normalizedName, $existingName) !== false)) {
                            return false;
                        }
                    }
                }

                return true;
            });

            // Group results by company name to avoid duplicates
            $resultsByName = [];
            foreach ($filteredApiResults as $result) {
                $normalizedName = strtolower($result['name'] ?? 'Unknown');
                if (!isset($resultsByName[$normalizedName])) {
                    $resultsByName[$normalizedName] = [];
                }
                $resultsByName[$normalizedName][] = $result;
            }

            // Take the first result from each name group
            foreach ($resultsByName as $results) {
                if (!empty($results)) {
                    $apiResults[] = $results[0];
                }
            }

            $this->logger->info('API search filtered results count: ' . count($apiResults));
        } catch (\Exception $e) {
            $this->logger->error('Error fetching external search results: ' . $e->getMessage(), [
                'term' => $searchTerm,
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // If we have no results at all, log this clearly
        if (empty($dbResults) && empty($apiResults)) {
            $this->logger->info('No results found for search term', ['term' => $searchTerm]);
        }

        return ['dbResults' => $dbResults, 'apiResults' => $apiResults];
    }
}
