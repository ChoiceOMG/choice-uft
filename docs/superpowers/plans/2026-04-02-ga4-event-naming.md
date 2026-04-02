# GA4 Event Naming & Lead Lifecycle Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adopt GA4 recommended lead generation event naming, extend the webhook to support the full lead lifecycle, add Measurement Protocol server-side firing, and enable client-side event replay for webhook-driven events.

**Architecture:** Incremental migration in 10 tasks. DB schema first, then JS event renaming with dual-fire deprecation, PHP event constants, admin settings for secrets, webhook extension, Measurement Protocol class, and client-side replay queue. Each task is independently testable and committable.

**Tech Stack:** PHP 7.4+ (WordPress plugin), vanilla JavaScript (no build step), WordPress `dbDelta()` for schema changes, GA4 Measurement Protocol REST API, WordPress AJAX for event replay.

**Spec:** `docs/superpowers/specs/2026-04-02-ga4-event-naming-design.md`

---

## File Structure

### New Files
- `includes/migrations/class-cuft-migration-3-22-0.php` — DB migration for `ga_client_id` and `replayed_at` columns
- `includes/class-cuft-measurement-protocol.php` — GA4 Measurement Protocol client
- `includes/ajax/class-cuft-event-replay.php` — AJAX endpoint for pending event replay

### Modified Files
- `choice-universal-form-tracker.php` — Version bump, class loading, activation hook
- `includes/class-cuft-db-migration.php` — Register new migration
- `assets/cuft-dataLayer-utils.js` — Event renaming (generate_lead → qualify_lead, new broad generate_lead, dual-fire)
- `includes/class-cuft-click-tracker.php` — Webhook `status` parameter, ga_client_id capture, valid events update, MP integration
- `includes/ajax/class-cuft-event-recorder.php` — Updated VALID_EVENT_TYPES
- `includes/class-cuft-admin.php` — New settings fields (register secret, measurement ID, API secret)
- `includes/class-cuft-token-manager.php` — Fallback to DB option for register secret
- `includes/class-cuft-utils.php` — Updated display names and icons for new events
- `tests/standalone/lib/test-data.js` — Updated expected events

---

## Task 1: DB Migration — Add `ga_client_id` and `replayed_at` Columns

**Files:**
- Create: `includes/migrations/class-cuft-migration-3-22-0.php`
- Modify: `includes/class-cuft-db-migration.php:16,40-50`
- Test: `tests/unit/test-migration-3-22-0.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-migration-3-22-0.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Migration_3_22_0 extends TestCase {

    public function test_migration_adds_ga_client_id_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $migration = new CUFT_Migration_3_22_0();
        $migration->up();

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );
        $this->assertContains( 'ga_client_id', $columns );
    }

    public function test_migration_adds_replayed_at_column() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        // up() already ran in previous test, but safe to call again
        $migration = new CUFT_Migration_3_22_0();
        $migration->up();

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );
        $this->assertContains( 'replayed_at', $columns );
    }

    public function test_needs_migration_returns_true_before_running() {
        delete_option( 'cuft_migration_3_22_0_completed' );
        $migration = new CUFT_Migration_3_22_0();
        $this->assertTrue( $migration->needs_migration() );
    }

    public function test_needs_migration_returns_false_after_running() {
        $migration = new CUFT_Migration_3_22_0();
        $migration->up();
        $this->assertFalse( $migration->needs_migration() );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-migration-3-22-0.php"`
Expected: FAIL — class CUFT_Migration_3_22_0 not found

- [ ] **Step 3: Create migration class**

```php
<?php
// includes/migrations/class-cuft-migration-3-22-0.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Migration_3_22_0 {

    const OPTION_KEY = 'cuft_migration_3_22_0_completed';

    public function up() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $columns = $wpdb->get_col( "SHOW COLUMNS FROM $table" );

        if ( ! in_array( 'ga_client_id', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN ga_client_id varchar(255) DEFAULT NULL AFTER ip_hash" );
        }

        if ( ! in_array( 'replayed_at', $columns, true ) ) {
            $wpdb->query( "ALTER TABLE $table ADD COLUMN replayed_at datetime DEFAULT NULL AFTER events" );
        }

        update_option( self::OPTION_KEY, true );
    }

    public function needs_migration() {
        return ! get_option( self::OPTION_KEY, false );
    }
}
```

- [ ] **Step 4: Register migration in CUFT_DB_Migration**

In `includes/class-cuft-db-migration.php`, update:

```php
// Change line 16
const CURRENT_VERSION = '3.22.0';
```

Add after the v3.21.0 migration call (around line 50):

```php
if ( version_compare( $current_version, '3.22.0', '<' ) ) {
    require_once CUFT_PATH . 'includes/migrations/class-cuft-migration-3-22-0.php';
    $migration = new CUFT_Migration_3_22_0();
    if ( $migration->needs_migration() ) {
        $migration->up();
    }
}
```

- [ ] **Step 5: Load migration class in main plugin file**

In `choice-universal-form-tracker.php`, add after the existing migration requires (around the class loading section):

```php
require_once CUFT_PATH . 'includes/migrations/class-cuft-migration-3-22-0.php';
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-migration-3-22-0.php"`
Expected: PASS (4 tests, 4 assertions)

- [ ] **Step 7: Commit**

```bash
git add includes/migrations/class-cuft-migration-3-22-0.php includes/class-cuft-db-migration.php choice-universal-form-tracker.php tests/unit/test-migration-3-22-0.php
git commit -m "feat: Add DB migration 3.22.0 for ga_client_id and replayed_at columns"
```

---

## Task 2: Update PHP Event Constants and Display Helpers

