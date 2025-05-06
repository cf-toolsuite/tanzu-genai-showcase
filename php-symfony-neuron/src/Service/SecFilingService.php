<?php

// src/Service/SecFilingService.php
namespace App\Service;

use App\Entity\Company;
use App\Entity\SecFiling;
use App\Repository\SecFilingRepository;
use App\Service\ApiClient\SecApiClientFactory;
use App\Service\ApiClient\ApiClientInterface;
use App\Service\ApiClient\KaleidoscopeApiClient;
use App\Service\ApiClient\MockKaleidoscopeApiClient;
use App\Service\NeuronAiService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class SecFilingService
{
    private ApiClientInterface $secApiClient; // SEC API client
    private NeuronAiService $neuronAiService;
    private EntityManagerInterface $entityManager;
    private SecFilingRepository $secFilingRepository;
    private LoggerInterface $logger;

    /**
     * Constructor
     */
    public function __construct(
        SecApiClientFactory $secApiClientFactory, // Inject the unified factory
        NeuronAiService $neuronAiService,
        EntityManagerInterface $entityManager,
        SecFilingRepository $secFilingRepository,
        LoggerInterface $logger
    ) {
        // Get client from factory - will be Kaleidoscope based on settings
        $this->secApiClient = $secApiClientFactory->createClient();

        // Assign other dependencies
        $this->neuronAiService = $neuronAiService;
        $this->entityManager = $entityManager;
        $this->secFilingRepository = $secFilingRepository;
        $this->logger = $logger;
    }

    // --- Existing Methods (Unchanged content, just ensure they use the correct property) ---

    /**
     * Fetch and store 10-K reports for a company
     */
    public function import10KReports(Company $company, bool $downloadContent = false, int $limit = 5): array
    {
        if (!$company->getTickerSymbol()) {
            $this->logger->warning('Cannot import 10-K reports: company has no ticker symbol');
            return [];
        }
        // Ensure we have the correct client type for SEC API specific methods
        if (!($this->secApiClient instanceof KaleidoscopeApiClient ||
              $this->secApiClient instanceof MockKaleidoscopeApiClient)) {
             $this->logger->error('Cannot import 10-K reports: Invalid SEC API client type.');
             return [];
        }

        $reports = $this->secApiClient->get10KReports($company->getTickerSymbol(), $limit);

        if (empty($reports)) {
            $this->logger->warning('No 10-K reports found for ' . $company->getTickerSymbol());
            return [];
        }
        $importedFilings = [];
        foreach ($reports as $report) {
            $existingFiling = $this->secFilingRepository->findByAccessionNumber($report['accessionNumber']);
            if ($existingFiling) {
                $this->logger->info('SEC filing already exists', ['accession' => $report['accessionNumber']]);
                $importedFilings[] = $existingFiling;
                continue;
            }
            $filing = new SecFiling();
            $filing->setCompany($company);
            $filing->setFormType($report['formType']);
            try { $filing->setFilingDate(new \DateTime($report['filingDate'])); } catch (\Exception $e) {}
            if (isset($report['reportDate']) && $report['reportDate']) {
                try { $filing->setReportDate(new \DateTime($report['reportDate'])); } catch (\Exception $e) {}
            }
            $filing->setAccessionNumber($report['accessionNumber']);
            $filing->setFileNumber($report['fileNumber'] ?? null);
            $filing->setDescription($report['description'] ?? null);
            $filing->setDocumentUrl($report['documentUrl']);
            $filing->setHtmlUrl($report['htmlUrl'] ?? null);
            $filing->setTextUrl($report['textUrl'] ?? null);
            $filingDate = $filing->getFilingDate();
            $fiscalYear = $filingDate ? $filingDate->format('Y') : date('Y');
            if ($filingDate && $filingDate->format('m') <= 3) {
                $fiscalYear = (int)$fiscalYear - 1;
            }
            $filing->setFiscalYear((string)$fiscalYear);
            if ($downloadContent && !empty($report['textUrl'])) {
                $this->logger->info('Downloading content for 10-K', ['accession' => $report['accessionNumber']]);
                try {
                    $content = $this->secApiClient->downloadReport($report['textUrl'], 'text');
                    $filing->setContent($content);
                } catch (\Exception $e) {
                    $this->logger->error('Error downloading 10-K content', ['error' => $e->getMessage()]);
                }
            }
            $this->entityManager->persist($filing);
            $importedFilings[] = $filing;
        }
        $this->entityManager->flush();
        return $importedFilings;
    }

    /**
     * Process SEC filing document to extract sections and generate summaries
     */
    public function processSecFiling(SecFiling $filing): bool
    {
        // Ensure we have the correct client type for SEC API specific methods
        if (!($this->secApiClient instanceof KaleidoscopeApiClient ||
              $this->secApiClient instanceof MockKaleidoscopeApiClient)) {
             $this->logger->error('Cannot process filing: Invalid SEC API client type.');
             return false;
        }

        if (!$filing->getContent()) {
            if ($filing->getTextUrl()) {
                try {
                    $content = $this->secApiClient->downloadReport($filing->getTextUrl(), 'text');
                    $filing->setContent($content);
                } catch (\Exception $e) {
                    $this->logger->error('Error downloading content for processing', ['error' => $e->getMessage()]);
                    return false;
                }
            } else {
                $this->logger->error('No content or URL available for processing filing', ['id' => $filing->getId()]);
                return false;
            }
        }

        $sections = $this->secApiClient->extractReportSections($filing->getContent());
        if (empty($sections)) {
            $this->logger->warning('No sections extracted from filing', ['id' => $filing->getId()]);
            // Don't mark as processed if sections fail
            // $filing->setIsProcessed(true); // Maybe set processed even if empty? Depends on logic.
            // $this->entityManager->flush();
            return false;
        }
        $filing->setSections($sections);

        $keyFindings = [];
        $summaries = [];
        foreach ($sections as $key => $content) {
            if (empty(trim($content))) continue;
            $sectionTitle = match($key) {
                'item1' => 'Business', 'item1a' => 'Risk Factors', 'item7' => 'Management\'s Discussion and Analysis', 'item8' => 'Financial Statements and Supplementary Data', default => 'Section ' . $key,
            };
            $truncatedContent = substr($content, 0, 8000);
            try {
                try {
                    $summary = $this->neuronAiService->generateCompletion(
                        "Summarize the following section from a 10-K report for {$filing->getCompany()->getName()}: {$sectionTitle}\n\n{$truncatedContent}",
                        ['max_tokens' => 1000]
                    );

                    $findings = $this->neuronAiService->generateCompletion(
                        "Extract 3-5 key findings from the following section of a 10-K report for {$filing->getCompany()->getName()}: {$sectionTitle}\n\n{$truncatedContent}",
                        ['max_tokens' => 500]
                    );
                } catch (\Exception $e) {
                    $this->logger->error('Error generating AI content for section', [
                        'section' => $sectionTitle,
                        'error' => $e->getMessage()
                    ]);
                    $summary = "Unable to generate summary for {$sectionTitle}.";
                    $findings = "Unable to extract key findings.";
                }

                $summaries[$key] = $summary;
                $keyFindings[$key] = $findings;
            } catch (\Exception $e) {
                $this->logger->error('Error generating AI summary', ['error' => $e->getMessage()]);
            }
        }

        $overallSummary = '';
        try {
            $combinedSummaries = implode("\n\n", $summaries);
            try {
                $overallSummary = $this->neuronAiService->generateCompletion(
                    "Create a concise executive summary of the following 10-K report highlights for {$filing->getCompany()->getName()} ({$filing->getFiscalYear()}):\n\n{$combinedSummaries}",
                    ['max_tokens' => 1500]
                );
            } catch (\Exception $e) {
                $this->logger->error('Error generating overall summary', [
                    'company' => $filing->getCompany()->getName(),
                    'error' => $e->getMessage()
                ]);
                $overallSummary = "Unable to generate executive summary for this report.";
            }
        } catch (\Exception $e) {
            $this->logger->error('Error preparing summaries for executive summary', ['error' => $e->getMessage()]);
            $overallSummary = "Unable to process report for executive summary.";
        }

        $filing->setSummary($overallSummary);
        $filing->setKeyFindings($keyFindings);
        $filing->setIsProcessed(true);
        $filing->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($filing);
        $this->entityManager->flush();
        return true;
    }

    /**
     * Process all unprocessed SEC filings
     */
    public function processUnprocessedFilings(int $limit = 10): int
    {
        $unprocessedFilings = $this->secFilingRepository->findUnprocessedFilings($limit);
        if (empty($unprocessedFilings)) {
            $this->logger->info('No unprocessed SEC filings found');
            return 0;
        }
        $count = 0;
        foreach ($unprocessedFilings as $filing) {
            if ($this->processSecFiling($filing)) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get latest 10-K report for a company
     */
    public function getLatest10K(Company $company, bool $processIfNeeded = true): ?SecFiling
    {
        $filing = $this->secFilingRepository->findLatest10K($company);
        if (!$filing) {
            $filings = $this->import10KReports($company, true, 1);
            $filing = $filings[0] ?? null;
        }
        if ($filing && !$filing->getIsProcessed() && $processIfNeeded) {
            $this->processSecFiling($filing);
        }
        return $filing;
    }

    /**
     * Get recommended key sections from 10-K for a company (for research reports)
     */
    public function getKeyInsightsFrom10K(Company $company): array
    {
        $filing = $this->getLatest10K($company);
        if (!$filing || !$filing->getIsProcessed()) {
            return ['summary' => 'No processed 10-K report available', 'business' => 'Info unavailable', 'risks' => 'Info unavailable', 'mda' => 'Info unavailable', 'keyFindings' => [], 'fiscalYear' => date('Y')];
        }
        return ['summary' => $filing->getSummary(), 'business' => $this->shortenText($filing->getSection('item1') ?? '', 1000), 'risks' => $this->shortenText($filing->getSection('item1a') ?? '', 1000), 'mda' => $this->shortenText($filing->getSection('item7') ?? '', 1000), 'keyFindings' => $filing->getKeyFindings(), 'fiscalYear' => $filing->getFiscalYear()];
    }

    /**
     * Shorten text to a maximum length while preserving whole sentences
     */
    private function shortenText(string $text, int $maxLength): string
    {
        if (strlen($text) <= $maxLength) return $text;
        $shortenedText = substr($text, 0, $maxLength);
        $lastPeriod = strrpos($shortenedText, '.');
        if ($lastPeriod !== false) {
            $shortenedText = substr($shortenedText, 0, $lastPeriod + 1);
        }
        return $shortenedText . '...';
    }
}
