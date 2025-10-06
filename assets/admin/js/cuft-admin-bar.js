/**
 * Admin Bar Handler
 *
 * Handles manual update check trigger from admin bar.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
	'use strict';

	// Wait for DOM to be ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	/**
	 * Initialize admin bar handlers
	 */
	function init() {
		handleManualCheck();
	}

	/**
	 * Handle manual update check from admin bar
	 */
	function handleManualCheck() {
		// Try jQuery first (WordPress standard)
		if (window.jQuery) {
			jQuery(document).on('click', '#wp-admin-bar-cuft-check-updates a', function(e) {
				e.preventDefault();
				triggerManualCheck(this);
			});
		}

		// Vanilla JavaScript fallback
		document.addEventListener('click', function(e) {
			var target = e.target;

			// Check if clicked element or parent is the manual check link
			if (target.id === 'wp-admin-bar-cuft-check-updates' ||
			    target.closest('#wp-admin-bar-cuft-check-updates')) {
				e.preventDefault();
				var link = target.tagName === 'A' ? target : target.querySelector('a');
				if (link) {
					triggerManualCheck(link);
				}
			}
		});
	}

	/**
	 * Trigger manual update check
	 *
	 * @param {Element} link Link element that was clicked
	 */
	function triggerManualCheck(link) {
		// Get parent menu item
		var menuItem = link.closest ? link.closest('li') :
		               (link.parentElement && link.parentElement.tagName === 'LI' ?
		                link.parentElement : null);

		if (!menuItem) {
			if (window.jQuery) {
				menuItem = jQuery(link).closest('li')[0];
			}
		}

		// Store original text
		var originalText = link.textContent || link.innerText;

		// Update text to show checking
		if (link.querySelector) {
			var textSpan = link.querySelector('.ab-item');
			if (textSpan) {
				textSpan.textContent = cuftAdminBar.checking;
			} else {
				link.textContent = cuftAdminBar.checking;
			}
		} else {
			link.textContent = cuftAdminBar.checking;
		}

		// Add checking class for animation
		if (menuItem) {
			menuItem.classList.add('cuft-checking');
		}

		// Prepare request data
		var data = {
			action: 'cuft_check_update',
			nonce: cuftAdminBar.nonce,
			force: true
		};

		// Send AJAX request (try fetch first)
		sendCheckRequest(data, function(success, result) {
			// Remove checking class
			if (menuItem) {
				menuItem.classList.remove('cuft-checking');
			}

			if (success) {
				handleCheckSuccess(link, result, originalText);
			} else {
				handleCheckFailure(link, result, originalText);
			}
		});
	}

	/**
	 * Send check request via AJAX
	 *
	 * @param {Object} data Request data
	 * @param {Function} callback Callback function
	 */
	function sendCheckRequest(data, callback) {
		// Try fetch API
		if (window.fetch) {
			var formData = new FormData();
			for (var key in data) {
				formData.append(key, data[key]);
			}

			fetch(cuftAdminBar.ajaxUrl, {
				method: 'POST',
				body: formData,
				credentials: 'same-origin'
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(result) {
				callback(result.success, result.data || result);
			})
			.catch(function(error) {
				console.warn('CUFT: Update check failed', error);
				callback(false, { message: error.message });
			});
			return;
		}

		// Fallback to jQuery AJAX
		if (window.jQuery) {
			jQuery.post(cuftAdminBar.ajaxUrl, data, function(response) {
				callback(response.success, response.data || response);
			})
			.fail(function() {
				callback(false, { message: 'AJAX request failed' });
			});
			return;
		}

		// Fallback to XMLHttpRequest
		var xhr = new XMLHttpRequest();
		xhr.open('POST', cuftAdminBar.ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
		xhr.onload = function() {
			if (xhr.status === 200) {
				try {
					var response = JSON.parse(xhr.responseText);
					callback(response.success, response.data || response);
				} catch (e) {
					callback(false, { message: 'Invalid response' });
				}
			} else {
				callback(false, { message: 'Request failed: ' + xhr.status });
			}
		};
		xhr.onerror = function() {
			callback(false, { message: 'Network error' });
		};

		var formData = [];
		for (var key in data) {
			formData.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
		}
		xhr.send(formData.join('&'));
	}

	/**
	 * Handle successful check
	 *
	 * @param {Element} link Link element
	 * @param {Object} result Result data
	 * @param {string} originalText Original link text
	 */
	function handleCheckSuccess(link, result, originalText) {
		// Update link text
		if (result.update_available) {
			updateLinkText(link, cuftAdminBar.updateAvailable);

			// Show notification
			showNotification(
				sprintf(cuftAdminBar.updateAvailable + ' Version %s is available.', result.latest_version),
				'success'
			);

			// Reload page after 2 seconds to show update notice
			setTimeout(function() {
				window.location.reload();
			}, 2000);
		} else {
			updateLinkText(link, cuftAdminBar.upToDate);

			// Show notification
			showNotification(cuftAdminBar.upToDate, 'info');

			// Restore original text after 3 seconds
			setTimeout(function() {
				updateLinkText(link, originalText);
			}, 3000);
		}
	}

	/**
	 * Handle check failure
	 *
	 * @param {Element} link Link element
	 * @param {Object} result Result data
	 * @param {string} originalText Original link text
	 */
	function handleCheckFailure(link, result, originalText) {
		// Update link text
		updateLinkText(link, cuftAdminBar.checkFailed);

		// Show error notification
		var errorMsg = result.message || 'Unknown error';
		showNotification(cuftAdminBar.checkFailed + ': ' + errorMsg, 'error');

		// Restore original text after 3 seconds
		setTimeout(function() {
			updateLinkText(link, originalText);
		}, 3000);
	}

	/**
	 * Update link text
	 *
	 * @param {Element} link Link element
	 * @param {string} text New text
	 */
	function updateLinkText(link, text) {
		if (link.querySelector) {
			var textSpan = link.querySelector('.ab-item');
			if (textSpan) {
				textSpan.textContent = text;
			} else {
				link.textContent = text;
			}
		} else {
			link.textContent = text;
		}
	}

	/**
	 * Show notification
	 *
	 * @param {string} message Message to show
	 * @param {string} type Notification type (success, error, info)
	 */
	function showNotification(message, type) {
		// Try to use WordPress admin notices if available
		if (window.wp && window.wp.data && window.wp.data.dispatch) {
			try {
				window.wp.data.dispatch('core/notices').createNotice(
					type,
					message,
					{
						isDismissible: true,
						type: type === 'error' ? 'snackbar' : 'default'
					}
				);
				return;
			} catch (e) {
				// Fall through to console
			}
		}

		// Fallback to console
		if (type === 'error') {
			console.error('CUFT: ' + message);
		} else {
			console.log('CUFT: ' + message);
		}
	}

	/**
	 * Simple sprintf implementation
	 *
	 * @param {string} format Format string
	 * @param {...*} args Arguments
	 * @return {string} Formatted string
	 */
	function sprintf(format) {
		var args = Array.prototype.slice.call(arguments, 1);
		var i = 0;
		return format.replace(/%s/g, function() {
			return args[i++];
		});
	}

})();
