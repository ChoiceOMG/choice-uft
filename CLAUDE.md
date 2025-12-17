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

4. **Create Release ZIP with CORRECT naming**:
   ```bash
   # CRITICAL: Use "v" prefix in filename
   git archive --format=zip --prefix=choice-uft/ -o /tmp/choice-uft-v3.x.x.zip HEAD
   ```

5. **Validate ZIP Structure**:
   ```bash
   # Must extract to choice-uft/ directory (NOT choice-uft-v3.x.x/)
   unzip -l /tmp/choice-uft-v3.x.x.zip | head -20

   # Verify:
   # ✅ All paths start with "choice-uft/"
   # ✅ Main plugin file is at: choice-uft/choice-universal-form-tracker.php
   # ✅ No development files (tests/, .github/, .specify/)
   ```

6. **Create GitHub Release**:
   ```bash
   # Create tag and release
   gh release create v3.x.x \
     --title "v3.x.x" \
     --notes "$(cat <<'EOF'
   [Paste changelog content here]
   EOF
   )" \
     /tmp/choice-uft-v3.x.x.zip
   ```

7. **Verify Release**:
   ```bash
   # Confirm asset uploaded with correct name
   gh release view v3.x.x --json assets --jq '.assets[] | .name'

   # Expected output: choice-uft-v3.x.x.zip (with "v" prefix!)
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
git archive --format=zip --prefix=choice-uft/ -o /tmp/choice-uft-v3.x.x.zip HEAD
gh release upload v3.x.x /tmp/choice-uft-v3.x.x.zip --clobber

# Verify both assets exist now
gh release view v3.x.x --json assets --jq '.assets[] | .name'

# The updater will use the correctly-named one (choice-uft-v3.x.x.zip)
```

---
