#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $0 <plugin-slug>"
    echo "Example: $0 contact-form-7"
    exit 1
fi

PLUGIN=$1

echo "Installing plugin: $PLUGIN..."

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "Error: Containers are not running. Run ./scripts/start.sh first."
    exit 1
fi

# Install and activate the plugin
docker-compose exec wpcli wp plugin install "$PLUGIN" --activate

if [ $? -eq 0 ]; then
    echo "Plugin $PLUGIN installed and activated successfully!"
else
    echo "Failed to install plugin $PLUGIN"
    exit 1
fi