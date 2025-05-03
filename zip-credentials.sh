#!/bin/bash

# Check if directory argument is provided
if [ $# -ne 1 ]; then
    echo "Usage: $0 <directory>"
    exit 1
fi

# Set the top-level directory from the argument
TOP_DIR="$1"

# Check if the directory exists
if [ ! -d "$TOP_DIR" ]; then
    echo "Error: Directory '$TOP_DIR' does not exist."
    exit 1
fi

# Create a temporary directory to store the file structure
TEMP_DIR=$(mktemp -d)
echo "Created temporary directory: $TEMP_DIR"

# Find all .env files and copy them to the temp directory with the same structure
find "$TOP_DIR" -name "*.env" -type f | while read -r file; do
    # Get the relative path from the top directory
    REL_PATH=$(realpath --relative-to="$TOP_DIR" "$file")

    # Create the directory structure in the temp directory
    mkdir -p "$TEMP_DIR/$(dirname "$REL_PATH")"

    # Copy the file to the temp directory
    cp "$file" "$TEMP_DIR/$(dirname "$REL_PATH")/"

    echo "Copied: $REL_PATH"
done

# Create the zip file from the temp directory
ZIP_FILE="env_files_$(date +%Y%m%d_%H%M%S).zip"
cd "$TEMP_DIR" && zip -r "../$ZIP_FILE" .
echo "Created zip file: $ZIP_FILE"

# Clean up the temp directory
rm -rf "$TEMP_DIR"
echo "Cleaned up temporary directory"

echo "Done! Zip file created: $ZIP_FILE"
