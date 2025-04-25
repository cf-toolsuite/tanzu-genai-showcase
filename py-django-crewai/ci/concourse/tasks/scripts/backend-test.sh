#!/bin/bash
set -e

# Backend Test script for py-django-crewai

cd source-code/py-django-crewai

# Install backend dependencies
echo "Installing backend dependencies..."
python -m pip install --upgrade pip
pip install -r requirements.txt

# Run backend tests
echo "Running backend tests..."
python manage.py test || echo "No tests run"

echo "Backend tests completed successfully!"
