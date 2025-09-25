# UTM & Click ID Tracking Parameters Specification

## Version: 1.0
## Date: 2025-09-25
## Status: Active
## Constitutional Compliance: Validated

---

## Overview

This specification defines the standardized approach for collecting, storing, and managing UTM parameters and Click IDs across all form frameworks. The system implements a hierarchical fallback chain to ensure maximum parameter capture while maintaining data integrity and user privacy.

---

## UTM Parameter Tracking

### Supported UTM Parameters

**Standard UTM Parameters** (MUST be tracked when available):
- `utm_source`: Identifies the source of traffic (e.g., "google", "facebook", "newsletter")
- `utm_medium`: Identifies the medium (e.g., "cpc", "email", "social", "organic")
- `utm_campaign`: Identifies the campaign name (e.g., "summer_sale", "product_launch")
- `utm_term`: Identifies search terms or keywords (e.g., "running_shoes", "contact_form")
- `utm_content`: Identifies specific content or ad variation (e.g., "sidebar", "header_banner")

**Custom Parameters** (MAY be tracked when present):
- `utm_id`: Campaign ID for advanced tracking
- `utm_source_platform`: Extended source information

### Data Source Hierarchy

**Parameter Resolution Order** (MUST be implemented in this priority):

1. **URL Parameters** (Current page URL)
   - Highest priority
   - Real-time capture
   - Immediate availability

2. **SessionStorage** (Browser session storage)
   - Medium-term persistence
   - Cross-page availability within session
   - Automatic cleanup on session end

3. **Cookies** (Browser cookies)
   - Long-term persistence
   - Cross-session availability
   - Configurable expiration

4. **Empty Object** (Fallback)
   - Prevents errors when no parameters available
   - Returns empty object, never null or undefined

### Implementation Pattern

```javascript
function getTrackingParameters() {
  const params = {};

  // 1. URL Parameters (highest priority)
  const urlParams = getURLParameters();
  Object.assign(params, urlParams);

  // 2. SessionStorage (if URL parameters not available)
  if (Object.keys(params).length === 0) {
    const sessionParams = getSessionStorageParameters();
    Object.assign(params, sessionParams);
  }

  // 3. Cookies (if neither URL nor session available)
  if (Object.keys(params).length === 0) {
    const cookieParams = getCookieParameters();
    Object.assign(params, cookieParams);
  }

  // 4. Always return object (never null/undefined)
  return params;
}
```

### Storage Duration Rules

**URL Parameters**:
- Duration: Single page load
- Persistence: None
- Cleanup: Automatic on navigation

**SessionStorage**:
- Duration: Browser session
- Persistence: Until browser/tab close
- Cleanup: Automatic on session end
- Key format: `cuft_utm_[parameter_name]`

**Cookies**:
- Duration: 30 days (configurable)
- Persistence: Cross-session
- Cleanup: Automatic expiration or manual
- Key format: `cuft_utm_[parameter_name]`
- SameSite: Lax
- Secure: true (HTTPS only)

---

## Click ID Tracking

### Supported Click ID Types

**Google Ads Click IDs**:
- `gclid`: Standard Google Ads click identifier
- `gbraid`: Google iOS click identifier (iOS 14.5+)
- `wbraid`: Google Web-to-App click identifier

**Social Media Click IDs**:
- `fbclid`: Facebook/Meta click identifier
- `ttclid`: TikTok click identifier
- `twclid`: Twitter/X click identifier
- `snap_click_id`: Snapchat click identifier
- `li_fat_id`: LinkedIn click identifier
- `pclid`: Pinterest click identifier

**Search Engine Click IDs**:
- `msclkid`: Microsoft/Bing click identifier

**Generic Click IDs**:
- `click_id`: Generic/custom click identifier

### Click ID Validation

