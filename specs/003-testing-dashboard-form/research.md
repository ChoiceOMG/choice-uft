# Phase 0: Research and Technical Decisions

## 1. Framework-Specific Form Generation APIs

### Decision: WordPress Page/Post Creation API
**Rationale**: All form frameworks store forms as WordPress posts or custom post types, making the WP Post API the universal approach.

**Alternatives Considered**:
- Direct database insertion: Too risky, bypasses framework hooks
- Framework-specific APIs: Not all frameworks expose public creation APIs
- Shortcode generation only: Doesn't create actual form instances

**Implementation Approach**:
```php
// Elementor: Create post with _elementor_data meta
wp_insert_post([
    'post_type' => 'page',
    'post_status' => 'private',
    'post_title' => 'CUFT Test Form - ' . $framework
]);
update_post_meta($post_id, '_elementor_data', $form_json);

// Contact Form 7: Create wpcf7_contact_form post
wp_insert_post([
    'post_type' => 'wpcf7_contact_form',
    'post_status' => 'publish',
    'post_title' => 'CUFT Test Form'
]);

// Gravity Forms: Use GFAPI
GFAPI::add_form($form_array);

// Ninja Forms: Use Ninja_Forms()->form()->save()
Ninja_Forms()->form()->save($form_data);

// Avada: Create fusion_form post type
wp_insert_post([
    'post_type' => 'fusion_form',
    'post_status' => 'publish'
]);
```

## 2. Iframe Security and Sandbox Requirements

### Decision: Same-origin iframe with minimal sandbox restrictions
**Rationale**: Test forms need to execute JavaScript for tracking validation, requiring same-origin access.

**Alternatives Considered**:
- Fully sandboxed iframe: Would block form JavaScript execution
- Cross-origin iframe: Would prevent data population and event capture
- No iframe (direct embed): Would mix test code with dashboard code

**Security Configuration**:
```html
<iframe
    src="/cuft-test-form/?form_id=123&test_mode=1"
    sandbox="allow-same-origin allow-scripts allow-forms allow-popups"
    id="cuft-test-frame"
    data-nonce="<?php echo wp_create_nonce('cuft_test_form'); ?>"
>
```

**Key Security Measures**:
- Admin-only access via capability check
- Nonce verification for all operations
- Test mode flag prevents real actions
- Private post status for test forms

## 3. Cross-Frame Communication Patterns

### Decision: postMessage API with structured message protocol
**Rationale**: Native browser API, secure cross-frame communication, supports all browsers.

**Alternatives Considered**:
- Direct DOM access: Security risk, blocked by browsers
- URL parameters: Limited data size, no bidirectional communication
- Cookies/localStorage: Not frame-specific, persistence issues

**Communication Protocol**:
```javascript
// Parent (Dashboard) → Iframe
parent.postMessage({
    action: 'populate_form',
    data: {
        email: 'test@example.com',
        phone: '555-0123',
        name: 'Test User'
    },
    nonce: cuftConfig.nonce
}, window.location.origin);

// Iframe → Parent (Dashboard)
window.parent.postMessage({
    action: 'form_submitted',
    event: {
        form_id: 'elementor-form-123',
        tracking_data: dataLayerEvent
    }
}, window.location.origin);
```

## 4. Test Endpoint Architecture

### Decision: Dedicated WordPress AJAX action with response interception
**Rationale**: Leverages existing WordPress infrastructure while preventing real processing.

**Alternatives Considered**:
- Mock server endpoint: Too complex for WordPress environment
- JavaScript-only interception: Misses server-side validation
- Null action attribute: Breaks form framework expectations

**Implementation Pattern**:
```php
// Hook into form submission early
add_action('init', function() {
    if (isset($_GET['test_mode']) && current_user_can('manage_options')) {
        // Intercept before form frameworks process
        add_filter('wpcf7_skip_mail', '__return_true', 1);
        add_filter('gform_pre_send_email', '__return_false', 1);
        add_filter('ninja_forms_submit_data', 'cuft_test_intercept', 1);

        // Log submission for dashboard display
        do_action('cuft_test_submission', $_POST);
    }
});
```

