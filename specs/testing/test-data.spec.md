# Test Data Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines standardized test data sets, form templates, and validation criteria for testing the Choice Universal Form Tracker. All test implementations MUST use these standardized data sets to ensure consistent and comprehensive testing.

---

## Form Templates

### Elementor Form Templates

**Basic Contact Form**:
```html
<div class="elementor-widget-form">
  <form class="elementor-form" data-form-id="contact-form-1" data-form-name="Contact Form">
    <div class="elementor-field-group elementor-column elementor-field-type-email">
      <label for="form-field-email" class="elementor-field-label">Email</label>
      <input size="1" type="email" name="form_fields[email]" id="form-field-email"
             class="elementor-field elementor-field-textual elementor-size-sm" required>
    </div>
    <div class="elementor-field-group elementor-column elementor-field-type-tel">
      <label for="form-field-phone" class="elementor-field-label">Phone</label>
      <input size="1" type="tel" name="form_fields[phone]" id="form-field-phone"
             class="elementor-field elementor-field-textual elementor-size-sm">
    </div>
    <div class="elementor-field-group elementor-column elementor-field-type-textarea">
      <label for="form-field-message" class="elementor-field-label">Message</label>
      <textarea class="elementor-field-textual elementor-field elementor-size-sm"
                name="form_fields[message]" id="form-field-message" rows="4"></textarea>
    </div>
    <div class="elementor-field-group elementor-column elementor-field-type-submit">
      <button type="submit" class="elementor-button elementor-size-sm">Submit</button>
    </div>
  </form>
</div>
```

**Multi-Step Elementor Form**:
```html
<div class="elementor-widget-form">
  <form class="elementor-form" data-form-id="multi-step-form-1">
    <!-- Step 1 -->
    <div class="elementor-field-type-step e-field-active">
      <div class="elementor-field-group elementor-column elementor-field-type-text">
        <label for="form-field-name" class="elementor-field-label">Full Name</label>
        <input type="text" name="form_fields[name]" id="form-field-name"
               class="elementor-field elementor-field-textual" required>
      </div>
      <div class="elementor-field-group elementor-column elementor-field-type-email">
        <label for="form-field-email" class="elementor-field-label">Email</label>
        <input type="email" name="form_fields[email]" id="form-field-email"
               class="elementor-field elementor-field-textual" required>
      </div>
      <button type="button" class="elementor-button elementor-button-next">Next</button>
    </div>

    <!-- Step 2 -->
    <div class="elementor-field-type-step">
      <div class="elementor-field-group elementor-column elementor-field-type-tel">
        <label for="form-field-phone" class="elementor-field-label">Phone</label>
        <input type="tel" name="form_fields[phone]" id="form-field-phone"
               class="elementor-field elementor-field-textual">
      </div>
      <div class="elementor-field-group elementor-column elementor-field-type-text">
        <label for="form-field-company" class="elementor-field-label">Company</label>
        <input type="text" name="form_fields[company]" id="form-field-company"
               class="elementor-field elementor-field-textual">
      </div>
      <button type="submit" class="elementor-button elementor-button-submit">Submit</button>
    </div>
  </form>
</div>
```

### Contact Form 7 Templates

