# CLAUDE.md - Development Guidelines for Choice Universal Form Tracker

## CRITICAL: Always Reference Specifications First

### Before ANY Code Changes
1. **MANDATORY**: Read and understand relevant specifications:
   - [.specify/memory/constitution.md](.specify/memory/constitution.md) - Core principles and standards
   - [specs/core/dataLayer.spec.md](specs/core/dataLayer.spec.md) - DataLayer event requirements
   - [specs/core/tracking-params.spec.md](specs/core/tracking-params.spec.md) - UTM/Click ID handling
   - Framework-specific specs in [specs/frameworks/](specs/frameworks/)
   - [specs/testing/test-suite.spec.md](specs/testing/test-suite.spec.md) - Testing requirements
   - [.specify/memory/agents.md](.specify/memory/agents.md) - AI development guidelines
   - [.specify/memory/review-checklist.md](.specify/memory/review-checklist.md) - Code review checklist

2. **VALIDATE**: Ensure proposed changes align with constitutional principles
3. **CHECK**: Verify compatibility with existing implementations
4. **PLAN**: Reference implementation plan templates if creating new features

### Implementation and Migration Templates
When implementing new features or migrating existing code:
- **New Features**: Use [.specify/templates/implementation-plan-template.md](.specify/templates/implementation-plan-template.md)
- **Code Updates**: Use [.specify/templates/migration-plan-template.md](.specify/templates/migration-plan-template.md)
- **All Changes**: Follow the constitutional compliance checklist
- **Risk Assessment**: Always include risk mitigation strategies

### Mandatory Pre-Commit Validation
Before committing any code changes, ALWAYS verify using [.specify/memory/review-checklist.md](.specify/memory/review-checklist.md):

---

## Two distribution channels, one tree

`build.sh` produces both packages. They differ only by file list, never by editing code.

```bash
./build.sh          # GitHub release  -> dist/choice-uft/
./build.sh --wporg  # WordPress.org   -> dist/choice-universal-form-tracker/
```

The WordPress.org package applies `.wporgignore` on top of `.distignore` and **strips the
self-update subsystem**. Directory guideline 8 forbids a hosted plugin from "serving updates
or otherwise installing plugins from servers other than WordPress.org's", so directory
installs get their updates from core. `Choice_Universal_Form_Tracker::has_updater()` detects
the missing files at runtime and skips loading them, the Force Update tab, and its assets.

The directory names differ on purpose: the GitHub updater matches releases against the
`choice-uft/` install path, while WordPress.org derives the slug from the directory and
expects it to match the `Text Domain` header.

`build.sh` fails the build on a version mismatch across the three version sources, hidden
files, em-dashes in shipped files, and (for `--wporg`) any self-update file or third-party
CDN reference reaching the package. Prefer it over `git archive`, which ships development
files and cannot produce the directory package.

## CRITICAL: GitHub Release Process

### Release Asset Naming Convention

**MANDATORY**: Release ZIP files MUST follow this exact naming pattern:

```
choice-uft-v{VERSION}.zip
```

**Examples**:
- ✅ CORRECT: `choice-uft-v3.20.0.zip`
- ✅ CORRECT: `choice-uft-v3.21.0.zip`
- ❌ WRONG: `choice-uft-3.20.0.zip` (missing "v" prefix)
- ❌ WRONG: `choice-uft.zip` (missing version)

**Why This Matters**:
The GitHub updater class (`includes/class-cuft-github-updater.php`) specifically looks for release assets with the "v" prefix:
- Line 300: `choice-uft-v{$version}.zip`
- Line 352: `choice-uft-v{$version}.zip`

If the asset name doesn't match exactly, WordPress update checks will fail with "Download failed. Not Found" errors.

### Release Creation Checklist

When creating a new GitHub release, follow these steps IN ORDER:

1. **Version Bump**:
   ```bash
   # Update version in main plugin file
   # Edit: choice-universal-form-tracker.php
   # Change: Version: 3.x.x
   # Change: define( 'CUFT_VERSION', '3.x.x' );
   ```

2. **Update Changelog**:
   ```bash
   # Edit CHANGELOG.md
   # Add new version section at the top with:
   # - Date
   # - Added/Changed/Fixed/Security sections
   # - Comprehensive feature descriptions
   ```

3. **Commit & Push**:
   ```bash
   git add choice-universal-form-tracker.php CHANGELOG.md
   git commit -m "chore: Bump version to 3.x.x"
   git push origin master
   ```

4. **Pre-flight the package locally**:
   ```bash
   ./build.sh
   ```
   Local check only. `release.yml` does NOT run `build.sh`: it rsyncs against
   `.distignore` directly. The build gates (version consistency across the three
   sources, hidden files, em-dashes, CDN references) therefore never run in CI, so
   this step is the only place they catch anything.

5. **Tag and push. The release publishes itself**:
   ```bash
   git tag -a v3.x.x -m "v3.x.x"
   git push origin v3.x.x
   ```
   `.github/workflows/release.yml` fires on any `v*.*.*` tag, builds
   `choice-uft-v3.x.x.zip` with the required "v" prefix, extracts the matching
   CHANGELOG section as release notes, and publishes as `github-actions[bot]`.

   Do NOT run `gh release create`. It creates the tag itself, which fires the same
   workflow, so the two paths collide over the notes and the asset upload.

6. **Verify the published release**:
   ```bash
   gh run list --workflow='Build and Release' --limit 1
   gh release view v3.x.x --json assets --jq '.assets[] | .name'
   # Expected: choice-uft-v3.x.x.zip (with "v" prefix)

   # Inspect the artifact clients will actually install
   gh release download v3.x.x --pattern 'choice-uft-v3.x.x.zip' --dir /tmp/rel
   unzip -l /tmp/rel/choice-uft-v3.x.x.zip | head
   # Every path starts with choice-uft/ ; no tests/, .github/, .specify/, vendor/
   ```

### Post-Release Validation

After publishing the release:

1. **Test Update Detection**:
   - Navigate to production site: Settings → Universal Form Tracker → Force Update
   - Click "Check for Updates"
   - Verify new version is detected

2. **Test Update Installation**:
   - Click "Force Reinstall" OR use WordPress plugins page
   - Verify download succeeds (no "Not Found" errors)
   - Confirm version updates correctly

3. **Verify Functionality**:
   - Check plugin settings page loads
   - Verify all features work as expected
   - Check for PHP errors in logs

### Common Mistakes to Avoid

1. ❌ **Missing "v" prefix in ZIP filename** - Most common error, breaks updates
2. ❌ **Wrong directory structure** - ZIP must extract to `choice-uft/` not `choice-uft-v3.x.x/`
3. ❌ **Including development files** - Verify `.gitattributes` excludes properly
4. ❌ **Forgetting to update CHANGELOG.md** - Users need release notes
5. ❌ **Not testing the update** - Always validate on staging/production before announcing

### Emergency Fix: Wrong Asset Name

If you accidentally upload with the wrong name:

```bash
# Upload corrected asset to existing release
./build.sh
cp dist/choice-uft.zip /tmp/choice-uft-v3.x.x.zip
gh release upload v3.x.x /tmp/choice-uft-v3.x.x.zip --clobber

# Verify both assets exist now
gh release view v3.x.x --json assets --jq '.assets[] | .name'

# The updater will use the correctly-named one (choice-uft-v3.x.x.zip)
```

---
