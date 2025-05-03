#!/bin/bash
echo "Building frontend..."
cd frontend
npm run build
echo "Frontend build complete."

echo "Build complete. To restart the server, run:"
echo "python manage.py runserver"
