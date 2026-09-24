<?php
/**
 * Server-side Google Tag Manager host handling.
 *
 * Server-side tagging serves the same Google Tag Manager container from a
 * first-party hostname (for example gtm.example.com) that the site owner
 * points at their tagging server. The administrator supplies only that bare
 * hostname. The plugin builds every URL itself from the validated host and
 * the validated GTM container ID:
 *
 *   https://<host>/gtm.js?id=<GTM-ID>
 *   https://<host>/ns.html?id=<GTM-ID>
 *
 * No scheme, path, query, port or credentials supplied by a user ever reach
 * the page. When no valid host is configured, or the tagging server fails its
 * health check, Google's own www.googletagmanager.com is used.
 *
 * @since 3.29.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_SGTM {

    /**
     * Option holding the bare tagging-server hostname.
     */
    const OPTION_HOST = 'cuft_sgtm_host';

    /**
     * Option used before 3.29.0, which held a full URL. Read once to migrate.
     */
    const LEGACY_OPTION_URL = 'cuft_sgtm_url';

    /**
     * Google's own GTM host, used whenever no custom host is active.
     */
    const DEFAULT_HOST = 'www.googletagmanager.com';

    /**
     * Validate an administrator-entered hostname.
     *
     * Accepts a bare hostname such as gtm.example.com. A leading "https://"
     * and a single trailing slash are tolerated and removed, as a convenience
     * for people who paste the address from a browser. Anything else (another
     * scheme, a path, a query, a port, credentials, an IP address, localhost
     * or a single-label name) is rejected.
     *
     * @param string $raw Raw input.
     * @return string|false Lowercase hostname, or false when invalid.
     */
    public static function sanitize_host( $raw ) {
        if ( ! is_string( $raw ) ) {
            return false;
        }

        $host = strtolower( trim( $raw ) );
        if ( 0 === strpos( $host, 'https://' ) ) {
            $host = substr( $host, 8 );
        }
        if ( '/' === substr( $host, -1 ) ) {
            $host = substr( $host, 0, -1 );
        }

        if ( '' === $host || strlen( $host ) > 253 ) {
            return false;
        }

        // Letters, digits, hyphens and dots only: rules out schemes, paths,
        // queries, fragments, ports, credentials and whitespace up front.
        if ( ! preg_match( '/^[a-z0-9.-]+$/', $host ) ) {
            return false;
        }

        if ( false === filter_var( $host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME ) ) {
            return false;
        }

        if ( false !== filter_var( $host, FILTER_VALIDATE_IP ) ) {
            return false;
        }

        $labels = explode( '.', $host );
        if ( count( $labels ) < 2 || in_array( '', $labels, true ) ) {
            return false;
        }

        // Reserved names that never point at a public tagging server.
        $tld = end( $labels );
        if ( 'localhost' === $tld || 'local' === $tld || 'localnet' === $tld || 'internal' === $tld || ctype_digit( $tld ) ) {
            return false;
        }

        return $host;
    }

    /**
     * Extract the host from a pre-3.29.0 stored URL.
     *
     * @param string $url Stored legacy value, e.g. https://tagging-server.example.com
     * @return array { host: string|false, exact: bool } exact is true when the
     *               legacy value carried nothing beyond scheme and host, so the
     *               migrated host produces the same script URL as before.
     */
    public static function parse_legacy_url( $url ) {
        $result = array(
            'host'  => false,
            'exact' => false,
        );

        if ( ! is_string( $url ) || '' === trim( $url ) ) {
            return $result;
        }

        $parts = wp_parse_url( trim( $url ) );
        if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
            return $result;
        }
        if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
            return $result;
        }
        if ( isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['port'] ) ) {
            return $result;
        }

        $result['host']  = self::sanitize_host( $parts['host'] );
        $path            = isset( $parts['path'] ) ? trim( $parts['path'], '/' ) : '';
        $result['exact'] = false !== $result['host'] && '' === $path && ! isset( $parts['query'] ) && ! isset( $parts['fragment'] );

        return $result;
    }

    /**
     * Move a pre-3.29.0 full-URL setting to the bare host option.
     *
     * Runs once: afterwards the legacy option is gone. A legacy URL that
     * carried a path cannot be represented by a host alone, so the host is
     * kept for the administrator to review but the site falls back to
     * Google's endpoint until the tagging server is tested again.
     *
     * @return void
     */
    public static function maybe_migrate_legacy_url() {
        $legacy = get_option( self::LEGACY_OPTION_URL, false );
        if ( false === $legacy ) {
            return;
        }

        if ( false === get_option( self::OPTION_HOST, false ) ) {
            $parsed = self::parse_legacy_url( (string) $legacy );
            update_option( self::OPTION_HOST, $parsed['host'] ? $parsed['host'] : '' );

            if ( ! $parsed['exact'] ) {
                update_option( 'cuft_sgtm_validated', false );
                update_option( 'cuft_sgtm_active_server', 'fallback' );
            }
        }

        delete_option( self::LEGACY_OPTION_URL );
    }

    /**
     * The configured tagging-server host, validated on every read.
     *
     * @return string Bare host, or an empty string when none is valid.
     */
    public static function get_host() {
        self::maybe_migrate_legacy_url();
        $host = self::sanitize_host( (string) get_option( self::OPTION_HOST, '' ) );
        return $host ? $host : '';
    }

    /**
     * Normalise and validate a GTM container ID.
     *
     * @param string $gtm_id Raw ID.
     * @return string|false Uppercase ID, or false when invalid.
     */
    public static function sanitize_gtm_id( $gtm_id ) {
        if ( ! is_string( $gtm_id ) ) {
            return false;
        }
        $gtm_id = strtoupper( trim( $gtm_id ) );
        return preg_match( '/^GTM-[A-Z0-9]+$/', $gtm_id ) ? $gtm_id : false;
    }

    /**
     * Whether pages should load GTM from the custom host right now.
     *
     * @return bool
     */
    public static function custom_host_active() {
        return get_option( 'cuft_sgtm_enabled', false )
            && '' !== self::get_host()
            && 'custom' === get_option( 'cuft_sgtm_active_server', 'fallback' );
    }

    /**
     * Host to load GTM from on this request.
     *
     * @return string
     */
    public static function get_active_host() {
        return self::custom_host_active() ? self::get_host() : self::DEFAULT_HOST;
    }

    /**
     * Build the gtm.js URL for a host and container.
     *
     * @param string $host   Host from sanitize_host() or DEFAULT_HOST.
     * @param string $gtm_id Container ID.
     * @return string Empty string when either part is invalid.
     */
    public static function script_url( $host, $gtm_id ) {
        return self::build_url( $host, 'gtm.js', $gtm_id );
    }

    /**
     * Build the ns.html (noscript) URL for a host and container.
     *
     * @param string $host   Host from sanitize_host() or DEFAULT_HOST.
     * @param string $gtm_id Container ID.
     * @return string Empty string when either part is invalid.
     */
    public static function noscript_url( $host, $gtm_id ) {
        return self::build_url( $host, 'ns.html', $gtm_id );
    }

    /**
     * @param string $host   Host.
     * @param string $file   gtm.js or ns.html.
     * @param string $gtm_id Container ID.
     * @return string
     */
    private static function build_url( $host, $file, $gtm_id ) {
        $gtm_id = self::sanitize_gtm_id( $gtm_id );
        $host   = self::DEFAULT_HOST === $host ? $host : self::sanitize_host( $host );
        if ( ! $gtm_id || ! $host ) {
            return '';
        }
        return 'https://' . $host . '/' . $file . '?id=' . $gtm_id;
    }
}
