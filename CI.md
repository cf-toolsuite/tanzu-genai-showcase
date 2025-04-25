# Tanzu GenAI Showcase: Continuous Integration Guide

This repository contains CI/CD configurations for multiple CI providers:

- GitHub Actions (default)
- GitLab CI/CD
- Bitbucket Pipelines
- Jenkins
- Concourse CI

Each project contains its own CI configuration files in its respective `ci` subdirectory.

## CI Provider Configurations

### GitHub Actions

GitHub Actions workflows are located in the `.github/workflows` directory at the root of the repository.

- **Usage**: Workflows automatically run when changes are pushed to the respective project directories.
- **Configuration**: No additional configuration is needed as long as the repository is hosted on GitHub.

#### GitHub Actions Status Badges

GitHub Actions workflow status badges are included in most project README.md files. The badge syntax is:

```markdown
![Github Action CI Workflow Status](https://github.com/username/repo-name/actions/workflows/workflow-file-name.yml/badge.svg)
```

### GitLab CI/CD

GitLab CI/CD configurations are located in each project's `ci/gitlab/.gitlab-ci.yml` file.

#### Setup Instructions

1. Copy the `.gitlab-ci.yml` file from the project's `ci/gitlab` directory to the root of the repository when using GitLab.
2. Configure GitLab CI/CD variables:
   - Go to `Settings` > `CI/CD` > `Variables` in your GitLab project.
   - Add any necessary secrets or environment variables.

#### Usage Notes

- The pipelines will automatically run when changes are detected in the respective project directories.
- Artifacts are stored in the GitLab Package Registry and can be accessed through the GitLab UI.

#### GitLab CI Status Badges

To add GitLab CI status badges to your project READMEs:

1. In your GitLab project, go to `Settings` > `General` > `Badges`.
2. Add a new badge with these settings:
   - Name: GitLab CI Pipeline Status
   - Link: `https://gitlab.com/<username>/<repo-name>`
   - Badge image URL: `https://gitlab.com/<username>/<repo-name>/badges/<branch>/pipeline.svg`
3. Add the badge to your README.md files:

```markdown
![GitLab CI Pipeline Status](https://gitlab.com/<username>/<repo-name>/badges/<branch>/pipeline.svg)
```

For specific job status badges:

```markdown
![GitLab CI Job Status](https://gitlab.com/<username>/<repo-name>/badges/<branch>/job-name.svg)
```

Replace `<username>`, `<repo-name>`, `<branch>`, and `job-name` with your specific values.

### Bitbucket Pipelines

Bitbucket Pipelines configurations are located in each project's `ci/bitbucket/bitbucket-pipelines.yml` file.

#### Setup Instructions

1. Copy the `bitbucket-pipelines.yml` file from the project's `ci/bitbucket` directory to the root of the repository when using Bitbucket.
2. Configure Bitbucket repository variables:
   - Go to `Repository settings` > `Pipelines` > `Repository variables`.
   - Add the following variables:
     - `BITBUCKET_USERNAME`: Your Bitbucket username for artifact uploads
     - `BITBUCKET_APP_PASSWORD`: An app password with appropriate permissions

#### Usage Notes

- Pipelines automatically run when changes are pushed to the respective project directories.
- Artifacts are uploaded to Bitbucket Downloads and can be accessed through the Bitbucket UI.

#### Bitbucket Pipelines Status Badges

To add Bitbucket Pipelines status badges to your project READMEs:

1. Generate the badge URL in this format:

   ```
   https://bitbucket.org/<workspace>/<repo-name>/pipelines/results/branch/<branch>.svg
   ```

2. Add the badge to your README.md files:

```markdown
![Bitbucket Pipelines Status](https://bitbucket.org/<workspace>/<repo-name>/pipelines/results/branch/<branch>.svg)
```

Replace `<workspace>`, `<repo-name>`, and `<branch>` with your specific values.

