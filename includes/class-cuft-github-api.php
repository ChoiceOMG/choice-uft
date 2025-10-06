<?php
/**
 * GitHub API Service
 *
 * Service wrapper for GitHub API interactions using the GitHubRelease model.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT GitHub API Service
 *
 * Provides a service interface for GitHub API operations.
 */
class CUFT_GitHub_API {

    /**
     * Get latest release
     *
     * @param bool $force_refresh Force API call bypassing cache
     * @return CUFT_GitHub_Release|null Release object or null on failure
     */
    public static function get_latest_release( $force_refresh = false ) {
        $include_prereleases = CUFT_Update_Configuration::includes_prereleases();
        return CUFT_GitHub_Release::fetch_latest( $include_prereleases, $force_refresh );
    }

    /**
     * Get specific release by version
     *
     * @param string $version Version to fetch
     * @param bool $force_refresh Force API call
     * @return CUFT_GitHub_Release|null Release object or null on failure
     */
    public static function get_release( $version, $force_refresh = false ) {
        return CUFT_GitHub_Release::fetch_version( $version, $force_refresh );
    }

    /**
     * Check if update is available
     *
     * @param bool $force_refresh Force API call
     * @return array Update information
     */
    public static function check_for_updates( $force_refresh = false ) {
        $current_version = CUFT_VERSION;
        $latest_release = self::get_latest_release( $force_refresh );

        if ( ! $latest_release ) {
            return array(
                'success' => false,
                'error' => 'Failed to fetch latest release from GitHub',
                'current_version' => $current_version
            );
        }

        $latest_version = $latest_release->get_version();
        $update_available = version_compare( $current_version, $latest_version, '<' );

        return array(
            'success' => true,
            'current_version' => $current_version,
            'latest_version' => $latest_version,
            'update_available' => $update_available,
            'download_url' => $latest_release->get_download_url(),
            'changelog' => $latest_release->get_changelog(),
            'file_size' => $latest_release->get_file_size(),
            'published_date' => $latest_release->get_published_date(),
            'is_prerelease' => $latest_release->is_prerelease()
        );
    }

    /**
     * Get download URL for a version
     *
     * @param string $version Version to download
     * @return string|null Download URL or null on failure
     */
    public static function get_download_url( $version ) {
        $release = self::get_release( $version );

        if ( ! $release ) {
            return null;
        }

        return $release->get_download_url();
    }

    /**
     * Verify download URL is accessible
     *
     * @param string $url URL to verify
     * @return bool True if accessible
     */
    public static function verify_download_url( $url ) {
        $response = wp_remote_head( $url, array(
            'timeout' => 10,
            'redirection' => 5
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        return $code === 200;
    }

    /**
     * Clear GitHub API cache
     *
     * @return bool True on success
     */
    public static function clear_cache() {
        $result = CUFT_GitHub_Release::clear_cache();

        // Also clear related transients
        delete_transient( 'cuft_github_version' );
        delete_transient( 'cuft_github_changelog' );

        return $result;
    }

    /**
     * Test GitHub API connectivity
     *
     * @return array Test results
     */
    public static function test_connection() {
        $start_time = microtime( true );

        $release = self::get_latest_release( true );

        $elapsed = microtime( true ) - $start_time;

        if ( ! $release ) {
            return array(
                'success' => false,
                'error' => 'Failed to connect to GitHub API',
                'elapsed_time' => round( $elapsed, 3 )
            );
        }

        return array(
            'success' => true,
            'latest_version' => $release->get_version(),
            'elapsed_time' => round( $elapsed, 3 ),
            'download_url' => $release->get_download_url(),
            'url_accessible' => self::verify_download_url( $release->get_download_url() )
        );
    }

    /**
     * Get API rate limit status
     *
     * @return array|null Rate limit info or null if unavailable
     */
    public static function get_rate_limit() {
        $api_url = 'https://api.github.com/rate_limit';

        $config = CUFT_Update_Configuration::get();
        $headers = array(
            'Accept' => 'application/vnd.github.v3+json'
        );

        // Add token if available
        if ( ! empty( $config['github_token'] ) ) {
            $token = CUFT_Update_Configuration::get_github_token();
            if ( $token ) {
                $headers['Authorization'] = 'token ' . $token;
            }
        }

        $response = wp_remote_get( $api_url, array(
            'timeout' => 10,
            'headers' => $headers
        ) );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) || ! isset( $data['rate'] ) ) {
            return null;
        }

        return array(
            'limit' => isset( $data['rate']['limit'] ) ? intval( $data['rate']['limit'] ) : 0,
            'remaining' => isset( $data['rate']['remaining'] ) ? intval( $data['rate']['remaining'] ) : 0,
            'reset' => isset( $data['rate']['reset'] ) ? intval( $data['rate']['reset'] ) : 0,
            'reset_human' => isset( $data['rate']['reset'] ) ?
                            human_time_diff( time(), $data['rate']['reset'] ) : 'Unknown'
        );
    }

