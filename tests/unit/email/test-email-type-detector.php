<?php
/**
 * Unit Tests for CUFT_Email_Type_Detector
 *
 * Tests email type detection logic with various subject line and header patterns.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Tests/Unit/Email
 * @since      3.11.0
 */

class Test_CUFT_Email_Type_Detector extends WP_UnitTestCase {

	/**
	 * Email type detector instance
	 *
	 * @var CUFT_Email_Type_Detector
	 */
	private $detector;

	/**
	 * Set up before each test
	 */
	public function setUp(): void {
		parent::setUp();
		$this->detector = new CUFT_Email_Type_Detector();
	}

	/**
	 * Test form submission detection - keyword "form"
	 */
	public function test_detect_form_submission_with_keyword_form() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'New Form Submission from Contact Page',
			'message' => 'Form data here',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'form_submission', $type );
	}

	/**
	 * Test form submission detection - keyword "submission"
	 */
	public function test_detect_form_submission_with_keyword_submission() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'Contact Submission Received',
			'message' => 'Form data here',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'form_submission', $type );
	}

	/**
	 * Test form submission detection - keyword "contact"
	 */
	public function test_detect_form_submission_with_keyword_contact() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'Contact Form - New Message',
			'message' => 'Form data here',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'form_submission', $type );
	}

	/**
	 * Test form submission detection - keyword "enquiry"
	 */
	public function test_detect_form_submission_with_keyword_enquiry() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'New Enquiry from Website',
			'message' => 'Form data here',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'form_submission', $type );
	}

	/**
	 * Test user registration detection - keyword "new user"
	 */
	public function test_detect_user_registration_with_keyword_new_user() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => '[WordPress] New User Registration',
			'message' => 'A new user has registered on your site.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'user_registration', $type );
	}

	/**
	 * Test user registration detection - keyword "registration"
	 */
	public function test_detect_user_registration_with_keyword_registration() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'User Registration Notification',
			'message' => 'Registration details...',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'user_registration', $type );
	}

	/**
	 * Test password reset detection - keyword "password reset"
	 */
	public function test_detect_password_reset_with_keyword_password_reset() {
		$email_args = array(
			'to' => 'user@example.com',
			'subject' => 'Password Reset Request',
			'message' => 'Click here to reset your password.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'password_reset', $type );
	}

	/**
	 * Test password reset detection - keyword "reset your password"
	 */
	public function test_detect_password_reset_with_keyword_reset_your_password() {
		$email_args = array(
			'to' => 'user@example.com',
			'subject' => '[WordPress] Reset Your Password',
			'message' => 'Password reset link...',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'password_reset', $type );
	}

	/**
	 * Test comment notification detection - keyword "new comment"
	 */
	public function test_detect_comment_notification_with_keyword_new_comment() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'New Comment on Your Post',
			'message' => 'Someone commented on your post.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'comment_notification', $type );
	}

	/**
	 * Test comment notification detection - keyword "comment on"
	 */
	public function test_detect_comment_notification_with_keyword_comment_on() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'Comment on "Sample Post"',
			'message' => 'Comment details...',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'comment_notification', $type );
	}

	/**
	 * Test admin notification detection - TO matches admin email
	 */
	public function test_detect_admin_notification_by_recipient() {
		$admin_email = get_option( 'admin_email' );

		$email_args = array(
			'to' => $admin_email,
			'subject' => 'Site Notification',
			'message' => 'Admin notification message.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'admin_notification', $type );
	}

	/**
	 * Test admin notification detection - subject contains "[Admin]"
	 */
	public function test_detect_admin_notification_by_subject() {
		$email_args = array(
			'to' => 'other@example.com',
			'subject' => '[Admin] System Alert',
			'message' => 'Admin notification message.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'admin_notification', $type );
	}

	/**
	 * Test unrecognized email returns "other"
	 */
	public function test_detect_unrecognized_email_returns_other() {
		$email_args = array(
			'to' => 'user@example.com',
			'subject' => 'Random Email Subject',
			'message' => 'Some random content.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'other', $type );
	}

	/**
	 * Test case insensitive detection
	 */
	public function test_detect_type_case_insensitive() {
		$email_args = array(
			'to' => 'admin@example.com',
			'subject' => 'NEW FORM SUBMISSION',
			'message' => 'Form data here',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'form_submission', $type );
	}

	/**
	 * Test detection with missing subject
	 */
	public function test_detect_type_with_missing_subject() {
		$email_args = array(
			'to' => 'user@example.com',
			'message' => 'Some content',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'other', $type );
	}

	/**
	 * Test detection with empty email args
	 */
	public function test_detect_type_with_empty_args() {
		$email_args = array();

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'other', $type );
	}

	/**
	 * Test priority order - form submission detected before admin notification
	 */
	public function test_detection_priority_form_over_admin() {
		$admin_email = get_option( 'admin_email' );

		$email_args = array(
			'to' => $admin_email,
			'subject' => 'Contact Form Submission',
			'message' => 'Form data here',
		);

		// Should detect as form_submission even though TO is admin email
		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'form_submission', $type );
	}

	/**
	 * Test TO field with array of recipients
	 */
	public function test_detect_admin_with_to_array() {
		$admin_email = get_option( 'admin_email' );

		$email_args = array(
			'to' => array( $admin_email, 'other@example.com' ),
			'subject' => 'Site Notification',
			'message' => 'Admin notification message.',
		);

		$type = $this->detector->detect_type( $email_args );
		$this->assertEquals( 'admin_notification', $type );
	}
}
