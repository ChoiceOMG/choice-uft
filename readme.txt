=== Choice Universal Form Tracker ===
Contributors: jaffray
Tags: form tracking, google tag manager, conversion tracking, utm, attribution
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.30.0
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
* `phone_click` when a visitor clicks a `tel:` link, and `email_click` for a
  `mailto:` link, except links and numbers you exclude (see the FAQ)
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
same identifier.

The `form_submit` event also carries the raw email address and phone number.
Send `lead_id` instead of those fields wherever raw personal data must not
go; Google Analytics, for one, forbids sending it email addresses or phone
numbers.

The normalisation matches what Google Enhanced Conversions and Meta CAPI
expect, so the value doubles as a match key for offline conversion imports.

= Built to stay out of the way =

* No jQuery dependency; the tracking scripts are plain JavaScript
* No third-party CDN requests; every library the plugin needs ships with it
* Framework scripts load only for form plugins that are actually present
* Tracking failures are contained and never block a form submission

= Who maintains this =

[Choice OMG](https://choice.marketing/) maintains this plugin. We are a
digital marketing and web agency in Edmonton, Alberta, and we built it because
our clients' sites run several form plugins at once and each one needed its own
analytics plumbing. The plugin's overview page is
[Choice Universal Form Tracker for WordPress](https://choice.marketing/tools/choice-uft/).

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
hostname. Off by default. The setting accepts a bare hostname only (for
example `gtm.example.com`); the plugin builds the script address itself as
`https://<host>/gtm.js?id=<container ID>`.

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
own providers and returns whether the number is valid, its line type, and a
quality score. The plugin itself never contacts those providers.

*When:* when a submitted phone number is validated. Results are cached on your
site for 24 hours per number, so a repeated number is not sent again within
that window.

* Service: [Choice OMG](https://choice.marketing/tools/choice-uft/)
* Terms of service: [https://choice.marketing/terms/](https://choice.marketing/terms/)
* Privacy policy: [https://choice.marketing/privacy-policy/](https://choice.marketing/privacy-policy/)

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

On the server, each tracked click is a row in the plugin's click tracking
table: the click ID, the campaign parameters, a keyed hash of the visitor's
IP address, the browser's user agent, the referring page and the landing page
address. A click on a phone or email link by a visitor who did not arrive from
an ad also creates a row, under a temporary ID. Rows older than the Click Data
Retention setting (365 days by default) are deleted once a day. The IP address
itself is never stored, and the hash is keyed with the authentication keys in
wp-config.php, so a copy of the database alone cannot reverse it.

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
Every request must carry the site's secret webhook key, which the same screen
displays and can regenerate. Requests without the right key are rejected with
HTTP 403 and change nothing.

= Can I change what is tracked? =

Yes. Filters cover the computed `lead_id` (`cuft_lead_id`), the phone country
code used to normalise it (`cuft_lead_id_phone_country`), the attribution
payload (`cuft_form_attribution_payload`), and whether the plugin runs at all
(`cuft_enabled`). Since 3.30.0, `cuft_ignore_phone_numbers` adds numbers that
are never tracked, `cuft_record_event` decides whether an event reported by
the browser is recorded on its click, and `cuft_click_retention_days` sets how
long click rows are kept.

= Can I keep one phone number or link out of tracking? =

Yes. List the number under Settings > Ignored Phone Numbers, and clicks on any
`tel:` link to it produce no `phone_click` in the dataLayer and nothing on the
click record. Numbers match on their last 10 digits, so every formatting of the
same number is covered. To exclude a single link of any kind, add the attribute
`data-cuft-ignore` or the CSS class `cuft-ignore` to the link or to an element
around it; in Elementor the class goes under Advanced > CSS Classes.

== Screenshots ==

1. Settings: Google Tag Manager container, Google tag gateway, server-side GTM and lead events.
2. Framework detection for Avada, Elementor Pro, Contact Form 7, Ninja Forms and Gravity Forms, with the active tracking features.
3. Click Tracking: each advertising click ID with its campaign, lead events, qualification status and score.
4. Testing Dashboard: generate sample data and simulate events to validate tracking.

== Changelog ==

= 3.30.0 =
* Added Ignored Phone Numbers (Settings). Clicks on `tel:` links to a listed number are not tracked: no `phone_click` in the dataLayer and no request to the server. Numbers match on their last 10 digits, ignoring formatting and extensions. The `cuft_ignore_phone_numbers` filter adds numbers from code.
* Any link inside an element with `data-cuft-ignore` or the class `cuft-ignore` is not tracked.
* Added the `cuft_record_event` filter, which can stop an event reported by the browser from being recorded on its click.
* Added Click Data Retention (Settings, default 365 days, 0 keeps rows forever). A daily job deletes older click rows in batches of 1,000, bounded in time, and continues in a follow-up run when there is more to delete. Before 3.30.0 click rows were never deleted.
* IP hashes are now keyed (HMAC-SHA256 with a key derived from `AUTH_KEY` and `AUTH_SALT` in wp-config.php) instead of a plain SHA-256 of the address, which could be reversed by hashing every IPv4 address. Hashes stored before the upgrade are cleared by the daily job. Define `CUFT_IP_HASH_KEY` to use your own key.
* Pages no longer contain the visitor's click ID or IP hash. A full-page cache could have served one ad visitor's click ID to everyone after them; the scripts now read the visitor's own cookie. Form events no longer carry `ip_hash` or `click_platform`.
* Fixed: reloading an ad landing page reset the click's qualification and score from your CRM and replaced its campaign. A click row is now written once and never rewritten from the front end.
* Fixed: the click row written on an ad landing had no campaign, because the browser saves the UTM parameters only after the page loads. They are now read from the landing URL, and values longer than their column are shortened instead of losing the row.
* Fixed: a failed Contact Form 7 email stored the visitor's email address and phone number in the plugin's log, even with debug logging off. The log no longer keeps contact details or form answers, and it is no longer loaded on every request.
* Fixed: the click retention job did not run where the `cuft_enabled` filter switched tracking off.
* Fixed: the six-hour server-side GTM health check never ran, so a failed tagging server never fell back to Google.
* Contact Form 7 failure, validation and spam warnings appear in the browser console only when Console Logging is on.
* The Support link on the Plugins screen goes to the WordPress.org support forum.

= 3.29.4 =
* First release published in the WordPress.org directory. No code change from 3.29.3, which was released on GitHub only.
* Readme: new screenshots taken on 3.29.3, directory tags that match what the plugin does, and links to the maintainer and the plugin's overview page.

= 3.29.3 =
* First release that carries both 3.29 lines: the directory review fixes listed under 3.29.0 to 3.29.2, and the Google tag gateway loader mode below.
* Added a Google tag gateway loader mode (Settings > Server-Side GTM). When switched on with a same-origin script path, the container loads from that path instead of googletagmanager.com. It is off by default and changes nothing on a site that leaves it off.
* An hourly health probe checks the path and the container: the container response must hold both the container id and `google_tag_manager`, and the probe follows no redirects. Two passes in a row select the gateway; two failures in a row return to the loader the site used before. The loader also falls back to Google in the browser if the gateway script errors or does not define the container.
* Every state change purges the page caches of WP Super Cache, WP Rocket, W3 Total Cache, SiteGround Optimizer and LiteSpeed Cache, and the object cache.
* The noscript iframe stays on googletagmanager.com while the gateway mode is enabled.
* The gateway loader builds its script URLs like every other mode: in PHP, from the validated path or host and the container ID, encoded for script context.

= 3.29.2 =
* The webhook always requires the site's secret key. The "Require webhook key" setting is removed; a key is generated for any site that lacks one, and a request without the right key is rejected before any other input is read.
* Scripts no longer fall back to a hard-coded `/wp-admin/admin-ajax.php`. The AJAX address comes only from `admin_url( 'admin-ajax.php' )`, passed in by PHP.
* Readme: the phone validation section lists only the service the plugin contacts.

= 3.29.1 =
* Lead events are no longer lost on the click record. The browser sends form_submit, generate_lead and qualify_lead as three requests within milliseconds; each one read the saved events, added its own and wrote the list back, so they overwrote each other and the record kept one or two of the three. Writes now take a per-click database lock and only land if the saved list is unchanged since it was read, retrying otherwise. Works on InnoDB and MyISAM tables and on hosts without user locks.
* Contact Form 7 submissions now record their events on the click record. The script settings the recorder needs were attached to jQuery, which WordPress does not load on a page where nothing depends on it, and Contact Form 7 does not. The settings now load on every page from the plugin's own script handle.

= 3.29.0 =
* Server-side GTM: the setting now takes a bare hostname (for example `gtm.example.com`) instead of a URL. The plugin builds `https://<host>/gtm.js?id=<container ID>` itself; hostnames with a scheme other than https, a path, a query, a port, credentials, an IP address, localhost or an internal suffix are rejected. A URL saved by an earlier version is converted to its host automatically. A URL that carried a path keeps its host but falls back to Google's endpoint until you test the connection again.
* Server-side GTM health checks always verify TLS certificates; the `.localnet` exception is removed. A new or changed host must pass its own health checks before pages load from it.
* Every value printed inside an inline script is now encoded so it cannot close the script element. Removed unused server-side dataLayer code in the Contact Form 7, Elementor, Ninja Forms and Gravity Forms integrations; it never reached the page, and the browser scripts fire the same events.
* The visitor event endpoint accepts only the events a browser produces (`phone_click`, `email_click`, `form_submit`, `generate_lead`, `qualify_lead`). CRM statuses reach the click table only through the webhook. Click IDs and UTM values longer than 255 characters are ignored or cut.
* Removed the "is active with N form framework(s) detected" admin notice. The remaining notices appear only on the plugin's own screens and can be dismissed.
* A click-write error shown on the Click Tracking screen now clears after the next successful write or table repair, and can be dismissed.

= 3.28.4 =
* Click tracking table now installs on hosts whose default storage engine is MyISAM (or InnoDB with the old 767/1000-byte index limit). The unique index on click_id covered all 255 utf8mb4 characters (1,020 bytes), which MySQL rejects there with "Specified key was too long; max key length is 1000 bytes", so the table was never created and no clicks were recorded. The index now covers the first 191 characters; click IDs are far shorter, so uniqueness is unchanged, and existing tables are not altered.

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

= 3.30.0 =
Click rows older than 365 days are now deleted daily; set Click Data Retention to 0 first to keep them. Stored IP hashes are cleared and new ones are keyed. Form events no longer carry `ip_hash` or `click_platform`.

= 3.29.4 =
Readme and listing changes only; no code change from 3.29.3. Upgrading from 3.28.x: the webhook now always requires the site's key, and a server-side GTM URL becomes a bare hostname.

= 3.29.3 =
Upgrading from 3.28.x: the webhook now always requires the site's key, and a server-side GTM URL becomes a bare hostname. Adds an optional Google tag gateway mode, off by default.

= 3.29.2 =
The webhook now always requires the site's key. Any integration calling it without `key=` must use the URL shown on the Click Tracking screen.

= 3.29.1 =
Fixes lead events missing from click records (concurrent writes, and every Contact Form 7 submission). No settings change.

= 3.29.0 =
The server-side GTM setting now takes a hostname only; a saved URL is converted automatically. A saved URL that included a path falls back to Google's endpoint until you test the connection again.

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