**Format Requirements**:
```javascript
const clickIdPatterns = {
  gclid: /^[A-Za-z0-9_-]+$/,
  gbraid: /^[A-Za-z0-9_-]+$/,
  wbraid: /^[A-Za-z0-9_-]+$/,
  fbclid: /^[A-Za-z0-9_-]+$/,
  msclkid: /^[A-Za-z0-9_-]+$/,
  ttclid: /^[A-Za-z0-9_-]+$/,
  li_fat_id: /^[A-Za-z0-9_-]+$/,
  twclid: /^[A-Za-z0-9_-]+$/,
  snap_click_id: /^[A-Za-z0-9_-]+$/,
  pclid: /^[A-Za-z0-9_-]+$/,
  click_id: /^[A-Za-z0-9_-]+$/
};
```

**Validation Rules**:
- Must contain only alphanumeric characters, hyphens, and underscores
- Minimum length: 1 character
- Maximum length: 500 characters
- Invalid characters are stripped, not rejected

### Click ID Storage

**Storage Strategy**: Same hierarchical approach as UTM parameters

**Storage Duration**:
- URL: Single page load
- SessionStorage: Browser session
- Cookies: 90 days (longer than UTM due to attribution importance)

**Cross-Domain Considerations**:
- Click IDs MUST persist across subdomain navigation
- Cookie domain MUST be set to support subdomain sharing
- Storage keys MUST be consistent across domains

---

## Data Retrieval Implementation

### URL Parameter Extraction

```javascript
function getURLParameters() {
  const params = {};
  const urlParams = new URLSearchParams(window.location.search);

  // UTM Parameters
  ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'].forEach(param => {
    const value = urlParams.get(param);
    if (value) {
      params[param] = sanitizeParameter(value);
    }
  });

  // Click IDs
  Object.keys(clickIdPatterns).forEach(clickId => {
    const value = urlParams.get(clickId);
    if (value && clickIdPatterns[clickId].test(value)) {
      params[clickId] = sanitizeParameter(value);
    }
  });

  return params;
}
```

### SessionStorage Operations

```javascript
function storeToSessionStorage(params) {
  Object.entries(params).forEach(([key, value]) => {
    try {
      sessionStorage.setItem(`cuft_${key}`, value);
    } catch (e) {
      // Handle storage quota exceeded
      console.warn(`[CUFT] SessionStorage error for ${key}:`, e);
    }
  });
}

function getSessionStorageParameters() {
  const params = {};
  const keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

  keys.concat(Object.keys(clickIdPatterns)).forEach(key => {
    try {
      const value = sessionStorage.getItem(`cuft_${key}`);
      if (value) {
        params[key] = value;
      }
    } catch (e) {
      // Handle storage access errors
    }
  });

  return params;
}
```

### Cookie Operations

```javascript
function storeToCookie(key, value, days = 30) {
  try {
    const expires = new Date();
    expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));

    document.cookie = `cuft_${key}=${encodeURIComponent(value)}; ` +
      `expires=${expires.toUTCString()}; ` +
      `path=/; SameSite=Lax; Secure`;
  } catch (e) {
    console.warn(`[CUFT] Cookie storage error for ${key}:`, e);
  }
}

function getCookieParameters() {
  const params = {};
  const cookies = document.cookie.split(';');

  cookies.forEach(cookie => {
    const [name, value] = cookie.trim().split('=');
    if (name && name.startsWith('cuft_')) {
      const paramName = name.replace('cuft_', '');
      if (value) {
        params[paramName] = decodeURIComponent(value);
      }
    }
  });

  return params;
}
```

---

## Data Sanitization

### Parameter Sanitization Rules

**All parameters MUST be sanitized before storage or transmission**:

```javascript
function sanitizeParameter(value) {
  if (typeof value !== 'string') {
    value = String(value);
  }

  // Remove potentially harmful characters
  value = value.replace(/[<>\"'&]/g, '');

  // Limit length
  value = value.substring(0, 500);

  // Trim whitespace
  value = value.trim();

  return value;
}
```

