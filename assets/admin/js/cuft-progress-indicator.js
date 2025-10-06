/**
 * Choice Universal Form Tracker - Update Progress Indicator Component
 *
 * Provides a visual progress indicator for update operations.
 * Shows real-time status updates during the update process.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
    'use strict';

    /**
     * CUFT Progress Indicator Component
     *
     * Displays update progress with percentage, stage, and messages
     */
    window.CUFTProgressIndicator = {
        // Configuration
        config: {
            debug: window.cuftUpdater?.debug || false,
            animationDuration: 300, // ms for smooth transitions
            stages: {
                idle: { label: 'Ready', icon: 'dashicons-yes', color: '#46b450' },
                checking: { label: 'Checking for updates', icon: 'dashicons-update', color: '#00a0d2' },
                downloading: { label: 'Downloading', icon: 'dashicons-download', color: '#00a0d2' },
                backing_up: { label: 'Creating backup', icon: 'dashicons-backup', color: '#ffb900' },
                installing: { label: 'Installing', icon: 'dashicons-admin-plugins', color: '#00a0d2' },
                verifying: { label: 'Verifying', icon: 'dashicons-yes-alt', color: '#00a0d2' },
                complete: { label: 'Complete', icon: 'dashicons-yes', color: '#46b450' },
                failed: { label: 'Failed', icon: 'dashicons-warning', color: '#dc3232' },
                rolling_back: { label: 'Rolling back', icon: 'dashicons-undo', color: '#ffb900' }
            }
        },

        // Progress state
        state: {
            container: null,
            currentStage: 'idle',
            percentage: 0,
            message: '',
            isVisible: false,
            animationFrame: null
        },

        /**
         * Initialize progress indicator
         */
        init: function(containerId) {
            try {
                // Find or create container
                const container = containerId ?
                    document.getElementById(containerId) :
                    document.querySelector('[data-cuft-progress]');

                if (!container) {
                    this.log('Progress container not found, creating default');
                    this.createContainer();
                } else {
                    this.state.container = container;
                }

                this.render();
                this.log('Progress indicator initialized');
            } catch (error) {
                this.log('Initialization error: ' + error.message);
            }
        },

        /**
         * Create default container
         */
        createContainer: function() {
            const container = document.createElement('div');
            container.id = 'cuft-progress-indicator';
            container.setAttribute('data-cuft-progress', '');
            container.style.display = 'none';

            // Append to body or settings page
            const settingsPage = document.querySelector('.cuft-settings-page, .wrap');
            if (settingsPage) {
                settingsPage.appendChild(container);
            } else {
                document.body.appendChild(container);
            }

            this.state.container = container;
        },

        /**
         * Render progress indicator UI
         */
        render: function() {
            if (!this.state.container) {
                return;
            }

            const html = `
                <div class="cuft-progress-wrapper" style="display: none;">
                    <div class="cuft-progress-modal">
                        <div class="cuft-progress-header">
                            <span class="cuft-progress-icon dashicons"></span>
                            <h3 class="cuft-progress-title">Update in Progress</h3>
                        </div>
                        <div class="cuft-progress-body">
                            <div class="cuft-progress-stage"></div>
                            <div class="cuft-progress-bar-container">
                                <div class="cuft-progress-bar">
                                    <div class="cuft-progress-fill" style="width: 0%;"></div>
                                </div>
                                <div class="cuft-progress-percentage">0%</div>
                            </div>
                            <div class="cuft-progress-message"></div>
                            <div class="cuft-progress-details"></div>
                        </div>
                        <div class="cuft-progress-footer">
                            <button type="button" class="button cuft-progress-cancel" style="display: none;">
                                Cancel Update
                            </button>
                        </div>
                    </div>
                    <div class="cuft-progress-overlay"></div>
                </div>
            `;

            this.state.container.innerHTML = html;
        },

        /**
         * Show progress indicator
         */
        show: function(stage, percentage, message) {
            try {
                if (!this.state.container) {
                    this.init();
                }

                const wrapper = this.state.container.querySelector('.cuft-progress-wrapper');
                if (wrapper) {
                    wrapper.style.display = 'flex';
                    this.state.isVisible = true;
                }

                // Update progress
                if (stage !== undefined) {
                    this.updateStage(stage);
                }

                if (percentage !== undefined) {
                    this.updatePercentage(percentage);
                }

                if (message) {
                    this.updateMessage(message);
                }

                this.log('Progress shown: ' + stage + ' - ' + percentage + '%');
            } catch (error) {
                this.log('Show error: ' + error.message);
            }
        },

        /**
         * Hide progress indicator
         */
        hide: function() {
            try {
                const wrapper = this.state.container?.querySelector('.cuft-progress-wrapper');
                if (wrapper) {
                    wrapper.style.display = 'none';
                    this.state.isVisible = false;
                }

                // Reset state
                this.reset();
                this.log('Progress hidden');
            } catch (error) {
                this.log('Hide error: ' + error.message);
            }
        },

        /**
         * Update progress stage
         */
        updateStage: function(stage) {
            try {
                if (!this.state.container) {
                    return;
                }

                this.state.currentStage = stage;
                const stageConfig = this.config.stages[stage] || this.config.stages.idle;

                // Update icon
                const iconEl = this.state.container.querySelector('.cuft-progress-icon');
                if (iconEl) {
                    iconEl.className = 'cuft-progress-icon dashicons ' + stageConfig.icon;
                    iconEl.style.color = stageConfig.color;
                }

                // Update stage label
                const stageEl = this.state.container.querySelector('.cuft-progress-stage');
                if (stageEl) {
                    stageEl.textContent = stageConfig.label;
                }

                // Update progress bar color
                const fillEl = this.state.container.querySelector('.cuft-progress-fill');
                if (fillEl) {
                    fillEl.style.backgroundColor = stageConfig.color;
                }

                this.log('Stage updated: ' + stage);
            } catch (error) {
                this.log('Update stage error: ' + error.message);
            }
        },

        /**
         * Update progress percentage
         */
        updatePercentage: function(percentage) {
            try {
                if (!this.state.container) {
                    return;
                }

                // Validate percentage
                percentage = Math.max(0, Math.min(100, parseInt(percentage) || 0));
                this.state.percentage = percentage;

                // Animate progress bar
                this.animateProgress(percentage);

                // Update percentage text
                const percentageEl = this.state.container.querySelector('.cuft-progress-percentage');
                if (percentageEl) {
                    percentageEl.textContent = percentage + '%';
                }

                this.log('Percentage updated: ' + percentage + '%');
            } catch (error) {
                this.log('Update percentage error: ' + error.message);
            }
        },

        /**
         * Animate progress bar
         */
        animateProgress: function(targetPercentage) {
            const self = this;
            const fillEl = this.state.container?.querySelector('.cuft-progress-fill');

            if (!fillEl) {
                return;
            }

            // Cancel previous animation
            if (this.state.animationFrame) {
                cancelAnimationFrame(this.state.animationFrame);
            }

            // Get current percentage
            const currentWidth = parseFloat(fillEl.style.width) || 0;
            const startTime = Date.now();
            const duration = this.config.animationDuration;

            function animate() {
                const elapsed = Date.now() - startTime;
                const progress = Math.min(elapsed / duration, 1);

                // Easing function (ease-out)
                const eased = 1 - Math.pow(1 - progress, 3);
                const width = currentWidth + (targetPercentage - currentWidth) * eased;

                fillEl.style.width = width + '%';

                if (progress < 1) {
                    self.state.animationFrame = requestAnimationFrame(animate);
                } else {
                    self.state.animationFrame = null;
                }
            }

            // Start animation
            this.state.animationFrame = requestAnimationFrame(animate);
        },

        /**
         * Update progress message
         */
        updateMessage: function(message) {
            try {
                if (!this.state.container) {
                    return;
                }

                this.state.message = message;

                const messageEl = this.state.container.querySelector('.cuft-progress-message');
                if (messageEl) {
                    messageEl.textContent = message;
                }

                this.log('Message updated: ' + message);
            } catch (error) {
                this.log('Update message error: ' + error.message);
            }
        },

        /**
         * Add detail message (optional secondary info)
         */
        addDetail: function(detail) {
            try {
                if (!this.state.container) {
                    return;
                }

                const detailsEl = this.state.container.querySelector('.cuft-progress-details');
                if (detailsEl) {
                    const timestamp = new Date().toLocaleTimeString();
                    const detailLine = document.createElement('div');
                    detailLine.className = 'cuft-progress-detail-line';
                    detailLine.textContent = '[' + timestamp + '] ' + detail;
                    detailsEl.appendChild(detailLine);

                    // Keep only last 5 details
                    while (detailsEl.children.length > 5) {
                        detailsEl.removeChild(detailsEl.firstChild);
                    }

                    // Auto-scroll to latest
                    detailsEl.scrollTop = detailsEl.scrollHeight;
                }
            } catch (error) {
                this.log('Add detail error: ' + error.message);
            }
        },

        /**
         * Show/hide cancel button
         */
        showCancelButton: function(show, callback) {
            try {
                const cancelBtn = this.state.container?.querySelector('.cuft-progress-cancel');
                if (!cancelBtn) {
                    return;
                }

                if (show) {
                    cancelBtn.style.display = 'inline-block';

                    // Remove previous listeners
                    const newBtn = cancelBtn.cloneNode(true);
                    cancelBtn.parentNode.replaceChild(newBtn, cancelBtn);

                    // Add new listener
                    if (callback) {
                        newBtn.addEventListener('click', callback);
                    }
                } else {
                    cancelBtn.style.display = 'none';
                }
            } catch (error) {
                this.log('Cancel button error: ' + error.message);
            }
        },

        /**
         * Reset progress to initial state
         */
        reset: function() {
            try {
                this.state.currentStage = 'idle';
                this.state.percentage = 0;
                this.state.message = '';

                if (this.state.container) {
                    const fillEl = this.state.container.querySelector('.cuft-progress-fill');
                    if (fillEl) {
                        fillEl.style.width = '0%';
                    }

                    const percentageEl = this.state.container.querySelector('.cuft-progress-percentage');
                    if (percentageEl) {
                        percentageEl.textContent = '0%';
                    }

                    const messageEl = this.state.container.querySelector('.cuft-progress-message');
                    if (messageEl) {
                        messageEl.textContent = '';
                    }

                    const detailsEl = this.state.container.querySelector('.cuft-progress-details');
                    if (detailsEl) {
                        detailsEl.innerHTML = '';
                    }
                }

                this.log('Progress reset');
            } catch (error) {
                this.log('Reset error: ' + error.message);
            }
        },

        /**
         * Update from status object
         */
        updateFromStatus: function(status) {
            try {
                if (!status) {
                    return;
                }

                const stage = status.status || status.stage || 'idle';
                const percentage = status.percentage || 0;
                const message = status.message || '';

                this.show(stage, percentage, message);

                // Add detail if provided
                if (status.detail) {
                    this.addDetail(status.detail);
                }
            } catch (error) {
                this.log('Update from status error: ' + error.message);
            }
        },

        /**
         * Debug logging
         */
        log: function(message) {
            if (this.config.debug && console && console.log) {
                console.log('[CUFT Progress]', message);
            }
        },

        /**
         * Destroy progress indicator
         */
        destroy: function() {
            try {
                // Cancel animations
                if (this.state.animationFrame) {
                    cancelAnimationFrame(this.state.animationFrame);
                }

                // Remove container
                if (this.state.container) {
                    this.state.container.innerHTML = '';
                }

                this.log('Progress indicator destroyed');
            } catch (error) {
                this.log('Destroy error: ' + error.message);
            }
        }
    };

    // Export for use in other components
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.CUFTProgressIndicator;
    }

})();
