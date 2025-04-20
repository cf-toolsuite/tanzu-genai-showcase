<?php

// src/Service/LinkedInService.php
namespace App\Service;

use App\Entity\Company;
use App\Entity\ExecutiveProfile;
use App\Service\ApiClient\LinkedInClientFactory; // Import the factory
use App\Service\ApiClient\LinkedInApiClient; // Keep concrete class hint
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LinkedInService
{
    private LinkedInApiClient $linkedInApiClient; // Keep concrete class hint
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private SessionInterface $session;

    /**
     * Constructor
     */
    public function __construct(
        LinkedInClientFactory $linkedInClientFactory, // Inject the factory
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        RequestStack $requestStack
    ) {
        // Get client from factory
        $this->linkedInApiClient = $linkedInClientFactory->createClient();

        // Assign other dependencies
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->session = $requestStack->getSession();
    }

    // --- Existing Methods (Unchanged content, just ensure they use the correct property) ---

    /**
     * Get the LinkedIn authorization URL
     */
    public function getAuthorizationUrl(): string
    {
        if ($executiveId = $this->session->get('current_executive_id')) {
            $this->session->set('linkedin_auth_executive_id', $executiveId);
        }
        return $this->linkedInApiClient->getAuthorizationUrl();
    }

    /**
     * Handle the OAuth callback and token exchange
     */
    public function handleCallback(string $code, string $state): array
    {
        try {
            $tokenData = $this->linkedInApiClient->getAccessToken($code, $state);
            // Session storage is handled within getAccessToken now
            return ['success' => true, 'token' => $tokenData];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn callback error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Check if we have a valid LinkedIn access token (delegates to client/session)
     */
    public function hasValidToken(): bool
    {
        // Check session directly or add a method to the client if preferred
        $token = $this->session->get('linkedin_access_token');
        $expiresAt = $this->session->get('linkedin_expires_at');
        return $token && $expiresAt && $expiresAt > time();
    }

    /**
     * Get the current LinkedIn access token (delegates to client/session)
     */
    public function getAccessToken(): ?string
    {
        if (!$this->hasValidToken()) return null;
        return $this->session->get('linkedin_access_token');
    }

    /**
     * Get the current user's LinkedIn profile
     */
    public function getCurrentUserProfile(): array
    {
        if (!$this->hasValidToken()) {
            return ['success' => false, 'error' => 'No valid access token'];
        }
        try {
            $profileData = $this->linkedInApiClient->getProfile(); // Uses token internally
            return ['success' => true, 'profile' => $profileData];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn profile fetch error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get the work experience of the current user
     */
    public function getCurrentUserWorkExperience(): array
    {
        if (!$this->hasValidToken()) {
            return ['success' => false, 'error' => 'No valid access token'];
        }
        try {
            $experiences = $this->linkedInApiClient->getWorkExperience(); // Uses token internally
            return ['success' => true, 'experiences' => $experiences];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn experience fetch error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get connections of the current user
     */
    public function getCurrentUserConnections(): array
    {
         if (!$this->hasValidToken()) {
            return ['success' => false, 'error' => 'No valid access token'];
        }
        try {
            $connections = $this->linkedInApiClient->getConnections(); // Uses token internally
            return ['success' => true, 'connections' => $connections];
        } catch (\Exception $e) {
            $this->logger->error('LinkedIn connections fetch error: ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Update an executive profile with LinkedIn data
     */
    public function updateExecutiveWithLinkedInData(ExecutiveProfile $profile, array $linkedinData): bool
    {
        try {
            if (!$this->hasValidToken()) return false;

            $profile->setLinkedinId($linkedinData['linkedinId'] ?? null);
            $profile->setLinkedinProfileUrl($linkedinData['profileUrl'] ?? null);
            $profile->setProfilePictureUrl($linkedinData['pictureUrl'] ?? null);
            $profile->setLinkedinData($linkedinData['rawData'] ?? null);

            if (empty($profile->getName()) && isset($linkedinData['firstName']) && isset($linkedinData['lastName'])) {
                $profile->setName($linkedinData['firstName'] . ' ' . $linkedinData['lastName']);
            }

            $experiences = $this->linkedInApiClient->getWorkExperience(); // Uses token internally
            $currentJob = null;
            foreach ($experiences as $experience) {
                if ($experience['current']) { $currentJob = $experience; break; }
            }
            if (empty($profile->getTitle()) && $currentJob && !empty($currentJob['title'])) {
                $profile->setTitle($currentJob['title']);
            }
            if (empty($profile->getPreviousCompanies())) {
                $previousCompanies = [];
                foreach ($experiences as $experience) {
                    if (!$experience['current'] && !empty($experience['companyName'])) {
                        $previousCompanies[] = $experience['companyName'];
                    }
                }
                if (!empty($previousCompanies)) {
                    $profile->setPreviousCompanies(implode(', ', array_unique($previousCompanies)));
                }
            }

            $connectionsData = $this->linkedInApiClient->getConnections(); // Uses token internally
            $profile->setConnectionCount($connectionsData['count'] ?? 0);
            $profile->setLastSynced(new \DateTimeImmutable());

            $this->entityManager->persist($profile);
            $this->entityManager->flush();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Error updating executive with LinkedIn data', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Update an executive profile from the session-stored LinkedIn access token
     */
    public function syncExecutiveProfile(ExecutiveProfile $profile): array
    {
        try {
            $profileResult = $this->getCurrentUserProfile();
            if (!$profileResult['success']) {
                return ['success' => false, 'message' => 'Failed to fetch LinkedIn profile: ' . ($profileResult['error'] ?? 'Unknown error')];
            }
            $updated = $this->updateExecutiveWithLinkedInData($profile, $profileResult['profile']);
            if (!$updated) {
                return ['success' => false, 'message' => 'Failed to update executive profile with LinkedIn data'];
            }
            return ['success' => true, 'message' => 'Successfully synced profile with LinkedIn', 'profile' => $profile];
        } catch (\Exception $e) {
            $this->logger->error('Error syncing executive profile', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Error syncing profile: ' . $e->getMessage()];
        }
    }

    /**
     * Find company connections for a specific company
     */
    public function findCompanyConnections(Company $company): array
    {
        $executives = $company->getExecutiveProfiles();
        $connectionStats = ['totalConnections' => 0, 'companyConnections' => 0, 'executivesWithLinkedIn' => 0, 'industry' => ['finance' => 0, 'technology' => 0, 'healthcare' => 0, 'manufacturing' => 0, 'retail' => 0, 'other' => 0]];
        foreach ($executives as $executive) {
            if ($executive->getLinkedinId()) {
                $connectionStats['executivesWithLinkedIn']++;
                $connectionStats['totalConnections'] += $executive->getConnectionCount() ?? 0;
                // Mock industry distribution
                $connectionStats['industry']['finance'] += rand(5, 50);
                $connectionStats['industry']['technology'] += rand(10, 100);
                // ... other industries ...
            }
        }
        $connectionStats['companyConnections'] = rand(5, 20); // Mock value
        return $connectionStats;
    }
}
