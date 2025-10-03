# Testing Documentation

## Overview

This document describes the testing procedures and test files available for the Choice Universal Form Tracker plugin, with a focus on Elementor forms tracking.

## Test Files

### 1. test-elementor-complete.html

**Purpose**: Comprehensive test suite for all tracking requirements

**Features**:
- Requirements checklist with visual status indicators
- Quick setup scenarios (complete/partial data)
- Manual data configuration
- Real-time tracking data display
- Form submission testing with optional fields
- DataLayer event logging with export capability

**How to Use**:
1. Open the file in a browser
2. Click "Setup Complete Scenario" to populate all required data
3. Submit the form to test tracking
4. Check the requirements checklist for validation
5. Review the DataLayer events log

**Test Scenarios**:
- ✅ Complete scenario: All data present (click_id, email, phone)
- ⚠️ Partial scenario: Missing phone (generate_lead should not fire)
- ❌ Empty scenario: No tracking data

### 2. test-fallback-chain.html

**Purpose**: Test the data retrieval fallback mechanism

**Features**:
- Visual display of all data sources
- Individual controls for each data source
- Real-time data source monitoring
- Fallback priority testing

**How to Use**:
1. Open the file in a browser
2. Use controls to set/clear different data sources
3. Click "Run Fallback Test" to see which source is used
4. Observe the priority: URL → SessionStorage → Cookie → Empty

**Test Cases**:
- Set only cookies - should retrieve from cookies
- Set SessionStorage and cookies - should prefer SessionStorage
- Set URL params - should always use URL first
- Clear all - should gracefully return empty object

### 3. test-elementor-form.html

**Purpose**: Basic form submission testing

**Features**:
- Simple Elementor form mock
- DataLayer event display
- Basic tracking validation

**How to Use**:
1. Open with URL parameters for testing
2. Submit the form
3. Check console and DataLayer display

### 4. debug-tracking.html

**Purpose**: Debug tracking data retrieval

**Features**:
- Session storage inspection
- Function availability testing
- Payload enrichment testing

## Manual Testing Checklist

### Pre-Deployment Testing

#### Basic Functionality
- [ ] Form submissions trigger `form_submit` event
- [ ] Event fires only once per submission
- [ ] Form ID and name are captured correctly
- [ ] Timestamp is in ISO format

#### UTM & Click ID Tracking
- [ ] UTM parameters captured from URL
- [ ] UTM parameters retrieved from SessionStorage
- [ ] UTM parameters retrieved from cookies
- [ ] Click IDs are properly captured
- [ ] Generic click_id field populated when any click ID present

#### Generate Lead Event
- [ ] Fires when email + phone + click_id present
- [ ] Does NOT fire when any requirement missing
- [ ] Console shows clear reason when not firing
- [ ] Includes all required GA4 parameters

#### Fallback Mechanism
- [ ] URL parameters take priority
- [ ] Falls back to SessionStorage when URL empty
- [ ] Falls back to cookies when SessionStorage empty
- [ ] Returns empty object when no data available
- [ ] No errors thrown during fallback

#### Browser Compatibility
- [ ] Works in Chrome
- [ ] Works in Firefox
- [ ] Works in Safari
- [ ] Works in Edge
- [ ] Works without jQuery
- [ ] Works with jQuery present

#### Elementor Compatibility
- [ ] Works with Elementor 3.5+
- [ ] Works with older Elementor versions
- [ ] Works with Elementor Pro
- [ ] Works with free Elementor (if applicable)

### Console Testing Commands

Run these commands in the browser console to verify functionality:

```javascript
// 1. Check if functions are loaded
console.log('Has tracking function:', typeof cuftGetTrackingData);
console.log('Has jQuery:', typeof jQuery);

// 2. Get current tracking data
cuftGetTrackingData();

// 3. Check session storage
JSON.parse(sessionStorage.getItem('cuft_tracking_data'));

// 4. Check cookies
document.cookie.split(';').find(c => c.includes('cuft_utm'));

// 5. Enable debug mode
window.cuftElementor = { console_logging: true };

// 6. Manually trigger form success
if (jQuery) {
  jQuery(document).trigger('submit_success', [{success: true}]);
}

// 7. Check dataLayer
window.dataLayer;

// 8. Filter dataLayer for form events
window.dataLayer.filter(e => e.event === 'form_submit');
```

### Testing with URL Parameters

