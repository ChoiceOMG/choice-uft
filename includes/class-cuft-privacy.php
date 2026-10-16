<?php
/**
 * Privacy controls: phone numbers the site never tracks, click table
 * retention, and removal of the unkeyed IP hashes written before 3.30.0.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.30.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_Privacy {

    /**
     * Option holding the ignored phone numbers, stored normalized (see normalize_phone()).
     */
    const OPTION_IGNORE_PHONES = 'cuft_ignore_phone_numbers';

    /**
     * Option holding the click table retention in days. 0 keeps rows forever.
     */
    const OPTION_RETENTION_DAYS = 'cuft_click_retention_days';

    /**
     * Database-clock datetime of the upgrade to keyed IP hashing (date_created
     * is filled by MySQL's CURRENT_TIMESTAMP, so it is compared on the same
     * clock, whatever the server time zone). Rows created before it
     * carry the old unkeyed sha256(ip), which the retention job clears.
     * Deleted once no such row is left.
     */
    const OPTION_LEGACY_IP_HASH_BEFORE = 'cuft_ip_hash_legacy_before';

    /**
     * Daily cron hook. A run that leaves work behind schedules one follow-up
     * on the same hook with the argument true.
     */
    const RETENTION_HOOK = 'cuft_click_retention';

    const DEFAULT_RETENTION_DAYS = 365;

    /**
     * Shortest retention accepted. A typo such as 3 for 365 would otherwise
     * delete nearly the whole table on the next run; 30 days still covers
     * the window in which a lead is called back and qualified.
     */
    const MIN_RETENTION_DAYS = 30;

    const MAX_RETENTION_DAYS = 3650;

    /**
     * Rows deleted or updated per query. Small enough that each statement
     * holds its locks briefly on a busy table.
     */
    const BATCH_SIZE = 1000;

    /**
     * Upper bounds for one cron run, so a first run against a large table
     * stays well inside PHP and proxy timeouts. Whatever is left is picked
     * up by the follow-up event.
     */
    const MAX_BATCHES_PER_RUN = 50;
    const MAX_SECONDS_PER_RUN = 20;

    /**
     * Delay before the follow-up run when a run stopped at a bound.
     */
    const CONTINUE_DELAY = 300;

    /**
     * Phone match keys must have at least this many digits. Shorter values
     * would match unrelated numbers by their last digits.
     */
    const MIN_PHONE_DIGITS = 7;

    /**
     * Register the cron callback and keep the daily event scheduled.
     */
    public static function init() {
        add_action( self::RETENTION_HOOK, array( __CLASS__, 'run_retention' ) );
        add_action( 'init', array( __CLASS__, 'maybe_schedule' ) );
    }

    /**
     * Schedule the daily retention run if it is missing.
     *
     * Runs on every request rather than only on activation, because sites
     * that update through the updater or by copying files never reactivate.
     * The check reads the autoloaded cron array, so it costs no query.
     */
    public static function maybe_schedule() {
        if ( ! wp_next_scheduled( self::RETENTION_HOOK ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::RETENTION_HOOK );
        }
    }

    /**
     * Remove the daily event and any pending follow-up.
     */
    public static function unschedule() {
        wp_unschedule_hook( self::RETENTION_HOOK );
    }

    /**
     * Reduce a phone number or tel: href to its match key: the digits before
     * any extension, last 10 kept, so +1 (780) 555-0173, 780.555.0173 and
     * tel:+17805550173;ext=2 all give 7805550173. assets/cuft-links.js
     * applies the same rule to the clicked href.
     *
     * @param string $raw Phone number or tel: href.
     * @return string Match key, or '' when fewer than MIN_PHONE_DIGITS digits remain.
     */
    public static function normalize_phone( $raw ) {
        if ( ! is_scalar( $raw ) ) {
            return '';
        }
        $value = preg_replace( '/^\s*tel:/i', '', rawurldecode( (string) $raw ) );
        // An extension starts at ; or , (RFC 3966 and pause syntax) or at the
        // first letter (ext, x, p, w).
        $parts  = preg_split( '/[;,a-z]/i', $value );
        $digits = preg_replace( '/\D/', '', $parts[0] );
        if ( strlen( $digits ) < self::MIN_PHONE_DIGITS ) {
            return '';
        }
        return substr( $digits, -10 );
    }

    /**
     * Turn settings input (one number per line, or comma separated) into a
     * list of match keys.
     *
     * @param string|array $input Raw textarea value or list.
     * @param array        $rejected Filled with the entries that had too few digits.
     * @return string[] Unique match keys in input order.
     */
    public static function parse_phone_list( $input, &$rejected = array() ) {
        $rejected = array();
        $entries  = is_array( $input ) ? $input : preg_split( '/[\r\n]+/', (string) $input );
        $keys     = array();

        foreach ( $entries as $entry ) {
            // ", " also separates entries. A bare comma stays inside the entry,
            // where it marks a pause or extension (tel:7805550173,2).
            foreach ( preg_split( '/,\s+/', (string) $entry ) as $candidate ) {
                $candidate = trim( $candidate );
                if ( '' === $candidate ) {
                    continue;
                }
                $key = self::normalize_phone( $candidate );
                if ( '' === $key ) {
                    $rejected[] = $candidate;
                    continue;
                }
                $keys[ $key ] = true;
            }
        }

        return array_map( 'strval', array_keys( $keys ) );
    }

    /**
     * Phone numbers whose clicks are never tracked.
     *
     * @return string[] Match keys.
     */
    public static function get_ignored_phones() {
        $stored = get_option( self::OPTION_IGNORE_PHONES, array() );
        if ( ! is_array( $stored ) ) {
            $stored = array();
        }

        /**
         * Filter the phone numbers CUFT never tracks.
         *
         * Values may be in any format; they are reduced to the last 10 digits
         * before any extension, the same rule the front end applies to the
         * clicked tel: link.
         *
         * @since 3.30.0
         *
         * @param string[] $numbers Numbers from the Ignored Phone Numbers setting.
         */
        $numbers = apply_filters( 'cuft_ignore_phone_numbers', $stored );

        return self::parse_phone_list( is_array( $numbers ) ? $numbers : array() );
    }

    /**
     * Whether a phone number or tel: href is on the ignore list.
     *
     * @param string $raw Phone number or tel: href.
     * @return bool
     */
    public static function is_phone_ignored( $raw ) {
        $key = self::normalize_phone( $raw );
        return '' !== $key && in_array( $key, self::get_ignored_phones(), true );
    }

    /**
     * Clamp a retention value from the settings form.
     *
     * @param mixed $raw Submitted value.
     * @return int 0 (keep forever) or a day count within the bounds.
     */
    public static function sanitize_retention_days( $raw ) {
        // Anything that is not a number keeps the default rather than becoming
        // 0, which would mean keep forever.
        if ( ! is_numeric( $raw ) ) {
            return self::DEFAULT_RETENTION_DAYS;
        }
        $days = absint( $raw );
        if ( 0 === $days ) {
            return 0;
        }
        return max( self::MIN_RETENTION_DAYS, min( self::MAX_RETENTION_DAYS, $days ) );
    }

    /**
     * Effective retention in days. 0 keeps rows forever.
     *
     * @return int
     */
    public static function get_retention_days() {
        $days = get_option( self::OPTION_RETENTION_DAYS, self::DEFAULT_RETENTION_DAYS );

        /**
         * Filter the click table retention, in days. 0 keeps rows forever.
         * Values between 1 and 29 are raised to 30.
         *
         * @since 3.30.0
         *
         * @param int $days Retention from the settings screen.
         */
        return self::sanitize_retention_days( apply_filters( 'cuft_click_retention_days', $days ) );
    }

    /**
     * Cron callback: delete expired click rows, then clear unkeyed IP hashes
     * left from before 3.30.0, in bounded batches.
     *
     * @return array Counts: deleted, ip_hashes_cleared, complete.
     */
    public static function run_retention() {
        $started  = microtime( true );
        $batches  = 0;
        $deleted  = 0;
        $cleared  = 0;
        $complete = true;
        $days     = self::get_retention_days();

        if ( self::click_table_exists() ) {
            if ( $days > 0 ) {
                do {
                    $rows     = self::delete_expired_batch( $days );
                    $deleted += max( 0, (int) $rows );
                    $batches++;
                    $more = self::BATCH_SIZE === $rows;
                } while ( $more && ! self::over_budget( $batches, $started ) );
                $complete = ! $more;
            }

            $legacy_before = get_option( self::OPTION_LEGACY_IP_HASH_BEFORE, '' );
            if ( $complete && $legacy_before ) {
                $done = false;
                while ( ! $done && ! self::over_budget( $batches, $started ) ) {
                    $rows     = self::clear_legacy_ip_hash_batch( $legacy_before );
                    $cleared += max( 0, (int) $rows );
                    $batches++;
                    if ( false === $rows ) {
                        break;
                    }
                    $done = self::BATCH_SIZE !== $rows;
                }
                if ( $done ) {
                    delete_option( self::OPTION_LEGACY_IP_HASH_BEFORE );
                } else {
                    $complete = false;
                }
            }
        }

        if ( ! $complete ) {
            wp_schedule_single_event( time() + self::CONTINUE_DELAY, self::RETENTION_HOOK, array( true ) );
        }

        if ( ( $deleted || $cleared ) && class_exists( 'CUFT_Logger' ) ) {
            CUFT_Logger::log(
                "Click retention: deleted {$deleted} rows older than {$days} days, cleared {$cleared} unkeyed IP hashes",
                'info'
            );
        }

        return array(
            'deleted'           => $deleted,
            'ip_hashes_cleared' => $cleared,
            'complete'          => $complete,
        );
    }

    /**
     * @param int   $batches Batches run so far.
     * @param float $started microtime() at the start of the run.
     * @return bool
     */
    private static function over_budget( $batches, $started ) {
        return $batches >= self::MAX_BATCHES_PER_RUN || ( microtime( true ) - $started ) >= self::MAX_SECONDS_PER_RUN;
    }

    /**
     * The cutoff is computed by MySQL, on the same clock that filled
     * date_created.
     *
     * @param int $days Rows created more than this many days ago are deleted.
     * @return int|false Rows deleted.
     */
    private static function delete_expired_batch( $days ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; bounded retention delete, no WP API covers it.
        return $wpdb->query( $wpdb->prepare(
            'DELETE FROM %i WHERE date_created < NOW() - INTERVAL %d DAY LIMIT %d',
            $wpdb->prefix . 'cuft_click_tracking',
            $days,
            self::BATCH_SIZE
        ) );
    }

    /**
     * @param string $before Database-clock datetime of the upgrade to keyed hashing.
     * @return int|false Rows updated.
     */
    private static function clear_legacy_ip_hash_batch( $before ) {
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Plugin's own table cuft_click_tracking; bounded one-time cleanup, no WP API covers it.
        return $wpdb->query( $wpdb->prepare(
            'UPDATE %i SET ip_hash = NULL WHERE date_created < %s AND ip_hash IS NOT NULL LIMIT %d',
            $wpdb->prefix . 'cuft_click_tracking',
            $before,
            self::BATCH_SIZE
        ) );
    }

    /**
     * @return bool
     */
    private static function click_table_exists() {
        global $wpdb;
        // A probe query, which also sees temporary tables (SHOW TABLES does
        // not); errors are suppressed because a missing table is an answer here.
        $suppress = $wpdb->suppress_errors( true );
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existence check on the plugin's own table before a cron cleanup.
        $result = $wpdb->query( $wpdb->prepare( 'SELECT 1 FROM %i LIMIT 1', $wpdb->prefix . 'cuft_click_tracking' ) );
        $wpdb->suppress_errors( $suppress );
        return false !== $result;
    }
}
