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