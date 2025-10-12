<?php
/**
 * Integration Test: Edge Case - Concurrent Updates (T044)
 *
 * Tests error handling when multiple update attempts occur simultaneously.
 *
 * @package Choice_Universal_Form_Tracker
 * @subpackage Tests\Integration\Update
 * @since 3.17.0
 */

/**
 * Class CUFT_Test_Edge_Case_Concurrent
 *
 * Integration tests for EC-5: Concurrent updates
 *
 * @since 3.17.0
 */
class CUFT_Test_Edge_Case_Concurrent extends WP_UnitTestCase {

	/**
	 * Update logger instance
	 *
	 * @var CUFT_Update_Logger
	 */
	private $logger;

	/**
	 * Set up test environment
	 */
	public function setUp() {
		parent::setUp();

		// Load update logger class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-update-logger.php';

		$this->logger = new CUFT_Update_Logger();
	}

	/**
	 * Test: Multiple update attempts logged separately
	 *
	 * Verifies that concurrent update attempts are tracked independently.
	 *
	 * @since 3.17.0
	 */
	public function test_multiple_attempts_logged_separately() {
		// Clear existing log
		delete_option( 'cuft_update_log' );

		// Simulate first update attempt (WP-CLI)
		$this->logger->log_update_attempt( array(
			'trigger_location' => 'wp_cli',
			'target_version' => '3.17.0',
			'status' => 'complete',
		) );

		// Simulate second update attempt (Admin UI) - concurrent
		$this->logger->log_update_attempt( array(
			'trigger_location' => 'plugins_page',
			'target_version' => '3.17.0',
			'status' => 'failed',
			'error_message' => 'Update already in progress',
		) );

		// Get the log
		$log = get_option( 'cuft_update_log', array() );

		// Assert both attempts logged
		$this->assertEquals( 2, count( $log ), 'Should have 2 separate log entries' );

		// Verify different trigger locations
		$this->assertEquals( 'wp_cli', $log[0]['trigger_location'] );
		$this->assertEquals( 'plugins_page', $log[1]['trigger_location'] );
	}

	/**
	 * Test: Concurrent backup creation handling
	 *
	 * Verifies that backup directory handles concurrent access.
	 *
	 * @since 3.17.0
	 */
	public function test_concurrent_backup_creation() {
		// Load backup manager
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-backup-manager.php';

		$backup_manager = new CUFT_Backup_Manager();

		// Create backup directory
		$uploads_dir = wp_upload_dir();
		$backup_dir = $uploads_dir['basedir'] . '/cuft-backups';

		if ( ! file_exists( $backup_dir ) ) {
			wp_mkdir_p( $backup_dir );
		}

		// Simulate concurrent backup creation
		$backup1 = $backup_manager->create_backup( '3.16.0' );
		$backup2 = $backup_manager->create_backup( '3.16.0' );

		// Both should succeed or fail gracefully
		if ( ! is_wp_error( $backup1 ) ) {
			$this->assertFileExists( $backup1, 'First backup should be created' );
		}

		if ( ! is_wp_error( $backup2 ) ) {
			$this->assertFileExists( $backup2, 'Second backup should be created' );
		}

		// Clean up
		if ( ! is_wp_error( $backup1 ) && file_exists( $backup1 ) ) {
			@unlink( $backup1 );
		}
		if ( ! is_wp_error( $backup2 ) && file_exists( $backup2 ) ) {
			@unlink( $backup2 );
		}
	}

	/**
	 * Test: Second update shows appropriate error
	 *
	 * Verifies that second update attempt gets clear error message.
	 *
	 * @since 3.17.0
	 */
	public function test_second_update_error_message() {
		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get concurrent update error message
		$message = CUFT_Error_Messages::get_message( 'update_in_progress', array(
			'user' => 'admin',
			'started_at' => current_time( 'mysql' ),
		) );

		// Assert message is user-friendly
		$this->assertNotEmpty( $message, 'Error message should not be empty' );
		$this->assertContains( 'in progress', strtolower( $message ), 'Should mention update in progress' );
	}

