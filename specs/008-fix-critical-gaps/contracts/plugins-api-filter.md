# Contract: plugins_api Filter

**Feature**: 008-fix-critical-gaps (FR-102)
**Purpose**: Provide plugin information for WordPress "View Details" modal
**Hook**: `plugins_api` filter
**Priority**: 10
**Accepted Parameters**: 3

---

## Overview

This filter allows custom plugin information to be returned for the "View Details" modal that appears when users click "View Details" on an available update. WordPress core calls this filter when requesting plugin information, and custom update systems can override the default WordPress.org API response.

---

## Filter Signature

```php
/**
 * Filter plugin information for custom update server
 *
 * @param false|object|array $result The result object or array. Default false.
 * @param string $action The type of information being requested from the Plugin Installation API.
 * @param object $args Plugin API arguments.
 * @return object|false Plugin information object or false to pass through.
 */
apply_filters('plugins_api', false, 'plugin_information', $args);
```

---

## Input Parameters

### Parameter 1: $result
- **Type**: `false|object|array`
- **Default**: `false`
- **Description**: The result from previous filters or default API call
- **Contract Behavior**: NEVER modify this value, always return new object or false

### Parameter 2: $action
- **Type**: `string`
- **Expected Value**: `'plugin_information'`
- **Description**: The type of information being requested
- **Contract Behavior**: Return `$result` unchanged if not `'plugin_information'`

### Parameter 3: $args
- **Type**: `object`
- **Required Properties**:
  - `$args->slug` (string): Plugin slug being requested
- **Description**: Arguments for the API request
- **Contract Behavior**: Check `$args->slug === 'choice-uft'` before processing

---

## Output Specification

### Success Case: Return Plugin Information Object

Return an object with the following structure:

```php
(object) array(
    // Required fields
    'name'              => string,  // Plugin display name
    'slug'              => string,  // Must equal 'choice-uft'
    'version'           => string,  // Latest version (semver format)
    'author'            => string,  // Author name
    'author_profile'    => string,  // HTTPS URL to author profile
    'homepage'          => string,  // HTTPS URL to plugin homepage
    'requires'          => string,  // Min WordPress version (e.g., "5.0")
    'tested'            => string,  // Tested up to WordPress version (e.g., "6.7")
    'requires_php'      => string,  // Min PHP version (e.g., "7.0")
    'download_link'     => string,  // HTTPS URL to ZIP download
    'trunk'             => string,  // Same as download_link
    'last_updated'      => string,  // ISO 8601 date format
    'sections'          => array(   // HTML content for modal tabs
        'description'   => string,  // Sanitized HTML
        'installation'  => string,  // Sanitized HTML
        'changelog'     => string,  // Sanitized HTML (optional, see graceful degradation)
    ),

    // Optional fields
    'banners'           => array(
        'high' => string,           // HTTPS URL to 1544x500 image
        'low'  => string,           // HTTPS URL to 772x250 image
    ),
    'icons'             => array(
        '1x'   => string,           // HTTPS URL to 128x128 image
        '2x'   => string,           // HTTPS URL to 256x256 image
    ),
)
```

### Pass-Through Case: Return false

Return `false` (boolean) in these scenarios:
- `$action !== 'plugin_information'`
- `$args->slug !== 'choice-uft'`
- Cannot fetch plugin data and no cached fallback available

**WordPress Behavior**: When `false` returned, WordPress continues with default behavior (WordPress.org API or "information not available" message)

---

## Data Validation Requirements

### Required Field Validation

| Field | Validation Rule | Error Handling |
|-------|----------------|----------------|
| `name` | Non-empty string, max 100 chars | Use hardcoded fallback |
| `slug` | Must equal `'choice-uft'` exactly | Return `false` |
| `version` | Match regex `/^\d+\.\d+\.\d+$/` | Return `false` |
| `download_link` | Valid HTTPS URL, GitHub domain | Return `false` |
| `requires` | Valid version format | Use hardcoded fallback |
| `tested` | Valid version format | Use hardcoded fallback |
| `requires_php` | Valid version format | Use hardcoded fallback |
| `sections` | Array with at least description key | Use hardcoded fallback |

### HTML Sanitization

ALL HTML content in `sections` array MUST be sanitized:

```php
$sections['description']  = wp_kses_post($raw_description);
$sections['installation'] = wp_kses_post($raw_installation);
$sections['changelog']    = wp_kses_post($raw_changelog);
```

**Allowed Tags**: WordPress default post tags (p, a, ul, ol, li, strong, em, h2, h3, h4, code, pre, blockquote)

---

## Caching Strategy

### Cache Key
```php
$cache_key = 'cuft_plugin_info'; // Site transient
```

### Cache Duration
- **Primary**: 12 hours (`12 * HOUR_IN_SECONDS`)
- **On Rate Limit**: Use cached data even if expired

### Cache Workflow

