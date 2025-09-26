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
- [ ] All relevant specifications reviewed and followed
- [ ] Constitutional principles compliance verified
- [ ] Framework-specific requirements met
- [ ] Silent exit implemented for non-relevant frameworks
- [ ] DataLayer events use snake_case naming
- [ ] Required fields (cuft_tracked, cuft_source) included
- [ ] Error handling implemented with fallbacks
- [ ] Performance requirements considered
- [ ] Security requirements met (input sanitization)

## Phase 5: AI Agent Integration - COMPLETED ✅

The AI development workflow has been successfully implemented with:

### ✅ Completed Setup
1. **AI Environment Configuration**: CLAUDE.md updated with mandatory specification references
2. **Code Pattern Implementation**: All framework files implement mandatory patterns (silent exit, framework detection, etc.)
3. **Pre-Commit Validation**: Automated pre-commit hook validates constitutional compliance
4. **Template Integration**: Implementation and migration plan templates referenced in workflow
5. **CI/CD Pipeline**: Constitutional compliance validation in GitHub Actions workflow

### ✅ Validation Systems
- **Pre-commit Hook**: `/home/r11/dev/choice-uft/.git/hooks/pre-commit` - Validates code changes before commit
- **GitHub Actions**: `.github/workflows/constitutional-compliance.yml` - CI/CD validation pipeline
- **Review Checklist**: `.specify/memory/review-checklist.md` - Comprehensive code review requirements
- **AI Guidelines**: `.specify/memory/agents.md` - Detailed AI development instructions

### ✅ Templates Available
- **New Features**: `.specify/templates/implementation-plan-template.md`
- **Code Updates**: `.specify/templates/migration-plan-template.md`

All future AI-assisted development will now automatically reference specifications first and maintain constitutional compliance.

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

### DataLayer Parameter Naming Convention

**All dataLayer parameters use consistent snake_case naming:**

- ✅ `form_type` (not `formType`)
- ✅ `form_id` (not `formId`)
- ✅ `form_name` (not `formName`)
- ✅ `user_email` (not `userEmail`)
- ✅ `user_phone` (not `userPhone`)
- ✅ `submitted_at` (not `submittedAt`)
- ✅ `cuft_tracked: true`
- ✅ `cuft_source: "framework_name"`

This ensures GTM compatibility and consistent data across all frameworks.

## Multi-Framework Implementation

### Supported Form Frameworks

The plugin supports multiple form frameworks with specialized tracking for each:

1. **Elementor Pro Forms** (primary focus)
2. **Contact Form 7**
3. **Ninja Forms**
4. **Gravity Forms**
5. **Avada/Fusion Forms**

Each framework has dedicated tracking scripts that:
- Listen for framework-specific events
- Extract form data using framework conventions
- Apply consistent dataLayer parameter naming
- Handle framework-specific success states

### Cross-Framework Compatibility

**Multiple frameworks can coexist on the same page without interference:**

- Each framework script only processes its own forms
- Non-relevant forms are ignored silently (no console noise)
- Framework detection happens before any logging
- Scripts exit early for non-matching forms

### Event Handling Strategy

Different frameworks use different event approaches:

**Event-Based Frameworks:**
- **Elementor**: Listens for `submit_success` events
- **Contact Form 7**: Listens for `wpcf7mailsent` events

**Submit-Based Frameworks:**
- **Avada**: Listens for `submit` events with `.fusion-form` detection
- **Ninja Forms**: Listens for `submit` events with `.nf-form-cont` detection
- **Gravity Forms**: Listens for `submit` events with `.gform_form` detection

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

## Test Forms Implementation

### Two Test Form Systems

The plugin provides two separate test form implementations:

1. **Admin Page Quick Tests** (`/wp-admin/options-general.php?page=choice-universal-form-tracker`)
   - Quick test buttons integrated directly into Framework Detection cards
   - One-click testing with customizable admin email
   - Inline results showing dataLayer events fired
   - Uses production tracking flow with real framework events
   - Copy-to-clipboard functionality for event data
   - No page navigation required

