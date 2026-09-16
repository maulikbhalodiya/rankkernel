# RankKernel Redirects and 404 Monitor: Architecture and Implementation Plan

Status: planning gate only. No implementation, no migrations, no tables, no branches. Awaiting owner approval.
Date: 2026-09-11. Baseline: main at `2ea6846`, 454 tests, 1856 assertions, lint and stan green.
Inputs: `docs/research/redirects-404-research-gate.md`, `docs/architecture/blueprint.md` (§D.4, §D.5, §G), `ROADMAP.md` (2.4, 2.5), current RankKernel source, and an independent architecture review.
Evidence tags: `[VERIFIED]` checked against source or WordPress behavior, `[DOCS]` official documentation, `[SOURCE]` public source inspected, `[INFERRED]` reasoned, `[PROPOSED]` RankKernel design.

---

# Executive Decision

The research is ready for implementation **with corrections**. Five research and blueprint assumptions must change before coding begins, or the module will inherit known bugs and complexity:

1. Drop the `wp_rankkernel_redirects_cache` table. It adds writes, invalidation, and a second lookup for no net saving over an object cache plus transient fallback.
2. Do not create module tables through `MigrationRunner`. Use an explicit enable time table creation path with `dbDelta` and an idempotent guard, because the runner is a single linear ledger that a later module enable can never re-run.
3. Do not match on `init` or `wp`. Dispatch on `template_redirect` priority 1, and log 404s on `template_redirect` priority 99.
4. Replace the `regex` boolean column with a `match_type` enum, because the matcher set is now exact, prefix, wildcard, and regex.
5. Clarify the runtime safety promise: RankKernel sends exactly one redirect per request and never follows chains internally. The real risk is a browser bounce loop, not a PHP infinite loop.

Everything else in the research holds and is strengthened. The verdict is: proceed to a build prompt after owner approval, using this plan as the binding specification.

---

# Corrections to Existing Research

| Item | Earlier position | Correction | Evidence |
|---|---|---|---|
| Redirect cache table | `wp_rankkernel_redirects_cache` (blueprint §D.4, ROADMAP 2.4, research §15) | Drop it. Object cache group plus transient fallback for positive hits only. No negative caching (negative caching recreates the Rank Math flood write bug). | `[PROPOSED]`, review |
| Table creation | Via `MigrationRunner` ledger | Per module `ensureTables` with `dbDelta` and `CREATE TABLE IF NOT EXISTS`, called on module enable and guarded at runtime. `MigrationRunner` serializes a single linear ledger and cannot re-run for a module enabled later. | `[VERIFIED]` `MigrationRunner::maybeRun()` skips versions at or below the ledger |
| Match hook | `template_redirect` priority 1 (research agreed) | Correct for dispatch. Add 404 capture at `template_redirect` priority 99 so only genuine 404s are logged after canonical and after our dispatch. Reject any `init` or `wp` matching. | `[VERIFIED]` WordPress runs `redirect_canonical` at `template_redirect` priority 10 |
| Runtime guard wording | "one hop then stop" | Means exactly one redirect response per request, no internal chain following. Clarified to avoid an implementer building a server side chain follower. | `[PROPOSED]` |
| Regex column | `regex` tinyint | `match_type` enum of `exact`, `prefix`, `wildcard`, `regex`. Cleaner uniqueness and precedence. | `[PROPOSED]` |
| CSV wording | Blueprint said Rank Math free CSV is export only | Rank Math CSV import and export is premium. Correct blueprint and feature matrix wording. Free CSV stays a RankKernel differentiator. | `[DOCS]` |
| Multiple sources per rule | Listed as a capability | Excluded. Separate rules express many to one without a serialized blob. | `[PROPOSED]` |
| Advanced 404 fields | Implied P0 | Referer and user agent are P1, opt in, truncated, no IP ever. | `[PROPOSED]` |
| Contains and suffix matchers | Listed as P0 | Kept in scope but flagged as the first candidates to defer if the v1 matcher surface must shrink. Core v1 is exact, prefix, wildcard, capped regex. | `[PROPOSED]` |

---

# Verified Competitor Parity

Every row separates competitor fact from RankKernel decision. Competitor columns are documentation or source verified as marked in the research report. RankKernel decision uses KEEP, IMPROVE, CHANGE, DROP, FUTURE.

