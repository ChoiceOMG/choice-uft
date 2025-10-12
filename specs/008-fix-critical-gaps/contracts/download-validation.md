# Contract: Download Validation

**Feature**: 008-fix-critical-gaps (FR-401)
**Purpose**: Validate downloaded update packages before extraction
**Operations**: `validate_file_size()`, `validate_zip_format()`, `cleanup_invalid_download()`
**Performance**: Validation <2 seconds

---

## Overview

This contract defines validation operations for downloaded plugin update packages. Validation ensures that downloaded ZIP files are complete, uncorrupted, and safe to extract before proceeding with the update. Failed validations trigger immediate cleanup and allow retry.

---

## Operation 1: validate_file_size()

### Purpose
Verify downloaded file size matches expected size from GitHub API (with tolerance for compression variance).

### Signature

```php
/**
 * Validate downloaded file size against expected size
 *
 * @param string $file_path Absolute path to downloaded file
 * @param int $expected_size Expected file size in bytes from GitHub API
 * @return bool|WP_Error True if valid, WP_Error if invalid
 */
function cuft_validate_file_size($file_path, $expected_size);
```

### Input Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `$file_path` | string | Yes | Path to downloaded ZIP | Must exist, must be readable |
| `$expected_size` | int | Yes | Expected size in bytes | Positive integer > 0 |

### Output

**Valid**: Returns `true`

**Invalid**: Returns `WP_Error` with details

### Tolerance Calculation

**Tolerance**: ±5% of expected size

**Rationale**: ZIP compression algorithms may produce slightly different file sizes across platforms/PHP versions

**Formula**:
```php
$min_size = $expected_size * 0.95;
$max_size = $expected_size * 1.05;

$valid = ($actual_size >= $min_size && $actual_size <= $max_size);
```

### Validation Process

```php
// 1. Initialize WP_Filesystem
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// 2. Verify file exists
if (!$wp_filesystem->exists($file_path)) {
    return new WP_Error(
        'file_not_found',
        __('Downloaded file not found.', 'choice-uft'),
        array('file_path' => $file_path)
    );
}

// 3. Get actual file size
$actual_size = $wp_filesystem->size($file_path);

if ($actual_size === false) {
    return new WP_Error(
        'file_size_unknown',
        __('Could not determine file size.', 'choice-uft'),
        array('file_path' => $file_path)
    );
}

// 4. Calculate tolerance bounds
$min_size = (int) ($expected_size * 0.95);
$max_size = (int) ($expected_size * 1.05);

// 5. Validate size within tolerance
if ($actual_size < $min_size || $actual_size > $max_size) {
    return new WP_Error(
        'file_size_mismatch',
        sprintf(
            __('Download verification failed: File size mismatch. Expected %s, got %s. Please try again.', 'choice-uft'),
            size_format($expected_size),
            size_format($actual_size)
        ),
        array(
            'expected_size' => $expected_size,
            'actual_size' => $actual_size,
            'min_size' => $min_size,
            'max_size' => $max_size,
            'file_path' => $file_path,
        )
    );
}

return true;
```

### Error Handling

| Error Scenario | Error Code | User Message | Error Data |
|----------------|------------|--------------|------------|
| File not found | `file_not_found` | "Downloaded file not found." | `file_path` |
| File size unknown | `file_size_unknown` | "Could not determine file size." | `file_path` |
| Size too small | `file_size_mismatch` | "File size mismatch. Expected X, got Y. Please try again." | `expected_size`, `actual_size`, `min_size`, `max_size` |
| Size too large | `file_size_mismatch` | "File size mismatch. Expected X, got Y. Please try again." | Same as above |

### User-Facing Error Message Format

```
Download verification failed: File size mismatch.
Expected 2.5 MB, got 1.2 MB. Please try again.
```

**Format Details**:
- Use `size_format()` for human-readable sizes (e.g., "2.5 MB" instead of "2621440 bytes")
- Suggest "Please try again" (indicating retry is possible)
- Don't expose technical details (file paths, tolerance calculations)

### Performance Target

**Target Time**: <100 ms (filesystem stat operation)

---

## Operation 2: validate_zip_format()

### Purpose
Verify downloaded file is a valid ZIP archive and can be extracted.

### Signature

