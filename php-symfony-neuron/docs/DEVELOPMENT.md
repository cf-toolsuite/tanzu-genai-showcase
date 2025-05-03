# Developer Guide

This document provides guidelines and information for developers working on or contributing to the Neuron AI + Symfony Company Research Application.

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

- PHP 8.3+ with required extensions (see [SETUP.md](./SETUP.md) for details)
- Composer
- Git
- A code editor (VS Code, PhpStorm recommended)
- API keys for external services:
  - An OpenAI-compatible LLM API key (for local development)
  - Financial data API key(s)

### Local Setup

1. Clone the repository:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase
   cd tanzu-genai-showcase/php-symfony-neuron
   ```

2. Install dependencies:

   ```bash
   composer install
   ```

3. Create a `.env.local` file with your API keys:

   ```bash
   APP_ENV=dev
   APP_SECRET=your_symfony_app_secret
   DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db
   GENAI_API_KEY=your_GENAI_API_KEY_here
   GENAI_BASE_URL=optional_custom_endpoint
   GENAI_MODEL=gpt-4o-mini
   FINANCIAL_API_KEY=your_financial_api_key
   ```

4. Set up the database:

   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:schema:create
   ```

5. Start the development server:

   ```bash
   symfony server:start
   ```

6. Open your browser to `http://localhost:8000`

### Environment Variables

Key environment variables for development:

```bash
# Required API keys
GENAI_API_KEY=your_llm_api_key_here
GENAI_BASE_URL=optional_custom_endpoint
GENAI_MODEL=gpt-4o-mini

# Financial APIs
FINANCIAL_API_KEY=your_financial_api_key
FINANCIAL_API_URL=https://api.example.com/financial

# Database configuration
DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db

# Development settings
APP_ENV=dev
APP_DEBUG=true
```

## Project Structure

The application follows a standard Symfony project structure with some additional organization:

```
php-symfony-neuron/
├── bin/                              # Symfony console and other executables
├── config/                           # Configuration files
│   ├── packages/                     # Package-specific configuration
│   ├── routes/                       # Route definitions
│   ├── bundles.php                   # Registered bundles
│   ├── services.yaml                 # Service definitions
│   └── routes.yaml                   # Main routes configuration
├── docs/                             # Documentation
│   ├── ARCHITECTURE.md               # System architecture
│   ├── DEVELOPMENT.md                # This developer guide
│   ├── DEPLOYMENT.md                 # Deployment instructions
│   ├── CONTRIBUTING.md               # Contribution guidelines
│   ├── SETUP.md                      # PHP/Composer setup guide
│   └── TROUBLESHOOTING.md            # Common issues and solutions
├── migrations/                       # Database migrations
├── public/                           # Web server document root
│   ├── index.php                     # Front controller
│   ├── js/                           # JavaScript assets
│   └── styles/                       # CSS assets
├── src/                              # Application source code
│   ├── Controller/                   # HTTP controllers
│   ├── Entity/                       # Doctrine entities
│   ├── Form/                         # Form definitions
│   ├── Repository/                   # Doctrine repositories
│   └── Service/                      # Business logic services
├── templates/                        # Twig templates
│   ├── base.html.twig                # Base template
│   ├── company/                      # Company-related templates
│   ├── dashboard/                    # Dashboard templates
│   ├── report/                       # Report templates
│   └── secfiling/                    # SEC filing templates
├── translations/                     # Localization files
├── var/                              # Generated files (cache, logs)
├── vendor/                           # Composer dependencies
├── .env                              # Environment variables
├── .env.example                      # Environment template
├── composer.json                     # Composer configuration
└── manifest.yml                      # Cloud Foundry manifest
```

## Core Concepts

### Symfony Framework

The application is built on the Symfony framework, which provides:

- Routing
- Dependency injection
- Form handling
- Security
- Database abstraction (via Doctrine)
- Template rendering (via Twig)

### Entity-Repository Pattern

Data access follows the Entity-Repository pattern:

- **Entities**: Represent database tables and business objects (e.g., `Company`, `FinancialData`)
- **Repositories**: Provide methods to query and persist entities

Example:

```php
// Entity
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    // Properties, getters, setters...
}

// Repository
class CompanyRepository extends ServiceEntityRepository
{
    public function findBySearchCriteria(string $term): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where('c.name LIKE :term OR c.tickerSymbol = :exact')
           ->setParameter('term', '%' . $term . '%')
           ->setParameter('exact', $term)
           ->orderBy('c.name', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
```

### Service Layer

Business logic is encapsulated in service classes that:

- Implement specific functionality
- Integrate with external services
- Process and transform data
- Handle errors and exceptions

Example:

```php
class NeuronAiService
{
    public function generateCompanyInfo(string $companyName): array
    {
        // Implementation details...
    }

    public function generateFinancialAnalysis(string $companyName, string $reportType): array
    {
        // Implementation details...
    }
}
```

### Form System

User input is handled using Symfony's form system:

```php
class CompanySearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add('searchTerm', TextType::class, [
            'required' => true,
            'label' => 'Search',
            'attr' => [
                'placeholder' => 'Company name or ticker symbol'
            ]
        ]);
    }
}
```

### Neuron AI Integration

The application integrates with LLM services through a dedicated service class:

- Prompt engineering
- API interaction
- Response parsing
- Error handling

## Development Workflow

### Feature Development Process

1. **Planning**:
   - Define the feature scope
   - Identify affected components
   - Plan integration with existing code

2. **Implementation**:
   - Create or modify controllers, services, entities
   - Implement business logic
   - Create templates and forms
   - Write or update tests

