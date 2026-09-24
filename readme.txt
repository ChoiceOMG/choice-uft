=== Choice Universal Form Tracker ===
Contributors: jaffray
Tags: forms, form tracking, analytics, conversion tracking, utm
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.28.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

One dataLayer event shape for every form plugin on your site, with campaign attribution attached to each submission.

== Description ==

Five form plugins, one event shape. Each form plugin fires its submissions
differently, so a site running more than one ends up with a different tag, a
different trigger, and a different set of field names for each. This plugin
detects whichever form plugins are active and emits a single, consistent
`form_submit` event for all of them, so one tag in Google Tag Manager covers
the whole site.

Campaign attribution rides along. UTM parameters and advertising click IDs are
captured on the visitor's first landing and stored for 30 days, so a form
submitted a week after the ad click still reports the campaign that produced
it.

= Supported form plugins =

Detection is automatic. No configuration is needed beyond activating the
plugin, and nothing is loaded for a form plugin that is not installed.

* Contact Form 7
* Gravity Forms
* Ninja Forms
* Elementor Pro forms
* Avada / Fusion Builder forms

These are third-party products, listed here to describe compatibility. This
plugin is not affiliated with, endorsed by, or supported by their makers.

= What gets tracked =

* `form_submit` on every detected submission, carrying the form type, form ID,
  and the email address and phone number the visitor entered
* `generate_lead` when a submission includes a valid email address and the
  Generate Lead Events setting is on (off by default on new installs)
* `qualify_lead` when a submission carries an email address, a phone number,
  and an advertising click ID
* `phone_click` when a visitor clicks a `tel:` link
* UTM parameters (source, medium, campaign, term, content) on all of the above
* Advertising click IDs: `gclid`, `gbraid`, `wbraid`, `fbclid`, `msclkid`,
  `ttclid`, `twclid`, `rdt_cid`, `li_fat_id`, `snap_click_id`, and `pclid`

Every event carries `cuft_tracked: true`, which gives you one reliable
condition to build triggers on.

= Shared lead identity =

Each event can carry a `lead_id`: the SHA-256 hash of the visitor's email
address, lowercased and trimmed, or of their phone number in E.164 form when
no email was given. The same hash is computed server-side, so an analytics
event and a downstream CRM record derived from the same person produce the
same identifier without any raw personal data reaching your analytics.

The normalisation matches what Google Enhanced Conversions and Meta CAPI
expect, so the value doubles as a match key for offline conversion imports.

= Built to stay out of the way =

* No jQuery dependency; the tracking scripts are plain JavaScript
* No third-party CDN requests; every library the plugin needs ships with it
* Framework scripts load only for form plugins that are actually present
* Tracking failures are contained and never block a form submission

= Who maintains this =

Choice OMG maintains this plugin. We are a digital marketing and web agency in
Edmonton, Alberta, and we built it because our clients' sites run several form
plugins at once and each one needed its own analytics plumbing.

== External services ==

This plugin can connect to the external services below. Every one of them is
off on a new install and stays off until an administrator configures it on
the settings screen. With none configured, the plugin only pushes events to
the `window.dataLayer` array in the visitor's own browser and sends nothing
anywhere.

= Google Tag Manager =

Used when you enter a GTM container ID on the settings screen. Leave that
field empty and no request is made; the plugin then pushes events to a
dataLayer supplied by your own GTM installation instead.

*What is sent:* the visitor's browser requests the container script from
`https://www.googletagmanager.com/gtm.js` (and, for visitors without
JavaScript, the `ns.html` frame). As with any GTM installation, Google
receives the request, the visitor's IP address and browser details, and
whatever your own container tags choose to send, which will include the form
and campaign data described above if you configure tags to forward it.

*When:* on every front-end page load, once a container ID is set.