If you want custom badge styles, you can use a third-party badge service like shields.io:

```markdown
![Bitbucket Pipelines Status](https://img.shields.io/bitbucket/pipelines/<workspace>/<repo-name>/<branch>)
```

### Jenkins

Jenkins pipeline definitions are located in each project's `ci/jenkins/Jenkinsfile`.

#### Setup Instructions

1. In Jenkins, create a new Pipeline job:
   - Go to `New Item` > select `Pipeline` > give it a name related to the project.
2. Configure the pipeline:
   - Under `Pipeline`, select `Pipeline script from SCM`.
   - Set `SCM` to `Git`.
   - Enter the repository URL.
   - In `Script Path`, enter the path to the Jenkinsfile, e.g., `java-spring-ai-mcp/ci/jenkins/Jenkinsfile` for the Java project.
3. Configure Jenkins with the necessary tools:
   - Java JDK (for Java projects)
   - Node.js (for JavaScript projects)
   - Python (for Python projects)
   - Go (for Go projects)
   - .NET SDK (for .NET projects)
   - PHP (for PHP projects)
   - Ruby (for Ruby projects)
4. Set up necessary credentials for artifact repositories or deployment targets.

#### Usage Notes

- Jenkins jobs are triggered based on SCM polling (every 5 minutes by default).
- Artifacts are archived in Jenkins and can be found in the build details.
- For deployment to external systems (AWS S3, Nexus, Artifactory), uncomment and configure the appropriate sections in the Jenkinsfile.

#### Jenkins Status Badges

To add Jenkins build status badges to your project READMEs:

1. Install the "Embeddable Build Status" plugin in Jenkins.
2. In your Jenkins job, go to the job page.
3. On the right side menu, click on "Embeddable Build Status".
4. Select the format and style you prefer (Markdown, HTML, etc.).

For Markdown format, use:

```markdown
[![Build Status](https://your-jenkins-server.com/buildStatus/icon?job=job-name&subject=build)](https://your-jenkins-server.com/job/job-name/)
```

If using Jenkins with authentication, you might need to generate tokens or use the anonymous endpoint option.

If using the Blue Ocean interface, the badge URL would be:

```markdown
![Jenkins Build Status](https://your-jenkins-server.com/blue/organizations/jenkins/job-name/badge/master)
```

Replace `your-jenkins-server.com` and `job-name` with your specific values.

### Concourse CI

Concourse CI pipeline definitions are located in each project's `ci/concourse` directory.

#### Setup Instructions

