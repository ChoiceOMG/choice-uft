# Contract: wp_mail Filter for Auto-BCC

**Feature**: 010-auto-bcc-everyting
**Hook**: `wp_mail` filter
**Priority**: 10

## Purpose
Intercept all WordPress emails and add BCC header when Auto-BCC feature is enabled.

## Input Parameters
```php
$args = array(
    'to' => 'recipient@example.com', // string or array
    'subject' => 'Email Subject',
    'message' => 'Email body content',
    'headers' => array(), // string or array
    'attachments' => array(),
);
```

## Output
Modified `$args` array with BCC header added to `headers` field.

## Contract Requirements

### MUST
1. Return unmodified `$args` when feature is disabled
2. Add BCC header when feature is enabled and email type matches selection
3. Skip BCC when configured email already in TO/CC fields (duplicate prevention)
4. Check rate limit before adding BCC
5. Handle errors gracefully (never throw exceptions)

### MUST NOT
1. Modify TO, CC, FROM, SUBJECT, or MESSAGE fields
2. Affect primary email delivery
3. Log PII to console (debug log only)

## Test Cases
- TC-001: Feature disabled → no BCC added
- TC-002: Feature enabled, email type matches → BCC added
- TC-003: Duplicate recipient → BCC skipped
- TC-004: Rate limit exceeded → BCC skipped, warning logged
