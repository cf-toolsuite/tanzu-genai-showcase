<?php

namespace App\Service\ApiClient;

use Psr\Log\LoggerInterface;

/**
 * Mock Implementation of LinkedIn API client
 * Returns predefined mock data.
 */
class MockLinkedInApiClient // Does not implement ApiClientInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->info("MockLinkedInApiClient instantiated.");
    }

    public function getAuthorizationUrl(array $scopes = []): string
    {
        $this->logger->info("MockLinkedInApiClient::getAuthorizationUrl called");
        // Return a dummy URL or a placeholder indicating mock mode
        return '#mock-linkedin-auth-url';
    }

    public function getAccessToken(string $code, string $state): array
    {
        $this->logger->info("MockLinkedInApiClient::getAccessToken called");
        // Return mock token data
        return [
            'access_token' => 'MOCK_ACCESS_TOKEN_' . bin2hex(random_bytes(10)),
            'expires_in' => 3600
        ];
    }

    // Public methods that perform API calls return mock data directly

    public function getProfile(): array
    {
        $this->logger->info("MockLinkedInApiClient::getProfile called");
        return $this->getMockUserProfile();
    }

    public function getWorkExperience(): array
    {
        $this->logger->info("MockLinkedInApiClient::getWorkExperience called");
        return $this->getMockWorkExperienceData();
    }

    public function getCompany(string $companyId): array
    {
        $this->logger->info("MockLinkedInApiClient::getCompany called", ['id' => $companyId]);
        return $this->getMockCompanyData();
    }

    public function getConnections(): array
    {
        $this->logger->info("MockLinkedInApiClient::getConnections called");
        return $this->getMockConnectionsData();
    }


    // --- Mock Data Generation Methods (Copied from original LinkedInApiClient) ---

    private function getMockUserProfile(): array
    { /* ... */
        return ['linkedinId' => 'mock123', 'firstName' => 'Mock', 'lastName' => 'User', 'headline' => 'Mock Professional', 'vanityName' => 'mockuser', 'profileUrl' => 'https://linkedin.com/in/mockuser', 'email' => 'mock@example.com', 'pictureUrl' => 'https://via.placeholder.com/100?text=MU', 'rawData' => ['id' => 'mock123']];
    }
    private function getMockWorkExperienceData(): array
    { /* ... */
        return [['companyName' => 'Mock Current Co', 'title' => 'Senior Mock', 'startDate' => '2020-01', 'endDate' => null, 'current' => true, 'description' => 'Did mock things.'], ['companyName' => 'Mock Past Co', 'title' => 'Junior Mock', 'startDate' => '2018-06', 'endDate' => '2019-12', 'current' => false, 'description' => 'Assisted mocks.']];
    }
    private function getMockCompanyData(): array
    { /* ... */
        return ['id' => 'urn:li:organization:mock123', 'name' => 'Mock Solutions Inc.', 'description' => 'Provider of mock data.', 'website' => 'https://mock.com', 'industry' => 'Mock Tech', 'companySize' => 500, 'headquarters' => '{"city":"Mockville"}', 'foundedYear' => 2015, 'specialties' => ['Mocking', 'Testing'], 'rawData' => []];
    }
    private function getMockConnectionsData(): array
    { /* ... */
        $conns = [];
        for ($i = 0; $i < 5; $i++) {
            $conns[] = ['urn' => 'urn:li:person:mockConn' . $i];
        }
        return ['count' => 150, 'connections' => $conns]; // Mock count and some URNs
    }
}