| Capability | Rank Math Free | Rank Math Premium | Yoast Free | Yoast Premium | RankKernel Decision |
|---|---|---|---|---|---|
| Manual redirect CRUD | Yes `[SOURCE]` | Yes | No | Yes `[DOCS]` | KEEP, free |
| Enable and disable | Yes | Yes | No | Yes | KEEP |
| Codes 301, 302, 307, 410, 451 | Yes | Yes | No | Yes | KEEP |
| Exact match | Yes | Yes | No | Yes | KEEP |
| Prefix and contains and suffix | Yes | Yes | No | Not documented | KEEP, safe string match |
| Wildcard | Via regex | Via regex | No | Via regex | IMPROVE, dedicated glob |
| Regex | Yes | Yes | No | Yes | IMPROVE, hard capped |
| Ignore case | Yes | Yes | No | Not documented | FUTURE |
| Multiple sources per row | Yes, serialized | Yes | No | Not documented | DROP, use separate rules |
| Query string preserve | Yes, filterable | Yes | No | Partial | CHANGE, explicit setting |
| Scheduled activation and expiration | No | Yes | No | Not documented | FUTURE, cron free evaluation |
| Rule categories | No | Yes | No | Not documented | DROP |
| Hit counter and last accessed | Yes | Yes | No | Not documented | IMPROVE, batched writes |
| Search and sort and pagination | Yes | Yes | No | Yes | KEEP |
| Bulk enable and disable and delete | Yes | Yes | No | Delete only | KEEP |
| CSV import | No | Yes | No | Yes | KEEP, free |
| CSV export | No | Yes | No | Yes | KEEP, free |
| Import from rivals | Yes | Yes | No | Yes | FUTURE |
| Auto slug change redirect | Yes | Yes | No | Yes | KEEP, free, disable setting |
| Auto trash and delete redirect | Notice | Notice | No | Yes | FUTURE, suggestion |
| Editor integration | Yes | Yes | No | Yes | FUTURE |
| 404 to redirect per row | Yes | Yes | No | Admin bar | KEEP, free |
| 404 bulk redirect | Yes, one target | Yes | No | Not documented | CHANGE, one target |
| 404 simple log | Yes | Yes | No | No log | KEEP |
| 404 advanced fields | Yes | Yes | No | No log | CHANGE, opt in, no IP |
| 404 log export | No | Yes | No | No | FUTURE |
| Server file sync | No | Yes | No | Yes | DROP |
| Loop detection | Exact, save time | Exact, save time | No | Not documented | IMPROVE, graph DFS |
| Chain warning | No | No | No | Warning on move | IMPROVE, explicit |
| Storage | Two custom tables | Two custom tables | None | Autoloaded options | CHANGE, two tables, no cache table |
| No IP in 404 log | Yes | Yes | Not applicable | Not applicable | KEEP |

---

# Final Scope

## P0 (baseline, first release)

- Redirects module, default off, request dispatch at `template_redirect` priority 1.
- Match types: exact, prefix, wildcard, and capped regex. Contains and suffix included if the matcher surface permits, else first to defer.
- Status codes 301, 302, 307, 410, 451.
- Destination validation and safe redirect sending.
- Admin CRUD, enable and disable, search, status views, sorting, pagination, bulk actions.
- Loop detection at save (cycles blocked) and chain warning (chains saveable).
- Query string policy: source matching ignores query, destination preserves incoming query by default with a global setting.
- Hit counter and last accessed with batched writes.
- Slug change watcher with a disable setting.
- CSV import and export, free.
- 404 Monitor module, default off, capture at `template_redirect` priority 99.
- 404 simple log with atomic dedupe, no IP.
- 404 retention by age and by count, oldest first, bounded batches, never truncate.
- 404 manual clear with confirmation, capability, and nonce.
- 404 near-limit notice.
- 404 exclusions by path and keyword.
- 404 flood protection with a bounded new URI budget.
- Redirect creation from a 404 row.
- Security: capabilities, nonces, prepared statements, escaping, safe schemes, no CRLF.
- Zero hooks when the module is off, proven by test.

## P1

- 404 advanced fields (referer, user agent), opt in, truncated to 255, no IP.
- 404 bulk redirect with one shared destination.
- Configurable retention days and maximum rows.
- 404 log export.
- Runtime reentry guard with an administrator only diagnostic action.

## P2

- Ignore case toggle per rule.
- Per rule query matching.
- Scheduled activation and expiration evaluated at match time, no cron.
- 404 grouping and basic trend hints.

## Future

- Redirect from the editor.
- Import from Yoast and Rank Math.
- Taxonomy term change auto redirect.
- Multisite network management.
- WP-CLI commands.
- Debug interstitial.

## Explicitly excluded

- Server file sync to `.htaccess` or Nginx. Security and multisite hazard, and it bypasses WordPress.
- Rule categories. Administrative overhead, no SEO value.
- Multiple source URLs in one row. Serialized blobs are the anti pattern we avoid.
- WooCommerce specific behavior. Belongs to a later commerce phase.
- REST endpoints for redirects or 404 in v1. The admin POST pattern is sufficient.
- Autoloaded option storage for rules.
- Full table truncate on any limit.
- IP address storage by default.
- Cron based expiry or pruning.
- Regex first matching.
- Negative result caching.

---

# Redirect Architecture

## Request lifecycle `[PROPOSED]`

```text
Incoming request
        |
WordPress bootstrap
        |
RankKernel module gate (disabled means no class, no hooks)
        |
Redirects module boot registers template_redirect priority 1
        |
template_redirect priority 1 callback
        |
guards: is_admin, wp_doing_ajax, REST, cron, sitemap query vars
        |
request normalization (single normalizer)
        |
cache lookup (object cache, then transient fallback)
        |
on miss: one indexed rule lookup by match rank
        |
matching in fixed order
        |
cycle guard and reentry guard
        |
destination validation
        |
redirect response, then exit
        |
shutdown: coalesced hit counter flush
```

Hook justification `[VERIFIED]`:
- `template_redirect` priority 1 runs after the main query is parsed (conditionals available) and before `redirect_canonical` at priority 10, so RankKernel wins intentionally.
- Admin, AJAX, REST, and cron are excluded by guards; `is_admin()` and `wp_doing_ajax()` are reliable here.
- The sitemap Router uses `pre_get_posts` and a scoped `redirect_canonical` disable. The redirector must skip any request carrying `rankkernel_sitemap` query vars, so the two never fight.
- `init` and `wp` matching is rejected: `init` has no conditionals and would duplicate normalization logic, and `wp` matches on every request like Rank Math, which is the cost we are avoiding.
- Page cache plugins can serve before WordPress runs. Mitigation is documentation plus `Cache-Control: no-cache` on redirect responses, not a hook change.

