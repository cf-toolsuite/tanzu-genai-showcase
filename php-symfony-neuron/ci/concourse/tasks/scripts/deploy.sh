#!/bin/bash
set -e

# Deploy script for php-symfony-neuron

# Extract artifacts
echo "Extracting build artifacts..."
mkdir -p app-to-deploy
tar -xzf build-artifacts/artifacts.tar.gz -C app-to-deploy
cd app-to-deploy
unzip php-symfony-neuron.zip -d php-symfony-neuron

# Copy manifest and any other required files
echo "Copying manifest file..."
cp -f ../source-code/php-symfony-neuron/manifest.yml php-symfony-neuron/

# Login to Cloud Foundry
echo "Logging in to Cloud Foundry..."
cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

# Determine app name from manifest or project name
APP_NAME=$(grep 'name:' php-symfony-neuron/manifest.yml | head -n 1 | awk '{print $2}')
if [ -z "$APP_NAME" ]; then
  APP_NAME="php-symfony-neuron" # Fallback to directory name
fi
echo "Deploying application: $APP_NAME"

# Push the application without starting it
echo "Pushing application to Cloud Foundry..."
cf push "$APP_NAME" -f php-symfony-neuron/manifest.yml --no-start

# Set environment variables
echo "Setting environment variables..."
cf set-env "$APP_NAME" "APP_ENV" "prod"
cf set-env "$APP_NAME" "APP_SECRET" "$APP_SECRET"

# Start the application
echo "Starting application..."
cf start "$APP_NAME"

echo "Deployment completed successfully!"
