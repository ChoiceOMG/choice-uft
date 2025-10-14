/**
 * Force Update JavaScript Module
 *
 * Client-side logic for AJAX interactions, button state management,
 * and progress polling.
 *
 * @package    Choice_Universal_Form_Tracker
 * @subpackage Assets/Admin
 * @since      3.19.0
 */

(function($) {
	'use strict';

	const CuftForceUpdate = {
		nonce: '',
		ajaxUrl: '',

		/**
		 * Initialize the module
		 */
		init: function() {
			this.nonce = $('#cuft-check-updates').data('nonce');
			this.ajaxUrl = window.cuftForceUpdate && window.cuftForceUpdate.ajaxUrl ? window.cuftForceUpdate.ajaxUrl : ajaxurl;
			this.bindEvents();
			this.loadHistory();
		},

		/**
		 * Bind event handlers
		 */
		bindEvents: function() {
			$('#cuft-check-updates').on('click', this.handleCheckUpdates.bind(this));
			$('#cuft-force-reinstall').on('click', this.handleForceReinstall.bind(this));
		},

		/**
		 * Handle Check for Updates button click
		 */
		handleCheckUpdates: function() {
			const $button = $('#cuft-check-updates');
			const originalText = $button.html();

			// Disable button and show loading state
			$button.prop('disabled', true);
			$button.html('<span class="dashicons dashicons-update dashicons-spin" style="margin-top:3px;"></span> Checking...');

			// Hide previous status
			$('#cuft-update-status').hide();

			// Make AJAX request
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'cuft_check_updates',
					nonce: this.nonce
				},
				success: (response) => {
					if (response.success) {
						const data = response.data;
						let message = data.message;

						if (data.update_available) {
							message += '<br><strong>Latest Version:</strong> ' + data.latest_version;
							message += '<br><strong>Release Date:</strong> ' + data.release_date;
							if (data.changelog_summary) {
								message += '<br><strong>Changes:</strong> ' + data.changelog_summary;
							}
						}

						this.showStatus(message, data.update_available ? 'info' : 'success');
					} else {
						const errorMessage = response.data && response.data.message ? response.data.message : 'Failed to check for updates';
						this.showStatus(errorMessage, 'error');
					}

					// Reload history
					this.loadHistory();
				},
				error: (xhr) => {
					this.showStatus('Network error while checking for updates', 'error');
				},
				complete: () => {
					// Re-enable button
					$button.prop('disabled', false);
					$button.html(originalText);
				}
			});
		},

		/**
		 * Handle Force Reinstall button click
		 */
		handleForceReinstall: function() {
			// Confirm action
			if (!confirm('This will download and reinstall the latest version from GitHub. The plugin will remain active and your settings will be preserved.\n\nContinue?')) {
				return;
			}

			const $button = $('#cuft-force-reinstall');
			const originalText = $button.html();

			// Disable button and show loading state
			$button.prop('disabled', true);
			$button.html('<span class="dashicons dashicons-update dashicons-spin" style="margin-top:3px;"></span> Installing...');

			// Hide previous status
			$('#cuft-update-status').hide();

			// Show progress indicator
			this.showProgress(10, 'Starting force reinstall...');

			// Make AJAX request
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'cuft_force_reinstall',
					nonce: this.nonce
				},
				timeout: 90000, // 90 second timeout (server has 60s limit)
				success: (response) => {
					if (response.success) {
						const data = response.data;
						this.showProgress(100, data.message);
						this.showStatus('✓ ' + data.message + '<br>Previous version: ' + data.source_version + '<br>New version: ' + data.target_version, 'success');

						// Reload history
						this.loadHistory();

						// Reload page after 3 seconds to show new version
						setTimeout(() => {
							window.location.reload();
						}, 3000);
					} else {
						const errorMessage = response.data && response.data.message ? response.data.message : 'Force reinstall failed';
						this.showProgress(0, errorMessage);
						this.showStatus('✗ ' + errorMessage, 'error');

						// Re-enable button on error
						$button.prop('disabled', false);
						$button.html(originalText);
					}

					// Reload history regardless
					this.loadHistory();
				},
				error: (xhr) => {
					let errorMsg = 'Network error during force reinstall';
					if (xhr.statusText === 'timeout') {
						errorMsg = 'Operation timed out. Please check the update history for results.';
					}
					this.showProgress(0, errorMsg);
					this.showStatus('✗ ' + errorMsg, 'error');

					// Re-enable button
					$button.prop('disabled', false);
					$button.html(originalText);

					// Reload history
					this.loadHistory();
				}
			});
		},

		/**
		 * Load update history
		 */
		loadHistory: function() {
			$.ajax({
				url: this.ajaxUrl,
				type: 'POST',
				data: {
					action: 'cuft_get_update_history',
					nonce: this.nonce
				},
				success: (response) => {
					if (response.success) {
						this.renderHistory(response.data);
					} else {
						$('#cuft-history-body').html('<tr><td colspan="5">Failed to load history</td></tr>');
					}
				},
				error: () => {
					$('#cuft-history-body').html('<tr><td colspan="5">Error loading history</td></tr>');
				}
			});
		},

		/**
		 * Render update history table
		 */
		renderHistory: function(data) {
			const $tbody = $('#cuft-history-body');
			$tbody.empty();

			if (!data.history || data.history.length === 0) {
				$tbody.append('<tr><td colspan="5">No update operations in history yet.</td></tr>');
				return;
			}

			data.history.forEach((entry) => {
				const operationType = entry.operation_type === 'manual_check' ? '🔍 Manual Check' : '🔄 Force Reinstall';
				const status = entry.status === 'complete' ? '<span style="color: green;">✓ Complete</span>' : '<span style="color: red;">✗ Failed</span>';

				let details = '';
				if (entry.details) {
					if (entry.operation_type === 'manual_check') {
						details = 'v' + (entry.details.latest_version || 'Unknown');
					} else if (entry.operation_type === 'force_reinstall') {
						details = 'v' + (entry.details.source_version || 'Unknown') + ' → v' + (entry.details.target_version || 'Unknown');
						if (entry.details.duration_seconds) {
							details += ' (' + entry.details.duration_seconds + 's)';
						}
					}
					if (entry.status === 'failed' && entry.details.error_message) {
						details += '<br><small style="color: red;">' + entry.details.error_message + '</small>';
					}
				}

				const row = '<tr>' +
					'<td>' + operationType + '</td>' +
					'<td>' + (entry.user_display_name || 'Unknown') + '</td>' +
					'<td>' + (entry.timestamp_formatted || '') + '</td>' +
					'<td>' + status + '</td>' +
					'<td>' + details + '</td>' +
					'</tr>';

				$tbody.append(row);
			});
		},

		/**
		 * Show status message
		 */
		showStatus: function(message, type) {
			const $status = $('#cuft-update-status');
			const typeClass = type === 'success' ? 'notice-success' : (type === 'error' ? 'notice-error' : 'notice-info');

			$status.removeClass('notice-success notice-error notice-info');
			$status.addClass('notice ' + typeClass);
			$status.html('<p>' + message + '</p>');
			$status.show();
		},

		/**
		 * Show/update progress indicator
		 */
		showProgress: function(percentage, message) {
			const $progress = $('#cuft-update-progress');
			const $fill = $('.cuft-progress-fill');
			const $message = $('.cuft-progress-message');

			$fill.css('width', percentage + '%');
			$message.text(message);

			if (percentage > 0 && percentage < 100) {
				$progress.show();
			} else if (percentage === 100) {
				// Keep showing for 2 seconds before hiding
				setTimeout(() => {
					$progress.hide();
				}, 2000);
			} else {
				$progress.hide();
			}
		}
	};

	// Initialize on document ready
	$(document).ready(function() {
		if ($('#cuft-check-updates').length > 0) {
			CuftForceUpdate.init();
		}
	});

})(jQuery);
