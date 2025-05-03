# Ruby Installation Script for Windows
# This script installs Ruby using RubyInstaller for Windows

# Default Ruby version
$DEFAULT_RUBY_VERSION = "3.3.6"
$RUBY_VERSION = if ($args[0]) { $args[0] } else { $DEFAULT_RUBY_VERSION }

# Colors for output
function Write-ColorOutput {
    param (
        [Parameter(Mandatory=$true)]
        [string]$Message,

        [Parameter(Mandatory=$false)]
        [string]$ForegroundColor = "White"
    )

    $originalColor = $host.UI.RawUI.ForegroundColor
    $host.UI.RawUI.ForegroundColor = $ForegroundColor
    Write-Output $Message
    $host.UI.RawUI.ForegroundColor = $originalColor
}

Write-ColorOutput "=== Ruby Installation Script for Windows ===" "Cyan"
Write-ColorOutput "This script will install Ruby $RUBY_VERSION using RubyInstaller" "Cyan"
Write-ColorOutput "================================================" "Cyan"

# Function to check if command exists
function Test-CommandExists {
    param (
        [Parameter(Mandatory=$true)]
        [string]$Command
    )

    $exists = $null -ne (Get-Command $Command -ErrorAction SilentlyContinue)
    return $exists
}

# Function to download a file
function Download-File {
    param (
        [Parameter(Mandatory=$true)]
        [string]$Url,

        [Parameter(Mandatory=$true)]
        [string]$OutputPath
    )

    Write-ColorOutput "Downloading from $Url to $OutputPath..." "Yellow"

    try {
        $webClient = New-Object System.Net.WebClient
        $webClient.DownloadFile($Url, $OutputPath)
        Write-ColorOutput "Download completed successfully!" "Green"
        return $true
    }
    catch {
        Write-ColorOutput "Failed to download file: $_" "Red"
        return $false
    }
}

# Check if Ruby is already installed
if (Test-CommandExists "ruby") {
    $installedVersion = (ruby -v)
    Write-ColorOutput "Ruby is already installed: $installedVersion" "Green"

    # Check if it's the correct version
    if ($installedVersion -match $RUBY_VERSION) {
        Write-ColorOutput "The installed version matches the requested version ($RUBY_VERSION)." "Green"
    }
    else {
        Write-ColorOutput "The installed version does not match the requested version ($RUBY_VERSION)." "Yellow"
        $installNewVersion = Read-Host "Do you want to install Ruby $RUBY_VERSION? (y/n)"

        if ($installNewVersion -ne "y") {
            Write-ColorOutput "Installation cancelled. Using existing Ruby installation." "Yellow"
            $RUBY_VERSION = ($installedVersion -split " ")[1]
        }
        else {
            # Continue with installation
            Write-ColorOutput "Proceeding with installation of Ruby $RUBY_VERSION..." "Yellow"
        }
    }
}
else {
    Write-ColorOutput "Ruby is not installed. Proceeding with installation..." "Yellow"
}

# If we need to install Ruby
if (-not (Test-CommandExists "ruby") -or ($installedVersion -notmatch $RUBY_VERSION -and $installNewVersion -eq "y")) {
    # Determine architecture
    $arch = if ([Environment]::Is64BitOperatingSystem) { "x64" } else { "x86" }

    # Format version for download URL (remove patch version if zero)
    $versionForUrl = $RUBY_VERSION
    if ($RUBY_VERSION -match '(\d+\.\d+)\.0$') {
        $versionForUrl = $matches[1]
    }

    # Construct download URL
    $rubyInstallerUrl = "https://github.com/oneclick/rubyinstaller2/releases/download/RubyInstaller-$RUBY_VERSION-1/rubyinstaller-$RUBY_VERSION-1-$arch.exe"

    # Download location
    $downloadPath = "$env:TEMP\rubyinstaller-$RUBY_VERSION-$arch.exe"

    # Download the installer
    $downloadSuccess = Download-File -Url $rubyInstallerUrl -OutputPath $downloadPath

    if (-not $downloadSuccess) {
        Write-ColorOutput "Failed to download Ruby installer. Please check the URL and try again." "Red"
        Write-ColorOutput "URL: $rubyInstallerUrl" "Red"
        Write-ColorOutput "You can manually download and install Ruby from https://rubyinstaller.org/downloads/" "Yellow"
        exit 1
    }

    # Run the installer
    Write-ColorOutput "Running Ruby installer..." "Yellow"
    Write-ColorOutput "Please follow the installation wizard. Make sure to:" "Yellow"
    Write-ColorOutput "1. Check 'Add Ruby executables to your PATH'" "Yellow"
    Write-ColorOutput "2. Check 'Associate .rb and .rbw files with this Ruby installation'" "Yellow"
    Write-ColorOutput "3. Select to install MSYS2 when prompted (option 1 - MSYS2 base installation)" "Yellow"

    Start-Process -FilePath $downloadPath -ArgumentList "/silent", "/tasks=modpath,assocfiles" -Wait

    # Verify installation
    if (Test-CommandExists "ruby") {
        $newVersion = (ruby -v)
        Write-ColorOutput "Ruby installed successfully: $newVersion" "Green"
    }
    else {
        Write-ColorOutput "Ruby installation may have failed. Please check the installation manually." "Red"
        Write-ColorOutput "After installation, restart your PowerShell session and try running 'ruby -v'" "Yellow"
        exit 1
    }

    # Run MSYS2 setup if needed
    Write-ColorOutput "Setting up MSYS2 for native gem support..." "Yellow"
    Start-Process -FilePath "ridk" -ArgumentList "install", "1", "3" -Wait
}

# Install Bundler
Write-ColorOutput "Installing Bundler..." "Yellow"
Start-Process -FilePath "gem" -ArgumentList "install", "bundler" -Wait -NoNewWindow

# Verify Bundler installation
if (Test-CommandExists "bundle") {
    $bundlerVersion = (bundle -v)
    Write-ColorOutput "Bundler installed successfully: $bundlerVersion" "Green"
}
else {
    Write-ColorOutput "Bundler installation may have failed. Please check manually." "Red"
    Write-ColorOutput "Try running 'gem install bundler' manually after restarting your PowerShell session." "Yellow"
}

# Project setup instructions
Write-ColorOutput "`n=== Project Setup ===" "Cyan"
Write-ColorOutput "To set up the Flight Tracking Chatbot project:" "Yellow"
Write-ColorOutput "1. Navigate to the project directory:" "White"
Write-ColorOutput "   cd path\to\ruby-sinatra-fastmcp" "Green"
Write-ColorOutput "2. Install project dependencies:" "White"
Write-ColorOutput "   bundle config set --local path 'vendor/bundle'" "Green"
Write-ColorOutput "   bundle install" "Green"
Write-ColorOutput "3. Create a .env file with your AviationStack API key:" "White"
Write-ColorOutput "   Copy-Item .env.example .env" "Green"
Write-ColorOutput "   Then edit the .env file to add your API key" "White"
Write-ColorOutput "4. Start the development server:" "White"
Write-ColorOutput "   bundle exec rackup -p 4567 config.ru" "Green"

Write-ColorOutput "`n=== Installation Complete ===" "Cyan"
Write-ColorOutput "Ruby $RUBY_VERSION and Bundler have been successfully installed!" "Green"
Write-ColorOutput "Note: You may need to restart your PowerShell session for all changes to take effect." "Yellow"
