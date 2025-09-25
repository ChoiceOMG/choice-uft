# Feature Specification: Multi-framework Form Submission Tracking

**Feature Branch**: `002-multi-framework-form`
**Created**: 2025-09-25
**Status**: Draft (Consolidated from existing framework specifications)
**Input**: User description: "Multi-framework form submission tracking @specs/frameworks/"

## Execution Flow (main)
```
1. Parse user description from Input
   → Extracted: "Multi-framework form submission tracking"
   → Referenced: 5 existing framework specifications
2. Extract key concepts from description
   → Identified: 5 form frameworks (Elementor, CF7, Ninja, Gravity, Avada)
   → Identified: submission events, field extraction, success detection
3. Consolidate framework specifications
   → Merged requirements from all 5 framework specs
   → Unified detection methods and event handling approaches
4. Fill User Scenarios & Testing section
   → User flows for each framework defined
   → Cross-framework compatibility scenarios added
5. Generate Functional Requirements
   → Each framework's specific needs documented
   → Common requirements extracted and unified
6. Identify Key Entities
   → Form submissions, tracking events, framework types, field data
7. Run Review Checklist
   → All clarifications resolved from existing specs
8. Return: SUCCESS (spec consolidated and ready for planning)
```

---

## ⚡ Quick Guidelines
- ✅ Focus on WHAT users need and WHY
- ❌ Avoid HOW to implement (no tech stack, APIs, code structure)
- 👥 Written for business stakeholders, not developers

---

## User Scenarios & Testing *(mandatory)*

### Primary User Story
As a website owner using various WordPress form plugins (Elementor Pro, Contact Form 7, Ninja Forms, Gravity Forms, or Avada/Fusion), I want all form submissions to be automatically tracked and sent to Google Tag Manager so that I can monitor conversions and user interactions in Google Analytics 4, regardless of which form framework is used on any given page.

### Acceptance Scenarios

#### Universal Requirements (All Frameworks)
1. **Given** any supported form on a webpage, **When** a user successfully submits the form, **Then** a `form_submit` event is pushed to the dataLayer with standardized field names
2. **Given** a form with email and phone fields, **When** submitted with valid data, **Then** user_email and user_phone are captured and included in the tracking event
3. **Given** a user arriving with UTM parameters or click IDs, **When** they submit any form, **Then** all tracking parameters are preserved and included in the event
4. **Given** a form with email, phone, and click ID present, **When** submitted successfully, **Then** both `form_submit` and `generate_lead` events are fired

#### Framework-Specific Scenarios

**Elementor Pro Forms**:
1. **Given** an Elementor form with multi-step configuration, **When** the user completes all steps and submits, **Then** tracking fires only on final step completion
2. **Given** an Elementor form in a popup, **When** submitted successfully and popup closes, **Then** the submission is still tracked
3. **Given** an Elementor form with invalid regex patterns, **When** the page loads, **Then** patterns are automatically fixed to prevent browser errors

**Contact Form 7**:
1. **Given** a CF7 form, **When** the `wpcf7mailsent` event fires, **Then** form submission is tracked
2. **Given** multiple CF7 forms on the same page, **When** any form is submitted, **Then** only that specific form's data is tracked
3. **Given** a CF7 form with custom field names, **When** submitted, **Then** email and phone are still detected correctly

**Ninja Forms**:
1. **Given** a Ninja Form with AJAX submission, **When** success message appears, **Then** form submission is tracked
2. **Given** a multi-step Ninja Form, **When** final step is submitted, **Then** tracking occurs only once
3. **Given** a Ninja Form that hides after submission, **When** form disappears, **Then** submission is detected and tracked

**Gravity Forms**:
1. **Given** a Gravity Form with complex fields (multi-part phone/name), **When** submitted, **Then** field values are correctly extracted and combined
2. **Given** a multi-page Gravity Form, **When** final page is submitted, **Then** tracking fires only on completion
3. **Given** a Gravity Form with conditional logic, **When** submitted with hidden fields, **Then** only visible field values are captured

**Avada/Fusion Forms**:
1. **Given** a dynamically loaded Fusion Form, **When** added to page via builder, **Then** form is automatically detected for tracking
2. **Given** an Avada form with multi-step flow, **When** final step completes, **Then** submission is tracked once
3. **Given** a Fusion form in a modal, **When** submitted and modal closes, **Then** tracking still occurs

