/**
 * Frontend Health Check Script
 * Runs background health checks for custom GTM server
 */

(function() {
    'use strict';

    // Check if custom server is configured
    if (!window.cuftConfig || !window.cuftConfig.custom_server_enabled) {
        return; // Exit if custom server not enabled
    }

    // Skip if last check was within last hour
    const lastCheckKey = 'cuft_last_health_check';
    const lastCheck = localStorage.getItem(lastCheckKey);
    if (lastCheck && (Date.now() - parseInt(lastCheck)) < 3600000) {
        return; // Skip if checked within last hour
    }

    // Run health check after 30-second delay (non-blocking)
    setTimeout(function() {
        performBackgroundHealthCheck();
    }, 30000);

    /**
     * Perform background health check
     */
    function performBackgroundHealthCheck() {
        // Check if we have the required configuration
        if (!window.cuftConfig || !window.cuftConfig.ajax_url || !window.cuftConfig.nonce) {
            if (window.cuftConfig && window.cuftConfig.debug) {
                console.warn('[CUFT Health Check] Missing configuration');
            }
            return;
        }

        // Use fetch API with fallback to jQuery
        if (window.fetch) {
            performHealthCheckWithFetch();
        } else if (window.jQuery) {
            performHealthCheckWithJQuery();
        } else {
            // Silent fail - don't interrupt user experience
            if (window.cuftConfig.debug) {
                console.warn('[CUFT Health Check] No AJAX method available');
            }
        }
    }

    /**
     * Perform health check using fetch API
     */
    function performHealthCheckWithFetch() {
        const formData = new URLSearchParams();
        formData.append('action', 'cuft_manual_health_check');
        formData.append('nonce', window.cuftConfig.nonce);

        fetch(window.cuftConfig.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            handleHealthCheckResponse(data);
        })
        .catch(function(error) {
            // Silent fail - don't interrupt user experience
            if (window.cuftConfig.debug) {
                console.warn('[CUFT Health Check] Fetch failed:', error);
            }
        });
    }

    /**
     * Perform health check using jQuery
     */
    function performHealthCheckWithJQuery() {
        window.jQuery.ajax({
            url: window.cuftConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'cuft_manual_health_check',
                nonce: window.cuftConfig.nonce
            },
            dataType: 'json',
            timeout: 10000,
            success: function(data) {
                handleHealthCheckResponse(data);
            },
            error: function(xhr, status, error) {
                // Silent fail - don't interrupt user experience
                if (window.cuftConfig.debug) {
                    console.warn('[CUFT Health Check] jQuery AJAX failed:', error);
                }
            }
        });
    }

    /**
     * Handle health check response
     */
    function handleHealthCheckResponse(data) {
        // Store last check time
        localStorage.setItem(lastCheckKey, Date.now().toString());

        if (data.success && data.data) {
            const responseData = data.data;
            
            // Log status change if it occurred
            if (responseData.status_changed) {
                console.info('[CUFT Health Check] Server status changed:', responseData.active_server);
            }

            // Log debug information if enabled
            if (window.cuftConfig.debug) {
                console.log('[CUFT Health Check] Result:', {
                    passed: responseData.health_check_passed,
                    active_server: responseData.active_server,
                    consecutive_success: responseData.consecutive_success,
                    response_time: responseData.response_time
                });
            }
        } else {
            // Silent fail - don't interrupt user experience
            if (window.cuftConfig.debug) {
                console.warn('[CUFT Health Check] Invalid response:', data);
            }
        }
    }

})();

