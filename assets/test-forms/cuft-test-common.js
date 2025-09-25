/**
 * CUFT Test Forms - Common Utilities
 * Shared functionality for all framework-specific test forms
 */
(function() {
    'use strict';

    // Global namespace for test forms
    window.CUFTTestForms = window.CUFTTestForms || {};

    // Check utility systems availability
    var hasErrorBoundary = !!(window.cuftErrorBoundary);
    var hasPerformanceMonitor = !!(window.cuftPerformanceMonitor);
    var hasObserverCleanup = !!(window.cuftObserverCleanup);
    var hasRetryLogic = !!(window.cuftRetryLogic);
    var hasDataLayerUtils = !!(window.cuftDataLayerUtils);

    // Common utilities
    window.CUFTTestForms.common = {

        /**
         * Get current tracking data for test submission
         * This data will be stored in sessionStorage for production code to retrieve
         */
        getTestTrackingData: function(framework, formId) {
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeExecute :
              function(fn) { try { return fn(); } catch (e) { return {}; } };

            return safeOperation(function() {
                const timestamp = Date.now();

                // Only return tracking data that should be stored in sessionStorage
                // Production code will add cuft_tracked, cuft_source, form_type, etc.
                return {
                // Click IDs (all test forms will have these to ensure generate_lead fires)
                click_id: `test_click_${framework}_${timestamp}`,
                gclid: `test_gclid_${framework}_${timestamp}`,
                fbclid: `test_fbclid_${framework}_${timestamp}`, // Add more click IDs for better testing

                // UTM parameters
                utm_source: 'cuft_test',
                utm_medium: 'test_form',
                utm_campaign: `test_campaign_${framework}`,
                utm_term: 'test_term',
                    utm_content: 'test_content'
                };
            }, 'Test Tracking Data Generation') || {};
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
         * Check if generate_lead should fire based on stored data
         * NOTE: We no longer fire generate_lead directly - production code handles this
         */
        checkGenerateLeadRequirements: function(framework, formElement) {
            const requirements = this.getGenerateLeadRequirements(framework);

            // Get stored tracking data
            let trackingData = {};
            try {
                const stored = JSON.parse(sessionStorage.getItem('cuft_tracking_data'));
                if (stored && stored.tracking) {
                    trackingData = stored.tracking;
                }
            } catch (e) {
                console.log('[CUFT Test] Could not retrieve tracking data');
            }

            // Get form field values
            const email = formElement.getAttribute('data-cuft-email') || '';
            const phone = formElement.getAttribute('data-cuft-phone') || '';

                // Check email requirement
                if (requirements.email && !email) {
                    console.log(`[CUFT Test] Generate lead should be blocked: Email required for ${framework}`);
                    return false;
            }

            // Check phone requirement
            if (requirements.phone && !phone) {
                console.log(`[CUFT Test] Generate lead should be blocked: Phone required for ${framework}`);
                return false;
            }

            // Check click_id requirement
            if (requirements.click_id && !trackingData.click_id && !trackingData.gclid && !trackingData.fbclid) {
                console.log(`[CUFT Test] Generate lead should be blocked: Click ID required for ${framework}`);
                return false;
            }

            // Check UTM campaign requirement
            if (requirements.utm_campaign && !trackingData.utm_campaign) {
                console.log(`[CUFT Test] Generate lead should be blocked: UTM campaign required for ${framework}`);
                return false;
            }

                console.log(`[CUFT Test] Generate lead should fire for ${framework}`);
                return true;
        },

        /**
         * Check if dataLayer events were fired by production code
         * NOTE: We no longer fire events directly - production code should handle this
         */
        checkDataLayerEvents: function(framework, expectedFormId) {
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeExecute :
              function(fn) { try { return fn(); } catch (e) { return { formSubmit: false, generateLead: false }; } };

            return safeOperation(function() {
                if (!window.dataLayer) {
                    console.error('[CUFT Test] ❌ dataLayer not found');
                    return { formSubmit: false, generateLead: false };
                }

            // Check recent events (last 10 events)
            const recentEvents = window.dataLayer.slice(-10);

            const formSubmitEvent = recentEvents.find(e =>
                e.event === 'form_submit' &&
                e.cuft_tracked &&
                e.cuft_source &&
                (e.form_id === expectedFormId || !expectedFormId)
            );

            const generateLeadEvent = recentEvents.find(e =>
                e.event === 'generate_lead' &&
                (e.form_id === expectedFormId || !expectedFormId)
            );

            console.log(`[CUFT Test] Event check for ${framework}:`, {
                formSubmit: !!formSubmitEvent,
                generateLead: !!generateLeadEvent,
                formSubmitData: formSubmitEvent || null,
                generateLeadData: generateLeadEvent || null
            });

                return {
                    formSubmit: !!formSubmitEvent,
                    generateLead: !!generateLeadEvent,
                    formSubmitEvent: formSubmitEvent,
                    generateLeadEvent: generateLeadEvent
                };
            }, 'DataLayer Event Check') || { formSubmit: false, generateLead: false };
        },

        /**
         * Get form field values
         */
        getFormFieldValues: function(form) {
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeDOMOperation :
              function(fn) { try { return fn(); } catch (e) { return { email: '', phone: '' }; } };

            return safeOperation(function() {
                const emailInput = form.querySelector('input[type="email"], input[name*="email"], input[data-field="email"]');
                const phoneInput = form.querySelector('input[type="tel"], input[name*="phone"], input[data-field="phone"]');

                return {
                    email: emailInput ? emailInput.value : '',
                    phone: phoneInput ? phoneInput.value : ''
                };
            }, form, 'Form Field Value Extraction') || { email: '', phone: '' };
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
        showSuccessMessage: function(container, message, duration) {
            duration = duration || 5000;
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeDOMOperation :
              function(fn) { try { return fn(); } catch (e) { return false; } };

            return safeOperation(function() {
                if (!container) return false;

                container.style.display = 'block';
                container.innerHTML = message;

                setTimeout(function() {
                    container.style.display = 'none';
                    container.innerHTML = '';
                }, duration);
                return true;
            }, container, 'Success Message Display');
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
         * Check utility systems availability
         */
        getUtilityStatus: function() {
            return {
                errorBoundary: hasErrorBoundary,
                performanceMonitor: hasPerformanceMonitor,
                observerCleanup: hasObserverCleanup,
                retryLogic: hasRetryLogic,
                dataLayerUtils: hasDataLayerUtils
            };
        },

        /**
         * Add testing controls to form
         */
        addTestingControls: function(formElement, framework) {
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeDOMOperation :
              function(fn) { try { return fn(); } catch (e) { return false; } };

            return safeOperation(function() {
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
                window.CUFTTestForms.common.setupTestingControlListeners(formElement, framework);

                return controlsDiv;
            }, formElement, 'Testing Controls Setup') || null;
        },

        /**
         * Setup testing control event listeners
         */
        setupTestingControlListeners: function(formElement, framework) {
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeDOMOperation :
              function(fn) { try { return fn(); } catch (e) { return false; } };

            return safeOperation(function() {
                const controlsDiv = formElement.parentNode.querySelector('.cuft-testing-controls');
                if (!controlsDiv) return false;

            const controls = {
                email: controlsDiv.querySelector('.test-control-email'),
                phone: controlsDiv.querySelector('.test-control-phone'),
                clickId: controlsDiv.querySelector('.test-control-click-id'),
                utmCampaign: controlsDiv.querySelector('.test-control-utm-campaign')
            };

            // Update form data based on controls when they change
            Object.keys(controls).forEach(key => {
                if (controls[key]) {
                    controls[key].addEventListener('change', function() {
                        window.CUFTTestForms.common.updateFormBasedOnControls(formElement, framework, controls);
                    });
                }
            });

                // Initialize form data based on current control states
                window.CUFTTestForms.common.updateFormBasedOnControls(formElement, framework, controls);
                return true;
            }, formElement, 'Testing Control Event Listeners') || false;
        },

        /**
         * Update form data based on testing controls
         */
        updateFormBasedOnControls: function(formElement, framework, controls) {
            var safeOperation = hasErrorBoundary ?
              window.cuftErrorBoundary.safeDOMOperation :
              function(fn) { try { return fn(); } catch (e) { return false; } };

            return safeOperation(function() {
                // Store control states in form element for later use during submission
                formElement.dataset.testControlEmail = controls.email ? controls.email.checked : 'true';
                formElement.dataset.testControlPhone = controls.phone ? controls.phone.checked : 'true';
                formElement.dataset.testControlClickId = controls.clickId ? controls.clickId.checked : 'true';
                formElement.dataset.testControlUtmCampaign = controls.utmCampaign ? controls.utmCampaign.checked : 'true';

                window.CUFTTestForms.common.log(`Testing controls updated for ${framework}:`, 'info');
                window.CUFTTestForms.common.log(`Email: ${formElement.dataset.testControlEmail}, Phone: ${formElement.dataset.testControlPhone}, Click ID: ${formElement.dataset.testControlClickId}, UTM: ${formElement.dataset.testControlUtmCampaign}`, 'info');
                return true;
            }, formElement, 'Testing Controls Data Update') || false;
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
        },

        /**
         * Update tracking info display with actual stored values
         */
        updateTrackingInfoDisplay: function(framework, formElement) {
            // Find the tracking info display for this form
            const trackingDisplay = formElement.parentNode.querySelector('.cuft-tracking-info, [style*="background: #f8f9fa"]');
            if (!trackingDisplay) return;

            // Get actual tracking data from storage
            const actualTrackingData = this.getCurrentTrackingDataFromStorage();

            // Update the display with actual values
            const clickIdDisplay = trackingDisplay.querySelector('div:contains("Click ID:")') ||
                                 Array.from(trackingDisplay.querySelectorAll('div')).find(div =>
                                     div.textContent.includes('Click ID:'));

            if (clickIdDisplay && actualTrackingData) {
                const clickIds = [];
                if (actualTrackingData.click_id) clickIds.push(`click_id: ${actualTrackingData.click_id}`);
                if (actualTrackingData.gclid) clickIds.push(`gclid: ${actualTrackingData.gclid}`);
                if (actualTrackingData.fbclid) clickIds.push(`fbclid: ${actualTrackingData.fbclid}`);
                if (actualTrackingData.wbraid) clickIds.push(`wbraid: ${actualTrackingData.wbraid}`);
                if (actualTrackingData.gbraid) clickIds.push(`gbraid: ${actualTrackingData.gbraid}`);

                const displayValue = clickIds.length > 0 ? clickIds.join(', ') : 'None stored';
                clickIdDisplay.innerHTML = `<strong>Stored Click IDs:</strong> ${displayValue}`;
            }

            // Add UTM info if available
            if (actualTrackingData) {
                const utmDisplay = document.createElement('div');
                const utmParams = [];
                if (actualTrackingData.utm_source) utmParams.push(`source: ${actualTrackingData.utm_source}`);
                if (actualTrackingData.utm_medium) utmParams.push(`medium: ${actualTrackingData.utm_medium}`);
                if (actualTrackingData.utm_campaign) utmParams.push(`campaign: ${actualTrackingData.utm_campaign}`);

                const utmValue = utmParams.length > 0 ? utmParams.join(', ') : 'None stored';
                utmDisplay.innerHTML = `<strong>Stored UTM:</strong> ${utmValue}`;
                trackingDisplay.appendChild(utmDisplay);
            }
        },

        /**
         * Get current tracking data from storage (sessionStorage -> cookie -> empty)
         */
        getCurrentTrackingDataFromStorage: function() {
            try {
                // Try sessionStorage first
                const sessionData = sessionStorage.getItem('cuft_tracking_data');
                if (sessionData) {
                    const parsed = JSON.parse(sessionData);
                    if (parsed.tracking) {
                        return parsed.tracking;
                    }
                }
            } catch (e) {
                // Fall back to cookie or return empty
            }

            try {
                // Try cookie fallback
                const cookieData = this.getCookieValue('cuft_tracking_data');
                if (cookieData) {
                    return JSON.parse(cookieData);
                }
            } catch (e) {
                // Return empty object if all fails
            }

            return {};
        },

        /**
         * Get cookie value by name
         */
        getCookieValue: function(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
            return null;
        },

        /**
         * Prepare tracking data for production code consumption
         * This method is now used by framework-specific test files
         */
        prepareTrackingDataForProduction: function(framework, formId, formElement) {
            const testTrackingData = this.getTestTrackingData(framework, formId);

            // Apply testing controls to modify the data
            const modifiedData = this.applyTestingControls(formElement, testTrackingData);

            // Structure data in the format production code expects
            const storageData = {
                tracking: {
                    click_id: modifiedData.click_id || null,
                    gclid: modifiedData.gclid || null,
                    fbclid: modifiedData.fbclid || null,
                    wbraid: modifiedData.wbraid || null,
                    gbraid: modifiedData.gbraid || null,
                    msclkid: modifiedData.msclkid || null,
                    ttclid: modifiedData.ttclid || null,
                    li_fat_id: modifiedData.li_fat_id || null,
                    twclid: modifiedData.twclid || null,
                    snap_click_id: modifiedData.snap_click_id || null,
                    pclid: modifiedData.pclid || null,
                    utm_source: modifiedData.utm_source || null,
                    utm_medium: modifiedData.utm_medium || null,
                    utm_campaign: modifiedData.utm_campaign || null,
                    utm_term: modifiedData.utm_term || null,
                    utm_content: modifiedData.utm_content || null
                },
                timestamp: Date.now()
            };

            try {
                sessionStorage.setItem('cuft_tracking_data', JSON.stringify(storageData));
                this.log(`Prepared tracking data for ${framework} production code:`, 'info');
                this.log(storageData, 'data');
                return storageData;
            } catch (e) {
                this.log(`Error preparing tracking data: ${e.message}`, 'error');
                return null;
            }
        },

        /**
         * DEPRECATED: Use prepareTrackingDataForProduction instead
         */
        updateTrackingDataForTest: function(framework, formId) {
            console.warn('[CUFT Test] updateTrackingDataForTest is deprecated, use prepareTrackingDataForProduction');
            return this.getTestTrackingData(framework, formId);
        }
    };

    // Initialize common functionality
    console.log('[CUFT Test Forms] Common utilities loaded');

})();