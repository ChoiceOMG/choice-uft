<?php
/**
 * AI Readiness Files
 *
 * Serves /llms.txt, /ai.txt, and /llms-full.txt as plain text responses.
 * Content for each file is stored in WordPress options and editable via
 * the CUFT settings page.
 *
 * Option keys:
 *   cuft_ai_file_llms      - /llms.txt content
 *   cuft_ai_file_ai        - /ai.txt content
 *   cuft_ai_file_llms_full - /llms-full.txt content
 *
 * Last-resort behaviour:
 *   - If a physical file already exists at the webroot path (placed manually
 *     or by another plugin), this handler steps aside and does nothing.
 *   - Another plugin or theme can disable the handler entirely via the
 *     `cuft_ai_files_enabled` filter:
 *       add_filter( 'cuft_ai_files_enabled', '__return_false' );
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_AI_Files {

    /**
     * Map of URL paths to option keys.
     */
    const FILE_MAP = array(
        '/llms.txt'      => 'cuft_ai_file_llms',
        '/ai.txt'        => 'cuft_ai_file_ai',
        '/llms-full.txt' => 'cuft_ai_file_llms_full',
    );

    public function __construct() {
        add_action( 'init', array( $this, 'maybe_serve_file' ), 1 );
    }

    /**
     * Intercept requests for AI readiness file paths and serve plain text.
     *
     * Steps aside when a physical file already exists at the path, or when
     * the `cuft_ai_files_enabled` filter returns false.
     */
    public function maybe_serve_file() {
        $path = rtrim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

        if ( ! isset( self::FILE_MAP[ $path ] ) ) {
            return;
        }

        // Yield to a physical file on disk (manual upload or another plugin).
        if ( file_exists( ABSPATH . ltrim( $path, '/' ) ) ) {
            return;
        }

        // Allow another plugin or theme to disable this handler entirely.
        if ( ! apply_filters( 'cuft_ai_files_enabled', true ) ) {
            return;
        }

        $content = get_option( self::FILE_MAP[ $path ], '' );

        if ( ! $content ) {
            return;
        }

        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );
        header( 'X-Robots-Tag: noindex' );
        echo $content;
        exit;
    }

    /**
     * Get all file option keys.
     */
    public static function get_option_keys() {
        return self::FILE_MAP;
    }
}
