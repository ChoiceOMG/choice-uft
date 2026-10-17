# Public readiness audit of 3.30.0 (2026-10-17)

Five read-only audits of `feat/privacy-controls` at eec92e9, judged as the directory build
(`./build.sh --wporg`) a stranger installs: privacy and consent, security and front-end
cost, a fresh install on WordPress 7.1.3 / PHP 8.5.11 with Contact Form 7, test and CI
health, and a scan of competing directory plugins. Items marked VERIFIED were re-read in
the code after the audit; the rest are the auditors' findings with their evidence.

Context at the time: 3.29.4 live on WordPress.org since 2026-10-17, zero installs, zero
support threads.

Status: section A was fixed in 3.30.0 on 2026-10-17 (CHANGELOG.md, 3.30.0). Sections B
to D are open; B is planned as 3.31.0.

## A. Fix before 3.30.0 ships

| # | Finding | Evidence | Effort |
|---|---|---|---|
| A1 | The keyed IP hash is keyed from the database on standard installs. `wp_salt()` with a custom scheme uses `SECRET_KEY`, which `wp-config-sample.php` does not define, then falls back to the `secret_key` site option. A database copy alone allows a full IPv4 sweep. The code comment and readme.txt:234-238 claim the opposite. Fix: key from wp-config constants (`wp_salt( 'auth' )` or AUTH_KEY), keep `CUFT_IP_HASH_KEY` as the override. Must land before release, because the migration re-keys once. VERIFIED | class-cuft-click-tracker.php:965-980 | S |
| A2 | The retention job has no handler on any request where `cuft_enabled` is false, so WP-Cron consumes the event and nothing is deleted. A consent-reading filter is false on every wp-cron.php request. Fix: wire `CUFT_Privacy` before the `cuft_enabled` return. VERIFIED | choice-universal-form-tracker.php:201 vs :271 | S |
| A3 | Contact Form 7 mail failures log the visitor's email and phone at error level, and error entries are stored in the `cuft_debug_logs` option even with debug logging off (up to 1,000 entries, about 510 KB, autoloaded on WP 6.2 to 6.5). Retention never touches it. Fix: log presence booleans only, autoload off, purge on the retention run. VERIFIED. Elementor with debug on logs every field value (class-cuft-elementor-forms.php:105,157) | class-cuft-cf7-forms.php:80-83, class-cuft-logger.php:46 | S |
| A4 | An existing click row is fully rewritten on every revisit of its landing URL: `qualified` and `score` reset to 0 and UTMs replaced from the visitor's cookie. A reload or back-navigation on a gclid URL undoes a CRM webhook qualification; an anonymous request can do the same on purpose (reproduced: (1, 8) to (0, 0), `utm_source=attacker`). Fix: insert only; never touch qualified, score or UTMs of an existing row from the front end. VERIFIED | class-cuft-click-tracker.php:426-445, class-cuft-click-integration.php:84-100 | S |
| A5 | The first click row of an ad visit has empty UTM columns: the landing request reads UTMs from a cookie that the browser script sets only after that page loads. Campaign column, CSV and Ads exports are blank for those rows. Fix: read UTMs from `$_GET` on the landing request. VERIFIED | class-cuft-click-integration.php:84 | S |
| A6 | The visitor's click ID and IP hash are printed into the page HTML (`cuftClickData`), and the scripts attach that click ID to any submission lacking its own (cuft-dataLayer-utils.js:440, hidden fields in cuft-click-integration.js:59). A full-page cache that stores a page rendered for an ad visitor would attribute later organic leads to that click and fire `qualify_lead`. Not observed: 48 anonymous page loads across 16 client sites on 2026-10-17 all served an empty click ID. Fix: the cookie is script-readable, so read it in JS and print nothing per visitor. VERIFIED mechanism, INFERRED exposure | class-cuft-click-integration.php:160-184 | S-M |
| A7 | The six-hour sGTM health check never runs. The event is scheduled (choice-universal-form-tracker.php:399), but its callback and the `six_hours` schedule are registered only in `CUFT_Admin`, which is constructed only when `is_admin()`; WP-Cron requests are not admin. If a tagging server dies, the browser fallback never engages. Affects drlampee.com today. VERIFIED | class-cuft-admin.php:25-26, choice-universal-form-tracker.php:235 | S |
| A8 | Readme corrections: readme.txt:60-64 says `lead_id` keeps raw personal data out of analytics while the same payload carries raw `user_email` and `user_phone`; the click-row FAQ omits user agent, referrer and landing URL, and that phone and email link clicks from organic visitors also create rows (`temp_` IDs). | readme.txt | S |
| A9 | PHP 8.5 deprecation on every ad-click landing: `add_query_arg( null, null )`. | class-cuft-click-integration.php:97 | S |
| A10 | Plugin row "Support" link points to GitHub issues; the directory build should point to the WordPress.org forum. | choice-universal-form-tracker.php:552 | S |
| A11 | A visitor-facing `console.warn` ("form_submit/generate_lead will NOT fire") prints when CF7 mail fails, even with console logging off. | assets/forms/cuft-cf7-forms.js | S |

## B. Public readiness release (3.31.0)

