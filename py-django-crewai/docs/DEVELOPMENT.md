# Developer Guide

This document provides guidelines and information for developers working on or contributing to the Movie Chatbot application.

## Table of Contents

- [Development Environment Setup](#development-environment-setup)
- [Project Structure](#project-structure)
- [Core Concepts](#core-concepts)
- [Development Workflow](#development-workflow)
- [Frontend Development](#frontend-development)
- [Backend Development](#backend-development)
- [Testing Strategy](#testing-strategy)
- [Adding New Features](#adding-new-features)
- [Debugging](#debugging)
- [Contribution Guidelines](#contribution-guidelines)

## Development Environment Setup

### Prerequisites

- Python 3.12+ and pip
- Node.js 18+ and npm
- Git
- A code editor (VS Code recommended)
- API keys for external services:
  - An OpenAI-compatible LLM API key (for local development)
  - TMDb API key (sign-up [here](https://www.themoviedb.org/signup))
  - SerpAPI key (sign-up for a free account [here](https://serpapi.com/users/sign_up))

### Local Setup

1. Clone the repository:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/py-django-crewai
   ```

2. Create and activate a virtual environment:

   ```bash
   python -m venv venv
   source venv/bin/activate  # On Windows, use: venv\Scripts\activate
   ```

3. Install backend dependencies:

   ```bash
   pip install -r requirements.txt
   ```

4. Install frontend dependencies:

   ```bash
   cd frontend
   npm install
   cd ..
   ```

5. Create a `.env` file with your API keys and configuration:

   ```bash
   cp .env.example .env
   # Edit the .env file with your API keys and settings
   ```

6. Run migrations:

   ```bash
   python manage.py makemigrations chatbot
   python manage.py migrate
   ```

7. Start the development server:

   ```bash
   python manage.py runserver
   ```

8. For frontend development with hot reloading:

   ```bash
   cd frontend
   npm start
   ```

   This will start the Webpack dev server that proxies API requests to your Django server.

9. Open your browser to:
   - `http://localhost:8000` (backend server)
   - `http://localhost:8080` (frontend dev server with hot reloading)

### Environment Variables

Key environment variables for development:

```bash
# Required API keys
OPENAI_API_KEY=your_llm_api_key_here
LLM_BASE_URL=optional_custom_endpoint
LLM_MODEL=gpt-4o-mini
TMDB_API_KEY=your_movie_db_api_key_here
SERPAPI_API_KEY=optional_serpapi_key_for_real_showtimes

# Optional configuration parameters
MOVIE_RESULTS_LIMIT=5            # Number of movie results to return from search
MAX_RECOMMENDATIONS=3            # Maximum number of recommended movies to show
THEATER_SEARCH_RADIUS_MILES=15   # Radius in miles to search for theaters
DEFAULT_SEARCH_START_YEAR=1900   # Default start year for historical movie searches

# API Request Configuration
API_REQUEST_TIMEOUT_SECONDS=180  # Maximum seconds to wait for API responses
API_MAX_RETRIES=10               # Maximum number of retry attempts for failed API requests
API_RETRY_BACKOFF_FACTOR=1.3     # Exponential backoff factor between retries (in seconds)

# SerpAPI Request Configuration
SERPAPI_REQUEST_BASE_DELAY=5.0   # Base delay between theater requests for different movies (seconds)
SERPAPI_PER_MOVIE_DELAY=2.0      # Additional delay per movie processed (seconds)
SERPAPI_MAX_RETRIES=2            # Maximum retries for SerpAPI requests
SERPAPI_BASE_RETRY_DELAY=3.0     # Base delay for exponential backoff during retries (seconds)
SERPAPI_RETRY_MULTIPLIER=1.5     # Multiplier for exponential backoff during retries

# Development settings
DEBUG=True                      # Enable debug mode
LOG_LEVEL=DEBUG                 # Set logging level
```

## Project Structure

The application follows a dual-structure with Django backend and React frontend:

```
py-django-crewai/
├── chatbot/                           # Main Django app
│   ├── migrations/                    # Database migrations
│   ├── services/                      # Business logic services
│   │   ├── movie_crew/                # CrewAI implementation
│   │   │   ├── agents/                # Agent definitions
│   │   │   ├── tools/                 # Tool implementations
│   │   │   └── utils/                 # Utility functions
│   │   ├── movie_crew.py              # Manager wrapper
│   │   ├── location_service.py        # Location services
│   │   ├── serp_service.py            # SerpAPI integration
│   │   └── tmdb_service.py            # TMDb API integration
│   ├── models.py                      # Data models
│   ├── views.py                       # API endpoints handlers
│   └── urls.py                        # URL routing
├── docs/                              # Documentation
├── frontend/                          # React frontend application
│   ├── src/                           # Source code
│   │   ├── components/                # React components
│   │   │   ├── Chat/                  # Chat components
│   │   │   ├── Movies/                # Movie components
│   │   │   └── Theaters/              # Theater components
│   │   ├── context/                   # React context providers
│   │   ├── hooks/                     # Custom React hooks
│   │   ├── services/                  # Frontend API services
│   │   └── styles/                    # CSS styles
│   ├── public/                        # Static assets
│   ├── package.json                   # npm dependencies
│   └── webpack.config.js              # Build configuration
├── movie_chatbot/                     # Django project settings
│   ├── settings.py                    # Application settings
│   ├── urls.py                        # Top-level URL routing
│   └── wsgi.py                        # WSGI configuration
├── static/                            # Collected static files
├── manage.py                          # Django management script
├── requirements.txt                   # Python dependencies
└── manifest.yml                       # Cloud Foundry manifest
```

### Key Directories

#### Backend
- **chatbot/services/movie_crew/**: Contains the CrewAI implementation with agent definitions, tool implementations, and utilities
- **chatbot/models.py**: Django ORM models for data persistence
- **chatbot/views.py**: API endpoint handlers

#### Frontend
- **frontend/src/components/**: React components organized by feature
- **frontend/src/context/**: React context providers for state management
- **frontend/src/services/**: API service integrations
- **frontend/src/hooks/**: Custom React hooks for reusable behavior

## Core Concepts

### Frontend Architecture

The frontend is built with modern React practices:

1. **Component-Based Architecture**:
   - Functional components with hooks for state management
   - Component organization by feature (Chat, Movies, Theaters)
   - Suspense and lazy loading for performance optimization

2. **State Management**:
   - React Context API via `AppContext` for shared state
   - Separate state for each conversation mode
   - React Query for data fetching, caching, and background updates

3. **Optimized Loading**:
   - Progressive loading indicators
   - Lazy-loaded components
   - Resource caching for improved performance

4. **API Integration**:
   - Centralized API service with error handling
   - Request deduplication and caching
   - Polling for long-running operations

### CrewAI Integration

The application uses CrewAI to implement a multi-agent system:

1. **Agents**: Specialized AI agents with specific roles
   - Movie Finder Agent
   - Recommendation Agent
   - Theater Finder Agent

2. **Tools**: Pydantic-based tools for each agent to perform specific tasks
   - SearchMoviesTool
   - AnalyzePreferencesTool
   - FindTheatersTool

3. **Tasks**: Defined work items for each agent
   - Find movies matching criteria
   - Recommend top N movies
   - Find theaters showing movies

4. **Crew**: Orchestrates agents and task execution flow
   - Different configurations based on conversation mode

### Conversation Modes

The application supports two distinct conversation modes:

1. **First Run Mode**: For current movies in theaters
   - Includes all three agents
   - Performs theater and showtime searches
   - Shows date-based showtime navigation

2. **Casual Viewing Mode**: For historical movie exploration
   - Uses only Movie Finder and Recommendation agents
   - Supports decade-specific searches
   - No theater information

### Location Services Architecture

Location determination follows a fallback cascade:

1. Browser geolocation API (primary)
2. ipapi.co geolocation (automatic fallback)
3. User-provided location (manual entry)
4. Default location (last resort)

## Development Workflow

### Full-Stack Development Process

1. **Planning**:
   - Define the feature scope
   - Identify affected frontend and backend components
   - Plan data flow between components

2. **Backend Implementation**:
   - Create or update API endpoints in Django
   - Implement business logic in services
   - Add appropriate models and database migrations

3. **Frontend Implementation**:
   - Create React components for the new feature
   - Add state management in context
   - Implement API service integration

4. **Integration Testing**:
   - Test the feature end-to-end
   - Verify data flow between frontend and backend
   - Test with different conversation modes

5. **Code Review**:
   - Submit pull request
   - Address review feedback
   - Ensure all tests pass

### Branching Strategy

- `main`: Stable, production-ready code
- `dev`: Integration branch for features
- `feature/feature-name`: For new feature development
- `bugfix/issue-number`: For bug fixes

### Commit Messages

Follow conventional commits format:

```
type(scope): short description

longer description if needed
```

Where `type` is one of:

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting, no logic change)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Build process or auxiliary tool changes

## Frontend Development

### Component Development

When building new components:

1. **Organize by Feature**:
   - Place related components in the appropriate feature folder
   - Keep component files small and focused
   - Use index.js files to organize exports

2. **Use Functional Components**:
   - Prefer functional components with hooks
   - Use custom hooks for reusable logic
   - Implement error boundaries for resilience

3. **Optimize Performance**:
   - Use React.memo for expensive renders
   - Implement useMemo and useCallback for optimizations
   - Lazy load components when appropriate

4. **Implement Progressive Loading**:
   - Show loading states during data fetching
   - Provide meaningful progress feedback
   - Handle errors gracefully with retry options

### React Context

The application uses React Context to manage state:

```jsx
// Example of using AppContext
import { useAppContext } from '../../context/AppContext';

function MovieComponent() {
  const { firstRunMovies, selectMovie, selectedMovieId } = useAppContext();

  return (
    <div>
      {firstRunMovies.map(movie => (
        <div
          key={movie.id}
          className={movie.id === selectedMovieId ? 'selected' : ''}
          onClick={() => selectMovie(movie.id)}
        >
          {movie.title}
        </div>
      ))}
    </div>
  );
}
```

### API Service Integration

Use the centralized API service:

```jsx
// Example of API service usage
import { chatApi } from '../../services/api';

async function handleSendMessage(message) {
  try {
    // Call appropriate API based on mode
    if (isFirstRunMode) {
      const response = await chatApi.getMoviesTheatersAndShowtimes(message, location);
      // Handle response...
    } else {
      const response = await chatApi.getMovieRecommendations(message);
      // Handle response...
    }
  } catch (error) {
    // Handle error...
  }
}
```

## Backend Development

### API Endpoint Development

When creating new API endpoints:

1. **Follow RESTful Principles**:
   - Use appropriate HTTP methods (GET, POST, PUT, DELETE)
   - Structure URLs around resources
   - Return proper status codes

2. **Implement Error Handling**:
   - Use try/except blocks for robust error handling
   - Return descriptive error messages in JSON format
   - Log errors with contextual information

3. **Input Validation**:
   - Validate all input data
   - Handle missing or malformed data gracefully
   - Provide clear error messages for validation failures

### CrewAI Integration

To extend the CrewAI functionality:

1. **Define New Agents**:
   - Create agent files in the agents directory
   - Define roles, goals, and backstories
   - Configure with appropriate LLM settings

2. **Create Custom Tools**:
   - Implement Pydantic-based tools
   - Define input schemas
   - Implement tool logic in _run method

3. **Update Task Sequences**:
   - Add new tasks to the crew
   - Configure task dependencies
   - Update the manager to handle new agents and tasks

## Testing Strategy

### Frontend Testing

For testing React components:

1. **Component Tests**:
   - Use React Testing Library for component tests
   - Test component rendering and user interactions
   - Mock context providers and API services

2. **Hook Tests**:
   - Test custom hooks with appropriate test harnesses
   - Verify state changes and side effects
   - Use act() for asynchronous operations

3. **Integration Tests**:
   - Test component integration with context
   - Verify data flow between components
   - Test error handling and edge cases

### Backend Testing

Test backend components with:

1. **Unit Tests**:
   - Test service methods with mocked dependencies
   - Test utility functions for correctness
   - Use pytest fixtures for common test data

2. **API Tests**:
   - Test API endpoints with Django test client
   - Verify response status codes and payload
   - Test different input scenarios including errors

3. **Integration Tests**:
   - Test CrewAI agent interactions
   - Test database operations
   - Verify end-to-end request processing

## Adding New Features

### Adding a New React Component

To add a new component:

1. Create the component file in the appropriate directory:

   ```jsx
   // frontend/src/components/Movies/NewMovieComponent.jsx
   import React from 'react';
   import { useAppContext } from '../../context/AppContext';

   function NewMovieComponent() {
     const { firstRunMovies } = useAppContext();

     return (
       <div className="new-movie-component">
         {/* Component implementation */}
       </div>
     );
   }

   export default NewMovieComponent;
   ```

2. Import and use the component:

   ```jsx
   // In parent component
   import NewMovieComponent from './NewMovieComponent';

   function ParentComponent() {
     return (
       <div>
         <NewMovieComponent />
       </div>
     );
   }
   ```

3. Add state to context if needed:

   ```jsx
   // In AppContext.jsx
   const [newFeatureState, setNewFeatureState] = useState(initialValue);

   // Add to provider value
   return (
     <AppContext.Provider value={{
       // Existing values...
       newFeatureState,
       setNewFeatureState
     }}>
       {children}
     </AppContext.Provider>
   );
   ```

### Adding a New API Endpoint

To add a new API endpoint:

1. Create the view function in views.py:

   ```python
   @csrf_exempt
   def new_feature_endpoint(request):
       """Handle requests for the new feature."""
       if request.method != 'POST':
           return JsonResponse({
               'status': 'error',
               'message': 'This endpoint only accepts POST requests'
           }, status=405)

       try:
           data = _parse_request_data(request)
           # Process the request...

           return JsonResponse({
               'status': 'success',
               'data': result_data
           })
       except Exception as e:
           logger.error(f"Error in new feature: {str(e)}")
           return JsonResponse({
               'status': 'error',
               'message': 'An error occurred'
           }, status=500)
   ```

2. Add the URL route in urls.py:

   ```python
   urlpatterns = [
       # Existing routes...
       path('new-feature/', views.new_feature_endpoint, name='new_feature'),
   ]
   ```

3. Add the API service method:

   ```javascript
   // In frontend/src/services/api.js
   newFeature: async (params) => {
     try {
       console.log('Calling new feature API');

       const response = await api.post('/new-feature/', params);

       if (!response.data || response.data.status !== 'success') {
         throw new Error(response.data?.message || 'Failed to process request');
       }

       return response.data;
     } catch (error) {
       console.error('Error in new feature:', error);
       throw error;
     }
   }
   ```

### Adding a New CrewAI Agent

To add a new agent to the system:

1. Create a new agent file in `chatbot/services/movie_crew/agents/`:

   ```python
   # new_agent.py
   from crewai import Agent
   from langchain_openai import ChatOpenAI

   class NewAgentName:
       """Description of the new agent."""

       @staticmethod
       def create(llm: ChatOpenAI) -> Agent:
           """Create the new agent."""
           return Agent(
               role="Role Name",
               goal="Goal description",
               backstory="""Detailed backstory for the agent""",
               verbose=True,
               llm=llm
           )
   ```

2. Create tools for the agent in `chatbot/services/movie_crew/tools/`:

   ```python
   # new_tool.py
   from crewai.tools import BaseTool
   from pydantic import BaseModel, Field

   class NewToolInput(BaseModel):
       """Input schema for the new tool."""
       param1: str = Field(default="", description="Parameter description")

   class NewTool(BaseTool):
       """Tool description."""

       name: str = "new_tool_name"
       description: str = "Tool description."
       args_schema: type[NewToolInput] = NewToolInput

       def _run(self, param1: str = "") -> str:
           """
           Tool implementation.

           Args:
               param1: Parameter description

           Returns:
               Tool output
           """
           # Implementation
           return result
   ```

3. Update the manager to include the new agent:

   ```python
   # In manager.py
   from .agents.new_agent import NewAgentName

   # In process_query method
   new_agent = NewAgentName.create(llm)
   new_tool = NewTool()

   # Create a task for the new agent
   new_task = Task(
       description="Task description",
       expected_output="Expected output description",
       agent=new_agent,
       tools=[new_tool]
   )

   # Update crew creation
   crew = Crew(
       agents=[movie_finder, recommender, theater_finder, new_agent],
       tasks=[find_movies_task, recommend_movies_task, find_theaters_task, new_task],
       verbose=True
   )
   ```

### Adding a New Conversation Mode

To add a new conversation mode:

1. Update the `Conversation` model in `models.py`:

   ```python
   class Conversation(models.Model):
       """A conversation between a user and the movie chatbot."""
       MODE_CHOICES = [
           ('first_run', 'First Run Movies'),
           ('casual', 'Casual Viewing'),
           ('new_mode', 'New Mode Name'),
       ]

       # Rest of the model...
   ```

2. Update the React frontend to support the new mode:

   ```jsx
   // In App.jsx, add new tab
   <li className="nav-item">
     <button
       className={`nav-link ${activeTab === 'new-mode' ? 'active' : ''}`}
       onClick={() => switchTab('new-mode')}
     >
       <i className="bi bi-icon-name me-2"></i>New Mode Name
     </button>
   </li>

   // Add tab content
   <div className={`tab-pane fade ${activeTab === 'new-mode' ? 'show active' : ''}`}>
     <div className="row">
       <div className="col-lg-8 mb-4 mb-lg-0">
         <Suspense fallback={<LoadingFallback />}>
           <ChatInterface />
         </Suspense>
       </div>
       <div className="col-lg-4">
         <Suspense fallback={<LoadingFallback />}>
           <NewModeComponent />
         </Suspense>
       </div>
     </div>
   </div>
   ```

3. Update AppContext to manage state for the new mode:

   ```jsx
   // In AppContext.jsx
   const [newModeMessages, setNewModeMessages] = useState([]);
   const [newModeData, setNewModeData] = useState([]);

   // Include in context value
   return (
     <AppContext.Provider value={{
       // Existing values...
       newModeMessages, setNewModeMessages,
       newModeData, setNewModeData,
     }}>
       {children}
     </AppContext.Provider>
   );
   ```

4. Create a new API endpoint for the mode:

   ```python
   @csrf_exempt
   def new_mode_endpoint(request):
       """Process a message in the new conversation mode."""
       # Implementation...
   ```

## Debugging

### Debugging React Frontend

1. **Browser Dev Tools**:
   - Use React DevTools extension for component inspection
   - Check network requests in Network tab
   - Monitor console.log statements in Console tab

2. **Debug Logging**:
   - Add strategic console.log statements
   - Use console.group/groupEnd for grouped logs
   - Log component renders and state changes

3. **Error Monitoring**:
   - Use Error Boundaries to catch component errors
   - Implement try/catch blocks for async operations
   - Add global error handler for unhandled errors

### Debugging Django Backend

1. **Django Debug Toolbar**:
   - Install and enable for local development
   - Monitor database queries and performance
   - Inspect request/response cycle

2. **Logging**:
   - Use Python's logging module
   - Set different log levels (DEBUG, INFO, ERROR)
   - Log contextual information with error messages

3. **API Testing**:
   - Use tools like Postman or Insomnia to test endpoints
   - Verify request and response formats
   - Test error handling with invalid inputs

### Debugging CrewAI

1. **Enable Verbose Mode**:
   - Set verbose=True on agents and crews
   - Monitor agent reasoning and task execution
   - Log intermediate outputs

2. **Tool Debugging**:
   - Test tools independently
   - Log tool inputs and outputs
   - Use mock inputs for testing

3. **Error Handling**:
   - Implement robust error handling in tools
   - Log detailed error messages
   - Add retry mechanisms for transient failures

## Contribution Guidelines

### Code Style

- **Python**: Follow PEP 8 style guidelines
- **JavaScript/JSX**: Follow ESLint configuration
- **General**:
  - Use meaningful variable and function names
  - Add comments for complex logic
  - Keep functions small and focused

### Documentation

- Update documentation when adding or changing features
- Document React components with JSDoc comments
- Add docstrings to Python classes and functions

### Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Run tests
5. Submit a pull request
6. Address review comments

### Code Review Checklist

- Does the code follow style guidelines?
- Are there appropriate tests?
- Is the documentation updated?
- Is the code secure and free of vulnerabilities?
- Does it work with both conversation modes?
- Is error handling implemented properly?

## Further Reading

- [CrewAI Documentation](https://docs.crewai.com/)
- [Django Documentation](https://docs.djangoproject.com/)
- [React Documentation](https://react.dev/)
- [React Query Documentation](https://tanstack.com/query/latest)
- [TMDb API Documentation](https://developer.themoviedb.org/docs)
- [SerpAPI Documentation](https://serpapi.com/search-api)
