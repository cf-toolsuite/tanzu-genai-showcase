<?php

namespace App\Service\ApiClient;

/**
 * Interface for financial data API clients
 */
interface ApiClientInterface
{
    /**
     * Search for companies matching a term (name or symbol)
     *
     * @param string $term The search term
     * @return array An array of matching companies with basic info
     * @throws \Exception If the API request fails
     */
    public function searchCompanies(string $term): array;

    /**
     * Get company profile information
     *
     * @param string $symbol Company ticker symbol
     * @return array Company profile data
     * @throws \Exception If the API request fails or profile not found
     */
    public function getCompanyProfile(string $symbol): array;

    /**
     * Get latest stock quote
     *
     * @param string $symbol Company ticker symbol
     * @return array Quote data
     * @throws \Exception If the API request fails or quote not found
     */
    public function getQuote(string $symbol): array;

    /**
     * Get company financial data
     *
     * @param string $symbol Company ticker symbol
     * @param string $period Period (quarterly, annual)
     * @return array Financial data
     * @throws \Exception If the API request fails
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array;

    /**
     * Get latest news for a company
     *
     * @param string $symbol Company ticker symbol or query
     * @param int $limit Number of news items to return
     * @return array Company news
     * @throws \Exception If the API request fails
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array;

    /**
     * Get company executive/leadership data
     *
     * @param string $symbol Company ticker symbol
     * @return array Executive data
     * @throws \Exception If the API request fails
     */
    public function getExecutives(string $symbol): array;

    /**
     * Get historical stock prices
     *
     * @param string $symbol Company ticker symbol
     * @param string $interval Time interval (daily, weekly, monthly)
     * @param string $outputSize Size of time series (compact = 100 data points, full = 20+ years)
     * @return array Historical price data
     * @throws \Exception If the API request fails
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array;

    /**
     * Get ESG (Environmental, Social, Governance) data for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array ESG data with scores
     * @throws \Exception If the API request fails
     */
    public function getESGData(string $symbol): array;

    /**
     * Get recent SEC filings for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of filings to return
     * @return array SEC filings data
     * @throws \Exception If the API request fails
     */
    public function getRecentSecFilings(string $symbol, int $limit = 5): array;

    /**
     * Get analyst ratings for a company
     *
     * @param string $symbol Company ticker symbol
     * @return array Analyst ratings data
     * @throws \Exception If the API request fails
     */
    public function getAnalystRatings(string $symbol): array;

    /**
     * Get insider trading data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of records to return
     * @return array Insider trading data
     * @throws \Exception If the API request fails
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array;

    /**
     * Get institutional ownership data for a company
     *
     * @param string $symbol Company ticker symbol
     * @param int $limit Maximum number of records to return
     * @return array Institutional ownership data
     * @throws \Exception If the API request fails
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array;
}
