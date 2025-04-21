# Tanzu GenAI Showcase: CI Status Badge Examples

This document provides examples of how to implement CI status badges across different CI providers for each project in this repository.

## GitHub Actions Badge Examples

GitHub Actions badges are already implemented in most project README.md files. The standard format is:

```markdown
![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/workflow-name.yml/badge.svg)
```

Examples for specific projects:

### java-spring-ai-mcp

```markdown
![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/java-spring-ai-mcp.yml/badge.svg)
```

### go-fiber-langchaingo

```markdown
![Github Action CI Workflow Status](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/go-fiber-langchaingo.yml/badge.svg)
```

## GitLab CI Badge Examples

For each project, you would use this format after migrating to GitLab:

```markdown
![GitLab CI Pipeline Status](https://gitlab.com/username/tanzu-genai-showcase/badges/main/pipeline.svg)
```

For specific job status within a project pipeline:

### java-spring-ai-mcp

```markdown
![java-spring-ai-mcp CI Status](https://gitlab.com/username/tanzu-genai-showcase/badges/main/pipeline.svg?job=build-java-spring-ai-mcp)
```

### go-fiber-langchaingo

```markdown
![go-fiber-langchaingo CI Status](https://gitlab.com/username/tanzu-genai-showcase/badges/main/pipeline.svg?job=build-go-fiber-langchaingo)
```

## Bitbucket Pipelines Badge Examples

For each project after migrating to Bitbucket:

```markdown
![Bitbucket Pipelines Status](https://bitbucket.org/workspace/tanzu-genai-showcase/pipelines/results/branch/main.svg)
```

Using shields.io for custom styling:

### java-spring-ai-mcp

```markdown
![java-spring-ai-mcp Build Status](https://img.shields.io/bitbucket/pipelines/workspace/tanzu-genai-showcase/main?label=java-spring-ai-mcp)
```

### go-fiber-langchaingo

```markdown
![go-fiber-langchaingo Build Status](https://img.shields.io/bitbucket/pipelines/workspace/tanzu-genai-showcase/main?label=go-fiber-langchaingo)
```

## Jenkins Badge Examples

For each project after setting up in Jenkins with the Embeddable Build Status plugin:

```markdown
[![Build Status](https://jenkins.example.com/buildStatus/icon?job=tanzu-genai-showcase/main&subject=build)](https://jenkins.example.com/job/tanzu-genai-showcase/job/main/)
```

For specific project pipelines:

### java-spring-ai-mcp

```markdown
[![java-spring-ai-mcp Build Status](https://jenkins.example.com/buildStatus/icon?job=tanzu-genai-showcase/main/java-spring-ai-mcp&subject=java-spring-ai-mcp)](https://jenkins.example.com/job/tanzu-genai-showcase/job/main/job/java-spring-ai-mcp/)
```

### go-fiber-langchaingo

```markdown
[![go-fiber-langchaingo Build Status](https://jenkins.example.com/buildStatus/icon?job=tanzu-genai-showcase/main/go-fiber-langchaingo&subject=go-fiber-langchaingo)](https://jenkins.example.com/job/tanzu-genai-showcase/job/main/job/go-fiber-langchaingo/)
```

## Adding Multiple Badges

You may want to show status for multiple CI providers. Here's an example of how to include badges for all providers:

```markdown
# Project Name

![Github Actions](https://github.com/cf-toolsuite/tanzu-genai-showcase/actions/workflows/java-spring-ai-mcp.yml/badge.svg)
![GitLab CI](https://gitlab.com/username/tanzu-genai-showcase/badges/main/pipeline.svg?job=build-java-spring-ai-mcp)
![Bitbucket Pipelines](https://img.shields.io/bitbucket/pipelines/workspace/tanzu-genai-showcase/main?label=java-spring-ai-mcp)
[![Jenkins](https://jenkins.example.com/buildStatus/icon?job=tanzu-genai-showcase/main/java-spring-ai-mcp&subject=java-spring-ai-mcp)](https://jenkins.example.com/job/tanzu-genai-showcase/job/main/job/java-spring-ai-mcp/)
```

## Additional Badge Options

You can also use shields.io to create consistent badge styles across providers:

```markdown
![GitHub Actions](https://img.shields.io/github/actions/workflow/status/cf-toolsuite/tanzu-genai-showcase/java-spring-ai-mcp.yml?label=GitHub)
![GitLab CI](https://img.shields.io/gitlab/pipeline-status/username/tanzu-genai-showcase?branch=main&label=GitLab)
![Bitbucket](https://img.shields.io/bitbucket/pipelines/workspace/tanzu-genai-showcase/main?label=Bitbucket)
![Jenkins](https://img.shields.io/jenkins/build?jobUrl=https%3A%2F%2Fjenkins.example.com%2Fjob%2Ftanzu-genai-showcase%2Fjob%2Fmain%2F&label=Jenkins)
```

Remember to replace `username`, `workspace`, and URLs with your actual repository details.
