#!/bin/bash
# Cloud Foundry Deployment Script for News Aggregator

# Exit on any error
set -e

# Color codes for terminal output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Starting deployment process...${NC}"

# Check for required environment variables
if [ -z "$NEWS_API_KEY" ]; then
  echo -e "${RED}ERROR: NEWS_API_KEY environment variable is not set${NC}"
  echo "Please set it using: export NEWS_API_KEY=your_api_key"
  exit 1
fi

# 1. Generate manifest.yml from template
echo -e "${GREEN}Generating manifest.yml from template...${NC}"
envsubst '${NEWS_API_KEY}' < manifest.template > manifest.yml
echo "manifest.yml generated successfully"

# 2. Check if service instance exists
echo -e "${GREEN}Checking for GenAI service instance...${NC}"
SERVICE_EXISTS=$(cf services | grep news-aggregator-llm || echo "")

if [ -z "$SERVICE_EXISTS" ]; then
  echo -e "${YELLOW}GenAI service instance not found. Creating it...${NC}"

  # Get available service plans
  echo "Available GenAI service plans:"
  cf marketplace -e genai

  # Prompt for service plan
  read -p "Enter the GenAI service plan name: " PLAN_NAME

  # Create service instance
  cf create-service genai $PLAN_NAME news-aggregator-llm
  echo "Service instance created"
else
  echo "Service instance already exists"
fi

# 3. Push application without starting it
echo -e "${GREEN}Pushing application to Cloud Foundry...${NC}"
cf push --no-start

# 4. Bind service if not already bound
BINDING_EXISTS=$(cf services | grep news-aggregator-llm | grep "news-aggregator " || echo "")
if [ -z "$BINDING_EXISTS" ]; then
  echo -e "${GREEN}Binding GenAI service to application...${NC}"
  cf bind-service news-aggregator news-aggregator-llm
else
  echo "Service already bound to application"
fi

# 5. Start the application
echo -e "${GREEN}Starting the application...${NC}"
cf start news-aggregator

# 6. Display application URL
echo -e "${GREEN}Deployment completed successfully!${NC}"
APP_URL=$(cf app news-aggregator | grep routes | awk '{print $2}')
echo -e "Your application is now available at: ${YELLOW}https://$APP_URL${NC}"

# 7. Tail logs for debugging (optional)
read -p "Do you want to view the application logs? (y/n): " VIEW_LOGS
if [[ $VIEW_LOGS == "y" || $VIEW_LOGS == "Y" ]]; then
  cf logs news-aggregator --recent
fi

echo -e "${GREEN}Deployment process completed${NC}"
