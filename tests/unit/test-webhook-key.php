<?php
/**
 * The cuft_webhook key check (3.28.0; mandatory since 3.29.2).
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

    public function test_missing_key_is_rejected() {
        $response = $this->call_webhook();
        $this->assertFalse( $response['success'] );
        $this->assertSame( 'Invalid or missing webhook key', $response['data']['message'] );
    }

    public function test_wrong_key_is_rejected() {
        $_GET['key'] = 'wrong-key';
        $response = $this->call_webhook();
        $this->assertFalse( $response['success'] );
    }

    public function test_correct_key_is_accepted() {
        $_GET['key'] = 'correct-key-0123456789abcdefghij';
        $response = $this->call_webhook();
        $this->assertTrue( $response['success'] );
    }

    public function test_legacy_opt_out_option_cannot_reopen_the_webhook() {
        update_option( 'cuft_webhook_require_key', 0 );
        $response = $this->call_webhook();
        $this->assertFalse( $response['success'] );
        $this->assertSame( 'Invalid or missing webhook key', $response['data']['message'] );
    }

    public function test_rejected_request_changes_nothing() {
        global $wpdb;
        $_GET['key'] = 'wrong-key';
        $this->call_webhook();
        $qualified = $wpdb->get_var( $wpdb->prepare( 'SELECT qualified FROM %i WHERE click_id = %s', $wpdb->prefix . 'cuft_click_tracking', $this->click_id ) );
        $this->assertSame( '0', (string) $qualified );
    }
}
