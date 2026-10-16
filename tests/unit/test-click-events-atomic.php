<?php
/**
 * Unit tests for lossless event recording on the click row (3.29.1).
 *
 * add_event() used to read the events JSON, change it in PHP and write it
 * back. The three lead events arrive as parallel requests, so they
 * overwrote each other. These tests cover the pure merge logic, the
 * compare-and-swap retry, the GET_LOCK fallback, legacy column values and
 * the cuftConfig handle that Contact Form 7 pages were missing.
 *
 * @package Choice_Universal_Form_Tracker
 * @since   3.29.1
 */

class Test_Click_Events_Atomic extends WP_UnitTestCase {

    private static $table_name;

    public static function set_up_before_class() {
        parent::set_up_before_class();
        global $wpdb;
        self::$table_name = $wpdb->prefix . 'cuft_click_tracking';
        CUFT_Click_Tracker::create_table();
    }

    public function tear_down() {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE click_id LIKE %s', self::$table_name, 'atomic-%' ) );
        parent::tear_down();
    }

    private function events_for( $click_id ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $raw = $wpdb->get_var( $wpdb->prepare( 'SELECT events FROM %i WHERE click_id = %s', self::$table_name, $click_id ) );
        return json_decode( (string) $raw, true );
    }

    private function event_names( $click_id ) {
        $events = $this->events_for( $click_id );
        $names  = is_array( $events ) ? array_column( $events, 'event' ) : array();
        sort( $names );
        return $names;
    }

    // ── decode_events ────────────────────────────────────────────────

