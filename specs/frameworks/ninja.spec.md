# Ninja Forms Tracking Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized tracking implementation for Ninja Forms within the Choice Universal Form Tracker. Ninja Forms uses a submit-based tracking approach due to its AJAX handling architecture.

---

## Framework Identification

### Framework Detection

**Primary Detection**: Forms MUST be identified using Ninja Forms-specific CSS classes:
```javascript
function isNinjaForm(form) {
  return form && (
    form.closest('.nf-form-cont') !== null ||
    form.classList.contains('nf-form') ||
    form.querySelector('.nf-field') !== null
  );
}
```

**Required Early Exit**: If form is not Ninja Forms, script MUST exit silently:
```javascript
if (!isNinjaForm(form)) {
  return; // Silent exit - no console output
}
```

### Framework Identifiers

**DataLayer Event Values**:
- `form_type`: `"ninja"`
- `cuft_source`: `"ninja_forms"` (form_submit events)
- `cuft_source`: `"ninja_forms_lead"` (generate_lead events)

---

## Event Handling

### Primary Event Listener

**Submit-Based Tracking**: Ninja Forms uses form submit events with AJAX detection:

```javascript
function setupNinjaEventListeners() {
  document.addEventListener('submit', function(event) {
    var form = event.target;

    if (!isNinjaForm(form)) {
      return; // Silent exit
    }

    // Capture form data before submission
    captureNinjaFormData(form);

    // Set up success detection
    setTimeout(function() {
      detectNinjaSuccess(form);
    }, 100); // Brief delay for AJAX processing

  }, true);

  log('Ninja Forms event listeners setup complete');
}
```

### Success Detection Methods

**Multiple Success Detection Approaches**:

1. **Success Message Detection**:
```javascript
function detectNinjaSuccess(form) {
  var container = form.closest('.nf-form-cont');
  if (!container) return;

  // Check for success message
  var successMsg = container.querySelector('.nf-response-msg, .nf-form-success');
  if (successMsg && successMsg.offsetHeight > 0) {
    handleNinjaSuccess(form);
    return;
  }

  // Check for form hiding (common success behavior)
  if (form.style.display === 'none' || form.offsetHeight === 0) {
    var wasVisible = form.getAttribute('data-cuft-was-visible');
    if (wasVisible === 'true') {
      handleNinjaSuccess(form);
      return;
    }
  }

  // Retry detection
  setTimeout(function() {
    detectNinjaSuccess(form);
  }, 200);
}
```

2. **MutationObserver for Dynamic Changes**:
```javascript
function setupNinjaMutationObserver(form) {
  var container = form.closest('.nf-form-cont');
  if (!container) return;

  var observer = new MutationObserver(function(mutations) {
    mutations.forEach(function(mutation) {
      // Check for added success messages
      if (mutation.type === 'childList') {
        var addedNodes = Array.from(mutation.addedNodes);
        var hasSuccessMsg = addedNodes.some(function(node) {
          return node.nodeType === 1 && (
            node.classList.contains('nf-response-msg') ||
            node.classList.contains('nf-form-success')
          );
        });

        if (hasSuccessMsg) {
          observer.disconnect();
          handleNinjaSuccess(form);
        }
      }
    });
  });

  observer.observe(container, { childList: true, subtree: true });

  // Cleanup observer after timeout
  setTimeout(function() {
    observer.disconnect();
  }, 10000); // 10 second timeout
}
```

---

## Field Value Extraction

### Ninja Fields Structure

**Ninja Fields**: Use `.nf-field` containers with specific data attributes:

