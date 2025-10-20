# Data Model: Auto-BCC Testing Email

**Feature**: 010-auto-bcc-everyting
**Date**: 2025-10-16
**Status**: Complete

## Overview

This document defines the data structures, validation rules, and storage mechanisms for the Auto-BCC Testing Email feature.

---

## Entities

### 1. CUFT_Auto_BCC_Config

**Purpose**: Represents the complete Auto-BCC feature configuration stored as a single WordPress option.

**Storage**: WordPress Options API
- **Option Name**: `cuft_auto_bcc_config`
- **Type**: Serialized array (auto-serialization by WordPress)
- **Scope**: Site-wide (single site or network-wide based on installation)

#### Fields

| Field | Type | Required | Default | Description |
|-------|------|----------|---------|-------------|
| `enabled` | boolean | Yes | `false` | Master on/off toggle for Auto-BCC feature |
| `bcc_email` | string | Conditional* | `''` | Email address to receive BCC copies (validated format) |
| `selected_email_types` | array<string> | Yes | `[]` | List of email type identifiers to BCC |
| `rate_limit_threshold` | integer | No | `100` | Maximum BCC emails per hour (0 = unlimited) |
| `rate_limit_action` | string (enum) | No | `'log_only'` | Action when rate limit exceeded |
| `last_modified` | integer | Yes | `time()` | Unix timestamp of last configuration change |
| `last_modified_by` | integer | Yes | `get_current_user_id()` | WordPress user ID who last modified config |

***Conditional**: `bcc_email` required when `enabled` is `true`

#### Valid Values

**`selected_email_types`** (array of strings):
- `'form_submission'` - Form submission notifications
- `'user_registration'` - New user account registrations
- `'password_reset'` - Password reset requests
- `'comment_notification'` - Comment notifications (new/moderation)
- `'admin_notification'` - Admin-specific notifications
- `'other'` - Catch-all for uncategorized emails (optional)

**`rate_limit_action`** (enum):
- `'log_only'` - Log warning to debug.log, continue BCC operation
- `'pause_until_next_period'` - Disable BCC until transient expires (next hour)

#### Example Data Structure

```php
array(
    'enabled' => true,
    'bcc_email' => 'testing@example.com',
    'selected_email_types' => array(
        'form_submission',
        'user_registration',
    ),
    'rate_limit_threshold' => 100,
    'rate_limit_action' => 'log_only',
    'last_modified' => 1729094400, // Unix timestamp
    'last_modified_by' => 1,        // Admin user ID
)
```

#### Validation Rules

**`enabled`** (boolean):
- Must be PHP boolean `true` or `false`
- No sanitization required (type-cast to boolean)

**`bcc_email`** (string):
- **Required when `enabled` is `true`**
- Must pass WordPress `is_email()` validation
- Sanitized with `sanitize_email()`
- Max length: 254 characters (RFC 5321 email limit)
- **Error if invalid**: "Invalid email address format. Please enter a valid email."

**`selected_email_types`** (array):
- Each element must be one of the valid type identifiers (see above)
- Empty array is valid (no email types selected → no BCC sent)
- Duplicates are removed (`array_unique()`)
- Invalid types are filtered out with warning
- **Error if invalid type**: "Invalid email type '{type}' ignored."

**`rate_limit_threshold`** (integer):
- Must be integer ≥ 0
- 0 = unlimited (no rate limiting)
- Reasonable maximum: 10,000 (performance safeguard)
- **Error if out of range**: "Rate limit must be between 0 and 10,000."

**`rate_limit_action`** (string):
- Must be one of: `'log_only'`, `'pause_until_next_period'`
- Defaults to `'log_only'` if invalid value provided
- **Warning if invalid**: "Invalid rate limit action '{action}', defaulting to 'log_only'."

**`last_modified`** (integer):
- Automatically set to `time()` on save
- Read-only from user perspective
- Unix timestamp (seconds since epoch)

**`last_modified_by`** (integer):
- Automatically set to `get_current_user_id()` on save
- Read-only from user perspective
- Must be valid WordPress user ID

#### State Transitions

```
[Initial State]
(enabled: false, bcc_email: '', selected_email_types: [])
    ↓
    User enables feature + configures email
    ↓
[Enabled State]
(enabled: true, bcc_email: 'test@example.com', selected_email_types: ['form_submission'])
    ↓
    BCC operation runs (wp_mail filter intercepts emails)
    ↓
[Active BCC State]
(rate limiter tracks count, emails intercepted)
    ↓
    User disables feature
    ↓
[Disabled State]
(enabled: false, config preserved for re-enabling)
```

