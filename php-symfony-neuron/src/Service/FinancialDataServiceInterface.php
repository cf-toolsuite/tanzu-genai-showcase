<?php

namespace App\Service;

interface FinancialDataServiceInterface
{
    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Stock symbol
     * @return array ESG data including overall score and component scores
     */
    public function getESGData(string $symbol): array;

    /**
     * Get SEC filings for a company
     *
     * @param string $symbol Stock symbol
     * @param int $limit Maximum number of results to return
     * @param string|null $filingType Filter by filing type (10-K, 10-Q, 8-K, etc.)
     * @return array List of SEC filings
     */
    public function getSecFilings(string $symbol, int $limit = 20, ?string $filingType = null): array;

    /**
     * Get insider trading activity for a company
     *
     * @param string $symbol Stock symbol
     * @param int $limit Maximum number of results to return
     * @return array List of insider transactions
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array;

    /**
     * Get institutional ownership data for a company
     *
     * @param string $symbol Stock symbol
     * @param int $limit Maximum number of results to return
     * @return array List of institutional holders with their positions
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array;

    /**
     * Get analyst ratings and consensus data for a company
     *
     * @param string $symbol Stock symbol
     * @param int $limit Maximum number of results to return
     * @return array Analyst ratings and consensus data
     */
    public function getAnalystRatings(string $symbol, int $limit = 20): array;

    /**
     * Get a summary of additional metrics for a company
     *
     * @param string $symbol Stock symbol
     * @return array Summary of ESG, SEC filings, insider trading, institutional ownership, and analyst ratings
     */
    public function getAdditionalMetricsSummary(string $symbol): array;
}
