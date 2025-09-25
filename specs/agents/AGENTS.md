# AI Agent Instructions for Choice Universal Form Tracker

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Purpose: Standardized instructions for AI coding agents working on CUFT

---

## Overview

This document provides comprehensive instructions for AI coding agents (Claude, GitHub Copilot, Gemini, Cursor, etc.) working on the Choice Universal Form Tracker project. All AI agents MUST follow these instructions to ensure consistent, specification-compliant development.

---

## CRITICAL: Always Reference Specifications First

### Before ANY Code Changes
1. **MANDATORY**: Read and understand relevant specifications:
   - [specs/CONSTITUTION.md](../CONSTITUTION.md) - Core principles and standards
   - [specs/core/dataLayer.spec.md](../core/dataLayer.spec.md) - DataLayer event requirements
   - [specs/core/tracking-params.spec.md](../core/tracking-params.spec.md) - UTM/Click ID handling
   - Framework-specific specs in [specs/frameworks/](../frameworks/)
   - [specs/testing/test-suite.spec.md](../testing/test-suite.spec.md) - Testing requirements

2. **VALIDATE**: Ensure proposed changes align with constitutional principles
3. **CHECK**: Verify compatibility with existing implementations
4. **PLAN**: Reference implementation plan templates if creating new features

### Specification Version Compatibility
Always check specification versions and ensure compatibility:
- Constitution: v1.0
- Core Specs: v1.0
- Framework Specs: v1.0
- Testing Specs: v1.0

---

## Core Development Principles

### 1. JavaScript-First Implementation

**ALWAYS implement vanilla JavaScript as primary method**:
```javascript
// ✅ CORRECT: Vanilla JavaScript first
document.addEventListener('submit_success', function(event) {
  handleElementorSuccess(event);
});

// ✅ ACCEPTABLE: jQuery as fallback
if (window.jQuery) {
  jQuery(document).on('submit_success', function(event) {
    handleElementorSuccess(event);
  });
}

// ❌ INCORRECT: jQuery as primary method
jQuery(document).on('submit_success', function(event) {
  handleElementorSuccess(event);
});
```

### 2. DataLayer Standardization

**ALL dataLayer events MUST use snake_case naming**:
```javascript
// ✅ CORRECT: snake_case with required fields
dataLayer.push({
  event: 'form_submit',
  form_type: 'elementor',        // NOT formType
  form_id: 'form-123',           // NOT formId
  user_email: 'user@test.com',   // NOT userEmail
  user_phone: '123-456-7890',    // NOT userPhone
  submitted_at: new Date().toISOString(),
  cuft_tracked: true,            // REQUIRED
  cuft_source: 'elementor_pro'   // REQUIRED
});

// ❌ INCORRECT: camelCase naming
dataLayer.push({
  event: 'form_submit',
  formType: 'elementor',         // Wrong!
  userEmail: 'user@test.com',    // Wrong!
  submittedAt: new Date().toISOString() // Wrong!
});
```

### 3. Framework Compatibility

**ALWAYS implement silent exit for non-relevant frameworks**:
```javascript
// ✅ CORRECT: Silent exit pattern
function handleFormSubmission(form) {
  if (!isElementorForm(form)) {
    return; // Silent exit - no logging
  }

  // Only process Elementor forms
  processElementorForm(form);
}

// ❌ INCORRECT: Logging for non-relevant forms
function handleFormSubmission(form) {
  if (!isElementorForm(form)) {
    console.log('[CUFT] Not an Elementor form, skipping');
    return;
  }
}
```

### 4. Error Handling Requirements

**ALWAYS implement graceful error handling with fallbacks**:
```javascript
// ✅ CORRECT: Try-catch with fallback
function safeGetFieldValue(form, type) {
  try {
    return getFieldValue(form, type);
  } catch (e) {
    if (DEBUG) {
      log('Field extraction error for ' + type + ':', e);
    }
    return ''; // Safe fallback
  }
}

// ✅ CORRECT: DataLayer fallback
function getDL() {
  try {
    return (window.dataLayer = window.dataLayer || []);
  } catch (e) {
    return { push: function() {} }; // No-op fallback
  }
}
```

