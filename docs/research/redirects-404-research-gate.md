# RankKernel Research Gate: Redirects and 404 Monitor

Status: RESEARCH ONLY. No implementation. Awaiting owner approval.
Date: 2026-09-11. Feature: Redirects and 404 Monitor, the next RankKernel modules.
Method: competitor research (Rank Math free and premium, Yoast free and premium), public source inspection where available, official documentation, RankKernel architecture review, then an independent RankKernel design.
Clean room: this report describes competitor behavior only. No competitor code, schema, CSS, JS, HTML, class names, or algorithms were copied. RankKernel design is original.

Evidence tags used throughout: `[DOCS]` official documentation, `[SOURCE]` public source inspected, `[OBSERVED]` third party or rendered behavior, `[INFERRED]` reasoned and not directly proven.

---

## 1. Executive summary

Redirects and 404 handling are the next planned modules (blueprint §G, ROADMAP 2.4 and 2.5). The research changes two assumptions that were carried in the earlier blueprint, and it strengthens the case for the existing RankKernel design.

Key findings:

1. Rank Math ships most redirect capability free, including regex matching, automatic slug change redirects, and 404 to redirect. Only scheduled activation and expiration, categories, CSV import and export, server file sync, and 404 log export are premium. So RankKernel parity requires real work, not a thin feature.
2. Yoast has no redirect manager and no 404 monitor in its free plugin. Both live behind Yoast Premium, and Yoast Premium stores redirects in autoloaded `wp_options` entries, which caused a documented scaling incident. This is a clear rankKernel opportunity: give the manager away free and store it properly.
3. The dominant performance risk in rival plugins is not the redirect itself, it is the per request matching strategy. Rank Math matches on `wp` priority 11 on every frontend request, prefiltering with `LIKE` on a serialized longtext column, and adds a cache table write on misses. RankKernel's blueprint design (single `template_redirect` priority 1 pass, cache first, at most one targeted query on a cold miss, zero hooks when the module is off) is fundamentally better and should be kept.
4. The dominant data integrity risk is 404 log growth. Rank Math truncates the entire log when it hits the limit, destroying history. RankKernel's binding policy (prune by age and by count, oldest first, never blanket truncate) is correct and must be kept.
5. The dominant security risks are open redirects, unsafe URL schemes, and catastrophic regular expression backtracking (ReDoS). Rivals ship partial protection at best. RankKernel must design these in from the start.
6. The storage decision is already made and justified. Redirect rules and 404 logs need custom tables because options would autoload bloat and postmeta does not scale. The blueprint already specifies exact table shapes. This report affirms that decision and refines the indexing.
7. One blueprint claim needs correcting: it says Rank Math free is "export-only" for CSV. Current research shows Rank Math CSV import and export is paid. This does not change the plan, it strengthens the differentiation, but the wording in the blueprint and feature matrix should be corrected.

Recommended scope for approval: a Redirects module (exact, prefix, and wildcard matching first, regex last with hard safety caps, then CSV import and export, slug change watcher, hit counter, full admin CRUD) and a 404 Monitor module (simple log with dedupe counter, prune by age and count, exclusions, per row and bulk redirect creation, no IP storage by default). Server file sync, rule categories, multi source rules, and WooCommerce specific behavior are excluded.

---

## 2. Rank Math Free functionality

Evidence: Rank Math knowledge base `[DOCS]`, public repo `rankmath/seo-by-rank-math` at commit `a9a9e44df7926acaba14e98d200049fe08226eb9` `[SOURCE]`.

Redirects free:
- Create, edit, delete, enable and disable, with trash, restore, permanent delete, and empty trash.
- Source URL and destination URL, internal or external, with a blocked list for the homepage and bare domain to prevent lockout.
- Status codes 301, 302, 307, 410, 451. Codes 410 and 451 clear the destination.
- Match types: Exact, Contains, Starts With, Ends With, Regex.
- Ignore case per rule.
- Multiple source URLs per rule, stored as a serialized array in one row.
- Query string preserved by default and filterable.
- Hit counter, created date, last accessed date.
- Search by source or destination, status views with counts, sorting, pagination.
- Bulk activate, deactivate, move to trash, restore, permanent delete.
- Backup download of current `.htaccess` or Nginx rules.
- Import from Yoast Premium and from the Redirection plugin.
- Auto post redirect on slug change for posts, pages, CPTs, and taxonomies, default off, with a notice and dismissible prompt.
- Editor metabox Advanced tab redirect field.
- 404 monitor row to redirect creation, and an admin bar link on a 404 page.
- Save time infinite loop check for exact matches only.
- Debug interstitial for administrators showing the matched rule.

404 monitor free:
- Simple mode: URI, hit count, access time, grouped by URI with a counter.
- Advanced mode: adds referer and user agent, one row per hit, with a resource warning.
- Settings: mode, log limit, exclude paths with comparators (Exact, Contains, Starts With, Ends With, Regex), ignore query parameters.
- Search, sort, pagination, per row delete, bulk delete, clear all log.
- Per row redirect creation and bulk redirect to one shared destination.
- 410 and 451 responses are not logged.
- No IP address stored.

Notable free gaps: no CSV import, no CSV export, no scheduled activation or expiration, no rule categories, no `.htaccess` sync, no 404 log export.

---

## 3. Rank Math Premium functionality

Evidence: official documentation and the free versus pro page `[DOCS]`. Premium source is not public and was not inspected. All statements are documented behavior only.

- Scheduled activation and scheduled expiration date pickers per rule.
- Rule categories, category management, and a category filter.
- Parameterized URL redirect examples in documentation.
- CSV import and export with a defined column contract and merge semantics.
- Sync to `.htaccess` for faster server level redirects.
- Export the 404 log with a date range.
- Adjacent reporting (broken link reports, AI Link Genius) is premium but is a separate product surface, not the 404 Monitor itself.

