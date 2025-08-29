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

  function getFieldValue(form, type) {
    var selector =
      type === "email" ? 'input[type="email"]' : 'input[type="tel"]';
    var field = form.querySelector(selector);

    if (!field) {
      // Fallback: search by field name/id
      var inputs = form.querySelectorAll("input");
      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var name = (input.name || "").toLowerCase();
        var id = (input.id || "").toLowerCase();

        if (
          type === "email" &&
          (name.indexOf("email") > -1 || id.indexOf("email") > -1)
        ) {
          field = input;
          break;
        } else if (
          type === "phone" &&
          (name.indexOf("phone") > -1 ||
            name.indexOf("tel") > -1 ||
            id.indexOf("phone") > -1 ||
            id.indexOf("tel") > -1)
        ) {
          field = input;
          break;
        }
      }
    }

    if (!field) return "";

    var value = (field.value || "").trim();
    if (type === "phone" && value) {
      return value.replace(/(?!^\+)[^\d]/g, "");
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

  function fireGenerateLeadEvent(basePayload, email) {
    // Check if generate_lead is enabled and we have the required data
    if (!window.cuftElementor || !window.cuftElementor.generate_lead_enabled) {
      return;
    }

    if (!email || !basePayload.utm_campaign) {
      log("Generate lead skipped - missing email or utm_campaign");
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
    ];

    for (var i = 0; i < copyFields.length; i++) {
      var field = copyFields[i];
      if (basePayload[field]) {
        leadPayload[field] = basePayload[field];
      }
    }

    leadPayload.submittedAt = new Date().toISOString();

    try {
      getDL().push(leadPayload);
      log("Generate lead event fired:", leadPayload);
    } catch (e) {
      log("Generate lead push error:", e);
    }
  }

  function pushToDataLayer(form, email, phone) {
    var formId =
      form.getAttribute("data-form-id") || form.getAttribute("id") || null;
    var formName = form.getAttribute("data-form-name") || null;

    var payload = {
      event: "form_submit",
      formType: "elementor",
      formId: formId,
      formName: formName,
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
      fireGenerateLeadEvent(payload, email);
    } catch (e) {
      log("DataLayer push error:", e);
    }
  }

  function handleElementorSuccess(event) {
    try {
      var form = event.target.closest(".elementor-form");
      if (!form) return;

      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      pushToDataLayer(form, email, phone);
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

      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      // For Elementor, we'll track on submit since it handles success via events
      setTimeout(function () {
        pushToDataLayer(form, email, phone);
      }, 500);

      log("Elementor form submit tracked");
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  ready(function () {
    // Listen for Elementor's custom success events
    document.addEventListener("submit", handleFormSubmit, true);

    // Also listen for Elementor-specific success events if available
    if (window.jQuery) {
      window
        .jQuery(document)
        .on("elementor/popup/hide", handleElementorSuccess);
    }

    log("Elementor forms tracking initialized");
  });
})();
