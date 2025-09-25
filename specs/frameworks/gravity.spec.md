# Gravity Forms Tracking Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized tracking implementation for Gravity Forms within the Choice Universal Form Tracker. Gravity Forms uses a submit-based tracking approach with specific field class structures.

---

## Framework Identification

### Framework Detection

**Primary Detection**: Forms MUST be identified using Gravity Forms-specific CSS classes:
```javascript
function isGravityForm(form) {
  return form && (
    form.classList.contains('gform_wrapper') ||
    form.closest('.gform_wrapper') !== null ||
    form.querySelector('.gfield') !== null
  );
}
```

**Required Early Exit**: If form is not Gravity Forms, script MUST exit silently:
```javascript
if (!isGravityForm(form)) {
  return; // Silent exit - no console output
}
```

### Framework Identifiers

**DataLayer Event Values**:
- `form_type`: `"gravity"`
- `cuft_source`: `"gravity_forms"` (form_submit events)
- `cuft_source`: `"gravity_forms_lead"` (generate_lead events)

---

## Event Handling

### Primary Event Listener

**Submit-Based Tracking**: Gravity Forms uses form submit events with success page detection:

```javascript
function setupGravityEventListeners() {
  document.addEventListener('submit', function(event) {
    var form = event.target;

    if (!isGravityForm(form)) {
      return; // Silent exit
    }

    // Capture form data before submission
    captureGravityFormData(form);

    // Set up success detection
    setTimeout(function() {
      detectGravitySuccess(form);
    }, 200); // Allow time for AJAX processing

  }, true);

  log('Gravity Forms event listeners setup complete');
}
```

### Success Detection Methods

**Multiple Success Detection Approaches**:

1. **Confirmation Message Detection**:
```javascript
function detectGravitySuccess(form) {
  var wrapper = form.closest('.gform_wrapper');
  if (!wrapper) return;

  // Check for confirmation message
  var confirmationMsg = wrapper.querySelector('.gform_confirmation_message');
  if (confirmationMsg && confirmationMsg.offsetHeight > 0) {
    handleGravitySuccess(form);
    return;
  }

  // Check if form is hidden (redirect might have occurred)
  if (form.style.display === 'none' || form.offsetHeight === 0) {
    var wasVisible = form.getAttribute('data-cuft-was-visible');
    if (wasVisible === 'true') {
      // Check for redirect scenario
      setTimeout(function() {
        if (window.location.href !== form.getAttribute('data-cuft-original-url')) {
          handleGravitySuccess(form);
        }
      }, 500);
      return;
    }
  }

  // Retry detection with backoff
  var retryCount = parseInt(form.getAttribute('data-cuft-retry-count') || '0');
  if (retryCount < 10) {
    form.setAttribute('data-cuft-retry-count', (retryCount + 1).toString());
    setTimeout(function() {
      detectGravitySuccess(form);
    }, Math.min(300 * (retryCount + 1), 2000)); // Exponential backoff
  }
}
```

2. **MutationObserver for Dynamic Changes**:
```javascript
function setupGravityMutationObserver(form) {
  var wrapper = form.closest('.gform_wrapper');
  if (!wrapper) return;

  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      // Check for added confirmation messages
      if (mutation.type === 'childList') {
        var addedNodes = Array.from(mutation.addedNodes);
        var hasConfirmation = addedNodes.some(function(node) {
          return node.nodeType === 1 && (
            node.classList.contains('gform_confirmation_message') ||
            node.classList.contains('gform_confirmation_wrapper')
          );
        });

        if (hasConfirmation) {
          observer.disconnect();
          handleGravitySuccess(form);
        }
      }
    });
  });

  observer.observe(wrapper, { childList: true, subtree: true });

  // Cleanup observer after timeout
  setTimeout(function() {
    observer.disconnect();
  }, 15000); // 15 second timeout for Gravity Forms
}
```

---

## Field Value Extraction

### Gravity Forms Field Structure

**Gravity Fields**: Use `.gfield` containers with specific CSS classes:

```javascript
function getGravityFieldValue(form, type) {
  var fields = form.querySelectorAll('.gfield');

  for (var i = 0; i < fields.length; i++) {
    var field = fields[i];
    var input = field.querySelector('input, textarea, select');
    if (!input) continue;

    var inputType = (input.getAttribute('type') || '').toLowerCase();
    var fieldClasses = field.className || '';
    var inputName = (input.name || '').toLowerCase();
    var inputId = (input.id || '').toLowerCase();
    var value = input.value ? input.value.trim() : '';

    if (type === 'email') {
      if (isGravityEmailField(inputType, fieldClasses, inputName, inputId)) {
        return value;
      }
    }

    if (type === 'phone') {
      if (isGravityPhoneField(inputType, fieldClasses, inputName, inputId)) {
        return cleanPhoneNumber(value);
      }
    }
  }

  return '';
}
```

### Email Field Detection

