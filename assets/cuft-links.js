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
