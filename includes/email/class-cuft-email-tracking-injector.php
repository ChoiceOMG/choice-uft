<?php
/**
 * Email Tracking Parameter Injector
 *
 * Injects UTM parameters and click IDs into form submission email messages.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Email_Tracking_Injector {

	/**
	 * Initialize tracking injector
	 *
	 * Registers wp_mail filter at priority 15 (after Auto-BCC at priority 10).
	 */
	public function init() {
		add_filter( 'wp_mail', array( $this, 'inject_tracking_parameters' ), 15, 1 );
	}

	/**
	 * Inject tracking parameters into email message
	 *
	 * @param array $args WordPress email arguments (to, subject, message, headers, attachments)
	 * @return array Modified email arguments
	 */
	public function inject_tracking_parameters( $args ) {
		try {
			// Only inject into form submission emails
			if ( ! $this->is_form_submission_email( $args ) ) {
				return $args;
			}

			// Get tracking data from session/cookie
			$tracking_data = $this->get_tracking_data();

			if ( empty( $tracking_data ) ) {
				return $args;
			}

			// Append tracking parameters to message
			$args['message'] = $this->append_tracking_to_message( $args['message'], $tracking_data );

			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf(
					'CUFT Tracking Injector: Added tracking parameters to email (subject: %s)',
					isset( $args['subject'] ) ? $args['subject'] : 'unknown'
				) );
			}

		} catch ( Exception $e ) {
			// Graceful degradation: Log error but don't block email
			error_log( 'CUFT Tracking Injector Error: ' . $e->getMessage() );
		}

		return $args;
	}

	/**
	 * Check if email is a form submission
	 *
	 * @param array $args Email arguments
	 * @return bool True if form submission, false otherwise
	 */
	private function is_form_submission_email( $args ) {
		if ( ! isset( $args['subject'] ) ) {
			return false;
		}

		$subject = strtolower( $args['subject'] );

		// Common form submission subject patterns
		$patterns = array(
			'contact form',
			'form submission',
			'new submission',
			'cf7',
			'gravity',
			'ninja forms',
			'elementor',
			'avada',
			'test form',
		);

		foreach ( $patterns as $pattern ) {
			if ( strpos( $subject, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get tracking data from session or cookie
	 *
	 * @return array Tracking parameters
	 */
	private function get_tracking_data() {
		if ( class_exists( 'CUFT_UTM_Tracker' ) ) {
			return CUFT_UTM_Tracker::get_utm_data();
		}

		return array();
	}

	/**
	 * Append tracking parameters to email message
	 *
	 * @param string $message Original email message
	 * @param array  $tracking_data Tracking parameters
	 * @return string Modified email message
	 */
	private function append_tracking_to_message( $message, $tracking_data ) {
		// Separate UTM parameters from click IDs
		$utm_params = array();
		$click_ids = array();

		$utm_keys = array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id' );
		$click_id_keys = array( 'click_id', 'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'ttclid', 'li_fat_id', 'twclid', 'snap_click_id', 'pclid' );

		foreach ( $tracking_data as $key => $value ) {
			if ( in_array( $key, $utm_keys, true ) ) {
				$utm_params[ $key ] = $value;
			} elseif ( in_array( $key, $click_id_keys, true ) ) {
				$click_ids[ $key ] = $value;
			}
		}

		// Build tracking information section
		$tracking_section = "\n\n" . str_repeat( '-', 60 ) . "\n";
		$tracking_section .= "TRACKING INFORMATION\n";
		$tracking_section .= str_repeat( '-', 60 ) . "\n\n";

		// Add UTM parameters if present
		if ( ! empty( $utm_params ) ) {
			$tracking_section .= "UTM Parameters:\n";
			foreach ( $utm_params as $key => $value ) {
				$label = $this->format_param_label( $key );
				$tracking_section .= sprintf( "  %s: %s\n", $label, $value );
			}
			$tracking_section .= "\n";
		}

		// Add click IDs if present
		if ( ! empty( $click_ids ) ) {
			$tracking_section .= "Click IDs:\n";
			foreach ( $click_ids as $key => $value ) {
				$label = $this->format_param_label( $key );
				$tracking_section .= sprintf( "  %s: %s\n", $label, $value );
			}
			$tracking_section .= "\n";
		}

		$tracking_section .= str_repeat( '-', 60 );

		// Append to message
		return $message . $tracking_section;
	}

	/**
	 * Format parameter label for display
	 *
	 * @param string $key Parameter key
	 * @return string Formatted label
	 */
	private function format_param_label( $key ) {
		$labels = array(
			'utm_source'    => 'Source',
			'utm_medium'    => 'Medium',
			'utm_campaign'  => 'Campaign',
			'utm_term'      => 'Term',
			'utm_content'   => 'Content',
			'utm_id'        => 'Campaign ID',
			'click_id'      => 'Click ID',
			'gclid'         => 'Google Click ID',
			'gbraid'        => 'Google iOS Click ID',
			'wbraid'        => 'Google Web-to-App Click ID',
			'fbclid'        => 'Facebook Click ID',
			'msclkid'       => 'Microsoft/Bing Click ID',
			'ttclid'        => 'TikTok Click ID',
			'li_fat_id'     => 'LinkedIn Click ID',
			'twclid'        => 'Twitter/X Click ID',
			'snap_click_id' => 'Snapchat Click ID',
			'pclid'         => 'Pinterest Click ID',
		);

		return isset( $labels[ $key ] ) ? $labels[ $key ] : ucwords( str_replace( '_', ' ', $key ) );
	}
}