```javascript
function isGravityEmailField(inputType, fieldClasses, inputName, inputId) {
  return (
    inputType === 'email' ||
    fieldClasses.indexOf('gfield_email') > -1 ||
    fieldClasses.indexOf('ginput_container_email') > -1 ||
    inputName.indexOf('email') > -1 ||
    inputId.indexOf('email') > -1
  );
}
```

### Phone Field Detection

```javascript
function isGravityPhoneField(inputType, fieldClasses, inputName, inputId) {
  return (
    inputType === 'tel' ||
    fieldClasses.indexOf('gfield_phone') > -1 ||
    fieldClasses.indexOf('ginput_container_phone') > -1 ||
    inputName.indexOf('phone') > -1 ||
    inputName.indexOf('tel') > -1 ||
    inputId.indexOf('phone') > -1 ||
    inputId.indexOf('tel') > -1
  );
}
```

### Complex Field Handling

**Multi-Part Fields**: Gravity Forms can have complex field structures:

```javascript
function getComplexGravityFieldValue(form, type) {
  if (type === 'phone') {
    // Handle phone fields with multiple inputs (area code, number, extension)
    var phoneFields = form.querySelectorAll('.gfield_phone input');
    if (phoneFields.length > 1) {
      var phoneValue = '';
      for (var i = 0; i < phoneFields.length; i++) {
        phoneValue += phoneFields[i].value || '';
      }
      return cleanPhoneNumber(phoneValue);
    }
  }

  if (type === 'name') {
    // Handle name fields with first/last name inputs
    var nameFields = form.querySelectorAll('.name_first input, .name_last input');
    if (nameFields.length > 0) {
      var nameValue = '';
      for (var j = 0; j < nameFields.length; j++) {
        nameValue += (nameFields[j].value || '') + ' ';
      }
      return nameValue.trim();
    }
  }

  return '';
}
```

---

## Form Identification

### Form ID Extraction

```javascript
function getGravityFormId(form) {
  // Gravity Forms ID pattern: gform_X where X is form ID
  var formId = form.getAttribute('id');
  if (formId && formId.startsWith('gform_')) {
    return formId;
  }

  // Check wrapper for form ID
  var wrapper = form.closest('.gform_wrapper');
  if (wrapper) {
    var wrapperId = wrapper.getAttribute('id');
    if (wrapperId && wrapperId.startsWith('gform_wrapper_')) {
      var idMatch = wrapperId.match(/gform_wrapper_(\d+)/);
      if (idMatch) {
        return 'gform_' + idMatch[1];
      }
    }
  }

  // Check for hidden form ID field
  var formIdInput = form.querySelector('input[name="gform_form_id"]');
  if (formIdInput && formIdInput.value) {
    return 'gform_' + formIdInput.value;
  }

  // Generate fallback ID
  var gravityForms = document.querySelectorAll('.gform_wrapper form');
  var index = Array.prototype.indexOf.call(gravityForms, form);
  return 'gravity-form-' + (index + 1);
}
```

### Form Name Extraction

```javascript
function getGravityFormName(form) {
  // Check for form title
  var wrapper = form.closest('.gform_wrapper');
  if (wrapper) {
    var title = wrapper.querySelector('.gform_title, .gform_heading .gform_title');
    if (title) {
      return title.textContent.trim();
    }
  }

  // Check for explicit name attribute
  var name = form.getAttribute('name') ||
             form.getAttribute('data-form-name') ||
             form.getAttribute('aria-label');

  if (name) return name;

  // Look for preceding heading
  if (wrapper) {
    var prevElement = wrapper.previousElementSibling;
    if (prevElement && /^H[1-6]$/.test(prevElement.tagName)) {
      return prevElement.textContent.trim();
    }
  }

  return 'Gravity Form';
}
```

---

## Data Capture and Storage

### Pre-Submission Data Capture

```javascript
function captureGravityFormData(form) {
  var email = getGravityFieldValue(form, 'email');
  var phone = getGravityFieldValue(form, 'phone') || getComplexGravityFieldValue(form, 'phone');

  // Store captured data
  form.setAttribute('data-cuft-email', email || '');
  form.setAttribute('data-cuft-phone', phone || '');
  form.setAttribute('data-cuft-capture-time', Date.now().toString());
  form.setAttribute('data-cuft-tracking', 'pending');
  form.setAttribute('data-cuft-was-visible', 'true');
  form.setAttribute('data-cuft-original-url', window.location.href);
  form.setAttribute('data-cuft-retry-count', '0');

  log('Captured Gravity form data:', {
    email: email || 'not found',
    phone: phone || 'not found',
    formId: getGravityFormId(form)
  });
}
```

### Success Handler

```javascript
function handleGravitySuccess(form) {
  try {
    // Check if already processed
    if (form.getAttribute('data-cuft-processed') === 'true') {
      return;
    }

    // Get stored field values
    var email = form.getAttribute('data-cuft-email') || '';
    var phone = form.getAttribute('data-cuft-phone') || '';

    // Push to dataLayer
    pushToDataLayer(form, email, phone);

    // Mark as processed and cleanup
    form.setAttribute('data-cuft-processed', 'true');
    form.removeAttribute('data-cuft-tracking');
    form.removeAttribute('data-cuft-retry-count');

    log('Gravity form success handled');
  } catch (e) {
    log('Gravity success handler error:', e);
  }
}
```

