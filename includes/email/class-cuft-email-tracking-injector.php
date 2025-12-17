<?php
/**
 * Email Tracking Parameter Injector
 *
 * Automatically injects UTM parameters and platform click IDs into form submission
 * email messages. Supports both HTML and plain text email formats with appropriate
 * formatting for each.
 *
 * ## Supported Parameters
 *
 * - **UTM Parameters**: utm_source, utm_medium, utm_campaign, utm_term, utm_content, utm_id
 * - **Click IDs**: gclid, gbraid, wbraid, fbclid, msclkid, ttclid, li_fat_id, twclid, snap_click_id, pclid
 *
 * ## Usage
 *
 * The injector hooks into `wp_mail` filter at priority 15 (after Auto-BCC at priority 10).
 * It automatically processes form submission emails when tracking data is available.
 *
 * ```php
 * // Initialize tracking injector
 * $tracking_injector = new CUFT_Email_Tracking_Injector();
 * $tracking_injector->init();
 * ```
 *
 * ## How It Works
 *
 * 1. Detects form submission emails by subject patterns and headers
 * 2. Retrieves tracking data from CUFT_UTM_Tracker (session/cookie storage)
 * 3. Detects email format (HTML or plain text)
 * 4. Formats tracking section appropriately for the email type
 * 5. Appends tracking information to email message
 *
 * ## HTML vs Plain Text
 *
 * - **Plain Text**: Adds formatted text section with separators
 * - **HTML**: Adds styled HTML div with lists and headings
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Email_Tracking_Injector {

	/**
	 * Separator line length for plain text tracking section
	 */
	const SEPARATOR_LENGTH = 60;

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

			// Detect if email is HTML or plain text
			$is_html = $this->is_html_email( $args );

			// Append tracking parameters to message
			$args['message'] = $this->append_tracking_to_message( $args['message'], $tracking_data, $is_html );

			self::log_debug( sprintf(
				'Added tracking parameters to email (subject: %s)',
				isset( $args['subject'] ) ? $args['subject'] : 'unknown'
			) );

		} catch ( Exception $e ) {
			// Graceful degradation: Log error but don't block email
			self::log_error( 'Tracking injection failed: ' . $e->getMessage() );
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
	 * Detect if email is HTML format
	 *
	 * @param array $args Email arguments
	 * @return bool True if HTML email, false if plain text
	 */
	private function is_html_email( $args ) {
		// Check headers for Content-Type: text/html
		if ( isset( $args['headers'] ) ) {
			$headers = is_array( $args['headers'] ) ? $args['headers'] : array( $args['headers'] );

			foreach ( $headers as $header ) {
				if ( stripos( $header, 'Content-Type:' ) === 0 && stripos( $header, 'text/html' ) !== false ) {
					return true;
				}
			}
		}

		// Check if message contains HTML tags
		if ( isset( $args['message'] ) ) {
			// Look for common HTML tags
			if ( preg_match( '/<(html|body|head|div|p|table|br)[\s>]/i', $args['message'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Append tracking parameters to email message
	 *
	 * @param string $message Original email message
	 * @param array  $tracking_data Tracking parameters
	 * @param bool   $is_html Whether email is HTML format
	 * @return string Modified email message
	 */
	private function append_tracking_to_message( $message, $tracking_data, $is_html = false ) {
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

		// Build tracking information section based on format
		if ( $is_html ) {
			$tracking_section = $this->build_html_tracking_section( $utm_params, $click_ids );
		} else {
			$tracking_section = $this->build_text_tracking_section( $utm_params, $click_ids );
		}

		// Append to message
		return $message . $tracking_section;
	}

	/**
	 * Build plain text tracking section
	 *
	 * @param array $utm_params UTM parameters
	 * @param array $click_ids Click ID parameters
	 * @return string Plain text tracking section
	 */
	private function build_text_tracking_section( $utm_params, $click_ids ) {
		$section = "\n\n" . str_repeat( '-', self::SEPARATOR_LENGTH ) . "\n";
		$section .= "TRACKING INFORMATION\n";
		$section .= str_repeat( '-', self::SEPARATOR_LENGTH ) . "\n\n";

		// Add UTM parameters if present
		if ( ! empty( $utm_params ) ) {
			$section .= "UTM Parameters:\n";
			foreach ( $utm_params as $key => $value ) {
				$label = $this->format_param_label( $key );
				$safe_value = sanitize_text_field( $value );
				$section .= sprintf( "  %s: %s\n", $label, $safe_value );
			}
			$section .= "\n";
		}

		// Add click IDs if present
		if ( ! empty( $click_ids ) ) {
			$section .= "Click IDs:\n";
			foreach ( $click_ids as $key => $value ) {
				$label = $this->format_param_label( $key );
				$safe_value = sanitize_text_field( $value );
				$section .= sprintf( "  %s: %s\n", $label, $safe_value );
			}
			$section .= "\n";
		}

		$section .= str_repeat( '-', self::SEPARATOR_LENGTH );

		return $section;
	}

	/**
	 * Build HTML tracking section
	 *
	 * @param array $utm_params UTM parameters
	 * @param array $click_ids Click ID parameters
	 * @return string HTML tracking section
	 */
	private function build_html_tracking_section( $utm_params, $click_ids ) {
		$section = '<div style="margin-top: 30px; padding: 20px; background-color: #f5f5f5; border: 1px solid #ddd;">';
		$section .= '<h3 style="margin: 0 0 15px 0; color: #333;">Tracking Information</h3>';

		// Add UTM parameters if present
		if ( ! empty( $utm_params ) ) {
			$section .= '<h4 style="margin: 10px 0 5px 0; color: #666;">UTM Parameters:</h4>';
			$section .= '<ul style="margin: 5px 0; padding-left: 20px;">';
			foreach ( $utm_params as $key => $value ) {
				$label = $this->format_param_label( $key );
				$safe_value = esc_html( $value );
				$section .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $label ), $safe_value );
			}
			$section .= '</ul>';
		}

		// Add click IDs if present
		if ( ! empty( $click_ids ) ) {
			$section .= '<h4 style="margin: 10px 0 5px 0; color: #666;">Click IDs:</h4>';
			$section .= '<ul style="margin: 5px 0; padding-left: 20px;">';
			foreach ( $click_ids as $key => $value ) {
				$label = $this->format_param_label( $key );
				$safe_value = esc_html( $value );
				$section .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $label ), $safe_value );
			}
			$section .= '</ul>';
		}

		$section .= '</div>';

		return $section;
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

	/**
	 * Log error message with consistent formatting
	 *
	 * @param string $message Error message
	 */
	private static function log_error( $message ) {
		error_log( 'CUFT Tracking Injector [ERROR]: ' . $message );
	}

	/**
	 * Log debug message (only if WP_DEBUG enabled)
	 *
	 * @param string $message Debug message
	 */
	private static function log_debug( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'CUFT Tracking Injector [DEBUG]: ' . $message );
		}
	}
}
