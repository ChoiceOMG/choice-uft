# WordPress Admin Testing Dashboard - Research & Best Practices

## Version: 1.0
## Date: 2025-09-30
## Status: Research Complete

---

## Executive Summary

This document provides comprehensive research findings for implementing a testing dashboard in the Choice Universal Form Tracker plugin. The dashboard will monitor dataLayer events in real-time, store test session data in localStorage, and provide CRUD operations for managing test data through WordPress admin interface.

---

## 1. WordPress Admin Page Registration

### Decision: Use `add_options_page()` with `manage_options` capability

### Rationale
- Follows WordPress security standards for admin-only functionality
- Integrates seamlessly with Settings menu structure
- Provides automatic capability checking
- Consistent with existing plugin implementation in `CUFT_Admin` class

### Implementation Pattern

```php
class CUFT_Testing_Dashboard {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_testing_menu'));
    }

    /**
     * Add testing dashboard to admin menu
     */
    public function add_testing_menu() {
        add_options_page(
            'CUFT Testing Dashboard',           // Page title
            'Form Tracker Testing',              // Menu title
            'manage_options',                    // Capability required
            'cuft-testing-dashboard',            // Menu slug
            array($this, 'render_dashboard')     // Callback function
        );
    }

    /**
     * Render testing dashboard page
     */
    public function render_dashboard() {
        // Capability check (double-check security)
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Render dashboard UI
        include CUFT_PLUGIN_DIR . 'includes/admin/templates/testing-dashboard.php';
    }
}
```

### Key Requirements

1. **Always use `manage_options` capability**: This is the standard capability for settings pages
2. **Hook on `admin_menu` action**: Not earlier (causes permission errors)
3. **Double-check capabilities**: Verify `current_user_can()` in render callback
4. **Use capability-based logic**: Never rely on role names (roles can be customized)

### Security Considerations

- **Capability vs Role**: Always check capabilities, not roles. WordPress roles can be modified by plugins/custom code
- **Nonce Validation**: Required for all form submissions and AJAX requests
- **Input Sanitization**: Use `sanitize_text_field()`, `sanitize_textarea_field()`, etc.
- **Output Escaping**: Use `esc_html()`, `esc_attr()`, `esc_url()` for all dynamic output

### Alternatives Considered

1. **`add_menu_page()`**: Creates top-level menu item
   - **Rejected**: Testing dashboard doesn't warrant top-level menu position

2. **`add_submenu_page()`**: Adds under existing plugin menu
   - **Rejected**: Plugin uses Settings menu approach, maintaining consistency

3. **Custom capability**: Create `manage_cuft_testing` capability
   - **Rejected**: Unnecessary complexity for admin-only feature

---

## 2. Admin Page Security

### Decision: Implement multi-layered security with nonces, capability checks, and input sanitization

### Rationale
- WordPress requires nonce validation for all state-changing operations
- Prevents CSRF attacks and unauthorized access
- Follows WordPress Coding Standards and security best practices

### Implementation Pattern

```php
/**
 * Render testing dashboard form
 */
public function render_dashboard() {
    // Layer 1: Capability check
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    // Layer 2: Handle form submission with nonce validation
    if (isset($_POST['cuft_testing_action'])) {
        $this->handle_form_submission();
    }

    // Render form with nonce field
    ?>
    <div class="wrap">
        <h1>CUFT Testing Dashboard</h1>

        <form method="post" action="">
            <?php
            // Layer 3: Generate nonce field
            wp_nonce_field('cuft_testing_dashboard', 'cuft_testing_nonce');
            ?>

            <input type="hidden" name="cuft_testing_action" value="save_test_session">

            <!-- Form fields -->

            <?php submit_button('Save Test Session'); ?>
        </form>
    </div>
    <?php
}

/**
 * Handle form submission with security validation
 */
private function handle_form_submission() {
    // Layer 1: Verify nonce
    if (!isset($_POST['cuft_testing_nonce']) ||
        !wp_verify_nonce($_POST['cuft_testing_nonce'], 'cuft_testing_dashboard')) {
        wp_die(__('Security check failed. Please refresh and try again.'));
    }

    // Layer 2: Verify capability
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to perform this action.'));
    }

    // Layer 3: Sanitize and validate inputs
    $action = isset($_POST['cuft_testing_action'])
        ? sanitize_text_field($_POST['cuft_testing_action'])
        : '';

    if (empty($action)) {
        return;
    }

    // Process action with sanitized data
    switch ($action) {
        case 'save_test_session':
            $this->save_test_session();
            break;
        case 'clear_test_data':
            $this->clear_test_data();
            break;
    }
}
```

### Nonce Best Practices

1. **Nonce Lifetime**: Default 24 hours, can be used multiple times
2. **Action Naming**: Use descriptive, unique action names: `cuft_testing_dashboard`, `cuft_clear_test_data`
3. **Nonce Functions**:
   - `wp_nonce_field()`: Generate form field
   - `wp_verify_nonce()`: Verify in form handler
   - `check_admin_referer()`: Verify in admin screens (dies on failure)
   - `wp_nonce_url()`: Add nonce to URLs
   - `wp_create_nonce()`: Create nonce for AJAX/custom use

4. **Never Trust Nonces Alone**: Always combine with capability checks
5. **Session Dependency**: Nonces are tied to user session - logout invalidates all nonces

### Input Sanitization Functions

```php
// Text fields
sanitize_text_field()      // Single-line text
sanitize_textarea_field()  // Multi-line text
sanitize_email()           // Email addresses
sanitize_url()             // URLs
sanitize_key()             // Alphanumeric keys

// Arrays
array_map('sanitize_text_field', $_POST['array_field'])

// JSON data
$json_data = sanitize_textarea_field($_POST['json_data']);
$decoded = json_decode($json_data, true);
// Validate decoded data structure
```

### Output Escaping Functions

```php
// HTML content
esc_html()        // Escape HTML entities
esc_attr()        // Escape HTML attributes
esc_url()         // Escape URLs
esc_js()          // Escape JavaScript strings
esc_textarea()    // Escape textarea content

// Database
$wpdb->prepare()  // Prepare SQL queries (REQUIRED for all user input)
```

### Alternatives Considered

1. **Custom security tokens**:
   - **Rejected**: WordPress nonces are battle-tested and integrate with core

2. **Session-based CSRF protection**:
   - **Rejected**: WordPress nonces handle sessions automatically

3. **API key authentication**:
   - **Rejected**: Unnecessary for admin-only features

---

## 3. Modern WordPress Admin UI

### Decision: Use native WordPress admin CSS classes with minimal custom styling

### Rationale
- Ensures consistent look with WordPress core
- Automatic compatibility with WordPress updates and color schemes
- Reduced maintenance burden
- Better accessibility compliance

### Implementation Pattern

