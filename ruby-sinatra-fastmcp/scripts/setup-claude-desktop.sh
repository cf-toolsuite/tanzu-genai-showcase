#!/bin/bash

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${YELLOW}Setting up Claude Desktop configuration${NC}"
echo "---------------------------------------------"

# Get the script directory and project root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
PROJECT_ROOT="$( cd "$SCRIPT_DIR/.." &> /dev/null && pwd )"

# Get absolute path to server script
SERVER_PATH="$PROJECT_ROOT/scripts/mcp_server_wrapper.rb"

# Make sure the wrapper script is executable
chmod +x "$SERVER_PATH"

# Detect OS
case "$(uname -s)" in
    Linux*)
        CONFIG_DIR="$HOME/.config/Claude"
        ;;
    Darwin*)
        CONFIG_DIR="$HOME/Library/Application Support/Claude"
        ;;
    CYGWIN*|MINGW*|MSYS*)
        CONFIG_DIR="$APPDATA/Claude"
        ;;
    *)
        echo -e "${RED}Unsupported operating system${NC}"
        exit 1
        ;;
esac

CONFIG_FILE="$CONFIG_DIR/claude_desktop_config.json"

# Create config directory if it doesn't exist
mkdir -p "$CONFIG_DIR"

# Check if config file exists
if [ -f "$CONFIG_FILE" ]; then
    echo -e "${YELLOW}Found existing Claude configuration at:${NC} $CONFIG_FILE"

    # Check if our server is already in the config
    if grep -q "flight-tracking-bot" "$CONFIG_FILE"; then
        echo -e "${GREEN}Flight tracking bot is already configured.${NC}"
        exit 0
    fi

    # Backup existing config
    cp "$CONFIG_FILE" "$CONFIG_FILE.backup"
    echo -e "${YELLOW}Created backup at:${NC} $CONFIG_FILE.backup"

    # Update existing config
    TMP_FILE=$(mktemp)
    jq --arg path "$SERVER_PATH" '.mcpServers."flight-tracking-bot" = {"command": "ruby", "args": [$path]}' "$CONFIG_FILE" > "$TMP_FILE" && cp "$TMP_FILE" "$CONFIG_FILE"
    rm "$TMP_FILE"
else
    # Create new config file
    cat > "$CONFIG_FILE" << EOF
{
  "mcpServers": {
    "flight-tracking-bot": {
      "command": "ruby",
      "args": ["$SERVER_PATH"]
    }
  }
}
EOF
fi

echo -e "${GREEN}Claude Desktop configuration updated successfully!${NC}"
echo -e "${YELLOW}Configuration file:${NC} $CONFIG_FILE"
echo -e "${YELLOW}Server path:${NC} $SERVER_PATH"
echo -e "\n${YELLOW}Important:${NC} You need to restart Claude Desktop for the changes to take effect."
