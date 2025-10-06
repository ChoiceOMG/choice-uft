/**
 * Choice Universal Form Tracker - Update Status Dashboard Widget
 *
 * Provides a compact dashboard widget for displaying current update status.
 * Designed to be embedded in WordPress admin dashboard.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
    'use strict';

    /**
     * CUFT Update Widget Component
     *
     * Displays current plugin version, update availability, and quick actions
     */
    window.CUFTUpdateWidget = {
        // Configuration
        config: {
            ajaxUrl: window.cuftUpdater?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: window.cuftUpdater?.nonce || '',
            refreshInterval: 300000, // Refresh every 5 minutes
            debug: window.cuftUpdater?.debug || false
        },

        // Widget state
        state: {
            currentVersion: null,
            latestVersion: null,
            updateAvailable: false,
            lastCheck: null,
            isLoading: false,
            refreshTimer: null
        },

        /**
         * Initialize the widget
         */
        init: function() {
            try {
                if (!this.config.nonce) {
                    this.log('Missing nonce. Widget functionality limited.');
                    return;
                }

                // Find widget container
                const container = document.querySelector('[data-cuft-widget="update-status"]');
                if (!container) {
                    this.log('Widget container not found');
                    return;
                }

                this.container = container;
                this.render();
                this.bindEvents();
                this.loadStatus();

                // Set up auto-refresh
                if (this.config.refreshInterval > 0) {
                    this.startAutoRefresh();
                }

                this.log('Update widget initialized');
            } catch (error) {
                this.log('Initialization error: ' + error.message);
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            if (!this.container) {
                return;
            }

            // Refresh button
            this.container.addEventListener('click', function(e) {
                if (e.target.matches('.cuft-widget-refresh, [data-action="refresh"]')) {
                    e.preventDefault();
                    self.loadStatus(true);
                }

                // Update now button
                if (e.target.matches('.cuft-widget-update, [data-action="update"]')) {
                    e.preventDefault();
                    // Delegate to main updater if available
                    if (window.CUFTUpdater && typeof window.CUFTUpdater.performUpdate === 'function') {
                        window.CUFTUpdater.performUpdate(self.state.latestVersion);
                    } else {
                        self.log('Main updater not available');
                    }
                }

                // View details link
                if (e.target.matches('.cuft-widget-details, [data-action="details"]')) {
                    e.preventDefault();
                    self.showDetails();
                }
            });
        },

        /**
         * Render widget UI
         */
        render: function() {
            if (!this.container) {
                return;
            }

            const html = `
                <div class="cuft-update-widget">
                    <div class="cuft-widget-header">
                        <h3>Plugin Updates</h3>
                        <button type="button" class="cuft-widget-refresh" data-action="refresh" title="Refresh">
                            <span class="dashicons dashicons-update"></span>
                        </button>
                    </div>
                    <div class="cuft-widget-content">
                        <div class="cuft-widget-loading">
                            <span class="spinner is-active"></span>
                            <p>Checking for updates...</p>
                        </div>
                    </div>
                </div>
            `;

            this.container.innerHTML = html;
        },

        /**
         * Load current update status
         */
        loadStatus: function(force) {
            const self = this;

            if (this.state.isLoading) {
                return;
            }

            this.state.isLoading = true;
            this.showLoading(true);

            const data = new URLSearchParams({
                action: 'cuft_update_status',
                nonce: this.config.nonce
            });

            if (force) {
                data.append('force', 'true');
            }

            // Use native fetch with fallbacks
            this.makeRequest('GET', data)
                .then(function(response) {
                    if (response.success && response.data) {
                        self.updateStatus(response.data);
                    } else {
                        self.showError(response.data?.message || 'Failed to load status');
                    }
                })
                .catch(function(error) {
                    self.showError('Network error: ' + error.message);
                })
                .finally(function() {
                    self.state.isLoading = false;
                    self.showLoading(false);
                });
        },

        /**
         * Update widget with status data
         */
        updateStatus: function(data) {
            this.state.currentVersion = data.current_version;
            this.state.latestVersion = data.latest_version;
            this.state.updateAvailable = data.update_available || false;
            this.state.lastCheck = data.last_check;

            this.renderStatus();
        },

        /**
         * Render status content
         */
        renderStatus: function() {
            const contentEl = this.container.querySelector('.cuft-widget-content');
            if (!contentEl) {
                return;
            }

            let html = '<div class="cuft-widget-status">';

            // Version information
            html += '<div class="cuft-widget-version">';
            html += '<span class="label">Current:</span> ';
            html += '<strong>' + (this.state.currentVersion || 'Unknown') + '</strong>';
            html += '</div>';

            if (this.state.updateAvailable) {
                // Update available
                html += '<div class="cuft-widget-update-available">';
                html += '<div class="cuft-widget-badge">Update Available</div>';
                html += '<div class="cuft-widget-version">';
                html += '<span class="label">Latest:</span> ';
                html += '<strong>' + this.state.latestVersion + '</strong>';
                html += '</div>';
                html += '<div class="cuft-widget-actions">';
                html += '<button type="button" class="button button-primary button-small cuft-widget-update" data-action="update">';
                html += 'Update Now';
                html += '</button>';
                html += '<a href="#" class="cuft-widget-details" data-action="details">View Details</a>';
                html += '</div>';
                html += '</div>';
            } else {
                // Up to date
                html += '<div class="cuft-widget-up-to-date">';
                html += '<span class="dashicons dashicons-yes-alt"></span> ';
                html += '<span>Plugin is up to date</span>';
                html += '</div>';
            }

            // Last check time
            if (this.state.lastCheck) {
                html += '<div class="cuft-widget-last-check">';
                html += '<small>Last checked: ' + this.formatDate(this.state.lastCheck) + '</small>';
                html += '</div>';
            }

            html += '</div>';

            contentEl.innerHTML = html;
        },

        /**
         * Show details (redirect to settings page)
         */
        showDetails: function() {
            try {
                // Navigate to plugin settings page
                const settingsUrl = window.cuftUpdater?.settingsUrl || 'options-general.php?page=cuft-settings&tab=updates';
                window.location.href = settingsUrl;
            } catch (error) {
                this.log('Failed to navigate: ' + error.message);
            }
        },

        /**
         * Show/hide loading state
         */
        showLoading: function(show) {
            const loadingEl = this.container.querySelector('.cuft-widget-loading');
            const statusEl = this.container.querySelector('.cuft-widget-status');

            if (show) {
                if (loadingEl) loadingEl.style.display = 'block';
                if (statusEl) statusEl.style.display = 'none';
            } else {
                if (loadingEl) loadingEl.style.display = 'none';
                if (statusEl) statusEl.style.display = 'block';
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const contentEl = this.container.querySelector('.cuft-widget-content');
            if (!contentEl) {
                return;
            }

            const html = `
                <div class="cuft-widget-error">
                    <span class="dashicons dashicons-warning"></span>
                    <p>${message}</p>
                    <button type="button" class="button button-small cuft-widget-refresh" data-action="refresh">
                        Try Again
                    </button>
                </div>
            `;

            contentEl.innerHTML = html;
        },

        /**
         * Make AJAX request with fallbacks
         */
        makeRequest: function(method, data) {
            const self = this;

            // Try native fetch first
            if (typeof fetch !== 'undefined') {
                const url = method === 'GET' ?
                    this.config.ajaxUrl + '?' + data.toString() :
                    this.config.ajaxUrl;

                const options = {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };

                if (method === 'POST') {
                    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    options.body = data.toString();
                }

                return fetch(url, options).then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                });
            }

            // Fallback to XMLHttpRequest
            return new Promise(function(resolve, reject) {
                try {
                    const xhr = new XMLHttpRequest();
                    const url = method === 'GET' ?
                        self.config.ajaxUrl + '?' + data.toString() :
                        self.config.ajaxUrl;

                    xhr.open(method, url, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                resolve(JSON.parse(xhr.responseText));
                            } catch (e) {
                                reject(new Error('Invalid JSON response'));
                            }
                        } else {
                            reject(new Error('Request failed: ' + xhr.status));
                        }
                    };

                    xhr.onerror = function() {
                        reject(new Error('Network error'));
                    };

                    xhr.send(method === 'POST' ? data.toString() : null);
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Start auto-refresh timer
         */
        startAutoRefresh: function() {
            const self = this;

            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
            }

            this.state.refreshTimer = setInterval(function() {
                if (!self.state.isLoading) {
                    self.loadStatus(false);
                }
            }, this.config.refreshInterval);
        },

        /**
         * Stop auto-refresh timer
         */
        stopAutoRefresh: function() {
            if (this.state.refreshTimer) {
                clearInterval(this.state.refreshTimer);
                this.state.refreshTimer = null;
            }
        },

        /**
         * Format date for display
         */
        formatDate: function(dateString) {
            try {
                const date = new Date(dateString);
                const now = new Date();
                const diff = Math.floor((now - date) / 1000); // seconds

                if (diff < 60) {
                    return 'just now';
                } else if (diff < 3600) {
                    const mins = Math.floor(diff / 60);
                    return mins + ' minute' + (mins > 1 ? 's' : '') + ' ago';
                } else if (diff < 86400) {
                    const hours = Math.floor(diff / 3600);
                    return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago';
                } else {
                    return date.toLocaleDateString();
                }
            } catch (error) {
                return dateString;
            }
        },

        /**
         * Debug logging
         */
        log: function(message) {
            if (this.config.debug && console && console.log) {
                console.log('[CUFT Update Widget]', message);
            }
        },

        /**
         * Destroy widget
         */
        destroy: function() {
            this.stopAutoRefresh();

            if (this.container) {
                this.container.innerHTML = '';
            }

            this.log('Update widget destroyed');
        }
    };

    // Auto-initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.CUFTUpdateWidget.init();
        });
    } else {
        window.CUFTUpdateWidget.init();
    }

    // jQuery fallback for WordPress environments
    if (window.jQuery) {
        jQuery(document).ready(function() {
            if (!window.CUFTUpdateWidget.container) {
                window.CUFTUpdateWidget.init();
            }
        });
    }

})();