```php
/**
 * Validate downloaded file is a valid ZIP archive
 *
 * @param string $file_path Absolute path to downloaded file
 * @return bool|WP_Error True if valid ZIP, WP_Error if invalid
 */
function cuft_validate_zip_format($file_path);
```

### Input Parameters

| Parameter | Type | Required | Description | Validation |
|-----------|------|----------|-------------|------------|
| `$file_path` | string | Yes | Path to downloaded ZIP | Must exist, must be readable |

### Output

**Valid**: Returns `true`

**Invalid**: Returns `WP_Error` with details

### Validation Methods

WordPress provides multiple methods for ZIP validation:

#### Method 1: ZipArchive::open() Test

```php
if (!class_exists('ZipArchive')) {
    return new WP_Error(
        'zip_unavailable',
        __('ZipArchive extension not available.', 'choice-uft')
    );
}

$zip = new ZipArchive();
$opened = $zip->open($file_path, ZipArchive::CHECKCONS);

if ($opened !== true) {
    return new WP_Error(
        'invalid_zip_format',
        __('Downloaded file is not a valid ZIP archive. Please try again or contact support.', 'choice-uft'),
        array(
            'file_path' => $file_path,
            'zip_error_code' => $opened,
        )
    );
}

$zip->close();
```

#### Method 2: WordPress unzip_file() Dry Run

```php
// Create temp directory for test extraction
$temp_dir = get_temp_dir() . 'cuft-test-' . uniqid() . '/';
wp_mkdir_p($temp_dir);

// Attempt extraction to temp directory
$result = unzip_file($file_path, $temp_dir);

// Clean up temp directory
$wp_filesystem->delete($temp_dir, true);

if (is_wp_error($result)) {
    return new WP_Error(
        'invalid_zip_format',
        __('Downloaded file is not a valid ZIP archive. Please try again or contact support.', 'choice-uft'),
        array(
            'file_path' => $file_path,
            'extraction_error' => $result->get_error_message(),
        )
    );
}
```

**Recommendation**: Use Method 1 (ZipArchive::open) as it's faster and doesn't require extraction.

### Validation Process

```php
// 1. Initialize WP_Filesystem
require_once ABSPATH . 'wp-admin/includes/file.php';
WP_Filesystem();

// 2. Verify file exists
if (!$wp_filesystem->exists($file_path)) {
    return new WP_Error(
        'file_not_found',
        __('Downloaded file not found.', 'choice-uft'),
        array('file_path' => $file_path)
    );
}

// 3. Check ZipArchive availability
if (!class_exists('ZipArchive')) {
    // Fallback: Check file extension and magic bytes
    return cuft_validate_zip_magic_bytes($file_path);
}

// 4. Attempt to open ZIP
$zip = new ZipArchive();
$opened = $zip->open($file_path, ZipArchive::CHECKCONS);

if ($opened !== true) {
    $error_messages = array(
        ZipArchive::ER_EXISTS    => 'File already exists',
        ZipArchive::ER_INCONS    => 'ZIP archive inconsistent',
        ZipArchive::ER_INVAL     => 'Invalid argument',
        ZipArchive::ER_MEMORY    => 'Memory allocation failed',
        ZipArchive::ER_NOENT     => 'No such file',
        ZipArchive::ER_NOZIP     => 'Not a ZIP archive',
        ZipArchive::ER_OPEN      => 'Cannot open file',
        ZipArchive::ER_READ      => 'Read error',
        ZipArchive::ER_SEEK      => 'Seek error',
    );

    $error_detail = isset($error_messages[$opened]) ? $error_messages[$opened] : 'Unknown error';

    return new WP_Error(
        'invalid_zip_format',
        __('Downloaded file is not a valid ZIP archive. Please try again or contact support.', 'choice-uft'),
        array(
            'file_path' => $file_path,
            'zip_error_code' => $opened,
            'zip_error_detail' => $error_detail,
        )
    );
}

// 5. Verify ZIP contains at least one file
$file_count = $zip->numFiles;

if ($file_count === 0) {
    $zip->close();

    return new WP_Error(
        'empty_zip_archive',
        __('ZIP archive is empty. Please try again.', 'choice-uft'),
        array('file_path' => $file_path)
    );
}

// 6. Verify ZIP contains expected plugin directory structure
$found_plugin_file = false;

for ($i = 0; $i < $file_count; $i++) {
    $filename = $zip->getNameIndex($i);

    if (strpos($filename, 'choice-universal-form-tracker.php') !== false) {
        $found_plugin_file = true;
        break;
    }
}

$zip->close();

if (!$found_plugin_file) {
    return new WP_Error(
        'invalid_plugin_structure',
        __('ZIP archive does not contain expected plugin files.', 'choice-uft'),
        array('file_path' => $file_path)
    );
}

return true;
```