Correction to note: the earlier blueprint wording said Rank Math free CSV is export only. Current sources indicate CSV import and export is a premium feature. Treat the wording as needing correction.

---

## 4. Yoast Free functionality

Evidence: public repo `Yoast/wordpress-seo` at commit `4240cd60c61cb4af1fe0ded4849f5d9e1426db8c` `[SOURCE]`, official docs `[DOCS]`.

- No redirect manager and no 404 monitor.
- Automatic behaviors only: attachment URL redirect, crawl cleanup parameter stripping with a safe redirect, disabled date and author archive redirects to home, and admin navigation redirects.
- Retired legacy per post 301 metadata, documented as removed in favor of the premium manager.
- 404 handling only produces correct 404 status and presentation, for example forcing a true 404 for empty feeds. There is no 404 log.

---

## 5. Yoast Premium functionality

Evidence: official documentation and developer changelogs `[DOCS]`. Premium source is not public and was not inspected.

- Dedicated redirect manager: create, edit, delete, bulk delete.
- Automatic redirects on move, slug change, and trash, for posts, pages, taxonomies, and CPTs, with undo, ignore, and disable options.
- Regex redirects with capture groups, with a caution warning.
- Import from plugins, CSV, and `.htaccess` paste. Export CSV. Existing redirects are skipped on import (no update in place).
- 404 to redirect assistance through an admin bar shortcut.
- Redirect method choice: PHP handling, or write to Apache `.htaccess` or Nginx.
- Storage is documented to live in Yoast, not the theme. Public writeups and the autoload toggle imply serialized `wp_options` entries. Column level schema is not publicly confirmed, so exact storage internals are marked inferred and must not be treated as fact.
- Documented production incident: autoloaded redirect option blobs grew the `alloptions` payload and broke object cache limits, requiring a filter and manual `autoload=no`. This is the canonical warning against storing rules as an autoloaded option.
- Documented security incident (27.6.1): an authenticated user with `edit_posts` could inject Apache directives through a redirect AJAX endpoint when server file redirects were enabled. Fixes included stripping control characters, removing the endpoint, and adding warnings. Server file mode is also disabled on multisite because one site could overwrite sibling rules.

---

## 6. Public source code inspected

- Rank Math free repo `rankmath/seo-by-rank-math`, commit `a9a9e44df7926acaba14e98d200049fe08226eb9`. Redirect and 404 modules, installer, helpers, frontend redirection, and the WooCommerce product redirection class were inspected for observable behavior.
- Yoast free repo `Yoast/wordpress-seo`, commit `4240cd60c61cb4af1fe0ded4849f5d9e1426db8c`. Redirect adjacent helpers, the 404 handler, permalink watcher, and the safe redirect helper were inspected. No premium package is present.
- Redirection plugin `johngodley/redirection`, public. Inspected as a third reference for storage, indexing, expiry, privacy controls, and REST and WP-CLI support.
- Safe Redirect Manager (10up), public. Inspected as a WordPress native alternative (custom post type plus postmeta plus transient, capped at 1000 rules).
- WordPress core documentation for `template_redirect`, `wp_safe_redirect`, `wp_validate_redirect`, `wp_redirect`, and `wp_allowed_protocols`.

Not inspected and not claimed: Rank Math premium source, Yoast premium source.

---

## 7. Source files and classes inspected

Rank Math (behavior reported, not copied):
- `includes/modules/redirections/class-redirections.php` module bootstrap and hook.
- `includes/modules/redirections/class-redirector.php` matching flow and redirect execution.
- `includes/modules/redirections/class-db.php` storage queries, two phase match, stats.
- `includes/modules/redirections/class-redirection.php` single rule model, sanitize, validate, loop check.
- `includes/modules/redirections/class-cache.php` cache operations.
- `includes/modules/redirections/class-watcher.php` slug change auto create and invalidation.
- `includes/modules/redirections/class-table.php`, `class-admin.php`, `class-metabox.php`, and views.
- `includes/modules/404-monitor/class-monitor.php`, `class-db.php`, `class-admin.php`, `class-table.php`, and views.
- `includes/class-installer.php` table shapes and per site creation.
- `includes/helpers/class-choices.php` and `class-str.php` comparison helpers.

Yoast free (behavior reported, not copied):
- `src/integrations/front-end/handle-404.php`
- `src/integrations/watchers/indexable-permalink-watcher.php`
- `src/integrations/admin/redirect-integration.php`
- `src/helpers/redirect-helper.php`, `src/helpers/crawl-cleanup-helper.php`
- 404 presentation helpers and the indexable repositories.

RankKernel (current architecture, read only):
- `src/Modules/ModuleInterface.php`, `ModuleManager.php`, `ModuleEnableMap.php`, `ModuleRegistry.php`
- `src/Plugin.php`, `rankkernel.php`
- `src/Database/Migrations/MigrationRunner.php`
- `src/Settings/SettingsStore.php`
- `src/Modules/Sitemaps/SitemapCache.php`, `Router.php`, `SitemapsModule.php`
- `src/Admin/AdminMenu.php`, `SettingsPage.php`, `SitemapSettingsPage.php`
- `src/Rest/SettingsController.php`, `ModulesController.php`
- `uninstall.php`
- `docs/architecture/blueprint.md` §D.4, §G, `ROADMAP.md` 2.4, 2.5
- `tests/Unit/ModuleManagerTest.php`, `tests/bootstrap.php`

---

## 8. Behavioral comparison