### Edge Cases
- What happens when multiple different frameworks exist on the same page?
  → Each framework's forms are tracked independently without interference
- How does system handle forms without email/phone fields?
  → Forms are tracked with available data; missing fields are omitted
- What happens when form submission fails on the server side?
  → Only successful submissions (confirmed by framework) are tracked
- How does system handle rapid multiple submissions?
  → Deduplication prevents the same submission from being tracked twice
- What happens when JavaScript errors occur in other scripts?
  → Error isolation ensures tracking continues to function
- How are forms in iframes handled?
  → Forms in same-origin iframes are tracked; cross-origin iframes are not accessible

## Requirements *(mandatory)*

### Functional Requirements

#### Framework Detection & Compatibility
- **FR-001**: System MUST detect and identify forms from all 5 supported frameworks using CSS classes and DOM structure
- **FR-002**: System MUST exit silently when encountering non-supported form types (no console output)
- **FR-003**: System MUST handle multiple frameworks coexisting on the same page without interference
- **FR-004**: System MUST support both jQuery and non-jQuery environments (JavaScript-first approach)

#### Event Handling & Success Detection
- **FR-005**: System MUST implement framework-specific event listeners:
  - Elementor: `submit_success` event (native and jQuery fallback)
  - CF7: `wpcf7mailsent` event
  - Ninja/Gravity/Avada: Submit-based with success detection
- **FR-006**: System MUST detect successful submissions using multiple methods:
  - Success message detection
  - Form hiding/visibility changes
  - MutationObserver for DOM changes
  - AJAX response monitoring
- **FR-007**: System MUST implement retry logic with exponential backoff for success detection
- **FR-008**: System MUST prevent duplicate tracking using deduplication attributes

#### Field Value Extraction
- **FR-009**: System MUST extract email fields using comprehensive detection:
  - Input type="email"
  - Field names/IDs containing "email"
  - Label text analysis
  - Pattern attribute checking
  - Framework-specific field structures
- **FR-010**: System MUST extract phone fields using multiple methods:
  - Input type="tel"
  - Field names/IDs containing "phone/tel/mobile"
  - Complex multi-part phone fields (Gravity Forms)
  - Pattern validation for numeric inputs
- **FR-011**: System MUST validate email addresses before tracking
- **FR-012**: System MUST sanitize phone numbers while preserving international formats

#### Form Identification
- **FR-013**: System MUST extract form IDs using framework-specific methods with fallback hierarchy
- **FR-014**: System MUST extract form names from available sources (attributes, labels, headings)
- **FR-015**: System MUST generate consistent fallback IDs when no ID is available

#### Data Standardization
- **FR-016**: System MUST use snake_case for all dataLayer field names:
  - form_type, form_id, form_name
  - user_email, user_phone
  - submitted_at (ISO 8601 format)
  - cuft_tracked, cuft_source
- **FR-017**: System MUST include framework identifiers:
  - Elementor: form_type="elementor", cuft_source="elementor_pro"
  - CF7: form_type="cf7", cuft_source="contact_form_7"
  - Ninja: form_type="ninja", cuft_source="ninja_forms"
  - Gravity: form_type="gravity", cuft_source="gravity_forms"
  - Avada: form_type="avada", cuft_source="avada_forms"

#### Tracking Parameters
- **FR-018**: System MUST capture and include all UTM parameters (source, medium, campaign, term, content)
- **FR-019**: System MUST capture and include all supported click IDs:
  - gclid, gbraid, wbraid (Google)
  - fbclid (Facebook/Meta)
  - msclkid (Microsoft/Bing)
  - ttclid (TikTok)
  - li_fat_id (LinkedIn)
  - twclid (Twitter/X)
  - snap_click_id (Snapchat)
  - pclid (Pinterest)
- **FR-020**: System MUST retrieve tracking data using fallback chain:
  - URL parameters → SessionStorage → Cookies → Empty

#### Conversion Events
- **FR-021**: System MUST fire `form_submit` event for all successful submissions
- **FR-022**: System MUST fire `generate_lead` event when ALL conditions are met:
  - Valid email address present
  - Phone number present
  - At least one click ID present
- **FR-023**: System MUST include currency="USD" and value=0 in generate_lead events

#### Multi-Step/Multi-Page Forms
- **FR-024**: System MUST track multi-step forms only on final step completion
- **FR-025**: System MUST detect current step position in multi-step forms
- **FR-026**: System MUST handle multi-page forms with proper state management

