# Contributing Guidelines

This document provides guidelines and information for contributors to the Neuron AI + Symfony Company Research Application.

## Table of Contents

- [Code of Conduct](#code-of-conduct)
- [How to Contribute](#how-to-contribute)
- [Development Environment](#development-environment)
- [Coding Standards](#coding-standards)
- [Git Workflow](#git-workflow)
- [Pull Request Process](#pull-request-process)
- [Testing Guidelines](#testing-guidelines)
- [Documentation Requirements](#documentation-requirements)
- [Release Process](#release-process)
- [Community](#community)

## Code of Conduct

Our project is committed to fostering an open and welcoming environment. We expect all participants to adhere to the following principles:

- **Respect**: Treat all contributors and users with respect and consideration.
- **Inclusivity**: Welcome contributions from people of all backgrounds and experience levels.
- **Collaboration**: Work together constructively and value diverse perspectives.
- **Thoughtful Communication**: Communicate professionally and considerately.

Unacceptable behaviors include:

- Harassment, discrimination, or derogatory comments
- Personal attacks or trolling
- Public or private harassment
- Any conduct that would be inappropriate in a professional setting

## How to Contribute

There are many ways to contribute to the project:

### Reporting Bugs

Before submitting a bug report:

1. Check the [TROUBLESHOOTING.md](./TROUBLESHOOTING.md) document for known issues
2. Search existing issues to avoid duplicates
3. Gather relevant information: PHP version, Symfony version, error messages, etc.

When submitting a bug report:

1. Use a clear, descriptive title
2. Provide detailed steps to reproduce the issue
3. Include error messages and logs
4. Describe expected vs. actual behavior
5. Share environment details

### Suggesting Enhancements

Suggestions for enhancements are welcome. Please include:

1. Clear problem statement (what issue is being solved)
2. Description of the proposed solution
3. Potential benefits and drawbacks
4. Any alternative solutions considered

### Code Contributions

Code contributions should follow these steps:

1. Check existing issues and discussions to avoid duplicating work
2. For significant changes, open an issue first to discuss your approach
3. Follow the [development workflow](#development-workflow)
4. Submit a pull request with a clear description

## Development Environment

Follow the setup instructions in the [DEVELOPMENT.md](./DEVELOPMENT.md) document to prepare your development environment.

### Prerequisites

- PHP 8.3+
- Composer
- Git
- Familiarity with Symfony framework
- Development editor (VS Code, PhpStorm recommended)

## Coding Standards

### PHP Code Style

We follow PSR-12 coding standards. Key points:

- Use 4 spaces for indentation, not tabs
- Lines should not exceed 120 characters
- Use camelCase for method names and variables
- Use CapitalizedCase for class names
- Add appropriate docblocks for classes and methods

### Symfony Best Practices

Follow the [Symfony Best Practices](https://symfony.com/doc/current/best_practices.html), including:

- Controllers should be thin, with business logic in services
- Use dependency injection rather than static calls
- Use annotations/attributes for routing and entity definitions
- Use Symfony's Form component for form handling
- Follow Doctrine's best practices for database access

### PHPDoc Comments

Document your code with PHPDoc comments:

```php
/**
 * Generates financial analysis for a company
 *
 * This method uses the Neuron AI service to analyze and generate
 * financial insights for the specified company.
 *
 * @param string $companyName The name of the company to analyze
 * @param string $reportType The type of report to generate (e.g., '10-K', 'quarterly')
 *
 * @return array The generated financial analysis data
 *
 * @throws \Exception If the AI service fails to generate the analysis
 */
public function generateFinancialAnalysis(string $companyName, string $reportType): array
{
    // Method implementation...
}
```

## Git Workflow

### Branching Strategy

We use a feature branch workflow:

- `main`: Stable, production-ready code
- `dev`: Development integration branch
- `feature/feature-name`: For new feature development
- `bugfix/bug-name`: For bug fixes
- `docs/change-description`: For documentation changes

### Commit Messages

Use conventional commits format:

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

Examples:

```
feat(company): add competitor analysis feature
fix(financial): correct stock price calculation
docs(setup): update PHP extension requirements
```

### Development Workflow

1. Fork the repository
2. Create a feature branch from `dev`
3. Make your changes
4. Run tests to ensure your changes don't break existing functionality
5. Push your branch to your fork
6. Submit a pull request from your branch to our `dev` branch

## Pull Request Process

When submitting a pull request:

1. Update the README.md or documentation with details of significant changes
2. Add or update tests for the changed functionality
3. Ensure all tests pass
4. Get at least one code review from a maintainer
5. Respond to feedback and make requested changes
6. Once approved, a maintainer will merge your changes

### Pull Request Template

When creating a pull request, please include:

```markdown
## Description
Brief description of the changes.

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Code refactoring
- [ ] Other (please describe):

## How Has This Been Tested?
Describe the tests you ran to verify your changes.

## Checklist:
- [ ] My code follows the project's coding standards
- [ ] I have added tests that prove my fix/feature works
- [ ] New and existing unit tests pass with my changes
- [ ] I have updated the documentation accordingly
- [ ] My changes don't introduce new warnings or deprecation notices
```

## Testing Guidelines

### Types of Tests

We use multiple testing approaches:

1. **Unit Tests**: Test individual components in isolation
2. **Functional Tests**: Test complete feature workflows
3. **Integration Tests**: Test interactions between components

### Test Coverage

Aim for high test coverage with these priorities:

1. Core business logic in services
2. Repository methods with complex queries
3. Controller actions with complex logic
4. Edge cases and error scenarios

### Running Tests

Use PHPUnit to run tests:

```bash
# Run all tests
php bin/phpunit

# Run specific test suite
php bin/phpunit --testsuite=Unit

# Run tests with coverage report
php bin/phpunit --coverage-html coverage
```

### Testing External Services

For tests involving external services:

- Use mocks or test doubles
- Avoid making real API calls in tests
- Consider using PHP-VCR for API interaction tests

## Documentation Requirements

Documentation is a critical part of any contribution. Please:

1. Document all public APIs with PHPDoc comments
2. Update README.md for user-facing changes
3. Update relevant documentation in the docs/ directory
4. Include example code when applicable
5. Document configuration options and default values

### Key Documentation Files

| File | Purpose |
|------|---------|
| README.md | Overview, quick start |
| docs/SETUP.md | Environment setup |
| docs/ARCHITECTURE.md | System architecture |
| docs/DEVELOPMENT.md | Development guide |
| docs/DEPLOYMENT.md | Deployment procedures |
| docs/TROUBLESHOOTING.md | Common issues and solutions |

## Release Process

Our release process follows these steps:

1. **Feature Freeze**: No new features added to `dev` branch
2. **Stabilization**: Bug fixes and documentation improvements
3. **Release Candidate**: Tagged from `dev` branch for final testing
4. **Release**: Merge to `main` and tag with version number
5. **Announcement**: Release notes published

### Versioning

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** version for incompatible API changes
- **MINOR** version for new functionality in a backward compatible manner
- **PATCH** version for backward compatible bug fixes

## Community

### Getting Help

If you have questions or need help:

1. Check existing documentation
2. Search for existing issues
3. Open a new issue with the "question" label

### Recognition

Contributors are acknowledged in:

1. Release notes
2. Contributors section in the README.md
3. Community highlights in project communications

### Communication Channels

- **Issues**: For bug reports and feature discussions
- **Pull Requests**: For code review discussions
- **Discussions**: For general questions and community conversation

Thank you for considering contributing to our project! Your efforts help make this project better for everyone.
