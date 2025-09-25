# Elementor Forms Tracking Specification

## Version: 1.0

## Date: 2025-09-25

## Status: Active

## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized tracking implementation for Elementor Pro Forms within the Choice Universal Form Tracker. Elementor is the primary focus framework and serves as the reference implementation for all other form frameworks.

---

## Framework Identification

### Framework Detection

**Primary Detection**: Forms MUST be identified using Elementor-specific CSS classes and attributes:

```javascript
function isElementorForm(form) {
  return (
    form &&
    (form.classList.contains("elementor-form") ||
      form.closest(".elementor-widget-form") !== null ||
      form.getAttribute("data-settings") !== null)
  );
}
```

**Required Early Exit**: If form is not Elementor, script MUST exit silently without logging:

```javascript
if (!isElementorForm(form)) {
  return; // Silent exit - no console output
}
```

### Framework Identifiers

**DataLayer Event Values**:

- `form_type`: `"elementor"`
- `cuft_source`: `"elementor_pro"` (form_submit events)
- `cuft_source`: `"elementor_pro_lead"` (generate_lead events)

---

## Event Handling

### Primary Event Listeners

**Event Priority Order** (MUST be implemented in this sequence):

1. **Native JavaScript Events** (Elementor 3.5+)

   ```javascript
   document.addEventListener("submit_success", function (event) {
     handleNativeSuccessEvent(event, event.detail);
   });
   ```

2. **jQuery Events** (Legacy Elementor < 3.5)

   ```javascript
   if (window.jQuery) {
     jQuery(document).on("submit_success", function (event, response) {
       handleJQuerySuccessEvent(event, response);
     });
   }
   ```

3. **Fallback Methods** (Edge cases only)
   - MutationObserver for success message detection
   - Form submit listeners with ajax response monitoring
   - Popup hide events for forms in popups

### Event Detection Methods

**Multiple Detection Layers** (implement all for maximum compatibility):

1. **Event Target Traversal**

   ```javascript
   function findFormFromEvent(event) {
     var form =
       event.target && event.target.closest
         ? event.target.closest(".elementor-form")
         : null;
     return form;
   }
   ```

2. **Pending Tracking Attribute**

   ```javascript
   function findPendingForm() {
     return document.querySelector(
       '.elementor-form[data-cuft-tracking="pending"]'
     );
   }
   ```

3. **Visible Form Detection**

   ```javascript
   function findVisibleElementorForm() {
     var forms = document.querySelectorAll(".elementor-form");
     return Array.from(forms).find(
       (form) => form.offsetHeight > 0 && form.offsetWidth > 0
     );
   }
   ```

4. **Recent Interaction Detection**
   ```javascript
   function findRecentlyInteractedForm() {
     // Find forms with recent user interaction
     // Implementation tracks last focused form
   }
   ```

---

## Field Value Extraction

### Email Field Detection

**Email field MUST be detected using multiple methods** (in priority order):

```javascript
function getEmailField(form) {
  var selectors = [
    'input[type="email"]',
    'input[inputmode="email"]',
    'input[data-field-type="email"]',
    'input[data-field="email"]',
    'input[name*="email" i]',
    'input[name="form_fields[email]"]',
    'input[id*="email" i]',
    'input[placeholder*="email" i]',
    'input[aria-label*="email" i]',
    'input[pattern*="@"]',
    'textarea[placeholder*="email" i]',
  ];

  for (var i = 0; i < selectors.length; i++) {
    var field = form.querySelector(selectors[i]);
    if (field && field.value) return field;
  }

  return null;
}
```

**Label-Based Detection**:

```javascript
function getEmailFieldByLabel(form) {
  var labels = form.querySelectorAll("label");
  for (var i = 0; i < labels.length; i++) {
    var labelText = labels[i].textContent.toLowerCase();
    if (labelText.includes("email") || labelText.includes("e-mail")) {
      var forId = labels[i].getAttribute("for");
      if (forId) {
        var field = form.querySelector("#" + forId);
        if (field && field.value) return field;
      }
    }
  }
  return null;
}
```

