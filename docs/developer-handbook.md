# leaStudios Site Audit — Developer Handbook

leaStudios Site Audit is an accessibility-monitoring dashboard powered by the
Google PageSpeed Insights API. It tracks scores for a portfolio of URLs over
time, fires alerts on threshold breaches, and emails PDF reports on each audit
run. Extension authors have two action hooks — one that fires when an audit run
finishes and one that fires when a notification email fails — plus access to the
seven custom tables via `$wpdb`.

---

## Table of Contents

1. [Overview](#1-overview)
2. [Architecture](#2-architecture)
3. [Development Setup](#3-development-setup)
4. [Data Model](#4-data-model)
5. [Hooks Reference](#5-hooks-reference)
6. [Hook Execution Order](#6-hook-execution-order)
7. [Testing](#7-testing)
8. [Release Process](#8-release-process)
9. [Where to Read More](#9-where-to-read-more)

---

## 1. Overview

leaStudios Site Audit gives site owners a self-hosted accessibility monitoring
dashboard without running a separate application. Add URLs (individually or via
bulk CSV import), assign them to projects, set an audit frequency (daily /
weekly / biweekly / monthly) and strategy (desktop, mobile, or both), then let
the hourly Action Scheduler tick fetch live scores from Google PageSpeed
Insights.

Each audit run persists the raw API response, extracts and categorizes
accessibility issues by severity, computes a score delta against the previous
run, and derives a trend (improving / stable / degrading). The dashboard shows
project cards, per-URL sparklines, and a categorized issue breakdown.
Threshold-breach alerts and per-run PDF report emails are delivered via
`wp_mail()`.

Two custom capabilities gate access: `manage_leastudios_siteaudit` (write) and
`view_leastudios_siteaudit` (read). Administrators receive both on activation;
Editors receive read-only access.

Extension authors can react to every completed audit run
(`leastudios_siteaudit_audit_completed`) and capture notification failures in a
privacy-safe way (`leastudios_siteaudit_mail_failed`).

---

## 2. Architecture

```
leastudios-siteaudit.php
    └── Plugin::init()  (on plugins_loaded)
            |
            ├── Database\Migration::maybe_migrate()   seven-table schema
            |
            ├── Modules\Scheduler
            |       └── hourly tick → Tick_Dispatcher
            |               └── per-URL run_audit AS jobs
            |
            ├── Modules\Audit
            |       └── Audit_Worker → Audit_Service
            |               ├── Audit_Pipeline
            |               │       ├── Wp_Http_Client → Google PSI API
            |               │       ├── RetryStrategy
            |               │       └── persist Audit + Issues + Comparison
            |               └── [action] leastudios_siteaudit_audit_completed
            |
            ├── Modules\Notification
            |       ├── Alert_Notifier      (threshold breach)
            |       ├── Audit_Report_Notifier (PDF email)
            |       └── Wp_Mail_Service
            |               └── [action] leastudios_siteaudit_mail_failed
            |
            ├── Modules\Reporting
            |       └── CSV + Dompdf PDF exports
            |
            ├── Modules\Url / Dashboard     admin pages, CRUD, bulk import
            |
            └── Admin\Settings_Page         PageSpeed API key, defaults
```

All HTTP calls go through `Wp_Http_Client` (a thin `wp_remote_get()` wrapper).
Datetimes are stored in UTC; admin views convert via `get_date_from_gmt()`.
Mutations are gated by `check_admin_referer()` and capability checks; there are
no REST routes.

---

## 3. Development Setup

```bash
cd wp-content/plugins/leastudios-siteaudit
composer install
composer lint              # phpcs + phpstan (level 6)
composer test              # PHPUnit 9.6
```

Install the shared WordPress test library once (required before `composer test`):

```bash
bash ../leastudios-dev-tools/bin/install-wp-tests.sh \
    wordpress_test root '' 127.0.0.1 latest
```

No sibling plugins are required to run lint or the test suite. To exercise
the full audit pipeline against the local Herd site:

```bash
wp plugin activate leastudios-siteaudit
wp action-scheduler run --hooks=leastudios_siteaudit_tick
```

A PageSpeed Insights API key is required for live audit runs. Set it under
**Site Audit → Settings** after activation.

---

## 4. Data Model

### Custom tables

Seven tables are created on activation via `dbDelta()`, all prefixed
`{$wpdb->prefix}leastudios_siteaudit_`. Cross-table referential integrity is
enforced in PHP; `dbDelta` strips foreign-key declarations.

| Table constant (Schema::TABLE_*) | Logical name | Purpose |
|---|---|---|
| `TABLE_PROJECTS` | `leastudios_siteaudit_projects` | Named URL groups. |
| `TABLE_URLS` | `leastudios_siteaudit_urls` | Monitored URLs with per-URL settings. |
| `TABLE_AUDITS` | `leastudios_siteaudit_audits` | One row per audit run per strategy. |
| `TABLE_ISSUES` | `leastudios_siteaudit_issues` | Individual accessibility issues per audit. |
| `TABLE_AUDIT_COMPARISONS` | `leastudios_siteaudit_audit_comparisons` | Score delta + issue diff between consecutive audits. |
| `TABLE_NOTIFICATIONS` | `leastudios_siteaudit_notifications` | Notification delivery log. |
| `TABLE_EMAIL_SUBSCRIPTIONS` | `leastudios_siteaudit_email_subscriptions` | Per-user, per-project email subscription rows. |

All tables are dropped on plugin uninstall. Use `Schema::table( Schema::TABLE_* )`
to get a fully-prefixed table name rather than constructing the string yourself.

### Options

| Option key | Type | Description |
|---|---|---|
| `leastudios_siteaudit_options` | `array` | Plugin settings: `pagespeed_api_key`, `pagespeed_rate_limit`, `pagespeed_retry_count`, `default_audit_frequency`, `default_audit_strategy`. Seeded on activation. |
| `leastudios_siteaudit_db_version` | `int` | Current schema version. `Migration::maybe_migrate()` compares this to `SCHEMA_VERSION` on every request. |

---

## 5. Hooks Reference

### Audit & Notification Hooks

#### `leastudios_siteaudit_audit_completed`

- **Type:** Action
- **Location:** `src/Modules/Audit/Application/Services/Audit_Service.php`
- **Since:** 1.0.0
- **Description:** Fires once per `run_audit()` call, after every configured
  strategy (desktop, mobile, or both) has finished. The prior-audit snapshot
  passed in `$previous_audits` reflects the state **before** this run started —
  so if the URL audits both strategies, neither strategy's new row appears in the
  map. The built-in `Alert_Notifier` and `Audit_Report_Notifier` hook this action
  to send threshold-breach alerts and PDF report emails. A batch of runs from the
  hourly tick fires one action per URL.

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$url` | `LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url` | The URL that was audited. |
| `$audits` | `array<int, LEAStudios\SiteAudit\Modules\Audit\Domain\Models\Audit>` | Audits produced by this run, one per strategy. |
| `$previous_audits` | `array<string, Audit\|null>` | Map of `Run_Strategy::value` => prior completed audit (null if first run for that strategy). |

**Example:**

```php
add_action(
    'leastudios_siteaudit_audit_completed',
    function (
        \LEAStudios\SiteAudit\Modules\Url\Domain\Models\Url $url,
        array $audits,
        array $previous_audits
    ): void {
        foreach ( $audits as $audit ) {
            $strategy = $audit->get_strategy();
            $score    = $audit->get_score();
            $prior    = $previous_audits[ $strategy ] ?? null;

            if ( null !== $prior && $score < $prior->get_score() - 10 ) {
                // Post a Slack alert when the score drops more than 10 points.
                wp_remote_post( 'https://hooks.slack.com/services/YOUR/WEBHOOK', [
                    'body'    => wp_json_encode( [
                        'text' => sprintf(
                            'Score for %s (%s) dropped from %d to %d.',
                            esc_html( $url->get_url() ),
                            esc_html( $strategy ),
                            $prior->get_score(),
                            $score
                        ),
                    ] ),
                    'headers'  => [ 'Content-Type' => 'application/json' ],
                    'blocking' => false,
                ] );
            }
        }
    },
    10,
    3
);
```

---

#### `leastudios_siteaudit_mail_failed`

- **Type:** Action
- **Location:** `src/Modules/Notification/Application/Services/Wp_Mail_Service.php`
- **Since:** 1.0.0
- **Description:** Fires when `wp_mail()` returns false for a notification email
  (alert or report). The call site deliberately limits the error log to a
  PII-free string (`[leastudios-siteaudit] mail_send_failed`) so recipient
  addresses and subject lines — which may contain password-reset links or PII —
  do not appear in web-accessible error logs. Hook this action to route the rich
  payload into a logger of your choice (a structured log service, a monitoring
  webhook, an admin notice queue).

**Parameters:**

| Parameter | Type | Description |
|---|---|---|
| `$to` | `string` | Recipient address of the failed email. |
| `$subject` | `string` | Subject line of the failed email. |

**Example:**

```php
add_action(
    'leastudios_siteaudit_mail_failed',
    function ( string $to, string $subject ): void {
        // Log to a structured logging service instead of the PHP error log.
        wp_remote_post( 'https://logs.example.com/ingest', [
            'body'    => wp_json_encode( [
                'level'   => 'error',
                'channel' => 'leastudios-siteaudit',
                'message' => 'Notification email failed',
                'context' => [
                    'to'      => sanitize_email( $to ),
                    'subject' => sanitize_text_field( $subject ),
                ],
            ] ),
            'headers'  => [ 'Content-Type' => 'application/json' ],
            'blocking' => false,
        ] );
    },
    10,
    2
);
```

---

## 6. Hook Execution Order

For a typical audit run triggered by the hourly tick, hooks fire in this order:

```
(Action Scheduler — leastudios_siteaudit_tick)
    |
    +-- Tick_Dispatcher dispatches per-URL run_audit jobs
    |
(Action Scheduler — run_audit job executes)
    |
    +-- Audit_Service::run_audit()
    |       ├── Audit_Pipeline::run() × N strategies
    |       │       └── PageSpeed API → persist Audit + Issues + Comparison
    |       └── [action] leastudios_siteaudit_audit_completed
    |               ├── Alert_Notifier (threshold breach)
    |               │       └── Wp_Mail_Service::send()
    |               │               └-- [action] leastudios_siteaudit_mail_failed (on wp_mail failure)
    |               └── Audit_Report_Notifier (PDF report)
    |                       └── Wp_Mail_Service::send()
    |                               └-- [action] leastudios_siteaudit_mail_failed (on wp_mail failure)
    |
    └── (your hooks fire here too, at any priority on audit_completed)
```

| Order | Hook | Type | Trigger |
|---|---|---|---|
| 1 | `leastudios_siteaudit_audit_completed` | Action | After every strategy in a `run_audit()` call has finished persisting. |
| 2 | `leastudios_siteaudit_mail_failed` | Action | Inside `Wp_Mail_Service`, when `wp_mail()` returns false for a notification. |

`leastudios_siteaudit_mail_failed` fires only when a notification send fails —
it is nested inside `leastudios_siteaudit_audit_completed` listener callbacks
(`Alert_Notifier`, `Audit_Report_Notifier`), not in the main flow. A clean audit
run with successful emails fires only `leastudios_siteaudit_audit_completed`.

---

## 7. Testing

```bash
cd wp-content/plugins/leastudios-siteaudit
composer test                                # run the full suite
vendor/bin/phpunit --filter AuditServiceTest # one class
vendor/bin/phpunit tests/Unit/AuditTest.php  # one file
```

Tests are split between `tests/Unit/` (pure PHPUnit — value objects, comparison
service, trend calculator, retry strategy) and `tests/Integration/` (extends
`WP_UnitTestCase` — repository round-trips, controller submissions, capability
enforcement).

**Writing tests for code that loads this plugin:**

1. Extend `WP_UnitTestCase` and ensure `leastudios-siteaudit` is active in the
   test environment.
2. To assert `leastudios_siteaudit_audit_completed` fired, hook it in `setUp()`
   and inspect the arguments via a captured closure; use `did_action()` for a
   simple fired/not-fired assertion.
3. To test `leastudios_siteaudit_mail_failed`, replace `wp_mail` with a filter
   that returns false and assert the action count increments.

---

## 8. Release Process

This plugin uses a tag-triggered release workflow (`.github/workflows/release.yml`)
that auto-generates release notes from the commit log between the previous and
current tag.

**To cut a release:** bump the `Version:` header in `leastudios-siteaudit.php`,
commit, then:

```bash
git tag vX.Y.Z && git push origin vX.Y.Z
```

The workflow verifies the tag matches the `Version:` header, builds the zip with
`composer install --no-dev`, and publishes the GitHub release.

**Commit-prefix → release-notes section:**

- `feat:` → `## Added`
- `fix:` → `## Fixed`
- `refactor:` → `## Changed`
- `perf:` → `## Performance`

**Hidden from release notes:** `ci:`, `chore:`, `docs:`, `test:`, `style:`, `build:`, `release:`.

---

## 9. Where to Read More

- [`CLAUDE.md`](../CLAUDE.md) — this plugin's repo conventions, naming tokens, locked architecture decisions, and phased build history.
- [`README.md`](../README.md) — user-facing overview, feature list, configuration reference, and the audit-run flow diagram.
- [`leastudios-dev-tools/CLAUDE.md`](../../leastudios-dev-tools/CLAUDE.md) — suite-wide coding standards, security checklist (escape / sanitize / nonce / capability), and `$wpdb` conventions inherited by every plugin.
