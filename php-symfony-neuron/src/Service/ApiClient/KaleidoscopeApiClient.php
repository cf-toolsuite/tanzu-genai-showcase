<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Kaleidoscope API Client Implementation
 *
 * This client accesses the Kaleidoscope API for retrieving SEC filings data.
 * It requires an API key for authentication.
 */
class KaleidoscopeApiClient extends AbstractApiClient
{
    private const KSCOPE_BASE_URL = 'https://api.kscope.io/v2';
    private const SEC_SEARCH_ENDPOINT = '/sec/search';

    /**
     * Initialize the Kaleidoscope API client
     */
    protected function initialize(): void
    {
        $this->baseUrl = $this->params->get('kaleidoscope_api.base_url', self::KSCOPE_BASE_URL);
        $this->apiKey = $this->params->get('kaleidoscope_api.key');

        if (empty($this->apiKey)) {
            if ($this->logger) {
                $this->logger->error("KaleidoscopeApiClient: API key is required but not provided.");
            }
            throw new \LogicException('Kaleidoscope API key is required but not provided.');
        }

        if ($this->logger) {
            $this->logger->info("KaleidoscopeApiClient initialized with base URL: {$this->baseUrl}");
        }
    }

    /**
     * Get authentication parameters for API requests
     */
    protected function getAuthParams(): array
    {
        return [
            'key' => $this->apiKey
        ];
    }

