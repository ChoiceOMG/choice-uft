<?php
/**
 * Force Update Handler Service
 *
 * Orchestrator class that coordinates force update operations using
 * infrastructure and model classes.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Includes
 * @since      3.19.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT_Force_Update_Handler class.
 *
 * Coordinates manual update checks and force reinstall operations with
 * locking, disk validation, backup/restore, and history tracking.
 *
 * @since 3.19.0
 */
class CUFT_Force_Update_Handler {

	/**
	 * Timeout for update check operations (5 seconds)
	 *
	 * @var int
	 */
	const CHECK_TIMEOUT = 5;

	/**
	 * Timeout for force reinstall operations (60 seconds)
	 *
	 * @var int
	 */
	const REINSTALL_TIMEOUT = 60;

	/**
	 * Handle manual update check operation
	 *
	 * Performs manual check for plugin updates from GitHub, bypassing
	 * WordPress's automatic schedule.
	 *
	 * @return array Response array for AJAX.
	 */
	public static function handle_check_updates() {
		$start_time = time();
		$user_id = get_current_user_id();

		// Create update check request model
		$request = new CUFT_Update_Check_Request( $user_id );

		try {
			// Try to acquire lock
			$lock_acquired = CUFT_Update_Lock_Manager::acquire_lock( 'check_updates', $user_id );

			if ( is_wp_error( $lock_acquired ) ) {
				// Log failed operation
				CUFT_Update_History_Entry::log_operation(
					CUFT_Update_History_Entry::TYPE_MANUAL_CHECK,
					$user_id,
					CUFT_Update_History_Entry::STATUS_FAILED,
					array(
						'error_message' => $lock_acquired->get_error_message(),
					)
				);

				return array(
					'success'    => false,
					'error_code' => $lock_acquired->get_error_code(),
					'message'    => $lock_acquired->get_error_message(),
				);
			}

			// Check for updates using existing update checker (force = true)
			$check_result = CUFT_Update_Checker::check( true );

			// Check timeout
			if ( ( time() - $start_time ) >= self::CHECK_TIMEOUT ) {
				$request->set_status( CUFT_Update_Check_Request::STATUS_TIMEOUT );

				// Release lock
				CUFT_Update_Lock_Manager::release_lock();

				// Log timeout
				CUFT_Update_History_Entry::log_operation(
					CUFT_Update_History_Entry::TYPE_MANUAL_CHECK,
					$user_id,
					CUFT_Update_History_Entry::STATUS_FAILED,
					array(
						'error_message' => 'GitHub API did not respond within 5 seconds',
					)
				);

				return array(
					'success'    => false,
					'error_code' => 'github_timeout',
					'message'    => __( 'GitHub API did not respond within 5 seconds. Please try again later.', 'choice-universal-form-tracker' ),
				);
			}

			// Handle check failure
			if ( ! $check_result['success'] ) {
				$request->set_error( $check_result['error'] );

				// Release lock
				CUFT_Update_Lock_Manager::release_lock();

				// Determine error code based on failure type
				$error_code = 'check_failed';
				if ( isset( $check_result['rate_limit'] ) ) {
					$error_code = 'rate_limited';
				}

				// Log failed operation
				CUFT_Update_History_Entry::log_operation(
					CUFT_Update_History_Entry::TYPE_MANUAL_CHECK,
					$user_id,
					CUFT_Update_History_Entry::STATUS_FAILED,
					array(
						'error_message' => $check_result['error'],
					)
				);

				return array(
					'success'    => false,
					'error_code' => $error_code,
					'message'    => $check_result['error'],
				);
			}

			// Clear plugin cache to force WordPress to recognize new version
			CUFT_Cache_Clearer::clear_plugin_cache();

			// Update plugin installation state cache
			$installed_version = defined( 'CUFT_VERSION' ) ? CUFT_VERSION : '0.0.0';
			$latest_version = $check_result['latest_version'];

			CUFT_Plugin_Installation_State::set( $installed_version, $latest_version );

			// Set GitHub response in request model
			$request->set_github_response(
				$latest_version,
				$check_result['published_date'] ?? '',
				$check_result['changelog'] ?? '',
				$check_result['download_url'] ?? ''
			);

			// Release lock
			CUFT_Update_Lock_Manager::release_lock();

			// Log successful operation
			CUFT_Update_History_Entry::log_operation(
				CUFT_Update_History_Entry::TYPE_MANUAL_CHECK,
				$user_id,
				CUFT_Update_History_Entry::STATUS_COMPLETE,
				array(
					'installed_version' => $installed_version,
					'latest_version'    => $latest_version,
					'update_available'  => $check_result['update_available'],
				)
			);

			// Build success response
			$message = $check_result['update_available']
				? sprintf( __( 'A new version (%s) is available!', 'choice-universal-form-tracker' ), $latest_version )
				: sprintf( __( 'Plugin is up to date (version %s)', 'choice-universal-form-tracker' ), $installed_version );

			return array(
				'success'              => true,
				'installed_version'    => $installed_version,
				'latest_version'       => $latest_version,
				'update_available'     => $check_result['update_available'],
				'release_date'         => $check_result['published_date'] ?? '',
				'changelog_summary'    => $check_result['changelog'] ?? '',
				'download_url'         => $check_result['download_url'] ?? '',
				'last_check'           => time(),
				'message'              => $message,
			);

		} catch ( Exception $e ) {
			// Release lock if acquired
			CUFT_Update_Lock_Manager::release_lock();

			// Log exception
			CUFT_Update_History_Entry::log_operation(
				CUFT_Update_History_Entry::TYPE_MANUAL_CHECK,
				$user_id,
				CUFT_Update_History_Entry::STATUS_FAILED,
				array(
					'error_message' => $e->getMessage(),
				)
			);

			return array(
				'success'    => false,
				'error_code' => 'exception',
				'message'    => __( 'An unexpected error occurred while checking for updates.', 'choice-universal-form-tracker' ),
			);
		}
	}

