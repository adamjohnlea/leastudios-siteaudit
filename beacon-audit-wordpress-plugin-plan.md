# Beacon Audit — WordPress Plugin Port

## Context

[Beacon Audit](https://github.com/adamjohnlea/beaconaudit) is an existing standalone PHP application (PHP 8.4 + SQLite + Twig + Symfony components) that monitors web pages for accessibility regressions using the Google PageSpeed Insights API. It supports projects, per-URL audit schedules (desktop/mobile/both), historical comparisons, trend analysis, CSV/PDF exports, and email alerts via AWS SES.

This plan describes a 1:1 functional port of that application to a self-contained **WordPress plugin** named `beacon-audit`. The motivation is distribution: shipping a WP plugin lets site owners install Beacon Audit alongside their existing site without provisioning a separate PHP app, reusing WordPress's auth, mail, cron, and admin UI primitives.

**Source repo to port from:** `/Users/adamlea/Herd/beaconaudit` — the modular architecture (`src/Modules/{Url,Audit,Dashboard,Reporting,Notification}`) is well-suited to a direct lift. The PageSpeed API client, comparison service, trend calculator, retry strategy, and value objects port cleanly. The HTTP layer (Symfony Routing), templating layer (Twig), Auth module (sessions + custom users table), and AWS SES wrapper are replaced with WordPress-native equivalents.

**Functional parity goal:** every feature listed in the source `README.md` and `accessibility-audit-dashboard-spec.md` works in the plugin. No features dropped.

---

## Architecture Decisions (locked)

| Concern | Decision | Reason |
|---|---|---|
| Templating | **Pure PHP templates** in `templates/` | Zero extra deps, idiomatic for WP. Twig views get rewritten as PHP partials. |
| PDF generation | **Bundle Dompdf** via Composer (`dompdf/dompdf ^3.1`) | Same library currently in use; full email-attached PDF reports retained. |
| Permissions | **Custom capabilities** `manage_beacon_audit` (write) + `view_beacon_audit` (read) added to Administrator (both) and Editor (view only) on activation | Site owners can reassign via any role-management plugin without us creating new roles. |
| Cron | **Action Scheduler** (WooCommerce's persistent async queue, bundled via Composer) drives the hourly tick AND every individual audit run, including manual "Run audit now". | Avoids tying up FPM workers on synchronous PageSpeed calls (~30–60s each). Persistent queue, automatic retries, built-in dashboard. Supersedes the original WP-Cron plan. |
| Database | **Custom MySQL tables** via `$wpdb` and `dbDelta()`, prefixed `{$wpdb->prefix}beacon_*` | Native to WP; backups, multisite, and migrations work out of the box. |
| Auth | **WordPress users**; drop the custom `users` table and `Auth` module entirely | `wp_get_current_user()`, nonces, and capabilities replace sessions/CSRF/roles. |
| Email | **`wp_mail()`** | Site owners pick their delivery (built-in PHP mail, WP Mail SMTP, AWS SES plugin, etc.). No SDK shipped. |
| Routing | **WP admin pages** via `add_menu_page` / `add_submenu_page`; form posts via `admin-post.php` actions | Standard WP admin UX, integrates with existing nav. |
| Settings | **WP Settings API** for PageSpeed API key, rate limit, retry count, default audit frequency | Standard WP options page. |
| Assets | Pre-built CSS shipped in `assets/css/admin.css`. Tailwind compile happens at dev time in the plugin repo, not at runtime. | No npm requirement on end-user installs. |

---

## Plugin Layout

```
beacon-audit/
├── beacon-audit.php              # Plugin header + bootstrap (loads autoloader, wires hooks)
├── readme.txt                    # WP.org-style readme
├── uninstall.php                 # Drops tables + options if user opts in
├── composer.json                 # Requires php ^8.1, dompdf/dompdf ^3.1
├── vendor/                       # Committed (so end-users don't need Composer)
├── src/
│   ├── Plugin.php                # Singleton bootstrap — registers all hooks
│   ├── Activation.php            # Creates tables (dbDelta), adds capabilities, schedules cron
│   ├── Deactivation.php          # Unschedules cron (does NOT drop data)
│   ├── Database/
│   │   ├── Schema.php            # dbDelta SQL for all tables
│   │   └── Migrator.php          # Versioned schema upgrades on plugin update
│   ├── Modules/
│   │   ├── Url/                  # Ported from src/Modules/Url
│   │   │   ├── Domain/           # Url, Project models + value objects (UrlAddress, AuditFrequency, AuditStrategy, ProjectName)
│   │   │   ├── Repository/       # WpdbUrlRepository, WpdbProjectRepository (replace Sqlite* repos)
│   │   │   └── Service/          # UrlService, ProjectService, BulkImportService
│   │   ├── Audit/                # Ported from src/Modules/Audit
│   │   │   ├── Domain/           # Audit, Issue, AuditComparison + VOs (AccessibilityScore, AuditStatus, IssueSeverity, IssueCategory, ScoreDelta, Trend, RunStrategy)
│   │   │   ├── Repository/       # WpdbAuditRepository, WpdbIssueRepository, WpdbAuditComparisonRepository
│   │   │   ├── Service/          # AuditService, ComparisonService, TrendCalculator, ScheduledAuditRunner
│   │   │   └── Api/              # PageSpeedApiClient, ApiResponse, RetryStrategy (port verbatim, swap curl for wp_remote_get)
│   │   ├── Dashboard/            # DashboardStatistics, UrlSummary, DashboardSummary
│   │   ├── Reporting/            # CsvExportService, PdfReportService (Dompdf), PdfReportDataCollector
│   │   └── Notification/
│   │       ├── AlertNotifier.php # Threshold-based alerts; sends via WpMailService
│   │       ├── AuditReportNotifier.php # Post-cron PDF reports to subscribers
│   │       ├── WpMailService.php # Thin wrapper over wp_mail()
│   │       └── Repository/WpdbEmailSubscriptionRepository.php
│   └── WP/                       # WordPress integration layer (replaces src/Http)
│       ├── AdminMenu.php         # Registers all admin pages
│       ├── Capabilities.php      # Defines manage_beacon_audit + view_beacon_audit
│       ├── Cron.php              # Registers beacon_audit_tick action; due-URL dispatcher
│       ├── Settings.php          # Settings API page (API key, rate limit, retry count, default frequency)
│       ├── Assets.php            # Enqueues admin CSS/JS only on plugin pages
│       ├── Controllers/          # One class per admin page; handles GET render + POST via admin_post_*
│       │   ├── DashboardController.php
│       │   ├── ProjectController.php
│       │   ├── UrlController.php
│       │   ├── AuditController.php   # Manual run + audit detail
│       │   └── ExportController.php  # CSV + PDF downloads
│       └── Notices.php           # Activation notice if API key missing
├── templates/                    # PHP partials, replace Twig views
│   ├── layout/header.php
│   ├── layout/footer.php
│   ├── layout/nav.php
│   ├── dashboard/index.php
│   ├── dashboard/project.php
│   ├── dashboard/url-detail.php  # Desktop/mobile tabs (ported from recent commit ed3cb3a)
│   ├── projects/{index,form}.php
│   ├── urls/{index,form,bulk-import,bulk-import-result}.php
│   ├── settings/index.php
│   ├── reports/project-pdf.php   # Dompdf-targeted, inline styles only
│   └── emails/{alert-score,audit-report}.php
├── assets/
│   ├── css/admin.css             # Pre-built; checked in
│   └── src/admin.css             # Tailwind source for plugin dev
├── tests/
│   ├── bootstrap.php             # Loads WP test suite + plugin
│   ├── Unit/                     # Pure PHPUnit — value objects, services with mocked repos
│   └── Integration/              # WP_UnitTestCase — schema, repos, controllers
└── bin/
    └── install-wp-tests.sh       # Standard WP test scaffolding script
```

---

## Database Schema

Eight tables, all prefixed with `$wpdb->prefix`. Created via `dbDelta()` on activation. Schema version stored in `option('beacon_audit_db_version')` for future migrations.

```sql
{prefix}beacon_projects          (id, name, description, created_at, updated_at)
{prefix}beacon_urls              (id, project_id, url, name, audit_frequency,
                                  audit_strategy, enabled, alerts_enabled,
                                  alert_threshold_score, alert_threshold_drop,
                                  last_audited_at, created_at, updated_at)
{prefix}beacon_audits            (id, url_id, score, status, strategy,
                                  audit_date, raw_response LONGTEXT,
                                  error_message, retry_count, created_at)
{prefix}beacon_issues            (id, audit_id, severity, category, title,
                                  description, element_selector, help_url, created_at)
{prefix}beacon_audit_comparisons (id, current_audit_id, previous_audit_id,
                                  score_delta, new_issues_count,
                                  resolved_issues_count, persistent_issues_count,
                                  trend, created_at)
{prefix}beacon_notifications     (id, url_id, audit_id, notification_type, channel,
                                  sent_at, failed_at, error_message, created_at)
{prefix}beacon_email_subscriptions (id, user_id, project_id, created_at)
```

The source `users` table is **dropped** — WordPress handles users. The `email_subscriptions.user_id` column references `wp_users.ID`.

Indexes: `project_id`, `enabled`, `audit_frequency`, `last_audited_at` on URLs; `(url_id, audit_date)` on audits; `audit_id` and `severity` on issues.

---

## Admin Pages (Routes)

Top-level menu **"Beacon Audit"** (dashicon `dashicons-universal-access-alt`). All pages require `view_beacon_audit`; mutating actions require `manage_beacon_audit`.

| Slug | Page | Cap | Source equivalent |
|---|---|---|---|
| `beacon-audit` | Dashboard (project cards + unassigned URLs) | view | `GET /` |
| `beacon-audit-project&id=` | Project detail | view | `GET /projects/{id}/dashboard` |
| `beacon-audit-url&id=` | URL detail (desktop/mobile tabs, history, issues) | view | URL detail screen |
| `beacon-audit-projects` | Project CRUD list | manage | `/projects` |
| `beacon-audit-urls` | URL CRUD list + bulk import | manage | `/urls`, `/urls/bulk-import` |
| `beacon-audit-settings` | API key, rate limit, retry count, default frequency, default strategy | manage | `.env` config |

Form submissions go to `admin-post.php?action=beacon_audit_<verb>` and are dispatched through `WP/Controllers/*` after `check_admin_referer()` + capability check. Success redirects use `wp_safe_redirect()` with a transient-stored flash message.

CSV/PDF downloads stream from `admin-post.php` actions with appropriate `Content-Type` and `Content-Disposition` headers.

---

## Core Service Port — What Stays vs. What Changes

**Port verbatim** (pure domain logic, no framework dependencies):
- All value objects: `AccessibilityScore`, `AuditStatus`, `IssueSeverity`, `IssueCategory`, `ScoreDelta`, `Trend`, `RunStrategy`, `UrlAddress`, `AuditFrequency`, `AuditStrategy`, `ProjectName`, `EmailAddress`, `BulkImportResult`
- `ComparisonService`, `TrendCalculator`
- `ApiResponse` (parses Lighthouse JSON)
- `RetryStrategy` (exponential backoff)
- `DashboardStatistics`
- `BulkImportService`
- `PdfReportDataCollector`

**Port with one swap** (HTTP transport):
- `PageSpeedApiClient` — replace the `CurlHttpClient` dependency with `wp_remote_get()` wrapped in a thin `WpHttpClient` adapter. API surface unchanged.

**Replace** (framework-bound):
- `Sqlite*Repository` classes → `Wpdb*Repository` using `$wpdb->prepare()` / `$wpdb->insert()` / `$wpdb->update()`. Same interface contracts so services don't change.
- `SesEmailService` → `WpMailService` using `wp_mail()`. Same interface so `AlertNotifier` and `AuditReportNotifier` don't change.
- All Twig templates → PHP partials in `templates/`, rendered via a tiny `view($name, $data)` helper that `extract()`s data and `require`s the file.
- All controllers → WP admin-page render callbacks + `admin_post_*` action handlers.
- `AuthService`, custom user CRUD, login/logout, CSRF token logic → **deleted**. Replaced by WP nonces + capability checks.

**Cron / async port — Action Scheduler:**
- Bundle Action Scheduler via Composer (`woocommerce/action-scheduler`) and load it from the plugin bootstrap. Registers its own hidden admin pages (`Tools → Scheduled Actions`) for visibility into the queue.
- Hourly tick: `as_schedule_recurring_action( time(), HOUR_IN_SECONDS, 'leastudios_siteaudit_tick' )` registered on activation; `Cron::tick()` (the dispatcher) becomes the action handler.
- Per-URL audits: `Cron::tick()` enqueues one `as_enqueue_async_action( 'leastudios_siteaudit_run_audit', [ $url_id ] )` per due URL instead of calling `Audit_Service::run_audit()` synchronously. Each enqueued action runs in its own background request, so one slow PageSpeed call cannot block the tick or other audits.
- Manual "Run audit now": same pattern. The admin-post handler enqueues `leastudios_siteaudit_run_audit` and redirects immediately with an "Audit queued" notice; the user refreshes to see the result.
- Post-tick notifications: a separate `leastudios_siteaudit_post_tick` action is enqueued at the end of `Cron::tick()` so `Audit_Report_Notifier` runs only once after all URL audits in that batch complete.
- README documents disabling WP-Cron (`define('DISABLE_WP_CRON', true);`) and adding `* * * * * curl https://site.com/wp-cron.php?doing_wp_cron` for reliability on low-traffic sites — Action Scheduler still runs its queue via WP-Cron's loopback by default.

---

## Critical Source Files to Reference

When implementing each module, read the corresponding source files first — they contain validated logic, edge cases, and TDD-driven invariants that must be preserved.

- Audit orchestration: `src/Modules/Audit/Application/Services/AuditService.php`
- PageSpeed API: `src/Modules/Audit/Infrastructure/Api/PageSpeedApiClient.php`, `ApiResponse.php`, `RetryStrategy.php`
- Comparison/trend: `src/Modules/Audit/Application/Services/ComparisonService.php`, `TrendCalculator.php`
- Cron dispatch: `cron/run-scheduled-audits.php` and `src/Modules/Audit/Application/Services/ScheduledAuditRunner.php`
- URL/Project services: `src/Modules/Url/Application/Services/{UrlService,ProjectService,BulkImportService}.php`
- PDF generation: `src/Modules/Reporting/Application/Services/{PdfReportService,PdfReportDataCollector}.php`
- Notifications: `src/Modules/Notification/Application/Services/{AlertNotifier,AuditReportNotifier}.php`
- Schema: `src/Database/Migrations/001_*.sql` through `012_*.sql`
- Templates to port: `src/Views/dashboard/*.twig`, `src/Views/urls/*.twig`, `src/Views/projects/*.twig`, `src/Views/reports/project-pdf.twig`, `src/Views/emails/*.twig`
- Spec: `accessibility-audit-dashboard-spec.md`

---

## Implementation Phases

Build in this order. Each phase ends with a working, testable slice — do not start a later phase until the previous one is verified.

1. **Skeleton + activation** — Plugin header, autoloader (Composer), `Activation`/`Deactivation`, schema creation via `dbDelta`, capabilities registration, settings page with API key field. Verify: plugin activates cleanly, tables appear in DB, settings save.
2. **URL + Project module** — Domain models, VOs, repositories, services, admin pages, bulk import. Verify: CRUD works in admin, bulk import handles valid/invalid rows correctly.
3. **Audit module (core)** — Port `PageSpeedApiClient` with `wp_remote_get`, `RetryStrategy`, `AuditService`, `ComparisonService`, `TrendCalculator`. Add a "Run audit now" button on URL detail. Verify: real PageSpeed call against a live URL returns a score, audit + issues + comparison persist.
4. **Dashboard views** — Port `DashboardStatistics` and the dashboard/project/URL-detail templates. Includes desktop/mobile tabs (recent commit `ed3cb3a`) and per-URL inline scoring (`44c4980`). Verify: dashboard shows realistic data after a few manual audits.
5. **Async / scheduling (Action Scheduler)** — bundle Action Scheduler via Composer; register the recurring `leastudios_siteaudit_tick` action; port the dispatcher (`Cron::tick()`) so it enqueues a per-URL `leastudios_siteaudit_run_audit` async action instead of running audits inline; convert "Run audit now" to also enqueue the same action and redirect immediately. Verify: queue a manual run, observe the `IN_PROGRESS` row appear seconds later, then `COMPLETED`, without the click handler blocking. Trigger the recurring tick via `wp action-scheduler run` and confirm due URLs get audited.
6. **Reporting** — `CsvExportService`, `PdfReportService` with Dompdf, export controllers. Verify: CSV downloads parse cleanly; PDF renders with score, trend, issue breakdown.
7. **Notifications** — `WpMailService`, `AlertNotifier` (threshold-driven), `AuditReportNotifier` (post-cron), email subscription toggle on project page. Verify: trigger an alert by setting an aggressive threshold; check email is delivered with the rendered template.
8. **Polish** — Activation notices when API key missing, capability gating audit, accessibility pass on the admin UI itself, `readme.txt` for WP.org metadata.

---

## Testing Strategy

- **Unit tests** (PHPUnit, no WP): all value objects, `ComparisonService`, `TrendCalculator`, `RetryStrategy`, `BulkImportService`, `ApiResponse` parsing. Port the existing tests from `tests/Unit/` in the source repo verbatim.
- **Integration tests** (`WP_UnitTestCase` via `bin/install-wp-tests.sh`): repository round-trips, controller form submission flow with nonces, cron dispatch logic, capability enforcement.
- **Manual verification checklist** at the end of each phase (above) — actual PageSpeed calls, actual `wp_mail` delivery via a logging plugin or local Mailhog.
- Static analysis: PHPStan level 8 (level 10 in source is impractical with WP's loosely-typed globals; 8 is the realistic ceiling for WP plugin code).

---

## Verification (end-to-end, after Phase 8)

Smoke test the full system in a fresh WP install:

1. `wp plugin install beacon-audit.zip --activate` — verify activation creates eight tables, schedules `beacon_audit_tick`, and adds the two capabilities to Administrator + Editor.
2. Visit **Beacon Audit → Settings**, paste a PageSpeed API key, save.
3. Create a project, add 2–3 URLs (mix of `desktop`, `mobile`, `both` strategies), set an alert threshold of 95 on one URL.
4. Click **Run audit now** on each URL — confirm scores and issues populate, comparisons appear after the second run.
5. Open the project dashboard — verify trend indicators, score cards, desktop/mobile tabs render correctly.
6. **Reports:** export CSV (single URL + summary) and PDF (project) — confirm content matches dashboard.
7. **Cron:** `wp cron event run beacon_audit_tick` — confirm due URLs are re-audited.
8. **Email:** subscribe yourself to the project; force a low-score audit; verify alert email arrives via configured `wp_mail` transport. After cron run, verify project PDF report email arrives.
9. **Capabilities:** create an Editor user — confirm they can view dashboards but cannot reach the URL/Project/Settings management pages.
10. `wp plugin deactivate beacon-audit` — confirm cron unschedules but data is preserved. Reactivate — confirm everything resumes.

---

## Out of scope for v1

- Multisite (`network_admin_menu`) — single-site only at launch; can be added later by gating menu registration on `is_multisite()`.
- WP-CLI commands beyond what `wp cron event run` already provides — defer.
- REST API endpoints — defer until there's a clear consumer (mobile app, headless dashboard).
- Migration tool from the standalone app's SQLite DB into the plugin's MySQL tables — out of scope; new installs only.
