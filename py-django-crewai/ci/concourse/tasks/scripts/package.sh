#!/bin/bash
set -e

# Package script for py-django-crewai

cd source-code/py-django-crewai

# Copy frontend build
if [ -d "../../frontend-build/frontend" ]; then
  echo "Copying frontend build..."
  cp -r ../../frontend-build/frontend/dist frontend/
fi

# Copy static files
if [ -d "../../frontend-build/static" ]; then
  echo "Copying static files..."
  cp -r ../../frontend-build/static .
fi

# Collect static files
echo "Collecting static files..."
python -m pip install --upgrade pip
pip install -r requirements.txt
python manage.py collectstatic --noinput || echo "Static collection skipped"

# Create package
echo "Creating package..."
mkdir -p ../../build-output
zip -r ../../build-output/py-django-crewai.zip . -x "*.git*" "*.env*" "*.pyc" "__pycache__/*" "*.github/*" "frontend/node_modules/*"

# Package artifacts
cd ../../build-output
tar -czf artifacts.tar.gz py-django-crewai.zip

echo "Package created successfully!"
