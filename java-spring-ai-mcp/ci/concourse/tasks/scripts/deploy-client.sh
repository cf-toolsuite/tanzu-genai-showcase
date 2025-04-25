#!/bin/bash
set -e

# Deploy client script for java-spring-ai-mcp

# Extract artifacts
echo "Extracting build artifacts..."
mkdir -p app-to-deploy
tar -xzf build-artifacts/artifacts.tar.gz -C app-to-deploy

# Copy manifest
echo "Copying manifest file..."
cp source-code/java-spring-ai-mcp/client/manifest.yml app-to-deploy/

# Find the client JAR
echo "Finding client JAR..."
CLIENT_JAR=$(find app-to-deploy -name "*client*.jar" | head -1)
if [ -z "$CLIENT_JAR" ]; then
  echo "Client JAR not found!"
  exit 1
fi

# Rename to app.jar for Cloud Foundry
echo "Preparing application for deployment..."
cp "$CLIENT_JAR" app-to-deploy/app.jar

# Login to Cloud Foundry
echo "Logging in to Cloud Foundry..."
cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

# Push the application without starting it
echo "Pushing application to Cloud Foundry..."
cf push java-spring-ai-mcp-client -f app-to-deploy/manifest.yml -p app-to-deploy --no-start

# Set environment variables
echo "Setting environment variables..."
cf set-env java-spring-ai-mcp-client "SPRING_PROFILES_ACTIVE" "cloud"
cf set-env java-spring-ai-mcp-client "OPENAI_API_KEY" "$OPENAI_API_KEY"

# Start the application
echo "Starting application..."
cf start java-spring-ai-mcp-client

echo "Client deployment completed successfully!"
