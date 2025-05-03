#!/usr/bin/env bash

# Script to create and bind user-defined services for Cloud Foundry deployment
# This script demonstrates how to create and bind user-defined services for
# both LLM configuration and other required configuration.

# Exit on any error
set -e

# Default service names
LLM_SERVICE_NAME="movie-chatbot-llm"
CONFIG_SERVICE_NAME="movie-chatbot-config"
APP_NAME="movie-chatbot"

# Display help
show_help() {
  echo "Usage: $0 [options]"
  echo ""
  echo "Options:"
  echo "  -h, --help                 Show this help message"
  echo "  -a, --app NAME             Application name (default: $APP_NAME)"
  echo "  -l, --llm-service NAME     LLM service name (default: $LLM_SERVICE_NAME)"
  echo "  -c, --config-service NAME  Config service name (default: $CONFIG_SERVICE_NAME)"
  echo "  --llm-key KEY              LLM API key"
  echo "  --llm-url URL              LLM base URL (default: https://api.openai.com/v1)"
  echo "  --llm-model MODEL          LLM model name (default: gpt-4o-mini)"
  echo "  --llm-provider PROVIDER    LLM provider (default: openai)"
  echo "  --django-key KEY           Django secret key"
  echo "  --tmdb-key KEY             TMDB API key"
  echo "  --serpapi-key KEY          SerpAPI key (optional)"
  echo ""
  echo "Example:"
  echo "  $0 --llm-key sk-abc123 --django-key mysecret --tmdb-key abc123"
  echo ""
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
  key="$1"
  case $key in
    -h|--help)
      show_help
      exit 0
      ;;
    -a|--app)
      APP_NAME="$2"
      shift
      shift
      ;;
    -l|--llm-service)
      LLM_SERVICE_NAME="$2"
      shift
      shift
      ;;
    -c|--config-service)
      CONFIG_SERVICE_NAME="$2"
      shift
      shift
      ;;
    --llm-key)
      LLM_API_KEY="$2"
      shift
      shift
      ;;
    --llm-url)
      LLM_BASE_URL="$2"
      shift
      shift
      ;;
    --llm-model)
      LLM_MODEL="$2"
      shift
      shift
      ;;
    --llm-provider)
      LLM_PROVIDER="$2"
      shift
      shift
      ;;
    --django-key)
      DJANGO_SECRET_KEY="$2"
      shift
      shift
      ;;
    --tmdb-key)
      TMDB_API_KEY="$2"
      shift
      shift
      ;;
    --serpapi-key)
      SERPAPI_API_KEY="$2"
      shift
      shift
      ;;
    *)
      echo "Unknown option: $1"
      show_help
      exit 1
      ;;
  esac
done

# Set default values if not provided
LLM_BASE_URL=${LLM_BASE_URL:-"https://api.openai.com/v1"}
LLM_MODEL=${LLM_MODEL:-"gpt-4o-mini"}
LLM_PROVIDER=${LLM_PROVIDER:-"openai"}

# Check for required parameters
if [ -z "$LLM_API_KEY" ] || [ -z "$DJANGO_SECRET_KEY" ] || [ -z "$TMDB_API_KEY" ]; then
  echo "Error: Missing required parameters"
  echo "LLM API key, Django secret key, and TMDB API key are required"
  show_help
  exit 1
fi

# Check if cf CLI is installed
if ! command -v cf &> /dev/null; then
  echo "Error: Cloud Foundry CLI (cf) is not installed"
  echo "Please install the Cloud Foundry CLI and try again"
  exit 1
fi

# Check if logged in to Cloud Foundry
if ! cf target &> /dev/null; then
  echo "Error: Not logged in to Cloud Foundry"
  echo "Please log in using 'cf login' and try again"
  exit 1
fi

echo "=== Creating Cloud Foundry Services ==="
echo "Application: $APP_NAME"
echo "LLM Service: $LLM_SERVICE_NAME"
echo "Config Service: $CONFIG_SERVICE_NAME"
echo ""

# Create LLM service
echo "Creating LLM service..."
cf create-user-provided-service "$LLM_SERVICE_NAME" -p "{\"api_key\":\"$LLM_API_KEY\",\"api_base\":\"$LLM_BASE_URL\",\"model_name\":\"$LLM_MODEL\",\"model_provider\":\"$LLM_PROVIDER\"}"

# Create config service
echo "Creating config service..."
CONFIG_JSON="{\"DJANGO_SECRET_KEY\":\"$DJANGO_SECRET_KEY\",\"TMDB_API_KEY\":\"$TMDB_API_KEY\""
if [ -n "$SERPAPI_API_KEY" ]; then
  CONFIG_JSON="$CONFIG_JSON,\"SERPAPI_API_KEY\":\"$SERPAPI_API_KEY\""
fi
CONFIG_JSON="$CONFIG_JSON}"
cf create-user-provided-service "$CONFIG_SERVICE_NAME" -p "$CONFIG_JSON"

# Bind services to application
echo "Binding services to application..."
cf bind-service "$APP_NAME" "$LLM_SERVICE_NAME" || echo "Warning: Could not bind LLM service. Is the application deployed?"
cf bind-service "$APP_NAME" "$CONFIG_SERVICE_NAME" || echo "Warning: Could not bind config service. Is the application deployed?"

echo ""
echo "=== Services Created Successfully ==="
echo "To start or restage your application, run:"
echo "  cf start $APP_NAME"
echo "  or"
echo "  cf restage $APP_NAME"
echo ""
echo "To verify service bindings, run:"
echo "  cf services"
echo "  cf env $APP_NAME"
echo ""
