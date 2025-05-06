<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\SecFiling;
use App\Repository\SecFilingRepository;
use App\Service\SecFilingService;
use App\Service\NeuronAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/secfiling')]
class SecFilingController extends AbstractController
{
    /**
     * List all SEC filings for a company
     */
    #[Route('/company/{id}', name: 'secfiling_company_index', methods: ['GET'])]
    public function index(
        Company $company,
        Request $request,
        SecFilingRepository $secFilingRepository
    ): Response {
        $formType = $request->query->get('formType');
        $limit = $request->query->getInt('limit', 20);

        if ($formType) {
            $filings = $secFilingRepository->findRecentFilingsByCompany($company, $formType, $limit);
        } else {
            $filings = $secFilingRepository->findRecentFilingsByCompany($company, null, $limit);
        }

        // Get counts by form type
        $counts = [];
        $allFilings = $secFilingRepository->findRecentFilingsByCompany($company, null, 100);
        foreach ($allFilings as $filing) {
            $type = $filing->getFormType();
            if (!isset($counts[$type])) {
                $counts[$type] = 0;
            }
            $counts[$type]++;
        }

        return $this->render('secfiling/index.html.twig', [
            'company' => $company,
            'filings' => $filings,
            'counts' => $counts,
            'selectedFormType' => $formType,
            'limit' => $limit,
        ]);
    }

    /**
     * Import 10-K reports for a company
     */
    #[Route('/company/{id}/import', name: 'secfiling_company_import', methods: ['POST'])]
    public function import(
        Company $company,
        SecFilingService $secFilingService
    ): Response {
        $filings = $secFilingService->import10KReports($company, true, 5);

        if (empty($filings)) {
            $this->addFlash('warning', 'No new 10-K reports were found for ' . $company->getName());
        } else {
            $this->addFlash('success', count($filings) . ' 10-K reports were imported for ' . $company->getName());
        }

        return $this->redirectToRoute('secfiling_company_index', ['id' => $company->getId()]);
    }

    /**
     * Show details of a specific SEC filing
     */
    #[Route('/{id}', name: 'secfiling_show', methods: ['GET'])]
    public function show(
        SecFiling $secFiling,
        SecFilingService $secFilingService
    ): Response {
        // Process the filing if it's not already processed
        if (!$secFiling->getIsProcessed() && $secFiling->getContent()) {
            $secFilingService->processSecFiling($secFiling);
        }

        return $this->render('secfiling/show.html.twig', [
            'filing' => $secFiling,
            'company' => $secFiling->getCompany(),
        ]);
    }

    /**
     * Process an SEC filing to extract sections and generate summaries
     */
    #[Route('/{id}/process', name: 'secfiling_process', methods: ['POST'])]
    public function process(
        SecFiling $secFiling,
        SecFilingService $secFilingService
    ): Response {
        $success = $secFilingService->processSecFiling($secFiling);

        if ($success) {
            $this->addFlash('success', 'Filing processed successfully');
        } else {
            $this->addFlash('error', 'Failed to process filing');
        }

        return $this->redirectToRoute('secfiling_show', ['id' => $secFiling->getId()]);
    }

    /**
     * Download the raw content of an SEC filing
     */
    #[Route('/{id}/download', name: 'secfiling_download', methods: ['GET'])]
    public function download(
        SecFiling $secFiling,
        SecFilingService $secFilingService
    ): Response {
        // If we don't have the content yet, download it
        if (!$secFiling->getContent() && $secFiling->getTextUrl()) {
            try {
                // Use the Kaleidoscope API client through the SecFilingService to download content
                $content = $secFilingService->processSecFiling($secFiling);

                if (!$content) {
                    $this->addFlash('error', 'Failed to download filing content');
                    return $this->redirectToRoute('secfiling_show', ['id' => $secFiling->getId()]);
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to download filing content: ' . $e->getMessage());
                return $this->redirectToRoute('secfiling_show', ['id' => $secFiling->getId()]);
            }
        }

        // Return the content as a download
        $response = new Response($secFiling->getContent());
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="' .
            $secFiling->getFormType() . '-' .
            $secFiling->getCompany()->getTickerSymbol() . '-' .
            $secFiling->getFilingDate()->format('Y-m-d') . '.txt"');

        return $response;
    }

