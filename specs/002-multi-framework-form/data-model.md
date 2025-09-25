# Data Model: Multi-framework Form Tracking

## Core Entities

### 1. FormSubmission
Primary entity representing a completed form submission event.

```javascript
{
  // Required fields (all frameworks)
  event: "form_submit",              // Event type (constant)
  form_type: string,                  // Framework identifier
  form_id: string,                    // Unique form identifier
  form_name: string,                  // Human-readable name
  submitted_at: string,               // ISO 8601 timestamp
  cuft_tracked: true,                 // Constitutional marker
  cuft_source: string,                // Framework source identifier

  // Optional fields (when available)
  user_email: string,                 // Validated email address
  user_phone: string,                 // Sanitized phone number

  // Tracking parameters (when present)
  utm_source: string,
  utm_medium: string,
  utm_campaign: string,
  utm_term: string,
  utm_content: string,

  // Click IDs (when present)
  click_id: string,                   // Generic click ID
  gclid: string,                      // Google Ads
  gbraid: string,                     // Google iOS
  wbraid: string,                     // Google Web-to-App
  fbclid: string,                     // Facebook/Meta
  msclkid: string,                    // Microsoft/Bing
  ttclid: string,                     // TikTok
  li_fat_id: string,                  // LinkedIn
  twclid: string,                     // Twitter/X
  snap_click_id: string,              // Snapchat
  pclid: string,                      // Pinterest

  // GA4 standard parameters
  page_location: string,              // Current URL
  page_referrer: string,              // Referrer URL
  page_title: string,                 // Page title
  language: string,                   // Browser language
  screen_resolution: string,          // Screen dimensions
  engagement_time_msec: number        // Time on page
}
```

### 2. GenerateLeadEvent
Conversion event with strict firing criteria.

```javascript
{
  // Required fields
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_tracked: true,
  cuft_source: string + "_lead",      // e.g., "elementor_pro_lead"

  // Inherited from FormSubmission
  // All FormSubmission fields are included
  // Plus strict requirements:
  // - MUST have valid user_email
  // - MUST have valid user_phone
  // - MUST have at least one click_id
}
```

### 3. TrackingParameters
Session-persistent tracking data.

```javascript
{
  // UTM Parameters
  utm_source: string | null,
  utm_medium: string | null,
  utm_campaign: string | null,
  utm_term: string | null,
  utm_content: string | null,

  // Click IDs
  click_ids: {
    click_id: string | null,
    gclid: string | null,
    gbraid: string | null,
    wbraid: string | null,
    fbclid: string | null,
    msclkid: string | null,
    ttclid: string | null,
    li_fat_id: string | null,
    twclid: string | null,
    snap_click_id: string | null,
    pclid: string | null
  },

  // Metadata
  captured_at: number,                // Timestamp
  source: "url" | "session" | "cookie", // Data source
  expires_at: number                  // Expiration timestamp
}
```

### 4. FrameworkConfig
Per-framework configuration and state.

```javascript
{
  // Framework identification
  framework_id: "elementor" | "cf7" | "ninja" | "gravity" | "avada",
  framework_name: string,              // Display name

  // Detection configuration
  detection: {
    css_classes: string[],            // CSS selectors
    dom_attributes: string[],         // Data attributes
    parent_selectors: string[]        // Container selectors
  },

  // Event configuration
  events: {
    primary_event: string,            // Main event to listen for
    fallback_events: string[],        // Alternative events
    success_indicators: string[]      // Success detection selectors
  },

  // Field mapping
  field_mapping: {
    email_selectors: string[],
    phone_selectors: string[],
    custom_mappings: object           // User-defined mappings
  },

  // Runtime configuration
  settings: {
    console_logging: boolean,
    generate_lead_enabled: boolean,
    multi_step_tracking: boolean,
    retry_attempts: number,
    timeout_ms: number
  }
}
```

### 5. FeatureFlags
Migration and rollout control.

