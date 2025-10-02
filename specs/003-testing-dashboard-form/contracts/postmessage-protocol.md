# PostMessage Communication Protocol

## Overview
Secure cross-frame communication between the testing dashboard and embedded test form iframes.

## Security Requirements
- All messages must include origin validation
- Messages must include action type
- Sensitive operations require nonce verification
- Only same-origin communication allowed

---

## Message Structure

### Base Message Format
```javascript
{
  "action": "string",      // Required: Message type identifier
  "nonce": "string",       // Required for sensitive operations
  "timestamp": "number",   // Unix timestamp
  "data": {}              // Action-specific payload
}
```

---

## Dashboard → Iframe Messages

### 1. Populate Form Fields
Instructs the iframe to populate form fields with test data.

```javascript
// Send from dashboard
iframe.contentWindow.postMessage({
  action: 'cuft_populate_fields',
  nonce: cuftFormBuilder.nonce,
  timestamp: Date.now(),
  data: {
    fields: {
      name: 'Test User',
      email: 'test@example.com',
      phone: '555-0123',
      message: 'Test submission message'
    },
    options: {
      trigger_events: true,  // Trigger input/change events
      clear_first: true      // Clear existing values first
    }
  }
}, window.location.origin);
```

### 2. Request Form Info
Requests information about the loaded form.

```javascript
iframe.contentWindow.postMessage({
  action: 'cuft_get_form_info',
  nonce: cuftFormBuilder.nonce,
  timestamp: Date.now()
}, window.location.origin);
```

### 3. Enable Test Mode
Activates test mode in the iframe to prevent real submissions.

```javascript
iframe.contentWindow.postMessage({
  action: 'cuft_enable_test_mode',
  nonce: cuftFormBuilder.nonce,
  timestamp: Date.now(),
  data: {
    intercept_submit: true,
    log_events: true
  }
}, window.location.origin);
```

### 4. Trigger Submission
Programmatically triggers form submission for testing.

```javascript
iframe.contentWindow.postMessage({
  action: 'cuft_trigger_submit',
  nonce: cuftFormBuilder.nonce,
  timestamp: Date.now()
}, window.location.origin);
```

---

## Iframe → Dashboard Messages

### 1. Form Loaded Confirmation
Confirms that the form has loaded and is ready.

```javascript
// Send from iframe
window.parent.postMessage({
  action: 'cuft_form_loaded',
  timestamp: Date.now(),
  data: {
    framework: 'elementor',
    form_id: 'elementor-form-123',
    field_count: 4,
    fields: ['name', 'email', 'phone', 'message'],
    ready: true
  }
}, window.location.origin);
```

### 2. Fields Populated Confirmation
Confirms that fields have been populated.

```javascript
window.parent.postMessage({
  action: 'cuft_fields_populated',
  timestamp: Date.now(),
  data: {
    success: true,
    populated_fields: ['name', 'email', 'phone', 'message'],
    failed_fields: []
  }
}, window.location.origin);
```

### 3. Form Submission Event
Reports form submission and tracking events.

```javascript
window.parent.postMessage({
  action: 'cuft_form_submitted',
  timestamp: Date.now(),
  data: {
    form_data: {
      name: 'Test User',
      email: 'test@example.com',
      phone: '555-0123',
      message: 'Test message'
    },
    tracking_event: {
      event: 'form_submit',
      form_type: 'elementor',
      form_id: 'elementor-form-123',
      form_name: 'Test Contact Form',
      user_email: 'test@example.com',
      user_phone: '555-0123',
      cuft_tracked: true,
      cuft_source: 'elementor_pro',
      submitted_at: '2025-01-10T10:30:00Z'
    },
    validation: {
      prevented_real_submit: true,
      captured_events: ['form_submit', 'generate_lead']
    }
  }
}, window.location.origin);
```

### 4. Error Report
Reports errors encountered in the iframe.

