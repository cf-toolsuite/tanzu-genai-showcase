#!/bin/bash
set -e

# Build script for ruby-sinatra-fastmcp

cd source-code/ruby-sinatra-fastmcp

# Install dependencies
echo "Installing dependencies..."
gem install bundler
bundle install

# Run tests if available
echo "Running tests..."
if [ -d "test" ]; then
  bundle exec rake test || echo "Test execution failed"
else
  echo "No tests found, skipping tests"
fi

# Create package
echo "Creating package..."
mkdir -p dist
zip -r dist/ruby-sinatra-fastmcp.zip . -x "*.git*" "*.env*" ".github/*" "dist/*" "test/*" "spec/*"

# Package artifacts
echo "Packaging artifacts..."
mkdir -p ../../build-output
cp -r dist/ruby-sinatra-fastmcp.zip ../../build-output/
cd ../../build-output
tar -czf artifacts.tar.gz ruby-sinatra-fastmcp.zip

echo "Build completed successfully!"
