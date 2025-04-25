# Concourse CI/CD for java-spring-ai-mcp

This directory contains Concourse CI/CD pipeline configuration for the java-spring-ai-mcp project.

## Pipeline Overview

The pipeline consists of three main jobs:

1. **build-and-test**: Builds and tests the Java Spring application, then packages the artifacts.
2. **deploy-client-to-cf**: Deploys the client component to Cloud Foundry.
3. **deploy-server-to-cf**: Deploys the server component to Cloud Foundry.

## Prerequisites

- [Concourse CI](https://concourse-ci.org/) server
- [Fly CLI](https://concourse-ci.org/fly.html) installed and authenticated with your Concourse server
- AWS S3 bucket for artifact storage (or another storage solution configured in the pipeline)
- Cloud Foundry account with access to create services and deploy applications

## Setup Instructions

1. **Configure Variables**:
   - Edit `vars.yml` to set your configuration values
   - Copy `credentials-template.yml` to `credentials.yml` and fill in your credentials
   - **IMPORTANT**: Do not commit `credentials.yml` to version control!

2. **Set Up the Pipeline**:
   ```bash
   fly -t your-target set-pipeline -p java-spring-ai-mcp -c pipeline.yml -l vars.yml -l credentials.yml
   ```

3. **Unpause the Pipeline**:
   ```bash
   fly -t your-target unpause-pipeline -p java-spring-ai-mcp
   ```

## Pipeline Variables

### Required Variables

- `git_repo_url`: URL of the Git repository
- `git_branch`: Branch to monitor for changes
- `artifact_bucket`: S3 bucket for storing build artifacts
- `aws_region`: AWS region for the S3 bucket
- `aws_access_key`: AWS access key with permissions to the S3 bucket
- `aws_secret_key`: AWS secret key
- `cf_api`: Cloud Foundry API endpoint
- `cf_username`: Cloud Foundry username
- `cf_password`: Cloud Foundry password
- `cf_org`: Cloud Foundry organization
- `cf_space`: Cloud Foundry space
- `openai_api_key`: OpenAI API key for the application

## Customizing the Pipeline

- Modify `pipeline.yml` to add or remove steps as needed
- Adjust task configurations in the `tasks` directory
- Update scripts in the `tasks/scripts` directory to customize build and deployment processes

## Project Structure

The java-spring-ai-mcp project consists of three main components:

1. **API**: Shared API definitions
2. **Client**: Client application that uses the API
3. **Server**: Server application that implements the API

Each component is built separately and the client and server components are deployed to Cloud Foundry.

## Troubleshooting

- **Build Failures**: Check the build logs for errors. Most common issues are related to Java dependencies or Maven configuration.
- **Deployment Failures**: Verify your Cloud Foundry credentials and service availability.
- **Resource Issues**: Ensure your S3 bucket is properly configured and accessible with the provided credentials.

## Additional Resources

- [Concourse CI Documentation](https://concourse-ci.org/docs.html)
- [Cloud Foundry CLI Documentation](https://docs.cloudfoundry.org/cf-cli/)
- [Java Documentation](https://docs.oracle.com/en/java/)
- [Spring Boot Documentation](https://docs.spring.io/spring-boot/docs/current/reference/html/)
- [Spring AI Documentation](https://docs.spring.io/spring-ai/reference/)
