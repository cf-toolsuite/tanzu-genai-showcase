<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * SEC EDGAR API client - REAL Implementation
 *
 * This client accesses the SEC EDGAR database directly without requiring an API key.
 * The SEC requires a User-Agent header with contact information for all requests.
 */
class EdgarApiClient extends AbstractApiClient
{
    private const EDGAR_BASE_URL = 'https://data.sec.gov';
    private const SUBMISSIONS_ENDPOINT = '/submissions';
    private const COMPANY_TICKERS_URL = 'https://www.sec.gov/files/company_tickers.json';
    private array $cikMapping = [];
    private ?string $userAgent = null;

    /**
     * Initialize the EDGAR API client
     *
     * Note: SEC EDGAR API does not require an API key but requires a User-Agent header
     * with contact information.
     */
    protected function initialize(): void
    {
        $this->baseUrl = self::EDGAR_BASE_URL;
        $this->apiKey = ''; // No API Key required for SEC EDGAR

        // Set a proper User-Agent with contact information as required by SEC
        $defaultUserAgent = 'Company Research App research@example.com';
        $this->userAgent = $this->params->get('edgar_api.user_agent', $defaultUserAgent);

        if (empty($this->userAgent)) {
            $this->logger->error("EdgarApiClient: User-Agent is required for SEC EDGAR API requests.");
            $this->userAgent = $defaultUserAgent;
        }

        $this->initializeCikMapping();
    }

    /**
     * No authentication parameters needed for SEC EDGAR
     */
    protected function getAuthParams(): array
    {
        return []; // No auth parameters needed for SEC EDGAR
    }

    /**
     * Override request to add User-Agent header which is required by SEC EDGAR
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        if (empty($this->userAgent)) {
            throw new \LogicException('User-Agent must be set for SEC EDGAR API requests.');
        }

        // SEC has rate limits - add a small delay to avoid hitting them
        usleep(100000); // 100ms delay

        // Add the required User-Agent header
        $options = array_merge_recursive($options, [
            'headers' => [
                'User-Agent' => $this->userAgent
            ]
        ]);

        // For EDGAR API, we may need to use the full URL in some cases
        if (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://')) {
            $url = $endpoint;
        } else {
            $url = rtrim(self::EDGAR_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
        }

        try {
            $this->logger->info("Making SEC EDGAR API request to {$url}");

            // Use parent request but with the full URL
            $result = parent::request($method, $url, $params, $options);
            return $result;
        } catch (\Exception $e) {
            $this->logger->error("SEC EDGAR API request failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Initialize CIK mapping for ticker symbol to CIK conversion
     */
    private function initializeCikMapping(): void
    {
        $cacheDir = $this->params->get('kernel.cache_dir');
        $cikFile = $cacheDir . '/cik_mapping.json';

        // Try to load from cache first
        if (file_exists($cikFile) && filemtime($cikFile) > (time() - 7 * 86400)) {
            $j = file_get_contents($cikFile);
            if ($j) {
                $m = json_decode($j, true);
                if (is_array($m)) {
                    $this->cikMapping = $m;
                    $this->logger->info("Loaded CIK map from cache.");
                    return;
                }
            }
        }

        try {
            $this->logger->info("Fetching new CIK map from SEC.");
            $this->fetchAndSaveCikMapping($cikFile);
        } catch (\Exception $e) {
            $this->logger->error('CIK map fetch failed: ' . $e->getMessage());

            // Try to use stale cache if available
            if (empty($this->cikMapping) && file_exists($cikFile)) {
                $j = file_get_contents($cikFile);
                if ($j) {
                    $m = json_decode($j, true);
                    if (is_array($m)) {
                        $this->cikMapping = $m;
                        $this->logger->warning("Using stale CIK map due to fetch failure.");
                        return;
                    }
                }
            }

            $this->cikMapping = [];
            $this->logger->error("CIK map initialization failed completely.");
        }
    }

