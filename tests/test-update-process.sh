#!/bin/bash

# Choice Universal Form Tracker - Update Process Test Script
# Usage: Run from development environment with wp-cli available
# ./tests/test-update-process.sh

echo "==============================================="
echo "Choice Universal Form Tracker - Update Test"
echo "==============================================="
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    local status=$1
    local message=$2

    case $status in
        "SUCCESS")
            echo -e "${GREEN}✅ $message${NC}"
            ;;
        "ERROR")
            echo -e "${RED}❌ $message${NC}"
            ;;
        "WARNING")
            echo -e "${YELLOW}⚠️  $message${NC}"
            ;;
        "INFO")
            echo -e "${BLUE}ℹ️  $message${NC}"
            ;;
    esac
}

# Check if wp-cli is available
if ! command -v wp &> /dev/null; then
    print_status "ERROR" "wp-cli not found. Please install wp-cli first."
    exit 1
fi

# Check if plugin is installed
if ! wp plugin is-installed choice-universal-form-tracker 2>/dev/null; then
    print_status "ERROR" "Choice Universal Form Tracker plugin not found."
    print_status "INFO" "Please install the plugin first in your development environment."
    exit 1
fi

print_status "INFO" "Testing plugin update functionality..."
echo

# Test 1: Check plugin status
echo "1. Plugin Status Check"
echo "----------------------"
PLUGIN_STATUS=$(wp plugin status choice-uft --field=status 2>/dev/null)
if [ "$PLUGIN_STATUS" = "active" ]; then
    print_status "SUCCESS" "Plugin is active"
else
    print_status "WARNING" "Plugin status: $PLUGIN_STATUS"
fi

# Get current version
CURRENT_VERSION=$(wp eval "echo defined('CUFT_VERSION') ? CUFT_VERSION : 'Unknown';" 2>/dev/null)
print_status "INFO" "Current version: $CURRENT_VERSION"
echo

# Test 2: Check updater availability
echo "2. GitHub Updater Availability"
echo "------------------------------"
UPDATER_AVAILABLE=$(wp eval "global \$cuft_updater; echo \$cuft_updater ? 'Available' : 'Not Available';" 2>/dev/null)
if [ "$UPDATER_AVAILABLE" = "Available" ]; then
    print_status "SUCCESS" "GitHub updater is available"
else
    print_status "ERROR" "GitHub updater not available"
    exit 1
fi
echo

# Test 3: Check remote version
echo "3. Remote Version Check"
echo "----------------------"
print_status "INFO" "Checking for updates..."
REMOTE_VERSION=$(wp eval "
global \$cuft_updater;
if (\$cuft_updater && method_exists(\$cuft_updater, 'force_check')) {
    echo \$cuft_updater->force_check();
} else {
    echo 'Error: force_check method not available';
}
" 2>/dev/null)

if [[ "$REMOTE_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    print_status "SUCCESS" "Latest version available: $REMOTE_VERSION"
else
    print_status "ERROR" "Failed to get remote version: $REMOTE_VERSION"
fi
echo

# Test 4: Check download URL
echo "4. Download URL Test"
echo "-------------------"
DOWNLOAD_URL=$(wp eval "
global \$cuft_updater;
if (\$cuft_updater) {
    \$reflection = new ReflectionClass(\$cuft_updater);
    \$method = \$reflection->getMethod('get_download_url');
    \$method->setAccessible(true);
    echo \$method->invoke(\$cuft_updater, '$REMOTE_VERSION');
} else {
    echo 'Error: Updater not available';
}
" 2>/dev/null)

if [[ "$DOWNLOAD_URL" =~ choice-uft-v.*\.zip ]]; then
    print_status "SUCCESS" "Download URL format is correct"
    print_status "INFO" "URL: $DOWNLOAD_URL"

    # Test if URL is accessible
    HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" "$DOWNLOAD_URL" 2>/dev/null)
    if [ "$HTTP_STATUS" = "200" ] || [ "$HTTP_STATUS" = "302" ]; then
        print_status "SUCCESS" "Download URL is accessible (HTTP $HTTP_STATUS)"
    else
        print_status "WARNING" "Download URL returned HTTP $HTTP_STATUS"
    fi
else
    print_status "ERROR" "Invalid download URL format: $DOWNLOAD_URL"
fi
echo

# Test 5: Update detection
echo "5. WordPress Update Detection"
echo "----------------------------"
print_status "INFO" "Forcing WordPress to check for updates..."
wp eval "delete_site_transient('update_plugins'); wp_update_plugins();" 2>/dev/null

UPDATE_AVAILABLE=$(wp eval "
\$updates = get_site_transient('update_plugins');
echo isset(\$updates->response['choice-uft/choice-universal-form-tracker.php']) ? 'Yes' : 'No';
" 2>/dev/null)

if [ "$UPDATE_AVAILABLE" = "Yes" ]; then
    print_status "SUCCESS" "WordPress detected update available"
else
    print_status "WARNING" "WordPress did not detect update (this may be normal if versions match)"
fi
echo

# Test 6: Version comparison
echo "6. Version Comparison"
echo "--------------------"
if [ "$CURRENT_VERSION" != "Unknown" ] && [[ "$REMOTE_VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    VERSION_COMPARE=$(wp eval "echo version_compare('$CURRENT_VERSION', '$REMOTE_VERSION', '<') ? 'Update Available' : 'Up to Date';" 2>/dev/null)
    if [ "$VERSION_COMPARE" = "Update Available" ]; then
        print_status "INFO" "Update available: $CURRENT_VERSION → $REMOTE_VERSION"
    else
        print_status "SUCCESS" "Plugin is up to date"
    fi
else
    print_status "WARNING" "Cannot compare versions (Current: $CURRENT_VERSION, Remote: $REMOTE_VERSION)"
fi
echo

# Test 7: Asset validation
echo "7. Release Asset Validation"
echo "--------------------------"
if [[ "$DOWNLOAD_URL" =~ choice-uft-v([0-9]+\.[0-9]+\.[0-9]+)\.zip ]]; then
    VERSION_IN_URL="${BASH_REMATCH[1]}"
    if [ "$VERSION_IN_URL" = "$REMOTE_VERSION" ]; then
        print_status "SUCCESS" "Asset naming follows convention: choice-uft-v$VERSION_IN_URL.zip"
    else
        print_status "ERROR" "Version mismatch in asset name"
    fi
else
    print_status "ERROR" "Asset does not follow naming convention"
fi
echo

# Summary
echo "==============================================="
echo "Test Summary"
echo "==============================================="
echo "Current Version: $CURRENT_VERSION"
echo "Remote Version: $REMOTE_VERSION"
echo "Update Available: $VERSION_COMPARE"
echo "Download URL: $DOWNLOAD_URL"
echo
print_status "INFO" "Test completed. Check above for any errors or warnings."

# Instructions for manual testing
echo
echo "==============================================="
echo "Manual Testing Instructions"
echo "==============================================="
echo "1. Go to WordPress Admin → Plugins"
echo "2. Look for update notification on Choice Universal Form Tracker"
echo "3. Click 'Update Now' to test update process"
echo "4. Verify plugin remains active after update"
echo "5. Check that version number updates correctly"
echo
echo "For admin panel testing:"
echo "1. Go to Settings → Universal Form Tracker"
echo "2. Scroll to 'GitHub Auto Updates' section"
echo "3. Click 'Force Update Check'"
echo "4. If update available, click 'Install Update'"
echo

exit 0