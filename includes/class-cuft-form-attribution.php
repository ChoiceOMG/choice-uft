<?php
/**
 * Server-side form attribution assembler.
 *
 * CUFT already captures the full UTM + click ID set client-side and pushes it to
 * the dataLayer. This helper reads that same data back server-side at form-submit
 * time so it can be persisted into the lead record and forwarded to downstream
 * webhooks (for example n8n) without injecting hidden form fields.
 *
 * Sources, in priority order, for each value:
 *   1. The cuft_utm_data cookie (last-touch UTM + click IDs the visitor arrived with).
 *   2. The cuft_click_id cookie (authoritative single click_id).
 *   3. The click tracking table row (platform, landing page, referrer at click time).
 *   4. The cuft_first_touch cookie (first-touch UTM, written once, never overwritten).
 *   5. Request context ($_SERVER, wp_get_referer()).
 *
 * @since 3.24.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Form_Attribution {

    /**
     * UTM parameter keys.
     *
     * @var string[]
     */
    private static $utm_keys = array(
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
    );

    /**
     * Click ID parameter keys (mirrors CUFT_UTM_Tracker / spec).
     *
     * @var string[]
     */
    private static $click_id_keys = array(
        'click_id',
        'gclid',
        'gbraid',
        'wbraid',
        'fbclid',
        'rdt_cid',
        'msclkid',
        'ttclid',
        'li_fat_id',
        'twclid',
        'snap_click_id',
        'pclid',
    );

    /**
     * Build the full attribution payload available at submit time.
     *
     * Only non-empty values are included, except submitted_at which is always set.
     *
     * @param array $context Optional context: form_id, form_name, page_url.
     * @return array Flat associative array of attribution values.
     */
    public static function get_payload( $context = array() ) {
        $payload = array();

        // 1. Last-touch UTM + click IDs from the cuft_utm_data cookie.
        $utm_data = class_exists( 'CUFT_UTM_Tracker' ) ? CUFT_UTM_Tracker::get_utm_data() : array();
        if ( is_array( $utm_data ) ) {
            foreach ( self::$utm_keys as $key ) {
                if ( ! empty( $utm_data[ $key ] ) ) {
                    $payload[ $key ] = sanitize_text_field( $utm_data[ $key ] );
                }
            }
            foreach ( self::$click_id_keys as $key ) {
                if ( ! empty( $utm_data[ $key ] ) ) {
                    $payload[ $key ] = sanitize_text_field( $utm_data[ $key ] );
                }
            }
        }

        // 2. Authoritative click_id cookie (fallback if not already present).
        if ( empty( $payload['click_id'] ) && class_exists( 'CUFT_Click_Integration' ) ) {
            $cookie_click_id = CUFT_Click_Integration::get_current_click_id();
            if ( ! empty( $cookie_click_id ) ) {
                $payload['click_id'] = $cookie_click_id;
            }
        }

        // 3. Stored click row backfills UTM gaps + landing page / referrer / platform.
        $current_click = ! empty( $payload['click_id'] ) ? $payload['click_id'] : '';
        if ( $current_click && class_exists( 'CUFT_Click_Tracker' ) ) {
            $row = CUFT_Click_Tracker::get_click_by_id( $current_click );
            if ( $row ) {
                foreach ( self::$utm_keys as $key ) {
                    if ( empty( $payload[ $key ] ) && ! empty( $row->$key ) ) {
                        $payload[ $key ] = sanitize_text_field( $row->$key );
                    }
                }
                if ( ! empty( $row->platform ) ) {
                    $payload['click_platform'] = sanitize_text_field( $row->platform );
                }
                if ( ! empty( $row->additional_data ) ) {
                    $extra = json_decode( $row->additional_data, true );
                    if ( is_array( $extra ) ) {
                        if ( ! empty( $extra['landing_page'] ) ) {
                            $payload['landing_page'] = esc_url_raw( $extra['landing_page'] );
                        }
                        if ( ! empty( $extra['referrer'] ) ) {
                            $payload['referrer'] = esc_url_raw( $extra['referrer'] );
                        }
                    }
                }
            }
        }

        // 4. First-touch UTM from the cuft_first_touch cookie.
        $first = class_exists( 'CUFT_UTM_Tracker' ) ? CUFT_UTM_Tracker::get_first_touch_data() : array();
        if ( is_array( $first ) && ! empty( $first ) ) {
            foreach ( self::$utm_keys as $key ) {
                if ( ! empty( $first[ $key ] ) ) {
                    $payload[ 'first_' . $key ] = sanitize_text_field( $first[ $key ] );
                }
            }
            if ( ! empty( $first['landing_page'] ) && empty( $payload['landing_page'] ) ) {
                $payload['landing_page'] = esc_url_raw( $first['landing_page'] );
            }
            if ( ! empty( $first['timestamp'] ) ) {
                $payload['first_touch_at'] = self::iso8601( $first['timestamp'] );
            }
        }

        // 5. Page context. During an AJAX submit, REQUEST_URI is admin-ajax.php,
        //    so the submitting page comes from context or the HTTP referer.
        $page_url = ! empty( $context['page_url'] ) ? $context['page_url'] : self::current_page_url();
        if ( $page_url ) {
            $payload['page_url'] = esc_url_raw( $page_url );
        }
        if ( empty( $payload['referrer'] ) && ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $payload['referrer'] = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
        }
        if ( ! empty( $context['form_name'] ) ) {
            $payload['form_name'] = sanitize_text_field( $context['form_name'] );
        }
        if ( ! empty( $context['form_id'] ) ) {
            $payload['form_id'] = sanitize_text_field( $context['form_id'] );
        }

        // Service interest, derived from the submitting page (configurable, not hard-coded).
        $service_interest = self::derive_service_interest( $page_url, $context );
        if ( ! empty( $service_interest ) ) {
            $payload['service_interest'] = sanitize_text_field( $service_interest );
        }

        // Submission timestamp, ISO 8601 UTC, always present.
        $payload['submitted_at'] = gmdate( 'c' );

        /**
         * Filter the assembled attribution payload before it is persisted or forwarded.
         *
         * @since 3.24.0
         *
         * @param array $payload Flat attribution payload.
         * @param array $context Submit context (form_id, form_name, page_url).
         */
        return apply_filters( 'cuft_form_attribution_payload', $payload, $context );
    }

    /**
     * Derive a service-interest label from the submitting page.
     *
     * Resolution order:
     *   1. cuft_service_interest_map option (path or last slug => label).
     *   2. Humanized last path segment.
     *   3. cuft_service_interest filter (final say, stays client-configurable).
     *
     * @param string $page_url Submitting page URL.
     * @param array  $context  Submit context.
     * @return string Service interest label (may be empty).
     */
    private static function derive_service_interest( $page_url, $context ) {
        $service = '';
        $path = '';

        if ( $page_url ) {
            $path = trim( (string) wp_parse_url( $page_url, PHP_URL_PATH ), '/' );
        }

        $map = get_option( 'cuft_service_interest_map', array() );
        if ( is_array( $map ) && ! empty( $map ) && $path ) {
            if ( isset( $map[ $path ] ) ) {
                $service = $map[ $path ];
            } else {
                $segments = explode( '/', $path );
                $last = end( $segments );
                if ( $last && isset( $map[ $last ] ) ) {
                    $service = $map[ $last ];
                }
            }
        }

        if ( '' === $service && $path ) {
            $segments = explode( '/', $path );
            $last = end( $segments );
            if ( $last ) {
                $service = ucwords( str_replace( array( '-', '_' ), ' ', $last ) );
            }
        }

        /**
         * Filter the derived service interest. Use this to map page URLs to
         * client-specific service names without editing core.
         *
         * @since 3.24.0
         *
         * @param string $service  Derived service interest.
         * @param string $page_url Submitting page URL.
         * @param array  $context  Submit context.
         */
        return apply_filters( 'cuft_service_interest', $service, $page_url, $context );
    }

    /**
     * Best-effort current page URL.
     *
     * During an AJAX form submit the request lands on admin-ajax.php, so the
     * actual page the visitor submitted from is the HTTP referer.
     *
     * @return string
     */
    private static function current_page_url() {
        $is_ajax_request = ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() )
            || ( ! empty( $_SERVER['REQUEST_URI'] ) && false !== strpos( $_SERVER['REQUEST_URI'], 'admin-ajax.php' ) );

        if ( $is_ajax_request && function_exists( 'wp_get_referer' ) ) {
            $referer = wp_get_referer();
            if ( $referer ) {
                return $referer;
            }
        }

        if ( empty( $_SERVER['HTTP_HOST'] ) || empty( $_SERVER['REQUEST_URI'] ) ) {
            return '';
        }

        $scheme = ( function_exists( 'is_ssl' ) && is_ssl() ) ? 'https' : 'http';
        return $scheme . '://'
            . sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) )
            . wp_unslash( $_SERVER['REQUEST_URI'] );
    }

    /**
     * Normalize a stored timestamp to ISO 8601 UTC.
     *
     * @param mixed $ts ISO string or unix timestamp.
     * @return string
     */
    private static function iso8601( $ts ) {
        if ( is_numeric( $ts ) ) {
            return gmdate( 'c', (int) $ts );
        }
        return sanitize_text_field( $ts );
    }
}
