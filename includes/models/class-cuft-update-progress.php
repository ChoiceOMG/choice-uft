<?php
/**
 * UpdateProgress Model
 *
 * Tracks real-time progress of an ongoing update using WordPress transients.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT UpdateProgress Model
 *
 * Manages the real-time progress tracking for ongoing updates.
 */
class CUFT_Update_Progress {

    /**
     * Transient key for storing update progress
     */
    const TRANSIENT_KEY = 'cuft_update_progress';

    /**
     * Transient expiration time (5 minutes in seconds)
     */
    const TRANSIENT_EXPIRATION = 300;

    /**
     * Valid status values
     */
    const VALID_STATUSES = array(
        'idle',
        'checking',
        'downloading',
        'backing_up',
        'installing',
        'verifying',
        'complete',
        'failed',
        'rolling_back'
    );

    /**
     * Progress percentages for each status
     */
    const STATUS_PERCENTAGES = array(
        'idle' => 0,
        'checking' => 10,
        'downloading' => 30,
        'backing_up' => 50,
        'installing' => 70,
        'verifying' => 90,
        'complete' => 100,
        'failed' => 0,
        'rolling_back' => 0
    );

    /**
     * Get current progress
     *
     * @return array Progress data
     */
    public static function get() {
        $progress = get_transient( self::TRANSIENT_KEY );

        if ( false === $progress ) {
            $progress = self::get_default_progress();
        }

        return self::validate_progress( $progress );
    }

    /**
     * Set progress
     *
     * @param string $status Current status
     * @param int $percentage Progress percentage (0-100)
     * @param string $message Status message
     * @return bool True on success
     */
    public static function set( $status, $percentage = null, $message = '' ) {
        // Use default percentage for status if not provided
        if ( $percentage === null ) {
            $percentage = self::get_status_percentage( $status );
        }

        // Get default message if not provided
        if ( empty( $message ) ) {
            $message = self::get_status_message( $status );
        }

        $progress = array(
            'status' => $status,
            'percentage' => $percentage,
            'message' => $message,
            'started_at' => self::get_start_time(),
            'stage_started_at' => current_time( 'c' ),
            'last_updated' => current_time( 'c' )
        );

        return set_transient( self::TRANSIENT_KEY, $progress, self::TRANSIENT_EXPIRATION );
    }

    /**
     * Update progress fields
     *
     * @param array $fields Fields to update
     * @return bool True on success
     */
    public static function update( $fields ) {
        $progress = self::get();

        foreach ( $fields as $key => $value ) {
            $progress[ $key ] = $value;
        }

        $progress['last_updated'] = current_time( 'c' );

        return set_transient( self::TRANSIENT_KEY, $progress, self::TRANSIENT_EXPIRATION );
    }

