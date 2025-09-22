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