**Sanitization Requirements**:
- Remove HTML/XML characters: `<`, `>`, `"`, `'`, `&`
- Limit maximum length to 500 characters
- Trim leading/trailing whitespace
- Convert non-strings to strings safely

### Security Considerations

**XSS Prevention**:
- Never use innerHTML with parameter values
- Always use textContent or safe DOM methods
- Sanitize before any DOM insertion

**Data Privacy**:
- No PII should be captured in UTM parameters
- Click IDs are pseudonymous identifiers only
- Storage duration limits prevent indefinite tracking

**GDPR Compliance**:
- Parameters are processed for legitimate business interests
- No personal data is intentionally captured
- Users can clear cookies/storage to remove data

---

## Error Handling Requirements

### Required Error Handling

**Storage Errors**: Handle quota exceeded, access denied, and other storage errors gracefully.

**Invalid Parameters**: Filter out invalid parameters rather than failing completely.

**Cross-Domain Issues**: Handle cookie/storage access errors across different domains.

**Browser Compatibility**: Provide fallbacks for browsers without storage support.

### Error Logging

**Debug Mode Logging**:
```javascript
function logParameterError(operation, parameter, error) {
  if (window.cuftDebug) {
    console.error(`[CUFT] Parameter ${operation} error:`, {
      parameter: parameter,
      error: error.message,
      timestamp: new Date().toISOString()
    });
  }
}
```

**Production Mode**: Silent error handling with minimal logging.

---

## Performance Requirements

### Performance Constraints

**Parameter Retrieval**: < 5ms per retrieval operation
**Storage Operations**: < 2ms per store operation
**Memory Impact**: < 500 bytes per parameter set
**DOM Queries**: Minimize URL parsing operations

### Optimization Strategies

**Caching**: Cache parsed URL parameters for multiple accesses
**Lazy Loading**: Only retrieve parameters when needed
**Batch Operations**: Group storage operations to minimize overhead
**Memory Management**: Clear unused parameters from memory

---

## Testing Requirements

### Required Tests

**Parameter Retrieval Tests**:
- URL parameter extraction accuracy
- SessionStorage fallback functionality
- Cookie fallback functionality
- Sanitization effectiveness

**Storage Tests**:
- Cross-session persistence
- Storage quota handling
- Cross-domain functionality
- Expiration behavior

**Error Handling Tests**:
- Invalid parameter handling
- Storage access errors
- Cross-domain restrictions
- Browser compatibility

### Test Data Sets

**Valid UTM Sets**:
```javascript
const validUTMTests = [
  {
    utm_source: 'google',
    utm_medium: 'cpc',
    utm_campaign: 'summer_2025'
  },
  {
    utm_source: 'facebook',
    utm_medium: 'social',
    utm_campaign: 'product_launch',
    utm_content: 'video_ad'
  }
];
```

**Valid Click ID Tests**:
```javascript
const validClickIdTests = [
  { gclid: 'TeSter-123_abc' },
  { fbclid: 'IwAR1234567890' },
  { msclkid: 'abcd1234efgh5678' }
];
```

**Invalid Parameter Tests**:
- XSS attempts: `<script>alert('xss')</script>`
- Overly long strings: 1000+ character values
- Special characters: quotes, ampersands, etc.

---

## Integration Requirements

### Framework Integration

**Each form framework MUST**:
- Call parameter retrieval before form submission
- Include available parameters in dataLayer events
- Handle parameter unavailability gracefully
- Not fail if parameter system is unavailable

### Third-Party Integration

**Google Analytics**: Parameters MUST be compatible with GA4 enhanced ecommerce
**Google Tag Manager**: All parameters MUST be accessible via dataLayer
**Facebook Pixel**: Click IDs MUST support Facebook attribution
**Other Platforms**: Parameters MUST follow industry standard naming

---

This specification ensures reliable, consistent tracking parameter management across all form frameworks while maintaining performance, security, and privacy standards.