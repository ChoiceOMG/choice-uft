<?php
/**
 * GitHub-based plugin updater
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CUFT_GitHub_Updater {
    
    private $plugin_file;
    private $plugin_basename;
    private $version;
    private $github_username;
    private $github_repo;
    private $plugin_slug;
    
    /**
     * Constructor
     */
    public function __construct( $plugin_file, $version, $github_username, $github_repo ) {
        // Validate required parameters
        if ( empty( $plugin_file ) || empty( $version ) || empty( $github_username ) || empty( $github_repo ) ) {
            return;
        }
        
        // Check if required WordPress functions exist
        if ( ! function_exists( 'plugin_basename' ) || ! function_exists( 'add_filter' ) ) {
            return;
        }
        
        $this->plugin_file = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->version = $version;
        $this->github_username = $github_username;
        $this->github_repo = $github_repo;
        $this->plugin_slug = dirname( $this->plugin_basename );
        
        // Only add hooks if we're in a proper WordPress environment
        if ( did_action( 'init' ) || doing_action( 'init' ) || did_action( 'plugins_loaded' ) ) {
            add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_update' ) );
            add_filter( 'plugins_api', array( $this, 'plugin_info' ), 20, 3 );
            add_filter( 'upgrader_pre_download', array( $this, 'download_package' ), 10, 3 );
            add_action( 'upgrader_process_complete', array( $this, 'purge_cache' ), 10, 2 );
        }
    }
    
    /**
     * Check for plugin updates
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }
        
        // Ensure WordPress HTTP functions are available
        if ( ! function_exists( 'wp_remote_get' ) ) {
            return $transient;
        }
        
        $remote_version = $this->get_remote_version();
        
        if ( version_compare( $this->version, $remote_version, '<' ) ) {
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->new_version = $remote_version;
            $obj->url = $this->get_github_repo_url();
            $obj->package = $this->get_download_url( $remote_version );
            $obj->icons = array(
                '1x' => CUFT_URL . '/assets/icon.svg',
                '2x' => CUFT_URL . '/assets/icon.svg',
            );
            $obj->banners = array();
            $obj->banners_rtl = array();
            $obj->tested = '6.8';
            $obj->requires_php = '7.4';
            $obj->compatibility = new stdClass();
            $obj->plugin = $this->plugin_basename;
            $obj->id = $this->plugin_basename;

            $transient->response[ $this->plugin_basename ] = $obj;
        }
        
        return $transient;
    }
    
    /**
     * Get plugin information for the update screen
     */
    public function plugin_info( $result, $action, $args ) {
        if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
            return $result;
        }

        $remote_version = $this->get_remote_version();
        $changelog = $this->get_changelog();

        $info = new stdClass();
        $info->name = 'Choice Universal Form Tracker';
        $info->slug = $this->plugin_slug;
        $info->version = $remote_version;
        $info->author = '<a href="https://choice.marketing">Choice OMG</a>';
        $info->author_profile = 'https://choice.marketing';
        $info->contributors = array( 'choiceomg' );
        $info->homepage = $this->get_github_repo_url();
        $info->short_description = 'Universal form tracking for WordPress - supports multiple form frameworks and tracks submissions via Google Tag Manager\'s dataLayer.';
        $info->sections = array(
            'description' => $this->get_description(),
            'installation' => $this->get_installation_instructions(),
            'changelog' => $changelog,
        );
        $info->download_link = $this->get_download_url( $remote_version );
        $info->trunk = $this->get_download_url( $remote_version );
        $info->requires = '5.0';
        $info->tested = '6.8';
        $info->requires_php = '7.4';
        $info->last_updated = $this->get_last_updated();
        $info->icons = array(
            '1x' => CUFT_URL . '/assets/icon.svg',
            '2x' => CUFT_URL . '/assets/icon.svg',
        );
        $info->banners = array();
        $info->banners_rtl = array();

        return $info;
    }
    
    /**
     * Download the plugin package
     */
    public function download_package( $reply, $package, $upgrader ) {
        if ( strpos( $package, 'github.com/' . $this->github_username . '/' . $this->github_repo ) !== false ) {
            $args = array(
                'timeout' => 300
            );
            
            $download = wp_remote_get( $package, $args );
            
            if ( is_wp_error( $download ) ) {
                return $download;
            }
            
            // Check if wp_remote_response_code exists (WordPress 2.7+)
            if ( ! function_exists( 'wp_remote_response_code' ) ) {
                // Fallback for older WordPress versions
                $code = isset( $download['response']['code'] ) ? $download['response']['code'] : 200;
            } else {
                $code = wp_remote_response_code( $download );
            }
            if ( $code !== 200 ) {
                return new WP_Error( 'download_failed', 'Download failed with HTTP code: ' . $code );
            }
            
            $body = wp_remote_retrieve_body( $download );
            $temp_file = download_url( $package );
            
            if ( is_wp_error( $temp_file ) ) {
                // Try manual download
                $temp_file = wp_tempnam( $package );
                if ( ! $temp_file ) {
                    return new WP_Error( 'temp_file_failed', 'Could not create temporary file.' );
                }
                
                file_put_contents( $temp_file, $body );
            }
            
            return $temp_file;
        }
        
        return $reply;
    }
    
    /**
     * Purge update cache
     */
    public function purge_cache( $upgrader, $options ) {
        if ( isset( $options['plugins'] ) && in_array( $this->plugin_basename, $options['plugins'] ) ) {
            delete_transient( 'cuft_github_version' );
            delete_transient( 'cuft_github_changelog' );
        }
    }
    
    /**
     * Force check for updates (manual trigger)
     */
    public function force_check() {
        delete_transient( 'cuft_github_version' );
        delete_transient( 'cuft_github_changelog' );
        return $this->get_remote_version();
    }
    
    /**
     * Get remote version from GitHub
     */
    private function get_remote_version() {
        // Ensure required WordPress functions are available
        if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_body' ) ) {
            return $this->version;
        }
        $version = get_transient( 'cuft_github_version' );
        
        if ( false === $version ) {
            $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
            $args = array(
                'timeout' => 30
            );
            
            $response = wp_remote_get( $api_url, $args );
            
            if ( is_wp_error( $response ) ) {
                if ( class_exists( 'CUFT_Logger' ) ) {
                    CUFT_Logger::log( 'GitHub API error: ' . $response->get_error_message(), 'error' );
                }
                return $this->version;
            }
            
            // Check if wp_remote_response_code exists (WordPress 2.7+)
            if ( ! function_exists( 'wp_remote_response_code' ) ) {
                // Fallback for older WordPress versions
                $code = isset( $response['response']['code'] ) ? $response['response']['code'] : 200;
            } else {
                $code = wp_remote_response_code( $response );
            }
            if ( $code !== 200 ) {
                CUFT_Logger::log( "GitHub API returned code: $code", 'error' );
                return $this->version;
            }
            
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );
            
            if ( isset( $data['tag_name'] ) ) {
                $version = ltrim( $data['tag_name'], 'v' );
                set_transient( 'cuft_github_version', $version, HOUR_IN_SECONDS );
            } else {
                $version = $this->version;
            }
        }
        
        return $version;
    }
    
    /**
     * Get download URL for a specific version
     */
    private function get_download_url( $version ) {
        // Try to get the release asset URL first
        $asset_url = $this->get_release_asset_url( $version );
        if ( $asset_url ) {
            return $asset_url;
        }

        // Fallback to archive URL if no release asset found
        return "https://github.com/{$this->github_username}/{$this->github_repo}/archive/refs/tags/v{$version}.zip";
    }

    /**
     * Get release asset URL from GitHub API
     */
    private function get_release_asset_url( $version ) {
        // Check transient cache first
        $cache_key = 'cuft_asset_url_' . $version;
        $cached_url = get_transient( $cache_key );
        if ( false !== $cached_url ) {
            return $cached_url;
        }

        // Ensure required WordPress functions are available
        if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_body' ) ) {
            return false;
        }

        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/tags/v{$version}";
        $args = array(
            'timeout' => 30
        );

        $response = wp_remote_get( $api_url, $args );

        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'CUFT_Logger' ) ) {
                CUFT_Logger::log( 'Failed to fetch release asset: ' . $response->get_error_message(), 'error' );
            }
            return false;
        }

        // Check response code
        if ( ! function_exists( 'wp_remote_response_code' ) ) {
            $code = isset( $response['response']['code'] ) ? $response['response']['code'] : 200;
        } else {
            $code = wp_remote_response_code( $response );
        }

        if ( $code !== 200 ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        // Look for a zip file in assets
        if ( isset( $data['assets'] ) && is_array( $data['assets'] ) ) {
            foreach ( $data['assets'] as $asset ) {
                // Look for the plugin zip file
                if ( isset( $asset['name'] ) && isset( $asset['browser_download_url'] ) ) {
                    // Match files like choice-uft-v3.8.3.zip or choice-uft.zip
                    if ( preg_match( '/choice-uft.*\.zip$/i', $asset['name'] ) ) {
                        $download_url = $asset['browser_download_url'];
                        // Cache for 1 hour
                        set_transient( $cache_key, $download_url, HOUR_IN_SECONDS );
                        return $download_url;
                    }
                }
            }
        }

        // No asset found
        return false;
    }
    
    /**
     * Get GitHub repository URL
     */
    private function get_github_repo_url() {
        return "https://github.com/{$this->github_username}/{$this->github_repo}";
    }
    
    /**
     * Get changelog from GitHub releases
     */
    private function get_changelog() {
        $changelog = get_transient( 'cuft_github_changelog' );
        
        if ( false === $changelog ) {
            $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases";
            $args = array(
                'timeout' => 30
            );
            
            $response = wp_remote_get( $api_url, $args );
            
            if ( is_wp_error( $response ) ) {
                return 'Could not retrieve changelog from GitHub.';
            }
            
            // Check if wp_remote_response_code exists (WordPress 2.7+)
            if ( ! function_exists( 'wp_remote_response_code' ) ) {
                // Fallback for older WordPress versions
                $code = isset( $response['response']['code'] ) ? $response['response']['code'] : 200;
            } else {
                $code = wp_remote_response_code( $response );
            }
            if ( $code !== 200 ) {
                return 'Could not retrieve changelog from GitHub.';
            }
            
            $body = wp_remote_retrieve_body( $response );
            $releases = json_decode( $body, true );
            
            if ( ! is_array( $releases ) ) {
                return 'Could not parse changelog from GitHub.';
            }
            
            $changelog = '';
            foreach ( $releases as $release ) {
                if ( isset( $release['tag_name'] ) && isset( $release['body'] ) ) {
                    $version = ltrim( $release['tag_name'], 'v' );
                    $changelog .= "<h4>Version {$version}</h4>\n";
                    $changelog .= wp_kses_post( $release['body'] ) . "\n\n";
                }
            }
            
            if ( empty( $changelog ) ) {
                $changelog = 'No changelog available.';
            }
            
            set_transient( 'cuft_github_changelog', $changelog, HOUR_IN_SECONDS );
        }
        
        return $changelog;
    }
    
    /**
     * Get plugin description
     */
    private function get_description() {
        return 'Choice Universal Form Tracker is a comprehensive solution for tracking form submissions across multiple WordPress form frameworks. Whether you\'re using Elementor Pro, Contact Form 7, Gravity Forms, Ninja Forms, or Avada/Fusion forms, this plugin automatically detects and tracks all form submissions with detailed analytics data.

<h3>Key Features</h3>
<ul>
<li><strong>Universal Framework Support</strong> - Automatically detects and supports Avada/Fusion Forms, Elementor Pro Forms, Contact Form 7, Ninja Forms, and Gravity Forms</li>
<li><strong>Advanced Tracking Capabilities</strong> - Form submission tracking with user email and phone data, phone number click tracking, and UTM campaign parameter tracking</li>
<li><strong>Google Tag Manager Integration</strong> - Optional GTM container injection with structured dataLayer events</li>
<li><strong>Marketing Attribution</strong> - Captures and stores UTM parameters for up to 30 days with form submission association</li>
<li><strong>Developer-Friendly</strong> - Vanilla JavaScript, comprehensive debug logging, and WordPress coding standards compliant</li>
</ul>';
    }
    
    /**
     * Get installation instructions
     */
    private function get_installation_instructions() {
        return '<ol>
<li>Upload the plugin folder to <code>/wp-content/plugins/</code></li>
<li>Activate the plugin through the \'Plugins\' menu in WordPress</li>
<li>Configure GTM container ID in Settings > Universal Form Tracker</li>
</ol>';
    }
    
    /**
     * Get last updated date
     */
    private function get_last_updated() {
        $api_url = "https://api.github.com/repos/{$this->github_username}/{$this->github_repo}/releases/latest";
        $args = array(
            'timeout' => 30
        );
        
        $response = wp_remote_get( $api_url, $args );
        
        if ( is_wp_error( $response ) || ! function_exists( 'wp_remote_retrieve_body' ) ) {
            return date( 'Y-m-d' );
        }
        
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );
        
        if ( isset( $data['published_at'] ) ) {
            return date( 'Y-m-d', strtotime( $data['published_at'] ) );
        }
        
        return date( 'Y-m-d' );
    }
    
    /**
     * Check if updates are enabled
     */
    public static function updates_enabled() {
        return get_option( 'cuft_github_updates_enabled', true );
    }
    
    /**
     * Enable or disable updates
     */
    public static function set_updates_enabled( $enabled ) {
        update_option( 'cuft_github_updates_enabled', (bool) $enabled );
    }
}
