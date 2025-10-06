<?php
/**
 * GitHubRelease Model
 *
 * Represents a release fetched from GitHub API.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CUFT GitHubRelease Model
 *
 * Handles GitHub release data fetching and caching.
 */
class CUFT_GitHub_Release {

    /**
     * GitHub repository owner
     */
    const GITHUB_OWNER = 'ChoiceOMG';

    /**
     * GitHub repository name
     */
    const GITHUB_REPO = 'choice-uft';

    /**
     * Transient key for caching releases
     */
    const TRANSIENT_KEY = 'cuft_github_releases';

    /**
     * Transient expiration time (12 hours in seconds)
     */
    const TRANSIENT_EXPIRATION = 43200;

    /**
     * Release data
     *
     * @var array
     */
    private $data = array();

    /**
     * Constructor
     *
     * @param array $data Release data
     */
    public function __construct( $data = array() ) {
        $this->data = $this->validate_data( $data );
    }

    /**
     * Get release property
     *
     * @param string $property Property name
     * @return mixed Property value or null
     */
    public function get( $property ) {
        return isset( $this->data[ $property ] ) ? $this->data[ $property ] : null;
    }

    /**
     * Get version
     *
     * @return string Version without 'v' prefix
     */
    public function get_version() {
        return ltrim( $this->get( 'version' ), 'v' );
    }

    /**
     * Get download URL
     *
     * @return string|null Download URL
     */
    public function get_download_url() {
        return $this->get( 'download_url' );
    }

    /**
     * Get changelog
     *
     * @return string Changelog text
     */
    public function get_changelog() {
        return $this->get( 'changelog' ) ?: '';
    }

    /**
     * Get file size
     *
     * @return string Formatted file size
     */
    public function get_file_size() {
        $size = $this->get( 'file_size' );
        return $size ? size_format( $size ) : 'Unknown';
    }

    /**
     * Get published date
     *
     * @return string Published date
     */
    public function get_published_date() {
        return $this->get( 'published_at' );
    }

    /**
     * Check if this is a prerelease
     *
     * @return bool True if prerelease
     */
    public function is_prerelease() {
        return (bool) $this->get( 'is_prerelease' );
    }

    /**
     * Check if this is a draft
     *
     * @return bool True if draft
     */
    public function is_draft() {
        return (bool) $this->get( 'is_draft' );
    }

    /**
     * To array
     *
     * @return array Release data
     */
    public function to_array() {
        return $this->data;
    }

    /**
     * Fetch latest release from GitHub
     *
     * @param bool $include_prereleases Include prereleases
     * @param bool $force_refresh Force API call (bypass cache)
     * @return CUFT_GitHub_Release|null Release object or null on failure
     */
    public static function fetch_latest( $include_prereleases = false, $force_refresh = false ) {
        // Check cache first
        if ( ! $force_refresh ) {
            $cached = self::get_cached_releases();
            if ( ! empty( $cached ) ) {
                return self::find_latest_from_releases( $cached, $include_prereleases );
            }
        }

        // Fetch from API
        $releases = self::fetch_from_api();
        if ( empty( $releases ) ) {
            return null;
        }

        // Cache the releases
        self::cache_releases( $releases );

        // Find latest
        return self::find_latest_from_releases( $releases, $include_prereleases );
    }

    /**
     * Fetch specific version from GitHub
     *
     * @param string $version Version to fetch (with or without 'v' prefix)
     * @param bool $force_refresh Force API call
     * @return CUFT_GitHub_Release|null Release object or null on failure
     */
    public static function fetch_version( $version, $force_refresh = false ) {
        // Normalize version (ensure 'v' prefix)
        $version = 'v' . ltrim( $version, 'v' );

        // Try cache first
        if ( ! $force_refresh ) {
            $cached = self::get_cached_releases();
            foreach ( $cached as $release ) {
                if ( $release->get( 'version' ) === $version ) {
                    return $release;
                }
            }
        }

        // Fetch specific release from API
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases/tags/%s',
            self::GITHUB_OWNER,
            self::GITHUB_REPO,
            $version
        );

        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
            'headers' => self::get_api_headers()
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

        if ( ! is_array( $data ) ) {
            return null;
        }

