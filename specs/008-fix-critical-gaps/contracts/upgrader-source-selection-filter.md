# Contract: upgrader_source_selection Filter

**Feature**: 008-fix-critical-gaps (FR-103)
**Purpose**: Rename extracted plugin directory to match WordPress expectations
**Hook**: `upgrader_source_selection` filter
**Priority**: 10
**Accepted Parameters**: 4

---

## Overview

This filter allows modification of the extracted plugin directory path before WordPress validates the plugin structure. GitHub release ZIPs contain directories named like `choice-uft-v3.17.0/`, but WordPress expects `choice-uft/`. Without renaming, WordPress will fail to recognize the plugin location and leave it in a broken state.

---

## The Problem

**GitHub ZIP Structure**:
```
choice-uft-v3.17.0.zip
└── choice-uft-v3.17.0/
    ├── choice-universal-form-tracker.php
    ├── includes/
    └── assets/
```

**WordPress Expectation**:
```
/wp-content/plugins/choice-uft/
├── choice-universal-form-tracker.php
├── includes/
└── assets/
```

**Without This Filter**:
1. WordPress extracts to `/wp-content/upgrade/choice-uft-v3.17.0/`
2. WordPress tries to copy to `/wp-content/plugins/choice-uft-v3.17.0/`
3. WordPress cannot find original plugin at `/wp-content/plugins/choice-uft/`
4. Update fails or leaves versioned directory

**With This Filter**:
1. WordPress extracts to `/wp-content/upgrade/choice-uft-v3.17.0/`
2. Filter renames to `/wp-content/upgrade/choice-uft/`
3. WordPress copies to `/wp-content/plugins/choice-uft/` (correct location)
4. Update succeeds

---

## Filter Signature

```php
/**
 * Rename extracted plugin directory to match expected slug
 *
 * @param string $source File source location (extracted directory path).
 * @param string $remote_source Remote file source location (downloaded ZIP path).
 * @param WP_Upgrader $upgrader WP_Upgrader instance.
 * @param array $hook_extra Extra arguments passed to hooked filters.
 * @return string|WP_Error Modified source location or WP_Error on failure.
 */
apply_filters('upgrader_source_selection', $source, $remote_source, $upgrader, $hook_extra);
```

---

## Input Parameters

### Parameter 1: $source
- **Type**: `string`
- **Format**: Absolute path with trailing slash
- **Example**: `/var/www/html/wp-content/upgrade/choice-uft-v3.17.0/`
- **Description**: Path to extracted plugin directory (before rename)
- **Contract Requirement**: MUST check this matches expected pattern before processing

### Parameter 2: $remote_source
- **Type**: `string`
- **Format**: Absolute path
- **Example**: `/tmp/wordpress-update-abc123.zip`
- **Description**: Path to downloaded ZIP file before extraction
- **Contract Requirement**: Not used by this filter, provided for context

### Parameter 3: $upgrader
- **Type**: `WP_Upgrader` (or subclass like `Plugin_Upgrader`)
- **Description**: Upgrader instance performing the update
- **Contract Requirement**: Type check can verify this is a plugin update

### Parameter 4: $hook_extra
- **Type**: `array`
- **Required Keys**:
  - `plugin` (string): Plugin basename (e.g., `choice-uft/choice-universal-form-tracker.php`)
  - `type` (string): Update type (`'plugin'`, `'theme'`, or `'core'`)
  - `action` (string): Action being performed (`'update'`, `'install'`)
- **Description**: Context about what is being updated
- **Contract Requirement**: MUST check `$hook_extra['plugin']` to identify our plugin

---

## Output Specification

### Success Case: Return Corrected Path

Return a string path with trailing slash:

```php
return '/var/www/html/wp-content/upgrade/choice-uft/';
```

**Contract Requirements**:
- Path MUST be absolute (start with `/`)
- Path MUST end with trailing slash (`/`)
- Path MUST be writable by WordPress
- Directory at path MUST exist
- Directory MUST contain valid plugin files

### Pass-Through Case: Return $source Unchanged

Return `$source` unchanged in these scenarios:
- `$hook_extra['type'] !== 'plugin'` (not a plugin update)
- `$hook_extra['plugin']` does not contain `'choice-uft'` (not our plugin)
- Directory name already correct (no rename needed)

```php
return $source;
```

### Error Case: Return WP_Error

