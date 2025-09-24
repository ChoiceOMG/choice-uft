(function () {
  "use strict";

  var DEBUG = !!(window.cuftElementor && window.cuftElementor.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Elementor]"].concat(Array.prototype.slice.call(arguments))
        );
      }
    } catch (e) {}
  }

  function getDL() {
    try {
      return (window.dataLayer = window.dataLayer || []);
    } catch (e) {
      return { push: function () {} };
    }
  }

  function ready(fn) {
    if (
      document.readyState === "complete" ||
      document.readyState === "interactive"
    ) {
      setTimeout(fn, 1);
    } else {
      document.addEventListener("DOMContentLoaded", fn);
    }
  }

  function fixInvalidPatterns() {
    try {
      var inputs = document.querySelectorAll("input[pattern]");
      var fixedCount = 0;

      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var pattern = input.getAttribute("pattern");

        if (!pattern) continue;

        // Check for the specific problematic pattern or similar issues
        // The pattern [0-9()#&+*-=.] has a hyphen in the middle which creates an invalid range
        // We need to either escape it or move it to the beginning/end of the character class

        // Fix patterns with hyphen in problematic positions
        var needsFix = false;
        var fixedPattern = pattern;

        // Look for character classes with hyphens that aren't at the beginning or end
        // and aren't properly escaped
        if (pattern.indexOf("[") !== -1) {
          // Handle the specific known problematic pattern first
          if (pattern === "[0-9()#&+*-=.]+") {
            fixedPattern = "[0-9()#&+*=.-]+";  // Move hyphen to end
            needsFix = true;
          }
          // Check for other patterns that have hyphens creating invalid ranges
          else if (pattern.indexOf("-") > -1 && pattern.indexOf("[") > -1) {
            // Simple approach: if we have a pattern with [characters-characters] where
            // the hyphen creates an invalid range, move it to the end
            var charClassMatch = pattern.match(/\[([^\]]+)\]/);
            if (charClassMatch) {
              var charClass = charClassMatch[1];
              var hyphenIndex = charClass.indexOf("-");

              // If hyphen is not at beginning or end, and creates an invalid range
              if (hyphenIndex > 0 && hyphenIndex < charClass.length - 1) {
                var beforeHyphen = charClass.charAt(hyphenIndex - 1);
                var afterHyphen = charClass.charAt(hyphenIndex + 1);

                // Check if this would be an invalid range (e.g., *-=)
                if (beforeHyphen && afterHyphen &&
                    !(beforeHyphen === "0" && afterHyphen === "9") &&
                    !(beforeHyphen === "a" && afterHyphen === "z") &&
                    !(beforeHyphen === "A" && afterHyphen === "Z") &&
                    beforeHyphen.charCodeAt(0) >= afterHyphen.charCodeAt(0)) {
                  // Move hyphen to end
                  var newCharClass = charClass.replace("-", "") + "-";
                  fixedPattern = pattern.replace(charClassMatch[0], "[" + newCharClass + "]");
                  needsFix = true;
                }
              }
            }
          }
        }

        if (needsFix) {
          input.setAttribute("pattern", fixedPattern);
          fixedCount++;
          log("Fixed invalid pattern on input:", {
            name: input.name,
            oldPattern: pattern,
            newPattern: fixedPattern
          });
        }
      }

      if (fixedCount > 0) {
        log("Fixed " + fixedCount + " invalid pattern(s) in form inputs");
      }
    } catch (e) {
      log("Error fixing patterns:", e);
    }
  }

  function getFieldValue(form, type) {
    var inputs = form.querySelectorAll("input, textarea");
    var field = null;

    log("Searching for " + type + " field in form with " + inputs.length + " inputs");

    // Also check textareas for email patterns
    if (type === "email") {
      var textareas = form.querySelectorAll("textarea");
      if (textareas.length > 0) {
        log("Also checking " + textareas.length + " textarea elements");
      }
    }

    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];

      // Skip hidden inputs
      if (input.type === "hidden") continue;

      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var inputMode = (input.getAttribute("inputmode") || "").toLowerCase();
      var name = (input.name || "").toLowerCase();
      var id = (input.id || "").toLowerCase();
      var placeholder = (input.placeholder || "").toLowerCase();
      var ariaLabel = (input.getAttribute("aria-label") || "").toLowerCase();
      var dataValidation = (input.getAttribute("data-parsley-type") || "").toLowerCase();
      var pattern = input.getAttribute("pattern") || "";

      // Check for Elementor-specific field attributes
      var fieldType = (input.getAttribute("data-field-type") || "").toLowerCase();
      var originalName = (input.getAttribute("data-original-name") || "").toLowerCase();
      var elementorFieldType = (input.getAttribute("data-field") || "").toLowerCase();

      // Also check for Elementor's standard naming pattern: form_fields[fieldname]
      var fieldNameMatch = name.match(/form_fields\[([^\]]+)\]/);
      var extractedFieldName = fieldNameMatch ? fieldNameMatch[1].toLowerCase() : "";

      // Get the label text if available
      var labelElement = form.querySelector('label[for="' + input.id + '"]');
      var labelText = labelElement ? (labelElement.textContent || "").toLowerCase() : "";

      // Also check parent container for field type clues
      var parentContainer = input.closest(".elementor-field-group");
      var parentLabel = parentContainer ? parentContainer.querySelector("label") : null;
      var parentLabelText = parentLabel ? (parentLabel.textContent || "").toLowerCase() : "";

      log("Checking input " + i + ":", {
        type: inputType,
        name: name,
        id: id,
        fieldType: fieldType,
        originalName: originalName,
        elementorFieldType: elementorFieldType,
        extractedFieldName: extractedFieldName,
        placeholder: placeholder,
        labelText: labelText || parentLabelText,
        value: input.value,
        tagName: input.tagName
      });

      if (type === "email") {
        if (
          inputType === "email" ||
          inputMode === "email" ||
          dataValidation === "email" ||
          fieldType === "email" ||
          elementorFieldType === "email" ||
          extractedFieldName === "email" ||
          name.indexOf("email") > -1 ||
          name.indexOf("e-mail") > -1 ||
          name === "form_fields[email]" ||
          originalName === "email" ||
          id.indexOf("email") > -1 ||
          id.indexOf("e-mail") > -1 ||
          placeholder.indexOf("email") > -1 ||
          placeholder.indexOf("e-mail") > -1 ||
          placeholder.indexOf("@") > -1 ||
          ariaLabel.indexOf("email") > -1 ||
          labelText.indexOf("email") > -1 ||
          labelText.indexOf("e-mail") > -1 ||
          parentLabelText.indexOf("email") > -1 ||
          parentLabelText.indexOf("e-mail") > -1 ||
          (pattern && pattern.indexOf("@") > -1)
        ) {
          field = input;
          log("Found email field:", input);
          break;
        }
      } else if (type === "phone") {
        // Check if pattern contains numbers but safely
        var hasNumberPattern = false;
        try {
          hasNumberPattern = pattern && (
            pattern.indexOf("0-9") > -1 ||
            pattern.indexOf("\\d") > -1 ||
            pattern.indexOf("[0-9") > -1
          );
        } catch (e) {
          // Pattern check failed, continue without it
        }

        if (
          inputType === "tel" ||
          inputMode === "tel" ||
          inputMode === "numeric" ||
          dataValidation === "phone" ||
          dataValidation === "number" ||
          fieldType === "tel" ||
          fieldType === "phone" ||
          elementorFieldType === "tel" ||
          elementorFieldType === "phone" ||
          extractedFieldName === "phone" ||
          extractedFieldName === "tel" ||
          name.indexOf("phone") > -1 ||
          name.indexOf("tel") > -1 ||
          name.indexOf("mobile") > -1 ||
          name.indexOf("number") > -1 ||
          name === "form_fields[phone]" ||
          name === "form_fields[tel]" ||
          originalName === "phone" ||
          originalName === "tel" ||
          id.indexOf("phone") > -1 ||
          id.indexOf("tel") > -1 ||
          id.indexOf("mobile") > -1 ||
          id.indexOf("number") > -1 ||
          placeholder.indexOf("phone") > -1 ||
          placeholder.indexOf("mobile") > -1 ||
          placeholder.indexOf("number") > -1 ||
          placeholder.indexOf("(") > -1 ||
          ariaLabel.indexOf("phone") > -1 ||
          ariaLabel.indexOf("mobile") > -1 ||
          labelText.indexOf("phone") > -1 ||
          labelText.indexOf("mobile") > -1 ||
          labelText.indexOf("number") > -1 ||
          parentLabelText.indexOf("phone") > -1 ||
          parentLabelText.indexOf("mobile") > -1 ||
          parentLabelText.indexOf("number") > -1 ||
          hasNumberPattern
        ) {
          field = input;
          log("Found phone field:", input);
          break;
        }
      }
    }

    if (!field) {
      log("No " + type + " field found in form - dumping all input details:");
      for (var j = 0; j < Math.min(inputs.length, 10); j++) {
        if (inputs[j].type !== "hidden") {
          log("Input " + j + ":", {
            type: inputs[j].type,
            name: inputs[j].name,
            id: inputs[j].id,
            placeholder: inputs[j].placeholder
          });
        }
      }
      return "";
    }

    var value = (field.value || "").trim();
    log("Field value for " + type + ":", value);

    if (type === "phone" && value) {
      var cleanedValue = value.replace(/(?!^\+)[^\d]/g, "");
      log("Cleaned phone value:", cleanedValue);
      return cleanedValue;
    }
    return value;
  }

  function getGA4StandardParams() {
    return {
      page_location: window.location.href,
      page_referrer: document.referrer || "",
      page_title: document.title || "",
      language: navigator.language || navigator.userLanguage || "",
      screen_resolution: screen.width + "x" + screen.height,
      engagement_time_msec: Math.max(
        0,
        Date.now() - (window.cuftPageLoadTime || Date.now())
      ),
    };
  }

  function fireGenerateLeadEvent(basePayload, email, phone) {
    // Check if generate_lead is enabled
    if (!window.cuftElementor || !window.cuftElementor.generate_lead_enabled) {
      log("Generate lead skipped - not enabled");
      return;
    }

    // Check for required fields: click_id, email, and phone
    var hasClickId = basePayload.click_id || basePayload.gclid || basePayload.fbclid ||
                     basePayload.msclkid || basePayload.ttclid || basePayload.li_fat_id ||
                     basePayload.twclid || basePayload.snap_click_id || basePayload.pclid;

    if (!email || !phone || !hasClickId) {
      log("Generate lead skipped - missing required fields (email, phone, or click_id)");
      log("Has email:", !!email, "Has phone:", !!phone, "Has click_id:", !!hasClickId);
      return;
    }

    var leadPayload = {
      event: "generate_lead",
      currency: "USD", // Default currency, could be configurable
      value: 0, // Default value, could be configurable
      cuft_tracked: true,
      cuft_source: basePayload.cuft_source + "_lead",
    };

    // Copy relevant data from base payload
    var copyFields = [
      "page_location",
      "page_referrer",
      "page_title",
      "language",
      "screen_resolution",
      "engagement_time_msec",
      "utm_source",
      "utm_medium",
      "utm_campaign",
      "utm_term",
      "utm_content",
      "formType",
      "formId",
      "formName",
      "click_id",
      "gclid",
      "fbclid",
      "msclkid",
      "ttclid",
      "li_fat_id",
      "twclid",
      "snap_click_id",
      "pclid",
      "user_email",
      "user_phone"
    ];

    for (var i = 0; i < copyFields.length; i++) {
      var field = copyFields[i];
      if (basePayload[field]) {
        leadPayload[field] = basePayload[field];
      }
    }

    // Add email and phone to lead payload
    if (email) leadPayload.user_email = email;
    if (phone) leadPayload.user_phone = phone;

    leadPayload.submittedAt = new Date().toISOString();

    try {
      getDL().push(leadPayload);
      log("Generate lead event fired:", leadPayload);
    } catch (e) {
      log("Generate lead push error:", e);
    }
  }

  function pushToDataLayer(form, email, phone) {
    // Try multiple methods to get form ID and name
    var formIdInput = form.querySelector("input[name='form_id']");
    var formNameInput = form.querySelector("input[name='form_name']");
    var elementorWidget = form.closest(".elementor-widget");

    var formId =
      form.getAttribute("data-form-id") ||
      form.getAttribute("id") ||
      form.getAttribute("data-elementor-form-id") ||
      (formIdInput ? formIdInput.value : null) ||
      (formNameInput ? formNameInput.value : null) ||
      null;

    var formName =
      form.getAttribute("data-form-name") ||
      form.getAttribute("name") ||
      form.getAttribute("aria-label") ||
      (formNameInput ? formNameInput.value : null) ||
      (elementorWidget ? elementorWidget.getAttribute("data-widget_type") : null) ||
      null;

    // Log debugging info about form identification
    log("Form identification debug:", {
      formElement: form,
      form_id: formId,
      form_name: formName,
      dataFormId: form.getAttribute("data-form-id"),
      id: form.getAttribute("id"),
      name: form.getAttribute("name"),
      ariaLabel: form.getAttribute("aria-label")
    });

    var payload = {
      event: "form_submit",
      formType: "elementor",
      form_id: formId,
      form_name: formName,
      submittedAt: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "elementor_pro",
    };

    // Add GA4 standard parameters
    var ga4Params = getGA4StandardParams();
    for (var key in ga4Params) {
      if (ga4Params[key]) payload[key] = ga4Params[key];
    }

    if (email) payload.user_email = email;
    if (phone) payload.user_phone = phone;

    // Add UTM data if available
    if (window.cuftUtmUtils) {
      payload = window.cuftUtmUtils.addUtmToPayload(payload);
    }

    try {
      getDL().push(payload);
      log("Form submission tracked:", payload);

      // Fire generate_lead event if conditions are met
      fireGenerateLeadEvent(payload, email, phone);
    } catch (e) {
      log("DataLayer push error:", e);
    }
  }

  function handleElementorSuccess(event, form) {
    try {
      // Handle both jQuery event and direct form reference
      var targetForm = form || (event && event.target && event.target.closest(".elementor-form"));
      if (!targetForm) {
        log("No form found in success handler");
        return;
      }

      // Check if this form has already been tracked to prevent duplicates
      var trackingId = targetForm.getAttribute("data-cuft-tracking-id");
      if (trackingId) {
        var now = Date.now();
        var trackingTime = parseInt(trackingId, 10);

        // If tracked within the last 5 seconds, skip to prevent duplicate
        if (now - trackingTime < 5000) {
          log("Form already tracked recently, skipping duplicate");
          return;
        }
      }

      // Mark form as tracked with timestamp
      targetForm.setAttribute("data-cuft-tracking-id", Date.now().toString());

      // Try to get stored values first (captured at submit time)
      var email = targetForm.getAttribute("data-cuft-email") || "";
      var phone = targetForm.getAttribute("data-cuft-phone") || "";

      // If not found, try to get current values
      if (!email) email = getFieldValue(targetForm, "email");
      if (!phone) phone = getFieldValue(targetForm, "phone");

      log("Processing success event with values:", {
        email: email || "not found",
        phone: phone || "not found"
      });

      pushToDataLayer(targetForm, email, phone);

      // Clean up stored attributes but keep tracking ID
      targetForm.removeAttribute("data-cuft-email");
      targetForm.removeAttribute("data-cuft-phone");
    } catch (e) {
      log("Success handler error:", e);
    }
  }

  function handleFormSubmit(event) {
    try {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return;

      // Check if this is an Elementor form
      var isElementorForm =
        form.classList.contains("elementor-form") ||
        form.closest(".elementor-form");

      if (!isElementorForm) return;

      // Capture field values before submission
      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      // Store form reference and field values for later use
      form.setAttribute("data-cuft-tracking", "pending");
      form.setAttribute("data-cuft-email", email || "");
      form.setAttribute("data-cuft-phone", phone || "");

      log("Elementor form submit detected, captured values:", {
        email: email || "not found",
        phone: phone || "not found"
      });
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  /**
   * Setup simplified event listeners focusing on native Elementor methods
   */
  function setupEventListeners() {
    var listenersSetup = [];

    // Primary: Native JavaScript CustomEvent listener (Elementor 3.5+)
    try {
      document.addEventListener("submit_success", function (event) {
        log("Native submit_success event detected", event.detail);
        handleNativeSuccessEvent(event, event.detail);
      });
      listenersSetup.push("submit_success listener (native)");
    } catch (e) {
      log("Could not add native submit_success listener:", e);
    }

    // Fallback: jQuery event listeners (for older Elementor versions)
    if (window.jQuery) {
      try {
        window.jQuery(document).on("submit_success", function (event, response) {
          log("jQuery submit_success event detected", response);
          handleJQuerySuccessEvent(event, response);
        });
        listenersSetup.push("submit_success listener (jQuery)");
      } catch (e) {
        log("jQuery listener setup error:", e);
      }
    }

    // Minimal fallback for edge cases only
    if (window.cuftElementor && !window.cuftElementor.use_native_methods) {
      try {
        setupLegacyFallbacks();
        listenersSetup.push("legacy fallback methods");
      } catch (e) {
        log("Could not setup legacy fallbacks:", e);
      }
    }

    log("Simplified event listeners setup complete:", listenersSetup);
  }

  /**
   * Handle native submit_success event (Elementor 3.5+)
   */
  function handleNativeSuccessEvent(event, detail) {
    try {
      var form = findFormFromEvent(event);
      if (!form) {
        log("Could not find form for native success event");
        return;
      }

      // Check for tracking data in response
      var trackingData = detail && detail.cuft_tracking ? detail.cuft_tracking : null;

      if (trackingData) {
        log("Using server-provided tracking data:", trackingData);
        pushToDataLayerWithData(form, trackingData);
      } else {
        log("No server tracking data, using client-side detection");
        handleClientSideTracking(form);
      }
    } catch (e) {
      log("Native success handler error:", e);
    }
  }

  /**
   * Handle jQuery submit_success event (legacy support)
   */
  function handleJQuerySuccessEvent(event, response) {
    try {
      var form = findFormFromEvent(event);
      if (!form) {
        log("Could not find form for jQuery success event");
        return;
      }

      // Check for tracking data in response
      var trackingData = response && response.cuft_tracking ? response.cuft_tracking : null;

      if (trackingData) {
        log("Using server-provided tracking data:", trackingData);
        pushToDataLayerWithData(form, trackingData);
      } else {
        log("No server tracking data, using client-side detection");
        handleClientSideTracking(form);
      }
    } catch (e) {
      log("jQuery success handler error:", e);
    }
  }

  /**
   * Find form element from event
   */
  function findFormFromEvent(event) {
    var form = null;

    // Try to get form from event target
    if (event && event.target) {
      form = event.target.closest(".elementor-form") ||
             event.target.querySelector(".elementor-form");
    }

    // Try to find most recently submitted form
    if (!form) {
      var recentForms = document.querySelectorAll('.elementor-form[data-cuft-tracking-id]');
      if (recentForms.length > 0) {
        // Get the most recently marked form
        var mostRecent = null;
        var latestTime = 0;

        for (var i = 0; i < recentForms.length; i++) {
          var time = parseInt(recentForms[i].getAttribute('data-cuft-tracking-id'), 10);
          if (time > latestTime) {
            latestTime = time;
            mostRecent = recentForms[i];
          }
        }
        form = mostRecent;
      }
    }

    return form;
  }

  /**
   * Push to dataLayer using server-provided tracking data
   */
  function pushToDataLayerWithData(form, trackingData) {
    // Prevent duplicate tracking
    if (isDuplicateTracking(form)) {
      log("Skipping duplicate tracking for form");
      return;
    }

    // Mark form as tracked
    markFormAsTracked(form);

    var payload = {
      event: "form_submit",
      formType: "elementor",
      form_id: trackingData.form_id || null,
      form_name: trackingData.form_name || null,
      submittedAt: trackingData.timestamp || new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: trackingData.source || "elementor_pro_native"
    };

    // Add GA4 standard parameters
    var ga4Params = getGA4StandardParams();
    for (var key in ga4Params) {
      if (ga4Params[key]) payload[key] = ga4Params[key];
    }

    // Add user data if available
    if (trackingData.user_email) payload.user_email = trackingData.user_email;
    if (trackingData.user_phone) payload.user_phone = trackingData.user_phone;

    // Add UTM data if available
    if (window.cuftUtmUtils) {
      payload = window.cuftUtmUtils.addUtmToPayload(payload);
    }

    try {
      getDL().push(payload);
      log("Form submission tracked with server data:", payload);

      // Fire generate_lead event if conditions are met
      fireGenerateLeadEvent(payload, trackingData.user_email, trackingData.user_phone);
    } catch (e) {
      log("DataLayer push error:", e);
    }
  }

  /**
   * Handle client-side tracking when no server data available
   */
  function handleClientSideTracking(form) {
    // Prevent duplicate tracking
    if (isDuplicateTracking(form)) {
      log("Skipping duplicate client-side tracking for form");
      return;
    }

    // Mark form as tracked
    markFormAsTracked(form);

    // Get field values using improved detection
    var email = getFieldValueImproved(form, "email");
    var phone = getFieldValueImproved(form, "phone");

    log("Client-side tracking with values:", {
      email: email || "not found",
      phone: phone || "not found"
    });

    pushToDataLayer(form, email, phone);
  }

  /**
   * Check if form was recently tracked to prevent duplicates
   */
  function isDuplicateTracking(form) {
    var trackingId = form.getAttribute("data-cuft-tracking-id");
    if (trackingId) {
      var now = Date.now();
      var trackingTime = parseInt(trackingId, 10);

      // If tracked within the last 5 seconds, skip to prevent duplicate
      if (now - trackingTime < 5000) {
        return true;
      }
    }
    return false;
  }

  /**
   * Mark form as tracked with timestamp
   */
  function markFormAsTracked(form) {
    form.setAttribute("data-cuft-tracking-id", Date.now().toString());
  }

  /**
   * Improved field value detection using native selectors
   */
  function getFieldValueImproved(form, type) {
    var selectors = window.cuftElementor && window.cuftElementor.field_selectors ?
                   window.cuftElementor.field_selectors[type] : null;

    if (selectors) {
      var field = form.querySelector(selectors);
      if (field && field.value) {
        log("Found " + type + " field using native selector:", field);
        return field.value.trim();
      }
    }

    // Fallback to original method
    return getFieldValue(form, type);
  }

  /**
   * Setup legacy fallback methods (only when needed)
   */
  function setupLegacyFallbacks() {
    // Keep minimal fallbacks for edge cases
    setupMutationObserver();
    log("Legacy fallback methods enabled");
  }

  /**
   * Handle success event from any source (legacy)
   */
  function handleSuccessEvent(event, response) {
    try {
      // Find the form that triggered the event
      var form = null;

      // Try to get form from event target
      if (event && event.target) {
        form = event.target.closest(".elementor-form") || event.target;
      }

      // Try to find form with pending tracking
      if (!form) {
        var pendingForms = document.querySelectorAll(
          '.elementor-form[data-cuft-tracking="pending"]'
        );
        if (pendingForms.length > 0) {
          form = pendingForms[0];
          log("Found form with pending tracking");
        }
      }

      // Try to find any visible Elementor form
      if (!form) {
        var allForms = document.querySelectorAll(".elementor-form");
        for (var i = 0; i < allForms.length; i++) {
          var f = allForms[i];
          // Check if form is visible and has been recently interacted with
          if (f.offsetParent !== null) {
            var inputs = f.querySelectorAll("input, textarea");
            for (var j = 0; j < inputs.length; j++) {
              if (inputs[j].value && inputs[j].value.trim()) {
                form = f;
                break;
              }
            }
          }
          if (form) break;
        }
      }

      if (form) {
        // Log if this form has stored values
        var storedEmail = form.getAttribute("data-cuft-email");
        var storedPhone = form.getAttribute("data-cuft-phone");

        if (storedEmail || storedPhone) {
          log("Using stored form values from submit time:", {
            email: storedEmail || "not stored",
            phone: storedPhone || "not stored"
          });
        }

        handleElementorSuccess(event, form);
        // Clear tracking attribute
        if (form.hasAttribute("data-cuft-tracking")) {
          form.removeAttribute("data-cuft-tracking");
        }
      } else {
        log("Warning: Could not identify form for success event");
      }
    } catch (e) {
      log("Error in handleSuccessEvent:", e);
    }
  }

  /**
   * Setup MutationObserver to detect form success messages
   */
  function setupMutationObserver() {
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        // Look for success message elements
        if (mutation.type === "childList") {
          mutation.addedNodes.forEach(function (node) {
            if (node.nodeType === 1) {
              // Element node

              // Check if new forms were added - fix patterns if so
              if (node.classList && node.classList.contains("elementor-form")) {
                log("New Elementor form detected, fixing patterns");
                fixInvalidPatterns();
              } else if (node.querySelector && node.querySelector(".elementor-form")) {
                log("Container with Elementor form detected, fixing patterns");
                fixInvalidPatterns();
              }

              // Check for Elementor success message classes
              if (
                node.classList &&
                (node.classList.contains("elementor-message-success") ||
                  node.classList.contains("elementor-form-success-message"))
              ) {
                log("Success message detected via MutationObserver");
                // Find the associated form
                var form = node.closest(".elementor-form");
                if (!form && node.parentElement) {
                  form = node.parentElement.querySelector(".elementor-form");
                }
                if (form) {
                  handleElementorSuccess(null, form);
                }
              }
            }
          });
        }
      });
    });

    // Start observing
    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  /**
   * Setup Ajax interceptor for form submissions
   */
  function setupAjaxInterceptor() {
    // Intercept native fetch if available
    if (window.fetch) {
      var originalFetch = window.fetch;
      window.fetch = function () {
        var args = arguments;
        var url = args[0];
        var options = args[1] || {};

        // Check if this might be an Elementor form submission
        if (
          typeof url === "string" &&
          url.indexOf("admin-ajax.php") > -1 &&
          options.body
        ) {
          var bodyStr = "";
          if (typeof options.body === "string") {
            bodyStr = options.body;
          } else if (options.body instanceof FormData) {
            try {
              // Try to check FormData for Elementor action
              var action = options.body.get("action");
              if (action === "elementor_pro_forms_send_form") {
                log("Elementor form submission detected via fetch");
              }
            } catch (e) {}
          }

          return originalFetch.apply(this, args).then(function (response) {
            // Clone response to read it
            var clonedResponse = response.clone();
            clonedResponse.json().then(function (data) {
              if (
                data &&
                data.success &&
                bodyStr.indexOf("elementor_pro_forms_send_form") > -1
              ) {
                log("Elementor form success detected via fetch interceptor");
                // Try to find the form
                var forms = document.querySelectorAll(
                  '.elementor-form[data-cuft-tracking="pending"]'
                );
                if (forms.length > 0) {
                  handleElementorSuccess(null, forms[0]);
                }
              }
            }).catch(function() {});
            return response;
          });
        }

        return originalFetch.apply(this, args);
      };
    }

    // Also intercept XMLHttpRequest
    var originalXHR = window.XMLHttpRequest.prototype.open;
    window.XMLHttpRequest.prototype.open = function () {
      var xhr = this;
      var url = arguments[1];
      var originalOnReadyStateChange = xhr.onreadystatechange;

      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
          try {
            if (
              url.indexOf("admin-ajax.php") > -1 &&
              xhr.responseText &&
              xhr.responseText.indexOf('"success":true') > -1
            ) {
              // Check if this was an Elementor form
              if (
                xhr.responseText.indexOf("elementor") > -1 ||
                (xhr._requestBody &&
                  xhr._requestBody.indexOf("elementor_pro_forms_send_form") > -1)
              ) {
                log("Elementor form success detected via XHR interceptor");
                var forms = document.querySelectorAll(
                  '.elementor-form[data-cuft-tracking="pending"]'
                );
                if (forms.length > 0) {
                  handleElementorSuccess(null, forms[0]);
                }
              }
            }
          } catch (e) {}
        }
        if (originalOnReadyStateChange) {
          originalOnReadyStateChange.apply(this, arguments);
        }
      };

      // Store request body for later inspection
      var originalSend = xhr.send;
      xhr.send = function (data) {
        xhr._requestBody = data;
        originalSend.apply(xhr, arguments);
      };

      return originalXHR.apply(this, arguments);
    };
  }

  ready(function () {
    // Fix any invalid regex patterns before setting up event listeners
    fixInvalidPatterns();

    // Setup the new simplified event listeners
    setupEventListeners();

    // Legacy jQuery Ajax monitoring (only if native methods disabled)
    if (!window.cuftElementor || !window.cuftElementor.use_native_methods) {
      setupLegacyAjaxMonitoring();
    }

    log("Elementor forms tracking initialized with native methods");
  });

  /**
   * Setup legacy Ajax monitoring for backward compatibility
   */
  function setupLegacyAjaxMonitoring() {
    if (window.jQuery && window.jQuery.ajaxSetup) {
      try {
        window.jQuery(document).ajaxComplete(function (event, xhr, settings) {
          try {
            // Check if this is an Elementor form submission
            var dataStr = "";
            if (settings && settings.data) {
              if (typeof settings.data === "string") {
                dataStr = settings.data;
              } else if (settings.data instanceof FormData) {
                // Can't easily convert FormData to string, so skip this check
                return;
              } else if (typeof settings.data === "object") {
                // Try to serialize object to string for checking
                try {
                  dataStr = JSON.stringify(settings.data) || "";
                } catch (e) {
                  dataStr = "";
                }
              }
            }

            if (
              settings &&
              settings.url &&
              settings.url.indexOf("admin-ajax.php") > -1 &&
              dataStr &&
              dataStr.indexOf("action=elementor_pro_forms_send_form") > -1
            ) {
              // Parse response
              var response = xhr.responseJSON || {};
              if (response.success) {
                log("Elementor form success via jQuery.ajaxComplete");
                handleSuccessEvent(event, response);
              }
            }
          } catch (e) {
            log("jQuery ajaxComplete handler error:", e);
          }
        });
        log("jQuery.ajaxComplete listener added");
      } catch (e) {
        log("Could not setup jQuery.ajaxComplete:", e);
      }
    }
  }
})();