**Key Invariants**:
1. If `enabled` is `true`, `bcc_email` MUST be non-empty and valid
2. `selected_email_types` can be empty even if `enabled` is `true` (results in no BCC operations)
3. `rate_limit_threshold` of 0 means unlimited (no rate limiting applied)

---

### 2. CUFT_BCC_Rate_Limit_Counter (Transient)

**Purpose**: Tracks number of BCC emails sent in the current hour for rate limiting.

**Storage**: WordPress Transients API
- **Transient Key Format**: `cuft_bcc_rate_limit_{YYYY-MM-DD-HH}`
- **Example**: `cuft_bcc_rate_limit_2025-10-16-14`
- **Type**: Integer (count of BCC emails)
- **Expiry**: 1 hour (`HOUR_IN_SECONDS`)

#### Fields

| Field | Type | Description |
|-------|------|-------------|
| (value) | integer | Count of BCC emails sent in current hour |

#### Behavior

- **Creation**: First BCC email of the hour creates transient with value `1`
- **Increment**: Each subsequent BCC increments value by 1
- **Check**: Before each BCC, current count compared to threshold
- **Expiry**: Automatic deletion after 1 hour (WordPress handles cleanup)

#### Example Usage

```php
$hour_key = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
$count = get_transient( $hour_key );

if ( false === $count ) {
    // First email this hour
    set_transient( $hour_key, 1, HOUR_IN_SECONDS );
} else {
    // Increment count
    set_transient( $hour_key, $count + 1, HOUR_IN_SECONDS );
}
```

---

## Data Access Patterns

### Read Configuration

```php
$config = get_option( 'cuft_auto_bcc_config', array(
    'enabled' => false,
    'bcc_email' => '',
    'selected_email_types' => array(),
    'rate_limit_threshold' => 100,
    'rate_limit_action' => 'log_only',
    'last_modified' => 0,
    'last_modified_by' => 0,
) );
```

**Performance**: Single database query (cached by WordPress object cache)

### Save Configuration

```php
$config = array(
    'enabled' => true,
    'bcc_email' => sanitize_email( $_POST['bcc_email'] ),
    'selected_email_types' => array_map( 'sanitize_text_field', $_POST['email_types'] ),
    'rate_limit_threshold' => absint( $_POST['rate_limit_threshold'] ),
    'rate_limit_action' => sanitize_text_field( $_POST['rate_limit_action'] ),
    'last_modified' => time(),
    'last_modified_by' => get_current_user_id(),
);

update_option( 'cuft_auto_bcc_config', $config );
```

**Performance**: Single database query (WordPress handles serialization/caching)

### Check Rate Limit

```php
$hour_key = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );
$count = get_transient( $hour_key );

if ( false !== $count && $count >= $threshold ) {
    // Rate limit exceeded
    return false;
}

// Increment counter
set_transient( $hour_key, ( $count ?: 0 ) + 1, HOUR_IN_SECONDS );
return true; // Under limit
```

**Performance**: 2 transient operations (<5ms total, object cache compatible)

---

## Database Schema

**No custom tables required** - Uses WordPress Options and Transients tables:

### `wp_options` Table (Configuration)
```sql
INSERT INTO wp_options (option_name, option_value, autoload)
VALUES (
    'cuft_auto_bcc_config',
    'a:7:{s:7:"enabled";b:1;s:9:"bcc_email";s:20:"test@example.com";...}',
    'yes'
);
```

**Autoload**: `yes` (configuration loaded on every request for fast access)

### `wp_options` Table (Rate Limit Transients)
```sql
INSERT INTO wp_options (option_name, option_value, autoload)
VALUES (
    '_transient_cuft_bcc_rate_limit_2025-10-16-14',
    '42', -- Count
    'no'
);

INSERT INTO wp_options (option_name, option_value, autoload)
VALUES (
    '_transient_timeout_cuft_bcc_rate_limit_2025-10-16-14',
    '1729098000', -- Expiry timestamp
    'no'
);
```

**Autoload**: `no` (transients not loaded on every request)

---

## Migration Strategy

### From No Config (Fresh Install)

**Default Configuration**:
```php
array(
    'enabled' => false,
    'bcc_email' => '',
    'selected_email_types' => array(),
    'rate_limit_threshold' => 100,
    'rate_limit_action' => 'log_only',
    'last_modified' => 0,
    'last_modified_by' => 0,
)
```

**No database migration required** - Config created on first save.

### Future Schema Changes

If fields need to be added in future versions:

