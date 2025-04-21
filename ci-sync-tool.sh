#!/bin/bash
# CI Sync Tool - A utility to help synchronize CI configurations across platforms
# Usage: ./ci-sync-tool.sh [command]
#   Commands:
#     check - Check for GitHub Actions workflow changes and suggest CI updates
#     sync  - Apply GitHub Actions workflow changes to corresponding CI configurations
#     list  - List all CI configurations

set -e

# Define colors for terminal output
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Configuration
REPO_ROOT=$(pwd)
GITHUB_WORKFLOWS_DIR=".github/workflows"
PROJECTS=(
  "dotnet-extensions-ai"
  "go-fiber-langchaingo"
  "java-spring-ai-mcp"
  "java-spring-langgraph-mcp-angular"
  "js-langchain-react"
  "php-symfony-neuron"
  "py-django-crewai"
  "ruby-sinatra-fastmcp"
)
CI_PLATFORMS=("gitlab" "bitbucket" "jenkins")

# Function to check if a string exists in an array
contains() {
    local n=$#
    local value=${!n}
    for ((i=1;i < $#;i++)) {
        if [ "${!i}" == "${value}" ]; then
            return 0
        fi
    }
    return 1
}

# Function to display help
show_help() {
  echo -e "${GREEN}CI Sync Tool${NC} - Synchronize CI configurations across platforms"
  echo ""
  echo "Usage: ./CI_SYNC_TOOL.sh [command]"
  echo ""
  echo "Commands:"
  echo "  check    - Check for GitHub Actions workflow changes and suggest CI updates"
  echo "  sync     - Apply GitHub Actions workflow changes to corresponding CI configurations"
  echo "  list     - List all CI configurations"
  echo "  help     - Display this help message"
  echo ""
  echo "Examples:"
  echo "  ./CI_SYNC_TOOL.sh check               # Check all projects for changes"
  echo "  ./CI_SYNC_TOOL.sh check java-spring-ai-mcp  # Check specific project"
  echo ""
}

# Function to list all CI configurations
list_configurations() {
  echo -e "${GREEN}Listing all CI configurations:${NC}"
  echo ""

  echo -e "${YELLOW}GitHub Actions Workflows:${NC}"
  find "$GITHUB_WORKFLOWS_DIR" -name "*.yml" | sort
  echo ""

  for platform in "${CI_PLATFORMS[@]}"; do
    echo -e "${YELLOW}${platform^} Configurations:${NC}"
    case "$platform" in
      gitlab)
        find . -path "*/ci/gitlab/*.yml" | sort
        ;;
      bitbucket)
        find . -path "*/ci/bitbucket/*.yml" | sort
        ;;
      jenkins)
        find . -path "*/ci/jenkins/Jenkinsfile" | sort
        ;;
    esac
    echo ""
  done
}

# Function to check for changes in GitHub Actions workflows
check_for_changes() {
  local specific_project=$1

  echo -e "${GREEN}Checking for changes in GitHub Actions workflows...${NC}"

  # Fetch latest changes from upstream if possible
  if git remote | grep -q "upstream"; then
    echo "Fetching from upstream..."
    git fetch upstream

    # Get the list of changed GitHub workflow files
    CHANGED_FILES=$(git diff --name-only upstream/main "$GITHUB_WORKFLOWS_DIR")
  else
    # If no upstream remote, check for local changes
    echo "No upstream remote found. Checking for local changes..."
    CHANGED_FILES=$(git diff --name-only HEAD@{1} "$GITHUB_WORKFLOWS_DIR" 2>/dev/null || echo "")

    if [ -z "$CHANGED_FILES" ]; then
      echo "No local changes detected. Listing all workflow files..."
      CHANGED_FILES=$(find "$GITHUB_WORKFLOWS_DIR" -name "*.yml")
    fi
  fi

  if [ -z "$CHANGED_FILES" ]; then
    echo -e "${YELLOW}No changes detected in GitHub Actions workflows.${NC}"
    return
  fi

  for FILE in $CHANGED_FILES; do
    # Extract project name from file path
    WORKFLOW_FILE=$(basename "$FILE")
    PROJECT_NAME=${WORKFLOW_FILE%.yml}

    # Skip if not in our projects list or if we're looking for a specific project
    if ! contains "${PROJECTS[@]}" "$PROJECT_NAME" ||
       ( [ -n "$specific_project" ] && [ "$specific_project" != "$PROJECT_NAME" ] ); then
      continue
    fi

    echo -e "\n${YELLOW}Changes detected in $PROJECT_NAME workflow${NC}"

    # Check for corresponding CI configurations
    for platform in "${CI_PLATFORMS[@]}"; do
      case "$platform" in
        gitlab)
          CI_FILE="$PROJECT_NAME/ci/gitlab/.gitlab-ci.yml"
          ;;
        bitbucket)
          CI_FILE="$PROJECT_NAME/ci/bitbucket/bitbucket-pipelines.yml"
          ;;
        jenkins)
          CI_FILE="$PROJECT_NAME/ci/jenkins/Jenkinsfile"
          ;;
      esac

      if [ -f "$CI_FILE" ]; then
        echo -e "  ${GREEN}✓${NC} $platform configuration exists: $CI_FILE"
        echo "    Consider reviewing this file to ensure it matches the GitHub workflow changes."
      else
        echo -e "  ${RED}✗${NC} $platform configuration missing: $CI_FILE"
        echo "    Consider creating this file based on the GitHub workflow."
      fi
    done
  done
}

# Function to apply GitHub Actions workflow changes to corresponding CI configurations
sync_changes() {
  echo -e "${YELLOW}This would synchronize GitHub Actions workflow changes to other CI platforms.${NC}"
  echo -e "${RED}This feature is not yet implemented.${NC}"
  echo ""
  echo "For now, please manually review the GitHub Actions workflow changes and update the corresponding CI configurations accordingly."
  echo "See FORKING.md for guidance on how to maintain CI configurations across different platforms."
}

# Main script logic
case "$1" in
  check)
    check_for_changes "$2"
    ;;
  sync)
    sync_changes
    ;;
  list)
    list_configurations
    ;;
  help|--help|-h)
    show_help
    ;;
  *)
    show_help
    ;;
esac

exit 0