1. Install the [Fly CLI](https://concourse-ci.org/fly.html) and authenticate with your Concourse server:
   ```bash
   fly -t your-target login -c https://your-concourse-server.com
   ```

2. Configure variables:
   - Edit the `vars.yml` file in the project's `ci/concourse` directory with your configuration values.
   - Copy `credentials-template.yml` to `credentials.yml` and fill in your credentials.
   - **IMPORTANT**: Do not commit `credentials.yml` to version control!

3. Set up the pipeline:
   ```bash
   fly -t your-target set-pipeline -p project-name -c project-name/ci/concourse/pipeline.yml -l project-name/ci/concourse/vars.yml -l project-name/ci/concourse/credentials.yml
   ```

4. Unpause the pipeline:
   ```bash
   fly -t your-target unpause-pipeline -p project-name
   ```

#### Usage Notes

- Pipelines are defined in `pipeline.yml` files with task configurations in the `tasks` directory.
- Build and deployment scripts are located in the `tasks/scripts` directory.
- Artifacts are stored in an S3 bucket or other configured storage.

#### Concourse CI Status Badges

To add Concourse CI status badges to your project READMEs:

1. Use the Concourse CI badge endpoint:
   ```
   https://your-concourse-server.com/api/v1/teams/main/pipelines/pipeline-name/badge
   ```

2. Add the badge to your README.md files:

```markdown
![Concourse CI Status](https://your-concourse-server.com/api/v1/teams/main/pipelines/pipeline-name/badge)
```

Replace `your-concourse-server.com` and `pipeline-name` with your specific values.

## Common CI Tasks

### Adding New Environment Variables

For all CI providers:

1. Add the variable to the respective CI configuration file.
2. Configure the variable in the CI provider's web interface (for secrets).

### Adding New Build Steps

1. Locate the appropriate CI configuration file for your project and CI provider.
2. Add the new build steps in the format specific to that CI provider.
3. Test the changes by committing them and monitoring the build.

### Handling Dependencies

Each CI configuration includes caching mechanisms to speed up builds:

- GitHub Actions: Uses actions/cache
- GitLab CI/CD: Uses the `cache` directive
- Bitbucket Pipelines: Uses the `caches` directive
- Jenkins: Uses a custom cache step
- Concourse CI: Uses resource caching

## Project-Specific Build Instructions

### Java Projects

#### java-spring-ai-mcp

- **Build Command**: `mvn -B package --file pom.xml`
- **Artifacts**: JAR files in the `target` directory for client, server, and API modules

#### java-spring-langgraph-mcp-angular

- **Backend Build Command**: `mvn -B package --file pom.xml`
- **Frontend Build Command**: `npm install && npm run build -- --configuration production`
- **Artifacts**: Backend JAR files in the `backend/target` directory and frontend files in the `frontend/dist` directory

### JavaScript Projects

#### js-langchain-react

- **Build Command**: `npm install && npm run build`
- **Artifacts**: Build files in the `build` directory

### Python Projects

#### py-django-crewai

- **Build Command**: `pip install -r requirements.txt && python manage.py test`
- **Artifacts**: Zip archive containing the entire project (excluding virtual environments)

### Go Projects

#### go-fiber-langchaingo

- **Build Command**: `make build || go build -o ./bin/app ./cmd/server`
- **Artifacts**: Compiled binary in the `bin` directory

### .NET Projects

#### dotnet-extensions-ai

- **Build Command**: `dotnet restore && dotnet build --no-restore`
- **Publish Command**: `dotnet publish -c Release -o ./publish`
- **Artifacts**: Published application in the `publish` directory

### PHP Projects

#### php-symfony-neuron

- **Build Command**: `composer install --prefer-dist --no-progress`
- **Packaging Command**: `composer dump-autoload --optimize --no-dev && zip -r build/php-symfony-neuron.zip .`
- **Artifacts**: Zip archive in the `build` directory

### Ruby Projects

#### ruby-sinatra-fastmcp

- **Build Command**: `bundle install`
- **Packaging Command**: `zip -r dist/ruby-sinatra-fastmcp.zip .`
- **Artifacts**: Zip archive in the `dist` directory

## Troubleshooting

### Common Issues

1. **Missing Dependency Errors**: Ensure all dependencies are listed in the appropriate files (package.json, requirements.txt, pom.xml, go.mod, composer.json, Gemfile, etc.).
2. **Pipeline Timeout**: Adjust timeout settings in the CI configuration if builds are taking too long.
3. **Cache Not Working**: Verify the cache key and paths in the CI configuration.
4. **Docker Issues**: For Jenkins pipelines using Docker agents, ensure the Docker daemon is properly configured and accessible.
5. **Permission Issues**: Especially in Jenkins, ensure the build agent has the necessary permissions to execute the scripts.

### Getting Help

For specific issues with each CI provider, refer to their respective documentation:

- [GitHub Actions Documentation](https://docs.github.com/en/actions)
- [GitLab CI/CD Documentation](https://docs.gitlab.com/ee/ci/)
- [Bitbucket Pipelines Documentation](https://support.atlassian.com/bitbucket-cloud/docs/bitbucket-pipelines-configuration-reference/)
- [Jenkins Pipeline Documentation](https://www.jenkins.io/doc/book/pipeline/)
- [Concourse CI Documentation](https://concourse-ci.org/docs.html)
