/**
 * CUFT Test Forms - Contact Form 7 Implementation
 * Simulates real CF7 form behavior for accurate testing
 * Note: CF7 generate_lead only requires Email + UTM Campaign (no phone/click_id needed)
 */
(function() {
    'use strict';

    // Ensure common utilities are loaded
    if (!window.CUFTTestForms || !window.CUFTTestForms.common) {
        console.error('[CUFT CF7 Test] Common utilities not loaded');
        return;
    }

    const common = window.CUFTTestForms.common;

    // Contact Form 7 implementation
    window.CUFTTestForms.contact_form_7 = {

        /**
         * Initialize CF7 test form
         */
        init: function(formElement) {
            if (!formElement) {
                common.log('CF7 form element not found', 'error');
                return;
            }

            this.setupEventListeners(formElement);

            // Add testing controls
            common.addTestingControls(formElement, 'contact_form_7');

            // Update tracking info display with actual stored values
            setTimeout(() => {
                common.updateTrackingInfoDisplay('contact_form_7', formElement);
            }, 100);

            common.log('CF7 test form initialized');
        },

        /**
         * Setup event listeners for CF7 form
         */
        setupEventListeners: function(formElement) {
            const submitButton = formElement.querySelector('.cuft-submit-btn, .wpcf7-submit');

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
            common.log('CF7 form submission started');

            const submitButton = formElement.querySelector('.cuft-submit-btn, .wpcf7-submit');
            const resultDiv = formElement.querySelector('.test-result');
            const wpcf7Wrapper = formElement.closest('.wpcf7');

            // Get form field values
            const fieldValues = common.getFormFieldValues(formElement);

            // Validate required fields
            if (!fieldValues.email) {
                alert('Email is required');
                return;
            }

            // Set loading state
            this.setLoadingState(formElement, submitButton, wpcf7Wrapper);

            // Prepare tracking data for production code
            const formId = formElement.dataset.formId || 'wpcf7-f123-p456-o1';
            common.prepareTrackingDataForProduction('contact_form_7', formId, formElement);

            // Store form values for production code to find
            formElement.setAttribute('data-cuft-email', fieldValues.email || '');
            formElement.setAttribute('data-cuft-phone', fieldValues.phone || '');
            formElement.setAttribute('data-cuft-tracking', 'pending');

            // Simulate CF7 processing time then fire events for production code
            setTimeout(() => {
                this.fireCF7EventsForProduction(formElement, formId, submitButton, resultDiv, wpcf7Wrapper);
            }, 1000);
        },

        /**
         * Set loading state (CF7-specific behavior)
         */
        setLoadingState: function(formElement, submitButton, wpcf7Wrapper) {
            // CF7 shows a spinner icon and adds submitting class
            if (wpcf7Wrapper) {
                wpcf7Wrapper.classList.add('submitting');
            }

            if (submitButton) {
                common.setSubmitButtonLoading(submitButton, '⏳ Sending...');
            }

            common.log('CF7 loading state activated');
        },

        /**
         * Fire CF7 events for production code to handle
         */
        fireCF7EventsForProduction: function(formElement, formId, submitButton, resultDiv, wpcf7Wrapper) {
            const email = formElement.getAttribute('data-cuft-email') || '';
            const phone = formElement.getAttribute('data-cuft-phone') || '';
            const numericId = formId.replace(/\D/g, '') || '123'; // Extract numeric ID

            // Fire CF7's wpcf7mailsent event that production code listens for
            const cf7Event = new CustomEvent('wpcf7mailsent', {
                detail: {
                    contactFormId: numericId,
                    inputs: [
                        {
                            name: 'your-email',
                            value: email
                        },
                        {
                            name: 'your-phone',
                            value: phone
                        }
                    ],
                    apiResponse: {
                        status: 'success',
                        message: 'Thank you for your message. It has been sent.'
                    }
                },
                bubbles: true
            });

            document.dispatchEvent(cf7Event);
            common.log('✅ CF7 wpcf7mailsent event fired for production code');

            // Also fire jQuery event if jQuery is available
            if (window.jQuery) {
                window.jQuery(document).trigger('wpcf7mailsent', {
                    contactFormId: numericId,
                    inputs: [
                        { name: 'your-email', value: email },
                        { name: 'your-phone', value: phone }
                    ]
                });
                common.log('✅ jQuery wpcf7mailsent event fired for production code');
            }

            // Fire CF7's submit event as well
            setTimeout(() => {
                const cf7SubmitEvent = new CustomEvent('wpcf7submit', {
                    detail: {
                        contactFormId: numericId,
                        status: 'success'
                    },
                    bubbles: true
                });
                document.dispatchEvent(cf7SubmitEvent);
                common.log('✅ CF7 wpcf7submit event fired for production code');
            }, 100);

            // Send email notification after a delay (let production code fire first)
            setTimeout(() => {
                this.sendTestEmailNotification(formId, submitButton, resultDiv, wpcf7Wrapper);
            }, 1500);
        },

        /**
         * Send test email notification
         */
        sendTestEmailNotification: function(formId, submitButton, resultDiv, wpcf7Wrapper) {
            if (!window.cuftTestConfig || !window.cuftTestConfig.ajax_url) {
                common.log('AJAX URL not configured, skipping email', 'warn');
                this.showSuccessState(resultDiv, wpcf7Wrapper, false, 'no-ajax');
                common.resetSubmitButton(submitButton);
                return;
            }

            // Get form element to retrieve stored values
            const formElement = submitButton.closest('.cuft-test-form');
            const email = formElement.getAttribute('data-cuft-email') || '';
            const phone = formElement.getAttribute('data-cuft-phone') || '';

            const formData = new FormData();
            formData.append('action', 'cuft_frontend_test_submit');
            formData.append('framework', 'contact_form_7');
            formData.append('email', email);
            formData.append('phone', phone);
            formData.append('form_id', formId);

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
                    this.showSuccessState(formElement, submitButton, resultDiv, wpcf7Wrapper, data.data.email_sent, data.data.tracking_id);
                } else {
                    common.log(`Email failed: ${data.data.message}`, 'error');
                    this.showSuccessState(formElement, submitButton, resultDiv, wpcf7Wrapper, false, 'error');
                }
            })
            .catch(error => {
                common.log(`Email request failed: ${error.message}`, 'error');
                this.showSuccessState(formElement, submitButton, resultDiv, wpcf7Wrapper, false, 'error');
            });
        },


        /**
         * Show success state (CF7-specific styling)
         */
        showSuccessState: function(formElement, submitButton, resultDiv, wpcf7Wrapper, emailSent, trackingId) {
            // Remove CF7 submitting class
            if (wpcf7Wrapper) {
                wpcf7Wrapper.classList.remove('submitting');
                wpcf7Wrapper.classList.add('sent');

                // CF7 shows green border on successful submission
                formElement.style.border = '2px solid #46b450';

                setTimeout(() => {
                    formElement.style.border = '';
                    wpcf7Wrapper.classList.remove('sent');
                }, 6000);
            }

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
            let successMessage = common.getSuccessMessageHTML('contact_form_7', emailSent, trackingId);

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

            // Show success message
            common.showSuccessMessage(resultDiv, successMessage, 8000);

            // Reset submit button
            common.resetSubmitButton(submitButton);

            // Show CF7 response output (simulates real CF7 behavior)
            const responseOutput = formElement.parentElement.querySelector('.wpcf7-response-output');
            if (responseOutput) {
                responseOutput.innerHTML = 'Thank you for your message. It has been sent.';
                responseOutput.style.display = 'block';
                responseOutput.style.color = '#46b450';
                responseOutput.style.background = 'transparent';
                responseOutput.style.padding = '10px 0';

                setTimeout(() => {
                    responseOutput.style.display = 'none';
                }, 8000);
            }

            // Clean up stored attributes
            formElement.removeAttribute('data-cuft-email');
            formElement.removeAttribute('data-cuft-phone');
            formElement.removeAttribute('data-cuft-tracking');

            common.log('CF7 success state displayed with tracking status');
        },

        /**
         * Get CF7 form HTML structure
         */
        getFormHTML: function(formId, adminEmail) {
            return `
                <form class="wpcf7-form init" novalidate="novalidate" data-status="init">
                    <p>
                        <label>Email Address<br>
                            <span class="wpcf7-form-control-wrap" data-name="your-email">
                                <input size="40"
                                       class="wpcf7-form-control wpcf7-text wpcf7-email wpcf7-validates-as-required wpcf7-validates-as-email"
                                       type="email"
                                       name="your-email"
                                       value="${adminEmail}"
                                       readonly>
                            </span>
                        </label>
                    </p>
                    <p>
                        <label>Phone Number<br>
                            <span class="wpcf7-form-control-wrap" data-name="your-phone">
                                <input size="40"
                                       class="wpcf7-form-control wpcf7-text wpcf7-tel"
                                       type="tel"
                                       name="your-phone"
                                       value="1-555-555-5555"
                                       readonly>
                            </span>
                        </label>
                    </p>
                    <p>
                        <input class="wpcf7-form-control wpcf7-submit has-spinner cuft-submit-btn"
                               type="submit"
                               value="🚀 Submit Test Form"
                               style="background: #007cba; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 14px;">
                    </p>
                </form>
                <div class="wpcf7-response-output" aria-hidden="true" style="display: none;"></div>
                <div class="test-result" style="display: none; margin-top: 10px;"></div>

                <div style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; color: #6c757d; margin-top: 10px;">
                    <div><strong>Form ID:</strong> ${formId}</div>
                    <div><strong>Click ID:</strong> test_click_cf7_${Date.now()}</div>
                    <div><strong>Campaign:</strong> test_campaign_contact_form_7</div>
                    <div><strong>Generate Lead:</strong> Email + UTM Campaign (phone & click_id NOT required)</div>
                </div>
            `;
        }
    };

    common.log('Contact Form 7 test form module loaded');

})();