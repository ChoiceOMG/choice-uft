#!/bin/bash

echo "Starting WordPress development environment..."

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    echo "Error: Docker is not running. Please start Docker Desktop first."
    exit 1
fi

# Start containers
docker-compose up -d

# Wait for WordPress to be ready
echo "Waiting for WordPress to be ready..."
sleep 10

# Check if WordPress is installed
if docker-compose exec wpcli wp core is-installed 2>/dev/null; then
    echo "WordPress is already installed."
else
    echo "Installing WordPress..."
    docker-compose exec wpcli wp core install \
        --url="http://localhost:8080" \
        --title="WordPress Plugin Dev" \
        --admin_user="admin" \
        --admin_password="admin" \
        --admin_email="admin@localhost.local" \
        --skip-email

    # Enable debug mode
    docker-compose exec wpcli wp config set WP_DEBUG true --raw
    docker-compose exec wpcli wp config set WP_DEBUG_LOG true --raw
    docker-compose exec wpcli wp config set WP_DEBUG_DISPLAY false --raw

    # Install useful plugins for development
    echo "Installing development plugins..."
    docker-compose exec wpcli wp plugin install query-monitor --activate
    docker-compose exec wpcli wp plugin install debug-bar --activate

    echo "WordPress installation complete!"
fi

# Create symlink for choice-uft if it exists
if [ -d "../choice-uft" ] && [ ! -L "plugins/choice-uft" ]; then
    echo "Creating symlink for choice-uft plugin..."
    ln -s ../../choice-uft plugins/choice-uft
    docker-compose exec wpcli wp plugin activate choice-uft
fi

echo ""
echo "WordPress is ready!"
echo "----------------------------------------"
echo "WordPress: http://localhost:8080"
echo "Admin: http://localhost:8080/wp-admin"
echo "Username: admin"
echo "Password: admin"
echo "----------------------------------------"
echo "MailCatcher: http://localhost:1080"
echo "MySQL CLI: ./scripts/db-cli.sh"
echo "----------------------------------------"