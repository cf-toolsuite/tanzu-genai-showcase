#!/bin/bash

set -e

echo "Starting build..."

# Clean any previous builds
dotnet clean

# Restore packages
echo "Restoring packages..."
dotnet restore

# Build the solution
echo "Building solution..."
dotnet build

echo "Build completed successfully!"
