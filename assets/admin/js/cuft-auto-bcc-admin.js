/**
 * Auto-BCC Admin JavaScript
 *
 * Handles real-time email validation, AJAX save/test operations, and UI feedback.
 *
 * @package Choice_Universal_Form_Tracker
 * @since   3.11.0
 */

(function($) {
	'use strict';

	/**
	 * Auto-BCC Admin Controller
	 */
	const AutoBCCAdmin = {
		/**
		 * Initialize
		 */
		init: function() {
			this.bindEvents();
			this.emailInput = $('#cuft-bcc-email');
			this.validationFeedback = $('.cuft-validation-feedback');
			this.messagesContainer = $('#cuft-auto-bcc-messages');
		},

		/**
		 * Bind UI events
		 */
		bindEvents: function() {
			// Email validation on blur
			$('#cuft-bcc-email').on('blur', this.validateEmail.bind(this));

			// Save settings form submit
			$('#cuft-auto-bcc-form').on('submit', this.saveSettings.bind(this));

			// Send test email button
			$('#cuft-send-test-email').on('click', this.sendTestEmail.bind(this));
		},

		/**
		 * Validate email address (real-time)
		 */
		validateEmail: function() {
			const email = this.emailInput.val().trim();

			// Clear previous feedback
			this.validationFeedback.removeClass('valid invalid').html('');

			if (!email) {
				return; // Empty is valid (will be caught on save if enabled)
			}

			// Basic email regex validation (client-side)
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

			if (emailRegex.test(email)) {
				this.validationFeedback
					.addClass('valid')
					.html('<span class="dashicons dashicons-yes-alt"></span>');
			} else {
				this.validationFeedback
					.addClass('invalid')
					.html('<span class="dashicons dashicons-dismiss"></span>');
			}
		},

		/**
		 * Save settings via AJAX
		 */
		saveSettings: function(e) {
			e.preventDefault();

			const $form = $('#cuft-auto-bcc-form');
			const $saveButton = $('#cuft-save-settings');
			const formData = $form.serializeArray();

			// Disable button during save
			$saveButton.prop('disabled', true).text('Saving...');

			// Convert form data to object
			const data = {
				action: 'cuft_auto_bcc_save_settings',
				nonce: cuftAutoBcc.saveNonce,
				enabled: $('#cuft-auto-bcc-enabled').is(':checked') ? 1 : 0,
				bcc_email: $('#cuft-bcc-email').val().trim(),
				selected_email_types: [],
				rate_limit_threshold: $('#cuft-rate-limit-threshold').val(),
				rate_limit_action: $('#cuft-rate-limit-action').val()
			};

			// Collect selected email types
			$('input[name="cuft_email_types[]"]:checked').each(function() {
				data.selected_email_types.push($(this).val());
			});

			// Send AJAX request
			$.ajax({
				url: cuftAutoBcc.ajaxUrl,
				type: 'POST',
				data: data,
				success: this.handleSaveSuccess.bind(this),
				error: this.handleSaveError.bind(this),
				complete: function() {
					$saveButton.prop('disabled', false).text('Save Settings');
				}
			});
		},

		/**
		 * Handle save success response
		 */
		handleSaveSuccess: function(response) {
			if (response.success) {
				this.showMessage('success', response.data.message || 'Settings saved successfully!');

				// Show warnings if present
				if (response.data.warnings && response.data.warnings.length > 0) {
					response.data.warnings.forEach(warning => {
						this.showMessage('warning', warning);
					});
				}
			} else {
				const errorMessage = response.data && response.data.message
					? response.data.message
					: 'Failed to save settings. Please try again.';
				this.showMessage('error', errorMessage);
			}
		},

		/**
		 * Handle save error response
		 */
		handleSaveError: function(xhr, status, error) {
			console.error('Auto-BCC Save Error:', error);
			this.showMessage('error', 'A server error occurred. Please check your connection and try again.');
		},

		/**
		 * Send test email via AJAX
		 */
		sendTestEmail: function(e) {
			e.preventDefault();

			const $testButton = $('#cuft-send-test-email');

			// Disable button during send
			$testButton.prop('disabled', true).text('Sending...');

			// Send AJAX request
			$.ajax({
				url: cuftAutoBcc.ajaxUrl,
				type: 'POST',
				data: {
					action: 'cuft_auto_bcc_send_test_email',
					nonce: cuftAutoBcc.testNonce,
					bcc_email: $('#cuft-bcc-email').val().trim()
				},
				success: this.handleTestSuccess.bind(this),
				error: this.handleTestError.bind(this),
				complete: function() {
					$testButton.prop('disabled', false).html('<span class="dashicons dashicons-email" style="margin-top:3px;"></span> Send Test Email');
				}
			});
		},

		/**
		 * Handle test email success response
		 */
		handleTestSuccess: function(response) {
			if (response.success) {
				const message = response.data.message || 'Test email sent successfully!';
				const details = response.data.details
					? `<br><small>${response.data.details.subject} sent at ${response.data.details.timestamp}</small>`
					: '';
				this.showMessage('success', message + details);
			} else {
				const errorMessage = response.data && response.data.message
					? response.data.message
					: 'Failed to send test email. Please check your configuration.';
				this.showMessage('error', errorMessage);
			}
		},

		/**
		 * Handle test email error response
		 */
		handleTestError: function(xhr, status, error) {
			console.error('Auto-BCC Test Email Error:', error);
			this.showMessage('error', 'A server error occurred. Please check your mail configuration.');
		},

		/**
		 * Show message to user
		 *
		 * @param {string} type - Message type: success, error, warning, info
		 * @param {string} message - Message text
		 */
		showMessage: function(type, message) {
			const messageClass = `notice notice-${type === 'error' ? 'error' : type} is-dismissible`;
			const $message = $(`<div class="${messageClass}"><p>${message}</p></div>`);

			// Clear previous messages
			this.messagesContainer.empty();

			// Add new message
			this.messagesContainer.append($message);

			// Auto-dismiss after 5 seconds
			setTimeout(() => {
				$message.fadeOut(400, function() {
					$(this).remove();
				});
			}, 5000);

			// Scroll to message
			$('html, body').animate({
				scrollTop: this.messagesContainer.offset().top - 100
			}, 400);
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		// Only initialize if Auto-BCC form exists
		if ($('#cuft-auto-bcc-form').length) {
			AutoBCCAdmin.init();
		}
	});

})(jQuery);