    /**
     * Fetch CIK mapping from SEC and save to cache
     */
    private function fetchAndSaveCikMapping(string $filename): void
    {
        // Create a one-time client for this request
        $client = HttpClient::create();
        $response = $client->request('GET', self::COMPANY_TICKERS_URL, [
            'headers' => ['User-Agent' => $this->userAgent]
        ]);

        if ($response->getStatusCode() === 200) {
            $data = $response->toArray();
            $mapping = [];

            foreach ($data as $entry) {
                if (isset($entry['ticker']) && isset($entry['cik_str'])) {
                    $ticker = strtoupper($entry['ticker']);
                    $cik = str_pad($entry['cik_str'], 10, '0', STR_PAD_LEFT);
                    $name = $entry['title'] ?? 'Unknown';
                    $mapping[$ticker] = ['cik' => $cik, 'name' => $name];
                }
            }

            if (!empty($mapping)) {
                $dir = dirname($filename);
                if (!is_dir($dir)) {
                    mkdir($dir, 0775, true);
                }

                if (file_put_contents($filename, json_encode($mapping)) === false) {
                    $this->logger->error("Failed to write CIK map to cache: {$filename}");
                } else {
                    $this->logger->info("Saved CIK map to cache.", ['count' => count($mapping)]);
                    $this->cikMapping = $mapping;
                }
            } else {
                $this->logger->warning("Fetched CIK map was empty.");
                $this->cikMapping = [];
            }
        } else {
            throw new \Exception('Failed to fetch CIK map: HTTP ' . $response->getStatusCode());
        }
    }

    /**
     * Get CIK (Central Index Key) for a ticker symbol
     *
     * @param string $ticker Stock ticker symbol
     * @return string|null CIK or null if not found
     */
    public function getCik(string $ticker): ?string
    {
        $ticker = strtoupper($ticker);
        return $this->cikMapping[$ticker]['cik'] ?? null;
    }

    /**
     * Get company submissions from SEC EDGAR
     *
     * @param string $identifier Ticker symbol or CIK
     * @return array Company submissions data
     */
    public function getCompanySubmissions(string $identifier): array
    {
        $endpoint = self::SUBMISSIONS_ENDPOINT;

        // Check if identifier is a CIK or ticker
        if (ctype_digit($identifier) && strlen($identifier) <= 10) {
            $cik = str_pad($identifier, 10, '0', STR_PAD_LEFT);
            $endpoint .= "/CIK{$cik}.json";
        } else {
            $cik = $this->getCik(strtoupper($identifier));
            if (!$cik) {
                $this->logger->error("CIK not found for ticker: {$identifier}");
                return [];
            }
            $endpoint .= "/CIK{$cik}.json";
        }

        return $this->request('GET', $endpoint);
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
        $cik = $this->getCik($ticker);
        if (!$cik) {
            $this->logger->warning("Cannot fetch 10-K reports: CIK not found for ticker {$ticker}");
            return [];
        }

        $submissionsData = $this->getCompanySubmissions($cik);
        $filings = [];

        if (isset($submissionsData['filings']['recent'])) {
            $recentFilings = $submissionsData['filings']['recent'];
            $count = 0;

            for ($i = 0; $i < count($recentFilings['accessionNumber']); $i++) {
                if (isset($recentFilings['form'][$i]) && $recentFilings['form'][$i] === '10-K') {
                    $accessionNumber = $recentFilings['accessionNumber'][$i];
                    $accessionNumberNoHyphens = str_replace('-', '', $accessionNumber);
                    $primaryDocument = $recentFilings['primaryDocument'][$i] ?? "{$accessionNumberNoHyphens}-index.htm";
                    $filingDate = $recentFilings['filingDate'][$i] ?? '';
                    $reportDate = $recentFilings['reportDate'][$i] ?? '';

                    $filings[] = [
                        'id' => $accessionNumber,
                        'cik' => $cik,
                        'companyName' => $submissionsData['name'] ?? $ticker,
                        'formType' => '10-K',
                        'filingDate' => $filingDate,
                        'reportDate' => $reportDate,
                        'accessionNumber' => $accessionNumber,
                        'fileNumber' => $recentFilings['fileNumber'][$i] ?? '',
                        'documentUrl' => "https://www.sec.gov/Archives/edgar/data/{$cik}/{$accessionNumberNoHyphens}/{$accessionNumber}-index.htm",
                        'htmlUrl' => "https://www.sec.gov/Archives/edgar/data/{$cik}/{$accessionNumberNoHyphens}/{$primaryDocument}",
                        'textUrl' => "https://www.sec.gov/Archives/edgar/data/{$cik}/{$accessionNumberNoHyphens}/{$accessionNumberNoHyphens}.txt",
                        'description' => $recentFilings['primaryDocDescription'][$i] ?? 'Annual report',
                        'fiscalYear' => $reportDate ? date('Y', strtotime($reportDate)) : ($filingDate ? date('Y', strtotime($filingDate)) : 'N/A')
                    ];

                    $count++;
                    if ($count >= $limit) break;
                }
            }
        } else {
            $this->logger->warning("No recent filings found for {$ticker}");
        }

        return $filings;
    }