Return `WP_Error` object when operation fails:

```php
return new WP_Error(
    'error_code',
    __('Human-readable error message', 'choice-uft')
);
```

**WordPress Behavior**: Update will be aborted, error message shown to user

---

## Directory Naming Pattern Detection

### Expected Patterns

| Pattern | Valid? | Action |
|---------|--------|--------|
| `choice-uft/` | Yes | Pass through (already correct) |
| `choice-uft-v3.17.0/` | Yes | Rename to `choice-uft/` |
| `choice-uft-3.17.0/` | Yes | Rename to `choice-uft/` |
| `choice-uft-master/` | Yes | Rename to `choice-uft/` (from branch ZIP) |
| `ChoiceOMG-choice-uft-abc123/` | Yes | Rename to `choice-uft/` (from commit ZIP) |
| `other-plugin/` | N/A | Pass through (not our plugin) |
| `choice-uft-something-else/` | Maybe | Validate pattern, rename or error |

### Pattern Validation Logic

```php
$source_basename = basename(rtrim($source, '/'));
$plugin_slug = 'choice-uft';

// Already correct
if ($source_basename === $plugin_slug) {
    return $source;
}

// Starts with our slug (versioned or branch name)
if (strpos($source_basename, $plugin_slug) === 0) {
    // Valid pattern, proceed with rename
}

// GitHub commit format (ChoiceOMG-choice-uft-abc123)
elseif (preg_match('/^ChoiceOMG-' . preg_quote($plugin_slug) . '-[a-f0-9]{7}$/', $source_basename)) {
    // Valid GitHub commit ZIP format, proceed with rename
}

// Unrecognized pattern
else {
    return new WP_Error(
        'incompatible_plugin_archive',
        __('Plugin archive does not contain expected directory structure.', 'choice-uft')
    );
}
```

---

## Rename Operation

### Using WordPress Filesystem API

**CRITICAL**: NEVER use PHP's `rename()` function. Always use `$wp_filesystem->move()`.

```php
global $wp_filesystem;

// Ensure WP_Filesystem is initialized
if (empty($wp_filesystem)) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();
}

// Build corrected path
$corrected_source = dirname($source) . '/' . $plugin_slug . '/';

// Rename directory
$renamed = $wp_filesystem->move($source, $corrected_source, true);

if (!$renamed) {
    return new WP_Error(
        'unable_to_rename_directory',
        sprintf(
            __('Unable to rename plugin directory from %1$s to %2$s.', 'choice-uft'),
            basename(rtrim($source, '/')),
            $plugin_slug
        )
    );
}

return $corrected_source;
```

### Overwrite Flag

The third parameter to `$wp_filesystem->move()` is the overwrite flag:

```php
$wp_filesystem->move($source, $destination, true); // Allow overwrite
```

**Why Overwrite?**: If a previous update failed and left a partial directory, we need to overwrite it.

---

## Error Handling

### Error Scenarios

| Scenario | Error Code | Error Message |
|----------|------------|---------------|
| Directory pattern unrecognized | `incompatible_plugin_archive` | "Plugin archive does not contain expected directory structure." |
| Source directory not found | `source_directory_missing` | "Extracted plugin directory not found at expected location." |
| Rename operation failed | `unable_to_rename_directory` | "Unable to rename plugin directory from {old} to {new}." |
| Destination not writable | `destination_not_writable` | "Plugin directory is not writable. Cannot perform update." |
| WP_Filesystem init failed | `filesystem_error` | "Could not initialize WordPress filesystem." |

### Error Response Format

```php
new WP_Error(
    'error_code',           // Machine-readable code
    __('Error message', 'choice-uft'), // Translatable human message
    array(                  // Optional error data
        'source' => $source,
        'destination' => $destination,
    )
)
```

---

## Validation Requirements

### Pre-Rename Validation

```php
// 1. Verify this is a plugin update
if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
    return $source; // Pass through
}

// 2. Verify this is our plugin
if (!isset($hook_extra['plugin'])) {
    return $source;
}

$plugin_slug = dirname($hook_extra['plugin']);
if ($plugin_slug !== 'choice-uft') {
    return $source; // Pass through
}

// 3. Verify source directory exists
if (!$wp_filesystem->is_dir($source)) {
    return new WP_Error(
        'source_directory_missing',
        __('Extracted plugin directory not found at expected location.', 'choice-uft')
    );
}

// 4. Verify source directory contains plugin file
$plugin_file = $source . 'choice-universal-form-tracker.php';
if (!$wp_filesystem->exists($plugin_file)) {
    return new WP_Error(
        'invalid_plugin_structure',
        __('Plugin archive does not contain main plugin file.', 'choice-uft')
    );
}
```

