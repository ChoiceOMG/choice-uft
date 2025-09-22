/**
 * UTM and Click ID utilities for form tracking
 */
window.cuftUtmUtils = (function () {
  "use strict";

  /**
   * UTM parameters
   */
  var UTM_PARAMS = [
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_term",
    "utm_content",
  ];

  /**
   * Click ID parameters
   */
  var CLICK_ID_PARAMS = [
    "click_id", // Generic click ID parameter
    "gclid", // Google Ads click ID
    "gbraid", // Google Ads click ID for iOS app-to-web journeys
    "wbraid", // Google Ads click ID for web-to-app journeys
    "fbclid", // Facebook / Instagram (Meta Ads) click ID
    "msclkid", // Microsoft Advertising (Bing Ads) click ID
    "ttclid", // TikTok Ads click ID
    "li_fat_id", // LinkedIn Ads click ID
    "twclid", // Twitter / X Ads click ID
    "snap_click_id", // Snapchat Ads click ID
    "pclid", // Pinterest Ads click ID
  ];

  /**
   * Add UTM and Click ID data to payload with graceful fallback
   */
  function addUtmToPayload(payload) {
    var trackingData = {};

    // Try new function first, fallback to legacy
    if (window.cuftGetTrackingData) {
      trackingData = window.cuftGetTrackingData() || {};
    } else if (window.cuftGetUtmData) {
      trackingData = window.cuftGetUtmData() || {};
    }

    // Debug logging
    if (window.console && window.console.log) {
      var hasData = trackingData && Object.keys(trackingData).length > 0;
      console.log(
        '[CUFT UTM Utils] Tracking data retrieved:',
        hasData ? trackingData : 'No data available'
      );
    }

    // Always attempt to add parameters, even if trackingData is empty
    if (trackingData && typeof trackingData === 'object') {
      var fieldsAdded = [];

      // Add UTM parameters
      for (var i = 0; i < UTM_PARAMS.length; i++) {
        var utmParam = UTM_PARAMS[i];
        if (trackingData[utmParam]) {
          payload[utmParam] = trackingData[utmParam];
          fieldsAdded.push(utmParam);
        }
      }

      // Add Click ID parameters
      var clickIdAdded = false;
      for (var j = 0; j < CLICK_ID_PARAMS.length; j++) {
        var clickParam = CLICK_ID_PARAMS[j];
        if (trackingData[clickParam]) {
          payload[clickParam] = trackingData[clickParam];
          fieldsAdded.push(clickParam);

          // Add generic click_id field for the first click ID found
          if (!clickIdAdded && !payload.click_id) {
            payload.click_id = trackingData[clickParam];
            clickIdAdded = true;
          }
        }
      }

      // Debug logging
      if (window.console && window.console.log) {
        if (fieldsAdded.length > 0) {
          console.log(
            '[CUFT UTM Utils] Added tracking fields to payload:',
            fieldsAdded.join(', ')
          );
          console.log('[CUFT UTM Utils] Final payload:', payload);
        } else {
          console.log('[CUFT UTM Utils] No tracking fields added to payload');
        }
      }
    }

    return payload;
  }

  return {
    addUtmToPayload: addUtmToPayload,
  };
})();
