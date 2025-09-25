(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT Ninja] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }

  // Check for available utility systems
  var hasErrorBoundary = !!(window.cuftErrorBoundary);
  var hasPerformanceMonitor = !!(window.cuftPerformanceMonitor);
  var hasObserverCleanup = !!(window.cuftObserverCleanup);
  var hasRetryLogic = !!(window.cuftRetryLogic);

  var DEBUG = !!(window.cuftNinja && window.cuftNinja.console_logging);

  function log() {
    if (!DEBUG) return;

    var args = arguments;

    var safeLog = hasErrorBoundary ?
      window.cuftErrorBoundary.safeExecute :
      function(fn) { try { return fn(); } catch (e) { return null; } };

    safeLog(function() {
      if (window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Ninja]"].concat(Array.prototype.slice.call(args))
        );
      }
    }, 'Ninja Forms Logging');
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

  /**
   * Check if form is a Ninja Forms form
   */
  function isNinjaForm(form) {
    if (!form) return false;

    var checkForm = hasErrorBoundary ?
      window.cuftErrorBoundary.safeDOMOperation :
      function(fn) { try { return fn(); } catch (e) { return false; } };

    return checkForm(function() {
      return form && (
        form.closest('.nf-form-cont') !== null ||
        form.classList.contains('nf-form') ||
        form.querySelector('.nf-field') !== null ||
        form.closest('.nf-form-wrap') !== null
      );
    }, form, 'Ninja Forms Detection') || false;
  }

  /**
   * Get field value from Ninja Forms using .nf-field container structure
   */
  function getFieldValue(form, type) {
    var measurement = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('ninja-field-extraction', {
        fieldType: type,
        context: 'Ninja Field Detection'
      }) : null;

    try {
      // Framework detection - exit silently if not Ninja Forms
      if (!isNinjaForm(form)) {
        if (measurement) measurement.end();
        return "";
      }

      var safeDOMQuery = hasErrorBoundary ?
        window.cuftErrorBoundary.safeDOMOperation :
        function(fn) { try { return fn(); } catch (e) { return []; } };

      var fields = safeDOMQuery(function() {
        return form.querySelectorAll(".nf-field");
      }, form, 'Ninja Field Container Query') || [];

      var field = null;

      log("Searching for " + type + " field in Ninja form with " + fields.length + " fields");

    for (var i = 0; i < fields.length; i++) {
      var fieldContainer = fields[i];
      var input = fieldContainer.querySelector("input, textarea, select");

      if (!input) continue;

      var fieldType = fieldContainer.getAttribute("data-field-type") || "";
      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var inputMode = (input.getAttribute("inputmode") || "").toLowerCase();
      var name = (input.name || "").toLowerCase();
      var id = (input.id || "").toLowerCase();
      var className = (input.className || "").toLowerCase();
      var placeholder = (input.placeholder || "").toLowerCase();
      var ariaLabel = (input.getAttribute("aria-label") || "").toLowerCase();

      // Get label from field container
      var labelElement = fieldContainer.querySelector("label");
      var labelText = labelElement ? (labelElement.textContent || "").toLowerCase() : "";

      // Check for field title in container
      var fieldTitle = fieldContainer.querySelector(".field-title");
      var fieldTitleText = fieldTitle ? (fieldTitle.textContent || "").toLowerCase() : "";

      log("Checking Ninja field " + i + ":", {
        fieldType: fieldType,
        inputType: inputType,
        name: name,
        id: id,
        className: className,
        placeholder: placeholder,
        labelText: labelText || fieldTitleText
      });

      if (type === "email") {
        var pattern = input.getAttribute("pattern") || "";
        if (
          fieldType === "email" ||
          inputType === "email" ||
          inputMode === "email" ||
          name.indexOf("email") > -1 ||
          name.indexOf("e-mail") > -1 ||
          name.indexOf("mail") > -1 ||
          id.indexOf("email") > -1 ||
          className.indexOf("email") > -1 ||
          placeholder.indexOf("email") > -1 ||
          placeholder.indexOf("@") > -1 ||
          ariaLabel.indexOf("email") > -1 ||
          labelText.indexOf("email") > -1 ||
          labelText.indexOf("e-mail") > -1 ||
          labelText.indexOf("mail") > -1 ||
          fieldTitleText.indexOf("email") > -1 ||
          fieldTitleText.indexOf("e-mail") > -1 ||
          (pattern && pattern.indexOf("@") > -1)
        ) {
          field = input;
          log("Found Ninja email field:", input);
          break;
        }
      } else if (type === "phone") {
        // Check if pattern contains numbers safely
        var pattern = input.getAttribute("pattern") || "";
        var hasNumberPattern = false;
        try {
          hasNumberPattern = pattern && (
            pattern.indexOf("0-9") > -1 ||
            pattern.indexOf("\\d") > -1 ||
            pattern.indexOf("[0-9") > -1
          );
        } catch (e) {}

        if (
          fieldType === "phone" ||
          fieldType === "tel" ||
          inputType === "tel" ||
          inputMode === "tel" ||
          inputMode === "numeric" ||
          name.indexOf("phone") > -1 ||
          name.indexOf("tel") > -1 ||
          name.indexOf("mobile") > -1 ||
          name.indexOf("number") > -1 ||
          id.indexOf("phone") > -1 ||
          id.indexOf("tel") > -1 ||
          className.indexOf("phone") > -1 ||
          className.indexOf("tel") > -1 ||
          placeholder.indexOf("phone") > -1 ||
          placeholder.indexOf("mobile") > -1 ||
          placeholder.indexOf("tel") > -1 ||
          placeholder.indexOf("(") > -1 ||
          ariaLabel.indexOf("phone") > -1 ||
          ariaLabel.indexOf("mobile") > -1 ||
          labelText.indexOf("phone") > -1 ||
          labelText.indexOf("mobile") > -1 ||
          labelText.indexOf("tel") > -1 ||
          labelText.indexOf("number") > -1 ||
          fieldTitleText.indexOf("phone") > -1 ||
          fieldTitleText.indexOf("mobile") > -1 ||
          fieldTitleText.indexOf("tel") > -1 ||
          hasNumberPattern
        ) {
          field = input;
          log("Found Ninja phone field:", input);
          break;
        }
      }
    }

    if (!field) {
      log("No " + type + " field found in Ninja form");
      if (measurement) measurement.end();
      return "";
    }

    var value = safeDOMQuery(function() {
      return (field.value || "").trim();
    }, field, 'Ninja Field Value Extraction') || "";

    log("Ninja field value for " + type + ":", value);

    if (measurement) measurement.end();
    return value;

    } catch (e) {
      log("Error in Ninja field extraction:", e);
      if (measurement) measurement.end();
      return "";
    }
  }

  /**
   * Get Ninja Forms form identification details
   */
  function getNinjaFormDetails(form) {
    var formId = form.getAttribute("data-form-id") ||
                 form.getAttribute("id") ||
                 form.getAttribute("data-ninja-form-id");

    // Try to get form name from wrapper or attributes
    var formName = form.getAttribute("data-form-name") ||
                   form.getAttribute("name") ||
                   form.getAttribute("aria-label");

    // Check form wrapper for additional details
    var formWrapper = form.closest('.nf-form-wrap, .nf-form-cont');
    if (formWrapper && !formId) {
      formId = formWrapper.getAttribute("data-form-id") ||
               formWrapper.getAttribute("id");
    }

    if (formWrapper && !formName) {
      formName = formWrapper.getAttribute("data-form-name") ||
                 formWrapper.getAttribute("data-form-title");
    }

    if (!formId) {
      formId = "unknown";
    }

    log("Ninja form identification:", {
      formId: formId,
      formName: formName
    });

    return {
      form_id: formId,
      form_name: formName
    };
  }

  /**
   * Check if Ninja form is in success state
   */
  function isNinjaSuccessState(form) {
    // Check for success message in form or parent
    var successMsg = form.querySelector(".nf-response-msg");
    var parentSuccessMsg = form.parentNode ? form.parentNode.querySelector(".nf-response-msg") : null;

    if (successMsg && successMsg.style.display !== "none" && successMsg.textContent.trim()) {
      log("Success message found in form");
      return true;
    }

    if (parentSuccessMsg && parentSuccessMsg.style.display !== "none" && parentSuccessMsg.textContent.trim()) {
      log("Success message found in parent");
      return true;
    }

    // Check if form is hidden (typical after success)
    if (form.style.display === "none" || !form.offsetParent) {
      if (successMsg || parentSuccessMsg) {
        log("Form hidden with success message present");
        return true;
      }
    }

    // Check for success-related CSS classes
    var formWrapper = form.closest('.nf-form-wrap, .nf-form-cont');
    if (formWrapper) {
      if (formWrapper.classList.contains('nf-success') ||
          formWrapper.classList.contains('nf-form-success') ||
          formWrapper.querySelector('.nf-success')) {
        log("Success class found on form wrapper");
        return true;
      }
    }

    return false;
  }

  /**
   * Main Ninja Forms success handler using standardized utilities
   */
  function handleNinjaSuccess(form, email, phone) {
    var measurement = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('ninja-form-processing', {
        context: 'Ninja Form Success Handler'
      }) : null;

    var safeProcess = hasErrorBoundary ?
      window.cuftErrorBoundary.safeFormOperation :
      function(formEl, fn, context) { try { return fn(formEl); } catch (e) { log("Ninja form processing error:", e); return false; } };

    return safeProcess(form, function(formElement) {
      // Framework detection - exit silently if not Ninja Forms
      if (!isNinjaForm(formElement)) {
        if (measurement) measurement.end();
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(formElement)) {
        log("Ninja form already processed, skipping");
        if (measurement) measurement.end();
        return false;
      }

      // Get form details
      var formDetails = getNinjaFormDetails(form);

      // Validate email if present
      if (email && !window.cuftDataLayerUtils.validateEmail(email)) {
        log("Invalid email found, excluding from Ninja tracking:", email);
        email = "";
      }

      // Sanitize phone if present
      if (phone) {
        phone = window.cuftDataLayerUtils.sanitizePhone(phone);
      }

      log("Processing Ninja form submission:", {
        formId: formDetails.form_id,
        formName: formDetails.form_name,
        email: email || "not found",
        phone: phone || "not found"
      });

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission('ninja', form, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG
      });

      if (measurement) measurement.end();

      if (success) {
        log("Ninja form successfully tracked");
        return true;
      } else {
        log("Ninja form tracking failed");
        return false;
      }

    }, 'Ninja Success Handler');
  }

  /**
   * Setup MutationObserver for success detection with exponential backoff
   */
  function observeNinjaSuccess(form, email, phone) {
    var observerConfig = {
      id: 'ninja-success-observer',
      element: form,
      timeout: 15000,
      context: 'Ninja Success Detection',
      description: 'Observing form for success state changes'
    };

    var cleanup = hasObserverCleanup ?
      window.cuftObserverCleanup.registerObserver(observerConfig) :
      function() {};

    var pushed = false;
    var attempts = 0;
    var maxAttempts = 6;

    function tryPush() {
      attempts++;
      if (!pushed && isNinjaSuccessState(form)) {
        pushed = true;
        handleNinjaSuccess(form, email, phone);
        cleanup();
        return;
      }

      // Exponential backoff with maximum attempts
      if (attempts < maxAttempts && !pushed) {
        var delay = Math.min(500 * Math.pow(2, attempts - 1), 4000); // Max 4s delay
        setTimeout(tryPush, delay);
        log("Ninja success check attempt " + attempts + ", next in " + delay + "ms");
      } else if (!pushed) {
        log("Ninja success detection timed out after " + attempts + " attempts");
        cleanup();
      }
    }

    // Initial attempt
    setTimeout(tryPush, 100);

    // Set up MutationObserver for real-time detection
    if (window.MutationObserver) {
      var observer = new MutationObserver(function(mutations) {
        if (pushed) return;

        mutations.forEach(function(mutation) {
          if (mutation.type === "childList" || mutation.type === "attributes") {
            if (isNinjaSuccessState(form)) {
              pushed = true;
              handleNinjaSuccess(form, email, phone);
              cleanup();
            }
          }
        });
      });

      // Observe form and parent container
      observer.observe(form, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["style", "class"]
      });

      var formWrapper = form.closest('.nf-form-wrap, .nf-form-cont');
      if (formWrapper && formWrapper !== form) {
        observer.observe(formWrapper, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ["style", "class"]
        });
      }

      var originalCleanup = cleanup;
      cleanup = function () {
        observer.disconnect();
        originalCleanup();
      };

      log("MutationObserver setup for Ninja success detection");
    }

    // Safety timeout to prevent memory leaks
    setTimeout(function () {
      if (!pushed) {
        log("Ninja success observation timed out, cleaning up");
        cleanup();
      }
    }, 15000); // 15 second maximum observation
  }

  /**
   * Handle form submit to capture field values and start observation
   */
  function handleNinjaFormSubmit(event) {
    var processEvent = function() {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return false;

      // Check if this is a Ninja Forms form - exit silently if not
      if (!isNinjaForm(form)) {
        return false;
      }

      // Prevent multiple observations on the same form
      if (form.hasAttribute("data-cuft-ninja-observing")) {
        log("Ninja form already being observed, skipping");
        return;
      }

      form.setAttribute("data-cuft-ninja-observing", "true");

      // Capture field values at submit time
      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      log("Ninja form submit detected, starting success observation:", {
        formId: form.getAttribute("data-form-id") || form.getAttribute("id") || "unknown",
        email: email || "not found",
        phone: phone || "not found"
      });

      // Start observing for success state
      observeNinjaSuccess(form, email, phone);
      return true;
    };

    if (hasRetryLogic) {
      window.cuftRetryLogic.executeWithRetry('ninja-form-submit', processEvent, {
        maxAttempts: 2,
        baseDelay: 500,
        context: 'Ninja Form Submit Handler'
      }).catch(function(error) {
        log("Ninja submit handler error after retry:", error);
      });
    } else {
      try {
        processEvent();
      } catch (e) {
        log("Ninja submit handler error:", e);
      }
    }
  }

  /**
   * Setup Ninja Forms event listeners
   */
  function setupNinjaEventListeners() {
    var listenersSetup = [];

    // Primary: Form submit handler with success observation
    try {
      document.addEventListener("submit", handleNinjaFormSubmit, true);
      listenersSetup.push("form submit handler");
    } catch (e) {
      log("Could not add Ninja submit listener:", e);
    }

    // Optional: Ninja Forms API events if available
    if (window.Marionette && window.nfRadio) {
      try {
        window.nfRadio.channel("forms").on("submit:response", function (response) {
          log("Ninja Forms API response received:", response);
          // Could potentially use this for more direct success detection
          // but submit observation is more reliable across versions
        });
        listenersSetup.push("Ninja Forms API events");
      } catch (e) {
        log("Could not setup Ninja Forms API listeners:", e);
      }
    }

    log("Ninja Forms event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    setupNinjaEventListeners();
    log("Ninja Forms tracking initialized using standardized dataLayer utilities");
  });

})();