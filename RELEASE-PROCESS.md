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

# 3. Create release ZIP with CORRECT structure
cd /home/r11/dev
zip -r choice-uft-vX.Y.Z.zip choice-uft \
  -x "choice-uft/.git/*" \
  -x "choice-uft/.gitignore" \
  -x "choice-uft/node_modules/*" \
  -x "choice-uft/.DS_Store"

# 4. Verify ZIP structure (CRITICAL!)
unzip -l choice-uft-vX.Y.Z.zip | head -10
# MUST show: choice-uft/choice-universal-form-tracker.php
#            choice-uft/includes/
#            choice-uft/assets/

# 5. Create GitHub release
gh release create vX.Y.Z choice-uft-vX.Y.Z.zip \
  --title "vX.Y.Z - Release Title" \
  --notes-file /path/to/release-notes.md

# 6. Verify release
gh release view vX.Y.Z --json assets --jq '.assets[].name'
# Should show: choice-uft-vX.Y.Z.zip
```

---

## ZIP File Structure Requirements

### ⚠️ CRITICAL: WordPress Plugin Directory Structure

**WordPress plugins MUST extract to a directory matching the plugin slug.**

### ✅ CORRECT Structure

```
choice-uft-v3.19.2.zip
└── choice-uft/                  ← Plugin slug (CORRECT!)
    ├── choice-universal-form-tracker.php
    ├── includes/
    ├── assets/
    ├── CHANGELOG.md
    └── readme.txt
```

**Command (from parent directory)**:
```bash
cd /home/r11/dev
zip -r choice-uft-v3.19.2.zip choice-uft \
  -x "choice-uft/.git/*" \
  -x "choice-uft/.gitignore"
#      ^^^^^^^^^^
#      Plugin folder name (NOT versioned!)
```

### ❌ INCORRECT Structure

```
choice-uft-v3.19.2.zip
└── choice-uft-v3.19.2/          ← Versioned directory (WRONG!)
    ├── choice-universal-form-tracker.php
    ├── includes/
    └── ...
```

**Wrong Command - DO NOT USE**:
```bash
git archive --format=zip --prefix=choice-uft-v3.19.2/ ...  # ❌ WRONG!
```

---

## Why This Matters

### WordPress Plugin Installation Process

1. **User downloads**: `choice-uft-v3.19.2.zip` from GitHub
2. **WordPress extracts** to `/wp-content/upgrade/choice-uft/` (temp location)
3. **WordPress moves** to `/wp-content/plugins/choice-uft/` (final location)
4. **WordPress activates** plugin from `/wp-content/plugins/choice-uft/choice-universal-form-tracker.php`

### What Happens With Wrong Structure

If ZIP contains `choice-uft-v3.19.2/`:
- ❌ WordPress extracts to `/wp-content/upgrade/choice-uft-v3.19.2/`
- ❌ WordPress tries to move to `/wp-content/plugins/choice-uft-v3.19.2/`
- ❌ Plugin slug mismatch: expects `choice-uft` but got `choice-uft-v3.19.2`
- ❌ WordPress cannot find original plugin at `/wp-content/plugins/choice-uft/`
- ❌ Update fails or creates duplicate plugin directory

### Plugin Slug Definition

The plugin slug is determined by the **directory name** in `/wp-content/plugins/`:

```
/wp-content/plugins/choice-uft/choice-universal-form-tracker.php
                    ^^^^^^^^^^
                    This is the plugin slug (must match ZIP contents)
```

WordPress uses this slug for:
- Plugin identification in database
- Update detection
- Activation/deactivation
- Plugin file paths

**The ZIP contents MUST match this directory name.**

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

**⚠️ CRITICAL STEP - Use Correct Method**

```bash
# Navigate to PARENT directory of plugin
cd /home/r11/dev

# Create ZIP with plugin folder (NOT versioned directory!)
zip -r choice-uft-v3.19.2.zip choice-uft \
  -x "choice-uft/.git/*" \
  -x "choice-uft/.gitignore" \
  -x "choice-uft/node_modules/*" \
  -x "choice-uft/.DS_Store" \
  -x "choice-uft/.env" \
  -x "choice-uft/*.log"

# Verify ZIP structure (MANDATORY!)
unzip -l choice-uft-v3.19.2.zip | head -20

