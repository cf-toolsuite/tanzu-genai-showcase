<?php

namespace App\Controller;

use App\Entity\Company;
use App\Service\HunterService;
use App\Service\NeuronAiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/company/{id}/generate')] // Base route for generation actions
class CompanyGenerationController extends AbstractController
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager; // For operations that might persist

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    #[Route('/leadership', name: 'company_generate_leadership', methods: ['POST'])]
    public function generateLeadership(Company $company, Request $request, HunterService $hunterService): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            // Or return a 400 Bad Request if it must be AJAX
            return new JsonResponse(['success' => false, 'message' => 'AJAX request expected.'], 400);
        }

        try {
            $executivesGenerated = $hunterService->findCompanyExecutives($company);
            // Assuming HunterService persists the data and returns a count
            return $this->json(['success' => true, 'message' => 'Successfully generated ' . $executivesGenerated . ' executive profiles.', 'count' => $executivesGenerated]);
        } catch (\Exception $e) {
            $this->logger->error('Error generating leadership for ' . $company->getName() . ': ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error generating executive profiles: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/competitors', name: 'company_generate_competitors', methods: ['POST'])]
    public function generateCompetitors(Company $company, Request $request, NeuronAiService $neuronAiService): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => 'AJAX request expected.'], 400);
        }

        try {
            // Assuming NeuronAiService persists or updates the company entity
            $neuronAiService->generateCompetitorAnalysis($company);
            return $this->json(['success' => true, 'message' => 'Successfully generated competitor analysis.']);
        } catch (\Exception $e) {
            $this->logger->error('Error generating competitors for ' . $company->getName() . ': ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error generating competitor analysis: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/reports', name: 'company_generate_reports', methods: ['POST'])]
    public function generateReports(Company $company, Request $request, NeuronAiService $neuronAiService): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => 'AJAX request expected.'], 400);
        }

        try {
            // Assuming NeuronAiService persists the reports
            $reportsGenerated = $neuronAiService->generateResearchReports($company);
            return $this->json(['success' => true, 'message' => 'Successfully generated ' . $reportsGenerated . ' research reports.', 'count' => $reportsGenerated]);
        } catch (\Exception $e) {
            $this->logger->error('Error generating reports for ' . $company->getName() . ': ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error generating research reports: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/financial', name: 'company_generate_financial', methods: ['POST'])]
    public function generateFinancial(Company $company, Request $request, NeuronAiService $neuronAiService): JsonResponse
    {
        if (!$request->isXmlHttpRequest()) {
            return new JsonResponse(['success' => false, 'message' => 'AJAX request expected.'], 400);
        }

        // The reflection hack for EntityManager should ideally be handled within NeuronAiService
        // or by passing EntityManager to the service method if absolutely necessary,
        // or NeuronAiService should return data to be persisted by the controller/another service.
        // For now, let's assume NeuronAiService handles its own persistence or this controller does.

        try {
            // If NeuronAiService needs EM, it should be injected there or the method should accept it.
            // The dynamic addition of getEntityManager is not a good practice.
            // Let's assume NeuronAiService's generateFinancialData method now accepts EM or handles persistence.
            // Example: $dataGenerated = $neuronAiService->generateFinancialData($company, $this->entityManager);

            $dataGenerated = $neuronAiService->generateFinancialData($company); // Assuming service handles persistence

            if ($dataGenerated > 0) {
                // This logic might belong in the service or a post-generation listener
                foreach ($company->getFinancialData() as $financialData) {
                    if ($financialData->getReportType() === null) {
                        $financialData->setReportType('Quarterly'); // Default
                    }
                }
                $this->entityManager->flush(); // Flush if changes made here
            }

            return $this->json(['success' => true, 'message' => 'Successfully generated financial data.', 'count' => $dataGenerated]);
        } catch (\Exception $e) {
            $this->logger->error('Error generating financial data for ' . $company->getName() . ': ' . $e->getMessage());
            return $this->json(['success' => false, 'message' => 'Error generating financial data: ' . $e->getMessage()], 500);
        }
    }
}
