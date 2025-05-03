#!/bin/bash
set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Print header
echo -e "${YELLOW}Deploying Flight Tracking Chatbot to Tanzu Platform for Cloud Foundry${NC}"
echo "-------------------------------------------------------------------------"

# Check if logged in to CF
CF_API=$(cf target | grep "API endpoint" || echo "")
if [[ -z "$CF_API" ]]; then
  echo -e "${RED}Not logged in to Cloud Foundry.${NC}"
  echo "Please log in using 'cf login' command before running this script."
  exit 1
fi

echo -e "${GREEN}Targeting:${NC}\n$(cf target | grep org -A 1)"
echo

# Deploy application
echo -e "${YELLOW}Deploying application...${NC}"
cf push --no-start

# Set environment variables
cf set-env $APP_NAME AVIATIONSTACK_API_KEY $AVIATIONSTACK_API_KEY

# Start application
echo -e "${YELLOW}Starting application...${NC}"
cf start $APP_NAME

# Display application information
echo -e "${GREEN}Deployment completed successfully!${NC}"
echo -e "${YELLOW}Application details:${NC}"
cf app $APP_NAME

echo
echo -e "${GREEN}Your Flight Tracking Chatbot is now running on Tanzu Platform for Cloud Foundry.${NC}"