### Phone Field Detection

**Phone field MUST be detected using multiple methods**:

```javascript
function getPhoneField(form) {
  var selectors = [
    'input[type="tel"]',
    'input[inputmode="tel"]',
    'input[data-field-type="tel"]',
    'input[data-field-type="phone"]',
    'input[data-field="phone"]',
    'input[name*="phone" i]',
    'input[name*="tel" i]',
    'input[name="form_fields[phone]"]',
    'input[id*="phone" i]',
    'input[id*="tel" i]',
    'input[placeholder*="phone" i]',
    'input[aria-label*="phone" i]',
    'input[pattern*="[0-9]"]', // Common phone patterns
  ];

  for (var i = 0; i < selectors.length; i++) {
    var field = form.querySelector(selectors[i]);
    if (field && field.value) return field;
  }

  return null;
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

**Form ID MUST be extracted using fallback hierarchy**:

```javascript
function getFormId(form) {
  return (
    form.getAttribute("data-form-id") ||
    form.getAttribute("id") ||
    form.getAttribute("data-elementor-form-id") ||
    getHiddenInputValue(form, "form_id") ||
    generateFallbackId(form)
  );
}

function generateFallbackId(form) {
  // Generate consistent ID based on form characteristics
  var widget = form.closest(".elementor-widget");
  if (widget) {
    var widgetId = widget.getAttribute("data-id");
    if (widgetId) return "elementor-widget-" + widgetId;
  }

  // Last resort: use form position in DOM
  var forms = document.querySelectorAll(".elementor-form");
  var index = Array.prototype.indexOf.call(forms, form);
  return "elementor-form-" + (index + 1);
}
```

### Form Name Extraction

**Form Name MUST be extracted using fallback hierarchy**:

```javascript
function getFormName(form) {
  return (
    form.getAttribute("data-form-name") ||
    form.getAttribute("name") ||
    form.getAttribute("aria-label") ||
    getHiddenInputValue(form, "form_name") ||
    getWidgetTypeLabel(form) ||
    "Elementor Form"
  );
}

function getWidgetTypeLabel(form) {
  var widget = form.closest(".elementor-widget");
  if (widget) {
    var widgetType = widget.getAttribute("data-widget_type");
    if (widgetType) {
      return widgetType
        .replace(/_/g, " ")
        .replace(/\b\w/g, (l) => l.toUpperCase());
    }
  }
  return null;
}
```

---

## Multi-Step Form Handling

### Step Detection

**Multi-step forms MUST only fire events on final step completion**:

```javascript
function isMultiStepForm(form) {
  return form.querySelector(".elementor-field-type-step") !== null;
}

function isFinalStep(form) {
  var steps = form.querySelectorAll(".elementor-field-type-step");
  var currentStep = form.querySelector(
    ".elementor-field-type-step.e-field-active"
  );

  if (!steps.length || !currentStep) return true; // Not multi-step or no active step

  var currentIndex = Array.prototype.indexOf.call(steps, currentStep);
  return currentIndex === steps.length - 1;
}
```

**Implementation Rule**: Only process form submission if `isFinalStep(form)` returns true.

### Step Progress Tracking

**Optional Step Tracking** (if required for advanced analytics):

```javascript
function trackStepProgress(form, stepNumber, totalSteps) {
  if (window.cuftElementor && window.cuftElementor.track_steps) {
    getDL().push({
      event: "form_step_completed",
      form_type: "elementor",
      form_id: getFormId(form),
      step_number: stepNumber,
      total_steps: totalSteps,
      cuft_tracked: true,
      cuft_source: "elementor_pro_step",
    });
  }
}
```

---

## Popup Form Handling

### Popup Detection

**Popup forms require special handling**:

```javascript
function isInPopup(form) {
  return form.closest(".elementor-popup-modal") !== null;
}
```

### Popup Event Listeners

**Additional event listeners for popup forms**:

```javascript
function setupPopupEventListeners() {
  if (window.jQuery) {
    // Listen for popup hide events
    jQuery(document).on("elementor/popup/hide", function (event, id, instance) {
      handlePopupHide(id, instance);
    });
  }
}

