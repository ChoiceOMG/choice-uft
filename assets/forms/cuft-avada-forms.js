(function () {
  "use strict";

  var DEBUG = !!(window.cuftAvada && window.cuftAvada.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Avada]"].concat(Array.prototype.slice.call(arguments))
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

  function findField(form, type) {
    var inputs = form.querySelectorAll("input");
    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];
      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var inputMode = (input.getAttribute("inputmode") || "").toLowerCase();
      var dataValidate = (
        (input.getAttribute("data-validate") ||
          input.getAttribute("data-validation") ||
          "") + ""
      ).toLowerCase();
      var pattern = input.getAttribute("pattern") || "";

      if (type === "email") {
        if (
          inputType === "email" ||
          inputMode === "email" ||
          dataValidate.indexOf("email") > -1 ||
          /@/.test(pattern)
        ) {
          return input;
        }
      } else if (type === "phone") {
        if (
          inputType === "tel" ||
          inputMode === "tel" ||
          inputMode === "numeric" ||
          /\d|\[0-9]/.test(pattern)
        ) {
          return input;
        }
      }
    }
    return null;
  }

  function getFieldValue(form, type) {
    var field = findField(form, type);
    if (!field) return "";

    var value = (field.value || "").trim();
    if (type === "phone" && value) {
      return value.replace(/(?!^\+)[^\d]/g, "");
    }
    return value;
  }

  function isSuccessState(form) {
    var successSelectors = [
      ".fusion-form-response-success",
      ".fusion-alert.success",
      ".fusion-form-success",
      ".fusion-success",
      ".avada-form-success",
      ".fusion-form-success-message",
      '[data-status="sent"]',
      '[data-avada-form-status="success"]',
    ];

    for (var i = 0; i < successSelectors.length; i++) {
      var element = form.querySelector(successSelectors[i]);
      if (element) {
        log(
          "Success state detected with selector:",
          successSelectors[i],
          element
        );
        return true;
      }
    }

    if (!form.offsetParent) {
      var parent = form.parentNode;
      if (
        parent &&
        parent.querySelector('.thank-you, .success, [role="alert"]')
      ) {
        log("Success state detected: form hidden and success element found");
        return true;
      }
    }

    // Check for common success class patterns
    var hasSuccessClass =
      form.classList.contains("sent") ||
      form.classList.contains("is-success") ||
      form.classList.contains("form-success") ||
      form.classList.contains("successfully-submitted") ||
      form.classList.contains("fusion-form-response-success");

    if (hasSuccessClass) {
      log("Success state detected: form has success class");
      return true;
    }

    // Check if parent container has success class
    var formParent = form.parentNode;
    if (
      formParent &&
      formParent.classList &&
      formParent.classList.contains("fusion-form-response-success")
    ) {
      log("Success state detected: parent container has success class");
      return true;
    }

    // Check if form is replaced with success message
    var container = form.closest(".fusion-form-wrapper, .avada-form-wrapper");
    if (
      container &&
      container.querySelector(
        ".fusion-form-response-success, .fusion-success, .success-message, .thank-you"
      )
    ) {
      log("Success state detected: success message in form container");
      return true;
    }

    return false;
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
    if (!window.cuftAvada || !window.cuftAvada.generate_lead_enabled) {
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
    var payload = {
      event: "form_submit",
      formType: "avada",
      formId: form.getAttribute("id") || null,
      formName:
        form.getAttribute("name") ||
        form.getAttribute("data-form-name") ||
        null,
      submittedAt: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "avada_fusion",
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

  function observeSuccess(form, email, phone) {
    log("Starting success observation for form:", form.id || "unnamed", {
      formClassName: form.className,
      initialSuccessState: isSuccessState(form),
    });

    var pushed = false;
    var cleanup = function () {};

    function tryPush() {
      log("Checking success state for form:", form.id || "unnamed", {
        pushed: pushed,
        isSuccessState: isSuccessState(form),
      });
      if (!pushed && isSuccessState(form)) {
        pushed = true;
        log("Success state confirmed, pushing to dataLayer");
        pushToDataLayer(form, email, phone);
        cleanup();
      }
    }

    // Try immediately
    tryPush();

    // Set up observers
    var timeouts = [
      setTimeout(tryPush, 1000),
      setTimeout(tryPush, 3000),
      setTimeout(tryPush, 7000),
    ];

    var stopTimeout = setTimeout(function () {
      if (!pushed) cleanup();
    }, 10000);

    cleanup = function () {
      timeouts.forEach(function (t) {
        clearTimeout(t);
      });
      clearTimeout(stopTimeout);
    };

    // Mutation observer
    if (window.MutationObserver) {
      var observer = new MutationObserver(tryPush);
      observer.observe(form.parentNode || document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["class", "style"],
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
      log("Form submit event triggered:", event.type, form);

      if (!form || form.tagName !== "FORM") {
        log("Event target is not a form, skipping");
        return;
      }

      // Check if this is an Avada/Fusion form
      var isAvadaForm =
        form.classList.contains("fusion-form") ||
        form.classList.contains("avada-form") ||
        form.className.indexOf("fusion-form") > -1 ||
        form.id.indexOf("avada") > -1;

      log("Form detection check:", {
        form: form,
        classList: form.className,
        id: form.id,
        isAvadaForm: isAvadaForm,
      });

      if (!isAvadaForm) {
        log("Form not detected as Avada form, skipping");
        return;
      }

      // Check if form has email field (indicates it's a contact/lead form)
      var hasEmailField = findField(form, "email") !== null;
      if (!hasEmailField) {
        log(
          "Skipping Avada form - no email field detected (likely search form)"
        );
        return;
      }

      log("Avada form submit detected! Starting tracking process...");

      if (form.hasAttribute("data-cuft-observing")) return;
      form.setAttribute("data-cuft-observing", "true");

      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      log("Extracted form data:", {
        email: email || "none",
        phone: phone || "none",
      });

      observeSuccess(form, email, phone);
      log("Form submit listener attached for:", form.id || "unnamed form");
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  // Function to watch for AJAX form submissions
  function watchAjaxForms() {
    var fusionForms = document.querySelectorAll(".fusion-form");
    log("Found", fusionForms.length, "Fusion forms, checking for email fields");

    for (var i = 0; i < fusionForms.length; i++) {
      var form = fusionForms[i];
      if (form.hasAttribute("data-cuft-ajax-watching")) continue;

      // Check if form has email field (indicates it's a contact/lead form)
      var hasEmailField = findField(form, "email") !== null;

      log("Form check:", {
        className: form.className,
        hasEmailField: hasEmailField,
        action: form.action || "no action",
      });

      if (!hasEmailField) {
        log("Skipping form - no email field detected (likely search form)");
        continue;
      }

      form.setAttribute("data-cuft-ajax-watching", "true");
      log("Setting up AJAX watcher for form with email field:", form.className);

      // Watch for form submissions via click events on submit buttons
      var submitButtons = form.querySelectorAll(
        'input[type="submit"], button[type="submit"], .fusion-button'
      );
      for (var j = 0; j < submitButtons.length; j++) {
        var button = submitButtons[j];
        button.addEventListener("click", function (event) {
          var clickedForm = event.target.closest(".fusion-form");
          if (clickedForm) {
            log("Submit button clicked for Fusion form, starting observation");
            setTimeout(function () {
              var email = getFieldValue(clickedForm, "email");
              var phone = getFieldValue(clickedForm, "phone");
              observeSuccess(clickedForm, email, phone);
            }, 100);
          }
        });
      }
    }
  }

  ready(function () {
    document.addEventListener("submit", handleFormSubmit, true);
    log("Avada forms tracking initialized");

    // Debug: Log all forms found on page
    var allForms = document.querySelectorAll("form");
    log("Forms found on page:", allForms.length);
    for (var i = 0; i < allForms.length; i++) {
      var form = allForms[i];
      log("Form " + i + ":", {
        id: form.id,
        className: form.className,
        action: form.action,
      });
    }

    // Set up AJAX form watchers
    watchAjaxForms();

    // Also watch for dynamically added forms
    if (window.MutationObserver) {
      var documentObserver = new MutationObserver(function (mutations) {
        var shouldRewatch = false;
        mutations.forEach(function (mutation) {
          if (mutation.type === "childList") {
            for (var i = 0; i < mutation.addedNodes.length; i++) {
              var node = mutation.addedNodes[i];
              if (
                node.nodeType === 1 &&
                (node.classList.contains("fusion-form") ||
                  node.querySelector(".fusion-form"))
              ) {
                shouldRewatch = true;
                break;
              }
            }
          }
        });
        if (shouldRewatch) {
          log("New forms detected, re-running AJAX watchers");
          watchAjaxForms();
        }
      });

      documentObserver.observe(document.body, {
        childList: true,
        subtree: true,
      });
    }
  });
})();