```php
/**
 * Render testing dashboard with WordPress admin UI components
 */
public function render_dashboard() {
    ?>
    <div class="wrap">
        <!-- Page Header -->
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

        <!-- Admin Notice (Success) -->
        <div class="notice notice-success is-dismissible">
            <p>Test session saved successfully!</p>
        </div>

        <!-- Admin Notice (Error) -->
        <div class="notice notice-error">
            <p>Failed to save test session.</p>
        </div>

        <!-- Admin Notice (Warning) -->
        <div class="notice notice-warning">
            <p>Test data will be cleared in 24 hours.</p>
        </div>

        <!-- Admin Notice (Info) -->
        <div class="notice notice-info">
            <p>DataLayer monitoring is active.</p>
        </div>

        <!-- Card Container -->
        <div class="card">
            <h2 class="title">DataLayer Events</h2>
            <p>Real-time monitoring of window.dataLayer.push events</p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column">Event Type</th>
                        <th class="manage-column">Timestamp</th>
                        <th class="manage-column">Data</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>form_submit</td>
                        <td>2025-09-30 10:15:32</td>
                        <td><code>{"form_id": "contact-form"}</code></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Form with Standard Components -->
        <form method="post" action="">
            <?php wp_nonce_field('cuft_testing_dashboard', 'cuft_testing_nonce'); ?>

            <table class="form-table" role="presentation">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="session_name">Session Name</label>
                        </th>
                        <td>
                            <input type="text"
                                   id="session_name"
                                   name="session_name"
                                   class="regular-text"
                                   value="<?php echo esc_attr($session_name); ?>">
                            <p class="description">
                                Enter a descriptive name for this test session.
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Standard WordPress Buttons -->
            <?php submit_button('Save Session', 'primary', 'save_session'); ?>
            <?php submit_button('Clear Data', 'secondary', 'clear_data', false); ?>
            <button type="button" class="button button-link-delete">
                Delete All Sessions
            </button>
        </form>
    </div>
    <?php
}
```

### Standard WordPress CSS Classes

#### Page Structure
- `.wrap`: Main container for admin pages
- `.card`: Card component for grouping related content

#### Notices/Alerts
- `.notice`: Base notice class
- `.notice-success`: Success message (green)
- `.notice-error`: Error message (red)
- `.notice-warning`: Warning message (orange/yellow)
- `.notice-info`: Information message (blue)
- `.is-dismissible`: Adds close button to notice

#### Tables
- `.wp-list-table`: Standard WordPress table
- `.widefat`: Full-width table
- `.fixed`: Fixed table layout
- `.striped`: Alternating row colors
- `.form-table`: Settings form table layout

#### Buttons
- `.button`: Default button
- `.button-primary`: Primary action button (blue)
- `.button-secondary`: Secondary action button (gray)
- `.button-link-delete`: Delete action link-style button (red)
- `.button-large`: Larger button size
- `.button-small`: Smaller button size

#### Form Elements
- `.regular-text`: Standard text input width
- `.small-text`: Small text input
- `.large-text`: Large text input
- `.description`: Helper text below inputs

#### Tabs
```php
<h2 class="nav-tab-wrapper">
    <a href="?page=cuft-testing&tab=dashboard" class="nav-tab nav-tab-active">Dashboard</a>
    <a href="?page=cuft-testing&tab=sessions" class="nav-tab">Sessions</a>
    <a href="?page=cuft-testing&tab=settings" class="nav-tab">Settings</a>
</h2>
```

### Custom CSS Guidelines

```css
/* Enqueue custom admin CSS */
add_action('admin_enqueue_scripts', 'cuft_testing_enqueue_admin_styles');

function cuft_testing_enqueue_admin_styles($hook) {
    // Only load on testing dashboard page
    if ($hook !== 'settings_page_cuft-testing-dashboard') {
        return;
    }

    wp_enqueue_style(
        'cuft-testing-dashboard',
        CUFT_PLUGIN_URL . 'assets/admin/css/testing-dashboard.css',
        array(),
        CUFT_VERSION
    );
}
```

**Custom CSS Best Practices**:
1. **Namespace all custom classes**: Use `.cuft-` prefix
2. **Extend WordPress classes**: Don't override core styles
3. **Use WordPress color variables**: `--wp-admin-theme-color`
4. **Respect admin color schemes**: Test with different themes
5. **Mobile responsive**: Use WordPress breakpoints

### Tools & Resources

1. **WP-Admin Reference**: https://wpadmin.bracketspace.com/
   - Live reference of all WordPress admin UI components
   - Copy-paste ready HTML markup

2. **WordPress Components Package**: `@wordpress/components`
   - React components for Gutenberg-style interfaces
   - Use for advanced interactive dashboards

### Alternatives Considered

1. **Custom UI framework (Bootstrap, Tailwind)**:
   - **Rejected**: Conflicts with WordPress admin styles, increases bundle size

2. **Complete custom CSS**:
   - **Rejected**: High maintenance, breaks with WordPress updates

3. **React-based admin interface**:
   - **Considered for future**: Good for complex dashboards, overkill for current needs

---

## 4. localStorage Management

### Decision: Implement robust localStorage wrapper with JSON serialization, size management, and error handling

### Rationale
- localStorage has ~5MB limit per origin (browser-dependent)
- Must handle QuotaExceededError gracefully
- JSON serialization required for complex data structures
- Need fallback strategy when localStorage unavailable (private browsing, disabled)

### Implementation Pattern

