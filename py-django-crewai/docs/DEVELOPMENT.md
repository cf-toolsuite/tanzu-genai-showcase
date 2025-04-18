# Developer Guide

This document provides guidelines and information for developers working on or contributing to the Movie Booking Chatbot application.

## Table of Contents

- [Development Environment Setup](#development-environment-setup)
- [Project Structure](#project-structure)
- [Core Concepts](#core-concepts)
- [Development Workflow](#development-workflow)
- [Testing Strategy](#testing-strategy)
- [Adding New Features](#adding-new-features)
- [Debugging](#debugging)
- [Contribution Guidelines](#contribution-guidelines)

## Development Environment Setup

### Prerequisites

- Python 3.10+ and pip
- Git
- A code editor (VS Code recommended)
- API keys for external services:
  - TMDb API key (sign-up [here](https://www.themoviedb.org/signup))
  - LLM API key (for local development)
  - SerpAPI key (optional, for real showtimes data)

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

3. Install dependencies:

   ```bash
   pip install -r requirements.txt
   ```

4. Create a `.env` file with your API keys and configuration:

   ```bash
   cp .env.example .env
   # Edit the .env file with your API keys and settings
   ```

5. Run migrations:

   ```bash
   python manage.py makemigrations chatbot
   python manage.py migrate
   ```

6. Start the development server:

   ```bash
   python manage.py runserver
   ```

7. Open your browser to `http://localhost:8000`

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

# Development settings
DEBUG=True                      # Enable debug mode
LOG_LEVEL=DEBUG                 # Set logging level
```

## Project Structure

The application follows a standard Django project structure with some additional organization for CrewAI integration:

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
│   │   └── serp_service.py            # SerpAPI integration
│   ├── models.py                      # Data models
│   ├── views.py                       # Request handlers
│   └── urls.py                        # URL routing
├── docs/                              # Documentation
├── movie_chatbot/                     # Django project settings
│   ├── settings.py                    # Application settings
│   ├── urls.py                        # Top-level URL routing
│   └── wsgi.py                        # WSGI configuration
├── static/                            # Static assets
│   ├── css/                           # Stylesheets
│   └── js/                            # JavaScript files
├── templates/                         # HTML templates
│   └── chatbot/                       # App-specific templates
├── manage.py                          # Django management script
├── requirements.txt                   # Python dependencies
└── manifest.yml                       # Cloud Foundry manifest
```

### Key Directories

- **chatbot/services/movie_crew/**: Contains the CrewAI implementation with agent definitions, tool implementations, and utilities
- **chatbot/models.py**: Django ORM models for data persistence
- **chatbot/views.py**: Request handlers and controller logic
- **static/js/app.js**: Frontend JavaScript for chat interface and UI interactions
- **templates/chatbot/**: HTML templates for the web interface

## Core Concepts

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

### Feature Development Process

1. **Planning**:
   - Define the feature scope
   - Identify affected components
   - Plan integration with existing code

2. **Implementation**:
   - Create or modify required files
   - Follow code style and patterns
   - Add appropriate documentation

3. **Testing**:
   - Write automated tests
   - Perform manual testing
   - Test with different conversation modes

4. **Code Review**:
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

## Testing Strategy

### Unit Tests

Write unit tests for individual components:

- Models
- Service methods
- Utility functions

Example:

```python
from django.test import TestCase
from chatbot.services.movie_crew.utils.json_parser import JsonParser

class JsonParserTests(TestCase):
    def test_parse_json_output_valid(self):
        """Test parsing valid JSON output."""
        json_str = '[{"title": "Test Movie", "id": 123}]'
        result = JsonParser.parse_json_output(json_str)
        self.assertEqual(len(result), 1)
        self.assertEqual(result[0]["title"], "Test Movie")
```

### Integration Tests

Test interactions between components:

- API integrations
- CrewAI agent interactions
- View functionality

Example:

```python
from django.test import TestCase, Client
from unittest.mock import patch

class ChatbotViewTests(TestCase):
    def setUp(self):
        self.client = Client()
        
    @patch('chatbot.services.movie_crew.MovieCrewManager.process_query')
    def test_send_message(self, mock_process_query):
        """Test the send_message view with a mocked crew manager."""
        # Mock the crew manager response
        mock_process_query.return_value = {
            "response": "Here are some movies.",
            "movies": [{"title": "Test Movie"}]
        }
        
        # Send a test message
        response = self.client.post('/send-message/', 
                                  {'message': 'Find action movies'}, 
                                  content_type='application/json')
        
        # Check the response
        self.assertEqual(response.status_code, 200)
        self.assertEqual(response.json()['status'], 'success')
```

### Browser Tests

Test frontend functionality:

- UI interactions
- AJAX requests
- Location detection

## Adding New Features

### Adding a New Agent

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

2. Update the UI in `templates/chatbot/index.html`:

   ```html
   <!-- Add a new tab -->
   <li class="nav-item" role="presentation">
     <button class="nav-link" id="new-mode-tab" data-bs-toggle="tab" data-bs-target="#new-mode" type="button" role="tab" aria-controls="new-mode" aria-selected="false">New Mode</button>
   </li>
   
   <!-- Add the tab content -->
   <div class="tab-pane fade" id="new-mode" role="tabpanel" aria-labelledby="new-mode-tab">
     <!-- New mode content -->
   </div>
   ```

3. Update the `send_message` view to handle the new mode:

   ```python
   # In views.py
   if mode == 'new_mode':
       conversation_id = request.session.get('new_mode_conversation_id')
       if not conversation_id:
           conversation = Conversation.objects.create(mode='new_mode')
           request.session['new_mode_conversation_id'] = conversation.id
       else:
           try:
               conversation = Conversation.objects.get(id=conversation_id)
           except Conversation.DoesNotExist:
               conversation = Conversation.objects.create(mode='new_mode')
               request.session['new_mode_conversation_id'] = conversation.id
   ```

4. Update the `MovieCrewManager` to handle the new mode:

   ```python
   # In movie_crew/manager.py
   def process_query(self, query: str, conversation_history: List[Dict[str, str]], mode: str = 'first_run') -> Dict[str, Any]:
       """Process a user query based on the specified mode."""
       
       if mode == 'first_run':
           # First Run mode logic
           pass
       elif mode == 'casual':
           # Casual Viewing mode logic
           pass
       elif mode == 'new_mode':
           # New mode logic
           pass
       else:
           # Default mode logic
           pass
   ```

## Debugging

### Logging

The application uses Python's logging module. To enable debug logging:

1. Set environment variables:

   ```bash
   DEBUG=True
   LOG_LEVEL=DEBUG
   ```

2. Access logs:
   - Development server: Output to console
   - Production: Cloud Foundry logs (`cf logs app-name`)

### Debug Toolbar

For local development, Django Debug Toolbar is available:

1. Install the package:

   ```bash
   pip install django-debug-toolbar
   ```

2. Add to `INSTALLED_APPS` in `settings.py`

### Troubleshooting CrewAI

When troubleshooting CrewAI issues:

1. Enable verbose mode on agents and crews:

   ```python
   agent = Agent(
       role="Role",
       goal="Goal",
       backstory="Backstory",
       verbose=True,  # Enable verbose logging
       llm=llm
   )
   
   crew = Crew(
       agents=[agent1, agent2],
       tasks=[task1, task2],
       verbose=True,  # Enable verbose logging
   )
   ```

2. Inspect task outputs:

   ```python
   # After crew execution
   for task in crew.tasks:
       print(f"Task: {task.description}")
       print(f"Output: {task.output}")
   ```

3. Test tools independently:

   ```python
   # Test a tool directly
   tool = SearchMoviesTool()
   result = tool._run("action movies")
   print(result)
   ```

## Contribution Guidelines

### Code Style

- Follow PEP 8 style guidelines
- Use consistent naming conventions:
  - CapWords for classes
  - lowercase_with_underscores for functions and variables
  - UPPERCASE for constants
- Add docstrings to all classes and methods (Google style docstrings)

### Documentation

- Update documentation when adding or changing features
- Document models, views, and services
- Add inline comments for complex logic

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

---

## Further Reading

- [CrewAI Documentation](https://docs.crewai.com/)
- [Django Documentation](https://docs.djangoproject.com/)
- [TMDb API Documentation](https://developer.themoviedb.org/docs)
- [SerpAPI Documentation](https://serpapi.com/docs)
