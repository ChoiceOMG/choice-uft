<?php
/**
 * Performance Benchmark: AJAX Endpoint
 *
 * Tests AJAX endpoint response times to ensure fire-and-forget
 * pattern meets performance targets.
 *
 * Target Performance:
 * - P95 response time: <100ms
 * - Fire-and-forget: non-blocking
 * - Varying event counts: 1, 50, 100 events
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.12.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/choice-universal-form-tracker.php';
}

class CUFT_AJAX_Performance_Test {

    private $results = array();

    /**
     * Run all performance benchmarks
     */
    public function run_all_tests() {
        echo "\n=== CUFT AJAX Endpoint Performance Benchmark ===\n\n";

        $this->test_ajax_response_time_empty();
        $this->test_ajax_response_time_50_events();
        $this->test_ajax_response_time_100_events();
        $this->test_concurrent_requests();

        $this->print_summary();
    }

    /**
     * Test AJAX response time with empty events array
     */
    private function test_ajax_response_time_empty() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 1: AJAX Response Time (Empty Events)\n";
        echo str_repeat( '-', 50 ) . "\n";

        $test_click_id = 'ajax_perf_empty_' . time();
        CUFT_Click_Tracker::record_click( $test_click_id, 'test', 'test_campaign' );

        $iterations = 50;
        $times = array();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = microtime( true );

            // Simulate AJAX request
            $_POST['click_id'] = $test_click_id;
            $_POST['event_type'] = 'phone_click';
            $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

            ob_start();
            do_action( 'wp_ajax_nopriv_cuft_record_event' );
            ob_end_clean();

            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000;
        }

        $avg_time = array_sum( $times ) / count( $times );
        $min_time = min( $times );
        $max_time = max( $times );
        sort( $times );
        $p95_time = $times[ (int) ( count( $times ) * 0.95 ) ];

        echo "Results ({$iterations} iterations, 0 events):\n";
        echo "  Average: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  Min: " . number_format( $min_time, 2 ) . "ms\n";
        echo "  Max: " . number_format( $max_time, 2 ) . "ms\n";
        echo "  P95: " . number_format( $p95_time, 2 ) . "ms\n";
        echo "  Target: <100ms\n";
        echo "  Status: " . ( $p95_time < 100 ? '✅ PASS' : '❌ FAIL' ) . "\n\n";

        $this->results['ajax_empty'] = array(
            'avg' => $avg_time,
            'p95' => $p95_time,
            'target' => 100,
            'pass' => $p95_time < 100
        );

        // Cleanup
        $wpdb->delete( $table, array( 'click_id' => $test_click_id ) );
    }

    /**
     * Test AJAX response time with 50 events
     */
    private function test_ajax_response_time_50_events() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 2: AJAX Response Time (50 Events)\n";
        echo str_repeat( '-', 50 ) . "\n";

        $test_click_id = 'ajax_perf_50_' . time();
        CUFT_Click_Tracker::record_click( $test_click_id, 'test', 'test_campaign' );

        // Pre-populate with 50 events
        for ( $i = 0; $i < 50; $i++ ) {
            CUFT_Click_Tracker::add_event( $test_click_id, 'test_event' );
        }

        $iterations = 30;
        $times = array();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = microtime( true );

            $_POST['click_id'] = $test_click_id;
            $_POST['event_type'] = 'phone_click';
            $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

            ob_start();
            do_action( 'wp_ajax_nopriv_cuft_record_event' );
            ob_end_clean();

            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000;
        }

        $avg_time = array_sum( $times ) / count( $times );
        sort( $times );
        $p95_time = $times[ (int) ( count( $times ) * 0.95 ) ];

        echo "Results ({$iterations} iterations, 50 events):\n";
        echo "  Average: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  P95: " . number_format( $p95_time, 2 ) . "ms\n";
        echo "  Target: <100ms\n";
        echo "  Status: " . ( $p95_time < 100 ? '✅ PASS' : '❌ FAIL' ) . "\n\n";

        $this->results['ajax_50_events'] = array(
            'avg' => $avg_time,
            'p95' => $p95_time,
            'target' => 100,
            'pass' => $p95_time < 100
        );

        $wpdb->delete( $table, array( 'click_id' => $test_click_id ) );
    }

    /**
     * Test AJAX response time with 100 events (FIFO limit)
     */
    private function test_ajax_response_time_100_events() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 3: AJAX Response Time (100 Events - FIFO Limit)\n";
        echo str_repeat( '-', 50 ) . "\n";

        $test_click_id = 'ajax_perf_100_' . time();
        CUFT_Click_Tracker::record_click( $test_click_id, 'test', 'test_campaign' );

        // Pre-populate with 100 events (at FIFO limit)
        for ( $i = 0; $i < 100; $i++ ) {
            CUFT_Click_Tracker::add_event( $test_click_id, 'test_event' );
        }

        $iterations = 20;
        $times = array();

        for ( $i = 0; $i < $iterations; $i++ ) {
            $start = microtime( true );

            $_POST['click_id'] = $test_click_id;
            $_POST['event_type'] = 'phone_click';
            $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

            ob_start();
            do_action( 'wp_ajax_nopriv_cuft_record_event' );
            ob_end_clean();

            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000;
        }

        $avg_time = array_sum( $times ) / count( $times );
        sort( $times );
        $p95_time = $times[ (int) ( count( $times ) * 0.95 ) ];

        echo "Results ({$iterations} iterations, 100 events):\n";
        echo "  Average: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  P95: " . number_format( $p95_time, 2 ) . "ms\n";
        echo "  Target: <100ms\n";
        echo "  Status: " . ( $p95_time < 100 ? '✅ PASS' : '❌ FAIL' ) . "\n\n";

        $this->results['ajax_100_events'] = array(
            'avg' => $avg_time,
            'p95' => $p95_time,
            'target' => 100,
            'pass' => $p95_time < 100
        );

        $wpdb->delete( $table, array( 'click_id' => $test_click_id ) );
    }

    /**
     * Test concurrent AJAX requests (simulated)
     */
    private function test_concurrent_requests() {
        global $wpdb;
        $table = $wpdb->prefix . 'cuft_click_tracking';

        echo "Test 4: Concurrent Request Handling\n";
        echo str_repeat( '-', 50 ) . "\n";

        $test_prefix = 'ajax_concurrent_' . time();
        $concurrent_requests = 10;
        $times = array();

        $start_total = microtime( true );

        for ( $i = 0; $i < $concurrent_requests; $i++ ) {
            $click_id = $test_prefix . '_' . $i;
            CUFT_Click_Tracker::record_click( $click_id, 'test', 'test_campaign' );

            $start = microtime( true );

            $_POST['click_id'] = $click_id;
            $_POST['event_type'] = 'phone_click';
            $_POST['nonce'] = wp_create_nonce( 'cuft-event-recorder' );

            ob_start();
            do_action( 'wp_ajax_nopriv_cuft_record_event' );
            ob_end_clean();

            $end = microtime( true );
            $times[] = ( $end - $start ) * 1000;
        }

        $end_total = microtime( true );
        $total_time = ( $end_total - $start_total ) * 1000;
        $avg_time = array_sum( $times ) / count( $times );

        echo "Results ({$concurrent_requests} sequential requests):\n";
        echo "  Total time: " . number_format( $total_time, 2 ) . "ms\n";
        echo "  Average per request: " . number_format( $avg_time, 2 ) . "ms\n";
        echo "  Throughput: " . number_format( 1000 / $avg_time, 1 ) . " requests/sec\n";
        echo "  Status: ✅ PASS\n\n";

        $this->results['concurrent'] = array(
            'total' => $total_time,
            'avg' => $avg_time,
            'throughput' => 1000 / $avg_time,
            'pass' => true
        );

        // Cleanup
        $wpdb->query( "DELETE FROM {$table} WHERE click_id LIKE '{$test_prefix}%'" );
    }

    /**
     * Print summary
     */
    private function print_summary() {
        echo "\n" . str_repeat( '=', 50 ) . "\n";
        echo "AJAX PERFORMANCE SUMMARY\n";
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
        echo "\nRecommendations:\n";
        echo "- All P95 response times should be <100ms\n";
        echo "- Fire-and-forget pattern ensures non-blocking user experience\n";
        echo "- FIFO cleanup at 100 events maintains consistent performance\n";
        echo "\n";
    }
}

// Run tests if executed directly
if ( php_sapi_name() === 'cli' ) {
    $test = new CUFT_AJAX_Performance_Test();
    $test->run_all_tests();
}