```javascript
/**
 * CUFT Testing Dashboard - localStorage Manager
 *
 * Handles localStorage operations with error handling, size limits,
 * and automatic cleanup of old data.
 */
class CuftStorageManager {
    constructor() {
        this.storageKey = 'cuft_test_sessions';
        this.maxSessions = 50;  // Prevent excessive storage usage
        this.maxAge = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

        // Check localStorage availability
        this.storageAvailable = this.checkStorageAvailable();

        if (this.storageAvailable) {
            this.cleanup();
        }
    }

    /**
     * Check if localStorage is available
     * Handles private browsing mode and disabled storage
     */
    checkStorageAvailable() {
        try {
            const test = '__cuft_storage_test__';
            localStorage.setItem(test, test);
            localStorage.removeItem(test);
            return true;
        } catch (e) {
            console.warn('CUFT: localStorage not available', e);
            return false;
        }
    }

    /**
     * Get all test sessions
     * @returns {Array} Array of session objects
     */
    getSessions() {
        if (!this.storageAvailable) {
            return [];
        }

        try {
            const data = localStorage.getItem(this.storageKey);
            if (!data) {
                return [];
            }

            const sessions = JSON.parse(data);

            // Validate data structure
            if (!Array.isArray(sessions)) {
                console.warn('CUFT: Invalid session data format');
                return [];
            }

            return sessions;
        } catch (e) {
            console.error('CUFT: Failed to parse sessions', e);
            return [];
        }
    }

    /**
     * Save test session
     * @param {Object} session - Session data to save
     * @returns {boolean} Success status
     */
    saveSession(session) {
        if (!this.storageAvailable) {
            console.warn('CUFT: localStorage not available, session not saved');
            return false;
        }

        try {
            // Validate session data
            if (!session || typeof session !== 'object') {
                throw new Error('Invalid session data');
            }

            // Add metadata
            session.timestamp = Date.now();
            session.id = session.id || this.generateId();

            // Get existing sessions
            let sessions = this.getSessions();

            // Check if updating existing session
            const existingIndex = sessions.findIndex(s => s.id === session.id);
            if (existingIndex !== -1) {
                sessions[existingIndex] = session;
            } else {
                sessions.push(session);
            }

            // Enforce max sessions limit (FIFO)
            if (sessions.length > this.maxSessions) {
                sessions = sessions.slice(-this.maxSessions);
            }

            // Serialize and save
            const serialized = JSON.stringify(sessions);

            // Check size before saving (5MB = 5 * 1024 * 1024 bytes)
            const sizeInBytes = new Blob([serialized]).size;
            const maxSize = 5 * 1024 * 1024;

            if (sizeInBytes > maxSize * 0.9) { // 90% threshold
                console.warn('CUFT: Approaching localStorage size limit');
                // Remove oldest sessions
                sessions = sessions.slice(-Math.floor(this.maxSessions / 2));
            }

            localStorage.setItem(this.storageKey, JSON.stringify(sessions));

            return true;

        } catch (e) {
            if (e.name === 'QuotaExceededError') {
                console.error('CUFT: localStorage quota exceeded');
                // Attempt cleanup and retry
                this.cleanup();
                try {
                    const sessions = this.getSessions().slice(-10); // Keep only 10 newest
                    localStorage.setItem(this.storageKey, JSON.stringify(sessions));
                    return this.saveSession(session); // Retry once
                } catch (retryError) {
                    console.error('CUFT: Failed to save after cleanup', retryError);
                    return false;
                }
            }

            console.error('CUFT: Failed to save session', e);
            return false;
        }
    }

    /**
     * Delete session by ID
     * @param {string} sessionId - Session ID to delete
     * @returns {boolean} Success status
     */
    deleteSession(sessionId) {
        if (!this.storageAvailable) {
            return false;
        }

        try {
            let sessions = this.getSessions();
            sessions = sessions.filter(s => s.id !== sessionId);
            localStorage.setItem(this.storageKey, JSON.stringify(sessions));
            return true;
        } catch (e) {
            console.error('CUFT: Failed to delete session', e);
            return false;
        }
    }

    /**
     * Clear all sessions
     */
    clearAll() {
        if (!this.storageAvailable) {
            return;
        }

        try {
            localStorage.removeItem(this.storageKey);
        } catch (e) {
            console.error('CUFT: Failed to clear sessions', e);
        }
    }

    /**
     * Cleanup old sessions
     * Removes sessions older than maxAge
     */
    cleanup() {
        if (!this.storageAvailable) {
            return;
        }

        try {
            const sessions = this.getSessions();
            const now = Date.now();

            const validSessions = sessions.filter(session => {
                if (!session.timestamp) {
                    return false; // Remove sessions without timestamp
                }
                return (now - session.timestamp) < this.maxAge;
            });

            if (validSessions.length !== sessions.length) {
                localStorage.setItem(this.storageKey, JSON.stringify(validSessions));
                console.log(`CUFT: Cleaned up ${sessions.length - validSessions.length} old sessions`);
            }
        } catch (e) {
            console.error('CUFT: Cleanup failed', e);
        }
    }

    /**
     * Generate unique session ID
     * @returns {string} Unique ID
     */
    generateId() {
        return 'cuft_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Get storage usage statistics
     * @returns {Object} Storage stats
     */
    getStorageStats() {
        if (!this.storageAvailable) {
            return {
                available: false,
                sessions: 0,
                sizeBytes: 0,
                sizeKB: 0,
                percentUsed: 0
            };
        }

        try {
            const sessions = this.getSessions();
            const serialized = JSON.stringify(sessions);
            const sizeBytes = new Blob([serialized]).size;
            const maxSize = 5 * 1024 * 1024; // 5MB

            return {
                available: true,
                sessions: sessions.length,
                sizeBytes: sizeBytes,
                sizeKB: Math.round(sizeBytes / 1024 * 100) / 100,
                percentUsed: Math.round((sizeBytes / maxSize) * 100 * 100) / 100
            };
        } catch (e) {
            console.error('CUFT: Failed to get storage stats', e);
            return {
                available: true,
                sessions: 0,
                sizeBytes: 0,
                sizeKB: 0,
                percentUsed: 0
            };
        }
    }
}

// Initialize storage manager
const cuftStorage = new CuftStorageManager();
```

### Usage Examples

```javascript
// Save a test session
const session = {
    name: 'Contact Form Test',
    events: [
        { type: 'form_submit', timestamp: Date.now(), data: {...} },
        { type: 'generate_lead', timestamp: Date.now(), data: {...} }
    ],
    metadata: {
        browser: navigator.userAgent,
        viewport: `${window.innerWidth}x${window.innerHeight}`
    }
};

if (cuftStorage.saveSession(session)) {
    console.log('Session saved successfully');
}

// Retrieve all sessions
const sessions = cuftStorage.getSessions();
console.log(`Found ${sessions.length} test sessions`);

// Delete specific session
cuftStorage.deleteSession('cuft_1234567890_abc123');

// Get storage usage
const stats = cuftStorage.getStorageStats();
console.log(`Using ${stats.percentUsed}% of localStorage (${stats.sizeKB} KB)`);
```

### Key Considerations

1. **Size Limits**:
   - 5MB typical limit (varies by browser)
   - Monitor usage with `getStorageStats()`
   - Implement automatic cleanup at 90% threshold

2. **Error Handling**:
   - `QuotaExceededError`: Storage full
   - `SecurityError`: Private browsing or disabled
   - `SyntaxError`: Invalid JSON data

3. **Data Validation**:
   - Always validate structure after `JSON.parse()`
   - Check for required fields
   - Handle corrupted data gracefully

4. **Performance**:
   - localStorage is synchronous (blocks main thread)
   - Minimize read/write frequency
   - Consider IndexedDB for large datasets (>1MB)

### Alternatives Considered

1. **IndexedDB**:
   - **Pros**: Much larger storage (50% of disk), asynchronous
   - **Cons**: More complex API, overkill for test sessions
   - **Decision**: Use localStorage for simplicity, IndexedDB for future if needed

2. **sessionStorage**:
   - **Pros**: Automatic cleanup on tab close
   - **Cons**: Lost on refresh, not shared across tabs
   - **Decision**: Rejected, need persistence across sessions

