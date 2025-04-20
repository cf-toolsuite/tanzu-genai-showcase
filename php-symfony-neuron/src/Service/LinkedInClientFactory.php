<?php

// src/Service/ApiClient/LinkedInClientFactory.php
namespace App\Service\ApiClient;

use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;

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
     * Creates and returns the appropriate LinkedIn API client instance.
     * Note: Returns the concrete class type as LinkedInApiClient doesn't follow ApiClientInterface here.
     *
     * @return LinkedInApiClient The LinkedIn API client instance.
     */
    public function createClient(): LinkedInApiClient
    {
        if ($this->useMockData) {
            // Get the mock client instance from the locator
            return $this->locator->get(MockLinkedInApiClient::class);
        } else {
            // Get the real client instance from the locator
            return $this->locator->get(LinkedInApiClient::class);
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
            LinkedInApiClient::class,
            MockLinkedInApiClient::class,
        ];
    }
}
