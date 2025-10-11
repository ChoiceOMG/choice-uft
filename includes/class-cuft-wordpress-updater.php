<?php
/**
 * WordPress Update Integration
 *
 * Integrates the update system with WordPress's native update mechanism.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT WordPress Updater
 *
 * Hooks into WordPress update transients to provide update notifications.
 */
class CUFT_WordPress_Updater {

	/**
	 * Plugin basename
	 *
	 * @var string
	 */
	private $plugin_basename;

	/**
	 * Plugin slug
	 *
	 * @var string
	 */
	private $plugin_slug;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->plugin_basename = plugin_basename( CUFT_PLUGIN_FILE );
		$this->plugin_slug = dirname( $this->plugin_basename );

		$this->register_hooks();
	}

	/**
	 * Register WordPress hooks
	 *
	 * @return void
	 */
	private function register_hooks() {
		// Hook into update transient
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_updates' ) );

		// Hook into plugin information
		add_filter( 'plugins_api', array( $this, 'plugin_information' ), 10, 3 );

		// Hook into update source selection
		add_filter( 'upgrader_source_selection', array( $this, 'upgrader_source_selection' ), 10, 3 );

		// Hook into actual update process
		add_action( 'cuft_process_update', array( $this, 'process_update' ), 10, 3 );

		// After plugin row for update notices
		add_action( "after_plugin_row_{$this->plugin_basename}", array( $this, 'plugin_row_notice' ), 10, 2 );

		// Cache invalidation after updates
		add_action( 'upgrader_process_complete', array( $this, 'invalidate_cache_after_update' ), 10, 2 );
	}

	/**
	 * Check for updates and inject into WordPress update transient
	 *
	 * @param object $transient Update transient object
	 * @return object Modified transient
	 */
	public function check_for_updates( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		// Get current plugin version
		$current_version = CUFT_VERSION;

		// Check if we have update information cached
		$update_status = CUFT_Update_Status::get();

		// Perform check if no cache or cache expired based on context
		$cache_timeout = $this->get_context_timeout();
		if ( empty( $update_status['last_check'] ) ||
		     ( time() - strtotime( $update_status['last_check'] ) > $cache_timeout ) ) {
			CUFT_Update_Checker::check( false );
			$update_status = CUFT_Update_Status::get();
		}

		// If update is available, add to transient
		if ( ! empty( $update_status['update_available'] ) &&
		     version_compare( $update_status['latest_version'], $current_version, '>' ) ) {

			// Get release information
			$release = CUFT_GitHub_Release::fetch_version( $update_status['latest_version'] );

			if ( $release && $release->get_download_url() ) {
				$plugin_data = array(
					'slug' => $this->plugin_slug,
					'plugin' => $this->plugin_basename,
					'new_version' => $update_status['latest_version'],
					'url' => 'https://github.com/ChoiceOMG/choice-uft',
					'package' => $release->get_download_url(),
					'tested' => '6.4',
					'requires_php' => '7.0',
					'compatibility' => new stdClass(),
				);

				$transient->response[ $this->plugin_basename ] = (object) $plugin_data;
			}
		}

		return $transient;
	}

	/**
	 * Provide plugin information for WordPress plugin installer
	 *
	 * @param false|object|array $result The result object or array
	 * @param string $action The type of information being requested
	 * @param object $args Plugin API arguments
	 * @return false|object Modified result
	 */
	public function plugin_information( $result, $action, $args ) {
		// Only handle plugin_information requests
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		// Only handle requests for this plugin
		if ( ! isset( $args->slug ) || $args->slug !== $this->plugin_slug ) {
			return $result;
		}

		// Get latest release information
		$release = CUFT_GitHub_Release::fetch_latest();

		if ( ! $release ) {
			return $result;
		}

		// Build plugin information object
		$plugin_info = new stdClass();
		$plugin_info->name = 'Choice Universal Form Tracker';
		$plugin_info->slug = $this->plugin_slug;
		$plugin_info->version = $release->get_version();
		$plugin_info->author = '<a href="https://github.com/ChoiceOMG">ChoiceOMG</a>';
		$plugin_info->homepage = 'https://github.com/ChoiceOMG/choice-uft';
		$plugin_info->requires = '5.0';
		$plugin_info->tested = '6.4';
		$plugin_info->requires_php = '7.0';
		$plugin_info->download_link = $release->get_download_url();
		$plugin_info->trunk = $release->get_download_url();
		$plugin_info->last_updated = $release->get_published_date();
		$plugin_info->sections = array(
			'description' => 'Universal form tracking plugin for WordPress with GTM integration.',
			'changelog' => $release->get_changelog() ?: 'See GitHub releases for changelog.',
		);

		// Note: download_count not available in CUFT_GitHub_Release model

		return $plugin_info;
	}

	/**
	 * Fix source directory after WordPress extracts the update
	 *
	 * GitHub releases create a directory with the tag name, but WordPress
	 * expects the plugin slug. This filter renames the directory.
	 *
	 * @param string $source Source directory
	 * @param string $remote_source Remote source
	 * @param WP_Upgrader $upgrader Upgrader instance
	 * @return string|WP_Error Modified source or error
	 */
	public function upgrader_source_selection( $source, $remote_source, $upgrader ) {
		global $wp_filesystem;

		// Only handle plugin updates
		if ( ! isset( $upgrader->skin->plugin ) || $upgrader->skin->plugin !== $this->plugin_basename ) {
			return $source;
		}

		// Check if source directory exists
		if ( ! $wp_filesystem->exists( $source ) ) {
			return new WP_Error( 'source_not_found', 'Source directory not found' );
		}

		// Expected directory name
		$expected_slug = $this->plugin_slug;
		$current_slug = basename( $source );

		// If already correct, return as-is
		if ( $current_slug === $expected_slug ) {
			return $source;
		}

		// Build corrected path
		$corrected_source = trailingslashit( dirname( $source ) ) . $expected_slug;

		// Rename directory
		if ( $wp_filesystem->move( $source, $corrected_source ) ) {
			return $corrected_source;
		}

		return new WP_Error( 'rename_failed', 'Failed to rename source directory' );
	}

	/**
	 * Process update asynchronously
	 *
	 * This is called via cron hook 'cuft_process_update'
	 *
	 * @param string $update_id Update ID
	 * @param string $version Target version
	 * @param bool $backup Whether to create backup
	 * @return void
	 */
	public function process_update( $update_id, $version, $backup = true ) {
		// Get target version
		if ( $version === 'latest' || empty( $version ) ) {
			$release = CUFT_GitHub_Release::fetch_latest();
			if ( ! $release ) {
				CUFT_Update_Progress::set_failed( 'Failed to fetch latest version' );
				return;
			}
			$version = $release->get_version();
		}

		// Create installer instance
		$installer = new CUFT_Update_Installer( $update_id, $version );

		// Execute update
		$result = $installer->execute( $backup );

		// Handle result
		if ( is_wp_error( $result ) ) {
			CUFT_Update_Progress::set_failed( $result->get_error_message() );
			CUFT_Update_Log::log_error( $result->get_error_message(), array(
				'update_id' => $update_id,
				'version_to' => $version,
			) );
		} else {
			CUFT_Update_Progress::set_complete( 'Update completed successfully' );
			
			// Clear all caches after successful update
			if ( class_exists( 'CUFT_Update_Installer' ) ) {
				// Use reflection to access private method
				try {
					$reflection = new ReflectionClass( 'CUFT_Update_Installer' );
					$method = $reflection->getMethod( 'invalidate_all_caches' );
					$method->setAccessible( true );
					$method->invoke( null );
				} catch ( Exception $e ) {
					// Fallback to basic cache clearing
					CUFT_Update_Status::clear();
					delete_site_transient( 'update_plugins' );
					wp_clean_plugins_cache();
				}
			}
		}
	}

	/**
	 * Display custom update notice in plugin row
	 *
	 * @param string $plugin_file Plugin file path
	 * @param array $plugin_data Plugin data
	 * @return void
	 */
	public function plugin_row_notice( $plugin_file, $plugin_data ) {
		$update_status = CUFT_Update_Status::get();

		// Only show if update is available
		if ( empty( $update_status['update_available'] ) ) {
			return;
		}

		// Check if update is in progress
		if ( CUFT_Update_Progress::is_in_progress() ) {
			$progress = CUFT_Update_Progress::get();
			?>
			<tr class="plugin-update-tr active cuft-update-progress" data-slug="<?php echo esc_attr( $this->plugin_slug ); ?>">
				<td colspan="4" class="plugin-update colspanchange">
					<div class="update-message notice inline notice-warning notice-alt">
						<p>
							<strong><?php esc_html_e( 'Update in progress:', 'choice-uft' ); ?></strong>
							<?php echo esc_html( $progress['message'] ); ?>
							<span class="cuft-progress-percentage">(<?php echo absint( $progress['percentage'] ); ?>%)</span>
						</p>
						<div class="cuft-progress-bar">
							<div class="cuft-progress-fill" style="width: <?php echo absint( $progress['percentage'] ); ?>%;"></div>
						</div>
					</div>
				</td>
			</tr>
			<?php
		}
	}

	/**
	 * Invalidate caches after plugin update completion
	 *
	 * Clears update-related caches when plugin updates complete to ensure
	 * fresh data is displayed immediately after updates.
	 *
	 * @param WP_Upgrader $upgrader Upgrader instance
	 * @param array $options Update options
	 * @return void
	 */
	public function invalidate_cache_after_update( $upgrader, $options ) {
		// Only process if this is a plugin update
		if ( ! isset( $options['type'] ) || $options['type'] !== 'plugin' ) {
			return;
		}

		// Check if our plugin was updated
		$updated_plugins = isset( $options['plugins'] ) ? $options['plugins'] : array();
		if ( ! in_array( $this->plugin_basename, $updated_plugins ) ) {
			return;
		}

		// Clear CUFT update status cache
		CUFT_Update_Status::clear();

		// Clear WordPress update transient to force recheck
		delete_site_transient( 'update_plugins' );

		// Set completion transient for admin notices
		set_site_transient( 'cuft_update_completed', array(
			'timestamp' => time(),
			'version' => CUFT_VERSION,
			'message' => 'Update completed successfully'
		), 5 * MINUTE_IN_SECONDS );

		// Clear any GitHub-specific caches (coordinate with GitHub updater)
		delete_transient( 'cuft_github_version' );
		delete_transient( 'cuft_github_changelog' );

		// Clear asset URL caches
		global $wpdb;
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM $wpdb->options WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_cuft_asset_url_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_cuft_asset_url_' ) . '%'
		) );

		// Schedule immediate recheck for updated status
		if ( ! wp_next_scheduled( 'cuft_check_updates' ) ) {
			wp_schedule_single_event( time() + 30, 'cuft_check_updates' );
		}
	}

	/**
	 * Get context-aware cache timeout
	 *
	 * Returns different timeout values based on the current WordPress context
	 * to optimize API calls and improve user experience.
	 *
	 * @return int Timeout in seconds
	 */
	private function get_context_timeout() {
		$filter = current_filter();
		$action = current_action();

		// Context-aware timeout map based on WordPress core patterns
		$timeouts = array(
			'upgrader_process_complete' => 0,                    // Immediate after update
			'load-update-core.php' => MINUTE_IN_SECONDS,         // 1 minute on Updates page
			'load-plugins.php' => HOUR_IN_SECONDS,               // 1 hour on Plugins page
			'load-update.php' => HOUR_IN_SECONDS,                // 1 hour on Updates page
		);

		// Check current filter first
		if ( isset( $timeouts[ $filter ] ) ) {
			return $timeouts[ $filter ];
		}

		// Check current action
		if ( isset( $timeouts[ $action ] ) ) {
			return $timeouts[ $action ];
		}

		// Default timeout for all other contexts (6 hours)
		return 6 * HOUR_IN_SECONDS;
	}
}

// Initialize WordPress updater
new CUFT_WordPress_Updater();
