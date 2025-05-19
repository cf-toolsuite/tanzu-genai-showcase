<?php

// src/Service/ApiClient/StockClientsFactory.php
namespace App\Service\ApiClient;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use App\Service\ApiClient\StockMarketDataApiClientInterface;
use App\Service\ApiClient\NewsApiClientInterface;
use App\Service\ApiClient\SecFilingsApiClientInterface;
use App\Service\ApiClient\AnalystRatingsApiClientInterface;

class StockClientsFactory implements ServiceSubscriberInterface
{
    private ContainerInterface $locator;

    /**
     * Constructor.
     *
     * @param ContainerInterface $locator     The service locator to get client instances.
     */
    public function __construct(ContainerInterface $locator)
    {
        $this->locator = $locator;
    }

    /**
     * Gets the appropriate Alpha Vantage client instance.
     *
     * @return StockMarketDataApiClientInterface The Alpha Vantage client instance.
     */
    public function getAlphaVantageClient(): StockMarketDataApiClientInterface
    {
        $serviceId = 'App\Service\ApiClient\AlphaVantageClient';
        return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate Yahoo Finance client instance.
     *
     * @return StockMarketDataApiClientInterface The Yahoo Finance client instance.
     */
    public function getYahooFinanceClient(): StockMarketDataApiClientInterface
    {
         $serviceId = 'App\Service\ApiClient\YahooFinanceClient';
         return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate News API client instance.
     *
     * @return NewsApiClientInterface The News API client instance.
     */
     public function getNewsApiClient(): NewsApiClientInterface
    {
         $serviceId = 'App\Service\ApiClient\NewsApiClient';
         return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate Kaleidoscope API client instance.
     *
     * @return SecFilingsApiClientInterface The Kaleidoscope API client instance.
     */
     public function getKaleidoscopeApiClient(): SecFilingsApiClientInterface
    {
         $serviceId = 'App\Service\ApiClient\KaleidoscopeApiClient';
         return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate TradeFeeds API client instance.
     *
     * @return AnalystRatingsApiClientInterface The TradeFeeds API client instance.
     */
     public function getTradeFeedsClient(): AnalystRatingsApiClientInterface
    {
         $serviceId = 'App\Service\ApiClient\TradeFeedsApiClient';
         return $this->locator->get($serviceId);
    }

    /**
     * Defines the services this factory needs access to via the locator.
     *
     * @return array An array of service class names.
     */
    public static function getSubscribedServices(): array
    {
        return [
            // Define all services this factory might need with fully qualified names
            'App\Service\ApiClient\AlphaVantageClient',
            'App\Service\ApiClient\YahooFinanceClient',
            'App\Service\ApiClient\NewsApiClient',
            'App\Service\ApiClient\KaleidoscopeApiClient',
            'App\Service\ApiClient\TradeFeedsApiClient'
        ];
    }
}
