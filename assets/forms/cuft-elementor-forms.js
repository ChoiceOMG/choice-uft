(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error(
      "[CUFT Elementor] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first"
    );
    return;
  }


  var DEBUG = !!(window.cuftElementor && window.cuftElementor.console_logging);

  function log() {
    if (!DEBUG) return;

    try {
      if (window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Elementor]"].concat(Array.prototype.slice.call(arguments))
        );
      }
    } catch (e) {
      // Silent failure
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

  /**
   * Check if form is an Elementor form
   */
  function isElementorForm(form) {
    if (!form) return false;

    try {
      return (
        form &&
        (form.classList.contains("elementor-form") ||
          form.closest(".elementor-widget-form") !== null ||
          form.getAttribute("data-settings") !== null)
      );
    } catch (e) {
      return false;
    }
  }

  /**
   * Get field value from form using comprehensive detection
   */
  function getFieldValue(form, type) {
    try {
      // Framework detection - exit silently if not Elementor
      if (!isElementorForm(form)) {
        return "";
      }

      var inputs;
      try {
        inputs = form.querySelectorAll("input, textarea") || [];
      } catch (e) {
        inputs = [];
      }

      var field = null;

      log(
        "Searching for " +
          type +
          " field in form with " +
          inputs.length +
          " inputs"
      );

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
        var dataValidation = (
          input.getAttribute("data-parsley-type") || ""
        ).toLowerCase();

        // Check for Elementor-specific field attributes
        var fieldType = (
          input.getAttribute("data-field-type") || ""
        ).toLowerCase();
        var originalName = (
          input.getAttribute("data-original-name") || ""
        ).toLowerCase();
        var elementorFieldType = (
          input.getAttribute("data-field") || ""
        ).toLowerCase();

        // Extract field name from Elementor's standard naming pattern: form_fields[fieldname]
        var fieldNameMatch = name.match(/form_fields\[([^\]]+)\]/);
        var extractedFieldName = fieldNameMatch
          ? fieldNameMatch[1].toLowerCase()
          : "";

        // Get the label text if available
        var labelElement = form.querySelector('label[for="' + input.id + '"]');
        var labelText = labelElement
          ? (labelElement.textContent || "").toLowerCase()
          : "";

        // Check parent container for field type clues
        var parentContainer = input.closest(".elementor-field-group");
        var parentLabel = parentContainer
          ? parentContainer.querySelector("label")
          : null;
        var parentLabelText = parentLabel
          ? (parentLabel.textContent || "").toLowerCase()
          : "";

        log("Checking input " + i + ":", {
          type: inputType,
          name: name,
          id: id,
          fieldType: fieldType,
          extractedFieldName: extractedFieldName,
          placeholder: placeholder,
          labelText: labelText || parentLabelText,
        });

        if (type === "email") {
          // Prioritize native HTML5 email validation
          if (
            inputType === "email" ||
            inputMode === "email" ||
            fieldType === "email" ||
            elementorFieldType === "email" ||
            extractedFieldName === "email" ||
            dataValidation === "email" ||
            originalName === "email" ||
            name.indexOf("email") > -1 ||
            name.indexOf("e-mail") > -1 ||
            id.indexOf("email") > -1 ||
            placeholder.indexOf("email") > -1 ||
            ariaLabel.indexOf("email") > -1 ||
            labelText.indexOf("email") > -1 ||
            parentLabelText.indexOf("email") > -1
          ) {
            field = input;
            log("Found email field:", input);
            break;
          }
        } else if (type === "phone") {
          // Use semantic field detection without pattern checking
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
            parentLabelText.indexOf("mobile") > -1
          ) {
            field = input;
            log("Found phone field:", input);
            break;
          }
        }
      }

      if (!field) {
        log("No " + type + " field found in form");
        return "";
      }

      var value;
      try {
        value = (field.value || "").trim();
      } catch (e) {
        value = "";
      }

      log("Field value for " + type + ":", value);
      return value;
    } catch (e) {
      log("Error in field extraction:", e);
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
      (elementorWidget
        ? elementorWidget.getAttribute("data-widget_type")
        : null) ||
      null;

    log("Form identification:", {
      formId: formId,
      formName: formName,
    });

    return {
      form_id: formId,
      form_name: formName,
    };
  }

  /**
   * Handle multi-step form detection (final step only)
   */
  function isFinalStepForm(form) {
    // Look for multi-step indicators
    var steps = form.querySelectorAll(".elementor-field-type-step");
    var currentStep = form.querySelector(".elementor-step-current");
    var submitButtons = form.querySelectorAll(
      'button[type="submit"], input[type="submit"]'
    );

    // If there are step elements, check if we're on the final step
    if (steps.length > 0) {
      var isLastStep = form.querySelector(
        ".elementor-step-current.elementor-step-last"
      );
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
    var popup = form.closest(".elementor-popup-modal");
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
    try {
      // Framework detection - exit silently if not Elementor
      if (!isElementorForm(form)) {
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(form)) {
        log("Form already processed, skipping");
        return false;
      }

      // Check for multi-step forms (only track final step)
      if (!isFinalStepForm(form)) {
        return false;
      }

      // Get form details
      var formDetails = getFormDetails(form);

      // Get field values
      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

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
        isPopup: isPopupForm(form),
      });

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission(
        "elementor",
        form,
        {
          form_id: formDetails.form_id,
          form_name: formDetails.form_name,
          user_email: email,
          user_phone: phone,
          debug: DEBUG,
          lead_currency: window.cuftElementor && window.cuftElementor.lead_currency ? window.cuftElementor.lead_currency : 'CAD',
          lead_value: window.cuftElementor && window.cuftElementor.lead_value ? window.cuftElementor.lead_value : 100,
        }
      );

      if (success) {
        log("Elementor form successfully tracked");
        return true;
      } else {
        log("Elementor form tracking failed");
        return false;
      }
    } catch (e) {
      log("Form processing error:", e);
      return false;
    }
  }

  /**
   * Handle native submit_success event (Elementor 3.5+)
   */
  function handleNativeSuccessEvent(event) {
    var processEvent = function () {
      var form = event.target && event.target.closest(".elementor-form");
      if (!form) {
        // Try to find the most recently submitted Elementor form
        var elementorForms = document.querySelectorAll('.elementor-form');
        for (var i = 0; i < elementorForms.length; i++) {
          var candidateForm = elementorForms[i];
          if (isElementorForm(candidateForm)) {
            form = candidateForm;
            break;
          }
        }
      }

      if (form) {
        log("Native submit_success event detected");
        return handleElementorSuccess(form);
      }
      return false;
    };

    try {
      processEvent();
    } catch (e) {
      log("Native success handler error:", e);
    }
  }

  /**
   * Handle jQuery submit_success event (legacy support)
   */
  function handleJQuerySuccessEvent(event) {
    var processEvent = function () {
      var form = null;

      if (event.target) {
        form = event.target.closest(".elementor-form");
      }

      if (!form) {
        // Try to find the most recently submitted Elementor form
        var elementorForms = document.querySelectorAll('.elementor-form');
        for (var i = 0; i < elementorForms.length; i++) {
          var candidateForm = elementorForms[i];
          if (isElementorForm(candidateForm)) {
            form = candidateForm;
            break;
          }
        }
      }

      if (form) {
        log("jQuery submit_success event detected");
        return handleElementorSuccess(form);
      }
      return false;
    };

    try {
      processEvent();
    } catch (e) {
      log("jQuery success handler error:", e);
    }
  }

  // REMOVED: handleFormSubmit function was interfering with Elementor's form validation
  // We now rely solely on submit_success events which fire after successful validation

  /**
   * Setup MutationObserver to detect success messages
   */
  function setupMutationObserver() {
    var observerCallback = function (mutations) {
      try {
        mutations.forEach(function (mutation) {
          if (mutation.type === "childList") {
            mutation.addedNodes.forEach(function (node) {
              if (node.nodeType === 1) {
                // Element node
                // Check for new Elementor forms (pattern fixing removed - using native validation)

                // Check for Elementor success message
                if (
                  node.classList &&
                  (node.classList.contains("elementor-message-success") ||
                    node.classList.contains("elementor-form-success-message"))
                ) {
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
      } catch (e) {
        log("Mutation processing error:", e);
      }
    };

    var observer = new MutationObserver(observerCallback);
    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

    log("MutationObserver setup for success message detection");
    return observer;
  }

  /**
   * Setup popup event handling
   */
  function setupPopupHandling() {
    // Listen for Elementor popup hide events (after form success)
    if (window.jQuery) {
      window
        .jQuery(document)
        .on("elementor/popup/hide", function (event, id, instance) {
          log("Elementor popup hide event detected");

          // Look for forms in the popup that might have been submitted
          var popup = document.querySelector(
            '.elementor-popup-modal[data-elementor-id="' + id + '"]'
          );
          if (popup) {
            var forms = popup.querySelectorAll('.elementor-form');
            for (var i = 0; i < forms.length; i++) {
              if (isElementorForm(forms[i])) {
                handleElementorSuccess(forms[i]);
              }
            }
          }
        });
    }

    // Also listen for native popup events if available
    document.addEventListener("elementor/popup/hide", function (event) {
      log("Native popup hide event detected");

      if (event.detail && event.detail.id) {
        var popup = document.querySelector(
          '.elementor-popup-modal[data-elementor-id="' + event.detail.id + '"]'
        );
        if (popup) {
          var forms = popup.querySelectorAll('.elementor-form');
          for (var i = 0; i < forms.length; i++) {
            if (isElementorForm(forms[i])) {
              handleElementorSuccess(forms[i]);
            }
          }
        }
      }
    });

    log("Popup event handling setup");
  }

  /**
   * Fix invalid pattern attributes on Elementor forms
   */
  function fixElementorPatterns() {
    try {
      var inputs = document.querySelectorAll('.elementor-form input[pattern]');

      if (inputs.length === 0) {
        log("No Elementor forms with patterns found");
        return;
      }

      var fixCount = 0;
      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var pattern = input.getAttribute('pattern');

        if (!pattern) continue;

        // Fix the common invalid phone pattern
        if (pattern === '[0-9()#&+*-=.]+') {
          // Move hyphen to end to make it literal, not a range
          input.setAttribute('pattern', '[0-9()#&+*=.-]+');
          fixCount++;
          log('Fixed invalid phone pattern on field:', input.name || input.id || 'unnamed');
        }

        // Add more pattern fixes as discovered
        // Test if pattern is valid by trying to create RegExp
        try {
          new RegExp(pattern);
        } catch (e) {
          log('Found another invalid pattern:', pattern, 'on field:', input.name || input.id);
          // Could add more specific fixes here
        }
      }

      if (fixCount > 0) {
        log('Fixed ' + fixCount + ' invalid pattern(s) on Elementor forms');
      }

    } catch (e) {
      log('Error fixing Elementor patterns:', e);
    }
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

    // REMOVED: Form submit handler was interfering with Elementor's validation
    // Now relying only on success events which fire after validation passes

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
    try {
      // Fix invalid pattern attributes before any form interactions
      fixElementorPatterns();

      // Setup all event listeners
      setupEventListeners();

      log("Elementor forms tracking initialized");
    } catch (e) {
      log("Initialization error:", e);
    }
  });
})();
