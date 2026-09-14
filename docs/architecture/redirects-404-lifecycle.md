# Redirects and 404 Monitor Lifecycle

Module disable and enable, table creation, deactivation,
reactivation, and uninstall. No path leaves orphaned data.

## Module disabled

No class work, no hooks, no tables created, no settings read. Both
`RedirectsModule` and `MonitorModule` return from `register()` and
`boot()` before touching anything when disabled, so a disabled module
registers zero `add_action` or `add_filter` calls. The frontend cost
is zero queries and zero writes. Proven by the module gate tests.

## Module enabled

Enabling runs the enable path in order:

1. `RedirectTable::ensureTables()` (Redirects) or
   `LogTable::ensureTables()` (404 Monitor) creates the table when
   missing. The statement is `CREATE TABLE IF NOT EXISTS` through
   `dbDelta` with a direct query fallback, idempotent and safe to call
   on every enable.
2. The settings option is seeded with defaults and autoload disabled:
   `rankkernel_redirects_settings` via `RedirectsSettings`, or
   `rankkernel_404_settings` via `MonitorSettings`.
3. Redirects flags schema readiness and invalidates the match cache,
   so a toggle never serves stale matches.
4. `boot()` registers the frontend hooks: `template_redirect`
   priority 1 for dispatch plus `post_updated` for the slug watcher,
   or `template_redirect` priority 99 for 404 capture.

## Table creation without MigrationRunner

Module tables are never created through `MigrationRunner`. The runner
is a single linear ledger run on `init` priority 10: a module enabled
after the ledger advanced would never create its tables, and the
ledger has no per module retry or multisite per site creation. The
explicit per module `ensureTables` routine replaces it for these two
tables. The hot path additionally guards with a cheap table exists
check that fails open (no redirect logged, no fatal) when the table
is missing.

## Rule and log writes

- Rule create or update: validate, loop and chain analysis, upsert,
  invalidate the match cache group.
- Rule delete: delete, invalidate the match cache group.
- CSV import: batched validate and upsert with a per row report, then
  one invalidation when anything changed.
- CSV export: builds the document, no writes.
- 404 create: dedupe upsert, bounded prune scheduled at shutdown for
  new URIs.
- 404 prune: bounded oldest first delete by age and count.
- Manual clear: bounded delete loop with a pass cap, success notice.

## Deactivation and reactivation

Deactivation changes no data: tables, rules, log rows, and settings
all stay in place. Reactivation reruns the enable path, and
`ensureTables` rechecks table existence idempotently.

## Uninstall

`uninstall.php` already purges by prefix, so no new uninstall code was
needed. When the `purge_on_uninstall` setting is on, uninstall deletes
every `rankkernel_*` option and drops every `wp_rankkernel_*` table:

- Options: `rankkernel_modules`, `rankkernel_redirects_settings`,
  `rankkernel_404_settings`, `rankkernel_redirects_validator`,
  `rankkernel_404_suppressed`, and any future `rankkernel_*` option.
- Tables: `wp_rankkernel_redirects` (`RedirectTable::SUFFIX`) and
  `wp_rankkernel_404_log` (`LogTable::SUFFIX`), matched by the site
  prefix plus `rankkernel_` pattern.
- Post, term, and user meta under `_rankkernel_*` (unused by these
  modules, purged by the same run).

The naming contract is proven by `RedirectsMonitorUninstallTest`:
table names carry the `rankkernel_` suffix under the site prefix and
every owned option name starts with `rankkernel_`, matching the purge
patterns. Multisite purge is single site scope in v1, matching the
existing ledger ruling; a network wide loop is deferred and documented
in the uninstall file.
