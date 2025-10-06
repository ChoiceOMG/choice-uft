<?php
/**
 * Choice Universal Form Tracker - Rate Limiter
 *
 * Provides rate limiting for update endpoints to prevent abuse.
 * Uses WordPress transients for temporary storage.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Rate Limiter Class
 *
 * Implements token bucket algorithm for rate limiting
 */
class CUFT_Rate_Limiter {
    /**
     * Rate limit configurations
     *
     * @var array
     */
    private static $limits = array(
        'update_check' => array(
            'max_requests' => 10,
            'time_window' => 3600, // 1 hour
            'action' => 'cuft_check_update'
        ),
        'update_perform' => array(
            'max_requests' => 3,
            'time_window' => 3600, // 1 hour
            'action' => 'cuft_perform_update'
        ),
        'update_status' => array(
            'max_requests' => 60,
            'time_window' => 3600, // 1 hour
            'action' => 'cuft_update_status'
        ),
        'update_history' => array(
            'max_requests' => 20,
            'time_window' => 3600, // 1 hour
            'action' => 'cuft_update_history'
        )
    );

    /**
     * Check if request is allowed
     *
     * @param string $action Action being rate limited
     * @param string $identifier Unique identifier (user ID, IP, etc.)
     * @return bool True if request is allowed, false otherwise
     */
    public static function is_allowed($action, $identifier = null) {
        try {
            // Get identifier (user ID or IP address)
            if ($identifier === null) {
                $identifier = self::get_identifier();
            }

            // Get limit configuration
            $limit = self::get_limit_config($action);
            if (!$limit) {
                // No limit configured, allow request
                return true;
            }

            // Get current bucket state
            $bucket_key = self::get_bucket_key($action, $identifier);
            $bucket = get_transient($bucket_key);

            // Initialize bucket if not exists
            if ($bucket === false) {
                $bucket = array(
                    'tokens' => $limit['max_requests'],
                    'last_refill' => time()
                );
            }

            // Refill tokens based on time passed
            $bucket = self::refill_tokens($bucket, $limit);

            // Check if tokens available
            if ($bucket['tokens'] >= 1) {
                // Consume token
                $bucket['tokens'] -= 1;

                // Save bucket state
                set_transient($bucket_key, $bucket, $limit['time_window']);

                return true;
            }

            // Rate limit exceeded
            self::log_rate_limit($action, $identifier, $bucket);
            return false;

        } catch (Exception $e) {
            // On error, fail open (allow request)
            error_log('CUFT Rate Limiter Error: ' . $e->getMessage());
            return true;
        }
    }

    /**
     * Refill tokens based on elapsed time
     *
     * @param array $bucket Current bucket state
     * @param array $limit Limit configuration
     * @return array Updated bucket state
     */
    private static function refill_tokens($bucket, $limit) {
        $now = time();
        $elapsed = $now - $bucket['last_refill'];

        // Calculate refill rate (tokens per second)
        $refill_rate = $limit['max_requests'] / $limit['time_window'];

        // Calculate tokens to add
        $tokens_to_add = $elapsed * $refill_rate;

        // Add tokens (capped at max)
        $bucket['tokens'] = min(
            $limit['max_requests'],
            $bucket['tokens'] + $tokens_to_add
        );

        // Update last refill time
        $bucket['last_refill'] = $now;

        return $bucket;
    }

    /**
     * Get limit configuration for action
     *
     * @param string $action Action name
     * @return array|null Limit configuration or null if not found
     */
    private static function get_limit_config($action) {
        foreach (self::$limits as $limit) {
            if ($limit['action'] === $action) {
                return $limit;
            }
        }
        return null;
    }

    /**
     * Get unique identifier for rate limiting
     *
     * @return string Unique identifier
     */
    private static function get_identifier() {
        // Prefer user ID if logged in
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        }

        // Fallback to IP address
        return 'ip_' . self::get_client_ip();
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private static function get_client_ip() {
        $ip = '';

        // Check various headers for IP (consider proxy scenarios)
        $headers = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];

