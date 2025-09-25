# Choice Universal Form Tracker - Project Constitution

## Version: 1.0
## Date: 2025-09-25
## Status: Active

---

## Core Principles

### 1. JavaScript-First Compatibility Principle

**Primary Implementation**: All functionality MUST be implemented using pure vanilla JavaScript as the primary method.

**jQuery Fallback**: jQuery implementations serve as secondary options when jQuery is available, but NEVER as the primary method.

**Multiple Detection Methods**: Implement multiple layers of event detection to ensure maximum compatibility across all WordPress environments.

```javascript
// Implementation Priority Order:
// 1. Native JavaScript events (Elementor 3.5+)
// 2. jQuery events (legacy compatibility)
// 3. MutationObserver (DOM change detection)
// 4. Ajax interceptors (fetch/XMLHttpRequest)
// 5. Form submit handlers (universal fallback)
```

### 2. DataLayer Standardization Rule

**Consistent Naming**: ALL dataLayer parameters MUST use snake_case naming convention without exception.

**Required Fields**: Every tracking event MUST include these standardization fields:
- `cuft_tracked: true` (identifies events from this plugin)
- `cuft_source: "framework_name"` (identifies the originating framework)

**Field Naming Standards**:
- ✅ `form_type` (not `formType` or `form_framework`)
- ✅ `form_id` (not `formId`)
- ✅ `user_email` (not `userEmail` or `email`)
- ✅ `submitted_at` (not `submittedAt` or `timestamp`)

### 3. Framework Compatibility Principle

**Non-Interference**: Framework-specific scripts MUST only process their own forms and ignore all other forms silently.

**Silent Failures**: Scripts MUST exit early for non-matching forms without generating console output or errors.

**Framework Detection**: All framework detection MUST happen before any logging or processing begins.

```javascript
// Required pattern for all framework scripts:
if (!isRelevantFramework(form)) {
  return; // Silent exit - no logging
}
// Only process if framework matches
```

### 4. Event Firing Rules

**form_submit Event**: MUST fire on every successful form submission regardless of field completion status.

**generate_lead Event**: MUST only fire when ALL three conditions are met:
1. Valid email address present
2. Valid phone number present
3. At least one click ID present (gclid, fbclid, click_id, etc.)

**Event Deduplication**: Each form submission MUST only generate one event of each type to prevent duplicate tracking.

### 5. Error Handling Philosophy

**Graceful Degradation**: System MUST continue functioning when individual components fail.

**Fallback Chains**: All data retrieval MUST implement fallback hierarchies:
```
URL Parameters → SessionStorage → Cookies → Empty Object
```

**Error Isolation**: Errors in one tracking method MUST NOT prevent other methods from functioning.

**Try-Catch Requirement**: All external API calls, DOM manipulation, and data parsing MUST be wrapped in try-catch blocks.

### 6. Testing Requirements

**Universal Test Coverage**: Every framework MUST have dedicated test forms that validate tracking functionality.

**Production Flow Testing**: Test forms MUST use the same production tracking code path, not separate test implementations.

**Cross-Framework Validation**: Test suites MUST verify that multiple frameworks can coexist without interference.

**Event Validation**: All tests MUST validate that events contain required fields and correct data types.

### 7. Performance Constraints

**Minimal Overhead**: Total tracking overhead MUST be less than 50ms per form submission.

**Lazy Loading**: Framework-specific scripts MUST only load when relevant forms are detected.

**Memory Management**: Event listeners MUST be properly cleaned up to prevent memory leaks.

**DOM Queries**: Minimize DOM queries through efficient caching and single-pass processing.

### 8. Security Principles

**PII Protection**: No personally identifiable information MUST be logged to console in production mode.

**Data Sanitization**: All form data MUST be sanitized before inclusion in tracking events.

**XSS Prevention**: All dynamic content insertion MUST use safe DOM methods, never innerHTML with unsanitized data.

**Click ID Validation**: All click IDs MUST be validated against expected patterns before storage or transmission.

---

## Implementation Standards

### Code Organization

**File Structure**: Each framework MUST have separate JavaScript and PHP files:
- `/assets/forms/cuft-{framework}-forms.js`
- `/includes/forms/class-cuft-{framework}-forms.php`

**Naming Convention**: All files, classes, and functions MUST use consistent kebab-case or snake_case naming.

**Dependencies**: Framework scripts MUST NOT depend on jQuery or any external libraries beyond WordPress core.

### Event Handling Standards

**Event Listener Management**: All event listeners MUST be added after DOM ready and removed appropriately.

**Event Bubbling**: Use event delegation where possible to handle dynamically added forms.

**Custom Events**: All custom events MUST use the CustomEvent constructor with appropriate detail objects.

### Debug Mode Standards

**Conditional Logging**: Debug output MUST only appear when explicitly enabled via configuration.

**Structured Logging**: All debug messages MUST include context information (framework, form ID, action).

**Production Silence**: Production deployments MUST have zero console output unless errors occur.

### Documentation Standards

**Inline Documentation**: All complex logic MUST include explanatory comments.

**Specification Compliance**: All code MUST reference and comply with relevant specifications.

**Change Documentation**: All modifications MUST document what changed and why.

---

## Enforcement Mechanisms

### Specification Validation

All implementations MUST be validated against specifications before deployment.

AI agents MUST reference specifications before making any code changes.

### Automated Testing

Continuous integration MUST validate all core principles through automated tests.

Performance benchmarks MUST be maintained and validated with each release.

### Review Requirements

All code changes MUST pass specification compliance review.

Cross-framework impact analysis MUST be performed for all modifications.

---

## Version Control

**Constitution Updates**: Any changes to this constitution require project lead approval.

**Backward Compatibility**: All changes MUST maintain backward compatibility or provide migration path.

**Change Documentation**: All constitution changes MUST be documented with rationale and impact analysis.

---

## Violation Handling

**Principle Violations**: Code that violates core principles MUST be rejected or fixed before merge.

**Performance Violations**: Code that exceeds performance constraints MUST be optimized or redesigned.

**Security Violations**: Code with security issues MUST be fixed immediately with highest priority.

---

This constitution serves as the foundational law for all development activities within the Choice Universal Form Tracker project. All specifications, implementations, and modifications MUST align with these core principles to ensure consistency, reliability, and maintainability.