3. **WordPress Transients API** (PHP):
   - **Pros**: Server-side storage, no client limits
   - **Cons**: Requires AJAX calls, slower, database overhead
   - **Decision**: Use for session sync/backup, not primary storage

---

## 5. DataLayer Event Monitoring

### Decision: Intercept `window.dataLayer.push()` method to capture events in real-time

### Rationale
- GTM works by redefining `Array.prototype.push()` for dataLayer
- Safe to create middleware that wraps GTM's implementation
- Allows real-time event capture without polling
- Minimal performance overhead
- Non-breaking if GTM not present

### Implementation Pattern

```javascript
/**
 * CUFT DataLayer Monitor
 *
 * Intercepts window.dataLayer.push to capture events in real-time.
 * Safe implementation that preserves GTM functionality.
 */
class CuftDataLayerMonitor {
    constructor() {
        this.events = [];
        this.maxEvents = 100; // Prevent memory bloat
        this.listeners = [];

        // Initialize dataLayer if not present
        window.dataLayer = window.dataLayer || [];

        // Store reference to original push method
        this.originalPush = window.dataLayer.push;

        // Intercept dataLayer.push
        this.interceptDataLayer();
    }

    /**
     * Intercept dataLayer.push method
     * Creates middleware around original implementation
     */
    interceptDataLayer() {
        const self = this;

        // Replace push method with interceptor
        window.dataLayer.push = function() {
            // Capture the pushed data
            const pushedData = Array.from(arguments);

            // Process each pushed object
            pushedData.forEach(data => {
                if (data && typeof data === 'object') {
                    self.captureEvent(data);
                }
            });

            // Call original push (GTM or Array.prototype.push)
            // CRITICAL: Must forward to original to maintain GTM functionality
            return self.originalPush.apply(window.dataLayer, arguments);
        };
    }

    /**
     * Capture dataLayer event
     * @param {Object} eventData - Event data from dataLayer.push
     */
    captureEvent(eventData) {
        try {
            // Clone data to prevent mutations
            const event = {
                timestamp: Date.now(),
                data: JSON.parse(JSON.stringify(eventData)),
                url: window.location.href,
                userAgent: navigator.userAgent
            };

            // Add to events array
            this.events.push(event);

            // Enforce max events limit (FIFO)
            if (this.events.length > this.maxEvents) {
                this.events = this.events.slice(-this.maxEvents);
            }

            // Notify listeners
            this.notifyListeners(event);

            // Log to console if debug mode
            if (window.cuftDebug) {
                console.log('CUFT DataLayer Event:', event);
            }

        } catch (e) {
            console.error('CUFT: Failed to capture event', e);
        }
    }

    /**
     * Register event listener
     * @param {Function} callback - Called when event captured
     * @returns {Function} Unsubscribe function
     */
    onEvent(callback) {
        if (typeof callback !== 'function') {
            throw new Error('Callback must be a function');
        }

        this.listeners.push(callback);

        // Return unsubscribe function
        return () => {
            this.listeners = this.listeners.filter(cb => cb !== callback);
        };
    }

    /**
     * Notify all registered listeners
     * @param {Object} event - Event data
     */
    notifyListeners(event) {
        this.listeners.forEach(callback => {
            try {
                callback(event);
            } catch (e) {
                console.error('CUFT: Listener error', e);
            }
        });
    }

    /**
     * Get all captured events
     * @param {Object} filters - Optional filters
     * @returns {Array} Filtered events
     */
    getEvents(filters = {}) {
        let filtered = [...this.events];

        // Filter by event type
        if (filters.eventType) {
            filtered = filtered.filter(e =>
                e.data.event === filters.eventType
            );
        }

        // Filter by CUFT tracked events only
        if (filters.cuftOnly) {
            filtered = filtered.filter(e =>
                e.data.cuft_tracked === true
            );
        }

        // Filter by time range
        if (filters.since) {
            filtered = filtered.filter(e =>
                e.timestamp >= filters.since
            );
        }

        return filtered;
    }

    /**
     * Get events by type
     * @param {string} eventType - Event type to filter
     * @returns {Array} Matching events
     */
    getEventsByType(eventType) {
        return this.getEvents({ eventType });
    }

    /**
     * Get CUFT tracked events only
     * @returns {Array} CUFT events
     */
    getCuftEvents() {
        return this.getEvents({ cuftOnly: true });
    }

    /**
     * Clear captured events
     */
    clearEvents() {
        this.events = [];
    }

    /**
     * Export events as JSON
     * @returns {string} JSON string
     */
    exportEvents() {
        try {
            return JSON.stringify(this.events, null, 2);
        } catch (e) {
            console.error('CUFT: Failed to export events', e);
            return '[]';
        }
    }

    /**
     * Get statistics about captured events
     * @returns {Object} Event statistics
     */
    getStats() {
        const stats = {
            total: this.events.length,
            cuftTracked: 0,
            byType: {},
            bySource: {},
            timeRange: {
                earliest: null,
                latest: null
            }
        };

        this.events.forEach(event => {
            // Count CUFT tracked
            if (event.data.cuft_tracked) {
                stats.cuftTracked++;
            }

            // Count by event type
            const eventType = event.data.event || 'unknown';
            stats.byType[eventType] = (stats.byType[eventType] || 0) + 1;

            // Count by source
            if (event.data.cuft_source) {
                const source = event.data.cuft_source;
                stats.bySource[source] = (stats.bySource[source] || 0) + 1;
            }

            // Track time range
            if (!stats.timeRange.earliest || event.timestamp < stats.timeRange.earliest) {
                stats.timeRange.earliest = event.timestamp;
            }
            if (!stats.timeRange.latest || event.timestamp > stats.timeRange.latest) {
                stats.timeRange.latest = event.timestamp;
            }
        });

        return stats;
    }
}

// Initialize monitor
const cuftDataLayerMonitor = new CuftDataLayerMonitor();

// Register listener for real-time updates
cuftDataLayerMonitor.onEvent((event) => {
    // Update UI with new event
    updateDashboard(event);
});
```

### Dashboard Integration Example

```javascript
/**
 * Update dashboard with new event
 * Real-time event display in testing dashboard
 */
function updateDashboard(event) {
    // Get events table
    const table = document.getElementById('cuft-events-table');
    if (!table) return;

    // Create new row
    const row = document.createElement('tr');

    // Format timestamp
    const timestamp = new Date(event.timestamp).toLocaleString();

    // Format event type
    const eventType = event.data.event || 'unknown';

    // Format data (collapsed JSON)
    const dataStr = JSON.stringify(event.data, null, 2);

    row.innerHTML = `
        <td><span class="event-type event-type-${eventType}">${eventType}</span></td>
        <td>${timestamp}</td>
        <td>
            <details>
                <summary>View Data</summary>
                <pre>${escapeHtml(dataStr)}</pre>
            </details>
        </td>
        <td>
            ${event.data.cuft_tracked ? '<span class="badge badge-success">CUFT</span>' : ''}
            ${event.data.cuft_source ? `<span class="badge badge-info">${event.data.cuft_source}</span>` : ''}
        </td>
    `;

    // Add to table (prepend for newest first)
    const tbody = table.querySelector('tbody');
    tbody.insertBefore(row, tbody.firstChild);

    // Limit visible rows
    const maxRows = 50;
    while (tbody.children.length > maxRows) {
        tbody.removeChild(tbody.lastChild);
    }

    // Update event count badge
    const badge = document.getElementById('event-count');
    if (badge) {
        badge.textContent = cuftDataLayerMonitor.events.length;
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
```