**Basic CF7 Form**:
```html
<div class="wpcf7" id="wpcf7-f123-p1-o1" lang="en-US" dir="ltr">
  <form action="/contact/#wpcf7-f123-p1-o1" method="post" class="wpcf7-form init">
    <p>
      <label>Your Name (required)<br>
      <span class="wpcf7-form-control-wrap your-name">
        <input type="text" name="your-name" value="" size="40"
               class="wpcf7-form-control wpcf7-text wpcf7-validates-as-required" required>
      </span></label>
    </p>

    <p>
      <label>Your Email (required)<br>
      <span class="wpcf7-form-control-wrap your-email">
        <input type="email" name="your-email" value="" size="40"
               class="wpcf7-form-control wpcf7-text wpcf7-email wpcf7-validates-as-required wpcf7-validates-as-email" required>
      </span></label>
    </p>

    <p>
      <label>Your Phone<br>
      <span class="wpcf7-form-control-wrap your-phone">
        <input type="tel" name="your-phone" value="" size="40"
               class="wpcf7-form-control wpcf7-text wpcf7-tel">
      </span></label>
    </p>

    <p>
      <label>Your Message<br>
      <span class="wpcf7-form-control-wrap your-message">
        <textarea name="your-message" cols="40" rows="10"
                  class="wpcf7-form-control wpcf7-textarea"></textarea>
      </span></label>
    </p>

    <p>
      <input type="submit" value="Send" class="wpcf7-form-control wpcf7-submit">
    </p>
  </form>
</div>
```

### Ninja Forms Templates

**Basic Ninja Form**:
```html
<div class="nf-form-cont" data-form-id="1">
  <form class="nf-form" data-form-id="1">
    <div class="nf-field-container textbox-container">
      <div class="nf-field" data-field-type="textbox">
        <label for="nf-field-1" class="ninja-forms-field-label">Name</label>
        <input type="text" name="nf-field-1" id="nf-field-1"
               class="ninja-forms-field nf-element" required>
      </div>
    </div>

    <div class="nf-field-container email-container">
      <div class="nf-field" data-field-type="email">
        <label for="nf-field-2" class="ninja-forms-field-label">Email</label>
        <input type="email" name="nf-field-2" id="nf-field-2"
               class="ninja-forms-field nf-element" required>
      </div>
    </div>

    <div class="nf-field-container phone-container">
      <div class="nf-field" data-field-type="phone">
        <label for="nf-field-3" class="ninja-forms-field-label">Phone</label>
        <input type="tel" name="nf-field-3" id="nf-field-3"
               class="ninja-forms-field nf-element">
      </div>
    </div>

    <div class="nf-field-container submit-container">
      <input type="submit" name="nf-field-submit" value="Submit"
             class="ninja-forms-field nf-element nf-submit">
    </div>
  </form>
</div>
```

### Gravity Forms Templates

**Basic Gravity Form**:
```html
<div class="gform_wrapper" id="gform_wrapper_1">
  <form method="post" enctype="multipart/form-data" id="gform_1" class="gform">
    <div class="gform_body gform-body">
      <div class="gform_fields top_label">

        <div class="gfield gfield_name" id="field_1_1">
          <label class="gfield_label" for="input_1_1">Name</label>
          <div class="ginput_container ginput_container_text">
            <input name="input_1" id="input_1_1" type="text" value=""
                   class="ginput ginput_text" tabindex="1" required>
          </div>
        </div>

        <div class="gfield gfield_email" id="field_1_2">
          <label class="gfield_label" for="input_1_2">Email</label>
          <div class="ginput_container ginput_container_email">
            <input name="input_2" id="input_1_2" type="email" value=""
                   class="ginput ginput_email" tabindex="2" required>
          </div>
        </div>

        <div class="gfield gfield_phone" id="field_1_3">
          <label class="gfield_label" for="input_1_3">Phone</label>
          <div class="ginput_container ginput_container_phone">
            <input name="input_3" id="input_1_3" type="tel" value=""
                   class="ginput ginput_phone" tabindex="3">
          </div>
        </div>

      </div>
    </div>

    <div class="gform_footer top_label">
      <input type="submit" id="gform_submit_button_1" class="gform_button button"
             value="Submit" tabindex="4">
      <input type="hidden" name="gform_form_id" value="1">
    </div>
  </form>
</div>
```

### Avada Forms Templates

