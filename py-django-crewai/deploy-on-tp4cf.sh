#!/bin/bash
# Script to prepare and deploy the application to Cloud Foundry with vendor dependencies

# Exit on any error
set -e

echo "=== Preparing application for Cloud Foundry deployment ==="

# Activate virtual environment if it exists
if [ -d "venv" ]; then
    echo "Activating virtual environment..."
    source venv/bin/activate
fi

# Step 1: Set up vendor directory
echo "Setting up vendor directory..."
bash setup-vendor.sh

# Step 2: Prepare static files
echo "Collecting static files..."
python manage.py collectstatic --noinput

# Step 3: Deploy to Cloud Foundry
echo "Pushing to Cloud Foundry..."
cf push --no-start

echo "=== Deployment staged ==="