### Key Safety Considerations

1. **Preserve Original Functionality**: MUST call original push method
2. **Error Isolation**: Wrap all event processing in try-catch
3. **Memory Management**: Enforce max events limit
4. **Clone Data**: Prevent mutations to original event objects
5. **XSS Prevention**: Escape all displayed event data

### Performance Considerations

1. **Minimal Overhead**: <1ms per event in most cases
2. **Async Processing**: Consider `requestIdleCallback` for heavy processing
3. **Memory Limit**: 100 events max, FIFO cleanup
4. **Debouncing**: For high-frequency events, consider debouncing UI updates

### Alternatives Considered

1. **Polling dataLayer array**:
   - **Rejected**: High CPU usage, misses events between polls

2. **MutationObserver on dataLayer**:
   - **Rejected**: dataLayer is array, not DOM element

3. **Proxy wrapper around dataLayer**:
   - **Considered**: Modern approach, but less compatible with GTM's own interception

---

## 6. WordPress Custom Database Tables

### Decision: Use `dbDelta()` with version tracking in wp_options for schema management

### Rationale
- Follows WordPress standards for plugin database tables
- `dbDelta()` safely handles both creation and updates
- Version tracking enables safe schema migrations
- Consistent with existing CUFT plugin architecture

### Implementation Pattern

```php
/**
 * Testing Dashboard Database Manager
 *
 * Handles creation and versioning of custom database tables
 * for storing test session data server-side.
 */
class CUFT_Testing_Database {

    /**
     * Database version
     * Increment when schema changes
     */
    const DB_VERSION = '1.0';

    /**
     * Table name (without prefix)
     */
    const TABLE_NAME = 'cuft_test_sessions';

    /**
     * Create or update database table
     * Called on plugin activation and version check
     */
    public static function create_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        // CRITICAL: dbDelta requires specific formatting
        // - Each field on own line
        // - Two spaces between PRIMARY KEY and definition
        // - Use KEY not INDEX
        // - No backticks around field names
        // - Field types lowercase

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(255) NOT NULL,
            session_name varchar(255) NOT NULL,
            user_id bigint(20) unsigned DEFAULT NULL,
            events longtext DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            date_created datetime DEFAULT CURRENT_TIMESTAMP,
            date_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY date_created (date_created)
        ) $charset_collate;";

        // Load dbDelta function
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Execute table creation/update
        $result = dbDelta($sql);

        // Update database version
        update_option('cuft_testing_db_version', self::DB_VERSION);

        // Log result
        if (class_exists('CUFT_Logger')) {
            CUFT_Logger::log('info', 'Testing database table created/updated', array(
                'table' => $table_name,
                'result' => $result,
                'version' => self::DB_VERSION
            ));
        }

        return $result;
    }

    /**
     * Check if database needs update
     * Called on plugins_loaded hook
     */
    public static function maybe_update_database() {
        $installed_version = get_option('cuft_testing_db_version', '0.0');

        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_table();
        }
    }

    /**
     * Drop database table
     * Called on plugin uninstall (not deactivation)
     */
    public static function drop_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;

        // Drop table
        $wpdb->query("DROP TABLE IF EXISTS $table_name");

        // Remove version option
        delete_option('cuft_testing_db_version');

        // Log
        if (class_exists('CUFT_Logger')) {
            CUFT_Logger::log('info', 'Testing database table dropped', array(
                'table' => $table_name
            ));
        }
    }
}
```

### CRUD Operations with wpdb Prepared Statements

