/**
 * CUFT Passive Click Ping
 *
 * On every page load, resolves a click ID (URL params, then _cuft_click cookie)
 * and POSTs click context to https://<collector_host>/p via navigator.sendBeacon
 * (with a fetch keepalive fallback). Enabled only when the admin has set the
 * "Click Collector Host" setting.
 */
(function () {
    "use strict";

    if (!window.cuftPing || !window.cuftPing.collector_host) {
        return;
    }

    var DEBUG = !!window.cuftPing.debug;

    function log() {
        if (DEBUG && window.console && window.console.log) {
            window.console.log.apply(
                window.console,
                ["[CUFT Ping]"].concat(Array.prototype.slice.call(arguments))
            );
        }
    }

    function readCookie(name) {
        try {
            var prefix = name + "=";
            var parts = document.cookie.split(";");
            for (var i = 0; i < parts.length; i++) {
                var c = parts[i].replace(/^\s+/, "");
                if (c.indexOf(prefix) === 0) {
                    return decodeURIComponent(c.substring(prefix.length));
                }
            }
        } catch (e) {}
        return "";
    }

    function param(name) {
        try {
            var s = window.location.search;
            if (s.charAt(0) === "?") s = s.substring(1);
            var pairs = s.split("&");
            for (var i = 0; i < pairs.length; i++) {
                var eq = pairs[i].indexOf("=");
                if (eq > 0 && decodeURIComponent(pairs[i].substring(0, eq)) === name) {
                    return decodeURIComponent(pairs[i].substring(eq + 1));
                }
            }
        } catch (e) {}
        return "";
    }

    function mapPlatform(n) {
        return (
            {
                gclid: "google",
                gbraid: "google",
                wbraid: "google",
                fbclid: "meta",
                rdt_cid: "reddit",
                msclkid: "microsoft",
                ttclid: "tiktok",
                twclid: "x",
                li_fat_id: "linkedin",
                snap_click_id: "snap",
                pclid: "pinterest"
            }[n]
        ) || "unknown";
    }

    function resolveClickID() {
        var names = [
            "gclid",
            "gbraid",
            "wbraid",
            "fbclid",
            "rdt_cid",
            "msclkid",
            "ttclid",
            "twclid",
            "li_fat_id",
            "snap_click_id",
            "pclid",
            "click_id"
        ];
        for (var i = 0; i < names.length; i++) {
            var v = param(names[i]);
            if (v) return { id: v, platform: mapPlatform(names[i]) };
        }
        var c = readCookie("_cuft_click");
        if (c) return { id: c, platform: "unknown" };
        return null;
    }

    function gaClientID() {
        var v = readCookie("_ga");
        if (!v) return "";
        // _ga cookie value: GA1.2.<clientId1>.<clientId2>
        var m = v.match(/GA\d\.\d\.(\d+\.\d+)/);
        return m ? m[1] : "";
    }

    function send(payload) {
        var url = "https://" + window.cuftPing.collector_host + "/p";
        var body = JSON.stringify(payload);
        var ok = false;
        try {
            var blob = new Blob([body], { type: "application/json" });
            ok = !!(navigator.sendBeacon && navigator.sendBeacon(url, blob));
        } catch (e) {}
        if (!ok) {
            try {
                fetch(url, {
                    method: "POST",
                    mode: "no-cors",
                    keepalive: true,
                    headers: { "Content-Type": "application/json" },
                    body: body
                });
            } catch (e) {
                log("send failed", e);
            }
        }
    }

    function run() {
        var click = resolveClickID();
        if (!click) {
            log("no click_id resolved; not pinging");
            return;
        }
        var payload = {
            click_id: click.id,
            platform_hint: click.platform,
            utm_source: param("utm_source"),
            utm_medium: param("utm_medium"),
            utm_campaign: param("utm_campaign"),
            utm_term: param("utm_term"),
            utm_content: param("utm_content"),
            referrer: document.referrer || "",
            landing_url: window.location.href,
            ga_client_id: gaClientID()
        };
        send(payload);
        log("pinged", payload);
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", run);
    } else {
        run();
    }
})();