| Behavior | Rank Math free | Rank Math premium | Yoast free | Yoast premium | RankKernel direction |
|---|---|---|---|---|---|
| Manual redirect CRUD | Yes | Yes | No | Yes | Yes, free |
| Match: exact | Yes | Yes | No | Yes (plain) | Yes |
| Match: prefix, contains, suffix | Yes | Yes | No | Documented plain and regex only | Yes, as safe string matches |
| Match: wildcard | Via regex | Via regex | No | Via regex | Yes, glob, safer than raw regex |
| Match: regex | Yes | Yes | No | Yes | Yes, capped and guarded |
| Status codes 301, 302, 307, 410, 451 | Yes | Yes | No | Yes | Yes |
| Ignore case | Yes | Yes | No | Not documented | Yes |
| Multiple sources per rule | Yes | Yes | No | Not documented | No, use separate rules |
| Query string preservation | Yes, filterable | Yes | No | Partial, UTMs discouraged | Yes, with a setting |
| Scheduled activation and expiration | No | Yes | No | Not documented | Candidate, cron free evaluation |
| Hit counter and last accessed | Yes | Yes | No | Not documented | Yes |
| Search, sort, pagination, bulk | Yes | Yes | No | Yes at a high level | Yes |
| CSV import | No | Yes | No | Yes, skips existing | Yes, free |
| CSV export | No | Yes | No | Yes | Yes, free |
| Import from rivals | Yes | Yes | No | Yes | Yes, later phase |
| Auto redirect on slug change | Yes | Yes | No | Yes | Yes, with a disable setting |
| Auto redirect on delete or trash | Notice only | Notice only | No | Yes | Suggestion notice, later |
| Editor integration | Yes, metabox | Yes | No | Yes, delete prompt | Later phase |
| 404 to redirect per row | Yes | Yes | No | Admin bar shortcut | Yes |
| 404 bulk redirect | Yes, one destination | Yes | No | Not documented | Candidate, one destination |
| 404 log monitor | Yes, simple and advanced | Yes | No | No documented log | Yes, simple first |
| 404 log export | No | Yes | No | No | Candidate, free |
| Rule categories | No | Yes | No | Not documented | No |
| Server file sync | Backup download only | Yes, sync | No | Yes | No |
| Loop and chain detection | Exact, at save | Exact, at save | No | Not documented | Save time plus bounded runtime guard |
| Per request cost when enabled | Match on every request, LIKE prefilter, cache write on miss | Same | None | Options blob or server file | Cache first, one targeted query on cold miss |
| Storage | Two custom tables | Two custom tables | None | Autoloaded options, inferred | Two custom tables, per blueprint |
| IP storage in 404 log | No | No | Not applicable | Not applicable | No by default |
| Multisite | Per site tables | Per site tables | Not applicable | Server mode disabled | Per site tables |

---

## 9. UX comparison

Rank Math:
- Location: Rank Math SEO, Redirections, plus a settings tab. The module must be enabled in the dashboard first, and the plugin wide Advanced Mode must be on.
- Create flow is six to eight steps: enable module, open list, add new, enter source, choose match and ignore case, enter destination, choose type, choose status, submit.
- The add form sits inline above the list. Edit loads the rule into the same form.
- Validation messages: field must not be empty, invalid regex pattern, may cause infinite loops, missing CSV columns.
- Empty states exist for an empty list and empty trash.
- 404 workflow: sort by hits, inspect referer in advanced mode, use the row redirect action, set exact match and destination, save, then delete the 404 row.
- Strengths: fast inline add, good 404 to redirect flow, admin bar shortcut, debug interstitial.
- Weaknesses: regex rules are not findable by behavior in search, the limit wipe destroys history, advanced mode grows fast, categories are paywalled, and the mental model of two nested settings pages plus Advanced Mode is heavy.

Yoast:
- Location: Yoast SEO, Redirects, with separate plain and regex tabs and an import screen under Tools.
- Delete flow is driven by prompts on trash and on slug change, with undo and ignore.
- Strengths: the automatic prompt flow is hard to get wrong, and the delete chooser (redirect versus 410) is clear.
- Weaknesses: the entire manager is paywalled in free, bulk edit is explicitly not supported, existing redirects are skipped on import, and there is no 404 log at all.

RankKernel target UX:
- One page under Tools for Redirects and one for 404 Monitor, both only visible when their module is on.
- Redirect add form inline at the top of the list, one screen, no nested Advanced Mode toggle.
- Redirect list columns: from, to, code, hits, last accessed, with status views and bulk actions.
- 404 list columns: URI, hits, last accessed, referer (advanced), with a per row and a bulk create redirect action.
- Clear, single sentence validation messages, safe defaults (301, active), and a confirmation dialog only for destructive actions.
- Native WordPress admin components, accessible labels, keyboard operable, no custom heavy UI. The branded admin redesign is a later phase (ROADMAP 4.1), so these screens start plain and native.

---

## 10. Performance comparison

Rank Math:
- Runs matching on every frontend request on the `wp` hook at priority 11 (or `template_redirect` under BuddyPress), with early bailouts for admin, login, AJAX, XHR, the customizer, and Elementor preview.
- Two phase match: candidate prefilter with `LIKE` on a serialized longtext `sources` column, then a PHP comparison. A second full active scan runs when no candidate matches. `LIKE` with wildcards cannot use an index.
- Adds a cache table row on a miss, which is a write per uncached miss and has been shown to balloon under bot probing.
- Hit counter writes on every redirect.
- Loop and chain detection is save time only, exact matches only.
- Indexes: status, and a composite status plus updated. Cache table has no index on the lookup column.
- 404 logging writes synchronously on every 404 with no throttle or sampling, and truncates the whole table when the limit is reached.

