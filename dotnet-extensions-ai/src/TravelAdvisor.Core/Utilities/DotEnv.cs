namespace TravelAdvisor.Core.Utilities;

/// <summary>
/// Simple .env file loader to load environment variables from a .env file
/// </summary>
public static class DotEnv
{
    /// <summary>
    /// Loads environment variables from a .env file
    /// </summary>
    /// <param name="filePath">Path to .env file (default is ".env" in current directory)</param>
    public static void Load(string filePath = ".env")
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

            string key = trimmedLine.Substring(0, separatorIndex).Trim();
            string value = trimmedLine.Substring(separatorIndex + 1).Trim();

            // Remove quotes if present
            if ((value.StartsWith("\"") && value.EndsWith("\"")) ||
                (value.StartsWith("'") && value.EndsWith("'")))
            {
                value = value.Substring(1, value.Length - 2);
            }

            Environment.SetEnvironmentVariable(key, value);
        }
    }
}