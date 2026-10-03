<?php
/**
 * Google tag gateway health (tag gateway spec D6 and 5.1). The script path must answer "ok"
 * on /healthy and /?validate_geo=healthy and serve the container on /?id=<GTM id>. The state
 * starts at fallback; two passes in a row select the gateway, two failures in a row the
 * Google fallback. Every state change purges page caches so visitors get the new loader.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_GTG_Health {
    const HOOK   = 'cuft_gtg_health_check';
    const STREAK = 2;

    public static function init() {
        add_action( self::HOOK, array( __CLASS__, 'run' ) );
        add_action( 'update_option_cuft_gtg_enabled', array( __CLASS__, 'on_enabled_change' ), 10, 2 );
        add_action( 'update_option_cuft_gtg_script_path', array( __CLASS__, 'on_path_change' ), 10, 2 );
        add_action( 'add_option_cuft_gtg_enabled', array( __CLASS__, 'reset' ), 10, 0 );
        add_action( 'add_option_cuft_gtg_script_path', array( __CLASS__, 'reset' ), 10, 0 );
        if ( get_option( 'cuft_gtg_enabled', false ) ) {
            if ( ! wp_next_scheduled( self::HOOK ) ) {
                wp_schedule_event( time() + 60, 'hourly', self::HOOK );
            }
        } elseif ( wp_next_scheduled( self::HOOK ) ) {
            wp_clear_scheduled_hook( self::HOOK );
        }
    }

    /**
     * WordPress fires update_option_* whenever the stored form differs ('1' versus true), so
     * only a real change of meaning resets the state.
     */
    public static function on_enabled_change( $old_value, $new_value ) {
        if ( (bool) $old_value !== (bool) $new_value ) {
            self::reset();
        }
    }

    public static function on_path_change( $old_value, $new_value ) {
        if ( self::normalize_path( $old_value ) !== self::normalize_path( $new_value ) ) {
            self::reset();
        }
    }

    /** A real change to the switch or the path starts over from the Google fallback. */
    public static function reset() {
        $before = get_option( 'cuft_gtg_active', 'fallback' );
        update_option( 'cuft_gtg_active', 'fallback' );
        update_option( 'cuft_gtg_fail_streak', 0 );
        update_option( 'cuft_gtg_ok_streak', 0 );
        update_option( 'cuft_gtg_fallback_reason', 'not yet checked' );
        if ( 'fallback' !== $before ) {
            // Switching off (a rollback) must also reach cached pages.
            self::purge_page_caches();
            do_action( 'cuft_gtg_state_changed', 'fallback', $before );
        }
    }

    public static function normalize_path( $path ) {
        $p = '/' . trim( (string) $path, '/' ) . '/';
        if ( ! preg_match( '#^/[a-z0-9]{4,32}/$#', $p ) || false !== strpos( $p, 'gtm' ) ) {
            return '';
        }
        return $p;
    }

    public static function probe() {
        $path = self::normalize_path( get_option( 'cuft_gtg_script_path', '' ) );
        $id   = (string) get_option( 'cuft_gtm_id', '' );
        if ( '' === $path || ! preg_match( '/^GTM-[A-Z0-9]+$/', $id ) ) {
            return array( false, 'no valid gateway path or GTM id' );
        }
        $base   = untrailingslashit( home_url() ) . $path;
        $checks = array(
            'healthy'               => 'ok',
            '?validate_geo=healthy' => 'ok',
            '?id=' . $id            => $id,
        );
        foreach ( $checks as $suffix => $want ) {
            $r    = wp_remote_get( $base . $suffix, array( 'timeout' => 5 ) );
            $code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
            $body = is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_body( $r );
            $good = 200 === $code && ( 'ok' === $want ? 'ok' === trim( $body ) : false !== strpos( $body, $want ) );
            if ( ! $good ) {
                return array( false, $suffix . ': ' . $code . ' ' . substr( trim( $body ), 0, 60 ) );
            }
        }
        return array( true, '' );
    }

    public static function record( $ok, $reason ) {
        $before = get_option( 'cuft_gtg_active', 'fallback' );
        $fail   = $ok ? 0 : (int) get_option( 'cuft_gtg_fail_streak', 0 ) + 1;
        $good   = $ok ? (int) get_option( 'cuft_gtg_ok_streak', 0 ) + 1 : 0;
        update_option( 'cuft_gtg_fail_streak', $fail );
        update_option( 'cuft_gtg_ok_streak', $good );
        $after = $before;
        if ( ! $ok && $fail >= self::STREAK ) {
            $after = 'fallback';
        } elseif ( $ok && $good >= self::STREAK ) {
            $after = 'gateway';
        }
        if ( ! $ok && 'fallback' === $after ) {
            update_option( 'cuft_gtg_fallback_reason', $reason );
        }
        if ( $after !== $before ) {
            update_option( 'cuft_gtg_active', $after );
            if ( 'gateway' === $after ) {
                delete_option( 'cuft_gtg_fallback_reason' );
            }
            self::purge_page_caches();
            do_action( 'cuft_gtg_state_changed', $after, $before );
        }
        return $after;
    }

    public static function run() {
        if ( ! get_option( 'cuft_gtg_enabled', false ) ) {
            return '';
        }
        list( $ok, $reason ) = self::probe();
        return self::record( $ok, $reason );
    }

    /**
     * Cached pages must pick up the new loader; each call is skipped when its plugin is absent.
     * The object cache is flushed last (spec 5.1), so a persistent cache holds no stale options.
     */
    public static function purge_page_caches() {
        if ( function_exists( 'wp_cache_clear_cache' ) ) {
            wp_cache_clear_cache();          // WP Super Cache
        }
        if ( function_exists( 'rocket_clean_domain' ) ) {
            rocket_clean_domain();           // WP Rocket
        }
        if ( function_exists( 'w3tc_flush_all' ) ) {
            w3tc_flush_all();                // W3 Total Cache
        }
        if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
            sg_cachepress_purge_cache();     // SiteGround Optimizer
        }
        do_action( 'litespeed_purge_all' );  // LiteSpeed Cache
        if ( function_exists( 'wp_cache_flush' ) ) {
            wp_cache_flush();                // Object cache
        }
    }
}
