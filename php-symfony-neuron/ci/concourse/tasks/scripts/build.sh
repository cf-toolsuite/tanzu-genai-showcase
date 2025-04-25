#!/bin/bash
set -e

# Build script for php-symfony-neuron

cd source-code/php-symfony-neuron

# Validate composer files
echo "Validating composer.json and composer.lock..."
composer validate --strict

# Install dependencies
echo "Installing dependencies..."
composer install --prefer-dist --no-progress

# Run tests if available
echo "Running tests..."
if [ -d "tests" ]; then
  vendor/bin/phpunit
else
  echo "No tests directory found, skipping tests"
fi

# Create package
echo "Creating package..."
mkdir -p build
composer dump-autoload --optimize --no-dev
mkdir -p ../../build-output
zip -r ../../build-output/php-symfony-neuron.zip . -x ".git/*" "tests/*" ".env*" ".github/*" "build/*"

# Package artifacts
echo "Packaging artifacts..."
cd ../../build-output
tar -czf artifacts.tar.gz php-symfony-neuron.zip

echo "Build completed successfully!"
