/**
 * Choice Universal Form Tracker - Update Manager JavaScript
 *
 * Handles all frontend interactions for the one-click automated update feature.
 * Implements proper nonce handling and fallback patterns per constitution.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
    'use strict';

    /**
     * CUFT Updater Manager
     *
     * Main controller for update functionality
     */
    window.CUFTUpdater = {
        // Configuration
        config: {
            ajaxUrl: window.cuftUpdater?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: window.cuftUpdater?.nonce || '',
            debug: window.cuftUpdater?.debug || false,
            updateCheckInterval: 2000, // Poll status every 2 seconds during update
            maxRetries: 3,
            retryDelay: 1000
        },

        // Current update state
        state: {
            isUpdating: false,
            updateId: null,
            currentVersion: null,
            latestVersion: null
        },

        /**
         * Initialize the updater
         */
        init: function() {
            // Ensure we have required configuration
            if (!this.config.nonce) {
                console.error('CUFT Updater: Missing nonce. Please ensure cuftUpdater object is properly localized.');
                return;
            }

            // Bind event handlers
            this.bindEventHandlers();

            // Check for updates on page load if on settings page
            if (this.isSettingsPage()) {
                this.checkForUpdates(false);
            }

            this.log('CUFT Updater initialized');
        },

        /**
         * Bind event handlers
         */
        bindEventHandlers: function() {
            const self = this;

            // Check for updates button
            document.addEventListener('click', function(e) {
                if (e.target.matches('.cuft-check-updates, [data-action="cuft-check-updates"]')) {
                    e.preventDefault();
                    self.checkForUpdates(true);
                }

                // Perform update button
                if (e.target.matches('.cuft-perform-update, [data-action="cuft-perform-update"]')) {
                    e.preventDefault();
                    const version = e.target.dataset.version || 'latest';
                    self.performUpdate(version);
                }

                // Cancel/rollback update button
                if (e.target.matches('.cuft-cancel-update, [data-action="cuft-cancel-update"]')) {
                    e.preventDefault();
                    self.cancelUpdate();
                }

                // View update history
                if (e.target.matches('.cuft-view-history, [data-action="cuft-view-history"]')) {
                    e.preventDefault();
                    self.viewUpdateHistory();
                }
            });

            // Settings form submission
            const settingsForm = document.getElementById('cuft-update-settings-form');
            if (settingsForm) {
                settingsForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    self.saveSettings(new FormData(settingsForm));
                });
            }
        },

        /**
         * Check for updates
         *
         * @param {boolean} force Force check (bypass cache)
         */
        checkForUpdates: function(force) {
            const self = this;

            // Show loading state
            this.showLoading('Checking for updates...');

            // Prepare request data
            const data = new URLSearchParams({
                action: 'cuft_check_update',
                nonce: this.config.nonce,
                force: force ? 'true' : 'false'
            });

            // Make AJAX request with fallback
            this.makeRequest('POST', data)
                .then(function(response) {
                    if (response.success) {
                        self.handleUpdateCheckResponse(response.data);
                    } else {
                        self.showError(response.data.message || 'Failed to check for updates');
                    }
                })
                .catch(function(error) {
                    self.showError('Network error: ' + error.message);
                })
                .finally(function() {
                    self.hideLoading();
                });
        },

        /**
         * Perform update
         *
         * @param {string} version Target version (or 'latest')
         */
        performUpdate: function(version) {
            const self = this;

            if (this.state.isUpdating) {
                this.showWarning('Update already in progress');
                return;
            }

            // Confirm update
            if (!confirm('Are you sure you want to update the plugin? A backup will be created automatically.')) {
                return;
            }

            // Set updating state
            this.state.isUpdating = true;

            // Show progress
            this.showProgress('Starting update...', 0);

            // Prepare request data
            const data = new URLSearchParams({
                action: 'cuft_perform_update',
                nonce: this.config.nonce,
                version: version || 'latest',
                backup: 'true'
            });

            // Start update
            this.makeRequest('POST', data)
                .then(function(response) {
                    if (response.success) {
                        self.state.updateId = response.data.update_id;
                        self.monitorUpdateProgress();
                    } else {
                        throw new Error(response.data.message || 'Failed to start update');
                    }
                })
                .catch(function(error) {
                    self.state.isUpdating = false;
                    self.showError('Update failed: ' + error.message);
                    self.hideProgress();
                });
        },

        /**
         * Monitor update progress
         */
        monitorUpdateProgress: function() {
            const self = this;

            if (!this.state.isUpdating) {
                return;
            }

            // Get status
            const data = new URLSearchParams({
                action: 'cuft_update_status',
                nonce: this.config.nonce,
                update_id: this.state.updateId || ''
            });

            this.makeRequest('GET', data)
                .then(function(response) {
                    if (response.success) {
                        self.handleUpdateStatus(response.data);

                        // Continue monitoring if not complete
                        if (response.data.status !== 'complete' && response.data.status !== 'failed') {
                            setTimeout(function() {
                                self.monitorUpdateProgress();
                            }, self.config.updateCheckInterval);
                        }
                    }
                })
                .catch(function(error) {
                    self.log('Status check error: ' + error.message);
                    // Continue monitoring even on error
                    setTimeout(function() {
                        self.monitorUpdateProgress();
                    }, self.config.updateCheckInterval * 2);
                });
        },

        /**
         * Cancel/rollback update
         */
        cancelUpdate: function() {
            const self = this;

            if (!this.state.updateId) {
                this.showWarning('No update in progress');
                return;
            }

            if (!confirm('Are you sure you want to cancel the update and rollback to the previous version?')) {
                return;
            }

            // Show loading
            this.showLoading('Rolling back update...');

            // Prepare request
            const data = new URLSearchParams({
                action: 'cuft_rollback_update',
                nonce: this.config.nonce,
                update_id: this.state.updateId,
                reason: 'User cancelled'
            });

            this.makeRequest('POST', data)
                .then(function(response) {
                    if (response.success) {
                        self.showSuccess('Update cancelled and previous version restored');
                        self.state.isUpdating = false;
                        self.state.updateId = null;
                    } else {
                        self.showError(response.data.message || 'Failed to rollback update');
                    }
                })
                .catch(function(error) {
                    self.showError('Rollback failed: ' + error.message);
                })
                .finally(function() {
                    self.hideLoading();
                    self.hideProgress();
                });
        },

        /**
         * View update history
         */
        viewUpdateHistory: function() {
            const self = this;

            // Show loading
            this.showLoading('Loading update history...');

            // Get history
            const data = new URLSearchParams({
                action: 'cuft_update_history',
                nonce: this.config.nonce,
                limit: '10',
                offset: '0'
            });

            this.makeRequest('GET', data)
                .then(function(response) {
                    if (response.success) {
                        self.displayUpdateHistory(response.data);
                    } else {
                        self.showError(response.data.message || 'Failed to load history');
                    }
                })
                .catch(function(error) {
                    self.showError('Failed to load history: ' + error.message);
                })
                .finally(function() {
                    self.hideLoading();
                });
        },

        /**
         * Save update settings
         *
         * @param {FormData} formData Form data to save
         */
        saveSettings: function(formData) {
            const self = this;

            // Show loading
            this.showLoading('Saving settings...');

            // Add action and nonce
            formData.append('action', 'cuft_update_settings');
            formData.append('nonce', this.config.nonce);

            // Convert FormData to URLSearchParams
            const data = new URLSearchParams(formData);

            this.makeRequest('POST', data)
                .then(function(response) {
                    if (response.success) {
                        self.showSuccess('Settings saved successfully');
                        self.updateSettingsDisplay(response.data.settings);
                    } else {
                        self.showError(response.data.message || 'Failed to save settings');
                    }
                })
                .catch(function(error) {
                    self.showError('Failed to save settings: ' + error.message);
                })
                .finally(function() {
                    self.hideLoading();
                });
        },

        /**
         * Make AJAX request with fallback pattern
         *
         * @param {string} method Request method (GET or POST)
         * @param {URLSearchParams} data Request data
         * @returns {Promise}
         */
        makeRequest: function(method, data) {
            const self = this;

            // Try native fetch first
            if (window.fetch) {
                const options = {
                    method: method,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };

                if (method === 'POST') {
                    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    options.body = data;
                } else {
                    // For GET, append to URL
                    const url = this.config.ajaxUrl + '?' + data.toString();
                    return fetch(url, options).then(function(response) {
                        return response.json();
                    });
                }

                return fetch(this.config.ajaxUrl, options)
                    .then(function(response) {
                        return response.json();
                    });
            }

            // Fallback to jQuery if available
            if (window.jQuery) {
                return new Promise(function(resolve, reject) {
                    jQuery.ajax({
                        url: self.config.ajaxUrl,
                        type: method,
                        data: data.toString(),
                        dataType: 'json',
                        success: resolve,
                        error: function(xhr, status, error) {
                            reject(new Error(error || status));
                        }
                    });
                });
            }

            // Fallback to XMLHttpRequest
            return new Promise(function(resolve, reject) {
                const xhr = new XMLHttpRequest();

                if (method === 'GET') {
                    xhr.open(method, self.config.ajaxUrl + '?' + data.toString(), true);
                } else {
                    xhr.open(method, self.config.ajaxUrl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                }

                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.onload = function() {
                    if (xhr.status >= 200 && xhr.status < 300) {
                        try {
                            resolve(JSON.parse(xhr.responseText));
                        } catch (e) {
                            reject(new Error('Invalid JSON response'));
                        }
                    } else {
                        reject(new Error('Request failed with status: ' + xhr.status));
                    }
                };

                xhr.onerror = function() {
                    reject(new Error('Network error'));
                };

                if (method === 'POST') {
                    xhr.send(data.toString());
                } else {
                    xhr.send();
                }
            });
        },

        /**
         * Handle update check response
         */
        handleUpdateCheckResponse: function(data) {
            this.state.currentVersion = data.current_version;
            this.state.latestVersion = data.latest_version;

            const container = document.getElementById('cuft-update-status');
            if (!container) {
                return;
            }

            if (data.update_available) {
                let html = '<div class="cuft-update-available">';
                html += '<h3>Update Available!</h3>';
                html += '<p>Current version: <strong>' + data.current_version + '</strong></p>';
                html += '<p>Latest version: <strong>' + data.latest_version + '</strong></p>';

                if (data.changelog) {
                    html += '<div class="cuft-changelog">';
                    html += '<h4>What\'s New:</h4>';
                    html += '<div class="cuft-changelog-content">' + data.changelog + '</div>';
                    html += '</div>';
                }

                html += '<p>Download size: ' + (data.download_size || 'Unknown') + '</p>';
                html += '<button class="button button-primary cuft-perform-update" data-version="' + data.latest_version + '">Update Now</button>';
                html += '</div>';

                container.innerHTML = html;
            } else {
                container.innerHTML = '<div class="cuft-up-to-date">' +
                    '<p>✓ You have the latest version (' + data.current_version + ')</p>' +
                    '<p class="description">Last checked: ' + this.formatDate(data.last_check) + '</p>' +
                    '</div>';
            }
        },

        /**
         * Handle update status
         */
        handleUpdateStatus: function(status) {
            // Update progress bar
            this.showProgress(status.message, status.percentage || 0);

            // Handle completion
            if (status.status === 'complete') {
                this.state.isUpdating = false;
                this.showSuccess('Update completed successfully! New version: ' + status.new_version);
                this.hideProgress();

                // Reload page after 3 seconds
                setTimeout(function() {
                    window.location.reload();
                }, 3000);
            }

            // Handle failure
            if (status.status === 'failed') {
                this.state.isUpdating = false;
                this.showError('Update failed: ' + (status.error || 'Unknown error'));

                if (status.rollback_status === 'complete') {
                    this.showWarning('Previous version has been restored');
                }

                this.hideProgress();
            }
        },

        /**
         * Display update history
         */
        displayUpdateHistory: function(data) {
            const container = document.getElementById('cuft-update-history');
            if (!container) {
                // Create modal or inline display
                console.table(data.entries);
                return;
            }

            let html = '<h3>Update History</h3>';
            html += '<table class="widefat">';
            html += '<thead><tr><th>Date</th><th>Action</th><th>Status</th><th>Details</th></tr></thead>';
            html += '<tbody>';

            data.entries.forEach(function(entry) {
                const statusClass = entry.status === 'success' ? 'cuft-success' :
                                   entry.status === 'error' ? 'cuft-error' : 'cuft-info';

                html += '<tr class="' + statusClass + '">';
                html += '<td>' + this.formatDate(entry.timestamp) + '</td>';
                html += '<td>' + entry.action + '</td>';
                html += '<td>' + entry.status + '</td>';
                html += '<td>' + (entry.details || entry.message || '-') + '</td>';
                html += '</tr>';
            }, this);

            html += '</tbody></table>';

            container.innerHTML = html;
        },

        /**
         * Update settings display
         */
        updateSettingsDisplay: function(settings) {
            // Update form values if needed
            const form = document.getElementById('cuft-update-settings-form');
            if (!form) {
                return;
            }

            // Update next scheduled check display
            const nextCheck = document.getElementById('cuft-next-check');
            if (nextCheck && settings.next_scheduled_check) {
                nextCheck.textContent = 'Next check: ' + this.formatDate(settings.next_scheduled_check);
            }
        },

        /**
         * UI Helper: Show loading
         */
        showLoading: function(message) {
            const container = this.getNoticeContainer();
            container.innerHTML = '<div class="notice notice-info"><p><span class="spinner is-active"></span> ' + message + '</p></div>';
        },

        /**
         * UI Helper: Hide loading
         */
        hideLoading: function() {
            const container = this.getNoticeContainer();
            const loading = container.querySelector('.notice-info');
            if (loading) {
                loading.remove();
            }
        },

        /**
         * UI Helper: Show progress
         */
        showProgress: function(message, percentage) {
            let progress = document.getElementById('cuft-update-progress');

            if (!progress) {
                const container = this.getNoticeContainer();
                progress = document.createElement('div');
                progress.id = 'cuft-update-progress';
                progress.className = 'cuft-progress-container';
                container.appendChild(progress);
            }

            progress.innerHTML =
                '<div class="cuft-progress-message">' + message + '</div>' +
                '<div class="cuft-progress-bar">' +
                '<div class="cuft-progress-fill" style="width: ' + percentage + '%"></div>' +
                '</div>' +
                '<div class="cuft-progress-percentage">' + percentage + '%</div>';
        },

        /**
         * UI Helper: Hide progress
         */
        hideProgress: function() {
            const progress = document.getElementById('cuft-update-progress');
            if (progress) {
                progress.remove();
            }
        },

        /**
         * UI Helper: Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * UI Helper: Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * UI Helper: Show warning message
         */
        showWarning: function(message) {
            this.showNotice(message, 'warning');
        },

        /**
         * UI Helper: Show notice
         */
        showNotice: function(message, type) {
            const container = this.getNoticeContainer();
            const notice = document.createElement('div');
            notice.className = 'notice notice-' + type + ' is-dismissible';
            notice.innerHTML = '<p>' + message + '</p><button type="button" class="notice-dismiss"></button>';

            container.appendChild(notice);

            // Auto-dismiss after 5 seconds for success messages
            if (type === 'success') {
                setTimeout(function() {
                    notice.remove();
                }, 5000);
            }

            // Handle dismiss button
            const dismissBtn = notice.querySelector('.notice-dismiss');
            if (dismissBtn) {
                dismissBtn.addEventListener('click', function() {
                    notice.remove();
                });
            }
        },

        /**
         * Get notice container
         */
        getNoticeContainer: function() {
            let container = document.getElementById('cuft-notices');

            if (!container) {
                // Try to find suitable container
                const wrap = document.querySelector('.wrap');
                if (wrap) {
                    container = document.createElement('div');
                    container.id = 'cuft-notices';
                    wrap.insertBefore(container, wrap.firstChild.nextSibling);
                } else {
                    // Fallback to body
                    container = document.createElement('div');
                    container.id = 'cuft-notices';
                    container.style.position = 'fixed';
                    container.style.top = '32px';
                    container.style.right = '20px';
                    container.style.zIndex = '9999';
                    document.body.appendChild(container);
                }
            }

            return container;
        },

        /**
         * Check if on settings page
         */
        isSettingsPage: function() {
            return window.location.href.indexOf('page=cuft-settings') > -1 ||
                   document.querySelector('.cuft-settings-page') !== null;
        },

        /**
         * Format date
         */
        formatDate: function(dateString) {
            if (!dateString) {
                return 'Never';
            }

            try {
                const date = new Date(dateString);
                return date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
            } catch (e) {
                return dateString;
            }
        },

        /**
         * Debug logging
         */
        log: function(message) {
            if (this.config.debug) {
                console.log('[CUFT Updater]', message);
            }
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.CUFTUpdater.init();
        });
    } else {
        // DOM already loaded
        window.CUFTUpdater.init();
    }

})();