Yoast:
- Free has no redirect cost. Premium, in PHP mode, resolves through WordPress, and in server mode bypasses WordPress entirely through `.htaccess` or Nginx, which is the fastest option but carries the security and multisite hazards noted above.
- Premium stores rules in autoloaded options, which is fast only while the payload stays small and becomes a global slowdown once it grows.

Redirection plugin (third reference):
- Main loop on `init`, with a dedicated `match_url` column and targeted indexes, exact before regex, and object cache support (including negative caching), with cron based log expiry.

RankKernel direction:
- Keep `template_redirect` priority 1, cache first. Cache hit means no rule table query. Cold miss means one targeted indexed query, then warm the cache.
- Never prefilter with `LIKE` on a serialized blob.
- Never write a cache row on every anonymous miss without a cap (avoid cache pollution and flood writes).
- Bound the cost honestly: zero cost when the module is off, cache first when on, at most one targeted query on a cold miss. This wording already exists in the blueprint and the README and must stay.
- 404 logging stays synchronous and cheap, with an indexed `uri_hash` dedupe path in simple mode.

---

## 11. Security comparison

Rank Math:
- Capability gated (`rank_math_redirections`, `404_monitor`), AJAX nonce checks, input sanitation, output escaping, and `esc_url_raw` into `wp_redirect`.
- Destinations may be external by design. No scheme allowlist or host validation was observed, so open redirect hardening is not documented.
- Regex is validated for validity and control characters are stripped, but there is no timeout or ReDoS guard.
- Loop detection is exact match only at save time.

Yoast:
- Premium had a real security incident (Apache directive injection through a redirect endpoint) when server file redirects were enabled. The fix layered control character stripping, endpoint removal, and warnings.
- Later hardening restricted who could create and delete redirects.
- Free uses a safe redirect helper for crawl cleanup, showing platform convention awareness.

Redirection plugin:
- Provides IP logging levels (none, full, anonymized), proxy header allowlists, capability tightening, and regex count warnings.

RankKernel direction:
- Use `wp_safe_redirect` and `wp_validate_redirect` semantics for destinations, with an explicit allowlist for external hosts, and never pass user input directly to a redirect location.
- Validate destination schemes against WordPress allowed protocols and reject `javascript:`, `data:`, `vbscript:`, and protocol relative tricks.
- Cap regex count, require anchored patterns, enforce a length limit, and check `preg_last_error`. Order matching exact, then prefix, then wildcard, then regex last, so the common cases never touch the regex engine.
- Add a bounded runtime chain guard (one hop, then stop) in addition to save time loop detection.
- Capability checks and nonces on every mutation, prepared statements everywhere, output escaping everywhere.
- Default to no IP storage in the 404 log. Truncate referer and user agent, make advanced fields opt in, and document retention in the readme privacy section.
- Rate limit or sample 404 logging to blunt flood attacks, and cap the log with oldest first pruning (never a full truncate).

---

## 12. Free versus premium matrix

| Capability | Rank Math Free | Rank Math Premium | Yoast Free | Yoast Premium | RankKernel Recommendation |
|---|---|---|---|---|---|
| Manual redirect CRUD | Yes | Yes | No | Yes | P0, free |
| Status codes 301, 302, 307, 410, 451 | Yes | Yes | No | Yes | P0, free |
| Exact match | Yes | Yes | No | Yes | P0, free |
| Prefix, contains, suffix match | Yes | Yes | No | Not documented | P1, free |
| Wildcard match (glob) | Via regex | Via regex | No | Via regex | P1, free |
| Regex match | Yes | Yes | No | Yes | P1, free, guarded |
| Ignore case | Yes | Yes | No | Not documented | P2, free |
| Query string preservation | Yes | Yes | No | Partial | P1, free, setting |
| Multiple sources per rule | Yes | Yes | No | Not documented | DROP, use separate rules |
| Scheduled activation and expiration | No | Yes | No | Not documented | Future, free, cron free if adopted |
| Rule categories | No | Yes | No | Not documented | DROP |
| Hit counter and last accessed | Yes | Yes | No | Not documented | P0, free |
| Search, sort, pagination | Yes | Yes | No | Yes | P0, free |
| Bulk activate, deactivate, delete | Yes | Yes | No | Delete only | P0, free |
| CSV import | No | Yes | No | Yes | P0, free |
| CSV export | No | Yes | No | Yes | P0, free |
| Import from rivals | Yes | Yes | No | Yes | Future, free |
| Auto redirect on slug change | Yes | Yes | No | Yes | P0, free, disable setting |
| Auto redirect on trash or delete | Notice | Notice | No | Yes | P2, free, suggestion |
| Editor integration | Yes | Yes | No | Yes | Future |
| 404 to redirect per row | Yes | Yes | No | Admin bar | P0, free |
| 404 bulk redirect | Yes | Yes | No | Not documented | P1, free, one destination |
| 404 simple monitor | Yes | Yes | No | No documented log | P0, free |
| 404 advanced fields (referer, agent) | Yes | Yes | No | No documented log | P1, opt in, no IP |
| 404 log export | No | Yes | No | No | P2, free |
| Server file sync (.htaccess, Nginx) | No | Yes | No | Yes | DROP |
| Loop and chain detection | Exact at save | Exact at save | No | Not documented | P0 at save, P1 runtime guard |
| Outbound cache table writes | On every miss | On every miss | None | Options blob | P1 with caps, no unbounded writes |
| IP storage in 404 log | No | No | Not applicable | Not applicable | No by default |

`[INFERRED]` marker: Yoast Premium column entries marked "not documented" mean no official source confirmed them and premium source was not inspected. They are not claimed as absent, only unconfirmed.

---

