<?php
/**
 * Force Reinstall Operation Model
 *
 * Data model for Force Reinstall Operation with state tracking.
 * Used to track the lifecycle of a force reinstall from start to completion.
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
 * CUFT_Force_Reinstall_Operation class.
 *
 * Represents a force reinstall operation with full state tracking for
 * recovery and auditing.
 *
 * @since 3.19.0
 */
class CUFT_Force_Reinstall_Operation {

	/**
	 * Status constants
	 */
	const STATUS_PENDING          = 'pending';
	const STATUS_BACKUP_CREATED   = 'backup_created';
	const STATUS_DOWNLOADING      = 'downloading';
	const STATUS_VALIDATING       = 'validating';
	const STATUS_INSTALLING       = 'installing';
	const STATUS_SUCCESS          = 'success';
	const STATUS_FAILED           = 'failed';
	const STATUS_ROLLED_BACK      = 'rolled_back';

	/**
	 * Operation data
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Constructor
	 *
	 * @param array $operation_data Initial operation data.
	 */
	public function __construct( $operation_data = array() ) {
		$defaults = array(
			'source_version'  => '',
			'target_version'  => '',
			'backup_location' => null,
			'status'          => self::STATUS_PENDING,
			'error_details'   => null,
			'started_at'      => time(),
			'completed_at'    => null,
		);

		$this->data = wp_parse_args( $operation_data, $defaults );
	}

	/**
	 * Get source version
	 *
	 * @return string Source version.
	 */
	public function get_source_version() {
		return $this->data['source_version'];
	}

	/**
	 * Get target version
	 *
	 * @return string Target version.
	 */
	public function get_target_version() {
		return $this->data['target_version'];
	}

	/**
	 * Get backup location
	 *
	 * @return string|null Backup file path or null.
	 */
	public function get_backup_location() {
		return $this->data['backup_location'];
	}

	/**
	 * Get status
	 *
	 * @return string Current status.
	 */
	public function get_status() {
		return $this->data['status'];
	}

	/**
	 * Get error details
	 *
	 * @return array|null Error details or null.
	 */
	public function get_error_details() {
		return $this->data['error_details'];
	}

	/**
	 * Get started at timestamp
	 *
	 * @return int Unix timestamp.
	 */
	public function get_started_at() {
		return $this->data['started_at'];
	}

	/**
	 * Get completed at timestamp
	 *
	 * @return int|null Unix timestamp or null if not completed.
	 */
	public function get_completed_at() {
		return $this->data['completed_at'];
	}

	/**
	 * Set operation status
	 *
	 * Validates state transitions before setting new status.
	 *
	 * @param string $status New status.
	 * @return bool|WP_Error True on success, WP_Error on invalid transition.
	 */
	public function set_status( $status ) {
		$valid_statuses = array(
			self::STATUS_PENDING,
			self::STATUS_BACKUP_CREATED,
			self::STATUS_DOWNLOADING,
			self::STATUS_VALIDATING,
			self::STATUS_INSTALLING,
			self::STATUS_SUCCESS,
			self::STATUS_FAILED,
			self::STATUS_ROLLED_BACK,
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new WP_Error(
				'invalid_status',
				sprintf( 'Invalid status: %s', $status )
			);
		}

		// Validate state transitions (cannot go backwards from terminal states)
		$current_status = $this->data['status'];
		$terminal_states = array( self::STATUS_SUCCESS, self::STATUS_ROLLED_BACK );

		if ( in_array( $current_status, $terminal_states, true ) && $status !== $current_status ) {
			return new WP_Error(
				'invalid_transition',
				sprintf( 'Cannot transition from terminal state %s to %s', $current_status, $status )
			);
		}

		$this->data['status'] = $status;

		return true;
	}

	/**
	 * Set backup location
	 *
	 * Validates path is within allowed backup directory.
	 *
	 * @param string $path Backup file path.
	 * @return bool|WP_Error True on success, WP_Error on invalid path.
	 */
	public function set_backup_location( $path ) {
		// Validate path is within wp-content/uploads/cuft-backups/
		$upload_dir = wp_upload_dir();
		$backup_dir = trailingslashit( $upload_dir['basedir'] ) . 'cuft-backups/';

		// Prevent directory traversal
		if ( false !== strpos( $path, '..' ) ) {
			return new WP_Error(
				'invalid_backup_path',
				__( 'Invalid backup path: directory traversal not allowed.', 'choice-universal-form-tracker' )
			);
		}

		// Validate path starts with backup directory
		if ( 0 !== strpos( $path, $backup_dir ) ) {
			return new WP_Error(
				'invalid_backup_path',
				__( 'Backup path must be within cuft-backups directory.', 'choice-universal-form-tracker' )
			);
		}

		$this->data['backup_location'] = $path;

		return true;
	}

	/**
	 * Set error details
	 *
	 * @param string $error_code    Error code.
	 * @param string $error_message Error message.
	 * @param array  $context       Additional context (optional).
	 * @return void
	 */
	public function set_error_details( $error_code, $error_message, $context = array() ) {
		$this->data['error_details'] = array(
			'error_code'    => $error_code,
			'error_message' => $error_message,
			'context'       => $context,
		);
	}

	/**
	 * Mark operation as completed
	 *
	 * Sets completed_at timestamp to current time.
	 *
	 * @return void
	 */
	public function mark_completed() {
		$this->data['completed_at'] = time();
	}

	/**
	 * Convert to array for serialization
	 *
	 * @return array Operation data.
	 */
	public function to_array() {
		return $this->data;
	}
}
