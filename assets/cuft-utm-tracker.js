(function () {
  "use strict";

  var DEBUG = !!(window.cuftUTM && window.cuftUTM.debug);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT UTM]"].concat(Array.prototype.slice.call(arguments))
        );
      }
    } catch (e) {}
  }

  /**
   * UTM parameters to track
   */
  var UTM_PARAMS = [
    "utm_source",
    "utm_medium",
    "utm_campaign",
    "utm_term",
    "utm_content",
  ];

  /**
   * Click ID parameters to track
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
   * Parse URL parameters
   */
  function getUrlParams() {
    var params = {};
    var search = window.location.search.substring(1);

    if (!search) return params;

    var pairs = search.split("&");
    for (var i = 0; i < pairs.length; i++) {
      var pair = pairs[i].split("=");
      if (pair.length === 2) {
        params[decodeURIComponent(pair[0])] = decodeURIComponent(pair[1]);
      }
    }

    return params;
  }

  /**
   * Extract UTM and Click ID parameters from URL
   */
  function getTrackingParams() {
    var urlParams = getUrlParams();
    var trackingData = {};
    var hasData = false;

    // Extract UTM parameters
    for (var i = 0; i < UTM_PARAMS.length; i++) {
      var param = UTM_PARAMS[i];
      if (urlParams[param]) {
        trackingData[param] = urlParams[param];
        hasData = true;
      }
    }

    // Extract Click ID parameters
    for (var j = 0; j < CLICK_ID_PARAMS.length; j++) {
      var clickParam = CLICK_ID_PARAMS[j];
      if (urlParams[clickParam]) {
        trackingData[clickParam] = urlParams[clickParam];
        hasData = true;
        log("Click ID detected:", clickParam, urlParams[clickParam]);
      }
    }

    return hasData ? trackingData : null;
  }

  /**
   * Extract UTM parameters from URL (legacy function for compatibility)
   */
  function getUtmParams() {
    return getTrackingParams();
  }

  /**
   * Store tracking data via AJAX
   */
  function storeTrackingData(trackingData) {
    if (!window.cuftUTM || !window.cuftUTM.ajaxUrl) {
      log("AJAX URL not available");
      return;
    }

    var xhr = new XMLHttpRequest();
    xhr.open("POST", window.cuftUTM.ajaxUrl, true);
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

    xhr.onreadystatechange = function () {
      if (xhr.readyState === 4) {
        if (xhr.status === 200) {
          log("Tracking data stored successfully:", trackingData);
        } else {
          log("Error storing tracking data:", xhr.status);
        }
      }
    };

    // Build form data
    var formData =
      "action=cuft_store_utm&nonce=" + encodeURIComponent(window.cuftUTM.nonce);
    for (var key in trackingData) {
      if (trackingData.hasOwnProperty(key)) {
        formData +=
          "&" +
          encodeURIComponent(key) +
          "=" +
          encodeURIComponent(trackingData[key]);
      }
    }

    xhr.send(formData);
  }

  /**
   * Get cookie value by name
   */
  function getCookie(name) {
    try {
      var value = "; " + document.cookie;
      var parts = value.split("; " + name + "=");
      if (parts.length === 2) {
        return parts.pop().split(";").shift();
      }
    } catch (e) {
      log("Error reading cookie:", e);
    }
    return null;
  }

  /**
   * Get stored tracking data from cookies
   */
  function getStoredTrackingDataFromCookie() {
    try {
      var cookieValue = getCookie("cuft_utm_data");
      if (cookieValue) {
        var decodedValue = decodeURIComponent(cookieValue);
        var data = JSON.parse(decodedValue);

        // Check if cookie data has the expected structure
        if (data && data.utm) {
          // Check if cookie is not too old (30 days)
          if (
            data.timestamp &&
            Date.now() / 1000 - data.timestamp < 30 * 24 * 60 * 60
          ) {
            log("Tracking data retrieved from cookie:", data.utm);
            return data.utm;
          } else {
            log("Cookie data is too old");
          }
        }
      }
    } catch (e) {
      log("Error reading tracking data from cookie:", e);
    }
    return null;
  }

  /**
   * Get stored tracking data from sessionStorage
   */
  function getStoredTrackingData() {
    try {
      var stored = sessionStorage.getItem("cuft_tracking_data");
      if (stored) {
        var data = JSON.parse(stored);
        // Check if data is not too old (30 days)
        if (
          data.timestamp &&
          Date.now() - data.timestamp < 30 * 24 * 60 * 60 * 1000
        ) {
          log("Tracking data retrieved from sessionStorage:", data.tracking);
          return data.tracking;
        } else {
          log("SessionStorage data is too old");
        }
      }
    } catch (e) {
      log("Error reading stored tracking data from sessionStorage:", e);
    }

    // Fallback to cookie if sessionStorage fails
    return getStoredTrackingDataFromCookie();
  }

  /**
   * Store tracking data in sessionStorage
   */
  function storeTrackingLocally(trackingData) {
    try {
      var data = {
        tracking: trackingData,
        timestamp: Date.now(),
      };
      sessionStorage.setItem("cuft_tracking_data", JSON.stringify(data));
      log("Tracking data stored locally:", trackingData);
    } catch (e) {
      log("Error storing tracking data locally:", e);
    }
  }

  /**
   * Get stored UTM data from sessionStorage (legacy function for compatibility)
   */
  function getStoredUtmData() {
    return getStoredTrackingData();
  }

  /**
   * Store UTM data in sessionStorage (legacy function for compatibility)
   */
  function storeUtmLocally(utmData) {
    return storeTrackingLocally(utmData);
  }

  /**
   * Get current tracking data with graceful fallback
   * Priority: URL parameters -> SessionStorage -> Cookie -> Empty
   */
  function getCurrentTrackingData() {
    var trackingData = null;

    // 1. First check URL for fresh tracking parameters
    log("Checking URL for tracking parameters...");
    trackingData = getTrackingParams();
    if (trackingData && Object.keys(trackingData).length > 0) {
      log("Tracking data found in URL:", trackingData);
      return trackingData;
    }

    // 2. Then check sessionStorage
    log("No URL parameters found, checking sessionStorage...");
    var sessionData = null;
    try {
      var stored = sessionStorage.getItem("cuft_tracking_data");
      if (stored) {
        var data = JSON.parse(stored);
        if (
          data.timestamp &&
          Date.now() - data.timestamp < 30 * 24 * 60 * 60 * 1000
        ) {
          sessionData = data.tracking;
          log("Tracking data found in sessionStorage:", sessionData);
        }
      }
    } catch (e) {
      log("Error reading sessionStorage:", e);
    }

    if (sessionData && Object.keys(sessionData).length > 0) {
      return sessionData;
    }

    // 3. Finally check cookies
    log("No sessionStorage data found, checking cookies...");
    var cookieData = getStoredTrackingDataFromCookie();
    if (cookieData && Object.keys(cookieData).length > 0) {
      log("Tracking data found in cookie:", cookieData);
      return cookieData;
    }

    // 4. Return empty object if nothing found
    log("No tracking data found in any source");
    return {};
  }

  /**
   * Get current UTM data (from URL or storage) - legacy function for compatibility
   */
  function getCurrentUtmData() {
    return getCurrentTrackingData();
  }

  /**
   * Initialize tracking
   */
  function initTracking() {
    var trackingData = getTrackingParams();

    if (trackingData) {
      log("Tracking parameters detected:", trackingData);

      // Store locally for immediate access
      storeTrackingLocally(trackingData);

      // Store on server for persistence
      storeTrackingData(trackingData);
    } else {
      log("No tracking parameters in current URL");
    }
  }

  /**
   * Initialize UTM tracking (legacy function for compatibility)
   */
  function initUtmTracking() {
    return initTracking();
  }

  // Make tracking data available globally
  window.cuftGetUtmData = getCurrentUtmData; // Legacy function name for compatibility
  window.cuftGetTrackingData = getCurrentTrackingData;

  // Initialize when DOM is ready
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initTracking);
  } else {
    initTracking();
  }

  log("UTM and Click ID tracking initialized");
})();
