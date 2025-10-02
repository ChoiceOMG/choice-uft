/**
 * CUFT AJAX Client Wrapper
 *
 * Handles all AJAX communications for the testing dashboard.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * AJAX Client Class
     */
    class CUFTAjaxClient {
        constructor() {
            this.ajaxUrl = window.cuftConfig?.ajaxUrl || '/wp-admin/admin-ajax.php';
            this.nonce = window.cuftConfig?.nonce || '';
        }

        /**
         * Make AJAX request
         */
        async request(action, data = {}) {
            try {
                const formData = new FormData();
                formData.append('action', action);
                formData.append('nonce', this.nonce);

                // Add additional data
                Object.entries(data).forEach(([key, value]) => {
                    if (typeof value === 'object') {
                        formData.append(key, JSON.stringify(value));
                    } else {
                        formData.append(key, value);
                    }
                });

                const response = await fetch(this.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const result = await response.json();

                if (!result.success) {
                    throw new Error(result.data?.message || 'Request failed');
                }

                return result.data;

            } catch (error) {
                console.error(`CUFT AJAX Error (${action}):`, error);
                throw error;
            }
        }

        /**
         * Generate test data
         */
        async generateTestData() {
            return this.request('cuft_generate_test_data');
        }

        /**
         * Simulate event
         */
        async simulateEvent(eventType, sessionId, testData) {
            return this.request('cuft_simulate_event', {
                event_type: eventType,
                session_id: sessionId,
                test_data: testData
            });
        }

        /**
         * Build test form
         */
        async buildTestForm(framework, testData) {
            return this.request('cuft_build_test_form', {
                framework: framework,
                test_data: testData
            });
        }

        /**
         * Get test events
         */
        async getTestEvents(filters = {}) {
            return this.request('cuft_get_test_events', filters);
        }

        /**
         * Delete test events
         */
        async deleteTestEvents(options = {}) {
            return this.request('cuft_delete_test_events', options);
        }
    }

    // Export to global scope
    window.CUFTAjaxClient = CUFTAjaxClient;

})();