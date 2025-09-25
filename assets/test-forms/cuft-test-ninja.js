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

            // Update tracking info display with actual stored values
            setTimeout(() => {
                common.updateTrackingInfoDisplay('ninja_forms', formElement);
            }, 100);

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

            // Prepare tracking data for production code
            const form_id = formElement.dataset.formId || 'nf-form-3';
            common.prepareTrackingDataForProduction('ninja_forms', form_id, formElement);

            // Store form values for production code to find
            formElement.setAttribute('data-cuft-email', fieldValues.email || '');
            formElement.setAttribute('data-cuft-phone', fieldValues.phone || '');
            formElement.setAttribute('data-cuft-tracking', 'pending');

            // Simulate Ninja Forms processing time then fire events for production code
            setTimeout(() => {
                this.fireNinjaEventsForProduction(formElement, form_id, submitButton, resultDiv, nfFormCont);
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
        fireNinjaEventsForProduction: function(formElement, form_id, submitButton, resultDiv, nfFormCont) {
            const numericId = form_id.replace(/\D/g, '') || '3'; // Extract numeric ID
            const email = formElement.getAttribute('data-cuft-email') || '';
            const phone = formElement.getAttribute('data-cuft-phone') || '';

            // Fire nfFormSubmitResponse event that production code listens for
            const nfEvent = new CustomEvent('nfFormSubmitResponse', {
                detail: {
                    form_id: parseInt(numericId),
                    response: {
                        success: true,
                        data: {
                            form_id: parseInt(numericId),
                            success: 'Form submitted successfully!',
                            email: email,
                            phone: phone
                        }
                    }
                },
                bubbles: true
            });

            formElement.dispatchEvent(nfEvent);
            document.dispatchEvent(nfEvent);
            common.log('✅ Ninja Forms nfFormSubmitResponse event fired for production code');

            // Also fire jQuery event if jQuery is available
            if (window.jQuery) {
                window.jQuery(document).trigger('nfFormSubmitResponse', [{
                    form_id: parseInt(numericId),
                    response: {
                        success: true,
                        data: {
                            email: email,
                            phone: phone
                        }
                    }
                }]);
                common.log('✅ jQuery nfFormSubmitResponse event fired for production code');
            }

            // Fire legacy Ninja Forms Radio event if available
            if (window.nfRadio) {
                window.nfRadio.channel('forms').trigger('submit:response', {
                    response: {
                        success: true,
                        data: {
                            form_id: parseInt(numericId),
                            success: 'Form submitted successfully!'
                        }
                    },
                    form_id: parseInt(numericId)
                });
                common.log('✅ Ninja Forms nfRadio event fired for production code');
            }

            // Send email notification after a short delay (let production code fire first)
            setTimeout(() => {
                this.sendTestEmailNotification(form_id, submitButton, resultDiv, nfFormCont);
            }, 1500);
        },


        /**
         * Send test email notification
         */
        sendTestEmailNotification: function(form_id, submitButton, resultDiv, nfFormCont) {
            if (!window.cuftTestConfig || !window.cuftTestConfig.ajax_url) {
                common.log('AJAX URL not configured, skipping email', 'warn');
                this.showSuccessState(resultDiv, false, 'no-ajax', submitButton, nfFormCont);
                return;
            }

            // Get form element to retrieve stored values
            const formElement = submitButton.closest('.cuft-test-form');
            const email = formElement.getAttribute('data-cuft-email') || '';
            const phone = formElement.getAttribute('data-cuft-phone') || '';

            const formData = new FormData();
            formData.append('action', 'cuft_frontend_test_submit');
            formData.append('framework', 'ninja_forms');
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('form_id', form_id);

            // Add UTM parameters from stored tracking data
            try {
                const storedData = JSON.parse(sessionStorage.getItem('cuft_tracking_data'));
                if (storedData && storedData.tracking) {
                    formData.append('utm_source', storedData.tracking.utm_source || '');
                    formData.append('utm_medium', storedData.tracking.utm_medium || '');
                    formData.append('utm_campaign', storedData.tracking.utm_campaign || '');
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
                    this.showSuccessState(resultDiv, true, data.data.tracking_id, submitButton, nfFormCont);
                } else {
                    common.log(`Email failed: ${data.data.message}`, 'error');
                    this.showSuccessState(resultDiv, false, 'error', submitButton, nfFormCont);
                }
            })
            .catch(error => {
                common.log(`Email request failed: ${error.message}`, 'error');
                this.showSuccessState(resultDiv, false, 'error', submitButton, nfFormCont);
            });
        },

        /**
         * Show success state (Ninja Forms-specific styling)
         */
        showSuccessState: function(resultDiv, emailSent, trackingId, submitButton, nfFormCont) {
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
        getFormHTML: function(form_id, adminEmail) {
            const numericId = form_id.replace(/\D/g, '') || '3';

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
                    <div><strong>Form ID:</strong> ${form_id}</div>
                    <div><strong>Click ID:</strong> test_click_ninja_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_ninja_forms</div>
                    <div><strong>Generate Lead:</strong> Email + Phone + Click ID (all required)</div>
                </div>
            `;
        }
    };

    common.log('Ninja Forms test form module loaded');

})();