```php
// Check cache first
$cached = get_transient('cuft_plugin_info');

if ($cached !== false) {
    // Use conditional request with ETag
    $headers['If-None-Match'] = $cached['etag'];
}

// Make API request
$response = wp_remote_get($api_url, array('headers' => $headers));

// Handle 304 Not Modified
if (wp_remote_retrieve_response_code($response) === 304) {
    return $cached['data']; // Use cached data
}

// Store new data with ETag
set_transient('cuft_plugin_info', array(
    'data' => $plugin_info,
    'etag' => wp_remote_retrieve_header($response, 'etag'),
), 12 * HOUR_IN_SECONDS);
```

---

## Error Handling

### GitHub API Unavailable (Network Error)

**Scenario**: GitHub API request fails due to network error

**Behavior**:
1. Check for cached data (even if expired)
2. If cached data exists, return it
3. If no cache, return hardcoded plugin info with omitted changelog
4. Log error to PHP error_log

**Contract**: MUST return valid plugin object or `false`, never `WP_Error`

### GitHub API Rate Limit Exceeded (403)

**Scenario**: HTTP 403 response with `x-ratelimit-remaining: 0`

**Behavior**:
1. Parse `x-ratelimit-reset` header (Unix timestamp)
2. Calculate wait time: `$wait_minutes = ceil(($reset_time - time()) / 60)`
3. Return cached data (even if expired)
4. Show admin notice: "GitHub API rate limit exceeded. Showing cached plugin information. Resets in {X} minutes."

**Contract**: MUST NOT abort, use cached data

### GitHub API Changelog Fetch Failure (Graceful Degradation)

**Scenario**: Release notes cannot be fetched from GitHub API

**Behavior**:
1. Return all OTHER plugin information (description, installation, version, etc.)
2. OMIT `changelog` key from `sections` array
3. WordPress will hide "Changelog" tab in modal

**Contract**: Partial success is acceptable, do not fail entire request

```php
$sections = array(
    'description'  => $hardcoded_description,
    'installation' => $hardcoded_installation,
    // NO 'changelog' key - WordPress handles gracefully
);
```

### Invalid JSON Response

**Scenario**: GitHub API returns malformed JSON

**Behavior**:
1. Check `json_last_error() !== JSON_ERROR_NONE`
2. Return `false` (pass through to WordPress)
3. Log error to PHP error_log

**Contract**: Never return malformed data

---

## Security Requirements

### Input Validation

```php
// Validate action parameter
if ($action !== 'plugin_information') {
    return $result; // Pass through
}

// Validate slug parameter exists
if (!isset($args->slug)) {
    return $result;
}

// Validate slug matches our plugin
if ($args->slug !== 'choice-uft') {
    return $result;
}
```

### URL Validation

```php
// Validate download URL is from GitHub
if (strpos($download_link, 'https://github.com/ChoiceOMG/choice-uft/releases/download/') !== 0) {
    error_log('CUFT: Invalid download URL rejected: ' . $download_link);
    return false;
}
```

### HTML Sanitization

```php
// Sanitize all HTML content
foreach ($sections as $key => $content) {
    $sections[$key] = wp_kses_post($content);
}
```

### Capability Checks

**NOT REQUIRED**: WordPress core handles capability checks before calling this filter. The filter itself is read-only and does not need additional permission checks.

---

## Performance Requirements

### Response Time Targets

| Scenario | Target |
|----------|--------|
| Cache hit | <10 ms |
| Cache miss (GitHub API) | <2 seconds |
| GitHub API unavailable | <100 ms (fallback to cache) |

### GitHub API Request Timeout

```php
wp_remote_get($api_url, array(
    'timeout' => 15, // 15-second timeout
    'headers' => $headers,
));
```

---

## Testing Requirements

### Test Cases

1. **TC-001: Normal Request**
   - Input: `$action = 'plugin_information'`, `$args->slug = 'choice-uft'`
   - Expected: Return complete plugin object with all required fields

2. **TC-002: Wrong Action**
   - Input: `$action = 'query_plugins'`
   - Expected: Return `$result` unchanged (pass through)

3. **TC-003: Wrong Slug**
   - Input: `$args->slug = 'other-plugin'`
   - Expected: Return `$result` unchanged (pass through)

4. **TC-004: Cache Hit**
   - Input: Valid request with cached data available
   - Expected: Return cached data without GitHub API request

5. **TC-005: Cache Miss**
   - Input: Valid request with expired or missing cache
   - Expected: Fetch from GitHub API, cache result, return data

6. **TC-006: GitHub API Unavailable**
   - Input: Valid request, GitHub API returns network error
   - Expected: Return cached data (even if expired) or hardcoded fallback

7. **TC-007: Rate Limit Exceeded**
   - Input: Valid request, GitHub API returns 403 with rate limit headers
   - Expected: Return cached data, do NOT make repeated requests

