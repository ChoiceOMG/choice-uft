# Feature Specification: Admin Testing Dashboard

**Feature Branch**: `002-when-the-plugin`
**Created**: 2025-09-30
**Status**: Draft
**Input**: User description: "When the plugin is activated, I would like to create a simple test page which allows users to easily create forms and buttons for one-click testing of all conversion tracking. Tools like generate sample data which creates click ids, campaigns, etc. Simulate phone click. Simulate form submission. Build test form with all fields preloaded. Change preloaded test form.  Keep it private for admin only but it should allow testing of all features in the choice-uft plugin including the click tracking"

## Execution Flow (main)
```
1. Parse user description from Input
   → Feature: Admin-only testing dashboard for conversion tracking
2. Extract key concepts from description
   → Actors: WordPress administrators
   → Actions: Generate test data, simulate clicks/submissions, manage test forms
   → Data: Click IDs, UTM parameters, form fields, tracking events
   → Constraints: Admin-only access, all tracking features testable
3. For each unclear aspect:
   → [NEEDS CLARIFICATION: Should test data generation persist to database or be session-only?]
   → [NEEDS CLARIFICATION: Should test events be visible in production analytics or flagged as test?]
4. Fill User Scenarios & Testing section
   → ✅ Clear user flow: Admin accesses dashboard → generates test data → simulates events → validates tracking
5. Generate Functional Requirements
   → All requirements testable and measurable
6. Identify Key Entities
   → Test Session, Test Form Configuration, Simulated Event
7. Run Review Checklist
   → WARN "Spec has uncertainties" (2 clarifications needed)
8. Return: SUCCESS (spec ready for planning after clarifications)
```

---

## ⚡ Quick Guidelines
- ✅ Focus on WHAT admins need and WHY
- ❌ Avoid HOW to implement (no tech stack, APIs, code structure)
- 👥 Written for business stakeholders, not developers

---

## Clarifications

### Session 2025-09-30
- Q: How should generated test data persist? → A: Browser storage (persists across reloads until browser cache cleared)
- Q: How should test events be prevented from polluting production analytics? → A: Combination of test_mode flag in dataLayer events + separate database table storage
- Q: What response time is acceptable for simulated event triggering? → A: Fast (<500ms)
- Q: What dataLayer events should the dashboard's event viewer display? → A: Configurable filter (admin can toggle test-only vs all events)
- Q: Which form framework should the "Build Test Form" feature use? → A: Admin choice (dropdown to select framework)

---

## User Scenarios & Testing *(mandatory)*

### Primary User Story
As a WordPress administrator, I need a private testing dashboard where I can quickly validate that all conversion tracking features (click tracking, form submissions, lead generation events) are working correctly without affecting production analytics or requiring real user interactions.

### Acceptance Scenarios

1. **Given** I am a logged-in WordPress administrator, **When** I access the testing dashboard, **Then** I should see a private admin page with testing tools that regular users cannot access.

2. **Given** I am on the testing dashboard, **When** I click "Generate Sample Data", **Then** the system should create realistic test data including click IDs (gclid, fbclid, etc.), UTM parameters (source, medium, campaign, term, content), and other tracking identifiers.

3. **Given** sample test data has been generated, **When** I click "Simulate Phone Click", **Then** the system should trigger a phone click event with the generated tracking parameters and I should be able to verify the event in the dataLayer.

4. **Given** sample test data has been generated, **When** I click "Simulate Form Submission", **Then** the system should trigger a form_submit event with all required fields (form_type, form_id, user_email, user_phone, cuft_tracked, cuft_source) populated with test values.

5. **Given** I am on the testing dashboard, **When** I select a form framework from a dropdown and click "Build Test Form", **Then** the system should create a fully functional test form using the selected framework with all standard fields (name, email, phone, message) pre-populated with test data.

6. **Given** a test form exists, **When** I submit the test form, **Then** the system should trigger both form_submit and generate_lead events (if conditions are met) that I can validate in the dataLayer console.

7. **Given** a test form is displayed, **When** I click "Change Preloaded Test Form", **Then** the system should allow me to modify the pre-populated field values for subsequent testing scenarios.

8. **Given** I have performed multiple test actions, **When** I review the test results, **Then** I should be able to validate that all conversion tracking features (click tracking, form tracking, lead generation) are functioning correctly with proper event formatting.

### Edge Cases
- What happens when an administrator tries to access the testing dashboard without sufficient permissions?
  - System should deny access and redirect to appropriate page.

- What happens when test data generation is triggered multiple times in succession?
  - System should overwrite previous test data stored in browser storage with newly generated values

- How does the system handle test form submissions when GTM or dataLayer is not properly configured?
  - System should display clear error messages indicating missing configuration without breaking functionality.

- What happens when simulated events are triggered but no tracking parameters exist in the current session?
  - System should generate temporary test parameters for that specific simulation.

- How does the system prevent test events from polluting production analytics data?
  - System should add test_mode: true flag to all dataLayer events AND store test events in a separate database table for isolation

---

## Requirements *(mandatory)*

### Functional Requirements

#### Access Control
- **FR-001**: System MUST restrict testing dashboard access to WordPress administrators only (capability: 'manage_options')
- **FR-002**: System MUST display "Access Denied" message to non-administrator users who attempt to access the testing dashboard
- **FR-003**: System MUST provide a dedicated admin menu item or submenu for accessing the testing dashboard