function handlePopupHide(popupId, instance) {
  // Check if popup contains recently submitted form
  var popup = document.querySelector('[data-elementor-id="' + popupId + '"]');
  if (popup) {
    var form = popup.querySelector(
      '.elementor-form[data-cuft-tracking="pending"]'
    );
    if (form) {
      handleClientSideTracking(form);
    }
  }
}
```

---

## AJAX Response Handling

### Server-Side Tracking Data

**If server provides tracking data in response**:

```javascript
function handleServerTrackingData(form, trackingData) {
  if (trackingData && trackingData.cuft_tracking) {
    log("Using server-provided tracking data:", trackingData);

    var payload = {
      event: "form_submit",
      form_type: "elementor",
      form_id: trackingData.form_id || getFormId(form),
      form_name: trackingData.form_name || getFormName(form),
      user_email: trackingData.email || "",
      user_phone: trackingData.phone || "",
      submitted_at: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "elementor_pro",
    };

    // Add UTM and click ID data
    if (window.cuftUtmUtils) {
      payload = window.cuftUtmUtils.addUtmToPayload(payload);
    }

    getDL().push(payload);
  }
}
```

### Client-Side Tracking Fallback

**When no server data available**:

```javascript
function handleClientSideTracking(form) {
  var email = getStoredFormData(form, "email") || getFieldValue(form, "email");
  var phone = getStoredFormData(form, "phone") || getFieldValue(form, "phone");

  pushToDataLayer(form, email, phone);

  // Clear tracking attributes
  form.removeAttribute("data-cuft-tracking");
  form.removeAttribute("data-cuft-email");
  form.removeAttribute("data-cuft-phone");
}
```

---

## Pattern Validation Fix

### Native Validation Approach

**Forms rely on browser's native HTML5 validation instead of custom pattern fixing**:

```javascript
// Email field detection prioritizes type="email" and semantic indicators
function getEmailField(form) {
  var selectors = [
    'input[type="email"]', // Native HTML5 email validation
    'input[inputmode="email"]', // Mobile keyboard hint
    'input[data-field-type="email"]', // Elementor field type
    'input[name*="email" i]', // Semantic naming
    'input[placeholder*="email" i]', // Placeholder hints
  ];

  for (var i = 0; i < selectors.length; i++) {
    var field = form.querySelector(selectors[i]);
    if (field && field.value) return field;
  }
  return null;
}

