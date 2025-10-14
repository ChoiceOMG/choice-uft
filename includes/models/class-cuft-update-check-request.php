<?php
/**
 * Update Check Request Model
 *
 * Ephemeral data model for Update Check Request (memory-only).
 * Tracks lifecycle of a manual update check from request to response.
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
 * CUFT_Update_Check_Request class.
 *
 * Ephemeral model for manual update check requests. Not persisted to database.
 * Used for request lifecycle tracking only.
 *
 * @since 3.19.0
 */
class CUFT_Update_Check_Request {

	/**
	 * Status constants
	 */
	const STATUS_PENDING = 'pending';
	const STATUS_SUCCESS = 'success';
	const STATUS_ERROR   = 'error';
	const STATUS_TIMEOUT = 'timeout';

	/**
	 * Request data
	 *
	 * @var array
	 */
	private $data = array();

	/**
	 * Constructor
	 *
	 * Initializes request with timestamp and user ID.
	 *
	 * @param int|null $user_id Optional user ID (defaults to current user).
	 */
	public function __construct( $user_id = null ) {
		$this->data = array(
			'timestamp'       => time(),
			'user_id'         => $user_id ? $user_id : get_current_user_id(),
			'github_response' => null,
			'status'          => self::STATUS_PENDING,
			'error_message'   => null,
		);
	}

	/**
	 * Set request status
	 *
	 * @param string $status New status.
	 * @return bool|WP_Error True on success, WP_Error on invalid status.
	 */
	public function set_status( $status ) {
		$valid_statuses = array(
			self::STATUS_PENDING,
			self::STATUS_SUCCESS,
			self::STATUS_ERROR,
			self::STATUS_TIMEOUT,
		);

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			return new WP_Error(
				'invalid_status',
				sprintf( 'Invalid status: %s', $status )
			);
		}

		$this->data['status'] = $status;

		return true;
	}

	/**
	 * Set GitHub API response
	 *
	 * @param string $version           Latest version from GitHub.
	 * @param string $release_date      Release date.
	 * @param string $changelog_summary Changelog summary.
	 * @param string $download_url      Download URL.
	 * @return void
	 */
	public function set_github_response( $version, $release_date, $changelog_summary, $download_url ) {
		$this->data['github_response'] = array(
			'version'           => $version,
			'release_date'      => $release_date,
			'changelog_summary' => $changelog_summary,
			'download_url'      => $download_url,
		);

		$this->set_status( self::STATUS_SUCCESS );
	}

	/**
	 * Set error message
	 *
	 * @param string $error_message Error message.
	 * @return void
	 */
	public function set_error( $error_message ) {
		$this->data['error_message'] = $error_message;
		$this->set_status( self::STATUS_ERROR );
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
	 * Get GitHub response
	 *
	 * @return array|null GitHub response data or null.
	 */
	public function get_github_response() {
		return $this->data['github_response'];
	}

	/**
	 * Get error message
	 *
	 * @return string|null Error message or null.
	 */
	public function get_error_message() {
		return $this->data['error_message'];
	}

	/**
	 * Get timestamp
	 *
	 * @return int Unix timestamp.
	 */
	public function get_timestamp() {
		return $this->data['timestamp'];
	}

	/**
	 * Get user ID
	 *
	 * @return int User ID.
	 */
	public function get_user_id() {
		return $this->data['user_id'];
	}

	/**
	 * Convert to array for serialization
	 *
	 * @return array Request data.
	 */
	public function to_array() {
		return $this->data;
	}
}
