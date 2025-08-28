(function() {
    'use strict';
    
    var DEBUG = !!(window.cuftUTM && window.cuftUTM.debug);
    
    function log() {
        try {
            if (DEBUG && window.console && window.console.log) {
                window.console.log.apply(window.console, ['[CUFT UTM]'].concat(Array.prototype.slice.call(arguments)));
            }
        } catch(e) {}
    }
    
    /**
     * UTM parameters to track
     */
    var UTM_PARAMS = [
        'utm_source',
        'utm_medium', 
        'utm_campaign',
        'utm_term',
        'utm_content'
    ];
    
    /**
     * Parse URL parameters
     */
    function getUrlParams() {
        var params = {};
        var search = window.location.search.substring(1);
        
        if (!search) return params;
        
        var pairs = search.split('&');
        for (var i = 0; i < pairs.length; i++) {
            var pair = pairs[i].split('=');
            if (pair.length === 2) {
                params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
            }
        }
        
        return params;
    }
    
    /**
     * Extract UTM parameters from URL
     */
    function getUtmParams() {
        var urlParams = getUrlParams();
        var utmData = {};
        var hasUtm = false;
        
        for (var i = 0; i < UTM_PARAMS.length; i++) {
            var param = UTM_PARAMS[i];
            if (urlParams[param]) {
                utmData[param] = urlParams[param];
                hasUtm = true;
            }
        }
        
        return hasUtm ? utmData : null;
    }
    
    /**
     * Store UTM data via AJAX
     */
    function storeUtmData(utmData) {
        if (!window.cuftUTM || !window.cuftUTM.ajaxUrl) {
            log('AJAX URL not available');
            return;
        }
        
        var xhr = new XMLHttpRequest();
        xhr.open('POST', window.cuftUTM.ajaxUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    log('UTM data stored successfully:', utmData);
                } else {
                    log('Error storing UTM data:', xhr.status);
                }
            }
        };
        
        // Build form data
        var formData = 'action=cuft_store_utm&nonce=' + encodeURIComponent(window.cuftUTM.nonce);
        for (var key in utmData) {
            if (utmData.hasOwnProperty(key)) {
                formData += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(utmData[key]);
            }
        }
        
        xhr.send(formData);
    }
    
    /**
     * Get stored UTM data from sessionStorage
     */
    function getStoredUtmData() {
        try {
            var stored = sessionStorage.getItem('cuft_utm_data');
            if (stored) {
                var data = JSON.parse(stored);
                // Check if data is not too old (30 days)
                if (data.timestamp && (Date.now() - data.timestamp) < (30 * 24 * 60 * 60 * 1000)) {
                    return data.utm;
                }
            }
        } catch(e) {
            log('Error reading stored UTM data:', e);
        }
        return null;
    }
    
    /**
     * Store UTM data in sessionStorage
     */
    function storeUtmLocally(utmData) {
        try {
            var data = {
                utm: utmData,
                timestamp: Date.now()
            };
            sessionStorage.setItem('cuft_utm_data', JSON.stringify(data));
            log('UTM data stored locally:', utmData);
        } catch(e) {
            log('Error storing UTM data locally:', e);
        }
    }
    
    /**
     * Get current UTM data (from URL or storage)
     */
    function getCurrentUtmData() {
        // First check URL for fresh UTM parameters
        var urlUtm = getUtmParams();
        if (urlUtm) {
            return urlUtm;
        }
        
        // Fallback to stored data
        return getStoredUtmData();
    }
    
    /**
     * Initialize UTM tracking
     */
    function initUtmTracking() {
        var utmData = getUtmParams();
        
        if (utmData) {
            log('UTM parameters detected:', utmData);
            
            // Store locally for immediate access
            storeUtmLocally(utmData);
            
            // Store on server for persistence
            storeUtmData(utmData);
        } else {
            log('No UTM parameters in current URL');
        }
    }
    
    // Make UTM data available globally
    window.cuftGetUtmData = getCurrentUtmData;
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initUtmTracking);
    } else {
        initUtmTracking();
    }
    
    log('UTM tracking initialized');
    
})();
