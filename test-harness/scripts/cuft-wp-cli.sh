#!/bin/bash

# WP-CLI Wrapper for CUFT Testing in Docker Environment
# Provides convenient commands for testing the Choice Universal Form Tracker plugin
#
# Usage:
#   ./cuft-wp-cli.sh plugin status           # Check plugin status
#   ./cuft-wp-cli.sh plugin install          # Install and activate plugin
#   ./cuft-wp-cli.sh plugin update-check     # Force update check
#   ./cuft-wp-cli.sh plugin version          # Get plugin version info
#   ./cuft-wp-cli.sh updater test            # Test GitHub updater
#   ./cuft-wp-cli.sh updater remote-version  # Get remote version
#   ./cuft-wp-cli.sh wordpress info          # WordPress environment info
#   ./cuft-wp-cli.sh debug enable            # Enable debug mode
#   ./cuft-wp-cli.sh debug disable           # Disable debug mode

set -e

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PDEV_DIR="$(dirname "$SCRIPT_DIR")"
CONTAINER_CLI="wp-pdev-cli"
CONTAINER_WORDPRESS="wp-pdev-wordpress"
PLUGIN_SLUG="choice-uft"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Logging functions
print_status() {
    local status=$1
    local message=$2
    local timestamp=$(date '+%H:%M:%S')

    case $status in
        "SUCCESS")
            echo -e "${GREEN}[$timestamp] ✅ $message${NC}"
            ;;
        "ERROR")
            echo -e "${RED}[$timestamp] ❌ $message${NC}"
            ;;
        "WARNING")
            echo -e "${YELLOW}[$timestamp] ⚠️  $message${NC}"
            ;;
        "INFO")
            echo -e "${BLUE}[$timestamp] ℹ️  $message${NC}"
            ;;
        "CMD")
            echo -e "${CYAN}[$timestamp] 🔧 $message${NC}"
            ;;
    esac
}

print_header() {
    echo -e "\n${PURPLE}===============================================${NC}"
    echo -e "${PURPLE}$1${NC}"
    echo -e "${PURPLE}===============================================${NC}\n"
}

# Container management
check_containers() {
    if ! docker ps | grep -q "$CONTAINER_CLI"; then
        print_status "ERROR" "WP-CLI container not running"
        print_status "INFO" "Start containers with: cd $WP_PDEV_DIR && docker-compose up -d"
        return 1
    fi

    if ! docker ps | grep -q "$CONTAINER_WORDPRESS"; then
        print_status "ERROR" "WordPress container not running"
        return 1
    fi

    return 0
}

# WP-CLI wrapper function
wp_cli() {
    print_status "CMD" "wp $*"
    docker exec "$CONTAINER_CLI" wp "$@"
}

