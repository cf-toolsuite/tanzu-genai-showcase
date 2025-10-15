using Markdig;
using Microsoft.AspNetCore.Components;

namespace TravelAdvisor.Web.Utilities;

/// <summary>
/// Utility class for rendering markdown content to HTML
/// </summary>
public static class MarkdownRenderer
{
    private static readonly MarkdownPipeline Pipeline = new MarkdownPipelineBuilder()
        .UseAdvancedExtensions()
        .UseAutoLinks()
        .Build();

    /// <summary>
    /// Renders markdown content to HTML
    /// </summary>
    /// <param name="markdown">Markdown content</param>
    /// <returns>HTML as MarkupString</returns>
    public static MarkupString RenderMarkdown(string markdown)
    {
        if (string.IsNullOrEmpty(markdown))
            return new MarkupString(string.Empty);

        // Convert markdown to HTML
        string html = Markdown.ToHtml(markdown, Pipeline);

        // Return as MarkupString so Blazor renders it as HTML
        return new MarkupString(html);
    }
}
