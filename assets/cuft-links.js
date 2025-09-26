/* Choice Universal Form Tracker — link click tracking (non-blocking, defensive)
   - Sends phone_click for tel: links (with normalized number)
   - Also sends email_click for mailto: links (optional bonus)
   - Never prevents default; wrapped in try/catch so it cannot interfere
*/

// Set page load time for engagement tracking
try {
  window.cuftPageLoadTime = window.cuftPageLoadTime || Date.now();
} catch (e) {}

(function () {
  var DEBUG = !!window.cuftDebug;
  function log() {
    try {
      if (DEBUG && window.console)
        console.log.apply(
          console,
          ["[CUFT Links]"].concat(Array.prototype.slice.call(arguments))
        );
    } catch (e) {}
  }

  function getDL() {
    try {
      return (window.dataLayer = window.dataLayer || []);
    } catch (e) {
      return { push: function () {} };
    }
  }

  function normPhone(href) {
    try {
      return href
        .replace(/^tel:/i, "")
        .trim()
        .replace(/(?!^\+)[^\d]/g, "");
    } catch (e) {
      return href || "";
    }
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

  function recordClickEvent(eventType, clickData) {
    try {
      // Extract or generate click_id from various sources
      var clickId = getClickIdFromUtm() || getClickIdFromSession() || generateTempClickId();

      if (!clickId) {
        log("No click_id available for event recording");
        return;
      }

      // Check if WordPress AJAX is available
      if (typeof window.cuftAdmin === "undefined" || !window.cuftAdmin.ajax_url) {
        log("CUFT admin AJAX not available");
        return;
      }

      // Send event to WordPress AJAX endpoint
      var xhr = new XMLHttpRequest();
      xhr.open("POST", window.cuftAdmin.ajax_url, true);
      xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

      var params = "action=cuft_record_event" +
                   "&nonce=" + encodeURIComponent(window.cuftAdmin.nonce) +
                   "&click_id=" + encodeURIComponent(clickId) +
                   "&event_type=" + encodeURIComponent(eventType);

      xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
          if (xhr.status === 200) {
            log("Event recorded:", eventType, "for click_id:", clickId);
          } else {
            log("Failed to record event:", xhr.status);
          }
        }
      };

      xhr.send(params);
    } catch (err) {
      log("Event recording error:", err);
      // Never interfere with main functionality
    }
  }

  function getClickIdFromUtm() {
    try {
      var urlParams = new URLSearchParams(window.location.search);
      return urlParams.get('gclid') ||
             urlParams.get('fbclid') ||
             urlParams.get('click_id') ||
             urlParams.get('gbraid') ||
             urlParams.get('wbraid');
    } catch (e) {
      return null;
    }
  }

  function getClickIdFromSession() {
    try {
      return sessionStorage.getItem('cuft_click_id');
    } catch (e) {
      return null;
    }
  }

  function generateTempClickId() {
    try {
      var tempId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
      sessionStorage.setItem('cuft_click_id', tempId);
      return tempId;
    } catch (e) {
      return 'temp_' + Date.now();
    }
  }

  function onClick(e) {
    try {
      var a =
        e.target && e.target.closest
          ? e.target.closest('a[href^="tel:"], a[href^="mailto:"]')
          : null;
      if (!a) return;

      var href = a.getAttribute("href") || "";

      // Get GA4 standard parameters
      var ga4Params = getGA4StandardParams();

      if (/^tel:/i.test(href)) {
        var phone = normPhone(href);
        var payload = {
          event: "phone_click",
          clicked_phone: phone || null,
          href: href,
          clickedAt: new Date().toISOString(),
          cuft_tracked: true,
          cuft_source: "link_tracking",
        };

        // Add GA4 standard parameters
        for (var key in ga4Params) {
          if (ga4Params[key]) payload[key] = ga4Params[key];
        }

        getDL().push(payload);
        log("phone_click:", phone);

        // Record event for click tracking
        recordClickEvent("phone_click", phone);

        // do NOT prevent default; we're non-interfering by design
        return;
      }

      // Optional: email click (kept here since user snippet included mailto:)
      if (/^mailto:/i.test(href)) {
        var email = (href.replace(/^mailto:/i, "").split("?")[0] || "").trim();
        var payload = {
          event: "email_click",
          clicked_email: email || null,
          href: href,
          clickedAt: new Date().toISOString(),
          cuft_tracked: true,
          cuft_source: "link_tracking",
        };

        // Add GA4 standard parameters
        for (var key in ga4Params) {
          if (ga4Params[key]) payload[key] = ga4Params[key];
        }

        getDL().push(payload);
        log("email_click:", email);

        // Record event for click tracking
        recordClickEvent("email_click", email);

        return;
      }
    } catch (err) {
      log("link tracking error:", err);
      // swallow error — never interfere
    }
  }

  // Use capture phase so we push even if other handlers stop propagation
  try {
    document.addEventListener("click", onClick, true);
  } catch (e) {}
})();
