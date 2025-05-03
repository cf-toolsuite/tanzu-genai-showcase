#!/usr/bin/env bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default Ruby version
DEFAULT_RUBY_VERSION="3.3.6"
RUBY_VERSION=${1:-$DEFAULT_RUBY_VERSION}

echo -e "${BLUE}=== Ruby Installation Script for macOS ===${NC}"
echo -e "${BLUE}This script will install Ruby $RUBY_VERSION using rbenv${NC}"
echo -e "${BLUE}================================================${NC}"

# Function to check if command exists
command_exists() {
  command -v "$1" &> /dev/null
}

# Check if Homebrew is installed
if ! command_exists brew; then
  echo -e "${YELLOW}Homebrew not found. Installing Homebrew...${NC}"
  /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"

  # Add Homebrew to PATH for the current session
  if [[ -f /opt/homebrew/bin/brew ]]; then
    eval "$(/opt/homebrew/bin/brew shellenv)"
  elif [[ -f /usr/local/bin/brew ]]; then
    eval "$(/usr/local/bin/brew shellenv)"
  else
    echo -e "${RED}Failed to find Homebrew after installation. Please restart your terminal and run this script again.${NC}"
    exit 1
  fi

  echo -e "${GREEN}Homebrew installed successfully!${NC}"
else
  echo -e "${GREEN}Homebrew is already installed.${NC}"
fi

# Check if rbenv is installed
if ! command_exists rbenv; then
  echo -e "${YELLOW}rbenv not found. Installing rbenv...${NC}"
  brew install rbenv ruby-build

  # Initialize rbenv
  echo -e "${YELLOW}Initializing rbenv...${NC}"
  rbenv init

  # Add rbenv to shell profile
  if [[ -f ~/.zshrc ]]; then
    echo 'eval "$(rbenv init -)"' >> ~/.zshrc
    source ~/.zshrc
  elif [[ -f ~/.bash_profile ]]; then
    echo 'eval "$(rbenv init -)"' >> ~/.bash_profile
    source ~/.bash_profile
  elif [[ -f ~/.bashrc ]]; then
    echo 'eval "$(rbenv init -)"' >> ~/.bashrc
    source ~/.bashrc
  else
    echo 'eval "$(rbenv init -)"' >> ~/.profile
    source ~/.profile
  fi

  echo -e "${GREEN}rbenv installed successfully!${NC}"
else
  echo -e "${GREEN}rbenv is already installed.${NC}"
fi

# Check if the specified Ruby version is installed
if rbenv versions | grep -q "$RUBY_VERSION"; then
  echo -e "${GREEN}Ruby $RUBY_VERSION is already installed.${NC}"
else
  echo -e "${YELLOW}Installing Ruby $RUBY_VERSION...${NC}"
  rbenv install $RUBY_VERSION

  if [ $? -ne 0 ]; then
    echo -e "${RED}Failed to install Ruby $RUBY_VERSION. Please check the error message above.${NC}"
    exit 1
  fi

  echo -e "${GREEN}Ruby $RUBY_VERSION installed successfully!${NC}"
fi

# Set the Ruby version as the global default
echo -e "${YELLOW}Setting Ruby $RUBY_VERSION as the global default...${NC}"
rbenv global $RUBY_VERSION
rbenv rehash

# Verify Ruby installation
INSTALLED_RUBY_VERSION=$(ruby -v)
echo -e "${GREEN}Using Ruby: $INSTALLED_RUBY_VERSION${NC}"

# Install Bundler
echo -e "${YELLOW}Installing Bundler...${NC}"
gem install bundler
rbenv rehash

echo -e "${GREEN}Bundler installed successfully!${NC}"

# Project setup instructions
echo -e "\n${BLUE}=== Project Setup ===${NC}"
echo -e "${YELLOW}To set up the Flight Tracking Chatbot project:${NC}"
echo -e "1. Navigate to the project directory:"
echo -e "   ${GREEN}cd path/to/ruby-sinatra-fastmcp${NC}"
echo -e "2. Install project dependencies:"
echo -e "   ${GREEN}bundle config set --local path 'vendor/bundle'${NC}"
echo -e "   ${GREEN}bundle install${NC}"
echo -e "3. Create a .env file with your AviationStack API key:"
echo -e "   ${GREEN}cp .env.example .env${NC}"
echo -e "   Then edit the .env file to add your API key"
echo -e "4. Start the development server:"
echo -e "   ${GREEN}./scripts/start-dev.sh${NC}"
echo -e "\n${BLUE}=== Installation Complete ===${NC}"
echo -e "${GREEN}Ruby $RUBY_VERSION and Bundler have been successfully installed!${NC}"
echo -e "${YELLOW}Note: You may need to restart your terminal for all changes to take effect.${NC}"
