# Release Process for Choice Universal Form Tracker

This document outlines the complete release process to ensure reliable automatic updates for all client sites.

## Pre-Release Checklist

### 1. Code Quality
- [ ] All code changes tested locally
- [ ] No console errors in production mode
- [ ] PHP compatibility checked (7.4+)
- [ ] WordPress compatibility verified (5.0+)

### 2. Version Consistency
- [ ] Plugin header version updated
- [ ] CUFT_VERSION constant updated
- [ ] readme.txt stable tag updated
- [ ] CHANGELOG.md entry added with all changes

### 3. Testing
- [ ] Form submission tracking works (all supported frameworks)
- [ ] UTM parameter capture works
- [ ] Click ID tracking works
- [ ] Generate lead fires correctly (email + phone + click_id)
- [ ] Admin panel functions properly
- [ ] No PHP warnings or errors

## Release Process

### Method 1: Using Version Bump Script (Recommended)

1. **Update version numbers:**
   ```bash
   ./bump-version.sh 3.8.5
   ```

2. **Edit CHANGELOG.md** to replace the placeholder with actual changes

3. **Commit changes:**
   ```bash
   git add -A
   git commit -m "Release version 3.8.5"
   ```

4. **Create and push tag:**
   ```bash
   git tag v3.8.5
   git push origin master
   git push origin v3.8.5
   ```

5. **GitHub Actions will automatically:**
   - Build the plugin zip (excluding dev files)
   - Create a GitHub release
   - Attach the plugin zip as an asset

### Method 2: Manual Process

1. **Update all version references:**
   - `choice-universal-form-tracker.php` header
   - `CUFT_VERSION` constant
   - `readme.txt` stable tag
   - Add entry to `CHANGELOG.md`

2. **Create plugin zip:**
   ```bash
   # CRITICAL: Always name zip 'choice-uft-v{VERSION}.zip' (with version number)
   # but ensure it extracts to choice-uft/ folder (without version)
   cd /path/to/parent/directory
   zip -r choice-uft-v[VERSION].zip choice-uft/ \
     -x "choice-uft/.git/*" \
     -x "choice-uft/.github/*" \
     -x "choice-uft/.gitignore" \
     -x "choice-uft/node_modules/*" \
     -x "choice-uft/.env" \
     -x "choice-uft/*.zip" \
     -x "choice-uft/tests/*" \
     -x "choice-uft/*.log"
   ```

3. **Create GitHub release:**
   ```bash
   gh release create v3.8.5 \
     --title "Version 3.8.5" \
     --notes "See CHANGELOG.md for details"
   ```

4. **Upload plugin zip:**
   ```bash
   gh release upload v3.8.5 choice-uft-v3.8.5.zip --clobber
   ```

## Post-Release Verification

### 1. GitHub Verification
- [ ] Release appears on GitHub releases page
- [ ] Plugin zip is attached to release
- [ ] Download link works

### 2. WordPress Auto-Update Testing

**Test on staging site first:**

1. Install previous version (e.g., 3.8.3)
2. Wait 12 hours for WordPress update check (or force check)
3. Verify update notification appears
4. Test update process
5. Confirm new version installed correctly

**Force update check (in WordPress admin):**
```php
// Add temporarily to functions.php or use a code snippet plugin
add_action('init', function() {
    if (is_admin() && current_user_can('manage_options')) {
        delete_transient('cuft_github_version');
        delete_transient('cuft_github_changelog');
        wp_update_plugins();
    }
});
```

### 3. Client Site Monitoring
- [ ] Check 2-3 client sites after 24 hours
- [ ] Verify they received update notification
- [ ] Confirm successful updates
- [ ] Check for any error reports

## Rollback Process

If issues are discovered after release:

1. **Delete the problematic release:**
   ```bash
   gh release delete v3.8.5 --yes
   git push --delete origin v3.8.5
   ```

2. **Create a fixed version:**
   - Fix the issues
   - Bump to next version (e.g., 3.8.6)
   - Follow release process again

3. **Notify affected sites** (if critical):
   - Email clients about the issue
   - Provide manual update instructions if needed

## Troubleshooting Common Issues

### Update Not Appearing
- **Cause:** WordPress transient cache
- **Solution:** Wait 12 hours or clear transients

### "Download failed" Error
- **Cause:** GitHub API rate limiting or network issues
- **Solution:** Try again later or increase timeout in updater

### Wrong Files in Update
- **Cause:** Archive URL used instead of release asset
- **Solution:** Ensure release has attached zip file

### Version Mismatch
- **Cause:** Inconsistent version numbers
- **Solution:** Use bump-version.sh script

## Important Notes

1. **Always test on staging first** - Never push directly to production
2. **Use semantic versioning** - MAJOR.MINOR.PATCH
3. **Document all changes** - Keep CHANGELOG.md updated
4. **Monitor after release** - Check for issues in first 24 hours
5. **Keep release assets** - Don't delete old release zips immediately

## GitHub Actions Workflow

The `.github/workflows/release.yml` workflow automatically:
1. Triggers on version tags (v*.*.*)
2. Creates a clean plugin directory
3. Builds the distribution zip
4. Creates GitHub release with changelog
5. Attaches zip as release asset

This ensures consistent, reliable releases every time.