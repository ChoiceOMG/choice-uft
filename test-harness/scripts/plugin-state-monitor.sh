#!/bin/bash

# Plugin State Monitor for CUFT Testing
# Monitors and logs plugin state changes during testing
#
# Usage:
#   ./plugin-state-monitor.sh --start             # Start monitoring
#   ./plugin-state-monitor.sh --snapshot          # Take a single snapshot
#   ./plugin-state-monitor.sh --compare           # Compare before/after states
#   ./plugin-state-monitor.sh --watch             # Continuous monitoring
#   ./plugin-state-monitor.sh --report            # Generate state report

set -e

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PDEV_DIR="$(dirname "$SCRIPT_DIR")"
LOGS_DIR="$SCRIPT_DIR/logs"
STATE_DIR="$SCRIPT_DIR/state"
PLUGIN_DIR="$WP_PDEV_DIR/wp-content/plugins/choice-uft"

# Container configuration
CONTAINER_CLI="wp-pdev-cli"
CONTAINER_WORDPRESS="wp-pdev-wordpress"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m'

# Create required directories
mkdir -p "$LOGS_DIR" "$STATE_DIR"

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
        "CHANGE")
            echo -e "${CYAN}[$timestamp] 🔄 $message${NC}"
            ;;
        "MONITOR")
            echo -e "${PURPLE}[$timestamp] 👁  $message${NC}"
            ;;
    esac
}

print_header() {
    echo -e "\n${PURPLE}===============================================${NC}"
    echo -e "${PURPLE}$1${NC}"
    echo -e "${PURPLE}===============================================${NC}\n"
}

# Container check functions
check_containers() {
    if ! docker ps | grep -q "$CONTAINER_CLI"; then
        print_status "ERROR" "WP-CLI container not running"
        return 1
    fi

    if ! docker ps | grep -q "$CONTAINER_WORDPRESS"; then
        print_status "ERROR" "WordPress container not running"
        return 1
    fi

    return 0
}

# WP-CLI wrapper
wp_cli() {
    docker exec "$CONTAINER_CLI" wp "$@" 2>/dev/null
}

