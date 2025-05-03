<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * SEC EDGAR API client - REAL Implementation
 */
class EdgarApiClient extends AbstractApiClient
{
    private const EDGAR_BASE_URL = 'https://data.sec.gov';
    private const SUBMISSIONS_ENDPOINT = '/submissions';
    private const COMPANY_TICKERS_URL = 'https://www.sec.gov/files/company_tickers.json';
    private array $cikMapping = [];
    private ?string $userAgent = null;

    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = self::EDGAR_BASE_URL;
        $this->apiKey = ''; // No API Key
        $this->userAgent = $this->params->get('edgar_api.user_agent', 'SymfonyApp research@example.com');
        $this->initializeCikMapping();
        if (empty($this->userAgent)) {
            $this->logger->error("EdgarApiClient: User-Agent is required but missing.");
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthParams(): array
    {
        return []; // No auth needed
    }

    /**
     * Override request to add User-Agent header.
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        if (empty($this->userAgent)) {
            throw new \LogicException('User-Agent must be set for SEC EDGAR API requests.');
        }
        $options = array_merge_recursive($options, ['headers' => ['User-Agent' => $this->userAgent]]);
        // Use parent::request, passing the FULL URL because EDGAR base URL differs from potential abstract base
        $url = rtrim(self::EDGAR_BASE_URL, '/') . '/' . ltrim($endpoint, '/');
        return parent::request($method, $url, $params, $options); // Parent now handles base URL logic differently
    }

    /**
     * Initialize CIK mapping - unchanged
     */
    private function initializeCikMapping(): void
    { /* ... same logic as before ... */
        $cacheDir = $this->params->get('kernel.cache_dir');
        $cikFile = $cacheDir . '/cik_mapping.json';
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
            $this->logger->info("Fetching new CIK map.");
            $this->fetchAndSaveCikMapping($cikFile);
        } catch (\Exception $e) {
            $this->logger->error('CIK map fetch failed: ' . $e->getMessage());
            if (empty($this->cikMapping) && file_exists($cikFile)) {
                $j = file_get_contents($cikFile);
                if ($j) {
                    $m = json_decode($j, true);
                    if (is_array($m)) {
                        $this->cikMapping = $m;
                        $this->logger->warning("Using stale CIK map.");
                        return;
                    }
                }
            }
            $this->cikMapping = [];
            $this->logger->error("CIK map init failed.");
        }
    }

