#!/bin/bash
set -e

# Build script for java-spring-ai-mcp

cd source-code/java-spring-ai-mcp

# Build with Maven
echo "Building with Maven..."
mvn -B clean package

# Package artifacts
echo "Packaging artifacts..."
mkdir -p ../../build-output
cp api/target/*.jar ../../build-output/ || echo "No API JAR found"
cp client/target/*.jar ../../build-output/ || echo "No client JAR found"
cp server/target/*.jar ../../build-output/ || echo "No server JAR found"

# Create a tarball of all artifacts
cd ../../build-output
tar -czf artifacts.tar.gz *.jar

echo "Build completed successfully!"
