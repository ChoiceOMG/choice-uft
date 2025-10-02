# Quick Start Guide: Testing Dashboard Form Builder

## Prerequisites
- WordPress 5.0+ with administrator access
- Choice Universal Form Tracker plugin activated
- At least one form framework installed (Elementor Pro, Contact Form 7, etc.)

## Feature Overview
The Testing Dashboard Form Builder allows you to generate real test forms within your installed form frameworks, display them in an iframe, populate them with test data, and validate that tracking is working correctly.

## Step-by-Step Testing Guide

### 1. Access the Testing Dashboard
1. Log into WordPress admin as an administrator
2. Navigate to **Settings → Universal Form Tracker**
3. Click on the **Testing Dashboard** tab
4. Look for the new **Form Builder** section

### 2. Check Available Frameworks
The dashboard automatically detects installed frameworks:
- ✅ Green checkmark = Framework available and supported
- ❌ Red X = Framework not installed or inactive
- ⚠️ Yellow warning = Framework installed but missing requirements

### 3. Generate a Test Form

#### For Elementor Pro:
```
1. Click "Generate Elementor Test Form"
2. Select "Basic Contact Form" template
3. Click "Create Form"
4. Wait for confirmation: "Test form created successfully"
```

#### For Contact Form 7:
```
1. Click "Generate CF7 Test Form"
2. Select "Basic Contact Form" template
3. Click "Create Form"
4. Wait for confirmation
```

### 4. View Form in Iframe
Once created, the form automatically loads in the testing iframe:
- Form displays within the dashboard
- URL shows: `/cuft-test-form/?form_id=cuft_test_123456&test_mode=1`
- Test mode indicator appears

### 5. Populate with Test Data
1. Click the **"Populate Test Data"** button
2. Observe fields being filled:
   - Name: "Test User"
   - Email: Dynamic test email (e.g., test-1736506800@example.com)
   - Phone: "555-0123"
   - Message: "This is a test submission from CUFT Testing Dashboard"

### 6. Submit and Validate
1. Click the form's submit button
2. Watch the **Event Monitor** panel for:
   ```javascript
   {
     event: "form_submit",
     form_type: "elementor",
     form_id: "elementor-form-123",
     user_email: "test-1736506800@example.com",
     user_phone: "555-0123",
     cuft_tracked: true,
     cuft_source: "elementor_pro",
     submitted_at: "2025-01-10T10:30:00Z"
   }
   ```

### 7. Validation Checklist
✅ **Successful tracking when you see:**
- `cuft_tracked: true` in the event
- `cuft_source` contains framework name
- All field names use snake_case
- Form data captured correctly
- No real emails sent (check Mailcatcher if configured)

❌ **Failed tracking indicators:**
- Missing `cuft_tracked` field
- Events use camelCase instead of snake_case
- Form submission triggers real actions
- JavaScript errors in console

### 8. Clean Up Test Forms
1. Click **"Delete Test Form"** button
2. Confirm deletion when prompted
3. Form removed from system

## Testing Multiple Frameworks

### Sequential Testing
1. Create test form for Framework A
2. Test and validate
3. Delete test form
4. Repeat for Framework B

### Parallel Testing (Advanced)
1. Open multiple browser tabs
2. Create different framework forms in each
3. Test simultaneously
4. Verify no interference between forms

## Troubleshooting

### Form Won't Generate
- **Check**: Is the framework plugin activated?
- **Check**: Do you have admin permissions?
- **Try**: Refresh the page and try again

### Iframe Shows Error
- **Check**: Browser console for JavaScript errors
- **Check**: URL accessibility (try opening directly)
- **Try**: Clear browser cache

### Fields Won't Populate
- **Check**: Are field selectors matching?
- **Check**: Is test mode enabled?
- **Try**: Manually trigger with console:
  ```javascript
  jQuery('#test-iframe')[0].contentWindow.postMessage({
    action: 'cuft_populate_fields',
    data: { fields: { email: 'test@example.com' } }
  }, window.location.origin);
  ```

### No Events Captured
- **Check**: Is tracking script loaded?
- **Check**: Browser console for errors
- **Try**: Check Network tab for blocked requests

## Console Commands for Debugging

### Check if form builder is loaded:
```javascript
console.log(typeof window.CUFTFormBuilder !== 'undefined');
```

### Manually trigger population:
```javascript
CUFTFormBuilder.populateTestData('cuft_test_123456');
```

### View captured events:
```javascript
console.table(CUFTFormBuilder.capturedEvents);
```

### Force test mode:
```javascript
window.cuftTestMode = { enabled: true, preventSubmit: true };
```

## Expected Test Results

### Successful Test Criteria
1. ✅ Form generates within 2 seconds
2. ✅ Iframe loads without errors
3. ✅ All fields populate correctly
4. ✅ Submission captures tracking event
5. ✅ Event contains required CUFT fields
6. ✅ No real actions triggered (emails, webhooks)

### Performance Benchmarks
- Form generation: < 100ms
- Iframe load: < 500ms
- Field population: < 50ms
- Event capture: < 10ms

## Integration Test Script

Run this in the browser console for automated testing:

```javascript
async function testFormBuilder() {
  console.log('Starting Form Builder Test...');

  // Step 1: Create form
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

  if (!createResponse.success) {
    console.error('Failed to create form:', createResponse);
    return;
  }

  console.log('✅ Form created:', createResponse.data.instance_id);

  // Step 2: Wait for iframe load
  await new Promise(resolve => setTimeout(resolve, 2000));

  // Step 3: Populate fields
  const iframe = document.querySelector('#cuft-test-iframe');
  iframe.contentWindow.postMessage({
    action: 'cuft_populate_fields',
    nonce: cuftFormBuilder.nonce,
    data: {
      fields: {
        email: 'test@example.com',
        phone: '555-0123'
      }
    }
  }, window.location.origin);

  console.log('✅ Fields populated');

  // Step 4: Listen for submission
  window.addEventListener('message', function(e) {
    if (e.data.action === 'cuft_form_submitted') {
      console.log('✅ Form submitted with tracking:', e.data.data.tracking_event);

      // Step 5: Cleanup
      jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
          action: 'cuft_delete_test_form',
          nonce: cuftFormBuilder.nonce,
          instance_id: createResponse.data.instance_id
        }
      }).done(() => {
        console.log('✅ Test form cleaned up');
        console.log('Test completed successfully!');
      });
    }
  });

  // Trigger submission
  await new Promise(resolve => setTimeout(resolve, 1000));
  iframe.contentWindow.postMessage({
    action: 'cuft_trigger_submit',
    nonce: cuftFormBuilder.nonce
  }, window.location.origin);
}

// Run the test
testFormBuilder();
```

## Next Steps

After successful testing:
1. Document any framework-specific issues
2. Test with different form templates
3. Verify tracking in Google Tag Manager
4. Test with various click IDs and UTM parameters
5. Validate generate_lead event triggering

## Support

For issues or questions:
1. Check JavaScript console for errors
2. Review `/wp-content/debug.log` if WP_DEBUG is enabled
3. Verify all frameworks are up to date
4. Check plugin compatibility