```php
/**
 * CRUD operations for test sessions
 * All queries use wpdb::prepare() for SQL injection prevention
 */
class CUFT_Testing_Sessions {

    /**
     * Insert new test session
     *
     * @param array $data Session data
     * @return int|false Insert ID or false on failure
     */
    public static function insert_session($data) {
        global $wpdb;

        $table_name = $wpdb->prefix . CUFT_Testing_Database::TABLE_NAME;

        // Validate required fields
        if (empty($data['session_id']) || empty($data['session_name'])) {
            return false;
        }

        // Prepare data for insertion
        $insert_data = array(
            'session_id' => sanitize_text_field($data['session_id']),
            'session_name' => sanitize_text_field($data['session_name']),
            'user_id' => get_current_user_id(),
            'events' => is_array($data['events']) ? json_encode($data['events']) : '[]',
            'metadata' => is_array($data['metadata']) ? json_encode($data['metadata']) : '{}'
        );

        // Define data types for each field
        $format = array(
            '%s', // session_id (string)
            '%s', // session_name (string)
            '%d', // user_id (integer)
            '%s', // events (string/JSON)
            '%s'  // metadata (string/JSON)
        );

        // wpdb::insert automatically uses prepare()
        $result = $wpdb->insert(
            $table_name,
            $insert_data,
            $format
        );

        if ($result === false) {
            if (class_exists('CUFT_Logger')) {
                CUFT_Logger::log('error', 'Failed to insert test session', array(
                    'error' => $wpdb->last_error,
                    'data' => $insert_data
                ));
            }
            return false;
        }

        return $wpdb->insert_id;
    }

    /**
     * Update existing test session
     *
     * @param string $session_id Session ID to update
     * @param array $data Updated session data
     * @return bool Success status
     */
    public static function update_session($session_id, $data) {
        global $wpdb;

        $table_name = $wpdb->prefix . CUFT_Testing_Database::TABLE_NAME;

        // Prepare update data
        $update_data = array();

        if (isset($data['session_name'])) {
            $update_data['session_name'] = sanitize_text_field($data['session_name']);
        }

        if (isset($data['events'])) {
            $update_data['events'] = is_array($data['events'])
                ? json_encode($data['events'])
                : sanitize_textarea_field($data['events']);
        }

        if (isset($data['metadata'])) {
            $update_data['metadata'] = is_array($data['metadata'])
                ? json_encode($data['metadata'])
                : sanitize_textarea_field($data['metadata']);
        }

        if (empty($update_data)) {
            return false;
        }

        // wpdb::update automatically uses prepare()
        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('session_id' => sanitize_text_field($session_id)),
            null, // format auto-detected
            array('%s') // where format
        );

        return $result !== false;
    }

    /**
     * Get test session by ID
     *
     * @param string $session_id Session ID
     * @return object|null Session object or null
     */
    public static function get_session($session_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . CUFT_Testing_Database::TABLE_NAME;

        // Use wpdb::prepare for safe query
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s LIMIT 1",
            sanitize_text_field($session_id)
        );

        $session = $wpdb->get_row($query);

        if (!$session) {
            return null;
        }

        // Decode JSON fields
        if (!empty($session->events)) {
            $session->events = json_decode($session->events, true);
        }

        if (!empty($session->metadata)) {
            $session->metadata = json_decode($session->metadata, true);
        }

        return $session;
    }

    /**
     * Get all test sessions for current user
     *
     * @param array $args Query arguments (limit, offset, orderby)
     * @return array Array of session objects
     */
    public static function get_sessions($args = array()) {
        global $wpdb;

        $table_name = $wpdb->prefix . CUFT_Testing_Database::TABLE_NAME;

        // Parse arguments
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'date_created',
            'order' => 'DESC',
            'user_id' => get_current_user_id()
        );

        $args = wp_parse_args($args, $defaults);

        // Validate orderby (prevent SQL injection)
        $allowed_orderby = array('id', 'session_name', 'date_created', 'date_updated');
        if (!in_array($args['orderby'], $allowed_orderby, true)) {
            $args['orderby'] = 'date_created';
        }

        // Validate order
        $args['order'] = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        // Build query with prepare
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name
             WHERE user_id = %d
             ORDER BY {$args['orderby']} {$args['order']}
             LIMIT %d OFFSET %d",
            $args['user_id'],
            $args['limit'],
            $args['offset']
        );

        $sessions = $wpdb->get_results($query);

        // Decode JSON fields for each session
        foreach ($sessions as $session) {
            if (!empty($session->events)) {
                $session->events = json_decode($session->events, true);
            }
            if (!empty($session->metadata)) {
                $session->metadata = json_decode($session->metadata, true);
            }
        }

        return $sessions;
    }

    /**
     * Delete test session
     *
     * @param string $session_id Session ID to delete
     * @return bool Success status
     */
    public static function delete_session($session_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . CUFT_Testing_Database::TABLE_NAME;

        // Verify ownership before deleting
        $session = self::get_session($session_id);

        if (!$session || $session->user_id != get_current_user_id()) {
            return false;
        }

        // wpdb::delete automatically uses prepare()
        $result = $wpdb->delete(
            $table_name,
            array('session_id' => sanitize_text_field($session_id)),
            array('%s')
        );

        return $result !== false;
    }

    /**
     * Delete old sessions (cleanup)
     *
     * @param int $days Delete sessions older than X days
     * @return int Number of deleted sessions
     */
    public static function delete_old_sessions($days = 30) {
        global $wpdb;

        $table_name = $wpdb->prefix . CUFT_Testing_Database::TABLE_NAME;

        // Calculate cutoff date
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete old sessions
        $query = $wpdb->prepare(
            "DELETE FROM $table_name WHERE date_created < %s",
            $cutoff
        );

        $result = $wpdb->query($query);

        return $result !== false ? $result : 0;
    }
}
```

### Database Table Registration

```php
/**
 * Register database hooks
 * In main plugin file or init class
 */
register_activation_hook(__FILE__, array('CUFT_Testing_Database', 'create_table'));
add_action('plugins_loaded', array('CUFT_Testing_Database', 'maybe_update_database'));

// Uninstall hook (in uninstall.php, not main file)
// CUFT_Testing_Database::drop_table();
```

### Key Requirements

1. **dbDelta Formatting**:
   - Each field on separate line
   - Two spaces between `PRIMARY KEY` and definition
   - Use `KEY` not `INDEX`
   - No backticks around field names
   - Lowercase field types

2. **Version Tracking**:
   - Store version in `wp_options`
   - Check version on `plugins_loaded`
   - Increment version when schema changes

3. **Prepared Statements**:
   - Use `wpdb::prepare()` for ALL user input
   - Use `%s` for strings, `%d` for integers, `%f` for floats
   - `wpdb::insert()`, `wpdb::update()`, `wpdb::delete()` use prepare internally

4. **JSON Storage**:
   - Use `longtext` for JSON columns
   - Encode with `json_encode()` before insert
   - Decode with `json_decode()` after select
   - Validate structure after decoding

### Alternatives Considered

1. **WordPress Transients API**:
   - **Rejected**: Automatic expiration not suitable for persistent test data

2. **WordPress Options API**:
   - **Rejected**: Not designed for multiple records, poor performance

3. **Custom table with direct SQL**:
   - **Rejected**: `dbDelta()` provides safe update mechanism

---

## 7. AJAX Endpoint Security

### Decision: Implement dedicated AJAX handler class with nonce validation and capability checks

### Rationale
- Follows WordPress AJAX security best practices
- Separates public and admin AJAX endpoints
- Uses `check_ajax_referer()` for automatic nonce validation
- Consistent with existing CUFT plugin architecture (see `CUFT_Event_Recorder`)

### Implementation Pattern

