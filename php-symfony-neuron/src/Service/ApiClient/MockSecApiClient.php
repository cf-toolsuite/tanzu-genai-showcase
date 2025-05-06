<?php

namespace App\Service\ApiClient;

/**
 * Mock SEC API client that extends MockKaleidoscopeApiClient for compatibility with StockClientsFactory
 *
 * This class exists to maintain compatibility with the StockClientsFactory
 * which expects a MockSecApiClient service, while our actual mock implementation
 * is now MockKaleidoscopeApiClient.
 */
class MockSecApiClient extends MockKaleidoscopeApiClient
{
    // No additional implementation needed - this class simply extends MockKaleidoscopeApiClient
    // to maintain compatibility with the StockClientsFactory
}
