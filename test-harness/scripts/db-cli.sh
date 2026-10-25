#!/bin/bash

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "Error: Containers are not running. Run ./scripts/start.sh first."
    exit 1
fi

# Check if we have additional parameters
if [ $# -eq 0 ]; then
    # Interactive mode
    echo "Connecting to MySQL database..."
    echo "Database: wordpress | User: wordpress"
    echo "----------------------------------------"
    docker-compose exec db mysql -u wordpress -pwordpress wordpress
else
    # Execute command mode
    docker-compose exec db mysql -u wordpress -pwordpress wordpress "$@"
fi