/**
 * CUFT Test Forms - Ninja Forms Implementation
 * Simulates real Ninja Forms behavior for accurate testing
 * Note: Ninja Forms generate_lead requires Email + Phone + Click ID (like Elementor)
 */
(function() {
    'use strict';

    // Ensure common utilities are loaded
    if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
        console.error('[CUFT Ninja Test] Common utilities not loaded');
        return;
    }

    const common = window.CUFTTestForms.common;

    // Ninja Forms implementation
    window.CUFTTestForms.ninja_forms = {

        /**
         * Initialize Ninja Forms test form
         */
        init: function(formElement) {
            if (!formElement) {
                common.log('Ninja Forms form element not found', 'error');
                return;
            }

            this.setupEventListeners(formElement);

            // Add testing controls
            common.addTestingControls(formElement, 'ninja_forms');

            common.log('Ninja Forms test form initialized');
        },

        /**
         * Setup event listeners for Ninja Forms
         */
        setupEventListeners: function(formElement) {
            const submitButton = formElement.querySelector('.cuft-submit-btn, .nf-element[type="button"]');

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
            common.log('Ninja Forms submission started');

            const submitButton = formElement.querySelector('.cuft-submit-btn, .nf-element[type="button"]');
            const resultDiv = formElement.querySelector('.test-result');
            const nfFormCont = formElement.closest('.nf-form-cont');

            // Get form field values
            const fieldValues = common.getFormFieldValues(formElement);

            // Validate required fields
            if (!fieldValues.email) {
                alert('Email is required');
                return;
            }

            // Set loading state
            this.setLoadingState(formElement, submitButton, nfFormCont);

            // Prepare tracking data
            const formId = formElement.dataset.formId || 'nf-form-3';
            const trackingData = common.getTestTrackingData('ninja_forms', formId);

            // Add form field values
            trackingData.user_email = fieldValues.email;
            trackingData.user_phone = fieldValues.phone;

            // Simulate Ninja Forms processing time
            setTimeout(() => {
                this.processSubmission(formElement, trackingData, submitButton, resultDiv, nfFormCont);
            }, 1000);
        },

        /**
         * Set loading state (Ninja Forms-specific behavior)
         */
        setLoadingState: function(formElement, submitButton, nfFormCont) {
            // Ninja Forms shows loading indicator
            if (nfFormCont) {
                nfFormCont.classList.add('nf-loading');
            }

            if (submitButton) {
                common.setSubmitButtonLoading(submitButton, 'Processing...');
            }

            common.log('Ninja Forms loading state activated');
        },

        /**
         * Process form submission
         */
        processSubmission: function(formElement, trackingData, submitButton, resultDiv, nfFormCont) {
            // Apply testing controls to modify tracking data
            trackingData = common.applyTestingControls(formElement, trackingData);

            // Fire form_submit event
            const formSubmitSuccess = common.fireFormSubmitEvent(trackingData);

            // Fire Ninja Forms-specific events
            this.fireNinjaEvents(formElement, trackingData);

            // Fire generate_lead if requirements are met (Ninja needs email + phone + click_id)
            const generateLeadFired = common.fireGenerateLeadEvent('ninja_forms', trackingData);

            // Send email notification
            this.sendEmailNotification(trackingData, (emailSent, trackingId) => {
                // Show success state
                this.showSuccessState(formElement, submitButton, resultDiv, nfFormCont, emailSent, trackingId, generateLeadFired);
            });
        },

        /**
         * Fire Ninja Forms-specific events
         */
        fireNinjaEvents: function(formElement, trackingData) {
            const formId = trackingData.form_id.replace(/\D/g, '') || '3'; // Extract numeric ID

            // Fire Ninja Forms Radio event if available
            if (window.nfRadio) {
                window.nfRadio.channel('forms').trigger('submit:response', {
                    response: {
                        success: true,
                        data: {
                            form_id: parseInt(formId),
                            success: 'Form submitted successfully!'
                        }
                    },
                    form_id: parseInt(formId)
                });
                common.log('✅ Ninja Forms nfRadio submit:response event fired');
            }

            // Fire generic Ninja Forms success event
            const ninjaSuccessEvent = new CustomEvent('nf_form_success', {
                detail: {
                    form: formElement,
                    formId: parseInt(formId),
                    response: {
                        success: true,
                        data: {
                            email: trackingData.user_email,
                            phone: trackingData.user_phone
                        }
                    }
                },
                bubbles: true
            });

            document.dispatchEvent(ninjaSuccessEvent);
            common.log('✅ Ninja Forms nf_form_success event fired');

            // Also fire jQuery event if jQuery is available
            if (window.jQuery) {
                window.jQuery(document).trigger('nf_form_success', [{
                    form_id: parseInt(formId),
                    response: { success: true }
                }]);
                common.log('✅ jQuery nf_form_success event fired');
            }

            // Fire submission complete event
            setTimeout(() => {
                if (window.nfRadio) {
                    window.nfRadio.channel('forms').trigger('submit:complete', {
                        form_id: parseInt(formId)
                    });
                    common.log('✅ Ninja Forms submit:complete event fired');
                }
            }, 200);
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
            formData.append('framework', 'ninja_forms');
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
         * Show success state (Ninja Forms-specific styling)
         */
        showSuccessState: function(formElement, submitButton, resultDiv, nfFormCont, emailSent, trackingId, generateLeadFired) {
            // Remove loading state
            if (nfFormCont) {
                nfFormCont.classList.remove('nf-loading');
                nfFormCont.classList.add('nf-success');
            }

            // Show Ninja Forms response message
            let responseDiv = nfFormCont.querySelector('.nf-response-msg');
            if (!responseDiv) {
                responseDiv = document.createElement('div');
                responseDiv.className = 'nf-response-msg';
                responseDiv.style.cssText = `
                    padding: 15px;
                    background: #dff0d8;
                    border: 1px solid #d6e9c6;
                    border-radius: 4px;
                    color: #3c763d;
                    margin-top: 10px;
                    display: block;
                `;
                nfFormCont.appendChild(responseDiv);
            }

            // Generate success message
            let successMessage = common.getSuccessMessageHTML('ninja_forms', emailSent, trackingId);

            // Add generate_lead status
            if (generateLeadFired) {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    '✓ form_submit & generate_lead events tracked'
                );
            }

            responseDiv.innerHTML = successMessage;
            responseDiv.style.display = 'block';

            // Show result div with additional info
            if (resultDiv) {
                resultDiv.innerHTML = `
                    <div style="padding: 10px; background: #e8f5e8; border-left: 4px solid #4caf50; border-radius: 4px; color: #2e7d32; margin-top: 10px;">
                        ✅ Ninja Forms submission completed! Success message displayed (typical NF behavior).
                    </div>
                `;
                resultDiv.style.display = 'block';
            }

            // Reset after delay
            setTimeout(() => {
                responseDiv.style.display = 'none';
                if (nfFormCont) {
                    nfFormCont.classList.remove('nf-success');
                }
                if (resultDiv) {
                    resultDiv.style.display = 'none';
                }
            }, 8000);

            // Reset submit button
            common.resetSubmitButton(submitButton);

            common.log('Ninja Forms success state displayed');
        },

        /**
         * Get Ninja Forms form HTML structure
         */
        getFormHTML: function(formId, adminEmail) {
            const numericId = formId.replace(/\D/g, '') || '3';

            return `
                <div class="nf-form-wrap">
                    <form class="nf-form" method="post">
                        <div class="nf-field-container email-container">
                            <div class="nf-field" data-field-type="email">
                                <label for="nf-field-${numericId}-email" class="nf-label">Email Address</label>
                                <input type="email"
                                       name="email"
                                       id="nf-field-${numericId}-email"
                                       class="nf-element"
                                       value="${adminEmail}"
                                       readonly>
                            </div>
                        </div>
                        <div class="nf-field-container phone-container">
                            <div class="nf-field" data-field-type="phone">
                                <label for="nf-field-${numericId}-phone" class="nf-label">Phone Number</label>
                                <input type="tel"
                                       name="phone"
                                       id="nf-field-${numericId}-phone"
                                       class="nf-element"
                                       value="1-555-555-5555"
                                       readonly>
                            </div>
                        </div>
                        <div class="nf-field-container submit-container">
                            <input type="button"
                                   class="nf-element cuft-submit-btn"
                                   value="🚀 Submit Test Form"
                                   style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                        </div>
                    </form>
                    <div class="nf-response-msg" style="display: none;"></div>
                </div>
                <div class="test-result" style="display: none; margin-top: 10px;"></div>

                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
                    <div><strong>Form ID:</strong> ${formId}</div>
                    <div><strong>Click ID:</strong> test_click_ninja_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_ninja_forms</div>
                    <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
                </div>
            `;
        }
    };

    common.log('Ninja Forms test form module loaded');

})();