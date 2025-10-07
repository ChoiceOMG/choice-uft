# Feature Specification: Custom GTM Server Domain with Health Checks

**Feature Branch**: `006-provide-users-with`
**Created**: 2025-10-06
**Status**: Draft
**Input**: User description: "Provide users with an option to configure their own first-party server domain for loading GTM container scripts instead of using Google's default domains. When a custom server URL is configured, the system must asynchronously test whether the GTM scripts load successfully from that endpoint before committing to use it. If the custom server fails to respond or the scripts fail to load, the system must automatically fallback to Google's default GTM endpoints to ensure tracking continues without interruption. The system must perform periodic background health checks of the configured custom server at regular intervals to detect when a previously-failing server becomes available or when a previously-working server goes down. Store both the timestamp of the most recent health check and the result (success/failure) of that check so administrators can monitor server status. The health check results must persist across page loads and be accessible for diagnostic purposes. This feature ensures that users who operate their own server-side GTM infrastructure for privacy, performance, or compliance reasons can seamlessly integrate it while maintaining resilience through automatic fallback when their infrastructure experiences issues."

## Execution Flow (main)

```
1. Parse user description from Input
   → Feature identified: Custom GTM server domain configuration with health monitoring
2. Extract key concepts from description
   → Actors: Site administrators, end users (visitors)
   → Actions: Configure custom server, validate availability, fallback, health check
   → Data: Server URL, health check results, timestamps
   → Constraints: Must not disrupt tracking, must fallback automatically
3. For each unclear aspect:
   → Health check interval: Every 6 hours (4 times daily)
   → Health check context: Both admin area and frontend page loads
   → Script validation: HTTP 200 response with parseable JavaScript
   → Administrator notifications: WordPress admin notices only
   → Timeout duration: 5 seconds
4. Fill User Scenarios & Testing section
   → Primary scenario: Configure custom server, system validates, tracking works
   → Edge cases: Server down during config, server goes down after working, etc.
5. Generate Functional Requirements
   → All requirements testable and measurable
6. Identify Key Entities
   → Custom Server Configuration, Health Check Result
7. Run Review Checklist
   → WARN "Spec has uncertainties" - 5 clarification markers present
8. Return: SUCCESS (spec ready for planning after clarifications)
```

---

## ⚡ Quick Guidelines

- ✅ Focus on WHAT users need and WHY
- ❌ Avoid HOW to implement (no tech stack, APIs, code structure)
- 👥 Written for business stakeholders, not developers

---

## Clarifications

### Session 2025-10-06

- Q: How frequently should the system perform background health checks of the custom GTM server? → A: Every 6 hours (4 times daily)
- Q: Should health checks run only in admin area or also during frontend page loads? → A: Both contexts - health checks run in admin area AND during frontend page loads
- Q: What constitutes a "successful" GTM script load test? → A: HTTP 200 + valid JavaScript syntax - server responds and returns parseable JavaScript
- Q: Should administrators receive alerts when server status changes? → A: WordPress admin notice only - display persistent notice in admin area when status changes
- Q: How long should the system wait before declaring a health check timeout? → A: 5 seconds - fast timeout for quick fallback
- Q: What should happen when a custom server is intermittently available? → A: Require 3 consecutive successes before switching back to custom server

---

## User Scenarios & Testing

### Primary User Story

A site administrator who operates their own first-party server-side GTM infrastructure for privacy compliance wants to configure their WordPress site to load GTM scripts from their custom domain instead of Google's default domains. They enter their custom server URL in the plugin settings, and the system automatically validates that the server is accessible and serving the correct GTM scripts. If the server is working, tracking begins using the custom domain. If the server is unavailable or not serving valid scripts, the system automatically falls back to Google's default endpoints so tracking continues without interruption. The system continuously monitors the custom server's health in the background and switches between custom and default endpoints based on availability, ensuring tracking never fails regardless of infrastructure issues.

### Acceptance Scenarios

1. **Given** the administrator has a working custom GTM server at `https://gtm.example.com`, **When** they enter this URL in the plugin settings and save, **Then** the system validates the server is accessible, loads GTM scripts from the custom domain, and displays a success message showing the custom server is active.

2. **Given** the administrator enters a custom server URL that is currently offline, **When** they save the settings, **Then** the system detects the server is unavailable, displays a warning message, automatically falls back to Google's default GTM endpoints, and continues tracking without interruption.

