using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Steeltoe.Extensions.Configuration.CloudFoundry;
using TravelAdvisor.Infrastructure.Options;

namespace TravelAdvisor.Infrastructure.CloudFoundry
{
    /// <summary>
    /// Configuration for Cloud Foundry service bindings
    /// </summary>
    public static class ServiceBindingConfiguration
    {
        /// <summary>
        /// Adds Cloud Foundry service binding configurations to the service collection
        /// </summary>
        public static IServiceCollection AddCloudFoundryServices(this IServiceCollection services, IConfiguration configuration)
        {
            // Add Cloud Foundry options
            services.AddOptions();
            services.ConfigureCloudFoundryOptions(configuration);

            // Try to get a logger for better diagnostics
            ILogger? logger = null;
            var serviceProvider = services.BuildServiceProvider();
            try
            {
                var loggerFactory = serviceProvider.GetService<ILoggerFactory>();
                if (loggerFactory != null)
                {
                    logger = loggerFactory.CreateLogger("ServiceBindingConfiguration");
                }
            }
            catch
            {
                // If we can't get a logger, just continue without it
            }

            // Get the raw VCAP_SERVICES environment variable for debugging
            var vcapServicesEnv = Environment.GetEnvironmentVariable("VCAP_SERVICES");
            if (!string.IsNullOrEmpty(vcapServicesEnv))
            {
                LogMessage(logger, "Raw VCAP_SERVICES environment variable:", Console.WriteLine);
                LogMessage(logger, vcapServicesEnv, Console.WriteLine);
            }

            // Configure GenAI service - this will override any existing configuration
            services.PostConfigure<GenAIOptions>(options =>
            {
                // Implement a clear fallback pattern for credentials:
                // 1. Try service binding from VCAP_SERVICES (with special handling for credhub-ref)
                // 2. Fall back to environment variables
                // 3. Fall back to configuration values
                // 4. Error out if no credentials are available

                bool credentialsFound = false;

                // Step 1: Try to get credentials from service binding
                if (TryGetGenAIChatServiceCredentials(configuration, out var credentialsSection, logger))
                {
                    LogMessage(logger, "Found GenAI service binding in VCAP_SERVICES", Console.WriteLine);

                    // Check for credhub-ref
                    var credhubRef = credentialsSection.GetValue<string>("credhub-ref");
                    if (!string.IsNullOrEmpty(credhubRef))
                    {
                        LogMessage(logger, $"Found credhub-ref: {credhubRef}", Console.WriteLine);

                        // Attempt to get credentials from CredHub via the service URL
                        var credhubCredentials = GetCredentialsFromCredhub(credhubRef, logger);
                        if (credhubCredentials != null)
                        {
                            // Extract credentials from the CredHub response
                            options.ApiKey = credhubCredentials.GetValue<string>("api_key") ??
                                            credhubCredentials.GetValue<string>("apiKey") ??
                                            credhubCredentials.GetValue<string>("key") ??
                                            credhubCredentials.GetValue<string>("openai_api_key") ?? "";

                            options.ApiUrl = credhubCredentials.GetValue<string>("api_base") ??
                                            credhubCredentials.GetValue<string>("apiBase") ??
                                            credhubCredentials.GetValue<string>("url") ??
                                            credhubCredentials.GetValue<string>("endpoint") ??
                                            credhubCredentials.GetValue<string>("openai_api_base") ?? "";
                            // Ensure ApiUrl has a trailing slash for correct HttpClient BaseAddress behavior
                            if (!string.IsNullOrEmpty(options.ApiUrl) && !options.ApiUrl.EndsWith("/"))
                            {
                                options.ApiUrl += "/";
                            }

                            options.Model = credhubCredentials.GetValue<string>("model_name") ??
                                           credhubCredentials.GetValue<string>("modelName") ??
                                           credhubCredentials.GetValue<string>("model") ??
                                           credhubCredentials.GetValue<string>("deployment_name") ??
                                           credhubCredentials.GetValue<string>("deploymentName") ?? "";

                            options.ServiceName = "GenAI on Tanzu Platform (CredHub)";

                            // Check if we successfully extracted the credentials
                            credentialsFound = !string.IsNullOrEmpty(options.ApiKey) && !string.IsNullOrEmpty(options.ApiUrl);

                            if (credentialsFound)
                            {
                                LogMessage(logger, "Successfully extracted GenAI credentials from CredHub", Console.WriteLine);
                                LogMessage(logger, $"  API Key: {(string.IsNullOrEmpty(options.ApiKey) ? "Not found" : options.ApiKey.Substring(0, Math.Min(5, options.ApiKey.Length)) + "...")}", Console.WriteLine);
                                LogMessage(logger, $"  API URL: {options.ApiUrl}", Console.WriteLine);
                                LogMessage(logger, $"  Model: {options.Model}", Console.WriteLine);
                            }
                            else
                            {
                                LogMessage(logger, "Found CredHub reference but failed to extract valid credentials. Will try direct service binding credentials.", Console.WriteLine);
                            }
                        }
                        else
                        {
                            LogMessage(logger, "Failed to retrieve credentials from CredHub. Will try direct service binding credentials.", Console.WriteLine);
                        }
                    }

                    // If CredHub didn't provide valid credentials or there was no CredHub reference, try direct credentials from the service binding
                    if (!credentialsFound)
                    {
                        // Map the credentials to the same properties as the Java implementation
                        // Try different possible field names for API key
                        options.ApiKey = credentialsSection.GetValue<string>("api_key") ??
                                        credentialsSection.GetValue<string>("apiKey") ??
                                        credentialsSection.GetValue<string>("key") ??
                                        credentialsSection.GetValue<string>("openai_api_key") ?? "";

                        // Try different possible field names for API URL
                        options.ApiUrl = credentialsSection.GetValue<string>("api_base") ??
                                        credentialsSection.GetValue<string>("apiBase") ??
                                        credentialsSection.GetValue<string>("url") ??
                                        credentialsSection.GetValue<string>("endpoint") ??
                                        credentialsSection.GetValue<string>("openai_api_base") ?? "";
                        // Ensure ApiUrl has a trailing slash for correct HttpClient BaseAddress behavior
                        if (!string.IsNullOrEmpty(options.ApiUrl) && !options.ApiUrl.EndsWith("/"))
                        {
                            options.ApiUrl += "/";
                        }

                        // Try different possible field names for model
                        options.Model = credentialsSection.GetValue<string>("model_name") ??
                                       credentialsSection.GetValue<string>("modelName") ??
                                       credentialsSection.GetValue<string>("model") ??
                                       credentialsSection.GetValue<string>("deployment_name") ??
                                       credentialsSection.GetValue<string>("deploymentName") ?? "";

                        options.ServiceName = "GenAI on Tanzu Platform (chat)";

                        // Check if we successfully extracted the credentials
                        credentialsFound = !string.IsNullOrEmpty(options.ApiKey) && !string.IsNullOrEmpty(options.ApiUrl);

                        if (credentialsFound)
                        {
                            LogMessage(logger, "Successfully extracted GenAI credentials from service binding:", Console.WriteLine);
                            LogMessage(logger, $"  API Key: {(string.IsNullOrEmpty(options.ApiKey) ? "Not found" : options.ApiKey.Substring(0, Math.Min(5, options.ApiKey.Length)) + "...")}", Console.WriteLine);
                            LogMessage(logger, $"  API URL: {options.ApiUrl}", Console.WriteLine);
                            LogMessage(logger, $"  Model: {options.Model}", Console.WriteLine);
                        }
                        else
                        {
                            LogMessage(logger, "Found service binding but failed to extract valid credentials. Will try environment variables.", Console.WriteLine, LogLevel.Warning);
                        }
                    }
                }

                // Step 2: If service binding didn't work, try environment variables
                if (!credentialsFound)
                {
                    // Check for direct environment variables
                    var envApiKey = Environment.GetEnvironmentVariable("GENAI__APIKEY");
                    var envApiUrl = Environment.GetEnvironmentVariable("GENAI__APIURL");
                    var envModel = Environment.GetEnvironmentVariable("GENAI__MODEL");

                    if (!string.IsNullOrEmpty(envApiKey) && !string.IsNullOrEmpty(envApiUrl))
                    {
                        LogMessage(logger, "Using GenAI configuration from environment variables.", Console.WriteLine);
                        options.ApiKey = envApiKey;
                        options.ApiUrl = envApiUrl;
                        // Ensure ApiUrl has a trailing slash for correct HttpClient BaseAddress behavior
                        if (!string.IsNullOrEmpty(options.ApiUrl) && !options.ApiUrl.EndsWith("/"))
                        {
                            options.ApiUrl += "/";
                        }
                        options.Model = envModel ?? configuration["GenAI:Model"] ?? "gpt-4o-mini"; // Default model if not specified
                        options.ServiceName = configuration["GenAI:ServiceName"] ?? "travel-advisor-llm";
                        credentialsFound = true;

                        LogMessage(logger, "Successfully configured GenAI from environment variables:", Console.WriteLine);
                        LogMessage(logger, $"  API Key: {(string.IsNullOrEmpty(options.ApiKey) ? "Not found" : options.ApiKey.Substring(0, Math.Min(5, options.ApiKey.Length)) + "...")}", Console.WriteLine);
                        LogMessage(logger, $"  API URL: {options.ApiUrl}", Console.WriteLine);
                        LogMessage(logger, $"  Model: {options.Model}", Console.WriteLine);
                    }
                }

                // Step 3: If environment variables didn't work, try configuration values
                if (!credentialsFound)
                {
                    // Fallback to configuration
                    var configApiKey = configuration["GenAI:ApiKey"];
                    var configApiUrl = configuration["GenAI:ApiUrl"];

                    if (!string.IsNullOrEmpty(configApiKey) && !string.IsNullOrEmpty(configApiUrl))
                    {
                        LogMessage(logger, "Using GenAI configuration from appsettings.json.", Console.WriteLine);
                        options.ApiKey = configApiKey;
                        options.ApiUrl = configApiUrl;
                        // Ensure ApiUrl has a trailing slash for correct HttpClient BaseAddress behavior
                        if (!string.IsNullOrEmpty(options.ApiUrl) && !options.ApiUrl.EndsWith("/"))
                        {
                            options.ApiUrl += "/";
                        }
                        options.Model = configuration["GenAI:Model"] ?? "gpt-4o-mini"; // Default model if not specified
                        options.ServiceName = configuration["GenAI:ServiceName"] ?? "travel-advisor-llm";
                        credentialsFound = true;

                        LogMessage(logger, "Successfully configured GenAI from appsettings.json:", Console.WriteLine);
                        LogMessage(logger, $"  API Key: {(string.IsNullOrEmpty(options.ApiKey) ? "Not found" : options.ApiKey.Substring(0, Math.Min(5, options.ApiKey.Length)) + "...")}", Console.WriteLine);
                        LogMessage(logger, $"  API URL: {options.ApiUrl}", Console.WriteLine);
                        LogMessage(logger, $"  Model: {options.Model}", Console.WriteLine);
                    }
                }

                // Step 4: If no credentials were found, log a clear error message
                if (!credentialsFound)
                {
                    LogMessage(logger, "ERROR: No valid GenAI credentials found in service binding, environment variables, or configuration.", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, "Please configure GenAI credentials using one of the following methods:", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, "1. Bind a GenAI service instance to this application", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, "2. Set GENAI__APIKEY and GENAI__APIURL environment variables", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, "3. Configure GenAI:ApiKey and GenAI:ApiUrl in appsettings.json", Console.WriteLine, LogLevel.Error);

                    // Add more detailed diagnostic information
                    LogMessage(logger, "Diagnostic information:", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, $"- VCAP_SERVICES environment variable exists: {!string.IsNullOrEmpty(Environment.GetEnvironmentVariable("VCAP_SERVICES"))}", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, $"- GENAI__APIKEY environment variable exists: {!string.IsNullOrEmpty(Environment.GetEnvironmentVariable("GENAI__APIKEY"))}", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, $"- GENAI__APIURL environment variable exists: {!string.IsNullOrEmpty(Environment.GetEnvironmentVariable("GENAI__APIURL"))}", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, $"- GenAI:ApiKey configuration exists: {!string.IsNullOrEmpty(configuration["GenAI:ApiKey"])}", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, $"- GenAI:ApiUrl configuration exists: {!string.IsNullOrEmpty(configuration["GenAI:ApiUrl"])}", Console.WriteLine, LogLevel.Error);
                    LogMessage(logger, $"- USE_MOCK_DATA environment variable: {Environment.GetEnvironmentVariable("USE_MOCK_DATA") ?? "not set"}", Console.WriteLine, LogLevel.Error);

                    // We don't throw an exception here, as the application might still work with mock data
                    // The DependencyInjection.cs will handle the case when credentials are missing
                }
            });

            // Configure Google Maps service (unchanged)
            services.Configure<GoogleMapsOptions>(options =>
            {
                options.ApiKey = configuration["GoogleMaps:ApiKey"] ?? "";
            });

            return services;
        }

