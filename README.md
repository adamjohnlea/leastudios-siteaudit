# leaStudios Site Audit

[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777bb3)](#requirements) [![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-21759b)](#requirements) [![License](https://img.shields.io/badge/License-GPL--2.0--or--later-blue)](LICENSE)

Accessibility monitoring dashboard for WordPress, powered by the Google PageSpeed Insights API. Track accessibility scores across a portfolio of URLs over time, get email alerts on regressions, and export PDF or CSV reports.

> **Status:** `1.0.0` released. Plugin is functional end-to-end.

## Features

- **Project + URL management** — group URLs into projects, set per-URL audit frequency (daily / weekly / biweekly / monthly) and strategy (desktop, mobile, or both).
- **Bulk import** — paste a list or upload a CSV. Reports rows imported, skipped (duplicates), and per-line errors.
- **Asynchronous audits** — runs via [Action Scheduler](https://actionscheduler.org/). The "Run audit now" button enqueues a job and returns immediately; the recurring hourly tick scans for due URLs and dispatches per-URL audit actions.
- **Score tracking** — accessibility scores stored per audit, per strategy. Per-URL score history, average score, trend (improving / stable / degrading) with a sparkline, and a categorized issue breakdown by severity (critical / serious / moderate / minor).
- **Dashboards** — overview page with project cards, project detail page with per-URL summary, URL detail page with audit history and desktop/mobile issue tabs.
- **Reporting** — CSV export of one URL's audit history, CSV export of one project's URL summaries, PDF export of a full project report (rendered via [Dompdf](https://github.com/dompdf/dompdf)).
- **Notifications** — per-project email subscriptions. Threshold-breach alerts when a score falls below a configured floor or drops by more than a configured number of points. Per-audit-run report emails with the PDF attached.
- **Capability-gated** — two custom capabilities (`manage_leastudios_siteaudit` for write, `view_leastudios_siteaudit` for read) granted to Administrator (both) and Editor (view only) on activation.

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.4 |
| PHP | 8.1 |
| Browser | Modern evergreen |
| External | A free [Google PageSpeed Insights API key](https://developers.google.com/speed/docs/insights/v5/get-started#APIKey) |

## Installation

```bash
cd wp-content/plugins
git clone https://github.com/adamjohnlea/leastudios-siteaudit.git
cd leastudios-siteaudit
composer install --no-dev
```

Activate the plugin from the WordPress admin (or via WP-CLI: `wp plugin activate leastudios-siteaudit`). On activation the plugin creates seven custom tables prefixed `{$wpdb->prefix}leastudios_siteaudit_*`, registers the capabilities, and seeds default settings.

After activation, a persistent admin notice will prompt you to set your PageSpeed API key. Visit **Site Audit → Settings**, paste the key, and save.

## Configuration

Settings live under **Site Audit → Settings**:

- **PageSpeed Insights API key** — required. Get a free key from the Google Cloud Console.
- **Rate limit (requests/sec)** — clamped to 1–60.
- **Retry count** — clamped to 0–10. Used by the retry strategy when a PageSpeed call returns a transient error.
- **Default audit frequency** — used when adding new URLs.
- **Default audit strategy** — desktop, mobile, or both.

The recurring tick runs hourly via Action Scheduler. No system-cron configuration is required.

## Usage

1. **Add a project** under **Site Audit → Projects** (optional — URLs can also live unassigned).
2. **Add URLs** under **Site Audit → URLs**, individually or via bulk import.
3. **Wait for the hourly tick** to pick up due URLs, or click **Run audit now** on a URL row to dispatch immediately.
4. **Review results** on the **Dashboard** (overview cards), the project detail page, or the URL detail page.
5. **Subscribe** to a project from its detail page to receive alert + report emails.
6. **Export** a CSV (per-URL history or per-project summary) or PDF (per-project report) from the relevant detail page.

## How it works

```
WordPress → admin_post → Url_Controller::handle_run_audit → Action_Enqueuer (Action Scheduler)
                                                                        │
                                                                        ▼
                                              hourly tick → Tick_Dispatcher → run_audit hook
                                                                        │
                                                                        ▼
                                              Audit_Worker → Audit_Service → PageSpeed_Api_Client → Google PSI API
                                                                        │
                                                                        ▼
                                              persist Audit + Issues + Comparison → fire `leastudios_siteaudit_audit_completed`
                                                                        │
                                              ┌─────────────────────────┼─────────────────────────┐
                                              ▼                         ▼                         ▼
                                       Alert_Notifier         Audit_Report_Notifier         (your hooks)
                                       (threshold breach)     (PDF report email)
```

All HTTP traffic goes through `Wp_Http_Client` (a thin `wp_remote_get()` wrapper) — no curl. All datetimes are stored in UTC; the dashboard converts to the WordPress display timezone via `get_date_from_gmt()`.

## Architecture

- **Domain-driven layout** — modules under `src/Modules/{Audit,Dashboard,Notification,Reporting,Scheduler,Url}/{Application,Domain,Infrastructure,Admin}` with explicit separation between domain models, application services, and WordPress-flavored infrastructure.
- **Templating** — pure PHP partials in `templates/`, rendered with output buffering. No Twig.
- **Persistence** — `$wpdb` + `dbDelta()`. Seven tables, schema version stored in `option('leastudios_siteaudit_db_version')` for migrations.
- **Auth** — WordPress users only. No custom session handling. Mutations are gated by `check_admin_referer()` + capability checks.
- **Assets** — pre-built CSS in `assets/css/`. Enqueued only on plugin pages.

## Development

```bash
composer install                         # install all deps including dev tooling
composer phpcs                           # WordPress coding standards
composer phpcbf                          # auto-fix coding standards
composer phpstan                         # static analysis (level 6, scans src/)
composer lint                            # phpcs + phpstan
composer test                            # PHPUnit (WP_UnitTestCase)

# One-time setup before composer test:
bash ../leastudios-dev-tools/bin/install-wp-tests.sh \
    wordpress_test root '' 127.0.0.1 latest

# WP-CLI smoke checks against a local WordPress install:
wp plugin activate leastudios-siteaudit
wp action-scheduler run --hooks=leastudios_siteaudit_tick
```

Tests are split between `tests/Unit/` (pure PHPUnit; value objects, services, retry strategy, comparison logic) and `tests/Integration/` (extends `WP_UnitTestCase`; controllers, repositories, capability enforcement).

## Roadmap

- [x] Phase 1 — Skeleton + activation
- [x] Phase 2 — URL + Project module
- [x] Phase 3 — Audit module + PageSpeed client
- [x] Phase 4 — Dashboard views
- [x] Phase 5 — Asynchronous audits via Action Scheduler
- [x] Phase 6 — CSV + PDF exports
- [x] Phase 7 — Email notifications + subscriptions
- [x] Phase 8 — Polish (activation notice, capability audit, accessibility pass, READMEs)
- [x] `1.0.0` tag
- [ ] WordPress.org submission

## Acknowledgments

Ported from the standalone PHP application [Beacon Audit](https://github.com/adamjohnlea/beaconaudit), which originated the domain model, value objects, comparison logic, trend calculator, and PDF report layout. The WordPress port swaps the curl-based HTTP client, custom auth, SES email, and SQLite repositories for `wp_remote_get()`, WordPress capabilities, `wp_mail()`, and `$wpdb`-backed repositories — but the audit, comparison, and reporting logic is essentially the same.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Author

Built by [Adam Lea](https://leastudios.com) at leaStudios.
