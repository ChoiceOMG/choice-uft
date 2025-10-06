/**
 * Lazy Loader for Update History
 *
 * Implements infinite scroll and lazy loading for the update history table.
 * Loads more entries as user scrolls to bottom of the list.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.15.0
 */

(function() {
    'use strict';

    /**
     * Lazy Loader Class
     */
    class CUFTLazyLoader {
        /**
         * Constructor
         *
         * @param {string} containerId - ID of the history container element
         * @param {Object} options - Configuration options
         */
        constructor(containerId, options = {}) {
            this.container = document.getElementById(containerId);
            if (!this.container) {
                console.error('CUFT Lazy Loader: Container not found:', containerId);
                return;
            }

            this.options = Object.assign({
                pageSize: 10,
                threshold: 200, // pixels from bottom to trigger load
                ajaxUrl: window.cuftUpdater?.ajaxUrl || '/wp-admin/admin-ajax.php',
                nonce: window.cuftUpdater?.nonce || '',
                action: 'cuft_update_history',
                loadingClass: 'cuft-loading',
                emptyMessage: 'No more entries to load',
                errorMessage: 'Failed to load more entries. Please try again.',
                filters: {} // Additional filters to apply
            }, options);

            this.offset = 0;
            this.loading = false;
            this.hasMore = true;
            this.retryCount = 0;
            this.maxRetries = 3;

            this.init();
        }

        /**
         * Initialize lazy loader
         */
        init() {
            // Create loading indicator
            this.createLoadingIndicator();

            // Attach scroll event listener
            this.attachScrollListener();

            // Initial load if container is empty
            if (this.container.children.length === 0) {
                this.loadMore();
            }
        }

        /**
         * Create loading indicator element
         */
        createLoadingIndicator() {
            this.loadingEl = document.createElement('div');
            this.loadingEl.className = 'cuft-lazy-loader-indicator';
            this.loadingEl.innerHTML = `
                <div class="${this.options.loadingClass}">
                    <span class="spinner is-active"></span>
                    <span class="loading-text">Loading more entries...</span>
                </div>
            `;
            this.loadingEl.style.display = 'none';
            this.loadingEl.style.textAlign = 'center';
            this.loadingEl.style.padding = '20px';

            // Insert after container
            this.container.parentNode.insertBefore(
                this.loadingEl,
                this.container.nextSibling
            );
        }

        /**
         * Attach scroll event listener
         */
        attachScrollListener() {
            // Use passive listener for better performance
            const scrollHandler = this.debounce(() => {
                this.checkScroll();
            }, 100);

            // Listen to window scroll
            window.addEventListener('scroll', scrollHandler, { passive: true });

            // Also listen to container scroll if it's scrollable
            if (this.container.scrollHeight > this.container.clientHeight) {
                this.container.addEventListener('scroll', scrollHandler, { passive: true });
            }
        }

        /**
         * Check if user scrolled near bottom
         */
        checkScroll() {
            if (this.loading || !this.hasMore) {
                return;
            }

            const rect = this.container.getBoundingClientRect();
            const distanceFromBottom = rect.bottom - window.innerHeight;

            if (distanceFromBottom < this.options.threshold) {
                this.loadMore();
            }
        }

        /**
         * Load more entries
         */
        async loadMore() {
            if (this.loading || !this.hasMore) {
                return;
            }

            this.loading = true;
            this.showLoading();

            try {
                const params = new URLSearchParams({
                    action: this.options.action,
                    nonce: this.options.nonce,
                    limit: this.options.pageSize,
                    offset: this.offset,
                    ...this.options.filters
                });

                const response = await fetch(
                    `${this.options.ajaxUrl}?${params.toString()}`,
                    {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.success && data.data && data.data.entries) {
                    this.appendEntries(data.data.entries);
                    this.offset += data.data.entries.length;

                    // Check if there are more entries
                    const total = data.data.total || 0;
                    this.hasMore = this.offset < total;

                    if (!this.hasMore) {
                        this.showEmptyMessage();
                    }

                    // Reset retry count on success
                    this.retryCount = 0;

                } else {
                    throw new Error(data.data?.message || 'Invalid response');
                }

            } catch (error) {
                console.error('CUFT Lazy Loader: Load error', error);
                this.handleError(error);

            } finally {
                this.loading = false;
                this.hideLoading();
            }
        }

        /**
         * Append entries to container
         *
         * @param {Array} entries - Log entries to append
         */
        appendEntries(entries) {
            if (!entries || entries.length === 0) {
                return;
            }

            const fragment = document.createDocumentFragment();

            entries.forEach(entry => {
                const row = this.createEntryRow(entry);
                fragment.appendChild(row);
            });

            this.container.appendChild(fragment);

            // Trigger custom event
            this.container.dispatchEvent(new CustomEvent('cuftEntriesLoaded', {
                detail: { count: entries.length, offset: this.offset }
            }));
        }

        /**
         * Create HTML for a single entry row
         *
         * @param {Object} entry - Log entry data
         * @return {HTMLElement} Entry row element
         */
        createEntryRow(entry) {
            const row = document.createElement('tr');
            row.className = 'cuft-history-row';
            row.dataset.entryId = entry.id;

            // Format timestamp
            const timestamp = new Date(entry.timestamp);
            const formattedTime = timestamp.toLocaleString();

            // Status badge class
            const statusClass = this.getStatusClass(entry.status);

            row.innerHTML = `
                <td class="cuft-history-timestamp">${formattedTime}</td>
                <td class="cuft-history-action">${this.escapeHtml(entry.action || '-')}</td>
                <td class="cuft-history-status">
                    <span class="cuft-badge ${statusClass}">${this.escapeHtml(entry.status || 'unknown')}</span>
                </td>
                <td class="cuft-history-details">${this.escapeHtml(entry.details || '-')}</td>
            `;

            return row;
        }

        /**
         * Get CSS class for status badge
         *
         * @param {string} status - Entry status
         * @return {string} CSS class name
         */
        getStatusClass(status) {
            const statusMap = {
                'success': 'cuft-badge-success',
                'error': 'cuft-badge-error',
                'warning': 'cuft-badge-warning',
                'info': 'cuft-badge-info'
            };

            return statusMap[status] || 'cuft-badge-default';
        }

        /**
         * Escape HTML to prevent XSS
         *
         * @param {string} text - Text to escape
         * @return {string} Escaped text
         */
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Show loading indicator
         */
        showLoading() {
            if (this.loadingEl) {
                this.loadingEl.style.display = 'block';
                this.container.classList.add(this.options.loadingClass);
            }
        }

        /**
         * Hide loading indicator
         */
        hideLoading() {
            if (this.loadingEl) {
                this.loadingEl.style.display = 'none';
                this.container.classList.remove(this.options.loadingClass);
            }
        }

        /**
         * Show empty message when no more entries
         */
        showEmptyMessage() {
            if (this.loadingEl) {
                this.loadingEl.innerHTML = `
                    <div class="cuft-lazy-loader-empty">
                        <p>${this.options.emptyMessage}</p>
                    </div>
                `;
                this.loadingEl.style.display = 'block';
            }
        }

        /**
         * Handle load error with retry logic
         *
         * @param {Error} error - Error object
         */
        handleError(error) {
            this.retryCount++;

            if (this.retryCount < this.maxRetries) {
                // Retry with exponential backoff
                const delay = Math.pow(2, this.retryCount) * 1000;
                setTimeout(() => {
                    this.loadMore();
                }, delay);

            } else {
                // Show error message
                if (this.loadingEl) {
                    this.loadingEl.innerHTML = `
                        <div class="cuft-lazy-loader-error notice notice-error">
                            <p>${this.options.errorMessage}</p>
                            <button type="button" class="button button-small cuft-retry-btn">Retry</button>
                        </div>
                    `;
                    this.loadingEl.style.display = 'block';

                    // Attach retry button handler
                    const retryBtn = this.loadingEl.querySelector('.cuft-retry-btn');
                    if (retryBtn) {
                        retryBtn.addEventListener('click', () => {
                            this.retryCount = 0;
                            this.loadMore();
                        });
                    }
                }
            }
        }

        /**
         * Debounce function for scroll handler
         *
         * @param {Function} func - Function to debounce
         * @param {number} wait - Debounce delay in ms
         * @return {Function} Debounced function
         */
        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        /**
         * Reset loader to initial state
         */
        reset() {
            this.offset = 0;
            this.hasMore = true;
            this.retryCount = 0;
            this.container.innerHTML = '';
            if (this.loadingEl) {
                this.loadingEl.style.display = 'none';
            }
        }

        /**
         * Apply filters and reload
         *
         * @param {Object} filters - Filter parameters
         */
        applyFilters(filters) {
            this.options.filters = filters;
            this.reset();
            this.loadMore();
        }
    }

    // Expose to global scope
    window.CUFTLazyLoader = CUFTLazyLoader;

    // Auto-initialize if container exists
    document.addEventListener('DOMContentLoaded', function() {
        const historyContainer = document.getElementById('cuft-update-history-tbody');
        if (historyContainer && window.cuftUpdater) {
            window.cuftHistoryLoader = new CUFTLazyLoader('cuft-update-history-tbody', {
                pageSize: 15,
                threshold: 300
            });
        }
    });

})();
