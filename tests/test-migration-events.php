<?php
/**
 * Basic tests for click tracking events migration
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Test_Migration_Events {

    /**
     * Run all tests
     */
    public static function run_tests() {
        echo "Running Click Tracking Events Migration Tests...\n";

        $results = array();

        $results['schema_migration'] = self::test_schema_migration();
        $results['event_recording'] = self::test_event_recording();
        $results['data_reconstruction'] = self::test_data_reconstruction();
        $results['json_validation'] = self::test_json_validation();

        // Summary
        $passed = count( array_filter( $results ) );
        $total = count( $results );

        echo "\n=== Test Results ===\n";
        foreach ( $results as $test => $result ) {
            $status = $result ? 'PASS' : 'FAIL';
            echo sprintf( "%-25s: %s\n", ucwords( str_replace( '_', ' ', $test ) ), $status );
        }

        echo "\nTotal: {$passed}/{$total} tests passed\n";

        return $passed === $total;
    }

    /**
     * Test schema migration
     */
    private static function test_schema_migration() {
        try {
            // Check if migration class exists
            if ( ! class_exists( 'CUFT_Migration_Events' ) ) {
                echo "FAIL: CUFT_Migration_Events class not found\n";
                return false;
            }

            // Test needs migration check
            $needs_migration = CUFT_Migration_Events::needs_migration();
            echo "Migration needed: " . ( $needs_migration ? 'YES' : 'NO' ) . "\n";

            // Test migration status
            $status = CUFT_Migration_Events::get_migration_status();
            if ( ! is_array( $status ) ) {
                echo "FAIL: Migration status should be array\n";
                return false;
            }

            echo "Schema migration test passed\n";
            return true;

        } catch ( Exception $e ) {
            echo "FAIL: Schema migration test error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test event recording
     */
    private static function test_event_recording() {
        try {
            // Check if click tracker class exists
            if ( ! class_exists( 'CUFT_Click_Tracker' ) ) {
                echo "FAIL: CUFT_Click_Tracker class not found\n";
                return false;
            }

            // Check if new methods exist
            if ( ! method_exists( 'CUFT_Click_Tracker', 'add_event' ) ) {
                echo "FAIL: add_event method not found\n";
                return false;
            }

            if ( ! method_exists( 'CUFT_Click_Tracker', 'get_events' ) ) {
                echo "FAIL: get_events method not found\n";
                return false;
            }

            echo "Event recording test passed\n";
            return true;

        } catch ( Exception $e ) {
            echo "FAIL: Event recording test error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test data reconstruction logic
     */
    private static function test_data_reconstruction() {
        try {
            // Create test data structure
            $test_record = (object) array(
                'id' => 1,
                'click_id' => 'test_click_123',
                'utm_source' => 'google',
                'platform' => 'facebook',
                'qualified' => 1,
                'date_created' => '2025-01-20 10:00:00',
                'date_updated' => '2025-01-22 15:30:00'
            );

            // Use reflection to test private method
            $reflection = new ReflectionClass( 'CUFT_Migration_Events' );
            $method = $reflection->getMethod( 'reconstruct_events' );
            $method->setAccessible( true );

            $events = $method->invokeArgs( null, array( $test_record ) );

            if ( ! is_array( $events ) ) {
                echo "FAIL: reconstruct_events should return array\n";
                return false;
            }

            // Should have form_submit and generate_lead events
            if ( count( $events ) < 2 ) {
                echo "FAIL: Should reconstruct at least 2 events\n";
                return false;
            }

            echo "Data reconstruction test passed\n";
            return true;

        } catch ( Exception $e ) {
            echo "FAIL: Data reconstruction test error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test JSON validation
     */
    private static function test_json_validation() {
        try {
            // Check if utils class exists
            if ( ! class_exists( 'CUFT_Utils' ) ) {
                echo "FAIL: CUFT_Utils class not found\n";
                return false;
            }

            // Test valid events JSON
            $valid_events = json_encode( array(
                array(
                    'event' => 'phone_click',
                    'timestamp' => '2025-01-25T14:30:00.000Z'
                ),
                array(
                    'event' => 'form_submit',
                    'timestamp' => '2025-01-25T14:32:45.000Z'
                )
            ) );

            $is_valid = CUFT_Utils::validate_json_schema( $valid_events, 'events' );
            if ( ! $is_valid ) {
                echo "FAIL: Valid events JSON should pass validation\n";
                return false;
            }

            // Test invalid events JSON
            $invalid_events = json_encode( array(
                array(
                    'event' => 'invalid_event',
                    'timestamp' => '2025-01-25T14:30:00.000Z'
                )
            ) );

            $is_invalid = CUFT_Utils::validate_json_schema( $invalid_events, 'events' );
            if ( $is_invalid ) {
                echo "FAIL: Invalid events JSON should fail validation\n";
                return false;
            }

            echo "JSON validation test passed\n";
            return true;

        } catch ( Exception $e ) {
            echo "FAIL: JSON validation test error: " . $e->getMessage() . "\n";
            return false;
        }
    }

    /**
     * Test feature flags
     */
    private static function test_feature_flags() {
        try {
            if ( ! class_exists( 'CUFT_Utils' ) ) {
                echo "FAIL: CUFT_Utils class not found\n";
                return false;
            }

            // Test feature flag check
            $is_enabled = CUFT_Utils::is_feature_enabled( 'click_event_tracking' );
            echo "Feature flag status: " . ( $is_enabled ? 'ENABLED' : 'DISABLED' ) . "\n";

            echo "Feature flags test passed\n";
            return true;

        } catch ( Exception $e ) {
            echo "FAIL: Feature flags test error: " . $e->getMessage() . "\n";
            return false;
        }
    }
}

// Run tests if called directly
if ( defined( 'WP_CLI' ) && WP_CLI ) {
    CUFT_Test_Migration_Events::run_tests();
}