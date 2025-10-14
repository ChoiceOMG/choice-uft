<?php
/**
 * Force Update Tab Admin View
 *
 * Admin UI template for force update controls in Settings → Universal Form Tracker.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Includes/Admin/Views
 * @since      3.19.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="cuft-force-update-section">
	<h2><?php esc_html_e( 'Manual Update Control', 'choice-universal-form-tracker' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Manually check for plugin updates or force reinstall the latest version from GitHub.', 'choice-universal-form-tracker' ); ?>
	</p>

	<!-- Current Version Info -->
	<div class="cuft-current-version">
		<strong><?php esc_html_e( 'Current Version:', 'choice-universal-form-tracker' ); ?></strong>
		<code><?php echo esc_html( CUFT_VERSION ); ?></code>
	</div>

	<!-- Update Controls -->
	<div class="cuft-update-controls">
		<button type="button" id="cuft-check-updates" class="button button-secondary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cuft_force_update' ) ); ?>">
			<span class="dashicons dashicons-update" style="margin-top:3px;"></span>
			<?php esc_html_e( 'Check for Updates', 'choice-universal-form-tracker' ); ?>
		</button>

		<button type="button" id="cuft-force-reinstall" class="button button-primary" data-nonce="<?php echo esc_attr( wp_create_nonce( 'cuft_force_update' ) ); ?>" <?php echo ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) ? 'disabled' : ''; ?>>
			<span class="dashicons dashicons-download" style="margin-top:3px;"></span>
			<?php esc_html_e( 'Force Reinstall Latest Version', 'choice-universal-form-tracker' ); ?>
		</button>
	</div>

	<!-- Status Display -->
	<div id="cuft-update-status" class="cuft-update-status" style="display:none;"></div>

	<!-- Progress Indicator -->
	<div id="cuft-update-progress" class="cuft-update-progress" style="display:none;">
		<div class="cuft-progress-bar">
			<div class="cuft-progress-fill" style="width:0%;"></div>
		</div>
		<p class="cuft-progress-message"></p>
	</div>

	<!-- Update History -->
	<div class="cuft-update-history">
		<h3><?php esc_html_e( 'Recent Update Operations', 'choice-universal-form-tracker' ); ?></h3>
		<table class="widefat" id="cuft-history-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Operation', 'choice-universal-form-tracker' ); ?></th>
					<th><?php esc_html_e( 'User', 'choice-universal-form-tracker' ); ?></th>
					<th><?php esc_html_e( 'Date/Time', 'choice-universal-form-tracker' ); ?></th>
					<th><?php esc_html_e( 'Status', 'choice-universal-form-tracker' ); ?></th>
					<th><?php esc_html_e( 'Details', 'choice-universal-form-tracker' ); ?></th>
				</tr>
			</thead>
			<tbody id="cuft-history-body">
				<tr><td colspan="5"><?php esc_html_e( 'Loading history...', 'choice-universal-form-tracker' ); ?></td></tr>
			</tbody>
		</table>
	</div>

	<?php if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) : ?>
	<p class="notice notice-info inline">
		<strong><?php esc_html_e( 'Note:', 'choice-universal-form-tracker' ); ?></strong>
		<?php esc_html_e( 'File modifications are disabled on this site (DISALLOW_FILE_MODS constant). Force reinstall is not available.', 'choice-universal-form-tracker' ); ?>
	</p>
	<?php endif; ?>
</div>
