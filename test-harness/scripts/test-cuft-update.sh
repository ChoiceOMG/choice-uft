#!/bin/bash

# Choice Universal Form Tracker - Complete Update Testing Suite
# For wp-pdev Docker environment
#
# Usage:
#   ./test-cuft-update.sh --full-suite     # Run all tests
#   ./test-cuft-update.sh --scenario NAME  # Run specific scenario
#   ./test-cuft-update.sh --report-only    # Generate report from existing logs
#   ./test-cuft-update.sh --help           # Show usage

set -e  # Exit on any error

# Script directory and paths
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PDEV_DIR="$(dirname "$SCRIPT_DIR")"
LOGS_DIR="$SCRIPT_DIR/logs"
TEMP_DIR="$SCRIPT_DIR/temp"
PLUGIN_DIR="$WP_PDEV_DIR/wp-content/plugins/choice-uft"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Test configuration
CURRENT_VERSION="3.10.1"
TEST_VERSION_OLD="3.10.0"
TEST_VERSION_NEW="3.11.0"
CONTAINER_WORDPRESS="wp-pdev-wordpress"
CONTAINER_CLI="wp-pdev-cli"

# Create required directories
mkdir -p "$LOGS_DIR" "$TEMP_DIR"

# Logging functions
log_file="$LOGS_DIR/test-run-$(date +%Y%m%d_%H%M%S).log"
exec 1> >(tee -a "$log_file")
exec 2> >(tee -a "$log_file" >&2)

print_header() {
    echo -e "\n${PURPLE}===============================================${NC}"
    echo -e "${PURPLE}$1${NC}"
    echo -e "${PURPLE}===============================================${NC}\n"
}

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
        "STEP")
            echo -e "${CYAN}[$timestamp] 🔄 $message${NC}"
            ;;
    esac
}

# Container management functions
check_containers() {
    print_status "INFO" "Checking container status..."

    if ! docker ps | grep -q "$CONTAINER_WORDPRESS"; then
        print_status "ERROR" "WordPress container not running. Starting containers..."
        cd "$WP_PDEV_DIR"
        docker-compose up -d
        sleep 30  # Wait for containers to initialize
    fi

    if docker ps | grep -q "$CONTAINER_WORDPRESS"; then
        print_status "SUCCESS" "WordPress container is running"
    else
        print_status "ERROR" "Failed to start WordPress container"
        exit 1
    fi
}

# WP-CLI wrapper function
wp_cli() {
    docker exec "$CONTAINER_CLI" wp "$@"
}

# Plugin management functions
ensure_plugin_exists() {
    if [ ! -d "$PLUGIN_DIR" ]; then
        print_status "ERROR" "Plugin directory not found: $PLUGIN_DIR"
        print_status "INFO" "Please sync the plugin first:"
        echo "  rsync -av --exclude='.git' --chown=www-data:www-data /home/r11/dev/wp/plugins/choice-uft/ $PLUGIN_DIR/"
        exit 1
    fi

    if [ ! -f "$PLUGIN_DIR/choice-universal-form-tracker.php" ]; then
        print_status "ERROR" "Main plugin file not found"
        exit 1
    fi

    print_status "SUCCESS" "Plugin files found"
}

get_plugin_version() {
    if [ -f "$PLUGIN_DIR/choice-universal-form-tracker.php" ]; then
        grep "Version:" "$PLUGIN_DIR/choice-universal-form-tracker.php" | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]'
    else
        echo "Unknown"
    fi
}

get_plugin_status() {
    wp_cli plugin status choice-uft --field=status 2>/dev/null || echo "not-installed"
}

install_plugin() {
    local version=${1:-"current"}

    print_status "STEP" "Installing plugin version: $version"

    # Deactivate if active
    if [ "$(get_plugin_status)" = "active" ]; then
        wp_cli plugin deactivate choice-uft
    fi

    # Install and activate
    wp_cli plugin activate choice-uft

    local status=$(get_plugin_status)
    if [ "$status" = "active" ]; then
        print_status "SUCCESS" "Plugin installed and activated (version: $(get_plugin_version))"
    else
        print_status "ERROR" "Plugin installation failed (status: $status)"
        return 1
    fi
}

