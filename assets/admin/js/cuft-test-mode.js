/**
 * CUFT Test Mode - Field Population & Event Capture
 *
 * Runs inside test form iframes to populate fields and capture tracking events.
 * Prevents real form submissions while testing.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * Test Mode Controller
     */
    class CUFTTestMode {
        constructor() {
            this.config = window.cuftTestMode || {};
            this.debugMode = this.config.debug || false;
            this.testModeEnabled = this.isTestMode();
            this.capturedEvents = [];
            this.formElement = null;

            if (this.testModeEnabled) {
                this.init();
            }
        }

        /**
         * Check if test mode is enabled
         *
         * @return {boolean} True if test mode enabled
         */
        isTestMode() {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get('test_mode') === '1';
        }

        /**
         * Initialize test mode
         */
        init() {
            this.log('Test mode initialized');

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
            this.detectForm();
            this.setupMessageListener();
            this.interceptFormSubmission();
            this.interceptDataLayerEvents();
            this.sendFormLoadedMessage();

            this.log('Test mode ready');
        }

        /**
         * Detect form element
         */
        detectForm() {
            // Try multiple form selectors
            const selectors = [
                '.elementor-form',
                '.wpcf7-form',
                '.gform_wrapper form',
                '.nf-form-cont form',
                '.fusion-form',
                'form[data-cuft-tracked]',
                'form'
            ];

            for (const selector of selectors) {
                this.formElement = document.querySelector(selector);
                if (this.formElement) {
                    this.log('Form detected:', selector);
                    break;
                }
            }

            if (!this.formElement) {
                this.log('No form element found');
            }
        }

        /**
         * Setup postMessage listener
         */
        setupMessageListener() {
            window.addEventListener('message', (event) => this.handleMessage(event), false);
            this.log('Message listener setup');
        }

        /**
         * Handle incoming postMessage
         *
         * @param {MessageEvent} event Message event
         */
        handleMessage(event) {
            try {
                // Validate origin
                if (event.origin !== window.location.origin) {
                    this.log('Rejected message from untrusted origin:', event.origin);
                    return;
                }

                // Validate message structure
                if (!event.data || !event.data.action) {
                    return;
                }

                const { action, data } = event.data;

                this.log('Received message:', action, data);

                // Route based on action
                switch (action) {
                    case 'cuft_populate_fields':
                        this.populateFields(data.fields, data.options);
                        break;

                    case 'cuft_trigger_submit':
                        this.triggerSubmit();
                        break;

                    case 'cuft_get_form_info':
                        this.sendFormInfo();
                        break;

                    case 'cuft_enable_test_mode':
                        this.enableTestMode(data);
                        break;
                }

            } catch (error) {
                this.log('Error handling message:', error);
                this.sendError('message_handler_error', error.message);
            }
        }

        /**
         * Populate form fields
         *
         * @param {Object} fields Field values
         * @param {Object} options Population options
         */
        populateFields(fields, options = {}) {
            try {
                const populatedFields = [];
                const failedFields = [];

                Object.keys(fields).forEach(fieldName => {
                    const value = fields[fieldName];
                    const populated = this.populateField(fieldName, value, options);

                    if (populated) {
                        populatedFields.push(fieldName);
                    } else {
                        failedFields.push(fieldName);
                    }
                });

                // Send confirmation
                this.sendMessage('cuft_fields_populated', {
                    success: true,
                    populated_fields: populatedFields,
                    failed_fields: failedFields
                });

                this.log('Fields populated:', populatedFields);

            } catch (error) {
                this.log('Error populating fields:', error);
                this.sendError('populate_fields_error', error.message);
            }
        }

        /**
         * Populate individual field
         *
         * @param {string} fieldName Field name
         * @param {string} value Field value
         * @param {Object} options Population options
         * @return {boolean} True if populated
         */
        populateField(fieldName, value, options = {}) {
            // Try multiple selector strategies
            const selectors = [
                `#${fieldName}`,
                `[name="${fieldName}"]`,
                `[name="form_fields[${fieldName}]"]`, // Elementor
                `[name="${fieldName}[]"]`, // Array fields
                `.${fieldName}-field input`,
                `input[type="text"][placeholder*="${fieldName}"]`,
                `input[type="email"][placeholder*="${fieldName}"]`,
                `textarea[placeholder*="${fieldName}"]`
            ];

            let field = null;

            for (const selector of selectors) {
                try {
                    field = document.querySelector(selector);
                    if (field) {
                        this.log(`Found field ${fieldName} with selector: ${selector}`);
                        break;
                    }
                } catch (e) {
                    // Invalid selector, continue
                }
            }

            if (!field) {
                this.log(`Field not found: ${fieldName}`);
                return false;
            }

            // Clear first if option is set
            if (options.clear_first) {
                field.value = '';
            }

            // Set value
            field.value = value;

            // Trigger events if option is set
            if (options.trigger_events !== false) {
                this.triggerFieldEvents(field);
            }

            return true;
        }

        /**
         * Trigger field events
         *
         * @param {HTMLElement} field Field element
         */
        triggerFieldEvents(field) {
            // Native events
            const events = ['input', 'change', 'blur'];

            events.forEach(eventType => {
                try {
                    const event = new Event(eventType, { bubbles: true });
                    field.dispatchEvent(event);
                } catch (e) {
                    // Fallback for older browsers
                    if (document.createEvent) {
                        const event = document.createEvent('HTMLEvents');
                        event.initEvent(eventType, true, true);
                        field.dispatchEvent(event);
                    }
                }
            });

            // jQuery events (if available)
            if (window.jQuery && window.jQuery.fn) {
                try {
                    window.jQuery(field).trigger('input').trigger('change').trigger('blur');
                } catch (e) {
                    this.log('jQuery event trigger failed:', e);
                }
            }
        }

        /**
         * Intercept form submission
         */
        interceptFormSubmission() {
            if (!this.formElement) return;

            // Prevent default submission
            this.formElement.addEventListener('submit', (event) => {
                this.log('Form submit intercepted');
                event.preventDefault();
                event.stopPropagation();

                this.handleFormSubmit(event);
            }, true);

            this.log('Form submission intercepted');
        }

        /**
         * Handle form submit
         *
         * @param {Event} event Submit event
         */
        handleFormSubmit(event) {
            this.log('Processing test form submission');

            // Collect form data
            const formData = this.collectFormData();

            // Get the last captured tracking event
            const trackingEvent = this.capturedEvents[this.capturedEvents.length - 1] || null;

            // Send submission message
            this.sendMessage('cuft_form_submitted', {
                form_data: formData,
                tracking_event: trackingEvent,
                validation: {
                    prevented_real_submit: true,
                    captured_events: this.capturedEvents.map(e => e.event)
                }
            });

            this.log('Form submission reported');
        }

        /**
         * Collect form data
         *
         * @return {Object} Form data
         */
        collectFormData() {
            if (!this.formElement) return {};

            const formData = {};
            const inputs = this.formElement.querySelectorAll('input, textarea, select');

            inputs.forEach(input => {
                const name = input.name || input.id;
                if (name && input.type !== 'submit' && input.type !== 'button') {
                    if (input.type === 'checkbox' || input.type === 'radio') {
                        if (input.checked) {
                            formData[name] = input.value;
                        }
                    } else {
                        formData[name] = input.value;
                    }
                }
            });

            return formData;
        }

        /**
         * Intercept dataLayer events
         */
        interceptDataLayerEvents() {
            // Intercept dataLayer.push
            if (window.dataLayer) {
                const originalPush = window.dataLayer.push;

                window.dataLayer.push = (...args) => {
                    args.forEach(event => {
                        if (event && typeof event === 'object') {
                            this.captureEvent(event);
                        }
                    });

                    // Still call original push
                    return originalPush.apply(window.dataLayer, args);
                };

                this.log('DataLayer interceptor installed');
            }

            // Listen for custom events
            document.addEventListener('cuft_tracking_event', (e) => {
                if (e.detail) {
                    this.captureEvent(e.detail);
                }
            });
        }

        /**
         * Capture tracking event
         *
         * Constitutional Compliance Note:
         * - This script MONITORS events created by form framework tracking scripts
         * - It does NOT create dataLayer events itself
         * - Form framework scripts (cuft-elementor.js, etc.) are responsible for adding:
         *   * cuft_tracked: true
         *   * cuft_source: 'framework_name'
         *   * snake_case field names
         * - This monitoring script simply captures and reports what it sees
         *
         * @param {Object} event Event data
         */
        captureEvent(event) {
            // Only capture CUFT-tracked events
            // Constitutional compliance is enforced by the form framework tracking scripts,
            // not by this monitoring script
            if (event.cuft_tracked || event.event === 'form_submit' || event.event === 'generate_lead') {
                this.capturedEvents.push(event);

                this.sendMessage('cuft_event_captured', {
                    event_type: 'dataLayer.push',
                    event: event
                });

                this.log('Event captured:', event);
            }
        }

        /**
         * Trigger form submit programmatically
         */
        triggerSubmit() {
            if (!this.formElement) {
                this.sendError('no_form', 'Form element not found');
                return;
            }

            try {
                // Try to find and click submit button
                const submitBtn = this.formElement.querySelector('[type="submit"], button[type="submit"], .elementor-button');

                if (submitBtn) {
                    submitBtn.click();
                } else {
                    // Fallback: dispatch submit event
                    const submitEvent = new Event('submit', { bubbles: true, cancelable: true });
                    this.formElement.dispatchEvent(submitEvent);
                }

                this.log('Submit triggered');

            } catch (error) {
                this.log('Error triggering submit:', error);
                this.sendError('trigger_submit_error', error.message);
            }
        }

        /**
         * Send form info message
         */
        sendFormInfo() {
            if (!this.formElement) {
                this.sendError('no_form', 'Form element not found');
                return;
            }

            const fields = [];
            const inputs = this.formElement.querySelectorAll('input, textarea, select');

            inputs.forEach(input => {
                const name = input.name || input.id;
                if (name && input.type !== 'submit' && input.type !== 'button') {
                    fields.push(name);
                }
            });

            this.sendMessage('cuft_form_info', {
                framework: this.detectFramework(),
                form_id: this.formElement.id || 'unknown',
                field_count: fields.length,
                fields: fields,
                ready: true
            });
        }

        /**
         * Detect framework type
         *
         * @return {string} Framework identifier
         */
        detectFramework() {
            if (this.formElement) {
                if (this.formElement.classList.contains('elementor-form')) return 'elementor';
                if (this.formElement.classList.contains('wpcf7-form')) return 'cf7';
                if (this.formElement.classList.contains('gform_form')) return 'gravity';
                if (this.formElement.closest('.nf-form-cont')) return 'ninja';
                if (this.formElement.classList.contains('fusion-form')) return 'avada';
            }

            return 'unknown';
        }

        /**
         * Enable test mode features
         *
         * @param {Object} options Test mode options
         */
        enableTestMode(options = {}) {
            this.log('Test mode enabled with options:', options);

            if (options.intercept_submit) {
                this.interceptFormSubmission();
            }

            if (options.log_events) {
                this.debugMode = true;
            }

            this.sendMessage('cuft_test_mode_enabled', {
                success: true,
                options: options
            });
        }

        /**
         * Send form loaded message
         */
        sendFormLoadedMessage() {
            const formInfo = {
                framework: this.detectFramework(),
                form_id: this.formElement?.id || 'unknown',
                ready: true,
                test_mode: true
            };

            this.sendMessage('cuft_form_loaded', formInfo);
            this.log('Form loaded message sent:', formInfo);
        }

        /**
         * Send message to parent
         *
         * @param {string} action Action name
         * @param {Object} data Message data
         */
        sendMessage(action, data = {}) {
            try {
                const message = {
                    action: action,
                    timestamp: Date.now(),
                    data: data
                };

                window.parent.postMessage(message, window.location.origin);
                this.log('Sent message:', action, data);

            } catch (error) {
                this.log('Error sending message:', error);
            }
        }

        /**
         * Send error message
         *
         * @param {string} errorType Error type
         * @param {string} message Error message
         */
        sendError(errorType, message) {
            this.sendMessage('cuft_error', {
                error_type: errorType,
                message: message,
                timestamp: Date.now()
            });
        }

        /**
         * Log message (debug mode)
         *
         * @param {...any} args Arguments to log
         */
        log(...args) {
            if (this.debugMode) {
                console.log('[CUFT Test Mode]', ...args);
            }
        }
    }

    // Initialize test mode if in iframe
    if (window.self !== window.top) {
        window.CUFTTestMode = new CUFTTestMode();
    }

})();
