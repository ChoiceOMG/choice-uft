(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT CF7] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }

  // Check for available utility systems
  var hasErrorBoundary = !!(window.cuftErrorBoundary);
  var hasPerformanceMonitor = !!(window.cuftPerformanceMonitor);
  var hasObserverCleanup = !!(window.cuftObserverCleanup);
  var hasRetryLogic = !!(window.cuftRetryLogic);

  var DEBUG = !!(window.cuftCF7 && window.cuftCF7.console_logging);

  function log() {
    if (!DEBUG) return;

    var safeLog = hasErrorBoundary ?
      window.cuftErrorBoundary.safeExecute :
      function(fn) { try { return fn(); } catch (e) { return null; } };

    safeLog(function() {
      if (window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT CF7]"].concat(Array.prototype.slice.call(arguments))
        );
      }
    }, 'CF7 Logging');
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
   * Check if form is a Contact Form 7 form
   */
  function isCF7Form(form) {
    if (!form) return false;

    var checkForm = hasErrorBoundary ?
      window.cuftErrorBoundary.safeDOMOperation :
      function(fn) { try { return fn(); } catch (e) { return false; } };

    return checkForm(function() {
      return form && (
        form.closest('.wpcf7') !== null ||
        form.classList.contains('wpcf7-form') ||
        form.hasAttribute('data-wpcf7-id')
      );
    }, form, 'CF7 Form Detection') || false;
  }

  /**
   * Get field value from CF7 form using CF7-specific naming patterns
   */
  function getFieldValue(form, type) {
    var measurement = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('cf7-field-extraction', {
        fieldType: type,
        context: 'CF7 Field Detection'
      }) : null;

    try {
      // Framework detection - exit silently if not CF7
      if (!isCF7Form(form)) {
        if (measurement) measurement.end();
        return "";
      }

      var safeDOMQuery = hasErrorBoundary ?
        window.cuftErrorBoundary.safeDOMOperation :
        function(fn) { try { return fn(); } catch (e) { return []; } };

      var inputs = safeDOMQuery(function() {
        return form.querySelectorAll("input, textarea, select");
      }, form, 'CF7 Field Input Query') || [];

      var field = null;

      log("Searching for " + type + " field in CF7 form with " + inputs.length + " inputs");

    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];

      // Skip hidden inputs
      if (input.type === "hidden") continue;

      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var name = (input.name || "").toLowerCase();
      var id = (input.id || "").toLowerCase();
      var className = (input.className || "").toLowerCase();
      var placeholder = (input.placeholder || "").toLowerCase();
      var ariaLabel = (input.getAttribute("aria-label") || "").toLowerCase();

      // Get associated label text
      var labelElement = form.querySelector('label[for="' + input.id + '"]');
      var labelText = labelElement ? (labelElement.textContent || "").toLowerCase() : "";

      // Check parent container for field type clues
      var parentLabel = input.closest('label');
      var parentLabelText = parentLabel ? (parentLabel.textContent || "").toLowerCase() : "";

      log("Checking CF7 input " + i + ":", {
        type: inputType,
        name: name,
        id: id,
        className: className,
        placeholder: placeholder,
        labelText: labelText || parentLabelText
      });

      if (type === "email") {
        if (
          inputType === "email" ||
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
          parentLabelText.indexOf("email") > -1 ||
          parentLabelText.indexOf("e-mail") > -1 ||
          parentLabelText.indexOf("mail") > -1
        ) {
          field = input;
          log("Found CF7 email field:", input);
          break;
        }
      } else if (type === "phone") {
        if (
          inputType === "tel" ||
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
          parentLabelText.indexOf("phone") > -1 ||
          parentLabelText.indexOf("mobile") > -1 ||
          parentLabelText.indexOf("tel") > -1
        ) {
          field = input;
          log("Found CF7 phone field:", input);
          break;
        }
      }
    }

    if (!field) {
      log("No " + type + " field found in CF7 form");
      if (measurement) measurement.end();
      return "";
    }

    var value = safeDOMQuery(function() {
      return (field.value || "").trim();
    }, field, 'CF7 Field Value Extraction') || "";

    log("CF7 field value for " + type + ":", value);

    if (measurement) measurement.end();
    return value;

    } catch (e) {
      log("Error in CF7 field extraction:", e);
      if (measurement) measurement.end();
      return "";
    }
  }

  /**
   * Get CF7 form identification details
   */
  function getCF7FormDetails(form) {
    var formWrapper = form.closest(".wpcf7");

    var formId = null;
    var formName = null;

    if (formWrapper) {
      formId = formWrapper.getAttribute("id") ||
               formWrapper.getAttribute("data-wpcf7-id") ||
               form.getAttribute("data-wpcf7-id");
    }

    if (!formId) {
      formId = form.getAttribute("id") || "unknown";
    }

    // CF7 doesn't typically expose form names in frontend, but check for any available
    formName = form.getAttribute("data-form-name") ||
               form.getAttribute("name") ||
               form.getAttribute("aria-label") ||
               (formWrapper ? formWrapper.getAttribute("data-form-name") : null);

    log("CF7 form identification:", {
      formId: formId,
      formName: formName
    });

    return {
      form_id: formId,
      form_name: formName
    };
  }

  /**
   * Main CF7 success handler using standardized utilities
   */
  function handleCF7Success(form) {
    var measurement = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('cf7-form-processing', {
        context: 'CF7 Form Success Handler'
      }) : null;

    var safeProcess = hasErrorBoundary ?
      window.cuftErrorBoundary.safeFormOperation :
      function(formEl, fn, context) { try { return fn(formEl); } catch (e) { log("CF7 form processing error:", e); return false; } };

    return safeProcess(form, function(formElement) {
      // Framework detection - exit silently if not CF7
      if (!isCF7Form(formElement)) {
        if (measurement) measurement.end();
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(formElement)) {
        log("CF7 form already processed, skipping");
        if (measurement) measurement.end();
        return false;
      }

      // Get form details
      var formDetails = getCF7FormDetails(formElement);

      // Get field values
      var email = getFieldValue(formElement, "email");
      var phone = getFieldValue(formElement, "phone");

      // Validate email if present
      if (email && !window.cuftDataLayerUtils.validateEmail(email)) {
        log("Invalid email found, excluding from CF7 tracking:", email);
        email = "";
      }

      // Sanitize phone if present
      if (phone) {
        phone = window.cuftDataLayerUtils.sanitizePhone(phone);
      }

      log("Processing CF7 form submission:", {
        formId: formDetails.form_id,
        formName: formDetails.form_name,
        email: email || "not found",
        phone: phone || "not found"
      });

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission('cf7', formElement, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG
      });

      if (measurement) measurement.end();

      if (success) {
        log("CF7 form successfully tracked");
        return true;
      } else {
        log("CF7 form tracking failed");
        return false;
      }

    }, 'CF7 Success Handler');
  }

  /**
   * Handle CF7 wpcf7mailsent event
   */
  function handleCF7MailSent(event) {
    var processEvent = function() {
      var form = event.target;

      if (form) {
        log("CF7 wpcf7mailsent event detected");
        return handleCF7Success(form);
      } else {
        log("CF7 mailsent event without form target");
        return false;
      }
    };

    if (hasRetryLogic) {
      window.cuftRetryLogic.executeWithRetry('cf7-mailsent-event', processEvent, {
        maxAttempts: 2,
        baseDelay: 500,
        context: 'CF7 MailSent Handler'
      }).catch(function(error) {
        log("CF7 mailsent handler error after retry:", error);
      });
    } else {
      try {
        processEvent();
      } catch (e) {
        log("CF7 mailsent handler error:", e);
      }
    }
  }

  /**
   * Handle CF7 wpcf7mailfailed event (for debugging)
   */
  function handleCF7MailFailed(event) {
    try {
      if (DEBUG) {
        log("CF7 mail failed for form:", event.target);
      }
    } catch (e) {
      log("CF7 mailfailed handler error:", e);
    }
  }

  /**
   * Handle CF7 wpcf7invalid event (for debugging)
   */
  function handleCF7Invalid(event) {
    try {
      if (DEBUG) {
        log("CF7 form validation failed:", event.target);
      }
    } catch (e) {
      log("CF7 invalid handler error:", e);
    }
  }

  /**
   * Setup CF7 event listeners
   */
  function setupCF7EventListeners() {
    var listenersSetup = [];

    // Primary: CF7 mailsent event (only successful submissions)
    try {
      document.addEventListener("wpcf7mailsent", handleCF7MailSent, false);
      listenersSetup.push("wpcf7mailsent");
    } catch (e) {
      log("Could not add CF7 mailsent listener:", e);
    }

    // Debug listeners for failed submissions
    if (DEBUG) {
      try {
        document.addEventListener("wpcf7mailfailed", handleCF7MailFailed, false);
        listenersSetup.push("wpcf7mailfailed (debug)");
      } catch (e) {
        log("Could not add CF7 mailfailed listener:", e);
      }

      try {
        document.addEventListener("wpcf7invalid", handleCF7Invalid, false);
        listenersSetup.push("wpcf7invalid (debug)");
      } catch (e) {
        log("Could not add CF7 invalid listener:", e);
      }
    }

    log("CF7 event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    var initialization = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('cf7-initialization', {
        context: 'CF7 Forms System Initialization'
      }) : null;

    var safeInit = hasErrorBoundary ?
      window.cuftErrorBoundary.safeExecute :
      function(fn, context) { try { return fn(); } catch (e) { log("CF7 initialization error:", e); return false; } };

    safeInit(function() {
      // Log utility system status
      log("CF7 Utility Systems Status:", {
        errorBoundary: hasErrorBoundary,
        performanceMonitor: hasPerformanceMonitor,
        observerCleanup: hasObserverCleanup,
        retryLogic: hasRetryLogic,
        dataLayerUtils: !!(window.cuftDataLayerUtils)
      });

      // Setup event listeners
      setupCF7EventListeners();

      if (initialization) initialization.end();

      log("Contact Form 7 tracking initialized with " +
          [hasErrorBoundary && "error boundary",
           hasPerformanceMonitor && "performance monitoring",
           hasObserverCleanup && "observer cleanup",
           hasRetryLogic && "retry logic"]
          .filter(Boolean).join(", ") + " systems");

      return true;
    }, 'CF7 Forms Initialization');
  });

})();