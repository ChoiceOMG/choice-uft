# AJAX Endpoints Contract

**Feature**: Admin Testing Dashboard
**Date**: 2025-09-30

## Overview

This contract defines all AJAX endpoints for the Admin Testing Dashboard, including request/response formats, security requirements, and error handling.

---

## Security Requirements (All Endpoints)

**Applies to ALL endpoints below:**

1. **Nonce Validation**: `check_ajax_referer('cuft_testing_dashboard', 'nonce')`
2. **Capability Check**: `current_user_can('manage_options')`
3. **Input Sanitization**: All $_POST values sanitized
4. **Error Masking**: No internal errors exposed to client
5. **Admin-Only**: No `wp_ajax_nopriv_` hooks registered

---

## 1. Generate Test Data

### Endpoint
`wp_ajax_cuft_generate_test_data`

### Request

**Method**: POST

**Parameters**:
```php
array(
    'action' => 'cuft_generate_test_data',  // Required
    'nonce' => string,                      // Required: wp_create_nonce('cuft_testing_dashboard')
    'click_id_types' => array|null          // Optional: ['gclid', 'fbclid', ...] (defaults to all)
)
```

**Example**:
```javascript
fetch(ajaxurl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'cuft_generate_test_data',
        nonce: cuftConfig.nonce,
        click_id_types: JSON.stringify(['gclid', 'fbclid'])
    })
});
```

### Response

**Success (200)**:
```json
{
    "success": true,
    "data": {
        "session_id": "session_1696089600000_abc123xyz",
        "click_ids": {
            "click_id": "test_click_abc123",
            "gclid": "Cj0KCQjw8O-VBhCpARIsACMvVLOH4R8Zs6P7jS",
            "fbclid": "IwAR37SDAQdPrxMqwHQEY6dcs5rle1Mt0b0WubR9"
        },
        "utm_params": {
            "utm_source": "google",
            "utm_medium": "cpc",
            "utm_campaign": "spring_sale_2025",
            "utm_term": "contact_form",
            "utm_content": "variation_a"
        },
        "test_email": "test+6758a92b3c1d2@example.com",
        "test_phone": "555-0142",
        "test_name": "Test User",
        "timestamp": 1696089600000
    }
}
```

**Error (403)**:
```json
{
    "success": false,
    "data": {
        "message": "Insufficient permissions"
    }
}
```

**Error (500)**:
```json
{
    "success": false,
    "data": {
        "message": "Failed to generate test data"
    }
}
```

### Performance Target
< 500ms (NFR-002)

---

## 2. Simulate Event

### Endpoint
`wp_ajax_cuft_simulate_event`

### Request

**Method**: POST

**Parameters**:
```php
array(
    'action' => 'cuft_simulate_event',      // Required
    'nonce' => string,                      // Required
    'event_type' => string,                 // Required: 'phone_click' | 'email_click' | 'form_submit' | 'generate_lead'
    'session_id' => string|null,            // Optional: Use specific session, defaults to latest
    'custom_data' => array|null             // Optional: Override default values
)
```

**Example**:
```javascript
fetch(ajaxurl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'cuft_simulate_event',
        nonce: cuftConfig.nonce,
        event_type: 'form_submit',
        session_id: 'session_1696089600000_abc123xyz'
    })
});
```

### Response

**Success (200)**:
```json
{
    "success": true,
    "data": {
        "event": {
            "event": "form_submit",
            "cuft_tracked": true,
            "cuft_source": "testing_dashboard",
            "test_mode": true,
            "form_type": "elementor",
            "form_id": "test-form-123",
            "user_email": "test@example.com",
            "user_phone": "555-0142",
            "click_id": "test_click_abc123",
            "gclid": "Cj0KCQjw8O-VBhCp...",
            "utm_source": "google",
            "utm_medium": "cpc",
            "utm_campaign": "spring_sale_2025",
            "submitted_at": "2025-09-30T14:32:17Z"
        },
        "db_id": 123
    }
}
```

**Error (400)**:
```json
{
    "success": false,
    "data": {
        "message": "Invalid event type"
    }
}
```

### Performance Target
< 500ms (NFR-001)

---

## 3. Build Test Form

### Endpoint
`wp_ajax_cuft_build_test_form`

### Request

**Method**: POST

**Parameters**:
```php
array(
    'action' => 'cuft_build_test_form',     // Required
    'nonce' => string,                      // Required
    'framework' => string,                  // Required: 'elementor' | 'cf7' | 'ninja' | 'gravity' | 'avada'
    'session_id' => string|null,            // Optional: Use test data from session
    'field_defaults' => array|null          // Optional: Override field values
)
```

**Example**:
```javascript
fetch(ajaxurl, {
    method: 'POST',
    body: new URLSearchParams({
        action: 'cuft_build_test_form',
        nonce: cuftConfig.nonce,
        framework: 'gravity',
        session_id: 'session_1696089600000_abc123xyz'
    })
});
```

### Response

