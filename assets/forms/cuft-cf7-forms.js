(function () {
  "use strict";

  var DEBUG = !!(window.cuftCF7 && window.cuftCF7.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT CF7]"].concat(Array.prototype.slice.call(arguments))
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
    var inputs = form.querySelectorAll("input");

    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var name = (input.name || "").toLowerCase();
      var value = (input.value || "").trim();

      if (type === "email") {
        if (inputType === "email" || name.indexOf("email") > -1) {
          return value;
        }
        // Check if value looks like an email
        if (value && /@/.test(value)) {
          return value;
        }
      } else if (type === "phone") {
        if (
          inputType === "tel" ||
          name.indexOf("tel") > -1 ||
          name.indexOf("phone") > -1
        ) {
          return value ? value.replace(/(?!^\+)[^\d]/g, "") : "";
        }
      }
    }

    return "";
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
    if (!window.cuftCF7 || !window.cuftCF7.generate_lead_enabled) {
      return;
    }

    if (!email || !basePayload.utm_campaign) {
      log("Generate lead skipped - missing email or utm_campaign");
      return;
    }

    var leadPayload = {
      event: "generate_lead",
      currency: "USD",
      value: 0,
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
      "form_type",
      "form_id",
      "form_name",
    ];

    for (var i = 0; i < copyFields.length; i++) {
      var field = copyFields[i];
      if (basePayload[field]) {
        leadPayload[field] = basePayload[field];
      }
    }

    leadPayload.submitted_at = new Date().toISOString();

    try {
      getDL().push(leadPayload);
      log("Generate lead event fired:", leadPayload);
    } catch (e) {
      log("Generate lead push error:", e);
    }
  }

  function pushToDataLayer(form, email, phone) {
    var formWrapper = form.closest(".wpcf7");
    var formId = formWrapper ? formWrapper.getAttribute("id") : null;

    var payload = {
      event: "form_submit",
      form_type: "contact_form_7",
      form_id: formId,
      form_name: null, // CF7 doesn't typically expose form names in frontend
      submitted_at: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "contact_form_7",
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

  function handleCF7Events(event) {
    try {
      var form = event.target;
      var eventType = event.type;

      // Only track on successful submissions
      if (eventType === "wpcf7mailsent") {
        var email = getFieldValue(form, "email");
        var phone = getFieldValue(form, "phone");

        pushToDataLayer(form, email, phone);
        log("CF7 mail sent event tracked");
      }
    } catch (e) {
      log("CF7 event handler error:", e);
    }
  }

  ready(function () {
    // Listen for CF7's custom events
    document.addEventListener("wpcf7mailsent", handleCF7Events, false);
    document.addEventListener(
      "wpcf7mailfailed",
      function (event) {
        log("CF7 mail failed for form:", event.target);
      },
      false
    );

    log("Contact Form 7 tracking initialized");
  });
})();
