/**
 * Admin Notices Handler
 *
 * Handles dismissal and interactions with admin update notices.
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
	 * Initialize notice handlers
	 */
	function init() {
		// Handle update notice dismissal
		handleUpdateNoticeDismissal();

		// Handle retry update button
		handleRetryUpdate();

		// Auto-refresh progress notices
		autoRefreshProgress();
	}

	/**
	 * Handle dismissal of update available notices
	 */
	function handleUpdateNoticeDismissal() {
		// Try jQuery first (WordPress standard)
		if (window.jQuery) {
			jQuery(document).on('click', '.cuft-dismiss-notice', function(e) {
				e.preventDefault();
				var $button = jQuery(this);
				var version = $button.data('version');
				dismissNotice(version, $button);
			});

			// Handle WordPress native dismiss button
			jQuery(document).on('click', '.cuft-update-notice .notice-dismiss', function() {
				var $notice = jQuery(this).closest('.cuft-update-notice');
				var version = $notice.data('version');
				if (version) {
					dismissNotice(version, null);
				}
			});
		}

		// Vanilla JavaScript fallback
		document.addEventListener('click', function(e) {
			var target = e.target;

			// Custom dismiss button
			if (target.classList.contains('cuft-dismiss-notice')) {
				e.preventDefault();
				var version = target.getAttribute('data-version');
				dismissNotice(version, target);
			}

			// WordPress native dismiss button
			if (target.classList.contains('notice-dismiss')) {
				var notice = target.closest('.cuft-update-notice');
				if (notice) {
					var version = notice.getAttribute('data-version');
					if (version) {
						dismissNotice(version, null);
					}
				}
			}
		});
	}

	/**
	 * Dismiss notice via AJAX
	 *
	 * @param {string} version Version to dismiss
	 * @param {Element|jQuery} button Button element
	 */
	function dismissNotice(version, button) {
		var data = {
			action: 'cuft_dismiss_update_notice',
			nonce: cuftNotices.nonce,
			version: version
		};

		// Update button text
		if (button) {
			var originalText = button.textContent || button.text();
			if (button.jquery) {
				button.text(cuftNotices.dismissing);
			} else {
				button.textContent = cuftNotices.dismissing;
			}
		}

		// Send AJAX request (try fetch first)
		var sendRequest = function() {
			// Try fetch API
			if (window.fetch) {
				var formData = new FormData();
				for (var key in data) {
					formData.append(key, data[key]);
				}

				return fetch(cuftNotices.ajaxUrl, {
					method: 'POST',
					body: formData,
					credentials: 'same-origin'
				})
				.then(function(response) {
					return response.json();
				})
				.then(function(result) {
					if (result.success) {
						// Update button text
						if (button && button.jquery) {
							button.text(cuftNotices.dismissed);
						} else if (button) {
							button.textContent = cuftNotices.dismissed;
						}

						// Fade out and remove the notice
						var noticeElement = button ?
							(button.jquery ? button.closest('.notice') : button.closest('.notice')) :
							document.querySelector('.cuft-update-notice[data-version="' + version + '"]');

						if (noticeElement) {
							// Add fade-out animation
							if (noticeElement.jquery) {
								noticeElement.fadeOut(500, function() {
									noticeElement.remove();
								});
							} else {
								noticeElement.style.transition = 'opacity 0.5s';
								noticeElement.style.opacity = '0';
								setTimeout(function() {
									noticeElement.remove();
								}, 500);
							}
						}
					}
				})
				.catch(function(error) {
					console.warn('CUFT: Notice dismissal failed', error);
				});
			}

			// Fallback to jQuery AJAX
			if (window.jQuery) {
				jQuery.post(cuftNotices.ajaxUrl, data, function(response) {
					if (response.success) {
						// Update button text
						if (button && button.jquery) {
							button.text(cuftNotices.dismissed);
						} else if (button) {
							button.textContent = cuftNotices.dismissed;
						}

						// Fade out and remove the notice
						var $notice = button && button.jquery ?
							button.closest('.notice') :
							jQuery('.cuft-update-notice[data-version="' + version + '"]');

						if ($notice.length) {
							$notice.fadeOut(500, function() {
								$notice.remove();
							});
						}
					}
				}).fail(function() {
					console.warn('CUFT: Notice dismissal failed');
				});
				return;
			}

			// Fallback to XMLHttpRequest
			var xhr = new XMLHttpRequest();
			xhr.open('POST', cuftNotices.ajaxUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function() {
				if (xhr.status === 200) {
					try {
						var response = JSON.parse(xhr.responseText);
						if (response.success) {
							// Update button text
							if (button) {
								button.textContent = cuftNotices.dismissed;
							}

							// Fade out and remove the notice
							var noticeElement = button ?
								button.closest('.notice') :
								document.querySelector('.cuft-update-notice[data-version="' + version + '"]');

							if (noticeElement) {
								noticeElement.style.transition = 'opacity 0.5s';
								noticeElement.style.opacity = '0';
								setTimeout(function() {
									noticeElement.remove();
								}, 500);
							}
						}
					} catch (e) {
						console.warn('CUFT: Notice dismissal response parse failed');
					}
				}
			};

			var formData = [];
			for (var key in data) {
				formData.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
			}
			xhr.send(formData.join('&'));
		};

		sendRequest();
	}

	/**
	 * Handle retry update button
	 */
	function handleRetryUpdate() {
		// Try jQuery first
		if (window.jQuery) {
			jQuery(document).on('click', '.cuft-retry-update', function(e) {
				e.preventDefault();
				retryUpdate();
			});
		}

		// Vanilla JavaScript fallback
		document.addEventListener('click', function(e) {
			if (e.target.classList.contains('cuft-retry-update')) {
				e.preventDefault();
				retryUpdate();
			}
		});
	}

	/**
	 * Retry failed update
	 */
	function retryUpdate() {
		// Redirect to plugins page where user can retry
		window.location.href = cuftNotices.pluginsUrl || 'plugins.php';
	}

	/**
	 * Auto-refresh progress notices
	 */
	function autoRefreshProgress() {
		var progressNotice = document.querySelector('.cuft-update-progress-notice');
		if (!progressNotice) {
			return;
		}

		// Refresh every 2 seconds
		var refreshInterval = setInterval(function() {
			refreshProgressNotice();
		}, 2000);

		// Stop refreshing after 5 minutes
		setTimeout(function() {
			clearInterval(refreshInterval);
		}, 5 * 60 * 1000);
	}

	/**
	 * Refresh progress notice content
	 */
	function refreshProgressNotice() {
		var data = {
			action: 'cuft_update_status',
			nonce: cuftNotices.nonce
		};

		// Try fetch API
		if (window.fetch) {
			var params = new URLSearchParams(data);

			fetch(cuftNotices.ajaxUrl + '?' + params.toString(), {
				method: 'GET',
				credentials: 'same-origin'
			})
			.then(function(response) {
				return response.json();
			})
			.then(function(result) {
				if (result.success && result.data) {
					updateProgressDisplay(result.data);
				}
			})
			.catch(function(error) {
				console.warn('CUFT: Progress refresh failed', error);
			});
			return;
		}

		// Fallback to jQuery AJAX
		if (window.jQuery) {
			jQuery.get(cuftNotices.ajaxUrl, data, function(response) {
				if (response.success && response.data) {
					updateProgressDisplay(response.data);
				}
			}).fail(function() {
				console.warn('CUFT: Progress refresh failed');
			});
			return;
		}

		// Fallback to XMLHttpRequest
		var xhr = new XMLHttpRequest();
		var params = [];
		for (var key in data) {
			params.push(encodeURIComponent(key) + '=' + encodeURIComponent(data[key]));
		}
		xhr.open('GET', cuftNotices.ajaxUrl + '?' + params.join('&'), true);
		xhr.onload = function() {
			if (xhr.status === 200) {
				try {
					var response = JSON.parse(xhr.responseText);
					if (response.success && response.data) {
						updateProgressDisplay(response.data);
					}
				} catch (e) {
					console.warn('CUFT: Progress refresh parse failed');
				}
			}
		};
		xhr.send();
	}

	/**
	 * Update progress display
	 *
	 * @param {Object} progress Progress data
	 */
	function updateProgressDisplay(progress) {
		var notice = document.querySelector('.cuft-update-progress-notice');
		if (!notice) {
			return;
		}

		// Update message
		var messageEl = notice.querySelector('p:nth-of-type(2)');
		if (messageEl) {
			var percentageSpan = messageEl.querySelector('.cuft-progress-percentage');
			if (percentageSpan) {
				messageEl.childNodes[0].textContent = progress.message + ' ';
				percentageSpan.textContent = '(' + progress.percentage + '%)';
			} else {
				messageEl.innerHTML = progress.message + ' <span class="cuft-progress-percentage">(' + progress.percentage + '%)</span>';
			}
		}

		// Update progress bar
		var progressFill = notice.querySelector('.cuft-progress-fill');
		if (progressFill) {
			progressFill.style.width = progress.percentage + '%';
		}

		// If complete or failed, reload page
		if (progress.status === 'complete' || progress.status === 'failed') {
			setTimeout(function() {
				window.location.reload();
			}, 2000);
		}
	}

})();
