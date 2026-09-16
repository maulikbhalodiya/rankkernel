# Audit: RankKernel 404 Monitor

Read-only audit of `src/Modules/Monitor/`, the NotFound admin screen, the
monitor JS, the Monitor unit tests, and the claimed state in
`docs/ROADMAP.md`, `docs/competitor-analysis/feature-parity-matrix.md`,
`docs/architecture/404-retention.md`, `docs/architecture/404-monitor.md`,
`docs/architecture/redirects-404-lifecycle.md`,
`docs/architecture/redirects-404-security.md`, and `docs/privacy.md`.

## Verdict

The 404 Monitor is substantially real and matches the roadmap's 2.5 scope: genuine
frontend 404 capture at `template_redirect` priority 99 (`Logger.php:171`) with a
full skip cascade (`Logger.php:177-231`), shared-normalizer URI identity
(`Logger.php:283-312` -> `Normalizer.php:60-106`), hash-deduplicated logging on a
`UNIQUE (uri_hash)` index (`LogTable.php:127`, `MonitorRepository.php:68-145`), an
age-then-count pruner that is explicitly oldest-first and bounded with `LIMIT`
(`Pruner.php:64-102`, `MonitorRepository.php:387-436`), a per-window flood guard
(`FloodGuard.php:68-104`), case-sensitive comparators with no regex
(`Exclusions.php:24-127`), a bounded manual clear (`NotFoundPage.php:542-558`),
capability+nonce on every mutation (`NotFoundPage.php:485-503`), and **no IP
storage of any kind** (`Logger.php:411-430`; proven by
`MonitorFloodRetentionTest::test_no_ip_address_is_read_or_stored`). The one-click
redirect path is a *link* into the Redirects add screen (`NotFoundPage.php:468-478`)
whose save is handled by the exact same `RedirectsPage::handleFormSave()` /
`validateFields()` pipeline as the Redirects admin screen
(`RedirectsPage.php:903-1162`), so it does **not** bypass validation (no P0). Two
adversarial findings remain, both non-critical: the hit counter is a non-atomic
read-modify-write so concurrent duplicate hits can undercount (`MonitorRepository.php:82`,
`133`), and two roadmap-deferred features (404-log CSV export, bulk 410 from
selected rows) are genuinely absent, correctly labelled in the matrix as
`PLANNED`/`MISSING` rather than `DONE`. The matrix's 404 rows are otherwise
accurate.

## Functionality table

