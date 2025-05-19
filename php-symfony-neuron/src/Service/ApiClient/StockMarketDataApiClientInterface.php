<?php

namespace App\Service\ApiClient;

/**
 * Interface for API clients that provide stock market data
 */
interface StockMarketDataApiClientInterface
{
    /**
     * Search for companies by name or symbol
     *
     * @param string $query The search query
     * @return array The search results
     */
    public function searchCompanies(string $query): array;

    /**
     * Get company profile information
     *
     * @param string $symbol The company symbol
     * @return array The company profile data
     */
    public function getCompanyProfile(string $symbol): array;

    /**
     * Get the latest stock quote for a company
     *
     * @param string $symbol The company symbol
     * @return array The stock quote data
     */
    public function getQuote(string $symbol): array;

    /**
     * Get financial data for a company
     *
     * @param string $symbol The company symbol
     * @return array The financial data
     */
    public function getFinancials(string $symbol): array;

    /**
     * Get historical stock prices for a company
     *
     * @param string $symbol The company symbol
     * @param string $interval The time interval (e.g., '1d', '1wk', '1mo')
     * @param string $range The time range (e.g., '1d', '5d', '1mo', '3mo', '6mo', '1y', '2y', '5y', '10y', 'ytd', 'max')
     * @return array The historical price data
     */
    public function getHistoricalPrices(string $symbol, string $interval = '1d', string $range = '1mo'): array;
}
