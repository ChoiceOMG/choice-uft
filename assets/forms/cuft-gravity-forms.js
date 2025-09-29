(function () {
  "use strict";

  // Check if dataLayer utilities are available
  if (!window.cuftDataLayerUtils) {
    console.error('[CUFT Gravity] DataLayer utilities not found - ensure cuft-dataLayer-utils.js is loaded first');
    return;
  }


  var DEBUG = !!(window.cuftGravity && window.cuftGravity.console_logging);

  function log() {
    if (!DEBUG) return;
    try {
      if (window.console && window.console.log) {
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

    try {
      return form && (
        form.classList.contains("gform_form") ||
        (form.id && form.id.indexOf("gform_") === 0) ||
        form.closest(".gform_wrapper") !== null
      );
    } catch (e) {
      return false;
    }
  }

  /**
   * Get field value from Gravity Forms using multiple detection strategies
   * Handles complex multi-part fields (name, address, email confirmation, etc.)
   */
  function getFieldValue(form, type) {
    try {
      // Framework detection - exit silently if not Gravity Forms
      if (!isGravityForm(form)) {
        return "";
      }

      var field = null;

      // Strategy 1: Direct input selection by autocomplete attribute and type
      if (type === "email") {
        try {
          var emailInputs = form.querySelectorAll('input[autocomplete="email"], input[type="email"]');
          if (emailInputs.length > 0) {
            for (var e = 0; e < emailInputs.length; e++) {
              if (emailInputs[e].value && emailInputs[e].value.trim()) {
                return emailInputs[e].value.trim();
              }
            }
            if (emailInputs[0].value) {
              return emailInputs[0].value.trim();
            }
          }
        } catch (e) {}
      } else if (type === "phone") {
        try {
          var directPhone = form.querySelector('input[autocomplete="tel"]') ||
                           form.querySelector('input[type="tel"]') ||
                           form.querySelector('input[name*="phone"]') ||
                           form.querySelector('input[name*="tel"]');
          if (directPhone && directPhone.value) {
            return (directPhone.value || "").trim();
          }
        } catch (e) {}
      }

      // Strategy 2: Look for Gravity Forms field containers
      var fields;
      try {
        fields = form.querySelectorAll(".gfield") || [];
      } catch (e) {
        fields = [];
      }

      for (var i = 0; i < fields.length; i++) {
        var fieldContainer = fields[i];

        // Check if this container IS an input or contains one
        var input = null;
        if (fieldContainer.tagName === 'INPUT' || fieldContainer.tagName === 'TEXTAREA' || fieldContainer.tagName === 'SELECT') {
          input = fieldContainer;
        } else {
          input = fieldContainer.querySelector("input, textarea, select");
        }

        if (!input) continue;

      var inputType = (input.getAttribute("type") || "").toLowerCase();
      var autocomplete = (input.getAttribute("autocomplete") || "").toLowerCase();
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

      if (type === "email") {
        if (
          autocomplete === "email" ||
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
          // Skip confirmation email fields (they usually have "confirm" in label or ID)
          var isConfirmation = labelText.indexOf("confirm") > -1 ||
                              inputId.indexOf("_2") > -1 ||
                              inputName.indexOf("_2") > -1;

          if (!isConfirmation && input.value) {
            field = input;
            log("Found Gravity email field:", input);
            break;
          } else if (!isConfirmation && !field) {
            // Store as potential field if no better match found
            field = input;
          }
        }
      } else if (type === "phone") {
        if (
          autocomplete === "tel" ||
          autocomplete.indexOf("phone") > -1 ||
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
      var allInputs;
      try {
        allInputs = form.querySelectorAll("input[type='text'], input[type='email']") || [];
      } catch (e) {
        allInputs = [];
      }

      for (var j = 0; j < allInputs.length; j++) {
        var testInput = allInputs[j];
        var value;
        try {
          value = (testInput.value || "").trim();
        } catch (e) {
          value = "";
        }

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

    var value;
    try {
      value = (field.value || "").trim();
    } catch (e) {
      value = "";
    }

    log("Gravity field value for " + type + ":", value);
    return value;

    } catch (e) {
      log("Error in Gravity field extraction:", e);
      return "";
    }
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
        return false;
      }

      // Prevent duplicate processing
      if (window.cuftDataLayerUtils.isFormProcessed(form)) {
        log("Gravity form already processed, skipping");
        return false;
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
                click_id: 'test_gravity_' + Date.now(),
                gclid: 'test_gclid_gravity_' + formDetails.form_id,
                utm_source: 'test_gravity',
                utm_medium: 'test_form',
                utm_campaign: 'gravity_forms_test',
                utm_term: 'gravity_test',
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
      var success = window.cuftDataLayerUtils.trackFormSubmission('gravity', form, {
        form_id: formDetails.form_id,
        form_name: formDetails.form_name,
        user_email: email,
        user_phone: phone,
        debug: DEBUG,
        lead_currency: window.cuftGravity && window.cuftGravity.lead_currency ? window.cuftGravity.lead_currency : 'CAD',
        lead_value: window.cuftGravity && window.cuftGravity.lead_value ? window.cuftGravity.lead_value : 100,
      });

      if (success) {
        log("Gravity form successfully tracked");
        return true;
      } else {
        log("Gravity form tracking failed");
        return false;
      }
    } catch (e) {
      log("Gravity form processing error:", e);
      return false;
    }
  }

  /**
   * Setup MutationObserver for confirmation message detection
   */
  function observeGravitySuccess(form, email, phone) {
    var observerConfig = {
      id: 'gravity-success-observer',
      element: form,
      timeout: 20000, // 20 second timeout for Gravity Forms
      context: 'Gravity Success Detection',
      description: 'Observing form for confirmation message'
    };

    var cleanup = function() {};

    var pushed = false;
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

    // Observer cleanup handles safety timeout automatically
    // Initial cleanup timeout as fallback
    setTimeout(function () {
      if (!pushed) {
        log("Gravity success observation timed out, cleaning up");
        cleanup();
      }
    }, 20000); // 20 second maximum observation for Gravity Forms
  }

  /**
   * Handle form submit - detects AJAX vs traditional submission automatically
   * Routes to appropriate tracking method based on detection
   */
  function handleGravityFormSubmit(event) {
    var processEvent = function() {
      var form = event.target;

      log("Submit event triggered on element:", form ? form.tagName : "null", form ? form.id : "no-id");

      if (!form || form.tagName !== "FORM") {
        log("Not a form element, skipping");
        return false;
      }

      // Check if this is a Gravity Forms form - exit silently if not
      var isGravity = isGravityForm(form);

      if (!isGravity) {
        return false;
      }

      log("✓ Gravity Form detected, proceeding with tracking");
      log("Form details:", {
        id: form.id,
        action: form.action,
        classes: form.className
      });

      // Detect submission method (AJAX vs traditional)
      var isAjax = isGravityFormAjax(form);
      log("Submission method detection:", isAjax ? "AJAX ✓" : "Traditional (page reload) ⚠");

      if (isAjax) {
        // AJAX submission - capture data now, track on confirmation event
        log("AJAX mode: Capturing field data for later tracking");

        // Prevent duplicate capture
        if (form.hasAttribute("data-cuft-gravity-capturing")) {
          log("Data already captured for this submission, skipping");
          return;
        }
        form.setAttribute("data-cuft-gravity-capturing", "true");

        // Capture field values for AJAX confirmation handler
        var email = getFieldValue(form, "email");
        var phone = getFieldValue(form, "phone");
        var formDetails = getGravityFormDetails(form);

        log("Email captured:", email || "not found");
        log("Phone captured:", phone || "not found");

        // Store for AJAX confirmation handler
        var formId = formDetails.form_id;
        gravityFormData[formId] = {
          form: form,
          email: email,
          phone: phone,
          formDetails: formDetails,
          timestamp: Date.now()
        };

        log("Data stored for AJAX confirmation - waiting for gform_confirmation_loaded event");

      } else {
        // Traditional submission - store data for post-reload tracking
        console.log("[CUFT Gravity] Traditional submission detected");

        // Prevent duplicate processing
        if (form.hasAttribute("data-cuft-gravity-submitted")) {
          return;
        }
        form.setAttribute("data-cuft-gravity-submitted", "true");

        // Capture field values
        var email = getFieldValue(form, "email");
        var phone = getFieldValue(form, "phone");
        var formDetails = getGravityFormDetails(form);

        console.log("[CUFT Gravity] Captured:", {
          email: email ? "YES" : "NO",
          phone: phone ? "YES" : "NO"
        });

        // For traditional submissions, store email/phone in localStorage
        if (email && phone) {
          try {
            var submissionData = {
              formId: formDetails.form_id,
              formName: formDetails.form_name,
              email: email,
              phone: phone,
              timestamp: Date.now()
            };
            localStorage.setItem('cuft_gravity_submission_data', JSON.stringify(submissionData));
            console.log("[CUFT Gravity] Stored submission data in localStorage");
          } catch (e) {
            console.error("[CUFT Gravity] Storage error:", e);
          }
        }
      }

      return true;
    };

    try {
      processEvent();
    } catch (e) {
      log("Gravity submit handler error:", e);
    }
  }

  /**
   * Store for capturing form data before submission (for AJAX forms)
   */
  var gravityFormData = {};

  /**
   * Detect if a Gravity Form is using AJAX submission
   */
  function isGravityFormAjax(form) {
    try {
      // Check for AJAX iframe (Gravity Forms AJAX indicator)
      var formId = form.id.replace("gform_", "");
      var ajaxFrame = document.getElementById("gform_ajax_frame_" + formId);
      if (ajaxFrame) {
        log("AJAX frame detected for form:", formId, "- using AJAX submission");
        return true;
      }

      // Check for ajax class on form wrapper
      var wrapper = form.closest('.gform_wrapper');
      if (wrapper && wrapper.classList.contains('gform_ajax_submission')) {
        log("AJAX class detected on wrapper - using AJAX submission");
        return true;
      }

      // Check form's target attribute (AJAX forms target the iframe)
      if (form.target && form.target.indexOf('gform_ajax_frame') > -1) {
        log("AJAX target attribute detected - using AJAX submission");
        return true;
      }

      log("No AJAX indicators found - using traditional submission");
      return false;
    } catch (e) {
      log("Error detecting AJAX mode:", e);
      return false; // Default to traditional submission
    }
  }

  /**
   * Handle Gravity Forms before submit (capture field data for AJAX submissions)
   */
  function handleGravityBeforeSubmit(event, formId) {
    try {
      log("Gravity Forms before submit event for form:", formId);

      // Find the form element
      var form = document.getElementById("gform_" + formId);
      if (!form) {
        log("Could not find form element for ID:", formId);
        return;
      }

      log("Found form element:", form.id);

      // Capture field values before submission
      log("Attempting to capture email field...");
      var email = getFieldValue(form, "email");
      log("Email captured:", email || "not found");

      log("Attempting to capture phone field...");
      var phone = getFieldValue(form, "phone");
      log("Phone captured:", phone || "not found");

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
            // Store test click ID in sessionStorage EARLY (during capture phase)
            var testData = {
              tracking: {
                click_id: 'test_gravity_capture_' + Date.now(),
                gclid: 'test_gclid_gravity_' + formId,
                utm_source: 'test_gravity',
                utm_medium: 'test_form_capture',
                utm_campaign: 'gravity_forms_test',
                utm_term: 'gravity_test',
                utm_content: 'form_test'
              },
              timestamp: Date.now()
            };

            try {
              sessionStorage.setItem('cuft_tracking_data', JSON.stringify(testData));
              log('Test mode (CAPTURE PHASE): Added test tracking data to sessionStorage');
              log('Test tracking data:', testData.tracking);
            } catch (storageError) {
              log('Test mode (CAPTURE PHASE): Could not store test data in sessionStorage:', storageError);
            }

            // ALSO store in localStorage for traditional (page reload) submissions
            try {
              localStorage.setItem('cuft_tracking_data', JSON.stringify(testData));
              log('Test mode (CAPTURE PHASE): Added test tracking data to localStorage (for page reload)');
            } catch (storageError) {
              log('Test mode (CAPTURE PHASE): Could not store test data in localStorage:', storageError);
            }
          } else {
            log('Test mode (CAPTURE PHASE): Click IDs already exist, using existing tracking data');
          }
        } catch (e) {
          log('Test mode (CAPTURE PHASE): Error adding test tracking data:', e);
        }
      }

      // Store the data for use after confirmation
      gravityFormData[formId] = {
        form: form,
        email: email,
        phone: phone,
        formDetails: getGravityFormDetails(form),
        timestamp: Date.now(),
        processed: false // Add flag to prevent double-processing
      };

      log("Gravity form data stored for form:", formId, {
        email: email || "not found",
        phone: phone || "not found",
        formId: gravityFormData[formId].formDetails.form_id
      });
    } catch (e) {
      log("Error in Gravity before submit handler:", e);
    }
  }

  /**
   * Handle Gravity Forms confirmation loaded (AJAX submission success)
   */
  function handleGravityConfirmationLoaded(event, formId) {
    try {
      log("✓ Gravity Forms AJAX confirmation loaded for form:", formId);
      log("Event object:", event);
      log("Available stored form IDs:", Object.keys(gravityFormData));

      var storedData = gravityFormData[formId];

      if (!storedData) {
        log("⚠ No stored form data found for form:", formId, "- may be traditional submission");
        log("gravityFormData contents:", gravityFormData);
        return;
      }

      // Check if already processed
      if (storedData.processed) {
        log("⚠ Form already processed via another event, skipping");
        return;
      }
      storedData.processed = true;

      log("Processing AJAX Gravity form submission with stored data:", {
        formId: storedData.formDetails.form_id,
        email: storedData.email || "not found",
        phone: storedData.phone || "not found",
        hasForm: !!storedData.form
      });

      // Use the success handler with stored data
      handleGravitySuccess(storedData.form, storedData.email, storedData.phone);

      // Clean up stored data
      delete gravityFormData[formId];

      log("✅ Gravity AJAX form tracking completed for form:", formId);
    } catch (e) {
      log("❌ Error in Gravity confirmation loaded handler:", e);
    }
  }

  /**
   * Setup Gravity Forms event listeners
   */
  function setupGravityEventListeners() {
    var listenersSetup = [];

    // Primary: Gravity Forms jQuery AJAX events (most reliable for AJAX forms)
    if (window.jQuery) {
      try {
        // Listen for before submit to capture field data
        // Try multiple event names in case of version differences
        window.jQuery(document).on("gform_pre_submission", handleGravityBeforeSubmit);
        window.jQuery(document).on("gform_submit", handleGravityBeforeSubmit);
        listenersSetup.push("gform_pre_submission/gform_submit (capture fields)");
        log("Listening for gform_pre_submission and gform_submit events");
      } catch (e) {
        log("Could not setup gform pre-submit listeners:", e);
      }

      try {
        // Listen for confirmation loaded (successful AJAX submission)
        window.jQuery(document).on("gform_confirmation_loaded", handleGravityConfirmationLoaded);
        listenersSetup.push("gform_confirmation_loaded (track submission)");
        log("Listening for gform_confirmation_loaded events");
      } catch (e) {
        log("Could not setup gform_confirmation_loaded listener:", e);
      }

      try {
        // Also listen for post_render in case form is replaced after submission
        window.jQuery(document).on("gform_post_render", function(event, formId) {
          log("gform_post_render event fired for form:", formId);
        });
        listenersSetup.push("gform_post_render (debug)");
      } catch (e) {
        log("Could not setup gform_post_render listener:", e);
      }
    }

    // Modern: Native JavaScript gform/postRender event (GF 2.9.0+)
    try {
      document.addEventListener("gform/postRender", function(event) {
        if (event && event.detail && event.detail.formId) {
          log("gform/postRender native event fired for form:", event.detail.formId);

          // Check if this is a confirmation render (form submitted successfully)
          // When AJAX submission succeeds, GF renders the confirmation which triggers this event
          setTimeout(function() {
            var storedData = gravityFormData[event.detail.formId];
            if (storedData && !storedData.processed) {
              log("Processing form via gform/postRender event:", event.detail.formId);
              storedData.processed = true;
              handleGravitySuccess(storedData.form, storedData.email, storedData.phone);
              delete gravityFormData[event.detail.formId];
            }
          }, 100);
        }
      });
      listenersSetup.push("gform/postRender (modern GF 2.9+)");
      log("Native gform/postRender listener added for modern Gravity Forms");
    } catch (e) {
      log("Could not add gform/postRender listener:", e);
    }

    // Fallback: Traditional form submit handler (for non-AJAX forms)
    try {
      document.addEventListener("submit", handleGravityFormSubmit, true);
      listenersSetup.push("form submit handler (fallback)");
    } catch (e) {
      log("Could not add Gravity submit listener:", e);
    }

    log("Gravity Forms event listeners setup complete:", listenersSetup);
  }

  // Initialize when DOM is ready
  ready(function () {
    console.log("[CUFT Gravity] Initialized");
    log("=== Gravity Forms Tracking Initialization ===");
    log("Page URL:", window.location.href);
    log("jQuery available:", !!window.jQuery);
    log("Gravity Forms detected on page:", document.querySelector('.gform_wrapper, .gform_form, form[id^="gform_"]') !== null);

    // Check if this is a confirmation page after traditional submission
    var confirmationMessage = document.querySelector('.gform_confirmation_message');
    if (confirmationMessage) {
      console.log("[CUFT Gravity] Confirmation page detected");

      // Check if we have submission data (email/phone) from the previous page
      try {
        var storedSubmission = localStorage.getItem('cuft_gravity_submission_data');
        if (storedSubmission) {
          var submissionData = JSON.parse(storedSubmission);
          console.log("[CUFT Gravity] Found stored submission data:", {
            email: submissionData.email ? "YES" : "NO",
            phone: submissionData.phone ? "YES" : "NO"
          });

          // Try to identify which form was submitted by looking for form wrapper
          var formWrapper = confirmationMessage.closest('.gform_wrapper');
          if (formWrapper) {
            var pseudoForm = formWrapper;

            // Use standardized tracking function
            var success = window.cuftDataLayerUtils.trackFormSubmission('gravity', pseudoForm, {
              form_id: submissionData.formId,
              form_name: submissionData.formName,
              user_email: submissionData.email,
              user_phone: submissionData.phone,
              debug: true,
              lead_currency: 'CAD',
              lead_value: 100
            });

            if (success) {
              console.log("[CUFT Gravity] ✅ Form tracked successfully");
              localStorage.removeItem('cuft_gravity_submission_data');
            } else {
              console.log("[CUFT Gravity] ❌ Tracking failed");
            }
          }
        } else {
          console.log("[CUFT Gravity] No stored submission data found");
        }
      } catch (e) {
        console.error("[CUFT Gravity] Error:", e);
      }
    }

    // DEBUG: Log all gform events
    if (DEBUG && window.jQuery) {
      var originalTrigger = window.jQuery.fn.trigger;
      window.jQuery.fn.trigger = function(event) {
        if (typeof event === 'string' && event.indexOf('gform') > -1) {
          log("📢 jQuery event triggered:", event, arguments);
        }
        return originalTrigger.apply(this, arguments);
      };

      // Also listen for ALL document events
      var jQueryDocTrigger = window.jQuery(document).trigger;
      window.jQuery(document).trigger = function(event) {
        if (typeof event === 'string' && event.indexOf('gform') > -1) {
          log("📢 Document event triggered:", event, arguments);
        }
        return jQueryDocTrigger.apply(this, arguments);
      };
      log("DEBUG: Event interception enabled for gform events");
    }

    setupGravityEventListeners();
    log("✅ Gravity Forms tracking initialized using standardized dataLayer utilities");
  });

})();