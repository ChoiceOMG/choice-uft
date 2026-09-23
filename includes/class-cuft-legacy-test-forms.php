<?php
/**
 * Removal of test forms left behind by the retired Test Form Builder.
 *
 * Versions before 3.28.0 could create test forms from the Testing Dashboard.
 * Each one was a WordPress post (an Elementor page, a Contact Form 7 form, an
 * Avada form) or a display page wrapping a Gravity Forms or Ninja Forms
 * shortcode, and every one of them carried the plugin's own marker meta:
 * `_cuft_test_form` = 1 and a `_cuft_instance_id` of the form
 * `cuft_test_<unix time>_<4 digits>`.
 *
 * Only posts carrying BOTH markers, with an instance ID in that exact format,
 * are touched. Titles are never used to identify anything.
 *
 * Called once by the 3.28.0 upgrade routine and again by uninstall.php.
 *
 * @package Choice_Universal_Form_Tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Legacy_Test_Forms {

    /**
     * Post types the Test Form Builder created.
     */
    const POST_TYPES = array( 'page', 'wpcf7_contact_form', 'fusion_form' );

    /**
     * Every postmeta key the Test Form Builder wrote.
     */
    const META_KEYS = array(
        '_cuft_test_form',
        '_cuft_instance_id',
        '_cuft_template_id',
        '_cuft_framework',
        '_cuft_created_at',
        '_cuft_test_count',
        '_cuft_form_id',
        '_cuft_shortcode',
    );

    /**
     * Options the Test Form Builder wrote. They hold IDs only; the forms and
     * pages some of them point at belong to the site owner and are left alone.
     */
    const OPTIONS = array(
        'cuft_test_gravity_form_id',
        'cuft_test_cf7_form_id',
        'cuft_test_ninja_form_id',
        'cuft_test_avada_page_id',
        'cuft_test_elementor_page_id',
        'cuft_form_templates',
    );

    /**
     * Delete every plugin-created test form and the options that tracked them.
     *
     * @return int Number of posts deleted.
     */
    public static function remove_all() {
        $deleted = 0;

        $post_ids = get_posts( array(
            'post_type'        => self::POST_TYPES,
            'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
            'numberposts'      => -1,
            'fields'           => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-time cleanup on upgrade and uninstall; the marker meta is the only safe way to find these posts.
            'meta_query'       => array(
                array(
                    'key'   => '_cuft_test_form',
                    'value' => '1',
                ),
            ),
        ) );

        foreach ( $post_ids as $post_id ) {
            $instance_id = (string) get_post_meta( $post_id, '_cuft_instance_id', true );
            if ( ! preg_match( '/^cuft_test_[0-9]+_[0-9]{4}$/', $instance_id ) ) {
                continue;
            }

            // A Ninja Forms form ID is not a post ID, so a Ninja test form's
            // markers could only ever have landed on an unrelated post. Strip
            // the markers and keep the post.
            if ( 'ninja' === get_post_meta( $post_id, '_cuft_framework', true ) ) {
                self::strip_meta( $post_id );
                continue;
            }

            self::remove_gravity_form_for_page( $post_id, $instance_id );

            if ( wp_delete_post( $post_id, true ) ) {
                $deleted++;
            }
        }

        foreach ( self::OPTIONS as $option ) {
            delete_option( $option );
        }

        return $deleted;
    }

    /**
     * Delete the Gravity Forms form a test display page wrapped, when that
     * form carries the same instance ID as the page.
     *
     * @param int    $page_id     Display page ID.
     * @param string $instance_id The page's _cuft_instance_id.
     */
    private static function remove_gravity_form_for_page( $page_id, $instance_id ) {
        if ( ! class_exists( 'GFAPI' ) || ! function_exists( 'gform_get_meta' ) ) {
            return;
        }

        $content = (string) get_post_field( 'post_content', $page_id );
        if ( ! preg_match( '/\[gravityform id="([0-9]+)"/', $content, $matches ) ) {
            return;
        }

        $form_id = (int) $matches[1];
        if ( $form_id <= 0 || gform_get_meta( $form_id, '_cuft_instance_id' ) !== $instance_id ) {
            return;
        }

        GFAPI::delete_form( $form_id );

        if ( function_exists( 'gform_delete_meta' ) ) {
            foreach ( array( '_cuft_test_form', '_cuft_instance_id', '_cuft_template_id', '_cuft_framework', '_cuft_created_at' ) as $key ) {
                gform_delete_meta( $form_id, $key );
            }
        }
    }

    /**
     * Remove the Test Form Builder's meta from a post without deleting it.
     *
     * @param int $post_id Post ID.
     */
    private static function strip_meta( $post_id ) {
        foreach ( self::META_KEYS as $key ) {
            delete_post_meta( $post_id, $key );
        }
    }
}
