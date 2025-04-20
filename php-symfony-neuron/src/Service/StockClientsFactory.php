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
        return $this->useMockData
            ? $this->locator->get(MockAlphaVantageClient::class)
            : $this->locator->get(AlphaVantageClient::class);
    }

    /**
     * Gets the appropriate Yahoo Finance client instance.
     *
     * @return ApiClientInterface The Yahoo Finance client instance.
     */
    public function getYahooFinanceClient(): ApiClientInterface
    {
         return $this->useMockData
            ? $this->locator->get(MockYahooFinanceClient::class)
            : $this->locator->get(YahooFinanceClient::class);
    }

    /**
     * Gets the appropriate News API client instance.
     *
     * @return ApiClientInterface The News API client instance.
     */
     public function getNewsApiClient(): ApiClientInterface
    {
         return $this->useMockData
            ? $this->locator->get(MockNewsApiClient::class)
            : $this->locator->get(NewsApiClient::class);
    }

    /**
     * Gets the appropriate SEC API client instance.
     *
     * @return ApiClientInterface The SEC API client instance.
     */
     public function getSecApiClient(): ApiClientInterface
    {
         return $this->useMockData
            ? $this->locator->get(MockSecApiClient::class)
            : $this->locator->get(SecApiClient::class);
    }

    /**
     * Defines the services this factory needs access to via the locator.
     *
     * @return array An array of service class names.
     */
    public static function getSubscribedServices(): array
    {
        return [
            // Define all real and mock services this factory might need
            AlphaVantageClient::class, MockAlphaVantageClient::class,
            YahooFinanceClient::class, MockYahooFinanceClient::class,
            NewsApiClient::class, MockNewsApiClient::class,
            SecApiClient::class, MockSecApiClient::class,
        ];
    }
}
