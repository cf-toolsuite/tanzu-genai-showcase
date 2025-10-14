using Steeltoe.Configuration.CloudFoundry;
using Steeltoe.Management.Endpoint.Actuators.All;
using TravelAdvisor.Infrastructure;

var builder = WebApplication.CreateBuilder(args);

// Add Cloud Foundry configuration provider
builder.AddCloudFoundryConfiguration();

// Add services to the container.
builder.Services.AddRazorPages();
builder.Services.AddServerSideBlazor();

// Add actuators for health monitoring
// Configure OpenTelemetry for Steeltoe Management
builder.Services.AddAllActuators();

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
