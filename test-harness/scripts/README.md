# CUFT Testing Suite

Comprehensive testing tools for the Choice Universal Form Tracker plugin update functionality.

## Quick Start

1. **Sync plugin code to test environment:**
   ```bash
   rsync -av --exclude='.git' --chown=www-data:www-data /home/r11/dev/wp/plugins/choice-uft/ /home/r11/dev/wp-pdev/wp-content/plugins/choice-uft/
   ```

2. **Run comprehensive tests:**
   ```bash
   cd /home/r11/dev/wp-pdev/scripts
   ./run-comprehensive-tests.sh --full
   ```

3. **View results:**
   - HTML report will open automatically
   - Check `logs/` directory for detailed logs
   - Check `reports/` directory for generated reports

## Test Tools

### 1. Main Test Orchestrator (`test-cuft-update.sh`)
Tests core plugin functionality and update mechanisms.

```bash
# Run all tests
./test-cuft-update.sh --full-suite

# Run specific test
./test-cuft-update.sh --scenario basic
./test-cuft-update.sh --scenario admin-panel
./test-cuft-update.sh --scenario reactivation
```

**Available scenarios:**
- `basic` - Basic plugin functionality
- `download-url` - Download URL format validation
- `update-detection` - WordPress update detection
- `admin-panel` - Admin panel update mechanism
- `reactivation` - Plugin reactivation safety
- `asset-naming` - Asset naming enforcement

### 2. Mock Release Builder (`mock-release-builder.sh`)
Creates test releases for various scenarios.

```bash
# Create correct release
./mock-release-builder.sh --version 3.11.0 --correct

# Create incorrect releases for testing
./mock-release-builder.sh --version 3.11.0 --incorrect naming
./mock-release-builder.sh --version 3.11.0 --incorrect structure
./mock-release-builder.sh --version 3.11.0 --incorrect missing-files

# Create version series (older/current/newer)
./mock-release-builder.sh --version 3.10.1 --series

# List created releases
./mock-release-builder.sh --list

# Clean up
./mock-release-builder.sh --cleanup
```

### 3. Plugin State Monitor (`plugin-state-monitor.sh`)
Monitors and tracks plugin state changes during testing.

```bash
# Take snapshot
./plugin-state-monitor.sh --snapshot before_update

# Compare states
./plugin-state-monitor.sh --compare before.json after.json

# Continuous monitoring
./plugin-state-monitor.sh --watch 300 10  # 5 minutes, check every 10 seconds

# Generate report
./plugin-state-monitor.sh --report

# List snapshots
./plugin-state-monitor.sh --list
```

### 4. WP-CLI Wrapper (`cuft-wp-cli.sh`)
Convenient wrapper for WordPress CLI operations.

```bash
# Plugin operations
./cuft-wp-cli.sh plugin status
./cuft-wp-cli.sh plugin install
./cuft-wp-cli.sh plugin update-check
./cuft-wp-cli.sh plugin version

# GitHub updater operations
./cuft-wp-cli.sh updater test
./cuft-wp-cli.sh updater remote-version

# WordPress info
./cuft-wp-cli.sh wordpress info

# Debug operations
./cuft-wp-cli.sh debug enable
./cuft-wp-cli.sh debug disable

# Direct wp-cli access
./cuft-wp-cli.sh wp plugin list
./cuft-wp-cli.sh wp option get cuft_gtm_id
```

### 5. Comprehensive Test Runner (`run-comprehensive-tests.sh`)
Orchestrates all test tools for complete validation.

```bash
# Full test suite (recommended)
./run-comprehensive-tests.sh --full

# Individual test suites
./run-comprehensive-tests.sh --basic
./run-comprehensive-tests.sh --advanced
./run-comprehensive-tests.sh --update-flow
./run-comprehensive-tests.sh --edge-cases
./run-comprehensive-tests.sh --performance

# Generate report only
./run-comprehensive-tests.sh --report
```

## Test Scenarios

### Basic Functionality Tests
- Plugin installation and activation
- GitHub updater availability
- Remote version checking
- Download URL format validation
- WordPress update detection
- Plugin reactivation safety

### Advanced Tests
- Mock release creation and validation
- State monitoring and comparison
- WP-CLI wrapper functionality
- Asset naming enforcement
- Error condition handling

### Update Flow Tests
- Complete update process simulation
- Before/after state comparison
- Plugin activation persistence
- Version number validation

### Edge Cases
- Incorrect asset naming
- Missing release assets
- Corrupted plugin state
- Network failure simulation

### Performance Tests
- Response time monitoring
- Resource usage tracking
- Concurrent operation handling

## Output Structure

```
scripts/
├── logs/                    # Test execution logs
├── reports/                 # HTML and text reports
├── state/                   # Plugin state snapshots
├── mock-releases/           # Test release assets
└── temp/                    # Temporary build files
```

## Test Results

### Success Criteria
- ✅ All update mechanisms use correct asset naming (`choice-uft-v{version}.zip`)
- ✅ Plugin remains active after updates
- ✅ Version numbers update correctly
- ✅ No duplicate plugin entries in WordPress
- ✅ Error messages are clear and actionable
- ✅ Archive URLs are never used (only release assets)
- ✅ Admin panel uses GitHub updater method
- ✅ Reactivation safety mechanisms work

### Report Generation
Each test run generates:
- **HTML Report**: Visual dashboard with test results, timing, and status
- **Text Summary**: Concise pass/fail summary
- **Detailed Logs**: Complete execution logs with timestamps
- **State Snapshots**: JSON files capturing plugin state at various points

## Troubleshooting

### Container Issues
```bash
# Check container status
docker ps | grep wp-pdev

# Restart containers
cd /home/r11/dev/wp-pdev
docker-compose down
docker-compose up -d
```

### Plugin Sync Issues
```bash
# Manual sync
rsync -av --exclude='.git' /home/r11/dev/wp/plugins/choice-uft/ /home/r11/dev/wp-pdev/wp-content/plugins/choice-uft/

# Check permissions
sudo chown -R www-data:www-data /home/r11/dev/wp-pdev/wp-content/plugins/choice-uft/
```

### Test Failures
1. Check detailed logs in `logs/` directory
2. Review state snapshots for changes
3. Verify container connectivity
4. Check plugin file permissions
5. Validate WordPress installation

### Common Issues
- **"Container not running"**: Start Docker containers
- **"Plugin directory not found"**: Sync plugin code first
- **"Permission denied"**: Check file permissions
- **"GitHub API errors"**: Check internet connectivity
- **"WP-CLI timeouts"**: Increase container resources

## Integration with CI/CD

These tests can be integrated into automated pipelines:

```bash
# In your CI/CD pipeline
./run-comprehensive-tests.sh --full
if [ $? -eq 0 ]; then
    echo "All tests passed - proceed with deployment"
else
    echo "Tests failed - block deployment"
    exit 1
fi
```

## Best Practices

1. **Always sync code** before running tests
2. **Take state snapshots** before major operations
3. **Run full suite** before releases
4. **Review HTML reports** for detailed analysis
5. **Clean up** old test files regularly
6. **Monitor container resources** during long test runs
7. **Keep test environment** separate from development

## Support

For issues or questions:
1. Check the detailed logs in `logs/` directory
2. Review the generated HTML reports
3. Verify container status and connectivity
4. Ensure all prerequisites are installed
5. Check plugin file permissions and structure