    /**
     * Check if rate limit is exceeded
     *
     * @return bool True if exceeded
     */
    public static function is_rate_limited() {
        $rate_limit = self::get_rate_limit();

        if ( ! $rate_limit ) {
            return false;
        }

        return $rate_limit['remaining'] <= 0;
    }

    /**
     * Download file from URL
     *
     * @param string $url URL to download
     * @param string $destination Destination file path
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public static function download_file( $url, $destination ) {
        // Use WordPress download_url function
        $temp_file = download_url( $url, 300 );

        if ( is_wp_error( $temp_file ) ) {
            return $temp_file;
        }

        // Move to destination
        if ( ! @rename( $temp_file, $destination ) ) {
            // Try copy/delete if rename fails
            if ( ! @copy( $temp_file, $destination ) ) {
                @unlink( $temp_file );
                return new WP_Error( 'download_failed', 'Could not move downloaded file to destination' );
            }
            @unlink( $temp_file );
        }

        return true;
    }

    /**
     * Verify downloaded file integrity
     *
     * @param string $file_path Path to downloaded file
     * @param int $expected_size Expected file size (bytes)
     * @return bool True if valid
     */
    public static function verify_download( $file_path, $expected_size = null ) {
        if ( ! file_exists( $file_path ) ) {
            return false;
        }

        // Check if file is readable
        if ( ! is_readable( $file_path ) ) {
            return false;
        }

        // Check file size if provided
        if ( $expected_size !== null ) {
            $actual_size = filesize( $file_path );
            if ( $actual_size !== $expected_size ) {
                return false;
            }
        }

        // Check if file is a valid ZIP archive
        if ( class_exists( 'ZipArchive' ) ) {
            $zip = new ZipArchive();
            $result = $zip->open( $file_path, ZipArchive::CHECKCONS );

            if ( $result !== true ) {
                return false;
            }

            $zip->close();
        }

        return true;
    }

    /**
     * Get repository information
     *
     * @return array Repository information
     */
    public static function get_repository_info() {
        return array(
            'owner' => CUFT_GitHub_Release::GITHUB_OWNER,
            'repo' => CUFT_GitHub_Release::GITHUB_REPO,
            'url' => sprintf(
                'https://github.com/%s/%s',
                CUFT_GitHub_Release::GITHUB_OWNER,
                CUFT_GitHub_Release::GITHUB_REPO
            ),
            'releases_url' => sprintf(
                'https://github.com/%s/%s/releases',
                CUFT_GitHub_Release::GITHUB_OWNER,
                CUFT_GitHub_Release::GITHUB_REPO
            ),
            'api_url' => sprintf(
                'https://api.github.com/repos/%s/%s',
                CUFT_GitHub_Release::GITHUB_OWNER,
                CUFT_GitHub_Release::GITHUB_REPO
            )
        );
    }
}