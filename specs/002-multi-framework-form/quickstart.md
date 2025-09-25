# Quickstart Guide: Multi-framework Form Tracking Testing

## Prerequisites
- WordPress site with Choice Universal Form Tracker installed
- At least one form framework installed (Elementor Pro, CF7, Ninja Forms, Gravity Forms, or Avada)
- Google Tag Manager container installed
- Browser Developer Tools access

## Quick Test Scenarios

### 1. Basic Form Submission Test
**Goal**: Verify form submissions are tracked

1. Open browser Developer Tools → Console
2. Enable debug mode:
   ```javascript
   window.cuftMigration = { debugMode: true };
   window.cuftElementor = { console_logging: true };  // Or framework of choice
   ```

3. Submit a form with email and phone
4. Check dataLayer:
   ```javascript
   window.dataLayer.filter(e => e.cuft_tracked);
   ```

5. **Expected**: `form_submit` event with all required fields

### 2. Generate Lead Test
**Goal**: Verify conversion events fire correctly

1. Add UTM parameters to URL:
   ```
   ?utm_source=test&utm_medium=test&utm_campaign=test&gclid=test123
   ```

2. Submit form with:
   - Valid email address
   - Valid phone number

3. Check dataLayer for both events:
   ```javascript
   window.dataLayer.filter(e => e.event === 'generate_lead');
   ```

4. **Expected**: Both `form_submit` and `generate_lead` events

### 3. Framework Detection Test
**Goal**: Ensure only relevant frameworks process forms

1. Create page with multiple form types
2. Open Console (should be clean)
3. Submit each form
4. **Expected**: No console errors, each form tracked once

### 4. Multi-Step Form Test
**Goal**: Verify tracking only on final step

**Elementor Multi-Step**:
1. Start multi-step form
2. Complete all steps except last
3. **Expected**: No tracking events
4. Complete final step
5. **Expected**: Single tracking event

**Gravity Multi-Page**:
1. Navigate through pages
2. **Expected**: No tracking until final page
3. Submit on final page
4. **Expected**: Single tracking event

### 5. Error Recovery Test
**Goal**: Verify graceful error handling

1. Inject error in another script:
   ```javascript
   throw new Error('Test error');
   ```

2. Submit form
3. **Expected**: Form still tracks despite error

## Automated Testing

### Setup Test Environment
```javascript
// Test helper function
function testFormTracking() {
  const results = {
    frameworks: [],
    events: [],
    errors: []
  };

  // Monitor dataLayer
  const originalPush = window.dataLayer.push;
  window.dataLayer.push = function(event) {
    if (event.cuft_tracked) {
      results.events.push(event);
      console.log('✅ Tracked:', event);
    }
    return originalPush.call(window.dataLayer, event);
  };

  // Monitor errors
  window.addEventListener('error', function(e) {
    if (e.message.includes('cuft')) {
      results.errors.push(e);
      console.error('❌ Error:', e);
    }
  });

  return results;
}

// Run test
const test = testFormTracking();
```

### Performance Benchmark
```javascript
function benchmarkTracking() {
  const start = performance.now();

  // Trigger form submission
  document.querySelector('.elementor-form').dispatchEvent(
    new CustomEvent('submit_success', { bubbles: true })
  );

  const end = performance.now();
  const duration = end - start;

  console.log(`Processing time: ${duration}ms`);
  console.assert(duration < 50, 'Performance target missed');

  return duration;
}
```

### Memory Leak Detection
```javascript
function checkMemoryLeaks() {
  const observers = [];

  // Patch MutationObserver
  const OriginalObserver = window.MutationObserver;
  window.MutationObserver = function(...args) {
    const observer = new OriginalObserver(...args);
    observers.push({
      observer: observer,
      created: Date.now(),
      disconnected: false
    });

    const originalDisconnect = observer.disconnect;
    observer.disconnect = function() {
      observers.find(o => o.observer === observer).disconnected = true;
      return originalDisconnect.call(observer);
    };

    return observer;
  };

  // Check after 15 seconds
  setTimeout(() => {
    const active = observers.filter(o => !o.disconnected);
    if (active.length > 0) {
      console.warn('⚠️ Active observers:', active.length);
      active.forEach(o => {
        console.log(`Observer alive for ${Date.now() - o.created}ms`);
      });
    } else {
      console.log('✅ All observers cleaned up');
    }
  }, 15000);
}
```

## Manual Test Checklist

### Framework: Elementor Pro
- [ ] Standard form submission
- [ ] Multi-step form (final step only)
- [ ] Popup form
- [ ] Form with invalid patterns (auto-fixed)
- [ ] Check console for errors

### Framework: Contact Form 7
- [ ] Single form submission
- [ ] Multiple forms on same page
- [ ] Custom field names
- [ ] Check for wpcf7mailsent event
- [ ] Verify deduplication

### Framework: Ninja Forms
- [ ] AJAX submission tracking
- [ ] Multi-step form (final only)
- [ ] Form hiding detection
- [ ] Success message detection
- [ ] Check retry logic

### Framework: Gravity Forms
- [ ] Complex fields (multi-part)
- [ ] Multi-page form (final only)
- [ ] Conditional logic fields
- [ ] Confirmation redirect
- [ ] Check field combination

### Framework: Avada/Fusion
- [ ] Dynamic form loading
- [ ] Multi-step tracking
- [ ] Modal form submission
- [ ] Success message varieties
- [ ] Check builder compatibility

## Validation Criteria

### Required for All Forms
- ✅ `event: "form_submit"`
- ✅ `form_type` matches framework
- ✅ `form_id` is non-empty
- ✅ `form_name` is non-empty
- ✅ `cuft_tracked: true`
- ✅ `cuft_source` matches framework
- ✅ `submitted_at` is ISO 8601

### Required for Generate Lead
- ✅ Valid email address
- ✅ Non-empty phone number
- ✅ At least one click ID
- ✅ `event: "generate_lead"`
- ✅ `currency: "USD"`
- ✅ `value: 0`

### Performance Targets
- ✅ < 50ms total processing
- ✅ < 3ms form detection
- ✅ < 10ms field extraction
- ✅ < 1KB memory per form

## Troubleshooting

### Events Not Firing
1. Check framework is supported
2. Verify form success (not validation error)
3. Check browser console for errors
4. Enable debug mode for details

### Duplicate Events
1. Check for multiple script loads
2. Verify deduplication attributes
3. Check for event bubbling

### Missing Field Data
1. Check field selectors match
2. Verify field is visible
3. Check validation passed
4. Try custom field mapping

### Performance Issues
1. Check observer count
2. Verify cleanup occurring
3. Check for infinite loops
4. Monitor memory usage

## Debug Commands

```javascript
// Enable all debug logging
window.cuftMigration = { debugMode: true };
window.cuftElementor = { console_logging: true };
window.cuftCF7 = { console_logging: true };
window.cuftNinja = { console_logging: true };
window.cuftGravity = { console_logging: true };
window.cuftAvada = { console_logging: true };

// Check feature flags
window.cuftMigration.getStatus();

// Monitor dataLayer in real-time
window.dataLayer.push = new Proxy(window.dataLayer.push, {
  apply: function(target, thisArg, args) {
    console.log('DataLayer Event:', args[0]);
    return target.apply(thisArg, args);
  }
});

// Force generate_lead (testing only)
window.cuftDataLayerUtils.meetsLeadConditions = () => true;
```