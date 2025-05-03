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

echo -e "${BLUE}=== Ruby Installation Script for Linux ===${NC}"
echo -e "${BLUE}This script will install Ruby $RUBY_VERSION using rbenv${NC}"
echo -e "${BLUE}===============================================${NC}"

# Function to check if command exists
command_exists() {
  command -v "$1" &> /dev/null
}

# Detect Linux distribution
if [ -f /etc/os-release ]; then
  . /etc/os-release
  DISTRO=$ID
elif [ -f /etc/lsb-release ]; then
  . /etc/lsb-release
  DISTRO=$DISTRIB_ID
else
  DISTRO=$(uname -s)
fi

echo -e "${YELLOW}Detected distribution: $DISTRO${NC}"

# Install dependencies based on distribution
install_dependencies() {
  echo -e "${YELLOW}Installing dependencies...${NC}"

  case $DISTRO in
    ubuntu|debian|linuxmint|pop)
      sudo apt-get update
      sudo apt-get install -y git curl build-essential libssl-dev zlib1g-dev \
        libbz2-dev libreadline-dev libsqlite3-dev wget llvm libncurses5-dev \
        libncursesw5-dev xz-utils tk-dev libffi-dev liblzma-dev
      ;;
    fedora|rhel|centos|rocky|almalinux)
      sudo dnf install -y git curl gcc make bzip2 openssl-devel zlib-devel \
        readline-devel sqlite-devel wget llvm ncurses-devel libffi-devel
      ;;
    arch|manjaro)
      sudo pacman -Sy --noconfirm git curl base-devel openssl zlib
      ;;
    opensuse|suse)
      sudo zypper install -y git curl gcc automake bzip2 libbz2-devel xz \
        xz-devel openssl-devel ncurses-devel readline-devel zlib-devel \
        sqlite3-devel libffi-devel
      ;;
    *)
      echo -e "${RED}Unsupported distribution: $DISTRO${NC}"
      echo -e "${YELLOW}Please install the following dependencies manually:${NC}"
      echo "git, curl, gcc, make, openssl-devel, zlib-devel, readline-devel, sqlite-devel"
      echo "Then run this script again."
      exit 1
      ;;
  esac

  echo -e "${GREEN}Dependencies installed successfully!${NC}"
}

# Check if git is installed
if ! command_exists git; then
  echo -e "${YELLOW}Git not found. Installing dependencies...${NC}"
  install_dependencies
else
  echo -e "${GREEN}Git is already installed.${NC}"
fi

# Check if rbenv is installed
if ! command_exists rbenv; then
  echo -e "${YELLOW}rbenv not found. Installing rbenv...${NC}"

  # Clone rbenv repository
  git clone https://github.com/rbenv/rbenv.git ~/.rbenv

  # Add rbenv to PATH for the current session
  export PATH="$HOME/.rbenv/bin:$PATH"
  eval "$(~/.rbenv/bin/rbenv init -)"

  # Add rbenv to shell profile
  if [[ -f ~/.bashrc ]]; then
    echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> ~/.bashrc
    echo 'eval "$(rbenv init -)"' >> ~/.bashrc
  elif [[ -f ~/.bash_profile ]]; then
    echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> ~/.bash_profile
    echo 'eval "$(rbenv init -)"' >> ~/.bash_profile
  elif [[ -f ~/.zshrc ]]; then
    echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> ~/.zshrc
    echo 'eval "$(rbenv init -)"' >> ~/.zshrc
  else
    echo 'export PATH="$HOME/.rbenv/bin:$PATH"' >> ~/.profile
    echo 'eval "$(rbenv init -)"' >> ~/.profile
  fi

  # Install ruby-build plugin
  mkdir -p "$(rbenv root)"/plugins
  git clone https://github.com/rbenv/ruby-build.git "$(rbenv root)"/plugins/ruby-build

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