## 5. Framework Detection and Capability Assessment

### Decision: Active plugin check with feature detection
**Rationale**: Most reliable method to determine available frameworks and their capabilities.

**Implementation**:
```php
function cuft_get_available_frameworks() {
    $frameworks = [];

    // Elementor Pro
    if (defined('ELEMENTOR_PRO_VERSION')) {
        $frameworks['elementor'] = [
            'name' => 'Elementor Pro',
            'supports_generation' => true,
            'post_type' => 'page',
            'min_version' => '3.0'
        ];
    }

    // Contact Form 7
    if (class_exists('WPCF7')) {
        $frameworks['cf7'] = [
            'name' => 'Contact Form 7',
            'supports_generation' => true,
            'post_type' => 'wpcf7_contact_form'
        ];
    }

    // Gravity Forms
    if (class_exists('GFAPI')) {
        $frameworks['gravity'] = [
            'name' => 'Gravity Forms',
            'supports_generation' => true,
            'api_class' => 'GFAPI'
        ];
    }

    // Ninja Forms
    if (function_exists('Ninja_Forms')) {
        $frameworks['ninja'] = [
            'name' => 'Ninja Forms',
            'supports_generation' => true,
            'api_function' => 'Ninja_Forms'
        ];
    }

    // Avada Forms
    if (class_exists('Fusion_Builder')) {
        $frameworks['avada'] = [
            'name' => 'Avada Forms',
            'supports_generation' => true,
            'post_type' => 'fusion_form'
        ];
    }

    return $frameworks;
}
```

## 6. Test Data Integration

### Decision: Reuse existing testing dashboard's test data generator
**Rationale**: Maintains consistency, avoids duplication, already validated data format.

**Access Pattern**:
```php
// Get test data from existing dashboard
$test_data = CUFT_Testing_Dashboard::get_test_data();

// Format for form population
$form_data = [
    'email' => $test_data['email'],
    'phone' => $test_data['phone'],
    'name' => $test_data['name'] ?? 'Test User',
    'message' => $test_data['message'] ?? 'This is a test submission'
];
```

## 7. Form Field Mapping

### Decision: Basic field types with framework-agnostic mapping
**Rationale**: Simplifies implementation, covers 95% of tracking validation needs.

**Field Mapping**:
```php
$field_map = [
    'name' => ['text', 'name', 'your-name', 'fname'],
    'email' => ['email', 'mail', 'your-email', 'email-address'],
    'phone' => ['tel', 'phone', 'your-phone', 'telephone'],
    'message' => ['textarea', 'message', 'your-message', 'comments']
];
```

## 8. Performance Optimization

### Decision: Lazy loading with on-demand adapter initialization
**Rationale**: Minimizes memory usage, faster dashboard load times.

**Implementation**:
```php
class CUFT_Framework_Adapter_Factory {
    private static $adapters = [];

    public static function get_adapter($framework) {
        if (!isset(self::$adapters[$framework])) {
            $class = 'CUFT_' . ucfirst($framework) . '_Adapter';
            if (class_exists($class)) {
                self::$adapters[$framework] = new $class();
            }
        }
        return self::$adapters[$framework];
    }
}
```

## Technical Decisions Summary

| Area | Decision | Key Benefit |
|------|----------|-------------|
| Form Generation | WordPress Post API | Universal compatibility |
| Iframe Security | Same-origin with sandbox | Balance security/functionality |
| Communication | postMessage protocol | Secure, bidirectional |
| Test Endpoint | AJAX with interception | Prevents real actions |
| Framework Detection | Active plugin check | Accurate capability assessment |
| Test Data | Reuse existing generator | Consistency |
| Field Types | Basic fields only | Simplicity |
| Performance | Lazy loading | Efficiency |

## Remaining Clarifications
None - all technical decisions have been made based on WordPress best practices and the clarified specification requirements.