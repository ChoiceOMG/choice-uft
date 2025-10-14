# Contract: Force Reinstall AJAX Endpoint

**Endpoint**: `/wp-admin/admin-ajax.php?action=cuft_force_reinstall`
**Method**: POST
**Feature**: 009-force-install-update

## Authentication

- **Nonce**: Required (`cuft_force_update` action)
- **Capability**: `update_plugins`
- **Constant Check**: Blocked if `DISALLOW_FILE_MODS` is true

## Request

```json
{
  "action": "cuft_force_reinstall",
  "nonce": "a1b2c3d4e5"
}
```

## Response: Success

**HTTP Status**: 200

```json
{
  "success": true,
  "data": {
    "message": "Plugin successfully reinstalled to version 3.19.0",
    "previous_version": "3.18.0",
    "new_version": "3.19.0",
    "backup_location": "/uploads/cuft-backups/choice-uft-3.18.0-backup-1728729600.zip",
    "duration_seconds": 35
  }
}
```

## Response: Error (Insufficient Disk Space)

```json
{
  "success": false,
  "data": {
    "error_code": "insufficient_disk_space",
    "message": "Insufficient disk space to create backup. Free at least 150 MB and try again.",
    "required_space": 157286400,
    "available_space": 52428800
  }
}
```

## Response: Error (Timeout)

```json
{
  "success": false,
  "data": {
    "error_code": "operation_timeout",
    "message": "Operation exceeded 60 second timeout. Please install manually from GitHub: https://github.com/ChoiceOMG/choice-uft/releases/latest",
    "elapsed_seconds": 61
  }
}
```

## Response: Error (Concurrent Operation)

```json
{
  "success": false,
  "data": {
    "error_code": "operation_in_progress",
    "message": "Another update operation is already in progress. Please wait.",
    "started_by": "admin",
    "started_at": 1728729500
  }
}
```

## Side Effects

1. Creates backup ZIP in `/wp-content/uploads/cuft-backups/`
2. Downloads latest release from GitHub
3. Validates ZIP (size, integrity)
4. Installs new version
5. Deletes backup on success (restores on failure)
6. Clears WordPress caches
7. Creates Update History Entry with `operation_type=force_reinstall`
8. Acquires/releases transient lock

## Performance

- **Target**: Complete within 60 seconds
- **Timeout**: Hard 60-second timeout, aborts operation
- **Disk Space**: Requires 3x plugin size (backup + download + extraction)

## Test Cases

- TC-009: Valid request completes successfully
- TC-010: Missing nonce returns 403
- TC-011: Insufficient disk space (3x) aborts
- TC-012: DISALLOW_FILE_MODS blocks operation
- TC-013: Concurrent request blocked by lock
- TC-014: Backup created before download
- TC-015: Download validation works
- TC-016: Timeout (60s) aborts with instructions
- TC-017: Failed installation restores backup
- TC-018: Update history logged correctly