## 13. Complete feature inventory

Redirects (each item is one capability):
- Create redirect.
- Edit redirect.
- Delete redirect, with trash, restore, and permanent delete.
- Enable and disable a redirect.
- Source URL entry, full or root relative, homepage blocked.
- Destination URL entry, internal or external.
- Destination cleared for 410 and 451.
- Status codes 301, 302, 307, 410, 451.
- Exact match.
- Prefix match (starts with).
- Suffix match (ends with).
- Contains match.
- Wildcard match.
- Regex match with capture groups.
- Ignore case toggle.
- Query string preservation.
- Query parameter handling on source.
- Multiple source URLs per rule.
- Scheduled activation.
- Scheduled expiration.
- Hit counter.
- Last accessed timestamp.
- Created timestamp.
- Active and inactive status.
- Search.
- Filtering by status.
- Sorting by column.
- Pagination.
- Bulk activate.
- Bulk deactivate.
- Bulk trash, restore, delete.
- CSV import.
- CSV export.
- Import from other plugins.
- Automatic redirect on slug change.
- Automatic redirect on post trashing or deletion.
- Automatic redirect on taxonomy term change.
- Redirect creation from the editor.
- Redirect creation from the 404 monitor.
- Redirect chains handling.
- Loop detection.
- Destination validation.
- Conflict detection and merge.
- WooCommerce behavior.
- Multisite behavior.
- Developer filters and hooks.
- REST support.

404 Monitor:
- Simple mode.
- Advanced mode.
- Logged URI.
- Hit count.
- First access timestamp (note: Rank Math does not store this).
- Last access timestamp.
- Referer.
- User agent.
- IP handling.
- Query parameter handling.
- Exclude paths.
- Exclude keywords.
- Log limit and growth control.
- Grouping.
- Search.
- Filtering.
- Sorting.
- Pagination.
- Delete individual log.
- Clear all logs.
- Bulk delete.
- Create redirect from a log row.
- Bulk redirect from logs.
- Export log.
- Privacy implications.
- Bot traffic handling.
- Static asset exclusions.
- REST exclusions.
- Admin request exclusions.
- Performance behavior.
- Storage behavior.
- Prune by age.
- Prune by count, oldest first.
- Retention policy.
- Flood protection.

---

## 14. P0, P1, P2, P3, DROP classification

P0 (required for baseline parity and safe operation):
- Redirect CRUD with trash and restore.
- Enable and disable.
- Source and destination entry, homepage protection.
- Status codes 301, 302, 307, 410, 451.
- Exact match.
- Cache first matching at `template_redirect` priority 1.
- Hit counter and last accessed.
- Search, status views, sorting, pagination.
- Bulk activate, deactivate, trash, restore, delete.
- CSV import and export, free.
- Auto redirect on slug change with a disable setting.
- Loop detection at save time for exact matches.
- Destination validation with `wp_safe_redirect` and scheme allowlist.
- 404 simple log with dedupe counter.
- 404 prune by age and by count, oldest first, never truncate.
- 404 per row create redirect.
- Capability checks, nonces, prepared statements, escaping throughout.
- Module off means zero hooks, proven by test.

P1 (strong improvement, ship in the first release if feasible):
- Prefix, contains, and suffix matching as safe string operations.
- Wildcard glob matching.
- Regex matching with hard caps (count, length, anchoring, error check).
- Query string preservation with a setting.
- Bounded runtime chain guard (one hop).
- 404 advanced fields (referer, user agent) as opt in, truncated, no IP.
- 404 exclusions by path and keyword.
- 404 ignore query parameters.
- 404 bulk redirect with one destination.
- Flood control on 404 logging (sampling or a hard per request cap).
- Configurable 404 retention days and max rows.

P2 (useful enhancement, later):
- Ignore case toggle.
- Auto redirect suggestion on post delete or trash.
- 404 log export.
- Redirect import from rivals.
- Scheduled activation and expiration, evaluated at match time, no cron.

P3 or Future:
- Editor metabox redirect field.
- Taxonomy term change auto redirect.
- Multisite network level management.
- Developer debug interstitial for administrators.
- WP-CLI commands.

DROP (do not implement):
- Server file sync to `.htaccess` or Nginx.
- Rule categories.
- Multiple source URLs in a single row.
- WooCommerce specific redirect behavior in this module.
- REST API for redirects or 404 in the first release.
- Autoloaded option storage for rules.
- Any full table truncate on log limit.

---

## 15. RankKernel proposed functionality

### Redirects

P0:
- A Redirects module, default off, id `redirects`, storing rules in `wp_rankkernel_redirects` and cold lookups in `wp_rankkernel_redirects_cache`, created by migration only when the module is enabled.
- `template_redirect` priority 1 interception, guarded by `is_admin()`, `wp_doing_ajax()`, and REST and cron checks. Cache hit redirects immediately. Cold miss runs one indexed query by normalized source hash, then warms the cache.
- Exact match. Codes 301, 302, 307, 410, 451. Hit counter and last accessed.
- Admin list with add, edit, delete, search, status views, sorting, pagination, and bulk actions.
- CSV import and export, free.
- Slug change watcher on `post_updated` that creates a 301 when the slug actually changed, with a setting to disable it.
- Safe redirect sending through validated destinations only.

P1:
- Prefix, contains, and suffix matching, implemented with string functions, not regex.
- Wildcard matching where `*` is translated to a single safe string operation.
- Regex matching as the last resort, with a maximum rule count, a pattern length limit, required anchoring, control character stripping, and a `preg_last_error` check.
- Query string preservation, default on, with a setting.
- A bounded runtime chain guard: after one redirect hop, stop and do not follow again in the same request.

