<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * SEC API client (sec-api.io) - REAL Implementation
 */
class SecApiClient extends AbstractApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'https://api.sec-api.io';
        $this->apiKey = $this->params->get('sec_api.api_key', '');
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        // Authentication is via 'token' query parameter
        return ['token' => $this->apiKey];
    }

    /**
     * Override request to handle sec-api.io's query param auth for POST.
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        // Ensure API Key is present
        if (empty($this->apiKey)) {
            throw new \LogicException("API Key is required but missing for SecApiClient");
        }

        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $authParams = $this->getAuthParams(); // Get {token: key}

        $requestOptions = array_merge_recursive([ // Use recursive merge
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Symfony/NeuronAI Financial Research Application'
            ],
            // Always add token to query params
            'query' => $authParams,
            // Add payload to json if POST/PUT etc.
            'json' => ($method !== 'GET') ? $params : null,
        ], $options); // Merge caller options last

        // If GET, merge regular params into query as well
        if ($method === 'GET' && !empty($params)) {
            $requestOptions['query'] = array_merge($requestOptions['query'], $params);
        }

        // Remove null json body if present
        if (isset($requestOptions['json']) && $requestOptions['json'] === null) {
            unset($requestOptions['json']);
        }

        // Call parent::request with the final URL and options, BUT empty params
        // as they are now part of requestOptions['query'] or requestOptions['json']
        // And the parent needs the *relative* endpoint if baseUrl is used there.
        // Let's call the httpClient directly here instead of parent::request to avoid confusion.
        try {
            $this->logger->info("Making REAL API {$method} request to {$url}", ['option_keys' => array_keys($requestOptions)]);
            $response = $this->httpClient->request($method, $url, $requestOptions);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $errorBody = '';
                try {
                    $errorBody = $response->getContent(false);
                } catch (\Exception $e) {
                }
                $this->logger->error("API returned error {$statusCode}", ['url' => $url, 'response' => $errorBody]);
                throw new \RuntimeException("API request to {$url} failed with status code {$statusCode}. Response: " . $errorBody, $statusCode);
            }
            return $response->toArray();
        } catch (\Exception $e) {
            $this->logger->error("API request failed for {$url}: {$e->getMessage()}");
            throw new \RuntimeException("Failed to execute API request to {$url}: " . $e->getMessage(), $e->getCode(), $e);
        }
    }

    // --- Implementation of ApiClientInterface methods ---

    public function searchCompanies(string $term): array
    {
        $this->logger->warning('SecApiClient does not support searchCompanies.');
        throw new \BadMethodCallException('SecApiClient does not support searchCompanies.');
    }
    public function getCompanyProfile(string $symbol): array
    {
        $this->logger->warning('SecApiClient does not support getCompanyProfile.');
        throw new \BadMethodCallException('SecApiClient does not support getCompanyProfile.');
    }
    public function getQuote(string $symbol): array
    {
        $this->logger->warning('SecApiClient does not support getQuote.');
        throw new \BadMethodCallException('SecApiClient does not support getQuote.');
    }
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        $this->logger->warning('SecApiClient does not support getFinancials.');
        throw new \BadMethodCallException('SecApiClient does not support getFinancials.');
    }
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        $this->logger->warning('SecApiClient does not support getCompanyNews.');
        throw new \BadMethodCallException('SecApiClient does not support getCompanyNews.');
    }
    public function getExecutives(string $symbol): array
    {
        $this->logger->warning('SecApiClient does not support getExecutives.');
        throw new \BadMethodCallException('SecApiClient does not support getExecutives.');
    }
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        $this->logger->warning('SecApiClient does not support getHistoricalPrices.');
        throw new \BadMethodCallException('SecApiClient does not support getHistoricalPrices.');
    }

    // --- Specific methods for SecApiClient ---

    public function getInsiderTrading(string $symbol, int $limit = 20, ?\DateTime $from = null, ?\DateTime $to = null): array
    {
        $endpoint = '/filings';
        if (!$from) $from = new \DateTime('-90 days');
        if (!$to) $to = new \DateTime();
        $queryString = "(ticker:{$symbol} OR companyName:\"{$symbol}\") AND formType:\"4\" AND filedAt:[{$from->format('Y-m-d')} TO {$to->format('Y-m-d')}]";
        $queryPayload = ['query' => ['query_string' => ['query' => $queryString]], 'from' => 0, 'size' => $limit, 'sort' => [['filedAt' => ['order' => 'desc']]]];

        // Real API call
        $response = $this->request('POST', $endpoint, $queryPayload);

        // Processing
        $insiderTrades = [];
        if (isset($response['filings']) && is_array($response['filings'])) {
            foreach ($response['filings'] as $filing) {
                $parsedData = $this->parseForm4Data($filing);
                if ($parsedData) $insiderTrades[] = $parsedData;
            }
        }
        return $insiderTrades;
    }

    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        $endpoint = '/filings';
        $queryString = "formType:\"13F*\" AND holdings.ticker:{$symbol}";
        $queryPayload = ['query' => ['query_string' => ['query' => $queryString]], 'from' => 0, 'size' => $limit, 'sort' => [['filedAt' => ['order' => 'desc']]]];

        // Real API call
        $response = $this->request('POST', $endpoint, $queryPayload);

        // Processing
        $institutionalHoldings = [];
        $institutionCiks = [];
        if (isset($response['filings']) && is_array($response['filings'])) {
            foreach ($response['filings'] as $filing) {
                $cik = $filing['cik'] ?? null;
                if (!$cik || isset($institutionCiks[$cik])) continue;
                if (isset($filing['holdings']) && is_array($filing['holdings'])) {
                    foreach ($filing['holdings'] as $holding) {
                        if (isset($holding['ticker']) && $holding['ticker'] === $symbol) {
                            $institutionCiks[$cik] = true;
                            $institutionalHoldings[] = ['institutionName' => $filing['companyName'] ?? 'Unknown', 'cik' => $cik, 'filingDate' => $filing['filedAt'] ?? '', 'reportDate' => $filing['periodOfReport'] ?? '', 'sharesHeld' => (int)($holding['shares'] ?? 0), 'valueInDollars' => (float)($holding['value'] ?? 0) * 1000, 'percentOfPortfolio' => (float)($holding['percentage'] ?? 0), 'changeFromPrevious' => (int)(($holding['shares'] ?? 0) - ($holding['priorShares'] ?? 0))];
                            break;
                        }
                    }
                }
                if (count($institutionalHoldings) >= $limit) break;
            }
        }
        return $institutionalHoldings;
    }

    public function getAnalystRatings(string $symbol): array
    {
        // SEC API doesn't support this directly
        $this->logger->warning("getAnalystRatings is not supported by the real SecApiClient.");
        throw new \BadMethodCallException('SecApiClient does not support getAnalystRatings method.');
        // return []; // Or return empty if preferred over exception
    }

    /**
     * Parse Form 4 filing data - unchanged
     */
    private function parseForm4Data(array $filing): ?array
    { /* ... same logic as before ... */
        if (!isset($filing['reportingOwner']) || !isset($filing['transactions'])) {
            $this->logger->warning('Skipping Form 4 parsing due to missing data.', ['filingId' => $filing['id'] ?? 'N/A']);
            return null;
        }
        $owner = $filing['reportingOwner'];
        $relationship = $owner['relationship'] ?? [];
        $parsedTransactions = [];
        foreach ($filing['transactions'] as $tx) {
            $parsedTransactions[] = ['transactionType' => $tx['transactionCode'] ?? 'N/A', 'securityType' => $tx['securityTitle'] ?? 'N/A', 'shares' => (float)($tx['transactionShares']['value'] ?? 0), 'pricePerShare' => (float)($tx['transactionPricePerShare']['value'] ?? 0), 'totalValue' => (float)($tx['transactionShares']['value'] ?? 0) * (float)($tx['transactionPricePerShare']['value'] ?? 0), 'ownershipType' => $tx['ownershipNature']['directOrIndirectOwnership']['value'] ?? 'N/A', 'sharesOwnedFollowing' => (float)($tx['postTransactionAmounts']['sharesOwnedFollowingTransaction']['value'] ?? 0)];
        }
        return ['filingId' => $filing['id'] ?? ($filing['accessionNo'] ?? 'N/A'), 'filingDate' => $filing['filedAt'] ?? '', 'issuerName' => $filing['companyName'] ?? 'Unknown', 'issuerTicker' => $filing['ticker'] ?? ($filing['tickers'][0] ?? 'N/A'), 'ownerName' => $owner['reportingOwnerName'] ?? 'Unknown Insider', 'ownerTitle' => $relationship['officerTitle'] ?? 'N/A', 'isDirector' => $relationship['isDirector'] ?? false, 'isOfficer' => $relationship['isOfficer'] ?? false, 'isTenPercentOwner' => $relationship['isTenPercentOwner'] ?? false, 'transactionDate' => $filing['periodOfReport'] ?? '', 'formType' => $filing['formType'] ?? '4', 'formUrl' => $filing['linkToFilingDetails'] ?? '', 'transactions' => $parsedTransactions];
    }
}
