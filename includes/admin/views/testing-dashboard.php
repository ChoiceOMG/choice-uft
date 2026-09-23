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
    <h1><?php esc_html_e('CUFT Testing Dashboard', 'choice-universal-form-tracker'); ?></h1>

    <?php
    $cuft_gtm_id = get_option('cuft_gtm_id');
    if (empty($cuft_gtm_id)) {
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php esc_html_e('GTM Not Configured:', 'choice-universal-form-tracker'); ?></strong>
                <?php esc_html_e('Google Tag Manager ID is not set. DataLayer events will still be visible in the console, but GTM will not process them.', 'choice-universal-form-tracker'); ?>
                <a href="<?php echo esc_url( admin_url('options-general.php?page=choice-universal-form-tracker&tab=settings') ); ?>"><?php esc_html_e('Configure GTM', 'choice-universal-form-tracker'); ?></a>
            </p>
        </div>
        <?php
    } else {
        ?>
        <div class="notice notice-success">
            <p>
                <strong><?php esc_html_e('GTM Active:', 'choice-universal-form-tracker'); ?></strong>
                <?php
                echo wp_kses(
                    sprintf(
                        /* translators: %s: Google Tag Manager container ID, wrapped in a code element */
                        __('Google Tag Manager is loaded with ID: %s. Open browser console to see dataLayer events.', 'choice-universal-form-tracker'),
                        '<code>' . esc_html($cuft_gtm_id) . '</code>'
                    ),
                    array( 'code' => array() )
                );
                ?>
            </p>
        </div>
        <?php
    }
    ?>

    <div class="notice notice-info">
        <p><?php esc_html_e('This dashboard is for testing conversion tracking features. All events generated here include a test_mode flag and are stored separately from production data.', 'choice-universal-form-tracker'); ?></p>
    </div>

    <?php wp_nonce_field('cuft-testing-dashboard', 'cuft_testing_nonce'); ?>

    <!-- Test Data Generator Section -->
    <div class="card">
        <h2><?php esc_html_e('Test Data Generator', 'choice-universal-form-tracker'); ?></h2>
        <p><?php esc_html_e('Generate realistic test data including click IDs, UTM parameters, and contact information.', 'choice-universal-form-tracker'); ?></p>

        <button type="button" class="button button-primary" id="cuft-generate-test-data">
            <?php esc_html_e('Generate Sample Data', 'choice-universal-form-tracker'); ?>
        </button>

        <div id="cuft-test-data-display" class="test-data-display" style="display:none;">
            <h3><?php esc_html_e('Generated Test Data:', 'choice-universal-form-tracker'); ?></h3>
            <div class="test-data-content"></div>
        </div>
    </div>

    <!-- Event Simulator Section -->
    <div class="card">
        <h2><?php esc_html_e('Event Simulator', 'choice-universal-form-tracker'); ?></h2>
        <p><?php esc_html_e('Simulate various tracking events with the generated test data.', 'choice-universal-form-tracker'); ?></p>

        <div class="button-group">
            <button type="button" class="button" id="cuft-simulate-phone-click">
                <?php esc_html_e('Simulate Phone Click', 'choice-universal-form-tracker'); ?>
            </button>
            <button type="button" class="button" id="cuft-simulate-email-click">
                <?php esc_html_e('Simulate Email Click', 'choice-universal-form-tracker'); ?>
            </button>
            <button type="button" class="button" id="cuft-simulate-form-submit">
                <?php esc_html_e('Simulate Form Submission', 'choice-universal-form-tracker'); ?>
            </button>
            <button type="button" class="button" id="cuft-simulate-generate-lead">
                <?php esc_html_e('Simulate Lead Generation', 'choice-universal-form-tracker'); ?>
            </button>
        </div>

        <div id="cuft-simulation-status" class="simulation-status"></div>
    </div>

    <!-- Test Form Builder Section -->
    <div class="card">
        <h2><?php esc_html_e('Test Form Builder', 'choice-universal-form-tracker'); ?></h2>
        <p><?php esc_html_e('Generate real test forms within your active form frameworks, populate them with test data, and validate tracking.', 'choice-universal-form-tracker'); ?></p>

        <!-- Notices Container -->
        <div id="cuft-notices"></div>

        <!-- Loading Indicator -->
        <div id="cuft-loader" class="cuft-loader" style="display:none;">
            <span class="spinner is-active"></span>
            <span id="cuft-loader-text"><?php esc_html_e('Loading...', 'choice-universal-form-tracker'); ?></span>
        </div>

        <!-- Form Builder Controls -->
        <div class="form-builder-controls">
            <div class="cuft-control-group">
                <label for="cuft-framework-select">
                    <?php esc_html_e('Select Form Framework:', 'choice-universal-form-tracker'); ?>
                </label>
                <select id="cuft-framework-select">
                    <option value=""><?php esc_html_e('-- Select Framework --', 'choice-universal-form-tracker'); ?></option>
                    <?php
                    // Dynamically detect which form frameworks are active
                    $cuft_available_frameworks = array();

                    // Check Elementor Pro
                    if (defined('ELEMENTOR_PRO_VERSION')) {
                        $cuft_available_frameworks['elementor'] = __('Elementor Pro', 'choice-universal-form-tracker');
                    }

                    // Check Contact Form 7
                    if (class_exists('WPCF7')) {
                        $cuft_available_frameworks['cf7'] = __('Contact Form 7', 'choice-universal-form-tracker');
                    }

                    // Check Ninja Forms
                    if (function_exists('Ninja_Forms')) {
                        $cuft_available_frameworks['ninja'] = __('Ninja Forms', 'choice-universal-form-tracker');
                    }

                    // Check Gravity Forms
                    if (class_exists('GFAPI')) {
                        $cuft_available_frameworks['gravity'] = __('Gravity Forms', 'choice-universal-form-tracker');
                    }

                    // Check Avada/Fusion Builder
                    if (class_exists('FusionBuilder')) {
                        $cuft_available_frameworks['avada'] = __('Avada Forms', 'choice-universal-form-tracker');
                    }

                    // Display available options
                    if (!empty($cuft_available_frameworks)) {
                        foreach ($cuft_available_frameworks as $cuft_framework_value => $cuft_framework_label) {
                            printf('<option value="%s">%s</option>', esc_attr($cuft_framework_value), esc_html($cuft_framework_label));
                        }
                    } else {
                        ?>
                        <option value="" disabled><?php esc_html_e('No form frameworks detected', 'choice-universal-form-tracker'); ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>

            <div class="cuft-control-group">
                <label for="cuft-template-select">
                    <?php esc_html_e('Select Template:', 'choice-universal-form-tracker'); ?>
                </label>
                <select id="cuft-template-select">
                    <option value="basic_contact_form"><?php esc_html_e('Basic Contact Form', 'choice-universal-form-tracker'); ?></option>
                </select>
            </div>

            <div class="cuft-button-group">
                <button type="button" class="button button-primary" id="cuft-create-form-btn">
                    <?php esc_html_e('Create Test Form', 'choice-universal-form-tracker'); ?>
                </button>

                <button type="button" class="button button-secondary" id="cuft-delete-form-btn">
                    <?php esc_html_e('Delete Test Form', 'choice-universal-form-tracker'); ?>
                </button>
            </div>
        </div>

        <?php if (empty($cuft_available_frameworks)) : ?>
            <div class="notice notice-warning inline">
                <p>
                    <?php esc_html_e('No form framework plugins are currently active. Please install and activate at least one supported form plugin:', 'choice-universal-form-tracker'); ?>
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
                    /* translators: 1: number of detected form frameworks, 2: comma-separated list of their names */
                    esc_html( _n(
                        'Detected %1$d active form framework: %2$s',
                        'Detected %1$d active form frameworks: %2$s',
                        count($cuft_available_frameworks),
                        'choice-universal-form-tracker'
                    ) ),
                    count($cuft_available_frameworks),
                    esc_html( implode(', ', $cuft_available_frameworks) )
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
                    <?php esc_html_e('Populate Test Data', 'choice-universal-form-tracker'); ?>
                </button>
                <button type="button" class="button" id="cuft-trigger-submit-btn">
                    <?php esc_html_e('Submit Form', 'choice-universal-form-tracker'); ?>
                </button>
            </div>

            <iframe id="cuft-test-iframe" class="cuft-test-iframe"></iframe>
        </div>

        <!-- Event Monitor -->
        <div class="cuft-event-monitor-container">
            <h3><?php esc_html_e('Captured Events', 'choice-universal-form-tracker'); ?></h3>
            <div id="cuft-event-monitor" class="cuft-event-monitor"></div>

            <h3><?php esc_html_e('Validation Results', 'choice-universal-form-tracker'); ?></h3>
            <div id="cuft-validation-results" class="cuft-validation-results"></div>
        </div>
    </div>

    <!-- Event Viewer Section -->
    <div class="card">
        <h2><?php esc_html_e('Event Viewer', 'choice-universal-form-tracker'); ?></h2>
        <p><?php esc_html_e('Monitor and validate dataLayer events in real-time.', 'choice-universal-form-tracker'); ?></p>

        <div class="event-viewer-controls">
            <label>
                <input type="checkbox" id="cuft-filter-test-only" checked>
                <?php esc_html_e('Show test events only', 'choice-universal-form-tracker'); ?>
            </label>

            <button type="button" class="button" id="cuft-clear-events">
                <?php esc_html_e('Clear Events', 'choice-universal-form-tracker'); ?>
            </button>

            <button type="button" class="button" id="cuft-refresh-events">
                <?php esc_html_e('Refresh', 'choice-universal-form-tracker'); ?>
            </button>
        </div>

        <div id="cuft-event-viewer" class="event-viewer">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Time', 'choice-universal-form-tracker'); ?></th>
                        <th><?php esc_html_e('Event', 'choice-universal-form-tracker'); ?></th>
                        <th><?php esc_html_e('Status', 'choice-universal-form-tracker'); ?></th>
                        <th><?php esc_html_e('Details', 'choice-universal-form-tracker'); ?></th>
                    </tr>
                </thead>
                <tbody id="cuft-event-list">
                    <tr>
                        <td colspan="4" class="no-events"><?php esc_html_e('No events captured yet. Generate test data and simulate events to begin.', 'choice-universal-form-tracker'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Test Database Events Section -->
    <div class="card">
        <h2><?php esc_html_e('Test Database Events', 'choice-universal-form-tracker'); ?></h2>
        <p><?php esc_html_e('View and manage events stored in the test database table.', 'choice-universal-form-tracker'); ?></p>

        <div class="db-events-controls">
            <button type="button" class="button" id="cuft-load-db-events">
                <?php esc_html_e('Load Events', 'choice-universal-form-tracker'); ?>
            </button>

            <button type="button" class="button" id="cuft-delete-session-events">
                <?php esc_html_e('Delete Session Events', 'choice-universal-form-tracker'); ?>
            </button>

            <button type="button" class="button button-link-delete" id="cuft-delete-all-events">
                <?php esc_html_e('Delete All Test Events', 'choice-universal-form-tracker'); ?>
            </button>
        </div>

        <div id="cuft-db-events-container" class="db-events-container"></div>
    </div>

</div>