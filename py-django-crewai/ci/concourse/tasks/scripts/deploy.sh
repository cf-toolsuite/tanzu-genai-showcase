#!/bin/bash
set -e

# Deploy script for py-django-crewai

# Extract artifacts
echo "Extracting build artifacts..."
mkdir -p app-to-deploy
tar -xzf build-artifacts/artifacts.tar.gz -C app-to-deploy
cd app-to-deploy
unzip py-django-crewai.zip -d py-django-crewai

# Copy manifest and any other required files
echo "Copying manifest file and deployment files..."
cp -f ../source-code/py-django-crewai/manifest.yml py-django-crewai/
cp -f ../source-code/py-django-crewai/Procfile py-django-crewai/ || echo "Procfile not found"
cp -f ../source-code/py-django-crewai/runtime.txt py-django-crewai/ || echo "runtime.txt not found"

# Login to Cloud Foundry
echo "Logging in to Cloud Foundry..."
cf api "$CF_API"
cf auth "$CF_USERNAME" "$CF_PASSWORD"
cf target -o "$CF_ORG" -s "$CF_SPACE"

# Determine app name from manifest or project name
APP_NAME=$(grep 'name:' py-django-crewai/manifest.yml | head -n 1 | awk '{print $2}')
if [ -z "$APP_NAME" ]; then
  APP_NAME="py-django-crewai" # Fallback to directory name
fi
echo "Deploying application: $APP_NAME"

# Push the application without starting it
echo "Pushing application to Cloud Foundry..."
cf push "$APP_NAME" -f py-django-crewai/manifest.yml --no-start

# Set environment variables
echo "Setting environment variables..."
cf set-env "$APP_NAME" "DJANGO_SETTINGS_MODULE" "$DJANGO_SETTINGS_MODULE"
cf set-env "$APP_NAME" "OPENAI_API_KEY" "$OPENAI_API_KEY"
cf set-env "$APP_NAME" "DJANGO_SECRET_KEY" "$DJANGO_SECRET_KEY"

# Start the application
echo "Starting application..."
cf start "$APP_NAME"

echo "Deployment completed successfully!"