# Expected output:
# Archive:  choice-uft-v3.19.2.zip
#   Length      Date    Time    Name
# ---------  ---------- -----   ----
#         0  2025-10-14 15:42   choice-uft/
#      9999  2025-10-14 15:42   choice-uft/choice-universal-form-tracker.php
#         0  2025-10-14 15:42   choice-uft/includes/
#         0  2025-10-14 15:42   choice-uft/assets/
```

**Alternative: Using git archive (also correct)**:

```bash
# If you prefer git archive, use this:
git archive --format=zip --prefix=choice-uft/ -o choice-uft-v3.19.2.zip v3.19.2
#                                   ^^^^^^^^^^^
#                                   Plugin slug (NO version number!)
```

### Phase 4: GitHub Release

```bash
# Option 1: Using GitHub CLI (recommended)
gh release create v3.19.2 choice-uft-v3.19.2.zip \
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
# 3. Upload: choice-uft-v3.19.2.zip
# 4. Add release notes
# 5. Publish release
```

### Phase 5: Verification

```bash
# Verify ZIP uploaded
gh release view v3.19.2 --json assets --jq '.assets[].name'
# Expected output: choice-uft-v3.19.2.zip

# Verify ZIP structure (CRITICAL!)
gh release download v3.19.2 --pattern "*.zip" --output /tmp/verify.zip
unzip -l /tmp/verify.zip | head -10
# MUST show: choice-uft/choice-universal-form-tracker.php
#            choice-uft/includes/
#            choice-uft/assets/

# Test installation on staging
docker exec cuft-choice-zone-cli wp plugin install /tmp/verify.zip --force
docker exec cuft-choice-zone-cli wp plugin list --name=choice-uft --fields=version
# Expected: 3.19.2
```

---

## Files to Exclude from ZIP

Always exclude these development files:

```bash
-x "choice-uft/.git/*"           # Git repository
-x "choice-uft/.gitignore"       # Git ignore file
-x "choice-uft/node_modules/*"   # Node dependencies (if any)
-x "choice-uft/.DS_Store"        # macOS system file
-x "choice-uft/.env"             # Environment variables
-x "choice-uft/*.log"            # Log files
-x "choice-uft/tests/*"          # Test files (optional)
-x "choice-uft/docs/*"           # Documentation (optional)
```

**Include these files**:
- ✅ `choice-universal-form-tracker.php` (main plugin file)
- ✅ `readme.txt` (WordPress.org readme)
- ✅ `CHANGELOG.md` (changelog)
- ✅ `includes/` (all PHP classes)
- ✅ `assets/` (JavaScript, CSS, images)
- ✅ `specs/` (feature specifications - optional but recommended)

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
8. **ZIP contents** - `choice-uft/` (NOT versioned!)

---

## Testing Checklist

### Pre-Release Testing

- [ ] All unit tests pass
- [ ] All integration tests pass
- [ ] Manual testing on local development environment
- [ ] Version numbers synchronized across all files
- [ ] CHANGELOG.md updated with release notes
- [ ] No uncommitted changes in git
- [ ] ZIP file created from parent directory
- [ ] ZIP structure verified (contains `choice-uft/` not `choice-uft-vX.Y.Z/`)

### Post-Release Testing

- [ ] GitHub release created successfully
- [ ] ZIP file uploaded and accessible
- [ ] **ZIP structure verified** (CRITICAL - check with `unzip -l`)
- [ ] Test installation on fresh WordPress site
- [ ] Test update from previous version
- [ ] Verify WordPress recognizes plugin after installation
- [ ] Test Force Update UI (if applicable)
- [ ] Verify update history tracked correctly

### WordPress Update System Testing

```bash
# Test fresh installation
docker exec cuft-choice-zone-cli wp plugin install https://github.com/ChoiceOMG/choice-uft/releases/download/v3.19.2/choice-uft-v3.19.2.zip --activate

# Test update from older version
docker exec cuft-choice-zone-cli wp plugin update choice-uft

# Verify plugin is in correct location
docker exec cuft-choice-zone-cli ls -la /var/www/html/wp-content/plugins/ | grep choice
# Should show: choice-uft (NOT choice-uft-v3.19.2)

# Verify plugin activates correctly
docker exec cuft-choice-zone-cli wp plugin list --name=choice-uft
```

---

## Common Issues

### Issue 1: ZIP Has Wrong Directory Structure

**Symptom**: ZIP contains `choice-uft-vX.Y.Z/` instead of `choice-uft/`

**Cause**: Used `git archive` with versioned prefix or wrong zip command

**Fix**:
```bash
# Wrong:
git archive --format=zip --prefix=choice-uft-v3.19.2/ ...  # ❌ WRONG!