P2:
- Ignore case toggle.
- Scheduled activation and expiration, evaluated by comparing stored timestamps at match time, so no cron is needed.

Future:
- Redirect creation from the editor metabox.
- Import from Yoast and Rank Math.
- WP-CLI commands.
- Debug view for administrators.

Do not implement:
- Server file sync.
- Rule categories.
- Multiple sources per row.
- WooCommerce specifics in this module.

### 404 Monitor

P0:
- A 404 Monitor module, default off, id `404`, storing rows in `wp_rankkernel_404_log`, created by migration only when the module is enabled.
- Simple mode with dedupe: same URI increments a counter and updates last access instead of inserting a new row.
- Capture on genuine 404s only, excluding admin, AJAX, REST, cron, sitemap 404s, and 410 and 451 responses.
- Logged fields: normalized URI, URI hash, hit count, last accessed, created. No IP.
- Prune by age (retention days) and by count (max rows), old oldest first, never a full truncate.
- Admin list with search, sort, pagination, per row delete, bulk delete, and clear log with confirmation.
- Per row create redirect, prefilled with the broken URI, requiring the Redirects module.

P1:
- Exclusions by path and keyword with comparators.
- Ignore query parameters setting.
- Advanced fields (referer, user agent) as an opt in, truncated to 255, with a clear privacy note.
- Bulk redirect with one shared destination.
- Flood control: a hard cap on rows written per request window and a per request dedupe.
- Configurable retention days and max rows with safe defaults (for example 30 days and 1000 rows).

P2:
- 404 log CSV export.

Future:
- Grouping and trend views.
- Admin bar quick redirect.

Do not implement:
- IP address storage by default.
- Per hit row explosion in the default mode.
- Cron dependent expiry.
- Full table truncate.

---

## 16. RankKernel improvements over competitors

1. Cache first matching with a bounded cold miss, versus Rank Math matching on every request with a serialized `LIKE` prefilter and a cache write per miss. Impact: lower per request cost and no cache pollution. Complexity: moderate. Security: neutral. UX: neutral.
2. Free CSV import and export, versus Rank Math paid and Yoast paid. Impact: real free differentiator. Complexity: moderate. UX: high value for migrations.
3. Prune by age and count, oldest first, versus Rank Math truncating the whole log. Impact: never lose history. Complexity: low.
4. No IP storage by default, versus the Redirection plugin logging IP by default. Impact: lower privacy exposure. Complexity: low. Security and privacy: strong.
5. Safe string matching before regex, with hard regex caps, versus regex available with weaker guards. Impact: materially lower ReDoS risk. Complexity: moderate. Security: strong.
6. Bounded runtime chain guard in addition to save time loop detection, versus save time only in rivals. Impact: prevents accidental redirect chains. Complexity: low. Security: moderate.
7. Single settings option for the module, `autoload=no`, versus Yoast autoloaded option blobs that caused a scaling incident. Impact: no global slowdown. Complexity: low.
8. One page per module, no nested Advanced Mode requirement, versus Rank Math requiring both a plugin wide Advanced Mode and a module toggle. Impact: simpler UX. Complexity: low.
9. Query string preservation as an explicit setting, versus implicit behavior. Impact: clearer expectations. Complexity: low.
10. Honest performance language in the code and docs: zero cost when off, cache first when on, at most one targeted query on a cold miss. Complexity: none.

---

## 17. Functionality deliberately excluded (Do Not Implement)

These are excluded with reasons, so future agents do not add them by reflex:
- Server file sync to `.htaccess` or Nginx: high security risk (proven by the Yoast 27.6.1 incident), multisite hazard, and it bypasses WordPress in a way that conflicts with the WordPress native principle.
- Rule categories: administrative overhead with no SEO value, and it was a Rank Math paywall feature that adds complexity.
- Multiple source URLs in a single row: a serialized blob is exactly the anti pattern the blueprint avoids. Separate rules achieve the same result.
- WooCommerce specific redirect behavior: belongs to a later commerce phase, not the core redirects module.
- REST endpoints for redirects and 404 in the first release: the admin POST pattern already in the codebase is simpler and sufficient.
- Autoloaded option storage for rules: breaks the minimal footprint and global performance rules.
- Full table truncate on log limit: a data integrity bug, not a feature.
- IP address storage by default: a privacy concern with no SEO value.
- Per hit row explosion in the default 404 mode: log flooding and database bloat.
- Cron based log expiry: violates the no unnecessary cron principle. Expiry is done at insert time and on admin action.
- Regex first matching: a performance and safety anti pattern.
- Heavy custom JavaScript UI: the branded admin is a later phase, and native components come first.

---

## 18. Proposed storage architecture

Table `wp_rankkernel_redirects` (created only when the Redirects module is enabled), matching the blueprint §D.4:
- `id` bigint unsigned auto increment primary key.
- `source_url_hash` varchar(64) not null unique, the hash of the normalized source.
- `source` text not null, the normalized source path for display and rehash.
- `target` text not null, the destination.
- `code` enum of 301, 302, 307, 410, 451, not null.
- `regex` tinyint(1) not null default 0.
- `hits` bigint unsigned not null default 0.
- `created` datetime not null.
- Index on the source hash for the cold miss lookup.

Table `wp_rankkernel_redirects_cache` (same module):
- `id` bigint unsigned auto increment primary key.
- `hash` varchar(64) not null, the hash of the request URI.
- `redirect_id` bigint unsigned not null.
- Index on `hash`.
- Purpose: cache first lookup. A hit answers with no query against the rules table. Bounded and prunable, and writes are capped to avoid pollution and flood writes.

