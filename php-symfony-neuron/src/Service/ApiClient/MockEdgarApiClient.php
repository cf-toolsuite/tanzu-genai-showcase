<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;

/**
 * Mock Implementation of SEC EDGAR API client
 */
class MockEdgarApiClient implements ApiClientInterface
{
    private LoggerInterface $logger;
    private array $mockCikMapping = [
        'AAPL' => ['cik' => '0000320193', 'name' => 'Apple Inc.'],
        'MSFT' => ['cik' => '0000789019', 'name' => 'Microsoft Corporation'],
        'AVGO' => ['cik' => '0001730168', 'name' => 'Broadcom Inc.'], // Corrected example CIK if known
    ];

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockEdgarApiClient instantiated.");
    }

    public function getCik(string $ticker): ?string
    {
        return $this->mockCikMapping[strtoupper($ticker)]['cik'] ?? null;
    }

    public function getCompanySubmissions(string $identifier): array
    {
        $this->logger->info("MockEdgarApiClient::getCompanySubmissions called", ['identifier' => $identifier]);
        return $this->getMockSubmissionsData($identifier);
    }

    public function get10KReports(string $ticker, int $limit = 5): array
    {
        $this->logger->info("MockEdgarApiClient::get10KReports called", ['ticker' => $ticker, 'limit' => $limit]);
        // Reuse the mock submissions data structure and filter/format
        $submissions = $this->getMockSubmissionsData($ticker);
        $filings = [];
        $recent = $submissions['filings']['recent'];
        $count = 0;
        $cik = $submissions['cik'];
        for ($i = 0; $i < count($recent['accessionNumber']); $i++) {
            if ($recent['form'][$i] === '10-K') {
                $a = $recent['accessionNumber'][$i];
                $ac = str_replace('-', '', $a);
                $p = $recent['primaryDocument'][$i] ?? "{$ac}-index.htm";
                $fd = $recent['filingDate'][$i] ?? '';
                $rd = $recent['reportDate'][$i] ?? '';
                $filings[] = ['id' => $a, 'cik' => $cik, 'companyName' => $submissions['name'], 'formType' => '10-K', 'filingDate' => $fd, 'reportDate' => $rd, 'accessionNumber' => $a, 'fileNumber' => $recent['fileNumber'][$i] ?? '', 'documentUrl' => "...", 'htmlUrl' => "...", 'textUrl' => "...", 'description' => $recent['primaryDocDescription'][$i] ?? 'Annual report', 'fiscalYear' => $rd ? date('Y', strtotime($rd)) : ($fd ? date('Y', strtotime($fd)) : 'N/A')];
                $count++;
                if ($count >= $limit) break;
            }
        }
        return $filings;
    }

    public function downloadReport(string $url, string $format = 'text'): string
    {
        $this->logger->info("MockEdgarApiClient::downloadReport called", ['url' => $url]);
        return "Mock 10-K Content \n\nItem 1. Business\nMock business description.\n\nItem 1A. Risk Factors\nMock risks.";
    }

    public function extractReportSections(string $content): array
    {
        $this->logger->info("MockEdgarApiClient::extractReportSections called");
        return [
            'item1' => "Mock Business section extracted.",
            'item1a' => "Mock Risk Factors section extracted.",
            'item7' => "Mock MD&A section extracted.",
            'item8' => "Mock Financial Statements section extracted.",
        ];
    }

    // --- Stubs for other ApiClientInterface methods ---
    public function searchCompanies(string $term): array
    {
        return [];
    }
    public function getCompanyProfile(string $symbol): array
    {
        $submissions = $this->getMockSubmissionsData($symbol);
        $cik = $submissions['cik'];
        return ['symbol' => $symbol, 'name' => $submissions['name'] ?? $symbol, 'cik' => $cik, 'industry' => $submissions['sicDescription'] ?? '', 'exchange' => $submissions['exchanges'][0] ?? '', 'address' => '', 'description' => '', 'sector' => '', 'employees' => 0, 'marketCap' => 0, 'peRatio' => 0, 'dividendYield' => 0, 'eps' => 0, 'beta' => 0, 'officers' => []];
    }
    public function getQuote(string $symbol): array
    {
        return ['symbol' => $symbol, 'price' => 0];
    }
    public function getFinancials(string $symbol, string $period = 'quarterly'): array
    {
        return [];
    }
    public function getCompanyNews(string $symbol, int $limit = 5): array
    {
        return [];
    }
    public function getExecutives(string $symbol): array
    {
        return [];
    }
    public function getHistoricalPrices(string $symbol, string $interval = 'daily', string $outputSize = 'compact'): array
    {
        return [];
    }

    // --- Mock Data Generation ---
    private function getMockSubmissionsData(string $identifier): array
    { /* same as before */
        $ticker = $identifier;
        $cik = $this->getCik($ticker);
        if (!$cik) {
            $ticker = 'AAPL';
            $cik = $this->getCik($ticker);
        }
        $name = $this->mockCikMapping[strtoupper($ticker)]['name'] ?? 'Mock Company';
        $accessionNumbers = [];
        $forms = [];
        $filingDates = [];
        $reportDates = [];
        $primaryDocs = [];
        $fileNumbers = [];
        $primaryDocDescs = [];
        for ($i = 0; $i < 10; $i++) {
            $form = (mt_rand(0, 1) === 0 && $i < 3) ? '10-K' : (mt_rand(0, 1) === 0 ? '10-Q' : '8-K');
            $date = (new \DateTime())->modify('-' . ($i * 30 + mt_rand(0, 15)) . ' days');
            $accNum = $date->format('Ymd') . '-' . mt_rand(10, 99) . '-' . mt_rand(100000, 999999);
            $accessionNumbers[] = $accNum;
            $forms[] = $form;
            $filingDates[] = $date->format('Y-m-d');
            $reportDates[] = ($form === '10-K' || $form === '10-Q') ? $date->modify('-1 day')->format('Y-m-d') : '';
            $primaryDocs[] = $form . '.htm';
            $fileNumbers[] = '001-' . mt_rand(10000, 99999);
            $primaryDocDescs[] = $form === '10-K' ? 'Annual' : ($form === '10-Q' ? 'Quarterly' : 'Current');
        }
        return ['cik' => $cik, 'name' => $name, 'sicDescription' => 'Mock Industry', 'exchanges' => ['MOCK'], 'filings' => ['recent' => ['accessionNumber' => $accessionNumbers, 'filingDate' => $filingDates, 'reportDate' => $reportDates, 'form' => $forms, 'primaryDocument' => $primaryDocs, 'fileNumber' => $fileNumbers, 'primaryDocDescription' => $primaryDocDescs]]];
    }
}