	/**
	 * Handle force reinstall operation
	 *
	 * Downloads and reinstalls the latest plugin version from GitHub,
	 * with backup/restore and rollback capabilities.
	 *
	 * @return array Response array for AJAX.
	 */
	public static function handle_force_reinstall() {
		$start_time = time();
		$user_id = get_current_user_id();

		// Get current and target versions
		$source_version = defined( 'CUFT_VERSION' ) ? CUFT_VERSION : '0.0.0';

		// Create force reinstall operation model
		$operation = new CUFT_Force_Reinstall_Operation(
			array(
				'source_version' => $source_version,
				'target_version' => '', // Will be set after GitHub check
			)
		);

		try {
			// Try to acquire lock
			$lock_acquired = CUFT_Update_Lock_Manager::acquire_lock( 'force_reinstall', $user_id );

			if ( is_wp_error( $lock_acquired ) ) {
				return array(
					'success'    => false,
					'error_code' => $lock_acquired->get_error_code(),
					'message'    => $lock_acquired->get_error_message(),
				);
			}

			// Get latest version from GitHub
			$check_result = CUFT_Update_Checker::check( true );

			if ( ! $check_result['success'] ) {
				CUFT_Update_Lock_Manager::release_lock();

				return array(
					'success'    => false,
					'error_code' => 'github_check_failed',
					'message'    => $check_result['error'],
				);
			}

			$target_version = $check_result['latest_version'];
			$download_url = $check_result['download_url'];

			// Update operation with target version
			$operation = new CUFT_Force_Reinstall_Operation(
				array(
					'source_version' => $source_version,
					'target_version' => $target_version,
				)
			);

			// Validate disk space (3x plugin size)
			$plugin_path = WP_PLUGIN_DIR . '/choice-uft';
			$space_valid = CUFT_Disk_Space_Validator::validate_space_for_reinstall( $plugin_path, 3 );

			if ( is_wp_error( $space_valid ) ) {
				CUFT_Update_Lock_Manager::release_lock();

				$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_FAILED );
				$operation->set_error_details(
					$space_valid->get_error_code(),
					$space_valid->get_error_message(),
					$space_valid->get_error_data()
				);
				$operation->mark_completed();

				// Log failed operation
				self::log_reinstall_operation( $user_id, $operation, CUFT_Update_History_Entry::STATUS_FAILED );

				return array(
					'success'            => false,
					'error_code'         => $space_valid->get_error_code(),
					'message'            => $space_valid->get_error_message(),
					'required_space_mb'  => $space_valid->get_error_data()['required_space_mb'] ?? 0,
					'available_space_mb' => $space_valid->get_error_data()['available_space_mb'] ?? 0,
				);
			}

			// Create backup
			$backup_manager = new CUFT_Backup_Manager();
			$backup_result = $backup_manager->create_backup( $source_version );

			if ( is_wp_error( $backup_result ) ) {
				CUFT_Update_Lock_Manager::release_lock();

				$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_FAILED );
				$operation->set_error_details(
					$backup_result->get_error_code(),
					$backup_result->get_error_message()
				);
				$operation->mark_completed();

				// Log failed operation
				self::log_reinstall_operation( $user_id, $operation, CUFT_Update_History_Entry::STATUS_FAILED );

				return array(
					'success'    => false,
					'error_code' => 'backup_failed',
					'message'    => __( 'Failed to create backup before reinstalling. Operation aborted.', 'choice-universal-form-tracker' ),
					'details'    => $backup_result->get_error_message(),
				);
			}