Table `wp_rankkernel_404_log` (created only when the 404 module is enabled), matching the blueprint §D.4:
- `id` bigint unsigned auto increment primary key.
- `uri_hash` varchar(64) not null, indexed, for the dedupe lookup.
- `uri` text not null.
- `referer` varchar(255) default empty, populated only in advanced mode.
- `user_agent` varchar(255) default empty, populated only in advanced mode.
- `hits` bigint unsigned not null default 0.
- `created` datetime not null.
- `last_accessed` datetime not null.
- Index on `uri_hash` and on `last_accessed` for oldest first pruning.

Lookup strategy: hash the normalized request URI, check the object cache first, then the cache table, then the rules table by source hash, then warm. Never scan by `LIKE`.

Cache strategy: an object cache group specific to redirects (for example `rankkernel-redirects`), with transient fallback, following the existing sitemap cache pattern but with its own group and its own validator keys so a redirect save never flushes the sitemap cache.

Cleanup strategy: the redirects cache table is capped and pruned oldest first. The 404 log is pruned by age and by count, oldest first, at insert time and on admin clear.

Growth limits: redirects cache max rows and 404 log max rows are settings with safe defaults. Both are enforced by oldest first deletion.

Migration strategy: register migrations on the existing `MigrationRunner` using the ledger `rankkernel_db_version`. Tables are created when the module is enabled, not at install, matching the blueprint rule that off means zero cost. The migration closure must be idempotent (`CREATE TABLE IF NOT EXISTS`) and must run before `init` fires.

Uninstall behavior: no new uninstall code is required if names follow the existing prefixes. `uninstall.php` already deletes `rankkernel_*` options, `_rankkernel_*` meta, and `wp_rankkernel_*` tables. The migration and settings names must stay inside those prefixes, and an uninstall test should assert it.

Justification for custom tables over options or postmeta: options would autoload bloat (the Yoast lesson) and postmeta does not scale for high cardinality logs and rule sets. The blueprint already mandates these tables. This is not a case of copying a rival, it is the correct fit for the workload and the minimal footprint rule, because separate narrow tables with autoload off for heavy data is lighter than any alternative.

---

## 19. Proposed performance architecture

- Module off means the class is never loaded and no hook is registered. Proven by a zero hook test.
- Module on: one `template_redirect` priority 1 callback, guarded by `is_admin()`, `wp_doing_ajax()`, REST, and cron checks.
- Cache first: an object cache lookup, then the cache table. A hit sends the redirect immediately with no rules table query.
- Cold miss: exactly one targeted query against the indexed rules table by source hash, then warm the cache. Bounded to one query.
- No `LIKE` prefilter on serialized data. No full scan fallback on the hot path.
- Matching order: exact, then prefix, contains, and suffix string comparisons, then wildcard glob, then regex last, so the regex engine is touched only when necessary.
- Regex rules are capped in count and length, and are required to be anchored.
- The 404 logger writes synchronously but cheaply: one indexed dedupe lookup, then an increment or an insert. It excludes admin, AJAX, REST, cron, sitemap 404s, and 410 and 451 responses. It honors exclude rules and the ignore query parameters setting. It caps writes per request window to blunt floods.
- Growth is controlled by settings and oldest first pruning, never a truncate.
- No cron for expiry or cleanup. Work happens at insert time and on admin action.
- Honest claim only: zero cost when the module is disabled, cache first when enabled, at most one targeted query on a cold miss.

---

## 20. Proposed security architecture

Threat by threat:
- Open redirect: send only through validated destinations. Use `wp_safe_redirect` semantics. Allow external hosts only through an explicit allowlist. Never pass request input straight into the redirect location.
- Unsafe URL schemes: validate against WordPress allowed protocols, and reject `javascript:`, `data:`, `vbscript:`, and protocol relative forms before storage and again before sending.
- Redirect loops and chains: detect loops at save time for exact matches, and stop after one hop at runtime. Log a bounded diagnostic for administrators only.
- Regex denial of service and malicious regex: cap the number of regex rules and the pattern length, require anchored patterns, strip control characters, and check `preg_last_error`. Prefer safe string matching so the common cases never reach the regex engine.
- Cross site request forgery: a nonce on every create, update, delete, bulk, import, export, and clear action, verified server side.
- Unauthorized access: a capability check on every page and action. Start with `manage_options` and keep a dedicated capability as a later option.
- SQL injection: prepared statements with placeholders on every query, no string interpolation of user data.
- Cross site scripting: escape all output, sanitize all input, treat stored referer and user agent as untrusted.
- 404 log flooding: dedupe in simple mode, exclude rules, ignore query parameters, a per request write cap, and a hard row cap with oldest first pruning. Optionally sample, with a setting.
- Privacy: by default store no IP, truncate referer and user agent to 255, make advanced fields opt in, provide one click purge and export, and document retention in the readme privacy section. No telemetry and no external requests.
- Query string leakage: when ignoring query parameters, store the path without the query. When preserving, keep the behavior explicit and documented.
- File system risk: none, because server file sync is excluded.

---

## 21. Proposed test plan

Unit tests:
- Matching: exact, prefix, contains, suffix, wildcard, and regex, each with positive and negative cases.
- Normalization: trailing slash, case, subdirectory install, query string, encoded characters, and homepage blocking.
- Destination validation: allowed schemes, blocked schemes, external host allowlist, and relative expansion.
- Loop detection at save and the runtime one hop guard.
- Regex guards: count cap, length cap, anchoring requirement, control character stripping, and error handling.
- CSV import parsing and CSV export formatting, including the column contract and invalid rows.
- 404 URI normalization, dedupe increment, exclusion rules, and ignore query parameters.
- Prune policy: by age, by count, oldest first, and never truncate.

