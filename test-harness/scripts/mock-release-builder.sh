#!/bin/bash

# Mock Release Builder for CUFT Testing
# Creates test release assets for various testing scenarios
#
# Usage:
#   ./mock-release-builder.sh --version 3.11.0 --correct     # Create correctly named release
#   ./mock-release-builder.sh --version 3.11.0 --incorrect   # Create incorrectly named release
#   ./mock-release-builder.sh --cleanup                      # Clean up all mock releases

set -e

# Script configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_PDEV_DIR="$(dirname "$SCRIPT_DIR")"
MOCK_RELEASES_DIR="$SCRIPT_DIR/mock-releases"
PLUGIN_SOURCE_DIR="/home/r11/dev/wp/plugins/choice-uft"
TEMP_BUILD_DIR="$SCRIPT_DIR/temp/build"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
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
        "STEP")
            echo -e "${PURPLE}[$timestamp] 🔄 $message${NC}"
            ;;
    esac
}

print_header() {
    echo -e "\n${PURPLE}===============================================${NC}"
    echo -e "${PURPLE}$1${NC}"
    echo -e "${PURPLE}===============================================${NC}\n"
}

# Utility functions
create_directories() {
    mkdir -p "$MOCK_RELEASES_DIR"
    mkdir -p "$TEMP_BUILD_DIR"
    print_status "INFO" "Created necessary directories"
}

cleanup_temp() {
    if [ -d "$TEMP_BUILD_DIR" ]; then
        rm -rf "$TEMP_BUILD_DIR"
        print_status "INFO" "Cleaned up temporary build directory"
    fi
}

# Version manipulation functions
update_plugin_version() {
    local target_dir=$1
    local new_version=$2

    print_status "STEP" "Updating plugin version to $new_version"

    # Update main plugin file header
    sed -i "s/Version:[[:space:]]*[0-9]\+\.[0-9]\+\.[0-9]\+/Version:           $new_version/" \
        "$target_dir/choice-universal-form-tracker.php"

    # Update CUFT_VERSION constant
    sed -i "s/define( 'CUFT_VERSION', '[0-9]\+\.[0-9]\+\.[0-9]\+' );/define( 'CUFT_VERSION', '$new_version' );/" \
        "$target_dir/choice-universal-form-tracker.php"

    print_status "SUCCESS" "Updated plugin version to $new_version"
}

