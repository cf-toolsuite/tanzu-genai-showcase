namespace TravelAdvisor.Core.Utilities
{
    /// <summary>
    /// Utility class for loading environment variables from .env files
    /// </summary>
    public static class EnvFileLoader
    {
        /// <summary>
        /// Loads environment variables from a .env file at the specified path
        /// </summary>
        /// <param name="path">Path to the .env file. If not specified, defaults to ".env" in the current directory</param>
        public static void Load(string path = ".env")
        {
            if (!File.Exists(path))
            {
                Console.WriteLine($"No .env file found at {path}");
                return;
            }

            Console.WriteLine($"Loading environment variables from {path}");

            foreach (var line in File.ReadAllLines(path))
            {
                string trimmedLine = line.Trim();

                // Skip empty lines and comments
                if (string.IsNullOrWhiteSpace(trimmedLine) || trimmedLine.StartsWith("#"))
                    continue;

                // Parse the line as KEY=VALUE
                var parts = trimmedLine.Split('=', 2);
                if (parts.Length != 2)
                    continue;

                var key = parts[0].Trim();
                var value = parts[1].Trim();

                // Remove quotes if present
                if ((value.StartsWith("\"") && value.EndsWith("\"")) ||
                    (value.StartsWith("'") && value.EndsWith("'")))
                {
                    value = value.Substring(1, value.Length - 2);
                }

                Environment.SetEnvironmentVariable(key, value);
                Console.WriteLine($"Set environment variable: {key}");
            }
        }
    }
}
