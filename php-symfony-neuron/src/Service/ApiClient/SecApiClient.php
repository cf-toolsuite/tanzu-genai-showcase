<?php

namespace App\Service\ApiClient;

/**
 * SEC API client - extends EdgarApiClient for compatibility with StockClientsFactory
 *
 * This class exists to maintain compatibility with the StockClientsFactory
 * which expects a SecApiClient service, while our actual implementation
 * is named EdgarApiClient.
 */
class SecApiClient extends EdgarApiClient
{
    // No additional implementation needed - this class simply extends EdgarApiClient
    // to maintain compatibility with the StockClientsFactory
}
