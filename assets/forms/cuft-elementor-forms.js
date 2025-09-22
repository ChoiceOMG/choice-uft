(function () {
  "use strict";

  var DEBUG = !!(window.cuftElementor && window.cuftElementor.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Elementor]"].concat(Array.prototype.slice.call(arguments))
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
    var selector =
      type === "email" ? 'input[type="email"]' : 'input[type="tel"]';
    var field = form.querySelector(selector);

    if (!field) {
      // Fallback: search by field name/id
      var inputs = form.querySelectorAll("input");
      for (var i = 0; i < inputs.length; i++) {
        var input = inputs[i];
        var name = (input.name || "").toLowerCase();
        var id = (input.id || "").toLowerCase();

        if (
          type === "email" &&
          (name.indexOf("email") > -1 || id.indexOf("email") > -1)
        ) {
          field = input;
          break;
        } else if (
          type === "phone" &&
          (name.indexOf("phone") > -1 ||
            name.indexOf("tel") > -1 ||
            id.indexOf("phone") > -1 ||
            id.indexOf("tel") > -1)
        ) {
          field = input;
          break;
        }
      }
    }

    if (!field) return "";

    var value = (field.value || "").trim();
    if (type === "phone" && value) {
      return value.replace(/(?!^\+)[^\d]/g, "");
    }
    return value;
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
    // Check if generate_lead is enabled
    if (!window.cuftElementor || !window.cuftElementor.generate_lead_enabled) {
      log("Generate lead skipped - not enabled");
      return;
    }

    // Check for required fields: click_id, email, and phone
    var hasClickId = basePayload.click_id || basePayload.gclid || basePayload.fbclid ||
                     basePayload.msclkid || basePayload.ttclid || basePayload.li_fat_id ||
                     basePayload.twclid || basePayload.snap_click_id || basePayload.pclid;

    if (!email || !phone || !hasClickId) {
      log("Generate lead skipped - missing required fields (email, phone, or click_id)");
      log("Has email:", !!email, "Has phone:", !!phone, "Has click_id:", !!hasClickId);
      return;
    }

    var leadPayload = {
      event: "generate_lead",
      currency: "USD", // Default currency, could be configurable
      value: 0, // Default value, could be configurable
      cuft_tracked: true,
      cuft_source: basePayload.cuft_source + "_lead",
    };

    // Copy relevant data from base payload
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
      "click_id",
      "gclid",
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

    leadPayload.submittedAt = new Date().toISOString();

    try {
      getDL().push(leadPayload);
      log("Generate lead event fired:", leadPayload);
    } catch (e) {
      log("Generate lead push error:", e);
    }
  }

  function pushToDataLayer(form, email, phone) {
    var formId =
      form.getAttribute("data-form-id") || form.getAttribute("id") || null;
    var formName = form.getAttribute("data-form-name") || null;

    var payload = {
      event: "form_submit",
      formType: "elementor",
      formId: formId,
      formName: formName,
      submittedAt: new Date().toISOString(),
      cuft_tracked: true,
      cuft_source: "elementor_pro",
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

      // Fire generate_lead event if conditions are met
      fireGenerateLeadEvent(payload, email, phone);
    } catch (e) {
      log("DataLayer push error:", e);
    }
  }

  function handleElementorSuccess(event, form) {
    try {
      // Handle both jQuery event and direct form reference
      var targetForm = form || (event && event.target && event.target.closest(".elementor-form"));
      if (!targetForm) {
        log("No form found in success handler");
        return;
      }

      var email = getFieldValue(targetForm, "email");
      var phone = getFieldValue(targetForm, "phone");

      pushToDataLayer(targetForm, email, phone);
    } catch (e) {
      log("Success handler error:", e);
    }
  }

  function handleFormSubmit(event) {
    try {
      var form = event.target;
      if (!form || form.tagName !== "FORM") return;

      // Check if this is an Elementor form
      var isElementorForm =
        form.classList.contains("elementor-form") ||
        form.closest(".elementor-form");

      if (!isElementorForm) return;

      // Store form reference for potential later use
      form.setAttribute("data-cuft-tracking", "pending");

      log("Elementor form submit detected, waiting for success event");
    } catch (e) {
      log("Submit handler error:", e);
    }
  }

  /**
   * Setup event listeners with multiple fallback methods
   */
  function setupEventListeners() {
    var listenersSetup = [];

    // Always listen for form submissions to mark forms for tracking
    document.addEventListener("submit", handleFormSubmit, true);
    listenersSetup.push("form submit listener (native)");

    // Method 1: Native JavaScript CustomEvent listener (Elementor 3.5+)
    try {
      document.addEventListener("submit_success", function (event) {
        log("Native JS submit_success event detected", event.detail);
        handleSuccessEvent(event, event.detail);
      });
      listenersSetup.push("submit_success listener (native)");
    } catch (e) {
      log("Could not add native submit_success listener:", e);
    }

    // Method 2: jQuery event listeners (for older Elementor versions)
    if (window.jQuery) {
      try {
        // Standard submit_success event
        window.jQuery(document).on(
          "submit_success",
          function (event, response) {
            log("jQuery submit_success event detected", response);
            handleSuccessEvent(event, response);
          }
        );
        listenersSetup.push("submit_success listener (jQuery)");

        // Popup hide events
        window
          .jQuery(document)
          .on("elementor/popup/hide", function (event) {
            log("Elementor popup hide event detected");
            handleElementorSuccess(event);
          });
        listenersSetup.push("popup hide listener (jQuery)");
      } catch (e) {
        log("jQuery listener setup error:", e);
      }
    }

    // Method 3: MutationObserver for success messages (ultimate fallback)
    try {
      setupMutationObserver();
      listenersSetup.push("MutationObserver for success messages");
    } catch (e) {
      log("Could not setup MutationObserver:", e);
    }

    // Method 4: Ajax interceptor for form submissions
    try {
      setupAjaxInterceptor();
      listenersSetup.push("Ajax interceptor");
    } catch (e) {
      log("Could not setup Ajax interceptor:", e);
    }

    log("Event listeners setup complete:", listenersSetup);
  }

  /**
   * Handle success event from any source
   */
  function handleSuccessEvent(event, response) {
    try {
      // Find the form that triggered the event
      var form = null;

      // Try to get form from event target
      if (event && event.target) {
        form = event.target.closest(".elementor-form") || event.target;
      }

      // Try to find form with pending tracking
      if (!form) {
        var pendingForms = document.querySelectorAll(
          '.elementor-form[data-cuft-tracking="pending"]'
        );
        if (pendingForms.length > 0) {
          form = pendingForms[0];
        }
      }

      // Try to find any visible Elementor form
      if (!form) {
        var allForms = document.querySelectorAll(".elementor-form");
        for (var i = 0; i < allForms.length; i++) {
          var f = allForms[i];
          // Check if form is visible and has been recently interacted with
          if (f.offsetParent !== null) {
            var inputs = f.querySelectorAll("input, textarea");
            for (var j = 0; j < inputs.length; j++) {
              if (inputs[j].value && inputs[j].value.trim()) {
                form = f;
                break;
              }
            }
          }
          if (form) break;
        }
      }

      if (form) {
        handleElementorSuccess(event, form);
        // Clear tracking attribute
        if (form.hasAttribute("data-cuft-tracking")) {
          form.removeAttribute("data-cuft-tracking");
        }
      } else {
        log("Warning: Could not identify form for success event");
      }
    } catch (e) {
      log("Error in handleSuccessEvent:", e);
    }
  }

  /**
   * Setup MutationObserver to detect form success messages
   */
  function setupMutationObserver() {
    var observer = new MutationObserver(function (mutations) {
      mutations.forEach(function (mutation) {
        // Look for success message elements
        if (mutation.type === "childList") {
          mutation.addedNodes.forEach(function (node) {
            if (node.nodeType === 1) {
              // Element node
              // Check for Elementor success message classes
              if (
                node.classList &&
                (node.classList.contains("elementor-message-success") ||
                  node.classList.contains("elementor-form-success-message"))
              ) {
                log("Success message detected via MutationObserver");
                // Find the associated form
                var form = node.closest(".elementor-form");
                if (!form && node.parentElement) {
                  form = node.parentElement.querySelector(".elementor-form");
                }
                if (form) {
                  handleElementorSuccess(null, form);
                }
              }
            }
          });
        }
      });
    });

    // Start observing
    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });
  }

  /**
   * Setup Ajax interceptor for form submissions
   */
  function setupAjaxInterceptor() {
    // Intercept native fetch if available
    if (window.fetch) {
      var originalFetch = window.fetch;
      window.fetch = function () {
        var args = arguments;
        var url = args[0];
        var options = args[1] || {};

        // Check if this might be an Elementor form submission
        if (
          typeof url === "string" &&
          url.indexOf("admin-ajax.php") > -1 &&
          options.body
        ) {
          var bodyStr = "";
          if (typeof options.body === "string") {
            bodyStr = options.body;
          } else if (options.body instanceof FormData) {
            try {
              // Try to check FormData for Elementor action
              var action = options.body.get("action");
              if (action === "elementor_pro_forms_send_form") {
                log("Elementor form submission detected via fetch");
              }
            } catch (e) {}
          }

          return originalFetch.apply(this, args).then(function (response) {
            // Clone response to read it
            var clonedResponse = response.clone();
            clonedResponse.json().then(function (data) {
              if (
                data &&
                data.success &&
                bodyStr.indexOf("elementor_pro_forms_send_form") > -1
              ) {
                log("Elementor form success detected via fetch interceptor");
                // Try to find the form
                var forms = document.querySelectorAll(
                  '.elementor-form[data-cuft-tracking="pending"]'
                );
                if (forms.length > 0) {
                  handleElementorSuccess(null, forms[0]);
                }
              }
            }).catch(function() {});
            return response;
          });
        }

        return originalFetch.apply(this, args);
      };
    }

    // Also intercept XMLHttpRequest
    var originalXHR = window.XMLHttpRequest.prototype.open;
    window.XMLHttpRequest.prototype.open = function () {
      var xhr = this;
      var url = arguments[1];
      var originalOnReadyStateChange = xhr.onreadystatechange;

      xhr.onreadystatechange = function () {
        if (xhr.readyState === 4 && xhr.status === 200) {
          try {
            if (
              url.indexOf("admin-ajax.php") > -1 &&
              xhr.responseText &&
              xhr.responseText.indexOf('"success":true') > -1
            ) {
              // Check if this was an Elementor form
              if (
                xhr.responseText.indexOf("elementor") > -1 ||
                (xhr._requestBody &&
                  xhr._requestBody.indexOf("elementor_pro_forms_send_form") > -1)
              ) {
                log("Elementor form success detected via XHR interceptor");
                var forms = document.querySelectorAll(
                  '.elementor-form[data-cuft-tracking="pending"]'
                );
                if (forms.length > 0) {
                  handleElementorSuccess(null, forms[0]);
                }
              }
            }
          } catch (e) {}
        }
        if (originalOnReadyStateChange) {
          originalOnReadyStateChange.apply(this, arguments);
        }
      };

      // Store request body for later inspection
      var originalSend = xhr.send;
      xhr.send = function (data) {
        xhr._requestBody = data;
        originalSend.apply(xhr, arguments);
      };

      return originalXHR.apply(this, arguments);
    };
  }

  ready(function () {
    setupEventListeners();
    // Additional jQuery Ajax monitoring if jQuery is available
    if (window.jQuery && window.jQuery.ajaxSetup) {
      try {
        window.jQuery(document).ajaxComplete(function (event, xhr, settings) {
          try {
            // Check if this is an Elementor form submission
            if (
              settings &&
              settings.url &&
              settings.url.indexOf("admin-ajax.php") > -1 &&
              settings.data &&
              settings.data.indexOf("action=elementor_pro_forms_send_form") > -1
            ) {
              // Parse response
              var response = xhr.responseJSON || {};
              if (response.success) {
                log("Elementor form success via jQuery.ajaxComplete");
                handleSuccessEvent(event, response);
              }
            }
          } catch (e) {
            log("jQuery ajaxComplete handler error:", e);
          }
        });
        log("jQuery.ajaxComplete listener added");
      } catch (e) {
        log("Could not setup jQuery.ajaxComplete:", e);
      }
    }

    log("Elementor forms tracking initialized");
  });
})();
