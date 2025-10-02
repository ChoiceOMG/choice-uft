/**
 * CUFT Form Builder - Main Controller
 *
 * Manages the testing dashboard form builder UI and interactions.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * Form Builder Controller
     */
    class CUFTFormBuilder {
        constructor() {
            this.config = window.cuftFormBuilder || {};
            this.currentForm = null;
            this.capturedEvents = [];
            this.iframe = null;
            this.debugMode = this.config.debug || false;

            this.init();
        }

        /**
         * Initialize form builder
         */
        init() {
            // Wait for DOM ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.onReady());
            } else {
                this.onReady();
            }
        }

        /**
         * On DOM ready
         */
        onReady() {
            this.bindEvents();
            this.loadExistingForms();
            this.log('Form Builder initialized');
        }

        /**
         * Bind UI events
         */
        bindEvents() {
            // Create form button
            const createBtn = document.getElementById('cuft-create-form-btn');
            if (createBtn) {
                createBtn.addEventListener('click', () => this.handleCreateForm());
            }

            // Delete form button
            const deleteBtn = document.getElementById('cuft-delete-form-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => this.handleDeleteForm());
            }

            // Populate fields button
            const populateBtn = document.getElementById('cuft-populate-fields-btn');
            if (populateBtn) {
                populateBtn.addEventListener('click', () => this.handlePopulateFields());
            }

            // Trigger submit button
            const submitBtn = document.getElementById('cuft-trigger-submit-btn');
            if (submitBtn) {
                submitBtn.addEventListener('click', () => this.handleTriggerSubmit());
            }

            // Listen for iframe messages
            document.addEventListener('cuft:form_loaded', (e) => this.onFormLoaded(e.detail));
            document.addEventListener('cuft:fields_populated', (e) => this.onFieldsPopulated(e.detail));
            document.addEventListener('cuft:form_submitted', (e) => this.onFormSubmitted(e.detail));
            document.addEventListener('cuft:event_captured', (e) => this.onEventCaptured(e.detail));
            document.addEventListener('cuft:error', (e) => this.onError(e.detail));

            this.log('Events bound');
        }

        /**
         * Handle create form button click
         */
        async handleCreateForm() {
            try {
                const frameworkSelect = document.getElementById('cuft-framework-select');
                const templateSelect = document.getElementById('cuft-template-select');

                if (!frameworkSelect || !templateSelect) {
                    throw new Error('Framework or template selector not found');
                }

                const framework = frameworkSelect.value;
                const templateId = templateSelect.value;

                if (!framework) {
                    this.showError('Please select a form framework');
                    return;
                }

                this.showLoading('Creating test form...');
                const result = await this.createTestForm(framework, templateId);

                if (result.success) {
                    this.currentForm = result.data;
                    this.showSuccess('Test form created successfully');
                    this.loadFormInIframe(result.data.iframe_url);
                    this.updateFormInfo(result.data);
                } else {
                    this.showError(result.data?.message || 'Failed to create test form');
                }

            } catch (error) {
                this.showError(error.message);
                this.log('Create form error:', error);
            }
        }

        /**
         * Create test form via AJAX
         *
         * @param {string} framework Framework identifier
         * @param {string} templateId Template ID
         * @return {Promise<Object>} AJAX response
         */
        async createTestForm(framework, templateId = 'basic_contact_form') {
            const formData = new FormData();
            formData.append('action', 'cuft_create_test_form');
            formData.append('nonce', this.config.nonce);
            formData.append('framework', framework);
            formData.append('template_id', templateId);

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            return await response.json();
        }

        /**
         * Load form in iframe
         *
         * @param {string} url Form URL
         */
        loadFormInIframe(url) {
            this.iframe = document.getElementById('cuft-test-iframe');

            if (!this.iframe) {
                this.showError('Iframe container not found');
                return;
            }

            // Show iframe container
            const iframeContainer = document.getElementById('cuft-iframe-container');
            if (iframeContainer) {
                iframeContainer.style.display = 'block';
            }

            // Set iframe source
            this.iframe.src = url;

            // Show loading state
            this.iframe.addEventListener('load', () => {
                this.log('Iframe loaded:', url);
                this.hideLoading();
            }, { once: true });

            this.log('Loading iframe:', url);
        }

        /**
         * Handle delete form button click
         */
        async handleDeleteForm() {
            if (!this.currentForm) {
                this.showError('No test form to delete');
                return;
            }

            if (!confirm('Are you sure you want to delete this test form?')) {
                return;
            }

            try {
                this.showLoading('Deleting test form...');
                const result = await this.deleteTestForm(this.currentForm.instance_id);

                if (result.success) {
                    this.showSuccess('Test form deleted successfully');
                    this.clearForm();
                } else {
                    this.showError(result.data?.message || 'Failed to delete test form');
                }

            } catch (error) {
                this.showError(error.message);
                this.log('Delete form error:', error);
            }
        }

        /**
         * Delete test form via AJAX
         *
         * @param {string} instanceId Form instance ID
         * @return {Promise<Object>} AJAX response
         */
        async deleteTestForm(instanceId) {
            const formData = new FormData();
            formData.append('action', 'cuft_delete_test_form');
            formData.append('nonce', this.config.nonce);
            formData.append('instance_id', instanceId);

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            return await response.json();
        }

        /**
         * Handle populate fields button click
         */
        async handlePopulateFields() {
            if (!this.currentForm || !this.iframe) {
                this.showError('No test form loaded');
                return;
            }

            try {
                this.showLoading('Populating test data...');
                const result = await this.getTestData(this.currentForm.instance_id);

                if (result.success) {
                    // Send populate message to iframe
                    window.cuftBridge.sendToIframe(this.iframe, 'cuft_populate_fields', {
                        fields: result.data.test_data,
                        options: {
                            trigger_events: true,
                            clear_first: true
                        }
                    });

                    this.log('Sent populate message with data:', result.data.test_data);
                } else {
                    this.showError(result.data?.message || 'Failed to get test data');
                }

            } catch (error) {
                this.showError(error.message);
                this.log('Populate fields error:', error);
            }
        }

        /**
         * Get test data via AJAX
         *
         * @param {string} instanceId Form instance ID
         * @return {Promise<Object>} AJAX response
         */
        async getTestData(instanceId) {
            const formData = new FormData();
            formData.append('action', 'cuft_populate_form');
            formData.append('nonce', this.config.nonce);
            formData.append('instance_id', instanceId);
            formData.append('use_test_data', 'true');

            const response = await fetch(this.config.ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            });

            return await response.json();
        }

        /**
         * Handle trigger submit button click
         */
        handleTriggerSubmit() {
            if (!this.currentForm || !this.iframe) {
                this.showError('No test form loaded');
                return;
            }

            // Send trigger submit message to iframe
            window.cuftBridge.sendToIframe(this.iframe, 'cuft_trigger_submit', {});
            this.log('Sent trigger submit message');
        }

        /**
         * Load existing test forms
         */
        async loadExistingForms() {
            try {
                const url = new URL(this.config.ajaxUrl);
                url.searchParams.append('action', 'cuft_get_test_forms');
                url.searchParams.append('nonce', this.config.nonce);
                url.searchParams.append('status', 'active');

                const response = await fetch(url, {
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data.forms.length > 0) {
                    // Load the first active form
                    this.currentForm = result.data.forms[0];
                    this.loadFormInIframe(this.currentForm.iframe_url);
                    this.updateFormInfo(this.currentForm);
                }

            } catch (error) {
                this.log('Error loading existing forms:', error);
            }
        }

        /**
         * Update form info display
         *
         * @param {Object} formData Form data
         */
        updateFormInfo(formData) {
            const infoDiv = document.getElementById('cuft-form-info');
            if (!infoDiv) return;

            infoDiv.innerHTML = `
                <div class="cuft-form-info-item">
                    <strong>Framework:</strong> ${formData.framework_label || formData.framework}
                </div>
                <div class="cuft-form-info-item">
                    <strong>Instance ID:</strong> ${formData.instance_id}
                </div>
                <div class="cuft-form-info-item">
                    <strong>Test URL:</strong> <a href="${formData.test_url}" target="_blank">Open in new tab</a>
                </div>
            `;
        }

        /**
         * Clear form state
         */
        clearForm() {
            this.currentForm = null;
            this.capturedEvents = [];

            if (this.iframe) {
                this.iframe.src = 'about:blank';
            }

            const iframeContainer = document.getElementById('cuft-iframe-container');
            if (iframeContainer) {
                iframeContainer.style.display = 'none';
            }

            const infoDiv = document.getElementById('cuft-form-info');
            if (infoDiv) {
                infoDiv.innerHTML = '';
            }

            this.clearEventMonitor();
        }

        /**
         * Event handler: Form loaded
         *
         * @param {Object} data Event data
         */
        onFormLoaded(data) {
            this.log('Form loaded event:', data);
            this.hideLoading();
            this.showSuccess('Form loaded successfully');
        }

        /**
         * Event handler: Fields populated
         *
         * @param {Object} data Event data
         */
        onFieldsPopulated(data) {
            this.log('Fields populated event:', data);
            this.hideLoading();
            this.showSuccess(`Fields populated: ${data.populated_fields?.join(', ')}`);
        }

        /**
         * Event handler: Form submitted
         *
         * @param {Object} data Event data
         */
        onFormSubmitted(data) {
            this.log('Form submitted event:', data);
            this.hideLoading();
            this.showSuccess('Form submitted successfully');

            if (data.tracking_event) {
                this.addEventToMonitor(data.tracking_event);
                this.validateTrackingEvent(data.tracking_event);
            }
        }

        /**
         * Event handler: Event captured
         *
         * @param {Object} data Event data
         */
        onEventCaptured(data) {
            this.log('Event captured:', data);

            if (data.event) {
                this.addEventToMonitor(data.event);
            }
        }

        /**
         * Event handler: Error
         *
         * @param {Object} data Error data
         */
        onError(data) {
            this.log('Error event:', data);
            this.showError(data.message || 'An error occurred');
        }

        /**
         * Add event to monitor display
         *
         * @param {Object} event Event data
         */
        addEventToMonitor(event) {
            this.capturedEvents.push({
                event: event,
                timestamp: Date.now()
            });

            const monitor = document.getElementById('cuft-event-monitor');
            if (!monitor) return;

            const eventDiv = document.createElement('div');
            eventDiv.className = 'cuft-event-item';
            eventDiv.innerHTML = `
                <div class="cuft-event-header">
                    <strong>${event.event || 'Unknown Event'}</strong>
                    <span class="cuft-event-time">${new Date().toLocaleTimeString()}</span>
                </div>
                <pre class="cuft-event-data">${JSON.stringify(event, null, 2)}</pre>
            `;

            monitor.insertBefore(eventDiv, monitor.firstChild);
            this.log('Added event to monitor:', event);
        }

        /**
         * Validate tracking event
         *
         * @param {Object} event Event data
         */
        async validateTrackingEvent(event) {
            try {
                const formData = new FormData();
                formData.append('action', 'cuft_test_submit');
                formData.append('nonce', this.config.nonce);
                formData.append('instance_id', this.currentForm?.instance_id || '');
                formData.append('tracking_event', JSON.stringify(event));

                const response = await fetch(this.config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (result.success && result.data.validation) {
                    this.displayValidationResults(result.data.validation);
                }

            } catch (error) {
                this.log('Validation error:', error);
            }
        }

        /**
         * Display validation results
         *
         * @param {Object} validation Validation data
         */
        displayValidationResults(validation) {
            const validationDiv = document.getElementById('cuft-validation-results');
            if (!validationDiv) return;

            const checks = [
                { label: 'CUFT Tracked', value: validation.has_cuft_tracked },
                { label: 'CUFT Source', value: validation.has_cuft_source },
                { label: 'Snake Case', value: validation.uses_snake_case },
                { label: 'Required Fields', value: validation.required_fields_present }
            ];

            const html = checks.map(check => `
                <div class="cuft-validation-item ${check.value ? 'success' : 'error'}">
                    <span class="dashicons dashicons-${check.value ? 'yes' : 'no'}"></span>
                    ${check.label}
                </div>
            `).join('');

            validationDiv.innerHTML = html;
            this.log('Validation results:', validation);
        }

        /**
         * Clear event monitor
         */
        clearEventMonitor() {
            const monitor = document.getElementById('cuft-event-monitor');
            if (monitor) {
                monitor.innerHTML = '';
            }

            const validationDiv = document.getElementById('cuft-validation-results');
            if (validationDiv) {
                validationDiv.innerHTML = '';
            }
        }

        /**
         * Show loading state
         *
         * @param {string} message Loading message
         */
        showLoading(message = 'Loading...') {
            const loader = document.getElementById('cuft-loader');
            const loaderText = document.getElementById('cuft-loader-text');

            if (loader) {
                loader.style.display = 'block';
            }

            if (loaderText) {
                loaderText.textContent = message;
            }
        }

        /**
         * Hide loading state
         */
        hideLoading() {
            const loader = document.getElementById('cuft-loader');
            if (loader) {
                loader.style.display = 'none';
            }
        }

        /**
         * Show success message
         *
         * @param {string} message Success message
         */
        showSuccess(message) {
            this.showNotice(message, 'success');
        }

        /**
         * Show error message
         *
         * @param {string} message Error message
         */
        showError(message) {
            this.showNotice(message, 'error');
        }

        /**
         * Show notice
         *
         * @param {string} message Notice message
         * @param {string} type Notice type (success|error|warning|info)
         */
        showNotice(message, type = 'info') {
            const noticeContainer = document.getElementById('cuft-notices');
            if (!noticeContainer) {
                console.log(`[CUFT ${type.toUpperCase()}]`, message);
                return;
            }

            const notice = document.createElement('div');
            notice.className = `notice notice-${type} is-dismissible`;
            notice.innerHTML = `<p>${message}</p>`;

            noticeContainer.appendChild(notice);

            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                notice.style.opacity = '0';
                setTimeout(() => notice.remove(), 300);
            }, 5000);
        }

        /**
         * Log message (debug mode)
         *
         * @param {...any} args Arguments to log
         */
        log(...args) {
            if (this.debugMode) {
                console.log('[CUFT Form Builder]', ...args);
            }
        }
    }

    // Initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.CUFTFormBuilder = new CUFTFormBuilder();
        });
    } else {
        window.CUFTFormBuilder = new CUFTFormBuilder();
    }

})();
