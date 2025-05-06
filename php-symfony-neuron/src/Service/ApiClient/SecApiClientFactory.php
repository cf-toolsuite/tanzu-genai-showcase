<?php

namespace App\Service\ApiClient;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Psr\Log\LoggerInterface;

/**
 * Factory for creating SEC API clients (Kaleidoscope only)
 */
class SecApiClientFactory implements ServiceSubscriberInterface
{
    private ContainerInterface $locator;
    private bool $useMockData;
    private LoggerInterface $logger;

    /**
     * Constructor.
     *
     * @param ContainerInterface $locator     The service locator to get client instances.
     * @param bool               $useMockData Flag indicating whether to use mock data.
     * @param LoggerInterface    $logger      The logger service.
     */
    public function __construct(
        ContainerInterface $locator,
        bool $useMockData,
        LoggerInterface $logger
    ) {
        $this->locator = $locator;
        $this->useMockData = $useMockData;
        $this->logger = $logger;
    }

    /**
     * Creates the appropriate SEC API client instance based on settings.
     *
     * @return ApiClientInterface The SEC API client instance.
     */
    public function createClient(): ApiClientInterface
    {
        // Determine if we need mock client
        $serviceId = $this->useMockData
            ? "App\\Service\\ApiClient\\MockKaleidoscopeApiClient"
            : "App\\Service\\ApiClient\\KaleidoscopeApiClient";

        $this->logger->debug("Creating Kaleidoscope API client");
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
            'App\Service\ApiClient\KaleidoscopeApiClient',
            'App\Service\ApiClient\MockKaleidoscopeApiClient'
        ];
    }
}
