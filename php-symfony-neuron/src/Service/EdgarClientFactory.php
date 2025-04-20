<?php

// src/Service/ApiClient/EdgarClientFactory.php
namespace App\Service\ApiClient;

use Psr\Container\ContainerInterface; // Use PSR container for service location
use Symfony\Contracts\Service\ServiceSubscriberInterface;

class EdgarClientFactory implements ServiceSubscriberInterface
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
     * Creates and returns the appropriate Edgar API client instance.
     *
     * @return ApiClientInterface The Edgar API client instance.
     */
    public function createClient(): ApiClientInterface
    {
        if ($this->useMockData) {
            // Get the mock client instance from the locator
            return $this->locator->get(MockEdgarApiClient::class);
        } else {
            // Get the real client instance from the locator
            return $this->locator->get(EdgarApiClient::class);
        }
    }

    /**
     * Defines the services this factory needs access to via the locator.
     *
     * @return array An array of service class names.
     */
    public static function getSubscribedServices(): array
    {
        return [
            // Define the real and mock services this factory might need
            EdgarApiClient::class,
            MockEdgarApiClient::class,
        ];
    }
}