```php
/**
 * CUFT Testing Dashboard AJAX Handler
 *
 * Handles all AJAX requests for testing dashboard with security validation.
 */
class CUFT_Testing_AJAX {

    /**
     * Constructor - register AJAX hooks
     */
    public function __construct() {
        // Admin-only endpoints (wp_ajax_)
        add_action('wp_ajax_cuft_save_test_session', array($this, 'save_test_session'));
        add_action('wp_ajax_cuft_load_test_session', array($this, 'load_test_session'));
        add_action('wp_ajax_cuft_delete_test_session', array($this, 'delete_test_session'));
        add_action('wp_ajax_cuft_export_test_data', array($this, 'export_test_data'));

        // NO wp_ajax_nopriv_ hooks - testing dashboard is admin-only
    }

    /**
     * Save test session via AJAX
     *
     * Security: Admin-only, nonce validation, capability check
     */
    public function save_test_session() {
        try {
            // Layer 1: Verify nonce
            // check_ajax_referer dies on failure by default
            check_ajax_referer('cuft-testing-dashboard', 'nonce');

            // Layer 2: Verify capability
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => 'Insufficient permissions'
                ), 403);
                return;
            }

            // Layer 3: Validate and sanitize inputs
            $session_name = isset($_POST['session_name'])
                ? sanitize_text_field($_POST['session_name'])
                : '';

            $events_json = isset($_POST['events'])
                ? sanitize_textarea_field($_POST['events'])
                : '';

            // Validate required fields
            if (empty($session_name)) {
                wp_send_json_error(array(
                    'message' => 'Session name is required'
                ), 400);
                return;
            }

            // Validate JSON format
            $events = json_decode($events_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json_error(array(
                    'message' => 'Invalid events data format'
                ), 400);
                return;
            }

            // Validate events structure
            if (!is_array($events)) {
                wp_send_json_error(array(
                    'message' => 'Events must be an array'
                ), 400);
                return;
            }

            // Generate session ID if not provided
            $session_id = isset($_POST['session_id']) && !empty($_POST['session_id'])
                ? sanitize_text_field($_POST['session_id'])
                : $this->generate_session_id();

            // Prepare session data
            $session_data = array(
                'session_id' => $session_id,
                'session_name' => $session_name,
                'events' => $events,
                'metadata' => array(
                    'user_agent' => isset($_SERVER['HTTP_USER_AGENT'])
                        ? sanitize_text_field($_SERVER['HTTP_USER_AGENT'])
                        : '',
                    'ip_address' => $this->get_client_ip(),
                    'saved_at' => current_time('mysql')
                )
            );

            // Save to database
            $result = CUFT_Testing_Sessions::insert_session($session_data);

            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Test session saved successfully',
                    'session_id' => $session_id,
                    'insert_id' => $result
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Failed to save test session'
                ), 500);
            }

        } catch (Exception $e) {
            // Log error but don't expose details to client
            if (class_exists('CUFT_Logger') && defined('WP_DEBUG') && WP_DEBUG) {
                CUFT_Logger::log('error', 'AJAX save session exception', array(
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ));
            }

            wp_send_json_error(array(
                'message' => 'Internal server error'
            ), 500);
        }
    }

    /**
     * Load test session via AJAX
     *
     * Security: Admin-only, nonce validation, capability check, ownership verification
     */
    public function load_test_session() {
        try {
            // Nonce validation
            check_ajax_referer('cuft-testing-dashboard', 'nonce');

            // Capability check
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => 'Insufficient permissions'
                ), 403);
                return;
            }

            // Validate session_id parameter
            $session_id = isset($_GET['session_id'])
                ? sanitize_text_field($_GET['session_id'])
                : '';

            if (empty($session_id)) {
                wp_send_json_error(array(
                    'message' => 'Session ID is required'
                ), 400);
                return;
            }

            // Load session from database
            $session = CUFT_Testing_Sessions::get_session($session_id);

            if (!$session) {
                wp_send_json_error(array(
                    'message' => 'Session not found'
                ), 404);
                return;
            }

            // Verify ownership (only allow user's own sessions)
            if ($session->user_id != get_current_user_id()) {
                wp_send_json_error(array(
                    'message' => 'Access denied'
                ), 403);
                return;
            }

            wp_send_json_success(array(
                'session' => $session
            ));

        } catch (Exception $e) {
            if (class_exists('CUFT_Logger') && defined('WP_DEBUG') && WP_DEBUG) {
                CUFT_Logger::log('error', 'AJAX load session exception', array(
                    'message' => $e->getMessage()
                ));
            }

            wp_send_json_error(array(
                'message' => 'Internal server error'
            ), 500);
        }
    }

    /**
     * Delete test session via AJAX
     *
     * Security: Admin-only, nonce validation, capability check, ownership verification
     */
    public function delete_test_session() {
        try {
            // Nonce validation (POST requests)
            check_ajax_referer('cuft-testing-dashboard', 'nonce');

            // Capability check
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => 'Insufficient permissions'
                ), 403);
                return;
            }

            // Validate session_id
            $session_id = isset($_POST['session_id'])
                ? sanitize_text_field($_POST['session_id'])
                : '';

            if (empty($session_id)) {
                wp_send_json_error(array(
                    'message' => 'Session ID is required'
                ), 400);
                return;
            }

            // Delete session (includes ownership verification)
            $result = CUFT_Testing_Sessions::delete_session($session_id);

            if ($result) {
                wp_send_json_success(array(
                    'message' => 'Test session deleted successfully'
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Failed to delete session or session not found'
                ), 404);
            }

        } catch (Exception $e) {
            if (class_exists('CUFT_Logger') && defined('WP_DEBUG') && WP_DEBUG) {
                CUFT_Logger::log('error', 'AJAX delete session exception', array(
                    'message' => $e->getMessage()
                ));
            }

            wp_send_json_error(array(
                'message' => 'Internal server error'
            ), 500);
        }
    }

    /**
     * Export test data as JSON
     *
     * Security: Admin-only, nonce validation, capability check
     */
    public function export_test_data() {
        try {
            // Nonce validation
            check_ajax_referer('cuft-testing-dashboard', 'nonce');

            // Capability check
            if (!current_user_can('manage_options')) {
                wp_send_json_error(array(
                    'message' => 'Insufficient permissions'
                ), 403);
                return;
            }

            // Get all sessions for current user
            $sessions = CUFT_Testing_Sessions::get_sessions(array(
                'limit' => 1000, // High limit for export
                'orderby' => 'date_created',
                'order' => 'DESC'
            ));

            // Prepare export data
            $export = array(
                'export_date' => current_time('mysql'),
                'export_by' => get_current_user_id(),
                'session_count' => count($sessions),
                'sessions' => $sessions
            );

            // Send as JSON download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="cuft-test-sessions-' . date('Y-m-d') . '.json"');
            echo json_encode($export, JSON_PRETTY_PRINT);
            exit;

        } catch (Exception $e) {
            if (class_exists('CUFT_Logger') && defined('WP_DEBUG') && WP_DEBUG) {
                CUFT_Logger::log('error', 'AJAX export exception', array(
                    'message' => $e->getMessage()
                ));
            }

            wp_send_json_error(array(
                'message' => 'Internal server error'
            ), 500);
        }
    }

    /**
     * Generate unique session ID
     *
     * @return string Unique session ID
     */
    private function generate_session_id() {
        return 'cuft_test_' . uniqid() . '_' . wp_generate_password(8, false);
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }
}

// Initialize AJAX handler
new CUFT_Testing_AJAX();
```

### JavaScript Client Side

```javascript
/**
 * AJAX helper for testing dashboard
 */
class CuftTestingAjax {
    constructor() {
        // Get nonce from localized script
        this.nonce = cuftTestingData.nonce;
        this.ajaxUrl = cuftTestingData.ajaxUrl;
    }

    /**
     * Save test session
     * @param {Object} sessionData - Session data
     * @returns {Promise} AJAX promise
     */
    async saveSession(sessionData) {
        const formData = new FormData();
        formData.append('action', 'cuft_save_test_session');
        formData.append('nonce', this.nonce);
        formData.append('session_name', sessionData.name);
        formData.append('events', JSON.stringify(sessionData.events));

        if (sessionData.id) {
            formData.append('session_id', sessionData.id);
        }

        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data.message || 'Failed to save session');
            }

            return data.data;

        } catch (error) {
            console.error('CUFT: Save session failed', error);
            throw error;
        }
    }

    /**
     * Load test session
     * @param {string} sessionId - Session ID
     * @returns {Promise} AJAX promise
     */
    async loadSession(sessionId) {
        try {
            const response = await fetch(
                `${this.ajaxUrl}?action=cuft_load_test_session&session_id=${encodeURIComponent(sessionId)}&nonce=${this.nonce}`,
                { method: 'GET' }
            );

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data.message || 'Failed to load session');
            }

            return data.data.session;

        } catch (error) {
            console.error('CUFT: Load session failed', error);
            throw error;
        }
    }

    /**
     * Delete test session
     * @param {string} sessionId - Session ID
     * @returns {Promise} AJAX promise
     */
    async deleteSession(sessionId) {
        const formData = new FormData();
        formData.append('action', 'cuft_delete_test_session');
        formData.append('nonce', this.nonce);
        formData.append('session_id', sessionId);

        try {
            const response = await fetch(this.ajaxUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.data.message || 'Failed to delete session');
            }

            return data.data;

        } catch (error) {
            console.error('CUFT: Delete session failed', error);
            throw error;
        }
    }
}

// Initialize AJAX handler
const cuftTestingAjax = new CuftTestingAjax();
```

