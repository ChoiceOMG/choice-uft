<?php
/**
 * Tests for Elementor attribution capture timing (OPS-3546).
 *
 * Elementor Pro runs every submit action inside the visitor's request, in this
 * order: record/actions_before, then the actions themselves (Submissions,
 * Email, Webhook), then new_record. These tests drive that sequence, because
 * the bug they cover was one of timing rather than of logic: attribution was
 * assembled at a point where the cookies it needs, and the consumers that need
 * it, had both already moved on.
 */

/**
 * Minimal stand-in for ElementorPro\Modules\Forms\Classes\Form_Record.
 */
class CUFT_Test_Form_Record {

    private $data;
    private $form_settings;

    public function __construct( array $fields = array(), array $form_settings = array() ) {
        $this->data = array( 'fields' => $fields );
        $this->form_settings = array_merge( array(
            'id' => '7ffc40a',
            'form_name' => 'Intake Form',
            'submit_actions' => array( 'save-to-database', 'email', 'webhook' ),
        ), $form_settings );
    }

    public function get( $property ) {
        return isset( $this->data[ $property ] ) ? $this->data[ $property ] : null;
    }

    public function set( $property, $value ) {
        $this->data[ $property ] = $value;
    }

    public function get_form_settings( $key ) {
        return isset( $this->form_settings[ $key ] ) ? $this->form_settings[ $key ] : null;
    }

    /** Mirrors Elementor's [all-fields] and simple-mode webhook body. */
    public function get_formatted_data() {
        $out = array();
        foreach ( (array) $this->get( 'fields' ) as $key => $field ) {
            $title = empty( $field['title'] ) ? 'No Label ' . $key : $field['title'];
            $out[ $title ] = $field['value'];
        }
        return $out;
    }
}

/**
 * Stand-in for an Elementor form action.
 */
class CUFT_Test_Form_Action {

    private $name;

    public function __construct( $name ) {
        $this->name = $name;
    }

    public function get_name() {
        return $this->name;
    }
}

/**
 * Stands in for Elementor's action registrar, which is not loaded under the
 * test harness. Elementor Pro registers save-to-database first by design.
 */
class CUFT_Test_Elementor_Forms extends CUFT_Elementor_Forms {

    public $action_order = array( 'save-to-database', 'email', 'email2', 'webhook' );

    protected function get_registered_action_order() {
        return $this->action_order;
    }
}

class Test_Elementor_Attribution_Capture extends WP_UnitTestCase {

    /** @var CUFT_Test_Elementor_Forms */
    private $forms;

    public function set_up() {
        parent::set_up();
        $this->forms = new CUFT_Test_Elementor_Forms();
    }

    public function tear_down() {
        unset( $_COOKIE['cuft_utm_data'], $_COOKIE['cuft_first_touch'], $_COOKIE['cuft_click_id'] );
        unset( $_POST['referrer'] );
        parent::tear_down();
    }

    private function set_utm_cookie( array $utm ) {
        $_COOKIE['cuft_utm_data'] = wp_json_encode( array(
            'utm'       => $utm,
            'timestamp' => current_time( 'timestamp' ),
        ) );
    }

    private function make_record( array $form_settings = array() ) {
        return new CUFT_Test_Form_Record( array(
            'email' => array(
                'id' => 'email',
                'type' => 'email',
                'title' => 'Email Address',
                'value' => 'visitor@example.com',
                'raw_value' => 'visitor@example.com',
            ),
        ), $form_settings );
    }

    /**
     * The captured payload is what the webhook sends, even though the cookies
     * are long gone by the time the webhook action runs.
     */
    public function test_webhook_uses_captured_attribution() {
        $this->set_utm_cookie( array(
            'utm_source'   => 'google',
            'utm_campaign' => 'spring',
            'fbclid'       => 'fb123',
        ) );

        $record = $this->make_record();
        $this->forms->capture_attribution( $record );

        // Everything cookie-based disappears once the capture has happened.
        unset( $_COOKIE['cuft_utm_data'] );

        $args = $this->forms->enrich_webhook_args( array( 'body' => array() ), $record );
        $attribution = $args['body']['cuft_attribution'];

        $this->assertSame( 'google', $attribution['utm_source'] );
        $this->assertSame( 'spring', $attribution['utm_campaign'] );
        $this->assertSame( 'fb123', $attribution['fbclid'] );
        $this->assertSame( 'google', $args['body']['utm_source'], 'Flattened top-level keys are part of the contract' );
    }

