# Deployment Guide

This document provides detailed instructions for deploying the Congress.gov API Chatbot application to various environments, with a focus on Tanzu Platform for Cloud Foundry.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Deployment to Tanzu Platform for Cloud Foundry](#deployment-to-tanzu-platform-for-cloud-foundry)
  - [Preparing for Deployment](#preparing-for-deployment)
  - [Deploying the Application](#deploying-the-application)
  - [Binding to the GenAI LLM Service](#binding-to-the-genai-llm-service)
  - [Configuring Environment Variables](#configuring-environment-variables)
  - [Scaling the Application](#scaling-the-application)
  - [Monitoring the Application](#monitoring-the-application)
- [Deployment to Other Environments](#deployment-to-other-environments)
  - [Docker Deployment](#docker-deployment)
  - [Kubernetes Deployment](#kubernetes-deployment)
- [Continuous Integration and Deployment](#continuous-integration-and-deployment)
- [Troubleshooting Deployment Issues](#troubleshooting-deployment-issues)

## Prerequisites

Before deploying the application, ensure you have the following:

- Access to a Tanzu Platform for Cloud Foundry environment
- Cloud Foundry CLI installed and configured
- Access to the GenAI tile and LLM service instances
- Congress.gov API key (get your API key at https://api.congress.gov/sign-up/)
- Go 1.24+ installed (for building the application)

## Deployment to Tanzu Platform for Cloud Foundry

### Preparing for Deployment

1. Build the application:

```bash
go build -o congress-chatbot cmd/server/main.go
```

2. Ensure the `manifest.yml` file is properly configured:

```yaml
applications:
- name: congress-chatbot
  memory: 256M
  instances: 1
  buildpacks:
    - go_buildpack
  env:
    GOPACKAGENAME: github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo
    GO_INSTALL_PACKAGE_SPEC: github.com/cf-toolsuite/tanzu-genai-showcase/go-fiber-langchaingo/cmd/server
    ENV: production
  health-check-type: http
  health-check-http-endpoint: /health
```

3. Verify that your `.env.example` file contains all the necessary environment variables:

```
CONGRESS_API_KEY=your_congress_api_key
GENAI_API_KEY=your_GENAI_API_KEY
GENAI_API_BASE_URL=your_GENAI_API_BASE_URL
LLM=gpt-4o-mini
```

### Deploying the Application

1. Log in to your Cloud Foundry environment:

```bash
cf login -a <API_ENDPOINT> -u <USERNAME> -p <PASSWORD> -o <ORG> -s <SPACE>
```

2. Push the application without starting it:

```bash
cf push --no-start
```

3. Set the Congress.gov API key:

```bash
cf set-env congress-chatbot CONGRESS_API_KEY <YOUR_CONGRESS_API_KEY>
```

### Binding to the GenAI LLM Service

1. List available plans for the GenAI service offering:

```bash
cf marketplace -e genai
```

2. Create an LLM service instance (if not already created):

```bash
cf create-service genai <PLAN_NAME> congress-llm
```

Replace `<PLAN_NAME>` with the name of an available plan of the GenAI tile service offering.

3. Bind the service to your application:

```bash
cf bind-service congress-chatbot congress-llm
```

4. Start your application to apply the binding:

```bash
cf start congress-chatbot
```

### Configuring Environment Variables

If you need to configure additional environment variables:

```bash
cf set-env congress-chatbot ENV production
cf set-env congress-chatbot PORT 8080
cf restage congress-chatbot
```

### Scaling the Application

To scale the application horizontally (add more instances):

```bash
cf scale congress-chatbot -i 3
```

To scale the application vertically (adjust memory or disk):

```bash
cf scale congress-chatbot -m 512M -k 512M
```

### Monitoring the Application

1. Check the application status:

```bash
cf app congress-chatbot
```

2. View application logs:

```bash
cf logs congress-chatbot --recent
```

3. Stream application logs:

```bash
cf logs congress-chatbot
```

## Deployment to Other Environments

### Docker Deployment

1. Create a `Dockerfile` in the project root:

```dockerfile
FROM golang:1.24-alpine AS builder

WORKDIR /app
COPY . .
RUN go mod download
RUN go build -o congress-chatbot cmd/server/main.go

FROM alpine:latest

WORKDIR /app
COPY --from=builder /app/congress-chatbot .
COPY --from=builder /app/public ./public

ENV PORT=8080
EXPOSE 8080

CMD ["./congress-chatbot"]
```

2. Build the Docker image:

```bash
docker build -t congress-chatbot .
```

3. Run the Docker container:

```bash
docker run -p 8080:8080 \
  -e CONGRESS_API_KEY=<YOUR_CONGRESS_API_KEY> \
  -e GENAI_API_KEY=<YOUR_GENAI_API_KEY> \
  -e GENAI_API_BASE_URL=<YOUR_GENAI_API_BASE_URL> \
  -e LLM=gpt-4o-mini \
  congress-chatbot
```

### Kubernetes Deployment

1. Create a Kubernetes deployment manifest (`deployment.yaml`):

```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: congress-chatbot
spec:
  replicas: 3
  selector:
    matchLabels:
      app: congress-chatbot
  template:
    metadata:
      labels:
        app: congress-chatbot
    spec:
      containers:
      - name: congress-chatbot
        image: congress-chatbot:latest
        ports:
        - containerPort: 8080
        env:
        - name: CONGRESS_API_KEY
          valueFrom:
            secretKeyRef:
              name: congress-chatbot-secrets
              key: congress-api-key
        - name: GENAI_API_KEY
          valueFrom:
            secretKeyRef:
              name: congress-chatbot-secrets
              key: genai-api-key
        - name: GENAI_API_BASE_URL
          valueFrom:
            secretKeyRef:
              name: congress-chatbot-secrets
              key: genai-api-base-url
        - name: LLM
          value: "gpt-4o-mini"
```

2. Create a Kubernetes service manifest (`service.yaml`):

```yaml
apiVersion: v1
kind: Service
metadata:
  name: congress-chatbot
spec:
  selector:
    app: congress-chatbot
  ports:
  - port: 80
    targetPort: 8080
  type: LoadBalancer
```

3. Create a Kubernetes secret for the API keys:

```bash
kubectl create secret generic congress-chatbot-secrets \
  --from-literal=congress-api-key=<YOUR_CONGRESS_API_KEY> \
  --from-literal=genai-api-key=<YOUR_GENAI_API_KEY> \
  --from-literal=genai-api-base-url=<YOUR_GENAI_API_BASE_URL>
```

4. Apply the Kubernetes manifests:

```bash
kubectl apply -f deployment.yaml
kubectl apply -f service.yaml
```

## Continuous Integration and Deployment

The project includes CI/CD configurations for various platforms:

### Jenkins

The `ci/jenkins/Jenkinsfile` contains a pipeline configuration for Jenkins:

```groovy
pipeline {
    agent any

    stages {
        stage('Build') {
            steps {
                sh 'go build -o congress-chatbot cmd/server/main.go'
            }
        }

        stage('Test') {
            steps {
                sh 'go test ./...'
            }
        }

        stage('Deploy') {
            steps {
                sh 'cf login -a $CF_API -u $CF_USERNAME -p $CF_PASSWORD -o $CF_ORG -s $CF_SPACE'
                sh 'cf push --no-start'
                sh 'cf set-env congress-chatbot CONGRESS_API_KEY $CONGRESS_API_KEY'
                sh 'cf bind-service congress-chatbot congress-llm'
                sh 'cf start congress-chatbot'
            }
        }
    }
}
```

### GitLab CI

The `ci/gitlab/.gitlab-ci.yml` contains a pipeline configuration for GitLab CI:

```yaml
stages:
  - build
  - test
  - deploy

build:
  stage: build
  image: golang:1.24
  script:
    - go build -o congress-chatbot cmd/server/main.go
  artifacts:
    paths:
      - congress-chatbot

test:
  stage: test
  image: golang:1.24
  script:
    - go test ./...

deploy:
  stage: deploy
  image: governmentpaas/cf-cli
  script:
    - cf login -a $CF_API -u $CF_USERNAME -p $CF_PASSWORD -o $CF_ORG -s $CF_SPACE
    - cf push --no-start
    - cf set-env congress-chatbot CONGRESS_API_KEY $CONGRESS_API_KEY
    - cf bind-service congress-chatbot congress-llm
    - cf start congress-chatbot
  only:
    - main
```

### Bitbucket Pipelines

The `ci/bitbucket/bitbucket-pipelines.yml` contains a pipeline configuration for Bitbucket Pipelines:

```yaml
image: golang:1.24

pipelines:
  default:
    - step:
        name: Build
        script:
          - go build -o congress-chatbot cmd/server/main.go
        artifacts:
          - congress-chatbot
    - step:
        name: Test
        script:
          - go test ./...
  branches:
    main:
      - step:
          name: Deploy
          image: governmentpaas/cf-cli
          script:
            - cf login -a $CF_API -u $CF_USERNAME -p $CF_PASSWORD -o $CF_ORG -s $CF_SPACE
            - cf push --no-start
            - cf set-env congress-chatbot CONGRESS_API_KEY $CONGRESS_API_KEY
            - cf bind-service congress-chatbot congress-llm
            - cf start congress-chatbot
```

## Troubleshooting Deployment Issues

### Common Issues and Solutions

#### Application Fails to Start

**Symptoms:**

- The application fails to start after deployment
- `cf start` command fails

**Possible Causes and Solutions:**

1. **Missing environment variables**

   - Check if all required environment variables are set:

   ```bash
   cf env congress-chatbot
   ```
   - Set any missing environment variables:

   ```bash
   cf set-env congress-chatbot CONGRESS_API_KEY <YOUR_CONGRESS_API_KEY>
   ```

2. **Service binding issues**

   - Check if the service is properly bound:

   ```bash
   cf services
   ```

   - Rebind the service if necessary:

   ```bash
   cf unbind-service congress-chatbot congress-llm
   cf bind-service congress-chatbot congress-llm
   ```

3. **Build issues**

   - Check the application logs for build errors:

   ```bash
   cf logs congress-chatbot --recent
   ```

   - Ensure the Go version is compatible:

   ```bash
   cf set-env congress-chatbot GOVERSION go1.24
   ```

#### Application Crashes After Starting

**Symptoms:**

- The application starts but crashes shortly after
- Health check fails

**Possible Causes and Solutions:**
1. **Memory issues**

   - Increase the memory allocation:

   ```bash
   cf scale congress-chatbot -m 512M
   ```

2. **Configuration issues**

   - Check the application logs for configuration errors:

   ```bash
   cf logs congress-chatbot --recent
   ```

   - Verify the service credentials are correctly parsed:

   ```bash
   cf env congress-chatbot
   ```

3. **API connectivity issues**

   - Ensure the application can connect to the Congress.gov API and the GenAI LLM service
   - Check for network or firewall issues

#### Performance Issues

**Symptoms:**

- The application is slow to respond
- Timeouts occur during API calls

**Possible Causes and Solutions:**

1. **Insufficient resources**

   - Scale the application vertically:

   ```bash
   cf scale congress-chatbot -m 512M
   ```

   - Scale the application horizontally:

   ```bash
   cf scale congress-chatbot -i 3
   ```

2. **External API latency**

   - Check the application logs for slow API calls:

   ```bash
   cf logs congress-chatbot --recent
   ```

   - Adjust the timeout settings in the application code

3. **Caching issues**

   - Verify that the caching mechanism is working correctly
   - Adjust the cache TTL if necessary
