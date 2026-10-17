<?php
/**
 * The Support link on the Plugins screen leads to the WordPress.org support
 * forum, where directory users are expected to ask, not to the source
 * repository's issue tracker.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 */

class Test_Plugin_Row extends WP_UnitTestCase {

    public function test_support_link_goes_to_the_wordpress_org_forum() {
        $meta = implode( ' ', apply_filters( 'plugin_row_meta', array(), CUFT_BASENAME, array(), 'all' ) );

        $this->assertStringContainsString( 'href="https://wordpress.org/support/plugin/choice-universal-form-tracker/"', $meta );
        $this->assertStringNotContainsString( 'github.com', $meta );
    }

    public function test_other_plugins_rows_are_untouched() {
        $this->assertSame( array( 'x' ), apply_filters( 'plugin_row_meta', array( 'x' ), 'hello.php', array(), 'all' ) );
    }
}
