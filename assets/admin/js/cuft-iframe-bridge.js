/**
 * CUFT Iframe Bridge - PostMessage Communication Handler
 *
 * Handles secure cross-frame communication between the testing dashboard
 * and embedded test form iframes.
 *
 * @package Choice_UTM_Form_Tracker
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * CUFT Iframe Bridge Class
     */
    class CUFTIframeBridge {
        constructor() {
            this.allowedOrigin = window.location.origin;
            this.messageHandlers = new Map();
            this.debugMode = false;

            this.init();
        }

        /**
         * Initialize bridge
         */
        init() {
            // Register message event listener
            window.addEventListener('message', this.handleMessage.bind(this), false);

            // Register default message handlers
            this.registerHandler('cuft_form_loaded', this.handleFormLoaded.bind(this));
            this.registerHandler('cuft_fields_populated', this.handleFieldsPopulated.bind(this));
            this.registerHandler('cuft_form_submitted', this.handleFormSubmitted.bind(this));
            this.registerHandler('cuft_error', this.handleError.bind(this));
            this.registerHandler('cuft_event_captured', this.handleEventCaptured.bind(this));

            this.log('Iframe bridge initialized');
        }

        /**
         * Handle incoming postMessage
         *
         * @param {MessageEvent} event Message event
         */
        handleMessage(event) {
            try {
                // Validate origin
                if (!this.validateOrigin(event.origin)) {
                    console.warn('[CUFT] Rejected message from untrusted origin:', event.origin);
                    return;
                }

                // Validate message structure
                if (!event.data || typeof event.data !== 'object' || !event.data.action) {
                    this.log('Invalid message structure:', event.data);
                    return;
                }

                const { action, data, timestamp } = event.data;

                this.log('Received message:', action, data);

                // Route to handler
                if (this.messageHandlers.has(action)) {
                    this.messageHandlers.get(action)(data, event);
                } else {
                    this.log('No handler registered for action:', action);
                }

            } catch (error) {
                console.error('[CUFT] Error handling message:', error);
                this.logError('message_handler_error', error.message);
            }
        }

        /**
         * Register a message handler
         *
         * @param {string} action Action name
         * @param {Function} handler Handler function
         */
        registerHandler(action, handler) {
            this.messageHandlers.set(action, handler);
            this.log('Registered handler for:', action);
        }

        /**
         * Send message to iframe
         *
         * @param {HTMLIFrameElement} iframe Target iframe
         * @param {string} action Action name
         * @param {Object} data Message data
         */
        sendToIframe(iframe, action, data = {}) {
            try {
                if (!iframe || !iframe.contentWindow) {
                    throw new Error('Invalid iframe reference');
                }

                const message = {
                    action: action,
                    nonce: this.getNonce(),
                    timestamp: Date.now(),
                    data: data
                };

                iframe.contentWindow.postMessage(message, this.allowedOrigin);
                this.log('Sent to iframe:', action, data);

            } catch (error) {
                console.error('[CUFT] Error sending to iframe:', error);
                this.logError('send_to_iframe_error', error.message);
            }
        }

        /**
         * Send message to parent (from within iframe)
         *
         * @param {string} action Action name
         * @param {Object} data Message data
         */
        sendToParent(action, data = {}) {
            try {
                const message = {
                    action: action,
                    timestamp: Date.now(),
                    data: data
                };

                window.parent.postMessage(message, this.allowedOrigin);
                this.log('Sent to parent:', action, data);

            } catch (error) {
                console.error('[CUFT] Error sending to parent:', error);
                this.logError('send_to_parent_error', error.message);
            }
        }

        /**
         * Validate message origin
         *
         * @param {string} origin Message origin
         * @return {boolean} True if valid
         */
        validateOrigin(origin) {
            return origin === this.allowedOrigin;
        }

        /**
         * Get nonce for secure operations
         *
         * @return {string} Nonce value
         */
        getNonce() {
            return window.cuftFormBuilder?.nonce || '';
        }

        /**
         * Handle form loaded message
         *
         * @param {Object} data Message data
         */
        handleFormLoaded(data) {
            this.log('Form loaded:', data);

            // Trigger custom event for dashboard
            this.triggerEvent('cuft:form_loaded', data);
        }

        /**
         * Handle fields populated message
         *
         * @param {Object} data Message data
         */
        handleFieldsPopulated(data) {
            this.log('Fields populated:', data);

            // Trigger custom event
            this.triggerEvent('cuft:fields_populated', data);
        }

        /**
         * Handle form submitted message
         *
         * @param {Object} data Message data
         */
        handleFormSubmitted(data) {
            this.log('Form submitted:', data);

            // Trigger custom event
            this.triggerEvent('cuft:form_submitted', data);

            // Display captured tracking event
            if (data.tracking_event) {
                this.displayTrackingEvent(data.tracking_event);
            }
        }

        /**
         * Handle error message
         *
         * @param {Object} data Error data
         */
        handleError(data) {
            console.error('[CUFT] Iframe error:', data);

            // Trigger error event
            this.triggerEvent('cuft:error', data);
        }

        /**
         * Handle event captured message
         *
         * @param {Object} data Event data
         */
        handleEventCaptured(data) {
            this.log('Event captured:', data);

            // Trigger event
            this.triggerEvent('cuft:event_captured', data);

            // Display event if it's a tracking event
            if (data.event) {
                this.displayTrackingEvent(data.event);
            }
        }

        /**
         * Display tracking event in dashboard
         *
         * @param {Object} event Tracking event
         */
        displayTrackingEvent(event) {
            // This will be implemented in the dashboard UI integration
            console.log('[CUFT] Tracking Event:', event);
        }

        /**
         * Trigger custom DOM event
         *
         * @param {string} eventName Event name
         * @param {Object} detail Event detail
         */
        triggerEvent(eventName, detail) {
            const event = new CustomEvent(eventName, {
                detail: detail,
                bubbles: true,
                cancelable: true
            });

            document.dispatchEvent(event);
        }

        /**
         * Log error
         *
         * @param {string} errorType Error type
         * @param {string} message Error message
         */
        logError(errorType, message) {
            this.sendToParent('cuft_error', {
                error_type: errorType,
                message: message,
                timestamp: Date.now()
            });
        }

        /**
         * Log message (debug mode)
         *
         * @param {...any} args Arguments to log
         */
        log(...args) {
            if (this.debugMode || (window.cuftFormBuilder && window.cuftFormBuilder.debug)) {
                console.log('[CUFT Bridge]', ...args);
            }
        }

        /**
         * Enable debug mode
         */
        enableDebug() {
            this.debugMode = true;
            this.log('Debug mode enabled');
        }
    }

    // Initialize global instance
    window.CUFTIframeBridge = new CUFTIframeBridge();

    // Expose convenience methods
    window.cuftBridge = {
        sendToIframe: (iframe, action, data) => window.CUFTIframeBridge.sendToIframe(iframe, action, data),
        sendToParent: (action, data) => window.CUFTIframeBridge.sendToParent(action, data),
        registerHandler: (action, handler) => window.CUFTIframeBridge.registerHandler(action, handler),
        enableDebug: () => window.CUFTIframeBridge.enableDebug()
    };

})();
