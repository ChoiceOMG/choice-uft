/**
 * CUFT Testing Dashboard Main Controller
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

(function() {
    'use strict';

    // Check if cuftConfig exists
    if (typeof cuftConfig === 'undefined') {
        console.error('CUFT Testing Dashboard: cuftConfig not defined');
        return;
    }

    /**
     * Main Dashboard Controller
     */
    class CUFTTestingDashboard {
        constructor() {
            this.testData = null;
            this.sessionId = null;
            this.init();
        }

        /**
         * Initialize dashboard
         */
        init() {
            document.addEventListener('DOMContentLoaded', () => {
                this.bindEventHandlers();
                console.log('CUFT Testing Dashboard initialized');
            });
        }

        /**
         * Bind event handlers
         */
        bindEventHandlers() {
            // Generate test data button
            const generateBtn = document.getElementById('cuft-generate-test-data');
            if (generateBtn) {
                generateBtn.addEventListener('click', () => this.generateTestData());
            }

            // Event simulator buttons
            const simulatorButtons = {
                'cuft-simulate-phone-click': 'phone_click',
                'cuft-simulate-email-click': 'email_click',
                'cuft-simulate-form-submit': 'form_submit',
                'cuft-simulate-generate-lead': 'generate_lead'
            };

            Object.entries(simulatorButtons).forEach(([id, eventType]) => {
                const btn = document.getElementById(id);
                if (btn) {
                    btn.addEventListener('click', () => this.simulateEvent(eventType));
                }
            });

            // Build test form button
            const buildFormBtn = document.getElementById('cuft-build-test-form');
            if (buildFormBtn) {
                buildFormBtn.addEventListener('click', () => this.buildTestForm());
            }

            // Change form data button
            const changeDataBtn = document.getElementById('cuft-change-form-data');
            if (changeDataBtn) {
                changeDataBtn.addEventListener('click', () => this.generateTestData());
            }
        }

        /**
         * Generate test data
         */
        async generateTestData() {
            const button = document.getElementById('cuft-generate-test-data');
            const display = document.getElementById('cuft-test-data-display');
            const content = display ? display.querySelector('.test-data-content') : null;

            if (!button || !display || !content) {
                console.error('CUFT: Required elements not found');
                return;
            }

            try {
                // Disable button and show loading
                button.disabled = true;
                button.textContent = 'Generating...';

                // Prepare request
                const formData = new FormData();
                formData.append('action', 'cuft_generate_test_data');
                formData.append('nonce', cuftConfig.nonce);

                // Make AJAX request
                const response = await fetch(cuftConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    // Store test data
                    this.testData = result.data;
                    this.sessionId = result.data.session_id;

                    // Display generated data
                    display.style.display = 'block';
                    content.textContent = JSON.stringify(result.data, null, 2);

                    // Save to localStorage
                    this.saveToLocalStorage(result.data);

                    // Show success message
                    this.showStatus('Test data generated successfully! (Execution: ' +
                        result.data.execution_time_ms + 'ms)', 'success');

                    // Log to console if debug mode
                    if (cuftConfig.debug) {
                        console.log('Generated test data:', result.data);
                    }
                } else {
                    throw new Error(result.data?.message || 'Unknown error occurred');
                }

            } catch (error) {
                console.error('CUFT: Failed to generate test data:', error);
                this.showStatus('Failed to generate test data: ' + error.message, 'error');
            } finally {
                // Re-enable button
                button.disabled = false;
                button.textContent = 'Generate Sample Data';
            }
        }

        /**
         * Simulate an event
         */
        async simulateEvent(eventType) {
            if (!this.testData) {
                this.showStatus('Please generate test data first!', 'error');
                return;
            }

            const statusDiv = document.getElementById('cuft-simulation-status');
            if (!statusDiv) {
                console.error('CUFT: Status div not found');
                return;
            }

            try {
                // Prepare request
                const formData = new FormData();
                formData.append('action', 'cuft_simulate_event');
                formData.append('nonce', cuftConfig.nonce);
                formData.append('event_type', eventType);
                formData.append('session_id', this.sessionId);
                formData.append('test_data', JSON.stringify(this.testData));

                // Make AJAX request
                const response = await fetch(cuftConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    // Push event to dataLayer
                    // Server adds cuft_tracked: true and cuft_source to all events
                    if (window.dataLayer && result.data.event) {
                        // Validate required fields are present (added server-side)
                        if (!result.data.event.cuft_tracked || !result.data.event.cuft_source) {
                            console.warn('CUFT: Event missing required fields', result.data.event);
                        }
                        window.dataLayer.push(result.data.event);
                    }

                    // Show success message
                    this.showStatus(
                        `Event "${eventType}" simulated successfully! (DB ID: ${result.data.db_id}, ` +
                        `Execution: ${result.data.execution_time_ms}ms)`,
                        'success'
                    );

                    // Log to console if debug mode
                    if (cuftConfig.debug) {
                        console.log('Simulated event:', result.data.event);
                    }

                    // Update event viewer if module is loaded
                    if (typeof CUFTDataLayerMonitor !== 'undefined') {
                        // Event will be captured by monitor automatically
                    }

                } else {
                    throw new Error(result.data?.message || 'Unknown error occurred');
                }

            } catch (error) {
                console.error('CUFT: Failed to simulate event:', error);
                this.showStatus('Failed to simulate event: ' + error.message, 'error');
            }
        }

        /**
         * Build test form
         */
        async buildTestForm() {
            const frameworkSelect = document.getElementById('cuft-form-framework');
            const container = document.getElementById('cuft-test-form-container');

            if (!frameworkSelect || !container) {
                console.error('CUFT: Required form builder elements not found');
                return;
            }

            const framework = frameworkSelect.value;
            if (!framework) {
                this.showStatus('Please select a form framework first!', 'error');
                return;
            }

            // Generate test data if not already available
            if (!this.testData) {
                await this.generateTestData();
            }

            try {
                container.innerHTML = '<p>Loading form...</p>';

                // Prepare request
                const formData = new FormData();
                formData.append('action', 'cuft_build_test_form');
                formData.append('nonce', cuftConfig.nonce);
                formData.append('framework', framework);
                formData.append('session_id', this.sessionId || 'test_' + Date.now());
                if (this.testData) {
                    formData.append('test_data', JSON.stringify(this.testData));
                }

                // Make AJAX request
                const response = await fetch(cuftConfig.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data) {
                    // Display the form
                    container.innerHTML = result.data.rendered_html || '<p>Form HTML not available</p>';

                    // Show success message
                    this.showStatus(result.data.message || 'Test form ready!', 'success');

                    // Show the change data button
                    const changeBtn = document.getElementById('cuft-change-form-data');
                    if (changeBtn) {
                        changeBtn.style.display = 'inline-block';
                    }

                    // Log form info
                    console.log('CUFT: Test form built', {
                        framework: result.data.framework,
                        form_id: result.data.form_id,
                        session_id: result.data.session_id
                    });

                } else {
                    throw new Error(result.data?.message || 'Failed to build test form');
                }

            } catch (error) {
                console.error('CUFT: Failed to build test form:', error);
                container.innerHTML = '<div class="notice notice-error"><p>Failed to build test form: ' + error.message + '</p></div>';
                this.showStatus('Failed to build test form: ' + error.message, 'error');
            }
        }

        /**
         * Show status message
         */
        showStatus(message, type = 'info') {
            const statusDiv = document.getElementById('cuft-simulation-status');
            if (!statusDiv) return;

            statusDiv.className = 'simulation-status ' + type;
            statusDiv.textContent = message;
            statusDiv.style.display = 'block';

            // Auto-hide after 5 seconds
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }

        /**
         * Save test data to localStorage
         */
        saveToLocalStorage(data) {
            try {
                const key = 'cuft_test_sessions';
                let sessions = [];

                // Get existing sessions
                const stored = localStorage.getItem(key);
                if (stored) {
                    try {
                        sessions = JSON.parse(stored);
                    } catch (e) {
                        sessions = [];
                    }
                }

                // Add new session (FIFO - keep last 50)
                sessions.unshift(data);
                if (sessions.length > 50) {
                    sessions = sessions.slice(0, 50);
                }

                // Save back to localStorage
                localStorage.setItem(key, JSON.stringify(sessions));

            } catch (error) {
                console.warn('CUFT: Failed to save to localStorage:', error);
            }
        }
    }

    // Initialize dashboard
    window.CUFTTestingDashboard = new CUFTTestingDashboard();

})();