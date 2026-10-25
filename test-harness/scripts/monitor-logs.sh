#!/bin/bash

# Elementor Form Debug Monitor Script
# This script monitors both WordPress debug logs and Elementor-specific logs

echo "🔍 Starting Elementor Form Debug Monitor..."
echo "📋 Monitoring the following logs:"
echo "   - WordPress debug.log"
echo "   - Elementor debug.log"
echo "   - PHP error logs"
echo ""
echo "💡 To stop monitoring, press Ctrl+C"
echo "📝 Submit your form now and watch for errors below:"
echo "============================================================"

# Create log files if they don't exist
docker exec cuft-choice-zone-wordpress touch /var/www/html/wp-content/debug.log
docker exec cuft-choice-zone-wordpress touch /var/www/html/wp-content/elementor-debug.log

# Function to display colored log output
display_log() {
    local source=$1
    local line=$2

    # Color codes
    local RED='\033[0;31m'
    local GREEN='\033[0;32m'
    local YELLOW='\033[1;33m'
    local BLUE='\033[0;34m'
    local PURPLE='\033[0;35m'
    local CYAN='\033[0;36m'
    local NC='\033[0m' # No Color

    case $source in
        "ELEMENTOR")
            echo -e "${PURPLE}[ELEMENTOR]${NC} $line"
            ;;
        "MAIL_ERROR")
            echo -e "${RED}[MAIL ERROR]${NC} $line"
            ;;
        "FATAL_ERROR")
            echo -e "${RED}[FATAL]${NC} $line"
            ;;
        "PHP_ERROR")
            echo -e "${YELLOW}[PHP]${NC} $line"
            ;;
        "WORDPRESS")
            echo -e "${BLUE}[WP]${NC} $line"
            ;;
        *)
            echo -e "${GREEN}[LOG]${NC} $line"
            ;;
    esac
}

# Monitor logs with different colors for different types
(
    # Monitor WordPress debug log
    docker exec cuft-choice-zone-wordpress tail -f /var/www/html/wp-content/debug.log 2>/dev/null | while read line; do
        if [[ $line == *"ELEMENTOR_DEBUG"* ]]; then
            display_log "ELEMENTOR" "$line"
        elif [[ $line == *"MAIL_ERROR"* ]]; then
            display_log "MAIL_ERROR" "$line"
        elif [[ $line == *"FATAL"* ]]; then
            display_log "FATAL_ERROR" "$line"
        elif [[ $line == *"PHP"* ]] || [[ $line == *"Fatal error"* ]] || [[ $line == *"Warning"* ]]; then
            display_log "PHP_ERROR" "$line"
        else
            display_log "WORDPRESS" "$line"
        fi
    done &

    # Monitor Elementor-specific debug log
    docker exec cuft-choice-zone-wordpress tail -f /var/www/html/wp-content/elementor-debug.log 2>/dev/null | while read line; do
        display_log "ELEMENTOR" "$line"
    done &

    # Monitor PHP error log inside container
    docker exec cuft-choice-zone-wordpress tail -f /var/log/apache2/error.log 2>/dev/null | while read line; do
        display_log "PHP_ERROR" "$line"
    done &

    # Keep the script running
    wait
)

echo "Monitor stopped."