3. **Given** a custom server is configured and working, **When** the server goes offline after initial configuration, **Then** the system detects the failure during a periodic health check, automatically switches to Google's default endpoints, and logs the failure with a timestamp for administrator review.

4. **Given** a custom server previously failed health checks and the system is using the default fallback, **When** the custom server becomes available again, **Then** the system detects availability during a periodic health check, switches back to the custom server, and logs the recovery with a timestamp.

5. **Given** an administrator wants to monitor server status, **When** they view the plugin settings page, **Then** they see the current server status (custom/fallback), the timestamp of the most recent health check, and the result (success/failure).

6. **Given** the system is performing a health check, **When** the custom server responds but serves invalid or incomplete GTM scripts, **Then** the system treats this as a failure, falls back to default endpoints, and logs the validation failure.

### Edge Cases

- **What happens when** the administrator enters a malformed URL (missing protocol, invalid domain format)?

  - System should validate URL format before attempting health check and display clear error message.

- **What happens when** the custom server is intermittently available (works sometimes, fails other times)?

  - System should require 3 consecutive successful health checks before switching back to the custom server to prevent rapid switching.

- **What happens when** the health check itself times out?

  - System should treat timeout as failure and use configured 5-second timeout before giving up.

- **What happens when** Google's default endpoints are also unavailable?

  - System should display a critical error message to administrators and log the failure for diagnostic purposes.

- **What happens when** multiple administrators on the same site configure different custom servers?
  - System should use last-save-wins approach (most recent configuration takes precedence).

## Requirements

### Functional Requirements

- **FR-001**: System MUST provide a configuration field for administrators to enter a custom GTM server domain URL

- **FR-002**: System MUST validate custom server URL format before accepting configuration (protocol, domain structure)

- **FR-003**: System MUST asynchronously test whether the custom server serves valid GTM container scripts (HTTP 200 response with parseable JavaScript) before activating the custom domain

- **FR-004**: System MUST automatically fall back to Google's default GTM endpoints if custom server validation fails

- **FR-005**: System MUST continue tracking without interruption when falling back from custom to default endpoints

- **FR-006**: System MUST perform periodic background health checks of configured custom servers every 6 hours (4 times daily) in both admin area and during frontend page loads

- **FR-007**: System MUST store the timestamp of the most recent health check in persistent storage

- **FR-008**: System MUST store the result (success/failure) of the most recent health check in persistent storage

- **FR-009**: System MUST automatically switch from default to custom endpoints when a previously-failing server passes 3 consecutive health checks

- **FR-010**: System MUST automatically switch from custom to default endpoints when a previously-working server fails health checks

- **FR-011**: System MUST display current server status (custom active, fallback active) to administrators in plugin settings

- **FR-012**: System MUST display the timestamp and result of the most recent health check to administrators

- **FR-013**: System MUST time out health checks after 5 seconds

- **FR-014**: System MUST treat script validation failures (server responds but JavaScript is invalid/unparseable) as health check failures

- **FR-015**: Administrators MUST be able to manually trigger a health check from the settings interface

- **FR-016**: System MUST preserve health check history across page loads and browser sessions

- **FR-017**: System MUST display WordPress admin notices when server status changes (custom server becomes available/unavailable)

### Key Entities

- **Custom Server Configuration**: Represents the administrator's custom GTM server settings, including the server URL, enabled/disabled status, and whether the server is currently active or in fallback mode

- **Health Check Result**: Represents the outcome of a single health check attempt, including timestamp, success/failure status, error details (if failed), and response time

---

## Review & Acceptance Checklist

### Content Quality

- [x] No implementation details (languages, frameworks, APIs)
- [x] Focused on user value and business needs
- [x] Written for non-technical stakeholders
- [x] All mandatory sections completed

### Requirement Completeness

- [x] No [NEEDS CLARIFICATION] markers remain (all resolved)
- [x] Requirements are testable and unambiguous (all clarified)
- [x] Success criteria are measurable (timeout/interval specifications defined)
- [x] Scope is clearly bounded
- [x] Dependencies and assumptions identified

---

## Execution Status

- [x] User description parsed
- [x] Key concepts extracted
- [x] Ambiguities marked (5 clarification points)
- [x] User scenarios defined
- [x] Requirements generated
- [x] Entities identified
- [x] Review checklist passed (all clarifications resolved)

---
