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
    "click_id", "gclid", "gbraid", "wbraid", "fbclid", "rdt_cid",
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
      // Silently fail; ga_client_id is optional
    }
    return null;
  }

  /**
   * Normalize an email for hashing: trim + lowercase.
   * Mirrors CUFT_Form_Attribution::lead_id_from_email() in PHP so digests match.
   */
  function normalizeEmailForHash(email) {
    return String(email).trim().toLowerCase();
  }

  /**
   * Normalize a phone for hashing to canonical E.164 ("+<digits>").
   * Mirrors CUFT_Form_Attribution::normalize_phone_e164() in PHP. NANP default:
   * a bare 10-digit number is prefixed with country code 1.
   */
  function normalizePhoneForHash(phone) {
    var digits = String(phone).replace(/\D+/g, "");
    if (!digits) return "";
    if (digits.length === 10) {
      digits = "1" + digits;
    }
    return "+" + digits;
  }

  /**
   * SHA-256 over a UTF-8 string, returned as lowercase hex.
   *
   * Bundled rather than pulled from a CDN: WordPress.org guideline 8 requires
   * all non-service JavaScript to ship inside the plugin. It stays synchronous
   * because lead_id has to be on the payload before the event is pushed, which
   * rules out the async SubtleCrypto digest. Output matches PHP hash('sha256').
   */
  var sha256Hex = (function () {
    var K = [
      0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1,
      0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3,
      0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786,
      0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
      0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147,
      0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13,
      0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b,
      0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
      0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a,
      0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208,
      0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
    ];

    function rotr(x, n) {
      return (x >>> n) | (x << (32 - n));
    }

    /** UTF-8 encode without TextEncoder, so the result is identical everywhere. */
    function utf8Bytes(str) {
      var bytes = [];
      for (var i = 0; i < str.length; i++) {
        var c = str.charCodeAt(i);
        if (c < 0x80) {
          bytes.push(c);
        } else if (c < 0x800) {
          bytes.push(0xc0 | (c >> 6), 0x80 | (c & 0x3f));
        } else if (c < 0xd800 || c >= 0xe000) {
          bytes.push(0xe0 | (c >> 12), 0x80 | ((c >> 6) & 0x3f), 0x80 | (c & 0x3f));
        } else {
          // Surrogate pair; combine into a single code point.
          i++;
          c = 0x10000 + (((c & 0x3ff) << 10) | (str.charCodeAt(i) & 0x3ff));
          bytes.push(
            0xf0 | (c >> 18),
            0x80 | ((c >> 12) & 0x3f),
            0x80 | ((c >> 6) & 0x3f),
            0x80 | (c & 0x3f)
          );
        }
      }
      return bytes;
    }

    return function (input) {
      try {
        var H = [
          0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a,
          0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19
        ];
        var bytes = utf8Bytes(String(input));
        var bitLen = bytes.length * 8;

        bytes.push(0x80);
        while (bytes.length % 64 !== 56) {
          bytes.push(0);
        }
        var hi = Math.floor(bitLen / 0x100000000);
        var lo = bitLen >>> 0;
        bytes.push(
          (hi >>> 24) & 0xff, (hi >>> 16) & 0xff, (hi >>> 8) & 0xff, hi & 0xff,
          (lo >>> 24) & 0xff, (lo >>> 16) & 0xff, (lo >>> 8) & 0xff, lo & 0xff
        );

        var w = new Array(64);
        var i, j, a, b, c, d, e, f, g, h, s0, s1, ch, maj, t1, t2;

        for (i = 0; i < bytes.length; i += 64) {
          for (j = 0; j < 16; j++) {
            w[j] =
              (bytes[i + j * 4] << 24) |
              (bytes[i + j * 4 + 1] << 16) |
              (bytes[i + j * 4 + 2] << 8) |
              bytes[i + j * 4 + 3];
          }
          for (j = 16; j < 64; j++) {
            s0 = rotr(w[j - 15], 7) ^ rotr(w[j - 15], 18) ^ (w[j - 15] >>> 3);
            s1 = rotr(w[j - 2], 17) ^ rotr(w[j - 2], 19) ^ (w[j - 2] >>> 10);
            w[j] = (w[j - 16] + s0 + w[j - 7] + s1) | 0;
          }

          a = H[0]; b = H[1]; c = H[2]; d = H[3];
          e = H[4]; f = H[5]; g = H[6]; h = H[7];

          for (j = 0; j < 64; j++) {
            s1 = rotr(e, 6) ^ rotr(e, 11) ^ rotr(e, 25);
            ch = (e & f) ^ (~e & g);
            t1 = (h + s1 + ch + K[j] + w[j]) | 0;
            s0 = rotr(a, 2) ^ rotr(a, 13) ^ rotr(a, 22);
            maj = (a & b) ^ (a & c) ^ (b & c);
            t2 = (s0 + maj) | 0;
            h = g; g = f; f = e; e = (d + t1) | 0;
            d = c; c = b; b = a; a = (t1 + t2) | 0;
          }

          H[0] = (H[0] + a) | 0; H[1] = (H[1] + b) | 0;
          H[2] = (H[2] + c) | 0; H[3] = (H[3] + d) | 0;
          H[4] = (H[4] + e) | 0; H[5] = (H[5] + f) | 0;
          H[6] = (H[6] + g) | 0; H[7] = (H[7] + h) | 0;
        }

        var hex = "";
        for (i = 0; i < 8; i++) {
          hex += ("00000000" + (H[i] >>> 0).toString(16)).slice(-8);
        }
        return hex;
      } catch (err) {
        // lead_id is optional client-side; the server-side payload still carries it.
        return null;
      }
    };
  })();

  /**
   * Compute the shared lead identity (lead_id + lead_id_source). Email preferred,
   * phone fallback. Returns {} when nothing can be computed. (OPS-2210)
   */
  function computeLeadIdentity(email, phone) {
    var hex = null;
    var source = null;

    if (email && validateEmail(email)) {
      hex = sha256Hex(normalizeEmailForHash(email));
      source = "email";
    }
    if (!hex && phone) {
      var normalized = normalizePhoneForHash(phone);
      if (normalized) {
        hex = sha256Hex(normalized);
        source = "phone";
      }
    }

    if (!hex) return {};
    return { lead_id: hex, lead_id_source: source };
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

    // Shared cross-system lead identity (OPS-2210). Deterministic sha256 of the
    // normalized email (or phone fallback) so the analytics event matches the
    // server-side webhook/entry/email value.
    var leadIdentity = computeLeadIdentity(payload.user_email, payload.user_phone);
    if (leadIdentity.lead_id) {
      payload.lead_id = leadIdentity.lead_id;
      payload.lead_id_source = leadIdentity.lead_id_source;
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
    computeLeadIdentity: computeLeadIdentity,
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