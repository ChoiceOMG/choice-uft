/**
 * Choice Universal Form Tracker - Error Handler
 *
 * Centralized error handling with fallbacks and retry mechanisms.
 * Implements proper error isolation and user-friendly messages per Constitution §5.
 *
 * @package Choice_Universal_Form_Tracker
 * @since 3.16.0
 */

(function() {
    'use strict';

    /**
     * CUFT Error Handler Component
     *
     * Provides error handling, logging, and retry mechanisms
     */
    window.CUFTErrorHandler = {
        // Configuration
        config: {
            debug: window.cuftUpdater?.debug || false,
            maxRetries: 3,
            retryDelay: 1000, // Base delay in ms
            retryBackoffMultiplier: 2, // Exponential backoff
            errorLogLimit: 50, // Max errors to keep in memory
            networkErrorCodes: [0, 408, 429, 500, 502, 503, 504]
        },

        // Error state
        state: {
            errorLog: [],
            retryAttempts: {},
            suppressedErrors: new Set()
        },

        /**
         * Handle error with appropriate response
         *
         * @param {Error|string} error - Error object or message
         * @param {Object} options - Error handling options
         * @returns {void}
         */
        handle: function(error, options) {
            try {
                options = options || {};

                // Extract error details
                const errorDetails = this.extractErrorDetails(error);

                // Log error
                this.logError(errorDetails, options);

                // Determine error type
                const errorType = this.classifyError(errorDetails);

                // Handle based on type
                switch (errorType) {
                    case 'network':
                        this.handleNetworkError(errorDetails, options);
                        break;

                    case 'validation':
                        this.handleValidationError(errorDetails, options);
                        break;

                    case 'permission':
                        this.handlePermissionError(errorDetails, options);
                        break;

                    case 'timeout':
                        this.handleTimeoutError(errorDetails, options);
                        break;

                    default:
                        this.handleGenericError(errorDetails, options);
                }

                // Call custom callback if provided
                if (options.onError && typeof options.onError === 'function') {
                    try {
                        options.onError(errorDetails);
                    } catch (callbackError) {
                        this.log('Error callback failed: ' + callbackError.message);
                    }
                }
            } catch (handlerError) {
                // Error in error handler - log to console only
                if (console && console.error) {
                    console.error('[CUFT Error Handler] Handler error:', handlerError);
                }
            }
        },

        /**
         * Extract error details from error object
         */
        extractErrorDetails: function(error) {
            const details = {
                message: '',
                type: 'unknown',
                code: null,
                stack: null,
                timestamp: new Date().toISOString(),
                url: window.location.href
            };

            if (typeof error === 'string') {
                details.message = error;
                details.type = 'string';
            } else if (error instanceof Error) {
                details.message = error.message || 'Unknown error';
                details.type = error.name || 'Error';
                details.stack = error.stack;
                details.code = error.code;
            } else if (typeof error === 'object') {
                details.message = error.message || error.statusText || 'Unknown error';
                details.type = error.type || 'object';
                details.code = error.status || error.code;
            } else {
                details.message = String(error);
                details.type = typeof error;
            }

            return details;
        },

        /**
         * Classify error type
         */
        classifyError: function(errorDetails) {
            const message = errorDetails.message.toLowerCase();
            const code = errorDetails.code;

            // Network errors
            if (message.indexOf('network') !== -1 ||
                message.indexOf('fetch') !== -1 ||
                message.indexOf('connection') !== -1 ||
                this.config.networkErrorCodes.indexOf(code) !== -1) {
                return 'network';
            }

            // Validation errors
            if (message.indexOf('validation') !== -1 ||
                message.indexOf('invalid') !== -1 ||
                message.indexOf('required') !== -1 ||
                code === 400) {
                return 'validation';
            }

            // Permission errors
            if (message.indexOf('permission') !== -1 ||
                message.indexOf('unauthorized') !== -1 ||
                message.indexOf('forbidden') !== -1 ||
                message.indexOf('security') !== -1 ||
                message.indexOf('nonce') !== -1 ||
                code === 401 || code === 403) {
                return 'permission';
            }

            // Timeout errors
            if (message.indexOf('timeout') !== -1 ||
                code === 408) {
                return 'timeout';
            }

            return 'generic';
        },

        /**
         * Handle network errors with retry
         */
        handleNetworkError: function(errorDetails, options) {
            const message = 'Network connection error. Please check your internet connection and try again.';

            // Show user-friendly message
            this.showUserMessage(message, 'error', options);

            // Attempt retry if configured
            if (options.retry && options.retryCallback) {
                this.attemptRetry(options.retryCallback, options);
            }
        },

        /**
         * Handle validation errors
         */
        handleValidationError: function(errorDetails, options) {
            const message = options.userMessage ||
                'Please check your input and try again. ' + errorDetails.message;

            this.showUserMessage(message, 'warning', options);
        },

        /**
         * Handle permission errors
         */
        handlePermissionError: function(errorDetails, options) {
            const message = 'You do not have permission to perform this action. Please refresh the page and try again.';

            this.showUserMessage(message, 'error', options);

            // Suggest page refresh
            if (options.suggestRefresh !== false) {
                setTimeout(function() {
                    if (confirm('Would you like to refresh the page now?')) {
                        window.location.reload();
                    }
                }, 2000);
            }
        },

        /**
         * Handle timeout errors with retry
         */
        handleTimeoutError: function(errorDetails, options) {
            const message = 'The request took too long. Please try again.';

            this.showUserMessage(message, 'warning', options);

            // Attempt retry with longer timeout
            if (options.retry && options.retryCallback) {
                options.timeout = (options.timeout || 30000) * 1.5;
                this.attemptRetry(options.retryCallback, options);
            }
        },

        /**
         * Handle generic errors
         */
        handleGenericError: function(errorDetails, options) {
            const message = options.userMessage ||
                'An error occurred: ' + errorDetails.message;

            this.showUserMessage(message, 'error', options);
        },

        /**
         * Attempt retry with exponential backoff
         */
        attemptRetry: function(callback, options) {
            const self = this;
            const retryKey = options.retryKey || 'default';

            // Initialize retry count
            if (!this.state.retryAttempts[retryKey]) {
                this.state.retryAttempts[retryKey] = 0;
            }

            this.state.retryAttempts[retryKey]++;

            // Check if max retries exceeded
            if (this.state.retryAttempts[retryKey] > this.config.maxRetries) {
                this.showUserMessage(
                    'Maximum retry attempts exceeded. Please try again later.',
                    'error',
                    options
                );
                this.state.retryAttempts[retryKey] = 0;
                return;
            }

            // Calculate delay with exponential backoff
            const attempt = this.state.retryAttempts[retryKey];
            const delay = this.config.retryDelay *
                Math.pow(this.config.retryBackoffMultiplier, attempt - 1);

            this.log('Retry attempt ' + attempt + ' in ' + delay + 'ms');

            // Show retry message
            this.showUserMessage(
                'Retrying... (Attempt ' + attempt + ' of ' + this.config.maxRetries + ')',
                'info',
                options
            );

            // Schedule retry
            setTimeout(function() {
                try {
                    callback();
                } catch (retryError) {
                    self.handle(retryError, options);
                }
            }, delay);
        },

        /**
         * Reset retry attempts
         */
        resetRetry: function(retryKey) {
            if (retryKey) {
                delete this.state.retryAttempts[retryKey];
            } else {
                this.state.retryAttempts = {};
            }
        },

        /**
         * Show user-friendly message
         */
        showUserMessage: function(message, type, options) {
            try {
                // Skip if silenced
                if (options.silent) {
                    return;
                }

                // Use custom display function if provided
                if (options.displayCallback && typeof options.displayCallback === 'function') {
                    options.displayCallback(message, type);
                    return;
                }

                // Try to use main updater's message display
                if (window.CUFTUpdater && typeof window.CUFTUpdater.showMessage === 'function') {
                    window.CUFTUpdater.showMessage(message, type);
                    return;
                }

                // Fallback to console and optional alert
                if (this.config.debug && console) {
                    console[type === 'error' ? 'error' : 'warn']('[CUFT]', message);
                }

                // Show alert for critical errors
                if (type === 'error' && options.alert !== false) {
                    alert(message);
                }
            } catch (error) {
                // Silent failure for display errors
                if (console && console.error) {
                    console.error('[CUFT] Failed to display message:', error);
                }
            }
        },

        /**
         * Log error to memory and optionally to server
         */
        logError: function(errorDetails, options) {
            try {
                // Add to memory log
                this.state.errorLog.push(errorDetails);

                // Trim log if too large
                if (this.state.errorLog.length > this.config.errorLogLimit) {
                    this.state.errorLog.shift();
                }

                // Log to console in debug mode
                if (this.config.debug && console) {
                    console.error('[CUFT Error]', errorDetails);
                }

                // Send to server if configured
                if (options.logToServer && window.cuftUpdater?.ajaxUrl) {
                    this.logToServer(errorDetails);
                }
            } catch (error) {
                // Silent failure for logging errors
            }
        },

        /**
         * Send error log to server
         */
        logToServer: function(errorDetails) {
            try {
                // Create a simplified version for server
                const logData = {
                    message: errorDetails.message,
                    type: errorDetails.type,
                    code: errorDetails.code,
                    timestamp: errorDetails.timestamp,
                    url: errorDetails.url
                };

                // Use beacon API if available (doesn't block page)
                if (navigator.sendBeacon) {
                    const data = new URLSearchParams({
                        action: 'cuft_log_error',
                        nonce: window.cuftUpdater?.nonce || '',
                        error: JSON.stringify(logData)
                    });

                    navigator.sendBeacon(window.cuftUpdater.ajaxUrl, data);
                } else {
                    // Fallback to async fetch (fire and forget)
                    fetch(window.cuftUpdater.ajaxUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: new URLSearchParams({
                            action: 'cuft_log_error',
                            nonce: window.cuftUpdater?.nonce || '',
                            error: JSON.stringify(logData)
                        })
                    }).catch(function() {
                        // Silent failure
                    });
                }
            } catch (error) {
                // Silent failure for server logging
            }
        },

        /**
         * Wrap async function with error handling and retry
         */
        wrapAsync: function(asyncFn, options) {
            const self = this;
            options = options || {};

            return function() {
                const args = arguments;
                const context = this;

                return new Promise(function(resolve, reject) {
                    try {
                        Promise.resolve(asyncFn.apply(context, args))
                            .then(resolve)
                            .catch(function(error) {
                                // Handle error
                                self.handle(error, options);

                                // Retry if configured
                                if (options.retry) {
                                    options.retryCallback = function() {
                                        asyncFn.apply(context, args).then(resolve).catch(reject);
                                    };
                                    self.attemptRetry(options.retryCallback, options);
                                } else {
                                    reject(error);
                                }
                            });
                    } catch (error) {
                        self.handle(error, options);
                        reject(error);
                    }
                });
            };
        },

        /**
         * Get error log
         */
        getErrorLog: function(limit) {
            limit = limit || this.state.errorLog.length;
            return this.state.errorLog.slice(-limit);
        },

        /**
         * Clear error log
         */
        clearErrorLog: function() {
            this.state.errorLog = [];
        },

        /**
         * Suppress specific error
         */
        suppressError: function(errorMessage) {
            this.state.suppressedErrors.add(errorMessage);
        },

        /**
         * Check if error is suppressed
         */
        isErrorSuppressed: function(errorDetails) {
            return this.state.suppressedErrors.has(errorDetails.message);
        },

        /**
         * Debug logging
         */
        log: function(message) {
            if (this.config.debug && console && console.log) {
                console.log('[CUFT Error Handler]', message);
            }
        }
    };

    // Global error handler for uncaught errors
    if (typeof window.addEventListener !== 'undefined') {
        window.addEventListener('error', function(event) {
            // Only handle errors from our scripts
            if (event.filename && event.filename.indexOf('cuft-') !== -1) {
                window.CUFTErrorHandler.handle(event.error || event.message, {
                    silent: true,
                    logToServer: true
                });
            }
        });

        // Handle unhandled promise rejections
        window.addEventListener('unhandledrejection', function(event) {
            window.CUFTErrorHandler.handle(event.reason, {
                silent: true,
                logToServer: true
            });
        });
    }

    // Export for use in other components
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = window.CUFTErrorHandler;
    }

})();
