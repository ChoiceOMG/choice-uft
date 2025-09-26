# Click Tracking Events Data Model

## Version: 1.0
## Date: 2025-01-25
## Status: Draft

---

## Overview

This document defines the data model for the enhanced click tracking table with event array support. The migration transforms a static qualification table into a dynamic event chronology system.

---

## Database Schema

### Before: Current Table Structure
```sql
CREATE TABLE wp_cuft_click_tracking (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    click_id varchar(255) NOT NULL,
    platform varchar(100) DEFAULT NULL,           -- DEPRECATED
    campaign varchar(255) DEFAULT NULL,
    utm_source varchar(255) DEFAULT NULL,         -- DEPRECATED
    utm_medium varchar(255) DEFAULT NULL,
    utm_campaign varchar(255) DEFAULT NULL,
    utm_term varchar(255) DEFAULT NULL,
    utm_content varchar(255) DEFAULT NULL,
    qualified tinyint(1) DEFAULT 0,
    score int(11) DEFAULT 0,
    date_created datetime DEFAULT CURRENT_TIMESTAMP,
    date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    additional_data longtext DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY click_id (click_id),
    KEY platform (platform),                      -- DEPRECATED
    KEY qualified (qualified),
    KEY score (score),
    KEY date_created (date_created)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### After: Enhanced Table Structure
```sql
CREATE TABLE wp_cuft_click_tracking (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    click_id varchar(255) NOT NULL,
    campaign varchar(255) DEFAULT NULL,
    utm_medium varchar(255) DEFAULT NULL,
    utm_campaign varchar(255) DEFAULT NULL,
    utm_term varchar(255) DEFAULT NULL,
    utm_content varchar(255) DEFAULT NULL,
    events JSON DEFAULT NULL,                     -- NEW: Event chronology
    qualified tinyint(1) DEFAULT 0,
    score int(11) DEFAULT 0,
    date_created datetime DEFAULT CURRENT_TIMESTAMP,
    date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ip_address varchar(45) DEFAULT NULL,
    user_agent text DEFAULT NULL,
    additional_data longtext DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY click_id (click_id),
    KEY qualified (qualified),
    KEY score (score),
    KEY date_created (date_created),
    KEY date_updated (date_updated)               -- NEW: For recent activity queries
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Column Definitions

### Retained Columns

#### Core Identity
- **`id`**: Primary key, auto-incrementing unique identifier
- **`click_id`**: Unique business key for external references (e.g., gclid, fbclid)

#### UTM Campaign Data
- **`campaign`**: Simplified campaign identifier (replaces platform column)
- **`utm_medium`**: Traffic medium (cpc, social, email, etc.)
- **`utm_campaign`**: Specific campaign name
- **`utm_term`**: Campaign keywords/terms
- **`utm_content`**: Campaign creative/content identifier

#### Qualification Status
- **`qualified`**: Boolean flag indicating lead qualification status
- **`score`**: Numeric score 0-10 for lead quality assessment

#### Tracking Metadata
- **`date_created`**: Initial record creation timestamp
- **`date_updated`**: Last modification timestamp (auto-updated with events)
- **`ip_address`**: Client IP address for fraud detection
- **`user_agent`**: Browser user agent string
- **`additional_data`**: Flexible JSON storage for custom attributes

### New Columns

#### `events` JSON Column
**Purpose**: Store chronological array of user interaction events

**Structure**:
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

**Constraints**:
- Maximum 100 events per click_id (with automatic cleanup)
- Events always sorted chronologically (newest last)
- Timestamps in ISO 8601 UTC format
- Event types limited to predefined set

### Removed Columns

#### `platform` (varchar)
**Reason for Removal**: Redundant with utm_medium and campaign
**Migration Strategy**: Data migrated to campaign column where applicable
**Cleanup**: Column dropped after successful data migration

#### `utm_source` (varchar)
**Reason for Removal**: Redundant with campaign and utm_medium
**Migration Strategy**: Data used to reconstruct initial events
**Cleanup**: Column dropped after successful data migration

---

## Event Types and Schema

### Supported Event Types

#### `phone_click`
**Trigger**: User clicks tel: link
**Source**: cuft-links.js click handler
**Data**: Phone number extracted and normalized
**Significance**: First direct contact intent

#### `email_click`
**Trigger**: User clicks mailto: link
**Source**: cuft-links.js click handler
**Data**: Email address extracted
**Significance**: Alternative contact intent

#### `form_submit`
**Trigger**: User submits tracked form
**Source**: Framework-specific form handlers
**Data**: Form ID and type extracted
**Significance**: Primary conversion action

#### `generate_lead`
**Trigger**: Qualified form submission (email + phone + click_id)
**Source**: Form handlers when criteria met
**Data**: Lead qualification confirmed
**Significance**: High-value conversion

#### `status_update`
**Trigger**: Webhook API qualification update
**Source**: External systems via webhook
**Data**: Qualification score changes
**Significance**: Post-conversion assessment

### Event JSON Schema Validation
```json
{
    "$schema": "http://json-schema.org/draft-07/schema#",
    "type": "array",
    "maxItems": 100,
    "items": {
        "type": "object",
        "properties": {
            "event": {
                "type": "string",
                "enum": [
                    "phone_click",
                    "email_click",
                    "form_submit",
                    "generate_lead",
                    "status_update"
                ]
            },
            "timestamp": {
                "type": "string",
                "format": "date-time"
            }
        },
        "required": ["event", "timestamp"],
        "additionalProperties": false
    }
}
```

---

## Indexing Strategy

### Primary Indexes
- **PRIMARY**: `id` - Auto-incrementing primary key
- **UNIQUE**: `click_id` - Business key for external lookups

### Performance Indexes
- **KEY**: `qualified` - For filtering qualified/unqualified leads
- **KEY**: `score` - For sorting by lead quality score
- **KEY**: `date_created` - For chronological ordering
- **KEY**: `date_updated` - NEW: For recent activity queries

### JSON Query Optimization
MySQL 5.7+ provides optimized JSON functions:
```sql
-- Query for specific event types
SELECT * FROM wp_cuft_click_tracking
WHERE JSON_CONTAINS(events, '{"event": "form_submit"}');

-- Query for events in date range
SELECT * FROM wp_cuft_click_tracking
WHERE JSON_EXTRACT(events, '$[*].timestamp') > '2025-01-01';

-- Count events per click
SELECT click_id, JSON_LENGTH(events) as event_count
FROM wp_cuft_click_tracking
WHERE events IS NOT NULL;
```

---

## Data Migration Mapping

### Column Migration Rules

#### Direct Transfers (No Change)
```
id → id
click_id → click_id
campaign → campaign
utm_medium → utm_medium
utm_campaign → utm_campaign
utm_term → utm_term
utm_content → utm_content
qualified → qualified
score → score
date_created → date_created
date_updated → date_updated
ip_address → ip_address
user_agent → user_agent
additional_data → additional_data
```

#### Data Reconstruction (Deprecated → Events)
```
utm_source (if present) → events[{"event": "form_submit", "timestamp": date_created}]
qualified=1 → events[{"event": "generate_lead", "timestamp": date_updated}]
platform → campaign (where campaign is empty)
```

#### Column Removal
```
platform → DROPPED (after data migrated to campaign)
utm_source → DROPPED (after data used for event reconstruction)
```

### Example Migration
**Before**:
```
id: 123
click_id: "gclid_abc123"
platform: "google"
utm_source: "google"
campaign: NULL
qualified: 1
date_created: "2025-01-20 10:00:00"
date_updated: "2025-01-22 15:30:00"
```

**After**:
```
id: 123
click_id: "gclid_abc123"
platform: DROPPED
utm_source: DROPPED
campaign: "google"
events: [
    {"event": "form_submit", "timestamp": "2025-01-20T10:00:00Z"},
    {"event": "generate_lead", "timestamp": "2025-01-22T15:30:00Z"}
]
qualified: 1
date_created: "2025-01-20 10:00:00"
date_updated: "2025-01-22 15:30:00"
```

---

## API Interfaces

### CUFT_Click_Tracker Enhanced Methods

#### New Event Methods
```php
/**
 * Add event to click tracking record
 * @param string $click_id Unique click identifier
 * @param string $event_type Event type (phone_click, email_click, etc.)
 * @return bool Success status
 */
public static function add_event($click_id, $event_type);

/**
 * Get events for specific click
 * @param string $click_id Unique click identifier
 * @return array Event array with timestamps
 */
public static function get_events($click_id);

/**
 * Get latest event timestamp
 * @param string $click_id Unique click identifier
 * @return string|null ISO timestamp or null
 */
public static function get_latest_event_time($click_id);

/**
 * Cleanup old events (keep latest 100)
 * @param string $click_id Unique click identifier
 * @return bool Success status
 */
public static function cleanup_events($click_id);
```

#### Enhanced Query Methods
```php
/**
 * Get clicks with event filtering
 * @param array $args Query arguments including event_type filter
 * @return array Click records with events
 */
public static function get_clicks($args = []);

/**
 * Get clicks by event type
 * @param string $event_type Event type to filter by
 * @param array $args Additional query arguments
 * @return array Matching click records
 */
public static function get_clicks_by_event($event_type, $args = []);
```

### Backward Compatibility
All existing CUFT_Click_Tracker methods remain unchanged:
- `track_click()` - Enhanced to initialize empty events array
- `update_click_status()` - Enhanced to record status_update events
- `get_clicks()` - Enhanced with event data in results
- `get_clicks_count()` - No changes required
- `export_csv()` - Enhanced to include events column

---

## Storage Considerations

### Storage Requirements

#### JSON Column Size
- **Empty events**: 4 bytes (JSON null)
- **Single event**: ~60 bytes (event + timestamp)
- **Typical events (5)**: ~300 bytes
- **Maximum events (100)**: ~6KB

#### Storage Growth Estimation
- **Current average row**: ~500 bytes
- **With events**: ~800 bytes (+60% average)
- **1 million records**: ~800MB total table size

### Performance Characteristics

#### JSON Operations
- **Insert event**: ~5-10ms (acceptable)
- **Query by event**: ~50-100ms with proper indexing
- **Event extraction**: ~1-2ms per record
- **Sort by latest event**: ~100-200ms for large datasets

#### Index Usage
- **date_updated index**: Enables fast "recent activity" queries
- **JSON functional indexes**: Available in MySQL 8.0+ for event queries
- **Compound indexes**: Not needed due to event array efficiency

---

## Data Validation Rules

### Record Validation
1. **click_id**: Must be unique, non-empty string, max 255 chars
2. **events**: Must be valid JSON array or null
3. **event types**: Must be from predefined enum list
4. **timestamps**: Must be valid ISO 8601 format
5. **event count**: Maximum 100 events per click_id

### Data Integrity Checks
1. **Event chronology**: Newer events should have later timestamps
2. **Date alignment**: date_updated should match latest event timestamp
3. **Event consistency**: generate_lead should not appear without form_submit
4. **JSON validity**: Events column must be valid JSON or null

### Migration Validation
1. **Data preservation**: All original data must be retained
2. **Count verification**: Record count unchanged after migration
3. **Reference integrity**: All click_id references remain valid
4. **Performance validation**: Query times within acceptable limits

---

This data model provides a robust foundation for event-based click tracking while maintaining full backward compatibility and ensuring optimal performance for both current and future requirements.