<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;

/**
 * Factory for creating the LLM client
 * This service handles the integration with the LLM API
 */
class LlmClientFactory
{
    private string $apiKey;
    private ?string $baseUrl;
    private string $model;
    private array $vcapServices;

    public function __construct(string $apiKey, ?string $baseUrl, string $model)
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl;
        $this->model = $model;
        $this->vcapServices = $this->parseVcapServices();
    }

    /**
     * Get the API key
     * Prioritizes VCAP_SERVICES if running on Cloud Foundry
     */
    public function getApiKey(): string
    {
        // If we have LLM credentials from VCAP_SERVICES, use those instead
        if (!empty($this->vcapServices['api_key'])) {
            return $this->vcapServices['api_key'];
        }

        return $this->apiKey;
    }

    /**
     * Get the base URL
     * Prioritizes VCAP_SERVICES if running on Cloud Foundry
     */
    public function getBaseUrl(): ?string
    {
        // If we have LLM credentials from VCAP_SERVICES, use those instead
        if (!empty($this->vcapServices['url'])) {
            return $this->vcapServices['url'];
        }

        return $this->baseUrl;
    }

    /**
     * Get the model name
     * Prioritizes VCAP_SERVICES if running on Cloud Foundry
     */
    public function getModel(): string
    {
        // If we have LLM credentials from VCAP_SERVICES, use those instead
        if (!empty($this->vcapServices['model'])) {
            return $this->vcapServices['model'];
        }

        return $this->model;
    }

    /**
     * Create a new HTTP client
     */
    public function createHttpClient(): \Symfony\Contracts\HttpClient\HttpClientInterface
    {
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getApiKey(),
                'Content-Type' => 'application/json',
            ],
        ];

        if ($this->getBaseUrl()) {
            $options['base_uri'] = $this->getBaseUrl();
        }

        return HttpClient::create($options);
    }

    /**
     * Parse VCAP_SERVICES environment variable when running on Cloud Foundry
     *
     * Environment Variable Fallbacks:
     * The service will check for credentials in this order:
     * 1. Cloud Foundry VCAP_SERVICES (GenAI service binding)
     * 2. Environment variables (multiple options for compatibility):
     *    - API Key: LLM_API_KEY, API_KEY, GENAI_API_KEY
     *    - API Base URL: LLM_API_BASE, API_BASE_URL, GENAI_API_BASE_URL
     *    - Model: LLM_MODEL, MODEL_NAME, GENAI_MODEL
     * 3. Default values where appropriate
     */
    private function parseVcapServices(): array
    {
        $vcapServices = [];
        // Check both $_SERVER and $_ENV to be thorough
        $vcapServicesJson = $_SERVER['VCAP_SERVICES'] ?? $_ENV['VCAP_SERVICES'] ?? null;

        if (!$vcapServicesJson) {
            return $vcapServices;
        }

        try {
            // Use JSON_THROW_ON_ERROR for explicit error handling
            $services = json_decode($vcapServicesJson, true, 512, JSON_THROW_ON_ERROR);

            // Look for GenAI service by tag, label, or name
            foreach ($services as $serviceName => $serviceInstances) {
                foreach ($serviceInstances as $instance) {
                    // Check for genai tag
                    $hasGenAITag = false;
                    if (isset($instance['tags']) && is_array($instance['tags'])) {
                        foreach ($instance['tags'] as $tag) {
                            if (stripos($tag, 'genai') !== false) {
                                $hasGenAITag = true;
                                break;
                            }
                        }
                    }

                    // Check for genai label
                    $hasGenAILabel = isset($instance['label']) &&
                        stripos($instance['label'], 'genai') !== false;

                    // Check service name
                    $hasGenAIName = stripos($serviceName, 'genai') !== false ||
                        stripos($serviceName, 'llm') !== false;

                    if ($hasGenAITag || $hasGenAILabel || $hasGenAIName) {
                        // Found a potential GenAI service, check for chat capability
                        if (isset($instance['credentials']) && is_array($instance['credentials'])) {
                            $credentials = $instance['credentials'];

                            // Check for model_capabilities
                            $hasChatCapability = false;
                            if (isset($credentials['model_capabilities']) && is_array($credentials['model_capabilities'])) {
                                foreach ($credentials['model_capabilities'] as $capability) {
                                    if (strtolower($capability) === 'chat') {
                                        $hasChatCapability = true;
                                        break;
                                    }
                                }
                            }

                            // If no capabilities specified or has chat capability
                            if (!isset($credentials['model_capabilities']) || $hasChatCapability) {
                                // Map the credentials to our expected format
                                $vcapServices['api_key'] = $credentials['api_key'] ?? $credentials['apiKey'] ?? null;

                                // Try different field names for API base URL
                                $vcapServices['url'] = $credentials['api_base'] ??
                                    $credentials['url'] ??
                                    $credentials['baseUrl'] ??
                                    $credentials['base_url'] ?? null;

                                // Try different field names for model
                                $modelName = $credentials['model_name'] ?? $credentials['model'] ?? null;

                                // If model_provider is available, prefix the model name
                                if (isset($credentials['model_provider']) && $modelName) {
                                    $vcapServices['model'] = $credentials['model_provider'] . '/' . $modelName;
                                } else {
                                    $vcapServices['model'] = $modelName;
                                }

                                break 2; // Exit both loops
                            }
                        }
                    }
                }
            }

            // If we don't have credentials from VCAP_SERVICES, use environment variables with fallbacks
            if (empty($vcapServices['api_key'])) {
                $vcapServices['api_key'] = $_ENV['LLM_API_KEY'] ?? $_ENV['API_KEY'] ?? $_ENV['GENAI_API_KEY'] ?? $this->apiKey;
            }
            if (empty($vcapServices['url'])) {
                $vcapServices['url'] = $_ENV['LLM_API_BASE'] ?? $_ENV['API_BASE_URL'] ?? $_ENV['GENAI_API_BASE_URL'] ?? $this->baseUrl;
            }
            if (empty($vcapServices['model'])) {
                $vcapServices['model'] = $_ENV['LLM_MODEL'] ?? $_ENV['MODEL_NAME'] ?? $_ENV['GENAI_MODEL'] ?? $this->model;
            }
        } catch (\JsonException $e) {
            // Log the error but don't crash
            error_log('Error parsing VCAP_SERVICES JSON: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Log the error but don't crash
            error_log('Error processing VCAP_SERVICES: ' . $e->getMessage());
        }

        return $vcapServices;
    }
}
