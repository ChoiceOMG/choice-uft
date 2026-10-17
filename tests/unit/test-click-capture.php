<?php
/**
 * The landing request that carries an ad click ID writes the click row.
 *
 * Covers three 3.30.0 fixes: the row takes its UTMs from the landing URL
 * (they used to come only from a cookie the browser sets after the page
 * loads, so every first row was blank); a later visit to the same URL never
 * rewrites the row (it used to reset the CRM's qualified and score to 0 and
 * replace the UTMs); and the landing page is recorded without
 * add_query_arg( null, null ), deprecated on PHP 8.5.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 */

class Test_Click_Capture extends WP_UnitTestCase {

    private $table;

    public function setUp(): void {
        parent::setUp();
        global $wpdb;
        $this->table = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( "DELETE FROM {$this->table}" );
        $_COOKIE = array();
    }

    public function tearDown(): void {
        $_GET                   = array();
        $_COOKIE                = array();
        $_SERVER['REQUEST_URI'] = '';
        parent::tearDown();
    }

    /**
     * Simulate a front-end request for /landing/ with the given query.
     */
    private function land( array $query ) {
        $_GET                   = $query;
        $_SERVER['REQUEST_URI'] = '/landing/?' . http_build_query( $query );
        ( new CUFT_Click_Integration() )->capture_click_ids();
    }

    private function row( $click_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table} WHERE click_id = %s", $click_id ) );
    }

    public function test_first_row_takes_utms_from_the_landing_url() {
        $this->land( array(
            'utm_source'   => 'google',
            'utm_medium'   => 'cpc',
            'utm_campaign' => 'summer_sale',
            'utm_term'     => 'hvac repair',
            'utm_content'  => 'ad_b',
            'gclid'        => 'GCLID-UTM-1',
        ) );

        $row = $this->row( 'GCLID-UTM-1' );
        $this->assertSame( 'google', $row->utm_source );
        $this->assertSame( 'cpc', $row->utm_medium );
        $this->assertSame( 'summer_sale', $row->utm_campaign );
        $this->assertSame( 'summer_sale', $row->campaign );
        $this->assertSame( 'hvac repair', $row->utm_term );
        $this->assertSame( 'ad_b', $row->utm_content );
    }

    public function test_landing_url_utms_win_over_an_older_cookie() {
        $_COOKIE['cuft_utm_data'] = wp_json_encode( array(
            'utm'       => array( 'utm_source' => 'facebook', 'utm_campaign' => 'old_campaign' ),
            'timestamp' => current_time( 'timestamp' ),
        ) );

        $this->land( array( 'utm_source' => 'google', 'utm_campaign' => 'new_campaign', 'gclid' => 'GCLID-UTM-2' ) );

        $row = $this->row( 'GCLID-UTM-2' );
        $this->assertSame( 'google', $row->utm_source );
        $this->assertSame( 'new_campaign', $row->utm_campaign );
    }

    /**
     * Google Ads auto-tagging adds a gclid and no UTMs. The browser script
     * replaces the UTM cookie with that click alone on the same page view, so
     * a newsletter visit last week must not label this ad click.
     */
    public function test_click_without_utms_does_not_inherit_an_older_campaign() {
        $_COOKIE['cuft_utm_data'] = wp_json_encode( array(
            'utm'       => array( 'utm_source' => 'newsletter', 'utm_campaign' => 'october' ),
            'timestamp' => current_time( 'timestamp' ),
        ) );

        $this->land( array( 'gclid' => 'GCLID-UTM-3' ) );

        $row = $this->row( 'GCLID-UTM-3' );
        $this->assertSame( 'google', $row->platform );
        $this->assertSame( '', (string) $row->utm_source );
        $this->assertSame( '', (string) $row->utm_campaign );
    }

    public function test_revisiting_the_landing_url_keeps_the_row() {
        $this->land( array( 'utm_source' => 'google', 'utm_campaign' => 'spring', 'gclid' => 'GCLID-REVISIT' ) );
        CUFT_Click_Tracker::update_click_status( 'GCLID-REVISIT', 1, 8 );

        // A reload, a back button, or anyone who knows the click ID.
        $_COOKIE['cuft_utm_data'] = wp_json_encode( array(
            'utm'       => array( 'utm_source' => 'attacker' ),
            'timestamp' => current_time( 'timestamp' ),
        ) );
        $this->land( array( 'gclid' => 'GCLID-REVISIT' ) );
        $this->land( array( 'utm_source' => 'attacker', 'gclid' => 'GCLID-REVISIT' ) );

        $row = $this->row( 'GCLID-REVISIT' );
        $this->assertSame( '1', (string) $row->qualified );
        $this->assertSame( '8', (string) $row->score );
        $this->assertSame( 'google', $row->utm_source );
        $this->assertSame( 'spring', $row->utm_campaign );
    }

    /**
     * $wpdb->insert() rejects the whole row when one value is longer than
     * its column, so an oversized parameter must not cost the click.
     */
    public function test_oversized_utm_value_still_records_the_click() {
        $long = str_repeat( 'x', 300 );
        $this->land( array( 'utm_source' => 'google', 'utm_content' => $long, 'gclid' => 'GCLID-LONG' ) );

        $row = $this->row( 'GCLID-LONG' );
        $this->assertNotNull( $row );
        $this->assertSame( str_repeat( 'x', 255 ), $row->utm_content );
        $this->assertSame( 'google', $row->utm_source );
    }

    /**
     * A full-page cache stores whatever HTML the first request produced. A
     * page rendered for an ad visitor that carries their click ID would hand
     * it to every later visitor, whose leads would then claim that ad click.
     * The click ID cookie is readable by scripts, so the page needs none of it.
     */
    public function test_page_html_carries_no_visitor_click_data() {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $this->land( array( 'gclid' => 'GCLID-PAGE' ) );
        $ip_hash = $this->row( 'GCLID-PAGE' )->ip_hash;
        $this->assertNotEmpty( $ip_hash );
        $_COOKIE['cuft_click_id'] = 'GCLID-PAGE';

        ( new CUFT_Click_Integration() )->enqueue_click_tracking_script();
        $before = implode( "\n", (array) wp_scripts()->get_data( 'cuft-click-integration', 'before' ) );
        wp_dequeue_script( 'cuft-click-integration' );
        wp_deregister_script( 'cuft-click-integration' );
        unset( $_SERVER['REMOTE_ADDR'] );

        $this->assertStringContainsString( 'cuftClickData', $before );
        $this->assertStringNotContainsString( 'GCLID-PAGE', $before );
        $this->assertStringNotContainsString( $ip_hash, $before );
    }

    /**
     * Pins the recorded value across the switch away from
     * add_query_arg( null, null ). That call's deprecation only shows on
     * PHP 8.5, so this test cannot catch a revert on older PHP; the 8.5 run
     * is part of the release verification instead.
     */
    public function test_landing_page_is_recorded() {
        $this->land( array( 'gclid' => 'GCLID-LP' ) );

        $extra = json_decode( $this->row( 'GCLID-LP' )->additional_data, true );
        $this->assertSame( home_url( '/landing/?gclid=GCLID-LP' ), $extra['landing_page'] );
    }
}
