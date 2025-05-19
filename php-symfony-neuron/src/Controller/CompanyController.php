<?php

namespace App\Controller;

use App\Entity\Company;
use App\Form\CompanyType;
use App\Repository\CompanyRepository;
use App\Service\CompanySearchService;
use App\Service\NeuronAiService;
use App\Service\StockDataService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/company')]
class CompanyController extends AbstractController
{
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;

    public function __construct(LoggerInterface $logger, EntityManagerInterface $entityManager)
    {
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    #[Route('/', name: 'company_index', methods: ['GET'])]
    public function index(CompanyRepository $companyRepository): Response
    {
        return $this->render('company/index.html.twig', [
            'companies' => $companyRepository->findAll(),
        ]);
    }

    #[Route('/search', name: 'company_search', methods: ['GET', 'POST'])]
    public function search(Request $request, CompanySearchService $companySearchService): Response
    {
        $searchTerm = trim($request->query->get('searchTerm', $request->request->get('searchTerm')));
        $results = ['dbResults' => [], 'apiResults' => []];
        $apiError = false;

        if (!empty($searchTerm)) {
            $this->logger->info('Processing search request', ['term' => $searchTerm]);

            // Attempt to search with the provided term
            try {
                $results = $companySearchService->searchCompanies($searchTerm);

                // Add appropriate flash messages based on results
                if (empty($results['dbResults']) && empty($results['apiResults'])) {
                    // No results found
                    $this->logger->info('No results found for search term', ['term' => $searchTerm]);
                } elseif (!empty($results['dbResults']) && empty($results['apiResults'])) {
                    // Only database results found
                    $this->logger->info('Found only database results', ['count' => count($results['dbResults'])]);

                    // Check if there was an API error
                    if ($apiError) {
                        $this->addFlash('warning', 'External search services are currently unavailable. Showing only results from our database.');
                    }
                } elseif (empty($results['dbResults']) && !empty($results['apiResults'])) {
                    // Only API results found
                    $this->logger->info('Found only external API results', ['count' => count($results['apiResults'])]);
                    $this->addFlash('info', 'We found some companies from external sources that match your search. You can import them to our database.');
                } else {
                    // Both database and API results found
                    $this->logger->info('Found results from both sources', [
                        'dbCount' => count($results['dbResults']),
                        'apiCount' => count($results['apiResults'])
                    ]);
                }
            } catch (\Exception $e) {
                $this->logger->error('Error during company search', ['error' => $e->getMessage()]);
                $this->addFlash('error', 'An error occurred while searching. Please try again later.');
                $apiError = true;
            }
        }

        return $this->render('company/search.html.twig', [
            'dbResults' => $results['dbResults'],
            'apiResults' => $results['apiResults'],
            'searchTerm' => $searchTerm,
            'apiError' => $apiError
        ]);
    }

    #[Route('/import/{symbol}', name: 'company_import', methods: ['POST'])]
    public function importFromApi(string $symbol, StockDataService $stockDataService): Response
    {
        try {
            $company = $stockDataService->importCompany($symbol);
            $this->addFlash('success', 'Company successfully imported: ' . $company->getName());
            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        } catch (\Exception $e) {
            $this->logger->error("Error importing company {$symbol}: " . $e->getMessage());
            $this->addFlash('error', 'Error importing company: ' . $e->getMessage());
            return $this->redirectToRoute('company_search');
        }
    }

    #[Route('/new', name: 'company_new', methods: ['GET', 'POST'])]
    public function new(Request $request, NeuronAiService $neuronAiService): Response
    {
        $company = new Company();
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($request->request->get('use_ai') === 'yes') {
                try {
                    $companyInfo = $neuronAiService->generateCompanyInfo($company->getName());
                    if (!isset($companyInfo['error'])) {
                        $company->setIndustry($companyInfo['industry'] ?? $company->getIndustry());
                        $company->setSector($companyInfo['sector'] ?? $company->getSector());
                        $company->setHeadquarters($companyInfo['headquarters'] ?? $company->getHeadquarters());
                        $company->setDescription($companyInfo['description'] ?? $company->getDescription());
                    }
                } catch (\Exception $e) {
                    $this->logger->warning('AI enhancement failed for new company: ' . $e->getMessage());
                    $this->addFlash('warning', 'AI enhancement failed, but company was created with provided information.');
                }
            }

            $company->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($company);
            $this->entityManager->flush();
            $this->addFlash('success', 'Company created successfully.');
            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/new.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'company_show', methods: ['GET'])]
    public function show(Company $company): Response
    {
        return $this->render('company/show.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/edit', name: 'company_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Company $company): Response
    {
        $form = $this->createForm(CompanyType::class, $company);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $company->setUpdatedAt(new \DateTimeImmutable());
            $this->entityManager->flush();
            $this->addFlash('success', 'Company updated successfully.');
            return $this->redirectToRoute('company_show', ['id' => $company->getId()]);
        }

        return $this->render('company/edit.html.twig', [
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/financial', name: 'company_financial', methods: ['GET'])]
    public function financial(Company $company): Response
    {
        return $this->render('company/financial.html.twig', [
            'company' => $company,
            'financialData' => $company->getFinancialData(),
        ]);
    }

    #[Route('/{id}/sec-filings', name: 'company_sec_filings', methods: ['GET'])]
    public function secFilings(Company $company): Response
    {
        // Assuming this is a simple render. If it fetches data, move to MarketDataController
        return $this->render('company/sec_filings.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/leadership', name: 'company_leadership', methods: ['GET'])]
    public function leadership(Company $company): Response
    {
        return $this->render('company/leadership.html.twig', [
            'company' => $company,
            'executives' => $company->getExecutiveProfiles(),
        ]);
    }

    #[Route('/{id}/institutional-ownership', name: 'company_institutional_ownership', methods: ['GET'])]
    public function institutionalOwnership(Company $company): Response
    {
         // Assuming this is a simple render. If it fetches data, move to MarketDataController
        return $this->render('company/institutional_ownership.html.twig', [
            'company' => $company,
        ]);
    }

    #[Route('/{id}/competitors', name: 'company_competitors', methods: ['GET'])]
    public function competitors(Company $company): Response
    {
        return $this->render('company/competitors.html.twig', [
            'company' => $company,
            'competitorAnalyses' => $company->getCompetitorAnalyses(),
        ]);
    }

    #[Route('/{id}/reports', name: 'company_reports', methods: ['GET'])]
    public function reports(Company $company): Response
    {
        return $this->render('company/reports.html.twig', [
            'company' => $company,
            'reports' => $company->getResearchReports(),
        ]);
    }

    #[Route('/{id}/delete', name: 'company_delete', methods: ['POST'])]
    public function delete(Request $request, Company $company): Response
    {
        if ($this->isCsrfTokenValid('delete' . $company->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($company);
            $this->entityManager->flush();
            $this->addFlash('success', 'Company deleted successfully.');
        }
        return $this->redirectToRoute('company_index');
    }
}