    /**
     * Download report content from SEC EDGAR
     *
     * @param string $url URL of the report
     * @param string $format Format of the report (text or html)
     * @return string Report content
     */
    public function downloadReport(string $url, string $format = 'text'): string
    {
        $client = HttpClient::create();

        try {
            // Add a delay to avoid rate limiting
            usleep(150000); // 150ms delay

            $response = $client->request('GET', $url, [
                'headers' => ['User-Agent' => $this->userAgent]
            ]);

            if ($response->getStatusCode() >= 300) {
                throw new \Exception("Report download failed: HTTP " . $response->getStatusCode());
            }

            return $response->getContent();
        } catch (\Exception $e) {
            $this->logger->error("Failed to download report from {$url}: " . $e->getMessage());
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
                $this->logger->warning("Could not extract section {$key}.");
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
        $results = [];
        $term = strtoupper($term);

        foreach ($this->cikMapping as $ticker => $company) {
            if (strpos($ticker, $term) !== false ||
                strpos(strtoupper($company['name']), $term) !== false) {
                $results[] = [
                    'symbol' => $ticker,
                    'name' => $company['name'],
                    'cik' => $company['cik'],
                    'exchange' => 'US',  // Default value
                ];
            }

            // Limit to reasonable number of results
            if (count($results) >= 10) {
                break;
            }
        }

        return $results;
    }

    /**
     * Get company profile (implementation for ApiClientInterface)
     */
    public function getCompanyProfile(string $symbol): array
    {
        $cik = $this->getCik($symbol);

        if (!$cik) {
            $this->logger->warning('CIK not found for ticker: ' . $symbol);
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

        return [
            'symbol' => $symbol,
            'name' => $this->cikMapping[strtoupper($symbol)]['name'] ?? 'Unknown Company',
            'exchange' => 'US',
            'cik' => $cik,
            'industry' => 'Not available from SEC EDGAR',
            'sector' => 'Not available from SEC EDGAR',
            'description' => 'Company description would be extracted from 10-K filings',
            'website' => '',
            'employees' => 0,
            'address' => '',
            'phone' => '',
        ];
    }

    /**
     * The following methods are required by ApiClientInterface but not
     * implemented for EDGAR API as they are not relevant for SEC filings.
     */

    public function getQuote(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented for EDGAR API');
    }

    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        // This could be implemented to extract financial data from 10-K/10-Q reports
        return [];
    }

    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        throw new \BadMethodCallException('Not implemented for EDGAR API');
    }

    public function getExecutives(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented for EDGAR API');
    }

    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        throw new \BadMethodCallException('Not implemented for EDGAR API');
    }

    /**
     * Get mock data for testing (not used in real implementation)
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        return [];
    }
}
