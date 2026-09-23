<?php
/**
 * The cuft_webhook key check (3.28.0).
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.28.0
 * @group ajax
 */

class Test_Webhook_Key extends WP_Ajax_UnitTestCase {

    private $click_id = 'test_webhook_key_click';

    public function setUp(): void {
        parent::setUp();
        CUFT_Click_Tracker::create_table();
        CUFT_Click_Tracker::track_click( $this->click_id, array( 'platform' => 'google' ) );
        update_option( 'cuft_webhook_key', 'correct-key-0123456789abcdefghij' );
        $_GET = array( 'click_id' => $this->click_id, 'qualified' => '1', 'score' => '7' );
    }

    public function tearDown(): void {
        $_GET = array();
        parent::tearDown();
    }

    private function call_webhook() {
        try {
            $this->_handleAjax( 'cuft_webhook' );
        } catch ( WPAjaxDieContinueException $e ) {
            unset( $e );
        }
        return json_decode( $this->_last_response, true );
    }

    public function test_missing_key_is_rejected_when_required() {
        update_option( 'cuft_webhook_require_key', 1 );
        $response = $this->call_webhook();
        $this->assertFalse( $response['success'] );
        $this->assertSame( 'Invalid or missing webhook key', $response['data']['message'] );
    }

    public function test_wrong_key_is_rejected_when_required() {
        update_option( 'cuft_webhook_require_key', 1 );
        $_GET['key'] = 'wrong-key';
        $response = $this->call_webhook();
        $this->assertFalse( $response['success'] );
    }

    public function test_correct_key_is_accepted_when_required() {
        update_option( 'cuft_webhook_require_key', 1 );
        $_GET['key'] = 'correct-key-0123456789abcdefghij';
        $response = $this->call_webhook();
        $this->assertTrue( $response['success'] );
    }

    public function test_no_key_needed_when_not_required() {
        update_option( 'cuft_webhook_require_key', 0 );
        $response = $this->call_webhook();
        $this->assertTrue( $response['success'] );
    }
}
