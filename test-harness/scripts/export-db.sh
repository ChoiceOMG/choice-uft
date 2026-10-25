#!/bin/bash

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="backup_${TIMESTAMP}.sql"

echo "Exporting database to $BACKUP_FILE..."

# Check if containers are running
if ! docker-compose ps | grep -q "Up"; then
    echo "Error: Containers are not running. Run ./scripts/start.sh first."
    exit 1
fi

# Export the database
docker-compose exec db mysqldump -u wordpress -pwordpress wordpress > "$BACKUP_FILE"

if [ $? -eq 0 ]; then
    echo "Database exported successfully to $BACKUP_FILE"
    echo "File size: $(du -h $BACKUP_FILE | cut -f1)"
else
    echo "Failed to export database"
    exit 1
fi