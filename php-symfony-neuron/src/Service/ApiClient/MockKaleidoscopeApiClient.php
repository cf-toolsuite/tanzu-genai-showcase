<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Mock Kaleidoscope API client for development/testing
 */
class MockKaleidoscopeApiClient extends KaleidoscopeApiClient
{
    /**
     * {@inheritdoc}
     */
    protected function initialize(): void
    {
        $this->baseUrl = 'mock://api.kscope.io';
        $this->apiKey = 'mock-api-key';
        if ($this->logger) {
            $this->logger->info('MockKaleidoscopeApiClient initialized');
        }
    }

    /**
     * Override request to return mock data
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        // Log mock request
        if ($this->logger) {
            $this->logger->info("Making MOCK Kaleidoscope API {$method} request to {$endpoint}", ['params' => $params]);
        }

        // Extract ticker from endpoint if present (e.g., /sec/search/AAPL)
        $ticker = '';
        if (preg_match('#/sec/search/([A-Z0-9]+)#', $endpoint, $matches)) {
            $ticker = $matches[1];
        }

        // Return mock data for SEC search endpoint
        if (strpos($endpoint, '/sec/search/') !== false) {
            return $this->getMockFilings($ticker);
        }

        // Default to empty response if no matching endpoint
        return ['data' => [], 'total' => 0];
    }

    /**
     * Get mock filings data for a ticker
     */
    private function getMockFilings(string $ticker): array
    {
        // Generate a mock CIK based on ticker
        $cik = intval(crc32($ticker)) % 10000000;

        // Create mock timestamp for recent dates
        $currentYear = date('Y');
        $today = time();
        $oneMonthAgo = strtotime('-1 month');
        $twoMonthsAgo = strtotime('-2 months');
        $threeMonthsAgo = strtotime('-3 months');
        $fourMonthsAgo = strtotime('-4 months');

        // Create mock data structure that matches Kaleidoscope API response format
        return [
            'data' => [
                [
                    'CIK' => $cik,
                    'Company Name' => 'MOCK ' . strtoupper($ticker) . ' INC',
                    'Date' => $today,
                    'Filer' => 'MOCK CEO',
                    'Form' => '10-K',
                    'Form_Desc' => 'Annual report pursuant to Section 13 or 15(d)',
                    'Form_Group' => 'Annual Reports',
                    'acc' => '0000' . $cik . '-' . $currentYear . '-000001',
                    'html' => 'https://cdn.kscope.io/' . md5($ticker.'1') . '.html',
                    'ixbrl' => 'https://cdn.kscope.io/' . md5($ticker.'1') . '-ixbrl.html',
                    'pdf' => 'https://cdn.kscope.io/' . md5($ticker.'1') . '.pdf',
                    'ticker' => $ticker,
                    'word' => 'https://cdn.kscope.io/' . md5($ticker.'1') . '.doc',
                    'xbrl' => 'https://cdn.kscope.io/' . md5($ticker.'1') . '-xbrl.html',
                    'xls' => 'https://cdn.kscope.io/' . md5($ticker.'1') . '.xlsx'
                ],
                [
                    'CIK' => $cik,
                    'Company Name' => 'MOCK ' . strtoupper($ticker) . ' INC',
                    'Date' => $oneMonthAgo,
                    'Filer' => 'MOCK CFO',
                    'Form' => '8-K',
                    'Form_Desc' => 'Current report pursuant to Section 13 or 15(d)',
                    'Form_Group' => '8k',
                    'acc' => '0000' . $cik . '-' . $currentYear . '-000002',
                    'html' => 'https://cdn.kscope.io/' . md5($ticker.'2') . '.html',
                    'ixbrl' => 'https://cdn.kscope.io/' . md5($ticker.'2') . '-ixbrl.html',
                    'pdf' => 'https://cdn.kscope.io/' . md5($ticker.'2') . '.pdf',
                    'ticker' => $ticker,
                    'word' => 'https://cdn.kscope.io/' . md5($ticker.'2') . '.doc',
                    'xbrl' => 'https://cdn.kscope.io/' . md5($ticker.'2') . '-xbrl.html',
                    'xls' => 'https://cdn.kscope.io/' . md5($ticker.'2') . '.xlsx'
                ],
                [
                    'CIK' => $cik,
                    'Company Name' => 'MOCK ' . strtoupper($ticker) . ' INC',
                    'Date' => $twoMonthsAgo,
                    'Filer' => $ticker . ' Inc.',
                    'Form' => '10-Q',
                    'Form_Desc' => 'Quarterly report pursuant to Section 13 or 15(d)',
                    'Form_Group' => 'Quarterly Reports',
                    'acc' => '0000' . $cik . '-' . $currentYear . '-000003',
                    'html' => 'https://cdn.kscope.io/' . md5($ticker.'3') . '.html',
                    'ixbrl' => 'https://cdn.kscope.io/' . md5($ticker.'3') . '-ixbrl.html',
                    'pdf' => 'https://cdn.kscope.io/' . md5($ticker.'3') . '.pdf',
                    'ticker' => $ticker,
                    'word' => 'https://cdn.kscope.io/' . md5($ticker.'3') . '.doc',
                    'xbrl' => 'https://cdn.kscope.io/' . md5($ticker.'3') . '-xbrl.html',
                    'xls' => 'https://cdn.kscope.io/' . md5($ticker.'3') . '.xlsx'
                ],
                [
                    'CIK' => $cik,
                    'Company Name' => 'MOCK ' . strtoupper($ticker) . ' INC',
                    'Date' => $threeMonthsAgo,
                    'Filer' => 'DIRECTOR 1',
                    'Form' => '4',
                    'Form_Desc' => 'Statement of changes in beneficial ownership of securities',
                    'Form_Group' => 'Insider Trading',
                    'acc' => '0000' . $cik . '-' . $currentYear . '-000004',
                    'html' => 'https://cdn.kscope.io/' . md5($ticker.'4') . '.html',
                    'ixbrl' => '',
                    'pdf' => 'https://cdn.kscope.io/' . md5($ticker.'4') . '.pdf',
                    'ticker' => $ticker,
                    'word' => 'https://cdn.kscope.io/' . md5($ticker.'4') . '.doc',
                    'xbrl' => '',
                    'xls' => ''
                ],
                [
                    'CIK' => $cik,
                    'Company Name' => 'MOCK ' . strtoupper($ticker) . ' INC',
                    'Date' => $fourMonthsAgo,
                    'Filer' => $ticker . ' Inc.',
                    'Form' => '10-K',
                    'Form_Desc' => 'Annual report pursuant to Section 13 or 15(d)',
                    'Form_Group' => 'Annual Reports',
                    'acc' => '0000' . $cik . '-' . (intval($currentYear)-1) . '-000005',
                    'html' => 'https://cdn.kscope.io/' . md5($ticker.'5') . '.html',
                    'ixbrl' => 'https://cdn.kscope.io/' . md5($ticker.'5') . '-ixbrl.html',
                    'pdf' => 'https://cdn.kscope.io/' . md5($ticker.'5') . '.pdf',
                    'ticker' => $ticker,
                    'word' => 'https://cdn.kscope.io/' . md5($ticker.'5') . '.doc',
                    'xbrl' => 'https://cdn.kscope.io/' . md5($ticker.'5') . '-xbrl.html',
                    'xls' => 'https://cdn.kscope.io/' . md5($ticker.'5') . '.xlsx'
                ]
            ],
            'total' => 5,
            'start' => 0,
            'end' => 5
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function downloadReport(string $url, string $format = 'text'): string
    {
        // Read from mock 10K file if available, otherwise return simple mock content
        $mockFilePath = __DIR__ . '/MockData/mock_10k.txt';

        if (file_exists($mockFilePath)) {
            return file_get_contents($mockFilePath);
        }

        // Fallback to basic mock content
        return "MOCK 10-K REPORT CONTENT\n\n" .
               "Item 1. BUSINESS\n\nThis is mock business description content for Kaleidoscope API.\n\n" .
               "Item 1A. RISK FACTORS\n\nThis is mock risk factors content for Kaleidoscope API.\n\n" .
               "Item 7. MANAGEMENT'S DISCUSSION AND ANALYSIS\n\nThis is mock MD&A content for Kaleidoscope API.\n\n" .
               "Item 8. FINANCIAL STATEMENTS AND SUPPLEMENTARY DATA\n\nThis is mock financial statements content for Kaleidoscope API.\n\n" .
               "Item 9. CHANGES IN AND DISAGREEMENTS WITH ACCOUNTANTS\n\nNone.";
    }

    /**
     * {@inheritdoc}
     */
    public function searchCompanies(string $term): array
    {
        // Return mock companies based on search term
        $companies = [];

        // Create variations of the search term
        $variations = [
            strtoupper($term),
            strtoupper($term) . ' CORP',
            strtoupper($term) . ' TECHNOLOGIES',
            'MOCK ' . strtoupper($term) . ' INC',
            'GLOBAL ' . strtoupper($term) . ' GROUP'
        ];

        foreach ($variations as $index => $name) {
            $ticker = substr(str_replace(' ', '', $name), 0, 4);
            $cik = intval(crc32($ticker)) % 10000000;

            $companies[] = [
                'symbol' => $ticker,
                'name' => $name,
                'cik' => $cik,
                'exchange' => 'US'
            ];

            // Limit to 5 results
            if (count($companies) >= 5) {
                break;
            }
        }

        return $companies;
    }

    /**
     * {@inheritdoc}
     */
    public function getCompanyProfile(string $symbol): array
    {
        // Generate mock CIK and other profile information
        $cik = intval(crc32($symbol)) % 10000000;

        return [
            'symbol' => $symbol,
            'name' => 'MOCK ' . strtoupper($symbol) . ' INC',
            'exchange' => 'US',
            'cik' => $cik,
            'industry' => 'Mock Industry',
            'sector' => 'Mock Sector',
            'description' => 'This is a mock company description for ' . strtoupper($symbol) . ' created for testing purposes.',
            'website' => 'https://www.mock-' . strtolower($symbol) . '.com',
            'employees' => rand(100, 50000),
            'address' => '123 Mock Street, Testville, CA 94000',
            'phone' => '+1-555-123-4567',
        ];
    }
}