1. **Consent.** Nothing calls `wp_has_consent`, registers with the WP Consent API (200,000+ installs) or reads Consent Mode. Three cookies (`cuft_click_id` 30 d, `cuft_utm_data` 30 d, `cuft_first_touch` 365 d), sessionStorage and a server click row are written on the first page view. The only gate is `cuft_enabled`, evaluated once per server request, so cached pages still ship storage-writing scripts. Fix: WP Consent API in PHP and JS; hold storage, click rows and pushes until consent where a consent plugin is present; unchanged behaviour where none is. M.
2. **Raw email and phone in the dataLayer** by default with no switch (cuft-dataLayer-utils.js:385-394, :467, :492). Google's GA4 policy forbids PII in Analytics. Add raw / hashed / omitted, hashed using Google's `sha256_email_address` and `sha256_phone_number` names; existing installs keep raw so client containers do not break. M.
3. **WordPress privacy tools.** No `wp_add_privacy_policy_content`, no exporter or eraser. Add suggested policy text (S) and an exporter/eraser over the IP hash and logs (M).
4. **Lead value defaults to 100 CAD** and is attached to `qualify_lead` even with Generate Lead Events off; the value fields are hidden until that box is ticked. A stranger wiring `qualify_lead` to Google Ads books phantom Canadian revenue. New installs: value empty or 0, currency from the site locale, fields always visible. S.
5. **Agency-only settings in front of strangers.** Phone Validation ("via Choice validator service", Register Site, a secret only Choice OMG issues; when on, Elementor submissions block on a 10-second call) and Click Collector Host (reads a `_cuft_click` cookie nothing in the plugin sets). Keep them in the one plugin, hidden unless a constant such as `CUFT_AGENCY_FEATURES` is defined. S-M.
6. **First-run copy.** "GTM container ID is missing or invalid" appears twice although an empty ID is valid; the gateway "State: fallback" line, Debug Logging destination and GA4 Measurement ID need one sentence each; Link Tracking omits `email_click`; single vestigial "Settings" tab; three menu entries. S.
7. **Testing Dashboard** loads the live container in wp-admin and pushes simulated `generate_lead` events that can reach real GA4. Mark them as debug or keep them out of the live container. S.
8. **i18n.** About 10 translatable calls in class-cuft-admin.php; most admin labels are hard-coded English, so translate.wordpress.org has little to work with. M.
9. **Anonymous write hardening.** Click rows for any `?click_id=` URL, `cuft_record_event` creating rows for any ID, `temp_` rows, 7,000-byte user agents accepted, no rate limit in either build (`CUFT_Rate_Limiter` is not called by shipped code). Accept only real ad parameters and known event types, cap lengths, add a transient limit per IP hash. Check client reporting before dropping `temp_` rows. M.
10. **Stale nonces on cached pages** make anonymous event recording 403 silently after 12 to 24 hours (LiteSpeed caches 7 days by default). M.
11. **Front-end weight.** Four base scripts on every page (43.8 KB raw, 12.3 KB gzip), two render-blocking in the head, no `defer`; `cuft-feature-flags.js` (12.3 KB) serves only four scripts that are never enqueued (46 KB dead in the package); an uncached admin-ajax GET on every page view for 30 days after an ad click; 5 extra option queries per view. S-M.
12. **Gravity Forms** keeps raw email and phone in localStorage indefinitely when the confirmation page never loads (cuft-gravity-forms.js:568-580). S.
13. **Dead code:** `class-cuft-ai-files.php` serves `/llms.txt` and `/ai.txt` with no UI; debug mode writes a permanent `cuft_user_id` to localStorage. S.
14. **Multisite:** uninstall drops only the main site's data; activation ignores network activation. M.
15. **Log noise:** `CUFT_Logger::debug_log` writes on any WP_DEBUG site regardless of the plugin's own setting (21 lines per test run). S.

## C. Reach (3.32.0 and later)

- **Form plugins with the largest install bases are missing:** WPForms Lite 5,000,000+, Fluent Forms 800,000+, Forminator 600,000+, SureForms 500,000+, Formidable 300,000+ (WordPress.org API, 2026-10-17). GTM4WP and GTM Kit cover CF7 only, so breadth is CUFT's lane.
- **Attribution into the lead itself:** server-side attribution exists for Elementor only (`CUFT_Form_Attribution` has one caller). CF7, Gravity, Ninja and Avada leads carry UTMs in the dataLayer but not in the email or stored entry. Easy UTM tracking with CF7 has 2,000+ installs doing just that.
- **GTM container templates:** `gtm-web-client/` and `gtm-server/` exports date from 3.14.0, predate `qualify_lead`, `phone_click`, `email_click` and `lead_id`, and carry a real Meta test dataset ID. Refresh, scrub, publish on the tool page with an import guide (PixelYourSite does this).
- **Playground blueprint** (`assets/blueprints/blueprint.json` in SVN) for a Live Preview button: GTM Kit has one; GTM4WP, HandL, PixelYourSite, MonsterInsights and Site Kit do not.
- More screenshots and FAQ (PixelYourSite 23 screenshots, HandL 17; CUFT 4).

## D. Test and CI trust (parallel)

- The Tests workflow has failed every run since 2026-09-23 (`tests/contracts` emptied by e7844e6, still listed in phpunit.xml), including 3.29.3 and 3.29.4; `release.yml` does not depend on it. S.
- The 18 non-passing unit tests all cover updater code the directory build strips. The 16 errors come from `TRUNCATE` at tests/unit/test-data-models.php:59 committing implicitly; they mask 6 failures: stale `3.17.0` asserts and two real update-log bugs (FIFO cleanup keeps 6 rows not 5, class-cuft-update-log.php:488-498; same-second ordering lacks an id tie-break, :246). S.
- 37 placeholder tests (11 skipped stubs, 26 incomplete); `tests/integration` dies at test 60; `tests/ajax` 57 of 57 fail. Delete or quarantine. M.
- Nothing in CI builds either channel or runs Plugin Check; the 9 node tests are not run in CI; the five form handlers (3,661 lines of JS) have no tests; no end-to-end submit test. Add a both-channel build job, Plugin Check on the `--wporg` package, gate `release.yml` on tests, and one wp-env + CF7 + Playwright submit test on the directory build. M-L.
