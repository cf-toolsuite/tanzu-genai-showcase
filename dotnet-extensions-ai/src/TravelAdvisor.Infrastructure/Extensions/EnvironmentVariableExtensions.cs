using Microsoft.Extensions.Configuration;

namespace TravelAdvisor.Infrastructure.Extensions
{
    /// <summary>
    /// Extension methods for handling environment variables from .env files
    /// </summary>
    public static class EnvironmentVariableExtensions
    {
        /// <summary>
        /// Adds environment variables with mapping for GenAI and GoogleMaps configuration
        /// to work with both .env files and standard environment variables.
        /// </summary>
        /// <param name="configuration">The configuration builder</param>
        /// <returns>The configuration builder for method chaining</returns>
        public static IConfigurationBuilder AddEnvironmentVariablesWithMapping(this IConfigurationBuilder configuration)
        {
            // Add standard environment variables
            configuration.AddEnvironmentVariables();

            // Add specific prefixes for GenAI and GoogleMaps
            // These map environment variables to configuration settings:
            // GENAI__APIKEY -> GenAI:ApiKey
            // GENAI__APIURL -> GenAI:ApiUrl
            // GENAI__MODEL -> GenAI:Model
            // GOOGLEMAPS__APIKEY -> GoogleMaps:ApiKey
            configuration.AddEnvironmentVariables("GENAI__");
            configuration.AddEnvironmentVariables("GOOGLEMAPS__");

            return configuration;
        }
    }
}
