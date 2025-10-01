using Microsoft.Extensions.Configuration;

namespace TravelAdvisor.Core.Utilities
{
    /// <summary>
    /// Centralized utility for managing environment variables across the application
    /// </summary>
    public static class EnvironmentVariables
    {
        // Cache for environment variables
        private static readonly Dictionary<string, string> _cache = new Dictionary<string, string>();

        // Configuration instance (can be set by Web or Infrastructure layers)
        private static IConfiguration? _configuration;

        /// <summary>
        /// Initializes the environment variables from a file and configuration
        /// </summary>
        /// <param name="envFilePath">Path to the .env file</param>
        /// <param name="configuration">Optional IConfiguration instance</param>
        public static void Initialize(string envFilePath = ".env", IConfiguration? configuration = null)
        {
            // Clear cache when initializing
            _cache.Clear();

            // Load from .env file if it exists
            if (File.Exists(envFilePath))
            {
                LoadFromFile(envFilePath);
            }

            // Store configuration for later use
            if (configuration != null)
            {
                _configuration = configuration;
            }
        }

        /// <summary>
        /// Gets a value from environment variables with optional fallback to configuration and default value
        /// </summary>
        /// <param name="key">The environment variable key</param>
        /// <param name="defaultValue">Optional default value if not found</param>
        /// <returns>The value of the environment variable or default value if not found</returns>
        public static string? GetString(string key, string? defaultValue = null)
        {
            // Check cache first
            if (_cache.TryGetValue(key, out string? cachedValue))
            {
                return cachedValue;
            }

            // Check environment variable
            string? value = Environment.GetEnvironmentVariable(key);

            // If not found in environment, try configuration (if available)
            if (string.IsNullOrEmpty(value) && _configuration != null)
            {
                // Try using the key directly and also with : as separator
                value = _configuration[key] ?? _configuration[key.Replace("__", ":")] ?? value;
            }

            // If still not found, use default
            if (string.IsNullOrEmpty(value))
            {
                value = defaultValue;
            }

            // Cache the result
            if (value != null)
            {
                _cache[key] = value;
            }

            return value;
        }

        /// <summary>
        /// Gets a boolean value from environment variables
        /// </summary>
        /// <param name="key">The environment variable key</param>
        /// <param name="defaultValue">Default value if not found or not a valid boolean</param>
        /// <returns>The boolean value or default if not found or invalid</returns>
        public static bool GetBool(string key, bool defaultValue = false)
        {
            string? value = GetString(key);

            if (string.IsNullOrEmpty(value))
            {
                return defaultValue;
            }

            if (bool.TryParse(value, out bool result))
            {
                return result;
            }

            // Handle common truthy/falsy strings
            value = value.ToLowerInvariant();
            if (value == "1" || value == "true" || value == "yes" || value == "y" || value == "on")
            {
                return true;
            }
            if (value == "0" || value == "false" || value == "no" || value == "n" || value == "off")
            {
                return false;
            }

            return defaultValue;
        }

        /// <summary>
        /// Gets an integer value from environment variables
        /// </summary>
        /// <param name="key">The environment variable key</param>
        /// <param name="defaultValue">Default value if not found or not a valid integer</param>
        /// <returns>The integer value or default if not found or invalid</returns>
        public static int GetInt(string key, int defaultValue = 0)
        {
            string? value = GetString(key);

            if (string.IsNullOrEmpty(value) || !int.TryParse(value, out int result))
            {
                return defaultValue;
            }

            return result;
        }

        /// <summary>
        /// Loads environment variables from a file
        /// </summary>
        /// <param name="filePath">Path to the environment file</param>
        private static void LoadFromFile(string filePath)
        {
            if (!File.Exists(filePath))
                return;

            foreach (var line in File.ReadAllLines(filePath))
            {
                string trimmedLine = line.Trim();

                // Skip empty lines and comments
                if (string.IsNullOrWhiteSpace(trimmedLine) || trimmedLine.StartsWith("#"))
                    continue;

                // Parse the line as KEY=VALUE
                int separatorIndex = trimmedLine.IndexOf('=');
                if (separatorIndex <= 0)
                    continue;

                string? key = trimmedLine.Substring(0, separatorIndex).Trim();
                string? value = trimmedLine.Substring(separatorIndex + 1).Trim();

                // Remove quotes if present
                if ((value.StartsWith("\"") && value.EndsWith("\"")) ||
                    (value.StartsWith("'") && value.EndsWith("'")))
                {
                    value = value.Substring(1, value.Length - 2);
                }

                Environment.SetEnvironmentVariable(key, value);
                _cache[key] = value;
            }
        }
    }
}
