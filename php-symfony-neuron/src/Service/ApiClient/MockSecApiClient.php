<?php

namespace App\Service\ApiClient;

/**
 * Mock SEC API client that extends MockEdgarApiClient for compatibility with StockClientsFactory
 *
 * This class exists to maintain compatibility with the StockClientsFactory
 * which expects a MockSecApiClient service, while our actual mock implementation
 * is named MockEdgarApiClient.
 */
class MockSecApiClient extends MockEdgarApiClient
{
    // No additional implementation needed - this class simply extends MockEdgarApiClient
    // to maintain compatibility with the StockClientsFactory
}