        return self::parse_release_data( $data );
    }

    /**
     * Fetch releases from GitHub API
     *
     * @return array Array of CUFT_GitHub_Release objects
     */
    private static function fetch_from_api() {
        $api_url = sprintf(
            'https://api.github.com/repos/%s/%s/releases',
            self::GITHUB_OWNER,
            self::GITHUB_REPO
        );

        $response = wp_remote_get( $api_url, array(
            'timeout' => 30,
            'headers' => self::get_api_headers()
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( 'CUFT: GitHub API error: ' . $response->get_error_message() );
            return array();
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( 'CUFT: GitHub API returned code: ' . $code );
            return array();
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) ) {
            return array();
        }

        $releases = array();
        foreach ( $data as $release_data ) {
            if ( is_array( $release_data ) ) {
                $release = self::parse_release_data( $release_data );
                if ( $release && ! $release->is_draft() ) {
                    $releases[] = $release;
                }
            }
        }

        return $releases;
    }

    /**
     * Parse release data from API response
     *
     * @param array $data API response data
     * @return CUFT_GitHub_Release|null Release object or null
     */
    private static function parse_release_data( $data ) {
        if ( empty( $data['tag_name'] ) ) {
            return null;
        }

        // Find the correct asset (choice-uft-vX.X.X.zip)
        $download_url = null;
        $file_size = null;

        if ( ! empty( $data['assets'] ) && is_array( $data['assets'] ) ) {
            foreach ( $data['assets'] as $asset ) {
                if ( isset( $asset['name'] ) &&
                     preg_match( '/^choice-uft-v[\d\.]+\.zip$/', $asset['name'] ) &&
                     ! empty( $asset['browser_download_url'] ) ) {
                    $download_url = $asset['browser_download_url'];
                    $file_size = isset( $asset['size'] ) ? intval( $asset['size'] ) : null;
                    break;
                }
            }
        }

        // If no asset found, construct expected URL
        if ( ! $download_url ) {
            $version = ltrim( $data['tag_name'], 'v' );
            $download_url = sprintf(
                'https://github.com/%s/%s/releases/download/v%s/choice-uft-v%s.zip',
                self::GITHUB_OWNER,
                self::GITHUB_REPO,
                $version,
                $version
            );
        }

        return new self( array(
            'version' => $data['tag_name'],
            'download_url' => $download_url,
            'published_at' => isset( $data['published_at'] ) ? $data['published_at'] : '',
            'changelog' => isset( $data['body'] ) ? $data['body'] : '',
            'file_size' => $file_size,
            'is_prerelease' => ! empty( $data['prerelease'] ),
            'is_draft' => ! empty( $data['draft'] )
        ) );
    }

    /**
     * Find latest release from array
     *
     * @param array $releases Array of CUFT_GitHub_Release objects
     * @param bool $include_prereleases Include prereleases
     * @return CUFT_GitHub_Release|null Latest release or null
     */
    private static function find_latest_from_releases( $releases, $include_prereleases ) {
        if ( empty( $releases ) ) {
            return null;
        }

        $latest = null;
        $latest_version = '0.0.0';

        foreach ( $releases as $release ) {
            // Skip prereleases if not included
            if ( ! $include_prereleases && $release->is_prerelease() ) {
                continue;
            }

            // Skip drafts
            if ( $release->is_draft() ) {
                continue;
            }

            $version = $release->get_version();
            if ( version_compare( $version, $latest_version, '>' ) ) {
                $latest = $release;
                $latest_version = $version;
            }
        }

        return $latest;
    }

    /**
     * Get cached releases
     *
     * @return array Array of CUFT_GitHub_Release objects
     */
    private static function get_cached_releases() {
        $cached = get_transient( self::TRANSIENT_KEY );

        if ( ! is_array( $cached ) ) {
            return array();
        }

        $releases = array();
        foreach ( $cached as $data ) {
            if ( is_array( $data ) ) {
                $releases[] = new self( $data );
            }
        }

        return $releases;
    }

    /**
     * Cache releases
     *
     * @param array $releases Array of CUFT_GitHub_Release objects
     * @return bool True on success
     */
    private static function cache_releases( $releases ) {
        $data = array();
        foreach ( $releases as $release ) {
            if ( $release instanceof self ) {
                $data[] = $release->to_array();
            }
        }

        return set_transient( self::TRANSIENT_KEY, $data, self::TRANSIENT_EXPIRATION );
    }

    /**
     * Clear release cache
     *
     * @return bool True on success
     */
    public static function clear_cache() {
        return delete_transient( self::TRANSIENT_KEY );
    }

    /**
     * Get API headers
     *
     * @return array Headers for API request
     */
    private static function get_api_headers() {
        $headers = array(
            'Accept' => 'application/vnd.github.v3+json'
        );

        // Add GitHub token if available
        $config = get_option( 'cuft_update_config', array() );
        if ( ! empty( $config['github_token'] ) ) {
            $headers['Authorization'] = 'token ' . $config['github_token'];
        }

        return $headers;
    }

    /**
     * Validate release data
     *
     * @param mixed $data Data to validate
     * @return array Validated data
     */
    private function validate_data( $data ) {
        if ( ! is_array( $data ) ) {
            $data = array();
        }

        $defaults = array(
            'version' => '',
            'download_url' => '',
            'published_at' => '',
            'changelog' => '',
            'file_size' => null,
            'is_prerelease' => false,
            'is_draft' => false
        );

        $data = wp_parse_args( $data, $defaults );

        // Validate version format
        if ( ! empty( $data['version'] ) && ! preg_match( '/^v?\d+\.\d+\.\d+/', $data['version'] ) ) {
            $data['version'] = '';
        }

        // Validate URL
        if ( ! empty( $data['download_url'] ) ) {
            $data['download_url'] = esc_url_raw( $data['download_url'] );

            // Ensure HTTPS
            if ( strpos( $data['download_url'], 'https://' ) !== 0 ) {
                $data['download_url'] = '';
            }
        }

        // Validate file size
        if ( $data['file_size'] !== null ) {
            $data['file_size'] = max( 0, intval( $data['file_size'] ) );
        }

        // Validate booleans
        $data['is_prerelease'] = (bool) $data['is_prerelease'];
        $data['is_draft'] = (bool) $data['is_draft'];

        // Sanitize changelog
        $data['changelog'] = wp_kses_post( $data['changelog'] );

        return $data;
    }
}