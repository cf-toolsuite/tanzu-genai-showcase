# Tanzu GenAI Showcase: Fork Maintenance Guide

This guide explains how to maintain a fork of this repository while keeping CI/CD workflows synchronized across different platforms (GitHub Actions, GitLab CI, Bitbucket Pipelines, and Jenkins).

## Initial Forking Process

### GitHub to GitHub

If you're maintaining your fork on GitHub:

1. Fork the repository using GitHub's "Fork" button
2. Clone your fork locally: `git clone https://github.com/YOUR_USERNAME/tanzu-genai-showcase.git`
3. Add the original repository as an upstream remote:

   ```bash
   git remote add upstream https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

### GitHub to GitLab

If you're migrating to GitLab:

1. Create a new project in GitLab
2. Clone the original GitHub repository locally:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

3. Change the remote URL to point to your GitLab repository:

   ```bash
   git remote set-url origin https://gitlab.com/YOUR_USERNAME/tanzu-genai-showcase.git
   ```

4. Add the original GitHub repository as an upstream remote:

   ```bash
   git remote add upstream https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

5. Push to your GitLab repository:

   ```bash
   git push -u origin main
   ```

6. Copy the appropriate GitLab CI configuration files from each project's `ci/gitlab` directory to the root:

   ```bash
   # For a specific project
   cp project-name/ci/gitlab/.gitlab-ci.yml .gitlab-ci.yml

   # Alternatively, use a multi-project configuration approach
   # (See "Advanced GitLab CI Configuration" below)
   ```

### GitHub to Bitbucket

If you're migrating to Bitbucket:

1. Create a new repository in Bitbucket
2. Clone the original GitHub repository locally:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

3. Change the remote URL to point to your Bitbucket repository:

   ```bash
   git remote set-url origin https://bitbucket.org/YOUR_USERNAME/tanzu-genai-showcase.git
   ```

4. Add the original GitHub repository as an upstream remote:

   ```bash
   git remote add upstream https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

5. Push to your Bitbucket repository:

   ```bash
   git push -u origin main
   ```

6. Copy the appropriate Bitbucket Pipelines configuration file from a project's `ci/bitbucket` directory to the root:

   ```bash
   cp project-name/ci/bitbucket/bitbucket-pipelines.yml bitbucket-pipelines.yml
   ```

### GitHub to Self-Hosted Git with Jenkins

If you're using a self-hosted Git server with Jenkins:

1. Set up a repository on your Git server
2. Clone the original GitHub repository locally:

   ```bash
   git clone https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

3. Change the remote URL to point to your self-hosted Git repository:

   ```bash
   git remote set-url origin https://your-git-server.com/YOUR_USERNAME/tanzu-genai-showcase.git
   ```

4. Add the original GitHub repository as an upstream remote:

   ```bash
   git remote add upstream https://github.com/cf-toolsuite/tanzu-genai-showcase.git
   ```

5. Push to your Git server:

   ```bash
   git push -u origin main
   ```

6. In Jenkins, set up pipeline jobs for each project, pointing to the respective Jenkinsfiles in the `ci/jenkins` directories

## Keeping Your Fork Updated

### Pulling Updates from Upstream

To pull the latest changes from the original repository:

1. Fetch the upstream changes:

   ```bash
   git fetch upstream
   ```

2. Make sure you're on your main branch:

   ```bash
   git checkout main
   ```

3. Merge the changes from the upstream main branch:

   ```bash
   git merge upstream/main
   ```

4. Push the changes to your fork:

   ```bash
   git push
   ```

### Monitoring CI Configuration Changes

To identify changes in CI configurations and adapt them to your platform:

1. After pulling upstream changes, check for modifications to the GitHub Actions workflows:

   ```bash
   git diff HEAD@{1} .github/workflows/
   ```

2. If changes are detected, analyze what was modified and update the corresponding CI configurations for your platform:
   - For GitLab CI: Update the corresponding `.gitlab-ci.yml` files
   - For Bitbucket Pipelines: Update the `bitbucket-pipelines.yml` file
   - For Jenkins: Update the corresponding `Jenkinsfile` files