**Basic Avada Form**:
```html
<div class="fusion-form-wrapper">
  <form class="fusion-form" data-form-id="avada-form-1">
    <div class="fusion-form-field fusion-form-text-field">
      <label for="fusion-form-field-1">Name</label>
      <input type="text" name="fusion-form-field-1" id="fusion-form-field-1"
             class="fusion-form-input" required>
    </div>

    <div class="fusion-form-field fusion-form-email-field">
      <label for="fusion-form-field-2">Email</label>
      <input type="email" name="fusion-form-field-2" id="fusion-form-field-2"
             class="fusion-form-input" data-validate="email" required>
    </div>

    <div class="fusion-form-field fusion-form-phone-field">
      <label for="fusion-form-field-3">Phone</label>
      <input type="tel" name="fusion-form-field-3" id="fusion-form-field-3"
             class="fusion-form-input" data-validate="phone">
    </div>

    <div class="fusion-form-field fusion-form-submit-field">
      <input type="submit" value="Submit" class="fusion-form-submit fusion-button">
    </div>
  </form>
</div>
```

---

## Test Data Sets

### Valid Email Addresses

```javascript
const validEmails = [
  'test@example.com',
  'user.name@domain.co.uk',
  'test+label@gmail.com',
  'user123@subdomain.example.org',
  'firstname-lastname@domain.com',
  'email@123.123.123.123', // IP address (technically valid)
  'email@[123.123.123.123]', // IP address with brackets
  'user@very-long-domain-name-that-is-still-valid.com',
  '"test email"@example.com', // Quoted local part
  'test.email.with+symbol@example.com'
];
```

### Invalid Email Addresses

```javascript
const invalidEmails = [
  'plainaddress',
  '@missingdomain.com',
  'missing@.com',
  'missing@domain',
  'spaces @domain.com',
  'user@domain .com',
  'user@domain..com', // Double dot
  'user@@domain.com', // Double @
  'user@domain@domain.com', // Multiple @
  '', // Empty string
  'user@', // Missing domain
  '@domain.com', // Missing local part
  'very-very-very-very-very-long-email-address-that-exceeds-the-maximum-length-allowed@domain.com'
];
```

### Valid Phone Numbers

```javascript
const validPhones = [
  '123-456-7890',
  '(123) 456-7890',
  '+1 (123) 456-7890',
  '123.456.7890',
  '123 456 7890',
  '+44 20 7123 4567', // UK format
  '+33 1 42 86 83 26', // France format
  '+81-3-1234-5678', // Japan format
  '555-0123',
  '1234567890', // No formatting
  '+1-123-456-7890',
  '(555) 123-4567 ext 123'
];
```

### Invalid Phone Numbers

```javascript
const invalidPhones = [
  'abc-def-ghij',
  '123',
  '123-45',
  '',
  'phone number',
  '+++123456789',
  '123-456-78901234567890' // Too long
];
```

### UTM Parameter Sets

```javascript
const utmParameterSets = [
  // Complete UTM set
  {
    utm_source: 'google',
    utm_medium: 'cpc',
    utm_campaign: 'summer_sale_2025',
    utm_term: 'contact_form',
    utm_content: 'header_cta'
  },

  // Social media campaign
  {
    utm_source: 'facebook',
    utm_medium: 'social',
    utm_campaign: 'brand_awareness',
    utm_content: 'video_ad_v1'
  },

  // Email campaign
  {
    utm_source: 'newsletter',
    utm_medium: 'email',
    utm_campaign: 'monthly_update_jan2025'
  },

  // Organic search
  {
    utm_source: 'google',
    utm_medium: 'organic'
  },

  // Minimal set
  {
    utm_source: 'direct'
  },

  // With special characters (should be sanitized)
  {
    utm_source: 'test<script>alert("xss")</script>',
    utm_medium: 'test&medium',
    utm_campaign: 'test campaign with spaces'
  }
];
```

### Click ID Sets