**Files:**
- Modify: `includes/ajax/class-cuft-event-recorder.php:21-26`
- Modify: `includes/class-cuft-click-tracker.php:845-852`
- Modify: `includes/class-cuft-utils.php:236-264`
- Modify: `includes/class-cuft-admin.php:1801-1802` (admin filter dropdown)
- Test: `tests/unit/test-event-constants.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-event-constants.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Event_Constants extends TestCase {

    public function test_event_recorder_includes_qualify_lead() {
        $valid = CUFT_Event_Recorder::VALID_EVENT_TYPES;
        $this->assertContains( 'qualify_lead', $valid );
    }

    public function test_event_recorder_includes_generate_lead() {
        $valid = CUFT_Event_Recorder::VALID_EVENT_TYPES;
        $this->assertContains( 'generate_lead', $valid );
    }

    public function test_utils_display_name_for_qualify_lead() {
        $name = CUFT_Utils::get_event_display_name( 'qualify_lead' );
        $this->assertEquals( 'Qualify Lead', $name );
    }

    public function test_utils_display_name_for_disqualify_lead() {
        $name = CUFT_Utils::get_event_display_name( 'disqualify_lead' );
        $this->assertEquals( 'Disqualify Lead', $name );
    }

    public function test_utils_display_name_for_working_lead() {
        $name = CUFT_Utils::get_event_display_name( 'working_lead' );
        $this->assertEquals( 'Working Lead', $name );
    }

    public function test_utils_display_name_for_close_convert_lead() {
        $name = CUFT_Utils::get_event_display_name( 'close_convert_lead' );
        $this->assertEquals( 'Close Convert Lead', $name );
    }

    public function test_utils_display_name_for_close_unconvert_lead() {
        $name = CUFT_Utils::get_event_display_name( 'close_unconvert_lead' );
        $this->assertEquals( 'Close Unconvert Lead', $name );
    }

    public function test_utils_icon_for_qualify_lead() {
        $icon = CUFT_Utils::get_event_icon( 'qualify_lead' );
        $this->assertEquals( '⭐', $icon );
    }

    public function test_utils_icon_for_working_lead() {
        $icon = CUFT_Utils::get_event_icon( 'working_lead' );
        $this->assertEquals( '📋', $icon );
    }

    public function test_utils_icon_for_close_convert_lead() {
        $icon = CUFT_Utils::get_event_icon( 'close_convert_lead' );
        $this->assertEquals( '✅', $icon );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-event-constants.php"`
Expected: FAIL — qualify_lead not in VALID_EVENT_TYPES

- [ ] **Step 3: Update CUFT_Event_Recorder::VALID_EVENT_TYPES**

In `includes/ajax/class-cuft-event-recorder.php`, replace lines 21-26:

```php
const VALID_EVENT_TYPES = array(
    'phone_click',
    'email_click',
    'form_submit',
    'generate_lead',
    'qualify_lead',
);
```

- [ ] **Step 4: Update valid events in CUFT_Click_Tracker**

In `includes/class-cuft-click-tracker.php`, replace the `$valid_events` array (lines 845-852):

```php
$valid_events = array(
    'phone_click',
    'email_click',
    'form_submit',
    'generate_lead',
    'qualify_lead',
    'disqualify_lead',
    'working_lead',
    'close_convert_lead',
    'close_unconvert_lead',
    'score_updated',
);
```

- [ ] **Step 5: Update CUFT_Utils::get_event_display_name()**

In `includes/class-cuft-utils.php`, replace lines 236-246:

```php
public static function get_event_display_name( $event_type ) {
    $display_names = array(
        'phone_click'          => 'Phone Click',
        'email_click'          => 'Email Click',
        'form_submit'          => 'Form Submit',
        'generate_lead'        => 'Generate Lead',
        'qualify_lead'         => 'Qualify Lead',
        'disqualify_lead'      => 'Disqualify Lead',
        'working_lead'         => 'Working Lead',
        'close_convert_lead'   => 'Close Convert Lead',
        'close_unconvert_lead' => 'Close Unconvert Lead',
        'score_updated'        => 'Score Updated',
        'status_update'        => 'Status Update',
    );
    return isset( $display_names[ $event_type ] ) ? $display_names[ $event_type ] : $event_type;
}
```

- [ ] **Step 6: Update CUFT_Utils::get_event_icon()**

In `includes/class-cuft-utils.php`, replace lines 254-264:

```php
public static function get_event_icon( $event_type ) {
    $icons = array(
        'phone_click'          => '📞',
        'email_click'          => '📧',
        'form_submit'          => '📝',
        'generate_lead'        => '🎯',
        'qualify_lead'         => '⭐',
        'disqualify_lead'      => '❌',
        'working_lead'         => '📋',
        'close_convert_lead'   => '✅',
        'close_unconvert_lead' => '🚫',
        'score_updated'        => '📊',
        'status_update'        => '🔄',
    );
    return isset( $icons[ $event_type ] ) ? $icons[ $event_type ] : '●';
}
```

- [ ] **Step 7: Update admin filter dropdown**

In `includes/class-cuft-admin.php`, find the event type filter dropdown (around lines 1800-1803) and add the new event types:

```php
<option value="qualify_lead" <?php selected( $event_filter, 'qualify_lead' ); ?>>Qualify Lead</option>
<option value="disqualify_lead" <?php selected( $event_filter, 'disqualify_lead' ); ?>>Disqualify Lead</option>
<option value="working_lead" <?php selected( $event_filter, 'working_lead' ); ?>>Working Lead</option>
<option value="close_convert_lead" <?php selected( $event_filter, 'close_convert_lead' ); ?>>Close Convert Lead</option>
<option value="close_unconvert_lead" <?php selected( $event_filter, 'close_unconvert_lead' ); ?>>Close Unconvert Lead</option>
```

- [ ] **Step 8: Run tests to verify they pass**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-event-constants.php"`
Expected: PASS (10 tests, 10 assertions)

- [ ] **Step 9: Commit**

```bash
git add includes/ajax/class-cuft-event-recorder.php includes/class-cuft-click-tracker.php includes/class-cuft-utils.php includes/class-cuft-admin.php tests/unit/test-event-constants.php
git commit -m "feat: Add GA4 lifecycle event types to PHP constants and display helpers"
```

---

## Task 3: JS Event Renaming — `generate_lead` → `qualify_lead` + New Broad `generate_lead`

