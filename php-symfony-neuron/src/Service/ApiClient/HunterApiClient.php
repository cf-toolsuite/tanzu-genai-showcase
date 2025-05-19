<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Hunter.io API client for finding company executives and email addresses
 * Refactored to align with the pattern used by other API clients
 */
class HunterApiClient extends AbstractApiClient implements HunterApiClientInterface, ExecutiveDataApiClientInterface
{
    private const API_BASE_URL = 'https://api.hunter.io/v2';

    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = self::API_BASE_URL;
        $this->apiKey = $this->params->get('hunter_api.api_key', '');

        if (empty($this->apiKey)) {
            $this->logger->warning('HunterApiClient initialized without API key');
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return ['api_key' => $this->apiKey];
    }

    /**
     * {@inheritdoc}
     */
    public function domainSearch(string $domain, array $options = []): array
    {
        // Merge options with required parameters
        $params = array_merge([
            'domain' => $domain,
        ], $options);

        try {
            return $this->request('GET', '/domain-search', $params);
        } catch (\Exception $e) {
            $this->logger->error('Hunter API domain search error', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function companySearch(string $companyName, array $options = []): array
    {
        $this->logger->info('Searching for company by name', ['company' => $companyName]);

        // First try to find the domain by company name
        try {
            $companyParams = array_merge([
                'company' => $companyName,
                'limit' => 1
            ], $this->getAuthParams());

            $companyResponse = $this->request('GET', '/domain-search', $companyParams);

            if (isset($companyResponse['data']['domain'])) {
                $domain = $companyResponse['data']['domain'];
                $this->logger->info('Found domain for company', [
                    'company' => $companyName,
                    'domain' => $domain
                ]);

                // Now search for executives using the found domain
                return $this->domainSearch($domain, $options);
            } else {
                $this->logger->warning('No domain found for company', ['company' => $companyName]);
                return ['error' => 'No domain found for company name: ' . $companyName];
            }
        } catch (\Exception $e) {
            $this->logger->error('Hunter API company search error', [
                'company' => $companyName,
                'error' => $e->getMessage()
            ]);
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get company executive/leadership data
     *
     * @param string $symbol Company ticker symbol
     * @return array Executive data
     */
    public function getExecutives(string $symbol): array
    {
        $this->logger->info('Getting executives for company', ['symbol' => $symbol]);

        try {
            // First, try to get company profile to find the company name
            $companyName = $symbol; // Default to using the symbol as company name

            // Try to get a better company name if possible
            try {
                // This would ideally use a service to get the company name from the symbol
                // For now, we'll just use the symbol as the company name
            } catch (\Exception $e) {
                $this->logger->warning('Could not get company name for symbol, using symbol as fallback', [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }

            // Search for executives by company name
            $result = $this->companySearch($companyName);

            if (isset($result['error'])) {
                return [];
            }

            // Transform Hunter API response to the expected format
            $executives = [];
            if (isset($result['data']['emails']) && is_array($result['data']['emails'])) {
                foreach ($result['data']['emails'] as $email) {
                    if (!isset($email['first_name']) || !isset($email['last_name'])) {
                        continue;
                    }

                    $executives[] = [
                        'name' => $email['first_name'] . ' ' . $email['last_name'],
                        'title' => $email['position'] ?? 'Unknown',
                        'age' => null, // Not provided by Hunter
                        'yearJoined' => null, // Not provided by Hunter
                        'bio' => '', // Not provided by Hunter
                        'compensation' => 0, // Not provided by Hunter
                        'education' => '', // Not provided by Hunter
                        'previousCompanies' => '', // Not provided by Hunter
                        'email' => $email['value'] ?? '',
                        'confidence' => $email['confidence'] ?? 0,
                    ];
                }
            }

            return $executives;
        } catch (\Exception $e) {
            $this->logger->error('Error getting executives', [
                'symbol' => $symbol,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

}