Test URLs to verify parameter capture:

```
// Basic UTM parameters
?utm_source=test&utm_medium=email&utm_campaign=test_campaign

// With click ID
?utm_campaign=test&click_id=test123

// With Google click ID
?utm_campaign=test&gclid=google123

// Multiple click IDs (should use first one)
?gclid=google123&fbclid=meta456

// Full parameter set
?utm_source=google&utm_medium=cpc&utm_campaign=summer_sale&utm_term=keyword&utm_content=ad1&gclid=abc123
```

## Automated Testing Scenarios

### Scenario 1: Complete Flow Test

```javascript
// Setup
sessionStorage.setItem('cuft_tracking_data', JSON.stringify({
  tracking: {
    utm_campaign: 'test',
    click_id: 'test123'
  },
  timestamp: Date.now()
}));

// Submit form with email and phone
// Expected: Both form_submit and generate_lead events fire
```

### Scenario 2: Partial Data Test

```javascript
// Setup - No click ID
sessionStorage.setItem('cuft_tracking_data', JSON.stringify({
  tracking: {
    utm_campaign: 'test'
  },
  timestamp: Date.now()
}));

// Submit form with email and phone
// Expected: Only form_submit fires, generate_lead skipped
```

### Scenario 3: Fallback Test

```javascript
// Clear all data sources
sessionStorage.clear();
document.cookie = 'cuft_utm_data=; expires=Thu, 01 Jan 1970 00:00:00 UTC';

// Get tracking data
cuftGetTrackingData();
// Expected: Returns empty object {}
```

## Performance Testing

### Metrics to Monitor

1. **Event Firing Latency**: Time between form submission and dataLayer push
2. **Memory Usage**: Check for memory leaks with repeated submissions
3. **CPU Usage**: Monitor during form submission
4. **Network Requests**: Verify no unnecessary requests

### Load Testing

```javascript
// Simulate multiple rapid form submissions
for (let i = 0; i < 10; i++) {
  setTimeout(() => {
    jQuery(document).trigger('submit_success', [{success: true}]);
  }, i * 100);
}
// Expected: Each submission tracked separately, no duplicates
```

## Debugging Guide

### Common Issues and Solutions

| Issue | Possible Cause | Solution |
|-------|---------------|----------|
| No events firing | Scripts not loaded | Check script tags and load order |
| Duplicate events | Multiple listeners | Check for duplicate script inclusion |
| Missing UTM data | Incorrect fallback | Verify data source priority |
| Generate lead not firing | Missing required field | Check console for specific missing field |
| jQuery errors | jQuery not loaded | Ensure jQuery fallback works |

### Debug Mode Output

When debug mode is enabled (`window.cuftElementor.console_logging = true`), you should see:

```
[CUFT Elementor] Event listeners setup complete: [...]
[CUFT Elementor] Native JS submit_success event detected {...}
[CUFT Elementor] Form submission tracked: {...}
[CUFT Elementor] Generate lead event fired: {...}
[CUFT UTM] Checking URL for tracking parameters...
[CUFT UTM] Tracking data found in SessionStorage: {...}
[CUFT UTM Utils] Added tracking fields to payload: utm_campaign, click_id
```

## Regression Testing

Before each release, verify:

1. **Backward Compatibility**: Test with older Elementor versions
2. **Data Migration**: Ensure existing tracked data still works
3. **Configuration**: Verify settings are preserved after update
4. **Third-party Conflicts**: Test with common plugins
5. **Theme Compatibility**: Test with popular themes

## Testing Dashboard Form Builder (v3.14.0)

### Overview

The Testing Dashboard Form Builder feature allows administrators to generate real test forms within any installed form framework, populate them with test data, and validate tracking functionality without affecting production analytics.

### Accessing the Form Builder

1. Log into WordPress admin as an administrator
2. Navigate to **Settings → Universal Form Tracker**
3. Click on the **Testing Dashboard** tab
4. Scroll to the **Test Form Builder** section

### Quick Testing Workflow

#### 1. Create a Test Form

```
1. Select framework from dropdown (e.g., "Elementor Pro")
2. Select template ("Basic Contact Form")
3. Click "Create Test Form"
4. Wait for confirmation message
5. Form loads automatically in iframe
```

**Expected Result**: Form appears in iframe with test mode indicator

#### 2. Populate Test Data

