#!/bin/bash

# Script to update dependencies for js-langchain-react project
# This script handles the migration from deprecated packages to their modern equivalents

echo "Starting dependency update process..."

# Step 1: Ensure npm is configured to use TLS 1.2 or higher
echo "Configuring npm to use secure TLS..."
npm config set registry https://registry.npmjs.org/

# Step 2: Clean up existing dependencies
echo "Removing existing node_modules and package-lock.json..."
rm -rf node_modules package-lock.json

# Step 3: Install updated dependencies
echo "Installing updated dependencies..."
npm install

# Step 4: Run audit fix to address any remaining issues
echo "Running npm audit fix..."
npm run fix-deps

echo "Dependency update completed!"
echo ""
echo "Next steps:"
echo "1. Test the application thoroughly to ensure everything works as expected"
echo "2. If you use Google Analytics, consider updating to GA4-compatible libraries"
echo "3. If your code directly uses any deprecated polyfills, update to use native browser APIs:"
echo "   - Replace abab with native atob() and btoa()"
echo "   - Replace domexception with native DOMException"
echo "   - Replace w3c-hr-time with native performance.now()"
echo ""
echo "If you encounter any issues, check the application logs and console for errors."
