using TravelAdvisor.Infrastructure;
using Steeltoe.Extensions.Configuration.CloudFoundry;
using Steeltoe.Management.Endpoint;
using TravelAdvisor.Core.Utilities;

var builder = WebApplication.CreateBuilder(args);

// Initialize environment variables
try
{
    // Look for .env file in various possible locations
    string[] possiblePaths = new[] {
        ".env",                                  // Current directory
        Path.Combine("src", ".env"),             // src subdirectory
        Path.Combine("src", "TravelAdvisor.Web", ".env"), // Web project directory
        Path.Combine(AppDomain.CurrentDomain.BaseDirectory, ".env") // Application base directory
    };

    string envPath = possiblePaths.FirstOrDefault(File.Exists) ?? ".env";

    Console.WriteLine($"Initializing environment variables from {envPath}");
    Console.WriteLine($"Current directory: {Directory.GetCurrentDirectory()}");
    EnvironmentVariables.Initialize(envPath, builder.Configuration);

    // Log loaded environment variables for debugging
    Console.WriteLine("Loaded environment variables:");
    Console.WriteLine($"GENAI__APIKEY: {(string.IsNullOrEmpty(Environment.GetEnvironmentVariable("GENAI__APIKEY")) ? "Not set" : "Set (value hidden)")}");
    Console.WriteLine($"GENAI__APIURL: {Environment.GetEnvironmentVariable("GENAI__APIURL")}");
    Console.WriteLine($"GENAI__MODEL: {Environment.GetEnvironmentVariable("GENAI__MODEL")}");
}
catch (Exception ex)
{
    Console.WriteLine($"Failed to initialize environment variables: {ex.Message}");
}

// Add environment variables configuration
// ASP.NET Core automatically maps GENAI__APIKEY to GenAI:ApiKey
builder.Configuration.AddEnvironmentVariables();

// Add Cloud Foundry configuration provider
builder.Configuration.AddCloudFoundry();

// Add services to the container.
builder.Services.AddRazorPages();
builder.Services.AddServerSideBlazor();

// Add actuators for health monitoring
// Configure OpenTelemetry for Steeltoe Management
builder.AddAllActuators();

// Add infrastructure services (including AI and Google Maps services)
builder.Services.AddInfrastructureServices(builder.Configuration);

// Add HttpClient
builder.Services.AddHttpClient();

var app = builder.Build();

// Configure the HTTP request pipeline.
if (!app.Environment.IsDevelopment())
{
    app.UseExceptionHandler("/Error");
    app.UseHsts();
}

app.UseHttpsRedirection();
app.UseStaticFiles();
app.UseRouting();

// Use top-level route registrations (addressing the warning)
app.MapRazorPages();
app.MapBlazorHub();
app.MapFallbackToPage("/_Host");

app.Run();
