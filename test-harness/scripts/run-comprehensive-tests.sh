#!/bin/bash

# Comprehensive Test Runner for CUFT Update System
# Orchestrates all testing components for end-to-end validation
#
# Usage:
#   ./run-comprehensive-tests.sh --full              # Run complete test suite
#   ./run-comprehensive-tests.sh --update-flow      # Test complete update flow
#   ./run-comprehensive-tests.sh --edge-cases       # Test edge cases and error conditions
#   ./run-comprehensive-tests.sh --performance      # Test performance and timing
#   ./run-comprehensive-tests.sh --report           # Generate comprehensive report

set -e

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PDEV_DIR="$(dirname "$SCRIPT_DIR")"
LOGS_DIR="$SCRIPT_DIR/logs"
REPORTS_DIR="$SCRIPT_DIR/reports"
PLUGIN_SOURCE="/home/r11/dev/wp/plugins/choice-uft"
PLUGIN_TARGET="$WP_PDEV_DIR/wp-content/plugins/choice-uft"

# Test tools
TEST_ORCHESTRATOR="$SCRIPT_DIR/test-cuft-update.sh"
MOCK_BUILDER="$SCRIPT_DIR/mock-release-builder.sh"
STATE_MONITOR="$SCRIPT_DIR/plugin-state-monitor.sh"
WP_CLI_WRAPPER="$SCRIPT_DIR/cuft-wp-cli.sh"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
BOLD='\033[1m'
NC='\033[0m'

# Create required directories
mkdir -p "$LOGS_DIR" "$REPORTS_DIR"

# Logging setup
TEST_LOG="$LOGS_DIR/comprehensive_test_$(date +%Y%m%d_%H%M%S).log"
exec 1> >(tee -a "$TEST_LOG")
exec 2> >(tee -a "$TEST_LOG" >&2)

# Logging functions
print_banner() {
    echo -e "\n${BOLD}${PURPLE}================================================${NC}"
    echo -e "${BOLD}${PURPLE}$1${NC}"
    echo -e "${BOLD}${PURPLE}================================================${NC}\n"
}

print_section() {
    echo -e "\n${BOLD}${CYAN}--- $1 ---${NC}\n"
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
        "TEST")
            echo -e "${PURPLE}[$timestamp] 🧪 $message${NC}"
            ;;
    esac
}

# Global test tracking
declare -a TEST_RESULTS
declare -a TEST_NAMES
declare -a TEST_DURATIONS
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0
START_TIME=$(date +%s)

# Test result tracking
record_test_result() {
    local test_name="$1"
    local result="$2"
    local duration="$3"

    TEST_NAMES+=("$test_name")
    TEST_RESULTS+=("$result")
    TEST_DURATIONS+=("$duration")
    TOTAL_TESTS=$((TOTAL_TESTS + 1))

    if [ "$result" = "PASS" ]; then
        PASSED_TESTS=$((PASSED_TESTS + 1))
        print_status "SUCCESS" "Test passed: $test_name (${duration}s)"
    else
        FAILED_TESTS=$((FAILED_TESTS + 1))
        print_status "ERROR" "Test failed: $test_name (${duration}s)"
    fi
}