```javascript
const clickIdSets = [
  // Google Ads
  {
    gclid: 'TeSter-123_abc',
    gbraid: 'test_gbraid_123',
    wbraid: 'test_wbraid_456'
  },

  // Facebook/Meta
  {
    fbclid: 'IwAR1234567890abcdef'
  },

  // Microsoft/Bing
  {
    msclkid: 'abcd1234efgh5678ijkl'
  },

  // TikTok
  {
    ttclid: 'tiktok_click_id_123456'
  },

  // LinkedIn
  {
    li_fat_id: 'linkedin_fat_id_789'
  },

  // Twitter/X
  {
    twclid: 'twitter_click_id_abc123'
  },

  // Snapchat
  {
    snap_click_id: 'snapchat_click_xyz789'
  },

  // Pinterest
  {
    pclid: 'pinterest_click_def456'
  },

  // Generic click ID
  {
    click_id: 'generic_click_id_123'
  },

  // Multiple click IDs (should use first available)
  {
    gclid: 'google_click_123',
    fbclid: 'facebook_click_456',
    msclkid: 'microsoft_click_789'
  },

  // Invalid click IDs (should be filtered out)
  {
    gclid: '<script>alert("xss")</script>',
    fbclid: 'click id with spaces',
    msclkid: '' // Empty string
  }
];
```

---

## Expected DataLayer Events

### Standard form_submit Event

```javascript
const expectedFormSubmitEvent = {
  event: "form_submit",
  form_type: "elementor", // or "cf7", "ninja", "gravity", "avada"
  form_id: "contact-form-1",
  form_name: "Contact Form",
  user_email: "test@example.com",
  user_phone: "123-456-7890",
  submitted_at: "2025-01-01T12:00:00.000Z", // ISO timestamp
  cuft_tracked: true,
  cuft_source: "elementor_pro", // Framework-specific source

  // GA4 standard parameters
  page_location: "https://example.com/contact",
  page_referrer: "https://google.com",
  page_title: "Contact Us - Example Site",
  language: "en-US",
  screen_resolution: "1920x1080",
  engagement_time_msec: 15000,

  // UTM parameters (if available)
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "summer_sale_2025",
  utm_term: "contact_form",
  utm_content: "header_cta",

  // Click IDs (if available)
  gclid: "TeSter-123_abc"
};
```

### Standard generate_lead Event

```javascript
const expectedGenerateLeadEvent = {
  event: "generate_lead",
  currency: "USD",
  value: 0,
  cuft_tracked: true,
  cuft_source: "elementor_pro_lead", // Framework-specific lead source

  // All form_submit fields are also included
  form_type: "elementor",
  form_id: "contact-form-1",
  form_name: "Contact Form",
  user_email: "test@example.com", // Required for generate_lead
  user_phone: "123-456-7890", // Required for generate_lead
  submitted_at: "2025-01-01T12:00:00.000Z",

  // GA4 parameters
  page_location: "https://example.com/contact",
  page_referrer: "https://google.com",
  page_title: "Contact Us - Example Site",

  // UTM parameters
  utm_source: "google",
  utm_medium: "cpc",
  utm_campaign: "summer_sale_2025",

  // Click ID (at least one required for generate_lead)
  gclid: "TeSter-123_abc"
};
```

---

## Test Scenarios

### Positive Test Scenarios

```javascript
const positiveTestScenarios = [
  {
    name: "Complete form with all fields",
    formData: {
      email: "test@example.com",
      phone: "123-456-7890",
      name: "John Doe",
      message: "This is a test message"
    },
    utmParams: {
      utm_source: "google",
      utm_medium: "cpc",
      utm_campaign: "test_campaign"
    },
    clickIds: {
      gclid: "test_click_id"
    },
    expectedEvents: ["form_submit", "generate_lead"]
  },

  {
    name: "Form with email only",
    formData: {
      email: "user@example.com",
      name: "Jane Smith"
    },
    utmParams: {},
    clickIds: {},
    expectedEvents: ["form_submit"] // No generate_lead without click ID
  },

  {
    name: "Form with phone only",
    formData: {
      phone: "555-123-4567",
      name: "Bob Johnson"
    },
    utmParams: {},
    clickIds: {},
    expectedEvents: ["form_submit"] // No generate_lead without email
  },

  {
    name: "Multi-step form completion",
    formData: {
      step1: { name: "Alice Brown", email: "alice@example.com" },
      step2: { phone: "555-987-6543", company: "Test Corp" }
    },
    expectedEvents: ["form_submit"], // Only fires on final step
    multiStep: true
  }
];
```

