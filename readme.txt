=== MainWP for Google Security for WordPress ===
Contributors: One Dog Solutions
Tags: mainwp, recaptcha, security, extension, addon
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Configure the Google Security for WordPress plugin on each connected child site directly from the MainWP Dashboard.

== Description ==

This is a MainWP extension, installed on your **MainWP Dashboard** site only. It lets you read and update the [Google Security for WordPress](https://github.com/onedogsolutions/google-security-for-wordpress) (GSWP) plugin's settings on any connected child site without logging in to that site individually.

Because reCAPTCHA site/secret keys are issued **per domain**, this extension does not offer a single global settings screen. Instead, a "Google Security" tab appears on each individual child site's own MainWP screen (**Manage Sites -> *site name* -> Google Security**), and every child site keeps its own independent set of keys, thresholds, and toggles.

= What you can configure =
* **API Credentials** — site/secret key, key type (Classic or Enterprise), and GCP project ID / API key for Enterprise.
* **Form Protection** — enable reCAPTCHA scoring on the WordPress login, registration, and lost-password screens, plus (when WooCommerce is active) the WooCommerce login, registration, and checkout forms, with independent score thresholds for each.
* **Enterprise Defense** — Transaction Defense (checkout fraud-risk scoring and blocking) and Account Defender (login/account-takeover signals), both Enterprise-only.
* **Two-Factor Auth** — master switch, role-based enforcement (built from the child site's own role list), enrolment grace period, "remember this browser," and application-password hardening with its exemption list.
* **Alerts & Compatibility** — admin email alerts on suspicious logins or blocked checkouts, reCAPTCHA conflict handling, and verbose logging.

= Requirements on the child site =

The child site needs Google Security for WordPress **2.9.0 or later**, which ships the small bridge this extension talks to over MainWP's existing signed connection (no new REST endpoint or authentication surface — just MainWP Child's own `extra_execution` callable). A site running an older GSWP version, or with GSWP inactive, shows a clear "not available" message instead of a blank or broken form; it is safe to install this extension ahead of upgrading child sites.

= Scope =

Configuration only, in this release. Bulk/group settings apply across multiple sites, 2FA user administration, and alert-log surfacing on the dashboard are not included — see the project's implementation plan for the roadmap.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mainwp-for-google-security-for-wordpress` directory of your **MainWP Dashboard** site (not the child sites), or install through the WordPress plugins screen directly.
2. Activate the plugin. It requires the MainWP Dashboard plugin to already be active.
3. On each child site, install or update **Google Security for WordPress** to 2.9.0 or later.
4. From MainWP, go to **Sites -> *site name* -> Google Security** to configure that site.

== Frequently Asked Questions ==

= Why isn't there one screen to configure every site at once? =
reCAPTCHA site/secret keys are issued per domain — a key valid for one site is invalid for another. A shared global form would either be misleading or would need to omit the credentials tab, so configuration is scoped to one site at a time instead.

= I see "Google Security for WordPress is not active on this child site, or is older than version 2.9.0." What do I do? =
Install or update Google Security for WordPress on that specific child site, then reload the tab. This extension never guesses at settings it can't confirm — it shows this message instead of a blank or stale form.

= Does this affect my MainWP connection to the site? =
No. This extension reads and writes settings through MainWP's own signed dashboard-to-child channel — the same mechanism MainWP already uses to manage the site. It does not use application passwords, so it is unaffected by GSWP's own "block application passwords for enforced roles" setting.

== Changelog ==

= 1.0.0 =
* Initial release: per-site "Google Security" configuration tab (API Credentials, Form Protection, Enterprise Defense, Two-Factor Auth, Alerts & Compatibility) and an Extensions-page site index with a connectivity check.
