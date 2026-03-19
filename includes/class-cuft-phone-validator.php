<?php
/**
 * CUFT Phone Validator
 *
 * Validates phone numbers by calling the Choice validator service
 * (phone-validator.choice.zone). Results are cached per phone number
 * for 24 hours via WordPress transients to avoid redundant API calls.
 *
 * The validator returns a combined Twilio + Abstract API payload plus
 * a quality_score (0-10) and line_type which feed into lead scoring.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Phone_Validator {

    const CACHE_TTL      = DAY_IN_SECONDS;
    const CACHE_PREFIX   = 'cuft_phone_';
    const OPTION_ENABLED = 'cuft_phone_validation_enabled';

    /**
     * Validate a phone number.
     * Returns the combined API payload on success, or WP_Error on failure.
     * Results are cached for 24 hours.
     *
     * @param string $phone Raw phone number (any format).
     * @return array|WP_Error
     */
    public static function validate( string $phone ): array|WP_Error {
        if ( ! self::is_enabled() ) {
            return new WP_Error( 'cuft_validation_disabled', 'Phone validation is not enabled' );
        }

        $token = CUFT_Token_Manager::get_token();
        if ( empty( $token ) ) {
            return new WP_Error( 'cuft_not_registered', 'Site is not registered with validator service' );
        }

        // Check cache
        $cache_key = self::CACHE_PREFIX . md5( $phone );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) {
            return $cached;
        }

        $url = CUFT_Token_Manager::get_service_url() . '/validate-phone';

        $response = wp_remote_post( $url, array(
            'timeout' => 10,
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( array(
                'phone' => $phone,
                'token' => $token,
            ) ),
        ) );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'cuft_validation_failed',
                'Could not reach validator: ' . $response->get_error_message()
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $code ) {
            $msg = is_array( $body ) ? ( $body['error'] ?? "HTTP {$code}" ) : "HTTP {$code}";
            return new WP_Error( 'cuft_validation_error', $msg );
        }

        // Cache successful result
        set_transient( $cache_key, $body, self::CACHE_TTL );

        return $body;
    }

    /**
     * Validate a phone number and return only the key fields needed for lead scoring:
     *   - is_valid (bool)
     *   - quality_score (int 0-10)
     *   - line_type (string)
     *   - phone_e164 (string)
     *
     * Returns null if validation is disabled or fails silently.
     */
    public static function get_phone_quality( string $phone ): ?array {
        $result = self::validate( $phone );

        if ( is_wp_error( $result ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'phone_validation_error', 'warning', array(
                    'phone' => substr( $phone, 0, 6 ) . '***',
                    'error' => $result->get_error_message(),
                ) );
            }
            return null;
        }

        return array(
            'is_valid'      => (bool) ( $result['is_valid'] ?? false ),
            'quality_score' => (int)  ( $result['quality_score'] ?? 0 ),
            'line_type'     => (string) ( $result['line_type'] ?? 'unknown' ),
            'phone_e164'    => (string) ( $result['phone_e164'] ?? $phone ),
        );
    }

    /**
     * Whether phone validation is enabled in settings.
     */
    public static function is_enabled(): bool {
        return (bool) get_option( self::OPTION_ENABLED, false );
    }
}
