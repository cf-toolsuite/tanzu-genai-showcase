<?php

namespace App\Service\ApiClient;

/**
 * SEC API client - extends KaleidoscopeApiClient for compatibility with StockClientsFactory
 *
 * This class exists to maintain compatibility with the StockClientsFactory
 * which expects a SecApiClient service, while our actual implementation
 * is now the KaleidoscopeApiClient.
 */
class SecApiClient extends KaleidoscopeApiClient
{
    // No additional implementation needed - this class simply extends KaleidoscopeApiClient
    // to maintain compatibility with the StockClientsFactory
}
