# GA4 Conventional Event Naming & Lead Lifecycle

Adopt GA4 recommended lead generation event naming and extend the plugin to support the full lead lifecycle funnel.

## 1. Event Name Realignment

### Current to New Mapping

| Current Event | New Event | Change |
|---|---|---|
| `form_submit` | `form_submit` | No change |
| `generate_lead` (email+phone+click_id) | `qualify_lead` | Renamed — this logic was always qualification |
| *(new)* | `generate_lead` | New — fires on any form submission with a valid email |
| `phone_click` | `phone_click` | No change |
| `email_click` | `email_click` | No change |
| `click_id_detected` | `click_id_detected` | No change (internal) |
| `status_qualified` (webhook) | `qualify_lead` | Merged — same event whether client or webhook triggered |
| `score_updated` (webhook) | `score_updated` | No change |

### Firing Order on Form Submission

1. `form_submit` — always (every submission)
2. `generate_lead` — if submission contains a valid email
3. `qualify_lead` — if submission contains email + phone + click_id

### GA4 Lead Generation Funnel (Full)

| GA4 Event | Trigger |
|---|---|
| `generate_lead` | User submits a form with an email address |
| `qualify_lead` | Lead meets criteria (email + phone + click_id), or marked qualified via webhook |
| `disqualify_lead` | Lead marked as disqualified via webhook |
| `working_lead` | Lead contacted by a rep via webhook |
| `close_convert_lead` | Lead became a customer via webhook |
| `close_unconvert_lead` | Lead closed without converting via webhook |

## 2. Admin Settings for Secrets

### New Settings

| Setting | Option Key | Description |
|---|---|---|
| Registration Secret | `cuft_register_secret` | Authenticates with validator service |
| GA4 Measurement ID | `cuft_measurement_id` | For Measurement Protocol (e.g., `G-XXXXXXX`) |
| GA4 API Secret | `cuft_measurement_api_secret` | Measurement Protocol authentication |

### Behavior

- `wp-config.php` constants still work as overrides — if `CUFT_REGISTER_SECRET` is defined, it takes precedence over the DB option.
- Admin UI shows a masked field when a `wp-config.php` constant is active, with a note explaining the override.
- Secrets stored encrypted in the DB using WordPress's `AUTH_SALT`.

## 3. Webhook Extension

### Current Contract

```
GET /cuft-webhook/?click_id=abc&qualified=1&score=85
```

### New Contract — `status` Parameter

```
GET /cuft-webhook/?click_id=abc&status=qualify_lead
GET /cuft-webhook/?click_id=abc&status=disqualify_lead
GET /cuft-webhook/?click_id=abc&status=working_lead
GET /cuft-webhook/?click_id=abc&status=close_convert_lead
GET /cuft-webhook/?click_id=abc&status=close_unconvert_lead
```

### Valid Status Values

| Status | Description |
|---|---|
| `qualify_lead` | Lead meets qualification criteria |
| `disqualify_lead` | Lead disqualified |
| `working_lead` | Rep is actively working the lead |
| `close_convert_lead` | Lead converted to customer |
| `close_unconvert_lead` | Lead closed without converting |

### Backward Compatibility

- `qualified=1` still works — internally mapped to `status=qualify_lead`.
- `score` parameter still works alongside any `status` value.
- If both `qualified=1` and `status=disqualify_lead` are sent, `status` wins (explicit takes precedence).
- Unknown status values return `400 Bad Request` with an error listing valid options.

### Event Recording

Each webhook call with a valid `status` records an event in the clicks event table (same mechanism as current `status_qualified` and `score_updated`).

## 4. Measurement Protocol (Server-Side)

### When

At webhook time, immediately after recording the event in the DB.

### What Fires

The same GA4 event name as the `status` parameter. A `qualify_lead` webhook pushes a `qualify_lead` event to GA4 via Measurement Protocol.

### Payload

```json
POST https://www.google-analytics.com/mp/collect?measurement_id={cuft_measurement_id}&api_secret={cuft_measurement_api_secret}

{
  "client_id": "<ga_client_id from clicks table>",
  "events": [{
    "name": "qualify_lead",
    "params": {
      "click_id": "abc123",
      "lead_source": "gravity_forms",
      "lead_value": 100,
      "lead_currency": "CAD",
      "engagement_time_msec": 1
    }
  }]
}
```

### Requirements

- **New DB column:** `ga_client_id` on the clicks table — captured from the `_ga` cookie at form submission time.
- **Graceful fallback:** If Measurement ID or API Secret aren't configured, webhook still works normally (no MP fire), logs a debug notice.
- **Fires for:** All webhook-driven lifecycle events (`qualify_lead` through `close_unconvert_lead`). `generate_lead` and client-side `qualify_lead` fire via dataLayer only (not MP) since they happen in the browser at form submission time.
- **Value params:** Uses existing `cuft_lead_value` and `cuft_lead_currency` options.

## 5. Client-Side Event Queue (Pageview Replay)

### Mechanism

1. **At webhook time:** After recording the event, queue it with `replayed_at = NULL`.
2. **On pageview:** JS reads `cuft_click_id` cookie. If present, AJAX call asks the server for pending events for that click_id.
3. **Server responds** with unreplayed events.
4. **JS pushes each to dataLayer:**

```javascript
dataLayer.push({
  event: 'qualify_lead',
  click_id: 'abc123',
  cuft_tracked: true,
  cuft_source: 'webhook_replay',
  cuft_replayed: true
});
```

5. **Server marks events as replayed** (sets `replayed_at` timestamp).

### Design Decisions

- Events replay once only — marked after push.
- `cuft_replayed: true` lets GTM distinguish real-time from queued events.
- `cuft_source: 'webhook_replay'` distinguishes from form-triggered events.
- No extra overhead on normal pageviews — AJAX only fires when click_id cookie exists, returns empty if no pending events.
- No PHP sessions — cookie-based only (consistent with session removal fix).
- Pending events expire after 30 days (cleanup via existing DB optimizer cron).

### DB Change

Add `replayed_at` (nullable datetime) column to the existing click events table. Pending = `replayed_at IS NULL` for webhook-originated events.

## 6. Dual-Fire Deprecation Strategy

### The Breaking Change

Current `generate_lead` means "email + phone + click_id." After this change, `generate_lead` means "any form with email" and `qualify_lead` takes over the strict meaning.

### Transition (1 Version)

During the transition version, when a form submission meets the strict criteria (email + phone + click_id), the plugin fires:

1. `form_submit` — always
2. `generate_lead` — new broad meaning (has email)
3. `qualify_lead` — new name for strict logic
4. `generate_lead` (duplicate) — with `cuft_deprecated: true` and the old strict payload

```javascript
dataLayer.push({
  event: 'generate_lead',
  cuft_tracked: true,
  cuft_deprecated: true,
  cuft_migrate_to: 'qualify_lead',
  // ... old strict payload
});
```

### Console Warning

When `cuft_console_logging` is enabled:

```
[CUFT] "generate_lead" with strict criteria is deprecated. Update your GTM trigger to use "qualify_lead" instead.
```

### Next Version

Remove the dual-fire. `generate_lead` only fires with the new broad meaning.
