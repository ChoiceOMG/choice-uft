# Click Tracking Events Migration - Implementation Summary

## Overview

This migration transforms the existing click tracking table from static data storage to dynamic event chronology tracking. Users can now see the complete journey of their clicks through various interaction events.

## What's Implemented

### 1. Database Migration System
- **Schema Migration**: Adds `events` JSON column and `date_updated` index
- **Progressive Data Migration**: Processes existing records in batches of 1000
- **Data Reconstruction**: Converts existing utm_source/platform data into events
- **Rollback Capability**: Full rollback support if issues occur

### 2. Enhanced Click Tracking
- **Event Recording**: `CUFT_Click_Tracker::add_event()` method
- **Event Retrieval**: `CUFT_Click_Tracker::get_events()` method
- **Event Cleanup**: Automatic limit to 100 events per click
- **JSON Validation**: Ensures data integrity

### 3. JavaScript Integration
- **Phone/Email Clicks**: Enhanced cuft-links.js records events automatically
- **AJAX Endpoint**: Secure event recording via WordPress AJAX
- **Click ID Detection**: Smart detection from UTM params or session storage
- **Silent Failure**: Never interferes with existing functionality

### 4. Feature Flag System
- **Gradual Rollout**: Support for percentage-based feature rollout
- **User-Based Flags**: Consistent user experience during rollout
- **Admin Controls**: Easy enable/disable through WordPress admin

## Event Types Supported

| Event Type | Description | Source |
|------------|-------------|---------|
| `phone_click` | User clicks tel: link | cuft-links.js |
| `email_click` | User clicks mailto: link | cuft-links.js |
| `form_submit` | User submits tracked form | Framework scripts |
| `generate_lead` | Qualified lead generated | Framework scripts |
| `status_update` | Webhook qualification update | External systems |

## Files Created/Modified

### New Files
- `includes/class-cuft-migration-events.php` - Migration utility class
- `includes/class-cuft-utils.php` - Feature flags and utility functions
- `specs/migrations/click-tracking-events/` - Complete specification suite
- `tests/test-migration-events.php` - Basic test suite

### Modified Files
- `includes/class-cuft-click-tracker.php` - Added event methods
- `includes/class-cuft-admin.php` - Added AJAX endpoint
- `assets/cuft-links.js` - Added event recording

## Database Schema Changes

### Before (Current)
```sql
CREATE TABLE wp_cuft_click_tracking (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    click_id varchar(255) NOT NULL,
    platform varchar(100) DEFAULT NULL,           -- TO BE REMOVED
    utm_source varchar(255) DEFAULT NULL,         -- TO BE REMOVED
    campaign varchar(255) DEFAULT NULL,
    -- ... other columns
);
```

### After (Enhanced)
```sql
CREATE TABLE wp_cuft_click_tracking (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    click_id varchar(255) NOT NULL,
    campaign varchar(255) DEFAULT NULL,
    events JSON DEFAULT NULL,                     -- NEW: Event array
    -- ... other columns
    KEY date_updated (date_updated)               -- NEW: Index
);
```

## Usage Examples

### Recording Events (JavaScript)
```javascript
// Automatic recording for phone clicks
// Already integrated in cuft-links.js

// Manual event recording
recordClickEvent("custom_event", "additional_data");
```

### Recording Events (PHP)
```php
// Add event to existing click
CUFT_Click_Tracker::add_event('gclid_123', 'form_submit');

// Get events for click
$events = CUFT_Click_Tracker::get_events('gclid_123');

// Get latest event timestamp
$latest = CUFT_Click_Tracker::get_latest_event_time('gclid_123');
```

### Feature Flags
```php
// Check if feature is enabled
if (CUFT_Utils::is_feature_enabled('click_event_tracking')) {
    // Use new event tracking
} else {
    // Use legacy behavior
}

// Set feature flag (admin only)
CUFT_Utils::set_feature_flag('click_event_tracking', array(
    'enabled' => true,
    'rollout_percentage' => 50 // Enable for 50% of users
));
```

## Migration Process

### Step 1: Schema Update
```php
// Add events column and index
CUFT_Migration_Events::migrate_schema();
```

### Step 2: Start Data Migration
```php
// Initialize migration
CUFT_Migration_Events::start_migration();
```

### Step 3: Process Batches
```php
// Process in batches (can be run multiple times)
while (CUFT_Migration_Events::process_batch()) {
    // Continue processing
}
```

### Step 4: Validate Results
```php
// Check migration integrity
$validation = CUFT_Migration_Events::validate_migration();
if (!$validation['valid']) {
    // Handle errors
}
```

### Step 5: Enable Feature
```php
// Enable event tracking
CUFT_Utils::set_feature_flag('click_event_tracking', true);
```

## Event JSON Format

Events are stored as JSON arrays with chronological ordering:

```json
[
    {
        "event": "phone_click",
        "timestamp": "2025-01-25T14:30:00.000Z"
    },
    {
        "event": "email_click",
        "timestamp": "2025-01-25T14:31:15.000Z"
    },
    {
        "event": "form_submit",
        "timestamp": "2025-01-25T14:32:45.000Z"
    },
    {
        "event": "generate_lead",
        "timestamp": "2025-01-25T14:32:46.000Z"
    }
]
```

## Admin Interface Enhancements (Planned)

The admin interface will be updated to show:
- Event timeline with visual indicators
- Event type filtering
- Sort by most recent activity
- Enhanced CSV export with event data

## Testing

### Run Basic Tests
```bash
wp eval-file tests/test-migration-events.php
```

### Manual Testing
1. Enable debug mode: `window.cuftDebug = true`
2. Click phone/email links - check console for event recording
3. Submit forms - verify events recorded in database
4. Check admin interface for event display

## Performance Considerations

- **JSON Storage**: ~300 bytes average per record (5 events)
- **Query Performance**: Indexed date_updated for recent activity
- **Memory Usage**: Minimal impact, events limited to 100 per click
- **AJAX Calls**: Throttled and non-blocking

## Rollback Plan

If issues occur:

```php
// Full rollback (removes events column)
CUFT_Migration_Events::rollback_migration();

// Or disable feature flag
CUFT_Utils::set_feature_flag('click_event_tracking', false);
```

## Next Steps

1. **Admin Interface**: Update click tracking table to show event timeline
2. **Form Integration**: Add event recording to all form framework scripts
3. **Testing**: Comprehensive integration tests
4. **Deployment**: Feature flag rollout strategy
5. **Monitoring**: Set up alerts for event recording failures

## Support

- **Specifications**: See `specs/migrations/click-tracking-events/` folder
- **Logs**: Check WordPress debug.log and CUFT logger
- **Debug**: Enable `window.cuftDebug = true` for JavaScript debugging

This implementation provides a solid foundation for event-based click tracking while maintaining full backward compatibility and ensuring smooth migration paths.