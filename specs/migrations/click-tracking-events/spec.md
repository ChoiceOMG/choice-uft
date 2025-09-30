# Click Tracking Events Migration Specification

## Version: 1.0
## Date: 2025-01-25
## Status: Draft
## Migration Type: Database Schema Enhancement

---

## Overview

### Current Problem
The existing click tracking table stores static data with limited event context:
- **Removed Columns**: `utm_source`, `platform` (redundant with utm_campaign and utm_medium)
- **Missing Feature**: No chronological event tracking capability
- **Limited Insights**: Cannot track user journey progression through events

### Proposed Solution
Migrate to event-based tracking with JSON array storage:
- **New Column**: `events` JSON array storing chronological event history
- **Event Types**: `phone_click`, `email_click`, `form_submit`, `generate_lead`
- **Automatic Updates**: `date_updated` reflects most recent event timestamp

---

## Clarifications

### Session 2025-09-29
- Q: When a duplicate event type occurs (e.g., user clicks phone link twice), how should the system handle it? → A: Update existing - replace the timestamp of the first occurrence with the latest
- Q: If the migration fails and rollback is triggered, what should be restored? → A: Hybrid - restore schema, preserve qualified/score updates, discard events
- Q: When the 100-event limit per click_id is reached, which events should be removed? → A: Oldest events - FIFO (First In First Out), remove earliest timestamps
- Q: During gradual rollout with feature flag, how should the system behave when flag is OFF? → A: Shadow mode - Write events silently but don't display in admin interface
- Q: How should events be recorded in the database when triggered from client-side (JavaScript)? → A: AJAX endpoint - JavaScript POSTs to dedicated PHP endpoint for event recording

---

## Requirements

### Functional Requirements

#### FR-01: Event Array Storage
- **Requirement**: Store events as JSON array with chronological order
- **Format**: `[{"event": "phone_click", "timestamp": "2025-01-01T12:00:00Z"}, ...]`
- **Behavior**: For new event types, append to array. For duplicate event types, update the existing entry's timestamp to the latest occurrence. The `date_updated` column always reflects the most recent event timestamp

#### FR-02: Supported Event Types
- **phone_click**: Tel link clicks from cuft-links.js
- **email_click**: Mailto link clicks from cuft-links.js
- **form_submit**: Form submission events from framework scripts
- **generate_lead**: Qualified lead events (email + phone + click_id present)

#### FR-03: Automatic Date Updates
- **Trigger**: When new event added to events array
- **Action**: Update `date_updated` to timestamp of most recent event
- **Purpose**: Maintain chronological tracking accuracy

#### FR-04: Backward Compatibility
- **Requirement**: Existing webhook API continues to work
- **Behavior**: Qualification/score updates still supported
- **Migration**: Zero downtime during transition

### Non-Functional Requirements

#### NFR-01: Performance
- **JSON Operations**: MySQL 5.7+ JSON functions for efficient queries
- **Index Strategy**: Maintain existing indexes, add date_updated index
- **Migration Speed**: Process existing records in batches of 1000

#### NFR-02: Data Integrity
- **Validation**: JSON schema validation for event structure
- **Rollback**: Hybrid rollback strategy - restore original schema (remove events column and indexes), preserve business-critical updates (qualified/score changes via webhook), discard all event data written during migration
- **Backup**: Full backup before migration starts to enable rollback

#### NFR-03: Scalability
- **Event Limit**: Maximum 100 events per click_id. When limit reached, apply FIFO cleanup by removing oldest events (earliest timestamps) before adding new events
- **Storage**: JSON column efficient for small to medium arrays
- **Growth**: Architecture supports future event types

---

## Data Model Changes

### Current Table Structure
```sql
CREATE TABLE cuft_click_tracking (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    click_id varchar(255) NOT NULL,
    platform varchar(100) DEFAULT NULL,           -- TO BE REMOVED
    campaign varchar(255) DEFAULT NULL,
    utm_source varchar(255) DEFAULT NULL,         -- TO BE REMOVED
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
    KEY platform (platform),                      -- TO BE REMOVED
    KEY qualified (qualified),
    KEY score (score),
    KEY date_created (date_created)
)
```

