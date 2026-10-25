#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $0 \"SQL QUERY\""
    echo "Example: $0 \"SELECT * FROM wp_users;\""
    exit 1
fi

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "Error: Containers are not running. Run ./scripts/start.sh first."
    exit 1
fi

# Execute the query
docker-compose exec db mysql -u wordpress -pwordpress wordpress -e "$1"