---

## Framework-Specific Requirements

### Elementor Forms
**Reference**: [specs/frameworks/elementor.spec.md](../frameworks/elementor.spec.md)

**Key Requirements**:
- Listen for both native and jQuery `submit_success` events
- Implement multi-step form detection (final step only)
- Fix invalid regex patterns in form inputs
- Handle popup forms with appropriate event listeners
- Support both event-based and fallback detection methods

```javascript
// Required event listener setup
document.addEventListener('submit_success', handleElementorSuccess);
if (window.jQuery) {
  jQuery(document).on('submit_success', handleElementorSuccess);
}
```

### Contact Form 7
**Reference**: [specs/frameworks/cf7.spec.md](../frameworks/cf7.spec.md)

**Key Requirements**:
- Listen only for `wpcf7mailsent` event (success only)
- Extract form ID from wrapper element
- Handle multiple forms on same page independently
- Implement CF7-specific field detection patterns

```javascript
// Required event listener
document.addEventListener('wpcf7mailsent', handleCF7Success, false);
```

### Ninja Forms
**Reference**: [specs/frameworks/ninja.spec.md](../frameworks/ninja.spec.md)

**Key Requirements**:
- Use submit-based tracking with success detection
- Implement MutationObserver for success message detection
- Handle `.nf-field` container structure
- Support multi-step forms (final step only)

### Gravity Forms
**Reference**: [specs/frameworks/gravity.spec.md](../frameworks/gravity.spec.md)

**Key Requirements**:
- Submit-based tracking with confirmation message detection
- Handle complex field structures (multi-part fields)
- Support multi-page forms (final page only)
- Implement retry logic for success detection

### Avada Forms
**Reference**: [specs/frameworks/avada.spec.md](../frameworks/avada.spec.md)

**Key Requirements**:
- Handle dynamically loaded forms via Fusion Builder
- Implement exponential backoff for success detection
- Support various success message patterns
- Handle form hiding as success indicator

---

## Mandatory Code Patterns

### 1. Framework Detection Pattern
```javascript
function isXFrameworkForm(form) {
  return form && (
    form.classList.contains('framework-class') ||
    form.closest('.framework-wrapper') !== null ||
    form.querySelector('.framework-field') !== null
  );
}

function handleFormEvent(form) {
  if (!isXFrameworkForm(form)) {
    return; // ALWAYS silent exit
  }

  // Process framework-specific form
}
```

### 2. Field Value Extraction Pattern
```javascript
function getFieldValue(form, type) {
  var field = getFieldByType(form, type);
  if (!field || !field.value) return '';

  var value = field.value.trim();

  if (type === 'email') {
    return validateEmail(value) ? value : '';
  }

  if (type === 'phone') {
    return cleanPhoneNumber(value);
  }

  return value;
}
```

### 3. DataLayer Push Pattern
```javascript
function pushToDataLayer(form, email, phone) {
  var payload = {
    event: 'form_submit',
    form_type: 'framework_name',
    form_id: getFormId(form),
    form_name: getFormName(form),
    submitted_at: new Date().toISOString(),
    cuft_tracked: true,
    cuft_source: 'framework_name_source'
  };

  // Add GA4 standard parameters
  var ga4Params = getGA4StandardParams();
  Object.assign(payload, ga4Params);

  // Add contact fields if present
  if (email && validateEmail(email)) {
    payload.user_email = email;
  }
  if (phone) {
    payload.user_phone = phone;
  }

  // Add UTM data
  if (window.cuftUtmUtils) {
    payload = window.cuftUtmUtils.addUtmToPayload(payload);
  }

  try {
    getDL().push(payload);
    log('Form submission tracked:', payload);

    // Fire generate_lead if conditions met
    fireGenerateLeadEvent(payload, email, phone);
  } catch (e) {
    log('DataLayer push error:', e);
  }
}
```

