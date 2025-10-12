<?php
/**
 * Plugin Information Provider for WordPress Update Modal
 *
 * Implements plugins_api filter to provide custom plugin information
 * for the "View Details" modal in WordPress admin.
 *
 * @package Choice_UFT
 * @subpackage Update
 * @since 3.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CUFT_Plugin_Info
 *
 * Handles plugin information retrieval from GitHub and caching for WordPress update modals.
 */
class CUFT_Plugin_Info {

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'choice-uft';

	/**
	 * GitHub repository owner
	 *
	 * @var string
	 */
	const GITHUB_OWNER = 'ChoiceOMG';

	/**
	 * GitHub repository name
	 *
	 * @var string
	 */
	const GITHUB_REPO = 'choice-uft';

	/**
	 * Cache transient key
	 *
	 * @var string
	 */
	const CACHE_KEY = 'cuft_plugin_info';

	/**
	 * Cache duration (12 hours)
	 *
	 * @var int
	 */
	const CACHE_DURATION = 43200; // 12 * HOUR_IN_SECONDS

	/**
	 * Initialize the plugin information provider
	 */
	public static function init() {
		add_filter( 'plugins_api', array( __CLASS__, 'plugins_api_handler' ), 10, 3 );
	}

	/**
	 * Handle plugins_api filter
	 *
	 * @param false|object|array $result The result object or array.
	 * @param string             $action The type of information being requested.
	 * @param object             $args   Plugin API arguments.
	 * @return false|object Plugin information object or false to pass through.
	 */
	public static function plugins_api_handler( $result, $action, $args ) {
		// Early exit: wrong action.
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		// Early exit: slug not specified.
		if ( ! isset( $args->slug ) ) {
			return $result;
		}

		// Early exit: not our plugin.
		if ( self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		// Check cache first.
		$cached = get_transient( self::CACHE_KEY );

		if ( false !== $cached ) {
			// Check if cache is fresh.
			if ( isset( $cached['timestamp'] ) && $cached['timestamp'] > ( time() - self::CACHE_DURATION ) ) {
				return $cached['data'];
			}
		}

		// Attempt to fetch from GitHub.
		$plugin_info = self::fetch_from_github( $cached );

		// If fetch failed, use fallback.
		if ( false === $plugin_info ) {
			// Try cached data even if expired.
			if ( false !== $cached && isset( $cached['data'] ) ) {
				return $cached['data'];
			}

			// Last resort: hardcoded fallback.
			return self::get_hardcoded_plugin_info();
		}

		return $plugin_info;
	}

	/**
	 * Fetch plugin information from GitHub API
	 *
	 * @param array|false $cached Cached data with ETag if available.
	 * @return object|false Plugin information object or false on failure.
	 */
	private static function fetch_from_github( $cached = false ) {
		$api_url = sprintf(
			'https://api.github.com/repos/%s/%s/releases/latest',
			self::GITHUB_OWNER,
			self::GITHUB_REPO
		);

		// Prepare headers.
		$headers = array(
			'Accept'     => 'application/vnd.github.v3+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
		);

		// Add ETag for conditional request.
		if ( false !== $cached && isset( $cached['etag'] ) && ! empty( $cached['etag'] ) ) {
			$headers['If-None-Match'] = $cached['etag'];
		}

		// Make API request.
		$response = wp_remote_get(
			$api_url,
			array(
				'headers' => $headers,
				'timeout' => 15,
			)
		);

		// Handle request errors.
		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CUFT: GitHub API error: ' . $response->get_error_message() );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $response );

		// Handle 304 Not Modified.
		if ( 304 === $response_code ) {
			// Cache is still valid, return cached data.
			if ( false !== $cached && isset( $cached['data'] ) ) {
				return $cached['data'];
			}
			return false;
		}

		// Handle rate limit.
		if ( 403 === $response_code ) {
			$rate_limit_remaining = wp_remote_retrieve_header( $response, 'x-ratelimit-remaining' );

			if ( '0' === $rate_limit_remaining ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'CUFT: GitHub API rate limit exceeded' );
				return false;
			}
		}

		// Handle non-200 responses.
		if ( 200 !== $response_code ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CUFT: GitHub API returned HTTP ' . $response_code );
			return false;
		}

		// Parse response body.
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CUFT: Invalid JSON from GitHub API: ' . json_last_error_msg() );
			return false;
		}

