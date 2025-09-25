# Contact Form 7 (CF7) Forms Tracking Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized tracking implementation for Contact Form 7 within the Choice Universal Form Tracker. Contact Form 7 is one of the most popular WordPress form plugins and requires specific event handling due to its unique architecture.

---

## Framework Identification

### Framework Detection

**Primary Detection**: Forms MUST be identified using CF7-specific CSS classes and wrapper elements:
```javascript
function isCF7Form(form) {
  return form && (
    form.closest('.wpcf7') !== null ||
    form.classList.contains('wpcf7-form') ||
    form.hasAttribute('data-wpcf7-id')
  );
}
```

**Required Early Exit**: If form is not CF7, script MUST exit silently without logging:
```javascript
if (!isCF7Form(form)) {
  return; // Silent exit - no console output
}
```

### Framework Identifiers

**DataLayer Event Values**:
- `form_type`: `"cf7"`
- `cuft_source`: `"contact_form_7"` (form_submit events)
- `cuft_source`: `"contact_form_7_lead"` (generate_lead events)

---

## Event Handling

### Primary Event Listener

**CF7 Custom Events**: Contact Form 7 fires specific custom events that MUST be monitored:

```javascript
function setupCF7EventListeners() {
  // Primary success event
  document.addEventListener('wpcf7mailsent', function(event) {
    handleCF7Success(event);
  }, false);

  // Optional: Monitor failure events for debugging
  document.addEventListener('wpcf7mailfailed', function(event) {
    if (window.cuftCF7 && window.cuftCF7.console_logging) {
      log('CF7 mail failed for form:', event.target);
    }
  }, false);

  // Optional: Monitor validation errors
  document.addEventListener('wpcf7invalid', function(event) {
    if (window.cuftCF7 && window.cuftCF7.console_logging) {
      log('CF7 validation failed for form:', event.target);
    }
  }, false);

  // Optional: Monitor spam detection
  document.addEventListener('wpcf7spam', function(event) {
    if (window.cuftCF7 && window.cuftCF7.console_logging) {
      log('CF7 spam detected for form:', event.target);
    }
  }, false);
}
```

### Event Types

**wpcf7mailsent**: ONLY event that should trigger tracking (successful submission)
**Other Events**: Should be monitored for debugging but not tracked in dataLayer

### Event Handler Implementation

```javascript
function handleCF7Success(event) {
  try {
    var form = event.target;

    // Verify this is a CF7 form before processing
    if (!isCF7Form(form)) {
      return; // Silent exit
    }

    // Check for deduplication
    if (form.getAttribute('data-cuft-processed') === 'true') {
      log('Form already processed, skipping');
      return;
    }

    // Mark as processed
    form.setAttribute('data-cuft-processed', 'true');

    // Extract field values
    var email = getFieldValue(form, 'email');
    var phone = getFieldValue(form, 'phone');

    // Push to dataLayer
    pushToDataLayer(form, email, phone);

    log('CF7 form submission tracked successfully');
  } catch (e) {
    log('CF7 success handler error:', e);
  }
}
```

---

## Field Value Extraction

### Email Field Detection

**CF7 Email Fields**: Contact Form 7 uses specific field types that MUST be detected:

```javascript
function getEmailField(form) {
  // CF7 standard email field patterns
  var selectors = [
    'input[type="email"]',
    'input[name*="email" i]',
    'input[name*="e-mail" i]',
    'input[id*="email" i]',
    'input[class*="email" i]',
    // CF7 shortcode field names
    'input[name^="your-email"]',
    'input[name^="email-"]',
    'input[name="email"]'
  ];

  for (var i = 0; i < selectors.length; i++) {
    var field = form.querySelector(selectors[i]);
    if (field && field.value && field.value.trim()) {
      return field;
    }
  }

  // Fallback: Find any field with email-like content
  var allInputs = form.querySelectorAll('input[type="text"], input[type="email"]');
  for (var j = 0; j < allInputs.length; j++) {
    var input = allInputs[j];
    var value = input.value ? input.value.trim() : '';
    if (value && /@/.test(value) && validateEmail(value)) {
      return input;
    }
  }

  return null;
}

function getFieldValue(form, type) {
  if (type === 'email') {
    var field = getEmailField(form);
    return field ? field.value.trim() : '';
  }

  if (type === 'phone') {
    return getPhoneValue(form);
  }

  return '';
}
```