#### Test Data Generation
- **FR-004**: System MUST generate realistic sample tracking data including all supported click ID types (click_id, gclid, gbraid, wbraid, fbclid, msclkid, ttclid, li_fat_id, twclid, snap_click_id, pclid)
- **FR-005**: System MUST generate complete UTM parameter sets (utm_source, utm_medium, utm_campaign, utm_term, utm_content)
- **FR-006**: System MUST allow administrators to view the generated test data before using it in simulations
- **FR-007**: System MUST persist generated test data in browser storage (localStorage) so it survives page reloads but is cleared when browser cache is cleared

#### Event Simulation
- **FR-008**: System MUST provide one-click phone click simulation that triggers phone_click events with generated tracking parameters
- **FR-009**: System MUST provide one-click email click simulation that triggers email_click events with generated tracking parameters
- **FR-010**: System MUST provide one-click form submission simulation that triggers form_submit events with all required dataLayer fields (cuft_tracked: true, cuft_source, form_type, form_id, form_name, user_email, user_phone, submitted_at)
- **FR-011**: System MUST trigger generate_lead events when simulated form submissions include email, phone, and click ID
- **FR-012**: System MUST display simulated event data in a readable format for validation

#### Test Form Management
- **FR-013**: System MUST dynamically detect which form frameworks are installed and active, then provide a dropdown showing only available options for test form creation
- **FR-014**: System MUST create fully functional test forms using the selected framework with standard fields (name, email, phone, message)
- **FR-015**: System MUST pre-populate test form fields with realistic sample data
- **FR-016**: System MUST allow administrators to modify pre-populated field values
- **FR-017**: System MUST use the same production tracking code when processing test form submissions (not separate test implementations)
- **FR-018**: Test forms MUST trigger the same dataLayer events as real forms to ensure accurate testing

#### Event Validation
- **FR-019**: System MUST provide a console viewer or event log showing dataLayer events with configurable filtering (test-only mode or all events mode)
- **FR-020**: System MUST validate that simulated events contain all required fields per constitutional standards
- **FR-021**: System MUST display validation status (success/failure) for each simulated event
- **FR-022**: System MUST highlight missing or incorrectly formatted fields in validation results
- **FR-023**: System MUST allow administrators to toggle between viewing only test events (test_mode: true) and all dataLayer events

#### Click Tracking Testing
- **FR-024**: System MUST allow testing of click tracking functionality by generating and validating click_id parameters
- **FR-025**: System MUST simulate the full click tracking lifecycle (parameter capture, storage, event recording)
- **FR-026**: System MUST display recorded click tracking events from the database
- **FR-027**: System MUST allow validation that click IDs are properly associated with form submissions

#### Test Data Isolation
- **FR-028**: System MUST add test_mode: true flag to all dataLayer events generated from the testing dashboard to enable filtering in analytics platforms
- **FR-029**: System MUST store test events in a separate database table isolated from production click tracking data
- **FR-030**: System MUST provide capability to view, filter, and delete test events from the separate test database table

### Non-Functional Requirements

#### Performance
- **NFR-001**: Simulated event triggering (phone click, email click, form submission) MUST respond within 500ms
- **NFR-002**: Test data generation MUST complete within 500ms
- **NFR-003**: Event validation display MUST render within 500ms of event trigger

#### Usability
- **NFR-004**: Dashboard interface MUST provide clear visual feedback (loading indicators, success/error states) for all async operations

### Key Entities

- **Test Session**: Represents a single administrator's testing session with generated sample data, including click IDs, UTM parameters, timestamp of generation, and session identifier

- **Test Form Configuration**: Represents a test form's structure and pre-populated values, including selected framework (Elementor, CF7, Ninja Forms, Gravity Forms, Avada), field names, field types, default values, and modification history

- **Simulated Event**: Represents a single simulated tracking event (phone click, email click, form submission, lead generation), including event type, timestamp, generated parameters, validation status, and associated test session

- **Event Validation Result**: Represents the validation outcome of a simulated event, including required fields present, field format correctness, dataLayer compatibility, and constitutional compliance status

---

## Review & Acceptance Checklist
*GATE: Automated checks run during main() execution*

### Content Quality
- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

### Requirement Completeness
- [x] No [NEEDS CLARIFICATION] markers remain (all resolved)
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

---

## Execution Status
*Updated by main() during processing*

- [x] User description parsed
- [x] Key concepts extracted
- [x] Ambiguities marked and resolved (5 clarifications completed)
- [x] User scenarios defined
- [x] Requirements generated (30 functional + 4 non-functional)
- [x] Entities identified
- [x] Review checklist passed

---

## Clarifications Required

All critical clarifications resolved in Session 2025-09-30:

1. ~~**Test Data Persistence** (FR-007)~~ ✅ Resolved: Browser storage (localStorage)
2. ~~**Test Event Isolation** (FR-028, FR-029, FR-030)~~ ✅ Resolved: Dual approach with test_mode flag + separate database table
3. ~~**Test Data Generation Behavior**~~ ✅ Resolved: Overwrite on each generation
4. ~~**Performance Expectations** (NFR-001, NFR-002, NFR-003)~~ ✅ Resolved: <500ms response time
5. ~~**Event Viewer Scope** (FR-019, FR-023)~~ ✅ Resolved: Configurable filter (test-only vs all events)
6. ~~**Test Form Framework Selection** (FR-013, FR-014)~~ ✅ Resolved: Dynamic detection of installed frameworks, dropdown shows only available options
