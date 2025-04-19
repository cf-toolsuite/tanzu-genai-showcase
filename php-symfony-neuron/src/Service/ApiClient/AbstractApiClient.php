<?php

namespace App\Service\ApiClient;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for REAL API clients. Mock logic is moved to separate classes.
 */
abstract class AbstractApiClient implements ApiClientInterface
{
    protected HttpClientInterface $httpClient;
    protected ParameterBagInterface $params;
    protected LoggerInterface $logger;
    protected string $apiKey;
    protected string $baseUrl;

    /**
     * Constructor
     */
    public function __construct(
        HttpClientInterface $httpClient,
        ParameterBagInterface $params,
        LoggerInterface $logger
    ) {
        $this->httpClient = $httpClient;
        $this->params = $params;
        $this->logger = $logger;

        // Call initialize AFTER setting dependencies
        $this->initialize();

        // Check for API key if it's required for this client (determined by getAuthParams)
        // This check happens when the REAL client is instantiated.
        if (empty($this->apiKey) && !empty($this->getAuthParams())) {
            $clientClass = get_class($this);
            $this->logger->error("{$clientClass}: API key is missing. Real API calls WILL fail.");
            // Optionally throw an exception immediately if a key is absolutely required
            // throw new \InvalidArgumentException("API Key is missing for " . $clientClass);
        }
    }

    /**
     * Initialize API client with configuration
     * Subclasses set $this->baseUrl and $this->apiKey here.
     */
    abstract protected function initialize(): void;

    /**
     * Get authentication parameters for the API (e.g., API key).
     * Return empty array if no auth params are needed.
     *
     * @return array Authentication parameters (e.g., ['apikey' => $this->apiKey])
     */
    abstract protected function getAuthParams(): array;

    /**
     * Make a REAL API request. Throws exception on failure.
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint (relative path or full URL if baseUrl is empty)
     * @param array $params Query parameters (for GET) or body (for POST/PUT etc.)
     * @param array $options Additional options for the HTTP client (e.g., headers)
     * @return array Response data decoded from JSON
     * @throws \Exception If API key is missing (and needed), request fails, or response is invalid
     */
    protected function request(string $method, string $endpoint, array $params = [], array $options = []): array
    {
        // Ensure API Key is present if required by the specific client implementation
        $authParams = $this->getAuthParams();
        if (!empty($authParams) && empty($this->apiKey)) {
            throw new \LogicException("API Key is required but missing for " . get_class($this));
        }

        // Construct URL: Use baseUrl if endpoint is relative, otherwise use endpoint as full URL
        $url = (str_starts_with($endpoint, 'http://') || str_starts_with($endpoint, 'https://'))
            ? $endpoint
            : rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');


        $requestOptions = array_merge_recursive([ // Use recursive merge for headers etc.
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Symfony/NeuronAI Financial Research Application'
            ],
            // Default query params handling (include auth params)
            'query' => ($method === 'GET') ? array_merge($params, $authParams) : $authParams,
            // Default body handling (use $params as body for non-GET)
            'json' => ($method !== 'GET') ? $params : null,
        ], $options); // Merge caller-provided options last

        // Remove null json body if present
        if (isset($requestOptions['json']) && $requestOptions['json'] === null) {
            unset($requestOptions['json']);
        }
        // Ensure query is set for GET, even if empty initially, but only add authParams if needed
        if ($method === 'GET' && !isset($requestOptions['query'])) {
            $requestOptions['query'] = $authParams; // Auth params might be empty
        }

        try {
            $this->logger->info("Making REAL API {$method} request to {$url}", ['option_keys' => array_keys($requestOptions)]);
            $response = $this->httpClient->request($method, $url, $requestOptions);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                $errorBody = '';
                try {
                    $errorBody = $response->getContent(false);
                } catch (\Exception $e) { /* Ignore */
                }
                $this->logger->error("API returned error {$statusCode}", ['url' => $url, 'response' => $errorBody]);
                // Provide more context in the exception message
                throw new \RuntimeException("API request to {$url} failed with status code {$statusCode}. Response: " . $errorBody, $statusCode);
            }

            // Attempt to decode JSON
            $data = $response->toArray(); // Throws exception on invalid JSON
            return $data;
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            $this->logger->error("API transport error for {$url}: {$e->getMessage()}");
            throw new \RuntimeException("API transport error for {$url}: " . $e->getMessage(), $e->getCode(), $e);
        } catch (\Symfony\Contracts\HttpClient\Exception\ExceptionInterface $e) { // Catch other client errors
            $this->logger->error("API client error for {$url}: {$e->getMessage()}");
            // Check if it's an HTTP exception to get response details
            $responseContent = 'N/A';
            if ($e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
                try {
                    $responseContent = $e->getResponse()->getContent(false);
                } catch (\Exception $innerEx) { /* Ignore */
                }
            }
            $this->logger->error('API client error details:', ['response_content' => $responseContent]);
            throw new \RuntimeException("API client error for {$url}: " . $e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) { // Catch potential JsonException from toArray() or others
            $this->logger->error("Error processing API request or response for {$url}: {$e->getMessage()}");
            throw new \RuntimeException("Error processing API request or response for {$url}: " . $e->getMessage(), $e->getCode(), $e);
        }
    }
}