### Fallback: Magic Bytes Validation

If ZipArchive is not available, validate using magic bytes:

```php
function cuft_validate_zip_magic_bytes($file_path) {
    $fp = fopen($file_path, 'rb');

    if (!$fp) {
        return new WP_Error(
            'file_not_readable',
            __('Cannot read downloaded file.', 'choice-uft')
        );
    }

    $magic_bytes = fread($fp, 4);
    fclose($fp);

    // ZIP magic bytes: 0x504B0304 (PK\x03\x04)
    if (substr($magic_bytes, 0, 4) !== "PK\x03\x04") {
        return new WP_Error(
            'invalid_zip_format',
            __('Downloaded file is not a valid ZIP archive. Please try again or contact support.', 'choice-uft')
        );
    }

    return true;
}
```

### Error Handling

| Error Scenario | Error Code | User Message | Error Data |
|----------------|------------|--------------|------------|
| File not found | `file_not_found` | "Downloaded file not found." | `file_path` |
| ZipArchive unavailable | `zip_unavailable` | "ZipArchive extension not available." | None |
| Invalid ZIP format | `invalid_zip_format` | "Downloaded file is not a valid ZIP archive. Please try again or contact support." | `file_path`, `zip_error_code`, `zip_error_detail` |
| Empty ZIP archive | `empty_zip_archive` | "ZIP archive is empty. Please try again." | `file_path` |
| Invalid plugin structure | `invalid_plugin_structure` | "ZIP archive does not contain expected plugin files." | `file_path` |
| File not readable | `file_not_readable` | "Cannot read downloaded file." | `file_path` |

### User-Facing Error Message Format

```
Downloaded file is not a valid ZIP archive.
Please try again or contact support.
```

**Format Details**:
- Don't expose ZIP error codes or technical details to users
- Suggest "try again" for transient errors (network, partial download)
- Suggest "contact support" for persistent errors (corrupted source file)
- Log technical details to PHP error_log for debugging

### Performance Target

**Target Time**: <1 second (ZipArchive::open is fast)

**Fallback Method**: <100 ms (reading 4 magic bytes)

---

## Operation 3: cleanup_invalid_download()

### Purpose
Delete invalid or incomplete downloaded files immediately after validation failure or on scheduled basis.

### Signature

```php
/**
 * Delete invalid or orphaned download files
 *
 * @param string|null $file_path Optional specific file to delete, or null for scheduled cleanup
 * @return bool True on success, false on failure (non-critical)
 */
function cuft_cleanup_invalid_download($file_path = null);
```

### Input Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$file_path` | string\|null | No | Path to specific file to delete, or null for scheduled cleanup of all orphaned files |

### Output

**Success**: Returns `true`

**Failure**: Returns `false` (logged, but non-critical)

### Cleanup Triggers

#### Trigger 1: Immediate Cleanup (After Validation Failure)

```php
// In validation workflow
$valid = cuft_validate_file_size($file_path, $expected_size);

if (is_wp_error($valid)) {
    // Delete invalid file immediately
    cuft_cleanup_invalid_download($file_path);

    return $valid; // Return error to user
}
```

#### Trigger 2: Scheduled Cleanup (Daily Cron)

```php
add_action('cuft_cleanup_orphaned_downloads', 'cuft_scheduled_download_cleanup');

function cuft_scheduled_download_cleanup() {
    // Clean up all orphaned download files
    cuft_cleanup_invalid_download(null);
}

// Register scheduled event
if (!wp_next_scheduled('cuft_cleanup_orphaned_downloads')) {
    wp_schedule_event(time(), 'daily', 'cuft_cleanup_orphaned_downloads');
}
```

### Immediate Cleanup Process (Specific File)

