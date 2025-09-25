# Quickstart: CI Release Preparation Testing

This quickstart guide validates the CI release preparation system by simulating the complete release workflow.

## Prerequisites

- GitHub repository with push access
- GitHub CLI installed and authenticated (`gh auth status`)
- Git repository with clean working directory
- Ability to create and push git tags

## Test Scenario 1: Successful Release Creation

**Objective**: Verify complete release workflow from tag creation to package upload

### Step 1: Prepare Test Release
```bash
# Ensure clean working directory
git status

# Update version in plugin files (if needed for test)
# This step validates version consistency checking

# Create annotated tag
git tag -a v3.9.99-test -m "Test release for CI validation"

# Push tag to trigger workflow
git push origin v3.9.99-test
```

### Step 2: Monitor Workflow Execution
```bash
# Watch workflow progress
gh run watch

# Check workflow logs
gh run view --log
```

### Step 3: Validate Release Creation
```bash
# List releases to confirm creation
gh release list

# Check release assets
gh release view v3.9.99-test

# Download and inspect package
gh release download v3.9.99-test
ls -la choice-uft.zip
```

### Step 4: Validate Package Structure
```bash
# Extract package to temporary directory
mkdir temp-validation
cd temp-validation
unzip ../choice-uft.zip

# Verify directory structure
ls -la
# Expected: choice-uft/ directory

cd choice-uft
ls -la
# Expected: choice-universal-form-tracker.php, README.md, assets/, includes/, etc.

# Check for excluded files (should not exist)
find . -name ".git" -o -name "node_modules" -o -name ".env*"
# Expected: no output (files properly excluded)

# Verify main plugin file
head choice-universal-form-tracker.php
# Expected: WordPress plugin header with version 3.9.99
```

### Step 5: Clean Up Test
```bash
# Remove test release
gh release delete v3.9.99-test --yes

# Remove local tag
git tag -d v3.9.99-test

# Remove remote tag
git push origin --delete v3.9.99-test

# Clean up files
cd ../..
rm -rf temp-validation choice-uft.zip
```

**Success Criteria**:
- [ ] Workflow completes without errors
- [ ] Release is created with correct tag name
- [ ] Package asset is attached to release
- [ ] Package extracts to 'choice-uft' directory
- [ ] All critical files are present
- [ ] No development files are included
- [ ] Version numbers match in plugin files

## Test Scenario 2: Version Mismatch Detection

**Objective**: Verify that CI detects and fails on version inconsistencies

### Step 1: Create Version Mismatch
```bash
# Backup current plugin file
cp choice-universal-form-tracker.php choice-universal-form-tracker.php.backup

# Temporarily modify version in plugin header only
sed -i 's/Version: [0-9.]*/Version: 9.9.9/' choice-universal-form-tracker.php

# Commit temporary change
git add choice-universal-form-tracker.php
git commit -m "Temporary version mismatch for CI testing"
```

### Step 2: Trigger Workflow with Mismatch
```bash
# Create tag with different version
git tag -a v3.9.98-test -m "Test version mismatch detection"
git push origin v3.9.98-test
```

### Step 3: Verify Failure Detection
```bash
# Check workflow status (should fail)
gh run list --limit 1

# View failure logs
gh run view --log
# Expected: Error message about version mismatch
```

### Step 4: Clean Up
```bash
# Restore original file
mv choice-universal-form-tracker.php.backup choice-universal-form-tracker.php

# Commit fix
git add choice-universal-form-tracker.php
git commit -m "Restore correct version after CI test"

# Clean up tags
git tag -d v3.9.98-test
git push origin --delete v3.9.98-test
```

**Success Criteria**:
- [ ] Workflow fails at version validation step
- [ ] Error message clearly identifies version mismatch
- [ ] No release is created
- [ ] No package is uploaded

## Test Scenario 3: Manual Package Validation

**Objective**: Test package validation logic independently of CI

### Step 1: Create Test Package Manually
```bash
# Create temporary directory with correct structure
mkdir -p temp-package/choice-uft
cd temp-package/choice-uft

# Copy production files (simulate what CI would do)
cp -r ../../assets .
cp -r ../../includes .
cp ../../choice-universal-form-tracker.php .
cp ../../README.md .
cp ../../CHANGELOG.md .

# Create package
cd ..
zip -r choice-uft-manual.zip choice-uft/

# Move package for validation
mv choice-uft-manual.zip ../
cd ..
rm -rf temp-package
```