```
1. Click "Populate Test Data" button
2. Observe fields auto-fill:
   - Name: "Test User"
   - Email: test-[timestamp]@example.com
   - Phone: "555-0123"
   - Message: "This is a test submission from CUFT Testing Dashboard"
```

**Expected Result**: All fields populated instantly, input/change events triggered

#### 3. Submit and Validate

```
1. Click form's submit button (or "Submit Form" button)
2. Check "Captured Events" panel
3. Verify event structure:
   - event: "form_submit"
   - cuft_tracked: true
   - cuft_source: "framework_name"
   - All fields use snake_case
4. Check validation results panel
```

**Expected Result**:
- Events captured and displayed
- Validation shows all compliance checks passed
- No real emails sent (check Mailcatcher)

#### 4. Clean Up

```
1. Click "Delete Test Form" button
2. Confirm deletion
3. Form removed from system
```

**Expected Result**: Form deleted, iframe clears, success message shown

### Framework-Specific Testing

#### Elementor Pro Forms

```javascript
// Test Elementor form generation
1. Create Elementor test form
2. Verify widget JSON structure in post meta
3. Check form renders correctly in iframe
4. Test field population with Elementor-specific selectors
5. Validate submit_success event capture
6. Verify no real Elementor actions triggered
```

**Key Validations**:
- [ ] Form created with `elementor_library` post type
- [ ] `_elementor_data` meta contains form widget
- [ ] Form renders in iframe without errors
- [ ] Fields populate using `.elementor-field` selectors
- [ ] `submit_success` event captured
- [ ] cuft_source: "elementor_pro"

#### Contact Form 7

```javascript
// Test CF7 form generation
1. Create CF7 test form
2. Verify shortcode generation
3. Check form tag structure
4. Test field population with CF7 selectors
5. Validate wpcf7mailsent event capture
6. Verify emails blocked (wpcf7_skip_mail filter)
```

**Key Validations**:
- [ ] Form created with `wpcf7_contact_form` post type
- [ ] Shortcode available and functional
- [ ] Fields use CF7 naming convention
- [ ] `wpcf7mailsent` event captured
- [ ] No emails sent (test mode active)
- [ ] cuft_source: "contact_form_7"

#### Gravity Forms

```javascript
// Test Gravity Forms generation
1. Create Gravity test form
2. Verify form created via GFAPI
3. Check form fields structure
4. Test field population with GF selectors
5. Validate gform_confirmation event
6. Verify emails blocked (gform_pre_send_email filter)
```

**Key Validations**:
- [ ] Form created using GFAPI::add_form()
- [ ] Form ID returned correctly
- [ ] Fields use Gravity naming: input_[form_id]_[field_id]
- [ ] Submission captured correctly
- [ ] No emails sent (test mode active)
- [ ] cuft_source: "gravity_forms"

#### Ninja Forms

```javascript
// Test Ninja Forms generation
1. Create Ninja test form
2. Verify form creation via Ninja API
3. Check field configuration
4. Test field population with NF selectors
5. Validate nfFormSubmitResponse event
6. Verify no real actions triggered
```

**Key Validations**:
- [ ] Form created using Ninja_Forms()->form()->save()
- [ ] Fields use .nf-field selectors
- [ ] Submission data captured
- [ ] No real actions executed
- [ ] cuft_source: "ninja_forms"

#### Avada/Fusion Forms

```javascript
// Test Avada Forms generation
1. Create Avada test form
2. Verify Fusion element structure
3. Check form rendering
4. Test field population with Fusion selectors
5. Validate form submission event
6. Verify no real actions triggered
```

**Key Validations**:
- [ ] Form created with `fusion_form` post type
- [ ] Fields use .fusion-form-field selectors
- [ ] Submission captured correctly
- [ ] No real actions executed
- [ ] cuft_source: "avada_forms"

### PostMessage Protocol Testing

#### Dashboard → Iframe Communication

Test sending messages to iframe:

```javascript
// 1. Test field population message
const iframe = document.getElementById('cuft-test-iframe');
iframe.contentWindow.postMessage({
  action: 'cuft_populate_fields',
  nonce: cuftFormBuilder.nonce,
  data: {
    fields: { email: 'test@example.com' },
    options: { trigger_events: true }
  }
}, window.location.origin);

// Expected: Fields populate in iframe

// 2. Test submit trigger
iframe.contentWindow.postMessage({
  action: 'cuft_trigger_submit',
  nonce: cuftFormBuilder.nonce
}, window.location.origin);

// Expected: Form submits automatically
```

