namespace TravelAdvisor.Core.Services
{
    /// <summary>
    /// Interface for creating chat prompts
    /// </summary>
    public interface IPromptFactory
    {
        /// <summary>
        /// Creates a chat prompt from a system message template
        /// </summary>
        /// <param name="systemMessage">The system message template</param>
        /// <returns>A ChatMessageContentBuilder object</returns>
        ChatMessageContentBuilder Create(string systemMessage);
    }
}