// Phone field detection uses semantic indicators without pattern validation
function getPhoneField(form) {
  var selectors = [
    'input[type="tel"]', // Native HTML5 tel input
    'input[inputmode="tel"]', // Mobile keyboard hint
    'input[data-field-type="tel"]', // Elementor field type
    'input[name*="phone" i]', // Semantic naming
    'input[placeholder*="phone" i]', // Placeholder hints
  ];

  for (var i = 0; i < selectors.length; i++) {
    var field = form.querySelector(selectors[i]);
    if (field && field.value) return field;
  }
  return null;
}
```

**Benefits of native validation**:

- Browser handles validation automatically
- No need to fix invalid regex patterns
- Better accessibility and user experience
- Consistent validation across all browsers

---

## Error Handling

### Required Error Handling

**Form Detection Errors**:

```javascript
function safeFormDetection() {
  try {
    return (
      findFormFromEvent(event) ||
      findPendingForm() ||
      findVisibleElementorForm()
    );
  } catch (e) {
    log("Form detection error:", e);
    return null;
  }
}
```

**Field Value Extraction Errors**:

```javascript
function safeGetFieldValue(form, type) {
  try {
    return getFieldValue(form, type);
  } catch (e) {
    log("Field value extraction error for " + type + ":", e);
    return null;
  }
}
```

**DataLayer Push Errors**:

```javascript
function safePushToDataLayer(payload) {
  try {
    getDL().push(payload);
    log("Form submission tracked:", payload);
  } catch (e) {
    log("DataLayer push error:", e);
  }
}
```

### Validation Errors

**Form Validation State**: Do NOT track forms that fail Elementor's built-in validation
**Network Errors**: Handle AJAX submission failures gracefully
**Missing Elements**: Continue tracking even if optional elements are missing

---

## Performance Requirements

### Initialization Performance

**Pattern Fixing**: < 5ms for pattern validation and fixing
**Event Listener Setup**: < 2ms for all event listeners
**Form Detection**: < 3ms per form detection attempt

### Runtime Performance

**Field Value Extraction**: < 10ms per form
**Form Identification**: < 5ms per form
**Event Processing**: < 15ms total per form submission

### Memory Management

**Event Listener Cleanup**: Remove listeners on form removal (if dynamic)
**Attribute Cleanup**: Remove tracking attributes after processing
**Cache Management**: Clear field value caches after submission

---

## Configuration Options

### Debug Configuration

```javascript
window.cuftElementor = {
  console_logging: false, // Enable debug logging
  generate_lead_enabled: true, // Enable generate_lead events
  track_steps: false, // Track multi-step progress
  use_native_methods: true, // Prefer native over jQuery
  pattern_fix_enabled: true, // Enable pattern fixing
  popup_tracking_enabled: true, // Enable popup form tracking
};
```

### Field Detection Configuration

```javascript
window.cuftElementor.field_config = {
  email_selectors: [
    /* custom selectors */
  ],
  phone_selectors: [
    /* custom selectors */
  ],
  custom_field_mappings: {
    custom_email_field: "email",
    custom_phone_field: "phone",
  },
};
```

---

## Testing Requirements

### Required Test Scenarios

1. **Standard Form Submission**

   - Single-step form with email and phone
   - Verify form_submit event fires
   - Verify generate_lead fires when click_id present

2. **Multi-Step Form Submission**

   - Complete multi-step form
   - Verify events fire only on final step

3. **Popup Form Submission**

   - Form within Elementor popup
   - Verify tracking works on popup close

4. **Field Detection Accuracy**

   - Various email field configurations
   - Various phone field configurations
   - Edge cases (textareas, custom fields)

5. **Error Handling**

   - Invalid form patterns
   - Missing required elements
   - Network failures

6. **Cross-Framework Compatibility**
   - Page with Elementor + other forms
   - Verify no interference or console noise

### Test Data Requirements

**Test Form Configurations**:

- Simple contact form
- Multi-step lead generation form
- Popup subscription form
- Form with custom field names
- Form with invalid patterns

**Expected DataLayer Events**:

```javascript
// Standard form_submit event
{
  event: "form_submit",
  form_type: "elementor",
  form_id: "elementor-widget-7a2c4f9",
  form_name: "Contact Form",
  user_email: "test@example.com",
  user_phone: "123-456-7890",
  submitted_at: "2025-01-01T12:00:00.000Z",
  cuft_tracked: true,
  cuft_source: "elementor_pro",
  // UTM parameters if available
  // Click IDs if available
}

// generate_lead event (when email + phone + click_id present)
{
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_tracked: true,
  cuft_source: "elementor_pro_lead",
  // All form_submit fields included
}
```

---

## Compliance Validation

### Specification Compliance Checklist

- [ ] Framework detection with silent exit for non-Elementor forms
- [ ] Multiple event listener types (native, jQuery, fallbacks)
- [ ] Comprehensive field detection for email and phone
- [ ] Multi-step form handling (final step only)
- [ ] Popup form support with appropriate event listeners
- [ ] Pattern validation fixing for problematic regex patterns
- [ ] Complete error handling for all operations
- [ ] Performance requirements met (< 15ms total processing)
- [ ] Memory cleanup and attribute management
- [ ] DataLayer events conform to core specification
- [ ] Testing scenarios cover all use cases

This specification ensures robust, reliable tracking for Elementor Pro Forms while serving as the reference implementation for all other form framework specifications.