---

## Multi-Page Form Support

### Page Detection

```javascript
function isMultiPageGravityForm(form) {
  return form.querySelector('.gform_page') !== null;
}

function isGravityFinalPage(form) {
  var pages = form.querySelectorAll('.gform_page');
  if (!pages.length) return true; // Not multi-page

  var currentPage = form.querySelector('.gform_page:not(.gform_hidden)');
  if (!currentPage) return true; // Assume final if no visible page

  var pageIndex = Array.prototype.indexOf.call(pages, currentPage);
  var isLast = pageIndex === pages.length - 1;

  // Also check for submit button on current page
  var submitButton = currentPage.querySelector('.gform_button[type="submit"]');

  return isLast || (submitButton !== null);
}
```

**Multi-Page Handling**: Only track final page completion:
```javascript
function handleGravitySubmit(form) {
  if (isMultiPageGravityForm(form) && !isGravityFinalPage(form)) {
    log('Gravity multi-page form - not final page, skipping tracking');
    return;
  }

  captureGravityFormData(form);
  setupGravityMutationObserver(form);
  setTimeout(function() { detectGravitySuccess(form); }, 200);
}
```

---

## Conditional Logic Handling

### Conditional Field Detection

```javascript
function handleConditionalFields(form) {
  // Gravity Forms may hide/show fields based on conditions
  // Only capture visible field values
  var visibleFields = form.querySelectorAll('.gfield:not(.gfield_hidden)');

  var email = '';
  var phone = '';

  for (var i = 0; i < visibleFields.length; i++) {
    var field = visibleFields[i];
    var input = field.querySelector('input, textarea, select');
    if (!input || !input.value) continue;

    var fieldClasses = field.className || '';
    var inputType = (input.getAttribute('type') || '').toLowerCase();

    if (!email && isGravityEmailField(inputType, fieldClasses, input.name, input.id)) {
      email = input.value.trim();
    }

    if (!phone && isGravityPhoneField(inputType, fieldClasses, input.name, input.id)) {
      phone = cleanPhoneNumber(input.value.trim());
    }
  }

  return { email: email, phone: phone };
}
```

---

## Error Handling

### Required Error Handling

**Form Detection Errors**:
```javascript
function safeGravityFormDetection(form) {
  try {
    return isGravityForm(form);
  } catch (e) {
    log('Gravity form detection error:', e);
    return false;
  }
}
```

**Success Detection with Retry Logic**:
```javascript
function safeDetectGravitySuccess(form) {
  try {
    detectGravitySuccess(form);
  } catch (e) {
    log('Gravity success detection error:', e);

    // Fallback: Try simple confirmation check
    setTimeout(function() {
      var wrapper = form.closest('.gform_wrapper');
      if (wrapper && wrapper.querySelector('.gform_confirmation_message')) {
        handleGravitySuccess(form);
      }
    }, 1000);
  }
}
```

---

## Performance Requirements

### Initialization Performance

**Event Listener Setup**: < 3ms for submit listener
**Form Detection**: < 5ms per form detection
**Data Capture**: < 12ms per form (including complex fields)

### Runtime Performance

**Success Detection**: < 20ms per detection attempt
**MutationObserver Setup**: < 5ms per observer
**Field Value Extraction**: < 15ms per form (complex fields)

---

## Configuration Options

### Debug Configuration

```javascript
window.cuftGravity = {
  console_logging: false,
  generate_lead_enabled: true,
  success_detection_timeout: 15000,
  max_retry_attempts: 10,
  handle_complex_fields: true,
  track_conditional_fields: true
};
```

---

## Testing Requirements

### Required Test Scenarios

1. **Single Page Form**
2. **Multi-Page Form** (final page only)
3. **Form with Complex Fields** (phone with area code, name with first/last)
4. **Form with Conditional Logic**
5. **Multiple Gravity Forms on Same Page**
6. **Form with Redirect Confirmation**

### Expected DataLayer Events

```javascript
{
  event: "form_submit",
  form_type: "gravity",
  form_id: "gform_1",
  form_name: "Contact Form",
  user_email: "test@example.com",
  user_phone: "123-456-7890",
  submitted_at: "2025-01-01T12:00:00.000Z",
  cuft_tracked: true,
  cuft_source: "gravity_forms"
}
```

---

## Compliance Validation

### Specification Compliance Checklist

- [ ] Framework detection with silent exit
- [ ] Submit-based event handling
- [ ] Multiple success detection methods with retry logic
- [ ] Field value extraction for .gfield structure
- [ ] Complex field handling (multi-part fields)
- [ ] Multi-page form support (final page only)
- [ ] Conditional field handling
- [ ] Pre-submission data capture with retry count
- [ ] MutationObserver cleanup
- [ ] Performance requirements met
- [ ] Error handling for all operations
- [ ] DataLayer events conform to core specification

This specification ensures reliable tracking for Gravity Forms while handling its complex field structures and multi-page capabilities.