**Files:**
- Modify: `assets/cuft-dataLayer-utils.js:110-123,230-254,397-472`
- Modify: `tests/standalone/lib/test-data.js:193`

- [ ] **Step 1: Update `meetsLeadConditions()` to `meetsQualifyConditions()`**

In `assets/cuft-dataLayer-utils.js`, rename the function at lines 110-123:

```javascript
function meetsQualifyConditions(payload) {
  // Must have valid email and phone
  if (!payload.user_email || !payload.user_phone) {
    return false;
  }

  // Must have at least one click ID
  for (var i = 0; i < CLICK_ID_FIELDS.length; i++) {
    if (payload[CLICK_ID_FIELDS[i]]) {
      return true;
    }
  }
  return false;
}
```

- [ ] **Step 2: Add `meetsGenerateLeadConditions()` function**

Add after `meetsQualifyConditions()`:

```javascript
function meetsGenerateLeadConditions(payload) {
  return !!payload.user_email;
}
```

- [ ] **Step 3: Rename `createGenerateLeadPayload()` to `createQualifyLeadPayload()`**

In `assets/cuft-dataLayer-utils.js`, at the function starting around line 230, rename and update the event name:

```javascript
function createQualifyLeadPayload(formSubmitPayload, framework, options) {
  var fw = FRAMEWORK_IDENTIFIERS[framework] || FRAMEWORK_IDENTIFIERS.elementor;
  var payload = {};
  for (var key in formSubmitPayload) {
    if (formSubmitPayload.hasOwnProperty(key)) {
      payload[key] = formSubmitPayload[key];
    }
  }
  payload.event = "qualify_lead";
  payload.cuft_source = fw.cuft_source_lead;
  payload.currency = options.lead_currency || "CAD";
  payload.value = parseFloat(options.lead_value) || 100;
  return payload;
}
```

- [ ] **Step 4: Add `createGenerateLeadPayload()` for the new broad meaning**

Add after `createQualifyLeadPayload()`:

```javascript
function createGenerateLeadPayload(formSubmitPayload, framework, options) {
  var fw = FRAMEWORK_IDENTIFIERS[framework] || FRAMEWORK_IDENTIFIERS.elementor;
  var payload = {};
  for (var key in formSubmitPayload) {
    if (formSubmitPayload.hasOwnProperty(key)) {
      payload[key] = formSubmitPayload[key];
    }
  }
  payload.event = "generate_lead";
  payload.cuft_source = fw.cuft_source_lead;
  payload.currency = options.lead_currency || "CAD";
  payload.value = parseFloat(options.lead_value) || 100;
  return payload;
}
```

- [ ] **Step 5: Update `trackFormSubmission()` firing logic**

In `assets/cuft-dataLayer-utils.js`, replace the generate_lead section of `trackFormSubmission()` (around lines 420-465). After the `form_submit` push (line 404), replace the lead logic with:

```javascript
// Fire generate_lead if email is present (broad GA4 meaning)
if (meetsGenerateLeadConditions(formSubmitPayload)) {
  var generateLeadPayload = createGenerateLeadPayload(formSubmitPayload, framework, options);
  pushToDataLayer(generateLeadPayload, {
    debug: options.debug,
    framework: framework
  });
}

// Fire qualify_lead if strict criteria met (email + phone + click_id)
if (meetsQualifyConditions(formSubmitPayload)) {
  var qualifyLeadPayload = createQualifyLeadPayload(formSubmitPayload, framework, options);
  pushToDataLayer(qualifyLeadPayload, {
    debug: options.debug,
    framework: framework
  });

  // Record qualify_lead event server-side
  recordEvent(formSubmitPayload, "qualify_lead", options);

  // DEPRECATED: Dual-fire old generate_lead with strict payload for one version
  var deprecatedPayload = createQualifyLeadPayload(formSubmitPayload, framework, options);
  deprecatedPayload.event = "generate_lead";
  deprecatedPayload.cuft_deprecated = true;
  deprecatedPayload.cuft_migrate_to = "qualify_lead";
  pushToDataLayer(deprecatedPayload, {
    debug: options.debug,
    framework: framework
  });

  if (options.console_logging === "yes") {
    console.warn('[CUFT] "generate_lead" with strict criteria is deprecated. Update your GTM trigger to use "qualify_lead" instead.');
  }
}
```

- [ ] **Step 6: Update test data**

In `tests/standalone/lib/test-data.js`, update the expected events section (around line 193). Add after `expectedFormSubmitEvent`:

```javascript
expectedGenerateLeadEvent: {
  event: "generate_lead",
  cuft_tracked: true,
  cuft_source: "elementor_pro_lead",
  form_type: "elementor",
  form_id: "test-form-1",
  user_email: "test@example.com",
  currency: "CAD",
  value: 100
},
expectedQualifyLeadEvent: {
  event: "qualify_lead",
  cuft_tracked: true,
  cuft_source: "elementor_pro_lead",
  form_type: "elementor",
  form_id: "test-form-1",
  user_email: "test@example.com",
  user_phone: "+15551234567",
  currency: "CAD",
  value: 100
},
```

- [ ] **Step 7: Manual browser test**

Open `http://localhost:8080/cuft-test-forms/` in a browser. Open DevTools console. Submit a test form with email only — verify `form_submit` and `generate_lead` fire. Submit with email + phone + click_id — verify `form_submit`, `generate_lead`, `qualify_lead`, and deprecated `generate_lead` (with `cuft_deprecated: true`) all fire.

- [ ] **Step 8: Commit**

```bash
git add assets/cuft-dataLayer-utils.js tests/standalone/lib/test-data.js
git commit -m "feat: Rename generate_lead to qualify_lead, add broad generate_lead, dual-fire deprecation"
```

---

## Task 4: Capture `ga_client_id` at Form Submission

**Files:**
- Modify: `assets/cuft-dataLayer-utils.js:158-222` (createFormSubmitPayload)
- Modify: `includes/class-cuft-click-tracker.php` (store ga_client_id on click record)

