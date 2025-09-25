/**
 * CUFT Test Forms - Elementor Forms Implementation
 * Simulates real Elementor form behavior for accurate testing
 */
(function() {
    'use strict';

    // Ensure common utilities are loaded
    if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
        console.error('[CUFT Elementor Test] Common utilities not loaded');
        return;
    }

    const common = window.CUFTTestForms.common;

    // Elementor-specific implementation
    window.CUFTTestForms.elementor = {

        /**
         * Initialize Elementor test form
         */
        init: function(formElement) {
            if (!formElement) {
                common.log('Elementor form element not found', 'error');
                return;
            }

            this.setupEventListeners(formElement);

            // Add testing controls
            common.addTestingControls(formElement, 'elementor');

            // Update tracking info display with actual stored values
            setTimeout(() => {
                common.updateTrackingInfoDisplay('elementor', formElement);
            }, 100);

            common.log('Elementor test form initialized');
        },

        /**
         * Setup event listeners for Elementor form
         */
        setupEventListeners: function(formElement) {
            const submitButton = formElement.querySelector('.cuft-submit-btn, .elementor-button');

            if (submitButton) {
                submitButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    this.handleSubmission(formElement);
                });
            }

            // Also listen for actual form submission
            formElement.addEventListener('submit', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this.handleSubmission(formElement);
            });
        },

        /**
         * Handle form submission
         */
        handleSubmission: function(formElement) {
            common.log('Elementor form submission started');

            const submitButton = formElement.querySelector('.cuft-submit-btn, .elementor-button');
            const resultDiv = formElement.querySelector('.test-result');

            // Get form field values
            const fieldValues = common.getFormFieldValues(formElement);

            // Validate required fields
            if (!fieldValues.email) {
                alert('Email is required');
                return;
            }

            // Set loading state
            this.setLoadingState(formElement, submitButton);

            // Prepare tracking data for storage (production code will retrieve this)
            const form_id = formElement.dataset.formId || 'elementor-form-widget-test';
            common.prepareTrackingDataForProduction('elementor', form_id, formElement);

            // Store form values in the form element for production code to find
            formElement.setAttribute('data-cuft-email', fieldValues.email || '');
            formElement.setAttribute('data-cuft-phone', fieldValues.phone || '');
            formElement.setAttribute('data-cuft-tracking', 'pending');

            // Simulate Elementor processing time then fire events for production code
            setTimeout(() => {
                this.fireElementorEventsForProduction(formElement, form_id, submitButton, resultDiv);
            }, 800);
        },

        /**
         * Set loading state (Elementor-specific behavior)
         */
        setLoadingState: function(formElement, submitButton) {
            // Elementor shows a loading overlay
            formElement.style.opacity = '0.7';
            formElement.style.pointerEvents = 'none';

            if (submitButton) {
                common.setSubmitButtonLoading(submitButton, 'Sending...');
            }

            common.log('Elementor loading state activated');
        },


        /**
         * Fire Elementor events for production code to handle
         */
        fireElementorEventsForProduction: function(formElement, form_id, submitButton, resultDiv) {
            // Fire native submit_success event that production code listens for
            const submitSuccessEvent = new CustomEvent('submit_success', {
                detail: {
                    success: true,
                    data: {
                        form_id: form_id,
                        response: 'success'
                    }
                },
                bubbles: true
            });

            // Target the form element specifically
            formElement.dispatchEvent(submitSuccessEvent);
            document.dispatchEvent(submitSuccessEvent);
            common.log('✅ Native submit_success event fired for production code');

            // Also fire jQuery event if jQuery is available
            if (window.jQuery) {
                window.jQuery(formElement).trigger('submit_success', [{
                    success: true,
                    data: {
                        form_id: form_id
                    }
                }]);
                window.jQuery(document).trigger('submit_success', [{
                    success: true,
                    data: {
                        form_id: form_id
                    }
                }]);
                common.log('✅ jQuery submit_success event fired for production code');
            }

            // Fire Elementor 3.5+ specific event
            const elementorFormEvent = new CustomEvent('elementor/frontend/form_success', {
                detail: {
                    form: formElement,
                    form_id: form_id,
                    success: true
                },
                bubbles: true
            });

            formElement.dispatchEvent(elementorFormEvent);
            document.dispatchEvent(elementorFormEvent);
            common.log('✅ Elementor frontend form_success event fired for production code');

            // Send email notification after a short delay (let production code fire first)
            setTimeout(() => {
                this.sendTestEmailNotification(form_id, submitButton, resultDiv);
            }, 1500);
        },

        /**
         * Send test email notification
         */
        sendTestEmailNotification: function(form_id, submitButton, resultDiv) {
            if (!window.cuftTestConfig || !window.cuftTestConfig.ajax_url) {
                common.log('AJAX URL not configured, skipping email', 'warn');
                this.showSuccessState(resultDiv, false, 'no-ajax');
                common.resetSubmitButton(submitButton);
                return;
            }

            // Get form element to retrieve stored values
            const formElement = submitButton.closest('.cuft-test-form');
            const email = formElement.getAttribute('data-cuft-email') || '';
            const phone = formElement.getAttribute('data-cuft-phone') || '';

            const formData = new FormData();
            formData.append('action', 'cuft_frontend_test_submit');
            formData.append('framework', 'elementor');
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('form_id', form_id);

            // Add UTM parameters from stored tracking data
            try {
                const storedData = JSON.parse(sessionStorage.getItem('cuft_tracking_data'));
                if (storedData && storedData.tracking) {
                    if (storedData.tracking.utm_source) formData.append('utm_source', storedData.tracking.utm_source);
                    if (storedData.tracking.utm_medium) formData.append('utm_medium', storedData.tracking.utm_medium);
                    if (storedData.tracking.utm_campaign) formData.append('utm_campaign', storedData.tracking.utm_campaign);
                }
            } catch (e) {
                common.log('Could not retrieve UTM data for email', 'warn');
            }

            fetch(window.cuftTestConfig.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    common.log(`Email sent successfully (ID: ${data.data.tracking_id})`);
                    this.showSuccessState(formElement, submitButton, resultDiv, data.data.email_sent, data.data.tracking_id);
                } else {
                    common.log(`Email failed: ${data.data.message}`, 'error');
                    this.showSuccessState(formElement, submitButton, resultDiv, false, 'error');
                }
            })
            .catch(error => {
                common.log(`Email request failed: ${error.message}`, 'error');
                this.showSuccessState(formElement, submitButton, resultDiv, false, 'error');
            });
        },

        /**
         * Show success state (Elementor-specific styling)
         */
        showSuccessState: function(formElement, submitButton, resultDiv, emailSent, trackingId) {
            // Reset form state
            formElement.style.opacity = '1';
            formElement.style.pointerEvents = 'auto';

            // Check dataLayer for events that production code should have fired
            let eventsTracked = [];
            if (window.dataLayer) {
                const recentEvents = window.dataLayer.slice(-10); // Check last 10 events
                const formSubmitEvent = recentEvents.find(e => e.event === 'form_submit' && e.cuft_source);
                const generateLeadEvent = recentEvents.find(e => e.event === 'generate_lead');

                if (formSubmitEvent) {
                    eventsTracked.push('form_submit');
                    if (formSubmitEvent.cuft_tracked) eventsTracked.push('cuft_tracked: true');
                    if (formSubmitEvent.cuft_source) eventsTracked.push(`cuft_source: ${formSubmitEvent.cuft_source}`);
                }
                if (generateLeadEvent) {
                    eventsTracked.push('generate_lead');
                }
            }

            // Generate success message with tracking status
            let successMessage = common.getSuccessMessageHTML('elementor', emailSent, trackingId);

            if (eventsTracked.length > 0) {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    `✓ Events tracked: ${eventsTracked.join(', ')}`
                );
            } else {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    '⚠️ No tracking events detected - check production code'
                );
            }

            // Show success message
            common.showSuccessMessage(resultDiv, successMessage, 8000);

            // Reset submit button
            common.resetSubmitButton(submitButton);

            // Clean up stored attributes
            formElement.removeAttribute('data-cuft-email');
            formElement.removeAttribute('data-cuft-phone');
            formElement.removeAttribute('data-cuft-tracking');

            common.log('Elementor success state displayed with tracking status');
        },

        /**
         * Get Elementor form HTML structure
         */
        getFormHTML: function(form_id, adminEmail) {
            return `
                <div class="elementor-form-fields-wrapper">
                    <div class="elementor-field-group elementor-column elementor-col-100">
                        <label for="form-field-email" class="elementor-field-label">Email Address</label>
                        <input size="1"
                               type="email"
                               name="form_fields[email]"
                               id="form-field-email"
                               class="elementor-field elementor-size-sm"
                               data-field-type="email"
                               data-field="email"
                               inputmode="email"
                               value="${adminEmail}"
                               readonly>
                    </div>
                    <div class="elementor-field-group elementor-column elementor-col-100">
                        <label for="form-field-phone" class="elementor-field-label">Phone Number</label>
                        <input size="1"
                               type="tel"
                               name="form_fields[phone]"
                               id="form-field-phone"
                               class="elementor-field elementor-size-sm"
                               data-field-type="tel"
                               data-field="phone"
                               inputmode="tel"
                               pattern="[0-9()#&+*-=.]+"
                               value="1-555-555-5555"
                               readonly>
                    </div>
                    <div class="elementor-field-group elementor-column elementor-col-100">
                        <button type="submit"
                                class="elementor-button elementor-size-sm cuft-submit-btn"
                                style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            🚀 Submit Test Form
                        </button>
                    </div>
                </div>
                <div class="test-result" style="display: none; margin-top: 10px;"></div>

                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
                    <div><strong>Form ID:</strong> ${form_id}</div>
                    <div><strong>Click ID:</strong> test_click_elementor_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_elementor</div>
                    <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
                </div>
            `;
        }
    };

    common.log('Elementor test form module loaded');

})();