        /// <summary>
        /// Try to get GenAI Chat service credentials from bound Cloud Foundry services
        /// Based on the Python implementation pattern but improved for better reliability
        /// </summary>
        private static bool TryGetGenAIChatServiceCredentials(IConfiguration configuration, out IConfigurationSection credentials, ILogger? logger = null)
        {
            // Default credentials section
            credentials = configuration.GetSection("GenAI");

            // Check if running on Cloud Foundry by looking for VCAP_* sections
            var vcapSection = configuration.GetSection("vcap");
            if (!vcapSection.Exists())
            {
                LogMessage(logger, "VCAP section not found in configuration. Not running on Cloud Foundry or VCAP_* environment variables not set.", Console.WriteLine);
                return false;
            }

            // Look for service bindings in VCAP_SERVICES
            var servicesSection = configuration.GetSection("vcap:services");
            if (!servicesSection.Exists())
            {
                LogMessage(logger, "VCAP_SERVICES section not found in configuration.", Console.WriteLine);
                return false;
            }

            // Dump the VCAP_SERVICES structure for debugging
            LogMessage(logger, "VCAP_SERVICES structure:", Console.WriteLine);
            LogMessage(logger, "=======================", Console.WriteLine);
            DumpConfigurationSection(servicesSection, 0, logger);
            LogMessage(logger, "=======================", Console.WriteLine);

            // Direct approach: Try to access the genai service directly based on the structure in the logs
            var genaiSection = servicesSection.GetSection("genai");
            if (genaiSection.Exists())
            {
                LogMessage(logger, "Found 'genai' section in VCAP_SERVICES", Console.WriteLine);

                // Get the first genai service instance
                var genaiInstances = genaiSection.GetChildren().ToList();
                if (genaiInstances.Any())
                {
                    var firstGenaiInstance = genaiInstances.First();
                    var credentialsSection = firstGenaiInstance.GetSection("credentials");

                    if (credentialsSection.Exists())
                    {
                        LogMessage(logger, "Found credentials in first genai service instance", Console.WriteLine);
                        credentials = credentialsSection;
                        return true;
                    }
                }
            }

            // If direct approach failed, try the original approaches

            // First approach: Check each service type for matching criteria
            foreach (var serviceType in servicesSection.GetChildren())
            {
                // Check each service instance within the type
                foreach (var serviceInstance in serviceType.GetChildren())
                {
                    bool hasGenAITag = false;
                    bool hasGenAILabel = false;

                    // Check if service has genai tag
                    var tagsSection = serviceInstance.GetSection("tags");
                    if (tagsSection.Exists())
                    {
                        var tags = tagsSection.GetChildren().Select(tag => tag.Value);
                        hasGenAITag = tags.Any(tag => tag != null &&
                                         tag.Contains("genai", StringComparison.OrdinalIgnoreCase));
                    }

                    // Check if service has label starting with genai
                    var labelSection = serviceInstance.GetSection("label");
                    if (labelSection.Exists())
                    {
                        var label = labelSection.Value;
                        hasGenAILabel = label != null &&
                                      label.StartsWith("genai", StringComparison.OrdinalIgnoreCase);
                    }

                    // If this is a GenAI service, check if it supports chat
                    if (hasGenAITag || hasGenAILabel)
                    {
                        var credentialsSection = serviceInstance.GetSection("credentials");
                        if (credentialsSection.Exists())
                        {
                            // Check if model_capabilities contains "chat"
                            var modelCapabilitiesSection = credentialsSection.GetSection("model_capabilities");
                            if (modelCapabilitiesSection.Exists())
                            {
                                var modelCapabilities = modelCapabilitiesSection.GetChildren().Select(c => c.Value);
                                if (modelCapabilities.Any(cap => cap != null &&
                                                     cap.Equals("chat", StringComparison.OrdinalIgnoreCase)))
                                {
                                    LogMessage(logger, "Found GenAI service with chat capability via tags/label and model_capabilities.", Console.WriteLine);
                                    credentials = credentialsSection;
                                    return true;
                                }
                            }
                            // If model_capabilities doesn't exist or doesn't contain "chat",
                            // we'll still use this service if it has the required credentials
                            else if (credentialsSection.GetSection("api_key").Exists() ||
                                    credentialsSection.GetSection("api_base").Exists())
                            {
                                LogMessage(logger, "Found GenAI service via tags/label with api_key or api_base.", Console.WriteLine);
                                credentials = credentialsSection;
                                return true;
                            }
                        }
                    }
                }
            }

            // Second approach: Try to find by service name
            var serviceNameToFind = configuration["GenAI:ServiceName"] ?? "travel-advisor-llm";
            LogMessage(logger, $"Trying to find service by name: {serviceNameToFind}", Console.WriteLine);

            foreach (var serviceType in servicesSection.GetChildren())
            {
                foreach (var serviceInstance in serviceType.GetChildren())
                {
                    var nameSection = serviceInstance.GetSection("name");
                    if (nameSection.Exists() && nameSection.Value == serviceNameToFind)
                    {
                        var credentialsSection = serviceInstance.GetSection("credentials");
                        if (credentialsSection.Exists())
                        {
                            LogMessage(logger, $"Found service with name {serviceNameToFind}", Console.WriteLine);
                            credentials = credentialsSection;
                            return true;
                        }
                    }
                }
            }

            // Third approach: Try to find by label
            LogMessage(logger, "Trying to find service by label 'genai'", Console.WriteLine);
            foreach (var serviceType in servicesSection.GetChildren())
            {
                foreach (var serviceInstance in serviceType.GetChildren())
                {
                    var labelSection = serviceInstance.GetSection("label");
                    if (labelSection.Exists() && labelSection.Value?.Equals("genai", StringComparison.OrdinalIgnoreCase) == true)
                    {
                        var credentialsSection = serviceInstance.GetSection("credentials");
                        if (credentialsSection.Exists())
                        {
                            LogMessage(logger, "Found service with label 'genai'", Console.WriteLine);
                            credentials = credentialsSection;
                            return true;
                        }
                    }
                }
            }

            // Fourth approach: Last resort - check any service that has credentials with api_key or api_base
            LogMessage(logger, "Trying to find any service with api_key or api_base in credentials", Console.WriteLine);
            foreach (var serviceType in servicesSection.GetChildren())
            {
                foreach (var serviceInstance in serviceType.GetChildren())
                {
                    var credentialsSection = serviceInstance.GetSection("credentials");
                    if (credentialsSection.Exists())
                    {
                        // Check for various possible credential field names
                        bool hasApiKey = credentialsSection.GetSection("api_key").Exists() ||
                                        credentialsSection.GetSection("apiKey").Exists() ||
                                        credentialsSection.GetSection("key").Exists() ||
                                        credentialsSection.GetSection("openai_api_key").Exists();

                        bool hasApiBase = credentialsSection.GetSection("api_base").Exists() ||
                                         credentialsSection.GetSection("apiBase").Exists() ||
                                         credentialsSection.GetSection("url").Exists() ||
                                         credentialsSection.GetSection("endpoint").Exists() ||
                                         credentialsSection.GetSection("openai_api_base").Exists();

                        if (hasApiKey || hasApiBase)
                        {
                            var nameSection = serviceInstance.GetSection("name");
                            string serviceName = nameSection.Exists() ? nameSection.Value ?? "unknown" : "unknown";
                            LogMessage(logger, $"Found service '{serviceName}' with api_key or api_base in credentials", Console.WriteLine);
                            credentials = credentialsSection;
                            return true;
                        }
                    }
                }
            }

            LogMessage(logger, "No suitable GenAI service found in VCAP_SERVICES. Will try environment variables.", Console.WriteLine);
            return false;
        }