```php
if ($file_path !== null) {
    // Initialize WP_Filesystem
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    // Check if file exists
    if (!$wp_filesystem->exists($file_path)) {
        // Already deleted
        return true;
    }

    // Delete file
    $deleted = $wp_filesystem->delete($file_path, false, 'f');

    if (!$deleted) {
        error_log('CUFT WARNING: Could not delete invalid download: ' . $file_path);
        return false;
    }

    error_log('CUFT: Deleted invalid download: ' . $file_path);
    return true;
}
```

### Scheduled Cleanup Process (Orphaned Files)

**Purpose**: Clean up files left behind by crashed/interrupted updates

**Location**: WordPress temp directory (`get_temp_dir()`)

**Pattern**: Files matching `choice-uft-*.zip` or `choice-uft-update-*.zip`

**Age Threshold**: Older than 24 hours

```php
function cuft_scheduled_download_cleanup() {
    $temp_dir = get_temp_dir();

    // Find orphaned CUFT download files
    $patterns = array(
        $temp_dir . 'choice-uft-*.zip',
        $temp_dir . 'choice-uft-update-*.zip',
        $temp_dir . 'cuft-update-*.zip',
    );

    $files = array();
    foreach ($patterns as $pattern) {
        $matches = glob($pattern);
        if ($matches) {
            $files = array_merge($files, $matches);
        }
    }

    $deleted_count = 0;
    $failed_count = 0;

    foreach ($files as $file) {
        // Check file age
        $age = time() - filemtime($file);

        // Delete if older than 24 hours
        if ($age > DAY_IN_SECONDS) {
            $deleted = wp_delete_file($file);

            if ($deleted) {
                $deleted_count++;
            } else {
                $failed_count++;
                error_log('CUFT WARNING: Could not delete orphaned download: ' . $file);
            }
        }
    }

    if ($deleted_count > 0) {
        error_log(sprintf('CUFT: Cleaned up %d orphaned download files', $deleted_count));
    }

    if ($failed_count > 0) {
        error_log(sprintf('CUFT WARNING: Failed to delete %d orphaned download files', $failed_count));
    }
}
```

### Error Handling

| Error Scenario | Action |
|----------------|--------|
| File doesn't exist | Consider success (already deleted) |
| Delete operation fails | Log warning, return false, but DON'T fail update/validation |
| Permission denied | Log warning, return false, file may be cleaned up on next cron run |

**Rationale**: Cleanup failures are non-critical. Validation already failed, so we don't want to compound the error. Orphaned files will be cleaned up by scheduled cron job.

### Performance Target

**Immediate Cleanup**: <100 ms per file

**Scheduled Cleanup**: <5 seconds for typical number of orphaned files

---

## Validation Workflow Integration

### Complete Validation Sequence

```
1. Download update package from GitHub
   ↓
2. validate_file_size($file_path, $expected_size)
   ↓ (if WP_Error)
   cleanup_invalid_download($file_path)
   ABORT: Show error, allow retry
   ↓ (if true)
3. validate_zip_format($file_path)
   ↓ (if WP_Error)
   cleanup_invalid_download($file_path)
   ABORT: Show error, allow retry
   ↓ (if true)
4. Proceed with extraction
```

### Hook Integration

**When to Validate**: After download, before extraction

```php
add_filter('upgrader_pre_download', 'cuft_validate_download_after_fetch', 10, 3);

function cuft_validate_download_after_fetch($reply, $package, $upgrader) {
    // Only process our plugin
    if (strpos($package, 'choice-uft') === false) {
        return $reply;
    }

    // Get local file path (WordPress stores it in $upgrader->skin->result)
    $file_path = $reply;

    // Get expected size from GitHub API (cached in transient)
    $expected_size = get_transient('cuft_expected_download_size');

    if (!$expected_size) {
        // Size not available, skip size validation (proceed with ZIP validation only)
    } else {
        // Validate file size
        $size_valid = cuft_validate_file_size($file_path, $expected_size);

        if (is_wp_error($size_valid)) {
            cuft_cleanup_invalid_download($file_path);
            return $size_valid; // Abort update
        }
    }

    // Validate ZIP format
    $zip_valid = cuft_validate_zip_format($file_path);

    if (is_wp_error($zip_valid)) {
        cuft_cleanup_invalid_download($file_path);
        return $zip_valid; // Abort update
    }

    return $reply; // Proceed with update
}
```

