(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT Elementor] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }

  // Check for available utility systems
  var hasErrorBoundary = !!(window.cuftErrorBoundary);
  var hasPerformanceMonitor = !!(window.cuftPerformanceMonitor);
  var hasObserverCleanup = !!(window.cuftObserverCleanup);
  var hasRetryLogic = !!(window.cuftRetryLogic);

  var DEBUG = !!(window.cuftElementor && window.cuftElementor.console_logging);

  function log() {
    if (!DEBUG) return;

    var safeLog = hasErrorBoundary ?
      window.cuftErrorBoundary.safeExecute :
      function(fn) { try { return fn(); } catch (e) { return null; } };

    safeLog(function() {
      if (window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Elementor]"].concat(Array.prototype.slice.call(arguments))
        );
      }
    }, 'Elementor Logging');
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
   * Check if form is an Elementor form
   */
  function isElementorForm(form) {
    if (!form) return false;

    var checkForm = hasErrorBoundary ?
      window.cuftErrorBoundary.safeDOMOperation :
      function(fn) { try { return fn(); } catch (e) { return false; } };

    return checkForm(function() {
      return form && (
        form.classList.contains('elementor-form') ||
        form.closest('.elementor-widget-form') !== null ||
        form.getAttribute('data-settings') !== null
      );
    }, form, 'Elementor Form Detection') || false;
  }

  /**
   * Fix invalid regex patterns in Elementor forms
   */
  function fixInvalidPatterns() {
    try {
      var inputs = document.querySelectorAll("input[pattern]");
      var fixedCount = 0;

      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var pattern = input.getAttribute("pattern");

        if (!pattern) continue;

        // Check for the specific problematic pattern or similar issues
        var needsFix = false;
        var fixedPattern = pattern;

        // Look for character classes with hyphens that aren't at the beginning or end
        if (pattern.indexOf("[") !== -1) {
          // Handle the specific known problematic pattern first
          if (pattern === "[0-9()#&+*-=.]+") {
            fixedPattern = "[0-9()#&+*=.-]+";  // Move hyphen to end
            needsFix = true;
          }
          // Check for other patterns that have hyphens creating invalid ranges
          else if (pattern.indexOf("-") > -1 && pattern.indexOf("[") > -1) {
            var charClassMatch = pattern.match(/\[([^\]]+)\]/);
            if (charClassMatch) {
              var charClass = charClassMatch[1];
              var hyphenIndex = charClass.indexOf("-");

              if (hyphenIndex > 0 && hyphenIndex < charClass.length - 1) {
                var beforeHyphen = charClass.charAt(hyphenIndex - 1);
                var afterHyphen = charClass.charAt(hyphenIndex + 1);

                // Check if this would be an invalid range
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

  /**
   * Get field value from form using comprehensive detection
   */
  function getFieldValue(form, type) {
    var measurement = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('elementor-field-extraction', {
        fieldType: type,
        context: 'Elementor Field Detection'
      }) : null;

    try {
      // Framework detection - exit silently if not Elementor
      if (!isElementorForm(form)) {
        if (measurement) measurement.end();
        return "";
      }

      var safeDOMQuery = hasErrorBoundary ?
        window.cuftErrorBoundary.safeDOMOperation :
        function(fn) { try { return fn(); } catch (e) { return []; } };

      var inputs = safeDOMQuery(function() {
        return form.querySelectorAll("input, textarea");
      }, form, 'Field Input Query') || [];

      var field = null;

      log("Searching for " + type + " field in form with " + inputs.length + " inputs");

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

      // Extract field name from Elementor's standard naming pattern: form_fields[fieldname]
      var fieldNameMatch = name.match(/form_fields\[([^\]]+)\]/);
      var extractedFieldName = fieldNameMatch ? fieldNameMatch[1].toLowerCase() : "";

      // Get the label text if available
      var labelElement = form.querySelector('label[for="' + input.id + '"]');
      var labelText = labelElement ? (labelElement.textContent || "").toLowerCase() : "";

      // Check parent container for field type clues
      var parentContainer = input.closest(".elementor-field-group");
      var parentLabel = parentContainer ? parentContainer.querySelector("label") : null;
      var parentLabelText = parentLabel ? (parentLabel.textContent || "").toLowerCase() : "";

      log("Checking input " + i + ":", {
        type: inputType,
        name: name,
        id: id,
        fieldType: fieldType,
        extractedFieldName: extractedFieldName,
        placeholder: placeholder,
        labelText: labelText || parentLabelText
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
          originalName === "email" ||
          id.indexOf("email") > -1 ||
          placeholder.indexOf("email") > -1 ||
          placeholder.indexOf("@") > -1 ||
          ariaLabel.indexOf("email") > -1 ||
          labelText.indexOf("email") > -1 ||
          parentLabelText.indexOf("email") > -1 ||
          (pattern && pattern.indexOf("@") > -1)
        ) {
          field = input;
          log("Found email field:", input);
          break;
        }
      } else if (type === "phone") {
        // Check if pattern contains numbers safely
        var hasNumberPattern = false;
        try {
          hasNumberPattern = pattern && (
            pattern.indexOf("0-9") > -1 ||
            pattern.indexOf("\\d") > -1 ||
            pattern.indexOf("[0-9") > -1
          );
        } catch (e) {}

        if (
          inputType === "tel" ||
          inputMode === "tel" ||
          inputMode === "numeric" ||
          dataValidation === "phone" ||
          fieldType === "tel" ||
          fieldType === "phone" ||
          elementorFieldType === "tel" ||
          extractedFieldName === "phone" ||
          extractedFieldName === "tel" ||
          name.indexOf("phone") > -1 ||
          name.indexOf("tel") > -1 ||
          name.indexOf("mobile") > -1 ||
          originalName === "phone" ||
          originalName === "tel" ||
          id.indexOf("phone") > -1 ||
          id.indexOf("tel") > -1 ||
          placeholder.indexOf("phone") > -1 ||
          placeholder.indexOf("mobile") > -1 ||
          placeholder.indexOf("(") > -1 ||
          ariaLabel.indexOf("phone") > -1 ||
          labelText.indexOf("phone") > -1 ||
          labelText.indexOf("mobile") > -1 ||
          parentLabelText.indexOf("phone") > -1 ||
          parentLabelText.indexOf("mobile") > -1 ||
          hasNumberPattern
        ) {
          field = input;
          log("Found phone field:", input);
          break;
        }
      }
    }

    if (!field) {
      log("No " + type + " field found in form");
      if (measurement) measurement.end();
      return "";
    }

    var value = safeDOMQuery(function() {
      return (field.value || "").trim();
    }, field, 'Field Value Extraction') || "";

    log("Field value for " + type + ":", value);

    if (measurement) measurement.end();
    return value;

    } catch (e) {
      log("Error in field extraction:", e);
      if (measurement) measurement.end();
      return "";
    }
  }

  /**
   * Get form identification details
   */
  function getFormDetails(form) {
    var formIdInput = form.querySelector("input[name='form_id']");
    var formNameInput = form.querySelector("input[name='form_name']");
    var elementorWidget = form.closest(".elementor-widget");

    var formId =
      form.getAttribute("data-form-id") ||
      form.getAttribute("id") ||
      form.getAttribute("data-elementor-form-id") ||
      (formIdInput ? formIdInput.value : null) ||
      "unknown";

    var formName =
      form.getAttribute("data-form-name") ||
      form.getAttribute("name") ||
      form.getAttribute("aria-label") ||
      (formNameInput ? formNameInput.value : null) ||
      (elementorWidget ? elementorWidget.getAttribute("data-widget_type") : null) ||
      null;

    log("Form identification:", {
      formId: formId,
      formName: formName
    });

    return {
      form_id: formId,
      form_name: formName
    };
  }

  /**
   * Handle multi-step form detection (final step only)
   */
  function isFinalStepForm(form) {
    // Look for multi-step indicators
    var steps = form.querySelectorAll('.elementor-field-type-step');
    var currentStep = form.querySelector('.elementor-step-current');
    var submitButtons = form.querySelectorAll('button[type="submit"], input[type="submit"]');

    // If there are step elements, check if we're on the final step
    if (steps.length > 0) {
      var isLastStep = form.querySelector('.elementor-step-current.elementor-step-last');
      if (!isLastStep) {
        log("Multi-step form detected, not on final step");
        return false;
      }
    }

    log("Form is final step (single step or multi-step final)");
    return true;
  }

  /**
   * Handle popup form detection
   */
  function isPopupForm(form) {
    var popup = form.closest('.elementor-popup-modal');
    if (popup) {
      log("Popup form detected");
      return true;
    }
    return false;
  }

  /**
   * Main success handler using standardized utilities
   */
  function handleElementorSuccess(form) {
    var measurement = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('elementor-form-processing', {
        context: 'Elementor Form Success Handler'
      }) : null;

    var safeProcess = hasErrorBoundary ?
      window.cuftErrorBoundary.safeFormOperation :
      function(formEl, fn, context) { try { return fn(formEl); } catch (e) { log("Form processing error:", e); return false; } };

    return safeProcess(form, function(formElement) {
      // Framework detection - exit silently if not Elementor
      if (!isElementorForm(formElement)) {
        if (measurement) measurement.end();
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(formElement)) {
        log("Form already processed, skipping");
        if (measurement) measurement.end();
        return false;
      }

      // Check for multi-step forms (only track final step)
      if (!isFinalStepForm(formElement)) {
        log("Multi-step form not on final step, skipping");
        if (measurement) measurement.end();
        return false;
      }

      // Get form details
      var formDetails = getFormDetails(formElement);

      // Get field values
      var email = getFieldValue(formElement, "email");
      var phone = getFieldValue(formElement, "phone");

      // Validate email if present
      if (email && !window.cuftDataLayerUtils.validateEmail(email)) {
        log("Invalid email found, excluding from tracking:", email);
        email = "";
      }

      // Sanitize phone if present
      if (phone) {
        phone = window.cuftDataLayerUtils.sanitizePhone(phone);
      }

      log("Processing Elementor form submission:", {
        formId: formDetails.form_id,
        formName: formDetails.form_name,
        email: email || "not found",
        phone: phone || "not found",
        isPopup: isPopupForm(formElement)
      });

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission('elementor', formElement, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG
      });

      if (measurement) measurement.end();

      if (success) {
        log("Elementor form successfully tracked");
        return true;
      } else {
        log("Elementor form tracking failed");
        return false;
      }

    }, 'Elementor Success Handler');
  }

  /**
   * Handle native submit_success event (Elementor 3.5+)
   */
  function handleNativeSuccessEvent(event) {
    var processEvent = function() {
      var form = event.target && event.target.closest(".elementor-form");
      if (!form) {
        // Try to find form with pending tracking attribute
        var pendingForms = document.querySelectorAll('.elementor-form[data-cuft-tracking="pending"]');
        if (pendingForms.length > 0) {
          form = pendingForms[0];
          form.removeAttribute("data-cuft-tracking");
        }
      }

      if (form) {
        log("Native submit_success event detected");
        return handleElementorSuccess(form);
      }
      return false;
    };

    if (hasRetryLogic) {
      window.cuftRetryLogic.executeWithRetry('elementor-native-success-event', processEvent, {
        maxAttempts: 2,
        baseDelay: 500,
        context: 'Elementor Native Success Handler'
      }).catch(function(error) {
        log("Native success handler error after retry:", error);
      });
    } else {
      try {
        processEvent();
      } catch (e) {
        log("Native success handler error:", e);
      }
    }
  }

  /**
   * Handle jQuery submit_success event (legacy support)
   */
  function handleJQuerySuccessEvent(event) {
    var processEvent = function() {
      var form = null;

      if (event.target) {
        form = event.target.closest(".elementor-form");
      }

      if (!form) {
        // Try to find form with pending tracking attribute
        var pendingForms = document.querySelectorAll('.elementor-form[data-cuft-tracking="pending"]');
        if (pendingForms.length > 0) {
          form = pendingForms[0];
          form.removeAttribute("data-cuft-tracking");
        }
      }

      if (form) {
        log("jQuery submit_success event detected");
        return handleElementorSuccess(form);
      }
      return false;
    };

    if (hasRetryLogic) {
      window.cuftRetryLogic.executeWithRetry('elementor-jquery-success-event', processEvent, {
        maxAttempts: 2,
        baseDelay: 500,
        context: 'Elementor jQuery Success Handler'
      }).catch(function(error) {
        log("jQuery success handler error after retry:", error);
      });
    } else {
      try {
        processEvent();
      } catch (e) {
        log("jQuery success handler error:", e);
      }
    }
  }

  /**
   * Handle form submit to capture field values
   */
  function handleFormSubmit(event) {
    try {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return;

      // Check if this is an Elementor form - exit silently if not
      if (!isElementorForm(form)) {
        return;
      }

      // Mark form as pending for later processing
      form.setAttribute("data-cuft-tracking", "pending");

      log("Elementor form submit detected, marked for tracking");
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  /**
   * Setup MutationObserver to detect success messages
   */
  function setupMutationObserver() {
    var observerCallback = function (mutations) {
      var safeMutationProcess = hasErrorBoundary ?
        window.cuftErrorBoundary.safeExecute :
        function(fn, context) { try { return fn(); } catch (e) { log("Mutation processing error:", e); } };

      safeMutationProcess(function() {
        mutations.forEach(function (mutation) {
          if (mutation.type === "childList") {
            mutation.addedNodes.forEach(function (node) {
              if (node.nodeType === 1) { // Element node
                // Fix patterns when new forms are added
                if (node.classList && node.classList.contains("elementor-form")) {
                  fixInvalidPatterns();
                } else if (node.querySelector && node.querySelector(".elementor-form")) {
                  fixInvalidPatterns();
                }

                // Check for Elementor success message
                if (node.classList &&
                    (node.classList.contains("elementor-message-success") ||
                     node.classList.contains("elementor-form-success-message"))) {
                  log("Success message detected via MutationObserver");

                  var form = node.closest(".elementor-form");
                  if (!form && node.parentElement) {
                    form = node.parentElement.querySelector(".elementor-form");
                  }

                  if (form) {
                    handleElementorSuccess(form);
                  }
                }
              }
            });
          }
        });
      }, 'Elementor Mutation Observer Processing');
    };

    var observer;
    if (hasObserverCleanup) {
      // Use scoped observer with automatic cleanup
      observer = window.cuftObserverCleanup.createScopedObserver(
        document.body,
        observerCallback,
        {
          config: { childList: true, subtree: true },
          timeout: 300000, // 5 minutes timeout for long-lived observer
          context: 'Elementor Success Message Detection'
        }
      );
    } else {
      // Fallback to standard observer
      observer = new MutationObserver(observerCallback);
      observer.observe(document.body, {
        childList: true,
        subtree: true
      });
    }

    log("MutationObserver setup for success message detection");
    return observer;
  }

  /**
   * Setup popup event handling
   */
  function setupPopupHandling() {
    // Listen for Elementor popup hide events (after form success)
    if (window.jQuery) {
      window.jQuery(document).on('elementor/popup/hide', function(event, id, instance) {
        log("Elementor popup hide event detected");

        // Look for forms in the popup that might have been submitted
        var popup = document.querySelector('.elementor-popup-modal[data-elementor-id="' + id + '"]');
        if (popup) {
          var forms = popup.querySelectorAll('.elementor-form[data-cuft-tracking="pending"]');
          for (var i = 0; i < forms.length; i++) {
            handleElementorSuccess(forms[i]);
          }
        }
      });
    }

    // Also listen for native popup events if available
    document.addEventListener('elementor/popup/hide', function(event) {
      log("Native popup hide event detected");

      if (event.detail && event.detail.id) {
        var popup = document.querySelector('.elementor-popup-modal[data-elementor-id="' + event.detail.id + '"]');
        if (popup) {
          var forms = popup.querySelectorAll('.elementor-form[data-cuft-tracking="pending"]');
          for (var i = 0; i < forms.length; i++) {
            handleElementorSuccess(forms[i]);
          }
        }
      }
    });

    log("Popup event handling setup");
  }

  /**
   * Main setup function
   */
  function setupEventListeners() {
    var listenersSetup = [];

    // Primary: Native JavaScript CustomEvent listener (Elementor 3.5+)
    try {
      document.addEventListener("submit_success", handleNativeSuccessEvent);
      listenersSetup.push("submit_success (native)");
    } catch (e) {
      log("Could not add native submit_success listener:", e);
    }

    // Fallback: jQuery event listeners (for older Elementor versions)
    if (window.jQuery) {
      try {
        window.jQuery(document).on("submit_success", handleJQuerySuccessEvent);
        listenersSetup.push("submit_success (jQuery)");
      } catch (e) {
        log("jQuery listener setup error:", e);
      }
    }

    // Form submit handler to capture field values
    try {
      document.addEventListener("submit", handleFormSubmit);
      listenersSetup.push("form submit handler");
    } catch (e) {
      log("Submit listener setup error:", e);
    }

    // Setup mutation observer for success message detection
    try {
      setupMutationObserver();
      listenersSetup.push("mutation observer");
    } catch (e) {
      log("MutationObserver setup error:", e);
    }

    // Setup popup handling
    try {
      setupPopupHandling();
      listenersSetup.push("popup handling");
    } catch (e) {
      log("Popup handling setup error:", e);
    }

    log("Event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    var initialization = hasPerformanceMonitor ?
      window.cuftPerformanceMonitor.startMeasurement('elementor-initialization', {
        context: 'Elementor Forms System Initialization'
      }) : null;

    var safeInit = hasErrorBoundary ?
      window.cuftErrorBoundary.safeExecute :
      function(fn, context) { try { return fn(); } catch (e) { log("Initialization error:", e); return false; } };

    safeInit(function() {
      // Log utility system status
      log("Utility Systems Status:", {
        errorBoundary: hasErrorBoundary,
        performanceMonitor: hasPerformanceMonitor,
        observerCleanup: hasObserverCleanup,
        retryLogic: hasRetryLogic,
        dataLayerUtils: !!(window.cuftDataLayerUtils)
      });

      // Fix any invalid regex patterns before setting up event listeners
      fixInvalidPatterns();

      // Setup all event listeners
      setupEventListeners();

      if (initialization) initialization.end();

      log("Elementor forms tracking initialized with " +
          [hasErrorBoundary && "error boundary",
           hasPerformanceMonitor && "performance monitoring",
           hasObserverCleanup && "observer cleanup",
           hasRetryLogic && "retry logic"]
          .filter(Boolean).join(", ") + " systems");

      return true;
    }, 'Elementor Forms Initialization');
  });

})();