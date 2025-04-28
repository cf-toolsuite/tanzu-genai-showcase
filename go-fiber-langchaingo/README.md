# Congress.gov API Chatbot with Go + Fiber + LangChainGo

![Status](https://img.shields.io/badge/status-ready-darkgreen) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/go-fiber-langchaingo.yml/badge.svg)

This application demonstrates how to build a chatbot that interacts with the Congress.gov API using Go, Fiber, and LangChainGo, deployable on Tanzu Platform for Cloud Foundry.

## What is it?

A web-based chatbot application that allows users to interact with the Congress.gov API to fetch information about bills, amendments, summaries, and members in natural language. The application uses LangChainGo to integrate with GenAI's LLM service for natural language processing.

The chatbot features a toggle that allows users to switch between:
- **API Tools Mode (enabled by default)**: Uses real-time API calls to Congress.gov for up-to-date information
- **Model-Only Mode**: Relies on the LLM's pre-trained knowledge without making external API calls

## Prerequisites

- Go 1.18+ installed locally for development
- Access to Congress.gov API (get your API key at https://api.congress.gov/sign-up/)
- For Tanzu deployment:
  - Tanzu Platform for Cloud Foundry environment
  - Access to GenAI tile and LLM service instances
  - Cloud Foundry CLI installed

## How to Build

Build a binary:

```bash
go build -o congress-chatbot cmd/server/main.go
```

## How to Run Locally

### 1. Clone the repository

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
cd tanzu-genai-showcase/go-fiber-langchaingo
```

### 2. Create a .env file for local development

```bash
cp .env.example .env
```

Edit the `.env` file to include your API keys:

```
CONGRESS_API_KEY=your_congress_api_key
GENAI_API_KEY=your_GENAI_API_KEY
GENAI_API_BASE_URL=your_GENAI_API_BASE_URL
LLM=gpt-4o-mini
```

> **Note:** The `LLM` environment variable allows you to specify which language model to use. If not set, it defaults to `gpt-4o-mini`.

### 3. Install dependencies

```bash
go mod tidy
```

### 4. Run the application locally

```bash
go run cmd/server/main.go
```

The application will be available at http://localhost:8080

## Using the Chatbot

1. Open the application in your web browser at http://localhost:8080
2. You'll see the chatbot interface with a toggle switch labeled "Use API Tools" in the top-right corner
3. By default, the toggle is enabled, which means the chatbot will make real-time API calls to Congress.gov for up-to-date information
4. You can disable the toggle if you want the chatbot to rely solely on the LLM's pre-trained knowledge without making external API calls
5. Type your question in the input field and press Enter or click the Send button
6. When API Tools mode is enabled, responses will include a small "🔧 Response generated using API tools" indicator

### Example Questions

With API Tools enabled (recommended for current information):

- "Who are the current senators from Washington state?"
- "What is the status of bill S.1260 in the 117th Congress?"
- "List the committees in the House of Representatives"

With API Tools disabled (for general knowledge questions):

- "How does a bill become a law?"
- "What is the role of the Speaker of the House?"
- "Explain the difference between the House and Senate"

## How to Run on Cloud Foundry


### 1. Push to Cloud Foundry

```bash
cf push --no-start
cf set-env congress-chatbot CONGRESS_API_KEY ${CONGRESS_API_KEY}
```

### 2. Bind to LLM Service

```bash
# List available plans for the GenAI service offering
cf marketplace -e genai

# Create an LLM service instance (if not already created)
cf create-service genai PLAN_NAME congress-llm
```

> [!IMPORTANT]
> Replace `PLAN_NAME` above with the name of an available plan of the GenAI tile service offering

```bash
# Bind the service to your application
cf bind-service congress-chatbot congress-llm

# Start your application to apply the binding
cf start congress-chatbot
```

## Tech Stack

- **Go**: Programming language
- **Fiber**: Web framework for building the API and serving the web interface
- **LangChainGo**: Framework for building applications with large language models
- **Congress.gov API**: External API for fetching legislative data
- **GenAI LLM Service**: Large language model service provided by Tanzu Platform for Cloud Foundry

## Project Structure

```bash
├── api/            # API clients (Congress.gov)
├── cmd/            # Application entry points
├── config/         # Configuration handling
├── internal/       # Private application code
├── pkg/            # Public libraries
├── .env.example    # Example environment variables
├── go.mod          # Go module definition
├── manifest.yml    # Cloud Foundry manifest
└── README.md       # This file
```