2. **Dedicated Test Page** (`[cuft_test_forms]` shortcode)
   - Full form interfaces for each framework
   - Advanced testing controls (enable/disable requirements)
   - Real-time dataLayer event monitoring
   - Framework-specific UI styling

### Test Form Architecture

**Both test systems use the same production tracking flow:**

```javascript
// 1. Store tracking data in sessionStorage
common.prepareTrackingDataForProduction('elementor', form_id, formElement);

// 2. Set form data attributes for production code
formElement.setAttribute('data-cuft-email', email);
formElement.setAttribute('data-cuft-phone', phone);
formElement.setAttribute('data-cuft-tracking', 'pending');

// 3. Fire framework-specific event
const event = new CustomEvent('submit_success', { ... });
formElement.dispatchEvent(event);

// 4. Production code handles the rest
// - Adds cuft_tracked: true
// - Adds cuft_source: "elementor_pro"
// - Pushes to dataLayer with correct field names
// - Fires generate_lead if requirements met
```

### Admin Page Quick Tests

**Location**: Framework Detection cards in WordPress admin settings page

**Features**:
- **One-Click Testing**: Test button for each detected framework
- **Customizable Email**: Admin can specify email for test submissions (defaults to site admin email)
- **Pre-set Test Values**: Uses standard test data:
  - Email: From admin input field
  - Phone: "555-TEST-1234"
  - Form Name: "Admin Quick Test"
  - UTM Campaign: "test_campaign_{framework}"
  - Click IDs: Generated test values to trigger generate_lead

**Results Display**:
- Inline results showing dataLayer events fired
- Status indicators for form_submit and generate_lead events
- JSON event data with copy-to-clipboard functionality
- Auto-hide after 10 seconds or manual collapse

**Framework Events Fired**:
- **Elementor**: `submit_success`, `elementor/frontend/form_success`
- **Contact Form 7**: `wpcf7mailsent`
- **Avada/Fusion**: `submit` event with success state
- **Ninja Forms**: `submit` event with `nfFormSubmitResponse`
- **Gravity Forms**: `submit` event with `gform_confirmation_loaded`

### Legacy Test Forms Removal

**IMPORTANT:** The old legacy test forms script (`assets/cuft-test-forms.js`) has been **completely removed** because it:
- Bypassed production tracking code
- Used wrong field names (`form_framework` instead of `form_type`)
- Added unwanted fields (`test_submission: true`)
- Missing required fields (`cuft_tracked`, `cuft_source`)
- Caused GTM tags not to fire

## Testing Guidelines

### Quick Testing Checklist

**Essential verifications before deployment:**

- [ ] Form submissions trigger `form_submit` event with `cuft_tracked: true`
- [ ] Events use snake_case field names (`form_type`, `user_email`, etc.)
- [ ] UTM parameters are captured from all sources
- [ ] Click IDs are properly tracked
- [ ] Generate lead fires only with email + phone + click_id
- [ ] Fallback chain works (URL → Session → Cookie)
- [ ] Works without jQuery
- [ ] Works with jQuery
- [ ] Console has no errors in production mode
- [ ] Events fire only once per submission
- [ ] Multiple frameworks can coexist without console noise

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

### Expected DataLayer Event Format

**Standard form_submit Event:**
```javascript
{
  event: "form_submit",
  form_type: "elementor",               // Framework identifier
  form_id: "elementor-widget-7a2c4f9",  // Form's unique ID
  form_name: "Contact Form",            // Human-readable form name
  user_email: "user@example.com",       // Email field value
  user_phone: "123-456-7890",           // Phone field value
  submitted_at: "2025-01-01T12:00:00Z", // ISO timestamp
  cuft_tracked: true,                   // Added by production code
  cuft_source: "elementor_pro",         // Added by production code
  click_id: "abc123",                   // If present
  gclid: "xyz789",                      // If present
  utm_source: "google",                 // If present
  utm_medium: "cpc",                    // If present
  utm_campaign: "summer_sale",          // If present
  utm_term: "contact_form",             // If present
  utm_content: "sidebar"                // If present
}
```

