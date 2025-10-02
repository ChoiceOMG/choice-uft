<?php
/**
 * Testing Dashboard View Template
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('CUFT Testing Dashboard', 'choice-uft'); ?></h1>

    <?php
    $gtm_id = get_option('cuft_gtm_id');
    if (empty($gtm_id)) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('GTM Not Configured:', 'choice-uft'); ?></strong>
                <?php _e('Google Tag Manager ID is not set. DataLayer events will still be visible in the console, but GTM will not process them.', 'choice-uft'); ?>
                <a href="<?php echo admin_url('options-general.php?page=choice-universal-form-tracker&tab=settings'); ?>"><?php _e('Configure GTM', 'choice-uft'); ?></a>
            </p>
        </div>
        <?php
    } else {
        ?>
        <div class="notice notice-success">
            <p>
                <strong><?php _e('GTM Active:', 'choice-uft'); ?></strong>
                <?php printf(__('Google Tag Manager is loaded with ID: %s. Open browser console to see dataLayer events.', 'choice-uft'), '<code>' . esc_html($gtm_id) . '</code>'); ?>
            </p>
        </div>
        <?php
    }
    ?>

    <div class="notice notice-info">
        <p><?php _e('This dashboard is for testing conversion tracking features. All events generated here include a test_mode flag and are stored separately from production data.', 'choice-uft'); ?></p>
    </div>

    <?php wp_nonce_field('cuft-testing-dashboard', 'cuft_testing_nonce'); ?>

    <!-- Test Data Generator Section -->
    <div class="card">
        <h2><?php _e('Test Data Generator', 'choice-uft'); ?></h2>
        <p><?php _e('Generate realistic test data including click IDs, UTM parameters, and contact information.', 'choice-uft'); ?></p>

        <button type="button" class="button button-primary" id="cuft-generate-test-data">
            <?php _e('Generate Sample Data', 'choice-uft'); ?>
        </button>

        <div id="cuft-test-data-display" class="test-data-display" style="display:none;">
            <h3><?php _e('Generated Test Data:', 'choice-uft'); ?></h3>
            <div class="test-data-content"></div>
        </div>
    </div>

    <!-- Event Simulator Section -->
    <div class="card">
        <h2><?php _e('Event Simulator', 'choice-uft'); ?></h2>
        <p><?php _e('Simulate various tracking events with the generated test data.', 'choice-uft'); ?></p>

        <div class="button-group">
            <button type="button" class="button" id="cuft-simulate-phone-click">
                <?php _e('Simulate Phone Click', 'choice-uft'); ?>
            </button>
            <button type="button" class="button" id="cuft-simulate-email-click">
                <?php _e('Simulate Email Click', 'choice-uft'); ?>
            </button>
            <button type="button" class="button" id="cuft-simulate-form-submit">
                <?php _e('Simulate Form Submission', 'choice-uft'); ?>
            </button>
            <button type="button" class="button" id="cuft-simulate-generate-lead">
                <?php _e('Simulate Lead Generation', 'choice-uft'); ?>
            </button>
        </div>

        <div id="cuft-simulation-status" class="simulation-status"></div>
    </div>

    <!-- Test Form Builder Section -->
    <div class="card">
        <h2><?php _e('Test Form Builder', 'choice-uft'); ?></h2>
        <p><?php _e('Generate real test forms within your active form frameworks, populate them with test data, and validate tracking.', 'choice-uft'); ?></p>

        <!-- Notices Container -->
        <div id="cuft-notices"></div>

        <!-- Loading Indicator -->
        <div id="cuft-loader" class="cuft-loader" style="display:none;">
            <span class="spinner is-active"></span>
            <span id="cuft-loader-text">Loading...</span>
        </div>

        <!-- Form Builder Controls -->
        <div class="form-builder-controls">
            <div class="cuft-control-group">
                <label for="cuft-framework-select">
                    <?php _e('Select Form Framework:', 'choice-uft'); ?>
                </label>
                <select id="cuft-framework-select">
                    <option value=""><?php _e('-- Select Framework --', 'choice-uft'); ?></option>
                    <?php
                    // Dynamically detect which form frameworks are active
                    $available_frameworks = array();

                    // Check Elementor Pro
                    if (defined('ELEMENTOR_PRO_VERSION')) {
                        $available_frameworks['elementor'] = __('Elementor Pro', 'choice-uft');
                    }

                    // Check Contact Form 7
                    if (class_exists('WPCF7')) {
                        $available_frameworks['cf7'] = __('Contact Form 7', 'choice-uft');
                    }

                    // Check Ninja Forms
                    if (function_exists('Ninja_Forms')) {
                        $available_frameworks['ninja'] = __('Ninja Forms', 'choice-uft');
                    }

                    // Check Gravity Forms
                    if (class_exists('GFAPI')) {
                        $available_frameworks['gravity'] = __('Gravity Forms', 'choice-uft');
                    }

                    // Check Avada/Fusion Builder
                    if (class_exists('FusionBuilder')) {
                        $available_frameworks['avada'] = __('Avada Forms', 'choice-uft');
                    }

                    // Display available options
                    if (!empty($available_frameworks)) {
                        foreach ($available_frameworks as $value => $label) {
                            printf('<option value="%s">%s</option>', esc_attr($value), esc_html($label));
                        }
                    } else {
                        ?>
                        <option value="" disabled><?php _e('No form frameworks detected', 'choice-uft'); ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="cuft-control-group">
                <label for="cuft-template-select">
                    <?php _e('Select Template:', 'choice-uft'); ?>
                </label>
                <select id="cuft-template-select">
                    <option value="basic_contact_form"><?php _e('Basic Contact Form', 'choice-uft'); ?></option>
                </select>
            </div>

            <div class="cuft-button-group">
                <button type="button" class="button button-primary" id="cuft-create-form-btn">
                    <?php _e('Create Test Form', 'choice-uft'); ?>
                </button>

                <button type="button" class="button button-secondary" id="cuft-delete-form-btn">
                    <?php _e('Delete Test Form', 'choice-uft'); ?>
                </button>
            </div>
        </div>

        <?php if (empty($available_frameworks)) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php _e('No form framework plugins are currently active. Please install and activate at least one supported form plugin:', 'choice-uft'); ?>
                    <br>• Elementor Pro
                    <br>• Contact Form 7
                    <br>• Ninja Forms
                    <br>• Gravity Forms
                    <br>• Avada Theme (with Fusion Builder)
                </p>
            </div>
        <?php else : ?>
            <p class="description">
                <?php
                printf(
                    _n(
                        'Detected %d active form framework: %s',
                        'Detected %d active form frameworks: %s',
                        count($available_frameworks),
                        'choice-uft'
                    ),
                    count($available_frameworks),
                    implode(', ', $available_frameworks)
                );
                ?>
            </p>
        <?php endif; ?>

        <!-- Form Info Display -->
        <div id="cuft-form-info" class="cuft-form-info"></div>

        <!-- Iframe Container -->
        <div id="cuft-iframe-container" class="cuft-iframe-container" style="display:none;">
            <div class="cuft-iframe-controls">
                <button type="button" class="button" id="cuft-populate-fields-btn">
                    <?php _e('Populate Test Data', 'choice-uft'); ?>
                </button>
                <button type="button" class="button" id="cuft-trigger-submit-btn">
                    <?php _e('Submit Form', 'choice-uft'); ?>
                </button>
            </div>

            <iframe id="cuft-test-iframe" class="cuft-test-iframe"></iframe>
        </div>

        <!-- Event Monitor -->
        <div class="cuft-event-monitor-container">
            <h3><?php _e('Captured Events', 'choice-uft'); ?></h3>
            <div id="cuft-event-monitor" class="cuft-event-monitor"></div>

            <h3><?php _e('Validation Results', 'choice-uft'); ?></h3>
            <div id="cuft-validation-results" class="cuft-validation-results"></div>
        </div>
    </div>

    <!-- Event Viewer Section -->
    <div class="card">
        <h2><?php _e('Event Viewer', 'choice-uft'); ?></h2>
        <p><?php _e('Monitor and validate dataLayer events in real-time.', 'choice-uft'); ?></p>

        <div class="event-viewer-controls">
            <label>
                <input type="checkbox" id="cuft-filter-test-only" checked>
                <?php _e('Show test events only', 'choice-uft'); ?>
            </label>

            <button type="button" class="button" id="cuft-clear-events">
                <?php _e('Clear Events', 'choice-uft'); ?>
            </button>

            <button type="button" class="button" id="cuft-refresh-events">
                <?php _e('Refresh', 'choice-uft'); ?>
            </button>
        </div>

        <div id="cuft-event-viewer" class="event-viewer">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Time', 'choice-uft'); ?></th>
                        <th><?php _e('Event', 'choice-uft'); ?></th>
                        <th><?php _e('Status', 'choice-uft'); ?></th>
                        <th><?php _e('Details', 'choice-uft'); ?></th>
                    </tr>
                </thead>
                <tbody id="cuft-event-list">
                    <tr>
                        <td colspan="4" class="no-events"><?php _e('No events captured yet. Generate test data and simulate events to begin.', 'choice-uft'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Test Database Events Section -->
    <div class="card">
        <h2><?php _e('Test Database Events', 'choice-uft'); ?></h2>
        <p><?php _e('View and manage events stored in the test database table.', 'choice-uft'); ?></p>

        <div class="db-events-controls">
            <button type="button" class="button" id="cuft-load-db-events">
                <?php _e('Load Events', 'choice-uft'); ?>
            </button>

            <button type="button" class="button" id="cuft-delete-session-events">
                <?php _e('Delete Session Events', 'choice-uft'); ?>
            </button>

            <button type="button" class="button button-link-delete" id="cuft-delete-all-events">
                <?php _e('Delete All Test Events', 'choice-uft'); ?>
            </button>
        </div>

        <div id="cuft-db-events-container" class="db-events-container"></div>
    </div>

</div>