    /**
     * Clear progress (reset to idle)
     *
     * @return bool True on success
     */
    public static function clear() {
        return delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Start new update process
     *
     * @param string $initial_message Initial message
     * @return bool True on success
     */
    public static function start( $initial_message = 'Starting update process...' ) {
        return self::set( 'checking', 0, $initial_message );
    }

    /**
     * Set status to checking
     *
     * @param string $message Status message
     * @return bool True on success
     */
    public static function set_checking( $message = 'Checking for updates...' ) {
        return self::set( 'checking', 10, $message );
    }

    /**
     * Set status to downloading
     *
     * @param int $percentage Download percentage
     * @param string $message Status message
     * @return bool True on success
     */
    public static function set_downloading( $percentage = 30, $message = 'Downloading update package...' ) {
        // Clamp percentage between 20-40 for download phase
        $percentage = max( 20, min( 40, $percentage ) );
        return self::set( 'downloading', $percentage, $message );
    }

    /**
     * Set status to backing up
     *
     * @param string $message Status message
     * @return bool True on success
     */
    public static function set_backing_up( $message = 'Creating backup...' ) {
        return self::set( 'backing_up', 50, $message );
    }

    /**
     * Set status to installing
     *
     * @param int $percentage Install percentage
     * @param string $message Status message
     * @return bool True on success
     */
    public static function set_installing( $percentage = 70, $message = 'Installing update...' ) {
        // Clamp percentage between 60-80 for install phase
        $percentage = max( 60, min( 80, $percentage ) );
        return self::set( 'installing', $percentage, $message );
    }

    /**
     * Set status to verifying
     *
     * @param string $message Status message
     * @return bool True on success
     */
    public static function set_verifying( $message = 'Verifying installation...' ) {
        return self::set( 'verifying', 90, $message );
    }

    /**
     * Set status to complete
     *
     * @param string $message Completion message
     * @return bool True on success
     */
    public static function set_complete( $message = 'Update completed successfully!' ) {
        $result = self::set( 'complete', 100, $message );

        // Clear progress after a short delay
        wp_schedule_single_event( time() + 10, 'cuft_clear_update_progress' );

        return $result;
    }

    /**
     * Set status to failed
     *
     * @param string $error Error message
     * @return bool True on success
     */
    public static function set_failed( $error = 'Update failed' ) {
        return self::set( 'failed', 0, $error );
    }

    /**
     * Set status to rolling back
     *
     * @param string $message Rollback message
     * @return bool True on success
     */
    public static function set_rolling_back( $message = 'Rolling back to previous version...' ) {
        return self::set( 'rolling_back', 0, $message );
    }

    /**
     * Check if update is in progress
     *
     * @return bool True if update is in progress
     */
    public static function is_in_progress() {
        $progress = self::get();
        return ! in_array( $progress['status'], array( 'idle', 'complete', 'failed' ) );
    }

    /**
     * Get elapsed time since start
     *
     * @return int Elapsed seconds
     */
    public static function get_elapsed_time() {
        $progress = self::get();

        if ( empty( $progress['started_at'] ) ) {
            return 0;
        }

        $start = strtotime( $progress['started_at'] );
        return time() - $start;
    }

    /**
     * Get estimated time remaining
     *
     * @return int|null Estimated seconds remaining or null if cannot estimate
     */
    public static function get_estimated_time_remaining() {
        $progress = self::get();

        if ( $progress['percentage'] <= 0 || $progress['percentage'] >= 100 ) {
            return null;
        }

        $elapsed = self::get_elapsed_time();
        if ( $elapsed <= 0 ) {
            return null;
        }

        // Simple linear estimation
        $total_estimated = ( $elapsed / $progress['percentage'] ) * 100;
        $remaining = $total_estimated - $elapsed;

        return max( 0, round( $remaining ) );
    }

    /**
     * Get default progress structure
     *
     * @return array Default progress
     */
    private static function get_default_progress() {
        return array(
            'status' => 'idle',
            'percentage' => 0,
            'message' => 'No update in progress',
            'started_at' => null,
            'stage_started_at' => null,
            'last_updated' => current_time( 'c' )
        );
    }

    /**
     * Validate progress data
     *
     * @param mixed $progress Progress to validate
     * @return array Validated progress
     */
    private static function validate_progress( $progress ) {
        if ( ! is_array( $progress ) ) {
            return self::get_default_progress();
        }

        $defaults = self::get_default_progress();
        $progress = wp_parse_args( $progress, $defaults );

        // Validate status
        if ( ! in_array( $progress['status'], self::VALID_STATUSES ) ) {
            $progress['status'] = 'idle';
        }

        // Validate percentage
        $progress['percentage'] = max( 0, min( 100, intval( $progress['percentage'] ) ) );

        // Validate message
        $progress['message'] = substr( sanitize_text_field( $progress['message'] ), 0, 255 );

        return $progress;
    }

    /**
     * Get start time for update process
     *
     * @return string Start time or current time
     */
    private static function get_start_time() {
        $progress = self::get();

        if ( ! empty( $progress['started_at'] ) && $progress['status'] !== 'idle' ) {
            return $progress['started_at'];
        }

        return current_time( 'c' );
    }

    /**
     * Get default percentage for status
     *
     * @param string $status Status to get percentage for
     * @return int Default percentage
     */
    private static function get_status_percentage( $status ) {
        return isset( self::STATUS_PERCENTAGES[ $status ] ) ?
               self::STATUS_PERCENTAGES[ $status ] : 0;
    }

    /**
     * Get default message for status
     *
     * @param string $status Status to get message for
     * @return string Default message
     */
    private static function get_status_message( $status ) {
        $messages = array(
            'idle' => 'No update in progress',
            'checking' => 'Checking for updates...',
            'downloading' => 'Downloading update package...',
            'backing_up' => 'Creating backup...',
            'installing' => 'Installing update...',
            'verifying' => 'Verifying installation...',
            'complete' => 'Update completed successfully!',
            'failed' => 'Update failed',
            'rolling_back' => 'Rolling back to previous version...'
        );

        return isset( $messages[ $status ] ) ? $messages[ $status ] : 'Processing...';
    }

    /**
     * Get progress for display
     *
     * @return array Formatted progress for display
     */
    public static function get_display_progress() {
        $progress = self::get();

        // Add elapsed time
        $elapsed = self::get_elapsed_time();
        if ( $elapsed > 0 ) {
            $progress['elapsed_time'] = $elapsed;
            $progress['elapsed_human'] = human_time_diff( time() - $elapsed );
        }

        // Add estimated time remaining
        $remaining = self::get_estimated_time_remaining();
        if ( $remaining !== null ) {
            $progress['estimated_remaining'] = $remaining;
            $progress['remaining_human'] = human_time_diff( time(), time() + $remaining );
        }

        // Add progress bar class
        if ( $progress['status'] === 'failed' ) {
            $progress['progress_class'] = 'error';
        } elseif ( $progress['status'] === 'complete' ) {
            $progress['progress_class'] = 'success';
        } elseif ( self::is_in_progress() ) {
            $progress['progress_class'] = 'active';
        } else {
            $progress['progress_class'] = 'idle';
        }

        return $progress;
    }
}

// Hook to clear progress
add_action( 'cuft_clear_update_progress', array( 'CUFT_Update_Progress', 'clear' ) );