    /**
     * submitted_at is the submit time, not the time a later action fired. The
     * gap was 11 seconds of mail sending on the site that surfaced this.
     */
    public function test_submitted_at_is_the_capture_time() {
        $this->set_utm_cookie( array( 'utm_source' => 'google' ) );

        $record = $this->make_record();
        $this->forms->capture_attribution( $record );
        $captured_at = $this->forms->enrich_webhook_args( array( 'body' => array() ), $record );
        $first = $captured_at['body']['cuft_attribution']['submitted_at'];

        sleep( 1 );

        $later = $this->forms->enrich_webhook_args( array( 'body' => array() ), $record );
        $this->assertSame(
            $first,
            $later['body']['cuft_attribution']['submitted_at'],
            'submitted_at must not drift with the action that reads it'
        );
    }

    /**
     * Without a capture (a site where the hook never ran), the webhook filter
     * still assembles attribution from the live request.
     */
    public function test_falls_back_to_live_request_without_capture() {
        $this->set_utm_cookie( array( 'utm_source' => 'bing' ) );

        $args = $this->forms->enrich_webhook_args( array( 'body' => array() ), $this->make_record() );

        $this->assertSame( 'bing', $args['body']['cuft_attribution']['utm_source'] );
    }

    /**
     * The stored entry carries attribution: the fields are on the record while
     * the Submissions action runs.
     */
    public function test_entry_fields_present_while_submissions_action_runs() {
        $this->set_utm_cookie( array( 'utm_source' => 'google' ) );

        $record = $this->make_record();
        $this->forms->capture_attribution( $record );

        $fields = $record->get( 'fields' );
        $this->assertArrayHasKey( 'cuft_utm_source', $fields );
        $this->assertSame( 'google', $fields['cuft_utm_source']['value'] );
        $this->assertArrayHasKey( 'email', $fields, 'Real form fields survive untouched' );
    }

    /**
     * ...and are gone again before the Email action can print them through
     * [all-fields]. This is the regression that would email a client's
     * notification with twenty lines of UTM values.
     */
    public function test_entry_fields_removed_before_email_action() {
        $this->set_utm_cookie( array( 'utm_source' => 'google', 'fbclid' => 'fb123' ) );

        $record = $this->make_record();
        $this->forms->capture_attribution( $record );
        $this->forms->remove_attribution_fields( new CUFT_Test_Form_Action( 'save-to-database' ), null );

        $fields = $record->get( 'fields' );
        $this->assertArrayNotHasKey( 'cuft_utm_source', $fields );
        $this->assertArrayNotHasKey( 'cuft_fbclid', $fields );
        $this->assertArrayHasKey( 'email', $fields );

        foreach ( array_keys( $record->get_formatted_data() ) as $title ) {
            $this->assertStringStartsNotWith( 'utm_', $title );
            $this->assertNotSame( 'fbclid', $title );
        }
    }

    /**
     * Nothing is injected when the form does not store submissions, since
     * there is no entry to carry it and an email could print it.
     */
    public function test_no_injection_without_submissions_action() {
        $this->set_utm_cookie( array( 'utm_source' => 'google' ) );

        $record = $this->make_record( array( 'submit_actions' => array( 'email', 'webhook' ) ) );
        $this->forms->capture_attribution( $record );

        $this->assertArrayNotHasKey( 'cuft_utm_source', $record->get( 'fields' ) );

        // The webhook is still enriched; only the entry injection is skipped.
        $args = $this->forms->enrich_webhook_args( array( 'body' => array() ), $record );
        $this->assertSame( 'google', $args['body']['cuft_attribution']['utm_source'] );
    }

    /**
     * If anything reorders the actions so an email runs before the entry is
     * stored, nothing is injected at all. Losing the entry copy is the
     * acceptable outcome; emailing the attribution is not.
     */
    public function test_no_injection_when_email_runs_before_submissions() {
        $this->set_utm_cookie( array( 'utm_source' => 'google' ) );

        $this->forms->action_order = array( 'email', 'save-to-database', 'webhook' );
        $record = $this->make_record();
        $this->forms->capture_attribution( $record );

        $this->assertArrayNotHasKey( 'cuft_utm_source', $record->get( 'fields' ) );
    }

    /**
     * An unrelated action finishing does not strip the fields early: only the
     * Submissions action does, and the entry must still carry them.
     */
    public function test_other_actions_do_not_strip_fields_early() {
        $this->set_utm_cookie( array( 'utm_source' => 'google' ) );

        $record = $this->make_record();
        $this->forms->capture_attribution( $record );
        $this->forms->remove_attribution_fields( new CUFT_Test_Form_Action( 'redirect' ), null );

        $this->assertArrayHasKey( 'cuft_utm_source', $record->get( 'fields' ) );
    }
}
