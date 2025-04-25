#!/bin/bash
set -e

# Package script for java-spring-langgraph-mcp-angular

# Create deployment package
echo "Creating deployment package..."
mkdir -p build-output/app

# Copy backend JAR
echo "Copying backend JAR..."
cp backend-build/*.jar build-output/app/app.jar

# Copy frontend build
echo "Copying frontend build..."
mkdir -p build-output/app/public
cp -r frontend-build/dist/* build-output/app/public/

# Copy manifest
echo "Copying manifest..."
cp source-code/java-spring-langgraph-mcp-angular/manifest.yml build-output/app/

# Create tarball
cd build-output
tar -czf artifacts.tar.gz app

echo "Package created successfully!"
