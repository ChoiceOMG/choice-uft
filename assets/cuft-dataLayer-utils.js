/**
 * Shared DataLayer Utilities for Choice Universal Form Tracker
 * Provides standardized functions for all form frameworks to ensure consistent dataLayer events
 * Version: 1.0
 * Date: 2025-09-25
 * Specification: specs/core/dataLayer.spec.md
 */
window.cuftDataLayerUtils = (function () {
  "use strict";


  /**
   * Framework identifiers mapping
   */
  var FRAMEWORK_IDENTIFIERS = {
    elementor: {
      form_type: "elementor",
      cuft_source: "elementor_pro",
      cuft_source_lead: "elementor_pro_lead"
    },
    cf7: {
      form_type: "cf7",
      cuft_source: "contact_form_7",
      cuft_source_lead: "contact_form_7_lead"
    },
    ninja: {
      form_type: "ninja",
      cuft_source: "ninja_forms",
      cuft_source_lead: "ninja_forms_lead"
    },
    gravity: {
      form_type: "gravity",
      cuft_source: "gravity_forms",
      cuft_source_lead: "gravity_forms_lead"
    },
    avada: {
      form_type: "avada",
      cuft_source: "avada_forms",
      cuft_source_lead: "avada_forms_lead"
    }
  };

  /**
   * Click ID parameters for lead qualification
   */
  var CLICK_ID_FIELDS = [
    "click_id", "gclid", "gbraid", "wbraid", "fbclid",
    "msclkid", "ttclid", "li_fat_id", "twclid", "snap_click_id", "pclid"
  ];

  /**
   * Get safe dataLayer reference with fallback
   */
  function getDataLayer() {
    try {
      return (window.dataLayer = window.dataLayer || []);
    } catch (e) {
      return { push: function () {} }; // No-op fallback
    }
  }

  /**
   * Generate ISO 8601 timestamp
   */
  function generateTimestamp() {
    try {
      return new Date().toISOString();
    } catch (e) {
      return new Date().toUTCString(); // Fallback format
    }
  }

  /**
   * Validate email address using specification pattern
   */
  function validateEmail(email) {
    if (!email || typeof email !== 'string') return false;
    var emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailPattern.test(email.trim());
  }

  /**
   * Sanitize phone number per specification
   */
  function sanitizePhone(phone) {
    if (!phone || typeof phone !== 'string') return "";
    // Strip common formatting characters, preserve international format indicators
    return phone.replace(/[()\-\s]/g, "").replace(/(?!^\+)[^\d]/g, "");
  }

  /**
   * Check if form has been processed to prevent duplicates
   */
  function isFormProcessed(formElement) {
    return formElement && formElement.getAttribute('data-cuft-processed') === 'true';
  }

  /**
   * Mark form as processed to prevent duplicates
   */
  function markFormProcessed(formElement) {
    if (formElement) {
      formElement.setAttribute('data-cuft-processed', 'true');
    }
  }

  /**
   * Check if qualify_lead conditions are met (email + phone + click_id)
   */
  function meetsQualifyConditions(payload) {
    // Must have valid email and phone
    if (!payload.user_email || !payload.user_phone) {
      return false;
    }

    // Must have at least one click ID
    for (var i = 0; i < CLICK_ID_FIELDS.length; i++) {
      if (payload[CLICK_ID_FIELDS[i]]) {
        return true;
      }
    }
    return false;
  }

  /**
   * Check if generate_lead conditions are met (broad: just email)
   */
  function meetsGenerateLeadConditions(payload) {
    return !!payload.user_email;
  }

  /**
   * Get UTM and tracking parameters from existing utilities
   */
  function getTrackingParameters() {
    var trackingData = {};

    try {
      // Use existing UTM utilities if available
      if (window.cuftGetTrackingData) {
        trackingData = window.cuftGetTrackingData() || {};
      } else if (window.cuftGetUtmData) {
        trackingData = window.cuftGetUtmData() || {};
      }
    } catch (e) {
      // Silent failure for tracking parameter retrieval
    }

    return trackingData;
  }

  /**
   * Extract GA4 client_id from _ga cookie
   */
  function getGaClientId() {
    try {
      var cookie = document.cookie.match(/(^|; )_ga=([^;]*)/);
      if (cookie && cookie[2]) {
        var parts = cookie[2].split(".");
        if (parts.length >= 4) {
          var clientId = parts[2] + "." + parts[3];
          if (/^\d+\.\d+$/.test(clientId)) {
            return clientId;
          }
        }
      }
    } catch (e) {
      // Silently fail — ga_client_id is optional
    }
    return null;
  }

  /**
   * Create standardized form_submit event payload
   */
  function createFormSubmitPayload(framework, formId, options) {
    options = options || {};

    // Validate framework
    var frameworkConfig = FRAMEWORK_IDENTIFIERS[framework];
    if (!frameworkConfig) {
      throw new Error('[CUFT DataLayer] Unknown framework: ' + framework);
    }

    // Required fields
    var payload = {
      event: "form_submit",
      cuft_tracked: true,
      cuft_source: frameworkConfig.cuft_source,
      form_type: frameworkConfig.form_type,
      form_id: formId || "unknown",
      submitted_at: generateTimestamp()
    };

    // Optional form name
    if (options.form_name) {
      payload.form_name = options.form_name;
    }

    // Optional and validated email
    if (options.user_email && validateEmail(options.user_email)) {
      payload.user_email = options.user_email;
    }

    // Optional and sanitized phone
    if (options.user_phone) {
      var sanitized = sanitizePhone(options.user_phone);
      if (sanitized) {
        payload.user_phone = sanitized;
      }
    }

    // Add UTM and click ID parameters
    var trackingParams = getTrackingParameters();
    if (trackingParams && typeof trackingParams === 'object') {
      // Ensure generic click_id is set if any specific click ID exists
      var hasClickId = false;
      for (var i = 0; i < CLICK_ID_FIELDS.length; i++) {
        var clickField = CLICK_ID_FIELDS[i];
        if (trackingParams[clickField] && clickField !== 'click_id') {
          // Set generic click_id to the first specific click ID found
          if (!trackingParams.click_id) {
            trackingParams.click_id = trackingParams[clickField];
          }
          hasClickId = true;
          break;
        }
      }

      // Add all tracking parameters to payload
      for (var key in trackingParams) {
        if (trackingParams.hasOwnProperty(key) && trackingParams[key]) {
          payload[key] = trackingParams[key];
        }
      }
    }

    // Add click tracking data (ip_hash, platform) from server-side tracking
    if (typeof window.cuftClickData === 'object' && window.cuftClickData) {
      if (window.cuftClickData.ip_hash) {
        payload.ip_hash = window.cuftClickData.ip_hash;
      }
      if (window.cuftClickData.platform) {
        payload.click_platform = window.cuftClickData.platform;
      }
      // Also set click_id from server if not already present
      if (!payload.click_id && window.cuftClickData.click_id) {
        payload.click_id = window.cuftClickData.click_id;
      }
    }

    // Add GA4 client_id for Measurement Protocol
    var gaClientId = getGaClientId();
    if (gaClientId) {
      payload.ga_client_id = gaClientId;
    }

    return payload;
  }

  /**
   * Create standardized qualify_lead event payload (strict: email + phone + click_id)
   */
  function createQualifyLeadPayload(formSubmitPayload, framework, options) {
    // Validate framework
    var frameworkConfig = FRAMEWORK_IDENTIFIERS[framework];
    if (!frameworkConfig) {
      throw new Error('[CUFT DataLayer] Unknown framework: ' + framework);
    }

    options = options || {};

    // Copy all form_submit fields and modify event-specific fields
    var leadPayload = {};
    for (var key in formSubmitPayload) {
      if (formSubmitPayload.hasOwnProperty(key)) {
        leadPayload[key] = formSubmitPayload[key];
      }
    }

    // Override event-specific fields
    leadPayload.event = "qualify_lead";
    leadPayload.cuft_source = frameworkConfig.cuft_source_lead;
    leadPayload.currency = options.lead_currency || "CAD";
    leadPayload.value = parseFloat(options.lead_value) || 100;

    return leadPayload;
  }

  /**
   * Create standardized generate_lead event payload (broad: just email)
   */
  function createGenerateLeadPayload(formSubmitPayload, framework, options) {
    var frameworkConfig = FRAMEWORK_IDENTIFIERS[framework];
    if (!frameworkConfig) {
      throw new Error('[CUFT DataLayer] Unknown framework: ' + framework);
    }
    options = options || {};
    var leadPayload = {};
    for (var key in formSubmitPayload) {
      if (formSubmitPayload.hasOwnProperty(key)) {
        leadPayload[key] = formSubmitPayload[key];
      }
    }
    leadPayload.event = "generate_lead";
    leadPayload.cuft_source = frameworkConfig.cuft_source_lead;
    leadPayload.currency = options.lead_currency || "CAD";
    leadPayload.value = parseFloat(options.lead_value) || 100;
    return leadPayload;
  }

  /**
   * Push event to dataLayer with error handling
   */
  function pushToDataLayer(payload, debugContext) {
    try {
      var dataLayer = getDataLayer();
      dataLayer.push(payload);

      // Debug logging if context provided
      if (debugContext && debugContext.debug && window.console && window.console.log) {
        window.console.log('[CUFT DataLayer] Event pushed:', payload.event, payload);
      }

      return true;
    } catch (e) {
      if (debugContext && debugContext.debug && window.console && window.console.error) {
        window.console.error('[CUFT DataLayer] Push error:', {
          framework: debugContext.framework || 'unknown',
          formId: payload ? payload.form_id : 'unknown',
          error: e.message,
          eventType: payload ? payload.event : 'unknown'
        });
      }
      return false;
    }
  }

  /**
   * Record event to click tracking database via AJAX
   * Fire-and-forget pattern with silent failures
   */
  function recordEvent(clickId, eventType, debugMode, gaClientId) {
    try {
      // Validate inputs
      if (!clickId || !eventType) {
        return;
      }

      // Check if cuftConfig is available
      if (typeof window.cuftConfig === "undefined" || !window.cuftConfig.ajaxUrl) {
        if (debugMode && window.console) {
          window.console.log('[CUFT DataLayer] cuftConfig not available for event recording');
        }
        return;
      }

      // Build POST params
      var postParams = {
        action: "cuft_record_event",
        nonce: window.cuftConfig.nonce,
        click_id: clickId,
        event_type: eventType,
      };
      if (gaClientId) {
        postParams.ga_client_id = gaClientId;
      }

      // Fire-and-forget: Use fetch if available, fallback to XMLHttpRequest
      if (typeof fetch !== "undefined") {
        fetch(window.cuftConfig.ajaxUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams(postParams),
        })
          .then(function (response) {
            if (response.ok && debugMode && window.console) {
              window.console.log('[CUFT DataLayer] Event recorded:', eventType, 'for click_id:', clickId);
            }
          })
          .catch(function (err) {
            // Silent failure in production, log in debug mode
            if (debugMode && window.console) {
              window.console.warn('[CUFT DataLayer] Failed to record event:', err);
            }
          });
      } else {
        // Legacy browsers: fallback to XMLHttpRequest
        var xhr = new XMLHttpRequest();
        xhr.open("POST", window.cuftConfig.ajaxUrl, true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

        var params =
          "action=cuft_record_event" +
          "&nonce=" + encodeURIComponent(window.cuftConfig.nonce) +
          "&click_id=" + encodeURIComponent(clickId) +
          "&event_type=" + encodeURIComponent(eventType);

        if (gaClientId) {
          params += "&ga_client_id=" + encodeURIComponent(gaClientId);
        }

        xhr.onreadystatechange = function () {
          if (xhr.readyState === 4 && xhr.status === 200 && debugMode && window.console) {
            window.console.log('[CUFT DataLayer] Event recorded:', eventType, 'for click_id:', clickId);
          }
        };

        xhr.send(params);
      }
    } catch (err) {
      // Silent failure - never interfere with main functionality
      if (debugMode && window.console) {
        window.console.error('[CUFT DataLayer] Event recording error:', err);
      }
    }
  }

  /**
   * Get click_id from tracking parameters
   */
  function getClickIdFromTracking() {
    try {
      var trackingParams = getTrackingParameters();
      if (trackingParams && typeof trackingParams === 'object') {
        // Check all click ID fields in priority order
        for (var i = 0; i < CLICK_ID_FIELDS.length; i++) {
          if (trackingParams[CLICK_ID_FIELDS[i]]) {
            return trackingParams[CLICK_ID_FIELDS[i]];
          }
        }
      }
    } catch (e) {
      // Silent failure
    }
    return null;
  }

  /**
   * Main function to handle form submission tracking
   */
  function trackFormSubmission(framework, formElement, options) {
    options = options || {};

    try {
      // Prevent duplicate processing
      if (isFormProcessed(formElement)) {
        if (options.debug) {
          console.log('[CUFT DataLayer] Form already processed, skipping:', formElement);
        }
        return false;
      }

      // Extract form ID
      var formId = options.form_id ||
                  (formElement ? formElement.id : null) ||
                  (formElement ? formElement.getAttribute('data-form-id') : null) ||
                  "unknown";

      // Create form_submit payload
      var formSubmitPayload = createFormSubmitPayload(framework, formId, {
        form_name: options.form_name,
        user_email: options.user_email,
        user_phone: options.user_phone
      });

      // Push form_submit event to dataLayer
      var submitSuccess = pushToDataLayer(formSubmitPayload, {
        debug: options.debug,
        framework: framework
      });

      if (!submitSuccess) {
        return false;
      }

      // Record form_submit event to click tracking database (fire-and-forget)
      var clickId = getClickIdFromTracking();
      var gaClientId = formSubmitPayload.ga_client_id || null;
      if (clickId) {
        recordEvent(clickId, 'form_submit', options.debug, gaClientId);
      }

      // Fire generate_lead if email is present (broad GA4 meaning)
      if (meetsGenerateLeadConditions(formSubmitPayload)) {
        var generateLeadPayload = createGenerateLeadPayload(formSubmitPayload, framework, {
          lead_currency: options.lead_currency,
          lead_value: options.lead_value
        });
        pushToDataLayer(generateLeadPayload, {
          debug: options.debug,
          framework: framework
        });

        // Record generate_lead event to click tracking database
        if (clickId) {
          recordEvent(clickId, 'generate_lead', options.debug, formSubmitPayload.ga_client_id);
        }

        if (options.debug && window.console && window.console.log) {
          window.console.log('[CUFT DataLayer] generate_lead event fired for:', framework);
        }
      }

      // Fire qualify_lead if strict criteria met (email + phone + click_id)
      if (meetsQualifyConditions(formSubmitPayload)) {
        var qualifyLeadPayload = createQualifyLeadPayload(formSubmitPayload, framework, {
          lead_currency: options.lead_currency,
          lead_value: options.lead_value
        });
        pushToDataLayer(qualifyLeadPayload, {
          debug: options.debug,
          framework: framework
        });

        // Record qualify_lead event server-side
        if (clickId) {
          recordEvent(clickId, 'qualify_lead', options.debug, gaClientId);
        }

        // DEPRECATED: Dual-fire old generate_lead with strict payload for one version
        var deprecatedPayload = createQualifyLeadPayload(formSubmitPayload, framework, {
          lead_currency: options.lead_currency,
          lead_value: options.lead_value
        });
        deprecatedPayload.event = "generate_lead";
        deprecatedPayload.cuft_deprecated = true;
        deprecatedPayload.cuft_migrate_to = "qualify_lead";
        pushToDataLayer(deprecatedPayload, {
          debug: options.debug,
          framework: framework
        });

        if ((options.console_logging === "yes" || options.debug) && window.console && window.console.warn) {
          window.console.warn('[CUFT] "generate_lead" with strict criteria is deprecated. Update your GTM trigger to use "qualify_lead" instead.');
        }

        if (options.debug && window.console && window.console.log) {
          window.console.log('[CUFT DataLayer] qualify_lead event fired for:', framework);
        }
      }

      // Mark form as processed
      markFormProcessed(formElement);
      return true;
    } catch (e) {
      return false;
    }
  }


  // Public API
  return {
    // Core tracking function
    trackFormSubmission: trackFormSubmission,

    // Utility functions
    validateEmail: validateEmail,
    sanitizePhone: sanitizePhone,
    generateTimestamp: generateTimestamp,
    isFormProcessed: isFormProcessed,
    markFormProcessed: markFormProcessed,
    meetsQualifyConditions: meetsQualifyConditions,
    meetsGenerateLeadConditions: meetsGenerateLeadConditions,

    // Event creation functions
    createFormSubmitPayload: createFormSubmitPayload,
    createGenerateLeadPayload: createGenerateLeadPayload,
    createQualifyLeadPayload: createQualifyLeadPayload,
    pushToDataLayer: pushToDataLayer,

    // Event recording functions (v3.12.0)
    recordEvent: recordEvent,
    getClickIdFromTracking: getClickIdFromTracking,
    getGaClientId: getGaClientId,

    // Data access
    getTrackingParameters: getTrackingParameters,
    getDataLayer: getDataLayer,

    // Constants
    FRAMEWORK_IDENTIFIERS: FRAMEWORK_IDENTIFIERS,
    CLICK_ID_FIELDS: CLICK_ID_FIELDS
  };
})();