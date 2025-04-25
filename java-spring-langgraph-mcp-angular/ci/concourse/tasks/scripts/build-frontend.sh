#!/bin/bash
set -e

# Build frontend script for java-spring-langgraph-mcp-angular

cd source-code/java-spring-langgraph-mcp-angular/frontend

# Install dependencies
echo "Installing frontend dependencies..."
npm install

# Build frontend
echo "Building frontend..."
npm run build -- --configuration production

# Copy build to output
echo "Copying frontend artifacts..."
mkdir -p ../../../frontend-build
cp -r dist ../../../frontend-build/

echo "Frontend build completed successfully!"
