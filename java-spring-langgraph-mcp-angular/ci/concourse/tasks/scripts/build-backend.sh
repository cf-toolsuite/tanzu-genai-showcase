#!/bin/bash
set -e

# Build backend script for java-spring-langgraph-mcp-angular

cd source-code/java-spring-langgraph-mcp-angular

# Build backend with Maven
echo "Building backend with Maven..."
mvn -B clean package -f backend/pom.xml

# Copy JAR to output
echo "Copying backend artifacts..."
mkdir -p ../../backend-build
cp backend/target/*.jar ../../backend-build/

echo "Backend build completed successfully!"
