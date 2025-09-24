# CLAUDE.md - Development Guidelines for Choice Universal Form Tracker

## Core Development Principles

### JavaScript-First Approach

**Principle: Maximize compatibility by preferring pure JavaScript over jQuery**

1. **Primary Implementation**: Always implement functionality using pure vanilla JavaScript first
2. **jQuery as Fallback**: Add jQuery implementations as a secondary option when available
3. **Multiple Fallback Methods**: Implement multiple detection and tracking methods to ensure maximum compatibility

#### Implementation Strategy
```javascript
// 1. Try native JavaScript first
if (window.CustomEvent) {
  document.addEventListener('submit_success', handler);
}

// 2. Add jQuery listener if available
if (window.jQuery) {
  jQuery(document).on('submit_success', handler);
}

// 3. Add additional fallback methods
// - MutationObserver for DOM changes
// - Ajax interceptors
// - Form submit handlers
```

### Event Tracking Robustness

The plugin implements multiple layers of event detection:

1. **Native JavaScript Events** (Elementor 3.5+)
2. **jQuery Events** (older Elementor versions)
3. **MutationObserver** (watches for success messages)
4. **Ajax Interceptors** (fetch and XMLHttpRequest)
5. **jQuery.ajaxComplete** (when jQuery is available)

This ensures form submissions are tracked regardless of:
- Elementor version (Pro or Free)
- jQuery availability
- JavaScript framework conflicts
- Custom implementations

### Data Retrieval Fallback Chain

**Graceful degradation for tracking data retrieval:**

```
URL Parameters → SessionStorage → Cookies → Empty Object
```

Each source is wrapped in try-catch blocks to ensure failures don't break the tracking.

## Elementor Forms Implementation

### Event Handling

Elementor forms fire a `submit_success` event after successful submission. Our implementation:

1. **Listens for multiple event types**:
   - `submit_success` (native and jQuery)
   - `elementor/frontend/form_success`
   - `elementor/popup/hide`

2. **Form Detection Methods**:
   - Event target traversal
   - Pending tracking attribute
   - Visible form detection
   - Recent interaction detection

### Required Fields for Events

#### form_submit Event
Fires on every successful form submission with:
- Form ID and name
- UTM parameters (if available)
- Click IDs (if available)
- User email and phone (if provided)
- GA4 standard parameters

#### generate_lead Event
Only fires when ALL three conditions are met:
1. **Click ID** present (click_id, gclid, fbclid, or any supported click ID)
2. **Email** field has a value
3. **Phone** field has a value

### Click ID Support

The following click IDs are tracked:
- `click_id` (generic)
- `gclid` (Google Ads)
- `gbraid` (Google iOS)
- `wbraid` (Google Web-to-App)
- `fbclid` (Facebook/Meta)
- `msclkid` (Microsoft/Bing)
- `ttclid` (TikTok)
- `li_fat_id` (LinkedIn)
- `twclid` (Twitter/X)
- `snap_click_id` (Snapchat)
- `pclid` (Pinterest)

## Testing Guidelines

### Quick Testing Checklist

**Essential verifications before deployment:**

- [ ] Form submissions trigger `form_submit` event
- [ ] UTM parameters are captured from all sources
- [ ] Click IDs are properly tracked
- [ ] Generate lead fires only with email + phone + click_id
- [ ] Fallback chain works (URL → Session → Cookie)
- [ ] Works without jQuery
- [ ] Works with jQuery
- [ ] Console has no errors in production mode
- [ ] Events fire only once per submission

### Testing Documentation

For comprehensive testing procedures, test files, and debugging guides, see:
- **[docs/TESTING.md](docs/TESTING.md)** - Full testing documentation
- Test scenarios and manual testing procedures
- Console commands for verification
- Performance testing guidelines
- Debugging guide and common issues

## Debug Mode

Enable debug logging by setting:
```javascript
window.cuftElementor = {
  console_logging: true,
  generate_lead_enabled: true
};
```

This will output detailed tracking information to the console.

## Browser Compatibility

The plugin is designed to work with:
- Modern browsers (Chrome, Firefox, Safari, Edge)
- Elementor 3.0+ (optimized for 3.5+)
- Elementor Pro 3.0+ (optimized for 3.7+)
- With or without jQuery
- WordPress 5.0+
- PHP 7.0+

## Release Creation Process

### When Creating a New Release

**IMPORTANT: Always create and upload a zip file for WordPress installations**

1. **Update Version Numbers**:
   - Update version in `choice-universal-form-tracker.php` header comment
   - Update `CUFT_VERSION` constant in the same file
   - Update `CHANGELOG.md` with new version entry

2. **Create Installation Zip**:
   ```bash
   # Create zip file excluding development files
   # IMPORTANT: Always name the zip 'choice-uft.zip' (WITHOUT version number)
   # This ensures WordPress extracts to /wp-content/plugins/choice-uft/
   cd choice-uft
   zip -r ../choice-uft.zip . \
     -x ".git/*" \
     -x ".github/*" \
     -x ".gitignore" \
     -x "node_modules/*" \
     -x ".env" \
     -x "*.zip"
   ```

3. **Create GitHub Release**:
   ```bash
   # Create release with comprehensive notes
   gh release create v[VERSION] --title "Version [VERSION]" --notes "[Release notes]"

   # Upload the zip file to release assets
   gh release upload v[VERSION] choice-uft.zip --clobber
   ```

4. **Verify Release**:
   - Check that zip file is attached to release assets
   - Verify download link works
   - Ensure WordPress auto-updater can detect the new version

### Example Release Commands
```bash
# For version 3.8.2
cd /home/r11/dev/choice-uft
zip -r ../choice-uft.zip . -x ".git/*" ".github/*" ".gitignore" "node_modules/*" ".env" "*.zip"
gh release create v3.8.2 --title "Version 3.8.2" --notes "Release notes here"
gh release upload v3.8.2 ../choice-uft.zip --clobber
```

## Important Notes

1. **Never depend solely on jQuery** - It may not be available
2. **Always provide fallbacks** - Multiple detection methods ensure reliability
3. **Test without jQuery** - Verify pure JavaScript paths work
4. **Handle errors gracefully** - Use try-catch blocks liberally
5. **Log in debug mode only** - Minimize console output in production
6. **Always create release zip files** - Required for WordPress installations and auto-updates
7. **CRITICAL: Always name zip files 'choice-uft.zip'** - NEVER include version numbers in the filename, as this causes WordPress to extract to wrong directory (e.g., choice-uft-v3.9.3 instead of choice-uft)