# State capture functions
capture_plugin_basic_state() {
    local output_file=$1

    print_status "MONITOR" "Capturing basic plugin state..."

    cat > "$output_file" << EOF
{
  "timestamp": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "basic_state": {
EOF

    # Plugin status
    local status=$(wp_cli plugin status choice-uft --field=status 2>/dev/null || echo "not-installed")
    echo "    \"status\": \"$status\"," >> "$output_file"

    # Plugin version from file
    local file_version="Unknown"
    if [ -f "$PLUGIN_DIR/choice-universal-form-tracker.php" ]; then
        file_version=$(grep "Version:" "$PLUGIN_DIR/choice-universal-form-tracker.php" | head -1 | sed 's/.*Version:[[:space:]]*//' | tr -d '[:space:]')
    fi
    echo "    \"file_version\": \"$file_version\"," >> "$output_file"

    # Plugin version from WordPress
    local wp_version=$(wp_cli eval "echo defined('CUFT_VERSION') ? CUFT_VERSION : 'Unknown';" 2>/dev/null || echo "Unknown")
    echo "    \"wp_version\": \"$wp_version\"," >> "$output_file"

    # Plugin path info
    local plugin_path=$(wp_cli plugin path choice-uft 2>/dev/null || echo "not-found")
    echo "    \"plugin_path\": \"$plugin_path\"," >> "$output_file"

    # File existence checks
    local main_file_exists="false"
    if [ -f "$PLUGIN_DIR/choice-universal-form-tracker.php" ]; then
        main_file_exists="true"
    fi
    echo "    \"main_file_exists\": $main_file_exists," >> "$output_file"

    # Directory structure
    local dir_structure=""
    if [ -d "$PLUGIN_DIR" ]; then
        dir_structure=$(find "$PLUGIN_DIR" -type f -name "*.php" | wc -l)
    fi
    echo "    \"php_files_count\": $dir_structure" >> "$output_file"

    echo "  }," >> "$output_file"
}

capture_plugin_detailed_state() {
    local output_file=$1

    print_status "MONITOR" "Capturing detailed plugin state..."

    echo "  \"detailed_state\": {" >> "$output_file"

    # GitHub updater state
    local updater_available=$(wp_cli eval "global \$cuft_updater; echo \$cuft_updater ? 'true' : 'false';" 2>/dev/null || echo "false")
    echo "    \"github_updater_available\": $updater_available," >> "$output_file"

    # Remote version check
    local remote_version="Unknown"
    if [ "$updater_available" = "true" ]; then
        remote_version=$(wp_cli eval "
        global \$cuft_updater;
        if (\$cuft_updater && method_exists(\$cuft_updater, 'get_remote_version')) {
            \$reflection = new ReflectionClass(\$cuft_updater);
            \$method = \$reflection->getMethod('get_remote_version');
            \$method->setAccessible(true);
            echo \$method->invoke(\$cuft_updater);
        } else {
            echo 'Unknown';
        }
        " 2>/dev/null || echo "Unknown")
    fi
    echo "    \"remote_version\": \"$remote_version\"," >> "$output_file"

    # WordPress update detection
    wp_cli eval "delete_site_transient('update_plugins'); wp_update_plugins();" 2>/dev/null || true
    local update_available=$(wp_cli eval "
    \$updates = get_site_transient('update_plugins');
    echo isset(\$updates->response['choice-uft/choice-universal-form-tracker.php']) ? 'true' : 'false';
    " 2>/dev/null || echo "false")
    echo "    \"wp_update_available\": $update_available," >> "$output_file"

    # Admin class availability
    local admin_available=$(wp_cli eval "echo class_exists('CUFT_Admin') ? 'true' : 'false';" 2>/dev/null || echo "false")
    echo "    \"admin_class_available\": $admin_available," >> "$output_file"

    # Options check
    local gtm_id=$(wp_cli option get cuft_gtm_id 2>/dev/null || echo "")
    echo "    \"gtm_id_set\": \"$gtm_id\"," >> "$output_file"

    local github_updates_enabled=$(wp_cli option get cuft_github_updates_enabled 2>/dev/null || echo "false")
    echo "    \"github_updates_enabled\": \"$github_updates_enabled\"" >> "$output_file"

    echo "  }," >> "$output_file"
}

capture_file_structure() {
    local output_file=$1

    print_status "MONITOR" "Capturing file structure..."

    echo "  \"file_structure\": {" >> "$output_file"

    if [ -d "$PLUGIN_DIR" ]; then
        # Get file count by type
        local php_files=$(find "$PLUGIN_DIR" -name "*.php" | wc -l)
        local js_files=$(find "$PLUGIN_DIR" -name "*.js" | wc -l)
        local css_files=$(find "$PLUGIN_DIR" -name "*.css" | wc -l)
        local total_files=$(find "$PLUGIN_DIR" -type f | wc -l)

        echo "    \"plugin_directory_exists\": true," >> "$output_file"
        echo "    \"php_files\": $php_files," >> "$output_file"
        echo "    \"js_files\": $js_files," >> "$output_file"
        echo "    \"css_files\": $css_files," >> "$output_file"
        echo "    \"total_files\": $total_files," >> "$output_file"

        # Directory structure hash (for change detection)
        local structure_hash=$(find "$PLUGIN_DIR" -type f -exec basename {} \; | sort | sha256sum | cut -d' ' -f1)
        echo "    \"structure_hash\": \"$structure_hash\"," >> "$output_file"

        # Check for critical files
        local critical_files=(
            "choice-universal-form-tracker.php"
            "includes/class-cuft-github-updater.php"
            "includes/class-cuft-admin.php"
            "assets/cuft-utm-tracker.js"
        )

        echo "    \"critical_files\": {" >> "$output_file"
        local first=true
        for file in "${critical_files[@]}"; do
            if [ "$first" = true ]; then
                first=false
            else
                echo "," >> "$output_file"
            fi

            if [ -f "$PLUGIN_DIR/$file" ]; then
                echo -n "      \"$file\": true" >> "$output_file"
            else
                echo -n "      \"$file\": false" >> "$output_file"
            fi
        done
        echo "" >> "$output_file"
        echo "    }" >> "$output_file"

    else
        echo "    \"plugin_directory_exists\": false," >> "$output_file"
        echo "    \"structure_hash\": \"\"," >> "$output_file"
        echo "    \"critical_files\": {}" >> "$output_file"
    fi

    echo "  }," >> "$output_file"
}

capture_wordpress_state() {
    local output_file=$1

    print_status "MONITOR" "Capturing WordPress state..."

    echo "  \"wordpress_state\": {" >> "$output_file"

    # WordPress version
    local wp_version=$(wp_cli core version 2>/dev/null || echo "Unknown")
    echo "    \"wp_version\": \"$wp_version\"," >> "$output_file"

    # Active plugins count
    local active_plugins=$(wp_cli plugin list --status=active --format=count 2>/dev/null || echo "0")
    echo "    \"active_plugins_count\": $active_plugins," >> "$output_file"

    # Total plugins count
    local total_plugins=$(wp_cli plugin list --format=count 2>/dev/null || echo "0")
    echo "    \"total_plugins_count\": $total_plugins," >> "$output_file"

    # WordPress memory limit
    local memory_limit=$(wp_cli eval "echo ini_get('memory_limit');" 2>/dev/null || echo "Unknown")
    echo "    \"memory_limit\": \"$memory_limit\"," >> "$output_file"

    # Debug status
    local debug_enabled=$(wp_cli eval "echo WP_DEBUG ? 'true' : 'false';" 2>/dev/null || echo "false")
    echo "    \"debug_enabled\": $debug_enabled" >> "$output_file"

    echo "  }" >> "$output_file"
}

# Main state capture function
capture_complete_state() {
    local snapshot_name=${1:-"snapshot"}
    local output_file="$STATE_DIR/${snapshot_name}_$(date +%Y%m%d_%H%M%S).json"

    print_header "Capturing Complete Plugin State: $snapshot_name"

    if ! check_containers; then
        print_status "ERROR" "Containers not running"
        return 1
    fi

    # Start JSON structure
    echo "{" > "$output_file"

    # Capture different aspects of state
    capture_plugin_basic_state "$output_file"
    capture_plugin_detailed_state "$output_file"
    capture_file_structure "$output_file"
    capture_wordpress_state "$output_file"

    # Close JSON structure (remove last comma and close)
    echo "  \"capture_complete\": true" >> "$output_file"
    echo "}" >> "$output_file"

    print_status "SUCCESS" "State captured: $output_file"
    echo "$output_file"
}

# State comparison functions
compare_states() {
    local before_file=$1
    local after_file=$2
    local comparison_file="$LOGS_DIR/state_comparison_$(date +%Y%m%d_%H%M%S).log"

    print_header "Comparing Plugin States"

    if [ ! -f "$before_file" ] || [ ! -f "$after_file" ]; then
        print_status "ERROR" "State files not found"
        return 1
    fi

    print_status "INFO" "Before: $before_file"
    print_status "INFO" "After: $after_file"
    print_status "INFO" "Comparison: $comparison_file"

    {
        echo "State Comparison Report"
        echo "====================="
        echo "Generated: $(date)"
        echo "Before: $before_file"
        echo "After: $after_file"
        echo ""

        # Compare basic state
        echo "Basic State Changes:"
        echo "-------------------"

        local before_status=$(jq -r '.basic_state.status' "$before_file" 2>/dev/null || echo "unknown")
        local after_status=$(jq -r '.basic_state.status' "$after_file" 2>/dev/null || echo "unknown")

        if [ "$before_status" != "$after_status" ]; then
            echo "✓ Status changed: $before_status → $after_status"
        else
            echo "• Status unchanged: $before_status"
        fi

        local before_version=$(jq -r '.basic_state.file_version' "$before_file" 2>/dev/null || echo "unknown")
        local after_version=$(jq -r '.basic_state.file_version' "$after_file" 2>/dev/null || echo "unknown")

        if [ "$before_version" != "$after_version" ]; then
            echo "✓ File version changed: $before_version → $after_version"
        else
            echo "• File version unchanged: $before_version"
        fi

        local before_wp_version=$(jq -r '.basic_state.wp_version' "$before_file" 2>/dev/null || echo "unknown")
        local after_wp_version=$(jq -r '.basic_state.wp_version' "$after_file" 2>/dev/null || echo "unknown")

        if [ "$before_wp_version" != "$after_wp_version" ]; then
            echo "✓ WordPress version changed: $before_wp_version → $after_wp_version"
        else
            echo "• WordPress version unchanged: $before_wp_version"
        fi

        # Compare file structure
        echo ""
        echo "File Structure Changes:"
        echo "----------------------"

        local before_hash=$(jq -r '.file_structure.structure_hash' "$before_file" 2>/dev/null || echo "unknown")
        local after_hash=$(jq -r '.file_structure.structure_hash' "$after_file" 2>/dev/null || echo "unknown")

        if [ "$before_hash" != "$after_hash" ]; then
            echo "✓ File structure changed"

            local before_files=$(jq -r '.file_structure.total_files' "$before_file" 2>/dev/null || echo "0")
            local after_files=$(jq -r '.file_structure.total_files' "$after_file" 2>/dev/null || echo "0")
            echo "  Files count: $before_files → $after_files"

            local before_php=$(jq -r '.file_structure.php_files' "$before_file" 2>/dev/null || echo "0")
            local after_php=$(jq -r '.file_structure.php_files' "$after_file" 2>/dev/null || echo "0")
            echo "  PHP files: $before_php → $after_php"
        else
            echo "• File structure unchanged"
        fi

        # Compare detailed state
        echo ""
        echo "Detailed State Changes:"
        echo "----------------------"

        local before_updater=$(jq -r '.detailed_state.github_updater_available' "$before_file" 2>/dev/null || echo "false")
        local after_updater=$(jq -r '.detailed_state.github_updater_available' "$after_file" 2>/dev/null || echo "false")

        if [ "$before_updater" != "$after_updater" ]; then
            echo "✓ GitHub updater availability changed: $before_updater → $after_updater"
        else
            echo "• GitHub updater availability unchanged: $before_updater"
        fi

        local before_remote=$(jq -r '.detailed_state.remote_version' "$before_file" 2>/dev/null || echo "unknown")
        local after_remote=$(jq -r '.detailed_state.remote_version' "$after_file" 2>/dev/null || echo "unknown")

        if [ "$before_remote" != "$after_remote" ]; then
            echo "✓ Remote version changed: $before_remote → $after_remote"
        else
            echo "• Remote version unchanged: $before_remote"
        fi

        echo ""
        echo "Summary:"
        echo "--------"

        # Count changes
        local changes=0
        if [ "$before_status" != "$after_status" ]; then changes=$((changes + 1)); fi
        if [ "$before_version" != "$after_version" ]; then changes=$((changes + 1)); fi
        if [ "$before_wp_version" != "$after_wp_version" ]; then changes=$((changes + 1)); fi
        if [ "$before_hash" != "$after_hash" ]; then changes=$((changes + 1)); fi
        if [ "$before_updater" != "$after_updater" ]; then changes=$((changes + 1)); fi
        if [ "$before_remote" != "$after_remote" ]; then changes=$((changes + 1)); fi

        echo "Total changes detected: $changes"

        if [ $changes -gt 0 ]; then
            echo "State change detected - possible update occurred"
        else
            echo "No changes detected - state is stable"
        fi

    } | tee "$comparison_file"

    print_status "SUCCESS" "Comparison completed: $comparison_file"
}

# Monitoring functions
start_monitoring() {
    local duration=${1:-300}  # Default 5 minutes
    local interval=${2:-10}   # Default 10 seconds

    print_header "Starting Continuous Plugin Monitoring"
    print_status "INFO" "Duration: ${duration}s, Interval: ${interval}s"

    local start_time=$(date +%s)
    local end_time=$((start_time + duration))
    local count=0

    # Take initial snapshot
    local initial_state=$(capture_complete_state "monitor_initial")
    print_status "SUCCESS" "Initial state captured"

    while [ $(date +%s) -lt $end_time ]; do
        count=$((count + 1))
        sleep $interval

        local current_state=$(capture_complete_state "monitor_${count}")

        # Compare with previous state
        if [ $count -eq 1 ]; then
            compare_states "$initial_state" "$current_state"
        else
            local previous_state="$STATE_DIR/monitor_$((count - 1))_*.json"
            if ls $previous_state 1> /dev/null 2>&1; then
                local prev_file=$(ls $previous_state | head -1)
                compare_states "$prev_file" "$current_state"
            fi
        fi

        local remaining=$((end_time - $(date +%s)))
        print_status "MONITOR" "Monitoring... ${remaining}s remaining"
    done

    print_status "SUCCESS" "Monitoring completed after ${duration}s"
}

# Report generation
generate_state_report() {
    local report_file="$LOGS_DIR/plugin_state_report_$(date +%Y%m%d_%H%M%S).html"

    print_header "Generating Plugin State Report"

    # Count available state files
    local state_count=$(ls "$STATE_DIR"/*.json 2>/dev/null | wc -l)

    if [ $state_count -eq 0 ]; then
        print_status "WARNING" "No state files found for report generation"
        return 1
    fi

    print_status "INFO" "Found $state_count state snapshots"
    print_status "INFO" "Generating HTML report: $report_file"

    # Generate HTML report
    cat > "$report_file" << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>CUFT Plugin State Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f0f0f0; padding: 20px; border-radius: 5px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .code { background: #f5f5f5; padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Choice Universal Form Tracker - Plugin State Report</h1>
        <p>Generated: $(date)</p>
        <p>State snapshots analyzed: $state_count</p>
    </div>
EOF

    # Add state files table
    echo '<div class="section">' >> "$report_file"
    echo '<h2>Available State Snapshots</h2>' >> "$report_file"
    echo '<table>' >> "$report_file"
    echo '<tr><th>Filename</th><th>Timestamp</th><th>Size</th></tr>' >> "$report_file"

    for state_file in "$STATE_DIR"/*.json; do
        if [ -f "$state_file" ]; then
            local filename=$(basename "$state_file")
            local timestamp=$(jq -r '.timestamp' "$state_file" 2>/dev/null || echo "Unknown")
            local size=$(stat -c%s "$state_file")
            echo "<tr><td>$filename</td><td>$timestamp</td><td>${size} bytes</td></tr>" >> "$report_file"
        fi
    done

    echo '</table>' >> "$report_file"
    echo '</div>' >> "$report_file"

    # Add latest state summary
    local latest_state=$(ls -t "$STATE_DIR"/*.json 2>/dev/null | head -1)
    if [ -f "$latest_state" ]; then
        echo '<div class="section">' >> "$report_file"
        echo '<h2>Latest Plugin State</h2>' >> "$report_file"

        local status=$(jq -r '.basic_state.status' "$latest_state" 2>/dev/null || echo "unknown")
        local version=$(jq -r '.basic_state.file_version' "$latest_state" 2>/dev/null || echo "unknown")
        local updater=$(jq -r '.detailed_state.github_updater_available' "$latest_state" 2>/dev/null || echo "false")

        echo "<p><strong>Status:</strong> $status</p>" >> "$report_file"
        echo "<p><strong>Version:</strong> $version</p>" >> "$report_file"
        echo "<p><strong>GitHub Updater:</strong> $updater</p>" >> "$report_file"

        echo '</div>' >> "$report_file"
    fi

    # Close HTML
    echo '</body></html>' >> "$report_file"

    print_status "SUCCESS" "Report generated: $report_file"

    if command -v xdg-open &> /dev/null; then
        print_status "INFO" "Opening report in browser..."
        xdg-open "$report_file" &
    fi
}

# Usage function
show_usage() {
    echo "Plugin State Monitor for Choice Universal Form Tracker"
    echo ""
    echo "Usage:"
    echo "  $0 --snapshot [name]                 Take a single state snapshot"
    echo "  $0 --compare FILE1 FILE2             Compare two state snapshots"
    echo "  $0 --watch [duration] [interval]     Continuous monitoring"
    echo "  $0 --report                          Generate HTML state report"
    echo "  $0 --list                            List available snapshots"
    echo "  $0 --cleanup                         Clean up old state files"
    echo "  $0 --help                            Show this help"
    echo ""
    echo "Examples:"
    echo "  $0 --snapshot before_update"
    echo "  $0 --snapshot after_update"
    echo "  $0 --compare before_update.json after_update.json"
    echo "  $0 --watch 300 10     # Monitor for 5 minutes, check every 10 seconds"
    echo ""
    echo "Output directories:"
    echo "  State files: $STATE_DIR"
    echo "  Log files: $LOGS_DIR"
}

# Cleanup function
cleanup_old_states() {
    print_header "Cleaning Up Old State Files"

    local count=0

    # Remove state files older than 7 days
    if [ -d "$STATE_DIR" ]; then
        count=$(find "$STATE_DIR" -name "*.json" -mtime +7 | wc -l)
        find "$STATE_DIR" -name "*.json" -mtime +7 -delete
    fi

    # Remove log files older than 7 days
    if [ -d "$LOGS_DIR" ]; then
        find "$LOGS_DIR" -name "*state*" -mtime +7 -delete
    fi

    print_status "SUCCESS" "Cleaned up $count old state files"
}

# List function
list_snapshots() {
    print_header "Available State Snapshots"

    if [ ! -d "$STATE_DIR" ] || [ -z "$(ls -A "$STATE_DIR" 2>/dev/null)" ]; then
        print_status "INFO" "No state snapshots found"
        return
    fi

    for state_file in "$STATE_DIR"/*.json; do
        if [ -f "$state_file" ]; then
            local filename=$(basename "$state_file")
            local timestamp=$(jq -r '.timestamp' "$state_file" 2>/dev/null || echo "Unknown")
            local status=$(jq -r '.basic_state.status' "$state_file" 2>/dev/null || echo "unknown")
            local version=$(jq -r '.basic_state.file_version' "$state_file" 2>/dev/null || echo "unknown")

            print_status "INFO" "$filename"
            print_status "INFO" "  Timestamp: $timestamp"
            print_status "INFO" "  Status: $status"
            print_status "INFO" "  Version: $version"
            echo
        fi
    done
}

# Main execution
main() {
    if [ $# -eq 0 ]; then
        show_usage
        exit 1
    fi

    case $1 in
        --snapshot)
            local snapshot_name=${2:-"manual"}
            capture_complete_state "$snapshot_name"
            ;;
        --compare)
            if [ -z "$2" ] || [ -z "$3" ]; then
                print_status "ERROR" "Two state files required for comparison"
                show_usage
                exit 1
            fi
            compare_states "$2" "$3"
            ;;
        --watch)
            local duration=${2:-300}
            local interval=${3:-10}
            start_monitoring "$duration" "$interval"
            ;;
        --report)
            generate_state_report
            ;;
        --list)
            list_snapshots
            ;;
        --cleanup)
            cleanup_old_states
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

# Check dependencies
if ! command -v jq &> /dev/null; then
    print_status "ERROR" "jq command not found. Please install jq for JSON processing."
    exit 1
fi

# Run main function with all arguments
main "$@"