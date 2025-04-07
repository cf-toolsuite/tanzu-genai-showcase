using Microsoft.AspNetCore.Builder;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Hosting;
using Microsoft.Extensions.Configuration;
using Microsoft.Extensions.AI;
using Microsoft.SemanticKernel;
using Microsoft.SemanticKernel.ChatCompletion;
using TravelAdvisor.Infrastructure;
using TravelAdvisor.Infrastructure.Options;
using Steeltoe.Extensions.Configuration.CloudFoundry;
using Steeltoe.Management.Endpoint;
using System;
using System.IO;
using TravelAdvisor.Core.Services;
using TravelAdvisor.Core.Utilities;

var builder = WebApplication.CreateBuilder(args);

// Set the port explicitly to avoid conflicts with default port (5000)
// Use environment variable PORT if provided, otherwise use default port
string port = Environment.GetEnvironmentVariable("PORT") ?? "5000";
builder.WebHost.UseUrls($"http://localhost:{port}");

// Initialize environment variables
try
{
    // Look for .env file in the current directory or the src directory
    string envPath = ".env";
    if (!File.Exists(envPath))
    {
        envPath = Path.Combine("src", ".env");
    }

    Console.WriteLine($"Initializing environment variables from {envPath}");
    EnvironmentVariables.Initialize(envPath, builder.Configuration);
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
builder.Services.AddAllActuators(builder.Configuration);

// Temporarily comment out CloudFoundry actuator due to compatibility issues
// builder.Services.AddCloudFoundryActuator();

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

// Map Steeltoe actuator endpoints
app.MapAllActuators();

app.Run();