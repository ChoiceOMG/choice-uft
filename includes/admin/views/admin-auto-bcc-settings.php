<?php
/**
 * Auto-BCC Settings Tab Admin View
 *
 * Admin UI template for Auto-BCC configuration in Settings → Universal Form Tracker.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Includes/Admin/Views
 * @since      3.11.0
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load configuration
$config = get_option( 'cuft_auto_bcc_config', array(
	'enabled' => false,
	'bcc_email' => '',
	'selected_email_types' => array(),
	'rate_limit_threshold' => 100,
	'rate_limit_action' => 'log_only',
) );
?>

<div class="cuft-auto-bcc-section">
	<h2><?php esc_html_e( 'Auto-BCC Testing Email', 'choice-universal-form-tracker' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Automatically receive BCC copies of selected email types for testing and monitoring purposes.', 'choice-universal-form-tracker' ); ?>
	</p>

	<form id="cuft-auto-bcc-form" method="post">
		<?php wp_nonce_field( 'cuft_auto_bcc_save_settings', 'cuft_auto_bcc_nonce' ); ?>

		<!-- Enable/Disable Toggle -->
		<div class="cuft-setting-row">
			<label class="cuft-toggle">
				<input
					type="checkbox"
					name="cuft_auto_bcc_enabled"
					id="cuft-auto-bcc-enabled"
					value="1"
					<?php checked( $config['enabled'], true ); ?>
				/>
				<span class="cuft-toggle-label">
					<?php esc_html_e( 'Enable Auto-BCC', 'choice-universal-form-tracker' ); ?>
				</span>
			</label>
			<p class="description">
				<?php esc_html_e( 'When enabled, selected email types will automatically include a BCC to the configured address.', 'choice-universal-form-tracker' ); ?>
			</p>
		</div>

		<!-- BCC Email Address -->
		<div class="cuft-setting-row">
			<label for="cuft-bcc-email">
				<?php esc_html_e( 'BCC Email Address', 'choice-universal-form-tracker' ); ?>
				<span class="required">*</span>
			</label>
			<div class="cuft-email-input-wrapper">
				<input
					type="email"
					name="cuft_bcc_email"
					id="cuft-bcc-email"
					class="regular-text"
					value="<?php echo esc_attr( $config['bcc_email'] ); ?>"
					placeholder="testing@example.com"
				/>
				<span class="cuft-validation-feedback"></span>
			</div>
			<p class="description">
				<?php esc_html_e( 'Email address that will receive BCC copies. Validation happens in real-time.', 'choice-universal-form-tracker' ); ?>
			</p>
		</div>

		<!-- Email Type Selection -->
		<div class="cuft-setting-row">
			<label><?php esc_html_e( 'Email Types to BCC', 'choice-universal-form-tracker' ); ?></label>
			<fieldset>
				<?php
				$email_types = array(
					'form_submission' => __( 'Form Submissions', 'choice-universal-form-tracker' ),
					'user_registration' => __( 'User Registrations', 'choice-universal-form-tracker' ),
					'password_reset' => __( 'Password Resets', 'choice-universal-form-tracker' ),
					'comment_notification' => __( 'Comment Notifications', 'choice-universal-form-tracker' ),
					'admin_notification' => __( 'Admin Notifications', 'choice-universal-form-tracker' ),
					'other' => __( 'Other Emails', 'choice-universal-form-tracker' ),
				);

				foreach ( $email_types as $type => $label ) :
					$checked = in_array( $type, $config['selected_email_types'], true );
				?>
					<label class="cuft-checkbox-label">
						<input
							type="checkbox"
							name="cuft_email_types[]"
							value="<?php echo esc_attr( $type ); ?>"
							<?php checked( $checked ); ?>
						/>
						<?php echo esc_html( $label ); ?>
					</label><br/>
				<?php endforeach; ?>
			</fieldset>
			<p class="description">
				<?php esc_html_e( 'Select which types of emails should include BCC. Leave all unchecked to disable BCC entirely.', 'choice-universal-form-tracker' ); ?>
			</p>
		</div>

		<!-- Rate Limiting -->
		<div class="cuft-setting-row">
			<label for="cuft-rate-limit-threshold">
				<?php esc_html_e( 'Rate Limit Threshold', 'choice-universal-form-tracker' ); ?>
			</label>
			<input
				type="number"
				name="cuft_rate_limit_threshold"
				id="cuft-rate-limit-threshold"
				class="small-text"
				value="<?php echo esc_attr( $config['rate_limit_threshold'] ); ?>"
				min="0"
				max="10000"
			/>
			<span><?php esc_html_e( 'emails per hour', 'choice-universal-form-tracker' ); ?></span>
			<p class="description">
				<?php esc_html_e( 'Maximum BCC emails allowed per hour. Set to 0 for unlimited.', 'choice-universal-form-tracker' ); ?>
			</p>
		</div>

		<!-- Rate Limit Action -->
		<div class="cuft-setting-row">
			<label for="cuft-rate-limit-action">
				<?php esc_html_e( 'Rate Limit Action', 'choice-universal-form-tracker' ); ?>
			</label>
			<select name="cuft_rate_limit_action" id="cuft-rate-limit-action">
				<option value="log_only" <?php selected( $config['rate_limit_action'], 'log_only' ); ?>>
					<?php esc_html_e( 'Log Only (continue BCC)', 'choice-universal-form-tracker' ); ?>
				</option>
				<option value="pause_until_next_period" <?php selected( $config['rate_limit_action'], 'pause_until_next_period' ); ?>>
					<?php esc_html_e( 'Pause Until Next Hour', 'choice-universal-form-tracker' ); ?>
				</option>
			</select>
			<p class="description">
				<?php esc_html_e( 'Action to take when rate limit is exceeded.', 'choice-universal-form-tracker' ); ?>
			</p>
		</div>

		<!-- Action Buttons -->
		<div class="cuft-actions">
			<button type="submit" id="cuft-save-settings" class="button button-primary">
				<?php esc_html_e( 'Save Settings', 'choice-universal-form-tracker' ); ?>
			</button>

			<button type="button" id="cuft-send-test-email" class="button button-secondary">
				<span class="dashicons dashicons-email" style="margin-top:3px;"></span>
				<?php esc_html_e( 'Send Test Email', 'choice-universal-form-tracker' ); ?>
			</button>
		</div>

		<!-- Status Messages -->
		<div id="cuft-auto-bcc-messages" class="cuft-messages"></div>
	</form>

	<!-- Help Section -->
	<div class="cuft-help-section">
		<h3><?php esc_html_e( 'How It Works', 'choice-universal-form-tracker' ); ?></h3>
		<ul>
			<li><?php esc_html_e( 'Auto-BCC intercepts WordPress emails before they are sent.', 'choice-universal-form-tracker' ); ?></li>
			<li><?php esc_html_e( 'Only emails matching selected types will include the BCC.', 'choice-universal-form-tracker' ); ?></li>
			<li><?php esc_html_e( 'BCC failures will not block the primary email from being sent.', 'choice-universal-form-tracker' ); ?></li>
			<li><?php esc_html_e( 'Rate limiting prevents excessive BCC emails during high-volume scenarios.', 'choice-universal-form-tracker' ); ?></li>
			<li><?php esc_html_e( 'Compatible with SMTP plugins (WP Mail SMTP, Post SMTP, etc.).', 'choice-universal-form-tracker' ); ?></li>
		</ul>
	</div>
</div>
