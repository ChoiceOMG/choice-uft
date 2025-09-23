jQuery(document).ready(function ($) {
  "use strict";

  // Show AJAX button if JavaScript is enabled
  $("#cuft-ajax-update-check").show();

  // Store latest version for install button
  var latestVersion = null;

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
        if (response.success && response.data) {
          var data = response.data;
          var bgColor = data.update_available ? "#e8f5e8" : "#f0f0f1";
          var borderColor = data.update_available ? "#28a745" : "#646970";
          var icon = data.update_available ? "🎉" : "✅";

          // Store the latest version if update is available
          if (data.update_available) {
            latestVersion = data.latest_version;
            $("#cuft-download-install").show();
          } else {
            latestVersion = null;
            $("#cuft-download-install").hide();
          }

          $result.html(
            '<div style="padding: 10px; background: ' +
              bgColor +
              "; border-left: 4px solid " +
              borderColor +
              '; border-radius: 4px;">' +
              "<strong>" +
              icon +
              " " +
              data.message +
              "</strong>" +
              (data.update_available
                ? "<br><small>Click 'Download & Install Update' to update now!</small>"
                : "") +
              "</div>"
          );
        } else {
          var errorMessage = (response.data && response.data.message) ? response.data.message :
                            (response.message ? response.message : "Unknown error occurred");
          $result.html(
            '<div style="padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">' +
              "<strong>⚠️ " +
              errorMessage +
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
          var errorMessage = (response.data && response.data.message) ? response.data.message :
                           (response.message ? response.message : "Connection test failed");
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

  // Handle Download & Install button
  $("#cuft-download-install").on("click", function (e) {
    e.preventDefault();

    if (!latestVersion) {
      alert("Please check for updates first.");
      return;
    }

    var $button = $(this);
    var $result = $("#cuft-update-result");
    var $progress = $("#cuft-install-progress");
    var $status = $("#cuft-install-status");

    // Confirm the action
    if (!confirm("Are you sure you want to update to version " + latestVersion + "?\n\nThe plugin will be updated automatically.")) {
      return;
    }

    // Disable buttons and show progress
    $button.prop("disabled", true);
    $("#cuft-ajax-update-check").prop("disabled", true);
    $progress.show();
    $result.empty();

    // Update status messages
    var statusMessages = [
      "Downloading update from GitHub...",
      "Extracting files...",
      "Installing update...",
      "Cleaning up..."
    ];
    var messageIndex = 0;

    var statusInterval = setInterval(function () {
      if (messageIndex < statusMessages.length - 1) {
        messageIndex++;
        $status.text(statusMessages[messageIndex]);
      }
    }, 2000);

    // Make AJAX request to install update
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_install_update",
        nonce: cuftAdmin.nonce,
        version: latestVersion,
      },
      dataType: "json",
      timeout: 60000, // 60 second timeout for installation
      success: function (response) {
        clearInterval(statusInterval);

        if (response.success && response.data) {
          $progress.hide();
          $result.html(
            '<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">' +
              '<strong>✅ ' + response.data.message + '</strong>' +
              '<br><small>Page will reload in 3 seconds...</small>' +
              '</div>'
          );

          // Hide the install button since update is complete
          $button.hide();

          // Reload the page after 3 seconds
          setTimeout(function () {
            window.location.reload();
          }, 3000);
        } else {
          $progress.hide();
          var errorMessage = (response.data && response.data.message) ? response.data.message :
                            (response.message ? response.message : "Update installation failed");
          var details = (response.data && response.data.details) ? response.data.details :
                       (response.details ? response.details : null);
          $result.html(
            '<div style="padding: 10px; background: #ffeaea; border-left: 4px solid #dc3545; border-radius: 4px;">' +
              '<strong>❌ ' + errorMessage + '</strong>' +
              (details && details.length ?
                '<br><small>' + details.join('<br>') + '</small>' : '') +
              '</div>'
          );

          // Re-enable buttons
          $button.prop("disabled", false);
          $("#cuft-ajax-update-check").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        clearInterval(statusInterval);
        $progress.hide();

        var errorMsg = "Installation failed";
        if (status === "timeout") {
          errorMsg = "Installation timed out - please try again";
        } else if (xhr.responseText) {
          try {
            var response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
          } catch (e) {
            errorMsg = "Installation failed: " + error;
          }
        }

        $result.html(
          '<div style="padding: 10px; background: #ffeaea; border-left: 4px solid #dc3545; border-radius: 4px;">' +
            '<strong>❌ ' + errorMsg + '</strong>' +
            '</div>'
        );

        // Re-enable buttons
        $button.prop("disabled", false);
        $("#cuft-ajax-update-check").prop("disabled", false);
      },
    });
  });

  // Handle Re-install Current Version button
  $("#cuft-reinstall-current").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var $result = $("#cuft-update-result");
    var $progress = $("#cuft-install-progress");
    var $status = $("#cuft-install-status");

    // Get current version from PHP constant
    var currentVersion = cuftAdmin.current_version || "unknown";

    // Confirm the action
    if (!confirm("Are you sure you want to re-install the current version (" + currentVersion + ")?\n\nThis will download and re-install the plugin to test the updater mechanism.")) {
      return;
    }

    // Disable buttons and show progress
    $button.prop("disabled", true);
    $("#cuft-ajax-update-check").prop("disabled", true);
    $("#cuft-download-install").prop("disabled", true);
    $progress.show();
    $result.empty();

    // Update status messages
    var statusMessages = [
      "Downloading current version from GitHub...",
      "Extracting files...",
      "Re-installing plugin...",
      "Cleaning up..."
    ];
    var messageIndex = 0;
    $status.text(statusMessages[messageIndex]);

    var statusInterval = setInterval(function () {
      if (messageIndex < statusMessages.length - 1) {
        messageIndex++;
        $status.text(statusMessages[messageIndex]);
      }
    }, 2000);

    // Make AJAX request to re-install current version
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_install_update",
        nonce: cuftAdmin.nonce,
        version: currentVersion,
        reinstall_current: true,
      },
      dataType: "json",
      timeout: 60000, // 60 second timeout for installation
      success: function (response) {
        clearInterval(statusInterval);

        if (response.success && response.data) {
          $progress.hide();
          $result.html(
            '<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">' +
              '<strong>✅ ' + response.data.message + '</strong>' +
              '<br><small>Re-installation completed successfully! Page will reload in 3 seconds...</small>' +
              '</div>'
          );

          // Reload the page after 3 seconds
          setTimeout(function () {
            window.location.reload();
          }, 3000);
        } else {
          $progress.hide();
          var errorMessage = (response.data && response.data.message) ? response.data.message :
                            (response.message ? response.message : "Re-installation failed");
          var details = (response.data && response.data.details) ? response.data.details :
                       (response.details ? response.details : null);
          $result.html(
            '<div style="padding: 10px; background: #ffeaea; border-left: 4px solid #dc3545; border-radius: 4px;">' +
              '<strong>❌ ' + errorMessage + '</strong>' +
              (details && details.length ?
                '<br><small>' + details.join('<br>') + '</small>' : '') +
              '</div>'
          );

          // Re-enable buttons
          $button.prop("disabled", false);
          $("#cuft-ajax-update-check").prop("disabled", false);
          $("#cuft-download-install").prop("disabled", false);
        }
      },
      error: function (xhr, status, error) {
        clearInterval(statusInterval);
        $progress.hide();

        var errorMsg = "Re-installation failed";
        if (status === "timeout") {
          errorMsg = "Re-installation timed out - please try again";
        } else if (xhr.responseText) {
          try {
            var response = JSON.parse(xhr.responseText);
            errorMsg = response.message || errorMsg;
          } catch (e) {
            errorMsg = "Re-installation failed: " + error;
          }
        }

        $result.html(
          '<div style="padding: 10px; background: #ffeaea; border-left: 4px solid #dc3545; border-radius: 4px;">' +
            '<strong>❌ ' + errorMsg + '</strong>' +
            '</div>'
        );

        // Re-enable buttons
        $button.prop("disabled", false);
        $("#cuft-ajax-update-check").prop("disabled", false);
        $("#cuft-download-install").prop("disabled", false);
      },
    });
  });

  // Handle test form submissions
  $(document).on("click", ".cuft-test-form-submit", function (e) {
    e.preventDefault();

    var $button = $(this);
    var framework = $button.data("framework");
    var email = $button.data("email"); // Email is now from data attribute
    var $result = $("#test-result-" + framework);

    // Disable button and show loading
    $button.prop("disabled", true).html("📧 Sending...");
    $result
      .html(
        '<div style="padding: 8px; background: #f0f0f1; border-radius: 4px; color: #646970;">⏳ Sending test form submission...</div>'
      )
      .slideDown();

    // Make AJAX request
    $.ajax({
      url: cuftAdmin.ajax_url,
      type: "POST",
      data: {
        action: "cuft_test_form_submit",
        nonce: cuftAdmin.nonce,
        framework: framework,
      },
      dataType: "json",
      timeout: 15000,
      success: function (response) {
        if (response.success && response.data) {
          var data = response.data.data;
          var gtmStatus = response.data.gtm_active
            ? '<span style="color: #28a745;">✓ GTM Active</span>'
            : '<span style="color: #dc3545;">✗ GTM Not Configured</span>';

          var detailsHtml =
            "<strong>Events Triggered:</strong><br>" +
            '<div style="margin-top: 5px; padding: 8px; background: #e8f5e8; border-radius: 4px; font-size: 12px;">' +
            "✅ <strong>form_submit</strong> - Always fires<br>" +
            "✅ <strong>generate_lead</strong> - Fires (email + phone + click_id present)<br>" +
            "</div>" +
            "<strong>Test Data Sent:</strong><br>" +
            '<div style="margin-top: 5px; padding: 8px; background: #f8f9fa; border-radius: 4px; font-size: 12px; font-family: monospace;">' +
            "📧 Email: " + data.user_email + "<br>" +
            "📞 Phone: " + data.user_phone + "<br>" +
            "🎯 Framework: " + data.form_framework + "<br>" +
            "📝 Form ID: " + data.form_id + "<br>" +
            "🔗 Click ID: " + data.click_id + "<br>" +
            "🔗 GCLID: " + (data.gclid || "N/A") + "<br>" +
            "🏷️ Tracking ID: " + response.data.tracking_id + "<br>";

          if (data.utm_source || data.utm_medium || data.utm_campaign) {
            detailsHtml += "<strong>UTM Data:</strong><br>";
            if (data.utm_source) detailsHtml += "Source: " + data.utm_source + "<br>";
            if (data.utm_medium) detailsHtml += "Medium: " + data.utm_medium + "<br>";
            if (data.utm_campaign) detailsHtml += "Campaign: " + data.utm_campaign + "<br>";
          }

          detailsHtml += "</div>";

          var emailStatus = response.data.email_sent
            ? '<span style="color: #28a745;">✓ Email sent to admin</span>'
            : '<span style="color: #dc3545;">✗ Email send failed</span>';

          $result.html(
            '<div style="padding: 8px; background: #d4edda; border-left: 3px solid #28a745; border-radius: 4px; color: #155724;">' +
              '<strong>✅ Test form submitted successfully!</strong><br>' +
              '<div style="margin-top: 5px; font-size: 13px;">' +
              emailStatus + " | " + gtmStatus + "<br>" +
              detailsHtml +
              "</div>" +
              "</div>"
          );
        } else {
          $result.html(
            '<div style="padding: 8px; background: #ffeaea; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">' +
              "❌ " +
              (response.data ? response.data.message : "Test submission failed") +
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
            errorMsg = response.data ? response.data.message : errorMsg;
          } catch (e) {
            // Keep default error message
          }
        }

        $result.html(
          '<div style="padding: 8px; background: #ffeaea; border-left: 3px solid #dc3545; border-radius: 4px; color: #721c24;">' +
            "❌ " +
            errorMsg +
            "</div>"
        );
      },
      complete: function () {
        // Re-enable button
        $button.prop("disabled", false).html("📧 Submit Test Form");
      },
    });
  });

  // Email validation helper
  function isValidEmail(email) {
    var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
  }
});