```javascript
function getNinjaFieldValue(form, type) {
  var fields = form.querySelectorAll('.nf-field');

  for (var i = 0; i < fields.length; i++) {
    var field = fields[i];
    var input = field.querySelector('input, textarea, select');
    if (!input) continue;

    var fieldType = field.getAttribute('data-field-type') || '';
    var inputType = (input.getAttribute('type') || '').toLowerCase();
    var inputMode = (input.getAttribute('inputmode') || '').toLowerCase();
    var name = (input.name || '').toLowerCase();
    var id = (input.id || '').toLowerCase();
    var placeholder = (input.placeholder || '').toLowerCase();

    // Get label text from Ninja Forms structure
    var label = field.querySelector('label, .nf-field-label');
    var labelText = label ? (label.textContent || '').toLowerCase() : '';

    var value = input.value ? input.value.trim() : '';

    if (type === 'email') {
      if (isEmailField(fieldType, inputType, inputMode, name, id, placeholder, labelText, input)) {
        return value;
      }
    }

    if (type === 'phone') {
      if (isPhoneField(fieldType, inputType, inputMode, name, id, placeholder, labelText, input)) {
        return cleanPhoneNumber(value);
      }
    }
  }

  return '';
}
```

### Email Field Detection

```javascript
function isEmailField(fieldType, inputType, inputMode, name, id, placeholder, labelText, input) {
  // Check pattern attribute safely
  var pattern = input.getAttribute('pattern') || '';
  var hasEmailPattern = pattern.indexOf('@') > -1;

  return (
    fieldType === 'email' ||
    inputType === 'email' ||
    inputMode === 'email' ||
    name.indexOf('email') > -1 ||
    id.indexOf('email') > -1 ||
    placeholder.indexOf('email') > -1 ||
    labelText.indexOf('email') > -1 ||
    hasEmailPattern
  );
}
```

### Phone Field Detection

```javascript
function isPhoneField(fieldType, inputType, inputMode, name, id, placeholder, labelText, input) {
  // Safely check pattern for numbers
  var pattern = input.getAttribute('pattern') || '';
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
    fieldType === 'phone' ||
    inputType === 'tel' ||
    inputMode === 'tel' ||
    inputMode === 'numeric' ||
    name.indexOf('phone') > -1 ||
    name.indexOf('tel') > -1 ||
    name.indexOf('mobile') > -1 ||
    id.indexOf('phone') > -1 ||
    id.indexOf('tel') > -1 ||
    placeholder.indexOf('phone') > -1 ||
    labelText.indexOf('phone') > -1 ||
    hasNumberPattern
  );
}
```

---

## Form Identification

### Form ID Extraction

```javascript
function getNinjaFormId(form) {
  // Check Ninja Forms specific attributes
  var formId = form.getAttribute('data-form-id') ||
               form.getAttribute('id');

  if (formId) return formId;

  // Check container for form ID
  var container = form.closest('.nf-form-cont');
  if (container) {
    var containerId = container.getAttribute('id') ||
                     container.getAttribute('data-form-id');
    if (containerId) return containerId;
  }

  // Generate fallback ID
  var ninjaForms = document.querySelectorAll('.nf-form');
  var index = Array.prototype.indexOf.call(ninjaForms, form);
  return 'ninja-form-' + (index + 1);
}
```

### Form Name Extraction

```javascript
function getNinjaFormName(form) {
  // Check for explicit form name
  var name = form.getAttribute('name') ||
             form.getAttribute('data-form-name') ||
             form.getAttribute('aria-label');

  if (name) return name;

  // Look for form title in container
  var container = form.closest('.nf-form-cont');
  if (container) {
    var title = container.querySelector('.nf-form-title, h1, h2, h3, h4, h5, h6');
    if (title) {
      return title.textContent.trim();
    }
  }

  // Check for Ninja Forms admin title (if available)
  var titleField = form.querySelector('input[name="form_title"]');
  if (titleField) {
    return titleField.value;
  }

  return 'Ninja Form';
}
```

---

## Data Capture and Storage

### Pre-Submission Data Capture

**Capture Form Data Before Submit**:

```javascript
function captureNinjaFormData(form) {
  var email = getNinjaFieldValue(form, 'email');
  var phone = getNinjaFieldValue(form, 'phone');

  // Store captured data
  form.setAttribute('data-cuft-email', email || '');
  form.setAttribute('data-cuft-phone', phone || '');
  form.setAttribute('data-cuft-capture-time', Date.now().toString());
  form.setAttribute('data-cuft-tracking', 'pending');
  form.setAttribute('data-cuft-was-visible', 'true');

  log('Captured Ninja form data:', {
    email: email || 'not found',
    phone: phone || 'not found',
    formId: getNinjaFormId(form)
  });
}
```