verify_plugin_structure() {
    local plugin_dir=$1

    print_status "STEP" "Verifying plugin structure..."

    local required_files=(
        "choice-universal-form-tracker.php"
        "includes/class-cuft-github-updater.php"
        "includes/class-cuft-admin.php"
        "assets/"
    )

    local missing_files=()

    for file in "${required_files[@]}"; do
        if [ ! -e "$plugin_dir/$file" ]; then
            missing_files+=("$file")
        fi
    done

    if [ ${#missing_files[@]} -eq 0 ]; then
        print_status "SUCCESS" "All required files present"
        return 0
    else
        print_status "ERROR" "Missing required files:"
        for file in "${missing_files[@]}"; do
            print_status "ERROR" "  - $file"
        done
        return 1
    fi
}

# Release building functions
build_correct_release() {
    local version=$1
    local output_dir=$2

    print_header "Building Correct Release: choice-uft-v$version.zip"

    # Create temporary plugin directory
    local temp_plugin_dir="$TEMP_BUILD_DIR/choice-uft"
    mkdir -p "$temp_plugin_dir"

    # Copy plugin files
    print_status "STEP" "Copying plugin files..."
    rsync -av \
        --exclude='.git' \
        --exclude='.github' \
        --exclude='node_modules' \
        --exclude='*.zip' \
        --exclude='*.log' \
        --exclude='tests' \
        --exclude='.env' \
        --chown=www-data:www-data \
        "$PLUGIN_SOURCE_DIR/" "$temp_plugin_dir/"

    # Update version in plugin files
    update_plugin_version "$temp_plugin_dir" "$version"

    # Verify structure
    if ! verify_plugin_structure "$temp_plugin_dir"; then
        print_status "ERROR" "Plugin structure verification failed"
        return 1
    fi

    # Create ZIP file with correct naming
    local zip_filename="choice-uft-v$version.zip"
    local zip_path="$output_dir/$zip_filename"

    print_status "STEP" "Creating ZIP file: $zip_filename"

    cd "$TEMP_BUILD_DIR"
    zip -r "$zip_path" choice-uft/ > /dev/null

    if [ -f "$zip_path" ]; then
        local file_size=$(stat -c%s "$zip_path")
        local file_size_mb=$(echo "scale=2; $file_size / 1024 / 1024" | bc)
        print_status "SUCCESS" "Created $zip_filename (${file_size_mb}MB)"

        # Verify ZIP contents
        print_status "STEP" "Verifying ZIP contents..."
        local zip_contents=$(unzip -l "$zip_path" | head -20)

        if echo "$zip_contents" | grep -q "choice-uft/choice-universal-form-tracker.php"; then
            print_status "SUCCESS" "ZIP structure is correct (extracts to choice-uft/)"
        else
            print_status "ERROR" "ZIP structure is incorrect"
            return 1
        fi

        return 0
    else
        print_status "ERROR" "Failed to create ZIP file"
        return 1
    fi
}

build_incorrect_release() {
    local version=$1
    local output_dir=$2
    local error_type=${3:-"naming"}

    print_header "Building Incorrect Release: $error_type error"

    case $error_type in
        "naming")
            # Create release with wrong naming (choice-uft.zip instead of choice-uft-v{version}.zip)
            local temp_plugin_dir="$TEMP_BUILD_DIR/choice-uft"
            mkdir -p "$temp_plugin_dir"

            rsync -av \
                --exclude='.git' \
                --exclude='.github' \
                --exclude='node_modules' \
                --exclude='*.zip' \
                --exclude='*.log' \
                --exclude='tests' \
                --exclude='.env' \
                --chown=www-data:www-data \
                "$PLUGIN_SOURCE_DIR/" "$temp_plugin_dir/"

            update_plugin_version "$temp_plugin_dir" "$version"

            local zip_filename="choice-uft.zip"  # Wrong naming - no version
            local zip_path="$output_dir/$zip_filename"

            cd "$TEMP_BUILD_DIR"
            zip -r "$zip_path" choice-uft/ > /dev/null

            print_status "WARNING" "Created incorrectly named file: $zip_filename"
            ;;

        "structure")
            # Create release with wrong directory structure (choice-uft-v{version}/ instead of choice-uft/)
            local temp_plugin_dir="$TEMP_BUILD_DIR/choice-uft-v$version"  # Wrong structure
            mkdir -p "$temp_plugin_dir"

            rsync -av \
                --exclude='.git' \
                --exclude='.github' \
                --exclude='node_modules' \
                --exclude='*.zip' \
                --exclude='*.log' \
                --exclude='tests' \
                --exclude='.env' \
                --chown=www-data:www-data \
                "$PLUGIN_SOURCE_DIR/" "$temp_plugin_dir/"

            update_plugin_version "$temp_plugin_dir" "$version"

            local zip_filename="choice-uft-v$version-bad-structure.zip"
            local zip_path="$output_dir/$zip_filename"

            cd "$TEMP_BUILD_DIR"
            zip -r "$zip_path" "choice-uft-v$version/" > /dev/null

            print_status "WARNING" "Created release with wrong structure: $zip_filename"
            ;;

        "missing-files")
            # Create release with missing critical files
            local temp_plugin_dir="$TEMP_BUILD_DIR/choice-uft"
            mkdir -p "$temp_plugin_dir"

            rsync -av \
                --exclude='.git' \
                --exclude='.github' \
                --exclude='node_modules' \
                --exclude='*.zip' \
                --exclude='*.log' \
                --exclude='tests' \
                --exclude='.env' \
                --chown=www-data:www-data \
                "$PLUGIN_SOURCE_DIR/" "$temp_plugin_dir/"

            # Remove critical file
            rm -f "$temp_plugin_dir/includes/class-cuft-github-updater.php"

            update_plugin_version "$temp_plugin_dir" "$version"

            local zip_filename="choice-uft-v$version-missing-files.zip"
            local zip_path="$output_dir/$zip_filename"

            cd "$TEMP_BUILD_DIR"
            zip -r "$zip_path" choice-uft/ > /dev/null

            print_status "WARNING" "Created release with missing files: $zip_filename"
            ;;

        *)
            print_status "ERROR" "Unknown error type: $error_type"
            return 1
            ;;
    esac

    return 0
}

build_version_series() {
    local base_version=$1
    local output_dir=$2

    print_header "Building Version Series for Testing"

    # Parse version components
    IFS='.' read -r major minor patch <<< "$base_version"

    # Build older version
    local old_version="$major.$minor.$((patch - 1))"
    print_status "STEP" "Building older version: $old_version"
    build_correct_release "$old_version" "$output_dir"

    # Build current version
    print_status "STEP" "Building current version: $base_version"
    build_correct_release "$base_version" "$output_dir"

    # Build newer version
    local new_version="$major.$minor.$((patch + 1))"
    print_status "STEP" "Building newer version: $new_version"
    build_correct_release "$new_version" "$output_dir"

    print_status "SUCCESS" "Built version series: $old_version, $base_version, $new_version"
}

