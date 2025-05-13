<?php

namespace App\Service\ApiClient;

interface HunterApiClientInterface
{
    /**
     * Search for emails on a specific domain
     *
     * @param string $domain Domain name (e.g., "broadcom.com")
     * @param array $options Additional search options
     * @return array Response data from Hunter.io API
     */
    public function domainSearch(string $domain, array $options = []): array;

    /**
     * Search for emails by company name
     *
     * @param string $companyName Company name (e.g., "Broadcom")
     * @param array $options Additional search options
     * @return array Response data from Hunter.io API
     */
    public function companySearch(string $companyName, array $options = []): array;
}
