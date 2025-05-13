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
     * Generate financial data for a company
     *
     * @param mixed $company The company name or Company object
     * @return int Number of financial data points generated
     */
    public function generateFinancialData($company): int
    {
        // Extract company name if an object is passed
        $companyName = is_object($company) && method_exists($company, 'getName') ? $company->getName() : (string)$company;
        
        $systemPrompt = "You are an AI assistant that specializes in financial data analysis. " .
            "Generate realistic financial data for a company based on its industry and size. " .
            "The data should be realistic but not necessarily accurate for the specific company. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Generate quarterly financial data for {$companyName} for the last 8 quarters. " .
            "Structure your data in JSON format as an array of quarterly results with the following fields for each quarter: " .
            "fiscalYear, fiscalQuarter, revenue, netIncome, eps, grossMargin, operatingMargin, profitMargin, " .
            "ebitda, totalAssets, totalLiabilities, shareholderEquity, cashAndEquivalents, longTermDebt, " .
            "peRatio, dividendYield, roe, debtToEquity, currentRatio, marketCap. " .
            "Return only valid JSON array without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Include response_format for models that support it, but don't depend on it
        $options = [
            'temperature' => 0.4,
            'max_tokens' => 2000,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // Parse the JSON response
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);
            
            // If the response is not an array of quarters, check if it's wrapped in another object
            if (!is_array($data) || !isset($data[0])) {
                // Check if there's a 'quarters' or 'data' field that contains the array
                if (isset($data['quarters']) && is_array($data['quarters'])) {
                    $data = $data['quarters'];
                } elseif (isset($data['data']) && is_array($data['data'])) {
                    $data = $data['data'];
                } else {
                    // If we can't find an array, create a single-item array from the data
                    $data = [$data];
                }
            }
            
            // If we have a Company object, save the financial data
            if (is_object($company) && method_exists($company, 'addFinancialData')) {
                $count = 0;
                
                // Get entity manager through reflection to avoid circular dependency
                $reflection = new \ReflectionClass($company);
                $property = $reflection->getProperty('id');
                $property->setAccessible(true);
                $companyId = $property->getValue($company);
                
                if ($companyId) {
                    // Company is already persisted, so we can add financial data
                    foreach ($data as $quarterData) {
                        // Create and persist the financial data
                        $financialData = new \App\Entity\FinancialData();
                        $financialData->setCompany($company);
                        $financialData->setReportType('Quarterly'); // Set a default report type
                        $financialData->setFiscalYear($quarterData['fiscalYear'] ?? date('Y'));
                        $financialData->setFiscalQuarter($quarterData['fiscalQuarter'] ?? 'Q'.rand(1,4));
                        $financialData->setRevenue($quarterData['revenue'] ?? 0);
                        $financialData->setNetIncome($quarterData['netIncome'] ?? 0);
                        $financialData->setEps($quarterData['eps'] ?? 0);
                        $financialData->setGrossMargin($quarterData['grossMargin'] ?? 0);
                        $financialData->setOperatingMargin($quarterData['operatingMargin'] ?? 0);
                        $financialData->setProfitMargin($quarterData['profitMargin'] ?? 0);
                        $financialData->setEbitda($quarterData['ebitda'] ?? 0);
                        $financialData->setTotalAssets($quarterData['totalAssets'] ?? 0);
                        $financialData->setTotalLiabilities($quarterData['totalLiabilities'] ?? 0);
                        $financialData->setShareholderEquity($quarterData['shareholderEquity'] ?? 0);
                        $financialData->setCashAndEquivalents($quarterData['cashAndEquivalents'] ?? 0);
                        $financialData->setLongTermDebt($quarterData['longTermDebt'] ?? 0);
                        $financialData->setPeRatio($quarterData['peRatio'] ?? 0);
                        $financialData->setDividendYield($quarterData['dividendYield'] ?? 0);
                        $financialData->setRoe($quarterData['roe'] ?? 0);
                        $financialData->setDebtToEquity($quarterData['debtToEquity'] ?? 0);
                        $financialData->setCurrentRatio($quarterData['currentRatio'] ?? 0);
                        $financialData->setMarketCap($quarterData['marketCap'] ?? 0);
                        
                        // Add the financial data to the company
                        $company->addFinancialData($financialData);
                        
                        $count++;
                    }
                }
                
                return $count;
            }
            
            // If we don't have a Company object or can't save the data, just return the count
            return count($data);
            
        } catch (\Exception $e) {
            // Log error but don't throw - we want to avoid transaction failures
            error_log('Error generating financial data: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Generate a leadership profile for a company executive, focusing on filling in missing data
     *
     * @param string $executiveName The name of the executive
     * @param string $companyName The name of the company
     * @param string $title The title of the executive (e.g., 'CEO', 'CFO')
     * @param array $completenessData Information about which fields are already complete (optional)
     * @return array The generated executive profile, focused on missing data
     */
    public function generateExecutiveProfile(
        string $executiveName,
        string $companyName,
        string $title,
        array $completenessData = []
    ): array {
        // Determine which fields need to be researched
        $fieldsNeeded = [];
        foreach ($completenessData as $field => $isComplete) {
            if (!$isComplete) {
                $fieldsNeeded[] = $field;
            }
        }

        // If no fields specified, assume we need all fields
        if (empty($fieldsNeeded) && empty($completenessData)) {
            $fieldsNeeded = ['biography', 'education', 'previousCompanies', 'achievements'];
        } elseif (empty($fieldsNeeded) && !empty($completenessData)) {
            // If all fields are complete, return an empty array
            return [
                'name' => $executiveName,
                'title' => $title,
                'source' => 'ai_not_needed'
            ];
        }

        $fieldsList = implode(", ", $fieldsNeeded);

        $systemPrompt = "You are an AI assistant that specializes in executive leadership research. " .
            "Provide ONLY verifiable facts about company executives based on publicly available information. " .
            "Focus on finding factual information about their professional background, education, and career history. " .
            "DO NOT generate fictional content when information is not available - indicate that it's unknown instead. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Research the following information about {$executiveName}, {$title} of {$companyName}: {$fieldsList}. " .
            "Search ONLY for verifiable facts from public sources. " .
            "Structure your findings in JSON format with the following fields: " .
            "name, title" . (!empty($fieldsNeeded) ? ", " . $fieldsList : "") . ". " .
            "Include ONLY fields that you can find factual information for. " .
            "If you cannot find information for a field, use an empty string or omit the field. " .
            "For fields where you have partial information, provide what you have found but clearly indicate any uncertainties. " .
            "Return only valid JSON, without any explanation or additional text.";

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt]
        ];

        // Lower temperature for more factual responses
        $options = [
            'temperature' => 0.2,
            'max_tokens' => 1200,
            'response_format' => ['type' => 'json_object'] // Will be ignored by models that don't support it
        ];

        $response = $this->generateChatCompletion($messages, $options);

        // Extract JSON in case there's additional text in the response
        $jsonResponse = $this->extractJsonFromText($response);

        try {
            // First try with the extracted JSON
            $data = json_decode($jsonResponse, true, 512, JSON_THROW_ON_ERROR);

            // Add metadata about the data source
            $data['source'] = 'ai';

            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

                // Add metadata about the data source
                $data['source'] = 'ai';

                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'name' => $executiveName,
                    'title' => $title,
                    'source' => 'ai_error',
                    'error' => 'Could not generate executive profile: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Generate competitive analysis for a company
     *
     * @param string|object $company The company name or Company object
     * @param string|null $competitorName The name of the competitor
     * @return array The generated competitive analysis
     */
    public function generateCompetitorAnalysis($company, string $competitorName = null): array
    {
        $companyName = is_object($company) && method_exists($company, 'getName') ? $company->getName() : (string)$company;
        
        $systemPrompt = "You are an AI assistant that specializes in competitive analysis. " .
            "Provide detailed comparison between companies in the same industry or market. " .
            "Focus on strengths, weaknesses, market position, and strategic initiatives. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = $competitorName ?
            "Compare {$companyName} with its competitor {$competitorName}. " :
            "Provide a competitive analysis for {$companyName} and its main competitors. ";
            
        $userPrompt .= "Structure your analysis in JSON format with the following fields: " .
            "companyName, industryOverview, marketPosition, competitors (an array with each competitor having: " .
            "name, marketShare, strengths, weaknesses, threatLevel), " .
            "swotStrengths, swotWeaknesses, swotOpportunities, swotThreats. " .
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
            
            // Save to database if we have a Company object
            if (is_object($company) && method_exists($company, 'addCompetitorAnalysis')) {
                // Implementation for saving to database would go here
            }
            
            return $data;
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
                return $data;
            } catch (\Exception $innerE) {
                // If JSON parsing fails, return a structured error response
                return [
                    'companyName' => $companyName,
                    'error' => 'Could not generate competitor analysis: ' . $e->getMessage()
                ];
            }
        }
    }

    /**
     * Generate a complete research report for a company
     *
     * @param string|object $company The company name or Company object
     * @param string $reportType The type of report (e.g., 'Comprehensive', 'Investment', 'Industry')
     * @return int The number of reports generated
     */
    public function generateResearchReports($company, string $reportType = 'Comprehensive'): int
    {
        $companyName = is_object($company) && method_exists($company, 'getName') ? $company->getName() : (string)$company;
        
        $systemPrompt = "You are an AI assistant that specializes in company research and analysis. " .
            "Provide detailed, structured research reports about companies. " .
            "Your reports should be factual, balanced, and informative, highlighting both " .
            "strengths and challenges facing the company. " .
            "Your response must be valid JSON format without any additional text.";

        $userPrompt = "Create a {$reportType} research report for {$companyName}. " .
            "Structure your report in JSON format with the following sections: " .
            "title, summary, content, reportType, recommendation, priceTarget, analyst. " .
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
            
            // Save to database if we have a Company object
            if (is_object($company) && method_exists($company, 'addResearchReport')) {
                // Implementation for saving to database would go here
                return 1; // Return count of reports generated
            }
            
            return 1; // Just return 1 since we generated one report
        } catch (\Exception $e) {
            // If parsing fails, try once more with the full response
            try {
                $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

                // Add metadata
                $data['reportType'] = $reportType;
                $data['generatedBy'] = 'Neuron AI';
                
                return 1; // Just return 1 since we generated one report
            } catch (\Exception $innerE) {
                // If JSON parsing fails, log the error
                error_log('Error generating research report: ' . $e->getMessage());
                return 0; // Return 0 to indicate failure
            }
        }
    }
}
