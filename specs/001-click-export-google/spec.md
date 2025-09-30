# Feature Specification: Google Ads Offline Conversion Import Export

**Feature Branch**: `001-click-export-google`
**Created**: 2025-09-30
**Status**: Draft
**Input**: User description: "click-export-google-ads"

## Execution Flow (main)
```
1. Parse user description from Input
   → Feature identified: Export click tracking data for Google Ads offline conversion import
2. Extract key concepts from description
   → Actors: WordPress administrators
   → Actions: Export filtered click data, import to Google Ads
   → Data: GCLID records, events, conversion values, timestamps
   → Constraints: Google Ads OCI format requirements
3. For each unclear aspect:
   → All requirements clear from implementation context
4. Fill User Scenarios & Testing section
   → User flow: Filter clicks → Export CSV → Import to Google Ads
5. Generate Functional Requirements
   → Each requirement is testable
6. Identify Key Entities
   → Click records, Events, Conversion data
7. Run Review Checklist
   → No implementation details in requirements
   → All requirements testable
8. Return: SUCCESS (spec ready for planning)
```

---

## ⚡ Quick Guidelines
- ✅ Focus on WHAT users need and WHY
- ❌ Avoid HOW to implement (no tech stack, APIs, code structure)
- 👥 Written for business stakeholders, not developers

---

## User Scenarios & Testing

### Primary User Story
As a WordPress administrator managing paid advertising campaigns, I need to export click tracking data in a format that Google Ads can import, so that I can track offline conversions (form submissions, phone calls, qualified leads) back to the original ad clicks and optimize campaign performance.

### Acceptance Scenarios

1. **Given** I have click tracking data with GCLID records, **When** I click "Export for Google Ads" button, **Then** the system downloads a CSV file containing only GCLID records in Google Ads OCI format with proper headers and timezone declaration

2. **Given** a click record has multiple events (phone click, form submit, qualified lead), **When** I export for Google Ads, **Then** the CSV includes one row per event with appropriate conversion names and timestamps

3. **Given** a click record has no recorded events, **When** I export for Google Ads, **Then** the CSV includes one row with "Ad Click" as the conversion name and the click creation date as the conversion time

4. **Given** I have date range filters applied in the admin interface, **When** I export for Google Ads, **Then** only click records within the selected date range are included in the export

5. **Given** a click record is for a qualified lead with a quality score, **When** I export for Google Ads, **Then** the conversion value is calculated proportionally based on the configured lead value and the record's score

6. **Given** I have configured a lead value and currency in settings, **When** I export for Google Ads, **Then** all conversion rows use the configured currency and qualified leads use the configured lead value for calculations

### Edge Cases

- What happens when no GCLID records exist in the database?
  - System should display appropriate message or return empty CSV with headers only

- How does the system handle click records with malformed events data?
  - System should skip invalid events gracefully and process valid ones

- What happens when a user exports with no filters and has thousands of records?
  - Export should be limited to a reasonable maximum (e.g., 10,000 records) to prevent performance issues

- How does the system handle non-GCLID click IDs (Facebook, TikTok, etc.)?
  - System should filter to include ONLY GCLID records, excluding all other click ID types

## Requirements

### Functional Requirements

- **FR-001**: System MUST provide an "Export for Google Ads" button in the Click Tracking Management interface, distinct from the existing general CSV export

- **FR-002**: System MUST filter exported data to include ONLY click records where the click_id is a valid Google Ads Click ID (GCLID)

- **FR-003**: System MUST format the exported CSV according to Google Ads Offline Conversion Import specifications, including a Parameters row declaring UTC timezone and column headers matching Google Ads requirements

- **FR-004**: System MUST export one CSV row per recorded event for each click record that has events (phone_click, email_click, form_submit, generate_lead)

- **FR-005**: System MUST export one CSV row with conversion name "Ad Click" for click records that have no recorded events, using the click creation date as the conversion time

- **FR-006**: System MUST include the following columns in the exported CSV: Google Click ID, Conversion Name, Conversion Time, Conversion Value, Conversion Currency

- **FR-007**: System MUST use ISO 8601 UTC format for all conversion timestamps (e.g., "2025-09-29T14:30:00Z")

- **FR-008**: System MUST map event types to human-readable conversion names:
  - phone_click → "Phone Click"
  - email_click → "Email Click"
  - form_submit → "Form Submit"
  - generate_lead → "Qualified Lead"
  - status_qualified → "Status Qualified"
  - score_updated → "Score Updated"

- **FR-009**: System MUST calculate conversion values as follows:
  - For "Qualified Lead" conversions: (configured lead value × record score) ÷ 10
  - For all other conversion types: 0

- **FR-010**: System MUST use the lead value and currency configured in the plugin settings for all exported conversions

- **FR-011**: System MUST respect existing admin filters (qualified status, date range) when determining which records to export

- **FR-012**: System MUST limit exports to a maximum of 10,000 records to ensure reasonable file sizes and processing times

- **FR-013**: System MUST use UTF-8 character encoding for the exported CSV file

- **FR-014**: System MUST generate a unique filename for each export using the pattern "google-ads-oci-[YYYY-MM-DD-HH-MM-SS].csv"

- **FR-015**: System MUST require administrator-level permissions to access the export functionality

### Key Entities

- **Click Record**: Represents a tracked ad click with attributes including:
  - click_id (the GCLID from Google Ads)
  - qualified (whether the lead meets quality criteria)
  - score (quality rating from 0-10)
  - date_created (when the click was first tracked)
  - events (chronological list of interactions)

- **Event**: Represents a user interaction associated with a click, with attributes including:
  - event type (phone_click, email_click, form_submit, generate_lead, etc.)
  - timestamp (when the event occurred in ISO 8601 UTC format)

- **Conversion Row**: Represents a single line in the Google Ads import file with attributes:
  - Google Click ID (the GCLID)
  - Conversion Name (human-readable event description)
  - Conversion Time (ISO 8601 UTC timestamp)
  - Conversion Value (monetary value, calculated or zero)
  - Conversion Currency (from plugin settings)

- **Export Configuration**: Settings that control export behavior:
  - Lead Value (monetary value assigned to qualified leads)
  - Currency (ISO currency code like USD, CAD, EUR, AUD)
  - Date filters (optional from/to date range)
  - Qualified filter (optional qualified/not qualified status)

---

## Review & Acceptance Checklist

### Content Quality
- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

### Requirement Completeness
- [x] No [NEEDS CLARIFICATION] markers remain
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

---

## Execution Status

- [x] User description parsed
- [x] Key concepts extracted
- [x] Ambiguities marked (none found)
- [x] User scenarios defined
- [x] Requirements generated
- [x] Entities identified
- [x] Review checklist passed

---
