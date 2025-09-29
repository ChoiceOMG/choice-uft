(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT Ninja] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }


  var DEBUG = !!(window.cuftNinja && window.cuftNinja.console_logging);

  function log() {
    if (!DEBUG) return;
    try {
      if (window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Ninja]"].concat(Array.prototype.slice.call(arguments))
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
   * Check if form is a Ninja Forms form
   */
  function isNinjaForm(form) {
    if (!form) return false;

    try {
      return form && (
        form.closest('.nf-form-cont') !== null ||
        form.classList.contains('nf-form') ||
        form.querySelector('.nf-field') !== null ||
        form.closest('.nf-form-wrap') !== null
      );
    } catch (e) {
      return false;
    }
  }

  /**
   * Get field value from Ninja Forms using multiple detection strategies
   */
  function getFieldValue(form, type) {
    try {
      // Framework detection - exit silently if not Ninja Forms
      if (!isNinjaForm(form)) {
        return "";
      }

      var field = null;

      // Strategy 1: Direct input selection by autocomplete attribute and type
      if (type === "email") {
        try {
          // Try autocomplete="email" first (most reliable for email fields)
          var directEmail = form.querySelector('input[autocomplete="email"]') ||
                           form.querySelector('input[type="email"]') ||
                           form.querySelector('input[name="email"]');
          if (directEmail && directEmail.value) {
            log("✓ Found email field via direct selector, value:", directEmail.value);
            return (directEmail.value || "").trim();
          }
        } catch (e) {
          log("Direct email selector failed:", e);
        }
      } else if (type === "phone") {
        try {
          // Try autocomplete="tel" or type="tel" first
          var directPhone = form.querySelector('input[autocomplete="tel"]') ||
                           form.querySelector('input[type="tel"]') ||
                           form.querySelector('input[name*="phone"]') ||
                           form.querySelector('input[name*="tel"]');
          if (directPhone && directPhone.value) {
            log("✓ Found phone field via direct selector, value:", directPhone.value);
            return (directPhone.value || "").trim();
          }
        } catch (e) {
          log("Direct phone selector failed:", e);
        }
      }

      // Strategy 2: Look for Ninja Forms field containers and inputs
      var fields;
      try {
        // Try multiple class selectors for Ninja Forms fields
        fields = form.querySelectorAll(".nf-field, .ninja-forms-field, .nf-element") || [];
      } catch (e) {
        fields = [];
      }

      log("Searching for " + type + " field in Ninja form with " + fields.length + " fields");

      for (var i = 0; i < fields.length; i++) {
        var fieldContainer = fields[i];

        // Check if this element IS an input (not a container)
        var input = null;
        if (fieldContainer.tagName === 'INPUT' || fieldContainer.tagName === 'TEXTAREA' || fieldContainer.tagName === 'SELECT') {
          input = fieldContainer;
        } else {
          // It's a container, look for input inside
          input = fieldContainer.querySelector("input, textarea, select");
        }

        if (!input) continue;

      var fieldType = fieldContainer.getAttribute("data-field-type") || "";
      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var inputMode = (input.getAttribute("inputmode") || "").toLowerCase();
      var autocomplete = (input.getAttribute("autocomplete") || "").toLowerCase();
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
        autocomplete: autocomplete,
        name: name,
        id: id,
        className: className,
        placeholder: placeholder,
        labelText: labelText || fieldTitleText
      });

      if (type === "email") {
        var pattern = input.getAttribute("pattern") || "";
        if (
          autocomplete === "email" ||
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
          autocomplete === "tel" ||
          autocomplete.indexOf("phone") > -1 ||
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
      return "";
    }

    var value;
    try {
      value = (field.value || "").trim();
    } catch (e) {
      value = "";
    }

    log("Ninja field value for " + type + ":", value);
    return value;

    } catch (e) {
      log("Error in Ninja field extraction:", e);
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
    try {
      // Framework detection - exit silently if not Ninja Forms
      if (!isNinjaForm(form)) {
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(form)) {
        log("Ninja form already processed, skipping");
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

      // Test mode: Add click IDs if on test page and none exist
      var isTestMode = window.location.pathname.indexOf('-test-form') > -1 ||
                       window.location.search.indexOf('test=1') > -1 ||
                       window.location.search.indexOf('cuft_test=1') > -1;

      if (isTestMode && email && phone) {
        try {
          // Check if tracking data already has click IDs
          var currentTracking = window.cuftGetTrackingData ? window.cuftGetTrackingData() : {};
          var hasClickId = currentTracking.click_id || currentTracking.gclid ||
                          currentTracking.fbclid || currentTracking.wbraid || currentTracking.gbraid;

          if (!hasClickId) {
            // Store test click ID in sessionStorage for generate_lead testing
            var testData = {
              tracking: {
                click_id: 'test_ninja_' + Date.now(),
                gclid: 'test_gclid_ninja_' + formDetails.form_id,
                fbclid: 'test_fbclid_ninja_' + formDetails.form_id,
                utm_source: 'test_ninja',
                utm_medium: 'test_form',
                utm_campaign: 'ninja_forms_test',
                utm_term: 'ninja_test',
                utm_content: 'form_test'
              },
              timestamp: Date.now()
            };

            try {
              sessionStorage.setItem('cuft_tracking_data', JSON.stringify(testData));
              log('Test mode: Added test tracking data for generate_lead testing');
              log('Test tracking data:', testData.tracking);
            } catch (storageError) {
              log('Test mode: Could not store test data in sessionStorage:', storageError);
            }
          } else {
            log('Test mode: Click IDs already exist, using existing tracking data');
          }
        } catch (e) {
          log('Test mode: Error adding test tracking data:', e);
        }
      }

      // Use standardized tracking function
      var success = window.cuftDataLayerUtils.trackFormSubmission('ninja', form, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG,
        lead_currency: window.cuftNinja && window.cuftNinja.lead_currency ? window.cuftNinja.lead_currency : 'CAD',
        lead_value: window.cuftNinja && window.cuftNinja.lead_value ? window.cuftNinja.lead_value : 100,
      });

      if (success) {
        log("Ninja form successfully tracked");
        return true;
      } else {
        log("Ninja form tracking failed");
        return false;
      }
    } catch (e) {
      log("Ninja form processing error:", e);
      return false;
    }
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

    var cleanup = function() {};

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
   * Store for capturing form data before submission
   */
  var ninjaFormData = {};

  /**
   * Handle Ninja Forms API before submit to capture field values
   */
  function handleNinjaBeforeSubmit(formModel) {
    try {
      // Handle different API parameter formats
      var formId, fields;

      if (formModel && typeof formModel.get === 'function') {
        // Backbone model format
        formId = formModel.get('id');
        fields = formModel.get('fields');
      } else if (formModel && formModel.id) {
        // Plain object format
        formId = formModel.id;
        fields = formModel.fields;
      } else {
        log("Ninja Forms before submit - unrecognized formModel format:", formModel);
        return;
      }

      log("Ninja Forms before submit - capturing field data for form:", formId);

      var email = "";
      var phone = "";
      var formElement = null;

      // Try to find the form element or container
      // Ninja Forms uses div containers, not <form> tags
      var formContainers = document.querySelectorAll('.nf-form-cont, .nf-form-wrap');
      log("Looking for Ninja Forms container for form ID:", formId, "- found", formContainers.length, "containers");

      for (var i = 0; i < formContainers.length; i++) {
        var container = formContainers[i];
        var containerId = container.id || "";
        var dataFormId = container.getAttribute('data-form-id');

        log("Checking container " + i + ":", {
          id: containerId,
          dataFormId: dataFormId,
          matchesFormId: containerId.indexOf(formId) > -1 || dataFormId == formId
        });

        if (containerId.indexOf(formId) > -1 || dataFormId == formId) {
          // Found the container, now look for actual form or use container
          var actualForm = container.querySelector('form');
          formElement = actualForm || container;
          log("✓ Found Ninja Forms container/form:", formElement.tagName, formElement.id || formElement.className);
          break;
        }
      }

      if (!formElement) {
        log("⚠ No form container found, will rely purely on API field extraction");
      }

      // Extract field values from Ninja Forms fields collection
      if (fields) {
        log("Extracting fields from API - fields type:", typeof fields, "has models:", !!fields.models);

        if (fields.models && Array.isArray(fields.models)) {
          // Backbone collection with models
          log("Processing", fields.models.length, "field models from Backbone collection");

          fields.models.forEach(function(field, index) {
            var fieldType, fieldValue, fieldKey, fieldId;

            if (typeof field.get === 'function') {
              fieldType = field.get('type');
              fieldValue = field.get('value');
              fieldKey = field.get('key');
              fieldId = field.get('id');

              log("Field " + index + " (Backbone model):", {
                id: fieldId,
                key: fieldKey,
                type: fieldType,
                value: fieldValue ? (fieldValue.substring ? fieldValue.substring(0, 30) : fieldValue) : "(empty)"
              });
            } else {
              fieldType = field.type;
              fieldValue = field.value;
              fieldKey = field.key;
              fieldId = field.id;

              log("Field " + index + " (plain object):", {
                id: fieldId,
                key: fieldKey,
                type: fieldType,
                value: fieldValue ? (fieldValue.substring ? fieldValue.substring(0, 30) : fieldValue) : "(empty)"
              });
            }

            if (fieldType === 'email' || (fieldKey && fieldKey.indexOf('email') > -1)) {
              email = fieldValue || "";
              log("✓ Email field found:", fieldKey, "value:", email ? email.substring(0, 30) : "(empty)");
            } else if (fieldType === 'phone' || (fieldKey && fieldKey.indexOf('phone') > -1)) {
              phone = fieldValue || "";
              log("✓ Phone field found:", fieldKey, "value:", phone || "(empty)");
            }
          });
        } else if (Array.isArray(fields)) {
          // Array of field objects
          log("Processing", fields.length, "fields from array");

          fields.forEach(function(field, index) {
            var fieldType = field.type || "";
            var fieldValue = field.value || "";
            var fieldKey = field.key || "";

            log("Field " + index + ":", {
              key: fieldKey,
              type: fieldType,
              value: fieldValue ? (fieldValue.substring ? fieldValue.substring(0, 30) : fieldValue) : "(empty)"
            });

            if (fieldType === 'email' || fieldKey.indexOf('email') > -1) {
              email = fieldValue;
              log("✓ Email field found:", fieldKey, "value:", email ? email.substring(0, 30) : "(empty)");
            } else if (fieldType === 'phone' || fieldKey.indexOf('phone') > -1) {
              phone = fieldValue;
              log("✓ Phone field found:", fieldKey, "value:", phone || "(empty)");
            }
          });
        } else {
          // Try iterating as object
          log("Processing fields as object");
          var fieldCount = 0;

          for (var key in fields) {
            if (fields.hasOwnProperty(key)) {
              var field = fields[key];
              if (field && typeof field === 'object') {
                fieldCount++;
                var fieldType = field.type || "";
                var fieldValue = field.value || "";
                var fieldKey = field.key || key;

                log("Field " + fieldCount + " (key: " + key + "):", {
                  key: fieldKey,
                  type: fieldType,
                  value: fieldValue ? (fieldValue.substring ? fieldValue.substring(0, 30) : fieldValue) : "(empty)"
                });

                if (fieldType === 'email' || fieldKey.indexOf('email') > -1) {
                  email = fieldValue;
                  log("✓ Email field found:", fieldKey, "value:", email ? email.substring(0, 30) : "(empty)");
                } else if (fieldType === 'phone' || fieldKey.indexOf('phone') > -1) {
                  phone = fieldValue;
                  log("✓ Phone field found:", fieldKey, "value:", phone || "(empty)");
                }
              }
            }
          }
          log("Total fields processed from object:", fieldCount);
        }
      }

      // Fallback: try to get values from DOM if API extraction failed
      if (formElement && (!email || !phone)) {
        log("Attempting DOM fallback extraction - current values:", {
          email: email || "empty",
          phone: phone || "empty"
        });

        var domEmail = getFieldValue(formElement, "email");
        var domPhone = getFieldValue(formElement, "phone");

        log("DOM extraction results:", {
          email: domEmail || "not found",
          phone: domPhone || "not found"
        });

        if (!email && domEmail) {
          email = domEmail;
          log("✓ Email set from DOM fallback:", email);
        }
        if (!phone && domPhone) {
          phone = domPhone;
          log("✓ Phone set from DOM fallback:", phone);
        }
      }

      // Store the data for later use
      ninjaFormData[formId] = {
        formElement: formElement,
        email: email,
        phone: phone,
        formId: formId
      };

      log("Ninja Forms field data captured:", {
        formId: formId,
        email: email || "not found",
        phone: phone || "not found",
        hasFormElement: !!formElement
      });

    } catch (e) {
      log("Error in Ninja Forms before submit handler:", e);
    }
  }

  /**
   * Handle Ninja Forms API submit response (successful submission)
   */
  function handleNinjaSubmitResponse(response, formModel) {
    try {
      // Handle different API parameter formats for formModel
      var formId;

      if (formModel && typeof formModel.get === 'function') {
        // Backbone model format
        formId = formModel.get('id');
      } else if (formModel && formModel.id) {
        // Plain object format
        formId = formModel.id;
      } else if (response && response.form_id) {
        // Form ID might be in response
        formId = response.form_id;
      } else {
        // Try to find any stored form data and use the first one
        var storedKeys = Object.keys(ninjaFormData);
        if (storedKeys.length > 0) {
          formId = storedKeys[0];
          log("Ninja Forms using fallback form ID from stored data:", formId);
        } else {
          log("Ninja Forms submit response - cannot determine form ID. formModel:", formModel, "response:", response);
          return;
        }
      }

      var storedData = ninjaFormData[formId];

      log("Ninja Forms submit response received for form:", formId);
      log("Response data:", response);

      if (!storedData) {
        log("No stored form data found for form:", formId);
        return;
      }

      // Check if submission was successful
      var isSuccess = response && (
        response.success === true ||
        response.data ||
        !response.errors ||
        (response.errors && response.errors.length === 0)
      );

      if (isSuccess) {
        log("Ninja Forms submission successful, processing tracking...");

        // Process the successful form submission
        var success = handleNinjaSuccess(
          storedData.formElement,
          storedData.email,
          storedData.phone
        );

        if (success) {
          log("Ninja Forms tracking completed successfully");
        } else {
          log("Ninja Forms tracking failed");
        }
      } else {
        log("Ninja Forms submission failed, not tracking:", response.errors || response);
      }

      // Clean up stored data
      delete ninjaFormData[formId];

    } catch (e) {
      log("Error in Ninja Forms submit response handler:", e);
    }
  }

  /**
   * Legacy fallback: Handle traditional form submit events
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

      log("Ninja form submit detected (fallback method), starting success observation:", {
        formId: form.getAttribute("data-form-id") || form.getAttribute("id") || "unknown",
        email: email || "not found",
        phone: phone || "not found"
      });

      // Start observing for success state
      observeNinjaSuccess(form, email, phone);
      return true;
    };

    try {
      processEvent();
    } catch (e) {
      log("Ninja submit handler error:", e);
    }
  }

  /**
   * Setup Ninja Forms event listeners
   */
  function setupNinjaEventListeners() {
    var listenersSetup = [];

    // Primary: Ninja Forms API events (modern approach)
    if (window.Marionette && window.nfRadio) {
      try {
        // Listen for before submit to capture field data
        window.nfRadio.channel("forms").on("before:submit", handleNinjaBeforeSubmit);

        // Listen for submit response to process successful submissions
        window.nfRadio.channel("forms").on("submit:response", handleNinjaSubmitResponse);

        listenersSetup.push("Ninja Forms API events (before:submit, submit:response)");
        log("Ninja Forms nfRadio API listeners attached successfully");
      } catch (e) {
        log("Could not setup Ninja Forms API listeners:", e);
      }
    } else {
      log("Ninja Forms API (nfRadio) not available, will try fallback methods");
    }

    // Fallback: Traditional form submit handler
    try {
      document.addEventListener("submit", handleNinjaFormSubmit, true);
      listenersSetup.push("form submit handler (fallback)");
      log("Traditional form submit listener added as fallback");
    } catch (e) {
      log("Could not add Ninja submit listener:", e);
    }

    // Additional fallback: Watch for DOM ready and try to set up API listeners later
    if (!window.nfRadio && !window.Marionette) {
      var checkForAPIInterval = setInterval(function() {
        if (window.Marionette && window.nfRadio) {
          try {
            window.nfRadio.channel("forms").on("before:submit", handleNinjaBeforeSubmit);
            window.nfRadio.channel("forms").on("submit:response", handleNinjaSubmitResponse);
            log("Ninja Forms API listeners attached after delay");
            clearInterval(checkForAPIInterval);
          } catch (e) {
            log("Delayed API setup failed:", e);
          }
        }
      }, 500);

      // Stop trying after 10 seconds
      setTimeout(function() {
        clearInterval(checkForAPIInterval);
      }, 10000);

      listenersSetup.push("delayed API setup check");
    }

    log("Ninja Forms event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    setupNinjaEventListeners();
    log("Ninja Forms tracking initialized using standardized dataLayer utilities");

    // Debug: Log available global objects for troubleshooting
    if (DEBUG) {
      log("DEBUG: Available global objects:", {
        hasMarionette: !!window.Marionette,
        hasNfRadio: !!window.nfRadio,
        hasJQuery: !!window.jQuery,
        ninjaFormsDetected: document.querySelector('.nf-form-cont, .nf-form-wrap, .nf-form') !== null,
        location: window.location.pathname
      });
    }
  });

})();