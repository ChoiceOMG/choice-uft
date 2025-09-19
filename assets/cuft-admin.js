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
        if (response.success) {
          var bgColor = response.update_available ? "#e8f5e8" : "#f0f0f1";
          var borderColor = response.update_available ? "#28a745" : "#646970";
          var icon = response.update_available ? "🎉" : "✅";

          // Store the latest version if update is available
          if (response.update_available) {
            latestVersion = response.latest_version;
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
              response.message +
              "</strong>" +
              (response.update_available
                ? "<br><small>Click 'Download & Install Update' to update now!</small>"
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

        if (response.success) {
          $progress.hide();
          $result.html(
            '<div style="padding: 10px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 4px;">' +
              '<strong>✅ ' + response.message + '</strong>' +
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
          $result.html(
            '<div style="padding: 10px; background: #ffeaea; border-left: 4px solid #dc3545; border-radius: 4px;">' +
              '<strong>❌ ' + response.message + '</strong>' +
              (response.details && response.details.length ?
                '<br><small>' + response.details.join('<br>') + '</small>' : '') +
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
});
