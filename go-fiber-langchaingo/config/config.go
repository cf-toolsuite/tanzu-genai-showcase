package config

import (
	"encoding/json"
	"fmt"
	"os"
	"strconv"
	"strings"

	"github.com/joho/godotenv"
)

// Config holds the application configuration
type Config struct {
	Port           int    `json:"port"`
	CongressAPIKey string `json:"congress_api_key"`
	LLMAPIKey      string `json:"llm_api_key"`
	LLMAPIURL      string `json:"llm_api_url"`
	LLMModel       string `json:"llm_model"`
	Environment    string `json:"environment"`
}

// LoadConfig loads configuration from environment variables
// and service bindings (when deployed on Tanzu)
func LoadConfig() (*Config, error) {
	// Load .env file if it exists (for local development)
	godotenv.Load()

	// Default values
	config := &Config{
		Port:        8080,
		Environment: "development",
	}

	// Override with environment variables
	if port, err := strconv.Atoi(os.Getenv("PORT")); err == nil {
		config.Port = port
	}

	if env := os.Getenv("ENV"); env != "" {
		config.Environment = env
	}

	config.CongressAPIKey = os.Getenv("CONGRESS_API_KEY")

	// Try to get configuration from VCAP_SERVICES first
	configuredFromVCAP := false
	if vcapServices := os.Getenv("VCAP_SERVICES"); vcapServices != "" {
		fmt.Println("VCAP_SERVICES found, attempting to parse...")

		// Parse VCAP_SERVICES as a generic JSON structure
		var services map[string][]map[string]interface{}
		if err := json.Unmarshal([]byte(vcapServices), &services); err == nil {
			// Log the structure for debugging
			vcapJSON, _ := json.MarshalIndent(services, "", "  ")
			fmt.Printf("VCAP_SERVICES content: %s\n", vcapJSON)

			// Look for GenAI service by tag, label, or name
			for serviceName, instances := range services {
				fmt.Printf("Checking service: %s\n", serviceName)

				for _, instance := range instances {
					// Check for genai tag
					hasGenAITag := false
					if tags, ok := instance["tags"].([]interface{}); ok {
						for _, tag := range tags {
							if tagStr, ok := tag.(string); ok && containsIgnoreCase(tagStr, "genai") {
								hasGenAITag = true
								fmt.Printf("Found GenAI tag in service: %s\n", serviceName)
								break
							}
						}
					}

					// Check for genai label
					hasGenAILabel := false
					if label, ok := instance["label"].(string); ok && containsIgnoreCase(label, "genai") {
						hasGenAILabel = true
						fmt.Printf("Found GenAI label in service: %s\n", serviceName)
					}

					// Check service name
					hasGenAIName := containsIgnoreCase(serviceName, "genai") ||
						containsIgnoreCase(serviceName, "llm") ||
						containsIgnoreCase(serviceName, "ai")

					if hasGenAIName {
						fmt.Printf("Service name contains GenAI/LLM/AI: %s\n", serviceName)
					}

					if hasGenAITag || hasGenAILabel || hasGenAIName {
						// Found a potential GenAI service
						fmt.Printf("Found potential GenAI service: %s\n", serviceName)

						if credentials, ok := instance["credentials"].(map[string]interface{}); ok {
							// Log credentials structure (excluding sensitive info)
							fmt.Println("Credentials keys found:", getMapKeys(credentials))

							// Check for model_capabilities
							hasChatCapability := false
							if capabilities, ok := credentials["model_capabilities"].([]interface{}); ok {
								for _, cap := range capabilities {
									if capStr, ok := cap.(string); ok && containsIgnoreCase(capStr, "chat") {
										hasChatCapability = true
										fmt.Println("Found chat capability in model_capabilities")
										break
									}
								}
							}

							// If no capabilities specified or has chat capability
							if _, hasCapabilities := credentials["model_capabilities"]; !hasCapabilities || hasChatCapability {
								// Extract credentials with proper field mapping
								if apiKey, ok := credentials["api_key"].(string); ok {
									config.LLMAPIKey = apiKey
									fmt.Println("Found api_key in credentials")
								}

								// Try different field names for API base URL
								if apiBase, ok := credentials["api_base"].(string); ok {
									config.LLMAPIURL = apiBase
									fmt.Println("Found api_base in credentials")
								} else if url, ok := credentials["url"].(string); ok {
									config.LLMAPIURL = url
									fmt.Println("Found url in credentials")
								} else if baseUrl, ok := credentials["base_url"].(string); ok {
									config.LLMAPIURL = baseUrl
									fmt.Println("Found base_url in credentials")
								}

								// Try different field names for model
								if modelName, ok := credentials["model_name"].(string); ok {
									fmt.Printf("Found model_name in credentials: %s\n", modelName)
									// If model_provider is available, prefix the model name
									if provider, ok := credentials["model_provider"].(string); ok {
										config.LLMModel = provider + "/" + modelName
										fmt.Printf("Using model with provider: %s\n", config.LLMModel)
									} else {
										config.LLMModel = modelName
										fmt.Printf("Using model: %s\n", config.LLMModel)
									}
								} else if model, ok := credentials["model"].(string); ok {
									config.LLMModel = model
									fmt.Printf("Found model in credentials: %s\n", config.LLMModel)
								} else if defaultModel, ok := credentials["default_model"].(string); ok {
									config.LLMModel = defaultModel
									fmt.Printf("Found default_model in credentials: %s\n", config.LLMModel)
								}

								// If we found at least API key and URL, consider it configured
								if config.LLMAPIKey != "" && config.LLMAPIURL != "" {
									configuredFromVCAP = true
									fmt.Println("Successfully configured from VCAP_SERVICES")
									break
								}
							}
						}
					}
				}
				if configuredFromVCAP {
					break
				}
			}
		} else {
			fmt.Printf("Error parsing VCAP_SERVICES: %v\n", err)
		}
	}

	// Fallback to environment variables if not set from VCAP_SERVICES
	if !configuredFromVCAP {
		fmt.Println("Falling back to environment variables for configuration")

		if config.LLMAPIKey == "" {
			config.LLMAPIKey = getFirstNonEmpty(os.Getenv("GENAI_API_KEY"), os.Getenv("LLM_API_KEY"), os.Getenv("API_KEY"))
		}

		if config.LLMAPIURL == "" {
			config.LLMAPIURL = getFirstNonEmpty(os.Getenv("GENAI_API_BASE_URL"), os.Getenv("LLM_API_BASE"), os.Getenv("API_BASE_URL"))
		}

		if config.LLMModel == "" {
			config.LLMModel = getFirstNonEmpty(os.Getenv("GENAI_MODEL"), os.Getenv("LLM_MODEL"), os.Getenv("MODEL_NAME"), os.Getenv("LLM"))
		}
	}

	// Validate configuration
	var missingConfig []string
	if config.CongressAPIKey == "" {
		missingConfig = append(missingConfig, "Congress API Key")
	}
	if config.LLMAPIKey == "" {
		missingConfig = append(missingConfig, "LLM API Key")
	}
	if config.LLMAPIURL == "" {
		missingConfig = append(missingConfig, "LLM API URL")
	}
	if config.LLMModel == "" {
		missingConfig = append(missingConfig, "LLM Model")
	}

	if len(missingConfig) > 0 {
		return nil, fmt.Errorf("missing required configuration: %s", strings.Join(missingConfig, ", "))
	}

	return config, nil
}

// Helper function to get keys from a map for logging
func getMapKeys(m map[string]interface{}) []string {
	keys := make([]string, 0, len(m))
	for k := range m {
		keys = append(keys, k)
	}
	return keys
}

// Helper function to check if a string contains another string, ignoring case
func containsIgnoreCase(s, substr string) bool {
	s, substr = strings.ToLower(s), strings.ToLower(substr)
	return strings.Contains(s, substr)
}

// Helper function to get the first non-empty string from a list
func getFirstNonEmpty(values ...string) string {
	for _, v := range values {
		if v != "" {
			return v
		}
	}
	return ""
}