### Negative Test Scenarios

```javascript
const negativeTestScenarios = [
  {
    name: "Form with invalid email",
    formData: {
      email: "invalid-email",
      phone: "123-456-7890"
    },
    expectedEvents: ["form_submit"], // Should still fire but without email field
    expectedEventData: {
      user_email: undefined // Invalid email should be excluded
    }
  },

  {
    name: "Empty form submission",
    formData: {},
    expectedEvents: ["form_submit"], // Should still fire with minimal data
    expectedEventData: {
      user_email: undefined,
      user_phone: undefined
    }
  },

  {
    name: "XSS attempt in form fields",
    formData: {
      email: "test@example.com",
      name: "<script>alert('xss')</script>",
      message: "javascript:alert('xss')"
    },
    expectedEvents: ["form_submit"],
    securityTest: true,
    expectedSanitization: true
  },

  {
    name: "Form submission with network error",
    formData: {
      email: "test@example.com",
      phone: "123-456-7890"
    },
    networkError: true,
    expectedEvents: [], // Should not fire events if form doesn't actually submit
    errorHandling: true
  }
];
```

---

## Data Validation Rules

### Email Validation

```javascript
const emailValidationRules = {
  pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
  maxLength: 254,
  required: false, // For form_submit, true for generate_lead
  sanitization: {
    trim: true,
    toLowerCase: false, // Preserve original case
    removeHtml: true
  }
};
```

### Phone Validation

```javascript
const phoneValidationRules = {
  minLength: 1,
  maxLength: 20,
  required: false, // For form_submit, true for generate_lead
  sanitization: {
    preserveInternationalPrefix: true, // Keep + at start
    removeNonDigits: true, // Except for +
    allowedCharacters: /^[\+\d\s\-\(\)\.]+$/
  }
};
```

### UTM Parameter Validation

```javascript
const utmValidationRules = {
  maxLength: 500,
  sanitization: {
    removeHtmlTags: true,
    removeJavaScript: true,
    trimWhitespace: true,
    preserveCase: true
  },
  allowedCharacters: /^[a-zA-Z0-9\-\_\.\s]+$/
};
```

### Click ID Validation

```javascript
const clickIdValidationRules = {
  maxLength: 500,
  pattern: /^[A-Za-z0-9_-]+$/,
  sanitization: {
    removeNonAlphanumeric: true,
    preserveHyphensUnderscores: true,
    trimWhitespace: true
  }
};
```

---

## Performance Test Data

### Load Testing Scenarios

```javascript
const performanceTestData = {
  concurrentFormSubmissions: [1, 5, 10, 25, 50, 100],
  formComplexity: [
    { fields: 3, name: "simple" },
    { fields: 10, name: "medium" },
    { fields: 25, name: "complex" }
  ],
  dataVolume: [
    { utmParams: 3, clickIds: 1, name: "minimal" },
    { utmParams: 5, clickIds: 3, name: "standard" },
    { utmParams: 8, clickIds: 5, name: "maximum" }
  ]
};
```

### Memory Usage Test Data

```javascript
const memoryTestData = {
  submissionCounts: [10, 50, 100, 500, 1000],
  expectedMemoryIncrease: {
    perSubmission: 1024, // bytes
    maxTotal: 1024 * 1024 // 1MB for 1000 submissions
  },
  memoryLeakDetection: {
    iterations: 1000,
    maxMemoryGrowth: 1024 * 100 // 100KB
  }
};
```

---

This comprehensive test data specification ensures consistent, thorough testing across all components and scenarios of the Choice Universal Form Tracker system.