    /**
     * Fetch CIK mapping - unchanged
     */
    private function fetchAndSaveCikMapping(string $filename): void
    { /* ... same logic as before ... */
        $client = HttpClient::create();
        $response = $client->request('GET', self::COMPANY_TICKERS_URL, ['headers' => ['User-Agent' => $this->userAgent]]);
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
                    $this->logger->error("Failed to write CIK map: {$filename}");
                } else {
                    $this->logger->info("Saved CIK map.", ['count' => count($mapping)]);
                    $this->cikMapping = $mapping;
                }
            } else {
                $this->logger->warning("Fetched CIK map empty.");
                $this->cikMapping = [];
            }
        } else {
            throw new \Exception('Failed CIK map fetch: ' . $response->getStatusCode());
        }
    }

    /**
     * Get CIK - unchanged
     */
    public function getCik(string $ticker): ?string
    { /* ... */
        return $this->cikMapping[strtoupper($ticker)]['cik'] ?? null;
    }

    /**
     * Get company submissions - unchanged
     */
    public function getCompanySubmissions(string $identifier): array
    { /* ... same logic as before ... */
        $endpoint = self::SUBMISSIONS_ENDPOINT;
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
        return $this->request('GET', $endpoint); // Use overridden request
    }

    /**
     * Get 10-K reports - unchanged
     */
    public function get10KReports(string $ticker, int $limit = 5): array
    { /* ... same logic as before ... */
        $cik = $this->getCik($ticker);
        if (!$cik) return [];
        $submissionsData = $this->getCompanySubmissions($cik);
        $filings = [];
        if (isset($submissionsData['filings']['recent'])) {
            $recentFilings = $submissionsData['filings']['recent'];
            $count = 0;
            for ($i = 0; $i < count($recentFilings['accessionNumber']); $i++) {
                if (isset($recentFilings['form'][$i]) && $recentFilings['form'][$i] === '10-K') {
                    $a = $recentFilings['accessionNumber'][$i];
                    $ac = str_replace('-', '', $a);
                    $p = $recentFilings['primaryDocument'][$i] ?? "{$ac}-index.htm";
                    $fd = $recentFilings['filingDate'][$i] ?? '';
                    $rd = $recentFilings['reportDate'][$i] ?? '';
                    $filings[] = ['id' => $a, 'cik' => $cik, 'companyName' => $submissionsData['name'] ?? $ticker, 'formType' => '10-K', 'filingDate' => $fd, 'reportDate' => $rd, 'accessionNumber' => $a, 'fileNumber' => $recentFilings['fileNumber'][$i] ?? '', 'documentUrl' => "https://www.sec.gov/Archives/edgar/data/{$cik}/{$ac}/{$a}-index.htm", 'htmlUrl' => "https://www.sec.gov/Archives/edgar/data/{$cik}/{$ac}/{$p}", 'textUrl' => "https://www.sec.gov/Archives/edgar/data/{$cik}/{$ac}/{$ac}.txt", 'description' => $recentFilings['primaryDocDescription'][$i] ?? 'Annual report', 'fiscalYear' => $rd ? date('Y', strtotime($rd)) : ($fd ? date('Y', strtotime($fd)) : 'N/A')];
                    $count++;
                    if ($count >= $limit) break;
                }
            }
        } else {
            $this->logger->warning("No recent filings structure.", ['identifier' => $cik]);
        }
        return $filings;
    }

    /**
     * Download report content - unchanged
     */
    public function downloadReport(string $url, string $format = 'text'): string
    { /* ... same logic as before ... */
        $client = HttpClient::create();
        try {
            usleep(150000);
            $response = $client->request('GET', $url, ['headers' => ['User-Agent' => $this->userAgent]]);
            if ($response->getStatusCode() >= 300) throw new \Exception("Download failed status: " . $response->getStatusCode());
            return $response->getContent();
        } catch (\Exception $e) {
            $this->logger->error("Failed report download {$url}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Extract report sections - unchanged
     */
    public function extractReportSections(string $content): array
    { /* ... same logic as before ... */
        $sections = ['item1' => $this->extractSectionBetweenMarkers($content, ['Item 1.', 'ITEM 1\. BUSINESS'], ['Item 1A.', 'ITEM 1A\. RISK FACTORS']), 'item1a' => $this->extractSectionBetweenMarkers($content, ['Item 1A.', 'ITEM 1A\. RISK FACTORS'], ['Item 1B.', 'ITEM 1B\. UNRESOLVED STAFF COMMENTS']), 'item7' => $this->extractSectionBetweenMarkers($content, ['Item 7.', 'ITEM 7\. MANAGEMENT\'S DISCUSSION AND ANALYSIS'], ['Item 7A.', 'ITEM 7A\. QUANTITATIVE AND QUALITATIVE DISCLOSURES']), 'item8' => $this->extractSectionBetweenMarkers($content, ['Item 8.', 'ITEM 8\. FINANCIAL STATEMENTS AND SUPPLEMENTARY DATA'], ['Item 9.', 'ITEM 9\. CHANGES IN AND DISAGREEMENTS'])];
        foreach ($sections as $key => $value) {
            if ($value) $sections[$key] = preg_replace("/\n{3,}/", "\n\n", trim($value));
            else {
                $this->logger->warning("Could not extract section {$key}.");
                $sections[$key] = 'Not Found.';
            }
        }
        return $sections;
    }

    /**
     * Extract a section from report content
     *
     * @param string $content Full report content
     * @param string $startMarker Section start marker
     * @param string $endMarker Section end marker
     * @param string $fallbackTitle Fallback title for searching
     * @return string Extracted section content
     */
    private function extractSection(string $content, string $startMarker, string $endMarker, string $fallbackTitle): string
    {
        // Try to find the section using the markers
        $pattern = '/' . preg_quote($startMarker, '/') . '.*?(?=' . preg_quote($endMarker, '/') . ')/s';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        // If that fails, try using the title
        $pattern = '/' . preg_quote($fallbackTitle, '/') . '.{0,100}.*?(?=' . preg_quote($endMarker, '/') . ')/s';
        if (preg_match($pattern, $content, $matches)) {
            return $matches[0];
        }

        // Return an empty string if section not found
        return '';
    }

    /**
     * Summarize a report section using Neuron AI
     *
     * @param string $sectionContent Section content
     * @param string $sectionTitle Section title
     * @return string Summary
     */
    public function summarizeSection(string $sectionContent, string $sectionTitle): string
    {
        // This would normally call the Neuron AI service
        // For now, return a placeholder
        return "Summary of {$sectionTitle} would be generated by Neuron AI.";
    }

    /**
     * {@inheritdoc}
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        // Not directly applicable for EDGAR API
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        // For EDGAR, we'll search by company name in the CIK mapping
        $results = [];
        $term = strtoupper($term);

        foreach ($this->cikMapping as $ticker => $company) {
            if (strpos($ticker, $term) !== false || strpos(strtoupper($company['name']), $term) !== false) {
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
     * {@inheritdoc}
     */
    public function getCompanyProfile(string $symbol): array
    {
        // Provide basic profile from CIK lookup
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

        // In a real implementation, we would search SEC data for company info
        // For now, return mock data
        return [
            'symbol' => $symbol,
            'name' => $this->cikMapping[$symbol]['name'] ?? 'Unknown Company',
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
     * {@inheritdoc}
     */
    public function getQuote(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        // Get the latest 10-K/10-Q reports and extract financial data
        $symbol = strtoupper($symbol);
        $cik = $this->getCik($symbol);

        if (!$cik) {
            return [];
        }

        $formType = $period === 'annual' ? '10-K' : '10-Q';
        $filings = $this->searchFilings("cik:{$cik} AND formType:\"{$formType}\"", [
            'forms' => [$formType],
            'limit' => 4  // Get last 4 quarters or years
        ]);

        // In a real implementation, we would extract financial data from filings
        // For now, return empty array
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getExecutives(string $symbol): array
    {
        throw new \BadMethodCallException('Not implemented');
    }

    /**
     * {@inheritdoc}
     */
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        throw new \BadMethodCallException('Not implemented');
    }
}
