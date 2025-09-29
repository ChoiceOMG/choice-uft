/**
 * Test script for CryptoJS loading functionality
 * This demonstrates how to use the CryptoJS loader in other scripts
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('[CUFT CryptoJS Test] Testing CryptoJS availability...');

    // Method 1: Using the utility function
    window.cuftWaitForCryptoJS(function(CryptoJS) {
        if (CryptoJS) {
            console.log('[CUFT CryptoJS Test] ✅ CryptoJS is available via utility function');

            // Test basic functionality
            try {
                var hash = CryptoJS.MD5('Hello World').toString();
                console.log('[CUFT CryptoJS Test] ✅ MD5 test successful:', hash);
            } catch (error) {
                console.error('[CUFT CryptoJS Test] ❌ MD5 test failed:', error);
            }
        } else {
            console.warn('[CUFT CryptoJS Test] ❌ CryptoJS not available via utility function');
        }
    }, 5000);

    // Method 2: Using direct check
    if (window.cuftIsCryptoJSAvailable()) {
        console.log('[CUFT CryptoJS Test] ✅ CryptoJS immediately available');
    } else {
        console.log('[CUFT CryptoJS Test] ⏳ CryptoJS not immediately available, waiting...');
    }

    // Method 3: Using events
    document.addEventListener('cuft:cryptojs:ready', function(event) {
        console.log('[CUFT CryptoJS Test] ✅ CryptoJS ready event fired:', event.detail);
    });

    document.addEventListener('cuft:cryptojs:failed', function(event) {
        console.error('[CUFT CryptoJS Test] ❌ CryptoJS failed event fired:', event.detail);
    });
});