| Module | Functionality | Expected Behavior | Current Code | Status | Evidence | Gap |
|---|---|---|---|---|---|---|
| Monitor | 404 detection | Genuine frontend 404s captured after canonical + redirector; non-404/admin/ajax/rest/cron/sitemap/410/451/static/probe skipped | `Logger.php:34` PRIORITY=99; `:171` hook; `:177-231` skip cascade; `:204` 410/451; `:366-380` sitemap | COMPLETE | `MonitorLoggerTest::test_genuine_404_creates_exactly_one_row`, `::test_skips_non_404`, `::test_skips_admin_ajax_and_cron`, `::test_skips_rest_requests`, `::test_skips_sitemap_requests`, `::test_skips_gone_and_unavailable_codes`, `::test_skips_static_assets_but_logs_content`, `::test_skips_probe_patterns` | None |
| Monitor | URI normalisation | Path via shared redirect normalizer; query kept only when ignore_query off; fragment stripped | `Logger.php:283-312`; `Normalizer.php:60-106` | COMPLETE | `MonitorLoggerTest::test_ignore_query_on_collapses_variants`, `::test_ignore_query_off_keeps_variants_distinct` | None |
| Monitor | Deduplication | Repeat hits for one normalized URI update one row, never a second row | `Logger.php:233-259` (per-request static map); `MonitorRepository.php:68-103`; `LogTable.php:127` UNIQUE uri_hash | COMPLETE | `MonitorRepositoryTest::test_record_inserts_then_increments_without_duplicate_row`; `MonitorLoggerTest::test_repeat_404_increments_same_row_across_requests`, `::test_second_hit_inside_one_request_counts_once` | None |
| Monitor | Concurrent duplicate rows | Two simultaneous inserts of same hash cannot both persist | `MonitorRepository.php:107-142` (insert fails -> re-select -> update fallback) | COMPLETE | `MonitorRepositoryTest::test_record_race_on_unique_hash_falls_back_to_update` | Unique index is the real guard; test simulates the failed insert, not true parallel sessions |
| Monitor | Hit count | `hits` increments once per logged request | `MonitorRepository.php:82` (`existing+1`), `:112` (insert hits=1), `:133` | COMPLETE | `MonitorRepositoryTest::test_record_inserts_then_increments_without_duplicate_row`; `MonitorLoggerTest::test_repeat_404_increments_same_row_across_requests` | Serial aggregation correct |
| Monitor | Hit count under concurrency | Concurrent increments must not be lost | `MonitorRepository.php:82` read-then-write absolute value; no `hits = hits + 1` SQL | PARTIAL | No test; logic at `:82`/`:133` is non-atomic | Lost-update: two readers at hits=5 both write 6. Stat undercount only, no row loss |
| Monitor | Last seen | `last_accessed` refreshed on every hit | `MonitorRepository.php:83`, `:116`, `:134` | COMPLETE | `MonitorRepositoryTest::test_record_inserts_then_increments_without_duplicate_row` (asserts `2026-06-01 12:00:00`) | None |
| Monitor | First seen | `created` set on insert, never changed | `MonitorRepository.php:115`; never in any UPDATE | COMPLETE | `MonitorLoggerTest::test_genuine_404_creates_exactly_one_row` (schema keys) | None |
| Monitor | Retention settings | `retention_days` default 30, clamped 1-365; option autoload off | `MonitorSettings.php:72-82`, `:245-257`, `:129/161` update_option false | COMPLETE | `MonitorSettingsTest::test_defaults_match_binding_contract`, `::test_retention_days_cannot_be_zero_or_unbounded` | None |
| Monitor | Age-based pruning | Delete rows with `last_accessed < cutoff`, bounded 500 | `Pruner.php:75-77`, `:109-118`; `MonitorRepository.php:387-402` | COMPLETE | `MonitorPrunerTest::test_cutoff_follows_retention_days`, `::test_prune_by_age_removes_only_stale_rows`; `MonitorRepositoryTest::test_delete_older_than_removes_only_stale_rows` | None |
| Monitor | Count-based pruning | Delete oldest past max, excess + 20% margin, cap 500 | `Pruner.php:89-102`; `MonitorRepository.php:414-436` | COMPLETE | `MonitorPrunerTest::test_prune_by_count_removes_oldest_first_with_margin`, `::test_prune_by_count_within_limit_removes_nothing`; `MonitorFloodRetentionTest::test_pruner_holds_flood_intake_at_maximum_oldest_first` | None |
| Monitor | Oldest-first ordering / no truncate | Every prune deletes `ORDER BY last_accessed ASC, id ASC LIMIT n`; never TRUNCATE | `MonitorRepository.php:399`, `:433`, `:375`; `Pruner.php:18-20` | COMPLETE | `MonitorPrunerTest::test_prune_runs_age_then_count_and_never_truncates`; `MonitorRepositoryTest::test_clear_all_bounded_loops_without_truncate`, `::test_delete_oldest_over_removes_oldest_first_bounded` | Truncation-bug pattern is avoided |
| Monitor | Exclusions | exact/prefix/contains/suffix/wildcard, case sensitive, no regex, bounded 200 rules | `Exclusions.php:24-127` (`:82-98` byte compares); `MonitorSettings.php:328-362`; `Logger.php:229-231` | COMPLETE | `MonitorExclusionsTest::test_matching_is_case_sensitive`, `::test_wildcard_star_matches_any_sequence`, `::test_malformed_rules_never_match_and_never_fatal`; `MonitorSettingsTest::test_exclusions_keep_only_well_formed_rules` | None |
| Monitor | Manual clearing | Clear Log uses bounded loop (500 x 20), cap+nonce, notice | `NotFoundPage.php:542-558`; `MonitorRepository.php:363-378` | COMPLETE | `MonitorAdminTest::test_clear_authorized_empties_log`, `::test_clear_without_capability_dies`, `::test_clear_with_bad_nonce_dies` | Documented in `404-retention.md:31-39` |
| Monitor | Configured limits | max_rows 100-10000, flood_budget 1-1000, flood_window 60-3600, all clamped at storage and input | `MonitorSettings.php:259-299`; `NotFoundPage.php:630-633` | COMPLETE | `MonitorSettingsTest::test_max_rows_cannot_be_zero_or_unbounded`, `::test_flood_budget_and_window_clamp`; `MonitorAdminTest::test_settings_save_stores_sanitized_values_with_clamping` | None |
| Monitor | Admin list | Search, sortable URL/Hits/First Seen/Last Seen, paginated 20/page, usage bar | `NotFoundPage.php:289-297`, `:329-365`, `:91-96`; `MonitorRepository.php:232-291` | COMPLETE | `MonitorAdminTest::test_render_list_shows_rows_and_controls`, `::test_render_search_without_matches_shows_filtered_empty_state`, `::test_limit_state_thresholds` | None |
| Monitor | Create redirect flow | Row action links to Redirects add screen with source + return prefilled; save reuses Redirects validation | `NotFoundPage.php:468-478`, `:363`; view `not-found.php:216`; `RedirectsPage.php:1703-1709` prefill, `:903-1162` save+validate | COMPLETE | `MonitorAdminTest::test_create_redirect_url_prefills_source`, `::test_create_redirect_url_carries_return`, `::test_render_shows_create_redirect_when_redirects_on`, `::test_render_hides_create_redirect_when_redirects_off` | Same pipeline, no bypass |
| Monitor | Module gating | Disabled = zero frontend hooks, no register/boot work | `MonitorModule.php:128-137` register early-return, `:142-152` boot early-return | COMPLETE | `MonitorModuleTest::test_disabled_module_boots_zero_hooks` (add_action/add_filter/update_option `never`); `RedirectsMonitorGatingTest::test_both_disabled_boot_zero_hooks`, `::test_only_monitor_enabled_registers_capture_only` | Admin submenu still registered unconditionally (`Plugin.php:142`); see gap classification |
| Monitor | Privacy: no IP storage | No IP column and no IP server field read | `LogTable.php:117-139` (schema has no IP); `Logger.php:245-251`, `:411-430` (only `HTTP_REFERER`/`HTTP_USER_AGENT`) | COMPLETE | `MonitorFloodRetentionTest::test_no_ip_address_is_read_or_stored` (sets `REMOTE_ADDR`/`HTTP_X_FORWARDED_FOR`); `MonitorLoggerTest::test_genuine_404_creates_exactly_one_row` asserts exact column set | None |
| Monitor | Referer / user agent | Gated by `advanced_fields` (off by default), truncated to 255, stored on insert and refreshed non-empty on repeat | `Logger.php:245-251`, `:411-430`; `MonitorRepository.php:88-96`, `:110-119`; `MonitorSettings.php:218-220` | COMPLETE | `MonitorLoggerTest::test_advanced_fields_off_by_default`, `::test_advanced_fields_on_captures_and_truncates` | Refresh-on-repeat path has no dedicated test |
| Monitor | Capability + nonce security | Every mutation requires `manage_options` and a per-action nonce | `NotFoundPage.php:485-503`; nonce actions `:49-64`; applied `:543, :564, :585, :621` | COMPLETE | `MonitorAdminTest::test_clear_without_capability_dies`, `::test_clear_with_bad_nonce_dies`, `::test_row_delete_without_capability_dies`, `::test_row_action_with_bad_nonce_dies`, `::test_bulk_without_capability_dies`, `::test_bulk_with_bad_nonce_dies`, `::test_settings_without_capability_dies`, `::test_settings_with_bad_nonce_dies` | None |
| Monitor | SQL preparation | All reads/writes prepared; id lists int-cast; sort columns whitelisted | `MonitorRepository.php:161, 184, 268, 345, 375, 399, 433`; `:249-257` orderby/order allow-list; `:321-352` int cast | COMPLETE | `MonitorRepositoryTest` (all) ; `MonitorTableTest::test_schema_matches_binding_contract` | `deleteMany`/`clearAllBounded`/`deleteOldestOver` interpolate only pre-validated integers |
| Monitor | 404 log CSV export | Export the 404 log | No export action/handler anywhere in `NotFoundPage.php`, views, or Monitor module | MISSING | `ROADMAP.md:163`, `:307`, `:351`; matrix line 261 `PLANNED` | Deferred to Step 1 / 1.5.6, not part of shipped 2.5 |
| Monitor | Bulk 410 from selected rows | Create 410 rules for checked log rows | Bulk handler accepts only `delete` (`NotFoundPage.php:584-615`); select offers only Delete (`not-found.php:162-165`) | MISSING | `ROADMAP.md:164`, `:352`; matrix line 263 `MISSING` | Deferred to Step 1.7.6 |
| Monitor | CSV export safety | Formula injection neutralised if a CSV export exists | N/A for 404 (no export). Redirect CSV does neutralise: `CsvHandler.php:338-368` (`sanitize_cell`/`escape_cell`) | NOT APPLICABLE | No 404 CSV code path exists | N/A until the export is built |

