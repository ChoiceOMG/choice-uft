#!/bin/bash

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "Error: Containers are not running. Run ./scripts/start.sh first."
    exit 1
fi

echo "WordPress Database Tables:"
echo "----------------------------------------"
docker-compose exec db mysql -u wordpress -pwordpress wordpress -e "SHOW TABLES;"