        /// <summary>
        /// Attempts to retrieve credentials from CredHub using the credhub-ref
        /// </summary>
        private static IConfigurationSection? GetCredentialsFromCredhub(string credhubRef, ILogger? logger)
        {
            try
            {
                // In a real implementation, this would make an HTTP request to the CredHub API
                // using the credhub-ref as the resource identifier
                // This would typically require authentication with UAA and might involve JWT tokens

                // For now, this is a placeholder that logs the attempt but doesn't actually retrieve credentials
                LogMessage(logger, $"Attempting to retrieve credentials from CredHub using reference: {credhubRef}", Console.WriteLine);
                LogMessage(logger, "This is a stub implementation. In a real system, this would connect to CredHub API.", Console.WriteLine);

                // Return null to indicate failure - this will trigger fallback to other credential sources
                return null;

                // In a real implementation, this would return an IConfigurationSection containing the credentials
                // Similar to how the service binding credentials are structured
            }
            catch (Exception ex)
            {
                LogMessage(logger, $"Error retrieving credentials from CredHub: {ex.Message}", Console.WriteLine, LogLevel.Error);
                return null;
            }
        }

        /// <summary>
        /// Helper method to log a message to both the logger and the console
        /// </summary>
        private static void LogMessage(ILogger? logger, string message, Action<string> consoleWriter, LogLevel level = LogLevel.Information)
        {
            // Log to the logger if available
            if (logger != null)
            {
                logger.Log(level, message);
            }

            // Always write to the console as well for debugging
            consoleWriter(message);
        }