## 404 capture placement

- Capture at `template_redirect` priority 99, after `redirect_canonical` and after the redirector at priority 1. Only genuine 404s are logged. A request that the redirector resolves never reaches the logger.

---

# Matching Architecture

## Matchers `[PROPOSED]`

Matching runs in a fixed order per request. The order is the precedence and the performance strategy.

1. Exact. Hash equality on `(match_type, source_hash)`. One indexed lookup.
2. Prefix. Longest prefix first. String `str_starts_with` on normalized path.
3. Wildcard. Single glob, translated to a bounded string or small regex internally, never user supplied regex.
4. Contains and suffix. String `strpos` and `str_ends_with`.
5. Regex. Last. Capped and guarded.

For each matcher the plan specifies:

| Matcher | Input | Normalization | Comparison | Query behavior | Case | Performance | Index | Security |
|---|---|---|---|---|---|---|---|---|
| exact | normalized path | full normalizer | hash equality | query ignored for match | sensitive unless ignore case | one indexed lookup | unique index | none |
| prefix | normalized path | full normalizer | longest prefix wins | query ignored | sensitive | in memory over candidate set | none, small set | none |
| wildcard | normalized path | full normalizer | glob match | query ignored | sensitive | in memory, bounded | none, small set | glob only, no regex |
| contains | normalized path | full normalizer | substring | query ignored | sensitive | in memory | none | none |
| suffix | normalized path | full normalizer | endswith | query ignored | sensitive | in memory | none | none |
| regex | normalized path | full normalizer | anchored PCRE | query ignored | flags per rule | last, capped count, fail closed | none | hard caps, ReDoS boundary |

Candidate loading: exact resolves with one indexed query. Pattern rules (prefix, wildcard, contains, suffix, regex) load as a small cached list. The rule count cap keeps this bounded. This avoids `LIKE` on serialized data and avoids full scans on the hot path.

## Normalization `[PROPOSED]`

A single shared normalizer is the only path to hashing. It is used at create, lookup, cache key, loop detection, chain detection, CSV import, CSV export, and 404 logging.

Rules:
- Enforce a single leading slash.
- Collapse duplicate slashes.
- Strip the fragment entirely.
- Trim the trailing slash except for the root path `/`.
- Empty path becomes `/`.
- Homepage and bare domain are blocked as redirect sources to prevent lockout.
- Strip the subdirectory home path for subdirectory installs, derived from `home_url` parsing.
- Percent encoding: canonicalize uppercase hex, decode unreserved characters, keep reserved characters encoded.
- Unicode: store UTF-8 as NFC, hash over the raw bytes.
- Case: case sensitive by default, lowercased only when the per rule ignore case flag is set (Future).

Canonical source representation and hash `[PROPOSED]`:
- Stored canonical source is the normalized path.
- Hash is SHA256 hex over `match_type + "|" + casefolded_path`.
- Query is excluded from the hash in v1, matching the default "source query ignored" semantics.
- `match_type` is folded into the hash so identical paths with different matchers are distinct rows.

## Conflicts and precedence `[PROPOSED]`

- Hard uniqueness: `UNIQUE (match_type, source_hash)` makes identical normalized duplicates impossible, even under a race.
- Overlapping patterns are allowed and produce a warning only.
- No stored priority or order column. Precedence is deterministic by specificity: exact, then prefix (longest first), then wildcard, then contains, then suffix, then regex, then lowest id as the final tiebreak.
- Duplicate source detection is enforced by the database, not only by application checks.

---

# Loop Detection Architecture

## Definitions

- A cycle is a path from a rule back to itself, for example `/a -> /b -> /a`. Cycles are blocked at save.
- A chain is a path that ends elsewhere, for example `/a -> /b -> /c`. Chains save with a warning.

## Algorithm `[PROPOSED]`

Save time, administrator context only:

1. Build the proposed rule in memory (do not persist yet).
2. Collect active internal rules that have a concrete target (exclude 410, 451, external, inactive).
3. Depth first search from the proposed rule: at each step, test the current target path against every other rule matcher.
4. Edge R1 to R2 exists when `matches(R2.source, normalize(R1.target_path))` is true.
5. If traversal returns to the proposed rule, a cycle exists: reject the save and render the cycle path.
6. If a target is dynamic (regex or wildcard capture such as `$1`) or external, mark the branch inconclusive and stop that branch.

Limits `[PROPOSED]`:
- Maximum traversal depth 10.
- Maximum nodes visited 50.
- Traversal is bounded, administrator only, and never runs on the frontend, so it cannot be attacker controlled.

Notice examples:

```text
This redirect would create a redirect loop:
/a -> /b -> /c -> /a
The rule was not saved.
```

```text
This redirect may loop through pattern rule #12.
Verify it manually. The rule was saved.
```

Only conclusive cycles block. Inconclusive branches warn and allow save.

---

# Chain Detection Architecture

## Model `[PROPOSED]`

Same traversal, maximum 5 hops, for warnings. Chains are never blocked.

- Traversal terminates at 410, 451, external destinations, and inactive rules.
- Query strings are ignored during traversal (path only) to stay conservative.
- Inconclusive analysis is stated explicitly and never blocks save.

Warnings are advisory:

```text
Redirect chain detected: /a -> /b -> /c
Consider redirecting /a directly to /c.
[Save anyway]
```

```text
Chain analysis could not determine the final destination because the next
rule uses a pattern matcher. Saving as entered.
```

Deterministic chains produce a direct recommendation. Pattern, capture, or external branches are labeled inconclusive.

---

# Runtime Safety Architecture

Real risk clarification `[VERIFIED]`:
- Each redirect ends the request with a response sent to the browser. RankKernel does not follow chains server side, so there is no internal PHP infinite loop and no unbounded query cost.
- The real risk is a browser bounce loop across requests (`A -> B -> A`), typically from a slug watcher plus redirector interaction, or from imported rules.

Guard `[PROPOSED]`:
- Send exactly one redirect per request, then exit.
- A same request reentry flag aborts a second send attempt and fires an administrator only diagnostic action `rankkernel/redirect/reentry`.
- Optional runtime visited token via a short lived cookie is a P1 diagnostic, not the default, so normal traffic stays cookie free.
- Cost bound: O(number of rules evaluated) matcher checks, with exact resolving in one indexed lookup, plus one coalesced counter update at shutdown.

---

# Status Codes

| Code | Type | Destination required | Hit counter | Traversal | Headers |
|---|---|---|---|---|---|
| 301 | permanent redirect | yes | yes | has outgoing edge | `Location`, cacheable |
| 302 | temporary redirect | yes | yes | has outgoing edge | `Location`, no cache |
| 307 | temporary redirect, method preserved | yes | yes | has outgoing edge | `Location`, no cache |
| 410 | terminal gone | no | yes | terminal, no outgoing edge | status only, no `Location` |
| 451 | terminal legal | no | yes | terminal, no outgoing edge | status only, no `Location` |

WordPress APIs `[VERIFIED]`: use `wp_redirect` with a validated destination for 301, 302, 307, and `status_header` plus a minimal response for 410 and 451. Validate internal destinations with the safe redirect rules and external destinations against an allowlist. Send `Cache-Control: no-cache` for temporary codes and for 410 and 451.

---

# Storage Architecture

Custom tables are necessary `[VERIFIED]`: options autoload bloat (the Yoast incident), postmeta does not scale for cardinality and range queries, and a serialized rule blob breaks indexed lookup. This is not copying a competitor, it is the correct fit.

Final shape: two tables, not three.

## `wp_rankkernel_redirects`

| Column | Type | Null | Default | Purpose | Index |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | no | | primary key | PRIMARY |
| match_type | ENUM('exact','prefix','contains','suffix','wildcard','regex') | no | 'exact' | matcher | part of UNIQUE |
| source_hash | CHAR(64) | no | | SHA256 of matcher and normalized path | part of UNIQUE |
| source | TEXT | no | | normalized source for display and rehash | |
| target | TEXT | no | | destination | |
| code | ENUM('301','302','307','410','451') | no | '301' | status | |
| hits | BIGINT UNSIGNED | no | 0 | counter | |
| is_active | TINYINT(1) | no | 1 | enabled flag | KEY |
| created | DATETIME | no | | created | |
| last_accessed | DATETIME | yes | null | last hit | |

Indexes: `UNIQUE (match_type, source_hash)`, `KEY (is_active)`. High read frequency on exact lookup, low write frequency, bounded cardinality by the rule cap. Regex pattern count is capped (see security).

## `wp_rankkernel_404_log`

| Column | Type | Null | Default | Purpose | Index |
|---|---|---|---|---|---|
| id | BIGINT UNSIGNED AUTO_INCREMENT | no | | primary key | PRIMARY |
| uri_hash | CHAR(64) | no | | SHA256 of normalized URI | UNIQUE |
| uri | TEXT | no | | normalized URI | |
| hits | BIGINT UNSIGNED | no | 0 | counter | |
| referer | VARCHAR(255) | no | '' | advanced, opt in | |
| user_agent | VARCHAR(255) | no | '' | advanced, opt in | |
| created | DATETIME | no | | first seen | |
| last_accessed | DATETIME | no | | last seen | KEY |

Indexes: `UNIQUE (uri_hash)` for atomic dedupe, `KEY (last_accessed)` for oldest first pruning. The `last_accessed` index is required or every prune becomes a full scan.

Dropped: `wp_rankkernel_redirects_cache`. Correction to blueprint §D.4 and ROADMAP 2.4.

---

# Cache Architecture

`[PROPOSED]` Object cache group `rankkernel-redirects`, separate from the sitemap group so a redirect save never flushes sitemaps.

- Positive caching only: cache the resolved match for a normalized request path hash.
- No negative caching. Negative caching recreates the Rank Math flood write bug and unbounded growth.
- With an external object cache: entries live in the object cache with a bounded TTL.
- Without an object cache: a transient fallback with a bounded TTL, plus a small in memory map for the request.
- Invalidation: on rule create, update, delete, and bulk action, and on module enable and disable, flush the group. A stored validator key can be used to confirm freshness cheaply.
- Cold miss path: exactly one indexed query against `wp_rankkernel_redirects`, then warm.

---

# Hit Statistics Architecture

`[PROPOSED]` No synchronous write before the redirect.

- Coalesce increments in memory per request.
- Flush once on the `shutdown` action with a single `UPDATE hits = hits + N, last_accessed = NOW` per touched rule.
- With an object cache, increment the cached counter synchronously and flush the same way at shutdown.
- Without an object cache, the shutdown `UPDATE` is the entire mechanism.
- Correctness: counts are exact in the normal case and lose at most the in flight increment on a hard kill. That is acceptable for statistics and is documented.
- The redirect response itself never waits on a statistics write.