Integration and request lifecycle tests:
- Hit path: cache hit issues zero rules table queries.
- Cold miss: exactly one targeted query, then a warm cache.
- Module off: zero hooks, proven like the existing module gate test.
- Redirect fires before canonical and template resolution.
- 404 logging excludes admin, AJAX, REST, cron, sitemap 404s, and 410 and 451.

Rendered output tests:
- The Location header and status code for each supported code.
- The 404 log admin row markup and the create redirect link.

Security tests:
- Nonce failure on each mutation is rejected.
- Capability failure is rejected.
- Blocked schemes are refused at storage and at send time.
- Prepared statement usage is exercised by the storage layer tests.

Performance tests:
- Bounded query count on hit and miss, using a fake `$wpdb` that counts queries.
- Cache table and 404 log growth caps are enforced.

Admin behavior tests:
- Save redirect through the post, redirect and nonce pattern already used by existing pages.
- Bulk actions.
- Search, sort, and pagination wiring.

Migration tests:
- Tables are created only when the module is enabled, and the ledger advances.
- Migration is idempotent.

Uninstall tests:
- Tables and options are purged when the purge setting is on.

Backward compatibility tests:
- The module off by default, and existing behavior unaffected when off.

Browser testing required:
- Editor and admin screens for usability and accessibility.
- The 404 to redirect flow end to end.
- Frontend redirect behavior with a real theme and a page cache plugin present, because cache interaction is a known failure mode in rivals.

---

## 22. Manual verification plan

The owner should be able to verify, once implemented and only after approval:
- Create a redirect from the admin and confirm it redirects on the frontend with the correct status code.
- Edit a redirect and confirm the change takes effect.
- Delete a redirect and confirm it stops redirecting.
- Enable and disable a redirect.
- Validate bad input: empty source, homepage source, unsafe destination scheme, external destination without allowlist, malformed regex, and a loop.
- Confirm the frontend behavior for 301, 302, 307, 410, and 451.
- Confirm cache behavior: first hit warms, second hit is served from cache.
- Create a 404 by visiting a missing URL, confirm it appears in the log, confirm a repeat hit increments the counter rather than adding a row.
- Use the per row create redirect action and confirm the redirect source is prefilled.
- Confirm excluded paths and keywords stay out of the log.
- Confirm query parameter handling matches the setting.
- Exercise bulk actions on both lists.
- Confirm the prune policy: old rows drop first, the log never fully truncates, and counts stay within the limit.
- Confirm performance on the 30k post site: no measurable frontend regression with the module on, and zero cost with it off.
- Confirm security: nonce and capability failures are rejected, and no unsafe redirect can be created.
- Confirm uninstall purge behavior for the new tables.

Nothing in this list is claimed as verified today. It is a plan for after implementation.

---

## 23. Open questions

1. The blueprint says Rank Math free CSV is export only. Current research says Rank Math CSV import and export is premium. Confirm and correct the blueprint and feature matrix wording.
2. Multiple source URLs per rule: the blueprint omits it. Confirm that separate rules are the intended way to express many to one, and that no serialized blob will be introduced.
3. 404 advanced fields (referer, user agent): confirm they are opt in and truncated, and confirm no IP is ever stored.
4. Query string preservation default: confirm default on, with a setting, and confirm the exact normalization rules.
5. Scheduled activation and expiration: confirm whether to include now (cron free evaluation at match time) or defer.
6. 404 log defaults: confirm retention days and max rows (for example 30 days and 1000 rows).
7. Regex policy: confirm the cap on regex rule count, the pattern length limit, and the requirement to anchor.
8. Slug change watcher scope: confirm it covers posts and pages first, with CPTs and taxonomies later.
9. Admin list implementation: confirm plain WordPress markup first, with `WP_List_Table` only if the redesign phase requires it.
10. 404 to redirect dependency: confirm the 404 module requires the Redirects module for its per row redirect action, or degrades gracefully when redirects is off.
11. Cache table purpose in a RankKernel context: confirm whether the cache table is needed at all when an object cache is present, or whether it is mainly the no object cache fallback.
12. Import from rivals: confirm it belongs to the later importer phase, not to this module.

---

## 24. Recommended implementation sequence

Only after owner approval. Each step lands with tests and green gates, on a new issue branch, no direct main merges.

1. Migration and tables. Register migrations that create `wp_rankkernel_redirects` and `wp_rankkernel_redirects_cache` when the Redirects module is enabled, and `wp_rankkernel_404_log` when the 404 module is enabled. Idempotent, ledger tracked, module off means no tables.
2. Redirects module skeleton. Implement `ModuleInterface`, register a settings option with `autoload=no`, add the module to `Plugin::registerCoreServices`, and prove zero hooks when off.
3. Redirect storage and exact matching. Implement the repository, the normalization and hashing, the exact match path, and the redirect send with destination validation. Add the cache first lookup and the single cold miss query.
4. Redirect admin. Add the Tools submenu page with the native save pattern, the list, add, edit, delete, search, status views, sorting, pagination, and bulk actions.
5. CSV import and export, free.
6. Slug change watcher with a disable setting.
7. Prefix, contains, suffix, and wildcard matching, then guarded regex with the safety caps.
8. Loop detection at save and the runtime one hop guard.
9. 404 Monitor module skeleton and storage with dedupe, then the logger with all exclusions and the write cap.
10. 404 admin list, per row create redirect, bulk actions, clear log, and the prune policy.
11. Security hardening pass across both modules, with the full security test set.
12. Performance pass with query count tests and the growth caps, then the browser and manual verification plan for the owner.

---

End of research report. No implementation performed. No Redirects or 404 code created. Awaiting owner decision: approved as proposed, approved with changes, research more, reject some functionality, or add functionality.
