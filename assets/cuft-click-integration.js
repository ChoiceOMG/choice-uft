(function () {
  "use strict";

  // Only run if we have click data
  if (!window.cuftClickData || !window.cuftClickData.click_id) {
    return;
  }

  var DEBUG = !!(window.cuftConfig && window.cuftConfig.console_logging);

  function log() {
    try {
      if (DEBUG && window.console && window.console.log) {
        window.console.log.apply(
          window.console,
          ["[CUFT Click Integration]"].concat(
            Array.prototype.slice.call(arguments)
          )
        );
      }
    } catch (e) {}
  }

  function addClickIdToDataLayer() {
    try {
      var dataLayer = (window.dataLayer = window.dataLayer || []);

      // Add click ID to dataLayer for tracking
      dataLayer.push({
        event: "click_id_detected",
        click_id: window.cuftClickData.click_id,
        cuft_tracked: true,
        cuft_source: "click_integration",
      });

      log("Click ID added to dataLayer:", window.cuftClickData.click_id);
    } catch (e) {
      log("Error adding click ID to dataLayer:", e);
    }
  }

  function enhanceFormSubmissions() {
    try {
      // Find all forms and add click ID as hidden field
      var forms = document.querySelectorAll("form");

      for (var i = 0; i < forms.length; i++) {
        var form = forms[i];

        // Skip if already has click ID field
        if (form.querySelector('input[name="cuft_click_id"]')) {
          continue;
        }

        // Add hidden field with click ID
        var hiddenField = document.createElement("input");
        hiddenField.type = "hidden";
        hiddenField.name = "cuft_click_id";
        hiddenField.value = window.cuftClickData.click_id;

        form.appendChild(hiddenField);

        log("Added click ID field to form:", form.className || "unnamed");
      }
    } catch (e) {
      log("Error enhancing form submissions:", e);
    }
  }

  function observeNewForms() {
    try {
      // Watch for dynamically added forms
      if (window.MutationObserver) {
        var observer = new MutationObserver(function (mutations) {
          mutations.forEach(function (mutation) {
            if (mutation.type === "childList") {
              for (var i = 0; i < mutation.addedNodes.length; i++) {
                var node = mutation.addedNodes[i];
                if (node.nodeType === 1) {
                  // Element node
                  if (node.tagName === "FORM") {
                    enhanceForm(node);
                  } else if (node.querySelector && node.querySelector("form")) {
                    enhanceFormSubmissions();
                  }
                }
              }
            }
          });
        });

        observer.observe(document.body, {
          childList: true,
          subtree: true,
        });

        log("Form observer initialized");
      }
    } catch (e) {
      log("Error setting up form observer:", e);
    }
  }

  function enhanceForm(form) {
    try {
      // Skip if already has click ID field
      if (form.querySelector('input[name="cuft_click_id"]')) {
        return;
      }

      // Add hidden field with click ID
      var hiddenField = document.createElement("input");
      hiddenField.type = "hidden";
      hiddenField.name = "cuft_click_id";
      hiddenField.value = window.cuftClickData.click_id;

      form.appendChild(hiddenField);

      log("Enhanced new form with click ID:", form.className || "unnamed");
    } catch (e) {
      log("Error enhancing form:", e);
    }
  }

  // Initialize when DOM is ready
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

  ready(function () {
    log(
      "Initializing click integration for click ID:",
      window.cuftClickData.click_id
    );

    // Add click ID to dataLayer
    addClickIdToDataLayer();

    // Enhance existing forms
    enhanceFormSubmissions();

    // Watch for new forms
    observeNewForms();

    log("Click integration initialized");
  });
})();
