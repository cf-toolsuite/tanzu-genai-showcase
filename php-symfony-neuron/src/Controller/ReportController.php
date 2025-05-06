<?php

namespace App\Controller;

use App\Entity\ResearchReport;
use App\Repository\ResearchReportRepository;
use App\Service\ReportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/report')]
class ReportController extends AbstractController
{
    #[Route('/', name: 'report_index', methods: ['GET'])]
    public function index(ResearchReportRepository $reportRepository): Response
    {
        return $this->render('report/index.html.twig', [
            'reports' => $reportRepository->findAll(),
        ]);
    }

    #[Route('/recent', name: 'report_recent', methods: ['GET'])]
    public function recent(ResearchReportRepository $reportRepository): Response
    {
        return $this->render('report/recent.html.twig', [
            'reports' => $reportRepository->findRecentReports(10),
        ]);
    }

    #[Route('/{id}', name: 'report_show', methods: ['GET'])]
    public function show(Request $request, ResearchReport $report): Response
    {
        // Return JSON for AJAX requests
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'id' => $report->getId(),
                'title' => $report->getTitle(),
                'reportType' => $report->getReportType(),
                'summary' => $report->getSummary(),
                'content' => $report->getContent(),
                'publicationDate' => $report->getPublicationDate()->format('M d, Y'),
                'analyst' => $report->getAnalyst(),
                'recommendation' => $report->getRecommendation(),
                'priceTarget' => $report->getPriceTarget()
            ]);
        }

        // Return HTML for regular requests
        return $this->render('report/show.html.twig', [
            'report' => $report,
        ]);
    }

    #[Route('/{id}/export/pdf', name: 'report_export_pdf', methods: ['GET'])]
    public function exportPdf(ResearchReport $report, ReportExportService $exportService): Response
    {
        try {
            $filePath = $exportService->exportToPdf($report);

            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'report_' . $report->getCompany()->getName() . '.pdf'
            );

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to generate PDF: ' . $e->getMessage());
            return $this->redirectToRoute('report_show', ['id' => $report->getId()]);
        }
    }

    #[Route('/{id}/export/excel', name: 'report_export_excel', methods: ['GET'])]
    public function exportExcel(ResearchReport $report, ReportExportService $exportService): Response
    {
        try {
            $filePath = $exportService->exportToExcel($report);

            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'report_' . $report->getCompany()->getName() . '.xlsx'
            );

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to generate Excel file: ' . $e->getMessage());
            return $this->redirectToRoute('report_show', ['id' => $report->getId()]);
        }
    }

    #[Route('/{id}/export/word', name: 'report_export_word', methods: ['GET'])]
    public function exportWord(ResearchReport $report, ReportExportService $exportService): Response
    {
        try {
            $filePath = $exportService->exportToWord($report);

            $response = new BinaryFileResponse($filePath);
            $response->setContentDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'report_' . $report->getCompany()->getName() . '.docx'
            );

            return $response;
        } catch (\Exception $e) {
            $this->addFlash('error', 'Failed to generate Word document: ' . $e->getMessage());
            return $this->redirectToRoute('report_show', ['id' => $report->getId()]);
        }
    }

    #[Route('/{id}/delete', name: 'report_delete', methods: ['POST'])]
    public function delete(Request $request, ResearchReport $report, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$report->getId(), $request->request->get('_token'))) {
            $companyId = $report->getCompany()->getId();
            $entityManager->remove($report);
            $entityManager->flush();

            $this->addFlash('success', 'Report deleted successfully.');

            return $this->redirectToRoute('company_reports', ['id' => $companyId]);
        }

        return $this->redirectToRoute('report_show', ['id' => $report->getId()]);
    }

    #[Route('/search', name: 'report_search', methods: ['GET', 'POST'])]
    public function search(Request $request, ResearchReportRepository $reportRepository): Response
    {
        $searchTerm = $request->query->get('term');
        $results = [];

        if ($searchTerm) {
            $results = $reportRepository->findBySearchTerm($searchTerm);
        }

        return $this->render('report/search.html.twig', [
            'searchTerm' => $searchTerm,
            'results' => $results,
        ]);
    }

    #[Route('/by-industry/{industry}', name: 'report_by_industry', methods: ['GET'])]
    public function byIndustry(string $industry, ResearchReportRepository $reportRepository): Response
    {
        return $this->render('report/by_industry.html.twig', [
            'industry' => $industry,
            'reports' => $reportRepository->findByIndustry($industry),
        ]);
    }

    #[Route('/by-type/{type}', name: 'report_by_type', methods: ['GET'])]
    public function byType(string $type, ResearchReportRepository $reportRepository): Response
    {
        return $this->render('report/by_type.html.twig', [
            'type' => $type,
            'reports' => $reportRepository->findByReportType($type),
        ]);
    }
}