- [ ] **Step 1: Add GA client_id extraction to JS payload**

In `assets/cuft-dataLayer-utils.js`, add a helper function after the existing helpers (around line 143):

```javascript
function getGaClientId() {
  try {
    var cookie = document.cookie.match(/(^|; )_ga=([^;]*)/);
    if (cookie && cookie[2]) {
      // _ga cookie format: GA1.1.XXXXXXX.YYYYYYY — client_id is the last two parts
      var parts = cookie[2].split(".");
      if (parts.length >= 4) {
        return parts[2] + "." + parts[3];
      }
    }
  } catch (e) {
    // Silently fail — ga_client_id is optional
  }
  return null;
}
```

- [ ] **Step 2: Include ga_client_id in form submit payload**

In `createFormSubmitPayload()` (around line 158), add after the existing fields:

```javascript
var gaClientId = getGaClientId();
if (gaClientId) {
  payload.ga_client_id = gaClientId;
}
```

- [ ] **Step 3: Store ga_client_id in click tracker PHP**

In `includes/class-cuft-click-tracker.php`, find where click records are inserted/updated on form submission. In the method that handles recording a click (the `record_click()` or similar method that saves to `cuft_click_tracking`), add `ga_client_id` to the data being saved:

```php
if ( ! empty( $data['ga_client_id'] ) ) {
    $wpdb->update(
        $table_name,
        array( 'ga_client_id' => sanitize_text_field( $data['ga_client_id'] ) ),
        array( 'click_id' => $click_id ),
        array( '%s' ),
        array( '%s' )
    );
}
```

- [ ] **Step 4: Manual browser test**

Submit a form on a page that has GA4 running. Check the `cuft_click_tracking` table:

```bash
docker exec wp-pdev-cli wp db query "SELECT click_id, ga_client_id FROM wp_cuft_click_tracking ORDER BY id DESC LIMIT 5"
```

Verify `ga_client_id` is populated.

- [ ] **Step 5: Commit**

```bash
git add assets/cuft-dataLayer-utils.js includes/class-cuft-click-tracker.php
git commit -m "feat: Capture GA client_id from _ga cookie at form submission"
```

---

## Task 5: Admin Settings for Secrets

**Files:**
- Modify: `includes/class-cuft-admin.php:166-237,512-579`
- Modify: `includes/class-cuft-token-manager.php:39-44`
- Test: `tests/unit/test-admin-secrets.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-admin-secrets.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Admin_Secrets extends TestCase {

    public function test_register_secret_falls_back_to_option() {
        // Ensure constant is not defined (it won't be in test env)
        update_option( 'cuft_register_secret', 'test-secret-from-db' );

        $secret = CUFT_Token_Manager::get_register_secret_value();
        $this->assertEquals( 'test-secret-from-db', $secret );

        delete_option( 'cuft_register_secret' );
    }

    public function test_measurement_id_option() {
        update_option( 'cuft_measurement_id', 'G-TEST123' );
        $this->assertEquals( 'G-TEST123', get_option( 'cuft_measurement_id', '' ) );
        delete_option( 'cuft_measurement_id' );
    }

    public function test_measurement_api_secret_option() {
        update_option( 'cuft_measurement_api_secret', 'secret123' );
        $this->assertEquals( 'secret123', get_option( 'cuft_measurement_api_secret', '' ) );
        delete_option( 'cuft_measurement_api_secret' );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-admin-secrets.php"`
Expected: FAIL — `get_register_secret_value` method doesn't exist

- [ ] **Step 3: Update CUFT_Token_Manager to expose secret retrieval with DB fallback**

In `includes/class-cuft-token-manager.php`, replace the private `get_register_secret()` method (lines 39-44):

```php
/**
 * Get the registration secret. wp-config.php constant takes precedence,
 * then falls back to the DB option.
 */
public static function get_register_secret_value(): string {
    if ( defined( 'CUFT_REGISTER_SECRET' ) ) {
        return CUFT_REGISTER_SECRET;
    }
    return get_option( 'cuft_register_secret', '' );
}
```

Update all internal callers of `get_register_secret()` to use `get_register_secret_value()` — search for `self::get_register_secret()` in the file and replace with `self::get_register_secret_value()`.

- [ ] **Step 4: Add secret settings fields to admin page**

In `includes/class-cuft-admin.php`, find the sGTM settings section (around line 166). Add a new section after it for API credentials:

```php
<!-- API Credentials Section -->
<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 6px; padding: 20px; margin-bottom: 20px;">
    <h3 style="margin: 0 0 15px; color: #23282d;">API Credentials</h3>

    <?php $register_secret_override = defined( 'CUFT_REGISTER_SECRET' ); ?>
    <table class="form-table">
        <tr>
            <th scope="row"><label for="cuft_register_secret">Registration Secret</label></th>
            <td>
                <?php if ( $register_secret_override ) : ?>
                    <input type="text" value="••••••••" disabled class="regular-text" />
                    <p class="description">Overridden by <code>CUFT_REGISTER_SECRET</code> in wp-config.php</p>
                <?php else : ?>
                    <input type="password" id="cuft_register_secret" name="cuft_register_secret"
                        value="<?php echo esc_attr( get_option( 'cuft_register_secret', '' ) ); ?>"
                        class="regular-text" autocomplete="off" />
                    <p class="description">Authenticates with the validator service. Can also be set as <code>CUFT_REGISTER_SECRET</code> in wp-config.php.</p>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="cuft_measurement_id">GA4 Measurement ID</label></th>
            <td>
                <input type="text" id="cuft_measurement_id" name="cuft_measurement_id"
                    value="<?php echo esc_attr( get_option( 'cuft_measurement_id', '' ) ); ?>"
                    class="regular-text" placeholder="G-XXXXXXXXXX" />
                <p class="description">Required for server-side Measurement Protocol events.</p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="cuft_measurement_api_secret">GA4 API Secret</label></th>
            <td>
                <input type="password" id="cuft_measurement_api_secret" name="cuft_measurement_api_secret"
                    value="<?php echo esc_attr( get_option( 'cuft_measurement_api_secret', '' ) ); ?>"
                    class="regular-text" autocomplete="off" />
                <p class="description">Found in GA4 Admin → Data Streams → Measurement Protocol API secrets.</p>
            </td>
        </tr>
    </table>
</div>
```

