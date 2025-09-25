# Avada Forms Tracking Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized tracking implementation for Avada/Fusion Forms within the Choice Universal Form Tracker. Avada Forms are part of the Fusion Builder and use submit-based tracking with success message detection.

---

## Framework Identification

### Framework Detection

**Primary Detection**: Forms MUST be identified using Avada/Fusion-specific CSS classes:
```javascript
function isAvadaForm(form) {
  return form && (
    form.classList.contains('fusion-form') ||
    form.closest('.fusion-form-container') !== null ||
    form.querySelector('.fusion-form-field') !== null ||
    form.closest('.fusion-form-wrapper') !== null
  );
}
```

**Required Early Exit**: If form is not Avada, script MUST exit silently:
```javascript
if (!isAvadaForm(form)) {
  return; // Silent exit - no console output
}
```

### Framework Identifiers

**DataLayer Event Values**:
- `form_type`: `"avada"`
- `cuft_source`: `"avada_forms"` (form_submit events)
- `cuft_source`: `"avada_forms_lead"` (generate_lead events)

---

## Event Handling

### Primary Event Listener

**Submit-Based Tracking**: Avada Forms uses form submit events with AJAX processing:

```javascript
function setupAvadaEventListeners() {
  document.addEventListener('submit', function(event) {
    var form = event.target;

    if (!isAvadaForm(form)) {
      return; // Silent exit
    }

    // Capture form data before submission
    captureAvadaFormData(form);

    // Set up success detection
    setTimeout(function() {
      detectAvadaSuccess(form);
    }, 150); // Brief delay for AJAX processing

  }, true);

  log('Avada Forms event listeners setup complete');
}
```

### Success Detection Methods

**Multiple Success Detection Approaches**:

1. **Success Message Detection**:
```javascript
function detectAvadaSuccess(form) {
  var wrapper = form.closest('.fusion-form-wrapper, .fusion-form-container');
  if (!wrapper) return;

  // Check for Fusion success message
  var successMsg = wrapper.querySelector(
    '.fusion-form-success-message, ' +
    '.fusion-form-confirmation, ' +
    '.success-message, ' +
    '.form-success'
  );

  if (successMsg && successMsg.offsetHeight > 0 &&
      !successMsg.classList.contains('fusion-hidden')) {
    handleAvadaSuccess(form);
    return;
  }

  // Check if form is hidden after successful submission
  if (form.style.display === 'none' || form.classList.contains('fusion-hidden')) {
    var wasVisible = form.getAttribute('data-cuft-was-visible');
    if (wasVisible === 'true') {
      handleAvadaSuccess(form);
      return;
    }
  }

  // Retry detection with exponential backoff
  var retryCount = parseInt(form.getAttribute('data-cuft-retry-count') || '0');
  if (retryCount < 8) {
    form.setAttribute('data-cuft-retry-count', (retryCount + 1).toString());
    setTimeout(function() {
      detectAvadaSuccess(form);
    }, Math.min(250 * Math.pow(1.5, retryCount), 3000));
  }
}
```

2. **MutationObserver for Dynamic Changes**:
```javascript
function setupAvadaMutationObserver(form) {
  var wrapper = form.closest('.fusion-form-wrapper, .fusion-form-container');
  if (!wrapper) return;

  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      // Check for added success messages
      if (mutation.type === 'childList') {
        var addedNodes = Array.from(mutation.addedNodes);
        var hasSuccessMsg = addedNodes.some(function(node) {
          return node.nodeType === 1 && (
            node.classList.contains('fusion-form-success-message') ||
            node.classList.contains('fusion-form-confirmation') ||
            node.classList.contains('success-message')
          );
        });

        if (hasSuccessMsg) {
          observer.disconnect();
          setTimeout(function() { handleAvadaSuccess(form); }, 100);
        }
      }

      // Check for class changes (form hiding)
      if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
        var target = mutation.target;
        if (target === form && target.classList.contains('fusion-hidden')) {
          observer.disconnect();
          setTimeout(function() { handleAvadaSuccess(form); }, 100);
        }
      }
    });
  });

  observer.observe(wrapper, {
    childList: true,
    subtree: true,
    attributes: true,
    attributeFilter: ['class', 'style']
  });

  // Cleanup observer after timeout
  setTimeout(function() {
    observer.disconnect();
  }, 12000); // 12 second timeout
}
```

---

## Field Value Extraction

### Avada Forms Field Structure

**Fusion Form Fields**: Use various input types with specific validation attributes:

```javascript
function getAvadaFieldValue(form, type) {
  var inputs = form.querySelectorAll('input, textarea');

  for (var i = 0; i < inputs.length; i++) {
    var input = inputs[i];

    // Skip hidden inputs
    if (input.type === 'hidden') continue;

    var inputType = (input.getAttribute('type') || '').toLowerCase();
    var inputMode = (input.getAttribute('inputmode') || '').toLowerCase();
    var dataValidate = (
      (input.getAttribute('data-validate') ||
       input.getAttribute('data-validation') ||
       '') + ''
    ).toLowerCase();
    var pattern = input.getAttribute('pattern') || '';
    var name = (input.name || '').toLowerCase();
    var id = (input.id || '').toLowerCase();
    var placeholder = (input.placeholder || '').toLowerCase();
    var ariaLabel = (input.getAttribute('aria-label') || '').toLowerCase();

    // Get label text
    var labelElement = form.querySelector('label[for="' + input.id + '"]');
    var labelText = labelElement ? (labelElement.textContent || '').toLowerCase() : '';

    var value = input.value ? input.value.trim() : '';

    if (type === 'email') {
      if (isAvadaEmailField(inputType, inputMode, dataValidate, pattern, name, id, placeholder, ariaLabel, labelText)) {
        return value;
      }
    }

    if (type === 'phone') {
      if (isAvadaPhoneField(inputType, inputMode, dataValidate, pattern, name, id, placeholder, ariaLabel, labelText)) {
        return cleanPhoneNumber(value);
      }
    }
  }

  return '';
}
```

### Email Field Detection

```javascript
function isAvadaEmailField(inputType, inputMode, dataValidate, pattern, name, id, placeholder, ariaLabel, labelText) {
  return (
    inputType === 'email' ||
    inputMode === 'email' ||
    dataValidate.indexOf('email') > -1 ||
    name.indexOf('email') > -1 ||
    name.indexOf('e-mail') > -1 ||
    id.indexOf('email') > -1 ||
    id.indexOf('e-mail') > -1 ||
    placeholder.indexOf('email') > -1 ||
    placeholder.indexOf('e-mail') > -1 ||
    ariaLabel.indexOf('email') > -1 ||
    labelText.indexOf('email') > -1 ||
    labelText.indexOf('e-mail') > -1 ||
    (pattern && pattern.indexOf('@') > -1)
  );
}
```

### Phone Field Detection

```javascript
function isAvadaPhoneField(inputType, inputMode, dataValidate, pattern, name, id, placeholder, ariaLabel, labelText) {
  // Safely check pattern for numbers
  var hasNumberPattern = false;
  try {
    hasNumberPattern = pattern && (
      pattern.indexOf('0-9') > -1 ||
      pattern.indexOf('\\d') > -1 ||
      pattern.indexOf('[0-9') > -1
    );
  } catch (e) {
    // Pattern check failed, continue without it
  }

  return (
    inputType === 'tel' ||
    inputMode === 'tel' ||
    inputMode === 'numeric' ||
    dataValidate.indexOf('phone') > -1 ||
    dataValidate.indexOf('tel') > -1 ||
    dataValidate.indexOf('number') > -1 ||
    name.indexOf('phone') > -1 ||
    name.indexOf('tel') > -1 ||
    name.indexOf('mobile') > -1 ||
    id.indexOf('phone') > -1 ||
    id.indexOf('tel') > -1 ||
    id.indexOf('mobile') > -1 ||
    placeholder.indexOf('phone') > -1 ||
    placeholder.indexOf('tel') > -1 ||
    placeholder.indexOf('mobile') > -1 ||
    ariaLabel.indexOf('phone') > -1 ||
    ariaLabel.indexOf('tel') > -1 ||
    labelText.indexOf('phone') > -1 ||
    labelText.indexOf('tel') > -1 ||
    labelText.indexOf('mobile') > -1 ||
    hasNumberPattern
  );
}
```

---

## Form Identification

### Form ID Extraction

```javascript
function getAvadaFormId(form) {
  // Check Fusion/Avada specific attributes
  var formId = form.getAttribute('data-form-id') ||
               form.getAttribute('id') ||
               form.getAttribute('data-fusion-form-id');

  if (formId) return formId;

  // Check wrapper for form identification
  var wrapper = form.closest('.fusion-form-wrapper, .fusion-form-container');
  if (wrapper) {
    var wrapperId = wrapper.getAttribute('id') ||
                   wrapper.getAttribute('data-form-id');
    if (wrapperId) return wrapperId;
  }

  // Check for hidden form ID input
  var formIdInput = form.querySelector('input[name="form_id"], input[name="fusion_form_id"]');
  if (formIdInput && formIdInput.value) {
    return 'avada-form-' + formIdInput.value;
  }

  // Generate fallback ID
  var avadaForms = document.querySelectorAll('.fusion-form');
  var index = Array.prototype.indexOf.call(avadaForms, form);
  return 'avada-form-' + (index + 1);
}
```