```javascript
window.parent.postMessage({
  action: 'cuft_error',
  timestamp: Date.now(),
  data: {
    error_type: 'population_failed',
    message: 'Could not find email field',
    details: {
      field: 'email',
      selectors_tried: ['#email', '[name="email"]', '.email-field']
    }
  }
}, window.location.origin);
```

### 5. Event Captured
Reports captured dataLayer or tracking events.

```javascript
window.parent.postMessage({
  action: 'cuft_event_captured',
  timestamp: Date.now(),
  data: {
    event_type: 'dataLayer.push',
    event: {
      event: 'generate_lead',
      currency: 'USD',
      value: 0,
      cuft_tracked: true,
      cuft_source: 'elementor_pro_lead'
    }
  }
}, window.location.origin);
```

---

## Implementation Examples

### Dashboard Listener
```javascript
// Dashboard side - listening for iframe messages
window.addEventListener('message', function(event) {
  // Security: Validate origin
  if (event.origin !== window.location.origin) {
    console.warn('Rejected message from untrusted origin:', event.origin);
    return;
  }

  // Validate message structure
  if (!event.data || !event.data.action) {
    return;
  }

  // Route based on action
  switch (event.data.action) {
    case 'cuft_form_loaded':
      handleFormLoaded(event.data.data);
      break;

    case 'cuft_form_submitted':
      handleFormSubmission(event.data.data);
      break;

    case 'cuft_error':
      handleIframeError(event.data.data);
      break;

    case 'cuft_event_captured':
      displayCapturedEvent(event.data.data);
      break;
  }
});
```

### Iframe Listener
```javascript
// Iframe side - listening for dashboard messages
window.addEventListener('message', function(event) {
  // Security: Validate origin
  if (event.origin !== window.location.origin) {
    return;
  }

  // Validate nonce for sensitive operations
  if (!event.data.nonce || event.data.nonce !== window.cuftTestMode.nonce) {
    console.warn('Invalid nonce in message');
    return;
  }

  // Route based on action
  switch (event.data.action) {
    case 'cuft_populate_fields':
      populateFormFields(event.data.data.fields);
      break;

    case 'cuft_enable_test_mode':
      enableTestMode(event.data.data);
      break;

    case 'cuft_trigger_submit':
      triggerFormSubmit();
      break;

    case 'cuft_get_form_info':
      sendFormInfo();
      break;
  }
});
```

---

## Error Handling

### Message Validation Errors
```javascript
{
  action: 'cuft_error',
  data: {
    error_type: 'invalid_message',
    message: 'Missing required field: action',
    received: { /* original message */ }
  }
}
```

### Security Violations
```javascript
{
  action: 'cuft_error',
  data: {
    error_type: 'security_violation',
    message: 'Invalid nonce',
    details: {
      expected_nonce: 'abc123',
      received_nonce: 'xyz789'
    }
  }
}
```

---

## Best Practices

1. **Always validate origin**: Never process messages from untrusted origins
2. **Include timestamps**: Helps with debugging and event ordering
3. **Use specific action names**: Prefix with `cuft_` to avoid conflicts
4. **Handle errors gracefully**: Always send error messages back to dashboard
5. **Async operations**: Use promises/callbacks for operations that may take time
6. **Cleanup listeners**: Remove event listeners when dashboard closes

---

## Testing Protocol Messages

### Test Handshake Sequence
```javascript
// 1. Dashboard initiates
parent → iframe: { action: 'cuft_enable_test_mode' }

// 2. Iframe confirms
iframe → parent: { action: 'cuft_form_loaded', data: { ready: true } }

// 3. Dashboard sends test data
parent → iframe: { action: 'cuft_populate_fields', data: { fields: {...} } }

// 4. Iframe confirms population
iframe → parent: { action: 'cuft_fields_populated', data: { success: true } }

// 5. Dashboard triggers submit
parent → iframe: { action: 'cuft_trigger_submit' }

// 6. Iframe reports submission
iframe → parent: { action: 'cuft_form_submitted', data: { tracking_event: {...} } }
```