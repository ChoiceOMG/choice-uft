<?php
/**
 * Cron Manager for Automatic Updates
 *
 * Manages scheduled update checks via WordPress cron.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT Cron Manager
 *
 * Handles cron scheduling for automatic update checks.
 */
class CUFT_Cron_Manager {

	/**
	 * Cron hook name for update checks
	 */
	const CRON_HOOK = 'cuft_check_updates';

	/**
	 * Cron hook name for history cleanup (Feature 009 - v3.19.0)
	 */
	const CLEANUP_HOOK = 'cuft_daily_cleanup';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Register cron callback
		add_action( self::CRON_HOOK, array( $this, 'check_updates' ) );

		// Register history cleanup callback (Feature 009 - v3.19.0)
		add_action( self::CLEANUP_HOOK, array( $this, 'cleanup_old_history' ) );

		// Add custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Schedule initial event on plugin activation
		register_activation_hook( CUFT_PLUGIN_FILE, array( $this, 'schedule_initial_event' ) );

		// Clear event on plugin deactivation
		register_deactivation_hook( CUFT_PLUGIN_FILE, array( $this, 'clear_scheduled_event' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public function add_cron_schedules( $schedules ) {
		// Twice daily (12 hours)
		$schedules['twicedaily'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display' => __( 'Twice Daily', 'choice-uft' ),
		);

		// Weekly
		$schedules['weekly'] = array(
			'interval' => 7 * DAY_IN_SECONDS,
			'display' => __( 'Once Weekly', 'choice-uft' ),
		);

		return $schedules;
	}

	/**
	 * Schedule initial cron event
	 *
	 * Called on plugin activation.
	 *
	 * @return void
	 */
	public function schedule_initial_event() {
		// Don't schedule if already scheduled
		if ( wp_next_scheduled( self::CRON_HOOK ) ) {
			return;
		}

		// Get update configuration
		$config = CUFT_Update_Configuration::get();

		// Only schedule if enabled
		if ( empty( $config['enabled'] ) ) {
			return;
		}

		// Get frequency (default to twicedaily)
		$frequency = ! empty( $config['check_frequency'] ) ? $config['check_frequency'] : 'twicedaily';

		// Don't schedule if manual
		if ( $frequency === 'manual' ) {
			return;
		}

		// Calculate next run time based on frequency
		$next_run = time();
		if ( $frequency === 'twicedaily' ) {
			// Schedule for next 12-hour interval (either noon or midnight)
			$next_run = time() + ( 12 * HOUR_IN_SECONDS );
		} elseif ( $frequency === 'daily' ) {
			// Schedule for tomorrow at same time
			$next_run = time() + DAY_IN_SECONDS;
		} elseif ( $frequency === 'weekly' ) {
			// Schedule for next week
			$next_run = time() + WEEK_IN_SECONDS;
		}

		// Schedule event
		wp_schedule_event( $next_run, $frequency, self::CRON_HOOK );

		// Log scheduling
		CUFT_Update_Log::log( 'cron_scheduled', 'info', array(
			'details' => sprintf( 'Scheduled automatic update checks: %s', $frequency ),
		) );
	}

	/**
	 * Clear scheduled cron event
	 *
	 * Called on plugin deactivation.
	 *
	 * @return void
	 */
	public function clear_scheduled_event() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );

			// Log clearing
			CUFT_Update_Log::log( 'cron_cleared', 'info', array(
				'details' => 'Cleared scheduled update checks',
			) );
		}
	}

	/**
	 * Reschedule cron event with new frequency
	 *
	 * @param string $frequency Cron frequency (twicedaily, daily, weekly, manual)
	 * @return bool True on success
	 */
	public static function reschedule( $frequency ) {
		// Clear existing schedule
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}

		// Don't schedule if manual
		if ( $frequency === 'manual' ) {
			CUFT_Update_Log::log( 'cron_disabled', 'info', array(
				'details' => 'Automatic update checks disabled (manual mode)',
			) );
			return true;
		}

		// Schedule new event
		$result = wp_schedule_event( time(), $frequency, self::CRON_HOOK );

		if ( $result !== false ) {
			CUFT_Update_Log::log( 'cron_rescheduled', 'info', array(
				'details' => sprintf( 'Rescheduled automatic update checks: %s', $frequency ),
			) );
		}

		return $result !== false;
	}

	/**
	 * Cron callback: Check for updates
	 *
	 * This is called by WordPress cron on the scheduled interval.
	 *
	 * @return void
	 */
	public function check_updates() {
		// Verify configuration is still enabled
		$config = CUFT_Update_Configuration::get();

		if ( empty( $config['enabled'] ) ) {
			CUFT_Update_Log::log( 'cron_check_skipped', 'info', array(
				'details' => 'Automatic update check skipped (disabled in settings)',
			) );
			return;
		}

		// Don't check if update is in progress
		if ( CUFT_Update_Progress::is_in_progress() ) {
			CUFT_Update_Log::log( 'cron_check_skipped', 'info', array(
				'details' => 'Automatic update check skipped (update in progress)',
			) );
			return;
		}

		// Log cron check
		CUFT_Update_Log::log( 'cron_check_started', 'info', array(
			'details' => 'Automatic update check started via cron',
		) );

		// Perform update check
		try {
			$result = CUFT_Update_Checker::check( false );

			if ( $result['success'] ) {
				CUFT_Update_Log::log( 'cron_check_completed', 'success', array(
					'details' => 'Automatic update check completed',
					'update_available' => ! empty( $result['update_available'] ),
					'latest_version' => ! empty( $result['latest_version'] ) ? $result['latest_version'] : null,
				) );
			} else {
				CUFT_Update_Log::log( 'cron_check_failed', 'error', array(
					'details' => 'Automatic update check failed: ' . ( ! empty( $result['error'] ) ? $result['error'] : 'Unknown error' ),
				) );
			}
		} catch ( Exception $e ) {
			CUFT_Update_Log::log_error( 'Cron update check exception: ' . $e->getMessage() );
		}
	}

	/**
	 * Get next scheduled check time
	 *
	 * @return int|null Timestamp or null if not scheduled
	 */
	public static function get_next_scheduled_time() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Get next scheduled check in human-readable format
	 *
	 * @return string|null Human-readable time or null
	 */
	public static function get_next_scheduled_human() {
		$timestamp = self::get_next_scheduled_time();

		if ( ! $timestamp ) {
			return null;
		}

		// If the scheduled time is in the past, return "due now"
		if ( $timestamp < time() ) {
			return __( 'due now', 'choice-uft' );
		}

		// Return time difference with "in" prefix
		return sprintf(
			/* translators: %s: human time difference */
			__( 'in %s', 'choice-uft' ),
			human_time_diff( time(), $timestamp )
		);
	}

	/**
	 * Check if cron is scheduled
	 *
	 * @return bool True if scheduled
	 */
	public static function is_scheduled() {
		return (bool) self::get_next_scheduled_time();
	}

	/**
	 * Trigger manual check now
	 *
	 * This bypasses the cron schedule and runs immediately.
	 *
	 * @return void
	 */
	public static function trigger_manual_check() {
		// Schedule immediate execution
		wp_schedule_single_event( time(), self::CRON_HOOK );

		// Spawn cron to execute immediately
		spawn_cron();
	}

	/**
	 * Schedule daily history cleanup (Feature 009 - v3.19.0)
	 *
	 * Called on plugin activation.
	 *
	 * @return void
	 */
	public static function schedule_history_cleanup() {
		// Don't schedule if already scheduled
		if ( wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			return;
		}

		// Schedule daily cleanup
		wp_schedule_event( time(), 'daily', self::CLEANUP_HOOK );
	}

	/**
	 * Cleanup old history entries (Feature 009 - v3.19.0)
	 *
	 * Cron callback: Remove history entries older than 7 days.
	 *
	 * @return void
	 */
	public static function cleanup_old_history() {
		// Get update history
		$history = get_option( CUFT_Update_History_Entry::OPTION_KEY, array() );

		if ( ! is_array( $history ) || empty( $history ) ) {
			return;
		}

		// Filter entries: keep only those newer than 7 days
		$seven_days_ago = time() - ( 7 * DAY_IN_SECONDS );
		$filtered_history = array_filter( $history, function( $entry ) use ( $seven_days_ago ) {
			return isset( $entry['timestamp'] ) && $entry['timestamp'] > $seven_days_ago;
		} );

		// Update option if any entries were removed
		if ( count( $filtered_history ) < count( $history ) ) {
			update_option( CUFT_Update_History_Entry::OPTION_KEY, array_values( $filtered_history ), false );

			// Log cleanup action if debug mode enabled
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$removed_count = count( $history ) - count( $filtered_history );
				error_log( sprintf( 'CUFT: Cleaned up %d old history entries (older than 7 days)', $removed_count ) );
			}
		}
	}
}

// Initialize cron manager
new CUFT_Cron_Manager();