			// Set backup location in operation
			$operation->set_backup_location( $backup_result );
			$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_BACKUP_CREATED );

			// Check timeout
			if ( ( time() - $start_time ) >= self::REINSTALL_TIMEOUT ) {
				CUFT_Update_Lock_Manager::release_lock();

				return self::handle_timeout( $operation, $user_id, $start_time, 'backup' );
			}

			// Perform WordPress plugin update using WP_Upgrader
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/misc.php';
			require_once ABSPATH . 'wp-admin/includes/plugin.php';

			$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_DOWNLOADING );

			// Use WordPress plugin upgrader
			$upgrader = new Plugin_Upgrader( new WP_Ajax_Upgrader_Skin() );
			$install_result = $upgrader->install( $download_url, array( 'overwrite_package' => true ) );

			// Check timeout
			if ( ( time() - $start_time ) >= self::REINSTALL_TIMEOUT ) {
				// Attempt rollback
				$backup_manager->restore_backup( $backup_result );
				CUFT_Update_Lock_Manager::release_lock();

				return self::handle_timeout( $operation, $user_id, $start_time, 'installing' );
			}

			// Check for installation errors
			if ( is_wp_error( $install_result ) ) {
				// Installation failed, attempt rollback
				$rollback_result = $backup_manager->restore_backup( $backup_result );

				CUFT_Update_Lock_Manager::release_lock();

				if ( is_wp_error( $rollback_result ) ) {
					$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_FAILED );
				} else {
					$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_ROLLED_BACK );
				}

				$operation->set_error_details(
					'installation_failed',
					$install_result->get_error_message()
				);
				$operation->mark_completed();

				// Log failed operation
				self::log_reinstall_operation( $user_id, $operation, CUFT_Update_History_Entry::STATUS_FAILED );

				return array(
					'success'             => false,
					'error_code'          => 'installation_failed',
					'message'             => __( 'Installation failed. Restored previous version from backup.', 'choice-universal-form-tracker' ),
					'source_version'      => $source_version,
					'target_version'      => $target_version,
					'rollback_successful' => ! is_wp_error( $rollback_result ),
					'error_details'       => $install_result->get_error_message(),
				);
			}

			// Installation successful
			$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_SUCCESS );
			$operation->mark_completed();

			// Delete backup file
			$backup_manager->delete_backup( $backup_result );

			// Clear plugin cache
			CUFT_Cache_Clearer::clear_plugin_cache();

			// Release lock
			CUFT_Update_Lock_Manager::release_lock();

			// Log successful operation
			$duration = time() - $start_time;
			self::log_reinstall_operation( $user_id, $operation, CUFT_Update_History_Entry::STATUS_COMPLETE, $duration );

			return array(
				'success'         => true,
				'message'         => sprintf( __( 'Plugin successfully reinstalled to version %s', 'choice-universal-form-tracker' ), $target_version ),
				'source_version'  => $source_version,
				'target_version'  => $target_version,
				'duration_seconds' => $duration,
				'backup_location' => basename( $backup_result ),
				'backup_deleted'  => true,
			);

		} catch ( Exception $e ) {
			// Release lock if acquired
			CUFT_Update_Lock_Manager::release_lock();

			$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_FAILED );
			$operation->set_error_details( 'exception', $e->getMessage() );
			$operation->mark_completed();

			// Log exception
			self::log_reinstall_operation( $user_id, $operation, CUFT_Update_History_Entry::STATUS_FAILED );

			return array(
				'success'    => false,
				'error_code' => 'exception',
				'message'    => __( 'An unexpected error occurred during force reinstall.', 'choice-universal-form-tracker' ),
			);
		}
	}

	/**
	 * Handle timeout scenario
	 *
	 * @param CUFT_Force_Reinstall_Operation $operation  Operation instance.
	 * @param int                            $user_id    User ID.
	 * @param int                            $start_time Start time.
	 * @param string                         $stage      Stage where timeout occurred.
	 * @return array Error response.
	 */
	private static function handle_timeout( $operation, $user_id, $start_time, $stage ) {
		$elapsed = time() - $start_time;

		$operation->set_status( CUFT_Force_Reinstall_Operation::STATUS_FAILED );
		$operation->set_error_details(
			'operation_timeout',
			sprintf( 'Operation exceeded %d second timeout at stage: %s', self::REINSTALL_TIMEOUT, $stage )
		);
		$operation->mark_completed();

		// Log timeout
		self::log_reinstall_operation( $user_id, $operation, CUFT_Update_History_Entry::STATUS_FAILED );

		return array(
			'success'        => false,
			'error_code'     => 'operation_timeout',
			'message'        => sprintf( __( 'Operation exceeded %d second timeout. Plugin remains at version %s.', 'choice-universal-form-tracker' ), self::REINSTALL_TIMEOUT, $operation->get_source_version() ),
			'elapsed_seconds' => $elapsed,
			'last_stage'     => $stage,
		);
	}

	/**
	 * Log force reinstall operation to history
	 *
	 * @param int                            $user_id   User ID.
	 * @param CUFT_Force_Reinstall_Operation $operation Operation instance.
	 * @param string                         $status    Operation status.
	 * @param int                            $duration  Duration in seconds (optional).
	 * @return void
	 */
	private static function log_reinstall_operation( $user_id, $operation, $status, $duration = 0 ) {
		$details = array(
			'source_version'  => $operation->get_source_version(),
			'target_version'  => $operation->get_target_version(),
			'backup_location' => $operation->get_backup_location() ? basename( $operation->get_backup_location() ) : null,
			'duration_seconds' => $duration,
		);

		if ( $status === CUFT_Update_History_Entry::STATUS_FAILED ) {
			$error_details = $operation->get_error_details();
			if ( $error_details ) {
				$details['error_code'] = $error_details['error_code'] ?? null;
				$details['error_message'] = $error_details['error_message'] ?? null;
			}
		}

		CUFT_Update_History_Entry::log_operation(
			CUFT_Update_History_Entry::TYPE_FORCE_REINSTALL,
			$user_id,
			$status,
			$details
		);
	}
}