### Step 2: Validate Package Structure
```bash
# Use validation script (to be created during implementation)
# This validates the contract specifications

# Test extraction
mkdir validation-test
cd validation-test
unzip ../choice-uft-manual.zip

# Check directory name
ls -la
# Expected: choice-uft/ directory

# Check critical files
cd choice-uft
for file in "choice-universal-form-tracker.php" "README.md" "CHANGELOG.md"; do
  if [ -f "$file" ]; then
    echo "✓ $file found"
  else
    echo "✗ $file missing"
  fi
done

# Check directories
for dir in "assets" "includes"; do
  if [ -d "$dir" ]; then
    echo "✓ $dir directory found"
  else
    echo "✗ $dir directory missing"
  fi
done
```

### Step 3: Test Version Extraction
```bash
# Extract version from plugin header
grep "Version:" choice-universal-form-tracker.php

# Extract version from constant
grep "CUFT_VERSION" choice-universal-form-tracker.php

# Verify versions match
# (This logic will be implemented in the validation script)
```

### Step 4: Clean Up
```bash
cd ../..
rm -rf validation-test choice-uft-manual.zip
```

**Success Criteria**:
- [ ] Package extracts correctly
- [ ] All critical files and directories present
- [ ] Version numbers are extractable and consistent
- [ ] Package size is within reasonable limits

## Test Scenario 4: WordPress Installation Simulation

**Objective**: Verify package works with WordPress plugin installation

### Step 1: Prepare WordPress Test Environment
```bash
# This requires a WordPress installation for testing
# Can be done with Docker, local install, or staging site

# Download latest package from actual release
gh release download --pattern "choice-uft.zip"
```

### Step 2: Install Plugin from Package
```bash
# Upload to WordPress (manual process)
# - Go to WordPress Admin → Plugins → Add New → Upload Plugin
# - Select choice-uft.zip
# - Click "Install Now"
# - Activate plugin

# Alternatively, extract to plugins directory
# unzip choice-uft.zip -d /path/to/wordpress/wp-content/plugins/
```

### Step 3: Verify Installation
- Check that plugin appears in WordPress admin plugin list
- Verify plugin can be activated without errors
- Test basic plugin functionality (form tracking)
- Check that plugin settings page loads correctly

**Success Criteria**:
- [ ] Plugin uploads successfully to WordPress
- [ ] Plugin extracts to correct directory name
- [ ] Plugin activates without PHP errors
- [ ] Plugin functionality works as expected
- [ ] Plugin appears correctly in admin interface

## Continuous Integration Test

**Objective**: Run these tests as part of CI to validate the release process

### Automated Test Script
```bash
#!/bin/bash
# This script will be created during implementation
# to automate the validation process

set -e

echo "Testing CI release preparation..."

# Run version validation test
echo "Testing version consistency..."
# Implementation: validate current versions are consistent

# Run package creation test
echo "Testing package creation..."
# Implementation: create package and validate structure

# Run package validation test
echo "Testing package validation..."
# Implementation: run validation contract tests

echo "All tests passed!"
```

## Performance Benchmarks

Track these metrics during testing:

- **Workflow Duration**: Total time from tag push to release creation
- **Package Size**: Size of generated zip file
- **Validation Time**: Time taken for package validation
- **Upload Time**: Time to upload package to GitHub releases

**Target Benchmarks**:
- Total workflow: < 5 minutes
- Package validation: < 30 seconds
- Package size: 1-10 MB (typical), < 50 MB (maximum)
- Upload time: < 2 minutes (network dependent)

## Troubleshooting Common Issues

### Workflow Fails to Trigger
- Verify tag follows 'v*' pattern (e.g., v1.2.3)
- Check that GitHub Actions are enabled for repository
- Confirm workflow file is in `.github/workflows/` directory

### Version Validation Fails
- Check all version numbers in plugin files match
- Verify semantic version format (X.Y.Z)
- Ensure no extra whitespace around version numbers

### Package Creation Fails
- Verify all critical files exist in repository
- Check file permissions for readability
- Ensure no file path conflicts or invalid characters

### Upload Fails
- Check GitHub token permissions
- Verify release doesn't already exist
- Confirm network connectivity

This quickstart provides comprehensive validation of the CI release preparation system through multiple test scenarios covering success paths, error conditions, and integration testing.