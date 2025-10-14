# CLAUDE.md - Development Guidelines for Choice Universal Form Tracker

## CRITICAL: Always Reference Specifications First

### Before ANY Code Changes
1. **MANDATORY**: Read and understand relevant specifications:
   - [.specify/memory/constitution.md](.specify/memory/constitution.md) - Core principles and standards
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

### Design Artifacts
- [PostMessage Protocol](specs/003-testing-dashboard-form/contracts/postmessage-protocol.md)
- [Quick Start Guide](specs/003-testing-dashboard-form/quickstart.md)
- [Implementation Tasks](specs/003-testing-dashboard-form/tasks.md)

### Troubleshooting Tips

**AJAX Endpoint Issues**
- Ensure `cuftConfig` JavaScript object is available globally
- Check browser console for nonce errors
- Verify AJAX URL points to `/wp-admin/admin-ajax.php`
- Flush rewrite rules: `wp rewrite flush` (for custom webhook URL)

**Events Not Recording**
- Verify `click_id` is present in URL parameters or sessionStorage
- Check `cuftConfig.debug` for detailed logging
- Ensure migration has been run (`CUFT_DB_Migration::run_migrations()`)
- Verify `events` column exists: `SHOW COLUMNS FROM wp_cuft_click_tracking`

**Admin UI Not Showing Events**
- Check if `events` column contains valid JSON: `SELECT click_id, events FROM wp_cuft_click_tracking`
- Verify `CUFT_Click_Tracker::get_events()` returns array
- Clear browser cache if events column appears empty

**Performance Issues**
- JSON operations target: <12ms for add_event, <5ms for get_events
- AJAX response time target: <100ms P95
- Run performance tests: `php tests/performance/test-json-performance.php`
- Monitor using `EXPLAIN` for query optimization

### Design Artifacts
- [research.md](specs/migrations/click-tracking-events/research.md) - Technical research findings
- [data-model.md](specs/migrations/click-tracking-events/data-model.md) - Database schema details
- [contracts/ajax-endpoint.md](specs/migrations/click-tracking-events/contracts/ajax-endpoint.md) - AJAX API contract
- [contracts/webhook-api.md](specs/migrations/click-tracking-events/contracts/webhook-api.md) - Webhook compatibility
- [quickstart.md](specs/migrations/click-tracking-events/quickstart.md) - Testing guide

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

