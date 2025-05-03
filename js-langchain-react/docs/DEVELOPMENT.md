# Development Guide

This document provides comprehensive information about setting up and running the News Aggregator application in your local development environment.

## Development Environment Setup

### Prerequisites

Before you begin, ensure you have the following installed:

- **Node.js**: Version 18.0.0 or higher (recommended: latest LTS version)
- **npm**: Usually comes with Node.js
- **Git**: For version control
- **Text Editor/IDE**: VS Code, WebStorm, or any preferred editor

### API Keys

You'll need the following API keys:

1. **News API Key**: Register at [newsapi.org](https://newsapi.org) to get a free API key
2. **LLM API Key**: For development, you can use an OpenAI API key or any compatible LLM service

## Project Structure

```
js-langchain-react/
├── public/                 # Static assets
├── src/                    # Frontend source code
│   ├── components/         # React components
│   │   ├── NewsItem.js     # Individual article display
│   │   ├── NewsList.js     # Article list container
│   │   └── NewsSearch.js   # Search form component
│   ├── services/           # Service integrations
│   │   └── newsService.js  # API client for news
│   ├── App.js              # Main application component
│   └── index.js            # Application entry point
├── server.js               # Express backend
├── .env.example            # Example environment variables
├── package.json            # Project dependencies
├── manifest.yml            # Cloud Foundry manifest
└── docs/                   # Documentation
```

## Getting Started

### 1. Clone the Repository

```bash
git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
cd tanzu-genai-showcase/js-langchain-react
```

### 2. Install Dependencies

```bash
npm install
```

This will install all required dependencies for both the frontend and backend.

### 3. Configure Environment Variables

Create a `.env` file in the project root by copying the example:

```bash
cp .env.example .env
```

Then edit the `.env` file to add your API keys:

```
REACT_APP_API_BASE_URL=http://localhost:3001
API_KEY=your_llm_api_key_here
NEWS_API_KEY=your_news_api_key_here
```

### 4. Build the React Application

Before starting the servers, you need to build the React application:

```bash
npm run build
```

This creates a production build of the React app in the `build` directory, which the Express server is configured to serve. This step is required even for development because the server is set up to serve the built React app.

### 5. Start the Development Servers

The application consists of two main parts that run as separate processes during development:

- **React Frontend**: Runs on port 3000
- **Express Backend**: Runs on port 3001 (or an alternative port if 3001 is in use)

To start both simultaneously:

```bash
# Option 1: Using the start script (recommended)
./start-app.sh
```

This script will:

- Check if port 3001 is already in use
- If it is, ask if you want to kill the process or use an alternative port
- If you choose to use an alternative port, it will:
  - Find an available port
  - Update your .env file with the correct API base URL
  - Start the application with the new configuration

```bash
# Option 2: Using npm directly
npm run dev
```

This uses the `concurrently` package to run both servers in parallel.

Alternatively, you can start them individually:

```bash
# Start just the backend
npm run server

# In another terminal, start just the frontend
npm start
```

If you start the servers individually and encounter port conflicts, you'll need to manually update your `.env` file to ensure the frontend and backend are using the same port:

```
# If the backend is running on port 3002
REACT_APP_API_BASE_URL=http://localhost:3002
```

### 5. Access the Application

Open your browser and navigate to:

```
http://localhost:3000
```

## Development Workflow

### Code Organization

- **React Components**: All UI components are in `src/components/`
- **API Services**: API communication is handled in `src/services/`
- **Backend Logic**: Express server code is in `server.js`

### Hot Reloading

The development setup includes hot reloading:

- **Frontend**: Changes to React components will automatically refresh in the browser
- **Backend**: Changes to server.js require a manual restart (`Ctrl+C` and `npm run server` again)

### Environment Detection

The application automatically detects whether it's running in development or production:

```javascript
// From src/services/newsService.js
function isLocalDevelopment() {
  return window.location.hostname === 'localhost' &&
         (window.location.port === '3000' || window.location.port === '3002');
}

const API_BASE_URL = isLocalDevelopment()
  ? (process.env.REACT_APP_API_BASE_URL || 'http://localhost:3001')
  : '';
```

This ensures API calls work correctly in both environments without manual configuration.

## Environment Variables

### Local Development Variables

| Variable | Purpose | Default | Required |
|----------|---------|---------|----------|
| `REACT_APP_API_BASE_URL` | URL for backend API | http://localhost:3001 | No |
| `API_KEY` | API key for the LLM service | None | Yes |
| `NEWS_API_KEY` | API key for News API | None | Yes |
| `PORT` | Port for backend server | 3001 | No |

### Variable Naming Flexibility

The backend supports multiple environment variable names for compatibility:

- **LLM API Key**: `API_KEY`, `LLM_API_KEY`, or `GENAI_API_KEY`
- **LLM Base URL**: `API_BASE_URL`, `LLM_API_BASE`, or `GENAI_API_BASE_URL`
- **LLM Model**: `MODEL_NAME`, `LLM_MODEL`, or `GENAI_MODEL`

## Building for Production

To create a production build:

```bash
npm run build
```

This creates optimized production files in the `build` directory. The Express server is configured to serve these static files in production.

## Testing

To run tests:

```bash
npm test
```

This runs the test suite using Jest and React Testing Library.

## Common Development Tasks

### Adding a New Component

1. Create a new file in `src/components/`
2. Import and use it in the appropriate parent component
3. Add CSS in a corresponding `.css` file

### Modifying the API

1. Update the endpoint in `server.js`
2. Update the corresponding service function in `src/services/newsService.js`

### Updating Dependencies

For regular updates:

```bash
npm update
```

To fix security issues:

```bash
npm run fix-deps
```

To update all dependencies and fix deprecated packages:

```bash
./update-dependencies.sh
```

This script will:

- Configure npm to use secure TLS
- Remove existing node_modules and package-lock.json
- Install the updated dependencies
- Run npm audit fix to address any remaining issues

See the [Dependencies documentation](DEPENDENCIES.md) for more details about the recent dependency updates and best practices for dependency management.

## Troubleshooting

### API Connection Issues

If the frontend can't connect to the backend:

1. Ensure both servers are running
2. Check that the REACT_APP_API_BASE_URL in .env matches your backend server address
3. Verify the backend server is listening on port 3001 (or update the URL accordingly)
4. Check browser console for CORS errors

### Missing API Keys

If you're seeing errors about invalid credentials:

1. Make sure your .env file contains valid API keys
2. Restart both the backend and frontend servers after updating .env
3. For the LLM service, check that your API key is active and has sufficient quota

### File Watcher Limit Issues

If you encounter the error: `ENOSPC: System limit for number of file watchers reached`, this is a common issue on Linux systems. The problem occurs because the React development server uses a lot of file watchers to monitor for file changes.

To fix this issue:

1. Run the included script:

   ```bash
   ./fix-watchers.sh
   ```

2. If you don't want to use the script, you can manually increase the file watcher limit:

   ```bash
   echo fs.inotify.max_user_watches=524288 | sudo tee -a /etc/sysctl.conf
   sudo sysctl -p
   ```

### Build Issues

When building locally:

1. Environment variables prefixed with REACT_APP_ are available to the client-side code
2. The React build process uses NODE_ENV=production automatically
3. You don't need to set NODE_ENV manually when using npm scripts

## Development Best Practices

1. **Code Style**: Follow the existing code style for consistency
2. **Component Structure**: Keep components small and focused on a single responsibility
3. **State Management**: Use React hooks for state management
4. **Error Handling**: Implement proper error handling in both frontend and backend
5. **Environment Variables**: Never commit .env files with real credentials
6. **API Communication**: Use the service layer for all API calls
7. **Responsive Design**: Ensure the UI works well on different screen sizes
8. **Accessibility**: Maintain basic accessibility standards
9. **Performance**: Be mindful of performance implications, especially with LLM calls