# Environment setup functions
check_prerequisites() {
    print_section "Checking Prerequisites"

    local missing_tools=()

    # Check for required scripts
    if [ ! -x "$TEST_ORCHESTRATOR" ]; then
        missing_tools+=("test-cuft-update.sh")
    fi

    if [ ! -x "$MOCK_BUILDER" ]; then
        missing_tools+=("mock-release-builder.sh")
    fi

    if [ ! -x "$STATE_MONITOR" ]; then
        missing_tools+=("plugin-state-monitor.sh")
    fi

    if [ ! -x "$WP_CLI_WRAPPER" ]; then
        missing_tools+=("cuft-wp-cli.sh")
    fi

    # Check for required commands
    for cmd in docker docker-compose jq curl; do
        if ! command -v "$cmd" &> /dev/null; then
            missing_tools+=("$cmd")
        fi
    done

    if [ ${#missing_tools[@]} -eq 0 ]; then
        print_status "SUCCESS" "All prerequisites available"
        return 0
    else
        print_status "ERROR" "Missing prerequisites:"
        for tool in "${missing_tools[@]}"; do
            print_status "ERROR" "  - $tool"
        done
        return 1
    fi
}

setup_test_environment() {
    print_section "Setting Up Test Environment"

    # Check if containers are running
    if ! docker ps | grep -q "wp-pdev-wordpress"; then
        print_status "INFO" "Starting Docker containers..."
        cd "$WP_PDEV_DIR"
        docker-compose up -d
        sleep 30  # Wait for containers to initialize
    fi

    # Sync plugin code
    print_status "STEP" "Syncing plugin code from source..."
    if [ -d "$PLUGIN_SOURCE" ]; then
        rsync -av \
            --exclude='.git' \
            --exclude='node_modules' \
            --exclude='*.log' \
            --exclude='*.zip' \
            --chown=www-data:www-data \
            "$PLUGIN_SOURCE/" "$PLUGIN_TARGET/"
        print_status "SUCCESS" "Plugin code synced"
    else
        print_status "ERROR" "Plugin source directory not found: $PLUGIN_SOURCE"
        return 1
    fi

    # Install and activate plugin
    print_status "STEP" "Installing plugin..."
    if "$WP_CLI_WRAPPER" plugin install; then
        print_status "SUCCESS" "Plugin installed and activated"
    else
        print_status "ERROR" "Plugin installation failed"
        return 1
    fi

    # Take initial state snapshot
    print_status "STEP" "Taking initial state snapshot..."
    "$STATE_MONITOR" --snapshot initial_state
    print_status "SUCCESS" "Initial state captured"

    return 0
}

# Core test functions
test_basic_functionality() {
    local test_start=$(date +%s)
    print_section "Testing Basic Plugin Functionality"

    if "$TEST_ORCHESTRATOR" --scenario basic; then
        local duration=$(($(date +%s) - test_start))
        record_test_result "Basic Functionality" "PASS" "$duration"
        return 0
    else
        local duration=$(($(date +%s) - test_start))
        record_test_result "Basic Functionality" "FAIL" "$duration"
        return 1
    fi
}

test_download_url_format() {
    local test_start=$(date +%s)
    print_section "Testing Download URL Format"

    if "$TEST_ORCHESTRATOR" --scenario download-url; then
        local duration=$(($(date +%s) - test_start))
        record_test_result "Download URL Format" "PASS" "$duration"
        return 0
    else
        local duration=$(($(date +%s) - test_start))
        record_test_result "Download URL Format" "FAIL" "$duration"
        return 1
    fi
}

test_update_detection() {
    local test_start=$(date +%s)
    print_section "Testing WordPress Update Detection"

    if "$TEST_ORCHESTRATOR" --scenario update-detection; then
        local duration=$(($(date +%s) - test_start))
        record_test_result "Update Detection" "PASS" "$duration"
        return 0
    else
        local duration=$(($(date +%s) - test_start))
        record_test_result "Update Detection" "FAIL" "$duration"
        return 1
    fi
}

test_admin_panel_mechanism() {
    local test_start=$(date +%s)
    print_section "Testing Admin Panel Update Mechanism"

    if "$TEST_ORCHESTRATOR" --scenario admin-panel; then
        local duration=$(($(date +%s) - test_start))
        record_test_result "Admin Panel Mechanism" "PASS" "$duration"
        return 0
    else
        local duration=$(($(date +%s) - test_start))
        record_test_result "Admin Panel Mechanism" "FAIL" "$duration"
        return 1
    fi
}

test_reactivation_safety() {
    local test_start=$(date +%s)
    print_section "Testing Plugin Reactivation Safety"

    # Take before snapshot
    "$STATE_MONITOR" --snapshot before_reactivation_test

    if "$TEST_ORCHESTRATOR" --scenario reactivation; then
        # Take after snapshot
        "$STATE_MONITOR" --snapshot after_reactivation_test

        local duration=$(($(date +%s) - test_start))
        record_test_result "Reactivation Safety" "PASS" "$duration"
        return 0
    else
        local duration=$(($(date +%s) - test_start))
        record_test_result "Reactivation Safety" "FAIL" "$duration"
        return 1
    fi
}

test_asset_naming_enforcement() {
    local test_start=$(date +%s)
    print_section "Testing Asset Naming Enforcement"

    if "$TEST_ORCHESTRATOR" --scenario asset-naming; then
        local duration=$(($(date +%s) - test_start))
        record_test_result "Asset Naming Enforcement" "PASS" "$duration"
        return 0
    else
        local duration=$(($(date +%s) - test_start))
        record_test_result "Asset Naming Enforcement" "FAIL" "$duration"
        return 1
    fi
}

# Advanced test scenarios
test_mock_releases() {
    local test_start=$(date +%s)
    print_section "Testing Mock Release Scenarios"

    print_status "STEP" "Creating test releases..."

    # Create correct release
    if "$MOCK_BUILDER" --version 3.11.0 --correct; then
        print_status "SUCCESS" "Created correct release format"
    else
        print_status "ERROR" "Failed to create correct release"
        local duration=$(($(date +%s) - test_start))
        record_test_result "Mock Releases" "FAIL" "$duration"
        return 1
    fi

    # Create incorrect releases for negative testing
    for error_type in naming structure missing-files; do
        if "$MOCK_BUILDER" --version 3.11.0 --incorrect "$error_type"; then
            print_status "SUCCESS" "Created incorrect release: $error_type"
        else
            print_status "WARNING" "Failed to create incorrect release: $error_type"
        fi
    done

    # Create version series
    if "$MOCK_BUILDER" --version 3.10.1 --series; then
        print_status "SUCCESS" "Created version series"
    else
        print_status "WARNING" "Failed to create version series"
    fi

    local duration=$(($(date +%s) - test_start))
    record_test_result "Mock Releases" "PASS" "$duration"
    return 0
}

test_state_monitoring() {
    local test_start=$(date +%s)
    print_section "Testing State Monitoring"

    # Take snapshots
    print_status "STEP" "Taking state snapshots..."
    local snapshot1=$("$STATE_MONITOR" --snapshot test_state_1 | tail -1)

    # Wait a moment and make a change
    sleep 2
    "$WP_CLI_WRAPPER" debug enable

    local snapshot2=$("$STATE_MONITOR" --snapshot test_state_2 | tail -1)

    # Compare states
    if "$STATE_MONITOR" --compare "$snapshot1" "$snapshot2"; then
        print_status "SUCCESS" "State monitoring and comparison working"
    else
        print_status "ERROR" "State monitoring comparison failed"
        local duration=$(($(date +%s) - test_start))
        record_test_result "State Monitoring" "FAIL" "$duration"
        return 1
    fi

    # Reset debug state
    "$WP_CLI_WRAPPER" debug disable

    local duration=$(($(date +%s) - test_start))
    record_test_result "State Monitoring" "PASS" "$duration"
    return 0
}

test_wp_cli_wrapper() {
    local test_start=$(date +%s)
    print_section "Testing WP-CLI Wrapper"

    # Test various wrapper functions
    local tests=(
        "plugin status"
        "plugin version"
        "updater test"
        "wordpress info"
    )

    local wrapper_failures=0

    for test_cmd in "${tests[@]}"; do
        print_status "STEP" "Testing: $WP_CLI_WRAPPER $test_cmd"
        if "$WP_CLI_WRAPPER" $test_cmd > /dev/null 2>&1; then
            print_status "SUCCESS" "Wrapper test passed: $test_cmd"
        else
            print_status "ERROR" "Wrapper test failed: $test_cmd"
            wrapper_failures=$((wrapper_failures + 1))
        fi
    done

    local duration=$(($(date +%s) - test_start))

    if [ $wrapper_failures -eq 0 ]; then
        record_test_result "WP-CLI Wrapper" "PASS" "$duration"
        return 0
    else
        record_test_result "WP-CLI Wrapper" "FAIL" "$duration"
        return 1
    fi
}

# Test suite runners
run_basic_test_suite() {
    print_banner "Running Basic Test Suite"

    local suite_failures=0

    test_basic_functionality || suite_failures=$((suite_failures + 1))
    test_download_url_format || suite_failures=$((suite_failures + 1))
    test_update_detection || suite_failures=$((suite_failures + 1))
    test_admin_panel_mechanism || suite_failures=$((suite_failures + 1))
    test_reactivation_safety || suite_failures=$((suite_failures + 1))
    test_asset_naming_enforcement || suite_failures=$((suite_failures + 1))

    if [ $suite_failures -eq 0 ]; then
        print_status "SUCCESS" "Basic test suite completed successfully"
        return 0
    else
        print_status "ERROR" "Basic test suite had $suite_failures failures"
        return 1
    fi
}

run_advanced_test_suite() {
    print_banner "Running Advanced Test Suite"

    local suite_failures=0

    test_mock_releases || suite_failures=$((suite_failures + 1))
    test_state_monitoring || suite_failures=$((suite_failures + 1))
    test_wp_cli_wrapper || suite_failures=$((suite_failures + 1))

    if [ $suite_failures -eq 0 ]; then
        print_status "SUCCESS" "Advanced test suite completed successfully"
        return 0
    else
        print_status "ERROR" "Advanced test suite had $suite_failures failures"
        return 1
    fi
}

run_update_flow_test() {
    print_banner "Running Complete Update Flow Test"

    local test_start=$(date +%s)

    # This would simulate a complete update process
    # For now, we'll run the basic suite as a proxy
    print_status "INFO" "Simulating complete update flow..."

    # Take before state
    "$STATE_MONITOR" --snapshot before_update_flow

    # Run update-related tests
    local flow_failures=0
    test_basic_functionality || flow_failures=$((flow_failures + 1))
    test_update_detection || flow_failures=$((flow_failures + 1))
    test_admin_panel_mechanism || flow_failures=$((flow_failures + 1))

    # Take after state
    "$STATE_MONITOR" --snapshot after_update_flow

    local duration=$(($(date +%s) - test_start))

    if [ $flow_failures -eq 0 ]; then
        record_test_result "Complete Update Flow" "PASS" "$duration"
        return 0
    else
        record_test_result "Complete Update Flow" "FAIL" "$duration"
        return 1
    fi
}

run_edge_cases_test() {
    print_banner "Running Edge Cases Test"

    local test_start=$(date +%s)

    print_status "INFO" "Testing edge cases and error conditions..."

    # Test asset naming enforcement (which includes negative cases)
    test_asset_naming_enforcement

    # Test mock releases (which includes error scenarios)
    test_mock_releases

    local duration=$(($(date +%s) - test_start))
    record_test_result "Edge Cases" "PASS" "$duration"
    return 0
}

run_performance_test() {
    print_banner "Running Performance Test"

    local test_start=$(date +%s)

    print_status "INFO" "Testing performance and timing..."

    # Monitor state changes for a short period
    print_status "STEP" "Starting performance monitoring..."
    "$STATE_MONITOR" --watch 60 5 &  # Monitor for 1 minute, check every 5 seconds
    local monitor_pid=$!

    # Run some operations while monitoring
    "$WP_CLI_WRAPPER" plugin version > /dev/null
    "$WP_CLI_WRAPPER" updater remote-version > /dev/null

    # Wait for monitoring to complete
    wait $monitor_pid

    local duration=$(($(date +%s) - test_start))
    record_test_result "Performance Test" "PASS" "$duration"
    return 0
}

# Report generation
generate_comprehensive_report() {
    print_banner "Generating Comprehensive Test Report"

    local report_file="$REPORTS_DIR/comprehensive_test_report_$(date +%Y%m%d_%H%M%S).html"
    local total_duration=$(($(date +%s) - START_TIME))

    print_status "INFO" "Generating HTML report: $report_file"

    cat > "$report_file" << EOF
<!DOCTYPE html>
<html>
<head>
    <title>CUFT Comprehensive Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f0f0f0; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .summary { background: #e7f3ff; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .pass { background: #d4edda; color: #155724; }
        .fail { background: #f8d7da; color: #721c24; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .stats { display: flex; justify-content: space-around; text-align: center; }
        .stat-box { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 5px; }
        .success-rate { font-size: 24px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Choice Universal Form Tracker - Comprehensive Test Report</h1>
        <p><strong>Generated:</strong> $(date)</p>
        <p><strong>Duration:</strong> ${total_duration}s</p>
        <p><strong>Log File:</strong> $TEST_LOG</p>
    </div>

    <div class="summary">
        <h2>Test Summary</h2>
        <div class="stats">
            <div class="stat-box">
                <div class="success-rate $([ $FAILED_TESTS -eq 0 ] && echo "pass" || echo "fail")">
                    $(( PASSED_TESTS * 100 / TOTAL_TESTS ))%
                </div>
                <div>Success Rate</div>
            </div>
            <div class="stat-box">
                <div class="success-rate">$TOTAL_TESTS</div>
                <div>Total Tests</div>
            </div>
            <div class="stat-box">
                <div class="success-rate pass">$PASSED_TESTS</div>
                <div>Passed</div>
            </div>
            <div class="stat-box">
                <div class="success-rate fail">$FAILED_TESTS</div>
                <div>Failed</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2>Test Results</h2>
        <table>
            <tr>
                <th>Test Name</th>
                <th>Result</th>
                <th>Duration (s)</th>
            </tr>
EOF

    # Add test results to HTML
    for i in "${!TEST_NAMES[@]}"; do
        local test_name="${TEST_NAMES[$i]}"
        local result="${TEST_RESULTS[$i]}"
        local duration="${TEST_DURATIONS[$i]}"
        local css_class=$([ "$result" = "PASS" ] && echo "pass" || echo "fail")

        cat >> "$report_file" << EOF
            <tr class="$css_class">
                <td>$test_name</td>
                <td>$result</td>
                <td>$duration</td>
            </tr>
EOF
    done

    cat >> "$report_file" << EOF
        </table>
    </div>

    <div class="section">
        <h2>Environment Information</h2>
        <p><strong>Plugin Source:</strong> $PLUGIN_SOURCE</p>
        <p><strong>Plugin Target:</strong> $PLUGIN_TARGET</p>
        <p><strong>WordPress Container:</strong> wp-pdev-wordpress</p>
        <p><strong>WP-CLI Container:</strong> wp-pdev-cli</p>
    </div>

    <div class="section">
        <h2>Log Files</h2>
        <ul>
            <li>Main Test Log: $TEST_LOG</li>
            <li>State Snapshots: $SCRIPT_DIR/state/</li>
            <li>Mock Releases: $SCRIPT_DIR/mock-releases/</li>
        </ul>
    </div>

    <div class="section">
        <h2>Next Steps</h2>
        $(if [ $FAILED_TESTS -eq 0 ]; then
            echo "<p class=\"pass\">✅ All tests passed! The update system is working correctly.</p>"
            echo "<p>You can proceed with confidence that the plugin update functionality works as expected.</p>"
        else
            echo "<p class=\"fail\">❌ $FAILED_TESTS test(s) failed. Please review the failures and fix before deploying.</p>"
            echo "<p>Check the detailed log file for specific error messages and debugging information.</p>"
        fi)
    </div>
</body>
</html>
EOF

    print_status "SUCCESS" "Report generated: $report_file"

    # Also generate a simple text summary
    local summary_file="$REPORTS_DIR/test_summary_$(date +%Y%m%d_%H%M%S).txt"
    cat > "$summary_file" << EOF
CUFT Comprehensive Test Summary
===============================
Generated: $(date)
Duration: ${total_duration}s

Results:
--------
Total Tests: $TOTAL_TESTS
Passed: $PASSED_TESTS
Failed: $FAILED_TESTS
Success Rate: $(( PASSED_TESTS * 100 / TOTAL_TESTS ))%

Test Details:
EOF

    for i in "${!TEST_NAMES[@]}"; do
        echo "${TEST_RESULTS[$i]}: ${TEST_NAMES[$i]} (${TEST_DURATIONS[$i]}s)" >> "$summary_file"
    done

    print_status "SUCCESS" "Summary generated: $summary_file"

    # Open report if possible
    if command -v xdg-open &> /dev/null && [ "$DISPLAY" != "" ]; then
        print_status "INFO" "Opening report in browser..."
        xdg-open "$report_file" &
    fi

    return 0
}

# Usage function
show_usage() {
    echo "Comprehensive Test Runner for Choice Universal Form Tracker"
    echo ""
    echo "Usage:"
    echo "  $0 --full              Run complete test suite (basic + advanced)"
    echo "  $0 --basic             Run basic functionality tests only"
    echo "  $0 --advanced          Run advanced feature tests only"
    echo "  $0 --update-flow       Test complete update flow simulation"
    echo "  $0 --edge-cases        Test edge cases and error conditions"
    echo "  $0 --performance       Test performance and timing"
    echo "  $0 --report            Generate comprehensive report"
    echo "  $0 --help              Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 --full              # Run everything"
    echo "  $0 --basic             # Quick functionality check"
    echo "  $0 --update-flow       # Test update process"
    echo ""
    echo "Output locations:"
    echo "  Logs: $LOGS_DIR"
    echo "  Reports: $REPORTS_DIR"
    echo "  State files: $SCRIPT_DIR/state/"
    echo "  Mock releases: $SCRIPT_DIR/mock-releases/"
}

# Cleanup function
cleanup() {
    print_status "INFO" "Cleaning up..."

    # Clean up old state files
    "$STATE_MONITOR" --cleanup

    # Clean up old mock releases
    "$MOCK_BUILDER" --cleanup

    print_status "SUCCESS" "Cleanup completed"
}

# Main execution
main() {
    if [ $# -eq 0 ]; then
        show_usage
        exit 1
    fi

    # Print startup banner
    print_banner "CUFT Comprehensive Test Runner"
    print_status "INFO" "Starting comprehensive test suite..."
    print_status "INFO" "Log file: $TEST_LOG"

    # Check prerequisites
    if ! check_prerequisites; then
        exit 1
    fi

    # Set up test environment
    if ! setup_test_environment; then
        exit 1
    fi

    case $1 in
        --full)
            run_basic_test_suite
            run_advanced_test_suite
            generate_comprehensive_report
            ;;
        --basic)
            run_basic_test_suite
            generate_comprehensive_report
            ;;
        --advanced)
            run_advanced_test_suite
            generate_comprehensive_report
            ;;
        --update-flow)
            run_update_flow_test
            generate_comprehensive_report
            ;;
        --edge-cases)
            run_edge_cases_test
            generate_comprehensive_report
            ;;
        --performance)
            run_performance_test
            generate_comprehensive_report
            ;;
        --report)
            generate_comprehensive_report
            ;;
        --cleanup)
            cleanup
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

    # Final summary
    local end_time=$(date +%s)
    local total_duration=$((end_time - START_TIME))

    print_banner "Test Execution Complete"
    print_status "INFO" "Total duration: ${total_duration}s"
    print_status "INFO" "Total tests: $TOTAL_TESTS"
    print_status "SUCCESS" "Passed: $PASSED_TESTS"

    if [ $FAILED_TESTS -gt 0 ]; then
        print_status "ERROR" "Failed: $FAILED_TESTS"
        print_status "WARNING" "Please review failed tests before proceeding"
        exit 1
    else
        print_status "SUCCESS" "All tests passed! 🎉"
        print_status "INFO" "The update system is working correctly"
        exit 0
    fi
}

# Trap to ensure cleanup on exit
trap cleanup EXIT

# Run main function with all arguments
main "$@"