# Plugin management functions
plugin_status() {
    print_header "Plugin Status Information"

    if ! check_containers; then
        exit 1
    fi

    # Basic status
    local status=$(wp_cli plugin status "$PLUGIN_SLUG" --field=status 2>/dev/null || echo "not-installed")
    print_status "INFO" "Plugin status: $status"

    if [ "$status" = "not-installed" ]; then
        print_status "WARNING" "Plugin is not installed"
        return 1
    fi

    # Version information
    local file_version=$(wp_cli eval "
    \$plugin_file = WP_PLUGIN_DIR . '/$PLUGIN_SLUG/$PLUGIN_SLUG.php';
    if (file_exists(\$plugin_file)) {
        \$content = file_get_contents(\$plugin_file);
        if (preg_match('/Version:[[:space:]]*([0-9\.]+)/', \$content, \$matches)) {
            echo \$matches[1];
        } else {
            echo 'Unknown';
        }
    } else {
        echo 'File not found';
    }
    " 2>/dev/null || echo "Unknown")

    print_status "INFO" "Plugin file version: $file_version"

    # WordPress constant version
    local wp_version=$(wp_cli eval "echo defined('CUFT_VERSION') ? CUFT_VERSION : 'Not loaded';" 2>/dev/null || echo "Error")
    print_status "INFO" "WordPress constant version: $wp_version"

    # Plugin path
    local plugin_path=$(wp_cli plugin path "$PLUGIN_SLUG" 2>/dev/null || echo "Not found")
    print_status "INFO" "Plugin path: $plugin_path"

    # GitHub updater status
    local updater_status=$(wp_cli eval "global \$cuft_updater; echo \$cuft_updater ? 'Available' : 'Not available';" 2>/dev/null || echo "Error")
    print_status "INFO" "GitHub updater: $updater_status"

    return 0
}

plugin_install() {
    print_header "Installing and Activating Plugin"

    if ! check_containers; then
        exit 1
    fi

    # Check if plugin directory exists
    local plugin_dir="$WP_PDEV_DIR/wp-content/plugins/$PLUGIN_SLUG"
    if [ ! -d "$plugin_dir" ]; then
        print_status "ERROR" "Plugin directory not found: $plugin_dir"
        print_status "INFO" "Please sync the plugin first:"
        echo "  rsync -av --exclude='.git' --exclude='test-harness' --chown=www-data:www-data /home/r11/dev/wp/plugins/choice-uft/ $plugin_dir/"
        exit 1
    fi

    # Activate plugin
    if wp_cli plugin activate "$PLUGIN_SLUG"; then
        print_status "SUCCESS" "Plugin activated successfully"
    else
        print_status "ERROR" "Plugin activation failed"
        exit 1
    fi

    # Verify activation
    plugin_status
}

plugin_update_check() {
    print_header "Forcing Plugin Update Check"

    if ! check_containers; then
        exit 1
    fi

    # Clear update transients
    print_status "INFO" "Clearing update transients..."
    wp_cli eval "
    delete_site_transient('update_plugins');
    delete_transient('cuft_github_version');
    delete_transient('cuft_github_changelog');
    echo 'Transients cleared';
    " 2>/dev/null

    # Force WordPress update check
    print_status "INFO" "Forcing WordPress update check..."
    wp_cli eval "wp_update_plugins(); echo 'Update check completed';" 2>/dev/null

    # Check for available updates
    local update_available=$(wp_cli eval "
    \$updates = get_site_transient('update_plugins');
    if (isset(\$updates->response['$PLUGIN_SLUG/$PLUGIN_SLUG.php'])) {
        echo 'Yes - Version: ' . \$updates->response['$PLUGIN_SLUG/$PLUGIN_SLUG.php']->new_version;
    } else {
        echo 'No';
    }
    " 2>/dev/null || echo "Error")

    print_status "INFO" "Update available: $update_available"

    return 0
}

plugin_version_info() {
    print_header "Plugin Version Information"

    if ! check_containers; then
        exit 1
    fi

    # Current installed version
    local current_version=$(wp_cli eval "echo defined('CUFT_VERSION') ? CUFT_VERSION : 'Unknown';" 2>/dev/null || echo "Error")
    print_status "INFO" "Current version: $current_version"

    # Remote version check
    print_status "INFO" "Checking remote version..."
    local remote_version=$(wp_cli eval "
    global \$cuft_updater;
    if (\$cuft_updater && method_exists(\$cuft_updater, 'force_check')) {
        echo \$cuft_updater->force_check();
    } else {
        echo 'Updater not available';
    }
    " 2>/dev/null || echo "Error")

    print_status "INFO" "Remote version: $remote_version"

    # Version comparison
    if [[ "$current_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] && [[ "$remote_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        local comparison=$(wp_cli eval "
        if (version_compare('$current_version', '$remote_version', '<')) {
            echo 'Update available';
        } elseif (version_compare('$current_version', '$remote_version', '>')) {
            echo 'Newer than remote';
        } else {
            echo 'Up to date';
        }
        " 2>/dev/null || echo "Unknown")

        print_status "INFO" "Version status: $comparison"
    fi

    return 0
}

# GitHub updater functions
updater_test() {
    print_header "Testing GitHub Updater Functionality"

    if ! check_containers; then
        exit 1
    fi

    # Check if updater is available
    local updater_available=$(wp_cli eval "global \$cuft_updater; echo \$cuft_updater ? 'true' : 'false';" 2>/dev/null || echo "false")

    if [ "$updater_available" = "false" ]; then
        print_status "ERROR" "GitHub updater not available"
        return 1
    fi

    print_status "SUCCESS" "GitHub updater is available"

    # Test remote version check
    print_status "INFO" "Testing remote version check..."
    local remote_version=$(wp_cli eval "
    global \$cuft_updater;
    \$reflection = new ReflectionClass(\$cuft_updater);
    \$method = \$reflection->getMethod('get_remote_version');
    \$method->setAccessible(true);
    echo \$method->invoke(\$cuft_updater);
    " 2>/dev/null || echo "Error")

    if [[ "$remote_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        print_status "SUCCESS" "Remote version check: $remote_version"
    else
        print_status "ERROR" "Remote version check failed: $remote_version"
        return 1
    fi

    # Test download URL generation
    print_status "INFO" "Testing download URL generation..."
    local download_url=$(wp_cli eval "
    global \$cuft_updater;
    \$reflection = new ReflectionClass(\$cuft_updater);
    \$method = \$reflection->getMethod('get_download_url');
    \$method->setAccessible(true);
    echo \$method->invoke(\$cuft_updater, '$remote_version');
    " 2>/dev/null || echo "Error")

    print_status "INFO" "Download URL: $download_url"

    # Validate URL format
    if [[ "$download_url" =~ choice-uft-v.*\.zip ]]; then
        print_status "SUCCESS" "Download URL format is correct"
    else
        print_status "ERROR" "Download URL format is incorrect"
        return 1
    fi

    # Test URL accessibility (if curl is available)
    if command -v curl &> /dev/null; then
        print_status "INFO" "Testing URL accessibility..."
        local http_status=$(curl -s -o /dev/null -w "%{http_code}" "$download_url" 2>/dev/null || echo "000")

        if [ "$http_status" = "200" ] || [ "$http_status" = "302" ]; then
            print_status "SUCCESS" "URL is accessible (HTTP $http_status)"
        else
            print_status "WARNING" "URL returned HTTP $http_status"
        fi
    fi

    return 0
}

updater_remote_version() {
    print_header "Getting Remote Version"

    if ! check_containers; then
        exit 1
    fi

    local remote_version=$(wp_cli eval "
    global \$cuft_updater;
    if (\$cuft_updater && method_exists(\$cuft_updater, 'force_check')) {
        echo \$cuft_updater->force_check();
    } else {
        echo 'Error: Updater not available';
    }
    " 2>/dev/null || echo "Error")

    echo "$remote_version"
}

# WordPress environment functions
wordpress_info() {
    print_header "WordPress Environment Information"

    if ! check_containers; then
        exit 1
    fi

    # WordPress version
    local wp_version=$(wp_cli core version 2>/dev/null || echo "Unknown")
    print_status "INFO" "WordPress version: $wp_version"

    # PHP version
    local php_version=$(wp_cli eval "echo PHP_VERSION;" 2>/dev/null || echo "Unknown")
    print_status "INFO" "PHP version: $php_version"

    # Memory limit
    local memory_limit=$(wp_cli eval "echo ini_get('memory_limit');" 2>/dev/null || echo "Unknown")
    print_status "INFO" "Memory limit: $memory_limit"

    # Debug status
    local debug_enabled=$(wp_cli eval "echo WP_DEBUG ? 'Enabled' : 'Disabled';" 2>/dev/null || echo "Unknown")
    print_status "INFO" "WP_DEBUG: $debug_enabled"

    # Active plugins
    local active_plugins=$(wp_cli plugin list --status=active --format=count 2>/dev/null || echo "0")
    print_status "INFO" "Active plugins: $active_plugins"

    # Database info
    local db_name=$(wp_cli eval "echo DB_NAME;" 2>/dev/null || echo "Unknown")
    print_status "INFO" "Database: $db_name"

    # Site URL
    local site_url=$(wp_cli option get siteurl 2>/dev/null || echo "Unknown")
    print_status "INFO" "Site URL: $site_url"

    return 0
}

# Debug functions
debug_enable() {
    print_header "Enabling Debug Mode"

    if ! check_containers; then
        exit 1
    fi

    # Enable plugin debug options
    wp_cli option update cuft_debug_enabled 1
    wp_cli option update cuft_console_logging "yes"

    print_status "SUCCESS" "Debug mode enabled"
    print_status "INFO" "Check debug.log in wp-content/ for messages"
}

debug_disable() {
    print_header "Disabling Debug Mode"

    if ! check_containers; then
        exit 1
    fi

    # Disable plugin debug options
    wp_cli option update cuft_debug_enabled 0
    wp_cli option update cuft_console_logging "no"

    print_status "SUCCESS" "Debug mode disabled"
}

# Usage function
show_usage() {
    echo "WP-CLI Wrapper for Choice Universal Form Tracker Testing"
    echo ""
    echo "Usage:"
    echo "  $0 plugin status                Check plugin status and version info"
    echo "  $0 plugin install               Install and activate plugin"
    echo "  $0 plugin update-check          Force plugin update check"
    echo "  $0 plugin version               Get version information"
    echo ""
    echo "  $0 updater test                 Test GitHub updater functionality"
    echo "  $0 updater remote-version       Get remote version only"
    echo ""
    echo "  $0 wordpress info               Show WordPress environment info"
    echo ""
    echo "  $0 debug enable                 Enable debug mode"
    echo "  $0 debug disable                Disable debug mode"
    echo ""
    echo "  $0 wp [COMMAND]                 Execute any wp-cli command directly"
    echo ""
    echo "Examples:"
    echo "  $0 plugin status"
    echo "  $0 updater test"
    echo "  $0 wp plugin list"
    echo "  $0 wp option get cuft_gtm_id"
    echo ""
    echo "Container info:"
    echo "  WordPress: $CONTAINER_WORDPRESS"
    echo "  WP-CLI: $CONTAINER_CLI"
}

# Raw WP-CLI command execution
execute_wp_command() {
    if ! check_containers; then
        exit 1
    fi

    shift  # Remove 'wp' from arguments
    wp_cli "$@"
}

# Main execution
main() {
    if [ $# -eq 0 ]; then
        show_usage
        exit 1
    fi

    case $1 in
        plugin)
            case $2 in
                status)
                    plugin_status
                    ;;
                install)
                    plugin_install
                    ;;
                update-check)
                    plugin_update_check
                    ;;
                version)
                    plugin_version_info
                    ;;
                *)
                    print_status "ERROR" "Unknown plugin command: $2"
                    show_usage
                    exit 1
                    ;;
            esac
            ;;
        updater)
            case $2 in
                test)
                    updater_test
                    ;;
                remote-version)
                    updater_remote_version
                    ;;
                *)
                    print_status "ERROR" "Unknown updater command: $2"
                    show_usage
                    exit 1
                    ;;
            esac
            ;;
        wordpress)
            case $2 in
                info)
                    wordpress_info
                    ;;
                *)
                    print_status "ERROR" "Unknown wordpress command: $2"
                    show_usage
                    exit 1
                    ;;
            esac
            ;;
        debug)
            case $2 in
                enable)
                    debug_enable
                    ;;
                disable)
                    debug_disable
                    ;;
                *)
                    print_status "ERROR" "Unknown debug command: $2"
                    show_usage
                    exit 1
                    ;;
            esac
            ;;
        wp)
            execute_wp_command "$@"
            ;;
        --help|help)
            show_usage
            exit 0
            ;;
        *)
            print_status "ERROR" "Unknown command: $1"
            show_usage
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"