### New Table Structure
```sql
CREATE TABLE cuft_click_tracking (
    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    click_id varchar(255) NOT NULL,
    campaign varchar(255) DEFAULT NULL,
    utm_medium varchar(255) DEFAULT NULL,
    utm_campaign varchar(255) DEFAULT NULL,
    utm_term varchar(255) DEFAULT NULL,
    utm_content varchar(255) DEFAULT NULL,
    events JSON DEFAULT NULL,                     -- NEW: Event array
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
    KEY date_updated (date_updated)               -- NEW: Index for recent activity
)
```

### Event JSON Schema
```json
{
  "type": "array",
  "items": {
    "type": "object",
    "properties": {
      "event": {
        "type": "string",
        "enum": ["phone_click", "email_click", "form_submit", "generate_lead"]
      },
      "timestamp": {
        "type": "string",
        "format": "date-time"
      }
    },
    "required": ["event", "timestamp"]
  }
}
```

---

## Integration Points

### Existing Event Sources

#### Phone/Email Clicks (cuft-links.js)
- **Current**: Fires `phone_click` and `email_click` events to dataLayer
- **Enhancement**: Hook these events to also record in click tracking table
- **Implementation**: Add event listener that POSTs to dedicated AJAX endpoint for event recording (calls CUFT_Click_Tracker::add_event() on server-side)

#### Form Submissions (Framework Scripts)
- **Current**: All framework scripts fire `form_submit` events
- **Enhancement**: Hook form submissions to record click tracking events
- **Implementation**: Extract click_id from form data, POST to AJAX endpoint to record form_submit event

#### Generate Lead Events
- **Current**: Fired when email + phone + click_id present
- **Enhancement**: Record as final event in click journey
- **Implementation**: Hook generate_lead dataLayer pushes, POST to AJAX endpoint to record generate_lead event

### API Compatibility

#### Webhook Endpoint
- **Current**: `/cuft-webhook/?key=xxx&click_id=xxx&qualified=1&score=8`
- **Maintained**: Existing API continues to work unchanged
- **Enhancement**: Webhook updates also trigger event recording

#### Admin Interface
- **Current**: Shows utm_source and platform columns
- **Updated**: Show events timeline instead
- **Enhancement**: Filter and sort by event types and recency

---

## Migration Strategy

### Phase 1: Table Structure Update
1. **Add events column** to existing table (nullable)
2. **Add date_updated index** for performance
3. **Update CUFT_Click_Tracker class** to handle events

### Phase 2: Event Integration
1. **Hook into existing event sources** (phone_click, email_click, etc.)
2. **Add event recording methods** to click tracker
3. **Update admin interface** to display events

### Phase 3: Column Cleanup
1. **Migrate data** from utm_source/platform to events where possible
2. **Drop unused columns** (utm_source, platform)
3. **Update admin filters** to use campaign/utm_medium instead

### Phase 4: Validation & Rollout
1. **Feature flag** for gradual rollout (shadow mode: when OFF, events are written to database but hidden from admin interface; when ON, events are both written and displayed)
2. **A/B testing** to compare data quality
3. **Full deployment** once validated

---

## Success Criteria

### Technical Success
- [ ] All existing click tracking functionality preserved
- [ ] New events properly recorded with accurate timestamps
- [ ] Admin interface shows event timeline
- [ ] Performance impact < 10% for typical workloads
- [ ] Zero data loss during migration

### Business Success
- [ ] Enhanced user journey visibility
- [ ] Improved lead qualification tracking
- [ ] Better campaign performance insights
- [ ] Simplified table structure (fewer redundant columns)

---

## Risk Assessment

### High Risks
- **JSON Performance**: MySQL JSON operations may be slower than simple columns
  - **Mitigation**: Use indexes and limit event array size
- **Migration Complexity**: Existing data needs careful handling
  - **Mitigation**: Comprehensive testing and rollback procedures

### Medium Risks
- **Backward Compatibility**: Third-party integrations may break
  - **Mitigation**: Maintain existing API endpoints unchanged
- **Storage Growth**: Event arrays may increase storage usage
  - **Mitigation**: Implement event cleanup for old records

---

## Dependencies

### Technical Dependencies
- **MySQL 5.7+**: Required for JSON column support
- **PHP 7.0+**: Required for JSON handling functions
- **WordPress 5.0+**: Required for WordPress API functions

### Integration Dependencies
- **cuft-links.js**: Must be updated to record events
- **Framework scripts**: Must be updated to record form events
- **Admin interface**: Must be updated to display events
- **Webhook system**: Must be updated to record events

---

This specification provides the foundation for implementing event-based click tracking while maintaining full backward compatibility and ensuring smooth migration.