```javascript
{
  // Phase control flags
  flags: {
    silentFrameworkDetection: boolean,  // Phase 1 - ENABLED
    strictGenerateLeadRules: boolean,   // Constitutional - ENABLED
    enhancedErrorHandling: boolean,     // Phase 2 - PENDING
    performanceOptimizations: boolean,  // Phase 2 - PENDING
    consolidatedUtilities: boolean,     // Phase 3 - PENDING
  },

  // Rollout configuration
  rollout: {
    percentage: number,                // 0-100
    user_id: string,                   // Stable user identifier
    enabled_features: string[],        // Active features
    rollback_triggered: boolean        // Emergency state
  },

  // Monitoring
  metrics: {
    submissions: number,
    successes: number,
    errors: object[],
    performance_samples: number[],
    start_time: number
  },

  // Version tracking
  version: {
    migration_version: string,         // e.g., "4.0.0-phase2"
    migration_phase: number,           // Current phase (1-5)
    last_updated: string               // ISO 8601
  }
}
```

## State Management

### 1. Form State
Tracking attributes applied to DOM elements.

```javascript
// Pre-submission state
form.setAttribute('data-cuft-tracking', 'pending');
form.setAttribute('data-cuft-email', email);
form.setAttribute('data-cuft-phone', phone);
form.setAttribute('data-cuft-capture-time', timestamp);

// Post-submission state
form.setAttribute('data-cuft-processed', 'true');
form.removeAttribute('data-cuft-tracking');

// Multi-step state
form.setAttribute('data-cuft-step', currentStep);
form.setAttribute('data-cuft-total-steps', totalSteps);
```

### 2. Observer State
MutationObserver lifecycle management.

```javascript
{
  observer_id: string,                // Unique identifier
  target_element: Element,            // Observed element
  start_time: number,                 // Creation timestamp
  timeout_ms: number,                 // Auto-disconnect timeout
  disconnected: boolean,               // Cleanup status
  observations: number                 // Event count
}
```

### 3. Session State
Cross-page tracking persistence.

```javascript
// SessionStorage structure
{
  'cuft_tracking_params': TrackingParameters,
  'cuft_form_data': {
    [form_id]: {
      started_at: number,
      field_values: object,
      abandoned: boolean
    }
  },
  'cuft_user_id': string,            // Stable identifier
  'cuft_session_start': number       // Session timestamp
}
```

## Validation Rules

### Email Validation
```javascript
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const isValid = emailPattern.test(email);
```

### Phone Validation
```javascript
// Accept any non-empty value after cleaning
const cleaned = phone.replace(/[^\d+]/g, '');
const isValid = cleaned.length > 0;
```

### Click ID Validation
```javascript
const hasClickId = !!(
  data.click_id || data.gclid || data.fbclid ||
  data.msclkid || data.ttclid || data.li_fat_id ||
  data.twclid || data.snap_click_id || data.pclid ||
  data.gbraid || data.wbraid
);
```

## Data Flow

### 1. Capture Flow
```
User Input → Framework Event → Field Extraction → Validation → Storage
```

### 2. Tracking Flow
```
Success Detection → State Check → Data Assembly → DataLayer Push → Cleanup
```

### 3. Persistence Flow
```
URL Parameters → Session Storage → Cookie Storage → Empty Fallback
```

## Framework-Specific Mappings

### form_type Values
- `"elementor"` → Elementor Pro Forms
- `"cf7"` → Contact Form 7
- `"ninja"` → Ninja Forms
- `"gravity"` → Gravity Forms
- `"avada"` → Avada/Fusion Forms

### cuft_source Values
- `"elementor_pro"` / `"elementor_pro_lead"`
- `"contact_form_7"` / `"contact_form_7_lead"`
- `"ninja_forms"` / `"ninja_forms_lead"`
- `"gravity_forms"` / `"gravity_forms_lead"`
- `"avada_forms"` / `"avada_forms_lead"`

## Privacy Considerations

### Data Minimization
- Only collect explicitly provided user data
- No automatic PII extraction
- No tracking without successful submission

### Data Retention
- Session data expires with browser session
- Cookie data expires after 30 days
- No server-side storage by plugin

### User Control
- Respects browser privacy settings
- Works with ad blockers (graceful degradation)
- No third-party data sharing by plugin