#!/bin/bash
#
# Script to create GitHub repository secrets from a .env file
# This script reads key-value pairs from a .env file and creates GitHub repository secrets
# using the GitHub CLI (gh)

set -e

# Default values
ENV_FILE=".env"
VERBOSE=false
FORCE=false

# Function to display usage information
usage() {
    echo "Usage: $0 [OPTIONS]"
    echo "Create GitHub repository secrets from a .env file"
    echo ""
    echo "Options:"
    echo "  -f, --file FILE     Specify the .env file to use (default: .env)"
    echo "  -v, --verbose       Enable verbose output"
    echo "  -y, --yes           Skip confirmation prompts"
    echo "  -h, --help          Display this help message"
    echo ""
    echo "Example:"
    echo "  $0 --file ./configs/.env.production"
    exit 1
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to log messages if verbose mode is enabled
log() {
    if [ "$VERBOSE" = true ]; then
        echo "$1"
    fi
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    key="$1"
    case $key in
        -f|--file)
            ENV_FILE="$2"
            shift
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            shift
            ;;
        -y|--yes)
            FORCE=true
            shift
            ;;
        -h|--help)
            usage
            ;;
        *)
            echo "Unknown option: $1"
            usage
            ;;
    esac
done

# Check if GitHub CLI is installed
if ! command_exists gh; then
    echo "Error: GitHub CLI (gh) is not installed."
    echo "Please install it from https://cli.github.com/ and try again."
    exit 1
fi

# Check if the user is authenticated with GitHub
if ! gh auth status >/dev/null 2>&1; then
    echo "Error: You are not authenticated with GitHub."
    echo "Please run 'gh auth login' and try again."
    exit 1
fi

# Check if the .env file exists
if [ ! -f "$ENV_FILE" ]; then
    echo "Error: File '$ENV_FILE' does not exist."
    exit 1
fi

# Count the number of secrets to be created
SECRET_COUNT=$(grep -v '^#' "$ENV_FILE" | grep -v '^$' | wc -l)

echo "Found $SECRET_COUNT potential secrets in $ENV_FILE"

# Confirm before proceeding
if [ "$FORCE" != true ]; then
    read -p "Do you want to create/update GitHub secrets from this file? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Operation cancelled."
        exit 0
    fi
fi

# Create secrets
SUCCESS_COUNT=0
FAILURE_COUNT=0

echo "Creating GitHub secrets..."

# Process each line in the .env file
while IFS= read -r line || [[ -n "$line" ]]; do
    # Skip comments and empty lines
    if [[ "$line" =~ ^#.*$ ]] || [[ -z "$line" ]]; then
        continue
    fi

    # Extract key and value
    if [[ "$line" =~ ^([^=]+)=(.*)$ ]]; then
        KEY="${BASH_REMATCH[1]}"
        VALUE="${BASH_REMATCH[2]}"

        # Remove surrounding quotes if present
        VALUE="${VALUE#\"}"
        VALUE="${VALUE%\"}"
        VALUE="${VALUE#\'}"
        VALUE="${VALUE%\'}"

        # Remove trailing comments if present
        VALUE=$(echo "$VALUE" | sed 's/[[:space:]]*#.*$//')

        # Trim whitespace
        KEY=$(echo "$KEY" | xargs)
        VALUE=$(echo "$VALUE" | xargs)

        log "Setting secret: $KEY"

        # Create or update the secret
        if echo "$VALUE" | gh secret set "$KEY" 2>/dev/null; then
            log "✓ Successfully set secret: $KEY"
            ((SUCCESS_COUNT++))
        else
            echo "✗ Failed to set secret: $KEY"
            ((FAILURE_COUNT++))
        fi
    fi
done < "$ENV_FILE"

# Summary
echo "Summary:"
echo "- Total secrets processed: $((SUCCESS_COUNT + FAILURE_COUNT))"
echo "- Successfully created/updated: $SUCCESS_COUNT"
echo "- Failed: $FAILURE_COUNT"

if [ $FAILURE_COUNT -gt 0 ]; then
    echo "Some secrets failed to be created/updated. Check the output above for details."
    exit 1
fi

echo "All secrets were successfully created/updated!"
exit 0