### Phone Field Detection

**CF7 Phone Fields**: Contact Form 7 phone field detection:

```javascript
function getPhoneValue(form) {
  var selectors = [
    'input[type="tel"]',
    'input[name*="tel" i]',
    'input[name*="phone" i]',
    'input[name*="mobile" i]',
    'input[id*="tel" i]',
    'input[id*="phone" i]',
    'input[class*="tel" i]',
    'input[class*="phone" i]',
    // CF7 shortcode field names
    'input[name^="your-tel"]',
    'input[name^="your-phone"]',
    'input[name^="tel-"]',
    'input[name^="phone-"]',
    'input[name="tel"]',
    'input[name="phone"]'
  ];

  for (var i = 0; i < selectors.length; i++) {
    var field = form.querySelector(selectors[i]);
    if (field && field.value && field.value.trim()) {
      // Clean phone number but preserve international prefix
      return cleanPhoneNumber(field.value.trim());
    }
  }

  return '';
}

function cleanPhoneNumber(phone) {
  // Remove formatting but preserve + for international numbers
  return phone.replace(/(?!^\+)[^\d]/g, '');
}
```

### Field Value Validation

**Email Validation**:
```javascript
function validateEmail(email) {
  const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return emailPattern.test(email);
}
```

**Phone Validation** (accepts any non-empty value):
```javascript
function validatePhone(phone) {
  return phone && phone.trim().length > 0;
}
```

---

## Form Identification

### Form ID Extraction

**CF7 Form ID** extraction follows CF7's wrapper structure:

```javascript
function getFormId(form) {
  // CF7 forms are wrapped in .wpcf7 div with ID
  var wrapper = form.closest('.wpcf7');
  if (wrapper) {
    var wrapperId = wrapper.getAttribute('id');
    if (wrapperId) return wrapperId;
  }

  // Check form's own data attributes
  var formId = form.getAttribute('data-wpcf7-id') ||
               form.getAttribute('id');

  if (formId) return formId;

  // Fallback: generate based on position
  var cf7Forms = document.querySelectorAll('.wpcf7-form');
  var index = Array.prototype.indexOf.call(cf7Forms, form);
  return 'cf7-form-' + (index + 1);
}
```

### Form Name Extraction

**CF7 Form Name**: CF7 doesn't typically expose form names in frontend, use fallbacks:

```javascript
function getFormName(form) {
  // Check for custom name attributes (rare in CF7)
  var name = form.getAttribute('name') ||
             form.getAttribute('data-form-name') ||
             form.getAttribute('aria-label');

  if (name) return name;

  // Check if there's a title or heading near the form
  var wrapper = form.closest('.wpcf7');
  if (wrapper) {
    var heading = wrapper.querySelector('h1, h2, h3, h4, h5, h6');
    if (heading) {
      return heading.textContent.trim();
    }

    // Check previous sibling for heading
    var prevElement = wrapper.previousElementSibling;
    if (prevElement && /^H[1-6]$/.test(prevElement.tagName)) {
      return prevElement.textContent.trim();
    }
  }

  // Default fallback
  return 'Contact Form 7';
}
```

---

## AJAX Submission Handling

### CF7 AJAX Architecture

**CF7 Submission Flow**: Contact Form 7 uses AJAX by default and fires custom events after processing:

1. Form submitted via AJAX
2. Server processes form
3. Response returned with status
4. Custom event fired based on result (`wpcf7mailsent`, `wpcf7mailfailed`, etc.)
5. Our tracking code processes the success event

### Response Data Access

**CF7 Response Data** (if needed for advanced tracking):

```javascript
function handleCF7Success(event) {
  // CF7 response data available in event.detail
  var response = event.detail;
  var form = event.target;

  if (response) {
    log('CF7 Response:', {
      status: response.status,
      message: response.message,
      mailSent: response.mailSent,
      into: response.into
    });
  }

  // Continue with standard tracking
  processFormSubmission(form);
}
```

### Form State Management

**Submission State Tracking**:

```javascript
function trackSubmissionState(form) {
  // Mark form as being processed
  form.setAttribute('data-cuft-submission-time', Date.now().toString());
  form.setAttribute('data-cuft-processing', 'true');
}

function clearSubmissionState(form) {
  // Clear processing state
  form.removeAttribute('data-cuft-processing');
  form.setAttribute('data-cuft-processed', 'true');
}
```

