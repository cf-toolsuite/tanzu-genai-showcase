<?php

// src/Service/ApiClient/LinkedInClientFactory.php
namespace App\Service\ApiClient;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

/**
 * Factory for creating LinkedIn API clients
 */
class LinkedInClientFactory implements ServiceSubscriberInterface
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
     * Creates the appropriate LinkedIn API client instance based on settings.
     *
     * @return ApiClientInterface The LinkedIn API client instance.
     */
    public function createClient(): ApiClientInterface
    {
        $serviceId = $this->useMockData
            ? 'App\Service\ApiClient\MockLinkedInApiClient'
            : 'App\Service\ApiClient\LinkedInApiClient';

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
            'App\Service\ApiClient\LinkedInApiClient',
            'App\Service\ApiClient\MockLinkedInApiClient'
        ];
    }
}