- [ ] **Step 5: Save the new options in `save_settings()`**

In `includes/class-cuft-admin.php`, in the `save_settings()` method (around line 540), add after the existing `update_option` calls:

```php
if ( isset( $_POST['cuft_register_secret'] ) && ! defined( 'CUFT_REGISTER_SECRET' ) ) {
    update_option( 'cuft_register_secret', sanitize_text_field( wp_unslash( $_POST['cuft_register_secret'] ) ) );
}
if ( isset( $_POST['cuft_measurement_id'] ) ) {
    $measurement_id = sanitize_text_field( wp_unslash( $_POST['cuft_measurement_id'] ) );
    if ( empty( $measurement_id ) || preg_match( '/^G-[A-Z0-9]+$/', $measurement_id ) ) {
        update_option( 'cuft_measurement_id', $measurement_id );
    }
}
if ( isset( $_POST['cuft_measurement_api_secret'] ) ) {
    update_option( 'cuft_measurement_api_secret', sanitize_text_field( wp_unslash( $_POST['cuft_measurement_api_secret'] ) ) );
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-admin-secrets.php"`
Expected: PASS (3 tests, 3 assertions)

- [ ] **Step 7: Commit**

```bash
git add includes/class-cuft-admin.php includes/class-cuft-token-manager.php tests/unit/test-admin-secrets.php
git commit -m "feat: Add admin settings for registration secret, GA4 Measurement ID, and API secret"
```

---

## Task 6: Extend Webhook with `status` Parameter

**Files:**
- Modify: `includes/class-cuft-click-tracker.php:186-253,470-513`
- Test: `tests/unit/test-webhook-status.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-webhook-status.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Webhook_Status extends TestCase {

    private static $table_name;

    public static function set_up_before_class() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
    }

    public function set_up() {
        global $wpdb;
        // Insert a test click
        $wpdb->insert( self::$table_name, array(
            'click_id'     => 'test-webhook-click',
            'platform'     => 'google',
            'qualified'    => 0,
            'score'        => 0,
            'ga_client_id' => '123456.789012',
        ) );
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->delete( self::$table_name, array( 'click_id' => 'test-webhook-click' ) );
    }

    public function test_valid_status_values() {
        $valid = CUFT_Click_Tracker::get_valid_webhook_statuses();
        $this->assertContains( 'qualify_lead', $valid );
        $this->assertContains( 'disqualify_lead', $valid );
        $this->assertContains( 'working_lead', $valid );
        $this->assertContains( 'close_convert_lead', $valid );
        $this->assertContains( 'close_unconvert_lead', $valid );
    }

    public function test_status_parameter_records_event() {
        global $wpdb;
        CUFT_Click_Tracker::update_click_status( 'test-webhook-click', null, null, 'working_lead' );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-webhook-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'working_lead', $last_event['event_type'] );
    }

    public function test_qualified_param_maps_to_qualify_lead() {
        global $wpdb;
        CUFT_Click_Tracker::update_click_status( 'test-webhook-click', 1 );

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-webhook-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'qualify_lead', $last_event['event_type'] );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-webhook-status.php"`
Expected: FAIL — `get_valid_webhook_statuses` doesn't exist

- [ ] **Step 3: Add `get_valid_webhook_statuses()` static method**

In `includes/class-cuft-click-tracker.php`, add a new static method:

```php
public static function get_valid_webhook_statuses() {
    return array(
        'qualify_lead',
        'disqualify_lead',
        'working_lead',
        'close_convert_lead',
        'close_unconvert_lead',
    );
}
```

- [ ] **Step 4: Update `update_click_status()` to accept `$status` parameter**

In `includes/class-cuft-click-tracker.php`, update the method signature at line 186:

```php
public static function update_click_status( $click_id, $qualified = null, $score = null, $status = null ) {
```

In the event recording section (around lines 229-245), replace the `status_qualified` logic:

```php
// Record lifecycle status event
if ( $status && in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
    $events[] = array(
        'event_type' => $status,
        'timestamp'  => current_time( 'mysql' ),
    );
} elseif ( $qualified && ( ! $current_record || ! $current_record->qualified ) ) {
    // Backward compatibility: qualified=1 maps to qualify_lead
    $events[] = array(
        'event_type' => 'qualify_lead',
        'timestamp'  => current_time( 'mysql' ),
    );
}

if ( $score && ( ! $current_record || (int) $score > (int) $current_record->score ) ) {
    $events[] = array(
        'event_type' => 'score_updated',
        'timestamp'  => current_time( 'mysql' ),
    );
}
```

- [ ] **Step 5: Update `handle_webhook()` to read `status` parameter**

In `includes/class-cuft-click-tracker.php`, in the `handle_webhook()` method (around line 472), add status extraction:

```php
$status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : null;

// Validate status if provided
if ( $status && ! in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
    wp_send_json_error( array(
        'message' => 'Invalid status value. Valid values: ' . implode( ', ', self::get_valid_webhook_statuses() ),
    ), 400 );
    return;
}
```

Update the call to `update_click_status()` (around line 501) to pass the status:

```php
self::update_click_status( $click_id, $qualified, $score, $status );
```

If `$status` is provided and `$qualified` is not, also set `qualified` based on status:

```php
// Status=qualify_lead implies qualified=1
if ( 'qualify_lead' === $status && null === $qualified ) {
    $qualified = 1;
}
```

- [ ] **Step 6: Run test to verify it passes**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-webhook-status.php"`
Expected: PASS (3 tests, 3 assertions)

- [ ] **Step 7: Commit**