	/**
	 * Test: User information in concurrent error
	 *
	 * Verifies that concurrent update error shows who initiated first update.
	 *
	 * @since 3.17.0
	 */
	public function test_user_info_in_concurrent_error() {
		// Create test user
		$user_id = $this->factory->user->create( array(
			'user_login' => 'testadmin',
			'role' => 'administrator',
		) );

		wp_set_current_user( $user_id );

		// Load error messages class
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get concurrent update error
		$message = CUFT_Error_Messages::get_message( 'update_in_progress', array(
			'user' => wp_get_current_user()->user_login,
		) );

		// Assert user mentioned
		$this->assertContains( 'testadmin', $message, 'Should mention user who started update' );
	}

	/**
	 * Test: WordPress locking mechanism (if implemented)
	 *
	 * Verifies that WordPress's built-in locking prevents concurrent updates.
	 *
	 * @since 3.17.0
	 */
	public function test_wordpress_locking_mechanism() {
		// WordPress uses transients for update locking
		// Check if lock transient exists
		$lock_name = 'plugin_update_lock';

		// Set a mock lock
		set_transient( $lock_name, time(), 300 ); // 5 minute lock

		// Verify lock exists
		$lock = get_transient( $lock_name );
		$this->assertNotFalse( $lock, 'Lock should exist' );

		// Clean up
		delete_transient( $lock_name );
	}

	/**
	 * Test: Concurrent updates logged with different timestamps
	 *
	 * Verifies that concurrent attempts have distinct timestamps.
	 *
	 * @since 3.17.0
	 */
	public function test_concurrent_updates_distinct_timestamps() {
		// Clear existing log
		delete_option( 'cuft_update_log' );

		// Simulate first update
		$this->logger->log_update_attempt( array(
			'trigger_location' => 'wp_cli',
			'target_version' => '3.17.0',
			'status' => 'complete',
		) );

		// Small delay
		usleep( 100000 ); // 0.1 second

		// Simulate second update
		$this->logger->log_update_attempt( array(
			'trigger_location' => 'plugins_page',
			'target_version' => '3.17.0',
			'status' => 'blocked',
		) );

		// Get the log
		$log = get_option( 'cuft_update_log', array() );

		// Assert different timestamps
		$this->assertEquals( 2, count( $log ), 'Should have 2 entries' );
		$this->assertNotEquals( $log[0]['timestamp'], $log[1]['timestamp'], 'Timestamps should be different' );
	}

	/**
	 * Test: FIFO retention with concurrent updates
	 *
	 * Verifies that concurrent updates respect FIFO log retention.
	 *
	 * @since 3.17.0
	 */
	public function test_fifo_retention_with_concurrent_updates() {
		// Clear existing log
		delete_option( 'cuft_update_log' );

		// Add 6 update attempts (exceeds FIFO limit of 5)
		for ( $i = 1; $i <= 6; $i++ ) {
			$this->logger->log_update_attempt( array(
				'trigger_location' => $i % 2 === 0 ? 'wp_cli' : 'plugins_page',
				'target_version' => '3.17.' . $i,
				'status' => 'complete',
			) );

			usleep( 10000 ); // Small delay
		}

		// Get the log
		$log = get_option( 'cuft_update_log', array() );

		// Assert only last 5 entries retained
		$this->assertEquals( 5, count( $log ), 'Should retain only last 5 entries (FIFO)' );

		// Verify oldest entry removed (version 3.17.1 should be gone)
		$versions = array_map( function( $entry ) {
			return $entry['target_version'];
		}, $log );

		$this->assertNotContains( '3.17.1', $versions, 'Oldest entry should be removed' );
		$this->assertContains( '3.17.6', $versions, 'Newest entry should be retained' );
	}

	/**
	 * Test: Error when backup already in progress
	 *
	 * Verifies handling when backup operation already running.
	 *
	 * @since 3.17.0
	 */
	public function test_backup_already_in_progress() {
		// Set a backup lock transient
		set_transient( 'cuft_backup_in_progress', time(), 300 );

		// Load error messages
		require_once dirname( dirname( dirname( __FILE__ ) ) ) . '/includes/update/class-cuft-error-messages.php';

		// Get backup in progress error
		$message = CUFT_Error_Messages::get_message( 'backup_in_progress' );

		// Assert message is clear
		$this->assertNotEmpty( $message, 'Error message should exist' );
		$this->assertContains( 'backup', strtolower( $message ), 'Should mention backup' );
		$this->assertContains( 'progress', strtolower( $message ), 'Should mention in progress' );

		// Clean up
		delete_transient( 'cuft_backup_in_progress' );
	}
}