    public function test_decode_events_null_and_empty() {
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( null ) );
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( '' ) );
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( '[]' ) );
    }

    public function test_decode_events_legacy_non_json_values() {
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( 'not json' ) );
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( '"a string"' ) );
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( '42' ) );
        $this->assertSame( array(), CUFT_Click_Tracker::decode_events( '{"a":1}' ) );
    }

    public function test_decode_events_drops_malformed_entries() {
        $raw     = '[{"event":"form_submit","timestamp":"2026-01-01T00:00:00+00:00"},"junk",{"no_event":1}]';
        $decoded = CUFT_Click_Tracker::decode_events( $raw );
        $this->assertCount( 1, $decoded );
        $this->assertSame( 'form_submit', $decoded[0]['event'] );
    }

    // ── merge_event ──────────────────────────────────────────────────

    public function test_merge_event_appends_new_type() {
        $events = CUFT_Click_Tracker::merge_event( array(), 'form_submit', '2026-01-01T00:00:00+00:00' );
        $events = CUFT_Click_Tracker::merge_event( $events, 'generate_lead', '2026-01-01T00:00:01+00:00' );
        $this->assertSame( array( 'form_submit', 'generate_lead' ), array_column( $events, 'event' ) );
        $this->assertArrayNotHasKey( 'source', $events[0] );
    }

    public function test_merge_event_dedupes_by_type_and_refreshes_timestamp() {
        $events = CUFT_Click_Tracker::merge_event( array(), 'form_submit', '2026-01-01T00:00:00+00:00' );
        $events = CUFT_Click_Tracker::merge_event( $events, 'generate_lead', '2026-01-01T00:00:01+00:00' );
        $events = CUFT_Click_Tracker::merge_event( $events, 'form_submit', '2026-01-02T00:00:00+00:00' );
        $this->assertCount( 2, $events );
        // Refreshed entry sorts last (newest last).
        $this->assertSame( 'form_submit', $events[1]['event'] );
        $this->assertSame( '2026-01-02T00:00:00+00:00', $events[1]['timestamp'] );
    }

    public function test_merge_event_source_adds_replay_fields() {
        $events = CUFT_Click_Tracker::merge_event( array(), 'qualify_lead', '2026-01-01T00:00:00+00:00', 'webhook' );
        $this->assertSame( 'webhook', $events[0]['source'] );
        $this->assertArrayHasKey( 'replayed_at', $events[0] );
        $this->assertNull( $events[0]['replayed_at'] );
    }

    public function test_merge_event_keeps_newest_100() {
        $events = array();
        for ( $i = 0; $i < 100; $i++ ) {
            $events[] = array(
                'event'     => 'legacy_' . $i,
                'timestamp' => sprintf( '2026-01-01T00:%02d:%02d+00:00', intdiv( $i, 60 ), $i % 60 ),
            );
        }
        $events = CUFT_Click_Tracker::merge_event( $events, 'form_submit', '2026-02-01T00:00:00+00:00' );
        $this->assertCount( 100, $events );
        $this->assertSame( 'legacy_1', $events[0]['event'] );
        $this->assertSame( 'form_submit', $events[99]['event'] );
    }

    // ── lock name ────────────────────────────────────────────────────

    public function test_lock_name_fits_mysql_limit_and_is_per_click() {
        $a = CUFT_Click_Tracker::events_lock_name( 'atomic-a' );
        $b = CUFT_Click_Tracker::events_lock_name( 'atomic-b' );
        $this->assertSame( 44, strlen( $a ) );
        $this->assertLessThanOrEqual( 64, strlen( CUFT_Click_Tracker::events_lock_name( str_repeat( 'x', 255 ) ) ) );
        $this->assertNotSame( $a, $b );
    }

    public function test_lock_is_acquired_and_released() {
        global $wpdb;
        $this->assertTrue( CUFT_Click_Tracker::acquire_events_lock( 'atomic-lock' ) );
        $name = CUFT_Click_Tracker::events_lock_name( 'atomic-lock' );
        $this->assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $name ) ) );
        CUFT_Click_Tracker::release_events_lock( 'atomic-lock' );
        $this->assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $name ) ) );
    }

    // ── add_event against the database ───────────────────────────────

    public function test_add_event_creates_row_and_records_three_lead_events() {
        foreach ( array( 'form_submit', 'generate_lead', 'qualify_lead' ) as $type ) {
            $this->assertTrue( CUFT_Click_Tracker::add_event( 'atomic-new', $type ) );
        }
        $this->assertSame( array( 'form_submit', 'generate_lead', 'qualify_lead' ), $this->event_names( 'atomic-new' ) );
    }

    public function test_add_event_on_null_events_column() {
        global $wpdb;
        $wpdb->insert( self::$table_name, array( 'click_id' => 'atomic-null', 'platform' => 'google' ) );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        $wpdb->query( $wpdb->prepare( 'UPDATE %i SET events = NULL WHERE click_id = %s', self::$table_name, 'atomic-null' ) );
        $this->assertTrue( CUFT_Click_Tracker::add_event( 'atomic-null', 'form_submit' ) );
        $this->assertSame( array( 'form_submit' ), $this->event_names( 'atomic-null' ) );
    }

    public function test_add_event_on_empty_string_events_column() {
        global $wpdb;
        $wpdb->insert( self::$table_name, array( 'click_id' => 'atomic-empty', 'platform' => 'google', 'events' => '' ) );
        $this->assertTrue( CUFT_Click_Tracker::add_event( 'atomic-empty', 'form_submit' ) );
        $this->assertSame( array( 'form_submit' ), $this->event_names( 'atomic-empty' ) );
    }

    public function test_add_event_is_idempotent_per_type() {
        $this->assertTrue( CUFT_Click_Tracker::add_event( 'atomic-dup', 'form_submit' ) );
        // Same second, same value: the UPDATE changes nothing and must still report success.
        $this->assertTrue( CUFT_Click_Tracker::add_event( 'atomic-dup', 'form_submit' ) );
        $this->assertSame( array( 'form_submit' ), $this->event_names( 'atomic-dup' ) );
    }

    /**
     * A concurrent writer changes the row between this request's read and
     * its write. The compare-and-swap must notice, re-read and keep both.
     */
    public function test_add_event_retries_when_row_changes_underneath() {
        global $wpdb;
        CUFT_Click_Tracker::add_event( 'atomic-race', 'form_submit' );

        $table   = self::$table_name;
        $fired   = 0;
        $meddler = function ( $query ) use ( $wpdb, $table, &$fired ) {
            if ( 0 === $fired && 0 === strpos( $query, 'UPDATE' ) && false !== strpos( $query, 'date_updated' ) ) {
                $fired++;
                // Simulate another request landing generate_lead first.
                $other = wp_json_encode(
                    CUFT_Click_Tracker::merge_event(
                        CUFT_Click_Tracker::decode_events( $wpdb->get_var( $wpdb->prepare( 'SELECT events FROM %i WHERE click_id = %s', $table, 'atomic-race' ) ) ),
                        'generate_lead',
                        gmdate( 'c' )
                    )
                );
                $wpdb->query( $wpdb->prepare( 'UPDATE %i SET events = %s WHERE click_id = %s', $table, $other, 'atomic-race' ) );
            }
            return $query;
        };
        add_filter( 'query', $meddler );
        $ok = CUFT_Click_Tracker::add_event( 'atomic-race', 'qualify_lead' );
        remove_filter( 'query', $meddler );

        $this->assertTrue( $ok );
        $this->assertSame( 1, $fired );
        $this->assertSame( array( 'form_submit', 'generate_lead', 'qualify_lead' ), $this->event_names( 'atomic-race' ) );
    }

    /**
     * Another connection holds the per-click lock (or GET_LOCK is
     * unavailable). add_event() waits out the timeout, then writes with
     * compare-and-swap alone instead of dropping the event.
     */
    public function test_add_event_falls_back_when_lock_is_held_elsewhere() {
        if ( ! class_exists( 'mysqli' ) ) {
            $this->markTestSkipped( 'mysqli not available' );
        }
        $host = DB_HOST;
        $port = 3306;
        if ( preg_match( '/^(.+):(\d+)$/', $host, $m ) ) {
            $host = $m[1];
            $port = (int) $m[2];
        }
        $other = mysqli_init();
        if ( ! @$other->real_connect( $host, DB_USER, DB_PASSWORD, DB_NAME, $port ) ) {
            $this->markTestSkipped( 'second connection unavailable' );
        }
        $name = $other->real_escape_string( CUFT_Click_Tracker::events_lock_name( 'atomic-held' ) );
        $got  = $other->query( "SELECT GET_LOCK('{$name}', 1)" )->fetch_row();
        $this->assertSame( '1', (string) $got[0] );

        $ok = CUFT_Click_Tracker::add_event( 'atomic-held', 'form_submit' );

        $other->query( "SELECT RELEASE_LOCK('{$name}')" );
        $other->close();

        $this->assertTrue( $ok );
        $this->assertSame( array( 'form_submit' ), $this->event_names( 'atomic-held' ) );
    }

    // ── cuftConfig on pages without jQuery (Contact Form 7) ──────────

    public function test_cuft_config_is_printed_from_own_handle_without_jquery() {
        $scripts = wp_scripts();
        CUFT_Plugin::get_instance()->enqueue_cuft_config();

        $this->assertTrue( wp_script_is( 'cuft-config', 'enqueued' ) );
        $this->assertFalse( wp_script_is( 'jquery', 'enqueued' ), 'config must not pull jQuery in' );
        $inline = implode( "\n", (array) $scripts->get_data( 'cuft-config', 'after' ) );
        $this->assertStringContainsString( 'var cuftConfig = ', $inline );
        $this->assertStringContainsString( 'admin-ajax.php', $inline );
        $this->assertSame( 0, (int) $scripts->get_data( 'cuft-config', 'group' ), 'config prints in the head' );
    }
}
