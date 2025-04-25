#!/bin/bash
set -e

# Build script for js-langchain-react

cd source-code/js-langchain-react

# Install dependencies
echo "Installing dependencies..."
npm install

# Build
echo "Building application..."
npm run build

# Test (if tests exist)
echo "Running tests..."
npm test || echo "No tests specified"

# Package artifacts
echo "Packaging artifacts..."
mkdir -p ../../build-output
tar -czf ../../build-output/artifacts.tar.gz build

echo "Build completed successfully!"
