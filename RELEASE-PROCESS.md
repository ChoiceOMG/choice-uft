# Release Process for Choice Universal Form Tracker

**Last Updated**: 2025-10-14
**Current Version**: 3.19.2

---

## Table of Contents

1. [Quick Reference](#quick-reference)
2. [ZIP File Structure Requirements](#zip-file-structure-requirements)
3. [Release Workflow](#release-workflow)
4. [Version Numbering](#version-numbering)
5. [Testing Checklist](#testing-checklist)
6. [Common Issues](#common-issues)

---

## Quick Reference

### Creating a Release (Standard Process)

```bash
# 1. Update version numbers
# Edit: choice-universal-form-tracker.php (Version header + CUFT_VERSION constant)
# Edit: CHANGELOG.md (add release notes)
# Edit: readme.txt (stable tag + changelog)

# 2. Commit and tag
git add choice-universal-form-tracker.php CHANGELOG.md readme.txt
git commit -m "chore: Bump version to X.Y.Z"
git tag -a vX.Y.Z -m "Release vX.Y.Z - Short Description"
git push origin master
git push origin vX.Y.Z

# 3. Create release ZIP with CORRECT directory structure
git checkout vX.Y.Z
git archive --format=zip --prefix=choice-uft-vX.Y.Z/ -o /tmp/choice-uft-vX.Y.Z.zip HEAD
git checkout master

# 4. Create GitHub release
gh release create vX.Y.Z /tmp/choice-uft-vX.Y.Z.zip \
  --title "vX.Y.Z - Release Title" \
  --notes-file /path/to/release-notes.md

# 5. Verify release
gh release view vX.Y.Z --json assets --jq '.assets[].name'
# Should show: choice-uft-vX.Y.Z.zip
```

---

## ZIP File Structure Requirements

### ⚠️ CRITICAL: Directory Naming Convention

**The ZIP file MUST contain a versioned directory name**, not the final plugin slug.

### ✅ Correct Structure

```
choice-uft-v3.19.2.zip
└── choice-uft-v3.19.2/          ← Versioned directory (CORRECT)
    ├── choice-universal-form-tracker.php
    ├── includes/
    ├── assets/
    ├── CHANGELOG.md
    └── readme.txt
```

**Command**:
```bash
git archive --format=zip --prefix=choice-uft-v3.19.2/ -o /tmp/choice-uft-v3.19.2.zip v3.19.2
#                                  ^^^^^^^^^^^^^^^^^^
#                                  Must include version number!
```

### ❌ Incorrect Structure

```
choice-uft-v3.19.2.zip
└── choice-uft/                   ← Non-versioned directory (WRONG!)
    ├── choice-universal-form-tracker.php
    ├── includes/
    └── ...
```

**Wrong Command**:
```bash
git archive --format=zip --prefix=choice-uft/ ...  # DON'T DO THIS
```

---

## Why This Matters

### WordPress Update System Integration

The plugin uses WordPress's native `Plugin_Upgrader` system with custom filters to handle GitHub releases:

1. **WordPress extracts the ZIP** to `/wp-content/upgrade/choice-uft-v3.19.2/`
2. **Our `upgrader_source_selection` filter detects** the versioned directory
3. **Filter renames** `choice-uft-v3.19.2/` → `choice-uft/`
4. **WordPress copies** renamed directory to `/wp-content/plugins/choice-uft/`

### The Filter (CUFT_Directory_Fixer)

Located in: `includes/update/class-cuft-directory-fixer.php`

```php
add_filter('upgrader_source_selection', [$this, 'fix_plugin_directory_name'], 10, 4);

public function fix_plugin_directory_name($source, $remote_source, $upgrader, $hook_extra) {
    // Detects patterns like:
    // - choice-uft-v3.19.2/
    // - choice-uft-3.19.2/
    // - choice-uft-master/
    // - ChoiceOMG-choice-uft-abc1234/

    // Renames to: choice-uft/
}
```

### What Happens Without Versioned Directory

If the ZIP contains `choice-uft/` (non-versioned):
- ✅ Update might succeed (directory already correct)
- ❌ Filter is bypassed (no validation happens)
- ❌ Breaks documented contract (Feature 008 specs)
- ❌ Inconsistent with GitHub's default ZIP structure
- ❌ Future maintainers may be confused

**Reference**: [specs/008-fix-critical-gaps/contracts/upgrader-source-selection-filter.md](specs/008-fix-critical-gaps/contracts/upgrader-source-selection-filter.md)

---

## Release Workflow

### Phase 1: Version Bump

1. **Determine version number** using [Semantic Versioning](https://semver.org/):
   - MAJOR: Breaking changes (X.0.0)
   - MINOR: New features, backward compatible (x.Y.0)
   - PATCH: Bug fixes, backward compatible (x.y.Z)

2. **Update version in files**:
   ```bash
   # Main plugin file
   vim choice-universal-form-tracker.php
   # Line 4:  * Version:           3.19.2
   # Line 16: define( 'CUFT_VERSION', '3.19.2' );

   # WordPress readme
   vim readme.txt
   # Line 7:  Stable tag: 3.19.2
   # Line 135: = 3.19.2 =

   # Developer changelog
   vim CHANGELOG.md
   # Add new section: ## [3.19.2] - 2025-10-14
   ```

3. **Commit version bump**:
   ```bash
   git add choice-universal-form-tracker.php CHANGELOG.md readme.txt
   git commit -m "chore: Bump version to 3.19.2"
   ```

### Phase 2: Git Tag & Push

```bash
# Create annotated tag
git tag -a v3.19.2 -m "Release v3.19.2 - Short description"

# Push commits and tag
git push origin master
git push origin v3.19.2
```

### Phase 3: Create Release ZIP

**⚠️ CRITICAL STEP - Use Correct Prefix**

```bash
# Checkout the tag
git checkout v3.19.2

# Create ZIP with VERSIONED directory prefix
git archive --format=zip --prefix=choice-uft-v3.19.2/ -o /tmp/choice-uft-v3.19.2.zip HEAD
#                                   ^^^^^^^^^^^^^^^^^^
#                                   MUST match tag version!

# Return to master
git checkout master

# Verify ZIP structure
unzip -l /tmp/choice-uft-v3.19.2.zip | head -20
# Should show: choice-uft-v3.19.2/choice-universal-form-tracker.php
#             choice-uft-v3.19.2/includes/
#             choice-uft-v3.19.2/assets/
```

### Phase 4: GitHub Release

```bash
# Option 1: Using GitHub CLI (recommended)
gh release create v3.19.2 /tmp/choice-uft-v3.19.2.zip \
  --title "v3.19.2 - Release Title" \
  --notes "$(cat <<'EOF'
# v3.19.2 - Release Title

## Changes
- Feature 1
- Bug fix 2

## Installation
Download and install via WordPress Plugins page or WP-CLI.
EOF
)"

# Option 2: Using web interface
# 1. Go to https://github.com/ChoiceOMG/choice-uft/releases/new
# 2. Choose tag: v3.19.2
# 3. Upload: /tmp/choice-uft-v3.19.2.zip
# 4. Add release notes
# 5. Publish release
```

### Phase 5: Verification

```bash
# Verify ZIP uploaded
gh release view v3.19.2 --json assets --jq '.assets[].name'
# Expected output: choice-uft-v3.19.2.zip

# Verify ZIP structure
gh release download v3.19.2 --pattern "*.zip" --output /tmp/verify.zip
unzip -l /tmp/verify.zip | head -5
# Expected: choice-uft-v3.19.2/choice-universal-form-tracker.php

# Test update on staging
docker exec cuft-choice-zone-cli wp plugin update choice-uft
docker exec cuft-choice-zone-cli wp plugin list --name=choice-uft --fields=version
# Expected: 3.19.2
```

---

## Version Numbering

### Semantic Versioning

**Format**: `MAJOR.MINOR.PATCH`

- **MAJOR** (X.0.0): Breaking changes, API changes, major features
  - Example: v4.0.0 - Complete rewrite, new framework requirements

- **MINOR** (x.Y.0): New features, backward compatible
  - Example: v3.19.0 - Feature 009 (Force Install Update)

- **PATCH** (x.y.Z): Bug fixes, backward compatible
  - Example: v3.19.1 - Permission error fix

### Version Synchronization

Ensure version matches across:
1. `choice-universal-form-tracker.php` - Header comment (line 4)
2. `choice-universal-form-tracker.php` - `CUFT_VERSION` constant (line 16)
3. `readme.txt` - Stable tag (line 7)
4. `CHANGELOG.md` - Latest version heading
5. Git tag - `vX.Y.Z`
6. GitHub release - `vX.Y.Z`
7. ZIP filename - `choice-uft-vX.Y.Z.zip`
8. ZIP directory - `choice-uft-vX.Y.Z/`

### Pre-Release Versions

For testing releases:
- Format: `X.Y.Z-beta.N` or `X.Y.Z-rc.N`
- Example: `3.20.0-beta.1`
- Should NOT be pushed to production sites

---

## Testing Checklist

### Pre-Release Testing

- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Manual testing on local development environment
- [ ] Version numbers synchronized across all files
- [ ] CHANGELOG.md updated with release notes
- [ ] No uncommitted changes in git

### Post-Release Testing

- [ ] GitHub release created successfully
- [ ] ZIP file uploaded and accessible
- [ ] ZIP structure verified (versioned directory)
- [ ] Download ZIP and inspect contents
- [ ] Test update on staging environment
- [ ] Verify WordPress recognizes new version
- [ ] Test Force Update UI (if applicable)
- [ ] Verify update history tracked correctly

### WordPress Update System Testing

```bash
# Test WP-CLI update
docker exec cuft-choice-zone-cli wp plugin update choice-uft

# Test WordPress admin update
# Navigate to: /wp-admin/plugins.php
# Click "Update Now" for Choice Universal Form Tracker

# Test Force Reinstall (Feature 009)
# Navigate to: Settings → Universal Form Tracker → Force Update
# Click "Force Reinstall"
```

---

## Common Issues

### Issue 1: ZIP Has Wrong Directory Structure

**Symptom**: ZIP contains `choice-uft/` instead of `choice-uft-vX.Y.Z/`

**Cause**: Wrong `--prefix` argument to `git archive`

**Fix**:
```bash
# Wrong:
git archive --format=zip --prefix=choice-uft/ ...

# Correct:
git archive --format=zip --prefix=choice-uft-v3.19.2/ -o /tmp/choice-uft-v3.19.2.zip v3.19.2
```

### Issue 2: WordPress Doesn't Detect Update

**Symptom**: WordPress shows "Plugin already updated" or doesn't show update available

**Cause**:
- GitHub updates disabled in settings
- Version cache not cleared
- GitHub release not published correctly

**Fix**:
```bash
# Enable GitHub updates
docker exec cuft-choice-zone-cli wp option update cuft_github_updates_enabled 1

# Clear update cache
docker exec cuft-choice-zone-cli wp option delete _site_transient_update_plugins
docker exec cuft-choice-zone-cli wp cron event run wp_update_plugins

# Verify GitHub release
gh release view vX.Y.Z --json assets
```

### Issue 3: Update Fails with "Directory Not Found"

**Symptom**: Update aborts with error about missing plugin directory

**Cause**: ZIP structure doesn't match expected pattern or filter failed

**Fix**:
1. Verify ZIP has versioned directory: `unzip -l choice-uft-vX.Y.Z.zip | head`
2. Check filter logs: `docker exec cuft-choice-zone-cli wp eval "echo get_option('cuft_debug_logs');"`
3. Verify `class-cuft-directory-fixer.php` is loaded

### Issue 4: Version Mismatch After Update

**Symptom**: WordPress shows old version after update completes

**Cause**:
- Cache not cleared
- Plugin not reloaded
- Version constant not updated in code

**Fix**:
```bash
# Clear all caches
docker exec cuft-choice-zone-cli wp cache flush
docker exec cuft-choice-zone-cli wp plugin deactivate choice-uft
docker exec cuft-choice-zone-cli wp plugin activate choice-uft

# Verify version in code
docker exec cuft-choice-zone-cli wp eval "echo CUFT_VERSION;"
```

---

## Emergency Rollback

If a release has critical issues:

### Option 1: Fix Forward (Preferred)

```bash
# Create hotfix version
# Example: 3.19.2 → 3.19.3

# Follow standard release process with fix
```

### Option 2: Delete Release (Not Recommended)

```bash
# Delete GitHub release
gh release delete vX.Y.Z --yes

# Delete git tag locally and remotely
git tag -d vX.Y.Z
git push origin :refs/tags/vX.Y.Z
```

**⚠️ Warning**: Deleting releases can break installations that already downloaded that version.

---

## Automation Opportunities

### Future Improvements

1. **Release Script**: Create `scripts/release.sh` to automate version bumping and ZIP creation
2. **GitHub Actions**: Automate ZIP creation on tag push
3. **Version Validation**: Pre-commit hook to ensure version sync
4. **Changelog Generation**: Auto-generate from git commits

---

## References

- [Feature 008 Specification](specs/008-fix-critical-gaps/spec.md)
- [Upgrader Source Selection Contract](specs/008-fix-critical-gaps/contracts/upgrader-source-selection-filter.md)
- [WordPress Plugin_Upgrader Verification](specs/008-fix-critical-gaps/PLUGIN-UPGRADER-VERIFICATION.md)
- [Semantic Versioning 2.0.0](https://semver.org/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)

---

## Version History of This Document

- **v1.0** (2025-10-14): Initial documentation
  - Documented correct ZIP structure requirement
  - Added release workflow
  - Added common issues and solutions