**generate_lead Event** (only when email + phone + click_id present):
```javascript
{
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_tracked: true,
  cuft_source: "elementor_pro_lead",
  // All form_submit fields also included
}
```

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
   # CRITICAL: WordPress Release ZIP Naming Convention:
   # - ZIP FILENAME: 'choice-uft-v3.xx.xx.zip' (includes version for GitHub releases)
   # - FOLDER INSIDE: 'choice-uft/' (NO version number - required for WordPress auto-updater)
   # This ensures WordPress extracts to /wp-content/plugins/choice-uft/ correctly

   cd /path/to/parent/directory
   zip -r choice-uft-v[VERSION].zip choice-uft/ \
     -x "choice-uft/.git/*" \
     -x "choice-uft/.github/*" \
     -x "choice-uft/.gitignore" \
     -x "choice-uft/node_modules/*" \
     -x "choice-uft/.env" \
     -x "choice-uft/*.zip"
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
# For version 3.10.1 (UPDATED PROCESS)
cd /home/r11/dev
zip -r choice-uft-v3.10.1.zip choice-uft/ \
  -x "choice-uft/.git/*" "choice-uft/.github/*" "choice-uft/.gitignore" \
  -x "choice-uft/node_modules/*" "choice-uft/.env" "choice-uft/*.zip"

gh release create v3.10.1 --title "Version 3.10.1" --notes "Release notes here"
gh release upload v3.10.1 choice-uft-v3.10.1.zip --clobber
```

## Important Notes

1. **Never depend solely on jQuery** - It may not be available
2. **Always provide fallbacks** - Multiple detection methods ensure reliability
3. **Test without jQuery** - Verify pure JavaScript paths work
4. **Handle errors gracefully** - Use try-catch blocks liberally
5. **Log in debug mode only** - Minimize console output in production
6. **Always create release zip files** - Required for WordPress installations and auto-updates
7. **CRITICAL: WordPress ZIP Naming Convention**:
   - **ZIP FILENAME**: `choice-uft-v3.xx.xx.zip` (includes version for GitHub releases and downloads)
   - **FOLDER INSIDE**: `choice-uft/` (NO version number - required for WordPress auto-updater)
   - This ensures WordPress extracts to `/wp-content/plugins/choice-uft/` correctly, not `choice-uft-v3.xx.xx/`

## Troubleshooting

### Common Issues and Fixes

#### GTM Tags Not Firing

**Problem**: Google Tag Manager tags don't fire when forms are submitted.

**Causes & Solutions**:
1. **Wrong field names**: Ensure events use `form_type` (not `form_framework`)
2. **Missing cuft_tracked**: Verify events have `cuft_tracked: true`
3. **Missing cuft_source**: Verify events have `cuft_source: "framework_name"`
4. **Legacy test forms**: Ensure old `cuft-test-forms.js` script is removed

#### Cross-Framework Console Noise

**Problem**: Multiple framework scripts logging messages for non-relevant forms.

**Fixed**: Framework detection now happens before logging. Scripts exit silently for non-matching forms.

#### Test Forms Not Working

**Problem**: Test forms show wrong field names or missing required fields.

**Fixed**: Both admin and test page forms now use production tracking flow and fire real framework events.

#### Multiple Frameworks Conflicting

**Problem**: Different form frameworks interfere with each other on the same page.

**Fixed**: Each framework script only processes its own forms and ignores others silently.

### Debug Commands

**Check dataLayer events in browser console:**
```javascript
// View all dataLayer events
console.log(window.dataLayer);

// Monitor new events
window.dataLayer.push = function(event) {
  console.log('dataLayer event:', event);
  Array.prototype.push.call(window.dataLayer, event);
};

// Check for CUFT events specifically
window.dataLayer.filter(e => e.cuft_tracked);
```

**Enable framework-specific debugging:**
```javascript
// Elementor debugging
window.cuftElementor = {
  console_logging: true,
  generate_lead_enabled: true
};

// Avada debugging
window.cuftAvada = {
  console_logging: true
};

// Global UTM debugging
window.cuftUtm = {
  console_logging: true
};
```
