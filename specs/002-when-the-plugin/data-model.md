# Data Model: Admin Testing Dashboard

**Feature**: Admin Testing Dashboard
**Branch**: `002-when-the-plugin`
**Date**: 2025-09-30

## Overview

This document defines all data structures used in the Admin Testing Dashboard feature, including browser localStorage schemas, MySQL database schemas, and dataLayer event schemas.

---

## 1. Test Session (localStorage)

### Purpose
Stores generated test data in browser localStorage for persistence across page reloads until browser cache is cleared.

### Schema

```javascript
{
  // Metadata
  id: string,              // Unique session ID: "session_{timestamp}_{random}"
  timestamp: number,       // Unix timestamp (milliseconds) when session was created

  // Click IDs
  clickIds: {
    click_id: string,      // Generic click ID
    gclid: string,         // Google Ads Click ID
    gbraid: string,        // Google iOS Click ID
    wbraid: string,        // Google Web-to-App Click ID
    fbclid: string,        // Facebook Click ID
    msclkid: string,       // Microsoft/Bing Click ID
    ttclid: string,        // TikTok Click ID
    li_fat_id: string,     // LinkedIn Click ID
    twclid: string,        // Twitter/X Click ID
    snap_click_id: string, // Snapchat Click ID
    pclid: string          // Pinterest Click ID
  },

  // UTM Parameters
  utmParams: {
    utm_source: string,    // Traffic source (e.g., "google", "facebook")
    utm_medium: string,    // Marketing medium (e.g., "cpc", "social")
    utm_campaign: string,  // Campaign name (e.g., "spring_sale_2025")
    utm_term: string,      // Paid search keyword (optional)
    utm_content: string    // Ad variation identifier (optional)
  },

  // Test Contact Information
  testEmail: string,       // Safe test email (e.g., "test+{uniqid}@example.com")
  testPhone: string,       // Safe test phone (e.g., "555-0123")
  testName: string         // Test user name (e.g., "Test User")
}
```

### Example

```javascript
{
  id: "session_1696089600000_abc123xyz",
  timestamp: 1696089600000,
  clickIds: {
    click_id: "test_click_abc123",
    gclid: "Cj0KCQjw8O-VBhCpARIsACMvVLOH4R8Zs6P7jS_5hgq9UF2f",
    gbraid: "1xAbC_dEfGhIjKlMnOpQrStUvWxYz",
    wbraid: "CjsKCQjw8O-VBhCpARIsACMvVLO",
    fbclid: "IwAR37SDAQdPrxMqwHQEY6dcs5rle1Mt0b0WubR9dL8Wb",
    msclkid: "Cj0KCQjw8O-VBhCpARIsACMvVLOH4R8Zs6P7jS",
    ttclid: "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqr",
    li_fat_id: "1234567890abcdef1234567890abcdef12345678",
    twclid: "Cj0KCQjw8O-VBhCpARIsACMvVLOH4R8",
    snap_click_id: "sc_6758a92b3c1d2",
    pclid: "dj0yJnU9abcdef1234567890"
  },
  utmParams: {
    utm_source: "google",
    utm_medium: "cpc",
    utm_campaign: "spring_sale_2025",
    utm_term: "contact_form",
    utm_content: "variation_a"
  },
  testEmail: "test+6758a92b3c1d2@example.com",
  testPhone: "555-0142",
  testName: "Test User"
}
```

### Storage Key
`cuft_test_sessions` (stores array of session objects)

### Constraints
- Maximum 50 sessions (FIFO enforcement)
- 24-hour TTL (sessions older than 24 hours are purged)
- ~5KB per session (estimated)
- Total localStorage budget: ~250KB

---

## 2. Test Form Configuration (localStorage)

### Purpose
Stores configuration for dynamically built test forms, including framework selection and field pre-population settings.

### Schema

