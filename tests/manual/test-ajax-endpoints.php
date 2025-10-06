#!/usr/bin/env php
<?php
/**
 * Manual Integration Test for AJAX Endpoints
 *
 * Run with: docker exec wp-pdev-cli php /var/www/html/wp-content/plugins/choice-uft/tests/manual/test-ajax-endpoints.php
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

// Test results tracker
$results = array(
    'passed' => 0,
    'failed' => 0,
    'tests' => array()
);

/**
 * Run a test
 */
function run_test($name, $callable) {
    global $results;

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "TEST: $name\n";
    echo str_repeat('=', 60) . "\n";

    try {
        $result = $callable();
        if ($result['success']) {
            echo "✅ PASSED\n";
            if (isset($result['message'])) {
                echo "   " . $result['message'] . "\n";
            }
            $results['passed']++;
            $results['tests'][] = array('name' => $name, 'status' => 'PASSED');
        } else {
            echo "❌ FAILED\n";
            echo "   " . ($result['message'] ?? 'Unknown error') . "\n";
            $results['failed']++;
            $results['tests'][] = array('name' => $name, 'status' => 'FAILED', 'error' => $result['message'] ?? 'Unknown');
        }
    } catch (Exception $e) {
        echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
        $results['failed']++;
        $results['tests'][] = array('name' => $name, 'status' => 'EXCEPTION', 'error' => $e->getMessage());
    }
}

// Test 1: Verify classes loaded
run_test('Classes Loaded', function() {
    $classes = array(
        'CUFT_Updater_Ajax',
        'CUFT_Update_Checker',
        'CUFT_GitHub_API',
        'CUFT_Update_Status',
        'CUFT_Update_Log'
    );

    $missing = array();
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            $missing[] = $class;
        }
    }

    if (empty($missing)) {
        return array('success' => true, 'message' => 'All required classes loaded');
    } else {
        return array('success' => false, 'message' => 'Missing classes: ' . implode(', ', $missing));
    }
});

// Test 2: Nonce creation and validation
run_test('Nonce Creation and Validation', function() {
    $nonce = wp_create_nonce('cuft_updater_nonce');
    $verified = wp_verify_nonce($nonce, 'cuft_updater_nonce');

    if ($verified) {
        return array('success' => true, 'message' => 'Nonce created and verified successfully');
    } else {
        return array('success' => false, 'message' => 'Nonce verification failed');
    }
});

// Test 3: GitHub API connectivity
run_test('GitHub API Connection', function() {
    $test = CUFT_GitHub_API::test_connection();

    if ($test['success']) {
        return array(
            'success' => true,
            'message' => sprintf('Connected in %.3fs, version: %s',
                $test['elapsed_time'] ?? 0,
                $test['latest_version'] ?? 'unknown'
            )
        );
    } else {
        return array('success' => false, 'message' => $test['error'] ?? 'Unknown error');
    }
});

// Test 4: Update check
run_test('Update Check Functionality', function() {
    $result = CUFT_Update_Checker::check(true);

    if ($result['success']) {
        $msg = sprintf('Current: %s, Latest: %s, Update available: %s',
            $result['current_version'],
            $result['latest_version'],
            $result['update_available'] ? 'Yes' : 'No'
        );
        return array('success' => true, 'message' => $msg);
    } else {
        return array('success' => false, 'message' => $result['error'] ?? 'Unknown error');
    }
});

// Test 5: Update status model
run_test('Update Status Model', function() {
    // Set a test status
    CUFT_Update_Status::set_checking(true);
    $is_checking = CUFT_Update_Status::is_checking();
    CUFT_Update_Status::set_checking(false);

    if ($is_checking) {
        return array('success' => true, 'message' => 'Status tracking working');
    } else {
        return array('success' => false, 'message' => 'Status not set correctly');
    }
});

// Test 6: Update configuration
run_test('Update Configuration Model', function() {
    $enabled = CUFT_Update_Configuration::is_enabled();
    $frequency = CUFT_Update_Configuration::get_check_frequency();

    return array(
        'success' => true,
        'message' => sprintf('Enabled: %s, Frequency: %s',
            $enabled ? 'Yes' : 'No',
            $frequency
        )
    );
});

// Test 7: Update log
run_test('Update Log Model', function() {
    // Log a test event
    CUFT_Update_Log::log_check_started();
    $logs = CUFT_Update_Log::get_logs(1);

    if (!empty($logs)) {
        return array('success' => true, 'message' => 'Logging functional, entries: ' . count($logs));
    } else {
        return array('success' => false, 'message' => 'No log entries found');
    }
});

// Test 8: Capability check
run_test('User Capability Check', function() {
    wp_set_current_user(1);

    if (current_user_can('update_plugins')) {
        return array('success' => true, 'message' => 'Admin user has update_plugins capability');
    } else {
        return array('success' => false, 'message' => 'Admin user missing required capability');
    }
});

// Test 9: Filesystem handler
run_test('Filesystem Handler', function() {
    if (class_exists('CUFT_Filesystem_Handler')) {
        $initialized = CUFT_Filesystem_Handler::init();
        if ($initialized) {
            return array('success' => true, 'message' => 'Filesystem initialized successfully');
        } else {
            return array('success' => false, 'message' => 'Filesystem initialization failed');
        }
    } else {
        return array('success' => false, 'message' => 'CUFT_Filesystem_Handler class not found');
    }
});

// Test 10: Rate limiter
run_test('Rate Limiter', function() {
    if (class_exists('CUFT_Rate_Limiter')) {
        $allowed = CUFT_Rate_Limiter::is_allowed('test_action', 1);
        if ($allowed) {
            return array('success' => true, 'message' => 'Rate limiter functional');
        } else {
            return array('success' => false, 'message' => 'Rate limit exceeded unexpectedly');
        }
    } else {
        return array('success' => false, 'message' => 'CUFT_Rate_Limiter class not found');
    }
});

// Print summary
echo "\n\n" . str_repeat('=', 60) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 60) . "\n";
echo "Total Tests: " . ($results['passed'] + $results['failed']) . "\n";
echo "✅ Passed: " . $results['passed'] . "\n";
echo "❌ Failed: " . $results['failed'] . "\n";
echo "\nSuccess Rate: " . round(($results['passed'] / ($results['passed'] + $results['failed'])) * 100, 1) . "%\n";

// Exit with appropriate code
exit($results['failed'] > 0 ? 1 : 0);