### Automatic CI Migration Script

You can create a script to help migrate GitHub Actions workflows to your chosen CI platform. Here's a basic example:

```bash
#!/bin/bash
# Script to detect GitHub Actions workflow changes and update equivalent CI files

# Get the list of changed GitHub workflow files
CHANGED_FILES=$(git diff --name-only HEAD@{1} .github/workflows/)

for FILE in $CHANGED_FILES; do
  # Extract project name from file path
  PROJECT_NAME=$(basename "$FILE" .yml)

  echo "Detected changes in $PROJECT_NAME workflow"

  # For GitLab CI
  if [ -d "$PROJECT_NAME/ci/gitlab" ]; then
    echo "Updating GitLab CI configuration for $PROJECT_NAME"
    # Here you could implement logic to update the GitLab CI file
    # or just notify the admin to review it
  fi

  # For Bitbucket Pipelines
  if [ -d "$PROJECT_NAME/ci/bitbucket" ]; then
    echo "Updating Bitbucket Pipelines configuration for $PROJECT_NAME"
    # Logic to update Bitbucket configuration
  fi

  # For Jenkins
  if [ -d "$PROJECT_NAME/ci/jenkins" ]; then
    echo "Updating Jenkins Pipeline for $PROJECT_NAME"
    # Logic to update Jenkins Pipeline
  fi
done

echo "CI migration check complete. Please review the indicated files and update as needed."
```

## Advanced Platform-Specific Configurations

### Advanced GitLab CI Configuration

GitLab allows you to include multiple CI configuration files. To maintain a more modular approach similar to GitHub Actions:

1. Create a root `.gitlab-ci.yml` file with includes:

```yaml
include:
  - local: 'java-spring-ai-mcp/ci/gitlab/.gitlab-ci.yml'
  - local: 'js-langchain-react/ci/gitlab/.gitlab-ci.yml'
  - local: 'py-django-crewai/ci/gitlab/.gitlab-ci.yml'
  # Include other project CI files as needed
```

2. Modify each project's GitLab CI file to use unique job names to avoid conflicts

### Advanced Bitbucket Pipelines Configuration

Bitbucket Pipelines doesn't support including multiple configuration files directly, but you can:

1. Create a comprehensive `bitbucket-pipelines.yml` that combines configurations for all projects
2. Use conditional steps based on file changes to determine which pipelines to run

### Advanced Jenkins Configuration

For Jenkins, consider:

1. Creating a "Pipeline Organizer" job that detects changes and triggers the appropriate project-specific pipeline
2. Using Jenkins Shared Libraries to centralize common pipeline functionality
3. Setting up a Jenkins multi-branch pipeline configuration that automatically discovers and builds branches

## Best Practices for Cross-Platform CI Maintenance

1. **Version Control CI Configurations**: Keep all CI configurations in version control alongside your code
2. **Documentation**: Document any platform-specific adaptations you've made
3. **Parameterization**: Use variables and parameters in your CI configurations to make updates easier
4. **Testing**: Test CI changes in a feature branch before merging to main
5. **Notifications**: Set up notifications for CI configuration changes in the upstream repository
6. **Regular Sync**: Establish a regular schedule for syncing with the upstream repository
7. **Change Log**: Maintain a change log of CI modifications to track adaptations between platforms

## Handling Major Changes

If the upstream repository undergoes significant changes to its CI structure:

1. Create a feature branch for CI migration
2. Pull the upstream changes into this branch
3. Analyze the changes carefully and adapt them to your platform
4. Test the new CI configurations thoroughly
5. Document the changes and update your CI documentation
6. Merge the feature branch once everything is working

## Conclusion

Maintaining a fork of this repository with custom CI configurations requires regular monitoring of upstream changes and careful adaptation. By following this guide, you can ensure your fork stays up-to-date while maintaining equivalent CI capabilities across platforms.