```javascript
{
  // Metadata
  id: string,              // Unique form config ID
  timestamp: number,       // When configuration was created

  // Framework Selection
  framework: string,       // Selected framework: "elementor" | "cf7" | "ninja" | "gravity" | "avada"

  // Field Values (pre-populated)
  fields: {
    name: string,          // Name field default value
    email: string,         // Email field default value
    phone: string,         // Phone field default value
    message: string        // Message field default value
  },

  // Form Metadata
  formId: number|null,     // WordPress form ID (if created)
  shortcode: string|null   // Generated shortcode (if applicable)
}
```

### Example

```javascript
{
  id: "form_config_1696089600000",
  timestamp: 1696089600000,
  framework: "gravity",
  fields: {
    name: "Test User",
    email: "test+6758a92b3c1d2@example.com",
    phone: "555-0142",
    message: "This is a test message for validation"
  },
  formId: 123,
  shortcode: "[gravityform id=\"123\" title=\"false\" description=\"false\"]"
}
```

### Storage Key
`cuft_test_form_config` (stores single object, overwritten on update)

---

## 3. Test Events Table (MySQL)

### Purpose
Separate database table for storing test event data, isolated from production click tracking table.

### Table Name
`{wp_prefix}cuft_test_events`

### Schema (SQL)

```sql
CREATE TABLE {wp_prefix}cuft_test_events (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    session_id varchar(50) NOT NULL,
    event_type varchar(50) NOT NULL,
    event_data longtext,
    test_mode tinyint(1) NOT NULL DEFAULT 1,
    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY session_id (session_id),
    KEY event_type (event_type),
    KEY created_at (created_at)
) {charset_collate};
```

### Columns

| Column | Type | Description |
|--------|------|-------------|
| `id` | bigint(20) unsigned | Auto-increment primary key |
| `session_id` | varchar(50) | Links to Test Session ID from localStorage |
| `event_type` | varchar(50) | Type of event: "phone_click", "email_click", "form_submit", "generate_lead" |
| `event_data` | longtext | JSON-encoded event data (complete dataLayer event object) |
| `test_mode` | tinyint(1) | Always 1 (marks as test data) |
| `created_at` | datetime | Timestamp when event was recorded |

### Indexes
- `PRIMARY KEY (id)` - Fast lookups by ID
- `KEY session_id` - Fast filtering by test session
- `KEY event_type` - Fast filtering by event type
- `KEY created_at` - Fast sorting and cleanup of old data

### Example Row

```sql
INSERT INTO wp_cuft_test_events
(session_id, event_type, event_data, test_mode, created_at)
VALUES (
  'session_1696089600000_abc123xyz',
  'form_submit',
  '{"event":"form_submit","form_type":"elementor","form_id":"widget-123","user_email":"test@example.com","test_mode":true,"cuft_tracked":true,"cuft_source":"testing_dashboard"}',
  1,
  '2025-09-30 14:32:15'
);
```

### Retention Policy
- Automatic cleanup of events older than 30 days
- Manual deletion via dashboard UI
- Truncate all test events option

---

## 4. Simulated Event (dataLayer)

### Purpose
Defines the structure of events pushed to window.dataLayer during simulation.

### Base Event Schema

All simulated events include these base fields:

```javascript
{
  // Standard dataLayer fields
  event: string,           // Event name: "phone_click" | "email_click" | "form_submit" | "generate_lead"

  // CUFT identification fields (constitutional requirement)
  cuft_tracked: boolean,   // Always true
  cuft_source: string,     // Always "testing_dashboard"

  // Test isolation field (FR-028)
  test_mode: boolean,      // Always true

  // Timestamp
  submitted_at: string     // ISO 8601 timestamp
}
```

### phone_click Event

```javascript
{
  event: "phone_click",
  cuft_tracked: true,
  cuft_source: "testing_dashboard",
  test_mode: true,
  submitted_at: "2025-09-30T14:32:15Z",
  phone_number: "555-0142",
  click_id: "test_click_abc123",
  gclid: "Cj0KCQjw8O-VBhCp...",
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "spring_sale_2025"
}
```

### email_click Event

