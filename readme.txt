=== Choice Universal Form Tracker ===
Contributors: choiceomg
Tags: forms, tracking, analytics, gtm, google tag manager, form tracking, utm tracking, elementor, contact form 7, gravity forms, ninja forms, avada
Requires at least: 5.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 3.3.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Universal form tracking for WordPress - supports multiple form frameworks and tracks submissions via Google Tag Manager's dataLayer.

== Description ==

Choice Universal Form Tracker is a comprehensive solution for tracking form submissions across multiple WordPress form frameworks. Whether you're using Elementor Pro, Contact Form 7, Gravity Forms, Ninja Forms, or Avada/Fusion forms, this plugin automatically detects and tracks all form submissions with detailed analytics data.

**Key Features:**

* **Universal Framework Support** - Automatically detects and supports:
  * Avada/Fusion Forms
  * Elementor Pro Forms
  * Contact Form 7
  * Ninja Forms
  * Gravity Forms

* **Advanced Tracking Capabilities**
  * Form submission tracking with user email and phone data
  * Phone number click tracking (tel: links)
  * UTM campaign parameter tracking for marketing attribution
  * Automatic framework detection and status reporting

* **Google Tag Manager Integration**
  * Optional GTM container injection
  * Pushes structured data to dataLayer for advanced analytics
  * Compatible with GA4 and other analytics platforms

* **Marketing Attribution**
  * Captures and stores UTM parameters for up to 30 days
  * Associates campaign data with form submissions
  * Supports all standard UTM parameters (source, medium, campaign, term, content)

* **Developer-Friendly**
  * Vanilla JavaScript (no jQuery dependency)
  * Comprehensive debug logging system
  * Clean, modular codebase
  * WordPress coding standards compliant

* **Beautiful Admin Interface**
  * Real-time framework detection status
  * UTM campaign monitoring
  * Debug log viewer
  * Easy GTM configuration
  * GitHub auto-update management

* **GitHub Auto-Updates**
  * Automatic updates from GitHub repository
  * No authentication required for public repos
  * Seamless WordPress integration
  * Fallback to WordPress.org if needed

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/choice-universal-form-tracker` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Navigate to Settings > Universal Form Tracker to configure the plugin.
4. Enter your Google Tag Manager container ID (optional but recommended).
5. Enable debug logging if you need to troubleshoot form tracking.
6. Enable GitHub auto-updates for the latest features (recommended).

== GitHub Auto-Updates ==

The plugin now includes automatic updates from the GitHub repository:

* **Easy Setup**: Simply check "Enable automatic updates from GitHub repository" in the plugin settings
* **No Authentication**: Public repository access requires no tokens or login
* **WordPress Native**: Updates appear in the standard WordPress admin update notifications
* **Automatic**: Checks for updates twice daily and notifies you when new versions are available
* **Fallback**: If GitHub updates are disabled, falls back to WordPress.org update system

This ensures you always have the latest features, bug fixes, and improvements directly from the development repository.

== Frequently Asked Questions ==

= Which form plugins are supported? =

The plugin automatically detects and supports:
* Avada/Fusion Forms (built into Avada theme)
* Elementor Pro Forms
* Contact Form 7
* Ninja Forms
* Gravity Forms

= Do I need Google Tag Manager? =

No, GTM is optional. The plugin can inject the GTM container code for you, or you can use your own GTM implementation. Form tracking data is pushed to the dataLayer regardless.

= What data is tracked? =

For form submissions:
* Form type and identification
* User email and phone number (when provided)
* UTM campaign parameters (for marketing attribution)
* Submission timestamp

For phone clicks:
* Phone number clicked
* Link href attribute
* Click timestamp

= How long are UTM parameters stored? =

UTM parameters are stored for 30 days from the user's first visit with UTM parameters. This ensures proper attribution even if the user submits a form days later.

= Is the plugin GDPR compliant? =

The plugin only tracks form submission events and does not store personal data permanently. UTM parameters are stored in user sessions/cookies for attribution purposes. Please review your local privacy requirements.

= How do I get updates for the plugin? =

The plugin now supports automatic updates from GitHub:
* Enable GitHub updates in Settings > Universal Form Tracker
* Updates will appear in WordPress admin notifications
* No additional accounts or tokens required
* Updates are checked twice daily automatically

== Screenshots ==

1. Admin dashboard showing framework detection status
2. UTM campaign tracking interface
3. Debug logging system
4. Plugin settings configuration

== Changelog ==

= 3.3.0 =
* Major Avada form tracking bug fixes and improvements
* Fixed form submission detection reliability issues
* Enhanced AJAX form support with improved success state detection
* Resolved duplicate form submission tracking
* Fixed email field validation preventing false positives
* Improved error handling and debug logging for Avada forms
* Enhanced compatibility with latest Avada/Fusion theme versions
* Fixed timing issues with form initialization
* Improved form selector specificity to prevent conflicts

= 3.1.0 =
* Added UTM campaign tracking for marketing attribution
* Enhanced admin interface with campaign status display
* Improved session and cookie-based UTM storage
* Added comprehensive UTM parameter support
* Enhanced debug logging for UTM tracking

= 3.0.0 =
* Complete rewrite with modular architecture
* Universal form framework support
* Vanilla JavaScript implementation (removed jQuery dependency)
* Advanced framework detection system
* Comprehensive debug logging
* Beautiful admin interface

= 2.0.0 =
* Refactored into object-oriented structure
* Added separate classes for each component
* Improved code organization and maintainability

= 1.2.0 =
* Added Google Tag Manager integration
* Introduced admin settings page
* Enhanced form tracking capabilities

= 1.0.0 =
* Initial release
* Basic form and link tracking functionality

== Upgrade Notice ==

= 3.1.0 =
Major update with UTM campaign tracking. Recommended for all users who want to track marketing campaign effectiveness.

= 3.0.0 =
Complete rewrite with improved performance and universal form support. Backup recommended before upgrading.

== Additional Information ==

**Repository:** View the source code, releases, and technical documentation on GitHub: [https://github.com/ChoiceOMG/choice-uft](https://github.com/ChoiceOMG/choice-uft)

**Releases:** Download the latest version and view release notes: [https://github.com/ChoiceOMG/choice-uft/releases](https://github.com/ChoiceOMG/choice-uft/releases)

**Auto-Updates:** The plugin now automatically updates from GitHub releases. Enable this feature in the plugin settings for the latest features and improvements.

**Technical Documentation:** For detailed technical documentation, API references, and code examples, see the comprehensive README.md and GITHUB-UPDATER.md in the repository.

**Support:** For technical support and feature requests, please visit [Choice OMG](https://choice.marketing)

**Contributing:** This plugin is developed with WordPress best practices and follows strict coding standards. Contributions and feedback are welcome.

**Privacy:** This plugin tracks form submissions for analytics purposes. It does not store personal information permanently and respects user privacy.