        /// <summary>
        /// Helper method to recursively dump a configuration section for debugging
        /// </summary>
        private static void DumpConfigurationSection(IConfigurationSection section, int indent, ILogger? logger = null)
        {
            string indentStr = new string(' ', indent * 2);

            // If this is a leaf node with a value
            if (section.Value != null)
            {
                // For API keys, only show the first few characters
                string value = section.Key.Contains("api_key", StringComparison.OrdinalIgnoreCase) ||
                              section.Key.Contains("apiKey", StringComparison.OrdinalIgnoreCase) ||
                              section.Key.Contains("key", StringComparison.OrdinalIgnoreCase) ?
                              (section.Value.Length > 5 ? section.Value.Substring(0, 5) + "..." : section.Value) :
                              section.Value;

                LogMessage(logger, $"{indentStr}{section.Key}: {value}", Console.WriteLine);
                return;
            }

            // For non-leaf nodes, print the key and recursively process children
            var children = section.GetChildren().ToList();
            if (children.Any())
            {
                LogMessage(logger, $"{indentStr}{section.Key}:", Console.WriteLine);
                foreach (var child in children)
                {
                    DumpConfigurationSection(child, indent + 1, logger);
                }
            }
            else
            {
                // Empty section
                LogMessage(logger, $"{indentStr}{section.Key}: (empty)", Console.WriteLine);
            }
        }
    }
}