		// Validate required fields.
		if ( ! isset( $data['tag_name'] ) || ! isset( $data['published_at'] ) || ! isset( $data['assets'] ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CUFT: GitHub API response missing required fields' );
			return false;
		}

		// Find ZIP asset.
		$download_url = self::find_zip_asset( $data['assets'] );
		if ( false === $download_url ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'CUFT: No ZIP asset found in GitHub release' );
			return false;
		}

		// Build plugin info object.
		$plugin_info = self::build_plugin_info_object( $data, $download_url );

		// Cache with ETag.
		$etag = wp_remote_retrieve_header( $response, 'etag' );
		set_transient(
			self::CACHE_KEY,
			array(
				'data'      => $plugin_info,
				'etag'      => $etag,
				'timestamp' => time(),
			),
			self::CACHE_DURATION
		);

		return $plugin_info;
	}

	/**
	 * Build plugin information object from GitHub data
	 *
	 * @param array  $data         GitHub API response data.
	 * @param string $download_url Download URL for ZIP file.
	 * @return object Plugin information object.
	 */
	private static function build_plugin_info_object( $data, $download_url ) {
		$sections = array(
			'description'  => wp_kses_post( self::get_description() ),
			'installation' => wp_kses_post( self::get_installation() ),
		);

		// Add changelog if available.
		if ( isset( $data['body'] ) && ! empty( $data['body'] ) ) {
			$sections['changelog'] = wp_kses_post( $data['body'] );
		}

		return (object) array(
			'name'           => 'Choice Universal Form Tracker',
			'slug'           => self::PLUGIN_SLUG,
			'version'        => ltrim( $data['tag_name'], 'v' ),
			'author'         => 'Choice Marketing',
			'author_profile' => 'https://github.com/' . self::GITHUB_OWNER,
			'homepage'       => sprintf( 'https://github.com/%s/%s', self::GITHUB_OWNER, self::GITHUB_REPO ),
			'requires'       => '5.0',
			'tested'         => '6.7',
			'requires_php'   => '7.0',
			'download_link'  => $download_url,
			'trunk'          => $download_url,
			'last_updated'   => $data['published_at'],
			'sections'       => $sections,
		);
	}

	/**
	 * Find ZIP asset in release assets
	 *
	 * @param array $assets GitHub release assets array.
	 * @return string|false ZIP download URL or false if not found.
	 */
	private static function find_zip_asset( $assets ) {
		if ( ! is_array( $assets ) ) {
			return false;
		}

		foreach ( $assets as $asset ) {
			if ( ! isset( $asset['name'] ) || ! isset( $asset['browser_download_url'] ) ) {
				continue;
			}

			// Look for ZIP files.
			if ( substr( $asset['name'], -4 ) === '.zip' ) {
				$url = $asset['browser_download_url'];

				// Validate URL is from our GitHub repository.
				$expected_prefix = sprintf(
					'https://github.com/%s/%s/releases/download/',
					self::GITHUB_OWNER,
					self::GITHUB_REPO
				);

				if ( 0 === strpos( $url, $expected_prefix ) ) {
					return $url;
				}
			}
		}

		return false;
	}

	/**
	 * Get hardcoded plugin information (fallback when GitHub unavailable)
	 *
	 * @return object Plugin information object without changelog.
	 */
	private static function get_hardcoded_plugin_info() {
		return (object) array(
			'name'           => 'Choice Universal Form Tracker',
			'slug'           => self::PLUGIN_SLUG,
			'version'        => defined( 'CUFT_VERSION' ) ? CUFT_VERSION : '3.16.0',
			'author'         => 'Choice Marketing',
			'author_profile' => 'https://github.com/' . self::GITHUB_OWNER,
			'homepage'       => sprintf( 'https://github.com/%s/%s', self::GITHUB_OWNER, self::GITHUB_REPO ),
			'requires'       => '5.0',
			'tested'         => '6.7',
			'requires_php'   => '7.0',
			'download_link'  => sprintf(
				'https://github.com/%s/%s/releases/latest/download/%s-v%s.zip',
				self::GITHUB_OWNER,
				self::GITHUB_REPO,
				self::PLUGIN_SLUG,
				defined( 'CUFT_VERSION' ) ? CUFT_VERSION : '3.16.0'
			),
			'trunk'          => sprintf(
				'https://github.com/%s/%s/releases/latest/download/%s-v%s.zip',
				self::GITHUB_OWNER,
				self::GITHUB_REPO,
				self::PLUGIN_SLUG,
				defined( 'CUFT_VERSION' ) ? CUFT_VERSION : '3.16.0'
			),
			'last_updated'   => gmdate( 'Y-m-d H:i:s' ),
			'sections'       => array(
				'description'  => wp_kses_post( self::get_description() ),
				'installation' => wp_kses_post( self::get_installation() ),
				// Changelog omitted - GitHub API unavailable.
			),
		);
	}

	/**
	 * Get plugin description
	 *
	 * @return string HTML description.
	 */
	private static function get_description() {
		return '<h2>Universal Form Tracking for GTM & GA4</h2>
<p>Track form submissions from multiple form frameworks (Elementor, Contact Form 7, Gravity Forms, Ninja Forms, Avada) with a single plugin. All tracking data flows through Google Tag Manager\'s dataLayer with standardized snake_case naming conventions.</p>

<h3>Key Features</h3>
<ul>
<li><strong>Multi-Framework Support:</strong> Works with Elementor Pro, Contact Form 7, Gravity Forms, Ninja Forms, and Avada Forms</li>
<li><strong>Automatic UTM Tracking:</strong> Captures and preserves UTM parameters from URL, sessionStorage, and cookies</li>
<li><strong>Click ID Tracking:</strong> Supports Google Ads (gclid), Facebook (fbclid), Microsoft (msclkid), and more</li>
<li><strong>Google Tag Manager Integration:</strong> Pushes standardized events to dataLayer for GTM processing</li>
<li><strong>GA4 Enhanced Conversions:</strong> Includes user_email and user_phone for enhanced conversion tracking</li>
<li><strong>Generate Lead Events:</strong> Fires qualified lead events when email, phone, and click_id are all present</li>
</ul>

<h3>DataLayer Events</h3>
<p>All events include <code>cuft_tracked: true</code> and <code>cuft_source</code> for framework identification.</p>
<ul>
<li><code>form_submit</code> - Fires on every successful form submission</li>
<li><code>generate_lead</code> - Fires when email + phone + click_id are all present</li>
</ul>';
	}

	/**
	 * Get installation instructions
	 *
	 * @return string HTML installation instructions.
	 */
	private static function get_installation() {
		return '<h3>Installation</h3>
<ol>
<li>Download the ZIP file from GitHub releases</li>
<li>Go to <strong>Plugins → Add New → Upload Plugin</strong></li>
<li>Select the downloaded ZIP file and click <strong>Install Now</strong></li>
<li>Click <strong>Activate Plugin</strong></li>
<li>Go to <strong>Settings → Universal Form Tracker</strong> to configure your GTM container ID</li>
</ol>

<h3>Configuration</h3>
<ol>
<li>Enter your Google Tag Manager container ID (format: GTM-XXXXXXX)</li>
<li>The plugin will automatically detect active form frameworks</li>
<li>Test form submissions and monitor dataLayer events in your browser console</li>
</ol>

<h3>Requirements</h3>
<ul>
<li>WordPress 5.0 or higher</li>
<li>PHP 7.0 or higher</li>
<li>At least one supported form framework active (Elementor Pro, CF7, Gravity, Ninja, or Avada)</li>
<li>Google Tag Manager container installed on your site</li>
</ul>';
	}
}

// Initialize plugin information provider.
CUFT_Plugin_Info::init();
