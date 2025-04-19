<?php

namespace App\Service\ApiClient;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
// SessionInterface might still be needed if RequestStack->getSession() return type isn't strictly enforced/hinted elsewhere
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * LinkedIn API client - REAL Implementation
 */
class LinkedInApiClient // Does not implement our standard ApiClientInterface
{
     private const API_BASE_URL = 'https://api.linkedin.com/v2';
     private const OAUTH_URL = 'https://www.linkedin.com/oauth/v2';

     private ParameterBagInterface $params;
     private LoggerInterface $logger;
     private RequestStack $requestStack;
     private ?HttpClientInterface $httpClient = null;

     private string $clientId;
     private string $clientSecret;
     private string $redirectUri;

     public function __construct(
          ParameterBagInterface $params,
          LoggerInterface $logger,
          RequestStack $requestStack
     ) {
          $this->params = $params;
          $this->logger = $logger;
          $this->requestStack = $requestStack;
          $this->initialize();

          // Check config when the real client is instantiated
          if (empty($this->clientId) || empty($this->clientSecret) || empty($this->redirectUri)) {
               $this->logger->error("LinkedInApiClient is missing required configuration (ID, Secret, or Redirect URI). Real API calls WILL fail.");
               // Depending on requirements, you might throw an exception here
               // throw new \InvalidArgumentException("LinkedIn API client configuration is incomplete.");
          }
     }

     /**
      * Initialize the API client with configuration parameters
      */
     private function initialize(): void
     {
          $this->clientId = $this->params->get('linkedin_api.client_id', '');
          $this->clientSecret = $this->params->get('linkedin_api.client_secret', '');
          $this->redirectUri = $this->params->get('linkedin_api.redirect_uri', '');
          $this->httpClient = HttpClient::create();
     }

     /**
      * Get the authorization URL for LinkedIn OAuth
      */
     public function getAuthorizationUrl(array $scopes = []): string
     {
          if (empty($this->clientId) || empty($this->redirectUri)) {
               $this->logger->error("Cannot generate LinkedIn Auth URL: Client ID or Redirect URI is missing.");
               return '#linkedin-config-error';
          }
          if (empty($scopes)) {
               $scopes = ['r_liteprofile', 'r_emailaddress'];
          } // Minimal scopes
          $state = bin2hex(random_bytes(16));
          $session = $this->getSession();
          $session->set('linkedin_oauth_state', $state);
          $params = ['response_type' => 'code', 'client_id' => $this->clientId, 'redirect_uri' => $this->redirectUri, 'state' => $state, 'scope' => implode(' ', $scopes)];
          return self::OAUTH_URL . '/authorization?' . http_build_query($params);
     }

