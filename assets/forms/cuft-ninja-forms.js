(function () {
  "use strict";

  var DEBUG = !!(window.cuftNinja && window.cuftNinja.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Ninja]"].concat(Array.prototype.slice.call(arguments))
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
    var fields = form.querySelectorAll(".nf-field");

    for (var i = 0; i < fields.length; i++) {
      var field = fields[i];
      var input = field.querySelector("input");
      if (!input) continue;

      var fieldType = field.getAttribute("data-field-type") || "";
      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var inputMode = (input.getAttribute("inputmode") || "").toLowerCase();
      var name = (input.name || "").toLowerCase();
      var id = (input.id || "").toLowerCase();
      var placeholder = (input.placeholder || "").toLowerCase();
      var label = field.querySelector("label");
      var labelText = label ? (label.textContent || "").toLowerCase() : "";
      var value = (input.value || "").trim();

      if (type === "email") {
        var pattern = input.getAttribute("pattern") || "";
        if (
          fieldType === "email" ||
          inputType === "email" ||
          inputMode === "email" ||
          name.indexOf("email") > -1 ||
          id.indexOf("email") > -1 ||
          placeholder.indexOf("email") > -1 ||
          labelText.indexOf("email") > -1 ||
          (pattern && pattern.indexOf("@") > -1)
        ) {
          return value;
        }
      } else if (type === "phone") {
        // Check if pattern contains numbers but safely
        var pattern = input.getAttribute("pattern") || "";
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
          fieldType === "phone" ||
          inputType === "tel" ||
          inputMode === "tel" ||
          inputMode === "numeric" ||
          name.indexOf("phone") > -1 ||
          name.indexOf("tel") > -1 ||
          name.indexOf("mobile") > -1 ||
          id.indexOf("phone") > -1 ||
          id.indexOf("tel") > -1 ||
          id.indexOf("mobile") > -1 ||
          placeholder.indexOf("phone") > -1 ||
          placeholder.indexOf("mobile") > -1 ||
          labelText.indexOf("phone") > -1 ||
          labelText.indexOf("mobile") > -1 ||
          hasNumberPattern
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

  function fireGenerateLeadEvent(basePayload, email, phone) {
    if (!window.cuftNinja || !window.cuftNinja.generate_lead_enabled) {
      log("Generate lead skipped - not enabled");
      return;
    }

    // Check for any click ID parameter
    var clickIdParams = [
      "click_id",
      "gclid",
      "gbraid",
      "wbraid",
      "fbclid",
      "msclkid",
      "ttclid",
      "li_fat_id",
      "twclid",
      "snap_click_id",
      "pclid"
    ];

    var hasClickId = false;
    for (var i = 0; i < clickIdParams.length; i++) {
      if (basePayload[clickIdParams[i]]) {
        hasClickId = true;
        break;
      }
    }

    if (!email || !phone || !hasClickId) {
      log("Generate lead skipped - missing required fields (email, phone, or click_id)");
      log("Has email:", !!email, "Has phone:", !!phone, "Has click_id:", !!hasClickId);
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
      "form_type",
      "form_id",
      "form_name",
      "click_id",
      "gclid",
      "gbraid",
      "wbraid",
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

    leadPayload.submitted_at = new Date().toISOString();

    try {
      getDL().push(leadPayload);
      log("Generate lead event fired:", leadPayload);
    } catch (e) {
      log("Generate lead push error:", e);
    }
  }

  function pushToDataLayer(form, email, phone) {
    var formId = form.getAttribute("data-form-id") || form.getAttribute("id");

    var payload = {
      event: "form_submit",
      form_type: "ninja_forms",
      form_id: formId,
      form_name: null, // Ninja Forms doesn't typically expose form names in frontend
      submitted_at: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "ninja_forms",
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
      fireGenerateLeadEvent(payload, email, phone);
    } catch (e) {
      log("DataLayer push error:", e);
    }
  }

  function isSuccessState(form) {
    // Check for success message
    var successMsg = form.querySelector(".nf-response-msg");
    if (successMsg && successMsg.style.display !== "none") {
      return true;
    }

    // Check if form is hidden (typical after success)
    if (form.style.display === "none" || !form.offsetParent) {
      var parent = form.parentNode;
      if (parent && parent.querySelector(".nf-response-msg")) {
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
      observer.observe(form, {
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

      // Check if this is a Ninja Forms form
      var isNinjaForm =
        form.classList.contains("nf-form-cont") ||
        form.querySelector(".nf-field") ||
        form.closest(".nf-form-wrap");

      if (!isNinjaForm) return;

      if (form.hasAttribute("data-cuft-observing")) return;
      form.setAttribute("data-cuft-observing", "true");

      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      observeSuccess(form, email, phone);
      log("Ninja form submit tracked for:", form.getAttribute("data-form-id") || form.getAttribute("id") || "unknown");
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  ready(function () {
    document.addEventListener("submit", handleFormSubmit, true);

    // Also listen for Ninja Forms specific events if available
    if (window.Marionette && window.nfRadio) {
      window.nfRadio
        .channel("forms")
        .on("submit:response", function (response) {
          log("Ninja Forms response received:", response);
        });
    }

    log("Ninja Forms tracking initialized");
  });
})();
