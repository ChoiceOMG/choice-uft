/**
 * CUFT DataLayer Monitor
 *
 * Monitors and intercepts dataLayer events for testing purposes.
 *
 * @package Choice_UFT
 * @since 3.14.0
 */

(function() {
    'use strict';

    /**
     * DataLayer Monitor Class
     */
    class CUFTDataLayerMonitor {
        constructor() {
            this.events = [];
            this.maxEvents = 100;
            this.callbacks = [];
            this.filterTestOnly = true;
            this.init();
        }

        /**
         * Initialize monitor
         * Note: This monitor intercepts dataLayer pushes but does not create events.
         * All events must include cuft_tracked and cuft_source from their original source.
         */
        init() {
            // Ensure dataLayer exists
            window.dataLayer = window.dataLayer || [];

            // Store original push method
            const originalPush = window.dataLayer.push;

            // Intercept push method (monitoring only, not creating events)
            window.dataLayer.push = (...args) => {
                // Call original method
                const result = originalPush.apply(window.dataLayer, args);

                // Process intercepted events
                args.forEach(event => {
                    if (typeof event === 'object' && event !== null) {
                        this.processEvent(event);
                    }
                });

                return result;
            };

            console.log('CUFT DataLayer Monitor initialized');
        }

        /**
         * Process intercepted event
         */
        processEvent(event) {
            // Add metadata
            const enrichedEvent = {
                ...event,
                _cuft_captured_at: new Date().toISOString(),
                _cuft_index: this.events.length
            };

            // Add to events array (FIFO)
            this.events.unshift(enrichedEvent);
            if (this.events.length > this.maxEvents) {
                this.events.pop();
            }

            // Trigger callbacks
            this.callbacks.forEach(callback => {
                try {
                    callback(enrichedEvent);
                } catch (error) {
                    console.error('CUFT Monitor: Callback error', error);
                }
            });

            // Log if debug mode
            if (window.cuftConfig && window.cuftConfig.debug) {
                console.log('CUFT DataLayer Event Captured:', enrichedEvent);
            }
        }

        /**
         * Register event callback
         */
        onEvent(callback) {
            if (typeof callback === 'function') {
                this.callbacks.push(callback);
            }
        }

        /**
         * Get test events only
         */
        getTestEvents() {
            return this.events.filter(event => event.test_mode === true);
        }

        /**
         * Get CUFT events (with cuft_tracked)
         */
        getCUFTEvents() {
            return this.events.filter(event => event.cuft_tracked === true);
        }

        /**
         * Get all events
         */
        getAllEvents() {
            return this.events;
        }

        /**
         * Clear captured events
         */
        clearEvents() {
            this.events = [];
        }

        /**
         * Set filter mode
         */
        setFilterMode(testOnly) {
            this.filterTestOnly = testOnly;
        }

        /**
         * Get filtered events based on current mode
         */
        getFilteredEvents() {
            return this.filterTestOnly ? this.getTestEvents() : this.getAllEvents();
        }
    }

    // Export to global scope
    window.CUFTDataLayerMonitor = CUFTDataLayerMonitor;

})();