### Form Name Extraction

```javascript
function getAvadaFormName(form) {
  // Check for explicit form name
  var name = form.getAttribute('name') ||
             form.getAttribute('data-form-name') ||
             form.getAttribute('aria-label');

  if (name) return name;

  // Look for form title in wrapper
  var wrapper = form.closest('.fusion-form-wrapper, .fusion-form-container');
  if (wrapper) {
    var title = wrapper.querySelector(
      '.fusion-form-title, ' +
      '.form-title, ' +
      'h1, h2, h3, h4, h5, h6'
    );
    if (title) {
      return title.textContent.trim();
    }
  }

  // Check preceding elements for title
  var prevElement = form.previousElementSibling;
  while (prevElement) {
    if (/^H[1-6]$/.test(prevElement.tagName)) {
      return prevElement.textContent.trim();
    }
    if (prevElement.classList.contains('fusion-title')) {
      return prevElement.textContent.trim();
    }
    prevElement = prevElement.previousElementSibling;
  }

  return 'Avada Form';
}
```

---

## Data Capture and Storage

### Pre-Submission Data Capture

```javascript
function captureAvadaFormData(form) {
  var email = getAvadaFieldValue(form, 'email');
  var phone = getAvadaFieldValue(form, 'phone');

  // Store captured data
  form.setAttribute('data-cuft-email', email || '');
  form.setAttribute('data-cuft-phone', phone || '');
  form.setAttribute('data-cuft-capture-time', Date.now().toString());
  form.setAttribute('data-cuft-tracking', 'pending');
  form.setAttribute('data-cuft-was-visible', 'true');
  form.setAttribute('data-cuft-retry-count', '0');

  log('Captured Avada form data:', {
    email: email || 'not found',
    phone: phone || 'not found',
    formId: getAvadaFormId(form)
  });
}
```

### Success Handler

```javascript
function handleAvadaSuccess(form) {
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

    log('Avada form success handled');
  } catch (e) {
    log('Avada success handler error:', e);
  }
}
```

---

## Multi-Step Form Support

### Step Detection

```javascript
function isMultiStepAvadaForm(form) {
  return form.querySelector('.fusion-form-step, .form-step') !== null;
}

function isAvadaFinalStep(form) {
  var steps = form.querySelectorAll('.fusion-form-step, .form-step');
  if (!steps.length) return true; // Not multi-step

  var currentStep = form.querySelector('.fusion-form-step.active, .form-step.active, .fusion-form-step:not(.fusion-hidden)');
  if (!currentStep) return true; // Assume final if no active step

  var stepIndex = Array.prototype.indexOf.call(steps, currentStep);
  var isLast = stepIndex === steps.length - 1;

  // Also check for submit button in current step
  var submitButton = currentStep.querySelector('input[type="submit"], button[type="submit"]');

  return isLast || (submitButton !== null);
}
```

**Multi-Step Handling**: Only track final step completion:
```javascript
function handleAvadaSubmit(form) {
  if (isMultiStepAvadaForm(form) && !isAvadaFinalStep(form)) {
    log('Avada multi-step form - not final step, skipping tracking');
    return;
  }

  captureAvadaFormData(form);
  setupAvadaMutationObserver(form);
  setTimeout(function() { detectAvadaSuccess(form); }, 150);
}
```

---

## Fusion Builder Integration

### Dynamic Form Handling

**Fusion Builder Forms**: May be loaded dynamically or via AJAX:

```javascript
function handleDynamicAvadaForms() {
  // Set up observer for dynamically added forms
  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      if (mutation.type === 'childList') {
        var addedNodes = Array.from(mutation.addedNodes);
        addedNodes.forEach(function(node) {
          if (node.nodeType === 1) {
            // Check if added node contains Avada forms
            var forms = node.querySelectorAll ?
              node.querySelectorAll('.fusion-form') : [];

            if (node.classList && node.classList.contains('fusion-form')) {
              forms = [node]; // The node itself is a form
            }

            forms.forEach(function(form) {
              setupFormTracking(form);
            });
          }
        });
      }
    });
  });

  observer.observe(document.body, { childList: true, subtree: true });
  return observer;
}

function setupFormTracking(form) {
  // Ensure form isn't already set up for tracking
  if (!form.getAttribute('data-cuft-setup')) {
    form.setAttribute('data-cuft-setup', 'true');
    log('Dynamic Avada form detected and set up for tracking');
  }
}
```