3. **Testing**:
   - Run unit tests
   - Perform manual testing
   - Validate with sample data

4. **Code Review**:
   - Submit pull request
   - Address review feedback
   - Ensure all tests pass

### Branching Strategy

- `main`: Stable, production-ready code
- `dev`: Development integration branch
- `feature/feature-name`: For new feature development
- `bugfix/bug-name`: For bug fixes

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

- Repositories
- Services
- Controllers
- Form types

Example:

```php
namespace App\Tests\Service;

use App\Service\NeuronAiService;
use PHPUnit\Framework\TestCase;

class NeuronAiServiceTest extends TestCase
{
    public function testGenerateCompanyInfo()
    {
        $service = new NeuronAiService('fake-api-key');

        // Test with mock responses
        $result = $service->generateCompanyInfo('Test Company');

        $this->assertArrayHasKey('industry', $result);
        $this->assertArrayHasKey('description', $result);
        // Additional assertions...
    }
}
```

### Functional Tests

Test complete feature workflows:

- Company search and import
- Financial data retrieval
- Report generation

Example:

```php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CompanyControllerTest extends WebTestCase
{
    public function testSearch()
    {
        $client = static::createClient();
        $client->request('GET', '/company/search');

        $this->assertResponseIsSuccessful();

        // Test form submission
        $client->submitForm('Search', [
            'company_search[searchTerm]' => 'AAPL'
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Search Results');
    }
}
```

### API Mocking

For tests that interact with external APIs:

- Use PHP-VCR or similar to record and replay HTTP interactions
- Create mock services for controlled testing
- Use fixtures for consistent test data

## Adding New Features

### Adding a New Entity

To add a new entity to the system:

1. Create a new entity class in `src/Entity/`:

   ```php
   namespace App\Entity;

   use Doctrine\ORM\Mapping as ORM;

   #[ORM\Entity(repositoryClass: SomeRepository::class)]
   class SomeEntity
   {
       #[ORM\Id]
       #[ORM\GeneratedValue]
       #[ORM\Column(type: 'integer')]
       private $id;

       #[ORM\Column(type: 'string', length: 255)]
       private $name;

       // Properties, getters, setters...
   }
   ```

2. Create a repository in `src/Repository/`:

   ```php
   namespace App\Repository;

   use App\Entity\SomeEntity;
   use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
   use Doctrine\Persistence\ManagerRegistry;

   class SomeRepository extends ServiceEntityRepository
   {
       public function __construct(ManagerRegistry $registry)
       {
           parent::__construct($registry, SomeEntity::class);
       }

       // Custom query methods...
   }
   ```

3. Create a migration:

   ```bash
   php bin/console make:migration
   php bin/console doctrine:migrations:migrate
   ```

### Adding a New Service

To add a new service:

1. Create a new service class in `src/Service/`:

   ```php
   namespace App\Service;

   class NewService
   {
       public function __construct(
           private SomeDependency $dependency
       ) {
       }

       public function someMethod()
       {
           // Implementation...
       }
   }
   ```

2. Register the service in `config/services.yaml` (if not using auto-configuration):

   ```yaml
   services:
       App\Service\NewService:
           arguments:
               $dependency: '@App\Service\SomeDependency'
   ```

### Adding a New Controller

To add a new controller:

1. Create a new controller class in `src/Controller/`:

   ```php
   namespace App\Controller;

   use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
   use Symfony\Component\HttpFoundation\Response;
   use Symfony\Component\Routing\Annotation\Route;

   #[Route('/some-route')]
   class SomeController extends AbstractController
   {
       #[Route('/', name: 'some_index', methods: ['GET'])]
       public function index(): Response
       {
           return $this->render('some/index.html.twig', [
               // Template variables...
           ]);
       }
   }
   ```

2. Create templates in `templates/some/`:

   ```twig
   {# templates/some/index.html.twig #}
   {% extends 'base.html.twig' %}

   {% block title %}Title{% endblock %}

   {% block body %}
       {# Template content... #}
   {% endblock %}
   ```

## Debugging

### Symfony Profiler

The Symfony Profiler provides detailed information about requests:

- Enable it in dev environment
- Access it via the toolbar at the bottom of the page
- Explore request details, database queries, and more

### Logging

Use the Symfony logging system:

```php
use Psr\Log\LoggerInterface;

public function someMethod(LoggerInterface $logger)
{
    try {
        // Some operation
    } catch (\Exception $e) {
        $logger->error('Operation failed', [
            'exception' => $e->getMessage(),
            'context' => 'relevant context'
        ]);
    }
}
```

Logs are stored in `var/log/dev.log` in development.

### Xdebug Integration

For PHP debugging:

1. Install and configure Xdebug
2. Configure your IDE to connect to Xdebug
3. Set breakpoints in your code
4. Enable debugging in your browser

### API Debugging

For debugging API interactions:

- Use the Symfony HttpClient profiler to inspect requests and responses
- Log request and response details
- Use tools like Postman to test API endpoints directly

## Contribution Guidelines

### Code Style

- Follow PSR-12 coding standards
- Use strict typing where possible
- Use consistent naming conventions
- Document code with PHPDoc comments

### Documentation

- Update documentation when adding or changing features
- Document public API methods
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
- Does it handle errors properly?
- Is the implementation efficient?

---

## Further Reading

- [Symfony Documentation](https://symfony.com/doc/current/index.html)
- [Doctrine ORM Documentation](https://www.doctrine-project.org/projects/doctrine-orm/en/current/index.html)
- [Twig Documentation](https://twig.symfony.com/doc/3.x/)
- [PHPUnit Documentation](https://phpunit.readthedocs.io/)
