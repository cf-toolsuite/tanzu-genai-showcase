<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class HunterApiClient implements HunterApiClientInterface
{
    private const API_BASE_URL = 'https://api.hunter.io/v2';
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->apiKey = $params->get('hunter_api.api_key', '');
        $this->logger = $logger;
    }

    /**
     * {@inheritdoc}
     */
    public function domainSearch(string $domain, array $options = []): array
    {
        // Merge options with required parameters
        $params = array_merge([
            'domain' => $domain,
            'api_key' => $this->apiKey,
        ], $options);

        try {
            return $this->makeRequest('GET', '/domain-search', $params);
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
            $companyResponse = $this->makeRequest('GET', '/domain-search', [
                'company' => $companyName,
                'api_key' => $this->apiKey,
                'limit' => 1
            ]);

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
     * Make a request to the Hunter API
     *
     * @param string $method HTTP method (GET, POST, etc)
     * @param string $endpoint API endpoint (e.g., /domain-search)
     * @param array $params Request parameters
     * @return array Response data
     */
    private function makeRequest(string $method, string $endpoint, array $params = []): array
    {
        $url = self::API_BASE_URL . $endpoint;
        
        try {
            $options = ['query' => $params];
            
            $response = $this->httpClient->request($method, $url, $options);
            $content = $response->getContent();
            
            return json_decode($content, true);
        } catch (\Exception $e) {
            $this->logger->error('Hunter API request error', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