---

## Error Handling

### Required Error Handling

**Form Detection Errors**:
```javascript
function safeAvadaFormDetection(form) {
  try {
    return isAvadaForm(form);
  } catch (e) {
    log('Avada form detection error:', e);
    return false;
  }
}
```

**Success Detection with Robust Retry**:
```javascript
function safeDetectAvadaSuccess(form) {
  try {
    detectAvadaSuccess(form);
  } catch (e) {
    log('Avada success detection error:', e);

    // Fallback: Simple success message check
    setTimeout(function() {
      try {
        var wrapper = form.closest('.fusion-form-wrapper, .fusion-form-container');
        var successMsg = wrapper ? wrapper.querySelector('.fusion-form-success-message, .success-message') : null;
        if (successMsg && successMsg.offsetHeight > 0) {
          handleAvadaSuccess(form);
        }
      } catch (fallbackError) {
        log('Avada fallback detection error:', fallbackError);
      }
    }, 1000);
  }
}
```

**Field Value Extraction Errors**:
```javascript
function safeGetAvadaFieldValue(form, type) {
  try {
    return getAvadaFieldValue(form, type);
  } catch (e) {
    log('Avada field extraction error for ' + type + ':', e);
    return '';
  }
}
```

---

## Performance Requirements

### Initialization Performance

**Event Listener Setup**: < 3ms for submit listener
**Form Detection**: < 4ms per form detection
**Dynamic Form Observer**: < 5ms for MutationObserver setup

### Runtime Performance

**Success Detection**: < 18ms per detection attempt
**Field Value Extraction**: < 12ms per form
**Data Capture**: < 10ms per form

### Memory Management

**Observer Cleanup**: Properly disconnect all observers
**Attribute Cleanup**: Remove tracking attributes after processing
**Dynamic Form Handling**: Prevent duplicate setup for dynamic forms

---

## Configuration Options

### Debug Configuration

```javascript
window.cuftAvada = {
  console_logging: false,
  generate_lead_enabled: true,
  success_detection_timeout: 12000,
  max_retry_attempts: 8,
  handle_dynamic_forms: true,
  track_multi_step_progress: false
};
```

---

## Testing Requirements

### Required Test Scenarios

1. **Single Step Fusion Form**
2. **Multi-Step Form** (final step only)
3. **Dynamically Loaded Form** (via Fusion Builder)
4. **Multiple Avada Forms on Same Page**
5. **Form with Various Field Types** (email, phone, text, textarea)
6. **Form with Success Message Display**
7. **Form with Redirect After Success**

### Expected DataLayer Events

```javascript
{
  event: "form_submit",
  form_type: "avada",
  form_id: "avada-form-1",
  form_name: "Contact Form",
  user_email: "test@example.com",
  user_phone: "123-456-7890",
  submitted_at: "2025-01-01T12:00:00.000Z",
  cuft_tracked: true,
  cuft_source: "avada_forms"
}
```

---

## Fusion Builder Compatibility

### Responsive Form Handling

**Different Device Breakpoints**: Avada forms may behave differently on different screen sizes
**Modal/Popup Forms**: Forms may appear in Fusion modals or popups
**Conditional Display**: Forms may be conditionally displayed based on user actions

### Theme Integration

**Fusion Theme Compatibility**: Must work with all Fusion/Avada theme versions
**Custom CSS Overrides**: Must handle custom styling that might affect form structure
**Plugin Conflicts**: Handle potential conflicts with other Fusion Builder addons

---

## Compliance Validation

### Specification Compliance Checklist

- [ ] Framework detection with silent exit
- [ ] Submit-based event handling
- [ ] Multiple success detection methods with exponential backoff
- [ ] Field value extraction for Fusion form structure
- [ ] Multi-step form support (final step only)
- [ ] Dynamic form handling for Fusion Builder
- [ ] Pre-submission data capture with retry logic
- [ ] MutationObserver cleanup and timeout management
- [ ] Performance requirements met
- [ ] Error handling for all operations
- [ ] DataLayer events conform to core specification
- [ ] Fusion Builder integration compatibility

This specification ensures reliable tracking for Avada/Fusion Forms while handling their dynamic loading capabilities and integration with the Fusion Builder ecosystem.