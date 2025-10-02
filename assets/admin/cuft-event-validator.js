/**
 * CUFT Event Validator
 *
 * Validates dataLayer events against constitutional standards.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * Event Validator Class
     */
    class CUFTEventValidator {
        constructor() {
            // Required fields for different event types
            this.requiredFields = {
                'form_submit': ['event', 'cuft_tracked', 'cuft_source', 'form_type', 'form_id'],
                'generate_lead': ['event', 'cuft_tracked', 'cuft_source', 'currency', 'value'],
                'phone_click': ['event', 'cuft_tracked', 'cuft_source', 'phone_number'],
                'email_click': ['event', 'cuft_tracked', 'cuft_source', 'email_address']
            };
        }

        /**
         * Validate an event
         */
        validate(event) {
            const result = {
                valid: true,
                errors: [],
                warnings: []
            };

            // Check if event is an object
            if (typeof event !== 'object' || event === null) {
                result.valid = false;
                result.errors.push('Event must be an object');
                return result;
            }

            // Check for event type
            if (!event.event) {
                result.valid = false;
                result.errors.push('Missing required field: event');
            }

            // Check required fields based on event type
            if (event.event && this.requiredFields[event.event]) {
                const required = this.requiredFields[event.event];
                required.forEach(field => {
                    if (!(field in event)) {
                        result.valid = false;
                        result.errors.push(`Missing required field: ${field}`);
                    }
                });
            }

            // Check cuft_tracked
            if (event.cuft_tracked !== true) {
                result.valid = false;
                result.errors.push('cuft_tracked must be true');
            }

            // Check cuft_source
            if (!event.cuft_source || typeof event.cuft_source !== 'string') {
                result.valid = false;
                result.errors.push('cuft_source must be a non-empty string');
            }

            // Check snake_case naming
            const camelCaseFields = this.detectCamelCase(event);
            if (camelCaseFields.length > 0) {
                result.warnings.push(`camelCase detected in fields: ${camelCaseFields.join(', ')}. Use snake_case instead.`);
            }

            // Check test_mode for test events
            if (event.cuft_source === 'testing_dashboard' && event.test_mode !== true) {
                result.warnings.push('Test events should have test_mode: true');
            }

            // Check data types
            this.validateDataTypes(event, result);

            // Performance check
            const startTime = performance.now();
            const executionTime = performance.now() - startTime;
            if (executionTime > 500) {
                result.warnings.push(`Validation took ${executionTime.toFixed(2)}ms (target: <500ms)`);
            }

            return result;
        }

        /**
         * Detect camelCase field names
         */
        detectCamelCase(event) {
            const camelCasePattern = /[a-z][A-Z]/;
            const camelCaseFields = [];

            Object.keys(event).forEach(key => {
                if (camelCasePattern.test(key) && !key.startsWith('_cuft_')) {
                    camelCaseFields.push(key);
                }
            });

            return camelCaseFields;
        }

        /**
         * Validate data types
         */
        validateDataTypes(event, result) {
            // Check boolean fields
            const booleanFields = ['cuft_tracked', 'test_mode', 'is_qualified'];
            booleanFields.forEach(field => {
                if (field in event && typeof event[field] !== 'boolean') {
                    result.errors.push(`${field} must be a boolean`);
                    result.valid = false;
                }
            });

            // Check string fields
            const stringFields = ['event', 'cuft_source', 'form_type', 'form_id', 'user_email', 'user_phone'];
            stringFields.forEach(field => {
                if (field in event && typeof event[field] !== 'string') {
                    result.warnings.push(`${field} should be a string`);
                }
            });

            // Check numeric fields
            if ('value' in event && typeof event.value !== 'number') {
                result.warnings.push('value should be a number');
            }
        }

        /**
         * Create validation report
         */
        createReport(event) {
            const validation = this.validate(event);

            return {
                event: event.event || 'unknown',
                valid: validation.valid,
                errors: validation.errors,
                warnings: validation.warnings,
                timestamp: new Date().toISOString()
            };
        }
    }

    // Export to global scope
    window.CUFTEventValidator = CUFTEventValidator;

})();