* Service: [Google Tag Manager](https://marketingplatform.google.com/about/tag-manager/)
* Terms of service: [https://www.google.com/analytics/terms/tag-manager/](https://www.google.com/analytics/terms/tag-manager/)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Server-side Google Tag Manager (optional custom host) =

Used only when you enable server-side GTM and enter your own tagging server
URL. Off by default.

*What is sent:* the container script is requested from the host you entered
instead of from Google, so that host receives the same page-load request
described above. To decide whether the host is healthy, the plugin also
requests `gtm.js` and `ns.html` from it, server to server, when you press
the test button and on a six-hour schedule. Those checks carry the container
ID and no visitor data. When the custom host fails its check, the plugin
falls back to `www.googletagmanager.com`.

*When:* on every front-end page load, and on the six-hour health check, while
server-side GTM is enabled.

You choose the host, so you decide who receives the request and which terms
apply.

= Google Analytics Measurement Protocol =

Used only when you supply both a GA4 Measurement ID and an API secret on the
settings screen. Without both, no request is made.

*What is sent:* a server-side event to
`https://www.google-analytics.com/mp/collect`, containing the GA client ID
read from the visitor's `_ga` cookie, the event name, the configured lead
value and currency, and the event parameters for that submission.

*When:* when a lead's status is updated through the plugin's webhook (for
example, a lead marked qualified).

* Service: [Google Analytics](https://marketingplatform.google.com/about/analytics/)
* Terms of service: [https://marketingplatform.google.com/about/analytics/terms/us/](https://marketingplatform.google.com/about/analytics/terms/us/)
* Privacy policy: [https://policies.google.com/privacy](https://policies.google.com/privacy)

= Click collector (optional custom host) =

Used only when you enter a Click Collector Host on the settings screen. Empty
by default, in which case nothing is sent.

*What is sent:* the visitor's browser sends a POST (`navigator.sendBeacon`)
to `https://<the host you entered>/p` carrying the advertising click ID and
its platform, the five UTM parameters, the referring URL, the landing page
URL, and the GA client ID from the `_ga` cookie.

*When:* on page load, only when a click ID is present in the URL or in the
plugin's own click cookie.

You choose the host. Point it at your own collector, or at the one Choice OMG
operates for its clients, in which case Choice OMG receives the data above
under the terms and privacy policy linked in the next section.

= Choice OMG phone validation service =

Used only when an administrator switches phone validation on and registers
the site with a registration secret that Choice OMG issues to its clients.
Both are off and empty by default, so this service is never contacted on a
standard install.

*What is sent:* registering the site (the Register Site button) sends the
site's domain name and the registration secret to
`https://phone-validator.choice.zone`. After that, each phone number to be
checked is sent from your server to
`https://phone-validator.choice.zone/validate-phone` together with the
site's registration token. Choice OMG's service looks the number up with its
own providers, Twilio Lookup and Abstract API, and returns whether the number
is valid, its line type, and a quality score.

*When:* when a submitted phone number is validated. Results are cached on your
site for 24 hours per number, so a repeated number is not sent again within
that window.

* Service: [Choice OMG](https://choice.marketing/tools/choice-uft/)
* Terms of service: [https://choice.marketing/terms/](https://choice.marketing/terms/)
* Privacy policy: [https://choice.marketing/privacy-policy/](https://choice.marketing/privacy-policy/)
* Sub-processor Twilio: [terms](https://www.twilio.com/en-us/legal/tos), [privacy policy](https://www.twilio.com/en-us/legal/privacy)
* Sub-processor Abstract API: [terms and privacy policy](https://www.abstractapi.com/legal/legal)

Because form submissions can carry personal data, review your own privacy
policy and any applicable data-protection obligations (GDPR, CCPA, PIPEDA and
similar) before enabling any of these services.

== Installation ==

1. Install the plugin through the Plugins screen, or upload the `choice-universal-form-tracker` folder to `/wp-content/plugins/`.
2. Activate it through the Plugins screen.
3. Go to **Settings > Universal Form Tracker**.
4. Enter your Google Tag Manager container ID, or leave it empty to use your own GTM installation.
5. Confirm your form plugins appear under Framework Detection Status.

Nothing else is required. Tracking starts as soon as the plugin is active.

== Frequently Asked Questions ==

= Do I need Google Tag Manager? =

No. The plugin pushes its events to `window.dataLayer` whether or not it
injects a container for you. If you already load GTM through a theme or
another plugin, leave the container ID empty and the events will reach your
existing container.

= Which form plugins does it support? =

Contact Form 7, Gravity Forms, Ninja Forms, Elementor Pro forms, and Avada /
Fusion Builder forms. Detection is automatic; scripts for a form plugin that
is not installed are never loaded.

= What data does it collect, and where does it go? =

Form submissions produce dataLayer events containing the email address and
phone number entered, the form's identity, and the campaign parameters
captured when the visitor arrived. Those events go to your own analytics
setup. Nothing leaves your site unless you configure one of the services
listed under External services.

= How long is campaign attribution kept? =

UTM parameters and click IDs are stored in the visitor's browser for 30 days
from their first visit carrying them, so attribution survives a gap between
the ad click and the form submission. First-touch attribution is kept for a
year in a separate cookie.

= Is it GDPR compliant? =

That depends on how you configure it. The plugin writes campaign parameters to
the visitor's browser and puts submitted contact details into your dataLayer,
so it processes personal data and belongs in your privacy policy and your
consent flow. Review the External services section for anything that leaves
your site.

= Does it slow the site down? =

The tracking scripts are plain JavaScript with no jQuery dependency, and each
form plugin's handler loads only when that form plugin is present. Tracking
errors are caught so a failure cannot block a form submission.

= Can my CRM or email platform update a lead's status? =

Yes. The Click Tracking screen shows a webhook URL that marks a tracked click
as qualified, scores it, or records a lifecycle status such as `qualify_lead`.
Each request must carry the site's webhook key, which the same screen displays
and can regenerate. Sites that installed a version before 3.28.0 keep the key
optional until an administrator switches on "Require webhook key", so their
existing links keep working.

= Can I change what is tracked? =

Yes. Filters cover the computed `lead_id` (`cuft_lead_id`), the phone country
code used to normalise it (`cuft_lead_id_phone_country`), the attribution
payload (`cuft_form_attribution_payload`), and whether the plugin runs at all
(`cuft_enabled`).

== Screenshots ==

1. Settings: Google Tag Manager container, server-side GTM, lead events and phone validation.
2. Framework detection for Avada, Elementor Pro, Contact Form 7, Ninja Forms and Gravity Forms, with the active tracking features.
3. Click Tracking: click IDs, qualification status and export for Google Ads offline conversions.
4. Testing Dashboard: generate sample data and simulate events to validate tracking.

== Changelog ==

= 3.28.3 =
* Click table repair now records MySQL's own error text. 3.28.2 read the error after running its existence check, which clears it, so failures showed "Unknown database error". When the table is still missing after dbDelta(), the CREATE is run once directly to capture the error, and a failed repair waits an hour before retrying instead of retrying on every admin page load.

= 3.28.2 =
* A click write that fails (missing table, missing column, or a database error) is now logged, and shows as a notice on the Click Tracking admin page, instead of failing silently. A missing click tracking table, or a table missing the `events` or `ga_client_id` columns added by earlier versions, is repaired automatically.
* The Click Tracking admin list now shows every click by default. It previously hid any click that never fired a form event (e.g. a click ID captured but no form submitted yet), which could make a site with real click data look empty.

= 3.28.1 =
* Removed the deprecated strict `generate_lead` dual-fire that 3.28.0 kept for one version. A submission with an email, a phone number and a click ID was pushing `generate_lead` twice (once broad, once with the strict payload and `cuft_deprecated: true`) and recording it once, so a GTM trigger on `generate_lead` counted that lead twice. `generate_lead` now fires at most once per submission; `qualify_lead` is unchanged. Update any GTM trigger that matched on `cuft_deprecated` equals `true`, since that copy no longer fires; a trigger on the event name `generate_lead` alone needs no change beyond seeing one event per lead instead of two.
* Removed a second, independent source of the same double-count: Gravity Forms also pushed `generate_lead` from the server (`gform_after_submission`) in addition to the client-side push every form framework already fires. Only Gravity Forms submissions that complete as a full page render (not an AJAX/iframe confirmation) were affected.

= 3.28.0 =
* `generate_lead` now fires only when the Generate Lead Events setting is on, covering the dataLayer push, the deprecated strict dual-fire, and the event recorded on the click. Earlier versions pushed it whatever the setting said. Sites upgrading from an earlier version have the setting switched on automatically so nothing changes for them; new installs start with it off.
* The webhook can now require a shared key (Require webhook key, on the Click Tracking screen). Requests without the right `key` are rejected with HTTP 403. On by default for new installs; sites upgrading keep it off until an administrator turns it on, so existing links keep working.
* Removed the Test Form Builder and its test mode. Test forms and pages created by earlier versions are deleted on upgrade and on uninstall, identified only by the plugin's own marker meta. The Gravity Forms and Ninja Forms scripts no longer inject test click IDs on URLs containing `test=1`, `cuft_test=1` or `-test-form`.
* Requires WordPress 6.2 or later. Every database query now goes through `$wpdb->prepare()`, with table and column names bound through the `%i` placeholder that 6.2 introduced.
* Hardened input handling across the plugin: every request, cookie and server value is unslashed and sanitized before use, and every admin action and AJAX handler checks both a nonce and the `manage_options` capability.
* The plugin's admin notices appear only on its own screens and the Plugins screen, and the informational notice can be dismissed permanently.
* Inline scripts and styles in the admin screens moved into enqueued assets; the Google Tag Manager loader is now enqueued rather than echoed, and prints with the other head scripts.
* CSV exports prefix a leading apostrophe to visitor-supplied values that start with `=`, `+`, `-` or `@`, so a spreadsheet cannot run them as formulas.
* Fixed a stray translator comment printed on the Testing Dashboard, a migration index check that failed on its second run, and debug log entries that recorded the log level as the message.
* Uninstall now removes the test events table, transient timeouts, and the scheduled events the plugin actually registers.

= 3.27.1 =
* Attribution cookies are written by the page itself, so a page served from cache after its nonce expired still records UTM and click-ID attribution.

= 3.27.0 =
* Attribution now reaches stored Elementor Pro submissions: it is captured before any submit action runs, and `submitted_at` reports the submit time rather than the time a later action fired.

= 3.26.1 =
* Fixed phantom `form_submit` events on Avada/Fusion forms. The success check read the inline `style` attribute, so a success message that Avada ships in the page markup and hides with a CSS class counted as a submission on any render, including ad-crawler and lock-screen renders that never touched the form. Detection now uses computed style plus layout and requires the success state to appear after the submission.
* Avada submissions carrying neither an email address nor a phone number no longer push `form_submit`. Other form frameworks are unchanged.
* Narrowed the Avada click watcher to real submit controls, so ordinary Fusion buttons elsewhere on the page no longer start submission tracking.

= 3.26.0 =
* Removed the CryptoJS dependency loaded from a third-party CDN. SHA-256 now ships with the plugin, which removes a network request, removes the race where a slow CDN response left `lead_id` off an event, and makes the hash available synchronously.
* Fixed the GitHub updates setting being silently switched off every time the settings form was saved. The checkbox it read had been removed, so the saved value was always false.
* Aligned the declared PHP requirement at 7.4 across the plugin header, readme.txt, and the runtime notice, which previously named 7.0.
* Added `Plugin URI`, `Requires at least`, `Requires PHP`, and `License URI` to the plugin header.

= 3.25.0 =
* Added a shared `lead_id` on every lead: the SHA-256 of the normalised email address, or of the phone number in E.164 form when no email is present, so one identifier matches a lead across analytics, the stored entry, the notification email, and downstream systems.
* Added `lead_id_source`, recording whether the hash came from the email address or the phone number.
* Added the `cuft_lead_id` and `cuft_lead_id_phone_country` filters.

= 3.24.0 =
* Added server-side form attribution: UTM parameters, click IDs, first-touch campaign data, landing page, referrer, and service interest are now attached to the submission server-side rather than only reaching the dataLayer.
* Added a write-once first-touch cookie with a one-year lifetime, preserving the first attributed visit.
* Added the `cuft_service_interest_map` option and the `cuft_service_interest` and `cuft_form_attribution_payload` filters.

= 3.23.0 =
* Added Reddit Ads `rdt_cid` to the tracked click IDs.
* Added an optional Click Collector Host setting for first-party click logging.
* Added a cookie fallback for the click ID when URL parameters are absent.

= 3.22.1 =
* Changed the Click Tracking tab to show only clicks with events attached by default. Select "All" in the Events filter to see every click.

= 3.22.0 =
* Added GA4 lifecycle event names: `qualify_lead` for a submission with email, phone, and click ID; `generate_lead` for any submission with a valid email address.
* Added server-side GA4 Measurement Protocol events, configurable with a Measurement ID and API secret.
* Added client-side event replay, so webhook events reach the dataLayer on the visitor's next pageview.
* Added `ga_client_id` capture from the `_ga` cookie at submission time.

= 3.21.0 =
* Added Cloudflare visitor IP detection through `HTTP_CF_CONNECTING_IP`.
* Added a PHPUnit test suite and a CI workflow covering PHP 7.4 through 8.2.

= 3.19.0 =
* Added update history tracking and capability checks on update operations.

= 3.5.0 =
* Added server-side Google Tag Manager support, so the container script can be served from your own tagging server.

= 3.1.0 =
* Added UTM campaign tracking for marketing attribution.

= 3.0.0 =
* Rewrote the plugin around a modular architecture with automatic framework detection, and removed the jQuery dependency.

== Upgrade Notice ==

= 3.28.0 =
Requires WordPress 6.2 or later. Existing sites keep generate_lead firing and the webhook open; new installs start with generate_lead off and the webhook key required. The Test Form Builder is removed and its test pages are deleted.

= 3.26.1 =
Stops phantom form submissions on Avada/Fusion sites. If your Avada conversion counts have looked inflated, this is the fix. No settings change is needed.

= 3.26.0 =
Removes a third-party CDN request and makes `lead_id` reliable on fast submissions. No settings change is needed.

= 3.25.0 =
Adds a shared `lead_id` to lead events. No reconfiguration needed.

= 3.22.0 =
Changes what `generate_lead` means: it now fires on any submission with a valid email address, and the stricter email-plus-phone-plus-click-ID condition became `qualify_lead`. Update your GTM triggers before upgrading.
