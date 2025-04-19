# PHP and Composer Setup Guide for Symfony Development

## Table of Contents

- [Introduction](#introduction)
- [Requirements](#requirements)
- [Ubuntu Setup](#ubuntu-setup)
  - [Install PHP](#install-php-on-ubuntu)
  - [Verify PHP Extensions](#verify-php-extensions-on-ubuntu)
  - [Install Composer](#install-composer-on-ubuntu)
  - [Install Symfony CLI](#install-symfony-cli-on-ubuntu)
  - [Upgrading Tools](#upgrading-tools-on-ubuntu)
- [macOS Setup](#macos-setup)
  - [Install PHP](#install-php-on-macos)
  - [Verify PHP Extensions](#verify-php-extensions-on-macos)
  - [Install Composer](#install-composer-on-macos)
  - [Install Symfony CLI](#install-symfony-cli-on-macos)
  - [Upgrading Tools](#upgrading-tools-on-macos)
- [Windows Setup](#windows-setup)
  - [Install PHP](#install-php-on-windows)
  - [Verify PHP Extensions](#verify-php-extensions-on-windows)
  - [Install Composer](#install-composer-on-windows)
  - [Install Symfony CLI](#install-symfony-cli-on-windows)
  - [Upgrading Tools](#upgrading-tools-on-windows)
- [Verifying Your Installation](#verifying-your-installation)
- [Creating a New Symfony Project](#creating-a-new-symfony-project)
- [Next Steps](#next-steps)

## Introduction

This guide provides step-by-step instructions for setting up PHP, Composer, and the Symfony CLI on Ubuntu, macOS, and Windows. These tools are essential for developing Symfony applications.

## Requirements

For Symfony development, you'll need:

- PHP 8.3 or higher
- Composer (PHP package manager)
- Symfony CLI (optional but recommended)
- Essential PHP extensions:
  - `intl`: For internationalization support
  - `mbstring`: For multi-byte string handling
  - `xml`: For XML processing
  - `zip`: For working with archives
  - `curl`: For making HTTP requests
  - `pdo`: For database abstraction
  - `mysql`: For MySQL database connectivity (if using MySQL)
  - `sqlite3`: For SQLite database connectivity (if using SQLite)

## Ubuntu Setup

### Install PHP on Ubuntu

```bash
# Update package lists
sudo apt update

# Install PHP and common extensions needed for Symfony
sudo apt install -y php8.3 php8.3-cli php8.3-common php8.3-curl php8.3-intl php8.3-mbstring php8.3-pdo php8.3-xml php8.3-zip php8.3-mysql php8.3-sqlite3
```

**Why these extensions?**

- `intl`: For internationalization support
- `mbstring`: For multi-byte string handling
- `xml`: For XML processing
- `zip`: For working with archives
- `curl`: For making HTTP requests
- `pdo`: For database abstraction
- `mysql`: For MySQL database connectivity
- `sqlite3`: For SQLite database connectivity

### Verify PHP Extensions on Ubuntu

To check which PHP extensions are already installed:

```bash
# List all installed PHP modules
php -m

# Or for a formatted list with descriptions
php -m | sort

# To check if a specific extension is installed
php -m | grep intl
```

To check the PHP configuration and all enabled modules with details:

```bash
# Create a phpinfo file
echo "<?php phpinfo(); ?>" > phpinfo.php

# View it in terminal (text mode)
php phpinfo.php | less

# Or serve it and view in browser
php -S localhost:8000 phpinfo.php
```

To check which PHP extensions are available but not installed:

```bash
# List available PHP extensions in apt repos
apt-cache search php8.3-*
```

### Install Composer on Ubuntu

```bash
# Download Composer installer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

# Verify installer
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

# Install globally
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Clean up
php -r "unlink('composer-setup.php');"
```

### Install Symfony CLI on Ubuntu

```bash
# Install using curl
curl -sS https://get.symfony.com/cli/installer | bash

# Move to global location
sudo mv ~/.symfony5/bin/symfony /usr/local/bin/symfony
```

### Upgrading Tools on Ubuntu

#### Upgrading PHP

```bash
# Update package lists
sudo apt update

# Upgrade PHP and its extensions
sudo apt upgrade php8.3 php8.3-*

# If you want to switch to a newer PHP version (e.g., from 8.3 to 8.4)
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.4
```

#### Upgrading Composer

```bash
# Self-update Composer to the latest version
sudo composer self-update

# To roll back to previous version if needed
sudo composer self-update --rollback
```

#### Upgrading Symfony CLI

```bash
# Update Symfony CLI
symfony self-update

# Check for available updates
symfony self-update --check
```

## macOS Setup

### Install PHP on macOS

Using Homebrew:

```bash
# Install Homebrew if not already installed
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

# Install PHP
brew install php
```

**Note**: Homebrew's PHP package often includes many extensions by default. Check installed extensions with `php -m` after installation to see what you already have and what might still be needed.

### Verify PHP Extensions on macOS

To check which PHP extensions are already installed:

```bash
# List all installed PHP modules
php -m

# For a formatted list with descriptions
php -m | sort

# Check if a specific extension is installed
php -m | grep intl
```

To see detailed PHP configuration:

```bash
# View PHP configuration
php --ini

# Create and view phpinfo page
echo "<?php phpinfo(); ?>" > phpinfo.php
php -S localhost:8000 phpinfo.php
```

To check available PHP extensions via Homebrew:

```bash
# List available PHP extensions
brew search php-
```

### Install Composer on macOS

```bash
# Download Composer installer
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"

# Verify installer
php -r "if (hash_file('sha384', 'composer-setup.php') === file_get_contents('https://composer.github.io/installer.sig')) { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"

# Install globally
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer

# Clean up
php -r "unlink('composer-setup.php');"
```

### Install Symfony CLI on macOS

```bash
# Install using Homebrew
brew install symfony-cli/tap/symfony-cli
```

### Upgrading Tools on macOS

#### Upgrading PHP

```bash
# Update Homebrew
brew update

# Upgrade PHP to the latest version in your current major version
brew upgrade php

# To switch PHP versions (e.g., from 8.3 to 8.4)
brew unlink php
brew install php@8.4
brew link --force php@8.4
```

#### Upgrading Composer

```bash
# Self-update Composer to the latest version
composer self-update

# To roll back to previous version if needed
composer self-update --rollback
```

#### Upgrading Symfony CLI

```bash
# Update Symfony CLI via Homebrew
brew upgrade symfony-cli

# Or using the Symfony self-updater
symfony self-update
```

## Windows Setup

### Install PHP on Windows

1. **Download PHP**:
   - Visit [windows.php.net/download](https://windows.php.net/download/)
   - Download the latest PHP 8.x x64 zip file (non-thread safe version is recommended)

2. **Extract and Configure**:

   ```powershell
   # Create a folder for PHP (example location)
   mkdir C:\php

   # Extract the downloaded zip to this folder
   # (Do this through Windows Explorer or use Expand-Archive PowerShell command)

   # Add PHP to your PATH environment variable
   [Environment]::SetEnvironmentVariable("Path", $env:Path + ";C:\php", "Machine")
   ```

3. **Configure PHP**:

   ```powershell
   cd C:\php
   copy php.ini-development php.ini
   ```

4. **Edit php.ini** (using Notepad or another editor) to uncomment these essential extensions:

   ```ini
   ; Essential extensions for Symfony development (matching Ubuntu setup)
   extension=curl
   extension=intl
   extension=mbstring
   extension=pdo_mysql
   extension=pdo_sqlite
   extension=fileinfo
   extension=xml
   extension=zip
   ```

**Why these extensions?**

- `intl`: For internationalization support
- `mbstring`: For multi-byte string handling
- `xml`: For XML processing
- `zip`: For working with archives
- `curl`: For making HTTP requests
- `pdo_mysql`: For MySQL database connectivity
- `pdo_sqlite`: For SQLite database connectivity
- `fileinfo`: For file type detection

### Verify PHP Extensions on Windows

To check which PHP extensions are already installed:

```powershell
# List all installed PHP modules
php -m

# Check if a specific extension is installed
php -m | findstr intl
```

To see PHP configuration and loaded extensions with details:

```powershell
# View PHP configuration
php --ini

# Create and view phpinfo page
echo "<?php phpinfo(); ?>" > phpinfo.php
php -S localhost:8000 phpinfo.php
```

To check which extensions are available but not enabled:

```powershell
# Look in your PHP directory for DLL files
dir C:\php\ext\*.dll
```

To check which extensions are enabled in your php.ini file:

```powershell
# Using PowerShell to find uncommented extensions
(Get-Content C:\php\php.ini) | Where-Object { $_ -match "^extension=" }
```

### Install Composer on Windows

1. **Download and Run Installer**:
   - Visit [getcomposer.org/download](https://getcomposer.org/download/)
   - Download and run Composer-Setup.exe
   - Follow the installation wizard

The installer will automatically add Composer to your PATH environment variable.

### Install Symfony CLI on Windows

1. **Download the Windows Installer**:
   - Visit [symfony.com/download](https://symfony.com/download)
   - Download the Windows installer

2. **Run the installer** and follow the prompts

### Upgrading Tools on Windows

#### Upgrading PHP

To upgrade PHP on Windows:

1. **Download the new version**:
   - Visit [windows.php.net/download](https://windows.php.net/download/)
   - Download the latest PHP version

2. **Backup your configuration**:

   ```powershell
   # Backup your current php.ini
   copy C:\php\php.ini C:\php\php.ini.backup
   ```

3. **Install the new version**:
   - Extract the new PHP version to a temporary folder
   - Compare your current php.ini with the new php.ini-development
   - Stop any running PHP processes
   - Replace the files in your C:\php folder with the new ones
   - Update your php.ini with your customizations
   - Ensure all required extensions are enabled

4. **Verify the upgrade**:

   ```powershell
   php -v
   ```

#### Upgrading Composer

```powershell
# Self-update Composer to the latest version
composer self-update

# To roll back to previous version if needed
composer self-update --rollback
```

#### Upgrading Symfony CLI

The Symfony CLI on Windows can be updated by:

1. **Using the built-in updater**:

   ```powershell
   symfony self-update
   ```

2. **Or by reinstalling**:
   - Download the latest installer from [symfony.com/download](https://symfony.com/download)
   - Run the installer to replace your current version

## Verifying Your Installation

After installation, verify everything is working correctly:

```bash
# Check PHP version
php -v

# Check Composer installation
composer -V

# Check Symfony CLI installation
symfony -V

# Check Symfony requirements
symfony check:requirements
```

## Creating a New Symfony Project

Now you can create a new Symfony project:

```bash
# Create a new Symfony application
symfony new my-symfony-app --webapp

# Or using Composer directly
composer create-project symfony/skeleton:"6.3.*" my-symfony-app
cd my-symfony-app
composer require webapp
```

## Next Steps

After installation, consider:

1. **Configure your web server**: Apache, Nginx, or use the built-in Symfony server (`symfony server:start`)
2. **Setup your IDE**: Extensions for PHP, Symfony, and Twig
3. **Learn Symfony fundamentals**: Visit [symfony.com/doc](https://symfony.com/doc/current/index.html)
4. **Install a database**: MySQL, PostgreSQL or SQLite
5. **Explore Symfony Maker**: `composer require --dev symfony/maker-bundle` for code generation

Congratulations! You now have the necessary tools to start developing Symfony applications.