## Privacy review

Per 404 hit the `wp_rankkernel_404_log` row stores exactly:
`id`, `uri_hash` (SHA256 of the normalized URI), `uri` (normalized path, plus the
raw query string only when `ignore_query` is off), `hits`, `referer`, `user_agent`,
`created`, `last_accessed` (`LogTable.php:117-139`; column set asserted by
`MonitorLoggerTest::test_genuine_404_creates_exactly_one_row`).

- **IP address: absent.** No IP column exists and no IP server field is ever read.
  `Logger::serverField()` only ever receives `HTTP_REFERER` and `HTTP_USER_AGENT`
  (`Logger.php:245-251`, `:411-430`). `REMOTE_ADDR` and `HTTP_X_FORWARDED_FOR` are
  never referenced anywhere in the module. Proven by
  `MonitorFloodRetentionTest::test_no_ip_address_is_read_or_stored`.
- **Personal data actually collected:** (1) the requested URI/path — always; this
  can itself contain PII (e.g. `/reset?email=...`), and the query string is stored
  verbatim when `ignore_query` is off (default on drops it, `Logger.php:295-311`);
  (2) referer and user agent — only when `advanced_fields` is enabled
  (`MonitorSettings.php:218-220`), sanitized via `sanitize_text_field` and truncated
  to 255 (`Logger.php:411-430`). User agent is a weak device fingerprint.