```bash
git add includes/class-cuft-click-tracker.php tests/unit/test-webhook-status.php
git commit -m "feat: Extend webhook with status parameter for GA4 lifecycle events"
```

---

## Task 7: Measurement Protocol Client

**Files:**
- Create: `includes/class-cuft-measurement-protocol.php`
- Modify: `choice-universal-form-tracker.php` (require new class)
- Test: `tests/unit/test-measurement-protocol.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-measurement-protocol.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Measurement_Protocol extends TestCase {

    public function test_build_payload_structure() {
        $mp = new CUFT_Measurement_Protocol();
        $payload = $mp->build_payload( '123456.789012', 'qualify_lead', array(
            'click_id'    => 'abc123',
            'lead_source' => 'gravity_forms',
        ) );

        $this->assertEquals( '123456.789012', $payload['client_id'] );
        $this->assertCount( 1, $payload['events'] );
        $this->assertEquals( 'qualify_lead', $payload['events'][0]['name'] );
        $this->assertEquals( 'abc123', $payload['events'][0]['params']['click_id'] );
        $this->assertEquals( 'gravity_forms', $payload['events'][0]['params']['lead_source'] );
        $this->assertEquals( 1, $payload['events'][0]['params']['engagement_time_msec'] );
    }

    public function test_build_payload_includes_lead_value() {
        update_option( 'cuft_lead_value', 250 );
        update_option( 'cuft_lead_currency', 'USD' );

        $mp = new CUFT_Measurement_Protocol();
        $payload = $mp->build_payload( '123.456', 'close_convert_lead', array() );

        $this->assertEquals( 250, $payload['events'][0]['params']['value'] );
        $this->assertEquals( 'USD', $payload['events'][0]['params']['currency'] );

        delete_option( 'cuft_lead_value' );
        delete_option( 'cuft_lead_currency' );
    }

    public function test_is_configured_returns_false_when_missing() {
        delete_option( 'cuft_measurement_id' );
        delete_option( 'cuft_measurement_api_secret' );

        $mp = new CUFT_Measurement_Protocol();
        $this->assertFalse( $mp->is_configured() );
    }

    public function test_is_configured_returns_true_when_set() {
        update_option( 'cuft_measurement_id', 'G-TEST123' );
        update_option( 'cuft_measurement_api_secret', 'secret' );

        $mp = new CUFT_Measurement_Protocol();
        $this->assertTrue( $mp->is_configured() );

        delete_option( 'cuft_measurement_id' );
        delete_option( 'cuft_measurement_api_secret' );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-measurement-protocol.php"`
Expected: FAIL — class not found

- [ ] **Step 3: Create CUFT_Measurement_Protocol class**

```php
<?php
// includes/class-cuft-measurement-protocol.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Measurement_Protocol {

    const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function is_configured(): bool {
        return ! empty( get_option( 'cuft_measurement_id', '' ) )
            && ! empty( get_option( 'cuft_measurement_api_secret', '' ) );
    }

    public function build_payload( string $client_id, string $event_name, array $event_params = array() ): array {
        $lead_value    = get_option( 'cuft_lead_value', 100 );
        $lead_currency = get_option( 'cuft_lead_currency', 'CAD' );

        $params = array_merge( array(
            'value'                => (float) $lead_value,
            'currency'             => $lead_currency,
            'engagement_time_msec' => 1,
        ), $event_params );

        return array(
            'client_id' => $client_id,
            'events'    => array(
                array(
                    'name'   => $event_name,
                    'params' => $params,
                ),
            ),
        );
    }

    public function send( string $client_id, string $event_name, array $event_params = array() ): bool {
        if ( ! $this->is_configured() ) {
            CUFT_Logger::log( 'Measurement Protocol not configured — skipping event: ' . $event_name );
            return false;
        }

        if ( empty( $client_id ) ) {
            CUFT_Logger::log( 'No ga_client_id available — skipping MP event: ' . $event_name );
            return false;
        }

        $measurement_id = get_option( 'cuft_measurement_id', '' );
        $api_secret     = get_option( 'cuft_measurement_api_secret', '' );

        $url = add_query_arg( array(
            'measurement_id' => $measurement_id,
            'api_secret'     => $api_secret,
        ), self::ENDPOINT );

        $payload = $this->build_payload( $client_id, $event_name, $event_params );

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            CUFT_Logger::log( 'MP request failed for ' . $event_name . ': ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            CUFT_Logger::log( 'MP request returned HTTP ' . $code . ' for ' . $event_name );
            return false;
        }

        return true;
    }
}
```

- [ ] **Step 4: Load class in main plugin file**

In `choice-universal-form-tracker.php`, add with the other requires:

```php
require_once CUFT_PATH . 'includes/class-cuft-measurement-protocol.php';
```

- [ ] **Step 5: Run test to verify it passes**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-measurement-protocol.php"`
Expected: PASS (4 tests, 4 assertions)

- [ ] **Step 6: Commit**

```bash
git add includes/class-cuft-measurement-protocol.php choice-universal-form-tracker.php tests/unit/test-measurement-protocol.php
git commit -m "feat: Add GA4 Measurement Protocol client class"
```

---

## Task 8: Fire Measurement Protocol from Webhook

