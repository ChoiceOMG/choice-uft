<?php
/**
 * Admin Notices for Updates
 *
 * Displays admin notices for update availability and status.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CUFT Admin Notices
 *
 * Manages admin notices for the update system.
 */
class CUFT_Admin_Notices {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Display admin notices
	 *
	 * @return void
	 */
	public function display_notices() {
		// Only show to users who can update plugins
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		// Don't show on WordPress core update pages (WordPress handles it there)
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'update-core' ) {
			return;
		}

		// Check for update availability
		$this->maybe_display_update_available_notice();

		// Check for update completion
		$this->maybe_display_update_complete_notice();

		// Check for update failure
		$this->maybe_display_update_failed_notice();

		// Check for update in progress
		$this->maybe_display_update_progress_notice();
	}

	/**
	 * Display update available notice
	 *
	 * @return void
	 */
	private function maybe_display_update_available_notice() {
		$update_status = CUFT_Update_Status::get();

		// Don't show if no update available
		if ( empty( $update_status['update_available'] ) ) {
			return;
		}

		// Don't show if update is in progress
		if ( CUFT_Update_Progress::is_in_progress() ) {
			return;
		}

		// Check if user dismissed this notice
		$dismissed = get_user_meta( get_current_user_id(), 'cuft_dismissed_update_' . $update_status['latest_version'], true );
		if ( $dismissed ) {
			return;
		}

		$current_version = CUFT_VERSION;
		$latest_version = $update_status['latest_version'];
		$plugin_page_url = admin_url( 'plugins.php' );

		?>
		<div class="notice notice-info is-dismissible cuft-update-notice" data-version="<?php echo esc_attr( $latest_version ); ?>">
			<p>
				<strong><?php esc_html_e( 'Choice Universal Form Tracker Update Available', 'choice-uft' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: 1: current version, 2: new version */
					esc_html__( 'Version %2$s is available. You are currently running version %1$s.', 'choice-uft' ),
					esc_html( $current_version ),
					'<strong>' . esc_html( $latest_version ) . '</strong>'
				);
				?>
			</p>
			<p>
				<a href="<?php echo esc_url( $plugin_page_url ); ?>" class="button button-primary">
					<?php esc_html_e( 'View Plugin Updates', 'choice-uft' ); ?>
				</a>
				<a href="#" class="button cuft-dismiss-notice" data-version="<?php echo esc_attr( $latest_version ); ?>">
					<?php esc_html_e( 'Dismiss', 'choice-uft' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Display update complete notice
	 *
	 * @return void
	 */
	private function maybe_display_update_complete_notice() {
		// Check for completion flag
		$completed = get_transient( 'cuft_update_completed' );
		if ( ! $completed ) {
			return;
		}

		// Clear the flag
		delete_transient( 'cuft_update_completed' );

		?>
		<div class="notice notice-success is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Update Completed Successfully!', 'choice-uft' ); ?></strong>
			</p>
			<p>
				<?php
				printf(
					/* translators: %s: new version */
					esc_html__( 'Choice Universal Form Tracker has been updated to version %s.', 'choice-uft' ),
					'<strong>' . esc_html( CUFT_VERSION ) . '</strong>'
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Display update failed notice
	 *
	 * @return void
	 */
	private function maybe_display_update_failed_notice() {
		$progress = CUFT_Update_Progress::get();

		// Only show if failed
		if ( $progress['status'] !== 'failed' ) {
			return;
		}

		// Check if user dismissed this notice
		$dismissed = get_user_meta( get_current_user_id(), 'cuft_dismissed_update_failed', true );
		if ( $dismissed ) {
			return;
		}

		?>
		<div class="notice notice-error is-dismissible cuft-update-failed-notice">
			<p>
				<strong><?php esc_html_e( 'Update Failed', 'choice-uft' ); ?></strong>
			</p>
			<p>
				<?php echo esc_html( $progress['message'] ); ?>
			</p>
			<p>
				<a href="#" class="button button-secondary cuft-retry-update">
					<?php esc_html_e( 'Retry Update', 'choice-uft' ); ?>
				</a>
				<a href="#" class="button cuft-dismiss-failed" data-dismiss-type="failed">
					<?php esc_html_e( 'Dismiss', 'choice-uft' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	/**
	 * Display update in progress notice
	 *
	 * @return void
	 */
	private function maybe_display_update_progress_notice() {
		// Don't show on plugins page (WordPress handles it there)
		$screen = get_current_screen();
		if ( $screen && $screen->id === 'plugins' ) {
			return;
		}

		$progress = CUFT_Update_Progress::get();

		// Only show if in progress
		if ( ! CUFT_Update_Progress::is_in_progress() ) {
			return;
		}

		?>
		<div class="notice notice-warning cuft-update-progress-notice">
			<p>
				<strong><?php esc_html_e( 'Update in Progress', 'choice-uft' ); ?></strong>
			</p>
			<p>
				<?php echo esc_html( $progress['message'] ); ?>
				<span class="cuft-progress-percentage">(<?php echo absint( $progress['percentage'] ); ?>%)</span>
			</p>
			<div class="cuft-progress-bar">
				<div class="cuft-progress-fill" style="width: <?php echo absint( $progress['percentage'] ); ?>%;"></div>
			</div>
			<p class="description">
				<?php esc_html_e( 'Please do not close this page or navigate away until the update is complete.', 'choice-uft' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts for admin notices
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue on pages where we show notices
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		wp_enqueue_script(
			'cuft-admin-notices',
			CUFT_URL . '/assets/admin/js/cuft-admin-notices.js',
			array( 'jquery' ),
			CUFT_VERSION,
			true
		);

		wp_localize_script( 'cuft-admin-notices', 'cuftNotices', array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'cuft_updater_nonce' ),
			'dismissing' => __( 'Dismissing...', 'choice-uft' ),
			'dismissed' => __( 'Dismissed', 'choice-uft' ),
		) );

		// Add inline styles
		wp_add_inline_style( 'wp-admin', '
			.cuft-progress-bar {
				height: 20px;
				background: #f0f0f1;
				border-radius: 3px;
				overflow: hidden;
				margin: 10px 0;
			}
			.cuft-progress-fill {
				height: 100%;
				background: #2271b1;
				transition: width 0.3s ease;
			}
			.cuft-update-progress-notice .cuft-progress-bar {
				max-width: 400px;
			}
			.cuft-progress-percentage {
				font-weight: bold;
				margin-left: 5px;
			}
		' );
	}
}

// Initialize admin notices
if ( is_admin() ) {
	new CUFT_Admin_Notices();
}
