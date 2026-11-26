#!/bin/bash

echo "Setting up favicon generation..."

# Create static directories if they don't exist
mkdir -p static/images

# Install required packages
pip install -r "$(dirname "$0")/favicon_requirements.txt"

# Generate ICO file from SVG
python generate_favicon.py

# Copy favicon.ico to the root static directory for direct access
cp static/images/favicon.ico ../static/favicon.ico

# Run Django collectstatic to collect all static files
python manage.py collectstatic --noinput

echo "Favicon generation and installation complete!"
echo "You should now have favicon.ico, favicon.svg, and apple-touch-icon.png in your static directories."
