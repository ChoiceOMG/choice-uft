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
   * Add UTM and Click ID data to payload
   */
  function addUtmToPayload(payload) {
    var trackingData = null;

    // Try new function first, fallback to legacy
    if (window.cuftGetTrackingData) {
      trackingData = window.cuftGetTrackingData();
    } else if (window.cuftGetUtmData) {
      trackingData = window.cuftGetUtmData();
    }

    if (trackingData) {
      // Add UTM parameters
      for (var i = 0; i < UTM_PARAMS.length; i++) {
        var utmParam = UTM_PARAMS[i];
        if (trackingData[utmParam]) {
          payload[utmParam] = trackingData[utmParam];
        }
      }

      // Add Click ID parameters
      for (var j = 0; j < CLICK_ID_PARAMS.length; j++) {
        var clickParam = CLICK_ID_PARAMS[j];
        if (trackingData[clickParam]) {
          payload[clickParam] = trackingData[clickParam];
        }
      }
    }

    return payload;
  }

  return {
    addUtmToPayload: addUtmToPayload,
  };
})();