### Success Handler

```javascript
function handleNinjaSuccess(form) {
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

    // Mark as processed
    form.setAttribute('data-cuft-processed', 'true');
    form.removeAttribute('data-cuft-tracking');

    log('Ninja form success handled');
  } catch (e) {
    log('Ninja success handler error:', e);
  }
}
```

---

## Multi-Step Form Support

### Step Detection

```javascript
function isMultiStepNinjaForm(form) {
  return form.querySelector('.nf-step') !== null;
}

function isNinjaFinalStep(form) {
  var steps = form.querySelectorAll('.nf-step');
  if (!steps.length) return true; // Not multi-step

  var currentStep = form.querySelector('.nf-step.active, .nf-step:not(.nf-hidden)');
  if (!currentStep) return true; // Assume final if no active step found

  var stepIndex = Array.prototype.indexOf.call(steps, currentStep);
  return stepIndex === steps.length - 1;
}
```

**Multi-Step Handling**: Only track final step completion:
```javascript
function handleNinjaSubmit(form) {
  if (isMultiStepNinjaForm(form) && !isNinjaFinalStep(form)) {
    log('Ninja multi-step form - not final step, skipping tracking');
    return;
  }

  captureNinjaFormData(form);
  setupNinjaMutationObserver(form);
  setTimeout(function() { detectNinjaSuccess(form); }, 100);
}
```

---

## Error Handling

### Required Error Handling

**Form Detection Errors**:
```javascript
function safeNinjaFormDetection(form) {
  try {
    return isNinjaForm(form);
  } catch (e) {
    log('Ninja form detection error:', e);
    return false;
  }
}
```

**Field Value Extraction Errors**:
```javascript
function safeGetNinjaFieldValue(form, type) {
  try {
    return getNinjaFieldValue(form, type);
  } catch (e) {
    log('Ninja field extraction error for ' + type + ':', e);
    return '';
  }
}
```

**Success Detection Errors**:
```javascript
function safeDetectNinjaSuccess(form) {
  try {
    detectNinjaSuccess(form);
  } catch (e) {
    log('Ninja success detection error:', e);
  }
}
```

---

## Performance Requirements

### Initialization Performance

**Event Listener Setup**: < 3ms for submit listener
**Form Detection**: < 4ms per form detection
**Data Capture**: < 8ms per form

### Runtime Performance

**Success Detection**: < 15ms per detection attempt
**MutationObserver Setup**: < 5ms per observer
**Field Value Extraction**: < 10ms per form

### Memory Management

**Observer Cleanup**: Disconnect observers after use
**Attribute Cleanup**: Remove tracking attributes after processing
**Timeout Management**: Clear timeouts to prevent memory leaks

---

## Configuration Options

### Debug Configuration

```javascript
window.cuftNinja = {
  console_logging: false,
  generate_lead_enabled: true,
  success_detection_timeout: 10000,
  retry_detection_interval: 200,
  track_multi_step_progress: false
};
```

---

## Testing Requirements

### Required Test Scenarios

1. **Single Page Form**
2. **Multi-Step Form** (final step only)
3. **Multiple Ninja Forms on Same Page**
4. **Form with Custom Field Names**
5. **AJAX Success/Failure Scenarios**

### Expected DataLayer Events

```javascript
{
  event: "form_submit",
  form_type: "ninja",
  form_id: "ninja-form-1",
  form_name: "Contact Form",
  user_email: "test@example.com",
  user_phone: "123-456-7890",
  submitted_at: "2025-01-01T12:00:00.000Z",
  cuft_tracked: true,
  cuft_source: "ninja_forms"
}
```

---

## Compliance Validation

### Specification Compliance Checklist

- [ ] Framework detection with silent exit
- [ ] Submit-based event handling
- [ ] Multiple success detection methods
- [ ] Field value extraction for .nf-field structure
- [ ] Multi-step form support (final step only)
- [ ] Pre-submission data capture
- [ ] MutationObserver cleanup
- [ ] Performance requirements met
- [ ] Error handling for all operations
- [ ] DataLayer events conform to core specification

This specification ensures reliable tracking for Ninja Forms while handling its unique AJAX submission architecture.