---

## Logging Requirements

### Validation Success

```php
error_log(sprintf(
    'CUFT: Download validation passed - Size: %s, Format: valid ZIP',
    size_format($actual_size)
));
```

### Validation Failure

```php
error_log(sprintf(
    'CUFT WARNING: Download validation failed - Error: %s, File: %s',
    $error_code,
    $file_path
));

// Include error data for debugging
error_log('CUFT DEBUG: ' . print_r($error_data, true));
```

### Cleanup Operations

```php
// Immediate cleanup
error_log('CUFT: Deleted invalid download: ' . $file_path);

// Scheduled cleanup
error_log(sprintf('CUFT: Cleaned up %d orphaned download files', $count));
```

**Log Location**: PHP error_log (typically `/var/log/php-error.log` or WordPress debug.log if `WP_DEBUG_LOG` enabled)

---

## Security Considerations

### Path Validation

```php
// Prevent directory traversal
if (strpos($file_path, '..') !== false) {
    return new WP_Error('invalid_path', __('Invalid file path.', 'choice-uft'));
}

// Ensure path is within temp directory
$temp_dir = get_temp_dir();
if (strpos($file_path, $temp_dir) !== 0) {
    return new WP_Error('invalid_path', __('File path is not in temp directory.', 'choice-uft'));
}
```

### File Extension Validation

```php
// Verify file has .zip extension
if (strtolower(substr($file_path, -4)) !== '.zip') {
    return new WP_Error('invalid_file_type', __('Downloaded file is not a ZIP archive.', 'choice-uft'));
}
```

### Content Type Validation

```php
// Check MIME type (if available)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file_path);
finfo_close($finfo);

if ($mime_type !== 'application/zip') {
    return new WP_Error('invalid_mime_type', __('Downloaded file MIME type is not application/zip.', 'choice-uft'));
}
```

---

## Testing Requirements

### Test Cases

1. **TC-001: File Size Validation - Valid Size**
   - Input: File size within ±5% tolerance
   - Expected: Return `true`

2. **TC-002: File Size Validation - Too Small**
   - Input: File size <95% of expected
   - Expected: Return `WP_Error` with `file_size_mismatch` code

3. **TC-003: File Size Validation - Too Large**
   - Input: File size >105% of expected
   - Expected: Return `WP_Error` with `file_size_mismatch` code

4. **TC-004: File Size Validation - File Not Found**
   - Input: Non-existent file path
   - Expected: Return `WP_Error` with `file_not_found` code

5. **TC-005: ZIP Format Validation - Valid ZIP**
   - Input: Valid ZIP file with plugin files
   - Expected: Return `true`

6. **TC-006: ZIP Format Validation - Invalid ZIP**
   - Input: Corrupted ZIP file
   - Expected: Return `WP_Error` with `invalid_zip_format` code

7. **TC-007: ZIP Format Validation - Empty ZIP**
   - Input: Valid ZIP but no files inside
   - Expected: Return `WP_Error` with `empty_zip_archive` code

8. **TC-008: ZIP Format Validation - Missing Plugin File**
   - Input: Valid ZIP but doesn't contain `choice-universal-form-tracker.php`
   - Expected: Return `WP_Error` with `invalid_plugin_structure` code

9. **TC-009: ZIP Format Validation - Not a ZIP (Text File)**
   - Input: Text file renamed to .zip
   - Expected: Return `WP_Error` with `invalid_zip_format` code

10. **TC-010: Immediate Cleanup - Valid File Path**
    - Input: Invalid download file exists
    - Expected: File deleted, return `true`

11. **TC-011: Immediate Cleanup - File Already Deleted**
    - Input: File doesn't exist
    - Expected: Return `true` (considered success)

12. **TC-012: Scheduled Cleanup - Orphaned Files**
    - Input: Multiple old download files in temp directory
    - Expected: Files older than 24 hours deleted

13. **TC-013: Scheduled Cleanup - Recent Files**
    - Input: Download files less than 24 hours old
    - Expected: Files preserved (not deleted)

14. **TC-014: Full Validation Workflow**
    - Input: Complete download → validate → cleanup on failure
    - Expected: Both validations run, cleanup occurs if either fails

---

**Version**: 1.0
**Last Updated**: 2025-10-11
**Status**: Ready for Implementation
