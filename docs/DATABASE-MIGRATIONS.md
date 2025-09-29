# Database Migration System

## Overview

The Choice Universal Form Tracker plugin includes a database migration system to manage schema changes safely and automatically.

## Migration Handler

**Class**: `CUFT_DB_Migration`
**File**: `includes/class-cuft-db-migration.php`

## How It Works

### Automatic Migration

Migrations run automatically in two scenarios:

1. **Plugin Activation**: When the plugin is activated or reactivated
2. **Plugin Load**: When WordPress loads the plugin and detects a version mismatch

### Version Tracking

- **Current Version**: Stored in WordPress option `cuft_db_version`
- **Target Version**: Defined in `CUFT_DB_Migration::CURRENT_VERSION`
- **Comparison**: Uses PHP's `version_compare()` for semantic versioning

## Current Migrations

### Version 1.0.0

**Added**: `events` column to `wp_cuft_click_tracking` table

**Purpose**: Store event history for each click ID (form submissions, phone clicks, etc.)

**Schema**:
```sql
ALTER TABLE `wp_cuft_click_tracking`
ADD COLUMN `events` LONGTEXT DEFAULT NULL
AFTER `utm_content`
```

**Data Format**: JSON array of event objects
```json
[
  {
    "event_type": "form_submit",
    "user_email": "user@example.com",
    "user_phone": "555-1234",
    "form_id": "elementor-form-123",
    "form_type": "elementor",
    "timestamp": "2025-09-29 20:30:15"
  },
  {
    "event_type": "generate_lead",
    "timestamp": "2025-09-29 20:30:15"
  }
]
```

## Manual Migration Commands

### Check Migration Status

```bash
docker exec wp-pdev-cli wp eval '
if (class_exists("CUFT_DB_Migration")) {
    echo "Current: " . CUFT_DB_Migration::get_current_version() . "\n";
    echo "Target: " . CUFT_DB_Migration::get_target_version() . "\n";
    echo "Needs migration: " . (CUFT_DB_Migration::needs_migration() ? "yes" : "no") . "\n";
}'
```

### Force Migration

```bash
docker exec wp-pdev-cli wp eval '
if (class_exists("CUFT_DB_Migration")) {
    CUFT_DB_Migration::force_migrate();
    echo "Migration forced\n";
}'
```

### Rollback (Development Only)

```bash
docker exec wp-pdev-cli wp eval '
if (class_exists("CUFT_DB_Migration")) {
    CUFT_DB_Migration::rollback();
    echo "Migration rolled back\n";
}'
```

### Check Table Structure

```bash
docker exec wp-pdev-cli wp eval '
global $wpdb;
$columns = $wpdb->get_results("SHOW COLUMNS FROM wp_cuft_click_tracking");
foreach($columns as $col) {
    echo $col->Field . " - " . $col->Type . "\n";
}'
```

## Adding New Migrations

### Step 1: Update Version Constant

In `includes/class-cuft-db-migration.php`:

```php
const CURRENT_VERSION = '1.1.0'; // Increment version
```

### Step 2: Add Migration Method

```php
private static function migrate_to_1_1_0() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'cuft_click_tracking';

    // Your migration logic here
    $sql = "ALTER TABLE `{$table_name}` ...";
    $wpdb->query( $sql );

    // Log success
    if ( class_exists( 'CUFT_Logger' ) ) {
        CUFT_Logger::log(
            'info',
            'Migration 1.1.0 completed',
            array( 'table' => $table_name )
        );
    }
}
```

### Step 3: Add Version Check

In `run_migrations()` method:

```php
if ( version_compare( $current_version, '1.1.0', '<' ) ) {
    self::migrate_to_1_1_0();
}
```

## Best Practices

1. **Always increment versions**: Use semantic versioning (MAJOR.MINOR.PATCH)
2. **Check before altering**: Always verify if a column/table exists before creating it
3. **Log everything**: Use CUFT_Logger to track migration success/failure
4. **Test rollback**: Implement rollback methods for development testing
5. **Preserve data**: Never drop columns or tables without explicit user consent
6. **Handle errors gracefully**: Catch and log database errors without breaking the plugin

## Logging

All migration activities are logged using `CUFT_Logger`:

- **Success**: Logged as 'info' level
- **Errors**: Logged as 'error' level
- **Details**: Include table names, versions, and error messages

View logs in WordPress admin: Settings → Universal Form Tracker → Debug Logs

## Troubleshooting

### Migration Not Running

1. Check database version: `wp option get cuft_db_version`
2. Force migration: Use `CUFT_DB_Migration::force_migrate()`
3. Check error logs: Look for migration-related errors in debug logs

### Column Already Exists Error

This is handled automatically - migrations check for column existence before attempting to add them.

### Permission Errors

Ensure WordPress database user has ALTER TABLE permissions:

```sql
GRANT ALTER ON database_name.* TO 'wp_user'@'localhost';
```

## Safety Features

1. **Idempotent**: Migrations can be run multiple times safely
2. **Version Checking**: Only runs necessary migrations
3. **Error Handling**: Database errors don't break the plugin
4. **Logging**: All actions are logged for debugging
5. **Table Existence Check**: Verifies tables exist before altering

## Development Workflow

### Testing New Migrations

1. Create migration method
2. Reset version: `update_option('cuft_db_version', '0.0.0')`
3. Run migration: `CUFT_DB_Migration::run_migrations()`
4. Verify table structure
5. Test rollback
6. Reset and test again

### Production Deployment

1. Increment `CURRENT_VERSION`
2. Add migration method
3. Add version check in `run_migrations()`
4. Test on staging environment
5. Deploy to production
6. Migration runs automatically on plugin load