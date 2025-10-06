/**
 * Choice Universal Form Tracker - Update History Viewer
 *
 * Displays historical update log entries with filtering and pagination.
 * Shows all update-related events for audit and troubleshooting.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
    'use strict';

    /**
     * CUFT Update History Viewer Component
     *
     * Manages display and interaction with update history data
     */
    window.CUFTUpdateHistory = {
        // Configuration
        config: {
            ajaxUrl: window.cuftUpdater?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: window.cuftUpdater?.nonce || '',
            debug: window.cuftUpdater?.debug || false,
            itemsPerPage: 10,
            maxPages: 10
        },

        // History state
        state: {
            container: null,
            entries: [],
            filteredEntries: [],
            currentPage: 1,
            totalPages: 1,
            filter: {
                action: 'all',
                status: 'all',
                search: ''
            },
            isLoading: false
        },

        /**
         * Initialize history viewer
         */
        init: function(containerId) {
            try {
                const container = containerId ?
                    document.getElementById(containerId) :
                    document.querySelector('[data-cuft-history]');

                if (!container) {
                    this.log('History container not found');
                    return;
                }

                this.state.container = container;
                this.render();
                this.bindEvents();
                this.loadHistory();

                this.log('Update history initialized');
            } catch (error) {
                this.log('Initialization error: ' + error.message);
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            if (!this.state.container) {
                return;
            }

            // Refresh button
            this.state.container.addEventListener('click', function(e) {
                if (e.target.matches('.cuft-history-refresh, [data-action="refresh"]')) {
                    e.preventDefault();
                    self.loadHistory();
                }

                // Pagination
                if (e.target.matches('.cuft-history-page, [data-page]')) {
                    e.preventDefault();
                    const page = parseInt(e.target.dataset.page) || 1;
                    self.goToPage(page);
                }

                // View details
                if (e.target.matches('.cuft-history-details, [data-entry-id]')) {
                    e.preventDefault();
                    const entryId = e.target.dataset.entryId;
                    self.showEntryDetails(entryId);
                }

                // Clear filters
                if (e.target.matches('.cuft-history-clear-filters, [data-action="clear-filters"]')) {
                    e.preventDefault();
                    self.clearFilters();
                }
            });

            // Filter inputs
            this.state.container.addEventListener('change', function(e) {
                if (e.target.matches('[data-filter]')) {
                    const filterType = e.target.dataset.filter;
                    const value = e.target.value;
                    self.applyFilter(filterType, value);
                }
            });

            // Search input with debouncing
            this.state.container.addEventListener('input', function(e) {
                if (e.target.matches('[data-filter="search"]')) {
                    clearTimeout(self.searchTimeout);
                    self.searchTimeout = setTimeout(function() {
                        self.applyFilter('search', e.target.value);
                    }, 300);
                }
            });
        },

        /**
         * Render history viewer UI
         */
        render: function() {
            if (!this.state.container) {
                return;
            }

            const html = `
                <div class="cuft-history-viewer">
                    <div class="cuft-history-header">
                        <h3>Update History</h3>
                        <button type="button" class="button cuft-history-refresh" data-action="refresh">
                            <span class="dashicons dashicons-update"></span> Refresh
                        </button>
                    </div>

                    <div class="cuft-history-filters">
                        <div class="cuft-filter-group">
                            <label for="cuft-filter-action">Action:</label>
                            <select id="cuft-filter-action" data-filter="action">
                                <option value="all">All Actions</option>
                                <option value="check_started">Check Started</option>
                                <option value="check_completed">Check Completed</option>
                                <option value="update_started">Update Started</option>
                                <option value="download_started">Download Started</option>
                                <option value="download_completed">Download Completed</option>
                                <option value="backup_created">Backup Created</option>
                                <option value="install_started">Install Started</option>
                                <option value="install_completed">Install Completed</option>
                                <option value="rollback_started">Rollback Started</option>
                                <option value="rollback_completed">Rollback Completed</option>
                                <option value="error">Error</option>
                            </select>
                        </div>

                        <div class="cuft-filter-group">
                            <label for="cuft-filter-status">Status:</label>
                            <select id="cuft-filter-status" data-filter="status">
                                <option value="all">All Statuses</option>
                                <option value="success">Success</option>
                                <option value="failure">Failure</option>
                                <option value="warning">Warning</option>
                                <option value="info">Info</option>
                            </select>
                        </div>

                        <div class="cuft-filter-group">
                            <label for="cuft-filter-search">Search:</label>
                            <input type="text" id="cuft-filter-search" data-filter="search" placeholder="Search details...">
                        </div>

                        <button type="button" class="button cuft-history-clear-filters" data-action="clear-filters">
                            Clear Filters
                        </button>
                    </div>

                    <div class="cuft-history-content">
                        <div class="cuft-history-loading" style="display: none;">
                            <span class="spinner is-active"></span>
                            <p>Loading history...</p>
                        </div>

                        <div class="cuft-history-table"></div>

                        <div class="cuft-history-pagination"></div>
                    </div>
                </div>
            `;

            this.state.container.innerHTML = html;
        },

        /**
         * Load update history from server
         */
        loadHistory: function() {
            const self = this;

            if (this.state.isLoading) {
                return;
            }

            this.state.isLoading = true;
            this.showLoading(true);

            const data = new URLSearchParams({
                action: 'cuft_update_history',
                nonce: this.config.nonce,
                limit: 100 // Get larger set for client-side filtering
            });

            this.makeRequest('GET', data)
                .then(function(response) {
                    if (response.success && response.data) {
                        self.state.entries = response.data.entries || [];
                        self.applyFilters();
                        self.renderTable();
                    } else {
                        self.showError(response.data?.message || 'Failed to load history');
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
         * Apply filter
         */
        applyFilter: function(filterType, value) {
            try {
                this.state.filter[filterType] = value;
                this.applyFilters();
                this.state.currentPage = 1; // Reset to first page
                this.renderTable();
                this.log('Filter applied: ' + filterType + ' = ' + value);
            } catch (error) {
                this.log('Filter error: ' + error.message);
            }
        },

        /**
         * Apply all filters
         */
        applyFilters: function() {
            const self = this;

            this.state.filteredEntries = this.state.entries.filter(function(entry) {
                // Action filter
                if (self.state.filter.action !== 'all' &&
                    entry.action !== self.state.filter.action) {
                    return false;
                }

                // Status filter
                if (self.state.filter.status !== 'all' &&
                    entry.status !== self.state.filter.status) {
                    return false;
                }

                // Search filter
                if (self.state.filter.search) {
                    const search = self.state.filter.search.toLowerCase();
                    const searchFields = [
                        entry.action,
                        entry.status,
                        entry.details || '',
                        entry.message || '',
                        entry.version_from || '',
                        entry.version_to || ''
                    ].join(' ').toLowerCase();

                    if (searchFields.indexOf(search) === -1) {
                        return false;
                    }
                }

                return true;
            });

            // Update pagination
            this.state.totalPages = Math.ceil(
                this.state.filteredEntries.length / this.config.itemsPerPage
            );
        },

        /**
         * Clear all filters
         */
        clearFilters: function() {
            try {
                this.state.filter = {
                    action: 'all',
                    status: 'all',
                    search: ''
                };

                // Reset form values
                const actionSelect = this.state.container.querySelector('[data-filter="action"]');
                if (actionSelect) actionSelect.value = 'all';

                const statusSelect = this.state.container.querySelector('[data-filter="status"]');
                if (statusSelect) statusSelect.value = 'all';

                const searchInput = this.state.container.querySelector('[data-filter="search"]');
                if (searchInput) searchInput.value = '';

                this.applyFilters();
                this.state.currentPage = 1;
                this.renderTable();

                this.log('Filters cleared');
            } catch (error) {
                this.log('Clear filters error: ' + error.message);
            }
        },

        /**
         * Render history table
         */
        renderTable: function() {
            const tableContainer = this.state.container.querySelector('.cuft-history-table');
            if (!tableContainer) {
                return;
            }

            if (this.state.filteredEntries.length === 0) {
                tableContainer.innerHTML = '<p class="cuft-no-history">No history entries found.</p>';
                this.renderPagination();
                return;
            }

            // Get entries for current page
            const start = (this.state.currentPage - 1) * this.config.itemsPerPage;
            const end = start + this.config.itemsPerPage;
            const pageEntries = this.state.filteredEntries.slice(start, end);

            let html = '<table class="widefat cuft-history-table-el">';
            html += '<thead>';
            html += '<tr>';
            html += '<th>Date/Time</th>';
            html += '<th>Action</th>';
            html += '<th>Status</th>';
            html += '<th>Version</th>';
            html += '<th>Details</th>';
            html += '<th>Actions</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            pageEntries.forEach(function(entry, index) {
                const statusClass = 'cuft-status-' + entry.status;
                const rowClass = index % 2 === 0 ? 'alternate' : '';

                html += '<tr class="' + rowClass + ' ' + statusClass + '">';
                html += '<td>' + this.formatDateTime(entry.timestamp) + '</td>';
                html += '<td>' + this.formatAction(entry.action) + '</td>';
                html += '<td><span class="cuft-status-badge cuft-badge-' + entry.status + '">' +
                       this.formatStatus(entry.status) + '</span></td>';
                html += '<td>' + this.formatVersion(entry) + '</td>';
                html += '<td class="cuft-details-cell">' + this.truncate(entry.details || entry.message || '-', 50) + '</td>';
                html += '<td><button type="button" class="button button-small cuft-history-details" data-entry-id="' + entry.id + '">View</button></td>';
                html += '</tr>';
            }, this);

            html += '</tbody>';
            html += '</table>';

            tableContainer.innerHTML = html;

            // Render pagination
            this.renderPagination();
        },

        /**
         * Render pagination controls
         */
        renderPagination: function() {
            const paginationContainer = this.state.container.querySelector('.cuft-history-pagination');
            if (!paginationContainer) {
                return;
            }

            if (this.state.totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            let html = '<div class="cuft-pagination">';
            html += '<span class="cuft-page-info">Page ' + this.state.currentPage + ' of ' + this.state.totalPages + '</span>';

            // Previous button
            if (this.state.currentPage > 1) {
                html += '<button type="button" class="button cuft-history-page" data-page="' +
                       (this.state.currentPage - 1) + '">← Previous</button>';
            }

            // Page numbers (show max 5)
            const startPage = Math.max(1, this.state.currentPage - 2);
            const endPage = Math.min(this.state.totalPages, startPage + 4);

            for (let i = startPage; i <= endPage; i++) {
                const activeClass = i === this.state.currentPage ? 'button-primary' : '';
                html += '<button type="button" class="button ' + activeClass +
                       ' cuft-history-page" data-page="' + i + '">' + i + '</button>';
            }

            // Next button
            if (this.state.currentPage < this.state.totalPages) {
                html += '<button type="button" class="button cuft-history-page" data-page="' +
                       (this.state.currentPage + 1) + '">Next →</button>';
            }

            html += '</div>';

            paginationContainer.innerHTML = html;
        },

        /**
         * Go to specific page
         */
        goToPage: function(page) {
            try {
                if (page < 1 || page > this.state.totalPages) {
                    return;
                }

                this.state.currentPage = page;
                this.renderTable();
                this.log('Navigated to page ' + page);
            } catch (error) {
                this.log('Page navigation error: ' + error.message);
            }
        },

        /**
         * Show entry details in modal or expand
         */
        showEntryDetails: function(entryId) {
            try {
                const entry = this.state.filteredEntries.find(function(e) {
                    return e.id == entryId;
                });

                if (!entry) {
                    return;
                }

                // For now, use alert or console
                // In future, create a proper modal
                const details = [
                    'Date: ' + this.formatDateTime(entry.timestamp),
                    'Action: ' + this.formatAction(entry.action),
                    'Status: ' + this.formatStatus(entry.status),
                    'Version From: ' + (entry.version_from || 'N/A'),
                    'Version To: ' + (entry.version_to || 'N/A'),
                    'Details: ' + (entry.details || entry.message || 'N/A'),
                    'User ID: ' + (entry.user_id || 'N/A')
                ].join('\n');

                alert('Update Log Entry Details\n\n' + details);

                this.log('Showed details for entry ' + entryId);
            } catch (error) {
                this.log('Show details error: ' + error.message);
            }
        },

        /**
         * Show/hide loading state
         */
        showLoading: function(show) {
            const loadingEl = this.state.container.querySelector('.cuft-history-loading');
            const contentEl = this.state.container.querySelector('.cuft-history-table');

            if (show) {
                if (loadingEl) loadingEl.style.display = 'block';
                if (contentEl) contentEl.style.display = 'none';
            } else {
                if (loadingEl) loadingEl.style.display = 'none';
                if (contentEl) contentEl.style.display = 'block';
            }
        },

        /**
         * Show error message
         */
        showError: function(message) {
            const tableContainer = this.state.container.querySelector('.cuft-history-table');
            if (!tableContainer) {
                return;
            }

            const html = `
                <div class="cuft-history-error">
                    <span class="dashicons dashicons-warning"></span>
                    <p>${message}</p>
                    <button type="button" class="button cuft-history-refresh" data-action="refresh">
                        Try Again
                    </button>
                </div>
            `;

            tableContainer.innerHTML = html;
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
         * Format action for display
         */
        formatAction: function(action) {
            return action.replace(/_/g, ' ').replace(/\b\w/g, function(l) {
                return l.toUpperCase();
            });
        },

        /**
         * Format status for display
         */
        formatStatus: function(status) {
            return status.charAt(0).toUpperCase() + status.slice(1);
        },

        /**
         * Format version information
         */
        formatVersion: function(entry) {
            if (entry.version_from && entry.version_to) {
                return entry.version_from + ' → ' + entry.version_to;
            } else if (entry.version_to) {
                return entry.version_to;
            } else if (entry.version_from) {
                return entry.version_from;
            }
            return '-';
        },

        /**
         * Format date/time for display
         */
        formatDateTime: function(dateString) {
            try {
                const date = new Date(dateString);
                return date.toLocaleString();
            } catch (error) {
                return dateString;
            }
        },

        /**
         * Truncate text with ellipsis
         */
        truncate: function(text, length) {
            if (!text || text.length <= length) {
                return text;
            }
            return text.substring(0, length) + '...';
        },

        /**
         * Debug logging
         */
        log: function(message) {
            if (this.config.debug && console && console.log) {
                console.log('[CUFT History]', message);
            }
        },

        /**
         * Destroy history viewer
         */
        destroy: function() {
            try {
                if (this.state.container) {
                    this.state.container.innerHTML = '';
                }

                if (this.searchTimeout) {
                    clearTimeout(this.searchTimeout);
                }

                this.log('History viewer destroyed');
            } catch (error) {
                this.log('Destroy error: ' + error.message);
            }
        }
    };

    // Export for use in other components
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.CUFTUpdateHistory;
    }

})();
