#!/bin/bash

if [ -z "$1" ]; then
    echo "Usage: $0 <sql-file>"
    echo "Example: $0 backup.sql"
    exit 1
fi

SQL_FILE=$1

if [ ! -f "$SQL_FILE" ]; then
    echo "Error: File $SQL_FILE not found"
    exit 1
fi

echo "Importing database from $SQL_FILE..."

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "Error: Containers are not running. Run ./scripts/start.sh first."
    exit 1
fi

# Import the database
docker-compose exec -T db mysql -u wordpress -pwordpress wordpress < "$SQL_FILE"

if [ $? -eq 0 ]; then
    echo "Database imported successfully from $SQL_FILE"

    # Update site URL if needed
    echo "Updating site URL to http://localhost:8080..."
    docker-compose exec wpcli wp search-replace "$(docker-compose exec wpcli wp option get siteurl)" "http://localhost:8080" --skip-columns=guid
else
    echo "Failed to import database"
    exit 1
fi