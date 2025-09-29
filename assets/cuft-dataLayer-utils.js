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
   * Check if lead conditions are met (email + phone + click_id)
   */
  function meetsLeadConditions(payload) {
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

    return payload;
  }

  /**
   * Create standardized generate_lead event payload
   */
  function createGenerateLeadPayload(formSubmitPayload, framework, options) {
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
      if (window.console && window.console.error) {
        window.console.error('[CUFT DataLayer] Push error:', {
          framework: debugContext ? debugContext.framework : 'unknown',
          formId: payload ? payload.form_id : 'unknown',
          error: e.message,
          eventType: payload ? payload.event : 'unknown'
        });
      }
      return false;
    }
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

      // Push form_submit event
      var submitSuccess = pushToDataLayer(formSubmitPayload, {
        debug: options.debug,
        framework: framework
      });

      if (!submitSuccess) {
        return false;
      }

      // Check for generate_lead conditions
      var meetsConditions = meetsLeadConditions(formSubmitPayload);

      if (options.debug && window.console && window.console.log) {
        window.console.log('[CUFT DataLayer] Generate lead conditions check:', {
          framework: framework,
          meetsConditions: meetsConditions,
          hasEmail: !!formSubmitPayload.user_email,
          hasPhone: !!formSubmitPayload.user_phone,
          hasClickId: !!(formSubmitPayload.click_id || formSubmitPayload.gclid ||
                       formSubmitPayload.fbclid || formSubmitPayload.wbraid ||
                       formSubmitPayload.gbraid || formSubmitPayload.msclkid),
          clickIds: {
            click_id: formSubmitPayload.click_id || null,
            gclid: formSubmitPayload.gclid || null,
            fbclid: formSubmitPayload.fbclid || null,
            wbraid: formSubmitPayload.wbraid || null,
            gbraid: formSubmitPayload.gbraid || null,
            msclkid: formSubmitPayload.msclkid || null
          }
        });
      }

      if (meetsConditions) {
        var leadPayload = createGenerateLeadPayload(formSubmitPayload, framework, {
          lead_currency: options.lead_currency,
          lead_value: options.lead_value
        });
        pushToDataLayer(leadPayload, {
          debug: options.debug,
          framework: framework
        });

        if (options.debug && window.console && window.console.log) {
          window.console.log('[CUFT DataLayer] ✅ generate_lead event fired for:', framework);
        }
      } else {
        if (options.debug && window.console && window.console.log) {
          window.console.log('[CUFT DataLayer] ❌ generate_lead NOT fired for:', framework, 'Missing requirements');
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
    meetsLeadConditions: meetsLeadConditions,

    // Event creation functions
    createFormSubmitPayload: createFormSubmitPayload,
    createGenerateLeadPayload: createGenerateLeadPayload,
    pushToDataLayer: pushToDataLayer,

    // Data access
    getTrackingParameters: getTrackingParameters,
    getDataLayer: getDataLayer,

    // Constants
    FRAMEWORK_IDENTIFIERS: FRAMEWORK_IDENTIFIERS,
    CLICK_ID_FIELDS: CLICK_ID_FIELDS
  };
})();