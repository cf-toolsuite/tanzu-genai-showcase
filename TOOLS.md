# Tanzu GenAI Showcase: Tools Compendium

This document provides a comprehensive list of all tools, CLIs, and SDKs required for local development across all projects in the Tanzu GenAI Showcase repository. Each section includes installation links, documentation references, and specific version requirements.

## Table of Contents

- [Common Tools](#common-tools)
- [.NET Development](#net-development)
- [Go Development](#go-development)
- [Java Development](#java-development)
- [JavaScript/Node.js Development](#javascriptnodejs-development)
- [PHP Development](#php-development)
- [Python Development](#python-development)
- [Ruby Development](#ruby-development)
- [API Keys and External Services](#api-keys-and-external-services)
- [IDE Recommendations](#ide-recommendations)
- [Cloud Deployment](#cloud-deployment)

## Common Tools

### Git

Required for version control across all projects.

- **Version**: Latest
- **Installation**: [https://git-scm.com/downloads](https://git-scm.com/downloads)
- **Documentation**: [https://git-scm.com/doc](https://git-scm.com/doc)

### Cloud Foundry CLI

Required for deploying applications to Tanzu Platform for Cloud Foundry.

- **Version**: v8
- **Installation**: [https://docs.cloudfoundry.org/cf-cli/install-go-cli.html](https://docs.cloudfoundry.org/cf-cli/install-go-cli.html)
- **Documentation**: [https://docs.cloudfoundry.org/cf-cli/](https://docs.cloudfoundry.org/cf-cli/)

## .NET Development

Required for the `dotnet-extensions-ai` project.

### .NET SDK

- **Version**: .NET 9
- **Installation**: [https://dotnet.microsoft.com/download/dotnet/9.0](https://dotnet.microsoft.com/download/dotnet/9.0)
- **Documentation**: [https://learn.microsoft.com/en-us/dotnet](https://learn.microsoft.com/en-us/dotnet)

### Visual Studio or VS Code

- **Visual Studio 2025**
  - **Installation**: [https://visualstudio.microsoft.com/](https://visualstudio.microsoft.com/)
  - **Documentation**: [https://learn.microsoft.com/en-us/visualstudio/](https://learn.microsoft.com/en-us/visualstudio/)

- **VS Code with C# Extensions**
  - **Installation**: [https://code.visualstudio.com/](https://code.visualstudio.com/)
  - **C# Extension**: [https://marketplace.visualstudio.com/items?itemName=ms-dotnettools.csharp](https://marketplace.visualstudio.com/items?itemName=ms-dotnettools.csharp)
  - **Documentation**: [https://code.visualstudio.com/docs](https://code.visualstudio.com/docs)

### Semantic Kernel

- **Documentation**: [https://learn.microsoft.com/semantic-kernel/overview/](https://learn.microsoft.com/semantic-kernel/overview/)
- **GitHub**: [https://github.com/microsoft/semantic-kernel](https://github.com/microsoft/semantic-kernel)

### Steeltoe

- **Documentation**: [https://steeltoe.io/docs](https://steeltoe.io/docs)
- **GitHub**: [https://github.com/SteeltoeOSS/Steeltoe](https://github.com/SteeltoeOSS/Steeltoe)

## Go Development

Required for the `go-fiber-langchaingo` project.

### Go

- **Version**: 1.23.0+ (with Go 1.24.1 toolchain)
- **Installation**: [https://golang.org/dl/](https://golang.org/dl/)
- **Documentation**: [https://golang.org/doc/](https://golang.org/doc/)

### Fiber Web Framework

- **GitHub**: [https://github.com/gofiber/fiber](https://github.com/gofiber/fiber)
- **Documentation**: [https://docs.gofiber.io/](https://docs.gofiber.io/)

### LangChainGo

- **GitHub**: [https://github.com/tmc/langchaingo](https://github.com/tmc/langchaingo)
- **Documentation**: [https://pkg.go.dev/github.com/tmc/langchaingo](https://pkg.go.dev/github.com/tmc/langchaingo)

## Java Development

Required for the `java-spring-ai-mcp` and `java-spring-langgraph-mcp-angular` projects.

### Java JDK

- **Version**: Java 17 (minimum) / Java 21 (recommended)
- **Installation**: [https://adoptium.net/](https://adoptium.net/) or [https://www.oracle.com/java/technologies/downloads/](https://www.oracle.com/java/technologies/downloads/)
- **Documentation**: [https://docs.oracle.com/en/java/](https://docs.oracle.com/en/java/)

### Maven

- **Version**: 3.9.4
- **Installation**: [https://maven.apache.org/download.cgi](https://maven.apache.org/download.cgi)
- **Documentation**: [https://maven.apache.org/guides/](https://maven.apache.org/guides/)

### Spring Boot

- **Version**: 3.4.4
- **Documentation**: [https://docs.spring.io/spring-boot/docs/current/reference/html/](https://docs.spring.io/spring-boot/docs/current/reference/html/)
- **GitHub**: [https://github.com/spring-projects/spring-boot](https://github.com/spring-projects/spring-boot)

### Spring AI

- **Documentation**: [https://docs.spring.io/spring-ai/reference/](https://docs.spring.io/spring-ai/reference/)
- **GitHub**: [https://github.com/spring-projects/spring-ai](https://github.com/spring-projects/spring-ai)

### LangGraph4j (for java-spring-langgraph-mcp-angular)

- **GitHub**: [https://github.com/bsorrentino/langgraph4j](https://github.com/bsorrentino/langgraph4j)

### Model Context Protocol (MCP)

- **Version**: 0.9.0
- **GitHub**: [https://github.com/modelcontextprotocol/mcp](https://github.com/modelcontextprotocol/mcp)

## JavaScript/Node.js Development

Required for the `js-langchain-react` project and the frontend of `java-spring-langgraph-mcp-angular`.

### Node.js and npm

- **Version**: Node.js 18+ (for js-langchain-react) / Node.js 20+ (for Angular)
- **Installation**: [https://nodejs.org/en/download/](https://nodejs.org/en/download/)
- **Documentation**: [https://nodejs.org/en/docs/](https://nodejs.org/en/docs/)

### React

- **Version**: 18.3.1
- **Documentation**: [https://react.dev/](https://react.dev/)
- **GitHub**: [https://github.com/facebook/react](https://github.com/facebook/react)

### Angular (for java-spring-langgraph-mcp-angular)

- **Version**: 17
- **Installation**: `npm install -g @angular/cli`
- **Documentation**: [https://angular.io/docs](https://angular.io/docs)
- **GitHub**: [https://github.com/angular/angular](https://github.com/angular/angular)

### LangChain JS

- **Version**: 0.3.20
- **Documentation**: [https://js.langchain.com/docs/](https://js.langchain.com/docs/)
- **GitHub**: [https://github.com/langchain-ai/langchainjs](https://github.com/langchain-ai/langchainjs)

## PHP Development

Required for the `php-symfony-neuron` project.

### PHP

- **Version**: 8.3
- **Installation**: [https://www.php.net/downloads](https://www.php.net/downloads)
- **Documentation**: [https://www.php.net/docs.php](https://www.php.net/docs.php)

### Composer

- **Installation**: [https://getcomposer.org/download/](https://getcomposer.org/download/)
- **Documentation**: [https://getcomposer.org/doc/](https://getcomposer.org/doc/)

### Symfony

- **Version**: 7.2
- **Installation**: [https://symfony.com/download](https://symfony.com/download)
- **Documentation**: [https://symfony.com/doc/current/index.html](https://symfony.com/doc/current/index.html)

### Neuron AI

- **Package**: inspector-apm/neuron-ai
- **Documentation**: [https://inspector.dev/neuron-ai/](https://inspector.dev/neuron-ai/)

## Python Development

Required for the `py-django-crewai` project.

### Python

- **Version**: 3.x (compatible with Django 5.2)
- **Installation**: [https://www.python.org/downloads/](https://www.python.org/downloads/)
- **Documentation**: [https://docs.python.org/3/](https://docs.python.org/3/)

### pip

- **Installation**: Included with Python
- **Documentation**: [https://pip.pypa.io/en/stable/](https://pip.pypa.io/en/stable/)

### Django

- **Version**: 5.2
- **Documentation**: [https://docs.djangoproject.com/en/5.2/](https://docs.djangoproject.com/en/5.2/)
- **GitHub**: [https://github.com/django/django](https://github.com/django/django)

### CrewAI

- **Version**: 0.114.0
- **Installation**: `pip install crewai==0.114.0`
- **Documentation**: [https://docs.crewai.com/](https://docs.crewai.com/)
- **GitHub**: [https://github.com/crewai/crewai](https://github.com/crewai/crewai)

### LangChain

- **Version**: 0.3.23
- **Installation**: `pip install langchain==0.3.23 langchain-openai==0.3.14`
- **Documentation**: [https://python.langchain.com/docs/](https://python.langchain.com/docs/)
- **GitHub**: [https://github.com/langchain-ai/langchain](https://github.com/langchain-ai/langchain)

## Ruby Development

Required for the `ruby-sinatra-fastmcp` project.

### Ruby

- **Version**: 3.3.6
- **Installation**: [https://www.ruby-lang.org/en/documentation/installation/](https://www.ruby-lang.org/en/documentation/installation/)
- **Documentation**: [https://www.ruby-lang.org/en/documentation/](https://www.ruby-lang.org/en/documentation/)

### Bundler

- **Installation**: `gem install bundler`
- **Documentation**: [https://bundler.io/](https://bundler.io/)

### Sinatra

- **Version**: 4.1
- **Documentation**: [http://sinatrarb.com/documentation.html](http://sinatrarb.com/documentation.html)
- **GitHub**: [https://github.com/sinatra/sinatra](https://github.com/sinatra/sinatra)

### Fast-MCP

- **Version**: 1.0.0
- **GitHub**: [https://github.com/modelcontextprotocol/fast-mcp-ruby](https://github.com/modelcontextprotocol/fast-mcp-ruby)

## API Keys and External Services

Various projects require API keys for external services:

### AI/LLM Services

- **OpenAI API Key**: Required for multiple projects
  - **Sign up**: [https://platform.openai.com/signup](https://platform.openai.com/signup)
  - **Documentation**: [https://platform.openai.com/docs/](https://platform.openai.com/docs/)

### Data and Information APIs

- **Google Maps API Key**: Required for dotnet-extensions-ai
  - **Sign up**: [https://developers.google.com/maps/documentation/javascript/get-api-key](https://developers.google.com/maps/documentation/javascript/get-api-key)
  - **Documentation**: [https://developers.google.com/maps/documentation](https://developers.google.com/maps/documentation)

- **Congress.gov API Key**: Required for go-fiber-langchaingo
  - **Sign up**: [https://api.congress.gov/sign-up/](https://api.congress.gov/sign-up/)
  - **Documentation**: [https://api.congress.gov/](https://api.congress.gov/)

- **AviationStack API Key**: Required for java-spring-ai-mcp and ruby-sinatra-fastmcp
  - **Sign up**: [https://aviationstack.com/signup](https://aviationstack.com/signup)
  - **Documentation**: [https://aviationstack.com/documentation](https://aviationstack.com/documentation)

- **Ticketmaster API Key**: Required for java-spring-langgraph-mcp-angular
  - **Sign up**: [https://developer.ticketmaster.com/products-and-docs/apis/getting-started/](https://developer.ticketmaster.com/products-and-docs/apis/getting-started/)
  - **Documentation**: [https://developer.ticketmaster.com/products-and-docs/apis/discovery-api/v2/](https://developer.ticketmaster.com/products-and-docs/apis/discovery-api/v2/)

- **API Ninjas Cities API Key**: Required for java-spring-langgraph-mcp-angular
  - **Sign up**: [https://api-ninjas.com/register](https://api-ninjas.com/register)
  - **Documentation**: [https://api-ninjas.com/api/city](https://api-ninjas.com/api/city)

- **News API Key**: Required for js-langchain-react and php-symfony-neuron
  - **Sign up**: [https://newsapi.org/register](https://newsapi.org/register)
  - **Documentation**: [https://newsapi.org/docs](https://newsapi.org/docs)

- **Stock API Key**: Required for php-symfony-neuron
  - Various providers available (Alpha Vantage, Finnhub, etc.)

- **Edgar API Key**: Required for php-symfony-neuron
  - SEC EDGAR database access

- **LinkedIn API Credentials**: Required for php-symfony-neuron
  - **Sign up**: [https://www.linkedin.com/developers/](https://www.linkedin.com/developers/)
  - **Documentation**: [https://learn.microsoft.com/en-us/linkedin/](https://learn.microsoft.com/en-us/linkedin/)

- **TMDb API Key**: Required for py-django-crewai
  - **Sign up**: [https://www.themoviedb.org/signup](https://www.themoviedb.org/signup)
  - **Documentation**: [https://developer.themoviedb.org/docs](https://developer.themoviedb.org/docs)

- **SerpAPI API Key**: Required for py-django-crewai
  - **Sign up**: [https://serpapi.com/users/sign_up](https://serpapi.com/users/sign_up)
  - **Documentation**: [https://serpapi.com/docs](https://serpapi.com/docs)

## IDE Recommendations

### Visual Studio Code

Recommended for most projects due to its versatility and extension ecosystem.

- **Installation**: [https://code.visualstudio.com/](https://code.visualstudio.com/)
- **Documentation**: [https://code.visualstudio.com/docs](https://code.visualstudio.com/docs)

#### Recommended Extensions:

- **For .NET**: C# Dev Kit, .NET Core Tools
- **For Go**: Go extension
- **For Java**: Extension Pack for Java
- **For JavaScript/TypeScript**: ESLint, Prettier
- **For PHP**: PHP Intelephense, PHP Debug
- **For Python**: Python extension, Pylance
- **For Ruby**: Ruby, Ruby Solargraph
- **For Cloud Foundry**: Cloud Foundry Tools

### JetBrains IDEs

- **For .NET**: [Rider](https://www.jetbrains.com/rider/)
- **For Go**: [GoLand](https://www.jetbrains.com/go/)
- **For Java**: [IntelliJ IDEA](https://www.jetbrains.com/idea/)
- **For JavaScript/TypeScript**: [WebStorm](https://www.jetbrains.com/webstorm/)
- **For PHP**: [PhpStorm](https://www.jetbrains.com/phpstorm/)
- **For Python**: [PyCharm](https://www.jetbrains.com/pycharm/)
- **For Ruby**: [RubyMine](https://www.jetbrains.com/ruby/)

## Cloud Deployment

### Tanzu Platform for Cloud Foundry

All projects are configured for deployment to Tanzu Platform for Cloud Foundry.

- **CLI**: Cloud Foundry CLI v8
- **Documentation**: [https://docs.cloudfoundry.org/](https://docs.cloudfoundry.org/)
- **Deployment Guide**: Consult the [guide](DEPLOY.md) in this repository

### Required Environment Variables

Each project requires specific environment variables to be set, either directly or through service bindings. Consult the aforementioned [guide](DEPLOY.md) for details on the required environment variables for each project.
