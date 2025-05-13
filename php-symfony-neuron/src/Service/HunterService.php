<?php

namespace App\Service;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Service\ApiClient\HunterApiClientInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class HunterService
{
    private HunterApiClientInterface $apiClient;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private ParameterBagInterface $params;

    /**
     * Constructor
     */
    public function __construct(
        HunterApiClientInterface $apiClient,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->apiClient = $apiClient;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->params = $params;
    }

    /**
     * Find executives by domain name
     *
     * @param string $domain Domain name (e.g., broadcom.com)
     * @param array $options Additional search options
     * @return array Executives data
     */
    public function findExecutivesByDomain(string $domain, array $options = []): array
    {
        $defaultOptions = [
            'seniority' => 'executive',
            'department' => 'executive',
            'required_field' => 'position',
            'type' => 'personal',
            'limit' => 50
        ];

        $options = array_merge($defaultOptions, $options);

        try {
            $response = $this->apiClient->domainSearch($domain, $options);

            if (isset($response['error'])) {
                $this->logger->error('Error finding executives by domain', [
                    'domain' => $domain,
                    'error' => $response['error']
                ]);
                return ['success' => false, 'message' => $response['error']];
            }

            return [
                'success' => true,
                'domain' => $domain,
                'organization' => $response['data']['organization'] ?? null,
                'executives' => $response['data']['emails'] ?? [],
                'total' => $response['meta']['results'] ?? 0
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception finding executives by domain', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Find executives by company name
     *
     * @param string $companyName Company name (e.g., Broadcom)
     * @param array $options Additional search options
     * @return array Executives data
     */
    public function findExecutivesByCompany(string $companyName, array $options = []): array
    {
        try {
            $response = $this->apiClient->companySearch($companyName, $options);

            if (isset($response['error'])) {
                $this->logger->error('Error finding executives by company name', [
                    'company' => $companyName,
                    'error' => $response['error']
                ]);
                return ['success' => false, 'message' => $response['error']];
            }

            return [
                'success' => true,
                'domain' => $response['data']['domain'] ?? null,
                'organization' => $response['data']['organization'] ?? null,
                'executives' => $response['data']['emails'] ?? [],
                'total' => $response['meta']['results'] ?? 0
            ];
        } catch (\Exception $e) {
            $this->logger->error('Exception finding executives by company name', [
                'company' => $companyName,
                'error' => $e->getMessage()
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Search for executives by role (matches position field)
     *
     * @param string $companyName Company name
     * @param string $role Role to search for (e.g., "CEO", "CFO")
     * @return array|null Executive data if found, null otherwise
     */
    public function searchExecutiveByRole(string $companyName, string $role): ?array
    {
        $this->logger->info('Searching for executive by role', [
            'company' => $companyName,
            'role' => $role
        ]);

        // Try to find executives with company name first
        $result = $this->findExecutivesByCompany($companyName, [
            'seniority' => 'executive',
            'department' => 'executive',
            'limit' => 50
        ]);

        if (!$result['success'] || empty($result['executives'])) {
            $this->logger->warning('No executives found for company', ['company' => $companyName]);
            return null;
        }

        // Search for the requested role in the position field
        $roleLower = strtolower($role);
        foreach ($result['executives'] as $executive) {
            $position = strtolower($executive['position'] ?? '');
            $positionRaw = strtolower($executive['position_raw'] ?? '');

            if (strpos($position, $roleLower) !== false || strpos($positionRaw, $roleLower) !== false) {
                $this->logger->info('Found executive matching role', [
                    'role' => $role,
                    'executive' => $executive['first_name'] . ' ' . $executive['last_name'],
                    'position' => $executive['position']
                ]);

                // Format the data to match expected output
                return [
                    'name' => $executive['first_name'] . ' ' . $executive['last_name'],
                    'title' => $executive['position_raw'] ?? $executive['position'],
                    'email' => $executive['value'] ?? null,
                    'linkedinProfileUrl' => $executive['linkedin'] ?? null,
                    'rawData' => $executive,
                    'completeness' => [
                        'biography' => false,
                        'education' => false,
                        'previousCompanies' => false
                    ]
                ];
            }
        }

        // If no exact role match, try to find the most senior person
        $priorityTitles = ['CEO', 'Chief Executive Officer', 'President', 'Founder', 'Chairman'];

        foreach ($priorityTitles as $priorityTitle) {
            foreach ($result['executives'] as $executive) {
                $position = $executive['position'] ?? '';
                $positionRaw = $executive['position_raw'] ?? '';

                if (stripos($position, $priorityTitle) !== false || stripos($positionRaw, $priorityTitle) !== false) {
                    $this->logger->info('Using senior executive instead of specific role', [
                        'requested_role' => $role,
                        'found_title' => $position,
                        'executive' => $executive['first_name'] . ' ' . $executive['last_name']
                    ]);

                    return [
                        'name' => $executive['first_name'] . ' ' . $executive['last_name'],
                        'title' => $executive['position_raw'] ?? $executive['position'],
                        'email' => $executive['value'] ?? null,
                        'linkedinProfileUrl' => $executive['linkedin'] ?? null,
                        'rawData' => $executive,
                        'completeness' => [
                            'biography' => false,
                            'education' => false,
                            'previousCompanies' => false,
                            'achievements' => false
                        ]
                    ];
                }
            }
        }

        // If no matches, return the first executive
        if (!empty($result['executives'][0])) {
            $executive = $result['executives'][0];
            $this->logger->info('No role match found, using first executive', [
                'requested_role' => $role,
                'executive' => $executive['first_name'] . ' ' . $executive['last_name'],
                'position' => $executive['position'] ?? 'Unknown'
            ]);

            return [
                'name' => $executive['first_name'] . ' ' . $executive['last_name'],
                'title' => $executive['position_raw'] ?? $executive['position'] ?? 'Executive',
                'email' => $executive['value'] ?? null,
                'linkedinProfileUrl' => $executive['linkedin'] ?? null,
                'rawData' => $executive,
                'completeness' => [
                    'biography' => false,
                    'education' => false,
                    'previousCompanies' => false,
                    'achievements' => false
                ]
            ];
        }

        $this->logger->warning('No executives found for role', [
            'company' => $companyName,
            'role' => $role
        ]);

        return null;
    }

    /**
     * Update an executive profile with Hunter data
     */
    public function updateExecutiveWithHunterData(ExecutiveProfile $profile, array $hunterData): bool
    {
        try {
            $profile->setName($hunterData['name'] ?? $profile->getName());
            $profile->setTitle($hunterData['title'] ?? $profile->getTitle());
            $profile->setLinkedinProfileUrl($hunterData['linkedinProfileUrl'] ?? $profile->getLinkedinProfileUrl());
            $profile->setEmail($hunterData['email'] ?? $profile->getEmail());

            $profile->setLastSynced(new \DateTimeImmutable());

            $this->entityManager->persist($profile);
            $this->entityManager->flush();

            $this->logger->info('Executive profile updated with Hunter data', [
                'executiveId' => $profile->getId(),
                'name' => $profile->getName()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating executive with Hunter data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Find company connections for a specific company (placeholder returning empty data)
     * This replaces the LinkedIn equivalent function but doesn't provide actual connection data
     */
    public function findCompanyConnections(Company $company): array
    {
        return [
            'totalConnections' => 0,
            'companyConnections' => 0,
            'executivesWithLinkedIn' => 0,
            'industry' => [
                'finance' => 0,
                'technology' => 0,
                'healthcare' => 0,
                'manufacturing' => 0,
                'retail' => 0,
                'other' => 0
            ]
        ];
    }

    /**
     * Find company executives and create profiles for them
     *
     * @param Company $company The company to find executives for
     * @return int Number of executives generated
     */
    public function findCompanyExecutives(Company $company): int
    {
        try {
            // Get company name and prepare required data
            $companyName = $company->getName();
            
            // Use the existing method to find executives by company name
            $executivesData = $this->findExecutivesByCompany($companyName);
            
            if (!$executivesData['success'] || empty($executivesData['executives'])) {
                return 0; // No executives found
            }
            
            // Process executives data and create profiles
            $count = 0;
            foreach ($executivesData['executives'] as $executiveData) {
                if (empty($executiveData['first_name']) || empty($executiveData['last_name'])) {
                    continue; // Skip if no name available
                }
                
                // Check if executive already exists
                $existingProfile = $this->entityManager->getRepository(ExecutiveProfile::class)
                    ->findOneBy([
                        'company' => $company,
                        'name' => $executiveData['first_name'] . ' ' . $executiveData['last_name']
                    ]);
                    
                if ($existingProfile) {
                    // Update existing profile
                    $this->updateExecutiveWithHunterData($existingProfile, [
                        'name' => $executiveData['first_name'] . ' ' . $executiveData['last_name'],
                        'title' => $executiveData['position'] ?? 'Executive',
                        'email' => $executiveData['value'] ?? null,
                        'linkedinProfileUrl' => $executiveData['linkedin'] ?? null,
                    ]);
                } else {
                    // Create new profile
                    $profile = new ExecutiveProfile();
                    $profile->setCompany($company);
                    $profile->setName($executiveData['first_name'] . ' ' . $executiveData['last_name']);
                    $profile->setTitle($executiveData['position'] ?? 'Executive');
                    $profile->setEmail($executiveData['value'] ?? null);
                    $profile->setLinkedinProfileUrl($executiveData['linkedin'] ?? null);
                    $profile->setCreatedAt(new \DateTimeImmutable());
                    $profile->setUpdatedAt(new \DateTimeImmutable());
                    
                    $this->entityManager->persist($profile);
                    $count++;
                }
            }
            
            $this->entityManager->flush();
            return $count;
            
        } catch (\Exception $e) {
            $this->logger->error('Error finding company executives', [
                'company' => $company->getName(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