---

# Slug Change Watcher

`[VERIFIED]` WordPress exposes the old slug in `$post->post_name` before update and the new slug after, on `post_updated`, and via `wp_insert_post_data`.

Design `[PROPOSED]`:
- Hook `post_updated` for posts and pages first (P0), with `edit_terms` and `edited_term` for taxonomies at Future.
- Compare the stored slug with the new slug. Only create a redirect when the slug actually changed.
- Create a 301 from the old permalink path to the new permalink path.
- Setting `auto_slug_redirect` controls it, default on when the module is on, and it can be disabled.
- Do not create a redirect for revisions, autosaves, or imports. Ignore `wp_is_post_revision` and `wp_is_post_autosave`.
- Do not create duplicates: use the `UNIQUE (match_type, source_hash)` constraint and skip when the source is already the homepage or already redirected.
- Do not create loops: run the same loop detection used by the manual save path.
- Trashed and restored content: at Future, offer a suggestion notice rather than an automatic redirect.
- Custom post types and hierarchical post types: supported through the same permalink comparison, verified by test.

---

# CSV Import and Export

Free, and a differentiator. A single documented contract is used for both directions.

Columns:
- `source` (required), full or relative source URL as entered, normalized on import.
- `target` (required unless code is 410 or 451), destination URL.
- `code` (optional, default 301), one of 301, 302, 307, 410, 451.
- `match_type` (optional, default exact), one of exact, prefix, contains, suffix, wildcard, regex.
- `active` (optional, default yes), `yes` or `no`.
- `hits` (export only, ignored on import).
- `last_accessed` (export only, ignored on import).

Contract rules:
- UTF-8, optional BOM tolerated on read and not emitted on write.
- Unix or Windows line endings tolerated on read, Unix on write.
- Comma delimiter, double quote wrapping, doubled quotes for escapes.
- Size limit and row limit enforced, with a hard cap on total rules.
- Every imported row passes the same validation as the admin form: normalization, scheme allowlist, regex compile test, loop detection, chain warning, duplicate check.
- Duplicate handling: identical `(match_type, source_hash)` updates the existing row (documented) or is skipped (configurable), never inserted twice.
- Conflict handling: overlapping patterns produce a warning report, not a hard failure.
- Malformed rows, invalid URLs, invalid regex, and invalid status are rejected per row and reported, never fatal.
- Transaction strategy: process in bounded batches, record a per row result, and never leave a partial duplicate. Validation happens before insert.
- Error report: a summary of created, updated, and skipped counts plus a per row error list.
- CSV formula injection defense: prefix dangerous leading characters in exported free text and sanitize on import.

Export produces the same columns with a stable order and a header row.

---

# 404 Architecture

## Capture

`[PROPOSED]` Capture on `template_redirect` priority 99 when `is_404()` is true.

Exclude:
- `is_admin()`, `wp_doing_ajax()`, REST requests (`defined('REST_REQUEST')`), and `wp_doing_cron()`.
- Sitemap requests (any `rankkernel_sitemap` query var) and RankKernel generated 404 responses.
- Requests whose response code is 410 or 451.
- Static asset requests by extension (images, css, js, fonts, and common probes) and by exclude rules.
- Bot and probe noise through exclude rules and the flood budget, not by IP.

## Deduplication

`[PROPOSED]` Atomic upsert on `uri_hash`:
- First request inserts a row with `hits = 1`.
- Repeat requests run `UPDATE ... SET hits = hits + 1, last_accessed = NOW`.
- Collapse repeat writes within a request window to reduce amplification: one increment per URI per request.

## Flood protection

`[PROPOSED]` No IP, no cron.
- A budget of 50 new URIs per 5 minute window per site, tracked in the object cache with a transient fallback.
- When the budget is exhausted: existing URIs keep incrementing, new URIs are suppressed, and a `rankkernel_404_suppressed` marker is set for an administrator notice.
- The budget excludes the categories above so it is spent only on real content 404s.
- The cap is configurable.

## Advanced fields

P1, opt in. Referer and user agent are truncated to 255 and stored only when the admin enables advanced logging. No IP is ever stored.

---

# Retention and Growth Architecture

Two independent, configurable limits:
- Retention age, default 30 days.
- Maximum rows, default 1000.

Pruning `[PROPOSED]`:
- Runs on `shutdown` after a 404 insert, and opportunistically on admin list view.
- Age rule: delete rows older than the retention cutoff, bounded to 500 rows per pass.
- Count rule: when over the maximum, delete the oldest rows, bounded to the excess plus a margin (20 percent of the maximum) capped at 500 per pass.
- Never `TRUNCATE`. Never unbounded `DELETE`. The batch plus margin amortizes cost so a sustained flood does one bounded delete per request instead of re pruning the full excess every time.
- Deterministic: oldest first by `last_accessed`, then `id`.

Manual clear `[PROPOSED]`:
- A Clear 404 Log action with a confirmation, capability check, and nonce.
- Uses the same bounded deletion path as pruning, looped until cleared or a hard pass cap is hit.
- Redirects back to the admin screen with a success notice.
- Never a raw `TRUNCATE`.

