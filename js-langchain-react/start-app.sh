#!/usr/bin/env bash

# Script to start the News Aggregator application
# This script handles port conflicts by dynamically setting the API base URL
# and ensures the React app is built before starting the server

echo "Starting News Aggregator application..."

# Check if the build directory exists
if [ ! -d "build" ]; then
  echo "Build directory not found. Running npm run build first..."
  npm run build

  if [ $? -ne 0 ]; then
    echo "Build failed. Please check the error messages above."
    exit 1
  fi

  echo "Build completed successfully."
fi

# Function to find an available port starting from a given port
find_available_port() {
  local port=$1
  while true; do
    if ! lsof -i:"$port" > /dev/null 2>&1; then
      echo "$port"
      return 0
    fi
    port=$((port + 1))
    if [ "$port" -gt "$((3001 + 100))" ]; then
      echo "Could not find an available port in range 3001-3101"
      return 1
    fi
  done
}

# Check if there's a process running on port 3001
PORT_CHECK=$(lsof -i:3001 -t 2>/dev/null)

if [ ! -z "$PORT_CHECK" ]; then
  echo "Process found running on port 3001."
  echo "You can kill the process with: kill -9 $PORT_CHECK"

  # Ask user if they want to kill the process or use a different port
  read -p "Do you want to kill the process on port 3001? (y/n): " KILL_PROCESS

  if [[ "$KILL_PROCESS" =~ ^[Yy]$ ]]; then
    echo "Killing process on port 3001..."
    kill -9 $PORT_CHECK
    sleep 1  # Give it a moment to release the port
    echo "Process killed."

    # Use port 3001 since it's now available
    API_PORT=3001
  else
    # Find an available port starting from 3002
    API_PORT=$(find_available_port 3002)
    echo "Using alternative port: $API_PORT"

    # Create or update .env file with the correct API base URL
    if [ -f .env ]; then
      # Update existing .env file
      grep -v "REACT_APP_API_BASE_URL" .env > .env.tmp
      echo "REACT_APP_API_BASE_URL=http://localhost:$API_PORT" >> .env.tmp
      mv .env.tmp .env
    else
      # Create new .env file
      echo "REACT_APP_API_BASE_URL=http://localhost:$API_PORT" > .env
    fi

    # Export the PORT environment variable for the server
    export PORT=$API_PORT

    echo "Set REACT_APP_API_BASE_URL=http://localhost:$API_PORT in .env file"
    echo "Set PORT=$API_PORT for the server"
  fi
else
  # No process on port 3001, use it
  API_PORT=3001
  echo "Port 3001 is available. Using default port."

  # Make sure .env has the correct API base URL
  if [ -f .env ]; then
    # Update existing .env file
    grep -v "REACT_APP_API_BASE_URL" .env > .env.tmp
    echo "REACT_APP_API_BASE_URL=http://localhost:$API_PORT" >> .env.tmp
    mv .env.tmp .env
  else
    # Create new .env file
    echo "REACT_APP_API_BASE_URL=http://localhost:$API_PORT" > .env
  fi

  echo "Set REACT_APP_API_BASE_URL=http://localhost:$API_PORT in .env file"
fi

# Start the application
echo "Starting the application with 'npm run dev'..."
npm run dev