### Enqueue Script with Localized Data

```php
/**
 * Enqueue testing dashboard scripts
 */
public function enqueue_testing_scripts($hook) {
    // Only load on testing dashboard page
    if ($hook !== 'settings_page_cuft-testing-dashboard') {
        return;
    }

    // Enqueue JavaScript
    wp_enqueue_script(
        'cuft-testing-dashboard',
        CUFT_PLUGIN_URL . 'assets/admin/js/testing-dashboard.js',
        array('jquery'),
        CUFT_VERSION,
        true
    );

    // Localize script with AJAX data
    wp_localize_script('cuft-testing-dashboard', 'cuftTestingData', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('cuft-testing-dashboard'),
        'userId' => get_current_user_id(),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
}
add_action('admin_enqueue_scripts', array($this, 'enqueue_testing_scripts'));
```

### Key Security Layers

1. **Nonce Validation**: `check_ajax_referer()` dies on failure
2. **Capability Check**: `current_user_can('manage_options')`
3. **Input Sanitization**: All `$_POST`/`$_GET` data sanitized
4. **Ownership Verification**: Users can only access own sessions
5. **Error Masking**: Don't expose internal errors to client
6. **JSON Validation**: Verify structure after decode
7. **Admin-Only Hooks**: No `wp_ajax_nopriv_` hooks

### Alternatives Considered

1. **REST API endpoints**:
   - **Pros**: Modern, standardized, versioned
   - **Cons**: More complex setup, overkill for admin-only
   - **Decision**: Consider for public API, use AJAX for admin

2. **Custom endpoint handler**:
   - **Rejected**: WordPress AJAX API provides security out-of-box

---

## 8. Implementation Recommendations

### High-Priority Decisions

1. **Start with localStorage + dataLayer monitoring**:
   - Fastest to implement
   - Provides immediate value
   - No database changes required

2. **Add database table for persistence**:
   - Implement after localStorage working
   - Provides session sync across devices
   - Enables admin management features

3. **Build UI incrementally**:
   - Phase 1: Basic event display
   - Phase 2: Session management
   - Phase 3: Export/import features
   - Phase 4: Analytics dashboard

### Code Organization

```
choice-uft/
├── includes/
│   ├── admin/
│   │   ├── class-cuft-testing-dashboard.php     # Main admin page
│   │   ├── class-cuft-testing-ajax.php          # AJAX handlers
│   │   ├── class-cuft-testing-database.php      # Database manager
│   │   ├── class-cuft-testing-sessions.php      # CRUD operations
│   │   └── templates/
│   │       └── testing-dashboard.php            # Dashboard template
│   └── ...
├── assets/
│   └── admin/
│       ├── css/
│       │   └── testing-dashboard.css
│       └── js/
│           ├── testing-dashboard.js             # Main dashboard script
│           ├── storage-manager.js               # localStorage wrapper
│           └── datalayer-monitor.js             # dataLayer interceptor
└── ...
```

### Performance Considerations

1. **Lazy Loading**: Only load dashboard assets on testing page
2. **Event Limits**: Cap at 100 events in memory, 50 in UI
3. **Debouncing**: Debounce UI updates for high-frequency events
4. **Pagination**: For session list when >50 sessions
5. **Background Cleanup**: Cron job for old session cleanup

### Testing Strategy

1. **Unit Tests**: localStorage manager, dataLayer monitor
2. **Integration Tests**: AJAX endpoints, CRUD operations
3. **Security Tests**: Nonce validation, capability checks, SQL injection
4. **Browser Tests**: localStorage availability, dataLayer interception
5. **Performance Tests**: Event capture overhead, storage limits

---

## 9. Constitutional Compliance Checklist

### JavaScript-First Principle
- ✅ All client-side code uses vanilla JavaScript
- ✅ No jQuery dependencies for core functionality
- ✅ Multiple fallback methods implemented

### Security Requirements
- ✅ All AJAX endpoints use nonce validation
- ✅ All database queries use prepared statements
- ✅ All inputs sanitized, all outputs escaped
- ✅ Capability checks on all admin functions

### Error Handling
- ✅ Try-catch blocks around all external operations
- ✅ Graceful degradation when localStorage unavailable
- ✅ Silent failures don't break core functionality
- ✅ Error logging for debugging, not user-facing

### Performance Constraints
- ✅ Event monitoring overhead <1ms per event
- ✅ UI updates debounced for high-frequency events
- ✅ Memory limits enforced (100 events max)
- ✅ Lazy loading of dashboard assets

---

## 10. References & Resources

### Official WordPress Documentation
- [Developer Resources](https://developer.wordpress.org/)
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Common APIs Handbook](https://developer.wordpress.org/apis/)
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/)

### Security Resources
- [WordPress Security Guide](https://developer.wordpress.org/apis/security/)
- [Nonces Documentation](https://developer.wordpress.org/apis/security/nonces/)
- [Data Validation](https://developer.wordpress.org/apis/security/data-validation/)

### Database Resources
- [wpdb Class Reference](https://developer.wordpress.org/reference/classes/wpdb/)
- [dbDelta Function](https://developer.wordpress.org/reference/functions/dbdelta/)
- [Creating Tables](https://codex.wordpress.org/Creating_Tables_with_Plugins)

### UI Resources
- [WP-Admin Reference](https://wpadmin.bracketspace.com/)
- [WordPress Components](https://developer.wordpress.org/block-editor/reference-guides/components/)

### JavaScript Resources
- [Web Storage API](https://developer.mozilla.org/en-US/docs/Web/API/Web_Storage_API)
- [Storage Quotas](https://developer.mozilla.org/en-US/docs/Web/API/Storage_API/Storage_quotas_and_eviction_criteria)

---

## Version History

- **1.0** (2025-09-30): Initial research document
  - Admin page registration patterns
  - Security best practices
  - UI component standards
  - localStorage management
  - DataLayer monitoring techniques
  - Database table creation
  - AJAX endpoint security
  - Implementation recommendations
