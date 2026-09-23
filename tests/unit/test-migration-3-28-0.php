<?php
/**
 * Unit tests for the 3.28.0 migration: new-install versus existing-install
 * defaults for the webhook key and generate_lead, and removal of test forms
 * left by the retired Test Form Builder.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.28.0
 */

class Test_Migration_3_28_0 extends WP_UnitTestCase {

    public function setUp(): void {
        parent::setUp();
        foreach ( array( 'cuft_db_version', 'cuft_gtm_id', 'cuft_webhook_require_key', 'cuft_webhook_key', 'cuft_generate_lead_enabled' ) as $option ) {
            delete_option( $option );
        }
        $this->set_activation_state( null );
    }

    private function set_activation_state( $value ) {
        $prop = new ReflectionProperty( 'CUFT_DB_Migration', 'activation_found_existing' );
        $prop->setAccessible( true );
        $prop->setValue( null, $value );
    }

    public function test_new_install_requires_webhook_key_and_leaves_generate_lead_off() {
        CUFT_DB_Migration::run_migrations();

        $this->assertSame( '1', (string) get_option( 'cuft_webhook_require_key' ) );
        $this->assertSame( 32, strlen( (string) get_option( 'cuft_webhook_key' ) ) );
        $this->assertSame( '0', (string) get_option( 'cuft_generate_lead_enabled' ) );
        $this->assertTrue( CUFT_Click_Tracker::webhook_key_required() );
    }

    public function test_upgrade_from_3_27_keeps_webhook_open_and_turns_generate_lead_on() {
        update_option( 'cuft_db_version', '3.22.0' );
        update_option( 'cuft_gtm_id', 'GTM-ABC123' );
        update_option( 'cuft_generate_lead_enabled', false );

        CUFT_DB_Migration::run_migrations();

        $this->assertSame( '0', (string) get_option( 'cuft_webhook_require_key' ) );
        $this->assertFalse( CUFT_Click_Tracker::webhook_key_required() );
        $this->assertSame( '1', (string) get_option( 'cuft_generate_lead_enabled' ) );
        $this->assertSame( '3.28.0', get_option( 'cuft_db_version' ) );
    }

    public function test_upgrade_keeps_an_explicit_webhook_key_setting() {
        update_option( 'cuft_db_version', '3.22.0' );
        update_option( 'cuft_webhook_require_key', 1 );

        CUFT_DB_Migration::run_migrations();

        $this->assertSame( '1', (string) get_option( 'cuft_webhook_require_key' ) );
        $this->assertNotEmpty( get_option( 'cuft_webhook_key' ) );
    }

    public function test_upgrade_from_version_without_db_version_counts_as_existing() {
        update_option( 'cuft_gtm_id', '' );

        CUFT_DB_Migration::run_migrations();

        $this->assertSame( '0', (string) get_option( 'cuft_webhook_require_key' ) );
        $this->assertSame( '1', (string) get_option( 'cuft_generate_lead_enabled' ) );
    }

    public function test_fresh_activation_is_new_even_after_defaults_are_written() {
        // activate() notes the state before it writes cuft_gtm_id.
        CUFT_DB_Migration::note_activation( false );
        add_option( 'cuft_gtm_id', '' );

        CUFT_DB_Migration::run_migrations();

        $this->assertSame( '1', (string) get_option( 'cuft_webhook_require_key' ) );
        $this->assertSame( '0', (string) get_option( 'cuft_generate_lead_enabled' ) );
    }

    public function test_migration_does_not_run_twice() {
        update_option( 'cuft_db_version', '3.22.0' );
        CUFT_DB_Migration::run_migrations();

        update_option( 'cuft_generate_lead_enabled', 0 );
        CUFT_DB_Migration::run_migrations();

        $this->assertSame( '0', (string) get_option( 'cuft_generate_lead_enabled' ) );
    }

    public function test_legacy_test_forms_are_removed_by_marker_only() {
        $marked = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Anything' ) );
        update_post_meta( $marked, '_cuft_test_form', 1 );
        update_post_meta( $marked, '_cuft_instance_id', 'cuft_test_1700000000_1234' );

        $wrong_instance = self::factory()->post->create( array( 'post_type' => 'page' ) );
        update_post_meta( $wrong_instance, '_cuft_test_form', 1 );
        update_post_meta( $wrong_instance, '_cuft_instance_id', 'something-else' );

        $titled_only = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'CUFT Test Form - cuft_test_1700000000_1234' ) );

        $ninja_collateral = self::factory()->post->create( array( 'post_type' => 'page' ) );
        update_post_meta( $ninja_collateral, '_cuft_test_form', 1 );
        update_post_meta( $ninja_collateral, '_cuft_instance_id', 'cuft_test_1700000000_4321' );
        update_post_meta( $ninja_collateral, '_cuft_framework', 'ninja' );

        update_option( 'cuft_test_cf7_form_id', 99 );

        $deleted = CUFT_Legacy_Test_Forms::remove_all();

        $this->assertSame( 1, $deleted );
        $this->assertNull( get_post( $marked ) );
        $this->assertNotNull( get_post( $wrong_instance ) );
        $this->assertNotNull( get_post( $titled_only ) );
        $this->assertNotNull( get_post( $ninja_collateral ) );
        $this->assertSame( '', get_post_meta( $ninja_collateral, '_cuft_test_form', true ) );
        $this->assertFalse( get_option( 'cuft_test_cf7_form_id' ) );
    }
}
