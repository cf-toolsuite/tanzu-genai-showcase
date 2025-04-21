#!/bin/bash
# Script to set up a vendor directory for Cloud Foundry deployment

# Refresh vendor directory
echo "Refreshing vendor directory..."
rm -Rf vendor
mkdir -p vendor

# Install dependencies to vendor directory
echo "Installing dependencies to vendor directory..."
pip download -r requirements.txt --no-binary=:none: -d vendor

echo "Vendor directory setup complete."