```php
public function migrate_config() {
    $config = get_option( 'cuft_auto_bcc_config', array() );

    // Add new fields with defaults
    if ( ! isset( $config['new_field'] ) ) {
        $config['new_field'] = 'default_value';
        update_option( 'cuft_auto_bcc_config', $config );
    }
}
```

**Backward Compatibility**: Always provide defaults for missing keys.

---

## Validation Class Structure

### CUFT_Auto_BCC_Validator

**Purpose**: Centralized validation logic for configuration data.

**Methods**:

```php
class CUFT_Auto_BCC_Validator {

    /**
     * Validate entire configuration array
     *
     * @param array $config Configuration to validate
     * @return array|WP_Error Valid config or error object
     */
    public function validate_config( $config ) {
        $errors = array();

        // Validate each field
        if ( ! $this->validate_enabled( $config['enabled'] ) ) {
            $errors[] = 'Invalid enabled value';
        }

        if ( ! empty( $config['bcc_email'] ) ) {
            $email_valid = $this->validate_email( $config['bcc_email'] );
            if ( is_wp_error( $email_valid ) ) {
                $errors[] = $email_valid->get_error_message();
            }
        } elseif ( $config['enabled'] ) {
            $errors[] = 'Email address required when feature is enabled';
        }

        // ... additional validations

        if ( ! empty( $errors ) ) {
            return new WP_Error( 'invalid_config', implode( '; ', $errors ) );
        }

        return $this->sanitize_config( $config );
    }

    /**
     * Validate email address
     *
     * @param string $email Email to validate
     * @return true|WP_Error
     */
    public function validate_email( $email ) {
        if ( ! is_email( $email ) ) {
            return new WP_Error(
                'invalid_email',
                'Invalid email address format'
            );
        }
        return true;
    }

    /**
     * Validate email types array
     *
     * @param array $types Email types to validate
     * @return array Filtered valid types
     */
    public function validate_email_types( $types ) {
        $valid_types = array(
            'form_submission',
            'user_registration',
            'password_reset',
            'comment_notification',
            'admin_notification',
            'other',
        );

        return array_intersect( $types, $valid_types );
    }

    /**
     * Sanitize configuration
     *
     * @param array $config Raw configuration
     * @return array Sanitized configuration
     */
    private function sanitize_config( $config ) {
        return array(
            'enabled' => (bool) $config['enabled'],
            'bcc_email' => sanitize_email( $config['bcc_email'] ),
            'selected_email_types' => array_map( 'sanitize_text_field', $config['selected_email_types'] ),
            'rate_limit_threshold' => absint( $config['rate_limit_threshold'] ),
            'rate_limit_action' => sanitize_text_field( $config['rate_limit_action'] ),
            'last_modified' => time(),
            'last_modified_by' => get_current_user_id(),
        );
    }
}
```

---

## Performance Considerations

### Configuration Access
- **Reads**: 1 database query (WordPress object cache reduces to 0 queries after first load)
- **Writes**: 1 database query (infrequent - only on settings save)
- **Autoload**: Yes (config loaded on every request, but small size ~500 bytes)

### Rate Limit Checks
- **Reads**: 1 transient get (object cache compatible, <1ms)
- **Writes**: 1 transient set per BCC email (<1ms)
- **Cleanup**: Automatic (WordPress cron deletes expired transients)

### Total Overhead per Email
- Config read: ~0ms (cached)
- Rate limit check: ~5ms (transient read/write)
- Total: <5ms per email (well under 50ms target)

---

## Security Considerations

### Input Sanitization
- All user inputs sanitized before storage
- Email addresses validated with `is_email()` and `sanitize_email()`
- Text fields sanitized with `sanitize_text_field()`
- Integers sanitized with `absint()`

### Capability Checks
- Only users with `update_plugins` capability can modify configuration
- WordPress nonce validation on all AJAX save operations

### SQL Injection
- No direct SQL queries (WordPress Options/Transients API used)
- WordPress handles escaping and parameterization

### XSS Prevention
- All outputs escaped with `esc_html()`, `esc_attr()`, etc.
- Email address displayed in UI escaped with `esc_html( $config['bcc_email'] )`

---

## Data Model Summary

**Single Entity**: `CUFT_Auto_BCC_Config` (WordPress option)
**Transient Counters**: `cuft_bcc_rate_limit_{hour}` (automatic expiry)
**No Custom Tables**: Uses WordPress core storage mechanisms
**Validation**: Centralized in `CUFT_Auto_BCC_Validator` class
**Performance**: <5ms overhead, object cache compatible
**Security**: Full sanitization, capability checks, WordPress API escaping

---
*Data model complete: 2025-10-16*
*Ready for contract generation (Phase 1 continued)*
