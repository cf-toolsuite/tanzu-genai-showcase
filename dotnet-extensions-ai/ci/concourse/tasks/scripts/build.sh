#!/bin/bash
set -e

# Build script for dotnet-extensions-ai

cd source-code/dotnet-extensions-ai

# Setup .NET from global.json
echo "Setting up .NET environment..."
dotnet --info

# Restore dependencies
echo "Restoring dependencies..."
dotnet restore

# Build
echo "Building project..."
dotnet build --no-restore

# Test
echo "Running tests..."
dotnet test --no-build --verbosity normal

# Publish
echo "Publishing application..."
dotnet publish -c Release -o ./publish

# Package artifacts
echo "Packaging artifacts..."
mkdir -p ../../build-output
tar -czf ../../build-output/artifacts.tar.gz publish

echo "Build completed successfully!"
