jQuery(document).ready(function ($) {
  "use strict";

  // Show AJAX button if JavaScript is enabled
  $("#cuft-ajax-update-check").show();

  // Handle AJAX update check
  $("#cuft-ajax-update-check").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var $result = $("#cuft-update-result");

    // Disable button and show loading
    $button.prop("disabled", true).text("Checking...");
    $result.html(
      '<div style="padding: 10px; background: #f0f0f1; border-radius: 4px; color: #646970;">🔄 Checking for updates...</div>'
    );

    // Make AJAX request
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_manual_update_check",
        nonce: cuftAdmin.nonce,
      },
      dataType: "json",
      timeout: 30000, // 30 second timeout
      success: function (response) {
        if (response.success) {
          var bgColor = response.update_available ? "#e8f5e8" : "#f0f0f1";
          var borderColor = response.update_available ? "#28a745" : "#646970";
          var icon = response.update_available ? "🎉" : "✅";

          $result.html(
            '<div style="padding: 10px; background: ' +
              bgColor +
              "; border-left: 4px solid " +
              borderColor +
              '; border-radius: 4px;">' +
              "<strong>" +
              icon +
              " " +
              response.message +
              "</strong>" +
              (response.update_available
                ? "<br><small>Go to the WordPress Plugins page to update.</small>"
                : "") +
              "</div>"
          );
        } else {
          $result.html(
            '<div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">' +
              "<strong>⚠️ " +
              response.message +
              "</strong>" +
              "</div>"
          );
        }
      },
      error: function (xhr, status, error) {
        var errorMsg = "Network error occurred";
        if (status === "timeout") {
          errorMsg = "Request timed out - please try again";
        } else if (xhr.responseText) {
          try {
            var response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
          } catch (e) {
            // Keep default error message
          }
        }

        $result.html(
          '<div style="padding: 10px; background: #ffeaea; border-left: 4px solid #dc3545; border-radius: 4px;">' +
            "<strong>❌ " +
            errorMsg +
            "</strong>" +
            "</div>"
        );
      },
      complete: function () {
        // Re-enable button
        $button.prop("disabled", false).text("Check for Updates (AJAX)");
      },
    });
  });

  // Handle sGTM checkbox toggle
  $("#cuft-sgtm-enabled").on("change", function () {
    if ($(this).is(":checked")) {
      $("#cuft-sgtm-url-row").slideDown();
    } else {
      $("#cuft-sgtm-url-row").slideUp();
    }
  });

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
        if (response.success) {
          var detailsHtml = "";
          if (response.details) {
            detailsHtml = "<br><small>";
            if (response.details.gtm_js) {
              detailsHtml += "gtm.js: " + response.details.gtm_js;
            }
            if (response.details.ns_html) {
              detailsHtml += " | ns.html: " + response.details.ns_html;
            }
            detailsHtml += "</small>";
          }

          $status.html(
            '<span style="color: #28a745;">✓ ' +
              response.message +
              detailsHtml +
              "</span>"
          );
        } else {
          var errorHtml = '<span style="color: #dc3545;">✗ ' + response.message;
          if (response.details) {
            errorHtml += "<br><small>";
            if (response.details.gtm_js) {
              errorHtml += "gtm.js: " + response.details.gtm_js;
            }
            if (response.details.ns_html) {
              errorHtml += " | ns.html: " + response.details.ns_html;
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
});