Near-limit notices `[PROPOSED]`:
- Below 80 percent: normal.
- 80 percent to below 90 percent: informational warning.
- 90 percent and above: stronger warning.
- Wording avoids "database full": "Your 404 log is approaching its configured entry limit. Oldest entries are automatically removed when the limit is reached."
- Shown above the list and on the settings screen.

Retention settings UX `[PROPOSED]`:
- Retention period in days, minimum 1, maximum 365, default 30.
- Maximum entries, minimum 100, maximum 10000, default 1000.
- Current usage shown as a count and a bar: `742 / 1000`.
- Zero or empty is not allowed for either value, because it would allow uncontrolled growth. Validation clamps to the allowed range.
- Capability `manage_options`, nonce per page, save through the existing SettingsStore pattern.

---

# 404 to Redirect

`[PROPOSED]`:
- A per row Create Redirect action prefilled with the broken URI as the source.
- Destination entered by the admin.
- The new rule passes the full redirect validation: normalization, scheme allowlist, loop detection, chain warning.
- Dependency: the action requires the Redirects module. When Redirects is off, the action is hidden and a notice explains that enabling Redirects is required.

---

# Database Growth Model

Ranges `[INFERRED]` from realistic usage and the 30k post LocalWP dataset.

| Store | Small site | Medium | Large | Notes |
|---|---|---|---|---|
| redirects rules | 10 to 100 | 100 to 1000 | 1000 to 10000 | capped; rows are small |
| redirects pattern set in memory | 0 to 20 | 20 to 100 | 100 to 500 | pattern rules only, bounded by regex cap |
| 404 log rows | up to 1000 | up to 1000 | up to 10000 | capped by setting, pruned oldest first |
| object cache entries | bounded by rule and path TTL | bounded | bounded | positive only |

Average 404 row size is small (URI text plus counters plus two optional 255 fields). Redirect rows are small. Indexes are narrow (a 64 char hash plus an enum plus a flag).

Scaling behavior:
- 100 to 1000 redirects: exact lookups are constant time via the unique index. Fine.
- 10000 redirects: exact lookups still constant time. Pattern rules must stay within the pattern cap. Fine with the cap.
- 100000 redirects: still constant time for exact, but the pattern set and admin list need pagination and the regex cap must be enforced. Acceptable but the admin list must paginate and search by hash. Flagged.
- 404 log is always bounded by the maximum rows setting, so it never grows without limit.

The design stays safe on the 30k post dataset because the hot path is a single indexed lookup, the pattern set is capped, and the 404 log is bounded.

---

# Performance Model

| Path | Queries | Writes | Notes |
|---|---|---|---|
| module disabled | 0 | 0 | no class, no hooks |
| cache hit | 0 rule queries | 0 before response | object cache or transient hit |
| cold miss, exact | 1 indexed lookup | 0 before response | warm cache after |
| pattern match | 1 cached pattern list read | 0 before response | bounded by cap |
| 404 request | 1 dedupe lookup | 1 insert or 1 increment at shutdown | pruning at shutdown, bounded |
| 404 dedupe hit | 1 update | 1 bounded update at shutdown | no insert |
| admin request | normal admin queries | per action | no frontend hooks |
| redirect request | as above plus 1 coalesced counter update at shutdown | 1 update at shutdown | never before the response |

Honest claim: zero cost when the module is off, cache first when on, at most one targeted query on a cold miss, one coalesced counter write at shutdown.

---

# Security Architecture

| Threat | Mitigation | Test |
|---|---|---|
| Open redirect | validated destinations, safe redirect semantics, external allowlist | yes |
| Unsafe schemes (`javascript:`, `data:`, `file:`, `vbscript:`) | scheme allowlist via WordPress allowed protocols, reject at save and at send | yes |
| Malformed URLs | parse and normalize, reject on failure | yes |
| CRLF and control characters in destination | strip and reject before storage and before send | yes |
| Header injection | no user data in header names or values | yes |
| XSS | escape all output, treat stored referer and user agent as untrusted | yes |
| CSRF | nonce on every mutation, verified server side | yes |
| Unauthorized access | `manage_options` on every page and action | yes |
| SQL injection | prepared statements with placeholders everywhere | yes |
| Regex injection | patterns escaped and wrapped, compile test at save | yes |
| ReDoS and catastrophic patterns | pattern cap 20, length cap 200, anchoring, lowered `pcre.backtrack_limit` and `pcre.recursion_limit` around the loop, fail closed on `preg_last_error`, match last | yes |
| Oversized input | length limits on source, target, and CSV rows | yes |
| CSV formula injection | sanitize and prefix dangerous leading characters | yes |
| Malicious CSV upload | per row validation, bounded batches, no code execution | yes |
| Redirect loops | graph cycle detection at save, no internal chain following | yes |
| Redirect chains | bounded warning, saveable | yes |
| External destinations | explicit allowlist, documented policy, permitted for legitimate SEO | yes |
| SSRF | no server side fetch of destinations, only browser redirects | yes |
| Cache poisoning | positive caching only, keyed by normalized path, invalidated on writes | yes |
| 404 flood | dedupe, exclusions, per window new URI budget, bounded pruning | yes |
| Admin bulk operations | capability and nonce on bulk and clear paths | yes |

Regex safety note `[VERIFIED]`: PHP 8.1 has no per call regex timeout. Anchoring alone does not stop nested quantifier ReDoS. The practical mitigation is the cap set above plus lowering the PCRE limits around the match loop and failing closed on backtrack limit errors. `ini_set` is process wide, so the limits are set once around the pattern loop and restored after.

