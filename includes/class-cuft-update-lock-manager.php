<?php
/**
 * Update Lock Manager
 *
 * Transient-based locking mechanism to prevent concurrent update operations
 * by multiple administrators.
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
 * CUFT_Update_Lock_Manager class.
 *
 * Provides transient-based locking to prevent concurrent update operations.
 * Lock automatically expires after 120 seconds to prevent orphaned locks.
 *
 * @since 3.19.0
 */
class CUFT_Update_Lock_Manager {

	/**
	 * Transient key for update lock
	 *
	 * @var string
	 */
	const LOCK_TRANSIENT = 'cuft_force_update_lock';

	/**
	 * Lock TTL in seconds (120 seconds = 2 minutes)
	 *
	 * @var int
	 */
	const LOCK_TTL = 120;

	/**
	 * Acquire update operation lock
	 *
	 * @param string $operation_type Operation type ('check_updates' or 'force_reinstall').
	 * @param int    $user_id        WordPress user ID acquiring the lock.
	 * @return bool|WP_Error True on success, WP_Error if lock already exists.
	 */
	public static function acquire_lock( $operation_type, $user_id ) {
		// Check if lock already exists
		$existing_lock = get_transient( self::LOCK_TRANSIENT );

		if ( false !== $existing_lock ) {
			return new WP_Error(
				'operation_in_progress',
				__( 'Another update operation is already in progress. Please wait.', 'choice-universal-form-tracker' ),
				array(
					'lock_info' => $existing_lock,
				)
			);
		}

		// Create lock
		$lock_data = array(
			'user_id'    => $user_id,
			'operation'  => $operation_type,
			'started_at' => time(),
		);

		set_transient( self::LOCK_TRANSIENT, $lock_data, self::LOCK_TTL );

		return true;
	}

	/**
	 * Release update operation lock
	 *
	 * @return bool True if lock was deleted, false otherwise.
	 */
	public static function release_lock() {
		return delete_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Check if update operation is locked
	 *
	 * @return bool True if locked, false otherwise.
	 */
	public static function is_locked() {
		return false !== get_transient( self::LOCK_TRANSIENT );
	}

	/**
	 * Get current lock information
	 *
	 * @return array|false Lock details array or false if not locked.
	 */
	public static function get_lock_info() {
		$lock_data = get_transient( self::LOCK_TRANSIENT );

		if ( false === $lock_data ) {
			return false;
		}

		// Enrich with user display name if available
		if ( isset( $lock_data['user_id'] ) ) {
			$user = get_userdata( $lock_data['user_id'] );
			if ( $user ) {
				$lock_data['user_display_name'] = $user->display_name;
			}
		}

		return $lock_data;
	}
}
