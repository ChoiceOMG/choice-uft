/**
 * CUFT Test Forms - Common Utilities
 * Shared functionality for all framework-specific test forms
 */
(function() {
    'use strict';

    // Global namespace for test forms
    window.CUFTTestForms = window.CUFTTestForms || {};

    // Common utilities
    window.CUFTTestForms.common = {

        /**
         * Get current tracking data for test submission
         */
        getTestTrackingData: function(framework, formId) {
            const timestamp = Date.now();

            return {
                event: 'form_submit',
                form_framework: framework,
                form_id: formId,
                form_name: `Test ${framework} Form`,
                test_submission: true,
                timestamp: new Date().toISOString(),
                submittedAt: new Date().toISOString(),

                // Click IDs (all test forms will have these)
                click_id: `test_click_${framework}_${timestamp}`,
                gclid: `test_gclid_${framework}_${timestamp}`,

                // UTM parameters
                utm_source: 'cuft_test',
                utm_medium: 'test_form',
                utm_campaign: `test_campaign_${framework}`,
                utm_term: 'test_term',
                utm_content: 'test_content'
            };
        },

        /**
         * Get generate_lead requirements for each framework
         */
        getGenerateLeadRequirements: function(framework) {
            const requirements = {
                elementor: {
                    email: true,
                    phone: true,
                    click_id: true,
                    utm_campaign: false
                },
                avada: {
                    email: true,
                    phone: true,
                    click_id: true,
                    utm_campaign: false
                },
                contact_form_7: {
                    email: true,
                    phone: false,
                    click_id: false,
                    utm_campaign: true
                },
                gravity_forms: {
                    email: true,
                    phone: false,
                    click_id: false,
                    utm_campaign: true
                },
                ninja_forms: {
                    email: true,
                    phone: true,
                    click_id: true,
                    utm_campaign: false
                }
            };

            return requirements[framework] || requirements.elementor;
        },

        /**
         * Check if generate_lead should fire based on framework requirements
         */
        shouldFireGenerateLead: function(framework, formData) {
            const requirements = this.getGenerateLeadRequirements(framework);

            // Check email requirement
            if (requirements.email && !formData.user_email) {
                console.log(`[CUFT Test] Generate lead blocked: Email required for ${framework}`);
                return false;
            }

            // Check phone requirement
            if (requirements.phone && !formData.user_phone) {
                console.log(`[CUFT Test] Generate lead blocked: Phone required for ${framework}`);
                return false;
            }

            // Check click_id requirement
            if (requirements.click_id && !formData.click_id && !formData.gclid && !formData.fbclid) {
                console.log(`[CUFT Test] Generate lead blocked: Click ID required for ${framework}`);
                return false;
            }

            // Check UTM campaign requirement
            if (requirements.utm_campaign && !formData.utm_campaign) {
                console.log(`[CUFT Test] Generate lead blocked: UTM campaign required for ${framework}`);
                return false;
            }

            return true;
        },

        /**
         * Fire generate_lead event if requirements are met
         */
        fireGenerateLeadEvent: function(framework, formData) {
            if (!this.shouldFireGenerateLead(framework, formData)) {
                return false;
            }

            const generateLeadData = {
                event: 'generate_lead',
                currency: 'USD',
                value: 0,
                form_framework: framework,
                user_email: formData.user_email,
                user_phone: formData.user_phone || '',
                click_id: formData.click_id || formData.gclid || formData.fbclid || '',
                utm_campaign: formData.utm_campaign || '',
                test_submission: true,
                timestamp: new Date().toISOString()
            };

            if (window.dataLayer) {
                window.dataLayer.push(generateLeadData);
                console.log(`[CUFT Test] ✅ generate_lead event fired for ${framework}:`, generateLeadData);
                return true;
            } else {
                console.error('[CUFT Test] ❌ dataLayer not found for generate_lead event');
                return false;
            }
        },

        /**
         * Push form_submit event to dataLayer
         */
        fireFormSubmitEvent: function(formData) {
            if (window.dataLayer) {
                window.dataLayer.push(formData);
                console.log(`[CUFT Test] ✅ form_submit event fired:`, formData);
                return true;
            } else {
                console.error('[CUFT Test] ❌ dataLayer not found for form_submit event');
                return false;
            }
        },

        /**
         * Get form field values
         */
        getFormFieldValues: function(form) {
            const emailInput = form.querySelector('input[type="email"], input[name*="email"], input[data-field="email"]');
            const phoneInput = form.querySelector('input[type="tel"], input[name*="phone"], input[data-field="phone"]');

            return {
                email: emailInput ? emailInput.value : '',
                phone: phoneInput ? phoneInput.value : ''
            };
        },

        /**
         * Disable submit button with loading state
         */
        setSubmitButtonLoading: function(button, loadingText) {
            if (!button) return;

            button.disabled = true;
            button.dataset.originalText = button.textContent;
            button.textContent = loadingText || 'Submitting...';
        },

        /**
         * Re-enable submit button
         */
        resetSubmitButton: function(button, delay = 2000) {
            if (!button) return;

            setTimeout(() => {
                button.disabled = false;
                button.textContent = button.dataset.originalText || '🚀 Submit Test Form';
            }, delay);
        },

        /**
         * Show success message
         */
        showSuccessMessage: function(container, message, duration = 5000) {
            if (!container) return;

            container.style.display = 'block';
            container.innerHTML = message;

            setTimeout(() => {
                container.style.display = 'none';
                container.innerHTML = '';
            }, duration);
        },

        /**
         * Log test form action
         */
        log: function(message, type = 'info') {
            const prefix = '[CUFT Test Forms]';
            const logMethod = type === 'error' ? 'error' : type === 'warn' ? 'warn' : 'log';
            console[logMethod](prefix, message);
        },

        /**
         * Add testing controls to form
         */
        addTestingControls: function(formElement, framework) {
            const controlsDiv = document.createElement('div');
            controlsDiv.className = 'cuft-testing-controls';
            controlsDiv.style.cssText = `
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 4px;
                padding: 10px;
                margin: 10px 0;
                font-size: 12px;
            `;

            const requirements = this.getGenerateLeadRequirements(framework);

            controlsDiv.innerHTML = `
                <div style="font-weight: bold; margin-bottom: 8px; color: #856404;">🧪 Testing Controls</div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 8px;">
                    <label style="display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" class="test-control-email" ${requirements.email ? 'checked' : ''}>
                        <span style="color: ${requirements.email ? '#28a745' : '#6c757d'};">Email ${requirements.email ? '(required)' : ''}</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" class="test-control-phone" ${requirements.phone ? 'checked' : ''}>
                        <span style="color: ${requirements.phone ? '#28a745' : '#6c757d'};">Phone ${requirements.phone ? '(required)' : ''}</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" class="test-control-click-id" ${requirements.click_id ? 'checked' : ''}>
                        <span style="color: ${requirements.click_id ? '#28a745' : '#6c757d'};">Click ID ${requirements.click_id ? '(required)' : ''}</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 4px;">
                        <input type="checkbox" class="test-control-utm-campaign" ${requirements.utm_campaign ? 'checked' : ''}>
                        <span style="color: ${requirements.utm_campaign ? '#28a745' : '#6c757d'};">UTM Campaign ${requirements.utm_campaign ? '(required)' : ''}</span>
                    </label>
                </div>
                <div style="margin-top: 6px; font-size: 11px; color: #6c757d;">
                    Toggle controls to test different generate_lead scenarios. Required fields for ${framework} are highlighted in green.
                </div>
            `;

            // Insert controls before the form
            formElement.parentNode.insertBefore(controlsDiv, formElement);

            // Add event listeners to controls
            this.setupTestingControlListeners(formElement, framework);

            return controlsDiv;
        },

        /**
         * Setup testing control event listeners
         */
        setupTestingControlListeners: function(formElement, framework) {
            const controlsDiv = formElement.parentNode.querySelector('.cuft-testing-controls');
            if (!controlsDiv) return;

            const controls = {
                email: controlsDiv.querySelector('.test-control-email'),
                phone: controlsDiv.querySelector('.test-control-phone'),
                clickId: controlsDiv.querySelector('.test-control-click-id'),
                utmCampaign: controlsDiv.querySelector('.test-control-utm-campaign')
            };

            // Update form data based on controls when they change
            Object.keys(controls).forEach(key => {
                if (controls[key]) {
                    controls[key].addEventListener('change', () => {
                        this.updateFormBasedOnControls(formElement, framework, controls);
                    });
                }
            });
        },

        /**
         * Update form data based on testing controls
         */
        updateFormBasedOnControls: function(formElement, framework, controls) {
            // Store control states in form element for later use during submission
            formElement.dataset.testControlEmail = controls.email ? controls.email.checked : 'true';
            formElement.dataset.testControlPhone = controls.phone ? controls.phone.checked : 'true';
            formElement.dataset.testControlClickId = controls.clickId ? controls.clickId.checked : 'true';
            formElement.dataset.testControlUtmCampaign = controls.utmCampaign ? controls.utmCampaign.checked : 'true';

            this.log(`Testing controls updated for ${framework}:`, 'info');
            this.log(`Email: ${formElement.dataset.testControlEmail}, Phone: ${formElement.dataset.testControlPhone}, Click ID: ${formElement.dataset.testControlClickId}, UTM: ${formElement.dataset.testControlUtmCampaign}`, 'info');
        },

        /**
         * Apply testing controls to tracking data
         */
        applyTestingControls: function(formElement, trackingData) {
            // Apply email control
            if (formElement.dataset.testControlEmail === 'false') {
                trackingData.user_email = '';
            }

            // Apply phone control
            if (formElement.dataset.testControlPhone === 'false') {
                trackingData.user_phone = '';
            }

            // Apply click ID control
            if (formElement.dataset.testControlClickId === 'false') {
                delete trackingData.click_id;
                delete trackingData.gclid;
                delete trackingData.fbclid;
            }

            // Apply UTM campaign control
            if (formElement.dataset.testControlUtmCampaign === 'false') {
                trackingData.utm_campaign = '';
            }

            this.log('Applied testing controls to tracking data:', 'info');
            this.log(trackingData, 'data');

            return trackingData;
        },

        /**
         * Generate framework-specific success message HTML
         */
        getSuccessMessageHTML: function(framework, emailSent = true, trackingId = '') {
            const emailStatus = emailSent
                ? `<br><small>✅ Email sent to admin${trackingId ? ` (ID: ${trackingId})` : ''}</small>`
                : `<br><small>⚠️ Email failed to send${trackingId ? ` (ID: ${trackingId})` : ''}</small>`;

            const messages = {
                elementor: `
                    <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; margin-top: 10px;">
                        <strong>The form was sent successfully.</strong><br>
                        <small>✓ Event tracked in dataLayer</small>${emailStatus}
                    </div>
                `,
                avada: `
                    <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px; margin-top: 10px;">
                        <strong>Thank You!</strong> Your submission has been received.<br>
                        <small>✓ Event tracked in dataLayer</small>${emailStatus}
                    </div>
                `,
                contact_form_7: `
                    <div style="padding: 10px; background: transparent; color: #46b450; margin-top: 10px; font-weight: bold;">
                        Thank you for your message. It has been sent.<br>
                        <small>✓ Event tracked in dataLayer</small>${emailStatus}
                    </div>
                `,
                gravity_forms: `
                    <div style="padding: 15px; background: #f0f8ff; border: 2px solid #0073aa; border-radius: 4px; margin-top: 10px;">
                        <strong>Thanks for contacting us!</strong> We will get in touch with you shortly.<br>
                        <small>✓ Event tracked in dataLayer</small>${emailStatus}
                    </div>
                `,
                ninja_forms: `
                    <div style="padding: 15px; background: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px; color: #3c763d; margin-top: 10px;">
                        <strong>Success!</strong> Your form has been submitted.<br>
                        <small>✓ Event tracked in dataLayer</small>${emailStatus}
                    </div>
                `
            };

            return messages[framework] || messages.elementor;
        }
    };

    // Initialize common functionality
    console.log('[CUFT Test Forms] Common utilities loaded');

})();