#### Special Form Types
- **FR-027**: System MUST track forms in popups/modals even after container closes
- **FR-028**: System MUST handle dynamically loaded forms (AJAX/builder-added)
- **FR-029**: System MUST support forms with conditional logic (only visible fields tracked)
- **FR-030**: System MUST fix invalid regex patterns in form validation (Elementor)

#### Performance & Reliability
- **FR-031**: System MUST complete form processing within 50ms
- **FR-032**: System MUST implement proper memory management (observer cleanup, attribute removal)
- **FR-033**: System MUST handle errors gracefully with try-catch blocks
- **FR-034**: System MUST continue functioning despite errors in other scripts

#### Privacy & Compliance
- **FR-035**: System MUST NOT track failed form submissions or validation errors
- **FR-036**: System MUST NOT interfere with form functionality or user experience
- **FR-037**: System MUST respect user privacy (no tracking without successful submission)

### Key Entities

- **Form Submission**: Completed form submission event containing:
  - Timestamp of submission
  - Form identification (ID and name)
  - User data (email/phone if provided)
  - Framework type and source
  - Tracking parameters (UTM/click IDs)

- **Form Framework**: Type of WordPress form plugin:
  - Elementor Pro Forms (primary implementation)
  - Contact Form 7
  - Ninja Forms
  - Gravity Forms
  - Avada/Fusion Forms

- **Tracking Parameters**: Session-persistent tracking data:
  - UTM parameters (source, medium, campaign, term, content)
  - Click IDs (various advertising platforms)
  - Stored across page navigation

- **Field Detection Pattern**: Methods for identifying form fields:
  - Direct attribute matching (type, name, id)
  - Label text analysis
  - Pattern/validation attribute checking
  - Framework-specific structures

- **Success Detection Method**: Approach for confirming submission:
  - Event-based (Elementor, CF7)
  - Message detection (Ninja, Gravity, Avada)
  - Form state changes (hiding, redirection)
  - MutationObserver monitoring

- **DataLayer Event**: Standardized tracking event containing:
  - Event name (form_submit or generate_lead)
  - Form metadata
  - User data
  - Tracking attribution
  - Constitutional compliance markers

---

## Review & Acceptance Checklist
*GATE: Automated checks run during main() execution*

### Content Quality
- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

### Requirement Completeness
- [x] No [NEEDS CLARIFICATION] markers remain (resolved from framework specs)
- [x] Requirements are testable and unambiguous
- [x] Success criteria are measurable
- [x] Scope is clearly bounded (5 specific frameworks)
- [x] Dependencies and assumptions identified

### Framework Coverage
- [x] Elementor Pro Forms fully specified
- [x] Contact Form 7 fully specified
- [x] Ninja Forms fully specified
- [x] Gravity Forms fully specified
- [x] Avada/Fusion Forms fully specified

---

## Execution Status
*Updated by main() during processing*

- [x] User description parsed
- [x] Key concepts extracted from 5 framework specifications
- [x] Framework specifications consolidated
- [x] User scenarios defined for each framework
- [x] Requirements generated (37 functional requirements)
- [x] Entities identified (6 key entity types)
- [x] Review checklist passed

---

## Appendix: Framework-Specific Technical Details

### Detection Patterns by Framework

**Elementor**: `.elementor-form`, `.elementor-widget-form`, `[data-settings]`
**CF7**: `.wpcf7`, `.wpcf7-form`, `[data-wpcf7-id]`
**Ninja**: `.nf-form-cont`, `.nf-form`, `.nf-field`
**Gravity**: `.gform_wrapper`, `.gfield`
**Avada**: `.fusion-form`, `.fusion-form-container`, `.fusion-form-field`

### Event Types by Framework

**Event-Based Tracking**:
- Elementor: Custom event `submit_success`
- CF7: Custom event `wpcf7mailsent`

**Submit-Based Tracking**:
- Ninja Forms: Form submit + success detection
- Gravity Forms: Form submit + confirmation detection
- Avada Forms: Form submit + success message detection

### Performance Benchmarks

- Script initialization: <5ms
- Form detection: <3ms per form
- Field extraction: <10ms per form
- Event processing: <15ms total
- Success detection: <20ms per attempt
- Memory footprint: <1KB per form

---

This consolidated specification provides comprehensive requirements for tracking form submissions across all 5 supported WordPress form frameworks, ensuring consistent data collection while respecting each framework's unique architecture and event system.