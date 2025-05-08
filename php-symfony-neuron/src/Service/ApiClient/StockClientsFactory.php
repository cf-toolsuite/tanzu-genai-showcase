<?php

// src/Service/ApiClient/StockClientsFactory.php
namespace App\Service\ApiClient;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class StockClientsFactory implements ServiceSubscriberInterface
{
    private ContainerInterface $locator;
    private bool $useMockData;

    /**
     * Constructor.
     *
     * @param ContainerInterface $locator     The service locator to get client instances.
     * @param bool               $useMockData Flag indicating whether to use mock data.
     */
    public function __construct(ContainerInterface $locator, bool $useMockData)
    {
        $this->locator = $locator;
        $this->useMockData = $useMockData;
    }

    /**
     * Gets the appropriate Alpha Vantage client instance.
     *
     * @return ApiClientInterface The Alpha Vantage client instance.
     */
    public function getAlphaVantageClient(): ApiClientInterface
    {
        $serviceId = $this->useMockData
            ? 'App\Service\ApiClient\MockAlphaVantageClient'
            : 'App\Service\ApiClient\AlphaVantageClient';

        return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate Yahoo Finance client instance.
     *
     * @return ApiClientInterface The Yahoo Finance client instance.
     */
    public function getYahooFinanceClient(): ApiClientInterface
    {
         $serviceId = $this->useMockData
            ? 'App\Service\ApiClient\MockYahooFinanceClient'
            : 'App\Service\ApiClient\YahooFinanceClient';

         return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate News API client instance.
     *
     * @return ApiClientInterface The News API client instance.
     */
     public function getNewsApiClient(): ApiClientInterface
    {
         $serviceId = $this->useMockData
            ? 'App\Service\ApiClient\MockNewsApiClient'
            : 'App\Service\ApiClient\NewsApiClient';

         return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate SEC API client instance.
     *
     * @return ApiClientInterface The SEC API client instance.
     */
     public function getSecApiClient(): ApiClientInterface
    {
         $serviceId = $this->useMockData
            ? 'App\Service\ApiClient\MockSecApiClient'
            : 'App\Service\ApiClient\SecApiClient';

         return $this->locator->get($serviceId);
    }

    /**
     * Gets the appropriate TradeFeeds API client instance.
     *
     * @return ApiClientInterface The TradeFeeds API client instance.
     */
     public function getTradeFeedsClient(): ApiClientInterface
    {
         $serviceId = $this->useMockData
            ? 'App\Service\ApiClient\MockTradeFeedsApiClient'
            : 'App\Service\ApiClient\TradeFeedsApiClient';

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
            // Define all real and mock services this factory might need with fully qualified names
            'App\Service\ApiClient\AlphaVantageClient',
            'App\Service\ApiClient\MockAlphaVantageClient',
            'App\Service\ApiClient\YahooFinanceClient',
            'App\Service\ApiClient\MockYahooFinanceClient',
            'App\Service\ApiClient\NewsApiClient',
            'App\Service\ApiClient\MockNewsApiClient',
            'App\Service\ApiClient\SecApiClient',
            'App\Service\ApiClient\MockSecApiClient',
            'App\Service\ApiClient\TradeFeedsApiClient',
            'App\Service\ApiClient\MockTradeFeedsApiClient',
        ];
    }
}
