/**
 * UTM utilities for form tracking
 */
window.cuftUtmUtils = (function() {
    'use strict';
    
    /**
     * Add UTM data to payload
     */
    function addUtmToPayload(payload) {
        if (window.cuftGetUtmData) {
            var utmData = window.cuftGetUtmData();
            if (utmData) {
                if (utmData.utm_source) payload.utm_source = utmData.utm_source;
                if (utmData.utm_medium) payload.utm_medium = utmData.utm_medium;
                if (utmData.utm_campaign) payload.utm_campaign = utmData.utm_campaign;
                if (utmData.utm_term) payload.utm_term = utmData.utm_term;
                if (utmData.utm_content) payload.utm_content = utmData.utm_content;
            }
        }
        return payload;
    }
    
    return {
        addUtmToPayload: addUtmToPayload
    };
})();