---

## Multi-Form Page Handling

### Multiple CF7 Forms

**Page with Multiple CF7 Forms**: Each form MUST be tracked independently:

```javascript
function handleMultipleForms() {
  // Each CF7 form wrapper has unique ID
  var wrappers = document.querySelectorAll('.wpcf7');

  for (var i = 0; i < wrappers.length; i++) {
    var wrapper = wrappers[i];
    var form = wrapper.querySelector('.wpcf7-form');

    if (form) {
      // Ensure each form can be tracked independently
      setupFormTracking(form, wrapper.getAttribute('id'));
    }
  }
}

function setupFormTracking(form, wrapperId) {
  // Form-specific tracking setup if needed
  form.setAttribute('data-cuft-wrapper-id', wrapperId);
}
```

### Event Target Verification

**Correct Form Detection** in multi-form scenarios:

```javascript
function handleCF7Success(event) {
  var form = event.target;

  // Verify event target is the correct CF7 form
  if (!form || !form.classList.contains('wpcf7-form')) {
    log('Invalid event target for CF7 tracking');
    return;
  }

  // Verify form hasn't been processed already
  if (form.getAttribute('data-cuft-processed') === 'true') {
    return;
  }

  // Continue with tracking
  processFormSubmission(form);
}
```

---

## Generate Lead Event Criteria

### CF7-Specific Lead Generation

**Modified Lead Generation Criteria**: CF7 may use different criteria than other frameworks:

```javascript
function shouldFireGenerateLeadEvent(email, phone, payload) {
  // Check if feature is enabled
  if (!window.cuftCF7 || !window.cuftCF7.generate_lead_enabled) {
    return false;
  }

  // CF7 specific criteria:
  // 1. Must have email
  // 2. Must have click ID OR UTM campaign
  // 3. Phone is optional for CF7

  var hasEmail = email && validateEmail(email);
  var hasClickId = payload.click_id || payload.gclid || payload.fbclid ||
                   payload.msclkid || payload.ttclid || payload.li_fat_id ||
                   payload.twclid || payload.snap_click_id || payload.pclid;
  var hasUTMCampaign = payload.utm_campaign;

  return hasEmail && (hasClickId || hasUTMCampaign);
}
```

### Generate Lead Event Firing

```javascript
function fireGenerateLeadEvent(basePayload, email, phone) {
  if (!shouldFireGenerateLeadEvent(email, phone, basePayload)) {
    log('Generate lead criteria not met for CF7 form');
    return;
  }

  var leadPayload = {
    event: 'generate_lead',
    currency: 'USD',
    value: 0,
    cuft_tracked: true,
    cuft_source: 'contact_form_7_lead',
    submitted_at: new Date().toISOString()
  };

  // Copy relevant fields from form_submit payload
  var copyFields = [
    'page_location', 'page_referrer', 'page_title', 'language',
    'screen_resolution', 'engagement_time_msec', 'utm_source',
    'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'form_type', 'form_id', 'form_name', 'click_id', 'gclid',
    'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'twclid',
    'snap_click_id', 'pclid', 'user_email', 'user_phone'
  ];

  copyFields.forEach(function(field) {
    if (basePayload[field]) {
      leadPayload[field] = basePayload[field];
    }
  });

  // Ensure email is included
  if (email) leadPayload.user_email = email;
  if (phone) leadPayload.user_phone = phone;

  try {
    getDL().push(leadPayload);
    log('CF7 generate_lead event fired:', leadPayload);
  } catch (e) {
    log('CF7 generate_lead push error:', e);
  }
}
```

---

## Error Handling

### Required Error Handling

**Event Listener Errors**:
```javascript
function safeAddEventListener() {
  try {
    document.addEventListener('wpcf7mailsent', handleCF7Success, false);
    log('CF7 event listeners added successfully');
  } catch (e) {
    log('Error adding CF7 event listeners:', e);
  }
}
```

**Field Value Extraction Errors**:
```javascript
function safeGetFieldValue(form, type) {
  try {
    return getFieldValue(form, type);
  } catch (e) {
    log('CF7 field value extraction error for ' + type + ':', e);
    return '';
  }
}
```

**Form Detection Errors**:
```javascript
function safeFormDetection(form) {
  try {
    return isCF7Form(form);
  } catch (e) {
    log('CF7 form detection error:', e);
    return false;
  }
}
```