### Post-Rename Validation

```php
// 1. Verify destination directory exists
if (!$wp_filesystem->is_dir($corrected_source)) {
    return new WP_Error(
        'rename_verification_failed',
        __('Plugin directory rename did not produce expected result.', 'choice-uft')
    );
}

// 2. Verify plugin file exists at new location
$plugin_file = $corrected_source . 'choice-universal-form-tracker.php';
if (!$wp_filesystem->exists($plugin_file)) {
    return new WP_Error(
        'rename_verification_failed',
        __('Plugin file not found after directory rename.', 'choice-uft')
    );
}
```

---

## Security Requirements

### Path Validation

```php
// Prevent directory traversal
if (strpos($source, '..') !== false) {
    return new WP_Error(
        'invalid_path',
        __('Invalid directory path detected.', 'choice-uft')
    );
}

// Ensure path is within WordPress directories
$wp_content_dir = WP_CONTENT_DIR;
if (strpos($source, $wp_content_dir) !== 0) {
    return new WP_Error(
        'invalid_path',
        __('Plugin directory is not within WordPress content directory.', 'choice-uft')
    );
}
```

### Slug Validation

```php
// Ensure plugin slug matches expected value exactly
if ($plugin_slug !== 'choice-uft') {
    return $source; // Pass through, not our plugin
}

// Sanitize slug (defense in depth)
$plugin_slug = sanitize_file_name($plugin_slug);
```

### Capability Checks

**NOT REQUIRED**: WordPress core validates `update_plugins` capability before calling this filter. The filter itself only performs filesystem operations, no additional capability checks needed.

---

## Performance Requirements

### Response Time Targets

| Operation | Target |
|-----------|--------|
| Pass-through (not our plugin) | <1 ms |
| Directory already correct | <5 ms |
| Rename operation | <100 ms |
| Validation failure | <10 ms |

### Filesystem Efficiency

- Use `$wp_filesystem->is_dir()` instead of PHP `is_dir()`
- Use `$wp_filesystem->exists()` instead of PHP `file_exists()`
- Minimize filesystem operations (check once, not multiple times)

---

## Testing Requirements

### Test Cases

1. **TC-001: Versioned Directory (Normal Case)**
   - Input: `$source = '/tmp/choice-uft-v3.17.0/'`, `$hook_extra['plugin'] = 'choice-uft/...'`
   - Expected: Rename to `/tmp/choice-uft/`, return corrected path

2. **TC-002: Already Correct Directory**
   - Input: `$source = '/tmp/choice-uft/'`, `$hook_extra['plugin'] = 'choice-uft/...'`
   - Expected: Return `$source` unchanged (no rename)

3. **TC-003: Not Our Plugin**
   - Input: `$hook_extra['plugin'] = 'other-plugin/...'`
   - Expected: Return `$source` unchanged (pass through)

4. **TC-004: Theme Update**
   - Input: `$hook_extra['type'] = 'theme'`
   - Expected: Return `$source` unchanged (pass through)

5. **TC-005: GitHub Commit ZIP Format**
   - Input: `$source = '/tmp/ChoiceOMG-choice-uft-abc1234/'`
   - Expected: Rename to `/tmp/choice-uft/`, return corrected path

6. **TC-006: Branch ZIP Format**
   - Input: `$source = '/tmp/choice-uft-master/'`
   - Expected: Rename to `/tmp/choice-uft/`, return corrected path

7. **TC-007: Unrecognized Directory Pattern**
   - Input: `$source = '/tmp/something-else-choice-uft/'`
   - Expected: Return `WP_Error` with `incompatible_plugin_archive` code

8. **TC-008: Source Directory Missing**
   - Input: `$source = '/tmp/nonexistent/'`
   - Expected: Return `WP_Error` with `source_directory_missing` code

9. **TC-009: Rename Operation Fails**
   - Input: Valid source, but filesystem permissions deny rename
   - Expected: Return `WP_Error` with `unable_to_rename_directory` code

