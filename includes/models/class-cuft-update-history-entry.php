<?php
/**
 * Update History Entry Model
 *
 * Data model for Update History Entry with FIFO persistence.
 * Tracks manual update operations for auditing and troubleshooting.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Includes/Models
 * @since      3.19.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT_Update_History_Entry class.
 *
 * Manages update operation history with FIFO retention (max 5 entries).
 * Entries auto-expire after 7 days.
 *
 * @since 3.19.0
 */
class CUFT_Update_History_Entry {

	/**
	 * Operation type constants
	 */
	const TYPE_MANUAL_CHECK    = 'manual_check';
	const TYPE_FORCE_REINSTALL = 'force_reinstall';

	/**
	 * Status constants
	 */
	const STATUS_COMPLETE = 'complete';
	const STATUS_FAILED   = 'failed';

	/**
	 * WordPress option key for update history
	 *
	 * @var string
	 */
	const OPTION_KEY = 'cuft_update_log';

	/**
	 * Maximum history entries to retain (FIFO)
	 *
	 * @var int
	 */
	const MAX_ENTRIES = 5;

	/**
	 * Entry data
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Constructor
	 *
	 * @param array $entry_data Entry data.
	 */
	public function __construct( $entry_data = array() ) {
		$defaults = array(
			'operation_type'     => '',
			'trigger_location'   => 'force_update_button',
			'user_id'            => get_current_user_id(),
			'user_display_name'  => '',
			'timestamp'          => time(),
			'status'             => self::STATUS_COMPLETE,
			'details'            => array(),
		);

		$this->data = wp_parse_args( $entry_data, $defaults );

		// Auto-populate user display name if not set
		if ( empty( $this->data['user_display_name'] ) && $this->data['user_id'] ) {
			$user = get_userdata( $this->data['user_id'] );
			if ( $user ) {
				$this->data['user_display_name'] = $user->display_name;
			}
		}
	}

	/**
	 * Log update operation to history
	 *
	 * Creates and persists a new history entry with FIFO enforcement.
	 *
	 * @param string $operation_type Operation type (TYPE_MANUAL_CHECK or TYPE_FORCE_REINSTALL).
	 * @param int    $user_id        WordPress user ID.
	 * @param string $status         Operation status (STATUS_COMPLETE or STATUS_FAILED).
	 * @param array  $details        Operation-specific details.
	 * @return array Created entry data.
	 */
	public static function log_operation( $operation_type, $user_id, $status, $details = array() ) {
		// Get current user display name
		$user = get_userdata( $user_id );
		$user_display_name = $user ? $user->display_name : 'Unknown';

		// Create entry
		$entry = array(
			'operation_type'    => $operation_type,
			'trigger_location'  => 'force_update_button',
			'user_id'           => $user_id,
			'user_display_name' => $user_display_name,
			'timestamp'         => time(),
			'status'            => $status,
			'details'           => $details,
		);

		// Get existing history
		$history = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $history ) ) {
			$history = array();
		}

		// Append new entry
		$history[] = $entry;

		// Enforce FIFO: keep only last MAX_ENTRIES entries
		if ( count( $history ) > self::MAX_ENTRIES ) {
			$history = array_slice( $history, -1 * self::MAX_ENTRIES );
		}

		// Save to database
		update_option( self::OPTION_KEY, $history, false );

		return $entry;
	}

	/**
	 * Get update history
	 *
	 * Returns recent update operations, most recent first.
	 *
	 * @param int $limit Maximum entries to return (default 5).
	 * @return array History entries.
	 */
	public static function get_history( $limit = 5 ) {
		$history = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $history ) ) {
			return array();
		}

		// Return most recent first (reverse order)
		$history = array_reverse( $history );

		// Apply limit
		if ( $limit > 0 && count( $history ) > $limit ) {
			$history = array_slice( $history, 0, $limit );
		}

		return $history;
	}

	/**
	 * Clear all history entries
	 *
	 * Deletes the update history option.
	 *
	 * @return bool True on success.
	 */
	public static function clear_history() {
		return delete_option( self::OPTION_KEY );
	}

	/**
	 * Convert entry to array for API responses
	 *
	 * @return array Entry data.
	 */
	public function to_array() {
		return $this->data;
	}
}
