#!/bin/bash
set -e

# Deploy script for ruby-sinatra-fastmcp

# Extract artifacts
echo "Extracting build artifacts..."
mkdir -p app-to-deploy
tar -xzf build-artifacts/artifacts.tar.gz -C app-to-deploy
cd app-to-deploy
unzip ruby-sinatra-fastmcp.zip -d ruby-sinatra-fastmcp

# Copy manifest and any other required files
echo "Copying manifest file and deployment files..."
cp -f ../source-code/ruby-sinatra-fastmcp/manifest.yml ruby-sinatra-fastmcp/
cp -f ../source-code/ruby-sinatra-fastmcp/Procfile ruby-sinatra-fastmcp/ || echo "Procfile not found"

# Login to Cloud Foundry
echo "Logging in to Cloud Foundry..."
cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

# Determine app name from manifest or project name
APP_NAME=$(grep 'name:' ruby-sinatra-fastmcp/manifest.yml | head -n 1 | awk '{print $2}')
if [ -z "$APP_NAME" ]; then
  APP_NAME="ruby-sinatra-fastmcp" # Fallback to directory name
fi
echo "Deploying application: $APP_NAME"

# Push the application without starting it
echo "Pushing application to Cloud Foundry..."
cf push "$APP_NAME" -f ruby-sinatra-fastmcp/manifest.yml --no-start

# Set environment variables
echo "Setting environment variables..."
cf set-env "$APP_NAME" "RACK_ENV" "production"
cf set-env "$APP_NAME" "OPENAI_API_KEY" "$OPENAI_API_KEY"

# Start the application
echo "Starting application..."
cf start "$APP_NAME"

echo "Deployment completed successfully!"
