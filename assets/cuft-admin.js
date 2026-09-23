jQuery(document).ready(function ($) {
  "use strict";

  // Real-time GTM ID validation
  function validateGTMID(gtmId) {
    const gtmPattern = /^GTM-[A-Z0-9]{4,}$/i;
    return gtmPattern.test(gtmId);
  }

  function showGTMValidation(isValid, message) {
    const $input = $('input[name="gtm_id"]');
    const $validation = $("#gtm-validation-status");

    // Create validation element if it doesn't exist
    if ($validation.length === 0) {
      $input.after(
        '<div id="gtm-validation-status" style="margin-top: 8px;"></div>'
      );
    }

    if (isValid) {
      $("#gtm-validation-status").html(
        '<span class="cuft-status-indicator success">✓ Valid GTM ID format</span>'
      );
      $input.css("border-color", "#28a745");
    } else if (message) {
      $("#gtm-validation-status").html(
        '<span class="cuft-status-indicator error">✗ ' + message + "</span>"
      );
      $input.css("border-color", "#dc3545");
    } else {
      $("#gtm-validation-status").empty();
      $input.css("border-color", "");
    }
  }

  // GTM ID validation on input
  $('input[name="gtm_id"]').on("input", function () {
    const gtmId = $(this).val().trim();

    if (gtmId === "") {
      showGTMValidation(false, "");
      return;
    }

    if (validateGTMID(gtmId)) {
      showGTMValidation(true);
    } else {
      showGTMValidation(
        false,
        "GTM ID must be in format GTM-XXXX (e.g., GTM-ABC123)"
      );
    }
  });

  // Trigger validation on page load if GTM ID exists
  const $gtmInput = $('input[name="gtm_id"]');
  if ($gtmInput.length > 0) {
    const initialGTMID = $gtmInput.val().trim();
    if (initialGTMID) {
      if (validateGTMID(initialGTMID)) {
        showGTMValidation(true);
      } else {
        showGTMValidation(
          false,
          "GTM ID must be in format GTM-XXXX (e.g., GTM-ABC123)"
        );
      }
    }
  }
  // Handle sGTM checkbox toggle
  $("#cuft-sgtm-enabled").on("change", function () {
    if ($(this).is(":checked")) {
      $("#cuft-sgtm-url-row").slideDown();
      $("#cuft-health-check-row").slideDown();
      loadHealthStatus(); // Load health status when enabled
    } else {
      $("#cuft-sgtm-url-row").slideUp();
      $("#cuft-health-check-row").slideUp();
    }
  });

  // Load health check status
  function loadHealthStatus() {
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_get_sgtm_status",
        nonce: cuftAdmin.nonce,
      },
      dataType: "json",
      timeout: 10000,
      success: function (response) {
        if (response.success && response.data) {
          var data = response.data;

          // Update status display
          $("#cuft-active-server").text(
            data.status.active_server === "custom"
              ? "Custom Server"
              : "Google Fallback"
          );
          $("#cuft-last-check").text(data.human_readable.last_check);
          $("#cuft-health-status-text").text(data.human_readable.status);
          $("#cuft-consecutive-success").text(data.status.consecutive_success);
          $("#cuft-consecutive-failure").text(data.status.consecutive_failure);
          $("#cuft-next-check").text(data.human_readable.next_check);

          // Update status colors
          var statusColor =
            data.status.active_server === "custom" ? "#28a745" : "#ffc107";
          $("#cuft-health-status-text").css("color", statusColor);
        } else {
          $("#cuft-active-server").text("Error loading status");
          $("#cuft-last-check").text("Error");
          $("#cuft-health-status-text").text("Error");
          $("#cuft-consecutive-success").text("Error");
          $("#cuft-consecutive-failure").text("Error");
          $("#cuft-next-check").text("Error");
        }
      },
      error: function () {
        $("#cuft-active-server").text("Error loading status");
        $("#cuft-last-check").text("Error");
        $("#cuft-health-status-text").text("Error");
        $("#cuft-consecutive-success").text("Error");
        $("#cuft-consecutive-failure").text("Error");
        $("#cuft-next-check").text("Error");
      },
    });
  }

  // Manual health check handler
  $("#cuft-manual-health-check").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var $result = $("#cuft-health-check-result");

    // Disable button and show loading
    $button.prop("disabled", true).text("Running Health Check...");
    $result.html(
      '<span style="color: #666;">🔄 Running health check...</span>'
    );

    // Make AJAX request
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_manual_health_check",
        nonce: cuftAdmin.nonce,
      },
      dataType: "json",
      timeout: 15000,
      success: function (response) {
        if (response.success && response.data) {
          var data = response.data;
          var statusColor = data.health_check_passed ? "#28a745" : "#dc3545";
          var statusIcon = data.health_check_passed ? "✅" : "❌";

          $result.html(
            '<span style="color: ' +
              statusColor +
              ';">' +
              statusIcon +
              " " +
              data.message +
              "<br><small>Response time: " +
              data.response_time +
              "ms | " +
              "Consecutive success: " +
              data.consecutive_success +
              " | " +
              "Active server: " +
              data.active_server +
              "</small></span>"
          );

          // Reload status display
          loadHealthStatus();
        } else {
          var errorMessage =
            response.data && response.data.message
              ? response.data.message
              : response.message
              ? response.message
              : "Health check failed";
          $result.html(
            '<span style="color: #dc3545;">❌ ' + errorMessage + "</span>"
          );
        }
      },
      error: function (xhr, status, error) {
        var errorMsg = "Network error occurred";
        if (status === "timeout") {
          errorMsg = "Health check timed out";
        } else if (xhr.responseText) {
          try {
            var response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
          } catch (e) {
            // Keep default error message
          }
        }

        $result.html(
          '<span style="color: #dc3545;">❌ ' + errorMsg + "</span>"
        );
      },
      complete: function () {
        // Re-enable button
        $button.prop("disabled", false).text("Run Health Check Now");
      },
    });
  });

  // Load health status on page load if sGTM is enabled
  // Check if the health check row is visible (which means sGTM is enabled)
  if (
    $("#cuft-health-check-row").is(":visible") ||
    $("#cuft-sgtm-enabled").is(":checked")
  ) {
    loadHealthStatus();
  }

  // Handle sGTM test button
  $("#cuft-test-sgtm").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var $status = $("#cuft-sgtm-status");
    var sgtmUrl = $("#cuft-sgtm-url").val();

    if (!sgtmUrl) {
      $status.html(
        '<span style="color: #dc3545;">✗ Please enter a Server GTM URL</span>'
      );
      return;
    }

    // Disable button and show loading
    $button.prop("disabled", true).text("Testing...");
    $status.html(
      '<span style="color: #666;">🔄 Testing connection to server GTM endpoints...</span>'
    );

    // Make AJAX request
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_test_sgtm",
        nonce: cuftAdmin.nonce,
        sgtm_url: sgtmUrl,
      },
      dataType: "json",
      timeout: 15000, // 15 second timeout
      success: function (response) {
        if (response.success && response.data) {
          var detailsHtml = "";
          if (response.data.details) {
            detailsHtml = "<br><small>";
            if (response.data.details.gtm_js) {
              detailsHtml += "gtm.js: " + response.data.details.gtm_js;
            }
            if (response.data.details.ns_html) {
              detailsHtml += " | ns.html: " + response.data.details.ns_html;
            }
            detailsHtml += "</small>";
          }

          $status.html(
            '<span style="color: #28a745;">✓ ' +
              response.data.message +
              detailsHtml +
              "</span>"
          );
        } else {
          var errorMessage =
            response.data && response.data.message
              ? response.data.message
              : response.message
              ? response.message
              : "Connection test failed";
          var errorHtml = '<span style="color: #dc3545;">✗ ' + errorMessage;
          if (response.data && response.data.details) {
            errorHtml += "<br><small>";
            if (response.data.details.gtm_js) {
              errorHtml += "gtm.js: " + response.data.details.gtm_js;
            }
            if (response.data.details.ns_html) {
              errorHtml += " | ns.html: " + response.data.details.ns_html;
            }
            errorHtml += "</small>";
          }
          errorHtml += "</span>";
          $status.html(errorHtml);
        }
      },
      error: function (xhr, status, error) {
        var errorMsg = "Network error occurred";
        if (status === "timeout") {
          errorMsg = "Request timed out - server may be unreachable";
        } else if (xhr.responseText) {
          try {
            var response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
          } catch (e) {
            // Keep default error message
          }
        }

        $status.html('<span style="color: #dc3545;">✗ ' + errorMsg + "</span>");
      },
      complete: function () {
        // Re-enable button
        $button.prop("disabled", false).text("Test Connection");
      },
    });
  });
  // Handle Generate Lead settings show/hide
  $("#cuft-generate-lead-enabled").on("change", function () {
    var $leadSettings = $("#cuft-lead-settings");
    if ($(this).is(":checked")) {
      $leadSettings.slideDown();
    } else {
      $leadSettings.slideUp();
    }
  });

  // Phone Validation: Register Site
  $("#cuft-register-site").on("click", function () {
    var $btn = $(this);
    var $status = $("#cuft-register-status");
    $btn.prop("disabled", true).text("Registering…");
    $status.hide();
    $.post(
      cuftAdmin.ajax_url,
      {
        action: "cuft_token_register",
        nonce: cuftAdmin.register_nonce,
      },
      function (response) {
        if (response.success) {
          $status
            .css("color", "#3a7c3a")
            .text("Registered: " + response.data.domain)
            .show();
          $btn.text("Re-register Site");
        } else {
          $status
            .css("color", "#a00")
            .text("Error: " + (response.data || "Unknown error"))
            .show();
        }
        $btn.prop("disabled", false);
      }
    ).fail(function () {
      $status.css("color", "#a00").text("Request failed").show();
      $btn.prop("disabled", false);
    });
  });

  // GTM Template Download buttons
  $(".cuft-download-template").on("click", function (e) {
    e.preventDefault();
    var $button = $(this);
    var template = $button.data("template");
    var originalText = $button.html();

    $button
      .prop("disabled", true)
      .html('<span class="dashicons dashicons-update spin"></span> Downloading...');

    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_download_gtm_template",
        template: template,
        nonce: cuftAdmin.nonce,
      },
      success: function (response) {
        if (response.success) {
          // Decode base64 content
          var content = atob(response.data.content);
          var filename = response.data.filename;

          // Create blob and download
          var blob = new Blob([content], { type: "application/json" });
          var url = window.URL.createObjectURL(blob);
          var a = document.createElement("a");
          a.href = url;
          a.download = filename;
          document.body.appendChild(a);
          a.click();
          window.URL.revokeObjectURL(url);
          document.body.removeChild(a);

          // Show success feedback
          $button.html('<span class="dashicons dashicons-yes"></span> Downloaded!');
          setTimeout(function () {
            $button.prop("disabled", false).html(originalText);
          }, 2000);
        } else {
          alert(response.data.message || "Download failed");
          $button.prop("disabled", false).html(originalText);
        }
      },
      error: function () {
        alert("Download failed");
        $button.prop("disabled", false).html(originalText);
      },
    });
  });

  // Click Tracking page: select the webhook URL field on click
  $(".cuft-select-on-click").on("click", function () {
    this.select();
  });

  // Click Tracking page: show a colored status line built with text nodes,
  // so a click_id or server message is never parsed as HTML.
  function showWebhookResult(color, text) {
    var resultDiv = document.getElementById("webhook-test-result");
    if (!resultDiv) {
      return;
    }
    resultDiv.textContent = "";
    var span = document.createElement("span");
    if (color) {
      span.style.color = color;
    }
    span.textContent = text;
    resultDiv.appendChild(span);
  }

  // Click Tracking page: test the public webhook endpoint
  $("#cuft-test-webhook").on("click", function () {
    var resultDiv = document.getElementById("webhook-test-result");
    var input = document.getElementById("test-click-id");
    var clickId = input ? input.value.trim() : "";

    if (!clickId) {
      showWebhookResult("#dc3545", "❌ Please enter a click_id to test");
      return;
    }

    if (resultDiv) {
      resultDiv.textContent = "";
      var em = document.createElement("em");
      em.textContent = "Testing webhook...";
      resultDiv.appendChild(em);
    }

    var testUrl =
      cuftAdmin.ajax_url +
      "?action=cuft_webhook&click_id=" +
      encodeURIComponent(clickId) +
      "&qualified=1&score=8";

    fetch(testUrl)
      .then(function (response) {
        return response.json();
      })
      .then(function (data) {
        if (data.success) {
          showWebhookResult(
            "#28a745",
            "✅ Webhook test successful! Click ID \"" + clickId + "\" updated."
          );
        } else {
          showWebhookResult(
            "#dc3545",
            "❌ Webhook test failed: " +
              (data.data ? data.data.message : "Unknown error")
          );
        }
      })
      .catch(function (error) {
        showWebhookResult("#dc3545", "❌ Webhook test failed: " + error.message);
      });
  });

  // Click Tracking page: copy a Click ID into the webhook test field
  $(document).on("click", ".cuft-click-id-copy", function () {
    var clickId = $(this).attr("data-click-id");
    var testInput = document.getElementById("test-click-id");
    if (!testInput) {
      return;
    }
    testInput.value = clickId;
    testInput.focus();

    // Visual feedback
    testInput.style.background = "#e7f3ff";
    setTimeout(function () {
      testInput.style.background = "";
    }, 500);

    // Scroll to webhook section if not visible
    var webhookSection = testInput.closest(".cuft-click-tracking");
    if (webhookSection) {
      var rect = testInput.getBoundingClientRect();
      if (rect.top < 0 || rect.bottom > window.innerHeight) {
        testInput.scrollIntoView({ behavior: "smooth", block: "center" });
      }
    }
  });

  // Click Tracking page: edit modal
  function closeEditModal() {
    var modal = document.getElementById("edit-click-modal");
    if (modal) {
      modal.style.display = "none";
    }
  }

  $(document).on("click", ".cuft-edit-click", function () {
    var $btn = $(this);
    var qualified = parseInt($btn.attr("data-qualified"), 10);
    document.getElementById("edit-click-id").value = $btn.attr("data-click-id");
    document.getElementById("edit-qualified-" + (qualified ? "yes" : "no")).checked = true;
    document.getElementById("edit-score").value = parseInt($btn.attr("data-score"), 10);
    document.getElementById("edit-click-modal").style.display = "block";
  });

  $(document).on("click", ".cuft-close-edit-modal", function () {
    closeEditModal();
  });

  // Close modal when clicking outside
  $("#edit-click-modal").on("click", function (e) {
    if (e.target === this) {
      closeEditModal();
    }
  });
});
