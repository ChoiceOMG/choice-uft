/**
 * CUFT Test Forms - Frontend Testing Script
 */
(function () {
  "use strict";

  // Global test forms object
  window.cuftTestForms = {
    verbose: false,
    events: [],
    initialized: false,

    // Initialize
    init: function () {
      // Prevent double initialization
      if (this.initialized) {
        console.log("[CUFT Test Forms] Already initialized");
        return;
      }

      console.log("[CUFT Test Forms] Starting initialization...");
      this.initialized = true;

      // Check if we have the config
      if (typeof cuftTestConfig === "undefined") {
        console.error("[CUFT Test Forms] Config not found!");
        return;
      }

      this.log("CUFT Test Forms initialized", "info");
      this.setupEventListeners();
      this.monitorDataLayer();

      // Check for auto-submit
      if (cuftTestConfig.autoSubmit && cuftTestConfig.framework) {
        this.log(
          "Auto-submit enabled for: " + cuftTestConfig.framework,
          "info"
        );
        setTimeout(() => {
          this.autoSubmitFramework(cuftTestConfig.framework);
        }, 2000);
      }

      // Set verbose mode from config
      if (cuftTestConfig.verbose) {
        this.verbose = true;
      }

      console.log("[CUFT Test Forms] Initialization complete");
    },

    // Log to browser console
    log: function (message, type = "log") {
      const logMethod =
        type === "error" ? "error" : type === "warn" ? "warn" : "log";
      console[logMethod]("[CUFT Test]", message);
    },

    // Setup event listeners
    setupEventListeners: function () {
      const self = this;
      console.log("[CUFT Test Forms] Setting up event listeners...");

      // Count forms found
      const forms = document.querySelectorAll(".cuft-test-form");
      console.log("[CUFT Test Forms] Found " + forms.length + " test forms");

      // Use event delegation for better reliability
      document.addEventListener("click", function (e) {
        // Debug what was clicked
        if (e.target && e.target.classList) {
          // Check if clicked element is a submit button
          if (e.target.classList.contains("cuft-submit-btn")) {
            console.log("[CUFT Test Forms] Submit button clicked");
            e.preventDefault();
            e.stopPropagation();

            const form = e.target.closest(".cuft-test-form");
            if (form) {
              console.log("[CUFT Test Forms] Found form, submitting...");
              self.submitTestForm(form);
            } else {
              console.error("[CUFT Test Forms] Could not find parent form");
            }
            return false;
          }
        }
      });

      // Also prevent form submissions
      document.addEventListener("submit", function (e) {
        if (
          e.target &&
          e.target.classList &&
          e.target.classList.contains("cuft-test-form")
        ) {
          e.preventDefault();
          e.stopPropagation();
          self.submitTestForm(e.target);
          return false;
        }
      });

      // Monitor dataLayer pushes
      if (window.dataLayer && Array.isArray(window.dataLayer)) {
        const originalPush = window.dataLayer.push;

        window.dataLayer.push = function () {
          const result = originalPush.apply(window.dataLayer, arguments);
          self.captureDataLayerEvent(arguments[0]);
          return result;
        };
      }
    },

    // Submit test form
    submitTestForm: function (form) {
      console.log("[CUFT Test Forms] submitTestForm called");

      if (!form) {
        console.error("[CUFT Test Forms] No form provided");
        return;
      }

      const framework = form.dataset.framework;
      const formId = form.dataset.formId;
      const emailInput = form.querySelector('input[type="email"], input[name*="email"], input[data-field="email"]');
      const phoneInput = form.querySelector('input[type="tel"], input[name*="phone"], input[data-field="phone"]');
      const resultDiv = form.querySelector(".test-result");
      const submitButton = form.querySelector(".cuft-submit-btn");

      if (!emailInput || !phoneInput) {
        console.error("[CUFT Test Forms] Could not find email or phone inputs");
        return;
      }

      const email = emailInput.value;
      const phone = phoneInput.value;

      console.log("[CUFT Test Forms] Form data:", {
        framework,
        formId,
        email,
        phone,
      });
      this.log(`Submitting test form for ${framework}...`, "info");

      // Simulate framework-specific behavior
      this.simulateFrameworkBehavior(framework, form, submitButton);

      // Prepare test data
      const testData = {
        event: "form_submit",
        user_email: email,
        user_phone: phone,
        form_framework: framework,
        form_id: formId,
        click_id: `click_id_${framework}_test`,
        utm_campaign: `test_campaign_${framework}_test`,
        utm_source: "cuft_test",
        utm_medium: "test_form",
        test_submission: true,
        timestamp: new Date().toISOString(),
      };

      // Add any existing UTM parameters from URL
      const urlParams = new URLSearchParams(window.location.search);
      [
        "utm_source",
        "utm_medium",
        "utm_campaign",
        "utm_term",
        "utm_content",
      ].forEach((param) => {
        if (urlParams.has(param) && param !== "utm_campaign") {
          testData[param] = urlParams.get(param);
        }
      });

      // Log the event details
      this.log(`Event Data: ${JSON.stringify(testData, null, 2)}`, "data");

      // Simulate the actual tracking behavior that would happen with real forms
      // This mimics what the actual form tracking scripts do
      setTimeout(() => {
        // Push to dataLayer (this is what the actual tracking scripts do)
        if (window.dataLayer) {
          window.dataLayer.push(testData);
          this.log(`✓ Event pushed to dataLayer`, "success");
        } else {
          this.log("✗ dataLayer not found!", "error");
        }

        // Send email notification via AJAX
        this.sendTestFormEmail(testData, framework, resultDiv, submitButton);
      }, 500); // Small delay to simulate processing

      // Trigger additional events if needed
      if (this.verbose) {
        setTimeout(() => {
          this.triggerVerboseEvents(framework, testData);
        }, 1000);
      }
    },

    // Simulate framework-specific submission behavior
    simulateFrameworkBehavior: function (framework, form, submitButton) {
      // Disable submit button and show loading state
      if (submitButton) {
        submitButton.disabled = true;
      }

      switch (framework) {
        case "elementor":
          // Elementor shows a spinner overlay
          submitButton.textContent = "Sending...";
          form.style.opacity = "0.7";
          break;

        case "contact_form_7":
          // CF7 shows a spinner next to the submit button
          submitButton.innerHTML =
            '<span class="ajax-loader" style="display: inline-block; margin-left: 10px;">⏳</span> Sending...';
          break;

        case "gravity_forms":
          // Gravity Forms shows a spinner and disables the form
          submitButton.textContent = "Processing...";
          form.style.pointerEvents = "none";
          break;

        case "ninja_forms":
          // Ninja Forms shows a processing message
          submitButton.textContent = "Processing...";
          break;

        case "avada":
          // Avada/Fusion shows a loading indicator
          submitButton.innerHTML = "⏳ Submitting...";
          break;

        default:
          submitButton.textContent = "Sending...";
      }

      this.log(`Simulating ${framework} submission behavior`, "info");
    },

    // Show framework-specific success message
    showFrameworkSuccess: function (framework, form, resultDiv) {
      // Reset form opacity if it was changed
      form.style.opacity = "1";
      form.style.pointerEvents = "auto";

      let successMessage = "";
      let displayDuration = 5000;

      switch (framework) {
        case "elementor":
          // Elementor shows a success message below the form
          successMessage = `
                        <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; margin-top: 10px;">
                            <strong>The form was sent successfully.</strong><br>
                            <small>✓ Event tracked in dataLayer</small>
                        </div>
                    `;
          break;

        case "contact_form_7":
          // CF7 shows a green border and success message
          form.style.border = "2px solid #46b450";
          successMessage = `
                        <div style="padding: 10px; background: transparent; color: #46b450; margin-top: 10px; font-weight: bold;">
                            Thank you for your message. It has been sent.<br>
                            <small>✓ Event tracked in dataLayer</small>
                        </div>
                    `;
          setTimeout(() => {
            form.style.border = "";
          }, displayDuration);
          break;

        case "gravity_forms":
          // Gravity Forms shows a confirmation message
          successMessage = `
                        <div style="padding: 15px; background: #f0f8ff; border: 2px solid #0073aa; border-radius: 4px; margin-top: 10px;">
                            <strong>Thanks for contacting us!</strong> We will get in touch with you shortly.<br>
                            <small>✓ Event tracked in dataLayer</small>
                        </div>
                    `;
          break;

        case "ninja_forms":
          // Ninja Forms shows a success message
          successMessage = `
                        <div style="padding: 15px; background: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px; color: #3c763d; margin-top: 10px;">
                            <strong>Success!</strong> Your form has been submitted.<br>
                            <small>✓ Event tracked in dataLayer</small>
                        </div>
                    `;
          break;

        case "avada":
          // Avada/Fusion shows a success notification
          successMessage = `
                        <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px; margin-top: 10px;">
                            <strong>Thank You!</strong> Your submission has been received.<br>
                            <small>✓ Event tracked in dataLayer</small>
                        </div>
                    `;
          break;

        default:
          successMessage = `
                        <div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; color: #155724;">
                            ✅ Form submitted! Check Tag Assistant for the <code>form_submit</code> event.
                        </div>
                    `;
      }

      if (resultDiv && successMessage) {
        resultDiv.style.display = "block";
        resultDiv.innerHTML = successMessage;

        // Hide success message after duration
        setTimeout(() => {
          resultDiv.style.display = "none";
          resultDiv.innerHTML = "";
        }, displayDuration);
      }

      this.log(
        `${framework} form submission completed successfully`,
        "success"
      );
    },

    // Send test form email via AJAX
    sendTestFormEmail: function (testData, framework, resultDiv, submitButton) {
      if (!cuftTestConfig.ajax_url) {
        this.log("✗ AJAX URL not configured", "error");
        this.showFrameworkSuccessWithEmail(
          framework,
          resultDiv,
          false,
          "no-ajax"
        );
        this.reEnableSubmitButton(submitButton);
        return;
      }

      this.log("📧 Sending email notification...", "info");

      // Prepare form data for AJAX request
      const formData = new FormData();
      formData.append("action", "cuft_frontend_test_submit");
      formData.append("framework", testData.form_framework);
      formData.append("email", testData.user_email);
      formData.append("phone", testData.user_phone);
      formData.append("form_id", testData.form_id);

      // Add UTM parameters if they exist
      if (testData.utm_source)
        formData.append("utm_source", testData.utm_source);
      if (testData.utm_medium)
        formData.append("utm_medium", testData.utm_medium);
      if (testData.utm_campaign)
        formData.append("utm_campaign", testData.utm_campaign);
      if (testData.utm_term) formData.append("utm_term", testData.utm_term);
      if (testData.utm_content)
        formData.append("utm_content", testData.utm_content);

      // Send AJAX request
      fetch(cuftTestConfig.ajax_url, {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            this.log(
              `✓ Email sent successfully (ID: ${data.data.tracking_id})`,
              "success"
            );
            this.showFrameworkSuccessWithEmail(
              framework,
              resultDiv,
              data.data.email_sent,
              data.data.tracking_id
            );
          } else {
            this.log(`✗ Email failed: ${data.data.message}`, "error");
            this.showFrameworkSuccessWithEmail(
              framework,
              resultDiv,
              false,
              "error"
            );
          }
        })
        .catch((error) => {
          this.log(`✗ Email request failed: ${error.message}`, "error");
          this.showFrameworkSuccessWithEmail(
            framework,
            resultDiv,
            false,
            "error"
          );
        })
        .finally(() => {
          this.reEnableSubmitButton(submitButton);
        });
    },

    // Re-enable submit button
    reEnableSubmitButton: function (submitButton) {
      setTimeout(() => {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = "🚀 Submit Test Form";
        }
      }, 2000);
    },

    // Show framework success with email status
    showFrameworkSuccessWithEmail: function (
      framework,
      resultDiv,
      emailSent,
      trackingId
    ) {
      let successMessage = "";
      let displayDuration = 6000; // Longer duration for email status

      const emailStatus = emailSent
        ? `<br><small>✅ Email sent to admin (ID: ${trackingId})</small>`
        : `<br><small>⚠️ Email failed to send (ID: ${trackingId})</small>`;

      switch (framework) {
        case "elementor":
          successMessage = `
                         <div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; color: #155724; margin-top: 10px;">
                             <strong>The form was sent successfully.</strong><br>
                             <small>✓ Event tracked in dataLayer</small>${emailStatus}
                         </div>
                     `;
          break;

        case "contact_form_7":
          successMessage = `
                         <div style="padding: 10px; background: transparent; color: #46b450; margin-top: 10px; font-weight: bold;">
                             Thank you for your message. It has been sent.<br>
                             <small>✓ Event tracked in dataLayer</small>${emailStatus}
                         </div>
                     `;
          break;

        case "gravity_forms":
          successMessage = `
                         <div style="padding: 15px; background: #f0f8ff; border: 2px solid #0073aa; border-radius: 4px; margin-top: 10px;">
                             <strong>Thanks for contacting us!</strong> We will get in touch with you shortly.<br>
                             <small>✓ Event tracked in dataLayer</small>${emailStatus}
                         </div>
                     `;
          break;

        case "ninja_forms":
          successMessage = `
                         <div style="padding: 15px; background: #dff0d8; border: 1px solid #d6e9c6; border-radius: 4px; color: #3c763d; margin-top: 10px;">
                             <strong>Success!</strong> Your form has been submitted.<br>
                             <small>✓ Event tracked in dataLayer</small>${emailStatus}
                         </div>
                     `;
          break;

        case "avada":
          successMessage = `
                         <div style="padding: 15px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px; margin-top: 10px;">
                             <strong>Thank You!</strong> Your submission has been received.<br>
                             <small>✓ Event tracked in dataLayer</small>${emailStatus}
                         </div>
                     `;
          break;

        default:
          successMessage = `
                         <div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px; color: #155724;">
                             ✅ Form submitted! Check Tag Assistant for the <code>form_submit</code> event.${emailStatus}
                         </div>
                     `;
      }

      if (resultDiv && successMessage) {
        resultDiv.style.display = "block";
        resultDiv.innerHTML = successMessage;

        // Hide success message after duration
        setTimeout(() => {
          resultDiv.style.display = "none";
          resultDiv.innerHTML = "";
        }, displayDuration);
      }

      this.log(
        `${framework} form submission completed successfully`,
        "success"
      );
    },

    // Trigger verbose events for deeper testing
    triggerVerboseEvents: function (framework, baseData) {
      // Trigger form_start event
      const startEvent = Object.assign({}, baseData, {
        event: "form_start",
        form_step: 1,
      });
      window.dataLayer.push(startEvent);
      this.log("Verbose: form_start event triggered", "event");

      // Trigger form_complete event
      setTimeout(() => {
        const completeEvent = Object.assign({}, baseData, {
          event: "form_complete",
          form_success: true,
        });
        window.dataLayer.push(completeEvent);
        this.log("Verbose: form_complete event triggered", "event");
      }, 500);

      // Trigger generate_lead if campaign data exists
      if (baseData.utm_campaign) {
        setTimeout(() => {
          const leadEvent = {
            event: "generate_lead",
            currency: "USD",
            value: 0,
            form_framework: framework,
            user_email: baseData.user_email,
          };
          window.dataLayer.push(leadEvent);
          this.log("Verbose: generate_lead event triggered", "event");
        }, 1000);
      }
    },

    // Monitor dataLayer for events
    monitorDataLayer: function () {
      if (!window.dataLayer) {
        this.log("dataLayer not initialized yet", "warn");
        return;
      }

      this.log(
        `dataLayer monitoring active (${window.dataLayer.length} existing events)`,
        "info"
      );
    },

    // Capture dataLayer events
    captureDataLayerEvent: function (data) {
      if (!data || typeof data !== "object") return;

      // Filter out GTM internal events unless verbose
      if (!this.verbose) {
        if (data.event && data.event.startsWith("gtm.")) return;
        if (data["gtm.uniqueEventId"]) return;
      }

      this.events.push(data);

      if (data.event) {
        let message = `📊 dataLayer Event: ${data.event}`;

        // Add key details for form events
        if (data.event === "form_submit" || data.event === "generate_lead") {
          const details = [];
          if (data.form_framework)
            details.push(`framework: ${data.form_framework}`);
          if (data.form_id) details.push(`id: ${data.form_id}`);
          if (data.click_id) details.push(`click: ${data.click_id}`);
          if (details.length) {
            message += ` (${details.join(", ")})`;
          }
        }

        this.log(message, "event");

        if (this.verbose) {
          this.log(`Event Details: ${JSON.stringify(data, null, 2)}`, "data");
        }
      }
    },

    // Auto-submit specific framework
    autoSubmitFramework: function (framework) {
      const form = document.querySelector(
        `.cuft-test-form[data-framework="${framework}"]`
      );
      if (form) {
        this.submitTestForm(form);
        this.log(`Auto-submitted ${framework} form`, "success");
      } else {
        this.log(`Framework ${framework} not found for auto-submit`, "error");
      }
    },
  };

  // Initialize when DOM is ready
  function initWhenReady() {
    console.log("[CUFT Test Forms] Initializing...");
    if (typeof cuftTestForms !== "undefined") {
      cuftTestForms.init();
      console.log("[CUFT Test Forms] Initialized successfully");
    } else {
      console.error("[CUFT Test Forms] Object not defined");
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initWhenReady);
  } else {
    // Small delay to ensure everything is loaded
    setTimeout(initWhenReady, 100);
  }

  // Also try on window load as fallback
  window.addEventListener("load", function () {
    if (typeof cuftTestForms !== "undefined" && !cuftTestForms.initialized) {
      console.log("[CUFT Test Forms] Fallback initialization on window load");
      cuftTestForms.init();
    }
  });
})();