### Validation Errors

**CF7 Validation Failures**: Do NOT track forms that fail CF7's validation
**Network Errors**: Handle AJAX submission failures gracefully
**Missing Elements**: Continue tracking even if optional elements are missing

---

## Performance Requirements

### Initialization Performance

**Event Listener Setup**: < 2ms for all CF7 event listeners
**Form Detection**: < 3ms per form detection attempt
**Field Value Extraction**: < 8ms per form

### Runtime Performance

**Event Processing**: < 12ms total per form submission
**DataLayer Push**: < 3ms per event
**Field Validation**: < 2ms per field

### Memory Management

**Event Deduplication**: Use data attributes to prevent duplicate processing
**State Cleanup**: Remove processing attributes after completion
**Memory Leaks**: Ensure no circular references or memory leaks

---

## Configuration Options

### Debug Configuration

```javascript
window.cuftCF7 = {
  console_logging: false,           // Enable debug logging
  generate_lead_enabled: true,      // Enable generate_lead events
  track_failed_submissions: false,  // Track failed submissions
  custom_field_mapping: {},         // Custom field name mappings
  validation_enabled: true          // Enable field validation
};
```

### Custom Field Mapping

```javascript
window.cuftCF7.custom_field_mapping = {
  'your-custom-email': 'email',
  'your-custom-phone': 'phone',
  'custom-field-name': 'standardized-name'
};
```

---

## Testing Requirements

### Required Test Scenarios

1. **Single CF7 Form Submission**
   - Standard contact form
   - Verify form_submit event fires with correct data
   - Verify generate_lead fires when criteria met

2. **Multiple CF7 Forms on Same Page**
   - Multiple contact forms
   - Verify each form tracks independently
   - Verify no cross-form interference

3. **CF7 Form with Custom Fields**
   - Non-standard field names
   - Verify field detection accuracy
   - Test custom field mapping

4. **CF7 Validation Scenarios**
   - Submit form with validation errors
   - Verify tracking only occurs on successful submission
   - Test spam detection handling

5. **Error Handling**
   - Network failures
   - Missing required elements
   - Invalid form configurations

### Test Data Requirements

**Test Form Configurations**:
- Simple contact form (name, email, message)
- Lead generation form (email, phone, company)
- Newsletter signup form (email only)
- Custom field form (non-standard field names)

**Expected DataLayer Events**:
```javascript
// Standard CF7 form_submit event
{
  event: "form_submit",
  form_type: "cf7",
  form_id: "wpcf7-f123-o1",
  form_name: "Contact Form",
  user_email: "test@example.com",
  user_phone: "123-456-7890",
  submitted_at: "2025-01-01T12:00:00.000Z",
  cuft_tracked: true,
  cuft_source: "contact_form_7",
  // UTM parameters if available
  // Click IDs if available
}

// generate_lead event (when criteria met)
{
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_tracked: true,
  cuft_source: "contact_form_7_lead",
  // All form_submit fields included
}
```

---

## CF7-Specific Considerations

### Plugin Compatibility

**CF7 Extensions**: Must work with popular CF7 extensions:
- Conditional Fields for Contact Form 7
- Contact Form 7 Multi-Step Forms
- Contact Form 7 Database Addon
- Flamingo (CF7 message storage)

### Form Caching

**Caching Plugin Compatibility**: Must work with caching plugins that may affect CF7 AJAX
**Static Form IDs**: Form IDs may change with caching, account for variations

### Multilingual Support

**WPML/Polylang**: CF7 forms may have different IDs per language
**Field Name Variations**: Field names may vary by language/locale

---

## Compliance Validation

### Specification Compliance Checklist

- [ ] Framework detection with silent exit for non-CF7 forms
- [ ] wpcf7mailsent event listener properly configured
- [ ] Comprehensive field detection for email and phone
- [ ] Multiple form support on same page
- [ ] Custom field mapping capability
- [ ] Modified generate_lead criteria for CF7
- [ ] Complete error handling for all operations
- [ ] Performance requirements met (< 12ms total processing)
- [ ] Deduplication prevents multiple tracking
- [ ] DataLayer events conform to core specification
- [ ] Testing scenarios cover all CF7 use cases

This specification ensures reliable tracking for Contact Form 7 while respecting its unique architecture and event system.