---

# Privacy Architecture

`[PROPOSED]`:
- The 404 log stores no IP address by default, ever.
- Stored fields: normalized URI, URI hash, hits, created, last accessed.
- Advanced fields (referer, user agent) are opt in, truncated to 255, and off by default.
- Retention is bounded and configurable, and manual clear is always available.
- No telemetry, no external requests, no GeoIP, no third party calls.
- The readme privacy section documents what is collected, why, and the retention policy.

---

# Admin UX

Redirects page, native components:
- Add form at the top: source, destination, match type, status code, active state, query behavior note.
- List: From, To, Code, Match, Hits, Last Accessed columns, status views, search, sort, pagination.
- Row actions: Edit, Enable or Disable, Delete.
- Bulk actions: Activate, Deactivate, Trash, Restore, Delete, Export selected.
- Import and Export controls with a clear CSV contract.

404 page, native components:
- Usage indicator `742 / 1000` and retention information.
- Clear 404 Log button with confirmation.
- List: URI, Hits, Last Accessed, and (advanced) Referer and User Agent columns, search, sort, pagination.
- Row actions: Create Redirect, Delete.
- Bulk actions: Delete, Redirect (one destination).
- Access to exclusions and settings.

Message classes:
- Error, must fix before saving: loop detected.
- Warning, can save: chain detected, inconclusive chain analysis, pattern overlap.
- Information, no problem: automatic pruning notice.

---

# Migration and Uninstall

## Table creation `[PROPOSED]`, correcting the research

- Do not use `MigrationRunner` for module tables. It is a single linear ledger run on `init` priority 10, so a module enabled after the ledger advanced would never create its tables, and it has no per module retry or multisite per site creation.
- Use an explicit `ensureTables` routine called from the module enable path, idempotent with `dbDelta` and `CREATE TABLE IF NOT EXISTS`.
- Guard the hot path with a cheap schema ok flag and a table exists check that fails open (no redirect, no fatal) if the table is missing.
- Multisite: v1 creates tables for the current site only. Network wide management is deferred and documented.

## Uninstall `[VERIFIED]`

- No new uninstall code is required if names stay in the existing prefixes (`rankkernel_*` options, `wp_rankkernel_*` tables). `uninstall.php` already deletes those. Add a test asserting the prefixes.

## Lifecycle

| Stage | Behavior |
|---|---|
| module disabled | no class, no hooks, no tables created, no settings read |
| module enabled | ensureTables runs, settings seeded, hooks registered |
| rule create or update | validate, loop and chain analysis, upsert, invalidate cache group |
| rule delete | delete, invalidate cache group |
| import | batched validate and upsert, error report, invalidate |
| export | streamed or built CSV, no writes |
| 404 create | dedupe upsert, bounded prune at shutdown |
| 404 prune | bounded oldest first delete by age and count |
| manual clear | bounded delete loop, success notice |
| deactivation | no data change |
| reactivation | ensureTables rechecks |
| uninstall | existing prefix purge drops tables and options |

No path leaves orphaned data.

---

# Multisite

`[PROPOSED]` v1 is per site: tables use the site prefix, settings are per site, and uninstall is single site scope, matching the existing ledger ruling. Network activation creates tables per site on each site's enable action. A network wide manager is out of scope and documented as future.

---

# Testing Strategy

Automated matrix. Every security item above gets a test.

Redirects:
- exact, prefix, contains, suffix, wildcard, regex match and non match.
- status codes 301, 302, 307, 410, 451.
- query string preserve and ignore.
- case sensitivity.
- normalization edge cases: trailing slash, duplicate slashes, percent encoding, Unicode, subdirectory, homepage and bare domain blocked.
- internal and external destination, and allowlisted external.
- duplicate rules blocked by uniqueness.
- conflicts: exact plus prefix precedence, overlapping pattern warning.
- inactive rules skipped.
- cache hit and miss and invalidation.
- hit counter batching and last accessed.
- slug change creates one 301, no duplicate, no loop.

Loop detection:
- direct loop, two hop loop, three hop cycle, longer cycle.
- proposed rule creates a cycle.
- imported rule creates a cycle.
- inactive rule ignored.
- pattern rule inconclusive.
- depth and node caps enforced.

Chain detection:
- one, two, three, and long chains.
- chain through a pattern rule is inconclusive.
- chain through external destination terminates.
- chain warning shown, save succeeds.
- recommended final destination.
- no false blocking.

404:
- genuine 404 logged.
- admin, AJAX, REST, cron, sitemap, 410, 451 excluded.
- dedupe increments instead of inserting.
- retention by age.
- maximum rows enforced.
- oldest first pruning, bounded batches, never truncate.
- manual clear works and is authorized.
- bulk delete.
- near-limit notice thresholds.
- exclusions by path and keyword.
- query handling.
- flood budget caps new rows and keeps updating existing.
- redirect creation from a row, and dependency behavior when Redirects is off.

Security:
- nonce failure rejected per action.
- capability failure rejected.
- blocked schemes rejected at save and send.
- CRLF and control characters rejected.
- CSV injection sanitized.
- regex caps enforced and fail closed.

Performance:
- query counts for disabled module, cache hit, cold miss, 404 hit, 404 dedupe, pruning, using a counting fake `$wpdb`.

Admin:
- save through the post, redirect and nonce pattern.
- bulk actions.
- search, sort and pagination wiring.

Migration:
- ensureTables creates tables, is idempotent, and fails open when missing.
- per site creation on multisite.

