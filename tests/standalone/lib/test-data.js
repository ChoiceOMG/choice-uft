/**
 * Test Data from Specifications
 * Source: specs/testing/test-data.spec.md
 * All test data exactly as defined in specifications
 */
window.CUFTTestData = (function() {
  'use strict';

  // Valid Email Addresses (from spec line 249-260)
  const validEmails = [
    'test@example.com',
    'user.name@domain.co.uk',
    'test+label@gmail.com',
    'user123@subdomain.example.org',
    'firstname-lastname@domain.com',
    'email@123.123.123.123',
    'email@[123.123.123.123]',
    'user@very-long-domain-name-that-is-still-valid.com',
    '"test email"@example.com',
    'test.email.with+symbol@example.com'
  ];

  // Invalid Email Addresses (from spec line 265-280)
  const invalidEmails = [
    'plainaddress',
    '@missingdomain.com',
    'missing@.com',
    'missing@domain',
    'spaces @domain.com',
    'user@domain .com',
    'user@domain..com',
    'user@@domain.com',
    'user@domain@domain.com',
    '',
    'user@',
    '@domain.com',
    'very-very-very-very-very-long-email-address-that-exceeds-the-maximum-length-allowed@domain.com'
  ];

  // Valid Phone Numbers (from spec line 285-299)
  const validPhones = [
    '123-456-7890',
    '(123) 456-7890',
    '+1 (123) 456-7890',
    '123.456.7890',
    '123 456 7890',
    '+44 20 7123 4567',
    '+33 1 42 86 83 26',
    '+81-3-1234-5678',
    '555-0123',
    '1234567890',
    '+1-123-456-7890',
    '(555) 123-4567 ext 123'
  ];

  // Invalid Phone Numbers (from spec line 304-313)
  const invalidPhones = [
    'abc-def-ghij',
    '123',
    '123-45',
    '',
    'phone number',
    '+++123456789',
    '123-456-78901234567890'
  ];

  // UTM Parameter Sets (from spec line 318-361)
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

  // Click ID Sets (from spec line 366-428)
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
      msclkid: ''
    }
  ];

  // Expected DataLayer Events (from spec line 437-500)
  const expectedFormSubmitEvent = {
    event: "form_submit",
    form_type: "elementor",
    form_id: "contact-form-1",
    form_name: "Contact Form",
    user_email: "test@example.com",
    user_phone: "123-456-7890",
    submitted_at: "2025-01-01T12:00:00.000Z",
    cuft_tracked: true,
    cuft_source: "elementor_pro",

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

  const expectedGenerateLeadEvent = {
    event: "generate_lead",
    currency: "USD",
    value: 0,
    cuft_tracked: true,
    cuft_source: "elementor_pro_lead",

    // All form_submit fields also included
    form_type: "elementor",
    form_id: "contact-form-1",
    form_name: "Contact Form",
    user_email: "test@example.com",
    user_phone: "123-456-7890",
    submitted_at: "2025-01-01T12:00:00.000Z",

    // GA4 parameters
    page_location: "https://example.com/contact",
    page_referrer: "https://google.com",
    page_title: "Contact Us - Example Site",

    // UTM parameters
    utm_source: "google",
    utm_medium: "cpc",
    utm_campaign: "summer_sale_2025",

    // Click ID (at least one required)
    gclid: "TeSter-123_abc"
  };

  // Test Scenarios (from spec line 509-611)
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
      expectedEvents: ["form_submit"],
      multiStep: true
    }
  ];

  const negativeTestScenarios = [
    {
      name: "Form with invalid email",
      formData: {
        email: "invalid-email",
        phone: "123-456-7890"
      },
      expectedEvents: ["form_submit"],
      expectedEventData: {
        user_email: undefined // Invalid email should be excluded
      }
    },
    {
      name: "Empty form submission",
      formData: {},
      expectedEvents: ["form_submit"],
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
      expectedEvents: [],
      errorHandling: true
    }
  ];

  // Performance Test Data (from spec line 683-717)
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

  // Form Templates (simplified for testing)
  const formTemplates = {
    elementor: `
      <form class="elementor-form" data-form-id="test-elementor-form">
        <input type="email" name="form_fields[email]" id="email">
        <input type="tel" name="form_fields[phone]" id="phone">
        <input type="submit" value="Submit">
      </form>
    `,
    cf7: `
      <form class="wpcf7-form">
        <input type="email" name="your-email">
        <input type="tel" name="your-phone">
        <input type="submit" value="Submit">
      </form>
    `,
    ninja: `
      <form class="nf-form" data-form-id="1">
        <input type="email" name="nf-field-2">
        <input type="tel" name="nf-field-3">
        <input type="submit" value="Submit">
      </form>
    `,
    gravity: `
      <form id="gform_1" class="gform">
        <input name="input_2" type="email">
        <input name="input_3" type="tel">
        <input type="submit" value="Submit">
      </form>
    `,
    avada: `
      <form class="fusion-form" data-form-id="avada-form-1">
        <input type="email" name="fusion-form-field-2">
        <input type="tel" name="fusion-form-field-3">
        <input type="submit" value="Submit">
      </form>
    `
  };

  // Export public API
  return {
    validEmails: validEmails,
    invalidEmails: invalidEmails,
    validPhones: validPhones,
    invalidPhones: invalidPhones,
    utmParameterSets: utmParameterSets,
    clickIdSets: clickIdSets,
    expectedFormSubmitEvent: expectedFormSubmitEvent,
    expectedGenerateLeadEvent: expectedGenerateLeadEvent,
    positiveTestScenarios: positiveTestScenarios,
    negativeTestScenarios: negativeTestScenarios,
    performanceTestData: performanceTestData,
    memoryTestData: memoryTestData,
    formTemplates: formTemplates
  };
})();