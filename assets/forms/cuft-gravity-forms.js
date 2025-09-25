(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT Gravity] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }

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
   * Check if form is a Gravity Forms form
   */
  function isGravityForm(form) {
    if (!form) return false;
    return form && (
      form.classList.contains("gform_form") ||
      (form.id && form.id.indexOf("gform_") === 0) ||
      form.closest(".gform_wrapper") !== null
    );
  }

  /**
   * Get field value from Gravity Forms using .gfield structure
   * Handles complex multi-part fields (name, address, etc.)
   */
  function getFieldValue(form, type) {
    // Framework detection - exit silently if not Gravity Forms
    if (!isGravityForm(form)) {
      return "";
    }

    var fields = form.querySelectorAll(".gfield");
    var field = null;

    log("Searching for " + type + " field in Gravity form with " + fields.length + " fields");

    for (var i = 0; i < fields.length; i++) {
      var fieldContainer = fields[i];
      var input = fieldContainer.querySelector("input, textarea, select");

      if (!input) continue;

      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var fieldClass = fieldContainer.className || "";
      var inputName = (input.name || "").toLowerCase();
      var inputId = (input.id || "").toLowerCase();
      var placeholder = (input.placeholder || "").toLowerCase();
      var ariaLabel = (input.getAttribute("aria-label") || "").toLowerCase();

      // Get label from field container
      var labelElement = fieldContainer.querySelector("label");
      var labelText = labelElement ? (labelElement.textContent || "").toLowerCase() : "";

      // Check for field description
      var fieldDesc = fieldContainer.querySelector(".gfield_description");
      var fieldDescText = fieldDesc ? (fieldDesc.textContent || "").toLowerCase() : "";

      log("Checking Gravity field " + i + ":", {
        fieldClass: fieldClass,
        inputType: inputType,
        inputName: inputName,
        inputId: inputId,
        placeholder: placeholder,
        labelText: labelText
      });

      if (type === "email") {
        if (
          inputType === "email" ||
          fieldClass.indexOf("gfield_email") > -1 ||
          fieldClass.indexOf("email") > -1 ||
          inputName.indexOf("email") > -1 ||
          inputName.indexOf("mail") > -1 ||
          inputId.indexOf("email") > -1 ||
          placeholder.indexOf("email") > -1 ||
          placeholder.indexOf("@") > -1 ||
          ariaLabel.indexOf("email") > -1 ||
          labelText.indexOf("email") > -1 ||
          labelText.indexOf("e-mail") > -1 ||
          labelText.indexOf("mail") > -1 ||
          fieldDescText.indexOf("email") > -1
        ) {
          field = input;
          log("Found Gravity email field:", input);
          break;
        }
      } else if (type === "phone") {
        if (
          inputType === "tel" ||
          fieldClass.indexOf("gfield_phone") > -1 ||
          fieldClass.indexOf("phone") > -1 ||
          inputName.indexOf("phone") > -1 ||
          inputName.indexOf("tel") > -1 ||
          inputName.indexOf("mobile") > -1 ||
          inputId.indexOf("phone") > -1 ||
          inputId.indexOf("tel") > -1 ||
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
          fieldDescText.indexOf("phone") > -1 ||
          fieldDescText.indexOf("mobile") > -1
        ) {
          field = input;
          log("Found Gravity phone field:", input);
          break;
        }
      }
    }

    // Handle complex multi-part fields (like name fields with multiple inputs)
    if (!field && type === "email") {
      // Look for any input that has an email value pattern
      var allInputs = form.querySelectorAll("input[type='text'], input[type='email']");
      for (var j = 0; j < allInputs.length; j++) {
        var testInput = allInputs[j];
        var value = (testInput.value || "").trim();
        if (value && window.cuftDataLayerUtils.validateEmail(value)) {
          field = testInput;
          log("Found Gravity email field by value pattern:", testInput);
          break;
        }
      }
    }

    if (!field) {
      log("No " + type + " field found in Gravity form");
      return "";
    }

    var value = (field.value || "").trim();
    log("Gravity field value for " + type + ":", value);

    return value;
  }

  /**
   * Get Gravity Forms form identification details
   */
  function getGravityFormDetails(form) {
    var formId = form.getAttribute("data-formid") ||
                 form.getAttribute("id") ||
                 form.getAttribute("data-gravity-form-id");

    // Clean up Gravity Forms ID format (remove gform_ prefix)
    if (formId && formId.indexOf("gform_") === 0) {
      formId = formId.replace("gform_", "");
    }

    // Try to get form name/title from various sources
    var formName = form.getAttribute("data-form-name") ||
                   form.getAttribute("name") ||
                   form.getAttribute("aria-label");

    // Check wrapper for additional details
    var formWrapper = form.closest('.gform_wrapper');
    if (formWrapper && !formName) {
      var formTitle = formWrapper.querySelector('.gform_title');
      if (formTitle) {
        formName = formTitle.textContent.trim();
      }
    }

    if (!formId) {
      formId = "unknown";
    }

    log("Gravity form identification:", {
      formId: formId,
      formName: formName
    });

    return {
      form_id: formId,
      form_name: formName
    };
  }

  /**
   * Check if Gravity form is in success state (confirmation message detection)
   */
  function isGravitySuccessState(form) {
    // Check for confirmation message in form
    var confirmDiv = form.querySelector(".gform_confirmation_message");
    if (confirmDiv && confirmDiv.style.display !== "none" && confirmDiv.textContent.trim()) {
      log("Gravity confirmation message found in form");
      return true;
    }

    // Check for confirmation message in wrapper
    var wrapper = form.closest(".gform_wrapper");
    if (wrapper) {
      var wrapperConfirm = wrapper.querySelector(".gform_confirmation_message");
      if (wrapperConfirm && wrapperConfirm.style.display !== "none" && wrapperConfirm.textContent.trim()) {
        log("Gravity confirmation message found in wrapper");
        return true;
      }
    }

    // Check if form is hidden after success
    if (form.style.display === "none" || !form.offsetParent) {
      if (wrapper && wrapper.querySelector(".gform_confirmation_message")) {
        log("Gravity form hidden with confirmation message");
        return true;
      }
    }

    // Check for success-related CSS classes
    if (wrapper) {
      if (wrapper.classList.contains('gform_confirmation_wrapper') ||
          wrapper.querySelector('.gform_confirmation_wrapper')) {
        log("Gravity confirmation wrapper found");
        return true;
      }
    }

    return false;
  }

  /**
   * Main Gravity Forms success handler using standardized utilities
   */
  function handleGravitySuccess(form, email, phone) {
    try {
      // Framework detection - exit silently if not Gravity Forms
      if (!isGravityForm(form)) {
        return;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(form)) {
        log("Gravity form already processed, skipping");
        return;
      }

      // Get form details
      var formDetails = getGravityFormDetails(form);

      // Validate email if present
      if (email && !window.cuftDataLayerUtils.validateEmail(email)) {
        log("Invalid email found, excluding from Gravity tracking:", email);
        email = "";
      }

      // Sanitize phone if present
      if (phone) {
        phone = window.cuftDataLayerUtils.sanitizePhone(phone);
      }

      log("Processing Gravity form submission:", {
        formId: formDetails.form_id,
        formName: formDetails.form_name,
        email: email || "not found",
        phone: phone || "not found"
      });

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission('gravity', form, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG
      });

      if (success) {
        log("Gravity form successfully tracked");
      } else {
        log("Gravity form tracking failed");
      }

    } catch (e) {
      log("Gravity success handler error:", e);
    }
  }

  /**
   * Setup MutationObserver for confirmation message detection
   */
  function observeGravitySuccess(form, email, phone) {
    var pushed = false;
    var cleanup = function () {};
    var attempts = 0;
    var maxAttempts = 8; // More attempts for Gravity Forms

    function tryPush() {
      attempts++;
      if (!pushed && isGravitySuccessState(form)) {
        pushed = true;
        handleGravitySuccess(form, email, phone);
        cleanup();
        return;
      }

      // Progressive delays for Gravity Forms confirmation detection
      if (attempts < maxAttempts && !pushed) {
        var delay;
        switch (attempts) {
          case 1: delay = 300; break;   // Quick initial check
          case 2: delay = 800; break;   // Standard response time
          case 3: delay = 1500; break;  // Slow server response
          case 4: delay = 2500; break;  // Very slow response
          default: delay = 3000; break; // Fallback delays
        }
        setTimeout(tryPush, delay);
        log("Gravity success check attempt " + attempts + ", next in " + delay + "ms");
      } else if (!pushed) {
        log("Gravity success detection timed out after " + attempts + " attempts");
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
            if (isGravitySuccessState(form)) {
              pushed = true;
              handleGravitySuccess(form, email, phone);
              cleanup();
            }
          }
        });
      });

      // Observe form and wrapper
      observer.observe(form, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["style", "class"]
      });

      var wrapper = form.closest(".gform_wrapper");
      if (wrapper && wrapper !== form) {
        observer.observe(wrapper, {
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

      log("MutationObserver setup for Gravity confirmation detection");
    }

    // Safety timeout to prevent memory leaks
    setTimeout(function () {
      if (!pushed) {
        log("Gravity success observation timed out, cleaning up");
        cleanup();
      }
    }, 20000); // 20 second maximum observation for Gravity Forms
  }

  /**
   * Handle form submit to capture field values and start observation
   */
  function handleGravityFormSubmit(event) {
    try {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return;

      // Check if this is a Gravity Forms form - exit silently if not
      if (!isGravityForm(form)) {
        return;
      }

      // Prevent multiple observations on the same form
      if (form.hasAttribute("data-cuft-gravity-observing")) {
        log("Gravity form already being observed, skipping");
        return;
      }

      form.setAttribute("data-cuft-gravity-observing", "true");

      // Capture field values at submit time
      var email = getFieldValue(form, "email");
      var phone = getFieldValue(form, "phone");

      log("Gravity form submit detected, starting success observation:", {
        formId: getGravityFormDetails(form).form_id,
        email: email || "not found",
        phone: phone || "not found"
      });

      // Start observing for success state
      observeGravitySuccess(form, email, phone);

    } catch (e) {
      log("Gravity submit handler error:", e);
    }
  }

  /**
   * Setup Gravity Forms event listeners
   */
  function setupGravityEventListeners() {
    var listenersSetup = [];

    // Primary: Form submit handler with confirmation observation
    try {
      document.addEventListener("submit", handleGravityFormSubmit, true);
      listenersSetup.push("form submit handler");
    } catch (e) {
      log("Could not add Gravity submit listener:", e);
    }

    // Optional: Gravity Forms jQuery events if available
    if (window.jQuery) {
      try {
        window.jQuery(document).on("gform_confirmation_loaded", function (event, formId) {
          log("Gravity Forms confirmation loaded for form:", formId);
          // This event could be used for more direct success detection
          // but form observation is more reliable across versions
        });
        listenersSetup.push("Gravity Forms jQuery events");
      } catch (e) {
        log("Could not setup Gravity Forms jQuery listeners:", e);
      }
    }

    log("Gravity Forms event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    setupGravityEventListeners();
    log("Gravity Forms tracking initialized using standardized dataLayer utilities");
  });

})();