### 4. Generate Lead Event Pattern
```javascript
function shouldFireGenerateLeadEvent(email, phone, payload) {
  if (!window.cuftFramework || !window.cuftFramework.generate_lead_enabled) {
    return false;
  }

  var hasEmail = email && validateEmail(email);
  var hasPhone = phone && phone.length > 0;
  var hasClickId = payload.click_id || payload.gclid || payload.fbclid ||
                   payload.msclkid || payload.ttclid || payload.li_fat_id ||
                   payload.twclid || payload.snap_click_id || payload.pclid;

  return hasEmail && hasPhone && hasClickId;
}
```

---

## Testing Requirements

### ALWAYS Create Tests for New Code

**Unit Tests Required**:
```javascript
describe('Framework Form Tracking', () => {
  test('detects framework forms correctly', () => {
    const form = createTestForm();
    expect(isFrameworkForm(form)).toBe(true);
  });

  test('extracts email field value', () => {
    const form = createFormWithEmail('test@example.com');
    expect(getFieldValue(form, 'email')).toBe('test@example.com');
  });

  test('pushes correct dataLayer event', () => {
    const form = createTestForm();
    const spy = jest.spyOn(window.dataLayer, 'push');

    pushToDataLayer(form, 'test@example.com', '123-456-7890');

    expect(spy).toHaveBeenCalledWith(
      expect.objectContaining({
        event: 'form_submit',
        cuft_tracked: true,
        cuft_source: 'framework_name'
      })
    );
  });
});
```

**Integration Tests Required**:
- Cross-framework compatibility (no interference)
- End-to-end form submission flows
- Performance impact validation

### ALWAYS Run Tests Before Committing
```bash
# Required test commands
npm test                    # Unit tests
npm run test:integration   # Integration tests
npm run test:performance   # Performance tests
```

---

## Performance Requirements

### MUST Meet Performance Benchmarks
- Script load time: < 100ms
- Form processing time: < 50ms per submission
- Memory usage: < 1KB per form submission
- No memory leaks in event listeners

### Performance Optimization Patterns
```javascript
// ✅ CORRECT: Efficient DOM queries
var form = event.target.closest('.framework-form');
if (!form) return;

// ✅ CORRECT: Event delegation
document.addEventListener('submit', function(event) {
  if (event.target.classList.contains('framework-form')) {
    handleFrameworkSubmit(event.target);
  }
}, true);

// ❌ INCORRECT: Inefficient repeated queries
document.querySelectorAll('.framework-form').forEach(form => {
  form.addEventListener('submit', handleSubmit);
});
```

---

## Security Requirements

### ALWAYS Sanitize Input Data
```javascript
function sanitizeParameter(value) {
  if (typeof value !== 'string') {
    value = String(value);
  }

  // Remove potentially harmful characters
  value = value.replace(/[<>"'&]/g, '');

  // Limit length
  value = value.substring(0, 500);

  return value.trim();
}
```

### NEVER Log PII in Production
```javascript
// ✅ CORRECT: Conditional logging
function log() {
  if (DEBUG && window.console && window.console.log) {
    window.console.log.apply(window.console, arguments);
  }
}

// ✅ CORRECT: Safe logging
log('Form submission tracked:', {
  formId: formId,
  hasEmail: !!email,  // Boolean, not actual email
  hasPhone: !!phone   // Boolean, not actual phone
});
```

---

## Code Quality Standards

### File Organization
```
assets/forms/cuft-{framework}-forms.js     // Client-side tracking
includes/forms/class-cuft-{framework}-forms.php  // Server-side integration
specs/frameworks/{framework}.spec.md       // Framework specification
```

### Naming Conventions
- **Functions**: camelCase (`getFieldValue`, `handleFormSuccess`)
- **Variables**: camelCase (`formElement`, `emailField`)
- **Constants**: UPPER_SNAKE_CASE (`DEBUG`, `FRAMEWORK_NAME`)
- **CSS Classes**: kebab-case (`elementor-form`, `nf-field`)
- **DataLayer Fields**: snake_case (`form_type`, `user_email`)

### Documentation Requirements
```javascript
/**
 * Extracts field value from form based on field type
 * @param {HTMLFormElement} form - The form element to search
 * @param {string} type - Field type ('email' or 'phone')
 * @returns {string} Field value or empty string if not found
 */
function getFieldValue(form, type) {
  // Implementation with inline comments for complex logic
}
```

