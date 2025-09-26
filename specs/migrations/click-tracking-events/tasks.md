# Click Tracking Events Migration Tasks

## Version: 1.0
## Date: 2025-01-25
## Status: Draft

---

## Task Overview

This document breaks down the click tracking events migration into specific, actionable tasks with clear dependencies and success criteria.

---

## Phase 1: Database Schema Preparation

### Task 1.1: Database Schema Update
**Priority**: Critical
**Estimated Time**: 4 hours
**Dependencies**: None
**Assignee**: Database Developer

**Description**: Add events JSON column and new indexes to existing table

**Subtasks**:
- [ ] Create database migration script
- [ ] Add events JSON column (nullable, default NULL)
- [ ] Add date_updated index for performance
- [ ] Test schema changes on development environment
- [ ] Validate MySQL JSON support (5.7+ required)

**Acceptance Criteria**:
- [ ] events column exists and accepts JSON data
- [ ] date_updated index improves query performance
- [ ] All existing functionality works unchanged
- [ ] No data loss during schema update

**Files Modified**:
- `includes/class-cuft-migration-events.php` (new)
- Database schema

---

### Task 1.2: Migration Utility Development
**Priority**: Critical
**Estimated Time**: 8 hours
**Dependencies**: Task 1.1
**Assignee**: Backend Developer

**Description**: Create utility class for progressive data migration

**Subtasks**:
- [ ] Create CUFT_Migration_Events class
- [ ] Implement batch processing (1000 records per batch)
- [ ] Add progress tracking and resumability
- [ ] Implement data reconstruction from utm_source/platform
- [ ] Add rollback capability
- [ ] Create WP-CLI command interface

**Acceptance Criteria**:
- [ ] Can process existing records in batches
- [ ] Reconstructs events from available data
- [ ] Handles errors gracefully with retry logic
- [ ] Provides progress reporting
- [ ] Can rollback changes if needed

**Files Created**:
- `includes/class-cuft-migration-events.php`
- `includes/class-cuft-cli-migration.php`

---

## Phase 2: Core Class Enhancement

### Task 2.1: Update CUFT_Click_Tracker Class
**Priority**: Critical
**Estimated Time**: 12 hours
**Dependencies**: Task 1.1
**Assignee**: Backend Developer

**Description**: Add event recording methods to existing click tracker class

**Subtasks**:
- [ ] Add `add_event($click_id, $event_type)` method
- [ ] Add `get_events($click_id)` method
- [ ] Add `get_latest_event_time($click_id)` method
- [ ] Add `cleanup_events($click_id)` method (limit to 100 events)
- [ ] Update `track_click()` to initialize empty events array
- [ ] Update `get_clicks()` to include events in results
- [ ] Add JSON validation for event structure
- [ ] Update date_updated when events are added

**Acceptance Criteria**:
- [ ] All new methods work correctly with JSON data
- [ ] Events maintain chronological order
- [ ] date_updated automatically updates with new events
- [ ] Events array limited to 100 items maximum
- [ ] All existing methods remain backward compatible

**Files Modified**:
- `includes/class-cuft-click-tracker.php`

---

### Task 2.2: Event Recording AJAX Endpoint
**Priority**: High
**Estimated Time**: 6 hours
**Dependencies**: Task 2.1
**Assignee**: Backend Developer

**Description**: Create WordPress AJAX endpoint for JavaScript event recording

**Subtasks**:
- [ ] Add wp_ajax_cuft_record_event action
- [ ] Implement nonce verification
- [ ] Validate click_id and event_type parameters
- [ ] Call CUFT_Click_Tracker::add_event()
- [ ] Return JSON response with success/error
- [ ] Add rate limiting for abuse prevention
- [ ] Log events for debugging (if debug mode enabled)

**Acceptance Criteria**:
- [ ] JavaScript can successfully record events
- [ ] Proper security validation (nonce, permissions)
- [ ] Handles errors gracefully
- [ ] Returns meaningful error messages
- [ ] Rate limiting prevents abuse

**Files Modified**:
- `includes/class-cuft-admin.php`
- `assets/cuft-admin.js` (localization)

