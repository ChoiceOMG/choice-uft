<?php
/**
 * Performance Benchmark: JSON Operations
 *
 * Tests JSON_ARRAY_APPEND, JSON_LENGTH, and JSON_EXTRACT operations
 * to ensure they meet performance targets.
 *
 * Target Performance:
 * - JSON_ARRAY_APPEND: <12ms
 * - JSON_LENGTH: <5ms
 * - JSON_EXTRACT with idx_date_updated: <10ms
 * - Aggregate overhead: <10%
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class CUFT_JSON_Performance_Test {

    private $results = array();

    /**
     * Run all performance benchmarks
     */
    public function run_all_tests() {
        echo "\n=== CUFT JSON Operations Performance Benchmark ===\n\n";

        $this->test_json_array_append();
        $this->test_json_length();
        $this->test_json_extract_with_index();
        $this->test_aggregate_overhead();

        $this->print_summary();
    }

    /**
     * Test JSON_ARRAY_APPEND performance
     */
    private function test_json_array_append() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 1: JSON_ARRAY_APPEND Operations\n";
        echo str_repeat( '-', 50 ) . "\n";

        // Create test record
        $test_click_id = 'perf_test_' . time();
        CUFT_Click_Tracker::record_click( $test_click_id, 'test', 'test_campaign' );

        $iterations = 20;
        $times = array();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = microtime( true );

            // Use EXPLAIN to analyze query
            if ( $i === 0 ) {
                $explain = $wpdb->get_results(
                    "EXPLAIN " . $wpdb->prepare(
                        "UPDATE {$table}
                         SET events = JSON_ARRAY_APPEND(
                             COALESCE(events, JSON_ARRAY()),
                             '$',
                             JSON_OBJECT('event', %s, 'timestamp', %s)
                         ),
                         date_updated = NOW()
                         WHERE click_id = %s",
                        'test_event',
                        current_time( 'mysql', true ),
                        $test_click_id
                    )
                );
                echo "EXPLAIN result:\n";
                print_r( $explain );
                echo "\n";
            }

            CUFT_Click_Tracker::add_event( $test_click_id, 'test_event_' . $i );
            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000; // Convert to ms
        }

        // Calculate statistics
        $avg_time = array_sum( $times ) / count( $times );
        $min_time = min( $times );
        $max_time = max( $times );
        sort( $times );
        $p95_time = $times[ (int) ( count( $times ) * 0.95 ) ];

        echo "Results ({$iterations} iterations):\n";
        echo "  Average: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  Min: " . number_format( $min_time, 2 ) . "ms\n";
        echo "  Max: " . number_format( $max_time, 2 ) . "ms\n";
        echo "  P95: " . number_format( $p95_time, 2 ) . "ms\n";
        echo "  Target: <12ms\n";
        echo "  Status: " . ( $p95_time < 12 ? '✅ PASS' : '❌ FAIL' ) . "\n\n";

        $this->results['json_array_append'] = array(
            'avg' => $avg_time,
            'p95' => $p95_time,
            'target' => 12,
            'pass' => $p95_time < 12
        );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $test_click_id ) );
    }

    /**
     * Test JSON_LENGTH performance
     */
    private function test_json_length() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 2: JSON_LENGTH Operations\n";
        echo str_repeat( '-', 50 ) . "\n";

        // Create test record with 50 events
        $test_click_id = 'perf_test_length_' . time();
        CUFT_Click_Tracker::record_click( $test_click_id, 'test', 'test_campaign' );

        for ( $i = 0; $i < 50; $i++ ) {
            CUFT_Click_Tracker::add_event( $test_click_id, 'test_event' );
        }

        $iterations = 50;
        $times = array();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = microtime( true );

            // Test JSON_LENGTH query
            $count = $wpdb->get_var( $wpdb->prepare(
                "SELECT JSON_LENGTH(events) FROM {$table} WHERE click_id = %s",
                $test_click_id
            ) );

            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000;
        }

        $avg_time = array_sum( $times ) / count( $times );
        sort( $times );
        $p95_time = $times[ (int) ( count( $times ) * 0.95 ) ];

        echo "Results ({$iterations} iterations, 50 events):\n";
        echo "  Average: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  P95: " . number_format( $p95_time, 2 ) . "ms\n";
        echo "  Target: <5ms\n";
        echo "  Status: " . ( $p95_time < 5 ? '✅ PASS' : '❌ FAIL' ) . "\n\n";

        $this->results['json_length'] = array(
            'avg' => $avg_time,
            'p95' => $p95_time,
            'target' => 5,
            'pass' => $p95_time < 5
        );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $test_click_id ) );
    }

    /**
     * Test JSON_EXTRACT with idx_date_updated
     */
    private function test_json_extract_with_index() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 3: JSON_EXTRACT with idx_date_updated\n";
        echo str_repeat( '-', 50 ) . "\n";

        // Create test records
        $test_prefix = 'perf_test_extract_' . time();
        for ( $i = 0; $i < 100; $i++ ) {
            $click_id = $test_prefix . '_' . $i;
            CUFT_Click_Tracker::record_click( $click_id, 'test', 'test_campaign' );
            CUFT_Click_Tracker::add_event( $click_id, 'phone_click' );
        }

        $iterations = 20;
        $times = array();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = microtime( true );

            // Query using date_updated index
            $results = $wpdb->get_results(
                "SELECT click_id, events
                 FROM {$table}
                 WHERE click_id LIKE '{$test_prefix}%'
                 ORDER BY date_updated DESC
                 LIMIT 10"
            );

            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000;
        }

        $avg_time = array_sum( $times ) / count( $times );
        sort( $times );
        $p95_time = $times[ (int) ( count( $times ) * 0.95 ) ];

        // Show EXPLAIN for the query
        $explain = $wpdb->get_results(
            "EXPLAIN SELECT click_id, events
             FROM {$table}
             WHERE click_id LIKE '{$test_prefix}%'
             ORDER BY date_updated DESC
             LIMIT 10"
        );
        echo "EXPLAIN result:\n";
        print_r( $explain );
        echo "\n";

        echo "Results ({$iterations} iterations, 100 records):\n";
        echo "  Average: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  P95: " . number_format( $p95_time, 2 ) . "ms\n";
        echo "  Target: <10ms\n";
        echo "  Status: " . ( $p95_time < 10 ? '✅ PASS' : '❌ FAIL' ) . "\n\n";

        $this->results['json_extract_index'] = array(
            'avg' => $avg_time,
            'p95' => $p95_time,
            'target' => 10,
            'pass' => $p95_time < 10
        );

        // Cleanup
        $wpdb->query( "DELETE FROM {$table} WHERE click_id LIKE '{$test_prefix}%'" );
    }

    /**
     * Test aggregate overhead
     */
    private function test_aggregate_overhead() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 4: Aggregate Overhead\n";
        echo str_repeat( '-', 50 ) . "\n";

        $test_click_id = 'perf_test_overhead_' . time();

        // Baseline: Record click without events
        $baseline_times = array();
        for ( $i = 0; $i < 10; $i++ ) {
            $start = microtime( true );
            CUFT_Click_Tracker::record_click( $test_click_id . '_baseline_' . $i, 'test', 'test_campaign' );
            $end = microtime( true );
            $baseline_times[] = ( $end - $start ) * 1000;
        }
        $baseline_avg = array_sum( $baseline_times ) / count( $baseline_times );

        // With events: Record click + add event
        $with_events_times = array();
        for ( $i = 0; $i < 10; $i++ ) {
            $click_id = $test_click_id . '_with_events_' . $i;
            $start = microtime( true );
            CUFT_Click_Tracker::record_click( $click_id, 'test', 'test_campaign' );
            CUFT_Click_Tracker::add_event( $click_id, 'phone_click' );
            $end = microtime( true );
            $with_events_times[] = ( $end - $start ) * 1000;
        }
        $with_events_avg = array_sum( $with_events_times ) / count( $with_events_times );

        $overhead_pct = ( ( $with_events_avg - $baseline_avg ) / $baseline_avg ) * 100;

        echo "Results (10 iterations each):\n";
        echo "  Baseline (no events): " . number_format( $baseline_avg, 2 ) . "ms\n";
        echo "  With events: " . number_format( $with_events_avg, 2 ) . "ms\n";
        echo "  Overhead: " . number_format( $overhead_pct, 1 ) . "%\n";
        echo "  Target: <10%\n";
        echo "  Status: " . ( $overhead_pct < 10 ? '✅ PASS' : '⚠️  WARNING' ) . "\n\n";

        $this->results['aggregate_overhead'] = array(
            'baseline' => $baseline_avg,
            'with_events' => $with_events_avg,
            'overhead_pct' => $overhead_pct,
            'target' => 10,
            'pass' => $overhead_pct < 10
        );

        // Cleanup
        $wpdb->query( "DELETE FROM {$table} WHERE click_id LIKE '{$test_click_id}%'" );
    }

    /**
     * Print summary of all tests
     */
    private function print_summary() {
        echo "\n" . str_repeat( '=', 50 ) . "\n";
        echo "PERFORMANCE SUMMARY\n";
        echo str_repeat( '=', 50 ) . "\n\n";

        $total_tests = count( $this->results );
        $passed_tests = 0;

        foreach ( $this->results as $test => $result ) {
            if ( $result['pass'] ) {
                $passed_tests++;
            }
        }

        echo "Tests: {$passed_tests}/{$total_tests} passed\n\n";

        foreach ( $this->results as $test => $result ) {
            $status = $result['pass'] ? '✅ PASS' : '❌ FAIL';
            echo "{$status} - {$test}\n";
        }

        echo "\n" . str_repeat( '=', 50 ) . "\n";
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' ) {
    $test = new CUFT_JSON_Performance_Test();
    $test->run_all_tests();
}
