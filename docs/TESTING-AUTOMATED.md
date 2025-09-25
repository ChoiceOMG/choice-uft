# Automated Testing Documentation

## Overview

This document describes the automated testing suite for the Choice Universal Form Tracker (CUFT) plugin. These tests validate compliance with specifications defined in `specs/testing/test-suite.spec.md` and use test data from `specs/testing/test-data.spec.md`.

## Test Architecture

### Approach: Browser-Based Standalone Tests

- **No WordPress Required**: These JavaScript tests run directly in any browser
- **No Build System Required**: Tests load the plugin's JavaScript files directly
- **Specification-Driven**: All assertions based on documented specifications
- **Zero Dependencies**: Uses only the plugin's own JavaScript files

## Test Files

### Core Test Suites

#### 1. `tests/standalone/test-runner.html`
**Purpose**: Comprehensive specification compliance testing

**Key Features**:
- ✅ Required field validation (cuft_tracked, cuft_source, form_type, etc.)
- ✅ generate_lead firing conditions (email + phone + click_id)
- ✅ Silent exit for non-supported forms
- ✅ PII non-logging compliance
- ✅ Console monitoring for debugging

**[NEEDS CLARIFICATION] Items**:
- WordPress Environment: Deferred to Phase 2 with Docker
- Browser Scope: Testing Chrome/Firefox initially
- CI/CD: Manual execution, GitHub Actions in Phase 2
- PHP Testing: Out of scope for JavaScript tests

#### 2. `tests/standalone/test-dataLayer-core.html`
**Purpose**: Core dataLayer function testing

**Tests**:
- DataLayer access and fallback handling
- Timestamp generation (ISO 8601 format)
- Email validation per specification pattern
- Phone sanitization and international format preservation
- Form processing state management
- Lead qualification logic
- Event payload creation
- Framework identifier compliance

#### 3. `tests/standalone/test-field-validation.html`
**Purpose**: Email and phone field validation

**Tests**:
- All valid emails from test-data.spec.md (10 patterns)
- All invalid emails from test-data.spec.md (13 patterns)
- Phone sanitization for various formats
- XSS prevention in form fields
- Edge cases and Unicode handling
- Performance: <1ms per validation

#### 4. `tests/standalone/test-performance.html`
**Purpose**: Performance benchmarking against <50ms requirement

**Tests**:
- Single form submission performance
- Form submission with full UTM parameters
- Dual event (form_submit + generate_lead) performance
- Framework-specific performance
- Stress test: 100 concurrent submissions
- Memory usage tracking

### Support Files

#### `tests/standalone/lib/test-data.js`
- Exact test data from specifications
- Valid/invalid emails and phones
- UTM parameter sets
- Click ID variations
- Expected event structures
- Test scenarios (positive and negative)

#### `tests/standalone/lib/test-utils.js`
- Assertion framework
- DOM manipulation helpers
- Event simulation
- DataLayer mocking
- Performance measurement
- Test reporting

## Running Tests

### Quick Start

1. **Open in Browser**:
   ```bash
   # Navigate to the plugin directory
   cd /path/to/choice-uft

   # Open the test runner in your browser
   open tests/standalone/test-runner.html
   # or
   firefox tests/standalone/test-runner.html
   ```

2. **Run All Tests**:
   - Click "Run All Specification Tests" button
   - Review results in the browser

3. **Run Specific Tests**:
   - Required Fields: Tests all mandatory dataLayer fields
   - Lead Generation: Tests generate_lead firing rules
   - Silent Exit: Tests non-interference with unsupported forms
   - PII Compliance: Tests that no personal data appears in logs

### Performance Testing

1. Open `tests/standalone/test-performance.html`
2. Click "Run Performance Tests" for standard tests
3. Click "Run Stress Test" for 100 submission test
4. Verify all submissions complete in <50ms

## Test Assertions

### Required Fields (Per dataLayer.spec.md)

Every `form_submit` event MUST include:
```javascript
{
  event: "form_submit",           // REQUIRED
  cuft_tracked: true,             // REQUIRED
  cuft_source: "framework_name",   // REQUIRED
  form_type: "framework",          // REQUIRED
  form_id: "unique_id",           // REQUIRED
  submitted_at: "ISO_timestamp"    // REQUIRED
}
```

### generate_lead Conditions

Fires ONLY when ALL three conditions are met:
1. Valid email present
2. Valid phone present
3. At least one click_id present

### Silent Exit Requirement

Non-supported forms MUST:
- Generate NO dataLayer events
- Produce NO console output
- Not interfere with other forms

### PII Non-Logging

- ❌ Console logs must NOT contain raw emails or phones
- ✅ DataLayer events CAN contain PII (needed for tracking)
- ✅ Error messages should mask sensitive data

## Interpreting Results

### Pass/Fail Criteria

| Test Category | Pass Criteria | Specification Reference |
|--------------|---------------|------------------------|
| Required Fields | All fields present and correct type | dataLayer.spec.md lines 24-33 |
| generate_lead | Fires only with all 3 conditions | dataLayer.spec.md lines 67-91 |
| Performance | All submissions <50ms | test-suite.spec.md line 281 |
| Silent Exit | No events/logs for unsupported forms | Constitutional requirement |
| PII Compliance | No PII in console logs | Security requirement |

### Console Monitor

The test runner includes a console monitor that:
- Captures all console.log, console.error, console.warn calls
- Detects PII patterns (emails, phones)
- Highlights PII violations in red
- Helps verify silent operation

## Known Limitations

1. **Browser-Specific Tests Only**: These tests validate JavaScript behavior, not WordPress integration
2. **Manual Execution**: No CI/CD automation yet (Phase 2)
3. **Limited Browser Coverage**: Chrome/Firefox tested, Safari/Edge deferred
4. **No PHP Testing**: Server-side functionality not covered

## Future Enhancements (Phase 2)

1. **WordPress Integration Tests**:
   - Docker-based WordPress environment
   - Real form plugin testing
   - Admin UI validation

2. **CI/CD Pipeline**:
   - GitHub Actions workflow
   - Automated test execution on PR
   - Coverage reporting

3. **Extended Browser Matrix**:
   - Safari (WebKit) via Playwright
   - Edge testing
   - Mobile browser testing

4. **PHP Unit Tests**:
   - Settings management
   - GTM injection
   - WordPress hooks

## Troubleshooting

### Tests Won't Load

- Ensure you're in the plugin directory
- Check that asset files exist: `assets/cuft-dataLayer-utils.js`
- Open browser developer console for errors

### Performance Tests Fail

- Close other applications to reduce system load
- Run tests in incognito/private mode
- Try different browser
- Check that debug logging is disabled

### PII Detection False Positives

- The PII detector uses regex patterns
- May flag email-like strings in URLs
- Review actual console output for context

## Test Coverage

Current coverage based on specification requirements:

- ✅ Core dataLayer functions: 100%
- ✅ Field validation: 100%
- ✅ Required event fields: 100%
- ✅ generate_lead conditions: 100%
- ✅ Performance requirements: Tested
- ✅ Silent exit behavior: Tested
- ✅ PII non-logging: Tested
- ⏸️ WordPress integration: Phase 2
- ⏸️ PHP functionality: Phase 2

## Contact

For questions about these tests or to report issues:
- Review specifications in `specs/` directory
- Check `CLAUDE.md` for development guidelines
- Submit issues to the project repository