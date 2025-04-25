#!/bin/bash
set -e

# Deploy server script for java-spring-ai-mcp

# Extract artifacts
echo "Extracting build artifacts..."
mkdir -p app-to-deploy
tar -xzf build-artifacts/artifacts.tar.gz -C app-to-deploy

# Copy manifest
echo "Copying manifest file..."
cp source-code/java-spring-ai-mcp/server/manifest.yml app-to-deploy/

# Find the server JAR
echo "Finding server JAR..."
SERVER_JAR=$(find app-to-deploy -name "*server*.jar" | head -1)
if [ -z "$SERVER_JAR" ]; then
  echo "Server JAR not found!"
  exit 1
fi

# Rename to app.jar for Cloud Foundry
echo "Preparing application for deployment..."
cp "$SERVER_JAR" app-to-deploy/app.jar

# Login to Cloud Foundry
echo "Logging in to Cloud Foundry..."
cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

# Push the application without starting it
echo "Pushing application to Cloud Foundry..."
cf push java-spring-ai-mcp-server -f app-to-deploy/manifest.yml -p app-to-deploy --no-start

# Set environment variables
echo "Setting environment variables..."
cf set-env java-spring-ai-mcp-server "SPRING_PROFILES_ACTIVE" "cloud"
cf set-env java-spring-ai-mcp-server "OPENAI_API_KEY" "$OPENAI_API_KEY"

# Start the application
echo "Starting application..."
cf start java-spring-ai-mcp-server

echo "Server deployment completed successfully!"
