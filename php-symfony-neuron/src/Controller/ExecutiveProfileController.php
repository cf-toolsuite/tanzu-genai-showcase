<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Form\ExecutiveProfileType;
use App\Repository\ExecutiveProfileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

#[Route('/executive')]
class ExecutiveProfileController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    #[Route('/{id}/new', name: 'executive_profile_new', methods: ['GET', 'POST'])]
    public function new(Request $request, Company $company): Response
    {
        $executive = new ExecutiveProfile();
        $executive->setCompany($company);

        $form = $this->createForm(ExecutiveProfileType::class, $executive);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set timestamps

            $executive->setCreatedAt(new \DateTimeImmutable());
            $executive->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($executive);
            $this->entityManager->flush();

            $this->addFlash('success', 'Executive profile created successfully.');

            return $this->redirectToRoute('company_leadership', ['id' => $company->getId()]);
        }

        return $this->render('executive_profile/new.html.twig', [
            'executive' => $executive,
            'company' => $company,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'executive_profile_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ExecutiveProfile $executive): Response
    {
        $form = $this->createForm(ExecutiveProfileType::class, $executive);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Set timestamp

            $executive->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            $this->addFlash('success', 'Executive profile updated successfully.');

            return $this->redirectToRoute('company_leadership', ['id' => $executive->getCompany()->getId()]);
        }

        return $this->render('executive_profile/edit.html.twig', [
            'executive' => $executive,
            'company' => $executive->getCompany(),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/show', name: 'executive_profile_show', methods: ['GET'])]
    public function show(ExecutiveProfile $executive): Response
    {
        return $this->render('executive_profile/show.html.twig', [
            'executive' => $executive,
            'company' => $executive->getCompany(),
        ]);
    }

    #[Route('/{id}/delete', name: 'executive_profile_delete', methods: ['POST'])]
    public function delete(Request $request, ExecutiveProfile $executive): Response
    {
        $companyId = $executive->getCompany()->getId();

        if ($this->isCsrfTokenValid('delete'.$executive->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($executive);
            $this->entityManager->flush();

            $this->addFlash('success', 'Executive profile deleted successfully.');
        }

        return $this->redirectToRoute('company_leadership', ['id' => $companyId]);
    }
}
