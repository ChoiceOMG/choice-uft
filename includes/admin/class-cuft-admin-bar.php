<?php
/**
 * Admin Bar Integration
 *
 * Adds update check trigger to WordPress admin bar.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT Admin Bar
 *
 * Adds CUFT menu items to the WordPress admin bar.
 */
class CUFT_Admin_Bar {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_admin_bar_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_bar_scripts' ) );
	}

	/**
	 * Add menu to admin bar
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Admin bar instance
	 * @return void
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		// Only show to users who can update plugins
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Get update status
		$update_status = CUFT_Update_Status::get();
		$update_available = ! empty( $update_status['update_available'] );

		// Parent menu
		$wp_admin_bar->add_node( array(
			'id' => 'cuft-updates',
			'title' => $this->get_menu_title( $update_available, $update_status ),
			'href' => admin_url( 'plugins.php' ),
			'meta' => array(
				'class' => $update_available ? 'cuft-update-available' : '',
			),
		) );

		// Check for updates submenu
		$wp_admin_bar->add_node( array(
			'id' => 'cuft-check-updates',
			'parent' => 'cuft-updates',
			'title' => __( 'Check for Updates', 'choice-uft' ),
			'href' => '#',
			'meta' => array(
				'class' => 'cuft-manual-check',
			),
		) );

		// View updates submenu (if updates available)
		if ( $update_available ) {
			$wp_admin_bar->add_node( array(
				'id' => 'cuft-view-updates',
				'parent' => 'cuft-updates',
				'title' => sprintf(
					/* translators: %s: version number */
					__( 'Update to %s', 'choice-uft' ),
					$update_status['latest_version']
				),
				'href' => admin_url( 'plugins.php' ),
			) );
		}

		// Update settings submenu
		$wp_admin_bar->add_node( array(
			'id' => 'cuft-update-settings',
			'parent' => 'cuft-updates',
			'title' => __( 'Update Settings', 'choice-uft' ),
			'href' => admin_url( 'options-general.php?page=choice-universal-form-tracker&tab=updates' ),
		) );

		// Update history submenu
		$wp_admin_bar->add_node( array(
			'id' => 'cuft-update-history',
			'parent' => 'cuft-updates',
			'title' => __( 'Update History', 'choice-uft' ),
			'href' => admin_url( 'options-general.php?page=choice-universal-form-tracker&tab=updates' ),
		) );

		// Add next scheduled check info
		if ( CUFT_Cron_Manager::is_scheduled() ) {
			$next_check = CUFT_Cron_Manager::get_next_scheduled_human();
			$wp_admin_bar->add_node( array(
				'id' => 'cuft-next-check',
				'parent' => 'cuft-updates',
				'title' => sprintf(
					/* translators: %s: time until next check */
					__( 'Next check: %s', 'choice-uft' ),
					$next_check
				),
				'href' => '#',
				'meta' => array(
					'class' => 'cuft-next-check-info',
				),
			) );
		}
	}

	/**
	 * Get menu title based on update status
	 *
	 * @param bool $update_available Whether update is available
	 * @param array $update_status Update status data
	 * @return string Menu title HTML
	 */
	private function get_menu_title( $update_available, $update_status ) {
		if ( $update_available ) {
			return sprintf(
				'<span class="ab-icon dashicons dashicons-update" style="color: #d63638;"></span>' .
				'<span class="ab-label">%s</span>',
				__( 'CUFT Update', 'choice-uft' )
			);
		}

		return sprintf(
			'<span class="ab-icon dashicons dashicons-plugins-checked"></span>' .
			'<span class="ab-label">%s</span>',
			__( 'CUFT', 'choice-uft' )
		);
	}

	/**
	 * Enqueue admin bar scripts
	 *
	 * @return void
	 */
	public function enqueue_admin_bar_scripts() {
		// Only enqueue if admin bar is showing
		if ( ! is_admin_bar_showing() ) {
			return;
		}

		// Only for users who can update plugins
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		wp_enqueue_script(
			'cuft-admin-bar',
			CUFT_URL . '/assets/admin/js/cuft-admin-bar.js',
			array( 'jquery' ),
			CUFT_VERSION,
			true
		);

		wp_localize_script( 'cuft-admin-bar', 'cuftAdminBar', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'cuft_updater_nonce' ),
			'checking' => __( 'Checking for updates...', 'choice-uft' ),
			'checkComplete' => __( 'Check complete', 'choice-uft' ),
			'checkFailed' => __( 'Check failed', 'choice-uft' ),
			'updateAvailable' => __( 'Update available!', 'choice-uft' ),
			'upToDate' => __( 'Plugin is up to date', 'choice-uft' ),
		) );

		// Add inline styles for admin bar
		wp_add_inline_style( 'admin-bar', '
			#wpadminbar .cuft-update-available .ab-icon:before {
				color: #d63638 !important;
			}
			#wpadminbar .cuft-next-check-info {
				cursor: default;
				opacity: 0.7;
			}
			#wpadminbar .cuft-manual-check {
				cursor: pointer;
			}
			#wpadminbar .cuft-checking .ab-icon:before {
				animation: cuft-spin 1s linear infinite;
			}
			@keyframes cuft-spin {
				from { transform: rotate(0deg); }
				to { transform: rotate(360deg); }
			}
		' );
	}
}

// Initialize admin bar integration after WordPress is loaded
add_action( 'init', function() {
	if ( is_admin_bar_showing() ) {
		new CUFT_Admin_Bar();
	}
}, 20 ); // Priority 20 to ensure WordPress is fully loaded
