# Concourse CI/CD for java-spring-langgraph-mcp-angular

This directory contains Concourse CI/CD pipeline configuration for the java-spring-langgraph-mcp-angular project.

## Pipeline Overview

The pipeline consists of four main jobs:

1. **build-backend**: Builds the Java Spring backend application.
2. **build-frontend**: Builds the Angular frontend application.
3. **package**: Combines the backend and frontend, then packages the artifacts.
4. **deploy-to-cf**: Deploys the application to Cloud Foundry.

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
   fly -t your-target set-pipeline -p java-spring-langgraph-mcp-angular -c pipeline.yml -l vars.yml -l credentials.yml
   ```

3. **Unpause the Pipeline**:
   ```bash
   fly -t your-target unpause-pipeline -p java-spring-langgraph-mcp-angular
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

The java-spring-langgraph-mcp-angular project consists of two main components:

1. **Backend**: Java Spring Boot application with Spring AI and LangGraph integration
2. **Frontend**: Angular application that provides the user interface

The pipeline builds both components separately and then combines them for deployment to Cloud Foundry.

## Troubleshooting

- **Backend Build Failures**: Check the build logs for errors. Most common issues are related to Java dependencies or Maven configuration.
- **Frontend Build Failures**: Check the build logs for errors. Most common issues are related to Node.js dependencies or Angular build configuration.
- **Deployment Failures**: Verify your Cloud Foundry credentials and service availability.
- **Resource Issues**: Ensure your S3 bucket is properly configured and accessible with the provided credentials.

## Additional Resources

- [Concourse CI Documentation](https://concourse-ci.org/docs.html)
- [Cloud Foundry CLI Documentation](https://docs.cloudfoundry.org/cf-cli/)
- [Java Documentation](https://docs.oracle.com/en/java/)
- [Spring Boot Documentation](https://docs.spring.io/spring-boot/docs/current/reference/html/)
- [Spring AI Documentation](https://docs.spring.io/spring-ai/reference/)
- [Angular Documentation](https://angular.io/docs)
