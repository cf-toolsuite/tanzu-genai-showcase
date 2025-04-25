#!/bin/bash
set -e

# Frontend Build script for py-django-crewai

cd source-code/py-django-crewai

# Install frontend dependencies
echo "Installing frontend dependencies..."
cd frontend
npm install

# Build frontend
echo "Building frontend..."
cd ..
./build_frontend.sh

# Copy frontend build to output
echo "Copying frontend build to output..."
mkdir -p ../frontend-build/frontend
cp -r frontend/dist ../frontend-build/frontend/

# Copy static files directory
echo "Copying static files directory..."
mkdir -p ../frontend-build/static
if [ -d "static" ]; then
  cp -r static ../frontend-build/
fi

echo "Frontend build completed successfully!"
