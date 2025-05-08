<?php

namespace App\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * Service for interacting with Neuron AI LLM APIs
 */
class NeuronAiService
{
    private LlmClientFactory $clientFactory;
    private HttpClientInterface $httpClient;

    public function __construct(LlmClientFactory $clientFactory)
    {
        $this->clientFactory = $clientFactory;
        $this->httpClient = $clientFactory->createHttpClient();
    }

    /**
     * Extract JSON from a text response that might contain additional text
     *
     * @param string $text The text that might contain JSON
     * @return string The extracted JSON
     */
    private function extractJsonFromText(string $text): string
    {
        // Log the original response for debugging
        error_log('Raw LLM response: ' . substr($text, 0, 500) . (strlen($text) > 500 ? '...' : ''));

        // Look for JSON object pattern ({...})
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $matches)) {
            $jsonCandidate = $matches[0];

            // Verify it's valid JSON
            if ($this->isValidJson($jsonCandidate)) {
                return $jsonCandidate;
            }

            // If not valid, try to clean it up
            $cleaned = $this->cleanJsonString($jsonCandidate);
            if ($this->isValidJson($cleaned)) {
                return $cleaned;
            }
        }

        // Remove markdown code block markers if present
        $text = preg_replace('/```(?:json)?\s*(.*?)\s*```/s', '$1', $text);
        $cleaned = $this->cleanJsonString(trim($text));

        return $cleaned;
    }

    /**
     * Check if a string is valid JSON
     *
     * @param string $string The string to check
     * @return bool Whether the string is valid JSON
     */
    private function isValidJson(string $string): bool
    {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Clean a JSON string to make it more likely to parse correctly
     *
     * @param string $string The JSON string to clean
     * @return string The cleaned JSON string
     */
    private function cleanJsonString(string $string): string
    {
        // Replace common issues with Together API responses

        // Fix unescaped quotes in strings
        $string = preg_replace('/"([^"]*?)(?<!\\\\)"([^"]*?)"/', '"$1\\"$2"', $string);

        // Fix missing commas between objects
        $string = preg_replace('/}(\s*){/', '},${1}{', $string);

        // Fix trailing commas in objects/arrays
        $string = preg_replace('/,(\s*)}/', '$1}', $string);
        $string = preg_replace('/,(\s*)]/', '$1]', $string);

        // Fix missing quotes around property names
        $string = preg_replace('/([{,]\s*)(\w+)(\s*:)/', '$1"$2"$3', $string);

        return $string;
    }

    /**
     * Generate a text completion from the LLM
     *
     * @param string $prompt The prompt to send to the LLM
     * @param array $options Additional options for the LLM request
     * @return string The generated text
     * @throws \Exception If the LLM request fails
     */
    public function generateCompletion(string $prompt, array $options = []): string
    {
        $defaultOptions = [
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
        ];

        $requestOptions = array_merge($defaultOptions, $options);
        $requestOptions['model'] = $this->clientFactory->getModel();
        $requestOptions['prompt'] = $prompt;

        try {
            $response = $this->httpClient->request('POST', '/v1/completions', [
                'json' => $requestOptions,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new \Exception('LLM API returned status code ' . $statusCode);
            }

            $data = $response->toArray();
            if (empty($data['choices'][0]['text'])) {
                throw new \Exception('LLM API returned no completions');
            }

            return trim($data['choices'][0]['text']);
        } catch (TransportExceptionInterface $e) {
            throw new \Exception('Failed to connect to LLM API: ' . $e->getMessage());
        } catch (\Exception $e) {
            throw new \Exception('Error in LLM request: ' . $e->getMessage());
        }
    }

    /**
     * Generate a chat completion from the LLM
     *
     * @param array $messages Array of message objects with 'role' and 'content'
     * @param array $options Additional options for the LLM request
     * @return string The generated response
     * @throws \Exception If the LLM request fails
     */
    public function generateChatCompletion(array $messages, array $options = []): string
    {
        $defaultOptions = [
            'temperature' => 0.7,
            'max_tokens' => 1000,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0,
        ];

        $requestOptions = array_merge($defaultOptions, $options);
        $requestOptions['model'] = $this->clientFactory->getModel();
        $requestOptions['messages'] = $messages;

        try {
            $response = $this->httpClient->request('POST', '/v1/chat/completions', [
                'json' => $requestOptions,
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                error_log('LLM API returned non-200 status code: ' . $statusCode);
                throw new \Exception('LLM API returned status code ' . $statusCode);
            }

            $data = $response->toArray();
            if (empty($data['choices'][0]['message']['content'])) {
                error_log('LLM API returned empty response: ' . json_encode($data));
                throw new \Exception('LLM API returned no completions');
            }

            return trim($data['choices'][0]['message']['content']);
        } catch (TransportExceptionInterface $e) {
            error_log('LLM API transport error: ' . $e->getMessage());
            error_log('API URL: ' . $this->clientFactory->getBaseUrl());
            error_log('API Model: ' . $this->clientFactory->getModel());
            $apiKey = $this->clientFactory->getApiKey();
            error_log('API Key Format: ' . substr($apiKey, 0, 8) . '...' . substr($apiKey, -5));
            throw new \Exception('Failed to connect to LLM API: ' . $e->getMessage());
        } catch (\Exception $e) {
            error_log('LLM API error: ' . $e->getMessage());
            throw new \Exception('Error in LLM request: ' . $e->getMessage());
        }
    }

    /**
     * Generate company information using the LLM
     *
     * @param string $companyName The name of the company to research
     * @return array The generated company information
     */
    public function generateCompanyInfo(string $companyName): array
    {
        $systemPrompt = "You are an AI assistant that specializes in company research. " .
            "Provide accurate, factual information about companies. " .
            "Focus on company overview, industry, sector, headquarters, and a brief description. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Provide information about {$companyName} in JSON format with the following fields: " .
            "name, industry, sector, headquarters, description. " .
            "Keep the description concise (2-3 sentences). " .
            "Return only valid JSON, without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Include response_format for models that support it, but don't depend on it
        $options = [
            'temperature' => 0.2,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // First try with the extracted JSON
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'name' => $companyName,
                    'error' => 'Could not generate company information: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Generate financial data analysis for a company
     *
     * @param string $companyName The name of the company
     * @param string $reportType The type of report (e.g., '10-K', '10-Q')
     * @return array The generated financial analysis
     */
    public function generateFinancialAnalysis(string $companyName, string $reportType = '10-K'): array
    {
        $systemPrompt = "You are an AI assistant that specializes in financial analysis. " .
            "Provide detailed analysis of company financial reports. Focus on key metrics, " .
            "trends, and important insights from financial statements. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Analyze the most recent {$reportType} financial report for {$companyName}. " .
            "Structure your analysis in JSON format with the following fields: " .
            "reportType, reportDate, revenue, netIncome, eps, ebitda, highlights, risks, " .
            "and source (indicate that this is AI-generated analysis). " .
            "Return only valid JSON, without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Include response_format for models that support it, but don't depend on it
        $options = [
            'temperature' => 0.3,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // First try with the extracted JSON
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'reportType' => $reportType,
                    'error' => 'Could not generate financial analysis: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Generate a leadership profile for a company executive
     *
     * @param string $executiveName The name of the executive
     * @param string $companyName The name of the company
     * @param string $title The title of the executive (e.g., 'CEO', 'CFO')
     * @return array The generated executive profile
     */
    public function generateExecutiveProfile(string $executiveName, string $companyName, string $title): array
    {
        $systemPrompt = "You are an AI assistant that specializes in executive leadership analysis. " .
            "Provide detailed profiles of company executives based on publicly available information. " .
            "Focus on professional background, leadership style, and achievements. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Create a profile for {$executiveName}, {$title} of {$companyName}. " .
            "Structure your analysis in JSON format with the following fields: " .
            "name, title, biography, education, previousCompanies, achievements, leadership. " .
            "Return only valid JSON, without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Include response_format for models that support it, but don't depend on it
        $options = [
            'temperature' => 0.4,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // First try with the extracted JSON
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'name' => $executiveName,
                    'title' => $title,
                    'error' => 'Could not generate executive profile: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Generate competitive analysis for a company
     *
     * @param string $companyName The name of the company
     * @param string $competitorName The name of the competitor
     * @return array The generated competitive analysis
     */
    public function generateCompetitorAnalysis(string $companyName, string $competitorName): array
    {
        $systemPrompt = "You are an AI assistant that specializes in competitive analysis. " .
            "Provide detailed comparison between companies in the same industry or market. " .
            "Focus on strengths, weaknesses, market position, and strategic initiatives. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Compare {$companyName} with its competitor {$competitorName}. " .
            "Structure your analysis in JSON format with the following fields: " .
            "competitorName, overview, strengths, weaknesses, marketShare, productComparison, " .
            "financialComparison, strategicInitiatives. " .
            "Return only valid JSON, without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Include response_format for models that support it, but don't depend on it
        $options = [
            'temperature' => 0.4,
            'max_tokens' => 1500,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // First try with the extracted JSON
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'competitorName' => $competitorName,
                    'error' => 'Could not generate competitor analysis: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Generate a complete research report for a company
     *
     * @param string $companyName The name of the company
     * @param string $reportType The type of report (e.g., 'Comprehensive', 'Investment', 'Industry')
     * @return array The generated research report
     */
    public function generateResearchReport(string $companyName, string $reportType = 'Comprehensive'): array
    {
        $systemPrompt = "You are an AI assistant that specializes in company research and analysis. " .
            "Provide detailed, structured research reports about companies. " .
            "Your reports should be factual, balanced, and informative, highlighting both " .
            "strengths and challenges facing the company. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Create a {$reportType} research report for {$companyName}. " .
            "Structure your report in JSON format with the following sections: " .
            "title, executiveSummary, companyOverview, industryAnalysis, financialAnalysis, " .
            "competitiveAnalysis, swotAnalysis, investmentHighlights, risksAndChallenges, conclusion. " .
            "Each section should be detailed but concise. " .
            "Return only valid JSON, without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Include response_format for models that support it, but don't depend on it
        $options = [
            'temperature' => 0.5,
            'max_tokens' => 4000,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // First try with the extracted JSON
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);

            // Add metadata
            $data['reportType'] = $reportType;
            $data['generatedBy'] = 'Neuron AI';

            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

                // Add metadata
                $data['reportType'] = $reportType;
                $data['generatedBy'] = 'Neuron AI';

                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'title' => "{$reportType} Report for {$companyName}",
                    'reportType' => $reportType,
                    'error' => 'Could not generate research report: ' . $e->getMessage()
                ];
            }
        }
    }
}
