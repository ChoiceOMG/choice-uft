# DataLayer Events Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized format for all dataLayer events pushed to Google Tag Manager (GTM) by the Choice Universal Form Tracker. All events MUST conform to these specifications to ensure consistent tracking and tag firing across all form frameworks.

---

## Core Event Types

### 1. form_submit Event

**Purpose**: Tracks every successful form submission regardless of field completion status.

**Firing Conditions**: MUST fire for every successful form submission, no exceptions.

**Required Fields**:
```javascript
{
  event: "form_submit",                    // STRING: Event identifier (REQUIRED)
  cuft_tracked: true,                      // BOOLEAN: Plugin identification (REQUIRED)
  cuft_source: "framework_name",           // STRING: Source framework identifier (REQUIRED)
  form_type: "framework_name",             // STRING: Framework identifier (REQUIRED)
  form_id: "unique_form_identifier",       // STRING: Form's unique ID (REQUIRED)
  submitted_at: "2025-01-01T12:00:00Z"     // STRING: ISO 8601 timestamp (REQUIRED)
}
```

**Optional Fields**:
```javascript
{
  form_name: "Contact Form",               // STRING: Human-readable form name
  user_email: "user@example.com",          // STRING: Email field value
  user_phone: "123-456-7890",              // STRING: Phone field value
  // UTM Parameters (if available)
  utm_source: "google",                    // STRING: Traffic source
  utm_medium: "cpc",                       // STRING: Marketing medium
  utm_campaign: "summer_sale",             // STRING: Campaign name
  utm_term: "contact_form",                // STRING: Campaign term
  utm_content: "sidebar",                  // STRING: Campaign content
  // Click IDs (if available)
  click_id: "abc123",                      // STRING: Generic click ID
  gclid: "xyz789",                         // STRING: Google Ads click ID
  gbraid: "def456",                        // STRING: Google iOS click ID
  wbraid: "ghi789",                        // STRING: Google Web-to-App click ID
  fbclid: "jkl012",                        // STRING: Facebook/Meta click ID
  msclkid: "mno345",                       // STRING: Microsoft/Bing click ID
  ttclid: "pqr678",                        // STRING: TikTok click ID
  li_fat_id: "stu901",                     // STRING: LinkedIn click ID
  twclid: "vwx234",                        // STRING: Twitter/X click ID
  snap_click_id: "yza567",                 // STRING: Snapchat click ID
  pclid: "bcd890"                          // STRING: Pinterest click ID
}
```

### 2. generate_lead Event

**Purpose**: Tracks qualified lead generation when specific conditions are met.

**Firing Conditions**: MUST fire only when ALL three conditions are met:
1. Valid email address present
2. Valid phone number present
3. At least one click ID present

**Required Fields**:
```javascript
{
  event: "generate_lead",                  // STRING: Event identifier (REQUIRED)
  cuft_tracked: true,                      // BOOLEAN: Plugin identification (REQUIRED)
  cuft_source: "framework_name_lead",      // STRING: Lead-specific source (REQUIRED)
  currency: "USD",                         // STRING: Currency code (REQUIRED)
  value: 0,                               // NUMBER: Lead value (REQUIRED)
  // All form_submit fields are also included
  form_type: "framework_name",             // STRING: Framework identifier (REQUIRED)
  form_id: "unique_form_identifier",       // STRING: Form's unique ID (REQUIRED)
  user_email: "user@example.com",          // STRING: Email (REQUIRED for lead)
  user_phone: "123-456-7890",              // STRING: Phone (REQUIRED for lead)
  submitted_at: "2025-01-01T12:00:00Z"     // STRING: ISO timestamp (REQUIRED)
}
```

**Click ID Requirement**: At least one of the following MUST be present:
- `click_id`, `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`, `ttclid`, `li_fat_id`, `twclid`, `snap_click_id`, `pclid`

---

## Field Specifications

### Data Type Requirements

| Field | Type | Format | Validation |
|-------|------|--------|------------|
| `event` | String | Lowercase with underscores | Must be "form_submit" or "generate_lead" |
| `cuft_tracked` | Boolean | true/false | Must be `true` |
| `cuft_source` | String | Lowercase with underscores | Must match pattern: `framework_name` or `framework_name_lead` |
| `form_type` | String | Lowercase with underscores | Must match known framework names |
| `form_id` | String | Any valid string | Must be unique within context |
| `form_name` | String | Any valid string | Human-readable identifier |
| `user_email` | String | Valid email format | Must pass email validation regex |
| `user_phone` | String | Any phone format | No specific format required |
| `submitted_at` | String | ISO 8601 format | Must be valid datetime string |
| `utm_*` | String | Any valid string | No special validation |
| `*clid` | String | Alphanumeric with allowed special chars | Must match click ID patterns |
| `currency` | String | 3-letter ISO code | Typically "USD" |
| `value` | Number | Numeric value | Typically 0 for leads |

