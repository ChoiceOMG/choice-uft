<?php
/**
 * Test Database Table Creation and CRUD
 *
 * Tests for the test events database table operations.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

class Test_Test_Events_Table extends WP_UnitTestCase {

    /**
     * Test events table instance
     *
     * @var CUFT_Test_Events_Table
     */
    private $table;

    /**
     * Table name with prefix
     *
     * @var string
     */
    private $table_name;

    /**
     * Set up test fixtures
     */
    public function setUp() {
        parent::setUp();

        global $wpdb;

        // Initialize table class
        $this->table = new CUFT_Test_Events_Table();
        $this->table_name = $wpdb->prefix . 'cuft_test_events';

        // Ensure table exists for tests
        $this->table->maybe_update();
    }

    /**
     * Clean up after tests
     */
    public function tearDown() {
        // Clean up test data
        $this->table->delete_all();
        parent::tearDown();
    }

    /**
     * Test table creation with maybe_update
     *
     * @test
     */
    public function test_table_created_on_maybe_update() {
        global $wpdb;

        // Drop table to test creation
        $wpdb->query("DROP TABLE IF EXISTS {$this->table_name}");

        // Verify table doesn't exist
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $this->assertNull($table_exists);

        // Run maybe_update
        $this->table->maybe_update();

        // Verify table now exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $this->assertEquals($this->table_name, $table_exists);

        // Check table structure
        $columns = $wpdb->get_results("SHOW COLUMNS FROM {$this->table_name}");
        $column_names = array_map(function($col) {
            return $col->Field;
        }, $columns);

        // Verify all required columns exist
        $expected_columns = array('id', 'session_id', 'event_type', 'event_data', 'test_mode', 'created_at');
        foreach ($expected_columns as $column) {
            $this->assertContains($column, $column_names);
        }

        // Check indexes
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$this->table_name}");
        $index_names = array_map(function($idx) {
            return $idx->Key_name;
        }, $indexes);

        $this->assertContains('session_id', $index_names);
        $this->assertContains('event_type', $index_names);
        $this->assertContains('created_at', $index_names);
    }

    /**
     * Test insert_event returns ID
     *
     * @test
     */
    public function test_insert_event_returns_id() {
        $session_id = 'test_session_' . uniqid();
        $event_type = 'form_submit';
        $event_data = array(
            'event' => 'form_submit',
            'form_id' => 'test-123',
            'user_email' => 'test@example.com',
            'test_mode' => true
        );

        // Insert event
        $insert_id = $this->table->insert_event($session_id, $event_type, $event_data);

        // Assert ID was returned
        $this->assertNotFalse($insert_id);
        $this->assertGreaterThan(0, $insert_id);

        // Verify event was inserted
        global $wpdb;
        $saved = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $insert_id
        ));

        $this->assertNotNull($saved);
        $this->assertEquals($session_id, $saved->session_id);
        $this->assertEquals($event_type, $saved->event_type);
        $this->assertEquals(json_encode($event_data), $saved->event_data);
        $this->assertEquals(1, $saved->test_mode);
    }

    /**
     * Test get_events_by_session returns correct events
     *
     * @test
     */
    public function test_get_events_by_session_returns_correct_events() {
        $session_id = 'test_session_' . uniqid();
        $other_session = 'other_session_' . uniqid();

        // Insert events for target session
        $event_ids = array();
        $event_types = array('phone_click', 'email_click', 'form_submit');

        foreach ($event_types as $type) {
            $event_ids[] = $this->table->insert_event($session_id, $type, array(
                'event' => $type,
                'timestamp' => time()
            ));
        }

        // Insert event for different session
        $this->table->insert_event($other_session, 'generate_lead', array(
            'event' => 'generate_lead'
        ));

        // Get events for target session
        $events = $this->table->get_events_by_session($session_id);

        // Assert correct number of events
        $this->assertCount(3, $events);

        // Verify all events belong to correct session
        foreach ($events as $event) {
            $this->assertEquals($session_id, $event->session_id);
        }

        // Verify event types match and are in chronological order
        $retrieved_types = array_map(function($e) {
            return $e->event_type;
        }, $events);
        $this->assertEquals($event_types, $retrieved_types);

        // Verify event_data is decoded
        foreach ($events as $event) {
            $this->assertInternalType('array', $event->event_data);
            $this->assertArrayHasKey('event', $event->event_data);
        }
    }

    /**
     * Test get_events with filters
     *
     * @test
     */
    public function test_get_events_with_filters() {
        // Insert various events
        $sessions = array('session_1', 'session_2', 'session_3');
        $types = array('phone_click', 'email_click', 'form_submit');

        foreach ($sessions as $session) {
            foreach ($types as $type) {
                $this->table->insert_event($session, $type, array('event' => $type));
            }
        }

        // Test filtering by session
        $events = $this->table->get_events(array('session_id' => 'session_1'));
        $this->assertCount(3, $events);

        // Test filtering by event type
        $events = $this->table->get_events(array('event_type' => 'phone_click'));
        $this->assertCount(3, $events);

        // Test filtering by both
        $events = $this->table->get_events(array(
            'session_id' => 'session_2',
            'event_type' => 'email_click'
        ));
        $this->assertCount(1, $events);

        // Test pagination
        $events = $this->table->get_events(array('limit' => 5));
        $this->assertCount(5, $events);

        $events = $this->table->get_events(array('limit' => 5, 'offset' => 5));
        $this->assertCount(4, $events); // 9 total events

        // Verify order is DESC by created_at
        $all_events = $this->table->get_events();
        $timestamps = array_map(function($e) {
            return strtotime($e->created_at);
        }, $all_events);

        for ($i = 1; $i < count($timestamps); $i++) {
            $this->assertLessThanOrEqual($timestamps[$i-1], $timestamps[$i],
                'Events should be ordered by created_at DESC');
        }
    }

    /**
     * Test delete_all truncates table
     *
     * @test
     */
    public function test_delete_all_truncates_table() {
        // Insert multiple events
        for ($i = 0; $i < 10; $i++) {
            $this->table->insert_event('session_' . $i, 'test_event', array('index' => $i));
        }

        // Verify events exist
        $events = $this->table->get_events();
        $this->assertGreaterThan(0, count($events));

        // Delete all
        $deleted = $this->table->delete_all();

        // Verify table is empty
        $events = $this->table->get_events();
        $this->assertCount(0, $events);

        // Verify table still exists
        global $wpdb;
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$this->table_name}'");
        $this->assertEquals($this->table_name, $table_exists);
    }

    /**
     * Test cleanup_old_events removes old data
     *
     * @test
     */
    public function test_cleanup_old_events_removes_old_data() {
        global $wpdb;

        // Insert old events (35 days ago)
        $old_date = date('Y-m-d H:i:s', strtotime('-35 days'));
        for ($i = 0; $i < 5; $i++) {
            $wpdb->insert($this->table_name, array(
                'session_id' => 'old_session_' . $i,
                'event_type' => 'test_event',
                'event_data' => json_encode(array('old' => true)),
                'test_mode' => 1,
                'created_at' => $old_date
            ));
        }

        // Insert recent events
        for ($i = 0; $i < 5; $i++) {
            $this->table->insert_event('new_session_' . $i, 'test_event', array('new' => true));
        }

        // Total should be 10
        $all_events = $this->table->get_events();
        $this->assertCount(10, $all_events);

        // Clean up events older than 30 days
        $deleted_count = $this->table->cleanup_old_events(30);

        // Should have deleted 5 old events
        $this->assertEquals(5, $deleted_count);

        // Only 5 recent events should remain
        $remaining = $this->table->get_events();
        $this->assertCount(5, $remaining);

        // Verify all remaining are new events
        foreach ($remaining as $event) {
            $this->assertStringStartsWith('new_session_', $event->session_id);
        }
    }

    /**
     * Test delete_by_id deletes specific events
     *
     * @test
     */
    public function test_delete_by_id() {
        // Insert multiple events
        $ids = array();
        for ($i = 0; $i < 5; $i++) {
            $ids[] = $this->table->insert_event('session_test', 'event_' . $i, array('index' => $i));
        }

        // Delete specific IDs
        $to_delete = array($ids[1], $ids[3]);
        $deleted_count = $this->table->delete_by_id($to_delete);

        $this->assertEquals(2, $deleted_count);

        // Verify correct events were deleted
        $remaining = $this->table->get_events();
        $this->assertCount(3, $remaining);

        $remaining_ids = array_map(function($e) {
            return $e->id;
        }, $remaining);

        $this->assertNotContains($ids[1], $remaining_ids);
        $this->assertNotContains($ids[3], $remaining_ids);
        $this->assertContains($ids[0], $remaining_ids);
        $this->assertContains($ids[2], $remaining_ids);
        $this->assertContains($ids[4], $remaining_ids);
    }

    /**
     * Test delete_by_session deletes all events from session
     *
     * @test
     */
    public function test_delete_by_session() {
        $target_session = 'target_session';
        $other_session = 'other_session';

        // Insert events for target session
        for ($i = 0; $i < 3; $i++) {
            $this->table->insert_event($target_session, 'event_' . $i, array());
        }

        // Insert events for other session
        for ($i = 0; $i < 2; $i++) {
            $this->table->insert_event($other_session, 'event_' . $i, array());
        }

        // Delete target session
        $deleted_count = $this->table->delete_by_session($target_session);
        $this->assertEquals(3, $deleted_count);

        // Verify only other session events remain
        $remaining = $this->table->get_events();
        $this->assertCount(2, $remaining);

        foreach ($remaining as $event) {
            $this->assertEquals($other_session, $event->session_id);
        }
    }

    /**
     * Test get_events_count
     *
     * @test
     */
    public function test_get_events_count() {
        // Insert events
        for ($i = 0; $i < 7; $i++) {
            $this->table->insert_event('session_a', 'phone_click', array());
        }
        for ($i = 0; $i < 3; $i++) {
            $this->table->insert_event('session_b', 'email_click', array());
        }

        // Test total count
        $total = $this->table->get_events_count();
        $this->assertEquals(10, $total);

        // Test filtered count
        $count = $this->table->get_events_count(array('session_id' => 'session_a'));
        $this->assertEquals(7, $count);

        $count = $this->table->get_events_count(array('event_type' => 'email_click'));
        $this->assertEquals(3, $count);
    }

    /**
     * Test table version tracking
     *
     * @test
     */
    public function test_table_version_tracking() {
        // Get current version
        $version = get_option('cuft_test_events_db_version');
        $this->assertEquals('1.0', $version);

        // Calling maybe_update with same version shouldn't recreate
        global $wpdb;

        // Add a test row
        $this->table->insert_event('version_test', 'test', array());

        // Run maybe_update
        $this->table->maybe_update();

        // Event should still exist (table not recreated)
        $events = $this->table->get_events_by_session('version_test');
        $this->assertCount(1, $events);
    }
}