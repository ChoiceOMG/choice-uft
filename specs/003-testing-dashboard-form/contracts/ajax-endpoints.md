# AJAX API Contracts

## Base URL
`/wp-admin/admin-ajax.php`

## Authentication
All endpoints require WordPress admin authentication (`manage_options` capability).

## Common Headers
```http
Content-Type: application/x-www-form-urlencoded
X-Requested-With: XMLHttpRequest
```

---

## 1. Create Test Form

### Endpoint
`POST /wp-admin/admin-ajax.php?action=cuft_create_test_form`

### Request Body
```json
{
  "action": "cuft_create_test_form",
  "nonce": "string",
  "framework": "elementor|cf7|gravity|ninja|avada",
  "template_id": "basic_contact_form"
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "instance_id": "cuft_test_123456",
    "framework": "elementor",
    "post_id": 789,
    "form_id": "elementor-form-123",
    "test_url": "/cuft-test-form/?form_id=cuft_test_123456",
    "iframe_url": "/cuft-test-form/?form_id=cuft_test_123456&test_mode=1",
    "created_at": "2025-01-10T10:30:00Z"
  }
}
```

### Response (Error - 400)
```json
{
  "success": false,
  "data": {
    "message": "Framework not available: gravity",
    "code": "framework_unavailable"
  }
}
```

### Error Codes
- `invalid_nonce`: Security check failed
- `insufficient_permissions`: User not admin
- `framework_unavailable`: Framework not installed/active
- `template_not_found`: Invalid template ID
- `creation_failed`: Form creation error

---

## 2. Get Test Forms

### Endpoint
`GET /wp-admin/admin-ajax.php?action=cuft_get_test_forms`

### Request Parameters
```
action: cuft_get_test_forms
nonce: string
status: active|all (optional, default: active)
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "forms": [
      {
        "instance_id": "cuft_test_123456",
        "framework": "elementor",
        "framework_label": "Elementor Pro",
        "post_id": 789,
        "form_id": "elementor-form-123",
        "template_name": "Basic Contact Form",
        "status": "active",
        "test_url": "/cuft-test-form/?form_id=cuft_test_123456",
        "iframe_url": "/cuft-test-form/?form_id=cuft_test_123456&test_mode=1",
        "created_at": "2025-01-10T10:30:00Z",
        "last_tested": "2025-01-10T10:35:00Z",
        "test_count": 3
      }
    ],
    "total": 1
  }
}
```

---

## 3. Delete Test Form

### Endpoint
`POST /wp-admin/admin-ajax.php?action=cuft_delete_test_form`

### Request Body
```json
{
  "action": "cuft_delete_test_form",
  "nonce": "string",
  "instance_id": "cuft_test_123456"
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "message": "Test form deleted successfully",
    "instance_id": "cuft_test_123456"
  }
}
```

### Response (Error - 404)
```json
{
  "success": false,
  "data": {
    "message": "Test form not found",
    "code": "form_not_found"
  }
}
```

---

## 4. Populate Form Fields

### Endpoint
`POST /wp-admin/admin-ajax.php?action=cuft_populate_form`

### Request Body
```json
{
  "action": "cuft_populate_form",
  "nonce": "string",
  "instance_id": "cuft_test_123456",
  "use_test_data": true
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "message_sent": true,
    "test_data": {
      "name": "Test User",
      "email": "test-1736506800@example.com",
      "phone": "555-0123",
      "message": "This is a test submission from CUFT Testing Dashboard"
    }
  }
}
```

Note: This endpoint triggers a postMessage to the iframe, not direct field population.

---

## 5. Handle Test Submission

### Endpoint
`POST /wp-admin/admin-ajax.php?action=cuft_test_submit`

### Request Body
```json
{
  "action": "cuft_test_submit",
  "nonce": "string",
  "instance_id": "cuft_test_123456",
  "form_data": {
    "name": "Test User",
    "email": "test@example.com",
    "phone": "555-0123",
    "message": "Test message"
  },
  "tracking_event": {
    "event": "form_submit",
    "form_type": "elementor",
    "form_id": "elementor-form-123",
    "cuft_tracked": true,
    "cuft_source": "elementor_pro"
  }
}
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "logged": true,
    "validation": {
      "has_cuft_tracked": true,
      "has_cuft_source": true,
      "uses_snake_case": true,
      "required_fields_present": true,
      "click_ids_tracked": ["gclid", "click_id"]
    },
    "message": "Test submission logged successfully"
  }
}
```

---

## 6. Get Framework Capabilities

### Endpoint
`GET /wp-admin/admin-ajax.php?action=cuft_get_frameworks`

### Request Parameters
```
action: cuft_get_frameworks
nonce: string
```

### Response (Success - 200)
```json
{
  "success": true,
  "data": {
    "frameworks": {
      "elementor": {
        "name": "Elementor Pro",
        "version": "3.18.0",
        "available": true,
        "supports_generation": true,
        "icon": "dashicons-elementor"
      },
      "cf7": {
        "name": "Contact Form 7",
        "version": "5.8",
        "available": true,
        "supports_generation": true,
        "icon": "dashicons-email"
      },
      "gravity": {
        "name": "Gravity Forms",
        "version": null,
        "available": false,
        "supports_generation": false,
        "icon": "dashicons-gravityforms"
      }
    }
  }
}
```

---

## Common Error Responses

### 401 Unauthorized
```json
{
  "success": false,
  "data": {
    "message": "You must be logged in as an administrator",
    "code": "unauthorized"
  }
}
```

### 403 Forbidden
```json
{
  "success": false,
  "data": {
    "message": "Invalid nonce",
    "code": "invalid_nonce"
  }
}
```

### 500 Internal Server Error
```json
{
  "success": false,
  "data": {
    "message": "An unexpected error occurred",
    "code": "internal_error",
    "details": "Error details for debugging"
  }
}
```

## JavaScript Usage Example

```javascript
// Create test form
jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
        action: 'cuft_create_test_form',
        nonce: cuftFormBuilder.nonce,
        framework: 'elementor',
        template_id: 'basic_contact_form'
    },
    success: function(response) {
        if (response.success) {
            const formUrl = response.data.iframe_url;
            // Load iframe with form
        }
    }
});
```