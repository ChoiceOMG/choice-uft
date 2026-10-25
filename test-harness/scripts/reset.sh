#!/bin/bash

echo "WARNING: This will delete all data and reset the environment!"
read -p "Are you sure? (y/N): " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled."
    exit 0
fi

echo "Resetting WordPress development environment..."

# Stop and remove containers
docker-compose down -v

# Remove plugin symlinks but keep the directory
if [ -L "plugins/choice-uft" ]; then
    rm plugins/choice-uft
fi

# Clear uploads if they exist
if [ -d "uploads" ]; then
    rm -rf uploads/*
fi

# Clear debug log
> debug.log

echo "Environment reset complete."
echo "Run ./scripts/start.sh to start fresh."