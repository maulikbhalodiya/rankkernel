# RankKernel Handoff: Redirects and 404 Monitor (Phase GH-12)

For the reviewing friend. This covers what was implemented after the approved plan, the current status, what is verified, what is not, and what we need from you next.

Date: 2026-09-12
Branch: `GH-12`, head `cfab2c4`, 15 commits, not pushed, working tree clean
Base: `main` at `2ea6846` (GH-11 already merged)
Plan that was implemented: `docs/architecture/redirects-404-plan.md`
Research behind the plan: `docs/research/redirects-404-research-gate.md`

## 1. What we need from you

Review this phase as a senior WordPress and SEO plugin architect. Verify against the code, not only this summary. Then reply with a next prompt that is precise, ordered, and has acceptance criteria.

Specifically, we want your read on:

1. Whether the plan was implemented faithfully, and where we deviated, whether each deviation is acceptable.
2. Whether the storage, matching, loop and chain, retention, and flood designs are sound for a plugin meant to live for years.
3. Whether the admin UX matches the "professional modern SEO plugin, native WordPress" bar.
4. What should come next: the remaining P1 and P2 items, or a different priority.
5. Any security or performance gap we missed.

Constraints for your prompt: stay on a `GH-<n>` branch, keep the three gates green, all features free, no telemetry, no cron unless justified, no new dependencies without justification, clean room.

## 2. Status at a glance

| Item | Value |
|---|---|
| Tests before | 454 tests, 1856 assertions |
| Tests after | 736 tests, 2708 assertions |
| `composer lint` | clean, exit 0 |
| `composer stan` | level 6, no errors |
| `composer test` | pass |
| Files changed | 72, of which 66 new |
| Diff size | about 18,735 insertions, 12 deletions |
| Live verification | passed on the real LocalWP site (see section 11) |
| Merged | no, awaiting owner review |

## 3. What was built

### Redirect Manager (module id `redirects`, default off, priority 40)

- Full CRUD, active and inactive state, trash style delete.
- Status codes 301, 302, 307, 410, 451. Codes 410 and 451 are terminal and take no destination.
- Match types: exact, prefix, contains, suffix, wildcard, regex. Regex is last and capped.
- Query string behavior: source matching ignores the query, destination preserves the incoming query by default, controlled by a setting.
- Hit counter and last accessed.
- Search, status views, match type and code filters, sorting, pagination, bulk actions.
- CSV import and export, free.
- Automatic slug change redirects for posts and pages, with a disable setting.
- Create Redirect from a 404 row.
- Loop detection at save (cycles blocked) and chain warning (chains saveable).

### 404 Monitor (module id `404`, default off, priority 50)

- Simple deduplicated logging with hit count, first seen, last seen.
- No IP address is read or stored, ever.
- Advanced fields (referer, user agent) are opt in, truncated to 255.
- Exclusions by path and keyword with exact, prefix, contains, suffix, wildcard.
- Ignore query parameters setting.
- Flood protection with a bounded budget of new URIs per time window.
- Retention by age and by maximum row count, oldest first, bounded, never a full truncate.
- Manual Clear Log, independent of automatic pruning, with confirmation, capability, and nonce.
- Near-limit notice at 80 percent and 90 percent thresholds.
- Settings for retention, maximum rows, flood budget and window, advanced fields, and exclusions.

## 4. Plan adherence and deviations

Implemented exactly as the plan specified: the two tables with no cache table, `ensureTables` via `dbDelta` and not `MigrationRunner`, dispatch at `template_redirect` priority 1, 404 capture at priority 99, deterministic matcher precedence with no stored priority, graph loop detection with cycles blocked, chain warnings that never block, no IP, no cron, object cache group `rankkernel-redirects` with positive caching only, coalesced hit counting on shutdown.

Deviations and additions, each small and intentional:

1. `src/Modules/ModuleManager.php` was changed to normalize numeric string module ids to string. PHP casts the array key `'404'` to int, which broke `isEnabled(string)`. This is a real latent bug that the 404 module surfaced. Fix is at four registry loop sites, and all existing tests stay green.
2. Admin list screens use plain native WordPress markup, not `WP_List_Table`, matching the plan's deferral of `WP_List_Table` to the redesign phase.
3. Admin actions use query flag notices (post, redirect, get) rather than transient notices. Small and consistent with the existing RankKernel admin pages.
4. CSV has a per file row cap of 5000 but no global rule cap. The plan mentioned a hard cap on total rules. This is left for owner approval because adding it changes behavior.
5. Regex safety uses caps plus fail closed on `preg_last_error`. The plan floated process wide PCRE `ini_set`; the implementation uses caps and fail closed instead, which is safer for concurrency. This was called out as a deliberate choice.
6. The 404 exclusions editor renders existing rows plus two blank rows, with no dynamic row adder (small JS only, per the plan's no heavy JS stance).

## 5. Architecture implemented

- Module gating: disabled means the module class is never instantiated and registers zero hooks. Proven by tests through `ModuleManager` for both modules, off and on.
- Request flow: WordPress bootstrap, module gate, request normalization, cache lookup, one indexed lookup on a cold miss, matching, loop and chain runtime protection, destination validation, redirect response.
- Redirect dispatch: `template_redirect` priority 1, guarded against admin, AJAX, REST, cron, and sitemap query vars.
- 404 capture: `template_redirect` priority 99, only for genuine frontend 404s.
- Table creation: idempotent `ensureTables` with `dbDelta` and `CREATE TABLE IF NOT EXISTS`, triggered on module enable, guarded by a cheap table exists check that fails open. `MigrationRunner` is deliberately not used for module tables.
- Cache: object cache group `rankkernel-redirects`, positive only, no negative caching, transient fallback, invalidated on every write and module toggle.
- Hit counting: coalesced in memory per request, one `UPDATE` per touched rule on `shutdown`, never before the response.

## 6. Matching and precedence

Deterministic, documented, and tested tier by tier: exact first (one indexed SHA256 lookup on match type plus casefolded path), then prefix (longest first), then wildcard, then contains, then suffix, then regex last, then lowest id as the final tiebreak.

Normalization is a single shared class used at create, lookup, cache key, loop detection, chain detection, CSV import, CSV export, and 404 logging: one leading slash, collapsed duplicate slashes, fragment dropped, trailing slash trimmed except root, empty becomes root, percent encoding canonicalized, Unicode folded to NFC, subdirectory home path stripped, homepage and bare domain blocked as sources. The hash covers match type plus the casefolded path only, so query strings never affect identity.

## 7. Loop and chain behavior

- True loops (including multi hop cycles such as `/a -> /b -> /c -> /a`) are blocked at save. Detection is a graph DFS over active internal rules with a concrete target, max depth 10, max nodes 50, administrator context only. The cycle path is shown in the error notice.
- Chains are allowed and save with a warning that recommends the final destination when it can be determined deterministically.
- Inconclusive analysis (regex or capture targets) is stated plainly and never blocks.
- Runtime: exactly one redirect per request, no internal chain following, plus a reentry guard.

## 8. 404 retention and pruning

Two independent configurable limits: retention days (1 to 365, default 30) and maximum rows (100 to 10000, default 1000). Values cannot be set to zero or unbounded.

Pruning runs on shutdown after an insert and opportunistically on the admin view: age pass bounded to 500 rows, count pass bounded to the excess plus a 20 percent margin capped at 500, oldest first by `last_accessed` then `id`. Never `TRUNCATE`, never an unbounded delete.

## 9. Manual clear and near-limit UX

- Clear Log loops the bounded deletion path with confirmation, capability, and nonce, and does not touch redirect rules.
- Usage is shown as `current / maximum` with a bar.
- Near-limit states: below 80 percent normal, 80 to below 90 percent informational, 90 percent and above stronger warning. Wording explains that oldest entries prune automatically and that manual clear is always available. The word database is never used to describe the limit.

## 10. Data model

`wp_rankkernel_redirects`: id, match_type enum, source_hash CHAR(64), source TEXT, target TEXT, code enum, hits, is_active, created, last_accessed. UNIQUE `(match_type, source_hash)`, KEY `(is_active)`.

`wp_rankkernel_404_log`: id, uri_hash CHAR(64), uri TEXT, hits, referer, user_agent, created, last_accessed. UNIQUE `(uri_hash)`, KEY `(last_accessed)`.

Settings options (autoload off): `rankkernel_redirects_settings`, `rankkernel_404_settings`. No redirect cache table. No cron. Uninstall already purges `rankkernel_` options and `wp_rankkernel_` tables by prefix, and a test proves the naming contract and the purge.

## 11. Live verification (real site, not only unit tests)

Executed against the running LocalWP site with the modules enabled:

- Created an exact rule `/rk-smoke-old -> /rk-smoke-new` (301). `GET /rk-smoke-old` returned `301` with `Location: http://localhost:10043/rk-smoke-new`.
- Hit counter: after repeated requests the row showed `hits=4` with `last_accessed` set, proving the shutdown write.
- 404 logging: `GET /rk-smoke-missing-page` returned `404` and created a log row `uri=/rk-smoke-missing-page hits=1`.
- Dedupe: three further requests to the same missing URL kept exactly one row with `hits=3`.
- Cleanup: test rows removed, both tables left empty.

Not yet verified live: the admin screens were tested by unit tests and PRG tests, not clicked through in a browser. Visual QA of the Redirects and 404 screens is pending.

## 12. Security

- Capability `manage_options` and per action nonces on every mutation across both screens (row, bulk, settings, import, export, clear).
- Sanitization on input, escaping on output, prepared statements only.
- Destination scheme allowlist, rejecting `javascript:`, `data:`, `vbscript:`, `file:`, CRLF, and control characters, at save and at send.
- Regex safety by rule count cap, pattern length cap, anchored wrapping, and fail closed on `preg_last_error`.
- CSV formula injection neutralized on export and sanitized on import.
- No IP storage. Referer and user agent are opt in and truncated.
- Flood protection on the 404 log with a bounded new URI budget.

## 13. Tests added

New coverage includes: normalizer rules, every matcher and the full precedence order, repository CRUD and pagination, loop detection (direct, two hop, three hop, longer, inactive ignored, pattern inconclusive, depth and node caps), chain detection (one, two, three, long, external terminal, inconclusive), destination validation, dispatcher query counts (cache hit zero rule queries, cold miss one), cache invalidation, hit coalescing, settings sanitize and clamping, CSV contract and per row validation and round trip, slug watcher, 404 capture and every exclusion, dedupe and hit increment, age and count pruning and oldest first, manual clear, flood budget, near-limit states, module gating for both modules, uninstall purge, and capability and nonce failures on every mutating action.

## 14. Documentation created

`docs/architecture/redirects.md`, `redirects-matching.md`, `redirects-safety.md`, `redirects-queries.md`, `redirects-csv.md`, `404-monitor.md`, `404-retention.md`, `redirects-404-security.md`, `redirects-404-lifecycle.md`, `performance-claims.md`, and `docs/privacy.md`.

## 15. Remaining limitations and decisions needing owner approval

1. CSV still has no global rule cap (per file cap of 5000 only). Adding one changes behavior, awaiting approval.
2. Admin CSV export uses an unbounded `SELECT` on an admin initiated action.
3. Slug watcher covers posts and pages only. Taxonomies, CPTs, attachments, and delete or trash suggestions are not yet handled.
4. 404 exclusions editor has no dynamic row adder, and the list page size is fixed at 20.
5. Advanced 404 fields are P1 and shipped as opt in; 404 log export is not built.
6. Multisite is per site only. No network wide management.
7. Browser and visual QA of the two admin screens is pending.
8. The `ModuleManager` numeric id fix should be reviewed as part of this phase even though it is small.

## 16. Suggested next investigations for you to weigh

1. A browser and visual QA pass on both admin screens, then polish.
2. The remaining P1 and P2 items: 404 log export, scheduled activation and expiration (cron free), per rule query matching, ignore case.
3. Slug watcher expansion to taxonomies and CPTs, and delete or trash suggestions.
4. A global CSV rule cap and an import dry run mode.
5. A performance benchmark on the 30k post dataset with many rules, to validate the pattern set cap.
6. Whether to adopt `WP_List_Table` and the branded admin redesign for these two screens.

## 17. Standing rules for your next prompt

Stay on an issue branch, keep `composer lint`, `composer stan`, and `composer test` green, report exact test numbers, keep every feature free, no telemetry, no upsell, no cron unless justified, no new dependency without justification, clean room (behavior only, never competitor code), WordPress standards with real tab indentation for all new code, and do not bypass the approved architecture (no redirect cache table, no `MigrationRunner` for module tables, no IP storage, no server file sync, no rule categories, no multi source rows, no REST in this phase).

## 18. File map for review

Redirects backend: `src/Modules/Redirects/` with Normalizer, RedirectTable, RedirectsSettings, RedirectCache, Matcher, Validator, DestinationValidator, RedirectRepository, HitCounter, CsvHandler, SlugWatcher, Redirector, RedirectsModule.
404 backend: `src/Modules/Monitor/` with MonitorModule, LogTable, MonitorSettings, Exclusions, MonitorRepository, Logger, FloodGuard, Pruner.
Admin: `src/Admin/RedirectsPage.php`, `src/Admin/NotFoundPage.php`, `assets/css/redirects-admin.css`, `assets/css/monitor-admin.css`, `assets/js/redirects-admin.js`, `assets/js/monitor-admin.js`.
Integration: `src/Plugin.php`, `src/Admin/AdminMenu.php`, `src/Modules/ModuleManager.php`, `phpcs.xml`, `docs/coding-standards.md`.
Tests: `tests/Unit/Redirects*`, `tests/Unit/Monitor*`, plus the combined gating and uninstall tests.