### Framework Identifiers

**Standardized framework names for `form_type` and `cuft_source`**:

| Framework | form_type | cuft_source (form) | cuft_source (lead) |
|-----------|-----------|-------------------|-------------------|
| Elementor Pro | `elementor` | `elementor_pro` | `elementor_pro_lead` |
| Contact Form 7 | `cf7` | `contact_form_7` | `contact_form_7_lead` |
| Ninja Forms | `ninja` | `ninja_forms` | `ninja_forms_lead` |
| Gravity Forms | `gravity` | `gravity_forms` | `gravity_forms_lead` |
| Avada Forms | `avada` | `avada_forms` | `avada_forms_lead` |

### Email Validation

**Email fields MUST be validated using this pattern**:
```javascript
const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
```

**Invalid emails MUST be**:
- Excluded from events (field not included)
- Never set to empty string or null
- Not cause event firing to fail

### Phone Validation

**Phone fields**:
- Accept any format (no standardization required)
- Include only if field contains value
- Strip common formatting characters for consistency: `()`, `-`, spaces
- Preserve international format indicators: `+`

### Timestamp Format

**All timestamps MUST use ISO 8601 format**:
```javascript
const timestamp = new Date().toISOString();
// Output: "2025-01-01T12:00:00.000Z"
```

---

## Event Deduplication

### Deduplication Rules

**Form-Level Deduplication**: Each form submission MUST generate exactly one `form_submit` event.

**Lead-Level Deduplication**: Each qualifying form submission MUST generate exactly one `generate_lead` event.

**Implementation**: Use form state tracking to prevent duplicate events:
```javascript
// Mark form as processed
formElement.setAttribute('data-cuft-processed', 'true');

// Check before processing
if (formElement.getAttribute('data-cuft-processed')) {
  return; // Skip duplicate processing
}
```

### Multi-Step Form Handling

**Multi-step forms**: Track only the final successful submission step.

**Partial submissions**: Do NOT fire events for incomplete multi-step forms.

**Step validation**: Each step completion may be tracked separately with distinct event names if required.

---

## Error Handling

### Required Error Handling

**Missing Required Fields**: If required fields are missing, log error but do NOT fire incomplete event.

**Invalid Data Types**: Convert invalid data types where possible, exclude field if conversion fails.

**DataLayer Access Errors**: Gracefully degrade if `window.dataLayer` is unavailable:
```javascript
function getDL() {
  try {
    return (window.dataLayer = window.dataLayer || []);
  } catch (e) {
    return { push: function() {} }; // No-op fallback
  }
}
```

**Event Push Errors**: Wrap all dataLayer.push() calls in try-catch blocks.

### Logging Requirements

**Debug Mode**: Detailed event logging only when debug mode enabled.

**Production Mode**: Silent operation with error logging only for critical failures.

**Error Format**:
```javascript
console.error('[CUFT] DataLayer Error:', {
  framework: 'elementor',
  formId: 'form123',
  error: errorMessage,
  eventType: 'form_submit'
});
```

---

## Testing Requirements

### Event Validation Tests

**Required Tests for each framework**:
1. `form_submit` event fires with all required fields
2. `generate_lead` event fires only when conditions met
3. Field naming uses correct snake_case format
4. Data types match specifications
5. Events include `cuft_tracked: true` and correct `cuft_source`
6. Deduplication prevents duplicate events
7. Error handling works for malformed data

### Test Data Requirements

**Valid Test Cases**:
- Complete form with email, phone, and click ID
- Form with email only
- Form with phone only
- Form with no contact information
- Form with UTM parameters
- Form with multiple click IDs

**Invalid Test Cases**:
- Malformed email addresses
- Empty form submissions
- Missing form ID
- DataLayer access errors

---

## Compliance Validation

### Specification Compliance Checklist

- [ ] All events use snake_case field names
- [ ] Required fields present in all events
- [ ] Framework identifiers match specification
- [ ] Data types conform to specifications
- [ ] Error handling implemented per requirements
- [ ] Deduplication rules enforced
- [ ] Testing requirements met
- [ ] Debug logging follows standards

### Performance Requirements

**Event Processing Time**: < 10ms per event
**Memory Impact**: < 1KB per form submission
**DOM Queries**: Minimize through caching
**Event Listener Cleanup**: Proper cleanup to prevent memory leaks

---

This specification ensures consistent, reliable dataLayer events across all form frameworks while maintaining compatibility with Google Tag Manager and other analytics platforms.