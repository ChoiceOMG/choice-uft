# Test Suite Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the comprehensive testing requirements for the Choice Universal Form Tracker. All implementations MUST pass these tests to ensure compliance with core specifications and framework requirements.

---

## Test Categories

### 1. Unit Tests

**Purpose**: Test individual components in isolation
**Coverage Requirement**: > 90% for all tracking modules
**Framework**: Jest (JavaScript) / PHPUnit (PHP)

#### Required Unit Tests:

**Core DataLayer Functions**:
```javascript
describe('DataLayer Core Functions', () => {
  test('getDL() returns dataLayer object', () => {
    expect(getDL()).toBeDefined();
    expect(typeof getDL().push).toBe('function');
  });

  test('getDL() handles missing dataLayer gracefully', () => {
    delete window.dataLayer;
    const dl = getDL();
    expect(dl.push).toBeDefined();
    expect(typeof dl.push).toBe('function');
  });
});
```

**Field Detection Functions**:
```javascript
describe('Field Detection', () => {
  test('getEmailField() detects email inputs correctly', () => {
    const form = createTestForm('<input type="email" name="email" value="test@example.com">');
    const field = getEmailField(form);
    expect(field).toBeTruthy();
    expect(field.value).toBe('test@example.com');
  });

  test('getPhoneField() detects phone inputs correctly', () => {
    const form = createTestForm('<input type="tel" name="phone" value="123-456-7890">');
    const field = getPhoneField(form);
    expect(field).toBeTruthy();
    expect(field.value).toBe('123-456-7890');
  });
});
```

**Parameter Tracking Functions**:
```javascript
describe('UTM Parameter Tracking', () => {
  test('getURLParameters() extracts UTM parameters from URL', () => {
    // Mock URL with UTM parameters
    Object.defineProperty(window, 'location', {
      value: { search: '?utm_source=test&utm_medium=email&utm_campaign=test_campaign' }
    });

    const params = getURLParameters();
    expect(params.utm_source).toBe('test');
    expect(params.utm_medium).toBe('email');
    expect(params.utm_campaign).toBe('test_campaign');
  });

  test('fallback chain works correctly', () => {
    // Test URL -> SessionStorage -> Cookies fallback
    const params = getTrackingParameters();
    expect(params).toBeDefined();
    expect(typeof params).toBe('object');
  });
});
```

### 2. Integration Tests

**Purpose**: Test component interactions and form submission flows
**Environment**: Simulated WordPress environment with form plugins

#### Framework Integration Tests:

**Elementor Forms Integration**:
```javascript
describe('Elementor Forms Integration', () => {
  test('submit_success event triggers tracking', async () => {
    const form = createElementorForm();
    const dataLayerSpy = jest.spyOn(window.dataLayer, 'push');

    // Simulate Elementor success event
    const event = new CustomEvent('submit_success', {
      detail: { formId: 'elementor-form-1' }
    });
    form.dispatchEvent(event);

    await waitFor(() => {
      expect(dataLayerSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          event: 'form_submit',
          form_type: 'elementor',
          cuft_tracked: true,
          cuft_source: 'elementor_pro'
        })
      );
    });
  });

  test('generate_lead fires when conditions met', async () => {
    const form = createElementorFormWithData({
      email: 'test@example.com',
      phone: '123-456-7890'
    });

    // Add UTM parameters with click ID
    window.cuftUtmUtils = {
      addUtmToPayload: jest.fn((payload) => ({
        ...payload,
        utm_campaign: 'test_campaign',
        gclid: 'test_click_id'
      }))
    };

    const dataLayerSpy = jest.spyOn(window.dataLayer, 'push');

    // Trigger form submission
    triggerElementorSuccess(form);

    await waitFor(() => {
      expect(dataLayerSpy).toHaveBeenCalledWith(
        expect.objectContaining({
          event: 'generate_lead',
          cuft_source: 'elementor_pro_lead'
        })
      );
    });
  });
});
```

**Cross-Framework Interference Tests**:
```javascript
describe('Cross-Framework Compatibility', () => {
  test('multiple frameworks on same page do not interfere', () => {
    const elementorForm = createElementorForm();
    const cf7Form = createCF7Form();
    const ninjaForm = createNinjaForm();

    document.body.appendChild(elementorForm);
    document.body.appendChild(cf7Form);
    document.body.appendChild(ninjaForm);

    const consoleSpy = jest.spyOn(console, 'log');

    // Trigger CF7 event on Elementor form (should be ignored)
    const cf7Event = new CustomEvent('wpcf7mailsent');
    elementorForm.dispatchEvent(cf7Event);

    // Verify no cross-framework logging
    expect(consoleSpy).not.toHaveBeenCalledWith(
      expect.stringContaining('[CUFT CF7]')
    );
  });
});
```

### 3. End-to-End Tests

**Purpose**: Test complete user workflows in real browser environment
**Framework**: Playwright/Cypress
**Environment**: Real WordPress installation with form plugins

#### E2E Test Scenarios:

**Form Submission Flow**:
```javascript
describe('Form Submission E2E', () => {
  test('user completes contact form successfully', async () => {
    await page.goto('/contact');

    // Fill form
    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="phone"]', '123-456-7890');
    await page.fill('textarea[name="message"]', 'Test message');

    // Set up dataLayer monitoring
    await page.evaluate(() => {
      window.dataLayerEvents = [];
      const originalPush = window.dataLayer.push;
      window.dataLayer.push = function(event) {
        window.dataLayerEvents.push(event);
        return originalPush.call(this, event);
      };
    });

    // Submit form
    await page.click('input[type="submit"]');

    // Wait for success
    await page.waitForSelector('.elementor-message-success', { timeout: 10000 });

    // Verify dataLayer events
    const events = await page.evaluate(() => window.dataLayerEvents);
    const formSubmitEvent = events.find(e => e.event === 'form_submit');

    expect(formSubmitEvent).toBeDefined();
    expect(formSubmitEvent.cuft_tracked).toBe(true);
    expect(formSubmitEvent.user_email).toBe('test@example.com');
    expect(formSubmitEvent.user_phone).toBe('123-456-7890');
  });

  test('generate_lead fires with UTM and click ID', async () => {
    // Navigate with UTM parameters and click ID
    await page.goto('/contact?utm_source=google&utm_medium=cpc&utm_campaign=test&gclid=abc123');

    await page.fill('input[name="email"]', 'test@example.com');
    await page.fill('input[name="phone"]', '123-456-7890');

    await page.evaluate(() => {
      window.dataLayerEvents = [];
      const originalPush = window.dataLayer.push;
      window.dataLayer.push = function(event) {
        window.dataLayerEvents.push(event);
        return originalPush.call(this, event);
      };
    });

    await page.click('input[type="submit"]');
    await page.waitForSelector('.elementor-message-success');

    const events = await page.evaluate(() => window.dataLayerEvents);
    const leadEvent = events.find(e => e.event === 'generate_lead');

    expect(leadEvent).toBeDefined();
    expect(leadEvent.utm_source).toBe('google');
    expect(leadEvent.gclid).toBe('abc123');
  });
});
```

### 4. Performance Tests

**Purpose**: Ensure tracking doesn't impact page performance
**Tools**: Lighthouse, WebPageTest integration

#### Performance Test Requirements:

**Loading Performance**:
```javascript
describe('Performance Tests', () => {
  test('tracking scripts load within performance budget', async () => {
    const startTime = performance.now();

    // Load all tracking scripts
    await loadTrackingScripts();

    const loadTime = performance.now() - startTime;
    expect(loadTime).toBeLessThan(100); // 100ms budget
  });

  test('form submission tracking completes quickly', async () => {
    const form = createElementorForm();
    const startTime = performance.now();

    triggerElementorSuccess(form);

    await waitFor(() => {
      expect(window.dataLayer.length).toBeGreaterThan(0);
    });

    const processingTime = performance.now() - startTime;
    expect(processingTime).toBeLessThan(50); // 50ms budget per spec
  });

  test('memory usage remains within bounds', () => {
    const initialMemory = performance.memory ? performance.memory.usedJSHeapSize : 0;

    // Simulate 100 form submissions
    for (let i = 0; i < 100; i++) {
      simulateFormSubmission();
    }

    const finalMemory = performance.memory ? performance.memory.usedJSHeapSize : 0;
    const memoryIncrease = finalMemory - initialMemory;

    // Should not increase memory by more than 1MB for 100 submissions
    expect(memoryIncrease).toBeLessThan(1024 * 1024);
  });
});
```

### 5. Browser Compatibility Tests

**Purpose**: Ensure functionality across supported browsers
**Browsers**: Chrome, Firefox, Safari, Edge (latest 3 versions each)

#### Browser Test Matrix:

**Feature Support Tests**:
```javascript
describe('Browser Compatibility', () => {
  const browsers = ['chrome', 'firefox', 'safari', 'edge'];

  browsers.forEach(browser => {
    test(`form tracking works in ${browser}`, async () => {
      const page = await getBrowserPage(browser);
      await page.goto('/test-form');

      await page.fill('input[name="email"]', 'test@example.com');
      await page.click('input[type="submit"]');

      const dataLayerEvents = await page.evaluate(() => window.dataLayer);
      expect(dataLayerEvents.some(e => e.event === 'form_submit')).toBe(true);
    });

    test(`localStorage/sessionStorage works in ${browser}`, async () => {
      const page = await getBrowserPage(browser);

      const storageTest = await page.evaluate(() => {
        try {
          sessionStorage.setItem('test', 'value');
          const retrieved = sessionStorage.getItem('test');
          sessionStorage.removeItem('test');
          return retrieved === 'value';
        } catch (e) {
          return false;
        }
      });

      expect(storageTest).toBe(true);
    });
  });
});
```

---

## Test Data Management

### Test Form Templates