#### Iframe → Dashboard Communication

Test receiving messages from iframe:

```javascript
// Listen for form loaded message
window.addEventListener('message', (event) => {
  if (event.data.action === 'cuft_form_loaded') {
    console.log('Form loaded:', event.data.data);
    // Expected: { framework: 'elementor', form_id: '...', ready: true }
  }

  if (event.data.action === 'cuft_form_submitted') {
    console.log('Form submitted:', event.data.data);
    // Expected: { form_data: {...}, tracking_event: {...} }
  }
});
```

### AJAX Endpoint Testing

#### Create Test Form Endpoint

```javascript
// Test form creation
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'cuft_create_test_form',
    nonce: cuftFormBuilder.nonce,
    framework: 'elementor',
    template_id: 'basic_contact_form'
  }
}).done((response) => {
  console.log('Create response:', response);
  // Expected: { success: true, data: { instance_id, form_id, url } }
});
```

#### Get Test Forms Endpoint

```javascript
// Retrieve all test forms
jQuery.ajax({
  url: ajaxurl,
  type: 'GET',
  data: {
    action: 'cuft_get_test_forms',
    nonce: cuftFormBuilder.nonce,
    status: 'active'
  }
}).done((response) => {
  console.log('Forms:', response);
  // Expected: { success: true, data: { forms: [...], total: N } }
});
```

#### Delete Test Form Endpoint

```javascript
// Delete a test form
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'cuft_delete_test_form',
    nonce: cuftFormBuilder.nonce,
    instance_id: 'cuft_test_abc123'
  }
}).done((response) => {
  console.log('Delete response:', response);
  // Expected: { success: true, data: { message: '...' } }
});
```

### Validation Testing

#### Constitutional Compliance Checks

The validator checks for:

```javascript
// 1. cuft_tracked field present
event.cuft_tracked === true

// 2. cuft_source field present
event.cuft_source === 'framework_name'

// 3. Snake_case naming
event.form_type (not formType)
event.user_email (not userEmail)

// 4. Required fields
event.form_id
event.submitted_at

// 5. Generate lead requirements (if applicable)
event.user_email && event.user_phone && event.click_id
```

**Run validation**:
```javascript
const validator = new CUFT_Form_Builder_Validator();
const result = validator.validate(capturedEvent);
console.log('Validation:', result);
// Expected: { valid: true, errors: [], warnings: [] }
```

### Performance Testing

#### Benchmark Targets

| Operation | Target | How to Measure |
|-----------|--------|----------------|
| Form generation | < 100ms | AJAX response time |
| Iframe load | < 500ms | iframe.onload timestamp |
| Field population | < 50ms | postMessage round-trip |
| Event capture | < 10ms | dataLayer.push latency |

#### Performance Measurement Script

```javascript
// Measure form generation
const start = performance.now();
await jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: { action: 'cuft_create_test_form', ... }
});
const duration = performance.now() - start;
console.log(`Form generation: ${duration}ms`); // Target: < 100ms

// Measure iframe load
iframe.onload = () => {
  const loadTime = performance.now() - iframeStartTime;
  console.log(`Iframe load: ${loadTime}ms`); // Target: < 500ms
};

// Measure field population
const popStart = performance.now();
iframe.contentWindow.postMessage(...);
// Listen for populated confirmation
window.addEventListener('message', (e) => {
  if (e.data.action === 'cuft_fields_populated') {
    const popTime = performance.now() - popStart;
    console.log(`Field population: ${popTime}ms`); // Target: < 50ms
  }
});
```

### Cross-Browser Testing

Test in multiple browsers:

- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

**Key areas to verify**:
- PostMessage communication works
- Iframe rendering correct
- Field population successful
- Events captured properly
- No console errors

### Security Testing

#### Nonce Validation

```javascript
// Test without nonce - should fail
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'cuft_create_test_form',
    // nonce: missing!
    framework: 'elementor'
  }
}).fail((xhr) => {
  console.log('Expected error:', xhr.responseJSON);
  // Expected: { success: false, data: 'Nonce verification failed' }
});
```

#### Capability Checks