     /**
      * Exchange authorization code for access token
      */
     public function getAccessToken(string $code, string $state): array
     {
          if (empty($this->clientId) || empty($this->clientSecret) || empty($this->redirectUri)) {
               throw new \RuntimeException("LinkedIn client configuration missing for token exchange.");
          }
          $session = $this->getSession();
          $savedState = $session->get('linkedin_oauth_state');
          if (!$savedState || $savedState !== $state) throw new \InvalidArgumentException('Invalid state parameter');
          $session->remove('linkedin_oauth_state');

          try {
               $response = $this->httpClient->request('POST', self::OAUTH_URL . '/accessToken', [
                    'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
                    'body' => http_build_query(['grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->redirectUri, 'client_id' => $this->clientId, 'client_secret' => $this->clientSecret])
               ]);
               $tokenData = $response->toArray(); // Throws on non-2xx or invalid JSON

               // Store real token in session
               $expiresIn = $tokenData['expires_in'] ?? 3600;
               $session->set('linkedin_access_token', $tokenData['access_token']);
               $session->set('linkedin_expires_at', time() + $expiresIn);
               return $tokenData;
          } catch (\Exception $e) {
               $this->logger->error('LinkedIn API error during token exchange: ' . $e->getMessage(), ['exception' => $e]);
               throw new \RuntimeException('Failed to obtain LinkedIn access token: ' . $e->getMessage(), $e->getCode(), $e);
          }
     }

     /**
      * Make an authenticated API request to LinkedIn. Internal helper.
      * Assumes token handling is done by the public method calling this.
      */
     private function performApiRequest(string $endpoint, string $method = 'GET', array $params = [], string $accessToken = null): array
     {
          if ($accessToken === null) $accessToken = $this->getAccessTokenFromSession();
          if (!$accessToken) throw new \RuntimeException('No valid LinkedIn access token available.');

          $url = self::API_BASE_URL . '/' . ltrim($endpoint, '/');

          try {
               $options = [
                    'headers' => ['Authorization' => 'Bearer ' . $accessToken, 'Content-Type' => 'application/json', 'X-Restli-Protocol-Version' => '2.0.0', 'LinkedIn-Version' => '202405'] // Specify recent version
               ];
               if ($method === 'GET' && !empty($params)) $url .= '?' . http_build_query($params);
               elseif ($method !== 'GET' && !empty($params)) $options['json'] = $params;

               $response = $this->httpClient->request($method, $url, $options);
               // Throws HttpException for 4xx/5xx automatically
               return $response->toArray(); // Throws DecodingException for invalid JSON
          } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) {
               $this->logger->error("LinkedIn API request error for {$url}: " . $e->getMessage(), ['exception' => $e]);
               // Provide more specific error message if possible
               $responseContent = 'N/A';
               if ($e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
                    try {
                         $responseContent = $e->getResponse()->getContent(false);
                    } catch (\Exception $innerEx) {
                    }
                    throw new \RuntimeException("LinkedIn API request failed with status {$e->getResponse()->getStatusCode()}: {$responseContent}", $e->getCode(), $e);
               }
               throw new \RuntimeException("LinkedIn API request failed: " . $e->getMessage(), $e->getCode(), $e);
          }
     }

     /**
      * Get the current LinkedIn access token from session if valid
      */
     private function getAccessTokenFromSession(): ?string
     {
          $session = $this->getSession();
          $token = $session->get('linkedin_access_token');
          $expiresAt = $session->get('linkedin_expires_at');
          if ($token && $expiresAt && $expiresAt > time()) return $token;
          if ($token) {
               $this->logger->info('LinkedIn token expired.');
               $session->remove('linkedin_access_token');
               $session->remove('linkedin_expires_at');
          }
          return null;
     }

     /** Helper to get session */
     private function getSession(): SessionInterface
     {
          return $this->requestStack->getSession();
     }


     /**
      * Get the current user's LinkedIn profile
      */
     public function getProfile(): array
     {
          try {
               $basicProfileFields = 'id,firstName,lastName,profilePicture(displayImage~:playableStreams),headline,vanityName';
               $basicProfile = $this->performApiRequest('/me?projection=(' . $basicProfileFields . ')');
               $emailData = $this->performApiRequest('/emailAddress?q=members&projection=(elements*(handle~))');
               if (isset($emailData['elements'][0]['handle~']['emailAddress'])) {
                    $basicProfile['emailAddress'] = $emailData['elements'][0]['handle~']['emailAddress'];
               }
               return $this->formatProfileData($basicProfile);
          } catch (\Exception $e) {
               $this->logger->error('Failed to get LinkedIn profile: ' . $e->getMessage());
               throw $e; // Re-throw the exception
          }
     }

     /** Format profile data - unchanged */
     private function formatProfileData(array $raw): array
     { /* ... */
          $formatted = ['linkedinId' => $raw['id'] ?? null, 'firstName' => $this->getLocalizedField($raw['firstName'] ?? []), 'lastName' => $this->getLocalizedField($raw['lastName'] ?? []), 'headline' => $this->getLocalizedField($raw['headline'] ?? []), 'vanityName' => $raw['vanityName'] ?? null, 'profileUrl' => 'https://www.linkedin.com/in/' . ($raw['vanityName'] ?? $raw['id'] ?? ''), 'email' => $raw['emailAddress'] ?? null, 'pictureUrl' => null, 'rawData' => $raw];
          if (isset($raw['profilePicture']['displayImage~']['elements'])) {
               $p = $raw['profilePicture']['displayImage~']['elements'];
               if (!empty($p)) {
                    $formatted['pictureUrl'] = end($p)['identifiers'][0]['identifier'] ?? null;
               }
          }
          return $formatted;
     }
     /** Get localized field - unchanged */
     private function getLocalizedField(mixed $f): ?string
     { /* ... */
          if (!is_array($f)) return null;
          if (isset($f['localized'])) {
               if (isset($f['preferredLocale']['language']) && isset($f['preferredLocale']['country'])) {
                    $k = $f['preferredLocale']['language'] . '_' . $f['preferredLocale']['country'];
                    if (isset($f['localized'][$k])) return $f['localized'][$k];
               }
               return reset($f['localized']) ?: null;
          }
          return null;
     }
     /** Format date - unchanged */
     private function formatDate(array $d): ?string
     { /* ... */
          if (empty($d) || !isset($d['year'])) return null;
          $m = isset($d['month']) ? str_pad($d['month'], 2, '0', STR_PAD_LEFT) : '01';
          return $d['year'] . '-' . $m;
     }


     /**
      * Get the work experience of a LinkedIn user
      */
     public function getWorkExperience(): array
     {
          try {
               // Endpoint might require specific projection or be different
               $response = $this->performApiRequest('/me/positions'); // Check correct endpoint
               if (!isset($response['elements'])) return [];
               $experiences = [];
               foreach ($response['elements'] as $pos) {
                    $coName = isset($pos['company']['name']) ? $this->getLocalizedField($pos['company']['name']) : 'Unknown';
                    $experiences[] = ['companyName' => $coName, 'title' => $this->getLocalizedField($pos['title'] ?? []), 'startDate' => $this->formatDate($pos['timePeriod']['startDate'] ?? []), 'endDate' => $this->formatDate($pos['timePeriod']['endDate'] ?? []), 'current' => !isset($pos['timePeriod']['endDate']), 'description' => $this->getLocalizedField($pos['description'] ?? [])];
               }
               return $experiences;
          } catch (\Exception $e) {
               $this->logger->error('Failed to get LinkedIn work experience: ' . $e->getMessage());
               return []; // Return empty on error
          }
     }


     /**
      * Get company data from LinkedIn
      */
     public function getCompany(string $companyId): array // Takes URN or ID
     {
          if (!str_starts_with($companyId, 'urn:li:organization:')) $companyUrn = 'urn:li:organization:' . $companyId;
          else $companyUrn = $companyId;
          $encodedUrn = urlencode($companyUrn);
          $projection = '(id,name,description,websiteUrl,industries,staffCount,headquarters,foundedOn,specialties)';
          $endpoint = "/organizations/{$encodedUrn}?projection={$projection}";

          try {
               $response = $this->performApiRequest($endpoint);
               $foundedOn = $response['foundedOn'] ?? null;
               $foundedYear = ($foundedOn && isset($foundedOn['year'])) ? $foundedOn['year'] : null;
               return ['id' => $response['id'] ?? null, 'name' => $this->getLocalizedField($response['name'] ?? []), 'description' => $this->getLocalizedField($response['description'] ?? []), 'website' => $response['websiteUrl'] ?? null, 'industry' => isset($response['industries'][0]) ? $this->getLocalizedField($response['industries'][0]) : null, 'companySize' => $response['staffCountRange']['start'] ?? null, /* Use start of range */ 'headquarters' => isset($response['headquarters']) ? json_encode($response['headquarters']) : null, 'foundedYear' => $foundedYear, 'specialties' => isset($response['specialties']) ? array_map([$this, 'getLocalizedField'], $response['specialties']) : [], 'rawData' => $response];
          } catch (\Exception $e) {
               $this->logger->error('Failed to get LinkedIn company: ' . $e->getMessage());
               throw $e; // Re-throw
          }
     }

     /**
      * Get basic connection data for the current user
      */
     public function getConnections(): array
     {
          try {
               $params = ['q' => 'viewer', 'start' => 0, 'count' => 10]; // Get first 10 connections
               $response = $this->performApiRequest('/connections', 'GET', $params); // Endpoint might differ based on permissions
               $connections = [];
               foreach ($response['elements'] ?? [] as $urn) {
                    $connections[] = ['urn' => $urn];
               } // Likely just URNs
               $count = $response['paging']['total'] ?? count($connections); // Approx count
               return ['count' => $count, 'connections' => $connections];
          } catch (\Exception $e) {
               $this->logger->error('Failed to get LinkedIn connections: ' . $e->getMessage());
               $errorMsg = 'Failed to get connections: ' . $e->getMessage();
               if ($e->getCode() === 403) {
                    $errorMsg = 'Permission denied for fetching connections.';
               }
               return ['count' => 0, 'connections' => [], 'error' => $errorMsg]; // Return error structure
          }
     }
}
