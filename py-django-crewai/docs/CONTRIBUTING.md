# Contributing to the Movie Chatbot

Thank you for your interest in contributing to the Movie Booking Chatbot project! This document provides guidelines and workflows for contributing effectively.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [Getting Started](#getting-started)
- [Development Workflow](#development-workflow)
- [Pull Request Process](#pull-request-process)
- [Coding Standards](#coding-standards)
- [Testing Guidelines](#testing-guidelines)
- [Documentation Requirements](#documentation-requirements)
- [Community and Communication](#community-and-communication)

## Code of Conduct

Our project is committed to fostering an open and welcoming environment. By participating, you agree to:

- Be respectful and inclusive in your communication
- Accept constructive feedback gracefully
- Focus on what's best for the community and users
- Show empathy towards other community members

## Getting Started

### Setting Up the Development Environment

1. Fork the repository on GitHub
2. Clone your fork locally:

   ```bash
   git clone https://github.com/YOUR-USERNAME/tanzu-genai-showcase.git
   cd tanzu-genai-showcase/py-django-crewai
   ```

3. Set up the upstream remote:

   ```bash
   git remote add upstream https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

4. Create and activate a virtual environment:

   ```bash
   python -m venv venv
   source venv/bin/activate  # On Windows: venv\Scripts\activate
   ```

5. Install dependencies:

   ```bash
   pip install -r requirements.txt
   ```

6. Create a `.env` file with necessary API keys (see [README.md](../README.md) for details)

7. Run migrations:

   ```bash
   python manage.py makemigrations chatbot
   python manage.py migrate
   ```

### Understanding the Project Structure

Review the [ARCHITECTURE.md](./ARCHITECTURE.md) and [DEVELOPMENT.md](./DEVELOPMENT.md) documents to understand the project's structure, components, and workflows.

## Development Workflow

### Branching Strategy

- `main`: Production-ready code
- `dev`: Integration branch for features
- `feature/feature-name`: For new feature development
- `bugfix/issue-number`: For bug fixes
- `docs/topic`: For documentation improvements

### Feature Development Process

1. **Create a Feature Branch**:

   ```bash
   git checkout dev
   git pull upstream dev
   git checkout -b feature/your-feature-name
   ```

2. **Make Changes**:
   - Implement your feature or fix
   - Add appropriate tests
   - Update documentation as needed

3. **Commit Your Changes**:
   - Use conventional commit messages (see below)
   - Keep commits focused and atomic

4. **Test Locally**:
   - Run tests to ensure your changes work
   - Check for code style compliance

5. **Push to Your Fork**:

   ```bash
   git push origin feature/your-feature-name
   ```

6. **Submit a Pull Request**:
   - Create a PR from your feature branch to the `dev` branch
   - Complete the PR template with all required information

### Commit Message Conventions

Follow the [Conventional Commits](https://www.conventionalcommits.org/) format:

```text
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

Example:

```
feat(theater-finder): add timezone support for showtimes

- Adds automatic timezone detection using browser API
- Converts all showtimes to user's local timezone
- Updates UI to display timezone information
```

## Pull Request Process

1. **Before Submitting a PR**:
   - Ensure all tests pass
   - Update documentation to reflect changes
   - Add yourself to CONTRIBUTORS.md if it's your first contribution

2. **PR Template**:
   - Fill out the entire PR template
   - Link to any related issues
   - Provide clear description of changes
   - Include screenshots for UI changes

3. **Code Review Process**:
   - At least one maintainer must review and approve
   - Address all comments and suggestions
   - Make requested changes in new commits
   - Once approved, squash commits if requested

4. **After Merging**:
   - Delete your feature branch
   - Update your local repository:

     ```bash
     git checkout dev
     git pull upstream dev
     ```

## Coding Standards

### Python Style Guidelines

- Follow [PEP 8](https://www.python.org/dev/peps/pep-0008/) style guide
- Use 4 spaces for indentation (no tabs)
- Maximum line length of 100 characters
- Use Google-style docstrings for documentation

### Docstring Format Example

```python
def function_name(param1, param2):
    """
    Brief description of function.

    More detailed explanation if needed.

    Args:
        param1: Description of param1
        param2: Description of param2

    Returns:
        Description of return value

    Raises:
        ExceptionType: When and why this exception is raised
    """
    # Function implementation
```

### JavaScript Style Guidelines

- Use ES6+ features when possible
- Follow [Airbnb JavaScript Style Guide](https://github.com/airbnb/javascript)
- Use 2 spaces for indentation in JavaScript files
- Add JSDoc comments for functions

### Template/HTML Guidelines

- Use 2 spaces for indentation in HTML files
- Follow semantic HTML principles
- Maintain accessibility standards (WCAG 2.1)
- Keep templates DRY using template inheritance

## Testing Guidelines

### Test Requirements

- All new features must include tests
- Bug fixes should include a test that verifies the fix
- Maintain or improve test coverage percentage

### Types of Tests

1. **Unit Tests**:
   - Test individual components in isolation
   - Mock external dependencies
   - Example:

     ```python
     from django.test import TestCase
     from chatbot.services.movie_crew.utils import JsonParser

     class JsonParserTest(TestCase):
         def test_parse_json_output(self):
             input_str = '[{"title": "Test Movie"}]'
             result = JsonParser.parse_json_output(input_str)
             self.assertEqual(result[0]['title'], 'Test Movie')
     ```

2. **Integration Tests**:
   - Test interactions between components
   - Example:

     ```python
     from django.test import TestCase, Client
     from unittest.mock import patch

     class ChatViewTests(TestCase):
         def test_send_message(self):
             with patch('chatbot.services.movie_crew.MovieCrewManager.process_query') as mock_process:
                 mock_process.return_value = {"response": "Test response", "movies": []}

                 client = Client()
                 response = client.post('/send-message/',
                                      {'message': 'Test message'},
                                      content_type='application/json')

                 self.assertEqual(response.status_code, 200)
     ```

3. **Frontend Tests**:
   - Test UI components and interactions
   - Check JavaScript functionality

### Running Tests

```bash
# Run all tests
python manage.py test

# Run specific test file
python manage.py test chatbot.tests.test_views

# Run with coverage
coverage run --source='.' manage.py test
coverage report
```

## Documentation Requirements

### Code Documentation

- All modules should have a module-level docstring
- All classes and functions should be documented
- Complex code sections should include explanatory comments

### User-Facing Documentation

- Update README.md with new features or changed behavior
- Keep the Architecture document up to date
- Add usage examples for new features

### When to Update Documentation

Documentation should be updated:

- When adding new features
- When changing existing behavior
- When deprecating functionality
- When fixing bugs that affect user experience

## Community and Communication

### Getting Help

- Use the GitHub Discussions section for questions
- Check existing issues before creating new ones
- Be specific about your problem or question

### Issue Reporting

When reporting issues, please include:

1. A clear, descriptive title
2. Detailed steps to reproduce the problem
3. Expected vs. actual behavior
4. Environment details (OS, browser, etc.)
5. Screenshots or logs if applicable

### Feature Requests

When suggesting features:

1. Describe the problem your feature would solve
2. Explain how your solution would work
3. Provide examples of similar features in other projects
4. Consider the impact on existing functionality

---

Thank you for contributing to the Movie Chatbot project! Your efforts help make this application better for everyone.
