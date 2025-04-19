<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;

/**
 * Mock Implementation of SEC API client (sec-api.io)
 * Returns predefined mock data.
 */
class MockSecApiClient implements ApiClientInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockSecApiClient instantiated. Will return mock data.");
    }

    // --- Implementation of ApiClientInterface methods ---

    public function searchCompanies(string $term): array
    {
        $this->logger->info("MockSecApiClient::searchCompanies called (returns empty).", ['term' => $term]);
        return [];
    }
    public function getCompanyProfile(string $symbol): array
    {
        $this->logger->info("MockSecApiClient::getCompanyProfile called (returns basic).", ['symbol' => $symbol]);
        return ['symbol' => $symbol, 'name' => 'Mock Company ' . $symbol, 'description' => 'Mock profile from SEC client.'];
    }
    public function getQuote(string $symbol): array
    {
        $this->logger->info("MockSecApiClient::getQuote called (returns empty).", ['symbol' => $symbol]);
        return ['symbol' => $symbol, 'price' => 0];
    }
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $this->logger->info("MockSecApiClient::getFinancials called (returns empty).", ['symbol' => $symbol]);
        return [];
    }
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $this->logger->info("MockSecApiClient::getCompanyNews called (returns empty).", ['symbol' => $symbol]);
        return [];
    }
    public function getExecutives(string $symbol): array
    {
        $this->logger->info("MockSecApiClient::getExecutives called (returns empty).", ['symbol' => $symbol]);
        return [];
    }
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $this->logger->info("MockSecApiClient::getHistoricalPrices called (returns empty).", ['symbol' => $symbol]);
        return [];
    }

    // --- Specific methods for MockSecApiClient ---

    public function getInsiderTrading(string $symbol, int $limit = 20, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $this->logger->info("MockSecApiClient::getInsiderTrading called", ['symbol' => $symbol]);
        // Return processed mock data directly
        $mockFilings = $this->getMockInsiderFilings($symbol);
        $insiderTrades = [];
        foreach ($mockFilings as $filing) {
            $parsedData = $this->parseForm4Data($filing); // Use parser on mock structure
            if ($parsedData) $insiderTrades[] = $parsedData;
        }
        return array_slice($insiderTrades, 0, $limit);
    }

    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        $this->logger->info("MockSecApiClient::getInstitutionalOwnership called", ['symbol' => $symbol]);
        // Return processed mock data directly
        $mockFilings = $this->getMockRawInstitutionalFilings($symbol); // Get raw mock
        $institutionalHoldings = [];
        $institutionCiks = [];
        foreach ($mockFilings as $filing) {
            $cik = $filing['cik'] ?? null;
            if (!$cik || isset($institutionCiks[$cik])) continue;
            foreach ($filing['holdings'] ?? [] as $holding) {
                if (isset($holding['ticker']) && $holding['ticker'] === $symbol) {
                    $institutionCiks[$cik] = true;
                    $institutionalHoldings[] = ['institutionName' => $filing['companyName'] ?? 'Unknown', 'cik' => $cik, 'filingDate' => $filing['filedAt'] ?? '', 'reportDate' => $filing['periodOfReport'] ?? '', 'sharesHeld' => (int)($holding['shares'] ?? 0), 'valueInDollars' => (float)($holding['value'] ?? 0) * 1000, 'percentOfPortfolio' => (float)($holding['percentage'] ?? 0), 'changeFromPrevious' => (int)(($holding['shares'] ?? 0) - ($holding['priorShares'] ?? 0))];
                    break;
                }
            }
            if (count($institutionalHoldings) >= $limit) break;
        }
        return $institutionalHoldings;
    }

    public function getAnalystRatings(string $symbol): array
    {
        $this->logger->info("MockSecApiClient::getAnalystRatings called", ['symbol' => $symbol]);
        return $this->getMockAnalystRatingsData($symbol);
    }

    /**
     * Parse Form 4 filing data - unchanged (needed for processing mock data)
     */
    private function parseForm4Data(array $filing): ?array
    { /* ... same logic as before ... */
        if (!isset($filing['reportingOwner']) || !isset($filing['transactions'])) {
            return null;
        }
        $owner = $filing['reportingOwner'];
        $relationship = $owner['relationship'] ?? [];
        $parsedTransactions = [];
        foreach ($filing['transactions'] as $tx) {
            $parsedTransactions[] = ['transactionType' => $tx['transactionCode'] ?? 'N/A', 'securityType' => $tx['securityTitle'] ?? 'N/A', 'shares' => (float)($tx['transactionShares']['value'] ?? 0), 'pricePerShare' => (float)($tx['transactionPricePerShare']['value'] ?? 0), 'totalValue' => (float)($tx['transactionShares']['value'] ?? 0) * (float)($tx['transactionPricePerShare']['value'] ?? 0), 'ownershipType' => $tx['ownershipNature']['directOrIndirectOwnership']['value'] ?? 'N/A', 'sharesOwnedFollowing' => (float)($tx['postTransactionAmounts']['sharesOwnedFollowingTransaction']['value'] ?? 0)];
        }
        return ['filingId' => $filing['id'] ?? ($filing['accessionNo'] ?? 'N/A'), 'filingDate' => $filing['filedAt'] ?? '', 'issuerName' => $filing['companyName'] ?? 'Unknown', 'issuerTicker' => $filing['ticker'] ?? ($filing['tickers'][0] ?? 'N/A'), 'ownerName' => $owner['reportingOwnerName'] ?? 'Unknown Insider', 'ownerTitle' => $relationship['officerTitle'] ?? 'N/A', 'isDirector' => $relationship['isDirector'] ?? false, 'isOfficer' => $relationship['isOfficer'] ?? false, 'isTenPercentOwner' => $relationship['isTenPercentOwner'] ?? false, 'transactionDate' => $filing['periodOfReport'] ?? '', 'formType' => $filing['formType'] ?? '4', 'formUrl' => $filing['linkToFilingDetails'] ?? '', 'transactions' => $parsedTransactions];
    }


    // --- Mock Data Generation Methods (Copied from original SecApiClient) ---
    private function getMockInsiderFilings(string $symbol): array
    { /* ... */
        $filings = [];
        $names = ['Alice Johnson (CEO)', 'Bob Williams (CFO)', 'Charlie Davis (Director)'];
        for ($i = 0; $i < 5; $i++) {
            $date = (new \DateTime())->modify('-' . ($i * 10 + mt_rand(0, 5)) . ' days');
            $insider = $names[array_rand($names)];
            $filings[] = ['id' => 'mock-form4-' . $i, 'filedAt' => $date->format('Y-m-d'), 'companyName' => 'Mock ' . $symbol . ' Inc.', 'ticker' => $symbol, 'formType' => '4', 'reportingOwner' => ['reportingOwnerName' => $insider, 'relationship' => ['isDirector' => (bool)mt_rand(0, 1), 'isOfficer' => (bool)mt_rand(0, 1)]], 'transactions' => $this->getMockTransactionsData(), 'periodOfReport' => $date->modify('-1 day')->format('Y-m-d'), 'linkToFilingDetails' => 'https://www.sec.gov/Archives/edgar/data/mock/mock-form4.html'];
        }
        return $filings;
    }
    private function getMockRawInstitutionalFilings(string $symbol): array
    { /* ... */
        $filings = [];
        $institutions = ['Vanguard Mock Group', 'BlackRock Mock Advisors', 'State Street Mock Corp'];
        for ($i = 0; $i < 3; $i++) {
            $date = (new \DateTime())->modify('-' . ($i * 90 + mt_rand(0, 15)) . ' days');
            $institution = $institutions[array_rand($institutions)];
            $shares = mt_rand(100000, 5000000);
            $value = $shares * mt_rand(50, 200);
            $priorShares = $shares - mt_rand(-50000, 50000);
            $filings[] = ['id' => 'mock-13f-' . $i, 'filedAt' => $date->format('Y-m-d'), 'companyName' => $institution, 'cik' => '000' . mt_rand(100000, 999999), 'formType' => '13F-HR', 'periodOfReport' => $date->modify('-30 days')->format('Y-m-d'), 'holdings' => [['ticker' => $symbol, 'shares' => $shares, 'value' => $value / 1000, 'percentage' => mt_rand(1, 100) / 100, 'priorShares' => $priorShares]]];
        }
        return $filings;
    }
    private function getMockAnalystRatingsData(string $symbol): array
    { /* ... */
        $ratings = [];
        $firms = ['Mock Stanley', 'Goldman Mock', 'JP Mock'];
        $actions = ['Initiated', 'Upgraded', 'Downgraded', 'Reiterated'];
        $ratingsList = ['Buy', 'Hold', 'Sell', 'Overweight', 'Underweight'];
        $ratingCount = mt_rand(5, 10);
        $totalPriceTarget = 0;
        $ratingCounts = array_fill_keys($ratingsList, 0);
        $currentPrice = mt_rand(50, 500);
        for ($i = 0; $i < $ratingCount; $i++) {
            $date = (new \DateTime())->modify('-' . ($i * 15 + mt_rand(0, 10)) . ' days');
            $rating = $ratingsList[array_rand($ratingsList)];
            $priceTarget = $currentPrice * (1 + (mt_rand(-20, 40) / 100));
            $ratings[] = ['firm' => $firms[array_rand($firms)], 'action' => $actions[array_rand($actions)], 'rating' => $rating, 'priceTarget' => round($priceTarget, 2), 'date' => $date->format('Y-m-d')];
            $ratingCounts[$rating]++;
            $totalPriceTarget += $priceTarget;
        }
        $buyCount = ($ratingCounts['Buy'] ?? 0) + ($ratingCounts['Overweight'] ?? 0);
        $holdCount = ($ratingCounts['Hold'] ?? 0) + ($ratingCounts['Neutral'] ?? 0);
        $sellCount = ($ratingCounts['Sell'] ?? 0) + ($ratingCounts['Underweight'] ?? 0);
        $consensusRating = 'Hold';
        if ($ratingCount > 0) {
            if ($buyCount / $ratingCount > 0.6) $consensusRating = 'Buy';
            if ($sellCount / $ratingCount > 0.6) $consensusRating = 'Sell';
        }
        return ['symbol' => $symbol, 'currentPrice' => $currentPrice, 'ratings' => $ratings, 'consensus' => ['buy' => $buyCount, 'hold' => $holdCount, 'sell' => $sellCount, 'consensusRating' => $consensusRating, 'averagePriceTarget' => $ratingCount > 0 ? round($totalPriceTarget / $ratingCount, 2) : 0]];
    }
    private function getMockTransactionsData(): array
    { /* ... */
        $transactions = [];
        $count = mt_rand(1, 2);
        $codes = ['P', 'S'];
        $titles = ['Common Stock'];
        for ($i = 0; $i < $count; $i++) {
            $shares = mt_rand(100, 5000);
            $price = mt_rand(50, 200) + (mt_rand(0, 99) / 100);
            $transactions[] = ['transactionCode' => $codes[array_rand($codes)], 'securityTitle' => $titles[array_rand($titles)], 'transactionShares' => ['value' => $shares], 'transactionPricePerShare' => ['value' => $price], 'ownershipNature' => ['directOrIndirectOwnership' => ['value' => mt_rand(0, 1) ? 'D' : 'I']], 'postTransactionAmounts' => ['sharesOwnedFollowingTransaction' => ['value' => mt_rand(10000, 100000)]]];
        }
        return $transactions;
    }
}
