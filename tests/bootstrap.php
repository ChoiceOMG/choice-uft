<?php
/**
 * PHPUnit bootstrap for Choice Universal Form Tracker.
 *
 * Loads the WordPress test environment and then the plugin itself.
 *
 * Usage:
 *   1. Run bin/install-wp-tests.sh to set up the WordPress test suite.
 *   2. Run: composer test  (or phpunit directly)
 */

// Load Yoast PHPUnit Polyfills — bridges PHPUnit 9 with WP's older assertion API.
require_once dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/phpunitpolyfills-autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php\n"
        . "Run bin/install-wp-tests.sh to set up the WordPress test suite.\n";
    exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

/**
 * Manually load the plugin being tested.
 */
function _cuft_manually_load_plugin() {
    require dirname( __DIR__ ) . '/choice-universal-form-tracker.php';
}
tests_add_filter( 'muplugins_loaded', '_cuft_manually_load_plugin' );

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
