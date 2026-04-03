<?php
// includes/class-cuft-measurement-protocol.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Measurement_Protocol {

    const ENDPOINT = 'https://www.google-analytics.com/mp/collect';

    public function is_configured(): bool {
        return ! empty( get_option( 'cuft_measurement_id', '' ) )
            && ! empty( get_option( 'cuft_measurement_api_secret', '' ) );
    }

    public function build_payload( string $client_id, string $event_name, array $event_params = array() ): array {
        $lead_value    = get_option( 'cuft_lead_value', 100 );
        $lead_currency = get_option( 'cuft_lead_currency', 'CAD' );

        $params = array_merge( array(
            'value'                => (float) $lead_value,
            'currency'             => $lead_currency,
            'engagement_time_msec' => 1,
        ), $event_params );

        return array(
            'client_id' => $client_id,
            'events'    => array(
                array(
                    'name'   => $event_name,
                    'params' => $params,
                ),
            ),
        );
    }

    public function send( string $client_id, string $event_name, array $event_params = array() ): bool {
        if ( ! $this->is_configured() ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Measurement Protocol not configured — skipping event: ' . $event_name );
            }
            return false;
        }

        if ( empty( $client_id ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'No ga_client_id available — skipping MP event: ' . $event_name );
            }
            return false;
        }

        $measurement_id = get_option( 'cuft_measurement_id', '' );
        $api_secret     = CUFT_Utils::decrypt_secret( get_option( 'cuft_measurement_api_secret', '' ) );

        $url = add_query_arg( array(
            'measurement_id' => $measurement_id,
            'api_secret'     => $api_secret,
        ), self::ENDPOINT );

        $payload = $this->build_payload( $client_id, $event_name, $event_params );

        $response = wp_remote_post( $url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 5,
        ) );

        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'MP request failed for ' . $event_name . ': ' . $response->get_error_message() );
            }
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'MP request returned HTTP ' . $code . ' for ' . $event_name );
            }
            return false;
        }

        return true;
    }
}