---

## Debugging and Logging

### Debug Mode Implementation
```javascript
var DEBUG = !!(window.cuftFramework && window.cuftFramework.console_logging);

function log() {
  try {
    if (DEBUG && window.console && window.console.log) {
      window.console.log.apply(
        window.console,
        ['[CUFT Framework]'].concat(Array.prototype.slice.call(arguments))
      );
    }
  } catch (e) {}
}
```

### Structured Logging
```javascript
// ✅ CORRECT: Structured log messages
log('Form detection result:', {
  formElement: form,
  isRelevantFramework: isFrameworkForm(form),
  formId: getFormId(form),
  hasEmail: !!getFieldValue(form, 'email'),
  hasPhone: !!getFieldValue(form, 'phone')
});
```

---

## Common Anti-Patterns to Avoid

### ❌ NEVER Do These Things

**1. Don't use jQuery as primary method**:
```javascript
// ❌ WRONG
if (window.jQuery) {
  jQuery(document).ready(function() {
    // Primary implementation
  });
}
```

**2. Don't use camelCase for dataLayer fields**:
```javascript
// ❌ WRONG
dataLayer.push({
  formType: 'elementor',
  userEmail: 'test@example.com'
});
```

**3. Don't log for non-relevant frameworks**:
```javascript
// ❌ WRONG
if (!isElementorForm(form)) {
  console.log('Not an Elementor form');
  return;
}
```

**4. Don't create memory leaks**:
```javascript
// ❌ WRONG - Event listeners not cleaned up
forms.forEach(form => {
  form.addEventListener('submit', handler);
});
// Form elements removed from DOM but listeners remain
```

**5. Don't ignore error handling**:
```javascript
// ❌ WRONG - No error handling
function processForm(form) {
  var email = form.querySelector('input[type="email"]').value;
  dataLayer.push({ email: email });
}
```

---

## Pre-Commit Checklist

Before committing any code changes, ALWAYS verify:

- [ ] All relevant specifications reviewed and followed
- [ ] Constitutional principles compliance verified
- [ ] Framework-specific requirements met
- [ ] Silent exit implemented for non-relevant frameworks
- [ ] DataLayer events use snake_case naming
- [ ] Required fields (cuft_tracked, cuft_source) included
- [ ] Error handling implemented with fallbacks
- [ ] Performance requirements considered
- [ ] Security requirements met (input sanitization)
- [ ] Unit tests written and passing
- [ ] Integration tests passing
- [ ] Code follows naming conventions
- [ ] Documentation updated where necessary
- [ ] No memory leaks introduced
- [ ] Debug logging properly implemented

---

## Code Review Process

### Self-Review Checklist
Use [review-checklist.md](./review-checklist.md) before requesting review.

### Peer Review Requirements
- Constitutional compliance verification
- Specification alignment check
- Performance impact assessment
- Security vulnerability review
- Cross-framework compatibility validation

---

## Emergency Procedures

### Critical Issue Response
1. **Immediate**: Stop deployment if in progress
2. **Assess**: Determine scope and impact
3. **Communicate**: Notify stakeholders immediately
4. **Rollback**: Use established rollback procedures
5. **Investigate**: Identify root cause
6. **Fix**: Implement fix with proper testing
7. **Deploy**: Redeploy with additional validation

### Hotfix Process
1. Create hotfix branch from main
2. Implement minimal fix with tests
3. Fast-track code review
4. Deploy to staging for validation
5. Deploy to production with monitoring
6. Merge back to main branch

---

## Continuous Learning

### Stay Updated
- Monitor specification updates
- Review constitutional changes
- Learn from code reviews
- Participate in technical discussions
- Share knowledge with team

### Best Practices Evolution
- Document lessons learned
- Propose specification improvements
- Share successful patterns
- Identify and eliminate anti-patterns
- Contribute to testing strategies

---

This document serves as the definitive guide for AI agents working on the Choice Universal Form Tracker. Adherence to these instructions ensures consistent, high-quality, specification-compliant code that maintains the integrity and reliability of the tracking system.