- **Storage timing:** referer/UA are written on the insert and refreshed to the
  latest non-empty value on repeat hits (`MonitorRepository.php:88-96`, `:110-119`);
  empty values never overwrite stored ones.
- **Justification:** documented in `ROADMAP.md:118` (table columns include referer,
  user_agent), `404-monitor.md:57-64`, `privacy.md:14-25`, and matrix line 260
  (`404 advanced fields` DONE). It is opt-in and off by default, which is a
  defensible justification.
- `flood` state is per-site and time-windowed, never per visitor (`privacy.md:17`);
  the only option written by it is `rankkernel_404_suppressed` (a timestamp)
  (`FloodGuard.php:90`).

## Pruning and retention correctness

- Cutoff: `Pruner::cutoff()` = site-local `current_time('mysql')` minus
  `retention_days*86400`, re-emitted with `gmdate` (`Pruner.php:109-118`). Both the
  stored `last_accessed`/`created` (`MonitorRepository.php:462-468`) and the cutoff
  derive from `current_time('mysql')`, so the comparison is consistent.
- Age delete: `DELETE ... WHERE last_accessed < %s ORDER BY last_accessed ASC, id ASC LIMIT %d`
  (`MonitorRepository.php:399`). Bounded, oldest first.
- Count delete: `DELETE ... ORDER BY last_accessed ASC, id ASC LIMIT %d`
  (`MonitorRepository.php:433`), batch = `min(min(500, limit), total-max)`
  (`:427-428`), so newer rows are never removed before older ones. The `id ASC`
  tiebreak makes ordering deterministic when timestamps collide.
- Manual clear: `DELETE ... ORDER BY id ASC LIMIT %d` with limit clamped to 500
  (`MonitorRepository.php:375`).
- **Truncation-bug verdict:** RankKernel **avoids** it. `TRUNCATE` appears nowhere
  in `MonitorRepository.php` or `Pruner.php`; every delete carries an explicit
  `LIMIT`; the count prune removes the excess plus a 20% margin, never the whole
  table, and `deleteOldestOver` clamps to `total - maxRows` (`:428`). Proven by
  `MonitorPrunerTest::test_prune_runs_age_then_count_and_never_truncates` and
  `MonitorRepositoryTest::test_clear_all_bounded_loops_without_truncate`.
- **Can pruning delete newer before older?** No. Both prune queries order by
  `last_accessed ASC, id ASC`; a recently hit row has a newer `last_accessed` and is
  preserved (`MonitorFloodRetentionTest::test_pruner_holds_flood_intake_at_maximum_oldest_first`
  asserts `/older-a` is removed and `/flood-4` survives).
- Residual nuance: pruning is only scheduled on a *new* insert
  (`Logger.php:261-270`), so a table that only receives repeat hits on known URIs
  never re-prunes. Growth cannot occur in that state, so the bound still holds; it
  is a schedule characteristic, not a growth leak.

## Redirect integration

