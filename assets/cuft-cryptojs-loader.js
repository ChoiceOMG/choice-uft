/**
 * CryptoJS Conditional Loader
 *
 * Checks if CryptoJS is already available in the browser before loading from CDN.
 * Only loads CryptoJS if it's not already present.
 */

(function() {
    'use strict';

    // Check if CryptoJS is already available
    if (typeof window.CryptoJS !== 'undefined') {
        console.log('[CUFT CryptoJS] CryptoJS already available, skipping CDN load');

        // Fire ready event for any code waiting for CryptoJS
        document.dispatchEvent(new CustomEvent('cuft:cryptojs:ready', {
            detail: {
                source: 'existing',
                available: true
            }
        }));

        return;
    }

    // CryptoJS not found, load from CDN
    console.log('[CUFT CryptoJS] CryptoJS not found, loading from CDN');

    // Get configuration from WordPress
    var config = window.cuftCryptoJS || {};
    var cdnUrl = config.cdnUrl || 'https://cdnjs.cloudflare.com/ajax/libs/crypto-js/4.2.0/crypto-js.min.js';
    var integrity = config.integrity || '';

    // Create script element
    var script = document.createElement('script');
    script.src = cdnUrl;
    script.async = true;
    script.crossOrigin = 'anonymous';

    // Add integrity if provided
    if (integrity) {
        script.integrity = integrity;
    }

    // Set up success handler
    script.onload = function() {
        console.log('[CUFT CryptoJS] CryptoJS loaded successfully from CDN');

        // Verify CryptoJS is actually available
        if (typeof window.CryptoJS !== 'undefined') {
            document.dispatchEvent(new CustomEvent('cuft:cryptojs:ready', {
                detail: {
                    source: 'cdn',
                    available: true
                }
            }));
        } else {
            console.error('[CUFT CryptoJS] CryptoJS script loaded but object not available');
            document.dispatchEvent(new CustomEvent('cuft:cryptojs:failed', {
                detail: { reason: 'load_verification_failed' }
            }));
        }
    };

    // Set up error handler
    script.onerror = function() {
        console.error('[CUFT CryptoJS] Failed to load CryptoJS from CDN');
        document.dispatchEvent(new CustomEvent('cuft:cryptojs:failed', {
            detail: { reason: 'cdn_load_error' }
        }));
    };

    // Add script to document head
    document.head.appendChild(script);

    // Set up fallback timeout
    setTimeout(function() {
        if (typeof window.CryptoJS === 'undefined') {
            console.warn('[CUFT CryptoJS] CDN load timeout - CryptoJS still not available');
            document.dispatchEvent(new CustomEvent('cuft:cryptojs:failed', {
                detail: { reason: 'timeout' }
            }));
        }
    }, 10000); // 10 second timeout

})();

/**
 * Utility function for other scripts to wait for CryptoJS
 *
 * @param {Function} callback - Called with CryptoJS object or null if failed
 * @param {number} timeoutMs - Timeout in milliseconds (default: 5000)
 */
window.cuftWaitForCryptoJS = function(callback, timeoutMs) {
    timeoutMs = timeoutMs || 5000;

    // If already available, call immediately
    if (typeof window.CryptoJS !== 'undefined') {
        callback(window.CryptoJS);
        return;
    }

    var timeout = setTimeout(function() {
        callback(null); // Call with null to indicate failure
    }, timeoutMs);

    // Listen for success
    document.addEventListener('cuft:cryptojs:ready', function(event) {
        clearTimeout(timeout);
        callback(window.CryptoJS);
    }, { once: true });

    // Listen for failure
    document.addEventListener('cuft:cryptojs:failed', function(event) {
        clearTimeout(timeout);
        callback(null); // Call with null to indicate failure
    }, { once: true });
};

/**
 * Check if CryptoJS is available (synchronous check)
 *
 * @return {boolean} True if CryptoJS is currently available
 */
window.cuftIsCryptoJSAvailable = function() {
    return typeof window.CryptoJS !== 'undefined';
};