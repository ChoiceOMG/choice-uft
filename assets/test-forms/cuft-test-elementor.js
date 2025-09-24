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

            // Prepare tracking data
            const formId = formElement.dataset.formId || 'elementor-form-widget-test';

            // Update sessionStorage with test tracking data BEFORE getting tracking data
            common.updateTrackingDataForTest('elementor', formId);

            const trackingData = common.getTestTrackingData('elementor', formId);

            // Add form field values
            trackingData.user_email = fieldValues.email;
            trackingData.user_phone = fieldValues.phone;

            // Simulate Elementor processing time
            setTimeout(() => {
                this.processSubmission(formElement, trackingData, submitButton, resultDiv);
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
         * Process form submission
         */
        processSubmission: function(formElement, trackingData, submitButton, resultDiv) {
            // Apply testing controls to modify tracking data
            trackingData = common.applyTestingControls(formElement, trackingData);

            // Fire form_submit event
            const formSubmitSuccess = common.fireFormSubmitEvent(trackingData);

            // Fire Elementor-specific events
            this.fireElementorEvents(formElement, trackingData);

            // Fire generate_lead if requirements are met
            const generateLeadFired = common.fireGenerateLeadEvent('elementor', trackingData);

            // Send email notification
            this.sendEmailNotification(trackingData, (emailSent, trackingId) => {
                // Show success state
                this.showSuccessState(formElement, submitButton, resultDiv, emailSent, trackingId, generateLeadFired);
            });
        },

        /**
         * Fire Elementor-specific events
         */
        fireElementorEvents: function(formElement, trackingData) {
            // Fire native submit_success event
            const submitSuccessEvent = new CustomEvent('submit_success', {
                detail: {
                    success: true,
                    data: {
                        form_id: trackingData.form_id,
                        response: 'success'
                    }
                },
                bubbles: true
            });

            document.dispatchEvent(submitSuccessEvent);
            common.log('✅ Native submit_success event fired');

            // Also fire jQuery event if jQuery is available
            if (window.jQuery) {
                window.jQuery(document).trigger('submit_success', [{
                    success: true,
                    data: {
                        form_id: trackingData.form_id
                    }
                }]);
                common.log('✅ jQuery submit_success event fired');
            }

            // Fire Elementor 3.5+ specific event
            const elementorFormEvent = new CustomEvent('elementor/frontend/form_success', {
                detail: {
                    form: formElement,
                    form_id: trackingData.form_id,
                    success: true
                },
                bubbles: true
            });

            document.dispatchEvent(elementorFormEvent);
            common.log('✅ Elementor frontend form_success event fired');
        },

        /**
         * Send email notification
         */
        sendEmailNotification: function(trackingData, callback) {
            if (!window.cuftTestConfig || !window.cuftTestConfig.ajax_url) {
                common.log('AJAX URL not configured, skipping email', 'warn');
                callback(false, 'no-ajax');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'cuft_frontend_test_submit');
            formData.append('framework', 'elementor');
            formData.append('email', trackingData.user_email);
            formData.append('phone', trackingData.user_phone);
            formData.append('form_id', trackingData.form_id);

            // Add UTM parameters
            formData.append('utm_source', trackingData.utm_source);
            formData.append('utm_medium', trackingData.utm_medium);
            formData.append('utm_campaign', trackingData.utm_campaign);

            fetch(window.cuftTestConfig.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    common.log(`Email sent successfully (ID: ${data.data.tracking_id})`);
                    callback(data.data.email_sent, data.data.tracking_id);
                } else {
                    common.log(`Email failed: ${data.data.message}`, 'error');
                    callback(false, 'error');
                }
            })
            .catch(error => {
                common.log(`Email request failed: ${error.message}`, 'error');
                callback(false, 'error');
            });
        },

        /**
         * Show success state (Elementor-specific styling)
         */
        showSuccessState: function(formElement, submitButton, resultDiv, emailSent, trackingId, generateLeadFired) {
            // Reset form state
            formElement.style.opacity = '1';
            formElement.style.pointerEvents = 'auto';

            // Generate success message
            let successMessage = common.getSuccessMessageHTML('elementor', emailSent, trackingId);

            // Add generate_lead status
            if (generateLeadFired) {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    '✓ form_submit & generate_lead events tracked'
                );
            }

            // Show success message
            common.showSuccessMessage(resultDiv, successMessage, 6000);

            // Reset submit button
            common.resetSubmitButton(submitButton);

            common.log('Elementor success state displayed');
        },

        /**
         * Get Elementor form HTML structure
         */
        getFormHTML: function(formId, adminEmail) {
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
                    <div><strong>Form ID:</strong> ${formId}</div>
                    <div><strong>Click ID:</strong> test_click_elementor_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_elementor</div>
                    <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
                </div>
            `;
        }
    };

    common.log('Elementor test form module loaded');

})();