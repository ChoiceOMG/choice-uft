<?php
/**
 * Email Type Detector
 *
 * Classifies WordPress emails into types using pattern matching.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUFT_Email_Type_Detector {

	/**
	 * Detect email type based on subject, headers, and recipient
	 *
	 * @param array $email_args WordPress email arguments (to, subject, message, headers, attachments)
	 * @return string Email type identifier (form_submission, user_registration, etc., or 'other')
	 */
	public static function detect_type( $email_args ) {
		$subject = isset( $email_args['subject'] ) ? strtolower( $email_args['subject'] ) : '';
		$headers = isset( $email_args['headers'] ) ? $email_args['headers'] : '';
		$to = isset( $email_args['to'] ) ? strtolower( $email_args['to'] ) : '';

		// Convert headers to lowercase string for pattern matching
		if ( is_array( $headers ) ) {
			$headers = strtolower( implode( ' ', $headers ) );
		} else {
			$headers = strtolower( $headers );
		}

		// Check patterns in priority order
		if ( self::is_form_submission( $subject, $headers ) ) {
			return 'form_submission';
		}

		if ( self::is_user_registration( $subject, $headers ) ) {
			return 'user_registration';
		}

		if ( self::is_password_reset( $subject ) ) {
			return 'password_reset';
		}

		if ( self::is_comment_notification( $subject, $headers ) ) {
			return 'comment_notification';
		}

		if ( self::is_admin_notification( $subject, $to ) ) {
			return 'admin_notification';
		}

		// Default: unclassified
		return 'other';
	}

	/**
	 * Check if email is a form submission
	 *
	 * Uses specific patterns and plugin-specific headers to avoid false positives.
	 *
	 * @param string $subject Lowercase subject line
	 * @param string $headers Lowercase headers string
	 * @return bool True if form submission, false otherwise
	 */
	private static function is_form_submission( $subject, $headers ) {
		// Check for form plugin-specific headers first (most reliable)
		$header_patterns = array(
			'x-form-type',
			'x-contact-form',
			'x-elementor-form',
			'x-gravity-form',
			'x-ninja-form',
			'x-cf7-',
			'x-formidable',
			'x-wpforms',
		);

		foreach ( $header_patterns as $pattern ) {
			if ( strpos( $headers, $pattern ) !== false ) {
				return true;
			}
		}

		// More specific subject patterns (require "form" + another keyword)
		$specific_patterns = array(
			'contact form',
			'form submission',
			'new form',
			'form entry',
			'form inquiry',
			'form enquiry',
			'feedback form',
			'quote form',
			'registration form',
			'application form',
			'cf7',           // Contact Form 7
			'gravity',       // Gravity Forms
			'ninja forms',
			'elementor form',
			'avada form',
		);

		foreach ( $specific_patterns as $pattern ) {
			if ( strpos( $subject, $pattern ) !== false ) {
				return true;
			}
		}

		// Check for submission-related keywords in subject
		if ( strpos( $subject, 'submission' ) !== false &&
		     ( strpos( $subject, 'new' ) !== false || strpos( $subject, 'form' ) !== false ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if email is a user registration
	 *
	 * @param string $subject Lowercase subject line
	 * @param string $headers Lowercase headers string
	 * @return bool True if user registration, false otherwise
	 */
	private static function is_user_registration( $subject, $headers ) {
		// Subject patterns
		$subject_patterns = array(
			'new user',
			'registration',
			'account created',
			'welcome',
		);

		foreach ( $subject_patterns as $pattern ) {
			if ( strpos( $subject, $pattern ) !== false ) {
				return true;
			}
		}

		// Header patterns
		$header_patterns = array(
			'x-wp-user-registration',
			'x-account-type: new',
		);

		foreach ( $header_patterns as $pattern ) {
			if ( strpos( $headers, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if email is a password reset
	 *
	 * @param string $subject Lowercase subject line
	 * @return bool True if password reset, false otherwise
	 */
	private static function is_password_reset( $subject ) {
		$patterns = array(
			'password reset',
			'reset your password',
			'forgot password',
			'password recovery',
		);

		foreach ( $patterns as $pattern ) {
			if ( strpos( $subject, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if email is a comment notification
	 *
	 * @param string $subject Lowercase subject line
	 * @param string $headers Lowercase headers string
	 * @return bool True if comment notification, false otherwise
	 */
	private static function is_comment_notification( $subject, $headers ) {
		// Subject patterns
		$subject_patterns = array(
			'new comment',
			'comment on',
			'comment awaiting moderation',
		);

		foreach ( $subject_patterns as $pattern ) {
			if ( strpos( $subject, $pattern ) !== false ) {
				return true;
			}
		}

		// Header patterns
		if ( strpos( $headers, 'x-comment-notification' ) !== false ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if email is an admin notification
	 *
	 * @param string $subject Lowercase subject line
	 * @param string $to Lowercase TO address
	 * @return bool True if admin notification, false otherwise
	 */
	private static function is_admin_notification( $subject, $to ) {
		// Check if TO address matches WordPress admin email
		$admin_email = strtolower( get_option( 'admin_email', '' ) );
		if ( ! empty( $admin_email ) && $to === $admin_email ) {
			return true;
		}

		// Subject patterns
		$subject_patterns = array(
			'[admin]',
			'admin notification',
		);

		foreach ( $subject_patterns as $pattern ) {
			if ( strpos( $subject, $pattern ) !== false ) {
				return true;
			}
		}

		return false;
	}
}