    /**
     * Generate a summary of a section using Neuron AI
     */
    #[Route('/{id}/summarize-section', name: 'secfiling_summarize_section', methods: ['POST'])]
    public function summarizeSection(
        Request $request,
        SecFiling $secFiling,
        NeuronAiService $neuronAiService,
        EntityManagerInterface $entityManager
    ): Response {
        $sectionKey = $request->request->get('section');
        $section = $secFiling->getSection($sectionKey);

        if (!$section) {
            $this->addFlash('error', 'Section not found');
            return $this->redirectToRoute('secfiling_show', ['id' => $secFiling->getId()]);
        }

        // Trim content to manageable size for AI processing
        $truncatedContent = substr($section, 0, 8000);

        $sectionTitle = match($sectionKey) {
            'item1' => 'Business',
            'item1a' => 'Risk Factors',
            'item7' => 'Management\'s Discussion and Analysis',
            'item8' => 'Financial Statements and Supplementary Data',
            default => 'Section ' . $sectionKey,
        };

        try {
            // Generate summary
            $summary = $neuronAiService->generateCompletion(
                "Summarize the following section from a 10-K report for {$secFiling->getCompany()->getName()}: {$sectionTitle}\n\n{$truncatedContent}",
                ['max_tokens' => 1000]
            );

            // Update key findings
            $keyFindings = $secFiling->getKeyFindings() ?? [];
            $keyFindings[$sectionKey] = $neuronAiService->generateCompletion(
                "Extract 3-5 key findings from the following section of a 10-K report for {$secFiling->getCompany()->getName()}: {$sectionTitle}\n\n{$truncatedContent}",
                ['max_tokens' => 500]
            );

            $secFiling->setKeyFindings($keyFindings);
            $entityManager->flush();

            return $this->json([
                'success' => true,
                'summary' => $summary,
                'keyFindings' => $keyFindings[$sectionKey],
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Visualize data from an SEC filing
     */
    #[Route('/{id}/visualize', name: 'secfiling_visualize', methods: ['GET'])]
    public function visualize(SecFiling $secFiling): Response
    {
        $section = $secFiling->getSection('item8') ?? '';

        // Extract financial numbers for visualization
        // This is a simplified approach - in a real world application,
        // you would use a more sophisticated parser
        $data = [
            'revenue' => [],
            'netIncome' => [],
            'assets' => [],
            'liabilities' => [],
        ];

        // For the demo, we'll just populate with random data
        $years = [];
        $startYear = (int)$secFiling->getFiscalYear() - 4;

        for ($i = 0; $i < 5; $i++) {
            $year = $startYear + $i;
            $years[] = $year;

            $baseRevenue = mt_rand(10000, 50000);
            $growthFactor = 1 + (mt_rand(-10, 30) / 100); // -10% to +30% growth

            if ($i > 0) {
                $baseRevenue = $data['revenue'][$i - 1] * $growthFactor;
            }

            $data['revenue'][] = round($baseRevenue);
            $data['netIncome'][] = round($baseRevenue * (mt_rand(5, 25) / 100)); // 5-25% profit margin
            $data['assets'][] = round($baseRevenue * (mt_rand(150, 300) / 100)); // 1.5-3x revenue in assets
            $data['liabilities'][] = round($baseRevenue * (mt_rand(50, 150) / 100)); // 0.5-1.5x revenue in liabilities
        }

        return $this->render('secfiling/visualize.html.twig', [
            'filing' => $secFiling,
            'company' => $secFiling->getCompany(),
            'years' => $years,
            'data' => $data,
        ]);
    }
}
