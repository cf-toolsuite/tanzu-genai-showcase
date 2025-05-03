# Managing GitHub Secrets for Tanzu GenAI Showcase

This document explains how to manage GitHub secrets for the Tanzu GenAI Showcase repository using the provided tools.

## Overview

The GitHub Actions workflows in this repository require various secrets to authenticate with Cloud Foundry and to provide API keys for the different applications. To simplify the process of creating and managing these secrets, we provide:

1. A master `.env.example` file that lists all required secrets with descriptions
2. A `create-gh-secrets.sh` script that creates GitHub repository secrets from a `.env` file

## Prerequisites

- [GitHub CLI (gh)](https://cli.github.com/) installed and authenticated
- Bash shell environment

## Step 1: Create Your `.env` File

1. Copy the example file to create your own `.env` file:

   ```bash
   cp .env.example .env
   ```

2. Edit the `.env` file and replace the placeholder values with your actual credentials:

   ```bash
   # Example
   CF_PASSWORD=your_actual_password_here
   OPENAI_API_KEY=sk-your_actual_openai_key_here
   ```

   > **Note**: The `.env` file is automatically added to `.gitignore` to prevent accidentally committing sensitive information.

## Step 2: Create GitHub Secrets

Use the provided script to create GitHub secrets from your `.env` file:

```bash
./create-gh-secrets.sh
```

This will:

1. Read each key-value pair from your `.env` file
2. Create or update corresponding GitHub repository secrets
3. Display a summary of the operation

### Script Options

The script supports several options:

```bash
# Use a different .env file
./create-gh-secrets.sh -f /path/to/your/.env

# Enable verbose output
./create-gh-secrets.sh -v

# Skip confirmation prompts
./create-gh-secrets.sh -y

# Display help
./create-gh-secrets.sh -h
```

## Required Secrets

The following secrets are required for the GitHub Actions workflows:

### Cloud Foundry Authentication

- `CF_PASSWORD` - Used for username/password authentication
- `CF_SSO_REFRESH_TOKEN` - Alternative for SSO authentication

### Project-Specific API Keys

Each project requires specific API keys. Refer to the `.env.example` file for detailed descriptions and where to obtain these keys.

## Verifying Secrets

To verify that your secrets have been created correctly, you can:

1. Go to your GitHub repository settings
2. Navigate to "Secrets and variables" → "Actions"
3. Check that all required secrets are listed

## Troubleshooting

### Authentication Issues

If you encounter authentication issues with the GitHub CLI:

```bash
gh auth login
```

### Permission Issues

Ensure you have admin access to the repository to create secrets.

### Script Execution Issues

If you encounter issues running the script:

```bash
# Make the script executable
chmod +x create-gh-secrets.sh

# Check for syntax errors
bash -n create-gh-secrets.sh
```

## Security Considerations

- Never commit your `.env` file to the repository
- Regularly rotate your API keys and update the corresponding GitHub secrets
- Consider using GitHub environments for production deployments to add additional protection to your secrets
