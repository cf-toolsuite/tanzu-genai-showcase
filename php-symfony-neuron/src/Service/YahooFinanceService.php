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
    private bool $useMockData;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $rapidApiKey,
        bool $useMockData = false
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->apiKey = $rapidApiKey;
        $this->useMockData = $useMockData;
    }

    /**
     * Fetch ESG data for a company
     *
     * @param Company $company The company entity
     * @return ESGData|null The ESG data entity or null if not found
     */
    public function fetchESGData(Company $company): ?ESGData
    {
        $ticker = $company->getTickerSymbol();
        if (!$ticker) {
            $this->logger->warning('Cannot fetch ESG data: Company has no ticker symbol', [
                'company_id' => $company->getId(),
                'company_name' => $company->getName()
            ]);
            return null;
        }

        try {
            if ($this->useMockData) {
                return $this->getMockESGData($company);
            }

            $response = $this->httpClient->request('GET', 'https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-esg-chart', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
                ],
                'query' => [
                    'symbol' => $ticker
                ]
            ]);

            $data = $response->toArray();

            // Process the API response
            $esgData = new ESGData();
            $esgData->setCompany($company);
            $esgData->setTotalScore($data['totalScore'] ?? null);
            $esgData->setEnvironmentScore($data['environmentScore'] ?? null);
            $esgData->setSocialScore($data['socialScore'] ?? null);
            $esgData->setGovernanceScore($data['governanceScore'] ?? null);
            $esgData->setPeerComparisonTotal($data['peerPercentile'] ?? null);
            $esgData->setLastUpdated(new \DateTime($data['lastUpdated'] ?? 'now'));

            $this->entityManager->persist($esgData);
            $this->entityManager->flush();

            return $esgData;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching ESG data', [
                'company' => $company->getTickerSymbol(),
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Fetch SEC filings for a company
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of filings to fetch
     * @return array Array of SecFiling entities
     */
    public function fetchSecFilings(Company $company, int $limit = 20): array
    {
        $ticker = $company->getTickerSymbol();
        if (!$ticker) {
            $this->logger->warning('Cannot fetch SEC filings: Company has no ticker symbol', [
                'company_id' => $company->getId(),
                'company_name' => $company->getName()
            ]);
            return [];
        }

        try {
            if ($this->useMockData) {
                return $this->getMockSecFilings($company, $limit);
            }

            $response = $this->httpClient->request('GET', 'https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-sec-filings', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
                ],
                'query' => [
                    'symbol' => $ticker,
                    'limit' => $limit
                ]
            ]);

            $data = $response->toArray();
            $filings = [];

            foreach ($data['filings'] ?? [] as $filingData) {
                $filing = new SecFiling();
                $filing->setCompany($company);
                $filing->setFormType($filingData['type'] ?? '');
                $filing->setFilingDate(new \DateTime($filingData['date'] ?? 'now'));
                $filing->setDescription($filingData['description'] ?? '');
                $filing->setDocumentUrl($filingData['url'] ?? '');
                if (isset($filingData['exhibitUrl'])) {
                    $filing->setHtmlUrl($filingData['exhibitUrl']);
                }

                $this->entityManager->persist($filing);
                $filings[] = $filing;
            }

            $this->entityManager->flush();

            return $filings;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching SEC filings', [
                'company' => $company->getTickerSymbol(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fetch insider transactions for a company
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of transactions to fetch
     * @return array Array of InsiderTransaction entities
     */
    public function fetchInsiderTransactions(Company $company, int $limit = 20): array
    {
        $ticker = $company->getTickerSymbol();
        if (!$ticker) {
            $this->logger->warning('Cannot fetch insider transactions: Company has no ticker symbol', [
                'company_id' => $company->getId(),
                'company_name' => $company->getName()
            ]);
            return [];
        }

        try {
            if ($this->useMockData) {
                return $this->getMockInsiderTransactions($company, $limit);
            }

            $response = $this->httpClient->request('GET', 'https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-holders', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
                ],
                'query' => [
                    'symbol' => $ticker,
                    'limit' => $limit
                ]
            ]);

            $data = $response->toArray();
            $transactions = [];

            foreach ($data['transactions'] ?? [] as $transactionData) {
                $transaction = new InsiderTransaction();
                $transaction->setCompany($company);
                $transaction->setInsiderName($transactionData['insiderName'] ?? '');
                $transaction->setTitle($transactionData['position'] ?? '');
                $transaction->setTransactionDate(new \DateTime($transactionData['date'] ?? 'now'));
                $transaction->setTransactionType($transactionData['type'] ?? '');
                $transaction->setShares($transactionData['shares'] ?? 0);
                $transaction->setValue($transactionData['value'] ?? 0);
                $transaction->setSharesOwned($transactionData['sharesOwned'] ?? 0);

                $this->entityManager->persist($transaction);
                $transactions[] = $transaction;
            }

            $this->entityManager->flush();

            return $transactions;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching insider transactions', [
                'company' => $company->getTickerSymbol(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fetch institutional ownership data for a company
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of institutions to fetch
     * @return array Array of InstitutionalOwnership entities
     */
    public function fetchInstitutionalOwnership(Company $company, int $limit = 20): array
    {
        $ticker = $company->getTickerSymbol();
        if (!$ticker) {
            $this->logger->warning('Cannot fetch institutional ownership: Company has no ticker symbol', [
                'company_id' => $company->getId(),
                'company_name' => $company->getName()
            ]);
            return [];
        }

        try {
            if ($this->useMockData) {
                return $this->getMockInstitutionalOwnership($company, $limit);
            }

            $response = $this->httpClient->request('GET', 'https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-holders', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
                ],
                'query' => [
                    'symbol' => $ticker,
                    'limit' => $limit
                ]
            ]);

            $data = $response->toArray();
            $institutions = [];

            foreach ($data['institutions'] ?? [] as $institutionData) {
                $institution = new InstitutionalOwnership();
                $institution->setCompany($company);
                $institution->setInstitutionName($institutionData['name'] ?? '');
                $institution->setShares($institutionData['shares'] ?? 0);
                $institution->setValue($institutionData['value'] ?? 0);
                $institution->setPercentageOwned($institutionData['percentOwned'] ?? 0);
                $institution->setPreviousShares($institutionData['previousShares'] ?? null);
                $institution->setPercentageChange($institutionData['percentChange'] ?? null);
                $institution->setReportDate(new \DateTime($institutionData['reportDate'] ?? 'now'));

                $this->entityManager->persist($institution);
                $institutions[] = $institution;
            }

            $this->entityManager->flush();

            return $institutions;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching institutional ownership', [
                'company' => $company->getTickerSymbol(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Fetch analyst ratings for a company
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of ratings to fetch
     * @return array Array of AnalystRating entities
     */
    public function fetchAnalystRatings(Company $company, int $limit = 20): array
    {
        $ticker = $company->getTickerSymbol();
        if (!$ticker) {
            $this->logger->warning('Cannot fetch analyst ratings: Company has no ticker symbol', [
                'company_id' => $company->getId(),
                'company_name' => $company->getName()
            ]);
            return [];
        }

        try {
            if ($this->useMockData) {
                return $this->getMockAnalystRatings($company, $limit);
            }

            $response = $this->httpClient->request('GET', 'https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-statistics', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
                ],
                'query' => [
                    'symbol' => $ticker,
                    'limit' => $limit
                ]
            ]);

            $data = $response->toArray();
            $ratings = [];

            foreach ($data['ratings'] ?? [] as $ratingData) {
                $rating = new AnalystRating();
                $rating->setCompany($company);
                $rating->setFirmName($ratingData['firm'] ?? '');
                $rating->setAnalystName($ratingData['analyst'] ?? '');
                $rating->setRating($ratingData['rating'] ?? '');
                $rating->setPreviousRating($ratingData['previousRating'] ?? null);
                $rating->setPriceTarget($ratingData['priceTarget'] ?? null);
                $rating->setPreviousPriceTarget($ratingData['previousPriceTarget'] ?? null);
                $rating->setRatingDate(new \DateTime($ratingData['date'] ?? 'now'));
                $rating->setCommentary($ratingData['commentary'] ?? null);

                $this->entityManager->persist($rating);
                $ratings[] = $rating;
            }

            $this->entityManager->flush();

            return $ratings;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching analyst ratings', [
                'company' => $company->getTickerSymbol(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get mock ESG data for testing
     *
     * @param Company $company The company entity
     * @return ESGData The mock ESG data
     */
    private function getMockESGData(Company $company): ESGData
    {
        $esgData = new ESGData();
        $esgData->setCompany($company);
        $esgData->setTotalScore(mt_rand(50, 95) / 10);
        $esgData->setEnvironmentScore(mt_rand(40, 100) / 10);
        $esgData->setSocialScore(mt_rand(40, 100) / 10);
        $esgData->setGovernanceScore(mt_rand(40, 100) / 10);
        $esgData->setPeerComparisonTotal(mt_rand(1, 100));
        $esgData->setLastUpdated(new \DateTime('-' . mt_rand(1, 30) . ' days'));

        $this->entityManager->persist($esgData);
        $this->entityManager->flush();

        return $esgData;
    }

    /**
     * Get mock SEC filings for testing
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of filings to generate
     * @return array Array of mock SecFiling entities
     */
    private function getMockSecFilings(Company $company, int $limit): array
    {
        $filingTypes = ['10-K', '10-Q', '8-K', 'S-1', 'DEF 14A', '4', 'SC 13G'];
        $descriptions = [
            'Annual Report',
            'Quarterly Report',
            'Current Report',
            'Registration Statement',
            'Proxy Statement',
            'Statement of Changes in Beneficial Ownership',
            'Schedule 13G - Institutional Ownership'
        ];

        $filings = [];

        for ($i = 0; $i < $limit; $i++) {
            $typeIndex = array_rand($filingTypes);

            $filing = new SecFiling();
            $filing->setCompany($company);
            $filing->setFormType($filingTypes[$typeIndex]);
            $filing->setFilingDate(new \DateTime('-' . mt_rand(1, 365) . ' days'));
            $filing->setDescription($descriptions[$typeIndex]);
            $filing->setDocumentUrl('https://www.sec.gov/Archives/edgar/data/mock/' . mt_rand(10000, 99999) . '.pdf');

            if (mt_rand(0, 1)) {
                $filing->setHtmlUrl('https://www.sec.gov/Archives/edgar/data/mock/exhibits/' . mt_rand(10000, 99999) . '.pdf');
            }

            $this->entityManager->persist($filing);
            $filings[] = $filing;
        }

        $this->entityManager->flush();

        return $filings;
    }

    /**
     * Get mock insider transactions for testing
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of transactions to generate
     * @return array Array of mock InsiderTransaction entities
     */
    private function getMockInsiderTransactions(Company $company, int $limit): array
    {
        $insiderNames = [
            'John Smith',
            'Jane Doe',
            'Robert Johnson',
            'Emily Williams',
            'Michael Brown',
            'Sarah Davis',
            'David Miller',
            'Lisa Wilson'
        ];

        $positions = [
            'CEO',
            'CFO',
            'COO',
            'CTO',
            'Director',
            'VP of Sales',
            'VP of Marketing',
            'General Counsel'
        ];

        $transactionTypes = ['Purchase', 'Sale', 'Option Exercise'];
        $transactions = [];

        for ($i = 0; $i < $limit; $i++) {
            $shares = mt_rand(100, 10000);
            $price = mt_rand(1000, 50000) / 100;
            $value = $shares * $price;
            $sharesOwned = mt_rand(10000, 1000000);

            $transaction = new InsiderTransaction();
            $transaction->setCompany($company);
            $transaction->setInsiderName($insiderNames[array_rand($insiderNames)]);
            $transaction->setTitle($positions[array_rand($positions)]);
            $transaction->setTransactionDate(new \DateTime('-' . mt_rand(1, 180) . ' days'));
            $transaction->setTransactionType($transactionTypes[array_rand($transactionTypes)]);
            $transaction->setShares($shares);
            $transaction->setValue($value);
            $transaction->setSharesOwned($sharesOwned);

            $this->entityManager->persist($transaction);
            $transactions[] = $transaction;
        }

        $this->entityManager->flush();

        return $transactions;
    }

    /**
     * Get mock institutional ownership data for testing
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of institutions to generate
     * @return array Array of mock InstitutionalOwnership entities
     */
    private function getMockInstitutionalOwnership(Company $company, int $limit): array
    {
        $institutionNames = [
            'Vanguard Group',
            'BlackRock Inc.',
            'State Street Corporation',
            'Fidelity Investments',
            'T. Rowe Price',
            'JPMorgan Chase & Co.',
            'Goldman Sachs Group',
            'Morgan Stanley',
            'Wellington Management',
            'Capital Group Companies'
        ];

        $institutions = [];

        for ($i = 0; $i < $limit; $i++) {
            $shares = mt_rand(100000, 10000000);
            $value = $shares * (mt_rand(1000, 50000) / 100);
            $percentOwned = mt_rand(1, 1000) / 100;
            $previousShares = $shares - mt_rand(-500000, 500000);
            $percentChange = (($shares - $previousShares) / $previousShares) * 100;

            $institution = new InstitutionalOwnership();
            $institution->setCompany($company);
            $institution->setInstitutionName($institutionNames[$i % count($institutionNames)]);
            $institution->setShares($shares);
            $institution->setValue($value);
            $institution->setPercentageOwned($percentOwned);
            $institution->setPreviousShares($previousShares);
            $institution->setPercentageChange($percentChange);
            $institution->setReportDate(new \DateTime('-' . mt_rand(1, 90) . ' days'));

            $this->entityManager->persist($institution);
            $institutions[] = $institution;
        }

        $this->entityManager->flush();

        return $institutions;
    }

    /**
     * Fetch industry peers for a company with their ESG data for comparison
     *
     * @param Company $company The company entity
     * @return array Array of peer companies with their ESG data
     */
    public function getIndustryPeers(Company $company): array
    {
        $ticker = $company->getTickerSymbol();
        if (!$ticker) {
            $this->logger->warning('Cannot fetch industry peers: Company has no ticker symbol', [
                'company_id' => $company->getId(),
                'company_name' => $company->getName()
            ]);
            return [];
        }

        try {
            if ($this->useMockData) {
                return $this->getMockIndustryPeers($company);
            }

            $response = $this->httpClient->request('GET', 'https://yahoo-finance-real-time1.p.rapidapi.com/stock/get-recommendations', [
                'headers' => [
                    'X-RapidAPI-Key' => $this->apiKey,
                    'X-RapidAPI-Host' => 'yahoo-finance-real-time1.p.rapidapi.com'
                ],
                'query' => [
                    'symbol' => $ticker
                ]
            ]);

            $data = $response->toArray();
            $peers = [];

            foreach ($data['peers'] ?? [] as $peerData) {
                $peers[] = [
                    'symbol' => $peerData['symbol'] ?? '',
                    'name' => $peerData['name'] ?? '',
                    'esgScore' => $peerData['esgScore'] ?? null,
                    'environmentScore' => $peerData['environmentScore'] ?? null,
                    'socialScore' => $peerData['socialScore'] ?? null,
                    'governanceScore' => $peerData['governanceScore'] ?? null
                ];
            }

            return $peers;
        } catch (\Exception $e) {
            $this->logger->error('Error fetching industry peers', [
                'company' => $company->getTickerSymbol(),
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get mock industry peers for testing
     *
     * @param Company $company The company entity
     * @return array Array of mock peer companies with ESG data
     */
    private function getMockIndustryPeers(Company $company): array
    {
        $industry = $company->getIndustry() ?? 'Technology';

        $peerCompanies = [
            'Technology' => [
                ['symbol' => 'AAPL', 'name' => 'Apple Inc.'],
                ['symbol' => 'MSFT', 'name' => 'Microsoft Corporation'],
                ['symbol' => 'GOOGL', 'name' => 'Alphabet Inc.'],
                ['symbol' => 'AMZN', 'name' => 'Amazon.com, Inc.'],
                ['symbol' => 'META', 'name' => 'Meta Platforms, Inc.']
            ],
            'Financial Services' => [
                ['symbol' => 'JPM', 'name' => 'JPMorgan Chase & Co.'],
                ['symbol' => 'BAC', 'name' => 'Bank of America Corporation'],
                ['symbol' => 'WFC', 'name' => 'Wells Fargo & Company'],
                ['symbol' => 'C', 'name' => 'Citigroup Inc.'],
                ['symbol' => 'GS', 'name' => 'The Goldman Sachs Group, Inc.']
            ],
            'Healthcare' => [
                ['symbol' => 'JNJ', 'name' => 'Johnson & Johnson'],
                ['symbol' => 'PFE', 'name' => 'Pfizer Inc.'],
                ['symbol' => 'UNH', 'name' => 'UnitedHealth Group Incorporated'],
                ['symbol' => 'MRK', 'name' => 'Merck & Co., Inc.'],
                ['symbol' => 'ABBV', 'name' => 'AbbVie Inc.']
            ],
            'Energy' => [
                ['symbol' => 'XOM', 'name' => 'Exxon Mobil Corporation'],
                ['symbol' => 'CVX', 'name' => 'Chevron Corporation'],
                ['symbol' => 'COP', 'name' => 'ConocoPhillips'],
                ['symbol' => 'SLB', 'name' => 'Schlumberger Limited'],
                ['symbol' => 'EOG', 'name' => 'EOG Resources, Inc.']
            ],
            'Consumer Cyclical' => [
                ['symbol' => 'HD', 'name' => 'The Home Depot, Inc.'],
                ['symbol' => 'NKE', 'name' => 'NIKE, Inc.'],
                ['symbol' => 'MCD', 'name' => 'McDonald\'s Corporation'],
                ['symbol' => 'SBUX', 'name' => 'Starbucks Corporation'],
                ['symbol' => 'TGT', 'name' => 'Target Corporation']
            ]
        ];

        $peers = $peerCompanies[$industry] ?? $peerCompanies['Technology'];

        // Add mock ESG scores
        foreach ($peers as &$peer) {
            $peer['esgScore'] = mt_rand(50, 95) / 10;
            $peer['environmentScore'] = mt_rand(40, 100) / 10;
            $peer['socialScore'] = mt_rand(40, 100) / 10;
            $peer['governanceScore'] = mt_rand(40, 100) / 10;
        }

        return $peers;
    }

    /**
     * Get mock analyst ratings for testing
     *
     * @param Company $company The company entity
     * @param int $limit Maximum number of ratings to generate
     * @return array Array of mock AnalystRating entities
     */
    private function getMockAnalystRatings(Company $company, int $limit): array
    {
        $firmNames = [
            'Morgan Stanley',
            'Goldman Sachs',
            'JP Morgan',
            'Bank of America',
            'Wells Fargo',
            'Citigroup',
            'UBS',
            'Deutsche Bank',
            'Credit Suisse',
            'Barclays'
        ];

        $analystNames = [
            'John Smith',
            'Jane Doe',
            'Robert Johnson',
            'Emily Williams',
            'Michael Brown',
            'Sarah Davis',
            'David Miller',
            'Lisa Wilson'
        ];

        $ratingTypes = ['Buy', 'Outperform', 'Hold', 'Underperform', 'Sell'];
        $ratings = [];

        for ($i = 0; $i < $limit; $i++) {
            $ratingIndex = array_rand($ratingTypes);
            $previousRatingIndex = array_rand($ratingTypes);
            $priceTarget = mt_rand(5000, 50000) / 100;
            $previousPriceTarget = mt_rand(5000, 50000) / 100;

            $rating = new AnalystRating();
            $rating->setCompany($company);
            $rating->setFirmName($firmNames[$i % count($firmNames)]);
            $rating->setAnalystName($analystNames[array_rand($analystNames)]);
            $rating->setRating($ratingTypes[$ratingIndex]);

            if (mt_rand(0, 1)) {
                $rating->setPreviousRating($ratingTypes[$previousRatingIndex]);
            }

            $rating->setPriceTarget($priceTarget);

            if (mt_rand(0, 1)) {
                $rating->setPreviousPriceTarget($previousPriceTarget);
            }

            $rating->setRatingDate(new \DateTime('-' . mt_rand(1, 90) . ' days'));

            if (mt_rand(0, 1)) {
                $rating->setCommentary('We ' . strtolower($ratingTypes[$ratingIndex]) . ' this stock based on ' .
                    (mt_rand(0, 1) ? 'strong fundamentals' : 'improving market conditions') . ' and ' .
                    (mt_rand(0, 1) ? 'positive growth outlook' : 'attractive valuation') . '.');
            }

            $this->entityManager->persist($rating);
            $ratings[] = $rating;
        }

        $this->entityManager->flush();

        return $ratings;
    }
}
