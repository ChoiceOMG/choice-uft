/**
 * CUFT Test Forms - Avada/Fusion Forms Implementation
 * Simulates real Avada/Fusion form behavior for accurate testing
 * Note: Avada generate_lead requires Email + Phone + Click ID (like Elementor)
 */
(function() {
    'use strict';

    // Ensure common utilities are loaded
    if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
        console.error('[CUFT Avada Test] Common utilities not loaded');
        return;
    }

    const common = window.CUFTTestForms.common;

    // Avada/Fusion Forms implementation
    window.CUFTTestForms.avada = {

        /**
         * Initialize Avada test form
         */
        init: function(formElement) {
            if (!formElement) {
                common.log('Avada form element not found', 'error');
                return;
            }

            this.setupEventListeners(formElement);

            // Add testing controls
            common.addTestingControls(formElement, 'avada');

            // Update tracking info display with actual stored values
            setTimeout(() => {
                common.updateTrackingInfoDisplay('avada', formElement);
            }, 100);

            common.log('Avada test form initialized');
        },

        /**
         * Setup event listeners for Avada form
         */
        setupEventListeners: function(formElement) {
            const submitButton = formElement.querySelector('.cuft-submit-btn, .fusion-button');

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
            common.log('Avada form submission started');

            const submitButton = formElement.querySelector('.cuft-submit-btn, .fusion-button');
            const resultDiv = formElement.querySelector('.test-result');
            const fusionForm = formElement.closest('.fusion-form');

            // Get form field values
            const fieldValues = common.getFormFieldValues(formElement);

            // Validate required fields
            if (!fieldValues.email) {
                alert('Email is required');
                return;
            }

            // Set loading state
            this.setLoadingState(formElement, submitButton, fusionForm);

            // Prepare tracking data
            const formId = formElement.dataset.formId || 'fusion_form_1';

            // Update sessionStorage with test tracking data BEFORE getting tracking data
            common.updateTrackingDataForTest('avada', formId);

            const trackingData = common.getTestTrackingData('avada', formId);

            // Add form field values
            trackingData.user_email = fieldValues.email;
            trackingData.user_phone = fieldValues.phone;

            // Simulate Avada processing time
            setTimeout(() => {
                this.processSubmission(formElement, trackingData, submitButton, resultDiv, fusionForm);
            }, 900);
        },

        /**
         * Set loading state (Avada-specific behavior)
         */
        setLoadingState: function(formElement, submitButton, fusionForm) {
            // Avada shows loading indicator and disables form
            if (fusionForm) {
                fusionForm.classList.add('fusion-form-loading');
                fusionForm.style.opacity = '0.8';
            }

            if (submitButton) {
                common.setSubmitButtonLoading(submitButton, '⏳ Submitting...');
            }

            common.log('Avada loading state activated');
        },

        /**
         * Process form submission
         */
        processSubmission: function(formElement, trackingData, submitButton, resultDiv, fusionForm) {
            // Apply testing controls to modify tracking data
            trackingData = common.applyTestingControls(formElement, trackingData);

            // Fire form_submit event
            const formSubmitSuccess = common.fireFormSubmitEvent(trackingData);

            // Fire Avada-specific events
            this.fireAvadaEvents(formElement, trackingData);

            // Fire generate_lead if requirements are met (Avada needs email + phone + click_id)
            const generateLeadFired = common.fireGenerateLeadEvent('avada', trackingData);

            // Send email notification
            this.sendEmailNotification(trackingData, (emailSent, trackingId) => {
                // Show success state
                this.showSuccessState(formElement, submitButton, resultDiv, fusionForm, emailSent, trackingId, generateLeadFired);
            });
        },

        /**
         * Fire Avada-specific events
         */
        fireAvadaEvents: function(formElement, trackingData) {
            // Fire Fusion form success event
            const fusionSuccessEvent = new CustomEvent('fusion_form_success', {
                detail: {
                    form: formElement,
                    form_id: trackingData.form_id,
                    success: true,
                    data: {
                        email: trackingData.user_email,
                        phone: trackingData.user_phone
                    }
                },
                bubbles: true
            });

            document.dispatchEvent(fusionSuccessEvent);
            common.log('✅ Fusion form_success event fired');

            // Also fire jQuery event if jQuery is available
            if (window.jQuery) {
                window.jQuery(document).trigger('fusion_form_success', [{
                    form: formElement,
                    form_id: trackingData.form_id,
                    success: true
                }]);
                common.log('✅ jQuery fusion_form_success event fired');
            }

            // Fire Avada form submitted event
            setTimeout(() => {
                const fusionSubmitEvent = new CustomEvent('fusion_form_submitted', {
                    detail: {
                        form_id: trackingData.form_id,
                        response: 'success'
                    },
                    bubbles: true
                });
                document.dispatchEvent(fusionSubmitEvent);
                common.log('✅ Fusion form_submitted event fired');
            }, 100);
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
            formData.append('framework', 'avada');
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
         * Show success state (Avada-specific styling)
         */
        showSuccessState: function(formElement, submitButton, resultDiv, fusionForm, emailSent, trackingId, generateLeadFired) {
            // Reset form state
            if (fusionForm) {
                fusionForm.classList.remove('fusion-form-loading');
                fusionForm.classList.add('fusion-form-success');
                fusionForm.style.opacity = '1';
            }

            // Hide form and show success response (Avada behavior)
            formElement.style.display = 'none';

            // Show Avada success response
            let successResponseDiv = fusionForm.querySelector('.fusion-form-response-success');
            if (!successResponseDiv) {
                successResponseDiv = document.createElement('div');
                successResponseDiv.className = 'fusion-form-response-success';
                successResponseDiv.style.cssText = `
                    padding: 15px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border-radius: 4px;
                    margin-top: 10px;
                    display: block;
                `;
                fusionForm.appendChild(successResponseDiv);
            }

            // Generate success message
            let successMessage = common.getSuccessMessageHTML('avada', emailSent, trackingId);

            // Add generate_lead status
            if (generateLeadFired) {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    '✓ form_submit & generate_lead events tracked'
                );
            }

            successResponseDiv.innerHTML = successMessage;
            successResponseDiv.style.display = 'block';

            // Show result div with additional info
            if (resultDiv) {
                resultDiv.innerHTML = `
                    <div style="padding: 10px; background: #f3e5f5; border-left: 4px solid #9c27b0; border-radius: 4px; color: #6a1b9a; margin-top: 10px;">
                        ✅ Avada/Fusion form submitted! Form hidden and success response shown (typical Avada behavior).
                    </div>
                `;
                resultDiv.style.display = 'block';
            }

            // Reset after delay
            setTimeout(() => {
                formElement.style.display = 'block';
                successResponseDiv.style.display = 'none';
                if (fusionForm) {
                    fusionForm.classList.remove('fusion-form-success');
                }
                if (resultDiv) {
                    resultDiv.style.display = 'none';
                }
            }, 8000);

            // Reset submit button
            common.resetSubmitButton(submitButton);

            common.log('Avada success state displayed');
        },

        /**
         * Get Avada form HTML structure
         */
        getFormHTML: function(formId, adminEmail) {
            return `
                <form class="fusion-form-form" method="post" enctype="multipart/form-data">
                    <div class="fusion-form-field fusion-form-field-email">
                        <label for="fusion-form-field-email">Email Address</label>
                        <input type="email"
                               name="email"
                               id="fusion-form-field-email"
                               class="fusion-form-input"
                               value="${adminEmail}"
                               readonly>
                    </div>
                    <div class="fusion-form-field fusion-form-field-phone">
                        <label for="fusion-form-field-phone">Phone Number</label>
                        <input type="tel"
                               name="phone"
                               id="fusion-form-field-phone"
                               class="fusion-form-input"
                               value="1-555-555-5555"
                               readonly>
                    </div>
                    <div class="fusion-form-field fusion-form-submit-field">
                        <button type="submit"
                                class="fusion-button fusion-button-default cuft-submit-btn"
                                style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            🚀 Submit Test Form
                        </button>
                    </div>
                </form>
                <div class="fusion-form-response-success" style="display: none;">
                    <div class="fusion-alert success">Form submitted successfully!</div>
                </div>
                <div class="test-result" style="display: none; margin-top: 10px;"></div>

                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
                    <div><strong>Form ID:</strong> ${formId}</div>
                    <div><strong>Click ID:</strong> test_click_avada_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_avada</div>
                    <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
                </div>
            `;
        }
    };

    common.log('Avada/Fusion test form module loaded');

})();