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

    /**
     * Get company profile
     *
     * @param string $symbol Company ticker symbol
     * @return array Company profile
     */
    public function getCompanyProfile(string $symbol): array;

    /**
     * Get stock quote
     *
     * @param string $symbol Company ticker symbol
     * @return array Stock quote
     */
    public function getStockQuote(string $symbol): array;

    /**
     * Get financial data
     *
     * @param string $symbol Company ticker symbol
     * @param string $period Period (quarterly, annual)
     * @return array Financial data
     */
    public function getFinancialData(string $symbol, string $period = 'quarterly'): array;

    /**
     * Get company news
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of news items to return
     * @return array Company news
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array;

    /**
     * Get company executives
     *
     * @param string $symbol Company ticker symbol
     * @return array Company executives
     */
    public function getExecutives(string $symbol): array;

    /**
     * Get historical stock prices
     *
     * @param string $symbol Company ticker symbol
     * @param string $interval Time interval (daily, weekly, monthly)
     * @param string $outputSize Size of time series (compact = 100 data points, full = 20+ years)
     * @return array Historical stock prices
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array;

    /**
     * Get general market news
     *
     * @param int $limit Maximum number of news items to return
     * @return array Market news
     */
    public function getMarketNews(int $limit = 10): array;
}