# Correct (Option 1 - using zip):
cd /home/r11/dev
zip -r choice-uft-v3.19.2.zip choice-uft -x "choice-uft/.git/*"

# Correct (Option 2 - using git archive):
git archive --format=zip --prefix=choice-uft/ -o choice-uft-v3.19.2.zip v3.19.2
```

**Verification**:
```bash
unzip -l choice-uft-v3.19.2.zip | head -5
# MUST show: choice-uft/choice-universal-form-tracker.php
# NOT: choice-uft-v3.19.2/choice-universal-form-tracker.php
```

### Issue 2: Plugin Installs to Wrong Directory

**Symptom**: After installation, plugin is at `/wp-content/plugins/choice-uft-v3.19.2/`

**Cause**: ZIP has versioned directory structure

**Fix**: Recreate ZIP with correct structure (see Issue 1)

### Issue 3: WordPress Shows "Plugin file does not exist"

**Symptom**: After update, WordPress says plugin file doesn't exist

**Cause**: Plugin directory name mismatch

**Fix**:
1. Verify ZIP structure: `unzip -l choice-uft-vX.Y.Z.zip | head`
2. Ensure ZIP contains `choice-uft/` not `choice-uft-vX.Y.Z/`
3. Recreate ZIP if necessary
4. Upload corrected ZIP to GitHub release

### Issue 4: Update Creates Duplicate Plugin Directory

**Symptom**: Both `/wp-content/plugins/choice-uft/` and `/wp-content/plugins/choice-uft-v3.19.2/` exist

**Cause**: ZIP has versioned directory structure

**Fix**:
1. Delete versioned directory: `rm -rf /wp-content/plugins/choice-uft-v3.19.2/`
2. Recreate ZIP with correct structure
3. Update GitHub release
4. Test update again

---

## The Role of CUFT_Directory_Fixer

**Important Note**: The `upgrader_source_selection` filter (`CUFT_Directory_Fixer`) is designed for:

- GitHub's auto-generated source archives (when downloading code via "Download ZIP" button)
- These archives have names like `ChoiceOMG-choice-uft-abc1234.zip`
- They extract to `ChoiceOMG-choice-uft-abc1234/` or `choice-uft-master/`

**Our release ZIPs do NOT need this filter** because:
- We manually create proper WordPress plugin ZIPs
- They already extract to `choice-uft/` (correct structure)
- The filter is a safety net, not required for proper releases

---

## Quick Verification Checklist

Before uploading ZIP to GitHub release:

```bash
# 1. Check ZIP filename
ls -lh choice-uft-v3.19.2.zip
# ✅ Should be: choice-uft-vX.Y.Z.zip

# 2. Check ZIP contents (CRITICAL!)
unzip -l choice-uft-v3.19.2.zip | head -10
# ✅ MUST show: choice-uft/choice-universal-form-tracker.php
# ❌ NOT: choice-uft-v3.19.2/choice-universal-form-tracker.php

# 3. Extract and verify
unzip -q choice-uft-v3.19.2.zip -d /tmp/verify
ls /tmp/verify/
# ✅ Should show: choice-uft/
# ❌ NOT: choice-uft-v3.19.2/

# 4. Check main plugin file
cat /tmp/verify/choice-uft/choice-universal-form-tracker.php | grep "Version:"
# ✅ Should match: 3.19.2
```

---

## Emergency Rollback

If a release has the wrong ZIP structure:

### Fix Without Deleting Release

```bash
# 1. Create corrected ZIP
cd /home/r11/dev
zip -r choice-uft-vX.Y.Z.zip choice-uft -x "choice-uft/.git/*"

# 2. Verify structure
unzip -l choice-uft-vX.Y.Z.zip | head -10

# 3. Delete bad ZIP from release
gh release delete-asset vX.Y.Z choice-uft-vX.Y.Z.zip --yes

# 4. Upload corrected ZIP
gh release upload vX.Y.Z choice-uft-vX.Y.Z.zip

# 5. Verify
gh release download vX.Y.Z --pattern "*.zip" --output /tmp/verify.zip
unzip -l /tmp/verify.zip | head -10
```

---

## Version History of This Document

- **v1.0** (2025-10-14): Initial documentation (INCORRECT - had versioned directories)
- **v2.0** (2025-10-14): **CORRECTED** - WordPress plugins MUST extract to plugin slug directory
  - Fixed ZIP creation command
  - Removed incorrect `git archive` with versioned prefix
  - Added clear explanation of WordPress plugin directory requirements
  - Added verification steps