---

## Phase 3: JavaScript Integration

### Task 3.1: Enhance cuft-links.js for Event Recording
**Priority**: High
**Estimated Time**: 6 hours
**Dependencies**: Task 2.2
**Assignee**: Frontend Developer

**Description**: Add event recording to existing link click tracking

**Subtasks**:
- [ ] Extract click_id from UTM parameters or generate temporary ID
- [ ] Add recordClickEvent() function for AJAX calls
- [ ] Hook into existing phone_click events
- [ ] Hook into existing email_click events
- [ ] Add error handling (silent failure)
- [ ] Ensure no interference with existing functionality
- [ ] Add debug logging when cuftDebug enabled

**Acceptance Criteria**:
- [ ] phone_click events recorded to database
- [ ] email_click events recorded to database
- [ ] Click functionality not affected by recording failures
- [ ] Works with or without click_id present
- [ ] Debug logging available for troubleshooting

**Files Modified**:
- `assets/cuft-links.js`

---

### Task 3.2: Integrate Form Event Recording
**Priority**: High
**Estimated Time**: 8 hours
**Dependencies**: Task 2.2
**Assignee**: Frontend Developer

**Description**: Add event recording to form submission tracking

**Subtasks**:
- [ ] Hook into form_submit dataLayer pushes
- [ ] Extract click_id from form data or session
- [ ] Record form_submit events after successful tracking
- [ ] Record generate_lead events when criteria met
- [ ] Add to all framework-specific form handlers
- [ ] Ensure proper timing (after dataLayer push)
- [ ] Add feature flag support for gradual rollout