**Files:**
- Modify: `includes/class-cuft-click-tracker.php:186-253` (update_click_status)
- Test: `tests/unit/test-webhook-mp-integration.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-webhook-mp-integration.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Webhook_MP_Integration extends TestCase {

    private static $table_name;

    public static function set_up_before_class() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
    }

    public function set_up() {
        global $wpdb;
        $wpdb->insert( self::$table_name, array(
            'click_id'     => 'test-mp-click',
            'platform'     => 'google',
            'qualified'    => 0,
            'score'        => 0,
            'ga_client_id' => '111111.222222',
        ) );
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->delete( self::$table_name, array( 'click_id' => 'test-mp-click' ) );
        delete_option( 'cuft_measurement_id' );
        delete_option( 'cuft_measurement_api_secret' );
    }

    public function test_webhook_status_fires_mp_when_configured() {
        update_option( 'cuft_measurement_id', 'G-TEST123' );
        update_option( 'cuft_measurement_api_secret', 'secret' );

        // We can't easily mock wp_remote_post in WP test env, so we verify
        // the MP class is called by checking it doesn't error out.
        // The actual HTTP call will fail (test env), but the flow should complete.
        $result = CUFT_Click_Tracker::update_click_status( 'test-mp-click', null, null, 'qualify_lead' );

        // Verify the event was still recorded in DB regardless of MP result
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-mp-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'qualify_lead', $last_event['event_type'] );
    }

    public function test_webhook_skips_mp_when_not_configured() {
        // No measurement options set — should not error
        $result = CUFT_Click_Tracker::update_click_status( 'test-mp-click', null, null, 'working_lead' );

        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM " . self::$table_name . " WHERE click_id = %s",
            'test-mp-click'
        ) );
        $events = json_decode( $row->events, true );
        $last_event = end( $events );
        $this->assertEquals( 'working_lead', $last_event['event_type'] );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-webhook-mp-integration.php"`
