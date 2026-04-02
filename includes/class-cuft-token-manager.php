<?php
/**
 * CUFT Token Manager
 *
 * Registers this WordPress site with the Choice phone validator service
 * and stores the resulting JWE token. The token encodes this site's domain
 * and can only be decrypted by the validator service.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Token_Manager {

    const OPTION_TOKEN      = 'cuft_validator_token';
    const OPTION_DOMAIN     = 'cuft_validator_domain';
    const OPTION_REGISTERED = 'cuft_validator_registered_at';

    public function __construct() {
        add_action( 'wp_ajax_cuft_token_register', array( __CLASS__, 'ajax_register' ) );
    }

    /**
     * Validator service base URL.
     * Can be overridden via CUFT_VALIDATOR_URL constant in wp-config.php.
     */
    public static function get_service_url(): string {
        if ( defined( 'CUFT_VALIDATOR_URL' ) ) {
            return rtrim( CUFT_VALIDATOR_URL, '/' );
        }
        return 'https://phone-validator.choice.zone';
    }

    /**
     * Get the registration secret. wp-config.php constant takes precedence,
     * then falls back to the DB option.
     */
    public static function get_register_secret_value(): string {
        if ( defined( 'CUFT_REGISTER_SECRET' ) ) {
            return CUFT_REGISTER_SECRET;
        }
        return get_option( 'cuft_register_secret', '' );
    }

    /**
     * Register this site with the validator service and store the returned token.
     * Returns WP_Error on failure, or the token string on success.
     */
    public static function register_site(): string|WP_Error {
        $secret = self::get_register_secret_value();
        if ( empty( $secret ) ) {
            return new WP_Error(
                'cuft_no_secret',
                'Registration secret is not configured. Define CUFT_REGISTER_SECRET in wp-config.php or set it in Settings > Universal Form Tracker > API Credentials.'
            );
        }

        $domain = wp_parse_url( home_url(), PHP_URL_HOST );
        if ( empty( $domain ) ) {
            return new WP_Error( 'cuft_no_domain', 'Could not determine site domain' );
        }

        $response = wp_remote_post(
            self::get_service_url() . '/register',
            array(
                'timeout' => 10,
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $secret,
                ),
                'body' => wp_json_encode( array( 'domain' => $domain ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'cuft_register_failed',
                'Could not reach validator service: ' . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code || empty( $body['token'] ) ) {
            $msg = $body['error'] ?? "HTTP {$code}";
            return new WP_Error( 'cuft_register_error', "Validator service error: {$msg}" );
        }

        // Store token and metadata
        update_option( self::OPTION_TOKEN,      $body['token'] );
        update_option( self::OPTION_DOMAIN,     $domain );
        update_option( self::OPTION_REGISTERED, time() );

        return $body['token'];
    }

    /**
     * Return the stored token, or empty string if not registered.
     */
    public static function get_token(): string {
        return (string) get_option( self::OPTION_TOKEN, '' );
    }

    /**
     * Whether this site has a stored token.
     */
    public static function is_registered(): bool {
        return ! empty( self::get_token() );
    }

    /**
     * Clear the stored token (forces re-registration).
     */
    public static function revoke(): void {
        delete_option( self::OPTION_TOKEN );
        delete_option( self::OPTION_DOMAIN );
        delete_option( self::OPTION_REGISTERED );
    }

    /**
     * AJAX handler: register this site and return the token status.
     * Called from the admin settings page.
     */
    public static function ajax_register(): void {
        check_ajax_referer( 'cuft_token_register', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions', 403 );
        }

        $result = self::register_site();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( array(
            'message' => 'Site registered successfully',
            'domain'  => get_option( self::OPTION_DOMAIN ),
        ) );
    }
}
