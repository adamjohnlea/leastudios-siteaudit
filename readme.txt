=== leaStudios Site Audit ===
Contributors: leastudios
Tags: accessibility, audit, pagespeed, a11y, monitoring
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.2
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accessibility monitoring dashboard for WordPress, powered by the Google PageSpeed Insights API.

== Description ==

leaStudios Site Audit tracks accessibility scores across a portfolio of URLs over time. It calls the Google PageSpeed Insights API on a recurring schedule, stores per-strategy scores and issues in your database, and surfaces dashboards, trend graphs, alerts, and exportable reports inside the WordPress admin.

= Features =

* **Project + URL management** — group URLs into projects, set per-URL audit frequency (daily, weekly, biweekly, monthly) and strategy (desktop, mobile, or both).
* **Bulk import** — paste a list or upload a CSV.
* **Asynchronous audits** — runs through the Action Scheduler library that powers WooCommerce. The "Run audit now" button enqueues a job and returns immediately; an hourly recurring tick scans for due URLs.
* **Score history + trends** — per-URL audit history, average score, trend direction, and a categorized issue breakdown by severity.
* **Dashboards** — overview page, project detail, and URL detail with desktop/mobile issue tabs.
* **Reporting** — CSV export of audit history and project URL summaries; PDF export of full project reports rendered with Dompdf.
* **Notifications** — per-project email subscriptions with threshold-breach alerts and post-run report emails.
* **Capability-gated** — two custom capabilities (`manage_leastudios_siteaudit`, `view_leastudios_siteaudit`) granted to Administrator (both) and Editor (view only) on activation.

= How it works =

All audits run through Action Scheduler. The "Run audit now" button enqueues an asynchronous action; the hourly recurring tick discovers URLs whose `last_audited_at` plus their configured frequency has elapsed. Scores, issues, and audit-to-audit comparisons are persisted to seven custom tables. After each successful audit a `leastudios_siteaudit_audit_completed` action fires; subscribed listeners send threshold-breach alerts and PDF reports.

All HTTP traffic uses `wp_remote_get()`. All datetimes are stored in UTC and converted to your WordPress display timezone in the admin UI.

= Requires a Google PageSpeed Insights API key =

You will need a free PageSpeed Insights API key from the Google Cloud Console. The plugin shows a persistent admin notice on every page until you provide one in **Site Audit → Settings**.

== Installation ==

1. Upload the `leastudios-siteaudit` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Run `composer install --no-dev` in the plugin folder to install the bundled Dompdf and Action Scheduler dependencies.
3. Activate the plugin through the **Plugins** screen in WordPress.
4. Visit **Site Audit → Settings** and paste your Google PageSpeed Insights API key.
5. Add URLs under **Site Audit → URLs** (individually, or in bulk).
6. Wait for the hourly tick, or click **Run audit now** to dispatch immediately.

== Frequently Asked Questions ==

= Where do I get a PageSpeed Insights API key? =

From the Google Cloud Console at https://developers.google.com/speed/docs/insights/v5/get-started#APIKey. The free tier is sufficient for most installs.

= How often do audits run? =

Each URL has its own configurable frequency (daily, weekly, biweekly, monthly). A single hourly tick discovers URLs whose `last_audited_at + frequency` has elapsed and dispatches them. You can also click **Run audit now** to bypass the schedule.

= Does the plugin work without a system cron? =

Yes. Action Scheduler self-heals on every page load, so as long as your site receives traffic, audits will run. Low-traffic installs may want to set up a real cron call to `wp-cron.php` for more predictable timing.

= Where is data stored? =

In seven custom tables prefixed `{prefix}leastudios_siteaudit_`: projects, urls, audits, issues, audit_comparisons, notifications, and email_subscriptions. The schema version is tracked in the `leastudios_siteaudit_db_version` option.

= How are emails sent? =

Through `wp_mail()`. Use whichever SMTP / transactional-mail plugin you already trust for email delivery. The plugin does not bundle its own SMTP transport.

= Who can see the dashboard? =

Anyone with the `view_leastudios_siteaudit` capability. By default that is Administrator and Editor. The `manage_leastudios_siteaudit` capability gates writes (create/edit/delete projects and URLs, change settings, trigger manual audits) and is granted to Administrator only.

= How do I uninstall? =

Deleting the plugin via the Plugins screen runs the uninstaller, which drops all seven custom tables and removes plugin options.

== Changelog ==

= 1.0.2 =
* Renamed the plugin display name from "LEA Studios Site Audit" to "leaStudios Site Audit" to match the rest of the leaStudios plugin suite. No functional changes.

= 1.0.1 =
* Fixed a race condition that could schedule the recurring audit tick twice on a freshly-activated site, which left some installs with no scheduled audits at all. Scheduling is now serialized with a database-level mutex.

= 1.0.0 =
* Initial public release. Complete port of the Beacon Audit standalone application: project + URL management, asynchronous audits via Action Scheduler, dashboards, CSV and PDF reports, threshold-breach alert emails, and per-project email subscriptions.

== Upgrade Notice ==

= 1.0.2 =
Cosmetic rename — plugin display name now matches the rest of the leaStudios suite. No data or behavior changes.

= 1.0.1 =
Fixes a scheduling race that could prevent automatic audits from running. Recommended for all installs.

= 1.0.0 =
First public release.