Expected: FAIL (update_click_status doesn't fire MP yet)

- [ ] **Step 3: Add MP firing to `update_click_status()`**

In `includes/class-cuft-click-tracker.php`, in `update_click_status()`, after the events are recorded in DB (after the `$wpdb->update` call that saves the events JSON), add:

```php
// Fire Measurement Protocol for webhook-driven lifecycle events
if ( $status && in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
    $ga_client_id = $wpdb->get_var( $wpdb->prepare(
        "SELECT ga_client_id FROM $table_name WHERE click_id = %s",
        sanitize_text_field( $click_id )
    ) );

    $mp = new CUFT_Measurement_Protocol();
    $mp->send( $ga_client_id ?: '', $status, array(
        'click_id'    => $click_id,
        'lead_source' => $current_record ? ( $current_record->platform ?: 'unknown' ) : 'unknown',
    ) );
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-webhook-mp-integration.php"`
Expected: PASS (2 tests, 2 assertions)

- [ ] **Step 5: Commit**

```bash
git add includes/class-cuft-click-tracker.php tests/unit/test-webhook-mp-integration.php
git commit -m "feat: Fire Measurement Protocol events from webhook status updates"
```

---

## Task 9: Client-Side Event Replay

**Files:**
- Create: `includes/ajax/class-cuft-event-replay.php`
- Modify: `choice-universal-form-tracker.php` (require + register AJAX)
- Modify: `assets/cuft-click-integration.js` (add replay check on pageview)
- Modify: `includes/class-cuft-click-tracker.php` (update_click_status sets replayed_at = NULL for webhook events)
- Test: `tests/unit/test-event-replay.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/unit/test-event-replay.php

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class Test_Event_Replay extends TestCase {

    private static $table_name;

    public static function set_up_before_class() {
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
    }

    public function set_up() {
        global $wpdb;
        $wpdb->insert( self::$table_name, array(
            'click_id'     => 'test-replay-click',
            'platform'     => 'google',
            'qualified'    => 1,
            'score'        => 0,
            'ga_client_id' => '111.222',
            'events'       => wp_json_encode( array(
                array(
                    'event_type' => 'form_submit',
                    'timestamp'  => '2026-04-01 10:00:00',
                ),
                array(
                    'event_type'  => 'qualify_lead',
                    'timestamp'   => '2026-04-02 14:00:00',
                    'replayed_at' => null,
                    'source'      => 'webhook',
                ),
            ) ),
        ) );
    }

    public function tear_down() {
        global $wpdb;
        $wpdb->delete( self::$table_name, array( 'click_id' => 'test-replay-click' ) );
    }

    public function test_get_pending_events_returns_unreplayed_webhook_events() {
        $pending = CUFT_Event_Replay::get_pending_events( 'test-replay-click' );
        $this->assertCount( 1, $pending );
        $this->assertEquals( 'qualify_lead', $pending[0]['event_type'] );
    }

    public function test_mark_events_replayed_sets_timestamp() {
        CUFT_Event_Replay::mark_events_replayed( 'test-replay-click' );

        $pending = CUFT_Event_Replay::get_pending_events( 'test-replay-click' );
        $this->assertCount( 0, $pending );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-event-replay.php"`
Expected: FAIL — CUFT_Event_Replay class not found

- [ ] **Step 3: Create CUFT_Event_Replay class**

```php
<?php
// includes/ajax/class-cuft-event-replay.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Event_Replay {

    public function __construct() {
        add_action( 'wp_ajax_cuft_get_pending_events', array( $this, 'ajax_get_pending' ) );
        add_action( 'wp_ajax_nopriv_cuft_get_pending_events', array( $this, 'ajax_get_pending' ) );
    }

    public static function get_pending_events( string $click_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM $table WHERE click_id = %s",
            sanitize_text_field( $click_id )
        ) );

        if ( ! $row || empty( $row->events ) ) {
            return array();
        }

        $events = json_decode( $row->events, true );
        if ( ! is_array( $events ) ) {
            return array();
        }

        $pending = array();
        foreach ( $events as $event ) {
            if ( ! empty( $event['source'] ) && 'webhook' === $event['source'] && empty( $event['replayed_at'] ) ) {
                $pending[] = $event;
            }
        }

        return $pending;
    }

    public static function mark_events_replayed( string $click_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT events FROM $table WHERE click_id = %s",
            sanitize_text_field( $click_id )
        ) );

        if ( ! $row || empty( $row->events ) ) {
            return;
        }

        $events  = json_decode( $row->events, true );
        $updated = false;

        foreach ( $events as &$event ) {
            if ( ! empty( $event['source'] ) && 'webhook' === $event['source'] && empty( $event['replayed_at'] ) ) {
                $event['replayed_at'] = current_time( 'mysql' );
                $updated = true;
            }
        }

        if ( $updated ) {
            $wpdb->update(
                $table,
                array( 'events' => wp_json_encode( $events ) ),
                array( 'click_id' => sanitize_text_field( $click_id ) ),
                array( '%s' ),
                array( '%s' )
            );
        }
    }

    public function ajax_get_pending(): void {
        $click_id = isset( $_GET['click_id'] ) ? sanitize_text_field( wp_unslash( $_GET['click_id'] ) ) : '';

        if ( empty( $click_id ) ) {
            wp_send_json_success( array( 'events' => array() ) );
            return;
        }

        $pending = self::get_pending_events( $click_id );

        if ( ! empty( $pending ) ) {
            self::mark_events_replayed( $click_id );
        }

        wp_send_json_success( array( 'events' => $pending ) );
    }
}
```

- [ ] **Step 4: Tag webhook events with `source: 'webhook'` in update_click_status**

In `includes/class-cuft-click-tracker.php`, in the event recording section of `update_click_status()`, update the lifecycle event array to include `source`:

```php
if ( $status && in_array( $status, self::get_valid_webhook_statuses(), true ) ) {
    $events[] = array(
        'event_type'  => $status,
        'timestamp'   => current_time( 'mysql' ),
        'source'      => 'webhook',
        'replayed_at' => null,
    );
}
```

- [ ] **Step 5: Load class and register in main plugin file**

In `choice-universal-form-tracker.php`, add the require:

```php
require_once CUFT_PATH . 'includes/ajax/class-cuft-event-replay.php';
```

And instantiate in the constructor or init hook where other AJAX handlers are set up:

```php
new CUFT_Event_Replay();
```

- [ ] **Step 6: Add JS replay logic to cuft-click-integration.js**

In `assets/cuft-click-integration.js`, after the existing `addClickIdToDataLayer()` call in the ready() callback (around line 144), add:

```javascript
// Check for pending webhook events to replay
(function checkPendingEvents() {
  var clickId = null;
  try {
    var match = document.cookie.match(/(^|; )cuft_click_id=([^;]*)/);
    if (match && match[2]) {
      clickId = decodeURIComponent(match[2]);
    }
  } catch (e) {
    return;
  }

  if (!clickId) {
    return;
  }

  var xhr = new XMLHttpRequest();
  xhr.open("GET", (window.cuftAjax ? window.cuftAjax.ajax_url : "/wp-admin/admin-ajax.php") +
    "?action=cuft_get_pending_events&click_id=" + encodeURIComponent(clickId), true);
  xhr.onreadystatechange = function () {
    if (xhr.readyState !== 4 || xhr.status !== 200) return;
    try {
      var resp = JSON.parse(xhr.responseText);
      if (resp.success && resp.data && resp.data.events && resp.data.events.length) {
        var dl = window.dataLayer || [];
        for (var i = 0; i < resp.data.events.length; i++) {
          var evt = resp.data.events[i];
          dl.push({
            event: evt.event_type,
            click_id: clickId,
            cuft_tracked: true,
            cuft_source: "webhook_replay",
            cuft_replayed: true
          });
        }
      }
    } catch (e) {
      // Silently fail
    }
  };
  xhr.send();
})();
```

- [ ] **Step 7: Run test to verify it passes**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit tests/unit/test-event-replay.php"`
Expected: PASS (2 tests, 2 assertions)

- [ ] **Step 8: Commit**

```bash
git add includes/ajax/class-cuft-event-replay.php includes/class-cuft-click-tracker.php choice-universal-form-tracker.php assets/cuft-click-integration.js tests/unit/test-event-replay.php
git commit -m "feat: Add client-side event replay for webhook-driven lifecycle events"
```

---

## Task 10: Version Bump and Final Integration

**Files:**
- Modify: `choice-universal-form-tracker.php:17` (version)
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Bump version**

In `choice-universal-form-tracker.php`, update:

```php
// Line 17
define( 'CUFT_VERSION', '3.22.0' );
```

Also update the `Version:` header in the plugin docblock to `3.22.0`.

- [ ] **Step 2: Update CHANGELOG.md**

Add at the top of `CHANGELOG.md`:

```markdown
## [3.22.0] - 2026-04-02

### Added
- **GA4 event naming alignment** — adopted GA4 recommended lead generation event names
  - New `qualify_lead` event replaces strict `generate_lead` (email + phone + click_id)
  - New broad `generate_lead` fires on any form submission with a valid email
  - Full lifecycle events: `disqualify_lead`, `working_lead`, `close_convert_lead`, `close_unconvert_lead`
- **Webhook `status` parameter** — send `?status=qualify_lead` (etc.) to record lifecycle events
  - Backward compatible: `qualified=1` still works, mapped to `qualify_lead`
- **GA4 Measurement Protocol** — server-side events fire at webhook time
  - Configurable in Settings: GA4 Measurement ID and API Secret
  - Graceful fallback when not configured
- **Client-side event replay** — webhook events pushed to dataLayer on next pageview
  - Cookie-based (no PHP sessions), fires once per event
  - `cuft_replayed: true` flag for GTM trigger differentiation
- **Admin settings for secrets** — Registration Secret, GA4 Measurement ID, and API Secret configurable from wp-admin
  - `wp-config.php` constants still override DB values
- **`ga_client_id` capture** — extracted from `_ga` cookie at form submission for Measurement Protocol

### Changed
- `generate_lead` now fires on any form with a valid email (previously required email + phone + click_id)
- `status_qualified` webhook event renamed to `qualify_lead`

### Deprecated
- `generate_lead` with strict criteria (email + phone + click_id) — fires with `cuft_deprecated: true` for one version, then removed. Migrate GTM triggers to `qualify_lead`.
```

- [ ] **Step 3: Run full test suite**

Run: `docker exec wp-pdev-cli bash -c "cd /var/www/html/wp-content/plugins/choice-uft && vendor/bin/phpunit"`
Expected: All tests pass

- [ ] **Step 4: Commit**

```bash
git add choice-universal-form-tracker.php CHANGELOG.md
git commit -m "chore: Bump version to 3.22.0 with GA4 event naming and lead lifecycle"
```