**Acceptance Criteria**:
- [ ] form_submit events recorded for all frameworks
- [ ] generate_lead events recorded when qualified
- [ ] Works across all supported form frameworks
- [ ] Timing is correct (doesn't interfere with GTM)
- [ ] Feature flag controls recording behavior

**Files Modified**:
- `assets/forms/cuft-avada-forms.js`
- `assets/forms/cuft-cf7-forms.js`
- `assets/forms/cuft-elementor-forms.js`
- `assets/forms/cuft-gravity-forms.js`
- `assets/forms/cuft-ninja-forms.js`

---

## Phase 4: Admin Interface Enhancement

### Task 4.1: Update Click Tracking Table Display
**Priority**: Medium
**Estimated Time**: 10 hours
**Dependencies**: Task 2.1
**Assignee**: Frontend Developer

**Description**: Replace utm_source/platform columns with events timeline

**Subtasks**:
- [ ] Remove utm_source and platform columns from table
- [ ] Add events timeline column with visual indicators
- [ ] Style events with appropriate colors/icons
- [ ] Show event timestamps in user-friendly format
- [ ] Handle empty events gracefully
- [ ] Add tooltip/expandable view for long event lists
- [ ] Ensure responsive design on mobile

**Acceptance Criteria**:
- [ ] Events display clearly in chronological order
- [ ] Visual design is intuitive and professional
- [ ] Performance acceptable for large datasets
- [ ] Mobile-friendly display
- [ ] Empty events handled appropriately

**Files Modified**:
- `includes/class-cuft-admin.php` (render_click_tracking_table method)
- `assets/cuft-admin.css` (new styles)

---

### Task 4.2: Enhanced Filtering and Sorting
**Priority**: Medium
**Estimated Time**: 6 hours
**Dependencies**: Task 4.1, Task 2.1
**Assignee**: Backend Developer

**Description**: Add event-based filtering and sorting options

**Subtasks**:
- [ ] Add event type filter dropdown
- [ ] Add "most recent event" date range filter
- [ ] Add "last activity" sort option
- [ ] Update get_clicks() to support event filtering
- [ ] Add JavaScript for dynamic filter updates
- [ ] Preserve existing filter functionality

**Acceptance Criteria**:
- [ ] Can filter by specific event types
- [ ] Can filter by recent activity date
- [ ] Can sort by last event timestamp
- [ ] All existing filters continue to work
- [ ] Filter state preserved in URL parameters

**Files Modified**:
- `includes/class-cuft-admin.php`
- `includes/class-cuft-click-tracker.php`
- `assets/cuft-admin.js`

---

### Task 4.3: Update CSV Export Format
**Priority**: Medium
**Estimated Time**: 4 hours
**Dependencies**: Task 2.1
**Assignee**: Backend Developer

**Description**: Modify CSV export to include event data

**Subtasks**:
- [ ] Remove utm_source and platform columns from export
- [ ] Add events column with formatted event list
- [ ] Add individual columns for each event type (latest timestamp)
- [ ] Format events as pipe-separated: "phone_click|2025-01-01 12:00:00"
- [ ] Maintain backward compatibility where possible
- [ ] Update export filename to indicate new format

**Acceptance Criteria**:
- [ ] CSV includes all event data
- [ ] Format is readable and parseable
- [ ] Export performance acceptable for large datasets
- [ ] File naming indicates format version

**Files Modified**:
- `includes/class-cuft-click-tracker.php` (export_csv method)

---

## Phase 5: Feature Flag Implementation

### Task 5.1: Feature Flag System
**Priority**: High
**Estimated Time**: 6 hours
**Dependencies**: None
**Assignee**: Backend Developer

**Description**: Implement feature flag system for gradual rollout

**Subtasks**:
- [ ] Add cuft_feature_flags option to wp_options
- [ ] Create CUFT_Utils::is_feature_enabled() method
- [ ] Add admin interface for flag management
- [ ] Implement user-based rollout (percentage)
- [ ] Add logging for flag state changes
- [ ] Create flag for click_event_tracking

**Acceptance Criteria**:
- [ ] Feature flags can be enabled/disabled via admin
- [ ] Supports percentage-based rollout
- [ ] Changes logged for debugging
- [ ] Easy to add new flags in future

**Files Created**:
- `includes/class-cuft-feature-flags.php`
- `includes/class-cuft-utils.php` (if not exists)

**Files Modified**:
- `includes/class-cuft-admin.php`

---

### Task 5.2: Apply Feature Flags to Event Recording
**Priority**: High
**Estimated Time**: 4 hours
**Dependencies**: Task 5.1, all event recording tasks
**Assignee**: Backend Developer

**Description**: Wrap all event recording functionality with feature flags

**Subtasks**:
- [ ] Add feature flag checks to add_event() method
- [ ] Add feature flag checks to JavaScript event recording
- [ ] Add feature flag checks to admin interface enhancements
- [ ] Ensure graceful degradation when flag disabled
- [ ] Add debug logging for flag-controlled behavior

**Acceptance Criteria**:
- [ ] Event recording only occurs when flag enabled
- [ ] Admin interface shows appropriate content based on flag
- [ ] No errors when flag disabled
- [ ] Easy to enable/disable entire feature

**Files Modified**:
- `includes/class-cuft-click-tracker.php`
- `includes/class-cuft-admin.php`
- `assets/cuft-links.js`
- All form tracking scripts

---

## Phase 6: Testing and Validation

### Task 6.1: Unit Tests for Event Methods
**Priority**: High
**Estimated Time**: 8 hours
**Dependencies**: Task 2.1
**Assignee**: QA Developer

**Description**: Create comprehensive unit tests for event functionality

**Subtasks**:
- [ ] Test add_event() with various event types
- [ ] Test get_events() data retrieval
- [ ] Test event chronological ordering
- [ ] Test event array size limits (100 max)
- [ ] Test JSON validation and error handling
- [ ] Test date_updated automatic updates
- [ ] Mock WordPress database functions

**Acceptance Criteria**:
- [ ] 100% code coverage for new event methods
- [ ] All edge cases tested and handled
- [ ] Tests pass in isolation and as suite
- [ ] Mock database interactions properly

**Files Created**:
- `tests/unit/test-click-tracker-events.php`
- `tests/unit/test-event-validation.php`

---

### Task 6.2: Integration Tests for Event Recording
**Priority**: High
**Estimated Time**: 10 hours
**Dependencies**: Task 3.1, Task 3.2, Task 2.2
**Assignee**: QA Developer

**Description**: Create end-to-end tests for complete event recording flow

**Subtasks**:
- [ ] Test phone_click event recording from JavaScript
- [ ] Test email_click event recording from JavaScript
- [ ] Test form_submit event recording from forms
- [ ] Test generate_lead event recording
- [ ] Test AJAX endpoint error handling
- [ ] Test feature flag behavior
- [ ] Use headless browser for JavaScript testing

**Acceptance Criteria**:
- [ ] Complete user journey tested end-to-end
- [ ] All event types recorded correctly
- [ ] Error conditions handled properly
- [ ] Feature flags work as expected

**Files Created**:
- `tests/integration/test-event-recording.php`
- `tests/integration/test-form-event-flow.php`

---

### Task 6.3: Performance Testing
**Priority**: Medium
**Estimated Time**: 6 hours
**Dependencies**: All implementation tasks
**Assignee**: QA Developer

**Description**: Validate performance impact of JSON operations

**Subtasks**:
- [ ] Benchmark add_event() performance
- [ ] Benchmark get_clicks() with events
- [ ] Test admin interface load times
- [ ] Test CSV export performance with events
- [ ] Compare performance with/without feature flag
- [ ] Test with realistic data volumes (100k+ records)

**Acceptance Criteria**:
- [ ] Performance impact <20% of baseline
- [ ] Admin interface loads within acceptable time
- [ ] Large dataset operations remain functional
- [ ] No memory leaks or excessive usage

**Files Created**:
- `tests/performance/test-event-performance.php`
- `tests/performance/benchmark-results.md`

---

## Phase 7: Data Migration Execution

### Task 7.1: Execute Data Migration
**Priority**: Critical
**Estimated Time**: 4 hours execution + monitoring
**Dependencies**: Task 1.2, all testing tasks
**Assignee**: Database Administrator

**Description**: Run progressive data migration on production data

**Subtasks**:
- [ ] Create full database backup
- [ ] Run migration in maintenance mode (optional)
- [ ] Execute batch processing with progress monitoring
- [ ] Validate data integrity after each batch
- [ ] Monitor performance impact during migration
- [ ] Handle any errors with retry logic
- [ ] Verify final data consistency

**Acceptance Criteria**:
- [ ] All existing records have events or null (acceptable)
- [ ] No data loss during migration
- [ ] Performance impact minimal during migration
- [ ] Error handling worked for any issues
- [ ] Final data validation passes all checks

**Files Used**:
- `includes/class-cuft-migration-events.php`
- `includes/class-cuft-cli-migration.php`

---

### Task 7.2: Column Cleanup
**Priority**: Medium
**Estimated Time**: 2 hours
**Dependencies**: Task 7.1, successful validation period
**Assignee**: Database Administrator

**Description**: Remove deprecated columns after successful migration

**Subtasks**:
- [ ] Verify all systems working with new structure
- [ ] Remove utm_source column
- [ ] Remove platform column
- [ ] Drop related indexes
- [ ] Update any remaining references in code
- [ ] Create final backup with new structure

**Acceptance Criteria**:
- [ ] Deprecated columns successfully removed
- [ ] No broken references or errors
- [ ] Database size reduced as expected
- [ ] All functionality working normally

**Files Modified**:
- Database schema
- Any code with column references

---

## Phase 8: Deployment and Monitoring

### Task 8.1: Gradual Feature Rollout
**Priority**: High
**Estimated Time**: 5 days (1 day each rollout stage)
**Dependencies**: Task 7.1, Task 5.2
**Assignee**: DevOps/Product Owner

**Description**: Gradually enable event tracking for increasing user percentages

**Subtasks**:
- [ ] Day 1: Enable for 1% of users
- [ ] Day 2: Monitor metrics, increase to 10%
- [ ] Day 3: Monitor metrics, increase to 50%
- [ ] Day 4: Monitor metrics, increase to 90%
- [ ] Day 5: Enable for 100% of users
- [ ] Monitor error rates at each stage
- [ ] Be ready to rollback if issues detected

**Acceptance Criteria**:
- [ ] Each rollout stage completes without issues
- [ ] Error rates remain within acceptable limits
- [ ] Performance metrics remain stable
- [ ] User experience not negatively affected

**Files Modified**:
- Feature flag settings in wp_options

---

### Task 8.2: Monitoring and Alerting Setup
**Priority**: Medium
**Estimated Time**: 4 hours
**Dependencies**: Task 8.1
**Assignee**: DevOps

**Description**: Set up monitoring for event recording functionality

**Subtasks**:
- [ ] Monitor event recording success/failure rates
- [ ] Monitor database performance for JSON operations
- [ ] Set up alerts for unusual error patterns
- [ ] Monitor admin interface performance
- [ ] Track event recording volume trends
- [ ] Set up dashboard for stakeholder visibility

**Acceptance Criteria**:
- [ ] Key metrics monitored and alerting configured
- [ ] Dashboard provides clear system status
- [ ] Alerts fire appropriately for issues
- [ ] Historical data tracked for analysis

**Tools/Services**:
- Application monitoring service
- Database monitoring tools
- Custom WordPress logging

---

## Task Dependencies Graph

```
Phase 1: Schema Preparation
Task 1.1 (Schema) → Task 1.2 (Migration Utility)

Phase 2: Core Enhancement
Task 1.1 → Task 2.1 (Click Tracker) → Task 2.2 (AJAX)

Phase 3: JavaScript Integration
Task 2.2 → Task 3.1 (Links JS)
Task 2.2 → Task 3.2 (Form JS)

Phase 4: Admin Interface
Task 2.1 → Task 4.1 (Table Display) → Task 4.2 (Filtering)
Task 2.1 → Task 4.3 (CSV Export)

Phase 5: Feature Flags
Task 5.1 (Flag System) → Task 5.2 (Apply Flags)
[All Event Tasks] → Task 5.2

Phase 6: Testing
Task 2.1 → Task 6.1 (Unit Tests)
[Task 3.1, 3.2, 2.2] → Task 6.2 (Integration Tests)
[All Implementation] → Task 6.3 (Performance Tests)

Phase 7: Migration
Task 1.2 + [All Tests] → Task 7.1 (Execute Migration)
Task 7.1 + Validation Period → Task 7.2 (Cleanup)

Phase 8: Deployment
Task 7.1 + Task 5.2 → Task 8.1 (Gradual Rollout)
Task 8.1 → Task 8.2 (Monitoring)
```

---

## Risk Mitigation Tasks

### High-Risk Mitigation
- **Data Loss Prevention**: Task 1.2 includes comprehensive backup and rollback
- **Performance Impact**: Task 6.3 validates before production deployment
- **Integration Breakage**: Task 6.2 tests all integration points

### Medium-Risk Mitigation
- **Feature Flag Rollback**: Task 5.2 enables quick disable if issues
- **Migration Failure**: Task 7.1 includes error handling and retry logic
- **User Experience**: Task 8.1 gradual rollout allows early issue detection

---

## Success Metrics

### Technical Metrics
- [ ] Zero data loss during migration
- [ ] <20% performance impact from JSON operations
- [ ] >99.9% event recording success rate
- [ ] <100ms average response time for admin interface

### Business Metrics
- [ ] Enhanced user journey visibility achieved
- [ ] Improved lead qualification insights available
- [ ] Simplified table structure (removed redundant columns)
- [ ] Maintained all existing functionality

---

## Timeline Summary

**Total Estimated Time**: 85 hours (approximately 11 working days)

**Phase Breakdown**:
- Phase 1: 12 hours (1.5 days)
- Phase 2: 18 hours (2.3 days)
- Phase 3: 14 hours (1.8 days)
- Phase 4: 20 hours (2.5 days)
- Phase 5: 10 hours (1.3 days)
- Phase 6: 24 hours (3 days)
- Phase 7: 6 hours (0.8 days)
- Phase 8: 5 days monitoring (includes waiting periods)

**Critical Path**: Schema → Core Enhancement → Event Integration → Testing → Migration

This task breakdown ensures systematic implementation with proper validation and risk mitigation at each stage.