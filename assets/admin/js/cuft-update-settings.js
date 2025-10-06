/**
 * Choice Universal Form Tracker - Update Settings Form
 *
 * Manages update configuration settings form.
 * Handles validation, submission, and real-time feedback.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
    'use strict';

    /**
     * CUFT Update Settings Form Component
     *
     * Manages all update-related settings and preferences
     */
    window.CUFTUpdateSettings = {
        // Configuration
        config: {
            ajaxUrl: window.cuftUpdater?.ajaxUrl || window.ajaxurl || '/wp-admin/admin-ajax.php',
            nonce: window.cuftUpdater?.nonce || '',
            debug: window.cuftUpdater?.debug || false
        },

        // Settings state
        state: {
            form: null,
            settings: {},
            isDirty: false,
            isSaving: false,
            originalSettings: {}
        },

        /**
         * Initialize settings form
         */
        init: function(formId) {
            try {
                const form = formId ?
                    document.getElementById(formId) :
                    document.querySelector('[data-cuft-settings-form]');

                if (!form) {
                    this.log('Settings form not found');
                    return;
                }

                this.state.form = form;
                this.bindEvents();
                this.loadSettings();

                this.log('Update settings initialized');
            } catch (error) {
                this.log('Initialization error: ' + error.message);
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            const self = this;

            if (!this.state.form) {
                return;
            }

            // Form submission
            this.state.form.addEventListener('submit', function(e) {
                e.preventDefault();
                self.saveSettings();
            });

            // Track changes for dirty state
            this.state.form.addEventListener('change', function(e) {
                if (e.target.matches('input, select, textarea')) {
                    self.state.isDirty = true;
                    self.updateSaveButton();
                }
            });

            // Reset button
            this.state.form.addEventListener('click', function(e) {
                if (e.target.matches('.cuft-settings-reset, [data-action="reset"]')) {
                    e.preventDefault();
                    self.resetSettings();
                }

                // Test connection button
                if (e.target.matches('.cuft-test-connection, [data-action="test"]')) {
                    e.preventDefault();
                    self.testConnection();
                }
            });

            // Conditional field visibility
            this.state.form.addEventListener('change', function(e) {
                if (e.target.matches('[data-toggle-fields]')) {
                    const targetFields = e.target.dataset.toggleFields;
                    self.toggleFields(targetFields, e.target.checked);
                }
            });

            // Warn on unsaved changes
            window.addEventListener('beforeunload', function(e) {
                if (self.state.isDirty && !self.state.isSaving) {
                    e.preventDefault();
                    e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                    return e.returnValue;
                }
            });
        },

        /**
         * Load current settings
         */
        loadSettings: function() {
            const self = this;

            const data = new URLSearchParams({
                action: 'cuft_get_update_settings',
                nonce: this.config.nonce
            });

            this.makeRequest('GET', data)
                .then(function(response) {
                    if (response.success && response.data) {
                        self.state.settings = response.data;
                        self.state.originalSettings = JSON.parse(JSON.stringify(response.data));
                        self.populateForm(response.data);
                    } else {
                        self.showError(response.data?.message || 'Failed to load settings');
                    }
                })
                .catch(function(error) {
                    self.showError('Network error: ' + error.message);
                });
        },

        /**
         * Populate form with settings
         */
        populateForm: function(settings) {
            if (!this.state.form) {
                return;
            }

            // Populate each field
            for (const key in settings) {
                if (!settings.hasOwnProperty(key)) {
                    continue;
                }

                const field = this.state.form.querySelector('[name="' + key + '"]');
                if (!field) {
                    continue;
                }

                if (field.type === 'checkbox') {
                    field.checked = !!settings[key];
                } else if (field.type === 'radio') {
                    const radio = this.state.form.querySelector('[name="' + key + '"][value="' + settings[key] + '"]');
                    if (radio) {
                        radio.checked = true;
                    }
                } else {
                    field.value = settings[key] || '';
                }
            }

            // Trigger conditional field visibility
            this.applyConditionalFields();

            this.log('Form populated with settings');
        },

        /**
         * Apply conditional field visibility based on settings
         */
        applyConditionalFields: function() {
            const self = this;

            if (!this.state.form) {
                return;
            }

            const toggleFields = this.state.form.querySelectorAll('[data-toggle-fields]');
            toggleFields.forEach(function(field) {
                const targetFields = field.dataset.toggleFields;
                self.toggleFields(targetFields, field.checked || field.value === 'true');
            });
        },

        /**
         * Toggle visibility of dependent fields
         */
        toggleFields: function(targetSelector, show) {
            try {
                if (!this.state.form) {
                    return;
                }

                const targets = this.state.form.querySelectorAll(targetSelector);
                targets.forEach(function(target) {
                    const row = target.closest('tr, .cuft-settings-row');
                    if (row) {
                        row.style.display = show ? '' : 'none';
                    } else {
                        target.style.display = show ? '' : 'none';
                    }
                });
            } catch (error) {
                this.log('Toggle fields error: ' + error.message);
            }
        },

        /**
         * Save settings
         */
        saveSettings: function() {
            const self = this;

            if (this.state.isSaving) {
                return;
            }

            // Validate form
            if (!this.validateForm()) {
                return;
            }

            this.state.isSaving = true;
            this.showSaving(true);

            // Gather form data
            const formData = new FormData(this.state.form);
            const data = new URLSearchParams({
                action: 'cuft_update_settings',
                nonce: this.config.nonce
            });

            // Add form fields to request
            for (const [key, value] of formData.entries()) {
                data.append(key, value);
            }

            this.makeRequest('POST', data)
                .then(function(response) {
                    if (response.success) {
                        self.showSuccess('Settings saved successfully');
                        self.state.isDirty = false;
                        self.state.originalSettings = JSON.parse(JSON.stringify(self.state.settings));
                        self.updateSaveButton();

                        // Update displayed values if needed
                        if (response.data?.next_scheduled_check) {
                            self.updateNextCheckDisplay(response.data.next_scheduled_check);
                        }
                    } else {
                        self.showError(response.data?.message || 'Failed to save settings');
                    }
                })
                .catch(function(error) {
                    self.showError('Network error: ' + error.message);
                })
                .finally(function() {
                    self.state.isSaving = false;
                    self.showSaving(false);
                });
        },

        /**
         * Validate form before submission
         */
        validateForm: function() {
            try {
                if (!this.state.form) {
                    return false;
                }

                // Clear previous errors
                this.clearValidationErrors();

                let isValid = true;

                // Check required fields
                const requiredFields = this.state.form.querySelectorAll('[required]');
                requiredFields.forEach(function(field) {
                    if (!field.value.trim()) {
                        this.showFieldError(field, 'This field is required');
                        isValid = false;
                    }
                }, this);

                // Validate email format if present
                const emailField = this.state.form.querySelector('[name="notification_email"]');
                if (emailField && emailField.value.trim()) {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(emailField.value)) {
                        this.showFieldError(emailField, 'Please enter a valid email address');
                        isValid = false;
                    }
                }

                // Validate check frequency
                const frequencyField = this.state.form.querySelector('[name="check_frequency"]');
                if (frequencyField) {
                    const validFrequencies = ['manual', 'hourly', 'twicedaily', 'daily', 'weekly'];
                    if (validFrequencies.indexOf(frequencyField.value) === -1) {
                        this.showFieldError(frequencyField, 'Please select a valid check frequency');
                        isValid = false;
                    }
                }

                return isValid;
            } catch (error) {
                this.log('Validation error: ' + error.message);
                return false;
            }
        },

        /**
         * Show field validation error
         */
        showFieldError: function(field, message) {
            try {
                const row = field.closest('tr, .cuft-settings-row');
                if (!row) {
                    return;
                }

                // Add error class
                field.classList.add('cuft-field-error');

                // Add error message
                let errorEl = row.querySelector('.cuft-field-error-message');
                if (!errorEl) {
                    errorEl = document.createElement('span');
                    errorEl.className = 'cuft-field-error-message';
                    field.parentNode.appendChild(errorEl);
                }
                errorEl.textContent = message;
            } catch (error) {
                this.log('Show field error: ' + error.message);
            }
        },

        /**
         * Clear all validation errors
         */
        clearValidationErrors: function() {
            try {
                if (!this.state.form) {
                    return;
                }

                // Remove error classes
                const errorFields = this.state.form.querySelectorAll('.cuft-field-error');
                errorFields.forEach(function(field) {
                    field.classList.remove('cuft-field-error');
                });

                // Remove error messages
                const errorMessages = this.state.form.querySelectorAll('.cuft-field-error-message');
                errorMessages.forEach(function(msg) {
                    msg.remove();
                });
            } catch (error) {
                this.log('Clear errors: ' + error.message);
            }
        },

        /**
         * Reset settings to defaults
         */
        resetSettings: function() {
            try {
                if (!confirm('Are you sure you want to reset settings to defaults? This cannot be undone.')) {
                    return;
                }

                this.populateForm(this.state.originalSettings);
                this.state.isDirty = false;
                this.updateSaveButton();

                this.log('Settings reset to original values');
            } catch (error) {
                this.log('Reset error: ' + error.message);
            }
        },

        /**
         * Test GitHub API connection
         */
        testConnection: function() {
            const self = this;

            this.showTestingConnection(true);

            const data = new URLSearchParams({
                action: 'cuft_test_github_connection',
                nonce: this.config.nonce
            });

            this.makeRequest('POST', data)
                .then(function(response) {
                    if (response.success) {
                        self.showSuccess('GitHub API connection successful');
                    } else {
                        self.showError('Connection failed: ' + (response.data?.message || 'Unknown error'));
                    }
                })
                .catch(function(error) {
                    self.showError('Connection error: ' + error.message);
                })
                .finally(function() {
                    self.showTestingConnection(false);
                });
        },

        /**
         * Update next check display
         */
        updateNextCheckDisplay: function(nextCheck) {
            try {
                const displayEl = this.state.form.querySelector('[data-next-check-display]');
                if (displayEl) {
                    displayEl.textContent = 'Next check: ' + this.formatDateTime(nextCheck);
                }
            } catch (error) {
                this.log('Update next check display error: ' + error.message);
            }
        },

        /**
         * Update save button state
         */
        updateSaveButton: function() {
            try {
                const saveButton = this.state.form.querySelector('[type="submit"]');
                if (!saveButton) {
                    return;
                }

                if (this.state.isDirty) {
                    saveButton.disabled = false;
                    saveButton.classList.add('button-primary');
                    saveButton.textContent = 'Save Changes';
                } else {
                    saveButton.disabled = false;
                    saveButton.classList.remove('button-primary');
                    saveButton.textContent = 'Saved';
                }
            } catch (error) {
                this.log('Update save button error: ' + error.message);
            }
        },

        /**
         * Show/hide saving indicator
         */
        showSaving: function(show) {
            try {
                const saveButton = this.state.form.querySelector('[type="submit"]');
                if (!saveButton) {
                    return;
                }

                if (show) {
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> Saving...';
                } else {
                    saveButton.disabled = false;
                    saveButton.innerHTML = this.state.isDirty ? 'Save Changes' : 'Saved';
                }
            } catch (error) {
                this.log('Show saving error: ' + error.message);
            }
        },

        /**
         * Show/hide testing connection indicator
         */
        showTestingConnection: function(show) {
            try {
                const testButton = this.state.form.querySelector('.cuft-test-connection, [data-action="test"]');
                if (!testButton) {
                    return;
                }

                if (show) {
                    testButton.disabled = true;
                    testButton.innerHTML = '<span class="spinner is-active" style="float:none;margin:0 5px 0 0;"></span> Testing...';
                } else {
                    testButton.disabled = false;
                    testButton.innerHTML = 'Test Connection';
                }
            } catch (error) {
                this.log('Show testing error: ' + error.message);
            }
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.showNotice(message, 'success');
        },

        /**
         * Show error message
         */
        showError: function(message) {
            this.showNotice(message, 'error');
        },

        /**
         * Show notice message
         */
        showNotice: function(message, type) {
            try {
                // Find or create notice container
                let noticeContainer = this.state.form.querySelector('.cuft-settings-notices');
                if (!noticeContainer) {
                    noticeContainer = document.createElement('div');
                    noticeContainer.className = 'cuft-settings-notices';
                    this.state.form.insertBefore(noticeContainer, this.state.form.firstChild);
                }

                // Create notice
                const notice = document.createElement('div');
                notice.className = 'notice notice-' + type + ' is-dismissible';
                notice.innerHTML = '<p>' + message + '</p>' +
                    '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss</span></button>';

                noticeContainer.appendChild(notice);

                // Make dismissible
                const dismissButton = notice.querySelector('.notice-dismiss');
                if (dismissButton) {
                    dismissButton.addEventListener('click', function() {
                        notice.remove();
                    });
                }

                // Auto-dismiss after 5 seconds
                setTimeout(function() {
                    notice.remove();
                }, 5000);

                // Scroll to notice
                noticeContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            } catch (error) {
                this.log('Show notice error: ' + error.message);
            }
        },

        /**
         * Make AJAX request with fallbacks
         */
        makeRequest: function(method, data) {
            const self = this;

            // Try native fetch first
            if (typeof fetch !== 'undefined') {
                const url = method === 'GET' ?
                    this.config.ajaxUrl + '?' + data.toString() :
                    this.config.ajaxUrl;

                const options = {
                    method: method,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                };

                if (method === 'POST') {
                    options.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                    options.body = data.toString();
                }

                return fetch(url, options).then(function(response) {
                    if (!response.ok) {
                        throw new Error('HTTP error ' + response.status);
                    }
                    return response.json();
                });
            }

            // Fallback to XMLHttpRequest
            return new Promise(function(resolve, reject) {
                try {
                    const xhr = new XMLHttpRequest();
                    const url = method === 'GET' ?
                        self.config.ajaxUrl + '?' + data.toString() :
                        self.config.ajaxUrl;

                    xhr.open(method, url, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                    xhr.onload = function() {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                resolve(JSON.parse(xhr.responseText));
                            } catch (e) {
                                reject(new Error('Invalid JSON response'));
                            }
                        } else {
                            reject(new Error('Request failed: ' + xhr.status));
                        }
                    };

                    xhr.onerror = function() {
                        reject(new Error('Network error'));
                    };

                    xhr.send(method === 'POST' ? data.toString() : null);
                } catch (error) {
                    reject(error);
                }
            });
        },

        /**
         * Format date/time for display
         */
        formatDateTime: function(dateString) {
            try {
                const date = new Date(dateString);
                return date.toLocaleString();
            } catch (error) {
                return dateString;
            }
        },

        /**
         * Debug logging
         */
        log: function(message) {
            if (this.config.debug && console && console.log) {
                console.log('[CUFT Settings]', message);
            }
        },

        /**
         * Destroy settings form
         */
        destroy: function() {
            try {
                this.state.isDirty = false;

                if (this.state.form) {
                    this.state.form.reset();
                }

                this.log('Settings form destroyed');
            } catch (error) {
                this.log('Destroy error: ' + error.message);
            }
        }
    };

    // Export for use in other components
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.CUFTUpdateSettings;
    }

})();