**Validation is shared; there is no bypass.** The monitor action is a plain link to
`admin.php?page=rankkernel-redirects&rk_source=<uri>&rk_return=rankkernel-404`
(`NotFoundPage.php:468-478`), rendered only when the Redirects module is enabled
(`NotFoundPage.php:453-455`, view `not-found.php:215-217`). `RedirectsPage::prefillSource()`
reads `rk_source` as a sanitized **display default only** (`RedirectsPage.php:1703-1709`,
used at `:1662`). The actual save is `RedirectsPage::handleFormSave()`
(`RedirectsPage.php:903-1048`), which calls `requireAccess(NONCE_SAVE)`
(`:783-801`, `:904`) then `validateFields()` (`:1102-1162`). That pipeline runs
`Normalizer::normalizeSource` and `Normalizer::isBlockedSource` (`:1129-1137`),
`DestinationValidator::validate` (`:1146`), duplicate-source `lookup` (`:978-987`),
and `Validator::assess_safety()` for equivalence/loop/chain (`:946-976`). This is the
identical code path used by the Redirects admin screen itself, so the monitor flow
inherits every validation and loop guard. No monitor-side write to the redirects
table exists. **Not a P0.**

## Matrix overstatement check

The 404 rows of `feature-parity-matrix.md` are accurate to the code:

- `404 monitor` DONE (line 259) — capture/dedupe/prune/flood/exclusions/admin all
  present and tested.
- `404 advanced fields` DONE (line 260) — implemented opt-in (`Logger.php:245-251`),
  tests exist.
- `404 grouping by hits` DONE (line 262) — implemented as hash dedupe + `hits`
  counter (`MonitorRepository.php:82`, `:112`).
- `404 to redirect workflow` DONE (line 264) — verified shared-pipeline link.
- `404 log export` PLANNED (line 261) and `404 bulk set 410` MISSING (line 263) —
  correctly *not* claimed; both absent, matching the code.
  No 404 row is overstated. **One documentation defect outside the matrix:**
  `docs/architecture/redirects-404-security.md:15,16,29` cites a
  `RedirectsMonitorSecurityTest` that does not exist in `tests/Unit/` (only
  `RedirectsSecurityTest.php` exists); the monitor's capability/nonce coverage lives
  in `MonitorAdminTest` instead.

## Gap classification

- **Hit-count lost update under concurrency — P3.** `MonitorRepository.php:82`/`:133`
  read `hits` then write `existing+1`; two parallel hits can both write the same
  value. It mis-counts a statistic only; rows, privacy, and bounds are unaffected.
- **404 log CSV export — P2 (product gap, not a defect).** Absent from code; tracked
  in `ROADMAP.md:163/307/351` and matrix line 261 as `PLANNED` for Step 1.5.6/1.7.6,
  not part of the shipped 2.5.
- **Bulk 410 from selected rows — P2 (product gap, not a defect).** Bulk handler
  supports delete only (`NotFoundPage.php:606`); tracked in `ROADMAP.md:164/352` and
  matrix line 263 as `MISSING` for Step 1.7.6.
- **Admin 404 submenu registered unconditionally — P3.** `Plugin.php:142` adds the
  submenu outside the module enable gate, so the screen is reachable while the
  module is disabled; the frontend capture hooks remain gated. The screen degrades
  safely (empty list) but the `disabled = no class work` wording in
  `redirects-404-lifecycle.md:6-12` only strictly covers the module lifecycle.
- **Stale test citation in security doc — P3.** `redirects-404-security.md:15,16,29`
  names a non-existent `RedirectsMonitorSecurityTest`.
- No P0 or P1 findings.

## Tests that would be needed

- Concurrency: parallel `record()` calls on the same hash must assert exactly one
  row and a `hits` total equal to the number of calls (would currently fail the
  counter assertion). Use independent PDO connections and the real `hits = hits + 1`
  fix to close the lost update.
- Repeat-hit advanced-field refresh: assert referer/user_agent update to the newest
  non-empty value on an existing row and are left untouched when empty
  (`MonitorRepository.php:88-96` currently untested).
- Pruning under equal `last_accessed`: seed rows with identical timestamps and assert
  the `id ASC` tiebreak gives a deterministic deletion order.
- Real-SQL prune verification: the unit tests use `MonitorFakeDb` (`MonitorFakeDb.php`)
  which re-implements `ORDER BY`/`LIMIT`; an integration test against MySQL should
  confirm `DELETE ... ORDER BY last_accessed ASC, id ASC LIMIT` behaves as assumed.
- 404 CSV export: shape, formula-injection neutralisation, and nonce/capability once
  the feature lands.
- Bulk 410: capability, nonce, per-row validation through the shared redirect
  pipeline, and partial-failure reporting once the feature lands.