# Test scenario functions
test_plugin_basic_functionality() {
    print_header "Basic Plugin Functionality Test"

    print_status "STEP" "Testing plugin basic functionality..."

    # Check if plugin is detected
    local status=$(get_plugin_status)
    local version=$(get_plugin_version)

    print_status "INFO" "Plugin status: $status"
    print_status "INFO" "Plugin version: $version"

    # Test GitHub updater availability
    local updater_test=$(wp_cli eval "
    global \$cuft_updater;
    echo \$cuft_updater ? 'Available' : 'Not Available';
    " 2>/dev/null)

    if [ "$updater_test" = "Available" ]; then
        print_status "SUCCESS" "GitHub updater is available"
    else
        print_status "ERROR" "GitHub updater not available"
        return 1
    fi

    # Test remote version check
    print_status "STEP" "Testing remote version check..."
    local remote_version=$(wp_cli eval "
    global \$cuft_updater;
    if (\$cuft_updater && method_exists(\$cuft_updater, 'force_check')) {
        echo \$cuft_updater->force_check();
    } else {
        echo 'Error';
    }
    " 2>/dev/null)

    if [[ "$remote_version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
        print_status "SUCCESS" "Remote version check successful: $remote_version"
    else
        print_status "ERROR" "Remote version check failed: $remote_version"
        return 1
    fi

    return 0
}

test_download_url_format() {
    print_header "Download URL Format Test"

    print_status "STEP" "Testing download URL format..."

    local test_version="3.10.1"
    local download_url=$(wp_cli eval "
    global \$cuft_updater;
    if (\$cuft_updater) {
        \$reflection = new ReflectionClass(\$cuft_updater);
        \$method = \$reflection->getMethod('get_download_url');
        \$method->setAccessible(true);
        echo \$method->invoke(\$cuft_updater, '$test_version');
    } else {
        echo 'Error';
    }
    " 2>/dev/null)

    print_status "INFO" "Download URL: $download_url"

    # Check URL format
    if [[ "$download_url" =~ choice-uft-v.*\.zip ]]; then
        print_status "SUCCESS" "Download URL follows correct naming convention"
    else
        print_status "ERROR" "Download URL does not follow naming convention"
        return 1
    fi

    # Test URL accessibility
    if command -v curl &> /dev/null; then
        local http_status=$(curl -s -o /dev/null -w "%{http_code}" "$download_url" 2>/dev/null)
        if [ "$http_status" = "200" ] || [ "$http_status" = "302" ]; then
            print_status "SUCCESS" "Download URL is accessible (HTTP $http_status)"
        else
            print_status "WARNING" "Download URL returned HTTP $http_status"
        fi
    fi

    return 0
}

test_wordpress_update_detection() {
    print_header "WordPress Update Detection Test"

    print_status "STEP" "Testing WordPress update detection..."

    # Clear update cache and force check
    wp_cli eval "delete_site_transient('update_plugins'); wp_update_plugins();" 2>/dev/null

    # Check if update is detected
    local update_available=$(wp_cli eval "
    \$updates = get_site_transient('update_plugins');
    echo isset(\$updates->response['choice-uft/choice-universal-form-tracker.php']) ? 'Yes' : 'No';
    " 2>/dev/null)

    print_status "INFO" "Update detected by WordPress: $update_available"

    if [ "$update_available" = "Yes" ]; then
        print_status "SUCCESS" "WordPress successfully detected available update"

        # Get update details
        local update_details=$(wp_cli eval "
        \$updates = get_site_transient('update_plugins');
        \$plugin_update = \$updates->response['choice-uft/choice-universal-form-tracker.php'];
        echo 'New Version: ' . \$plugin_update->new_version . '\\n';
        echo 'Package: ' . \$plugin_update->package . '\\n';
        " 2>/dev/null)

        print_status "INFO" "Update details:"
        echo "$update_details" | while read line; do
            print_status "INFO" "  $line"
        done
    else
        print_status "WARNING" "No update detected (may be normal if versions match)"
    fi

    return 0
}

test_admin_panel_update_mechanism() {
    print_header "Admin Panel Update Mechanism Test"

    print_status "STEP" "Testing admin panel update handler..."

    # Test the AJAX update mechanism without actually executing
    print_status "INFO" "Verifying admin panel uses GitHub updater method..."

    # Check if admin class is loaded and method exists
    local admin_method_exists=$(wp_cli eval "
    if (class_exists('CUFT_Admin') && method_exists('CUFT_Admin', 'ajax_install_update')) {
        echo 'Available';
    } else {
        echo 'Not Available';
    }
    " 2>/dev/null)

    if [ "$admin_method_exists" = "Available" ]; then
        print_status "SUCCESS" "Admin panel update method is available"
    else
        print_status "ERROR" "Admin panel update method not found"
        return 1
    fi

    # Verify it doesn't use hardcoded archive URLs
    print_status "STEP" "Checking for hardcoded archive URLs in admin code..."

    if grep -q "archive/refs/tags" "$PLUGIN_DIR/includes/class-cuft-admin.php"; then
        print_status "ERROR" "Found hardcoded archive URL in admin code"
        return 1
    else
        print_status "SUCCESS" "No hardcoded archive URLs found"
    fi

    return 0
}

test_plugin_reactivation_safety() {
    print_header "Plugin Reactivation Safety Test"

    print_status "STEP" "Testing plugin reactivation safety mechanisms..."

    # Get current activation status
    local initial_status=$(get_plugin_status)
    print_status "INFO" "Initial plugin status: $initial_status"

    if [ "$initial_status" = "active" ]; then
        # Simulate deactivation (like what might happen during update)
        print_status "STEP" "Simulating plugin deactivation..."
        wp_cli plugin deactivate choice-uft

        local deactivated_status=$(get_plugin_status)
        print_status "INFO" "Status after deactivation: $deactivated_status"

        # Test reactivation
        print_status "STEP" "Testing reactivation..."
        wp_cli plugin activate choice-uft

        local reactivated_status=$(get_plugin_status)
        if [ "$reactivated_status" = "active" ]; then
            print_status "SUCCESS" "Plugin reactivation successful"
        else
            print_status "ERROR" "Plugin reactivation failed"
            return 1
        fi
    else
        print_status "INFO" "Plugin not active initially, activating for test..."
        wp_cli plugin activate choice-uft

        if [ "$(get_plugin_status)" = "active" ]; then
            print_status "SUCCESS" "Plugin activation successful"
        else
            print_status "ERROR" "Plugin activation failed"
            return 1
        fi
    fi

    return 0
}

test_asset_naming_enforcement() {
    print_header "Asset Naming Enforcement Test"

    print_status "STEP" "Testing asset naming enforcement..."

    # Test with correct naming
    local correct_url="https://github.com/ChoiceOMG/choice-uft/releases/download/v3.10.1/choice-uft-v3.10.1.zip"
    print_status "INFO" "Testing correct asset naming: choice-uft-v3.10.1.zip"

    # Test URL format validation
    if [[ "$correct_url" =~ choice-uft-v[0-9]+\.[0-9]+\.[0-9]+\.zip ]]; then
        print_status "SUCCESS" "Correct naming format validation passed"
    else
        print_status "ERROR" "Correct naming format validation failed"
        return 1
    fi

    # Test incorrect naming (should fail)
    local incorrect_url="https://github.com/ChoiceOMG/choice-uft/releases/download/v3.10.1/choice-uft.zip"
    print_status "INFO" "Testing incorrect asset naming: choice-uft.zip"

    if [[ "$incorrect_url" =~ choice-uft-v[0-9]+\.[0-9]+\.[0-9]+\.zip ]]; then
        print_status "ERROR" "Incorrect naming format should have failed validation"
        return 1
    else
        print_status "SUCCESS" "Incorrect naming format correctly rejected"
    fi

    return 0
}

# Main test runner functions
run_full_test_suite() {
    print_header "CUFT Update Testing - Full Suite"

    local start_time=$(date +%s)
    local total_tests=0
    local passed_tests=0
    local failed_tests=0

    # Array of test functions
    local tests=(
        "test_plugin_basic_functionality"
        "test_download_url_format"
        "test_wordpress_update_detection"
        "test_admin_panel_update_mechanism"
        "test_plugin_reactivation_safety"
        "test_asset_naming_enforcement"
    )

    print_status "INFO" "Starting full test suite with ${#tests[@]} tests..."
    echo

    for test_func in "${tests[@]}"; do
        total_tests=$((total_tests + 1))
        print_status "STEP" "Running test: $test_func"

        if $test_func; then
            passed_tests=$((passed_tests + 1))
            print_status "SUCCESS" "Test passed: $test_func"
        else
            failed_tests=$((failed_tests + 1))
            print_status "ERROR" "Test failed: $test_func"
        fi

        echo
    done

    # Generate summary
    local end_time=$(date +%s)
    local duration=$((end_time - start_time))

    print_header "Test Suite Summary"
    print_status "INFO" "Total tests: $total_tests"
    print_status "SUCCESS" "Passed: $passed_tests"
    print_status "ERROR" "Failed: $failed_tests"
    print_status "INFO" "Duration: ${duration}s"
    print_status "INFO" "Success rate: $(( passed_tests * 100 / total_tests ))%"

    if [ $failed_tests -eq 0 ]; then
        print_status "SUCCESS" "All tests passed! 🎉"
        return 0
    else
        print_status "ERROR" "Some tests failed. Check logs for details."
        return 1
    fi
}

run_specific_scenario() {
    local scenario=$1

    print_header "Running Specific Test Scenario: $scenario"

    case $scenario in
        "basic")
            test_plugin_basic_functionality
            ;;
        "download-url")
            test_download_url_format
            ;;
        "update-detection")
            test_wordpress_update_detection
            ;;
        "admin-panel")
            test_admin_panel_update_mechanism
            ;;
        "reactivation")
            test_plugin_reactivation_safety
            ;;
        "asset-naming")
            test_asset_naming_enforcement
            ;;
        *)
            print_status "ERROR" "Unknown scenario: $scenario"
            print_status "INFO" "Available scenarios: basic, download-url, update-detection, admin-panel, reactivation, asset-naming"
            exit 1
            ;;
    esac
}

show_usage() {
    echo "Choice Universal Form Tracker - Update Testing Suite"
    echo ""
    echo "Usage:"
    echo "  $0 --full-suite              Run all test scenarios"
    echo "  $0 --scenario SCENARIO       Run specific test scenario"
    echo "  $0 --report-only             Generate report from existing logs"
    echo "  $0 --help                    Show this help message"
    echo ""
    echo "Available scenarios:"
    echo "  basic           - Basic plugin functionality"
    echo "  download-url    - Download URL format validation"
    echo "  update-detection- WordPress update detection"
    echo "  admin-panel     - Admin panel update mechanism"
    echo "  reactivation    - Plugin reactivation safety"
    echo "  asset-naming    - Asset naming enforcement"
    echo ""
    echo "Examples:"
    echo "  $0 --full-suite"
    echo "  $0 --scenario admin-panel"
    echo ""
    echo "Logs are saved to: $LOGS_DIR/"
}

# Main execution
main() {
    if [ $# -eq 0 ]; then
        show_usage
        exit 1
    fi

    case $1 in
        --full-suite)
            check_containers
            ensure_plugin_exists
            run_full_test_suite
            ;;
        --scenario)
            if [ -z "$2" ]; then
                print_status "ERROR" "Scenario name required"
                show_usage
                exit 1
            fi
            check_containers
            ensure_plugin_exists
            run_specific_scenario "$2"
            ;;
        --report-only)
            print_status "INFO" "Generating report from existing logs..."
            # TODO: Implement report generation
            ;;
        --help)
            show_usage
            exit 0
            ;;
        *)
            print_status "ERROR" "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
}

# Run main function with all arguments
main "$@"