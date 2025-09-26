/**
 * CUFT Admin Quick Tests
 * Provides one-click testing functionality for each detected framework
 * within the admin page Framework Detection cards
 */
(function() {
    'use strict';

    // Quick test module namespace
    window.CUFTAdminQuickTests = {

        /**
         * Initialize quick test functionality
         */
        init: function() {
            this.setupEventListeners();
            this.loadTestFormUtilities();
            console.log('[CUFT Admin] Quick tests initialized');
        },

        /**
         * Load test form utilities if not already loaded
         */
        loadTestFormUtilities: function() {
            if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
                console.log('[CUFT Admin] Test form utilities not loaded, attempting to load...');

                // Try to load the common utilities script
                const script = document.createElement('script');
                script.src = cuftAdmin.plugin_url + '/assets/test-forms/cuft-test-common.js';
                script.onload = () => {
                    console.log('[CUFT Admin] Test form utilities loaded successfully');
                };
                script.onerror = () => {
                    console.error('[CUFT Admin] Failed to load test form utilities');
                };
                document.head.appendChild(script);
            }
        },

        /**
         * Setup event listeners for quick test buttons
         */
        setupEventListeners: function() {
            const self = this;

            // Use event delegation to handle quick test button clicks
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('cuft-quick-test-btn')) {
                    e.preventDefault();
                    const framework = e.target.closest('.cuft-quick-test-controls').dataset.framework;
                    self.handleQuickTest(e.target, framework);
                }
            });

            // Handle copy-to-clipboard for event data
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('cuft-copy-event-btn')) {
                    e.preventDefault();
                    self.copyEventData(e.target);
                }
            });
        },

        /**
         * Handle quick test button click
         */
        handleQuickTest: function(button, framework) {
            const controlsDiv = button.closest('.cuft-quick-test-controls');
            const emailInput = controlsDiv.querySelector('.cuft-test-email');
            const resultsDiv = controlsDiv.querySelector('.cuft-quick-test-results');
            const statusDiv = resultsDiv.querySelector('.cuft-test-status');
            const eventsDiv = resultsDiv.querySelector('.cuft-test-events');

            const email = emailInput.value.trim();

            // Validate email
            if (!email || !this.isValidEmail(email)) {
                this.showError(resultsDiv, statusDiv, 'Please enter a valid email address');
                return;
            }

            // Show loading state
            this.showLoading(button, resultsDiv, statusDiv);

            // Execute the test
            try {
                this.executeQuickTest(framework, email, button, resultsDiv, statusDiv, eventsDiv);
            } catch (error) {
                console.error('[CUFT Admin] Quick test error:', error);
                this.showError(resultsDiv, statusDiv, 'Test execution failed: ' + error.message);
                this.resetButton(button);
            }
        },

        /**
         * Execute the quick test for the specified framework
         */
        executeQuickTest: function(framework, email, button, resultsDiv, statusDiv, eventsDiv) {
            const self = this;
            const timestamp = Date.now();
            const formId = `admin_quick_test_${framework}_${timestamp}`;

            // Check if test utilities are available
            if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
                this.showError(resultsDiv, statusDiv, 'Test utilities not loaded. Please refresh the page.');
                this.resetButton(button);
                return;
            }

            const common = window.CUFTTestForms.common;

            // Create a temporary form element for the test
            const tempForm = this.createTempFormElement(framework, formId, email);

            // Prepare tracking data using the existing production flow
            common.prepareTrackingDataForProduction(framework, formId, tempForm);

            // Set form attributes for production code
            tempForm.setAttribute('data-cuft-email', email);
            tempForm.setAttribute('data-cuft-phone', '555-TEST-1234');
            tempForm.setAttribute('data-cuft-tracking', 'pending');

            // Monitor dataLayer before firing event
            const initialDataLayerLength = window.dataLayer ? window.dataLayer.length : 0;

            // Fire the framework-specific event
            this.fireFrameworkEvent(framework, formId, tempForm);

            // Monitor for events and show results
            setTimeout(function() {
                self.monitorAndShowResults(framework, formId, tempForm, initialDataLayerLength,
                                         button, resultsDiv, statusDiv, eventsDiv, email);

                // Clean up temp element
                if (tempForm.parentNode) {
                    tempForm.parentNode.removeChild(tempForm);
                }
            }, 1000);
        },

        /**
         * Create temporary form element for testing
         */
        createTempFormElement: function(framework, formId, email) {
            const tempForm = document.createElement('div');
            tempForm.id = formId;
            tempForm.className = this.getFrameworkFormClass(framework);
            tempForm.style.display = 'none';

            // Add to body temporarily
            document.body.appendChild(tempForm);

            return tempForm;
        },

        /**
         * Get the appropriate CSS class for the framework
         */
        getFrameworkFormClass: function(framework) {
            const classMap = {
                'elementor': 'elementor-form',
                'contact_form_7': 'wpcf7-form',
                'avada': 'fusion-form',
                'ninja_forms': 'nf-form-cont',
                'gravity_forms': 'gform_form'
            };
            return classMap[framework] || 'cuft-test-form';
        },

        /**
         * Fire the appropriate event for each framework
         */
        fireFrameworkEvent: function(framework, formId, formElement) {
            console.log(`[CUFT Admin] Firing ${framework} event for quick test`);

            switch (framework) {
                case 'elementor':
                    this.fireElementorEvent(formId, formElement);
                    break;
                case 'contact_form_7':
                    this.fireCF7Event(formId, formElement);
                    break;
                case 'avada':
                    this.fireAvadaEvent(formId, formElement);
                    break;
                case 'ninja_forms':
                    this.fireNinjaEvent(formId, formElement);
                    break;
                case 'gravity_forms':
                    this.fireGravityEvent(formId, formElement);
                    break;
                default:
                    throw new Error(`Unknown framework: ${framework}`);
            }
        },

        /**
         * Fire Elementor submit_success event
         */
        fireElementorEvent: function(formId, formElement) {
            // Fire native submit_success event
            const submitSuccessEvent = new CustomEvent('submit_success', {
                detail: {
                    success: true,
                    data: {
                        form_id: formId,
                        response: 'success'
                    }
                },
                bubbles: true
            });

            formElement.dispatchEvent(submitSuccessEvent);
            document.dispatchEvent(submitSuccessEvent);

            // Also fire jQuery event if available
            if (window.jQuery) {
                window.jQuery(formElement).trigger('submit_success', [{
                    success: true,
                    data: { form_id: formId }
                }]);
                window.jQuery(document).trigger('submit_success', [{
                    success: true,
                    data: { form_id: formId }
                }]);
            }

            // Fire Elementor specific event
            const elementorEvent = new CustomEvent('elementor/frontend/form_success', {
                detail: {
                    form: formElement,
                    form_id: formId,
                    success: true
                },
                bubbles: true
            });
            formElement.dispatchEvent(elementorEvent);
            document.dispatchEvent(elementorEvent);
        },

        /**
         * Fire Contact Form 7 wpcf7mailsent event
         */
        fireCF7Event: function(formId, formElement) {
            const cf7Event = new CustomEvent('wpcf7mailsent', {
                detail: {
                    contactFormId: formId,
                    status: 'mail_sent',
                    message: 'Thank you for your message. It has been sent.'
                },
                bubbles: true
            });

            formElement.dispatchEvent(cf7Event);
            document.dispatchEvent(cf7Event);

            if (window.jQuery) {
                window.jQuery(document).trigger('wpcf7mailsent', [cf7Event.detail]);
            }
        },

        /**
         * Fire Avada/Fusion form submit event
         */
        fireAvadaEvent: function(formId, formElement) {
            const submitEvent = new Event('submit', { bubbles: true });
            formElement.dispatchEvent(submitEvent);

            // Simulate form success
            setTimeout(() => {
                formElement.classList.add('fusion-form-sent');
                const successEvent = new CustomEvent('fusion-form-success', {
                    detail: { form_id: formId },
                    bubbles: true
                });
                formElement.dispatchEvent(successEvent);
            }, 100);
        },

        /**
         * Fire Ninja Forms submit event
         */
        fireNinjaEvent: function(formId, formElement) {
            const submitEvent = new Event('submit', { bubbles: true });
            formElement.dispatchEvent(submitEvent);

            if (window.jQuery) {
                window.jQuery(document).trigger('nfFormSubmitResponse', {
                    response: {
                        success: true,
                        form_id: formId
                    }
                });
            }
        },

        /**
         * Fire Gravity Forms submit event
         */
        fireGravityEvent: function(formId, formElement) {
            const submitEvent = new Event('submit', { bubbles: true });
            formElement.dispatchEvent(submitEvent);

            if (window.jQuery) {
                window.jQuery(document).trigger('gform_confirmation_loaded', [formId]);
            }
        },

        /**
         * Monitor dataLayer and show results
         */
        monitorAndShowResults: function(framework, formId, tempForm, initialLength, button, resultsDiv, statusDiv, eventsDiv, email) {
            const events = this.getNewDataLayerEvents(initialLength);
            const formSubmitEvent = events.find(e => e.event === 'form_submit' && e.cuft_tracked);
            const generateLeadEvent = events.find(e => e.event === 'generate_lead');

            // Reset button
            this.resetButton(button);

            // Show results
            this.showResults(resultsDiv, statusDiv, eventsDiv, {
                formSubmitEvent,
                generateLeadEvent,
                framework,
                email,
                formId
            });

            // Send email notification if configured
            this.sendEmailNotification(framework, formId, email, events);
        },

        /**
         * Get new dataLayer events since the initial length
         */
        getNewDataLayerEvents: function(initialLength) {
            if (!window.dataLayer) return [];
            return window.dataLayer.slice(initialLength);
        },

        /**
         * Show test results
         */
        showResults: function(resultsDiv, statusDiv, eventsDiv, data) {
            const { formSubmitEvent, generateLeadEvent, framework, email, formId } = data;

            let statusHtml = '';
            let eventsHtml = '';

            // Status summary
            if (formSubmitEvent) {
                statusHtml += '<span style="color: #28a745;">✓</span> form_submit event fired<br>';
                if (formSubmitEvent.cuft_tracked) {
                    statusHtml += '<span style="color: #28a745;">✓</span> cuft_tracked: true<br>';
                }
                if (formSubmitEvent.cuft_source) {
                    statusHtml += '<span style="color: #28a745;">✓</span> cuft_source: ' + formSubmitEvent.cuft_source + '<br>';
                }
            } else {
                statusHtml += '<span style="color: #dc3545;">✗</span> No form_submit event detected<br>';
            }

            if (generateLeadEvent) {
                statusHtml += '<span style="color: #28a745;">✓</span> generate_lead event fired<br>';
            }

            // Events details
            if (formSubmitEvent) {
                eventsHtml += this.formatEventHtml('form_submit', formSubmitEvent);
            }
            if (generateLeadEvent) {
                eventsHtml += this.formatEventHtml('generate_lead', generateLeadEvent);
            }

            statusDiv.innerHTML = statusHtml;
            eventsDiv.innerHTML = eventsHtml;
            resultsDiv.style.display = 'block';

            // Auto-hide after 10 seconds
            setTimeout(() => {
                resultsDiv.style.display = 'none';
            }, 10000);
        },

        /**
         * Format event data as HTML
         */
        formatEventHtml: function(eventType, eventData) {
            const eventJson = JSON.stringify(eventData, null, 2);
            const eventId = 'event_' + Math.random().toString(36).substr(2, 9);

            return `
                <div style="margin-top: 6px;">
                    <div style="display: flex; justify-content: between; align-items: center;">
                        <strong>${eventType} event:</strong>
                        <button type="button" class="cuft-copy-event-btn button button-secondary"
                                data-event-id="${eventId}"
                                style="font-size: 10px; padding: 2px 6px; margin-left: 8px;">
                            Copy
                        </button>
                    </div>
                    <pre id="${eventId}" style="font-size: 10px; background: #f1f1f1; padding: 4px; border-radius: 3px; margin: 4px 0; white-space: pre-wrap; overflow-x: auto;">${eventJson}</pre>
                </div>
            `;
        },

        /**
         * Send email notification (placeholder - would integrate with existing AJAX handler)
         */
        sendEmailNotification: function(framework, formId, email, events) {
            // This would integrate with the existing email notification system
            // For now, just log the intention
            console.log(`[CUFT Admin] Would send notification for ${framework} test ${formId}`);
        },

        /**
         * Copy event data to clipboard
         */
        copyEventData: function(button) {
            const eventId = button.dataset.eventId;
            const eventElement = document.getElementById(eventId);

            if (eventElement) {
                const text = eventElement.textContent;

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(() => {
                        this.showCopyFeedback(button, 'Copied!');
                    }).catch(() => {
                        this.fallbackCopy(text, button);
                    });
                } else {
                    this.fallbackCopy(text, button);
                }
            }
        },

        /**
         * Fallback copy method
         */
        fallbackCopy: function(text, button) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                this.showCopyFeedback(button, 'Copied!');
            } catch (err) {
                this.showCopyFeedback(button, 'Failed');
            }

            document.body.removeChild(textArea);
        },

        /**
         * Show copy feedback
         */
        showCopyFeedback: function(button, message) {
            const originalText = button.textContent;
            button.textContent = message;
            button.style.background = message === 'Copied!' ? '#28a745' : '#dc3545';

            setTimeout(() => {
                button.textContent = originalText;
                button.style.background = '';
            }, 1500);
        },

        /**
         * Show loading state
         */
        showLoading: function(button, resultsDiv, statusDiv) {
            button.textContent = 'Testing...';
            button.disabled = true;

            resultsDiv.style.display = 'block';
            statusDiv.innerHTML = '<span class="spinner is-active" style="float: none; vertical-align: middle;"></span> Running test...';
        },

        /**
         * Show error state
         */
        showError: function(resultsDiv, statusDiv, message) {
            resultsDiv.style.display = 'block';
            statusDiv.innerHTML = '<span style="color: #dc3545;">✗</span> ' + message;

            setTimeout(() => {
                resultsDiv.style.display = 'none';
            }, 5000);
        },

        /**
         * Reset button to normal state
         */
        resetButton: function(button) {
            const framework = button.closest('.cuft-quick-test-controls').dataset.framework;
            const frameworkName = this.getFrameworkDisplayName(framework);

            button.textContent = 'Test ' + frameworkName;
            button.disabled = false;
        },

        /**
         * Get display name for framework
         */
        getFrameworkDisplayName: function(framework) {
            const names = {
                'elementor': 'Elementor',
                'contact_form_7': 'CF7',
                'avada': 'Avada',
                'ninja_forms': 'Ninja',
                'gravity_forms': 'Gravity'
            };
            return names[framework] || framework;
        },

        /**
         * Validate email address
         */
        isValidEmail: function(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }
    };

    // Auto-initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            window.CUFTAdminQuickTests.init();
        });
    } else {
        window.CUFTAdminQuickTests.init();
    }

})();