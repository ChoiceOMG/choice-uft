(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT Avada] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }


  var DEBUG = !!(window.cuftAvada && window.cuftAvada.console_logging);

  function log() {
    if (!DEBUG) return;

    try {
      if (window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Avada]"].concat(Array.prototype.slice.call(arguments))
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
   * Check if form is an Avada/Fusion form
   */
  function isAvadaForm(form) {
    if (!form) return false;

    try {
      return form && (
        form.classList.contains("fusion-form") ||
        form.classList.contains("avada-form") ||
        form.className.indexOf("fusion-form") > -1 ||
        form.id.indexOf("avada") > -1 ||
        form.closest('.fusion-form-wrapper') !== null
      );
    } catch (e) {
      return false;
    }
  }

  /**
   * Get field value from Avada/Fusion forms with dynamic form loading support
   */
  function getFieldValue(form, type) {
    try {
      // Framework detection - exit silently if not Avada
      if (!isAvadaForm(form)) {
        return "";
      }

      var inputs;
      try {
        inputs = form.querySelectorAll("input, textarea, select") || [];
      } catch (e) {
        inputs = [];
      }

      var field = null;

      log("Searching for " + type + " field in Avada form with " + inputs.length + " inputs");

    for (var i = 0; i < inputs.length; i++) {
      var input = inputs[i];

      // Skip hidden inputs
      if (input.type === "hidden") continue;

      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var inputMode = (input.getAttribute("inputmode") || "").toLowerCase();
      var dataValidate = (
        (input.getAttribute("data-validate") ||
          input.getAttribute("data-validation") ||
          "") + ""
      ).toLowerCase();
      var pattern = input.getAttribute("pattern") || "";
      var name = (input.name || "").toLowerCase();
      var id = (input.id || "").toLowerCase();
      var className = (input.className || "").toLowerCase();
      var placeholder = (input.placeholder || "").toLowerCase();
      var ariaLabel = (input.getAttribute("aria-label") || "").toLowerCase();

      // Get the label text if available
      var labelElement = form.querySelector('label[for="' + input.id + '"]');
      var labelText = labelElement ? (labelElement.textContent || "").toLowerCase() : "";

      // Check parent container for field clues
      var parentElement = input.parentNode;
      var parentClass = parentElement ? (parentElement.className || "").toLowerCase() : "";

      log("Checking Avada input " + i + ":", {
        inputType: inputType,
        name: name,
        id: id,
        className: className,
        placeholder: placeholder,
        labelText: labelText,
        parentClass: parentClass
      });

      if (type === "email") {
        if (
          inputType === "email" ||
          inputMode === "email" ||
          dataValidate.indexOf("email") > -1 ||
          name.indexOf("email") > -1 ||
          name.indexOf("e-mail") > -1 ||
          name.indexOf("mail") > -1 ||
          id.indexOf("email") > -1 ||
          id.indexOf("e-mail") > -1 ||
          className.indexOf("email") > -1 ||
          placeholder.indexOf("email") > -1 ||
          placeholder.indexOf("e-mail") > -1 ||
          placeholder.indexOf("@") > -1 ||
          ariaLabel.indexOf("email") > -1 ||
          labelText.indexOf("email") > -1 ||
          labelText.indexOf("e-mail") > -1 ||
          labelText.indexOf("mail") > -1 ||
          parentClass.indexOf("email") > -1 ||
          (pattern && pattern.indexOf("@") > -1)
        ) {
          field = input;
          log("Found Avada email field:", input);
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
          dataValidate.indexOf("phone") > -1 ||
          dataValidate.indexOf("tel") > -1 ||
          dataValidate.indexOf("number") > -1 ||
          name.indexOf("phone") > -1 ||
          name.indexOf("tel") > -1 ||
          name.indexOf("mobile") > -1 ||
          name.indexOf("number") > -1 ||
          id.indexOf("phone") > -1 ||
          id.indexOf("tel") > -1 ||
          id.indexOf("mobile") > -1 ||
          className.indexOf("phone") > -1 ||
          className.indexOf("tel") > -1 ||
          placeholder.indexOf("phone") > -1 ||
          placeholder.indexOf("mobile") > -1 ||
          placeholder.indexOf("tel") > -1 ||
          placeholder.indexOf("number") > -1 ||
          placeholder.indexOf("(") > -1 ||
          ariaLabel.indexOf("phone") > -1 ||
          ariaLabel.indexOf("mobile") > -1 ||
          labelText.indexOf("phone") > -1 ||
          labelText.indexOf("mobile") > -1 ||
          labelText.indexOf("tel") > -1 ||
          labelText.indexOf("number") > -1 ||
          parentClass.indexOf("phone") > -1 ||
          parentClass.indexOf("tel") > -1 ||
          hasNumberPattern
        ) {
          field = input;
          log("Found Avada phone field:", input);
          break;
        }
      }
    }

    if (!field) {
      log("No " + type + " field found in Avada form");
      return "";
    }

    var value;
    try {
      value = (field.value || "").trim();
    } catch (e) {
      value = "";
    }

    log("Avada field value for " + type + ":", value);
    return value;

    } catch (e) {
      log("Error in Avada field extraction:", e);
      return "";
    }
  }

  /**
   * Get Avada/Fusion form identification details
   */
  function getAvadaFormDetails(form) {
    var formId = form.getAttribute("id") ||
                 form.getAttribute("data-form-id") ||
                 form.getAttribute("data-avada-form-id");

    var formName = form.getAttribute("name") ||
                   form.getAttribute("data-form-name") ||
                   form.getAttribute("aria-label");

    // Check wrapper for additional details
    var formWrapper = form.closest('.fusion-form-wrapper, .avada-form-wrapper');
    if (formWrapper && !formName) {
      formName = formWrapper.getAttribute("data-form-name") ||
                 formWrapper.getAttribute("data-form-title");
    }

    if (!formId) {
      formId = "unknown";
    }

    log("Avada form identification:", {
      formId: formId,
      formName: formName
    });

    return {
      form_id: formId,
      form_name: formName
    };
  }

  /**
   * Check if Avada form is in success state with exponential backoff
   */
  function isAvadaSuccessState(form) {
    var successSelectors = [
      ".fusion-form-response-success",
      ".fusion-alert.success",
      ".fusion-form-success",
      ".fusion-success",
      ".avada-form-success",
      ".fusion-form-success-message",
      '[data-status="sent"]',
      '[data-avada-form-status="success"]'
    ];

    // Check for success elements in form
    for (var i = 0; i < successSelectors.length; i++) {
      var element = form.querySelector(successSelectors[i]);
      if (element && element.style.display !== "none") {
        log("Avada success state detected with selector:", successSelectors[i]);
        return true;
      }
    }

    // Check if form is hidden with success message in parent
    if (!form.offsetParent) {
      var parent = form.parentNode;
      if (parent && parent.querySelector('.thank-you, .success, [role="alert"]')) {
        log("Avada success state: form hidden with success element");
        return true;
      }
    }

    // Check for success-related CSS classes
    var hasSuccessClass =
      form.classList.contains("sent") ||
      form.classList.contains("is-success") ||
      form.classList.contains("form-success") ||
      form.classList.contains("successfully-submitted") ||
      form.classList.contains("fusion-form-response-success");

    if (hasSuccessClass) {
      log("Avada success state: form has success class");
      return true;
    }

    // Check parent container for success class
    var formParent = form.parentNode;
    if (formParent && formParent.classList &&
        formParent.classList.contains("fusion-form-response-success")) {
      log("Avada success state: parent has success class");
      return true;
    }

    // Check form container for success message
    var container = form.closest(".fusion-form-wrapper, .avada-form-wrapper");
    if (container && container.querySelector(
        ".fusion-form-response-success, .fusion-success, .success-message, .thank-you"
      )) {
      log("Avada success state: success message in container");
      return true;
    }

    return false;
  }

  /**
   * Main Avada Forms success handler using standardized utilities
   */
  function handleAvadaSuccess(form, email, phone) {
    try {
      // Framework detection - exit silently if not Avada
      if (!isAvadaForm(form)) {
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(form)) {
        log("Avada form already processed, skipping");
        return false;
      }

      // Get form details
      var formDetails = getAvadaFormDetails(form);

      // Validate email if present
      if (email && !window.cuftDataLayerUtils.validateEmail(email)) {
        log("Invalid email found, excluding from Avada tracking:", email);
        email = "";
      }

      // Sanitize phone if present
      if (phone) {
        phone = window.cuftDataLayerUtils.sanitizePhone(phone);
      }

      log("Processing Avada form submission:", {
        formId: formDetails.form_id,
        formName: formDetails.form_name,
        email: email || "not found",
        phone: phone || "not found"
      });

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission('avada', form, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG
      });

      if (success) {
        log("Avada form successfully tracked");
        return true;
      } else {
        log("Avada form tracking failed");
        return false;
      }
    } catch (e) {
      log("Avada form processing error:", e);
      return false;
    }
  }

  /**
   * Setup MutationObserver for success detection with exponential backoff
   */
  function observeAvadaSuccess(form, email, phone) {
    log("Starting Avada success observation for form:", form.id || "unnamed");

    var observerConfig = {
      id: 'avada-success-observer',
      element: form,
      timeout: 25000, // 25 second timeout for dynamic loading
      context: 'Avada Success Detection',
      description: 'Observing form for dynamic success state changes'
    };

    var cleanup = function() {};

    var pushed = false;
    var attempts = 0;
    var maxAttempts = 10; // More attempts for dynamic loading

    function tryPush() {
      attempts++;
      log("Avada success check attempt " + attempts + " for form:", form.id || "unnamed");

      if (!pushed && isAvadaSuccessState(form)) {
        pushed = true;
        log("Avada success state confirmed, tracking submission");
        handleAvadaSuccess(form, email, phone);
        cleanup();
        return;
      }

      // Exponential backoff with progressive delays for dynamic loading
      if (attempts < maxAttempts && !pushed) {
        var delay;
        switch (attempts) {
          case 1: delay = 200; break;   // Very quick check
          case 2: delay = 500; break;   // Quick check
          case 3: delay = 1000; break;  // Standard check
          case 4: delay = 2000; break;  // Slower check
          case 5: delay = 3000; break;  // Even slower
          case 6: delay = 4000; break;  // Very slow loading
          default: delay = 5000; break; // Maximum delay
        }
        setTimeout(tryPush, delay);
        log("Avada next success check in " + delay + "ms");
      } else if (!pushed) {
        log("Avada success detection timed out after " + attempts + " attempts");
        cleanup();
      }
    }

    // Initial quick check
    setTimeout(tryPush, 50);

    // Set up MutationObserver for real-time detection
    if (window.MutationObserver) {
      var observer = new MutationObserver(function(mutations) {
        if (pushed) return;

        mutations.forEach(function(mutation) {
          if (mutation.type === "childList" || mutation.type === "attributes") {
            if (isAvadaSuccessState(form)) {
              pushed = true;
              handleAvadaSuccess(form, email, phone);
              cleanup();
            }
          }
        });
      });

      // Observe form and its containers for dynamic content loading
      observer.observe(form, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["class", "style", "data-status"]
      });

      var container = form.closest(".fusion-form-wrapper, .avada-form-wrapper");
      if (container && container !== form) {
        observer.observe(container, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ["class", "style", "data-status"]
        });
      }

      // Also observe parent node for success messages
      if (form.parentNode) {
        observer.observe(form.parentNode, {
          childList: true,
          subtree: true,
          attributes: true,
          attributeFilter: ["class", "style"]
        });
      }

      var originalCleanup = cleanup;
      cleanup = function () {
        observer.disconnect();
        originalCleanup();
      };

      log("MutationObserver setup for Avada dynamic success detection");
    }

    // Observer cleanup handles safety timeout automatically
    // Initial cleanup timeout as fallback
    setTimeout(function () {
      if (!pushed) {
        log("Avada success observation timed out, cleaning up");
        cleanup();
      }
    }, 25000); // 25 second maximum observation for dynamic loading
  }

  /**
   * Handle form submit to capture field values and start observation
   */
  function handleAvadaFormSubmit(event) {
    var processEvent = function() {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return false;

      // Check if this is an Avada form - exit silently if not
      if (!isAvadaForm(form)) {
        return false;
      }

      // Check if form has email field (contact forms only, not search forms)
      var hasEmailField = getFieldValue(form, "email") !== "";
      if (!hasEmailField) {
        log("Avada form has no email field, skipping (likely search form)");
        return;
      }

      // Prevent multiple observations on the same form
      if (form.hasAttribute("data-cuft-avada-observing")) {
        log("Avada form already being observed, skipping");
        return;
      }

      form.setAttribute("data-cuft-avada-observing", "true");

      // Capture field values at submit time
      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      log("Avada form submit detected, starting success observation:", {
        formId: getAvadaFormDetails(form).form_id,
        email: email || "not found",
        phone: phone || "not found"
      });

      // Start observing for success state
      observeAvadaSuccess(form, email, phone);
      return true;
    };

    try {
      processEvent();
    } catch (e) {
      log("Avada submit handler error:", e);
    }
  }

  /**
   * Watch for dynamically loaded Avada forms and AJAX submissions
   */
  function watchAvadaAjaxForms() {
    var fusionForms = document.querySelectorAll(".fusion-form");

    // Filter out test forms to avoid processing them with production code
    var productionForms = [];
    for (var i = 0; i < fusionForms.length; i++) {
      if (!fusionForms[i].hasAttribute('data-cuft-test-form')) {
        productionForms.push(fusionForms[i]);
      }
    }

    // Only log if there are production forms to process
    if (productionForms.length > 0) {
      log("Found " + productionForms.length + " Avada/Fusion forms, checking for email fields");
    }

    fusionForms = productionForms; // Process only production forms

    for (var i = 0; i < fusionForms.length; i++) {
      var form = fusionForms[i];
      if (form.hasAttribute("data-cuft-avada-ajax-watching")) continue;

      // Check if form has email field (contact forms only)
      var hasEmailField = getFieldValue(form, "email") !== "";
      if (!hasEmailField) {
        log("Avada form has no email field, skipping AJAX watch");
        continue;
      }

      form.setAttribute("data-cuft-avada-ajax-watching", "true");
      log("Setting up AJAX watcher for Avada form:", form.id || form.className);

      // Watch for submit button clicks (for AJAX submissions)
      var submitButtons = form.querySelectorAll(
        'input[type="submit"], button[type="submit"], .fusion-button'
      );

      for (var j = 0; j < submitButtons.length; j++) {
        var button = submitButtons[j];
        button.addEventListener("click", function (event) {
          var clickedForm = event.target.closest(".fusion-form");
          if (clickedForm && !clickedForm.hasAttribute("data-cuft-avada-observing")) {
            // Framework detection - exit silently if not Avada
            if (!isAvadaForm(clickedForm)) {
              return;
            }
            log("Avada submit button clicked, starting observation");
            clickedForm.setAttribute("data-cuft-avada-observing", "true");

            setTimeout(function () {
              var email = getFieldValue(clickedForm, "email");
              var phone = getFieldValue(clickedForm, "phone");
              observeAvadaSuccess(clickedForm, email, phone);
            }, 100);
          }
        });
      }
    }
  }

  /**
   * Setup Avada Forms event listeners
   */
  function setupAvadaEventListeners() {
    var listenersSetup = [];

    // Primary: Form submit handler with success observation
    try {
      document.addEventListener("submit", handleAvadaFormSubmit, true);
      listenersSetup.push("form submit handler");
    } catch (e) {
      log("Could not add Avada submit listener:", e);
    }

    // Setup AJAX form watchers for dynamically loaded forms
    try {
      watchAvadaAjaxForms();
      listenersSetup.push("AJAX form watchers");
    } catch (e) {
      log("Could not setup Avada AJAX watchers:", e);
    }

    // Watch for dynamically added forms
    if (window.MutationObserver) {
      try {
        var documentObserver = new MutationObserver(function (mutations) {
          var shouldRewatch = false;
          mutations.forEach(function (mutation) {
            if (mutation.type === "childList") {
              for (var i = 0; i < mutation.addedNodes.length; i++) {
                var node = mutation.addedNodes[i];
                if (node.nodeType === 1 &&
                    (node.classList.contains("fusion-form") ||
                     node.querySelector(".fusion-form"))) {
                  shouldRewatch = true;
                  break;
                }
              }
            }
          });
          if (shouldRewatch) {
            log("New Avada forms detected, re-running AJAX watchers");
            watchAvadaAjaxForms();
          }
        });

        documentObserver.observe(document.body, {
          childList: true,
          subtree: true
        });
        listenersSetup.push("dynamic form observer");
      } catch (e) {
        log("Could not setup dynamic form observer:", e);
      }
    }

    log("Avada Forms event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    setupAvadaEventListeners();
    log("Avada Forms tracking initialized using standardized dataLayer utilities");
  });

})();