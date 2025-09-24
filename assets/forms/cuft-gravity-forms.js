(function () {
  "use strict";

  var DEBUG = !!(window.cuftGravity && window.cuftGravity.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Gravity]"].concat(Array.prototype.slice.call(arguments))
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
    var fields = form.querySelectorAll(".gfield");

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      var input = field.querySelector("input");
      if (!input) continue;

      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var classes = field.className || "";
      var value = (input.value || "").trim();

      if (type === "email") {
        if (inputType === "email" || classes.indexOf("gfield_email") > -1) {
          return value;
        }
      } else if (type === "phone") {
        if (inputType === "tel" || classes.indexOf("gfield_phone") > -1) {
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
    if (!window.cuftGravity || !window.cuftGravity.generate_lead_enabled) {
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
    var formId = form.getAttribute("data-formid") || form.getAttribute("id");
    if (formId && formId.indexOf("gform_") === 0) {
      formId = formId.replace("gform_", "");
    }

    var payload = {
      event: "form_submit",
      formType: "gravity_forms",
      form_id: formId,
      form_name: null, // GF doesn't typically expose form names in frontend
      submittedAt: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "gravity_forms",
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
      fireGenerateLeadEvent(payload, email);
    } catch (e) {
      log("DataLayer push error:", e);
    }
  }

  function isSuccessState(form) {
    // Check for confirmation message
    var confirmDiv = form.querySelector(".gform_confirmation_message");
    if (confirmDiv && confirmDiv.style.display !== "none") {
      return true;
    }

    // Check if form is hidden after success
    if (form.style.display === "none" || !form.offsetParent) {
      var wrapper = form.closest(".gform_wrapper");
      if (wrapper && wrapper.querySelector(".gform_confirmation_message")) {
        return true;
      }
    }

    return false;
  }

  function observeSuccess(form, email, phone) {
    var pushed = false;
    var cleanup = function () {};

    function tryPush() {
      if (!pushed && isSuccessState(form)) {
        pushed = true;
        pushToDataLayer(form, email, phone);
        cleanup();
      }
    }

    // Set up observers
    var timeouts = [
      setTimeout(tryPush, 500),
      setTimeout(tryPush, 1500),
      setTimeout(tryPush, 3000),
    ];

    var stopTimeout = setTimeout(function () {
      if (!pushed) cleanup();
    }, 8000);

    cleanup = function () {
      timeouts.forEach(function (t) {
        clearTimeout(t);
      });
      clearTimeout(stopTimeout);
    };

    // Mutation observer
    if (window.MutationObserver) {
      var observer = new MutationObserver(tryPush);
      var wrapper = form.closest(".gform_wrapper") || form;
      observer.observe(wrapper, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["style", "class"],
      });

      var originalCleanup = cleanup;
      cleanup = function () {
        observer.disconnect();
        originalCleanup();
      };
    }
  }

  function handleFormSubmit(event) {
    try {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return;

      // Check if this is a Gravity Forms form
      var isGravityForm =
        form.classList.contains("gform_form") ||
        (form.id && form.id.indexOf("gform_") === 0) ||
        form.closest(".gform_wrapper");

      if (!isGravityForm) return;

      if (form.hasAttribute("data-cuft-observing")) return;
      form.setAttribute("data-cuft-observing", "true");

      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      observeSuccess(form, email, phone);
      log("Gravity form submit tracked");
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  ready(function () {
    document.addEventListener("submit", handleFormSubmit, true);

    // Listen for Gravity Forms AJAX events if available
    if (window.jQuery) {
      window
        .jQuery(document)
        .on("gform_confirmation_loaded", function (event, formId) {
          log("Gravity Forms confirmation loaded for form:", formId);
        });
    }

    log("Gravity Forms tracking initialized");
  });
})();
