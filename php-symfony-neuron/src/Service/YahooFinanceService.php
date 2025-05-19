<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\ESGData;
use App\Entity\SecFiling;
use App\Entity\InsiderTransaction;
use App\Entity\InstitutionalOwnership;
use App\Entity\AnalystRating;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class YahooFinanceService
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private string $apiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $rapidApiKey,
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->apiKey = $rapidApiKey;
    }












}