Uninstall:
- tables and options removed under the purge setting.

Backward compatibility:
- module off leaves existing behavior unchanged.

Browser testing required: admin screens for usability and accessibility, the 404 to redirect flow, and frontend redirect behavior with a real theme and a page cache plugin present.

---

# Manual Verification

Owner checklist:
1. Enable Redirects.
2. Create an exact redirect.
3. Test the browser response and status code.
4. Test the cache hit on the second request.
5. Create an intentional chain.
6. Confirm the chain warning.
7. Save anyway.
8. Create a direct cycle.
9. Confirm the save is blocked.
10. Create a multi hop cycle.
11. Confirm the save is blocked.
12. Test a slug change creates a 301.
13. Enable 404 Monitor.
14. Generate a real 404.
15. Generate a repeated 404.
16. Verify dedupe increments hits.
17. Verify the usage count.
18. Generate enough entries to approach the limit.
19. Verify the near-limit notice.
20. Verify automatic pruning removes oldest first and never truncates.
21. Manually clear before the limit.
22. Verify confirmation and success.
23. Verify exclusions.
24. Verify flood protection caps new rows while existing rows keep counting.
25. Create a redirect from a 404 entry.
26. Verify the module dependency when Redirects is off.
27. Run CSV export.
28. Modify and import CSV.
29. Test malformed CSV is rejected per row.
30. Test the security cases.
31. Run the 30k post test environment for a frontend performance check.
32. Run all gates.

Nothing here is claimed as verified today.

---

# Documentation Plan

To be written during implementation, not now:

| Document | File |
|---|---|
| Redirect architecture | `docs/architecture/redirects.md` |
| 404 architecture | `docs/architecture/404-monitor.md` |
| Database schema | `docs/architecture/redirects-404-schema.md` |
| Matching and normalization | `docs/architecture/redirects-matching.md` |
| Loop and chain detection | `docs/architecture/redirects-safety.md` |
| Query handling | `docs/architecture/redirects-queries.md` |
| CSV format | `docs/architecture/redirects-csv.md` |
| Retention and growth | `docs/architecture/404-retention.md` |
| Privacy | `docs/privacy.md` |
| Security | `docs/architecture/redirects-404-security.md` |
| Admin usage | `docs/user-guide-redirects.md` |
| Troubleshooting | `docs/troubleshooting-redirects.md` |
| Performance claims | `docs/architecture/performance-claims.md` |
| Migration and uninstall | `docs/architecture/redirects-404-lifecycle.md` |

---

# Open Questions

Only decisions that genuinely need the owner:

1. Confirm dropping `wp_rankkernel_redirects_cache` (recommended) or keep it as a no object cache fallback.
2. Confirm two tables only, and the `match_type` enum replacing the `regex` boolean.
3. Confirm per module `ensureTables` instead of `MigrationRunner` for table creation.
4. Confirm the v1 matcher surface: exact, prefix, wildcard, capped regex, with contains and suffix included or deferred.
5. Confirm the regex cap numbers: 20 active regex rules and 200 character pattern limit.
6. Confirm the query policy: source query ignored, destination preserves incoming query by default with a global setting.
7. Confirm hit counting is batched at shutdown rather than synchronous.
8. Confirm 404 defaults: 30 day retention, 1000 maximum rows, 50 new URIs per 5 minutes flood budget.
9. Confirm advanced 404 fields are P1 opt in, and no IP ever.
10. Confirm the external destination policy: allowlist by default with a documented admin path to permit a host.
11. Confirm slug watcher scope for v1 is posts and pages only, with taxonomies and CPTs later.

---

# Implementation Order

Each step is a separate issue branch, with tests and green gates, no direct main merge.

1. Schema and table creation. `ensureTables` for `wp_rankkernel_redirects`, `wp_rankkernel_404_log`. Idempotent, module enable triggered, fail open guard.
2. Redirects module skeleton. `ModuleInterface` implementation, settings option `autoload=no`, registration in `Plugin::registerCoreServices`, zero hook test when off.
3. Normalization and hashing. The single shared normalizer with the full rule set and tests.
4. Redirect storage and exact matching. Repository, exact lookup by hash, safe destination validation, dispatch at `template_redirect` priority 1.
5. Cache layer. Object cache group, transient fallback, positive only, invalidation on writes.
6. Hit counters. Coalesced in memory, single shutdown update.
7. Admin redirect page. Add, edit, delete, enable and disable, search, status views, sort, pagination, bulk actions.
8. Loop and chain detection. Graph DFS with caps, cycle block, chain warning, inconclusive handling.
9. Pattern matchers. Prefix, wildcard, then guarded regex with caps and PCRE limits.
10. CSV import and export with the full contract and per row validation.
11. Slug change watcher with a disable setting.
12. 404 Monitor module. Capture at priority 99 with exclusions, dedupe upsert, no IP.
13. 404 retention and pruning, manual clear, near-limit notices, settings.
14. 404 admin page. List, search, sort, delete, bulk, create redirect.
15. Flood protection. Per window new URI budget with a suppressed notice.
16. Security hardening pass and the full security test set.
17. Performance pass with query count tests and the growth caps.
18. Documentation set from the documentation plan.
19. Owner manual verification, then merge.

---

End of plan. No implementation performed. No source files modified, no migrations created, no tables created, no branches created, working tree unchanged. Awaiting owner approval of this plan, and answers to the open questions, before any build prompt is issued.
