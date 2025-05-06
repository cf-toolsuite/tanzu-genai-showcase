<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Mock SEC EDGAR API client for development/testing
 */
class MockEdgarApiClient extends EdgarApiClient
{
    /**
     * {@inheritdoc}
     */
    private ?string $userAgent = null;

    protected function initialize(): void
    {
        $this->baseUrl = 'mock://api.sec.gov';
        $this->apiKey = '';
        $this->userAgent = 'Mock EDGAR Client';
        $this->logger->info('MockEdgarApiClient initialized');
    }

    /**
     * Override request to return mock data
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        // Log mock request
        $this->logger->info("Making MOCK API {$method} request to {$endpoint}", ['params' => $params]);

        // Use a simplified pattern matching approach to return different mock data based on endpoint
        if (strpos($endpoint, '/submissions') !== false) {
            return $this->getMockSubmissions($endpoint, $params);
        }

        // Default to empty response if no matching endpoint
        return [];
    }

    /**
     * Get mock submissions data
     */
    private function getMockSubmissions(string $endpoint, array $params): array
    {
        // Extract CIK from endpoint if present
        $cik = '';
        if (preg_match('/CIK(\d+)\.json/', $endpoint, $matches)) {
            $cik = $matches[1];
        }

        // Return mock data structure that matches EDGAR API response format
        return [
            'cik' => $cik,
            'name' => 'MOCK COMPANY ' . ($cik ? substr($cik, -4) : 'INC'),
            'filings' => [
                'recent' => [
                    'accessionNumber' => ['0000000000-22-000001', '0000000000-21-000001', '0000000000-20-000001'],
                    'filingDate' => ['2022-04-01', '2021-04-01', '2020-04-01'],
                    'reportDate' => ['2022-03-31', '2021-03-31', '2020-03-31'],
                    'form' => ['10-K', '10-K', '10-K'],
                    'primaryDocument' => ['form10k.htm', 'form10k.htm', 'form10k.htm'],
                    'primaryDocDescription' => ['Annual Report', 'Annual Report', 'Annual Report'],
                    'fileNumber' => ['000-00000', '000-00000', '000-00000'],
                ]
            ]
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function get10KReports(string $ticker, int $limit = 5): array
    {
        $mockReports = [];

        // Create mock 10-K reports
        for ($i = 0; $i < min($limit, 5); $i++) {
            $year = date('Y') - $i;
            $filing_date = $year . '-04-01';
            $report_date = $year . '-03-31';
            $accession = '0000000000-' . substr($year, -2) . '-00000' . ($i + 1);
            $accession_no_dashes = str_replace('-', '', $accession);

            $mockReports[] = [
                'id' => $accession,
                'cik' => '0000000000',
                'companyName' => 'MOCK ' . strtoupper($ticker) . ' INC',
                'formType' => '10-K',
                'filingDate' => $filing_date,
                'reportDate' => $report_date,
                'accessionNumber' => $accession,
                'fileNumber' => '000-00000',
                'documentUrl' => "https://www.sec.gov/Archives/edgar/data/0000000000/{$accession_no_dashes}/{$accession}-index.htm",
                'htmlUrl' => "https://www.sec.gov/Archives/edgar/data/0000000000/{$accession_no_dashes}/form10k.htm",
                'textUrl' => "https://www.sec.gov/Archives/edgar/data/0000000000/{$accession_no_dashes}/{$accession_no_dashes}.txt",
                'description' => 'Annual report',
                'fiscalYear' => $year
            ];
        }

        return $mockReports;
    }

    /**
     * {@inheritdoc}
     */
    public function downloadReport(string $url, string $format = 'text'): string
    {
        // Return mock content - this could be enhanced to return more realistic mock data
        return "MOCK 10-K REPORT CONTENT\n\n" .
               "Item 1. BUSINESS\n\nThis is mock business description content.\n\n" .
               "Item 1A. RISK FACTORS\n\nThis is mock risk factors content.\n\n" .
               "Item 7. MANAGEMENT'S DISCUSSION AND ANALYSIS\n\nThis is mock MD&A content.\n\n" .
               "Item 8. FINANCIAL STATEMENTS AND SUPPLEMENTARY DATA\n\nThis is mock financial statements content.\n\n" .
               "Item 9. CHANGES IN AND DISAGREEMENTS WITH ACCOUNTANTS\n\nNone.";
    }

    /**
     * {@inheritdoc}
     */
    public function extractReportSections(string $content): array
    {
        // Return mock sections
        return [
            'item1' => 'This is mock business description content.',
            'item1a' => 'This is mock risk factors content.',
            'item7' => 'This is mock MD&A content.',
            'item8' => 'This is mock financial statements content.'
        ];
    }
}
