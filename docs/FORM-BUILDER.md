# Form Builder Developer Documentation

**Version**: 3.14.0
**Feature**: Testing Dashboard Form Builder
**Status**: Production Ready

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Framework Adapter System](#framework-adapter-system)
3. [PostMessage Protocol](#postmessage-protocol)
4. [AJAX Endpoints](#ajax-endpoints)
5. [Test Mode Infrastructure](#test-mode-infrastructure)
6. [Creating Custom Adapters](#creating-custom-adapters)
7. [Extension Points](#extension-points)
8. [Testing Guidelines](#testing-guidelines)
9. [Performance Considerations](#performance-considerations)
10. [Security](#security)

---

## Architecture Overview

### Component Hierarchy

```
Testing Dashboard (Admin UI)
    ↓
Form Builder Core (class-cuft-form-builder.php)
    ↓
AJAX Handler (class-cuft-form-builder-ajax.php)
    ↓
Adapter Factory (class-cuft-adapter-factory.php)
    ↓
Framework Adapters (framework-adapters/*)
    ↓
Test Mode Manager (class-cuft-test-mode.php)
```

### Data Flow

```
1. User clicks "Create Test Form"
   ↓
2. AJAX request to cuft_create_test_form
   ↓
3. Factory loads appropriate framework adapter
   ↓
4. Adapter creates form in framework's format
   ↓
5. Form metadata stored in wp_postmeta
   ↓
6. Iframe URL generated with test_mode=1
   ↓
7. Form loaded in iframe
   ↓
8. Test Mode script injected
   ↓
9. PostMessage bridge established
   ↓
10. User populates and submits form
   ↓
11. Events captured and validated
```

### File Structure

```
choice-uft/
├── includes/
│   ├── admin/
│   │   ├── class-cuft-form-builder.php          # Core builder class
│   │   ├── class-cuft-adapter-factory.php       # Lazy-loading factory
│   │   └── framework-adapters/
│   │       ├── abstract-cuft-adapter.php        # Base adapter interface
│   │       ├── class-cuft-elementor-adapter.php # Elementor implementation
│   │       ├── class-cuft-cf7-adapter.php       # Contact Form 7
│   │       ├── class-cuft-gravity-adapter.php   # Gravity Forms
│   │       ├── class-cuft-ninja-adapter.php     # Ninja Forms
│   │       └── class-cuft-avada-adapter.php     # Avada/Fusion
│   ├── ajax/
│   │   └── class-cuft-form-builder-ajax.php     # AJAX endpoints
│   ├── class-cuft-test-mode.php                 # Test mode manager
│   ├── class-cuft-test-routing.php              # URL routing
│   ├── class-cuft-form-template.php             # Template storage
│   ├── class-cuft-test-session.php              # Session management
│   └── class-cuft-form-builder-validator.php    # Compliance validator
├── assets/admin/
│   ├── js/
│   │   ├── cuft-form-builder.js                 # Main dashboard controller
│   │   ├── cuft-iframe-bridge.js                # PostMessage communication
│   │   └── cuft-test-mode.js                    # Iframe test mode script
│   └── css/
│       └── cuft-form-builder.css                # UI styles
└── specs/003-testing-dashboard-form/
    ├── plan.md                                   # Implementation plan
    ├── research.md                               # Technical decisions
    ├── data-model.md                             # Entity definitions
    ├── tasks.md                                  # Task breakdown
    ├── quickstart.md                             # Testing guide
    └── contracts/
        ├── ajax-endpoints.md                     # API contracts
        └── postmessage-protocol.md               # Cross-frame protocol
```

---

## Framework Adapter System

### Abstract Base Class

All framework adapters extend `CUFT_Adapter_Abstract`:

```php
<?php
abstract class CUFT_Adapter_Abstract {
    /**
     * Check if framework is available and active
     * @return bool
     */
    abstract public function is_available();

    /**
     * Create a test form
     * @param array $template Template configuration
     * @return array ['success' => bool, 'form_id' => int, 'url' => string]
     */
    abstract public function create_form($template);

    /**
     * Delete a test form
     * @param int $form_id Framework-specific form ID
     * @return bool Success status
     */
    abstract public function delete_form($form_id);

    /**
     * Get framework name
     * @return string
     */
    abstract public function get_framework_name();

    /**
     * Get framework version
     * @return string|null
     */
    abstract public function get_version();
}
```

### Adapter Factory Pattern

The factory uses lazy loading to minimize memory footprint:

```php
<?php
class CUFT_Adapter_Factory {
    private static $adapters = [];
    private static $adapter_classes = [
        'elementor' => 'CUFT_Elementor_Adapter',
        'cf7'       => 'CUFT_CF7_Adapter',
        'gravity'   => 'CUFT_Gravity_Adapter',
        'ninja'     => 'CUFT_Ninja_Adapter',
        'avada'     => 'CUFT_Avada_Adapter',
    ];

    /**
     * Get adapter for framework (lazy-loaded)
     * @param string $framework Framework identifier
     * @return CUFT_Adapter_Abstract|null
     */
    public static function get_adapter($framework) {
        if (isset(self::$adapters[$framework])) {
            return self::$adapters[$framework];
        }

        if (!isset(self::$adapter_classes[$framework])) {
            return null;
        }

        $class = self::$adapter_classes[$framework];
        if (!class_exists($class)) {
            require_once CUFT_PLUGIN_DIR . "includes/admin/framework-adapters/class-cuft-{$framework}-adapter.php";
        }

        $adapter = new $class();
        self::$adapters[$framework] = $adapter;

        return $adapter;
    }
}
```

### Example: Elementor Adapter

```php
<?php
class CUFT_Elementor_Adapter extends CUFT_Adapter_Abstract {
    public function is_available() {
        return defined('ELEMENTOR_PRO_VERSION') && version_compare(ELEMENTOR_PRO_VERSION, '3.0.0', '>=');
    }

    public function create_form($template) {
        // Create WordPress post
        $post_id = wp_insert_post([
            'post_title'   => 'CUFT Test Form - ' . time(),
            'post_type'    => 'elementor_library',
            'post_status'  => 'publish',
            'meta_input'   => [
                '_elementor_template_type' => 'page',
                '_elementor_edit_mode'     => 'builder',
            ],
        ]);

        if (is_wp_error($post_id)) {
            return ['success' => false, 'error' => $post_id->get_error_message()];
        }

        // Generate Elementor form data
        $form_data = $this->generate_elementor_form_data($template);
        update_post_meta($post_id, '_elementor_data', wp_json_encode($form_data));

        return [
            'success' => true,
            'form_id' => $post_id,
            'url'     => get_permalink($post_id),
        ];
    }

    public function delete_form($form_id) {
        return wp_delete_post($form_id, true) !== false;
    }

    private function generate_elementor_form_data($template) {
        // Generate Elementor JSON structure
        return [
            [
                'id'       => 'unique-id-' . uniqid(),
                'elType'   => 'section',
                'elements' => [
                    [
                        'id'       => 'form-widget-' . uniqid(),
                        'elType'   => 'widget',
                        'widgetType' => 'form',
                        'settings' => [
                            'form_name'   => $template['name'],
                            'form_fields' => $this->build_form_fields($template['fields']),
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

---

## PostMessage Protocol

### Message Types

#### Dashboard → Iframe

**1. Populate Fields**
```javascript
{
  action: 'cuft_populate_fields',
  nonce: 'security-nonce',
  data: {
    fields: {
      email: 'test@example.com',
      phone: '555-0123',
      name: 'Test User',
      message: 'Test message'
    },
    options: {
      trigger_events: true,  // Trigger input/change events
      clear_first: false     // Clear existing values first
    }
  }
}
```

**2. Trigger Submit**
```javascript
{
  action: 'cuft_trigger_submit',
  nonce: 'security-nonce'
}
```

**3. Clear Form**
```javascript
{
  action: 'cuft_clear_form',
  nonce: 'security-nonce'
}
```

#### Iframe → Dashboard

**1. Form Loaded**
```javascript
{
  action: 'cuft_form_loaded',
  data: {
    framework: 'elementor',
    form_id: 'elementor-form-abc123',
    ready: true,
    field_count: 5
  }
}
```

**2. Form Submitted**
```javascript
{
  action: 'cuft_form_submitted',
  data: {
    form_data: {
      email: 'test@example.com',
      phone: '555-0123'
    },
    tracking_event: {
      event: 'form_submit',
      form_type: 'elementor',
      cuft_tracked: true,
      cuft_source: 'elementor_pro'
    }
  }
}
```

**3. Error Occurred**
```javascript
{
  action: 'cuft_error',
  data: {
    message: 'Failed to populate field: email',
    field: 'email',
    error: 'Selector not found'
  }
}
```

### Implementation: Dashboard Side

```javascript
// assets/admin/js/cuft-iframe-bridge.js
const CUFTIframeBridge = {
  sendToIframe(iframe, action, data) {
    if (!iframe || !iframe.contentWindow) {
      console.error('Invalid iframe');
      return;
    }

    const message = {
      action: action,
      nonce: cuftFormBuilder.nonce,
      data: data
    };

    iframe.contentWindow.postMessage(message, window.location.origin);
  },

  receiveFromIframe(event) {
    // Origin validation
    if (event.origin !== window.location.origin) {
      console.warn('Invalid origin:', event.origin);
      return;
    }

    // Nonce validation for sensitive actions
    if (event.data.requiresNonce && event.data.nonce !== cuftFormBuilder.nonce) {
      console.error('Invalid nonce');
      return;
    }

    // Route message
    switch (event.data.action) {
      case 'cuft_form_loaded':
        this.handleFormLoaded(event.data.data);
        break;
      case 'cuft_form_submitted':
        this.handleFormSubmitted(event.data.data);
        break;
      case 'cuft_error':
        this.handleError(event.data.data);
        break;
    }
  }
};

// Initialize listener
window.addEventListener('message', (e) => CUFTIframeBridge.receiveFromIframe(e));
```

### Implementation: Iframe Side

```javascript
// assets/admin/js/cuft-test-mode.js (loaded in iframe)
const CUFTTestMode = {
  init() {
    window.addEventListener('message', this.handleMessage.bind(this));
    this.notifyLoaded();
  },

  handleMessage(event) {
    if (event.origin !== window.location.origin) return;

    switch (event.data.action) {
      case 'cuft_populate_fields':
        this.populateFields(event.data.data);
        break;
      case 'cuft_trigger_submit':
        this.triggerSubmit();
        break;
      case 'cuft_clear_form':
        this.clearForm();
        break;
    }
  },

  populateFields(data) {
    const { fields, options } = data;

    Object.entries(fields).forEach(([fieldName, value]) => {
      const selectors = [
        `input[name="${fieldName}"]`,
        `textarea[name="${fieldName}"]`,
        `select[name="${fieldName}"]`,
        `#${fieldName}`,
        `.${fieldName}`
      ];

      for (const selector of selectors) {
        const field = document.querySelector(selector);
        if (field) {
          field.value = value;

          if (options.trigger_events) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
            field.dispatchEvent(new Event('change', { bubbles: true }));
          }
          break;
        }
      }
    });

    this.notifyPopulated();
  },

  notifyLoaded() {
    window.parent.postMessage({
      action: 'cuft_form_loaded',
      data: {
        framework: this.detectFramework(),
        form_id: this.getFormId(),
        ready: true
      }
    }, window.location.origin);
  }
};

// Auto-initialize if test mode
if (new URLSearchParams(window.location.search).get('test_mode') === '1') {
  CUFTTestMode.init();
}
```

---

## AJAX Endpoints

### Endpoint: Create Test Form

**URL**: `/wp-admin/admin-ajax.php?action=cuft_create_test_form`
**Method**: POST
**Capability**: `manage_options`

**Request:**
```json
{
  "action": "cuft_create_test_form",
  "nonce": "security-nonce",
  "framework": "elementor",
  "template_id": "basic_contact_form"
}
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "instance_id": "cuft_test_abc123xyz",
    "form_id": 456,
    "framework": "elementor",
    "url": "http://example.com/cuft-test-form/?form_id=456&test_mode=1",
    "created_at": "2025-01-10T10:30:00Z"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "data": {
    "message": "Framework not available",
    "code": "framework_unavailable"
  }
}
```

### Endpoint: Get Test Forms

**URL**: `/wp-admin/admin-ajax.php?action=cuft_get_test_forms`
**Method**: GET
**Capability**: `manage_options`

**Request:**
```json
{
  "action": "cuft_get_test_forms",
  "nonce": "security-nonce",
  "status": "active"  // Optional: "active" | "all"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "forms": [
      {
        "instance_id": "cuft_test_abc123",
        "framework": "elementor",
        "form_id": 456,
        "url": "http://example.com/?p=456&test_mode=1",
        "created_at": "2025-01-10T10:00:00Z"
      }
    ],
    "total": 1
  }
}
```

### Endpoint: Delete Test Form

**URL**: `/wp-admin/admin-ajax.php?action=cuft_delete_test_form`
**Method**: POST
**Capability**: `manage_options`

**Request:**
```json
{
  "action": "cuft_delete_test_form",
  "nonce": "security-nonce",
  "instance_id": "cuft_test_abc123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Test form deleted successfully",
    "instance_id": "cuft_test_abc123"
  }
}
```

### Endpoint: Populate Form

**URL**: `/wp-admin/admin-ajax.php?action=cuft_populate_form`
**Method**: POST
**Capability**: `manage_options`

**Request:**
```json
{
  "action": "cuft_populate_form",
  "nonce": "security-nonce",
  "instance_id": "cuft_test_abc123"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "fields": {
      "email": "test-1736506800@example.com",
      "phone": "555-0123",
      "name": "Test User",
      "message": "This is a test submission"
    }
  }
}
```

---

## Test Mode Infrastructure

### Test Mode Manager

**File**: `includes/class-cuft-test-mode.php`

Prevents real actions during testing:

```php
<?php
class CUFT_Test_Mode {
    public function __construct() {
        if ($this->is_test_mode()) {
            $this->add_filters();
        }
    }

    private function is_test_mode() {
        return isset($_GET['test_mode']) && $_GET['test_mode'] === '1';
    }

    private function add_filters() {
        // Contact Form 7
        add_filter('wpcf7_skip_mail', '__return_true');

        // Gravity Forms
        add_filter('gform_pre_send_email', '__return_false');

        // Ninja Forms
        add_filter('ninja_forms_submit_data', [$this, 'block_ninja_actions'], 10, 2);

        // Elementor Pro
        add_filter('elementor_pro/forms/record/actions', '__return_empty_array');

        // Fake mail success
        add_filter('pre_wp_mail', [$this, 'fake_mail_success'], 10, 2);
    }

    public function fake_mail_success($null, $atts) {
        return true; // Pretend mail was sent
    }
}
```

### Test Form Routing

**File**: `includes/class-cuft-test-routing.php`

Custom URL routing for test forms:

```php
<?php
class CUFT_Test_Routing {
    public function __construct() {
        add_action('init', [$this, 'add_rewrite_rules']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_test_form_request']);
    }

    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^cuft-test-form/([^/]+)/?',
            'index.php?cuft_test_form=$matches[1]',
            'top'
        );
    }

    public function add_query_vars($vars) {
        $vars[] = 'cuft_test_form';
        $vars[] = 'form_id';
        $vars[] = 'test_mode';
        return $vars;
    }

    public function handle_test_form_request() {
        $instance_id = get_query_var('cuft_test_form');
        if (!$instance_id) return;

        $form_id = get_option("cuft_test_form_{$instance_id}");
        if (!$form_id) {
            wp_die('Test form not found', 'Not Found', ['response' => 404]);
        }

        // Redirect to actual form with test mode
        wp_redirect(add_query_arg([
            'test_mode' => '1',
            'instance_id' => $instance_id
        ], get_permalink($form_id)));
        exit;
    }
}
```

---

## Creating Custom Adapters

### Step 1: Create Adapter Class

```php
<?php
// includes/admin/framework-adapters/class-cuft-my-framework-adapter.php

class CUFT_My_Framework_Adapter extends CUFT_Adapter_Abstract {

    public function is_available() {
        // Check if your framework is active
        return class_exists('My_Framework') && defined('MY_FRAMEWORK_VERSION');
    }

    public function create_form($template) {
        // 1. Create form using framework API
        $form_id = my_framework_create_form([
            'title' => $template['name'],
            'fields' => $this->map_fields($template['fields']),
        ]);

        if (!$form_id) {
            return ['success' => false, 'error' => 'Failed to create form'];
        }

        // 2. Mark as test form
        update_post_meta($form_id, '_cuft_test_form', true);

        // 3. Return success
        return [
            'success' => true,
            'form_id' => $form_id,
            'url'     => get_permalink($form_id),
        ];
    }

    public function delete_form($form_id) {
        // Delete form using framework API
        return my_framework_delete_form($form_id);
    }

    public function get_framework_name() {
        return 'My Framework';
    }

    public function get_version() {
        return defined('MY_FRAMEWORK_VERSION') ? MY_FRAMEWORK_VERSION : null;
    }

    private function map_fields($template_fields) {
        // Convert template fields to framework format
        $fields = [];
        foreach ($template_fields as $field) {
            $fields[] = [
                'type'  => $this->map_field_type($field['type']),
                'label' => $field['label'],
                'name'  => $field['name'],
                'required' => $field['required'] ?? false,
            ];
        }
        return $fields;
    }
}
```

### Step 2: Register in Factory

```php
// includes/admin/class-cuft-adapter-factory.php

private static $adapter_classes = [
    'elementor' => 'CUFT_Elementor_Adapter',
    'cf7'       => 'CUFT_CF7_Adapter',
    'gravity'   => 'CUFT_Gravity_Adapter',
    'ninja'     => 'CUFT_Ninja_Adapter',
    'avada'     => 'CUFT_Avada_Adapter',
    'my_framework' => 'CUFT_My_Framework_Adapter', // Add your adapter
];
```

### Step 3: Add to Admin UI

```php
// includes/admin/views/testing-dashboard.php

<select id="cuft-framework-select">
    <option value="elementor">Elementor Pro</option>
    <option value="cf7">Contact Form 7</option>
    <option value="gravity">Gravity Forms</option>
    <option value="ninja">Ninja Forms</option>
    <option value="avada">Avada Forms</option>
    <option value="my_framework">My Framework</option> <!-- Add option -->
</select>
```

---

## Extension Points

### Filters

**`cuft_form_builder_frameworks`**
Add or modify available frameworks:
```php
add_filter('cuft_form_builder_frameworks', function($frameworks) {
    $frameworks['my_framework'] = 'CUFT_My_Framework_Adapter';
    return $frameworks;
});
```

**`cuft_form_builder_templates`**
Add custom templates:
```php
add_filter('cuft_form_builder_templates', function($templates) {
    $templates['my_template'] = [
        'name' => 'Custom Template',
        'fields' => [
            ['type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true],
            ['type' => 'text', 'label' => 'Custom Field', 'name' => 'custom', 'required' => false],
        ],
    ];
    return $templates;
});
```

**`cuft_test_mode_active`**
Override test mode detection:
```php
add_filter('cuft_test_mode_active', function($is_test_mode) {
    return isset($_GET['my_test_param']);
});
```

**`cuft_form_builder_test_data`**
Customize test data generation:
```php
add_filter('cuft_form_builder_test_data', function($data, $template) {
    $data['custom_field'] = 'My custom value';
    return $data;
}, 10, 2);
```

### Actions

**`cuft_form_created`**
Runs after form creation:
```php
add_action('cuft_form_created', function($form_id, $instance_id, $framework) {
    error_log("Created test form: {$instance_id} (framework: {$framework})");
}, 10, 3);
```

**`cuft_form_deleted`**
Runs after form deletion:
```php
add_action('cuft_form_deleted', function($form_id, $instance_id) {
    // Cleanup custom data
    delete_option("my_custom_data_{$instance_id}");
}, 10, 2);
```

**`cuft_test_mode_init`**
Runs when test mode initializes:
```php
add_action('cuft_test_mode_init', function() {
    // Add custom test mode behaviors
    add_filter('my_plugin_send_notification', '__return_false');
});
```

---

## Testing Guidelines

### Unit Testing

```php
<?php
// tests/unit/test-adapter-factory.php

class Test_Adapter_Factory extends WP_UnitTestCase {
    public function test_get_elementor_adapter() {
        $adapter = CUFT_Adapter_Factory::get_adapter('elementor');
        $this->assertInstanceOf('CUFT_Elementor_Adapter', $adapter);
    }

    public function test_adapter_lazy_loading() {
        $adapter1 = CUFT_Adapter_Factory::get_adapter('elementor');
        $adapter2 = CUFT_Adapter_Factory::get_adapter('elementor');
        $this->assertSame($adapter1, $adapter2); // Same instance
    }

    public function test_invalid_framework() {
        $adapter = CUFT_Adapter_Factory::get_adapter('invalid');
        $this->assertNull($adapter);
    }
}
```

### Integration Testing

```php
<?php
// tests/integration/test-form-creation.php

class Test_Form_Creation extends WP_UnitTestCase {
    public function test_create_elementor_form() {
        // Ensure Elementor is available
        if (!class_exists('ElementorPro\Plugin')) {
            $this->markTestSkipped('Elementor Pro not available');
        }

        $adapter = CUFT_Adapter_Factory::get_adapter('elementor');
        $result = $adapter->create_form([
            'name' => 'Test Form',
            'fields' => [
                ['type' => 'email', 'label' => 'Email', 'name' => 'email', 'required' => true],
            ],
        ]);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('form_id', $result);
        $this->assertIsInt($result['form_id']);

        // Cleanup
        $adapter->delete_form($result['form_id']);
    }
}
```

### JavaScript Testing

```javascript
// tests/js/test-iframe-bridge.js

describe('CUFT Iframe Bridge', () => {
    let iframe;

    beforeEach(() => {
        iframe = document.createElement('iframe');
        document.body.appendChild(iframe);
    });

    afterEach(() => {
        document.body.removeChild(iframe);
    });

    it('should send message to iframe', () => {
        const spy = jest.spyOn(iframe.contentWindow, 'postMessage');

        CUFTIframeBridge.sendToIframe(iframe, 'cuft_populate_fields', {
            fields: { email: 'test@example.com' }
        });

        expect(spy).toHaveBeenCalledWith(
            expect.objectContaining({
                action: 'cuft_populate_fields',
                data: expect.objectContaining({
                    fields: { email: 'test@example.com' }
                })
            }),
            window.location.origin
        );
    });

    it('should validate message origin', () => {
        const event = new MessageEvent('message', {
            origin: 'http://evil.com',
            data: { action: 'cuft_form_loaded' }
        });

        const consoleSpy = jest.spyOn(console, 'warn');
        CUFTIframeBridge.receiveFromIframe(event);

        expect(consoleSpy).toHaveBeenCalledWith('Invalid origin:', 'http://evil.com');
    });
});
```

---

## Performance Considerations

### Lazy Loading

Always use the Adapter Factory to load framework adapters on-demand:

```php
// ✅ Good: Lazy loading
$adapter = CUFT_Adapter_Factory::get_adapter('elementor');

// ❌ Bad: Direct instantiation
$adapter = new CUFT_Elementor_Adapter();
```

### Caching

Cache framework availability checks:

```php
public function is_available() {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = defined('ELEMENTOR_PRO_VERSION');
    return $cache;
}
```

### DOM Query Optimization

Cache selectors in JavaScript:

```javascript
// ✅ Good: Cached selector
const iframe = document.getElementById('cuft-test-iframe');
iframe.contentWindow.postMessage(...);

// ❌ Bad: Repeated queries
document.getElementById('cuft-test-iframe').contentWindow.postMessage(...);
document.getElementById('cuft-test-iframe').contentWindow.postMessage(...);
```

### Event Delegation

Use event delegation for dynamic forms:

```javascript
// ✅ Good: Event delegation
document.addEventListener('submit', (e) => {
    if (e.target.matches('.cuft-test-form')) {
        handleSubmit(e);
    }
});

// ❌ Bad: Direct binding
document.querySelectorAll('.cuft-test-form').forEach(form => {
    form.addEventListener('submit', handleSubmit);
});
```

---

## Security

### Capability Checks

All AJAX endpoints require `manage_options`:

```php
public function handle_create_test_form() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Insufficient permissions'], 403);
    }
    // ... proceed
}
```

### Nonce Validation

All requests validate nonces:

```php
public function handle_create_test_form() {
    check_ajax_referer('cuft_form_builder', 'nonce');
    // ... proceed
}
```

### Origin Validation

PostMessage validates origin:

```javascript
receiveFromIframe(event) {
    if (event.origin !== window.location.origin) {
        console.warn('Invalid origin');
        return;
    }
    // ... proceed
}
```

### Input Sanitization

Always sanitize user input:

```php
$framework = sanitize_text_field($_POST['framework']);
$template_id = sanitize_text_field($_POST['template_id']);
```

### SQL Injection Prevention

Use prepared statements:

```php
global $wpdb;
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s",
    '_cuft_test_form'
));
```

---

## Troubleshooting

### Forms Won't Create

1. Check framework plugin is active
2. Verify adapter `is_available()` returns true
3. Check PHP error logs for exceptions
4. Enable `WP_DEBUG` and check debug.log

### PostMessage Not Working

1. Verify iframe loaded successfully
2. Check browser console for origin errors
3. Ensure test mode script is enqueued
4. Verify nonce is being passed correctly

### Events Not Captured

1. Check dataLayer is defined
2. Verify tracking script loaded
3. Ensure test mode doesn't block tracking
4. Check for JavaScript errors in console

---

## Support & Resources

- **Quickstart Guide**: `specs/003-testing-dashboard-form/quickstart.md`
- **API Contracts**: `specs/003-testing-dashboard-form/contracts/`
- **Performance Benchmarks**: `specs/003-testing-dashboard-form/PERFORMANCE-VALIDATION.md`
- **GitHub Issues**: https://github.com/ChoiceOMG/choice-uft/issues

---

**Last Updated**: 2025-10-02
**Author**: Choice Universal Form Tracker Development Team
**License**: Proprietary