```javascript
{
  event: "email_click",
  cuft_tracked: true,
  cuft_source: "testing_dashboard",
  test_mode: true,
  submitted_at: "2025-09-30T14:32:16Z",
  email_address: "test@example.com",
  click_id: "test_click_abc123",
  gclid: "Cj0KCQjw8O-VBhCp...",
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "spring_sale_2025"
}
```

### form_submit Event

```javascript
{
  event: "form_submit",
  cuft_tracked: true,
  cuft_source: "testing_dashboard",
  test_mode: true,

  // Form identification (constitutional snake_case)
  form_type: "elementor",
  form_id: "elementor-widget-7a2c4f9",
  form_name: "Test Contact Form",

  // User data
  user_email: "test@example.com",
  user_phone: "555-0142",

  // Tracking parameters
  click_id: "test_click_abc123",
  gclid: "Cj0KCQjw8O-VBhCp...",
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "spring_sale_2025",
  utm_term: "contact_form",
  utm_content: "variation_a",

  // Timestamp
  submitted_at: "2025-09-30T14:32:17Z"
}
```

### generate_lead Event

**Only fires when ALL three conditions are met** (FR-011):
1. Valid email address present
2. Valid phone number present
3. At least one click ID present

```javascript
{
  event: "generate_lead",
  cuft_tracked: true,
  cuft_source: "testing_dashboard_lead",
  test_mode: true,

  // GA4 standard fields
  currency: "USD",
  value: 0,

  // All form_submit fields also included
  form_type: "elementor",
  form_id: "elementor-widget-7a2c4f9",
  form_name: "Test Contact Form",
  user_email: "test@example.com",
  user_phone: "555-0142",
  click_id: "test_click_abc123",
  gclid: "Cj0KCQjw8O-VBhCp...",
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "spring_sale_2025",
  submitted_at: "2025-09-30T14:32:17Z"
}
```

### Naming Convention (Constitutional Requirement)
- ✅ ALL fields use snake_case (never camelCase)
- ✅ `cuft_tracked: true` (required identification field)
- ✅ `cuft_source` identifies origin (required)
- ✅ `test_mode: true` (required for test isolation)

---

## 5. Event Validation Result

### Purpose
Represents the validation outcome when a simulated event is checked against constitutional standards.

### Schema

```javascript
{
  // Validation metadata
  eventIndex: number,      // Position in captured events array
  timestamp: string,       // When validation was performed

  // Event reference
  event: object,           // Complete event object being validated

  // Validation results
  isValid: boolean,        // Overall validation status
  errors: array,           // Array of error messages
  warnings: array,         // Array of warning messages

  // Field-level validation
  fields: {
    required: {
      cuft_tracked: boolean,
      cuft_source: boolean,
      event: boolean,
      test_mode: boolean
    },
    naming: {
      usesSnakeCase: boolean,
      violations: array    // List of camelCase fields found
    },
    types: {
      cuft_tracked_is_boolean: boolean,
      test_mode_is_boolean: boolean,
      event_is_string: boolean
    }
  }
}
```

### Example (Valid Event)

```javascript
{
  eventIndex: 0,
  timestamp: "2025-09-30T14:32:18Z",
  event: {
    event: "form_submit",
    cuft_tracked: true,
    cuft_source: "testing_dashboard",
    test_mode: true,
    form_type: "elementor",
    user_email: "test@example.com"
  },
  isValid: true,
  errors: [],
  warnings: [],
  fields: {
    required: {
      cuft_tracked: true,
      cuft_source: true,
      event: true,
      test_mode: true
    },
    naming: {
      usesSnakeCase: true,
      violations: []
    },
    types: {
      cuft_tracked_is_boolean: true,
      test_mode_is_boolean: true,
      event_is_string: true
    }
  }
}
```

### Example (Invalid Event)