    /**
     * Search for SEC filings by ticker symbol
     *
     * @param string $ticker Stock ticker symbol
     * @param array $params Additional parameters for the search
     * @return array List of SEC filings
     */
    public function searchFilings(string $ticker, array $params = []): array
    {
        $endpoint = self::SEC_SEARCH_ENDPOINT . '/' . urlencode($ticker);

        // Merge any additional parameters with auth params
        $requestParams = array_merge($params, $this->getAuthParams());

        try {
            return $this->request('GET', $endpoint, $requestParams);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Kaleidoscope API search filings failed: " . $e->getMessage());
            }
            return ['data' => [], 'total' => 0];
        }
    }

    /**
     * Filter filings by form type
     *
     * @param array $filings List of filings
     * @param string $formType Form type to filter (e.g., '10-K', '10-Q')
     * @param int $limit Maximum number of results to return
     * @return array Filtered filings
     */
    public function filterFilingsByType(array $filings, string $formType, int $limit = 5): array
    {
        if (empty($filings['data'])) {
            return [];
        }

        $filtered = [];
        $count = 0;

        foreach ($filings['data'] as $filing) {
            if ($filing['Form'] === $formType) {
                $filtered[] = $this->normalizeFilingData($filing);
                $count++;

                if ($count >= $limit) {
                    break;
                }
            }
        }

        return $filtered;
    }

    /**
     * Normalize filing data to a consistent format
     *
     * @param array $filing Raw filing data from Kaleidoscope API
     * @return array Normalized filing data
     */
    private function normalizeFilingData(array $filing): array
    {
        $date = isset($filing['Date']) ? date('Y-m-d', $filing['Date']) : '';

        return [
            'id' => $filing['acc'] ?? '',
            'cik' => $filing['CIK'] ?? '',
            'companyName' => $filing['Company Name'] ?? '',
            'formType' => $filing['Form'] ?? '',
            'formDescription' => $filing['Form_Desc'] ?? '',
            'filingDate' => $date,
            'reportDate' => $date, // Use same date as filing date if report date not provided
            'accessionNumber' => $filing['acc'] ?? '',
            'fileNumber' => '', // Not provided by Kaleidoscope
            'htmlUrl' => $filing['html'] ?? '',
            'pdfUrl' => $filing['pdf'] ?? '',
            'textUrl' => '', // Kaleidoscope doesn't provide a direct text URL
            'wordUrl' => $filing['word'] ?? '',
            'xbrlUrl' => $filing['xbrl'] ?? '',
            'xlsUrl' => $filing['xls'] ?? '',
            'ticker' => $filing['ticker'] ?? '',
            'description' => $filing['Form_Desc'] ?? '',
            'fiscalYear' => $date ? date('Y', strtotime($date)) : ''
        ];
    }

    /**
     * Get 10-K reports for a company
     *
     * @param string $ticker Stock ticker symbol
     * @param int $limit Maximum number of reports to return
     * @return array List of 10-K reports
     */
    public function get10KReports(string $ticker, int $limit = 5): array
    {
        $filings = $this->searchFilings($ticker);
        return $this->filterFilingsByType($filings, '10-K', $limit);
    }

    /**
     * Download report content from Kaleidoscope
     *
     * @param string $url URL of the report
     * @param string $format Format of the report (text, html, pdf, etc.)
     * @return string Report content
     */
    public function downloadReport(string $url, string $format = 'text'): string
    {
        $client = HttpClient::create();

        try {
            $response = $client->request('GET', $url);

            if ($response->getStatusCode() >= 300) {
                throw new \Exception("Report download failed: HTTP " . $response->getStatusCode());
            }

            return $response->getContent();
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to download report from {$url}: " . $e->getMessage());
            }
            throw $e;
        }
    }

    /**
     * Extract sections from a report
     *
     * @param string $content Report content
     * @return array Extracted sections
     */
    public function extractReportSections(string $content): array
    {
        $sections = [
            'item1' => $this->extractSectionBetweenMarkers(
                $content,
                ['Item 1.', 'ITEM 1\. BUSINESS'],
                ['Item 1A.', 'ITEM 1A\. RISK FACTORS']
            ),
            'item1a' => $this->extractSectionBetweenMarkers(
                $content,
                ['Item 1A.', 'ITEM 1A\. RISK FACTORS'],
                ['Item 1B.', 'ITEM 1B\. UNRESOLVED STAFF COMMENTS']
            ),
            'item7' => $this->extractSectionBetweenMarkers(
                $content,
                ['Item 7.', 'ITEM 7\. MANAGEMENT\'S DISCUSSION AND ANALYSIS'],
                ['Item 7A.', 'ITEM 7A\. QUANTITATIVE AND QUALITATIVE DISCLOSURES']
            ),
            'item8' => $this->extractSectionBetweenMarkers(
                $content,
                ['Item 8.', 'ITEM 8\. FINANCIAL STATEMENTS AND SUPPLEMENTARY DATA'],
                ['Item 9.', 'ITEM 9\. CHANGES IN AND DISAGREEMENTS']
            )
        ];

        foreach ($sections as $key => $value) {
            if ($value) {
                $sections[$key] = preg_replace("/\n{3,}/", "\n\n", trim($value));
            } else {
                if ($this->logger) {
                    $this->logger->warning("Could not extract section {$key}.");
                }
                $sections[$key] = 'Not Found.';
            }
        }

        return $sections;
    }

    /**
     * Extract a section between markers using regex patterns
     *
     * @param string $content Content to search in
     * @param array $startMarkers Possible start markers
     * @param array $endMarkers Possible end markers
     * @return string Extracted section or empty string if not found
     */
    private function extractSectionBetweenMarkers(string $content, array $startMarkers, array $endMarkers): string
    {
        $startPattern = implode('|', array_map(fn($m) => preg_quote($m, '/'), $startMarkers));
        $endPattern = implode('|', array_map(fn($m) => preg_quote($m, '/'), $endMarkers));

        $pattern = "/({$startPattern}).*?(?={$endPattern})/s";
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        return '';
    }

    /**
     * Search companies by term (implementation for ApiClientInterface)
     */
    public function searchCompanies(string $term): array
    {
        // For Kaleidoscope, we'll need to use the search results and extract unique companies
        $results = [];

        // Do a search for the term and extract company info
        try {
            $searchResults = $this->searchFilings($term);

            $companies = [];
            // Process search results to extract unique companies
            foreach ($searchResults['data'] as $filing) {
                $ticker = $filing['ticker'] ?? '';
                $companyName = $filing['Company Name'] ?? '';
                $cik = $filing['CIK'] ?? '';

                if (!empty($ticker) && !isset($companies[$ticker])) {
                    $companies[$ticker] = [
                        'symbol' => $ticker,
                        'name' => $companyName,
                        'cik' => $cik,
                        'exchange' => 'US', // Default value
                    ];
                }

                // Limit to reasonable number of results
                if (count($companies) >= 10) {
                    break;
                }
            }

            $results = array_values($companies);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Company search failed: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Get company profile (implementation for ApiClientInterface)
     */
    public function getCompanyProfile(string $symbol): array
    {
        // Get company filings
        $filings = $this->searchFilings($symbol);

        if (empty($filings['data'])) {
            return [
                'symbol' => $symbol,
                'name' => 'Unknown Company',
                'exchange' => 'US',
                'cik' => '',
                'industry' => '',
                'sector' => '',
                'description' => 'Company information not available',
                'website' => '',
                'employees' => 0,
                'address' => '',
                'phone' => '',
            ];
        }

        // Use the first filing to get company information
        $firstFiling = $filings['data'][0];

        return [
            'symbol' => $symbol,
            'name' => $firstFiling['Company Name'] ?? 'Unknown Company',
            'exchange' => 'US',
            'cik' => $firstFiling['CIK'] ?? '',
            'industry' => 'Not available from Kaleidoscope',
            'sector' => 'Not available from Kaleidoscope',
            'description' => 'Company description would be extracted from 10-K filings',
            'website' => '',
            'employees' => 0,
            'address' => '',
            'phone' => '',
        ];
    }

    /**
     * The following methods are required by ApiClientInterface but not
     * implemented for Kaleidoscope API as they are not relevant for SEC filings.
     */

    public function getQuote(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented for Kaleidoscope API');
    }

    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        // This could be implemented to extract financial data from 10-K/10-Q reports
        return [];
    }

    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        throw new \BadMethodCallException('Not implemented for Kaleidoscope API');
    }

    public function getExecutives(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented for Kaleidoscope API');
    }

    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        throw new \BadMethodCallException('Not implemented for Kaleidoscope API');
    }

    /**
     * {@inheritdoc}
     */
    public function getESGData(string $symbol): array
    {
        if ($this->logger) {
            $this->logger->warning('KaleidoscopeApiClient does not support ESG data retrieval.', ['symbol' => $symbol]);
        }

        return [
            'totalEsg' => null,
            'environmentScore' => null,
            'socialScore' => null,
            'governanceScore' => null,
            'peerComparison' => [],
            'lastUpdated' => null
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getRecentSecFilings(string $symbol, int $limit = 5): array
    {
        try {
            // This method is perfect for Kaleidoscope as it specializes in SEC filings
            $filings = $this->searchFilings($symbol, ['limit' => $limit]);

            if (empty($filings['data'])) {
                return [];
            }

            $result = [];
            foreach (array_slice($filings['data'], 0, $limit) as $filing) {
                $result[] = $this->normalizeFilingData($filing);
            }

            return $result;
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Failed to retrieve recent SEC filings", [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getAnalystRatings(string $symbol): array
    {
        if ($this->logger) {
            $this->logger->warning('KaleidoscopeApiClient does not support analyst ratings.', ['symbol' => $symbol]);
        }

        return [
            'ratings' => [],
            'consensus' => [
                'consensusRating' => 'N/A',
                'averagePriceTarget' => 0,
                'lowPriceTarget' => 0,
                'highPriceTarget' => 0,
                'buy' => 0,
                'hold' => 0,
                'sell' => 0,
                'upside' => 0
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getInsiderTrading(string $symbol, int $limit = 20): array
    {
        if ($this->logger) {
            $this->logger->warning('KaleidoscopeApiClient does not support insider trading data.', ['symbol' => $symbol]);
        }

        // For insider trading, we can check if there are any Form 4 filings
        // Form 4 is used to report changes in insider ownership
        try {
            $filings = $this->searchFilings($symbol);
            return $this->filterFilingsByType($filings, '4', $limit);
        } catch (\Exception $e) {
            if ($this->logger) {
                $this->logger->error("Error searching for Form 4 filings", [
                    'symbol' => $symbol,
                    'error' => $e->getMessage()
                ]);
            }
            return [];
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getInstitutionalOwnership(string $symbol, int $limit = 20): array
    {
        if ($this->logger) {
            $this->logger->warning('KaleidoscopeApiClient does not support institutional ownership data.', ['symbol' => $symbol]);
        }

        // Could potentially extract from 13F filings, but that's complex
        // Just return empty array for now
        return [];
    }

    /**
     * Get mock data for testing (not used in real implementation)
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        return [];
    }
}
