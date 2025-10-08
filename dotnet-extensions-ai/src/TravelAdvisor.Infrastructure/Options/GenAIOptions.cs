using Microsoft.Extensions.AI;

namespace TravelAdvisor.Infrastructure.Options;

/// <summary>
/// Configuration options for GenAI services
/// </summary>
public class GenAIOptions
{
    /// <summary>
    /// API key for the GenAI service
    /// </summary>
    public string ApiKey { get; set; } = string.Empty;

    /// <summary>
    /// API endpoint URL for the GenAI service
    /// </summary>
    public string ApiUrl { get; set; } = string.Empty;

    /// <summary>
    /// Model to use for AI completions
    /// </summary>
    public string Model { get; set; } = string.Empty;

    /// <summary>
    /// Configure the chat options for the AI service
    /// </summary>
    public ChatOptions ChatOptions { get; set; } = new ();

    /// <summary>
    /// Service name in Cloud Foundry
    /// </summary>
    public string ServiceName { get; set; } = "travel-advisor-llm";
}