```javascript
{
  eventIndex: 1,
  timestamp: "2025-09-30T14:32:19Z",
  event: {
    event: "form_submit",
    cuft_tracked: "yes",   // Should be boolean!
    formType: "elementor", // Should be form_type (snake_case)!
    userEmail: "test@example.com" // Should be user_email!
  },
  isValid: false,
  errors: [
    "Missing required field: cuft_source",
    "Missing required field: test_mode",
    "Field cuft_tracked must be boolean (got string)",
    "camelCase field found: formType (should be form_type)",
    "camelCase field found: userEmail (should be user_email)"
  ],
  warnings: [],
  fields: {
    required: {
      cuft_tracked: true,  // Present but wrong type
      cuft_source: false,  // Missing!
      event: true,
      test_mode: false     // Missing!
    },
    naming: {
      usesSnakeCase: false,
      violations: ["formType", "userEmail"]
    },
    types: {
      cuft_tracked_is_boolean: false,
      test_mode_is_boolean: false,
      event_is_string: true
    }
  }
}
```

---

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                     Admin Testing Dashboard                     │
└─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
                ┌───────────────────────────────┐
                │  Generate Test Data (Button)  │
                └───────────────────────────────┘
                                │
                                ▼
        ┌───────────────────────────────────────────┐
        │  AJAX: cuft_generate_test_data            │
        │  Returns: clickIds, utmParams, contacts   │
        └───────────────────────────────────────────┘
                                │
                                ▼
    ┌─────────────────────────────────────────────────┐
    │  Save to localStorage (cuft_test_sessions)      │
    │  Schema: Test Session                           │
    └─────────────────────────────────────────────────┘
                                │
                                ▼
┌───────────────────────────────────────────────────────────────────┐
│  Simulate Event (phone_click, email_click, form_submit)           │
└───────────────────────────────────────────────────────────────────┘
                                │
                    ┌───────────┴───────────┐
                    ▼                       ▼
        ┌─────────────────────┐   ┌──────────────────────┐
        │  Push to dataLayer  │   │  Save to MySQL       │
        │  (window.dataLayer) │   │  (cuft_test_events)  │
        └─────────────────────┘   └──────────────────────┘
                    │                       │
                    ▼                       ▼
        ┌─────────────────────┐   ┌──────────────────────┐
        │  Event Monitor      │   │  Event History View  │
        │  Captures & Filters │   │  View/Delete Events  │
        └─────────────────────┘   └──────────────────────┘
                    │
                    ▼
        ┌─────────────────────┐
        │  Event Validator    │
        │  Constitutional     │
        │  Compliance Check   │
        └─────────────────────┘
```

---

## Summary

### Storage Locations

| Data Type | Storage | Key/Table | Persistence |
|-----------|---------|-----------|-------------|
| Test Session | Browser localStorage | `cuft_test_sessions` | Until cache cleared |
| Test Form Config | Browser localStorage | `cuft_test_form_config` | Until cache cleared |
| Test Events | MySQL | `wp_cuft_test_events` | 30 days (auto-cleanup) |
| Simulated Events | window.dataLayer | N/A | Session only (captured by monitor) |
| Validation Results | Memory (JavaScript) | N/A | Session only |

### Data Lifecycle

1. **Test Session**: Created when "Generate Test Data" is clicked → Stored in localStorage → Referenced by event simulations → Expires after 24 hours or cache clear

2. **Test Form Config**: Created when "Build Test Form" is clicked → Stored in localStorage → Used to render form → Overwritten on new form creation

3. **Test Events**: Created when events are simulated → Stored in MySQL → Displayed in event history → Deleted manually or after 30 days

4. **Simulated Events**: Pushed to dataLayer → Captured by monitor → Validated → Displayed in real-time viewer → Cleared on page reload

### Constitutional Compliance

All data structures follow Choice UFT Constitution v1.0:
- ✅ snake_case naming (never camelCase)
- ✅ `cuft_tracked: true` in all events
- ✅ `cuft_source` identifies origin
- ✅ `test_mode: true` isolates test data
- ✅ Try-catch error handling in all data operations
- ✅ Input sanitization before storage
- ✅ Output escaping before display