create_test_manifest() {
    local output_dir=$1

    print_status "STEP" "Creating test manifest..."

    local manifest_file="$output_dir/test-manifest.json"

    cat > "$manifest_file" << EOF
{
  "created": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")",
  "created_by": "mock-release-builder.sh",
  "releases": [
EOF

    local first=true
    for zip_file in "$output_dir"/*.zip; do
        if [ -f "$zip_file" ]; then
            local filename=$(basename "$zip_file")
            local filesize=$(stat -c%s "$zip_file")
            local sha256=$(sha256sum "$zip_file" | cut -d' ' -f1)

            if [ "$first" = true ]; then
                first=false
            else
                echo "," >> "$manifest_file"
            fi

            cat >> "$manifest_file" << EOF
    {
      "filename": "$filename",
      "size": $filesize,
      "sha256": "$sha256",
      "created": "$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
    }
EOF
        fi
    done

    cat >> "$manifest_file" << EOF

  ]
}
EOF

    print_status "SUCCESS" "Created test manifest: $manifest_file"
}

# Cleanup functions
cleanup_all_releases() {
    print_header "Cleaning Up All Mock Releases"

    if [ -d "$MOCK_RELEASES_DIR" ]; then
        rm -rf "$MOCK_RELEASES_DIR"
        print_status "SUCCESS" "Removed all mock releases"
    else
        print_status "INFO" "No mock releases to clean up"
    fi

    cleanup_temp
}

list_releases() {
    print_header "Available Mock Releases"

    if [ ! -d "$MOCK_RELEASES_DIR" ] || [ -z "$(ls -A "$MOCK_RELEASES_DIR" 2>/dev/null)" ]; then
        print_status "INFO" "No mock releases found"
        return
    fi

    for zip_file in "$MOCK_RELEASES_DIR"/*.zip; do
        if [ -f "$zip_file" ]; then
            local filename=$(basename "$zip_file")
            local filesize=$(stat -c%s "$zip_file")
            local filesize_mb=$(echo "scale=2; $filesize / 1024 / 1024" | bc)

            print_status "INFO" "$filename (${filesize_mb}MB)"
        fi
    done

    if [ -f "$MOCK_RELEASES_DIR/test-manifest.json" ]; then
        print_status "INFO" "Test manifest available"
    fi
}

show_usage() {
    echo "Mock Release Builder for Choice Universal Form Tracker"
    echo ""
    echo "Usage:"
    echo "  $0 --version VERSION --correct              Create correctly formatted release"
    echo "  $0 --version VERSION --incorrect [TYPE]     Create incorrectly formatted release"
    echo "  $0 --version VERSION --series               Create version series (older/current/newer)"
    echo "  $0 --cleanup                                Clean up all mock releases"
    echo "  $0 --list                                   List available releases"
    echo "  $0 --help                                   Show this help"
    echo ""
    echo "Incorrect release types:"
    echo "  naming          - Wrong filename (choice-uft.zip instead of choice-uft-v{version}.zip)"
    echo "  structure       - Wrong directory structure (versioned folder name)"
    echo "  missing-files   - Missing critical plugin files"
    echo ""
    echo "Examples:"
    echo "  $0 --version 3.11.0 --correct"
    echo "  $0 --version 3.11.0 --incorrect naming"
    echo "  $0 --version 3.10.1 --series"
    echo "  $0 --cleanup"
    echo ""
    echo "Output directory: $MOCK_RELEASES_DIR"
}

# Main execution
main() {
    # Trap to ensure cleanup on exit
    trap cleanup_temp EXIT

    if [ $# -eq 0 ]; then
        show_usage
        exit 1
    fi

    # Check dependencies
    if ! command -v zip &> /dev/null; then
        print_status "ERROR" "zip command not found. Please install zip."
        exit 1
    fi

    if ! command -v bc &> /dev/null; then
        print_status "ERROR" "bc command not found. Please install bc."
        exit 1
    fi

    # Parse arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --version)
                VERSION="$2"
                shift 2
                ;;
            --correct)
                ACTION="correct"
                shift
                ;;
            --incorrect)
                ACTION="incorrect"
                ERROR_TYPE="${2:-naming}"
                if [[ "$2" =~ ^(naming|structure|missing-files)$ ]]; then
                    shift 2
                else
                    shift
                fi
                ;;
            --series)
                ACTION="series"
                shift
                ;;
            --cleanup)
                cleanup_all_releases
                exit 0
                ;;
            --list)
                list_releases
                exit 0
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
    done

    # Validate arguments
    if [ -z "$ACTION" ]; then
        print_status "ERROR" "Action required (--correct, --incorrect, or --series)"
        show_usage
        exit 1
    fi

    if [ -z "$VERSION" ] && [ "$ACTION" != "cleanup" ] && [ "$ACTION" != "list" ]; then
        print_status "ERROR" "Version required for release building"
        show_usage
        exit 1
    fi

    if [ ! -d "$PLUGIN_SOURCE_DIR" ]; then
        print_status "ERROR" "Plugin source directory not found: $PLUGIN_SOURCE_DIR"
        exit 1
    fi

    # Create directories
    create_directories

    # Execute action
    case $ACTION in
        correct)
            build_correct_release "$VERSION" "$MOCK_RELEASES_DIR"
            create_test_manifest "$MOCK_RELEASES_DIR"
            ;;
        incorrect)
            build_incorrect_release "$VERSION" "$MOCK_RELEASES_DIR" "$ERROR_TYPE"
            create_test_manifest "$MOCK_RELEASES_DIR"
            ;;
        series)
            build_version_series "$VERSION" "$MOCK_RELEASES_DIR"
            create_test_manifest "$MOCK_RELEASES_DIR"
            ;;
    esac

    print_status "SUCCESS" "Mock release building completed"
    list_releases
}

# Run main function with all arguments
main "$@"