**Success (200)** - Gravity Forms (dynamic creation):
```json
{
    "success": true,
    "data": {
        "framework": "gravity",
        "form_id": 123,
        "shortcode": "[gravityform id=\"123\" title=\"false\" description=\"false\"]",
        "html": "<div class=\"gform_wrapper\">...</div>",
        "method": "created"
    }
}
```

**Success (200)** - Other frameworks (pre-built):
```json
{
    "success": true,
    "data": {
        "framework": "elementor",
        "form_id": 456,
        "shortcode": "[elementor-template id=\"456\"]",
        "html": "<div class=\"elementor-widget-container\">...</div>",
        "method": "pre_built"
    }
}
```

**Error (400)**:
```json
{
    "success": false,
    "data": {
        "message": "Invalid framework"
    }
}
```

**Error (404)**:
```json
{
    "success": false,
    "data": {
        "message": "Pre-built form not found for framework: elementor"
    }
}
```

### Performance Target
< 500ms

---

## 4. Get Test Events

### Endpoint
`wp_ajax_cuft_get_test_events`

### Request

**Method**: POST

**Parameters**:
```php
array(
    'action' => 'cuft_get_test_events',     // Required
    'nonce' => string,                      // Required
    'session_id' => string|null,            // Optional: Filter by session
    'event_type' => string|null,            // Optional: Filter by type
    'limit' => int|null,                    // Optional: Max results (default 100)
    'offset' => int|null                    // Optional: Pagination offset
)
```

### Response

**Success (200)**:
```json
{
    "success": true,
    "data": {
        "events": [
            {
                "id": 123,
                "session_id": "session_1696089600000_abc123xyz",
                "event_type": "form_submit",
                "event_data": {
                    "event": "form_submit",
                    "cuft_tracked": true,
                    "test_mode": true,
                    "form_type": "elementor"
                },
                "created_at": "2025-09-30 14:32:17"
            }
        ],
        "total": 1,
        "limit": 100,
        "offset": 0
    }
}
```

---

## 5. Delete Test Events

### Endpoint
`wp_ajax_cuft_delete_test_events`

### Request

**Method**: POST

**Parameters**:
```php
array(
    'action' => 'cuft_delete_test_events',  // Required
    'nonce' => string,                      // Required
    'event_ids' => array|null,              // Optional: Specific IDs to delete
    'session_id' => string|null,            // Optional: Delete all from session
    'delete_all' => boolean|null            // Optional: Delete ALL test events
)
```

### Response

**Success (200)**:
```json
{
    "success": true,
    "data": {
        "deleted_count": 15,
        "message": "Successfully deleted 15 test events"
    }
}
```

---

## Error Handling Pattern

```php
public function ajax_endpoint_handler() {
    try {
        // Security checks
        check_ajax_referer('cuft_testing_dashboard', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'), 403);
        }

        // Input validation
        $input = $this->sanitize_and_validate_input();

        if (is_wp_error($input)) {
            wp_send_json_error(array('message' => $input->get_error_message()), 400);
        }

        // Business logic
        $result = $this->perform_operation($input);

        wp_send_json_success($result);

    } catch (Exception $e) {
        // Log error internally
        error_log('CUFT AJAX Error: ' . $e->getMessage());

        // Return generic error to client (don't expose internals)
        wp_send_json_error(array('message' => 'Operation failed'), 500);
    }
}
```

---

## JavaScript Client Example

```javascript
class CUFTAjaxClient {
    constructor(ajaxUrl, nonce) {
        this.ajaxUrl = ajaxUrl;
        this.nonce = nonce;
    }

    async generateTestData(clickIdTypes = null) {
        const params = {
            action: 'cuft_generate_test_data',
            nonce: this.nonce
        };

        if (clickIdTypes) {
            params.click_id_types = JSON.stringify(clickIdTypes);
        }

        return this.request(params);
    }

    async simulateEvent(eventType, sessionId = null, customData = null) {
        const params = {
            action: 'cuft_simulate_event',
            nonce: this.nonce,
            event_type: eventType
        };

        if (sessionId) params.session_id = sessionId;
        if (customData) params.custom_data = JSON.stringify(customData);

        return this.request(params);
    }

    async buildTestForm(framework, sessionId = null) {
        return this.request({
            action: 'cuft_build_test_form',
            nonce: this.nonce,
            framework,
            session_id: sessionId
        });
    }

    async request(params) {
        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(params)
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data.message || 'Unknown error');
            }

            return data.data;
        } catch (error) {
            console.error('AJAX request failed:', error);
            throw error;
        }
    }
}
```

---

## Testing Checklist

- [ ] All endpoints validate nonce
- [ ] All endpoints check manage_options capability
- [ ] All inputs are sanitized
- [ ] No internal errors exposed to client
- [ ] All responses follow wp_send_json_success/error format
- [ ] Performance targets met (<500ms)
- [ ] Error logging implemented
- [ ] JavaScript client handles all error cases
