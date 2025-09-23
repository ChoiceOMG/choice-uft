/**
 * CUFT Test Forms - Gravity Forms Implementation
 * Simulates real Gravity Forms behavior for accurate testing
 * Note: Gravity Forms generate_lead only requires Email + UTM Campaign (no phone/click_id needed)
 */
(function() {
    'use strict';

    // Ensure common utilities are loaded
    if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
        console.error('[CUFT Gravity Test] Common utilities not loaded');
        return;
    }

    const common = window.CUFTTestForms.common;

    // Gravity Forms implementation
    window.CUFTTestForms.gravity_forms = {

        /**
         * Initialize Gravity Forms test form
         */
        init: function(formElement) {
            if (!formElement) {
                common.log('Gravity Forms form element not found', 'error');
                return;
            }

            this.setupEventListeners(formElement);

            // Add testing controls
            common.addTestingControls(formElement, 'gravity_forms');

            common.log('Gravity Forms test form initialized');
        },

        /**
         * Setup event listeners for Gravity Forms
         */
        setupEventListeners: function(formElement) {
            const submitButton = formElement.querySelector('.cuft-submit-btn, .gform_button');

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
            common.log('Gravity Forms submission started');

            const submitButton = formElement.querySelector('.cuft-submit-btn, .gform_button');
            const resultDiv = formElement.querySelector('.test-result');
            const gformWrapper = formElement.closest('.gform_wrapper');

            // Get form field values
            const fieldValues = common.getFormFieldValues(formElement);

            // Validate required fields
            if (!fieldValues.email) {
                alert('Email is required');
                return;
            }

            // Set loading state
            this.setLoadingState(formElement, submitButton, gformWrapper);

            // Prepare tracking data
            const formId = formElement.dataset.formId || 'gform_1';
            const trackingData = common.getTestTrackingData('gravity_forms', formId);

            // Add form field values
            trackingData.user_email = fieldValues.email;
            trackingData.user_phone = fieldValues.phone;

            // Simulate Gravity Forms processing time
            setTimeout(() => {
                this.processSubmission(formElement, trackingData, submitButton, resultDiv, gformWrapper);
            }, 1200);
        },

        /**
         * Set loading state (Gravity Forms-specific behavior)
         */
        setLoadingState: function(formElement, submitButton, gformWrapper) {
            // Gravity Forms disables the entire form and shows processing
            if (gformWrapper) {
                gformWrapper.style.pointerEvents = 'none';
                gformWrapper.style.opacity = '0.7';
            }

            if (submitButton) {
                common.setSubmitButtonLoading(submitButton, 'Processing...');
            }

            common.log('Gravity Forms loading state activated');
        },

        /**
         * Process form submission
         */
        processSubmission: function(formElement, trackingData, submitButton, resultDiv, gformWrapper) {
            // Apply testing controls to modify tracking data
            trackingData = common.applyTestingControls(formElement, trackingData);

            // Fire form_submit event
            const formSubmitSuccess = common.fireFormSubmitEvent(trackingData);

            // Fire Gravity Forms-specific events
            this.fireGravityEvents(formElement, trackingData);

            // Fire generate_lead if requirements are met (Gravity only needs email + utm_campaign)
            const generateLeadFired = common.fireGenerateLeadEvent('gravity_forms', trackingData);

            // Send email notification
            this.sendEmailNotification(trackingData, (emailSent, trackingId) => {
                // Show success state
                this.showSuccessState(formElement, submitButton, resultDiv, gformWrapper, emailSent, trackingId, generateLeadFired);
            });
        },

        /**
         * Fire Gravity Forms-specific events
         */
        fireGravityEvents: function(formElement, trackingData) {
            const formId = trackingData.form_id.replace(/\D/g, '') || '1'; // Extract numeric ID

            // Fire Gravity Forms confirmation event
            if (window.jQuery) {
                window.jQuery(document).trigger('gform_confirmation_loaded', [parseInt(formId)]);
                common.log('✅ Gravity Forms gform_confirmation_loaded event fired');
            }

            // Fire page submit event
            const gformSubmitEvent = new CustomEvent('gform_page_loaded', {
                detail: {
                    formId: parseInt(formId),
                    currentPage: 1
                },
                bubbles: true
            });

            document.dispatchEvent(gformSubmitEvent);
            common.log('✅ Gravity Forms gform_page_loaded event fired');

            // Fire additional Gravity Forms events
            setTimeout(() => {
                if (window.jQuery) {
                    window.jQuery(document).trigger('gform_post_render', [parseInt(formId), 1]);
                    common.log('✅ Gravity Forms gform_post_render event fired');
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
            formData.append('framework', 'gravity_forms');
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
         * Show success state (Gravity Forms-specific styling)
         */
        showSuccessState: function(formElement, submitButton, resultDiv, gformWrapper, emailSent, trackingId, generateLeadFired) {
            // Reset form wrapper state
            if (gformWrapper) {
                gformWrapper.style.pointerEvents = 'auto';
                gformWrapper.style.opacity = '1';
            }

            // Hide the form and show confirmation (Gravity Forms behavior)
            formElement.style.display = 'none';

            // Show confirmation message
            const confirmationDiv = gformWrapper.querySelector('.gform_confirmation_message')
                || document.createElement('div');

            if (!confirmationDiv.classList.contains('gform_confirmation_message')) {
                confirmationDiv.className = 'gform_confirmation_message';
                confirmationDiv.style.cssText = `
                    padding: 15px;
                    background: #f0f8ff;
                    border: 2px solid #0073aa;
                    border-radius: 4px;
                    margin-top: 10px;
                    display: block;
                `;
                gformWrapper.appendChild(confirmationDiv);
            }

            // Generate success message
            let successMessage = common.getSuccessMessageHTML('gravity_forms', emailSent, trackingId);

            // Add generate_lead status
            if (generateLeadFired) {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    '✓ form_submit & generate_lead events tracked'
                );
            } else {
                successMessage = successMessage.replace(
                    '✓ Event tracked in dataLayer',
                    '✓ form_submit event tracked (generate_lead requires UTM campaign)'
                );
            }

            confirmationDiv.innerHTML = successMessage;
            confirmationDiv.style.display = 'block';

            // Show result div with additional info
            if (resultDiv) {
                resultDiv.innerHTML = `
                    <div style="padding: 10px; background: #e7f3ff; border-left: 4px solid #0073aa; border-radius: 4px; color: #0073aa; margin-top: 10px;">
                        ✅ Gravity Forms submission completed! Form hidden and confirmation shown (typical GF behavior).
                    </div>
                `;
                resultDiv.style.display = 'block';
            }

            // Reset after delay
            setTimeout(() => {
                formElement.style.display = 'block';
                confirmationDiv.style.display = 'none';
                if (resultDiv) {
                    resultDiv.style.display = 'none';
                }
            }, 8000);

            // Reset submit button
            common.resetSubmitButton(submitButton);

            common.log('Gravity Forms success state displayed');
        },

        /**
         * Get Gravity Forms form HTML structure
         */
        getFormHTML: function(formId, adminEmail) {
            const numericId = formId.replace(/\D/g, '') || '1';

            return `
                <div class="gform_body">
                    <ul class="gform_fields">
                        <li class="gfield gfield_email gfield_required">
                            <label class="gfield_label" for="input_${numericId}_1">Email Address</label>
                            <div class="ginput_container ginput_container_email">
                                <input name="input_1"
                                       id="input_${numericId}_1"
                                       type="email"
                                       value="${adminEmail}"
                                       class="large"
                                       readonly>
                            </div>
                        </li>
                        <li class="gfield gfield_phone">
                            <label class="gfield_label" for="input_${numericId}_2">Phone Number</label>
                            <div class="ginput_container ginput_container_phone">
                                <input name="input_2"
                                       id="input_${numericId}_2"
                                       type="tel"
                                       value="1-555-555-5555"
                                       class="large"
                                       readonly>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="gform_footer">
                    <input type="submit"
                           class="gform_button button cuft-submit-btn"
                           value="🚀 Submit Test Form"
                           style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                </div>
                <div class="gform_confirmation_message" style="display: none;"></div>
                <div class="test-result" style="display: none; margin-top: 10px;"></div>

                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
                    <div><strong>Form ID:</strong> ${formId}</div>
                    <div><strong>Click ID:</strong> test_click_gravity_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_gravity_forms</div>
                    <div><strong>Generate Lead:</strong> Email + UTM Campaign (phone & click_id NOT required)</div>
                </div>
            `;
        }
    };

    common.log('Gravity Forms test form module loaded');

})();