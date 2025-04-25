#!/bin/bash
set -e

# Deploy script for js-langchain-react

# Extract artifacts
echo "Extracting build artifacts..."
mkdir -p app-to-deploy
tar -xzf build-artifacts/artifacts.tar.gz -C app-to-deploy

# Copy manifest and any other required files
echo "Copying manifest file and server files..."
cp source-code/js-langchain-react/manifest.yml app-to-deploy/
cp source-code/js-langchain-react/server.js app-to-deploy/
cp source-code/js-langchain-react/package.json app-to-deploy/

# Login to Cloud Foundry
echo "Logging in to Cloud Foundry..."
cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

# Determine app name from manifest or project name
APP_NAME=$(grep 'name:' app-to-deploy/manifest.yml | head -n 1 | awk '{print $2}')
if [ -z "$APP_NAME" ]; then
  APP_NAME="js-langchain-react" # Fallback to directory name
fi
echo "Deploying application: $APP_NAME"

# Push the application without starting it
echo "Pushing application to Cloud Foundry..."
cf push "$APP_NAME" -f app-to-deploy/manifest.yml --no-start

# Set environment variables
echo "Setting environment variables..."
if [ -n "$OPENAI_API_KEY" ]; then
  cf set-env "$APP_NAME" "OPENAI_API_KEY" "$OPENAI_API_KEY"
fi

# Start the application
echo "Starting application..."
cf start "$APP_NAME"

echo "Deployment completed successfully!"