**Elementor Test Form**:
```html
<form class="elementor-form" data-form-id="test-elementor-form">
  <div class="elementor-field-group">
    <label for="email">Email</label>
    <input type="email" name="form_fields[email]" id="email" required>
  </div>
  <div class="elementor-field-group">
    <label for="phone">Phone</label>
    <input type="tel" name="form_fields[phone]" id="phone">
  </div>
  <div class="elementor-field-group">
    <input type="submit" value="Submit">
  </div>
</form>
```

**CF7 Test Form**:
```html
<div class="wpcf7" id="wpcf7-f123-o1">
  <form class="wpcf7-form" method="post">
    <p>
      <label>Email: <input type="email" name="your-email" size="40" required></label>
    </p>
    <p>
      <label>Phone: <input type="tel" name="your-tel" size="40"></label>
    </p>
    <p>
      <input type="submit" value="Submit">
    </p>
  </form>
</div>
```

### Test Data Sets

**Valid Test Inputs**:
```javascript
const validTestData = {
  emails: [
    'test@example.com',
    'user.name@domain.co.uk',
    'test+label@gmail.com',
    'user123@subdomain.example.org'
  ],
  phones: [
    '123-456-7890',
    '+1 (555) 123-4567',
    '555.123.4567',
    '+44 20 7123 4567'
  ],
  utmParameters: [
    {
      utm_source: 'google',
      utm_medium: 'cpc',
      utm_campaign: 'summer_sale',
      utm_term: 'contact_form',
      utm_content: 'header'
    },
    {
      utm_source: 'facebook',
      utm_medium: 'social',
      utm_campaign: 'brand_awareness'
    }
  ],
  clickIds: [
    { gclid: 'TeSter-123_abc' },
    { fbclid: 'IwAR1234567890' },
    { msclkid: 'abcd1234efgh5678' }
  ]
};
```

**Invalid Test Inputs**:
```javascript
const invalidTestData = {
  emails: [
    'invalid-email',
    '@domain.com',
    'user@',
    'user space@domain.com'
  ],
  xssAttempts: [
    '<script>alert("xss")</script>',
    'javascript:alert("xss")',
    '<img src="x" onerror="alert(1)">'
  ],
  overlyLongStrings: [
    'a'.repeat(1000), // Test length limits
    'very-long-email@' + 'domain'.repeat(100) + '.com'
  ]
};
```

---

## Test Execution Requirements

### Automated Test Pipeline

**Pre-commit Tests**:
- Unit tests (required to pass)
- Linting and code formatting
- Basic integration tests

**CI/CD Pipeline Tests**:
- Full unit test suite
- Integration tests
- Cross-browser compatibility tests
- Performance benchmarks

**Release Tests**:
- End-to-end test suite
- Manual verification checklist
- Performance regression tests

### Test Environment Setup

**WordPress Test Environment**:
```yaml
# docker-compose.test.yml
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: test
      WORDPRESS_DB_PASSWORD: test
      WORDPRESS_DB_NAME: wordpress_test
    volumes:
      - ./plugin:/var/www/html/wp-content/plugins/choice-uft
    depends_on:
      - db

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress_test
      MYSQL_USER: test
      MYSQL_PASSWORD: test
      MYSQL_ROOT_PASSWORD: root

  playwright:
    image: mcr.microsoft.com/playwright:latest
    volumes:
      - ./tests:/app/tests
    depends_on:
      - wordpress
```

**Required Plugin Installations**:
- Elementor Pro (latest version)
- Contact Form 7 (latest version)
- Ninja Forms (latest version)
- Gravity Forms (latest version)
- Avada Theme with Fusion Builder

---

## Test Reporting

### Coverage Reports

**Minimum Coverage Requirements**:
- Unit Tests: 90% line coverage, 85% branch coverage
- Integration Tests: 80% feature coverage
- E2E Tests: 100% critical path coverage

**Coverage Tools**:
- JavaScript: Istanbul/nyc
- PHP: PHPUnit with Xdebug

### Performance Reports

**Performance Metrics to Track**:
- Script load time (< 100ms)
- Form processing time (< 50ms)
- Memory usage per form submission (< 1KB)
- DataLayer event count accuracy

### Test Result Documentation

**Test Report Format**:
```markdown
# Test Report - Version X.Y.Z

## Summary
- Total Tests: XXX
- Passed: XXX
- Failed: X
- Coverage: XX.X%

## Failed Tests
[Details of any failing tests]

## Performance Metrics
- Average processing time: XXms
- Memory usage: XXKB
- Browser compatibility: X/Y browsers passing

## Recommendations
[Any recommendations for improvement]
```

---

## Quality Gates

### Release Criteria

**Required for Release**:
- [ ] All unit tests pass (100%)
- [ ] All integration tests pass (100%)
- [ ] Critical E2E tests pass (100%)
- [ ] Performance benchmarks met
- [ ] Cross-browser compatibility verified
- [ ] Security tests pass
- [ ] No critical or high-severity bugs

**Pre-merge Criteria**:
- [ ] Unit tests pass
- [ ] Code coverage maintained or improved
- [ ] Linting passes
- [ ] Integration tests for changed components pass

---

This comprehensive test suite ensures that the Choice Universal Form Tracker maintains high quality, performance, and reliability across all supported form frameworks and environments.