                // Handle comma-separated IPs (take first)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }

                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    break;
                }
            }
        }

        // Fallback to REMOTE_ADDR if no valid IP found
        if (empty($ip) && !empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return $ip ?: '0.0.0.0';
    }

    /**
     * Get bucket key for transient storage
     *
     * @param string $action Action name
     * @param string $identifier Unique identifier
     * @return string Transient key
     */
    private static function get_bucket_key($action, $identifier) {
        // Sanitize identifier to prevent injection
        $identifier = sanitize_key($identifier);
        $action = sanitize_key($action);

        return 'cuft_rate_limit_' . $action . '_' . md5($identifier);
    }

    /**
     * Get remaining requests for identifier
     *
     * @param string $action Action being checked
     * @param string $identifier Unique identifier
     * @return array Remaining requests and reset time
     */
    public static function get_remaining($action, $identifier = null) {
        try {
            if ($identifier === null) {
                $identifier = self::get_identifier();
            }

            $limit = self::get_limit_config($action);
            if (!$limit) {
                return array(
                    'remaining' => 999,
                    'reset' => 0,
                    'limit' => 999
                );
            }

            $bucket_key = self::get_bucket_key($action, $identifier);
            $bucket = get_transient($bucket_key);

            if ($bucket === false) {
                return array(
                    'remaining' => $limit['max_requests'],
                    'reset' => 0,
                    'limit' => $limit['max_requests']
                );
            }

            // Refill tokens
            $bucket = self::refill_tokens($bucket, $limit);

            return array(
                'remaining' => (int) $bucket['tokens'],
                'reset' => $bucket['last_refill'] + $limit['time_window'],
                'limit' => $limit['max_requests']
            );

        } catch (Exception $e) {
            error_log('CUFT Rate Limiter Error: ' . $e->getMessage());
            return array(
                'remaining' => 0,
                'reset' => 0,
                'limit' => 0
            );
        }
    }

    /**
     * Reset rate limit for identifier
     *
     * @param string $action Action to reset
     * @param string $identifier Unique identifier
     * @return bool Success
     */
    public static function reset($action, $identifier = null) {
        try {
            if ($identifier === null) {
                $identifier = self::get_identifier();
            }

            $bucket_key = self::get_bucket_key($action, $identifier);
            return delete_transient($bucket_key);

        } catch (Exception $e) {
            error_log('CUFT Rate Limiter Error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Log rate limit event
     *
     * @param string $action Action that was rate limited
     * @param string $identifier Unique identifier
     * @param array $bucket Current bucket state
     * @return void
     */
    private static function log_rate_limit($action, $identifier, $bucket) {
        try {
            // Only log if in debug mode
            if (!defined('WP_DEBUG') || !WP_DEBUG) {
                return;
            }

            $log_entry = sprintf(
                '[CUFT Rate Limit] Action: %s, Identifier: %s, Tokens: %s',
                $action,
                $identifier,
                $bucket['tokens']
            );

            error_log($log_entry);

        } catch (Exception $e) {
            // Silent failure for logging
        }
    }

    /**
     * Add rate limit headers to response
     *
     * @param string $action Action being performed
     * @param string $identifier Unique identifier
     * @return void
     */
    public static function add_headers($action, $identifier = null) {
        try {
            $remaining = self::get_remaining($action, $identifier);

            header('X-RateLimit-Limit: ' . $remaining['limit']);
            header('X-RateLimit-Remaining: ' . $remaining['remaining']);
            header('X-RateLimit-Reset: ' . $remaining['reset']);

        } catch (Exception $e) {
            // Silent failure for headers
        }
    }

    /**
     * Get rate limit error response
     *
     * @param string $action Action that was rate limited
     * @return array Error response data
     */
    public static function get_error_response($action) {
        $remaining = self::get_remaining($action);
        $reset_time = $remaining['reset'];
        $wait_seconds = max(0, $reset_time - time());

        return array(
            'success' => false,
            'data' => array(
                'message' => sprintf(
                    'Rate limit exceeded. Please try again in %s.',
                    self::format_wait_time($wait_seconds)
                ),
                'code' => 'rate_limit_exceeded',
                'wait_seconds' => $wait_seconds,
                'reset_time' => $reset_time
            )
        );
    }

    /**
     * Format wait time for display
     *
     * @param int $seconds Wait time in seconds
     * @return string Formatted time
     */
    private static function format_wait_time($seconds) {
        if ($seconds < 60) {
            return $seconds . ' second' . ($seconds !== 1 ? 's' : '');
        }

        $minutes = ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes !== 1 ? 's' : '');
    }

    /**
     * Check and enforce rate limit (wrapper for use in endpoints)
     *
     * @param string $action Action being rate limited
     * @return bool True if allowed, sends error response and exits if denied
     */
    public static function check_and_enforce($action) {
        $identifier = self::get_identifier();

        // Check rate limit
        if (!self::is_allowed($action, $identifier)) {
            // Add headers
            self::add_headers($action, $identifier);

            // Send error response
            wp_send_json(self::get_error_response($action), 429);
            exit;
        }

        // Add headers for successful request
        self::add_headers($action, $identifier);

        return true;
    }
}
