# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project status

**This plugin is not yet implemented.** The repository currently contains only [beacon-audit-wordpress-plugin-plan.md](beacon-audit-wordpress-plugin-plan.md) — a 244-line port plan. Read it before proposing or writing any code. The next concrete action is **Phase 1** of the port plan (skeleton + activation), starting from `../leastudios-dev-tools/_boilerplate/`.

## What this plugin is

A WordPress plugin port of [Beacon Audit](https://github.com/adamjohnlea/beaconaudit) — a Google PageSpeed Insights–driven accessibility-monitoring dashboard for a portfolio of URLs. It is being repackaged as a single self-contained plugin so site owners can install it alongside an existing site instead of running a separate PHP app, with full functional parity (no features dropped).

## Authoritative references — read in this order

1. [beacon-audit-wordpress-plugin-plan.md](beacon-audit-wordpress-plugin-plan.md) — locked architecture, phased build order, schema, port matrix. **Mentally translate** `beacon-audit` / `beacon_*` → `leastudios-siteaudit` / `leastudios_siteaudit_*` while reading; the plan predates the LEA Studios rename.
2. `/Users/adamlea/Herd/beaconaudit/` — the source application being ported. Lift domain logic, value objects, services, and unit tests from here verbatim where possible: `src/Modules/{Url,Audit,Dashboard,Reporting,Notification}/**`, `tests/Unit/**`, `src/Database/Migrations/**`, `accessibility-audit-dashboard-spec.md`.
3. `../leastudios-dev-tools/CLAUDE.md` — the **mother CLAUDE.md** for all `leastudios-*` plugins. Coding standards, escape/sanitize rules, nonce/capability conventions, REST patterns, shared `composer` quality commands. **Conventions there are inherited; this file does not duplicate them.**
4. `../leastudios-dev-tools/_boilerplate/` — copy this to scaffold the plugin in Phase 1.

## Naming conventions (canonical — overrides the port plan)

The directory is `leastudios-siteaudit`; the port plan calls the plugin `beacon-audit` only because it predates the rename. Use these values everywhere:

| Concern | Value |
|---|---|
| Plugin slug / dir / text domain | `leastudios-siteaudit` |
| Display / menu title | `leaStudios Site Audit` |
| Bootstrap file | `leastudios-siteaudit.php` |
| Composer namespace root | `LEAStudios\SiteAudit\` |
| Constants | `LEASTUDIOS_SITEAUDIT_VERSION` / `_FILE` / `_DIR` / `_URL` |
| Init function | `leastudios_siteaudit_init()` |
| Hook / option / action prefix | `leastudios_siteaudit_` |
| Cron action | `leastudios_siteaudit_tick` |
| DB schema-version option | `leastudios_siteaudit_db_version` |
| DB tables | `{$wpdb->prefix}leastudios_siteaudit_*` (projects, urls, audits, issues, audit_comparisons, notifications, email_subscriptions — seven tables; the port plan's eighth was the dropped users table) |
| Capabilities | `manage_leastudios_siteaudit` (write), `view_leastudios_siteaudit` (read) |
| REST namespace | `leastudios-siteaudit/v1/` |

Translate `beacon-audit` / `beacon_*` strings as you go.

## Build / test / lint commands

These match the conventions used by every sibling `leastudios-*` plugin. **Available after Phase 1 wires up Composer** — they will not work yet.

```bash
composer install                                  # in this plugin dir
composer phpcs                                    # WordPress coding standards
composer phpcbf                                   # auto-fix coding standards
composer phpstan                                  # static analysis (level 6, scans src/)
composer lint                                     # phpcs + phpstan
composer test                                     # PHPUnit (WP_UnitTestCase)

# One-time, before composer test:
bash ../leastudios-dev-tools/bin/install-wp-tests.sh \
    wordpress_test root '' 127.0.0.1 latest

# WP-CLI smoke checks against the local Herd site:
wp plugin activate leastudios-siteaudit
wp cron event run leastudios_siteaudit_tick
```

**No npm/Node tooling** — assets are pre-built and shipped, enqueued via `wp_enqueue_script()` / `wp_enqueue_style()`. The port plan mentions a Tailwind dev-time compile step; defer that until there is real demand. For now ship hand-written or pre-built CSS in `assets/css/admin.css`.

## Locked architecture (do not relitigate)

- **Templating**: pure PHP partials in `templates/` (no Twig).
- **PDF**: Dompdf bundled via Composer, vendored into the plugin.
- **Permissions**: caps `manage_leastudios_siteaudit` (write) and `view_leastudios_siteaudit` (read); granted on activation to Administrator (both) and Editor (view only).
- **Cron**: `wp_schedule_event('hourly', 'leastudios_siteaudit_tick')`; document a system-cron fallback for low-traffic sites.
- **DB**: `$wpdb` + `dbDelta()`; seven tables prefixed `{$wpdb->prefix}leastudios_siteaudit_`; schema version stored in `option('leastudios_siteaudit_db_version')` for migrations.
- **Auth**: WordPress users only — delete the source repo's `Auth` module entirely. `wp_get_current_user()` + nonces + capabilities replace sessions / CSRF / custom roles.
- **Email**: `wp_mail()` only — no AWS SDK. Site owner's choice of SMTP/SES plugin handles delivery.
- **HTTP**: `PageSpeedApiClient` keeps its surface; only the curl client is swapped for a `WpHttpClient` adapter wrapping `wp_remote_get()`.
- **Routing**: `add_menu_page` / `add_submenu_page` for screens; `admin-post.php?action=leastudios_siteaudit_<verb>` for mutations, behind `check_admin_referer()` + capability check; `wp_safe_redirect()` on success with transient-stored flash messages.
- **Settings**: WP Settings API page (PageSpeed API key, rate limit, retry count, default frequency, default strategy).
- **Assets**: pre-built CSS in `assets/css/admin.css`, enqueued only on plugin pages.

## Module port matrix

- **Port verbatim** (zero framework deps — copy as-is from `/Users/adamlea/Herd/beaconaudit/src/`): all value objects (`AccessibilityScore`, `AuditStatus`, `IssueSeverity`, `IssueCategory`, `ScoreDelta`, `Trend`, `RunStrategy`, `UrlAddress`, `AuditFrequency`, `AuditStrategy`, `ProjectName`, `EmailAddress`, `BulkImportResult`), `ComparisonService`, `TrendCalculator`, `ApiResponse`, `RetryStrategy`, `DashboardStatistics`, `BulkImportService`, `PdfReportDataCollector`.
- **Port with one swap** (HTTP transport): `PageSpeedApiClient` — replace the curl client with `WpHttpClient` (a thin `wp_remote_get()` adapter). API surface unchanged.
- **Replace** (framework-bound): `Sqlite*Repository` → `Wpdb*Repository` (same interfaces, so services don't change); `SesEmailService` → `WpMailService`; Twig views → PHP partials in `templates/`; controllers → admin-page render callbacks + `admin_post_*` handlers; `Auth` module → **deleted**.

## Phased build order (current state: not started)

Each phase ends with a working, testable slice. **Do not start phase N+1 until phase N is verified.**

1. **Skeleton + activation** ← *active starting point.* Copy `../leastudios-dev-tools/_boilerplate/`, find-and-replace name tokens, wire `composer.json` (PHP `^8.1`, `dompdf/dompdf ^3.1`), `dbDelta()` schema, capability registration, settings page with API key field. Verify: plugin activates cleanly, tables appear, settings save.
2. **URL + Project module** — domain models, VOs, repositories, services, admin pages, bulk import. Verify: CRUD works; bulk import handles valid/invalid rows.
3. **Audit module (core)** — port `PageSpeedApiClient` with `wp_remote_get`, `RetryStrategy`, `AuditService`, `ComparisonService`, `TrendCalculator`. Add "Run audit now" on URL detail. Verify: real PageSpeed call returns a score; audit + issues + comparison persist.
4. **Dashboard views** — `DashboardStatistics`, dashboard / project / URL-detail templates (incl. desktop/mobile tabs). Verify: dashboard shows realistic data after several manual audits.
5. **Cron** — `WP/Cron::tick()`, `wp_schedule_event` registration, system-cron docs. Verify: `wp cron event run leastudios_siteaudit_tick` audits due URLs.
6. **Reporting** — `CsvExportService`, `PdfReportService` (Dompdf), export controllers. Verify: CSV parses cleanly; PDF renders score + trend + issue breakdown.
7. **Notifications** — `WpMailService`, `AlertNotifier` (threshold), `AuditReportNotifier` (post-cron), per-project subscription toggle. Verify: aggressive threshold triggers an alert email; post-cron PDF report email arrives.
8. **Polish** — activation notice if API key missing, capability gating audit, accessibility pass on the admin UI, `readme.txt` for WP.org metadata.

## Testing

PHPUnit 9.6 with the WP test suite. Tests extend a shared abstract `tests/TestCase.php` (which extends `WP_UnitTestCase`) — match the sibling pattern (see `../leastudios-forms/tests/TestCase.php` for the abstraction to copy). Port `tests/Unit/` from `/Users/adamlea/Herd/beaconaudit/tests/Unit/` verbatim for value objects and pure services (`ComparisonService`, `TrendCalculator`, `RetryStrategy`, `BulkImportService`, `ApiResponse`). Integration tests (repository round-trips, controller submission with nonces, cron dispatch, capability enforcement) use `WP_UnitTestCase` and the shared `bin/install-wp-tests.sh` from `leastudios-dev-tools`. PHPStan **level 6** (the sibling default) — not level 8/9 as the port plan and source repo specify.

## House rules (carried over from `~/.claude/CLAUDE.md`)

- No fallbacks or workarounds without explicit written approval; silence is not approval.
- One code path per action — buttons, menus, keyboard shortcuts, gestures, REST endpoints all call the same implementation.
- Surface every error to the user; never silently swallow exceptions.
- No backwards-compatibility shims for code that has not shipped.
- Never run destructive git commands (`reset --hard`, `checkout --`, `restore`, `revert`, `clean`, force push, `commit --amend`) without explicit written approval.

## Releases

This plugin uses a tag-triggered release workflow (`.github/workflows/release.yml`) that auto-generates release notes from the commit log between the previous and current tag.

**To cut a release:** bump the `Version:` header in the main plugin file, commit, then:

```bash
git tag vX.Y.Z && git push origin vX.Y.Z
```

The workflow verifies the tag matches the header, builds the zip with `composer install --no-dev`, and publishes the release.

**Commit-prefix → release-notes section:**

- `feat:` → `## Added`
- `fix:` → `## Fixed`
- `refactor:` → `## Changed`
- `perf:` → `## Performance`

**Hidden from release notes** (use these prefixes for changes you don't want surfaced): `ci:`, `chore:`, `docs:`, `test:`, `style:`, `build:`, `release:`.

The subject text after the prefix becomes the bullet verbatim, with the first letter capitalized. To override auto-notes for a specific release, edit the body in the GitHub UI after publish.
