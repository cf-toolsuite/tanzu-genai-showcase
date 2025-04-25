#!/bin/bash
set -e

# Build script for go-fiber-langchaingo

cd source-code/go-fiber-langchaingo

# Set up Go environment
echo "Setting up Go environment..."
export GOPATH=$PWD/gopath
export PATH=$GOPATH/bin:$PATH
mkdir -p $GOPATH/src

# Build using Makefile if available, otherwise use go build
echo "Building application..."
if [ -f "Makefile" ]; then
  make build
else
  go build -o ./bin/app ./cmd/server
fi

# Run tests
echo "Running tests..."
go test ./... -v

# Package artifacts
echo "Packaging artifacts..."
mkdir -p ../../build-output
tar -czf ../../build-output/artifacts.tar.gz bin

echo "Build completed successfully!"
