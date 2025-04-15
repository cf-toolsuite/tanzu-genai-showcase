# Python Pyramid with Agno AI Chatbot

![Status](https://img.shields.io/badge/status-under%20development-darkred) ![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/py-pyramid-agno.yml/badge.svg)

This application implements an Airbnb search assistant chatbot interface that uses Agno for AI capabilities and the Model Context Protocol (MCP) for external data integration.

![Airbnb Assistant Screenshot]()

## Features

- Modern chat interface with light and dark theme support
- AI-powered assistant using Agno and OpenAI/LLM integration
- Integration with MCP Server for Airbnb listings
- Responsive design for desktop and mobile
- Interactive listing cards with detailed information

## Quick Start

We provide convenience scripts to automate the setup process:

### On macOS/Linux

```bash
# Clone the repository
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
cd tanzu-genai-showcase/py-pyramid-agno

# Run the setup script
./setup_env.sh

# Activate the virtual environment
source venv/bin/activate

# Start the application
pserve development.ini --reload
```

### On Windows

```powershell
# Clone the repository
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
cd tanzu-genai-showcase/py-pyramid-agno

# Activate the virtual environment
.\venv\Scripts\Activate.ps1

# Run the setup script
.\setup_env.ps1

# Start the application
pserve development.ini --reload
```

Then visit [http://localhost:8080](http://localhost:8080) in your browser.

> **Note:** The setup scripts create a `.env` file with default values. You'll need to edit this file to add your LLM API key for full functionality.

## Manual Setup (Simple Version)

If you prefer a manual setup, here are the minimal steps required:

1. **Prerequisites:**
   - Python 3.8+ (compatible with Python 3.12.3)
   - pip
   - LLM API key (e.g., OpenAI API key)

2. **Setup:**

   ```bash
   # Create a virtual environment and install dependencies
   python -m venv venv
   source venv/bin/activate  # On Windows: venv\Scripts\activate
   pip install -e .

   # Initialize the database
   initialize_db development.ini

   # Create a .env file with your API key
   cp .env.example .env
   # Edit the .env file and change the values
   ```

3. **Run:**

   ```bash
   pserve development.ini --reload
   ```

> [!NOTE]
> For detailed setup instructions, including Cloud Foundry deployment, consult the [deployment guide](DEPLOY.md)

## Project Structure

```
py-pyramid-agno/
├── airbnb_assistant/       # Main application package
│   ├── ai/                 # AI components with Agno
│   │   ├── mcp/            # MCP client integration
│   │   ├── agent.py        # Agent implementation
│   │   └── tools.py        # Custom Agno tools
│   ├── static/             # Static assets (CSS, JS, etc.)
│   ├── templates/          # Mako templates
│   ├── scripts/            # Utility scripts
│   ├── models.py           # SQLAlchemy models
│   ├── views.py            # Pyramid views
│   └── __init__.py         # Package initialization
├── setup_env.sh            # Setup script for macOS/Linux
├── setup_env.ps1           # Setup script for Windows
├── development.ini         # Development configuration
├── production.ini          # Production configuration
├── requirements.txt        # Python dependencies
├── setup.py                # Package setup
├── SETUP_DEPLOY.md         # Detailed setup and deployment guide
└── README.md               # This file
```

## AI Implementation

The application uses the [Agno](https://docs.agno.com) AI framework to create an intelligent assistant that can:

1. Understand natural language queries about accommodations
2. Search for listings based on location and other criteria
3. Provide detailed information about listings

For details on the Agno implementation, see [airbnb_assistant/ai/README.md](airbnb_assistant/ai/README.md).

## Testing the Application

Once running, you can test the application by:

1. Opening [http://localhost:8080](http://localhost:8080) in your browser
2. Trying the chatbot with a query like "Find me a place to stay in San Francisco"
3. Testing the theme toggle button to switch between light and dark mode
4. Clicking on listing cards to view details

## Common Issues

- **No API Key**: If you see mock responses, check that your `.env` file contains a valid `OPENAI_API_KEY`.
- **Database Errors**: If you encounter database errors, run `initialize_db development.ini --reinitialize`.
- **Module Not Found**: Make sure you've activated the virtual environment and installed dependencies.

For more troubleshooting tips, see the Troubleshooting section in the [deployment guide](DEPLOY.md#troubleshooting).

## Deploying to Tanzu Platform for Cloud Foundry

This application is designed to run on Tanzu Platform for Cloud Foundry. For detailed deployment instructions, refer to the appropriate section in the [deployment guide](DEPLOY.md#deployment-to-tanzu-platform-for-cloud-foundry).

## References

- [Agno Documentation](https://docs.agno.com)
- [Pyramid Documentation](https://docs.pylonsproject.org/projects/pyramid/en/latest/)
- [Model Context Protocol Servers](https://github.com/openbnb-org/mcp-server-airbnb)
- [Tanzu Platform Documentation](https://docs.vmware.com/en/VMware-Tanzu-Application-Platform)