8. **TC-008: Changelog Fetch Failure**
   - Input: Valid request, changelog cannot be fetched
   - Expected: Return plugin info WITHOUT changelog key in sections

9. **TC-009: Invalid JSON Response**
   - Input: Valid request, GitHub API returns malformed JSON
   - Expected: Return `false`, log error

10. **TC-010: HTML Sanitization**
    - Input: Plugin info with malicious HTML in sections
    - Expected: Strip disallowed tags, return sanitized HTML

11. **TC-011: ETag Conditional Request**
    - Input: Valid request with cached ETag
    - Expected: Send `If-None-Match` header, handle 304 response

---

## Example Implementation

```php
add_filter('plugins_api', 'cuft_plugins_api_handler', 10, 3);

function cuft_plugins_api_handler($result, $action, $args) {
    // Early exit: wrong action
    if ($action !== 'plugin_information') {
        return $result;
    }

    // Early exit: wrong slug
    if (!isset($args->slug) || $args->slug !== 'choice-uft') {
        return $result;
    }

    // Check cache
    $cached = get_transient('cuft_plugin_info');

    if ($cached !== false) {
        // Check if cache is fresh enough
        if ($cached['timestamp'] > (time() - 12 * HOUR_IN_SECONDS)) {
            return $cached['data'];
        }

        // Cache expired, but keep for conditional request
        $etag = $cached['etag'];
    }

    // Fetch from GitHub with conditional request
    $api_url = 'https://api.github.com/repos/ChoiceOMG/choice-uft/releases/latest';
    $headers = array(
        'Accept' => 'application/vnd.github.v3+json',
        'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
    );

    if (!empty($etag)) {
        $headers['If-None-Match'] = $etag;
    }

    $response = wp_remote_get($api_url, array(
        'headers' => $headers,
        'timeout' => 15,
    ));

    // Handle errors
    if (is_wp_error($response)) {
        error_log('CUFT: GitHub API error: ' . $response->get_error_message());

        // Return cached data if available
        if ($cached !== false) {
            return $cached['data'];
        }

        // Return hardcoded fallback
        return cuft_get_hardcoded_plugin_info();
    }

    $response_code = wp_remote_retrieve_response_code($response);

    // Handle 304 Not Modified
    if ($response_code === 304) {
        return $cached['data'];
    }

    // Handle rate limit
    if ($response_code === 403) {
        $rate_limit_remaining = wp_remote_retrieve_header($response, 'x-ratelimit-remaining');

        if ($rate_limit_remaining === '0') {
            error_log('CUFT: GitHub API rate limit exceeded');

            // Return cached data
            if ($cached !== false) {
                return $cached['data'];
            }

            return cuft_get_hardcoded_plugin_info();
        }
    }

    // Handle non-200 responses
    if ($response_code !== 200) {
        error_log('CUFT: GitHub API returned HTTP ' . $response_code);

        if ($cached !== false) {
            return $cached['data'];
        }

        return cuft_get_hardcoded_plugin_info();
    }

    // Parse response
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('CUFT: Invalid JSON from GitHub API');
        return false;
    }

    // Build plugin info object
    $plugin_info = (object) array(
        'name'              => 'Choice Universal Form Tracker',
        'slug'              => 'choice-uft',
        'version'           => ltrim($data['tag_name'], 'v'),
        'author'            => 'Choice Marketing',
        'author_profile'    => 'https://github.com/ChoiceOMG',
        'homepage'          => 'https://github.com/ChoiceOMG/choice-uft',
        'requires'          => '5.0',
        'tested'            => '6.7',
        'requires_php'      => '7.0',
        'download_link'     => cuft_find_zip_asset($data['assets']),
        'trunk'             => cuft_find_zip_asset($data['assets']),
        'last_updated'      => $data['published_at'],
        'sections'          => array(
            'description'   => wp_kses_post(cuft_get_description()),
            'installation'  => wp_kses_post(cuft_get_installation()),
            'changelog'     => wp_kses_post($data['body']),
        ),
    );

    // Cache with ETag
    $etag = wp_remote_retrieve_header($response, 'etag');
    set_transient('cuft_plugin_info', array(
        'data' => $plugin_info,
        'etag' => $etag,
        'timestamp' => time(),
    ), 12 * HOUR_IN_SECONDS);

    return $plugin_info;
}
```

---

## WordPress Core Behavior

When this filter returns a valid plugin object:
1. WordPress displays "View Details" modal with provided information
2. Modal shows tabs for Description, Installation, Changelog (if provided)
3. "Update Now" button in modal uses `download_link` field
4. WordPress calls `Plugin_Upgrader` to perform update

When this filter returns `false`:
1. WordPress attempts to fetch from WordPress.org API
2. If not found there, shows "information not available" message
3. Update button still works (uses info from update transient)

---

**Version**: 1.0
**Last Updated**: 2025-10-11
**Status**: Ready for Implementation