```javascript
// Test as non-admin user - should fail
// (Switch to subscriber account)
jQuery.ajax({
  url: ajaxurl,
  type: 'POST',
  data: {
    action: 'cuft_create_test_form',
    nonce: cuftFormBuilder.nonce,
    framework: 'elementor'
  }
}).fail((xhr) => {
  console.log('Expected error:', xhr.responseJSON);
  // Expected: 403 Insufficient permissions
});
```

#### Origin Validation

```javascript
// Simulate message from different origin
const event = new MessageEvent('message', {
  origin: 'http://evil.com',
  data: { action: 'cuft_form_loaded' }
});
window.dispatchEvent(event);

// Expected: Console warning, message ignored
```

### Automated Integration Test

Run this complete test sequence:

```javascript
async function runFormBuilderIntegrationTest() {
  console.log('Starting Form Builder Integration Test...');

  // 1. Create form
  const createResponse = await jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
      action: 'cuft_create_test_form',
      nonce: cuftFormBuilder.nonce,
      framework: 'elementor',
      template_id: 'basic_contact_form'
    }
  });

  console.assert(createResponse.success, '✅ Form created');
  const { instance_id } = createResponse.data;

  // 2. Wait for iframe load
  await new Promise(resolve => setTimeout(resolve, 2000));
  console.log('✅ Iframe loaded');

  // 3. Populate fields
  const iframe = document.querySelector('#cuft-test-iframe');
  iframe.contentWindow.postMessage({
    action: 'cuft_populate_fields',
    nonce: cuftFormBuilder.nonce,
    data: {
      fields: { email: 'test@example.com', phone: '555-0123' }
    }
  }, window.location.origin);

  await new Promise(resolve => setTimeout(resolve, 500));
  console.log('✅ Fields populated');

  // 4. Submit form
  iframe.contentWindow.postMessage({
    action: 'cuft_trigger_submit',
    nonce: cuftFormBuilder.nonce
  }, window.location.origin);

  // 5. Wait for submission
  await new Promise(resolve => {
    window.addEventListener('message', function handler(e) {
      if (e.data.action === 'cuft_form_submitted') {
        console.log('✅ Form submitted');
        console.assert(e.data.data.tracking_event.cuft_tracked, '✅ Tracking event valid');
        window.removeEventListener('message', handler);
        resolve();
      }
    });
  });

  // 6. Delete form
  await jQuery.ajax({
    url: ajaxurl,
    type: 'POST',
    data: {
      action: 'cuft_delete_test_form',
      nonce: cuftFormBuilder.nonce,
      instance_id: instance_id
    }
  });
  console.log('✅ Form deleted');

  console.log('🎉 Integration test completed successfully!');
}

// Run the test
runFormBuilderIntegrationTest();
```

### Troubleshooting Form Builder

| Issue | Possible Cause | Solution |
|-------|---------------|----------|
| Form won't create | Framework not active | Verify plugin is activated |
| Iframe shows 404 | Routing not flushed | Run `wp rewrite flush` |
| Fields won't populate | Wrong selectors | Check framework-specific selectors |
| Events not captured | Test mode not active | Verify `?test_mode=1` in URL |
| No validation results | Validator not loaded | Check class instantiation |

### Test Reporting (Form Builder)

```markdown
## Form Builder Test Report - [Date]

### Environment
- WordPress: [version]
- Framework: [name and version]
- Browser: [name and version]
- Form Builder Version: 3.14.0

### Test Results
- [ ] Form creation successful
- [ ] Iframe loading correct
- [ ] Field population working
- [ ] PostMessage communication functional
- [ ] Event capture accurate
- [ ] Validation passing
- [ ] No real actions triggered
- [ ] Cleanup successful

### Performance Metrics
- Form generation: [X]ms (target: < 100ms)
- Iframe load: [X]ms (target: < 500ms)
- Field population: [X]ms (target: < 50ms)
- Event capture: [X]ms (target: < 10ms)

### Issues Found
[List any issues]

### Notes
[Additional observations]
```

---

## Test Reporting

Document test results using this template:

```markdown
## Test Report - [Date]

### Environment
- WordPress: [version]
- Elementor: [version]
- Browser: [name and version]
- jQuery: [present/absent]

### Test Results
- [ ] Basic form tracking
- [ ] UTM parameter capture
- [ ] Click ID tracking
- [ ] Generate lead event
- [ ] Fallback mechanism
- [ ] Browser compatibility

### Issues Found
[List any issues]

### Notes
[Additional observations]
```