10. **TC-010: Main Plugin File Missing**
    - Input: Directory exists but doesn't contain `choice-universal-form-tracker.php`
    - Expected: Return `WP_Error` with `invalid_plugin_structure` code

11. **TC-011: Overwrite Existing Directory**
    - Input: Destination directory already exists from previous failed update
    - Expected: Overwrite successfully, return corrected path

---

## Example Implementation

```php
add_filter('upgrader_source_selection', 'cuft_fix_plugin_directory_name', 10, 4);

function cuft_fix_plugin_directory_name($source, $remote_source, $upgrader, $hook_extra) {
    global $wp_filesystem;

    // Early exit: not a plugin update
    if (!isset($hook_extra['type']) || $hook_extra['type'] !== 'plugin') {
        return $source;
    }

    // Early exit: plugin not specified
    if (!isset($hook_extra['plugin']) || empty($hook_extra['plugin'])) {
        return $source;
    }

    // Early exit: not our plugin
    $plugin_slug = dirname($hook_extra['plugin']);
    if ($plugin_slug !== 'choice-uft') {
        return $source;
    }

    // Initialize WP_Filesystem if needed
    if (empty($wp_filesystem)) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        WP_Filesystem();
    }

    if (empty($wp_filesystem)) {
        return new WP_Error(
            'filesystem_error',
            __('Could not initialize WordPress filesystem.', 'choice-uft')
        );
    }

    // Get current directory name
    $source_basename = basename(rtrim($source, '/'));

    // Already correct, no action needed
    if ($source_basename === $plugin_slug) {
        return $source;
    }

    // Validate pattern
    $valid_pattern = false;

    // Pattern 1: Starts with our slug (versioned, branch)
    if (strpos($source_basename, $plugin_slug) === 0) {
        $valid_pattern = true;
    }

    // Pattern 2: GitHub commit format
    elseif (preg_match('/^ChoiceOMG-' . preg_quote($plugin_slug, '/') . '-[a-f0-9]{7}$/', $source_basename)) {
        $valid_pattern = true;
    }

    if (!$valid_pattern) {
        return new WP_Error(
            'incompatible_plugin_archive',
            __('Plugin archive does not contain expected directory structure.', 'choice-uft')
        );
    }

    // Verify source directory exists
    if (!$wp_filesystem->is_dir($source)) {
        return new WP_Error(
            'source_directory_missing',
            __('Extracted plugin directory not found at expected location.', 'choice-uft')
        );
    }

    // Verify plugin file exists
    $plugin_file = trailingslashit($source) . 'choice-universal-form-tracker.php';
    if (!$wp_filesystem->exists($plugin_file)) {
        return new WP_Error(
            'invalid_plugin_structure',
            __('Plugin archive does not contain main plugin file.', 'choice-uft')
        );
    }

    // Build corrected path
    $corrected_source = trailingslashit(dirname($source)) . $plugin_slug . '/';

    // Rename directory
    $renamed = $wp_filesystem->move($source, $corrected_source, true);

    if (!$renamed) {
        return new WP_Error(
            'unable_to_rename_directory',
            sprintf(
                __('Unable to rename plugin directory from %1$s to %2$s.', 'choice-uft'),
                $source_basename,
                $plugin_slug
            )
        );
    }

    // Verify rename succeeded
    if (!$wp_filesystem->is_dir($corrected_source)) {
        return new WP_Error(
            'rename_verification_failed',
            __('Plugin directory rename did not produce expected result.', 'choice-uft')
        );
    }

    return $corrected_source;
}
```

---

## WordPress Core Behavior

**After This Filter Returns**:
1. WordPress validates plugin structure at returned path
2. WordPress calls `upgrader_clear_destination` filter
3. WordPress deletes old plugin directory
4. WordPress copies new plugin files from returned path to `/wp-content/plugins/{slug}/`
5. WordPress calls `upgrader_post_install` filter

**If This Filter Returns WP_Error**:
1. Update is aborted immediately
2. Error message displayed to user
3. Temporary files cleaned up
4. Old plugin remains unchanged

---

## Integration with Feature 007

This filter integrates with Feature 007's update system:
- Update history log records directory rename events
- Admin bar refresh triggered after successful update
- Concurrent update detection prevents race conditions

---

**Version**: 1.0
**Last Updated**: 2025-10-11
**Status**: Ready for Implementation
