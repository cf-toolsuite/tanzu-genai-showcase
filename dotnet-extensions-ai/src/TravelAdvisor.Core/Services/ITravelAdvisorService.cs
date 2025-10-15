using TravelAdvisor.Core.Models;

namespace TravelAdvisor.Core.Services;

/// <summary>
/// Service for generating travel recommendations
/// </summary>
public interface ITravelAdvisorService
{
    /// <summary>
    /// Process a natural language travel query and extract structured data
    /// </summary>
    /// <param name="query">Natural language query from the user</param>
    /// <returns>Structured travel query</returns>
    Task<TravelQuery> ProcessNaturalLanguageQueryAsync(string query);

    /// <summary>
    /// Generate travel recommendations based on a structured query
    /// </summary>
    /// <param name="query">Structured travel query</param>
    /// <returns>List of travel recommendations</returns>
    Task<List<TravelRecommendation>> GenerateRecommendationsAsync(TravelQuery query);

    /// <summary>
    /// Generate a natural language explanation for a recommendation
    /// </summary>
    /// <param name="recommendation">Travel recommendation</param>
    /// <param name="query">Original travel query</param>
    /// <returns>Natural language explanation</returns>
    Task<string> GenerateExplanationAsync(TravelRecommendation recommendation, TravelQuery query);

    /// <summary>
    /// Answer a follow-up question about a recommendation
    /// </summary>
    /// <param name="question">User's follow-up question</param>
    /// <param name="recommendation">The recommendation being asked about</param>
    /// <param name="query">Original travel query</param>
    /// <returns>Natural language answer</returns>
    Task<string> AnswerFollowUpQuestionAsync(string question, TravelRecommendation recommendation, TravelQuery query);
}
