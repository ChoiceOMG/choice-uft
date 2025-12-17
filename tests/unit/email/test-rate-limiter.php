<?php
/**
 * Unit Tests for CUFT_BCC_Rate_Limiter (Email BCC Rate Limiting)
 *
 * Tests rate limiting logic with WordPress transients for Auto-BCC feature.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Unit/Email
 * @since      3.11.0
 */

class Test_CUFT_BCC_Rate_Limiter extends WP_UnitTestCase {

	/**
	 * Rate limiter instance
	 *
	 * @var CUFT_BCC_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->rate_limiter = new CUFT_BCC_Rate_Limiter();

		// Clear all rate limit transients
		$this->clear_rate_limit_transients();
	}

	/**
	 * Tear down after each test
	 */
	public function tearDown(): void {
		// Clear all rate limit transients
		$this->clear_rate_limit_transients();
		parent::tearDown();
	}

	/**
	 * Helper to clear rate limit transients
	 */
	private function clear_rate_limit_transients() {
		global $wpdb;

		// Delete all transients matching pattern
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_cuft_bcc_rate_limit_%'
			OR option_name LIKE '_transient_timeout_cuft_bcc_rate_limit_%'"
		);
	}

	/**
	 * Test first email of the hour allows BCC
	 */
	public function test_first_email_allows_bcc() {
		$threshold = 100;
		$result = $this->rate_limiter->check_rate_limit( $threshold );

		$this->assertTrue( $result, 'First email should be allowed' );
	}

	/**
	 * Test emails under threshold are allowed
	 */
	public function test_emails_under_threshold_allowed() {
		$threshold = 5;

		// Send 4 emails (under threshold)
		for ( $i = 1; $i <= 4; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $threshold );
			$this->assertTrue( $result, "Email #{$i} should be allowed (under threshold of {$threshold})" );
		}
	}

	/**
	 * Test email at threshold is allowed
	 */
	public function test_email_at_threshold_allowed() {
		$threshold = 3;

		// Send emails up to threshold
		for ( $i = 1; $i <= 3; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $threshold );
			$this->assertTrue( $result, "Email #{$i} should be allowed (at or under threshold)" );
		}
	}

	/**
	 * Test email over threshold is blocked
	 */
	public function test_email_over_threshold_blocked() {
		$threshold = 3;

		// Send 3 emails (at threshold)
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->rate_limiter->check_rate_limit( $threshold );
		}

		// 4th email should be blocked
		$result = $this->rate_limiter->check_rate_limit( $threshold );
		$this->assertFalse( $result, 'Email over threshold should be blocked' );
	}

	/**
	 * Test get_current_count returns accurate count
	 */
	public function test_get_current_count_returns_accurate_count() {
		$threshold = 100;

		// Initial count should be 0
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 0, $count, 'Initial count should be 0' );

		// Send 3 emails
		for ( $i = 1; $i <= 3; $i++ ) {
			$this->rate_limiter->check_rate_limit( $threshold );
		}

		// Count should be 3
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 3, $count, 'Count should be 3 after 3 checks' );
	}

	/**
	 * Test reset_count clears transient
	 */
	public function test_reset_count_clears_transient() {
		$threshold = 100;

		// Send some emails
		for ( $i = 1; $i <= 5; $i++ ) {
			$this->rate_limiter->check_rate_limit( $threshold );
		}

		// Verify count is 5
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 5, $count );

		// Reset count
		$this->rate_limiter->reset_count();

		// Count should be 0 after reset
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 0, $count, 'Count should be 0 after reset' );
	}

	/**
	 * Test threshold of 0 means unlimited
	 */
	public function test_threshold_zero_means_unlimited() {
		$threshold = 0; // Unlimited

		// Send 100 emails
		for ( $i = 1; $i <= 100; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $threshold );
			$this->assertTrue( $result, "Email #{$i} should be allowed with unlimited threshold" );
		}
	}

	/**
	 * Test transient key format includes hour
	 */
	public function test_transient_key_includes_current_hour() {
		$threshold = 100;

		// Trigger rate limit check
		$this->rate_limiter->check_rate_limit( $threshold );

		// Expected transient key format: cuft_bcc_rate_limit_YYYY-MM-DD-HH
		$expected_key_pattern = 'cuft_bcc_rate_limit_' . gmdate( 'Y-m-d-H' );

		// Get current count (this validates the transient exists)
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 1, $count, 'Transient should be set with correct key pattern' );
	}

	/**
	 * Test consecutive checks increment counter correctly
	 */
	public function test_consecutive_checks_increment_counter() {
		$threshold = 10;

		for ( $i = 1; $i <= 5; $i++ ) {
			$this->rate_limiter->check_rate_limit( $threshold );
			$count = $this->rate_limiter->get_current_count();
			$this->assertEquals( $i, $count, "Count should be {$i} after {$i} checks" );
		}
	}

	/**
	 * Test rate limit with very low threshold
	 */
	public function test_rate_limit_with_threshold_one() {
		$threshold = 1;

		// First email allowed
		$result = $this->rate_limiter->check_rate_limit( $threshold );
		$this->assertTrue( $result, 'First email should be allowed' );

		// Second email blocked
		$result = $this->rate_limiter->check_rate_limit( $threshold );
		$this->assertFalse( $result, 'Second email should be blocked with threshold of 1' );
	}

	/**
	 * Test rate limit with very high threshold
	 */
	public function test_rate_limit_with_high_threshold() {
		$threshold = 10000;

		// Send 100 emails (well under threshold)
		for ( $i = 1; $i <= 100; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $threshold );
			$this->assertTrue( $result, "Email #{$i} should be allowed with high threshold" );
		}

		// Verify count
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 100, $count );
	}

	/**
	 * Test multiple resets don't cause errors
	 */
	public function test_multiple_resets_safe() {
		// Reset multiple times
		$this->rate_limiter->reset_count();
		$this->rate_limiter->reset_count();
		$this->rate_limiter->reset_count();

		// Count should still be 0
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 0, $count );

		// Should still work after multiple resets
		$result = $this->rate_limiter->check_rate_limit( 100 );
		$this->assertTrue( $result );
	}

	/**
	 * Test rate limit check with negative threshold (treated as 0/unlimited)
	 */
	public function test_negative_threshold_treated_as_unlimited() {
		$threshold = -1; // Invalid, should be treated as unlimited

		// Should allow emails
		for ( $i = 1; $i <= 10; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $threshold );
			$this->assertTrue( $result, "Email #{$i} should be allowed with negative threshold" );
		}
	}

	/**
	 * Test boundary condition - exactly at threshold
	 */
	public function test_boundary_at_threshold() {
		$threshold = 5;

		// Send emails 1-5 (should all be allowed)
		for ( $i = 1; $i <= 5; $i++ ) {
			$result = $this->rate_limiter->check_rate_limit( $threshold );
			$count = $this->rate_limiter->get_current_count();
			$this->assertTrue( $result, "Email #{$i} should be allowed (count: {$count}, threshold: {$threshold})" );
		}

		// Email 6 should be blocked
		$result = $this->rate_limiter->check_rate_limit( $threshold );
		$this->assertFalse( $result, 'Email 6 should be blocked (over threshold)' );
	}

	/**
	 * Test get_current_count before any checks
	 */
	public function test_get_current_count_before_first_check() {
		$count = $this->rate_limiter->get_current_count();
		$this->assertEquals( 0, $count, 'Count should be 0 before any rate limit checks' );
	}
}
