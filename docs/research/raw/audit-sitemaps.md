# Sitemaps Module Audit (rankkernel)

Scope: `src/Modules/Sitemaps/` (Router, IndexBuilder, SitemapCache, SitemapSettings, SitemapsModule, XslStylesheet, all providers, `sitemap.xsl`), the 15 sitemap test files under `tests/Unit/`, and the claimed state in `docs/ROADMAP.md` + `docs/competitor-analysis/feature-parity-matrix.md` / `feature-parity-master.md`.

AUDIT ONLY — no source or test file was modified.

---

## Verdict

The XML Sitemaps module is substantially real: routing, providers, XSL, core-sitemap takeover, plain-permalink forms, `/sitemap.xml` 301, robots.txt directive and settings wiring all exist and are covered by unit tests, and the disabled-module gating genuinely costs nothing beyond one `get_option` read. But the parity matrix overstates three rows and one row is outright false: there is **no search-engine ping or submission of any kind** — `rankkernel/sitemap/ping` is a `do_action` with no listener in `src/` (`SitemapsModule.php:249`), which the ROADMAP itself admits is "cache-warm only, never claim engine ping", so matrix row 234 `DONE` is a misreport. Caching is default-on but **incompletely invalidated**: there is no `deleted_post` hook (`SitemapCache.php:223-233`), so permanently deleting a post leaves its URL advertised in the cached sitemap until an unrelated event bumps a validator; term-relationship changes (`set_object_terms`) and direct writes to `rankkernel_sitemap_settings` are likewise unhooked. The cache **can and does serve stale data after a content change**. Two further adversarial defects were found: (1) URLs containing `&` are double-escaped (`esc_url` emits `&#038;`, then `htmlspecialchars` re-escapes the `&` to `&amp;`, `IndexBuilder.php:237,247-249,304,318,327`), corrupting every `<loc>` and the `<?xml-stylesheet?>` href that carries multiple query args (all paginated plain-permalink sitemaps); (2) noindex exclusion is a fragile serialized-substring `LIKE` (`s:5:"index";b:0`) that only matches serialized rows and would silently leak noindex content stored as legacy JSON. Most of the above is invisible to the test suite because the tests stub `esc_url` as `filter_var` (never reproducing WordPress's `&#038;` substitution) and no test deletes a post or changes a term relationship.

---

## Functionality Table

| Module | Functionality | Expected Behavior | Current Code | Status | Evidence | Gap |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Sitemaps | Rewrite rules (index, numbered sets, XSL) | `sitemap_index.xml`, `*-sitemapN.xml`, `sitemap.xsl` resolve to query vars | `Router.php:120-125` | COMPLETE | `RouterTest::test_rewrite_rules_registered_on_init` | none |
| Sitemaps | Query vars (prefixed only) | Register `rankkernel_sitemap[_n/_xsl]`, never collide with core's `sitemap` | `Router.php:137-143` | COMPLETE | `RouterTest::test_query_vars_added`; `RouterPlainModeTest::test_query_vars_are_prefixed_only` | none |
| Sitemaps | `pre_get_posts` interception + `exit` | Main-query intercept, render XML, `exit` in production | `Router.php:150-261,266-270` | COMPLETE | `RouterTest::test_intercept_echoes_xml_and_exits` | `exit` suppressed only under `RANKKERNEL_TESTING`; correct for prod |
| Sitemaps | Main-query guard (recursion) | Inner queries never render (no memory blowup) | `Router.php:154-156` | COMPLETE | `RouterTest::test_intercept_ignores_non_main_queries` | none |
| Sitemaps | `redirect_canonical` suppression | Return false whenever a sitemap var is present | `Router.php:288-298` | COMPLETE | `RouterTest::test_canonical_filter_false_on_sitemap_vars`; `RouterPlainModeTest::test_canonical_disabled_for_plain_vars` | none |
| Sitemaps | Theme action stripping | Strip theme output so only XML is emitted | `Router.php:182-184` (only `remove_all_actions('wp_footer')`) | PARTIAL | `RouterTest` setUp stubs `remove_all_actions` `justReturn`; no assertion on which hooks | `wp_head`/other template actions not stripped; safety depends solely on `exit` after echo |
| Sitemaps | `/sitemap.xml` 301 → index | Legacy short URL redirects, never 404s | `Router.php:158-169` | COMPLETE | `RouterPlainModeTest::test_sitemap_xml_redirects_to_index` | only exact lower-case `sitemap.xml` (trimmed) |
| Sitemaps | Plain-permalink URL forms | Prefixed GET forms for index/set/XSL | `Router.php:55-103` | COMPLETE | `RouterPlainModeTest::test_plain_url_forms`; `IndexBuilderRouterTest::test_plain_router_drives_index_locs_and_pi_href` | see XML-escaping defect for page >1 |
| Sitemaps | XSL request route | Serve stylesheet on `_xsl` var / rewrite | `Router.php:124,186-194` | COMPLETE | `RouterTest::test_intercept_renders_xsl` | none |
| Sitemaps | Posts provider | Public types, no attachments, published, not password/noindex | `PostsProvider.php:63-88,96-235` | COMPLETE | `SitemapAuthorsAttachmentsTest::test_attachment_sets_filtered_and_short_circuited`; `SitemapExclusionsTest::test_password_post_excluded_from_entries_and_counts` | attachment entries intentionally omitted |
| Sitemaps | Taxonomies provider | Public taxonomies, terms with published posts (or all when include-empty) | `TaxonomiesProvider.php:63-82,93-176` | COMPLETE | `SitemapSettingsProvidersTest::test_taxonomy_toggle_off_removes_set_and_zeroes_count`; `SitemapExclusionsTest::test_noindex_term_excluded_from_entries_and_counts` | none |
| Sitemaps | Authors provider | Authors of public posts; include-empty lists post-less users | `AuthorsProvider.php:42-106,143-215` | COMPLETE | `SitemapAuthorsAttachmentsTest::test_author_with_only_non_public_posts_is_absent`; `SitemapSettingsProvidersTest::test_authors_include_empty_true_lists_post_less_user` | author noindex deferred (documented `AuthorsProvider.php:20-21`) |
| Sitemaps | Direct `$wpdb` listing | Provider queries run against `$wpdb`, `prepare()`d | `PostsProvider.php:116-125,158-171` | COMPLETE | `SitemapExclusionsTest` (fake wpdb asserts LIKE fragment in prepared SQL) | none |
| Sitemaps | Attachment exclusion | Attachments never in sets/entries/counts | `PostsProvider.php:71-77,97-99,139-141` | COMPLETE | `SitemapAuthorsAttachmentsTest::test_attachment_sets_filtered_and_short_circuited` | none |
| Sitemaps | 1000 URLs/page | Default 1000, filter override, clamp 1–50000 | `IndexBuilder.php:151-170`; `SitemapSettings.php:62-63` | COMPLETE | `SitemapSettingsProvidersTest::test_index_builder_per_page_honors_settings_then_filter`; `IndexBuilderTest::test_index_page_count_math` | none |
| Sitemaps | Pagination slicing + range 404 | LIMIT/OFFSET slices; page > page-count 404s | `PostsProvider.php:149-163`; `Router.php:229-247` | COMPLETE | `RouterTest::test_intercept_404s_page_out_of_range`; `IndexBuilderTest::test_index_page_count_math` | page-count from `getCount` ignores canonical drops (documented approximation) |
| Sitemaps | lastmod: posts | W3C date from `post_modified_gmt`, GMT (translate=false) | `PostsProvider.php:201-206` | COMPLETE | `SitemapExclusionsTest` seeds `post_modified_gmt`; `IndexBuilderTest::test_entries_xml_with_lastmod_and_image` | none |
| Sitemaps | lastmod: taxonomy | `MAX(p.post_modified_gmt)` or current time for empty terms | `TaxonomiesProvider.php:216,273-290` | COMPLETE | `SitemapSettingsProvidersTest::test_include_empty_terms_true_lists_zero_post_term` | empty-term lastmod changes every build |
| Sitemaps | lastmod: authors | `MAX(post_modified_gmt)` or `user_registered` | `AuthorsProvider.php:190-205` | COMPLETE | `SitemapAuthorsAttachmentsTest`; `SitemapSettingsProvidersTest::test_authors_include_empty_true_lists_post_less_user` | none |
| Sitemaps | lastmod: index | Index `<lastmod>` reflects content recency | `IndexBuilder.php:248` uses `current_time('mysql', true)` | PARTIAL | `IndexBuilderTest::test_index_xml_shape_one_sitemap_per_populated_set` (asserts shape only) | index lastmod is build time, not content-derived; changes on every cache miss |
| Sitemaps | Images: featured + content + gallery | Featured first, inline `<img>` (post-`do_blocks`), gallery ids, same-host, dedupe, cap 100 | `PostsProvider.php:208-224,274-365` | COMPLETE | `SitemapSettingsProvidersTest::test_content_images_parsed_featured_first_deduped`, `test_dynamic_blocks_render_before_image_parse`, `test_gallery_shortcode_ids_resolve` | none |
| Sitemaps | Images: external / data URI exclusion | External-host and `data:` images skipped | `PostsProvider.php:341-365` | COMPLETE | `SitemapSettingsProvidersTest::test_external_and_data_images_skipped` | none |
| Sitemaps | Attachment image-sitemap entries | Media attachments listed as image entries | none | MISSING | `ROADMAP.md:253` ("2.1 left these partial"); `PostsProvider.php:71-77` | matrix `feature-parity-matrix.md:228` marks image entries DONE; roadmap says partial |
| Sitemaps | Noindex exclusion (posts/terms) | Exclude content with `robots.index=false` | `PostsProvider.php:39,111-120`; `TaxonomiesProvider.php:39,121-133` | PARTIAL | `SitemapExclusionsTest::test_noindex_post_excluded_from_entries_and_counts`, `test_noindex_term_excluded_from_entries_and_counts` | matches only serialized `s:5:"index";b:0`; legacy JSON payloads in same meta key bypass; global/context noindex not derived |
| Sitemaps | Password-protected posts excluded | `post_password = ''` filter on counts + entries | `PostsProvider.php:117,159` | COMPLETE | `SitemapExclusionsTest::test_password_post_excluded_from_entries_and_counts` | none |
| Sitemaps | Exclude posts/terms settings | ID lists excluded from counts + entries, chunked ≤500 | `PostsProvider.php:242-260,398-418`; `TaxonomiesProvider.php:368-419` | COMPLETE | `SitemapSettingsProvidersTest::test_exclude_posts_absent_from_entries_and_counts`, `test_exclude_posts_chunked_beyond_500`, `test_exclude_terms_absent_from_entries_and_counts` | none |
| Sitemaps | Per-type toggles | Disabled pt/tax vanish from sets, counts, entries | `SitemapSettings.php:160-169`; `PostsProvider.php:79-85`; `TaxonomiesProvider.php:73-79` | COMPLETE | `SitemapSettingsProvidersTest::test_post_type_toggle_off_removes_set_and_zeroes_count`, `test_taxonomy_toggle_off_removes_set_and_zeroes_count` | none |
| Sitemaps | Author role/user exclusions | Exclude by user id (NOT IN) and role (`capabilities` LIKE) | `AuthorsProvider.php:312-405` | COMPLETE | `SitemapSettingsProvidersTest::test_authors_excluded_roles_and_users_absent` | role exclusion not yet in settings UI? UI exists `SitemapSettingsPage.php:198-234` |
| Sitemaps | Canonical mismatch drop | Rows whose stored canonical differs are dropped | `PostsProvider.php:177,430-554`; `TaxonomiesProvider.php:243,432-556` | PARTIAL | `SitemapCanonicalTest::test_post_canonical_matrix`, `test_term_canonical_matrix` | counts stay unfiltered (pages map over-counts); relative canonicals drop valid URLs; extra batched meta query per page |
| Sitemaps | Core sitemap takeover | `wp_sitemaps_enabled` false + admin notice | `SitemapsModule.php:154,161,225-233` | COMPLETE | `SitemapsModuleTest::test_boot_registers_takeover_filter_and_notice` | unconditional once booted; no fallback if own routes fail |
| Sitemaps | XSL served with cache headers | `text/xsl`, 1-year cache, version query busts | `XslStylesheet.php:31-56`; `IndexBuilder.php:237` | COMPLETE | `SitemapsModuleTest::test_xsl_uses_namespaced_xpaths`, `test_xsl_shows_counts_backlink_and_image_counts` | `output()` calls `header()` without `headers_sent()` guard (safe at `pre_get_posts` timing) |
| Sitemaps | Cache default ON | Caching on unless filtered off | `SitemapCache.php:68-77` | COMPLETE | `SitemapCacheTest::test_default_on_cache_hit_skips_builder`, `test_enable_cache_false_bypasses_cache` | none |
| Sitemaps | Object cache + transient fallback | `wp_cache_*` when ext cache, else transients | `SitemapCache.php:341-378` | COMPLETE | `SitemapCacheTest::test_default_on_cache_hit_skips_builder` (transient path) | `set_transient(...,0)` stores non-expiring option; autoload/performance concern (see gaps) |
| Sitemaps | Invalidation: post save | `save_post` bumps global + post type | `SitemapCache.php:224,242-248` | COMPLETE | `SitemapInvalidationTest::test_register_hooks_covers_user_and_term_events` | none |
| Sitemaps | Invalidation: term edit/delete/clean | `edited_terms`, `delete_term`, `clean_term_cache` bump global + taxonomy | `SitemapCache.php:225-227,256-292` | COMPLETE | `SitemapInvalidationTest::test_clean_term_cache_queues_global_and_taxonomy`, `test_deleted_term_queues_global_and_taxonomy` | none |
| Sitemaps | Invalidation: term relationships | Attach/detach terms to posts invalidates taxonomy set | none | MISSING | `SitemapCache.php:223-233` has no `set_object_terms`/`added_term_relationship` | stale term membership after programmatic `wp_set_object_terms` |
| Sitemaps | Invalidation: user change | register/delete/profile_update bump global + authors | `SitemapCache.php:228-230,299-322` | COMPLETE | `SitemapInvalidationTest::test_delete_user_queues_global_and_authors`, `test_profile_update_queues_global_and_authors` | none |
| Sitemaps | Invalidation: post deletion | Permanent delete invalidates sitemap | none | MISSING | `SitemapCache.php:223-233` has no `deleted_post`/`delete_post` | **stale**: deleted URL stays advertised until an unrelated bump |
| Sitemaps | Invalidation: settings change | Changing sitemap settings rebuilds cache | admin save calls `invalidateAll()` (`SitemapSettingsPage.php:265`); no option hook; `SitemapCache.php:231-232` covers only `rankkernel_settings`/`rankkernel_modules` | PARTIAL | `SitemapSettingsCacheTest::test_invalidate_all_bumps_global_validator_exactly_once` | programmatic/REST `update_option('rankkernel_sitemap_settings')` does not invalidate |
| Sitemaps | Invalidation: module toggle | Enable/disable modules invalidates | `SitemapCache.php:232` | COMPLETE | `SitemapInvalidationTest::test_register_hooks_covers_user_and_term_events` | re-enabling module after off-period content changes can serve a pre-disable cache until next save |
| Sitemaps | Ping: search-engine submission | Ping/submit sitemaps on publish | none (`do_action` with no listener) | MISSING | `SitemapsModule.php:242-251`; grep finds no `add_action('rankkernel/sitemap/ping'` in `src/`; `ROADMAP.md:75` | matrix `feature-parity-matrix.md:234` marks this DONE — false |
| Sitemaps | Ping: publish hook point | Fire `rankkernel/sitemap/ping` on draft→publish only | `SitemapsModule.php:242-251` | COMPLETE | `SitemapsModuleTest::test_ping_fires_only_on_publish` | hook point only, no default consumer and no cache warm |
| Sitemaps | robots.txt Sitemap directive | Strip stale lines, append index URL, skip private blog | `SitemapsModule.php:159,198-220` | COMPLETE | `SitemapRobotsDirectiveTest::test_replaces_core_sitemap_line`, `test_private_blog_untouched`, `test_appends_when_missing_and_stays_idempotent` | strips *all* third-party Sitemap lines, not just core's |
| Sitemaps | HTTP 404 for unknown set / bad page | No empty 200 urlset advertised | `Router.php:235-247` | COMPLETE | `RouterTest::test_intercept_404s_unknown_set`, `test_intercept_404s_page_out_of_range` | none |
| Sitemaps | HTTP headers on success | `application/xml` + `X-Robots-Tag: noindex, follow` | `Router.php:275-280` | COMPLETE | `RouterTest::test_intercept_renders_known_set` (render); header send not asserted | headers only on 200 path |
| Sitemaps | Malformed / invalid requests | Sensible status for garbage input | `Router.php:196-247` | PARTIAL | `RouterTest::test_intercept_404s_unknown_set` | `rankkernel_sitemap_n` alone suppresses canonical (`Router.php:293`) yet renders nothing; `index` ignores page param |
| Sitemaps | Sitemap index generation | One entry per populated set page | `IndexBuilder.php:234-262` | COMPLETE | `IndexBuilderTest::test_index_xml_shape_one_sitemap_per_populated_set`, `test_empty_set_skipped` | empty site yields valid empty `<sitemapindex>` |
| Sitemaps | XML escaping / validity | `<loc>`/hrefs correct and well-formed | `IndexBuilder.php:237,247-249,304,318,327` | PARTIAL | no test reproduces real `esc_url`; tests stub `filter_var` | `esc_url` emits `&#038;` then `htmlspecialchars` re-escapes → `&amp;#038;` (double-escaped URLs on `&`); corrupts paginated plain-mode locs and XSL href |
| Sitemaps | Disabled module costs nothing | No hooks, tables or work when off | `ModuleManager.php:132-162` (skip); `Plugin.php:162-163` | COMPLETE | `ModuleManagerTest::test_module_off_boot_never_called_zero_hooks` | one `get_option` (enable map) unavoidable | 
| Sitemaps | Admin settings + save | Nonce/cap-guarded save, merge, invalidate | `SitemapSettingsPage.php:243-273` | COMPLETE | `SitemapSettingsAdminTest::test_save_general_saves_invalidates_once_and_redirects`, `test_invalid_nonce_no_save_wp_die`, `test_missing_caps_wp_die` | none |
| Sitemaps | Large datasets | 50k clamp, chunked NOT IN, bounded queries | `IndexBuilder.php:165-167`; `PostsProvider.php:408` | PARTIAL | no >1000-row or load test exists | canonical drop adds a per-page meta query; no scale verification |
| Sitemaps | HTML sitemap | Shortcode + dedicated page | none | MISSING | `ROADMAP.md:147,341` (1.7.3 micro-gap) | matrix `feature-parity-matrix.md:235` = PLANNED (roadmap step) |
| Sitemaps | Custom URL injection filter | Filter for third-party endpoints | none | MISSING | `ROADMAP.md:148,342` (1.7.3 micro-gap) | matrix `feature-parity-matrix.md:237` = MISSING (no filter anywhere) |

---

## Matrix overstatement check

Rows in `docs/competitor-analysis/feature-parity-matrix.md` (and `-master.md`) whose status does not match the code:

| Matrix row | Matrix says | Code reality | Correct status | Citation |
| :--- | :--- | :--- | :--- | :--- |
| `feature-parity-matrix.md:234` Search engine ping or submission | DONE | No ping and no submission exists; only a listener-less `do_action` | MISSING | `SitemapsModule.php:249`; `ROADMAP.md:75` explicitly says "cache-warm only, never claim engine ping" |
| `feature-parity-matrix.md:228` Image entries in sitemap | DONE | Featured + inline + gallery images are done, but attachment image-sitemap entries and image `title`/`caption` are absent; roadmap marks 2.1 image work "partial" | PARTIAL | `PostsProvider.php:208-224`; `ROADMAP.md:253` |
| `feature-parity-matrix.md:231` Sitemap exclusion rules | DONE | Noindex matching is a fragile serialized `LIKE`; term-relationship changes and deleted posts are not invalidated | PARTIAL | `PostsProvider.php:39,111-120`; `SitemapCache.php:223-233` |
| `feature-parity-matrix.md:230` Sitemap caching | DONE | Default-on caching is real but invalidation is incomplete (no `deleted_post`, no `set_object_terms`, no sitemap-settings option hook) | PARTIAL | `SitemapCache.php:223-233` |
| `feature-parity-master.md:980` "Cache ON … validator invalidation" | DONE | Same invalidation holes as above | PARTIAL | `SitemapCache.php:223-233` |
| `feature-parity-matrix.md:224-227,229,232,233` index/per-type/tax/author/XSL/robots/takeover | DONE | Verified against code | DONE (accurate) | see table rows |
| `feature-parity-matrix.md:235` HTML sitemap | PLANNED | No code; elsewhere identical situations are labelled MISSING | PLANNED is defensible but inconsistent with `:237` (MISSING) | `ROADMAP.md:341` |

Citation drift in `feature-parity-master.md` §5.4 (statuses broadly OK, evidence refs stale):
- `:978` cites `Router.php:150-330`, but the file is 299 lines and the intercept ends at `Router.php:261`.
- `:979` cites `/sitemap.xml` 301 at `Router.php:236-244`; it is actually `Router.php:158-169`.
- `:980` cites `SitemapCache.php:60-78,381-406`; actual is `isEnabled` `SitemapCache.php:68-77` and key/validator helpers `:387-409`.
- `:974` cites `IndexBuilder.php:177-232`; `getSetsWithPageCounts` is `:177-227`.
- `:977` "Images inline from featured images" cites `PostsProvider.php:18-22` (a docblock) and understates the feature (content + gallery too).

---

## Cache correctness analysis

**Storage/validation.** `SitemapCache::get()` reads a payload, then compares the cached `validator_global` / `validator_set` against option values and rebuilds on mismatch (`SitemapCache.php:87-111`). The map payload is global-validator-only (`SitemapCache.php:122-149`). Validators are opaque `time()_uniqid()` strings written at `shutdown` by `flushQueue()` (`SitemapCache.php:173-207`). Settings save bumps the global validator synchronously via `invalidateAll()` (`SitemapSettingsPage.php:265`, `SitemapCache.php:216-218`). A plugin-version bump also forces a global rebuild (`SitemapsModule.php:169-174`). Cache keys are `xml_<set>_<page>` / `map_sets_1` (`SitemapCache.php:387-400`).

**Coverage.** Hooked: `save_post`, `edited_terms`, `delete_term`, `clean_term_cache`, `user_register`, `delete_user`, `profile_update`, `update_option_rankkernel_settings`, `update_option_rankkernel_modules` (`SitemapCache.php:223-233`).

**Gaps (staleness windows):**
1. **Permanent post deletion** — no `deleted_post` / `delete_post` hook. `wp_delete_post()` does not fire `save_post`, so the global validator is not bumped and the cached XML keeps the deleted permalink until some unrelated post/term/user/settings event fires. This answers the audit question directly: **post save, term change and user change are covered; post *deletion* is NOT.**
2. **Term relationships** — `wp_set_object_terms()` fires `set_object_terms` / `added_term_relationship` / `deleted_term_relationships`, none hooked. A term's post set (and thus a term sitemap) can change with no invalidation.
3. **Settings option** — the cache registers `update_option_rankkernel_settings` and `update_option_rankkernel_modules` but **not** `update_option_rankkernel_sitemap_settings`; only the admin POST handler invalidates. Any programmatic/REST/WP-CLI write to `SitemapSettings::OPTION` leaves stale XML.
4. **Module re-enable** — while disabled the module registers no hooks, so content changes made during the off-period never bump validators; on re-enable the version check only rebuilds if the plugin version changed (`SitemapsModule.php:169-174`), otherwise pre-disable cached XML can be served until the next content event.
5. **Transient fallback durability** — `set_transient($key,$payload,0)` (`SitemapCache.php:377`) writes a non-expiring `_transient_` option, which `set_transient` adds with autoload `yes` when it does not yet exist. Large per-page XML blobs can therefore be autoloaded into every request on sites without a persistent object cache.

**Net answer: yes — the cache can serve stale data after a content change**, specifically after a permanent post deletion (highest impact), a term-relationship change, a direct settings write, or a module re-enable.

---

## Gap classification

- **P0** — none. No security, data-loss or site-breaking defect found in the sitemap module.
- **P1** — Cache serves stale sitemaps after permanent post deletion: `SitemapCache.php:223-233` never hooks `deleted_post`, so dead URLs stay advertised until an unrelated event.
- **P1** — Double-escaped ampersands corrupt `<loc>` and the XSL href whenever a URL carries `&`: `IndexBuilder.php:237,247-249,304,318,327` run `esc_url` (which emits `&#038;`) through `htmlspecialchars` again; breaks all page>1 plain-permalink locs.
- **P1** — Missing search-engine ping/submission while the parity matrix marks it DONE: `SitemapsModule.php:242-251` + `feature-parity-matrix.md:234`; ROADMAP schedules IndexNow as P1 (`ROADMAP.md:292`).
- **P2** — Term-relationship changes (`set_object_terms`) do not invalidate taxonomy sets: `SitemapCache.php:223-233`.
- **P2** — No `update_option_rankkernel_sitemap_settings` invalidation hook: `SitemapCache.php:231-232`; stale after non-admin settings writes.
- **P2** — Noindex exclusion is a fragile serialized substring that misses legacy JSON payloads and any context/global noindex: `PostsProvider.php:39`, `TaxonomiesProvider.php:39`.
- **P2** — Index `<lastmod>` is build time, not content-derived: `IndexBuilder.php:248`.
- **P2** — Attachment image-sitemap entries absent while matrix marks image entries DONE: `PostsProvider.php:71-77`; `ROADMAP.md:253`.
- **P2** — Canonical mismatch filtering leaves counts unfiltered (page map over-counts → phantom pages) and adds a meta query per page: `PostsProvider.php:425-430`.
- **P3** — HTML sitemap missing: `ROADMAP.md:341`.
- **P3** — Custom URL injection filter missing: `ROADMAP.md:342`.
- **P3** — Theme action stripping limited to `wp_footer`: `Router.php:182-184`.
- **P3** — Transient fallback writes non-expiring autoloaded options: `SitemapCache.php:377`.
- **P3** — `rankkernel_sitemap_n` alone suppresses canonical yet renders nothing: `Router.php:293`.
- **P3** — Matrix/roadmap citation drift in `feature-parity-master.md` §5.4 (`:978-980`).

---

## Tests that would be needed

1. **Cache staleness on post deletion** — fire `deleted_post` (and `wp_delete_post`) and assert the global validator bumps / the cached set rebuilds. Currently absent; proves the P1.
2. **Cache staleness on `set_object_terms`** — attach/detach a term and assert the taxonomy sitemap invalidates.
3. **Settings-option invalidation** — `update_option('rankkernel_sitemap_settings', …)` must bump the global validator without going through the admin page.
4. **Real-`esc_url` escaping test** — use WordPress's actual `esc_url` (not `filter_var`) and assert a URL containing `&` appears once-escaped in `<loc>` (catches the double-escape bug).
5. **Paginated plain-permalink loc test** — assert `post-sitemap` page 2 plain URL round-trips intact through `buildIndexXml`/`buildEntriesXml` with real escaping.
6. **Noindex leak test** — store `robots.index=false` as legacy JSON under `_rankkernel_meta_data` and assert the post is excluded (currently it is not).
7. **End-to-end XML well-formedness** — parse generated index and urlset with `DOMDocument::loadXML` and `libxml_get_errors()` to prove valid XML, including image-namespace entries and ampersand URLs.
8. **HTTP integration test** — a real WP integration test that requests `sitemap_index.xml`, `*-sitemap.xml`, an unknown set, an out-of-range page, `sitemap.xsl` and `/sitemap.xml`, asserting status codes (200/301/404) and headers (`application/xml`, `X-Robots-Tag`).
9. **Disabled-module zero-hook test at the Plugin level** — assert that with `sitemaps` off, no rewrite rule, `wp_sitemaps_enabled` filter, `robots_txt` filter or cache hooks are registered end-to-end (the existing proof `ModuleManagerTest::test_module_off_boot_never_called_zero_hooks` uses a generic mock, not `SitemapsModule`).
10. **Large-dataset/pagination stability test** — >1000 posts and terms split across pages, asserting no URL duplication or omission at page boundaries and that canonical-dropped rows do not create phantom pages.
11. **Core-takeover fallback test** — assert behavior when RankKernel's own rewrite rules are missing while `wp_sitemaps_enabled` is forced false.
12. **Module re-enable cache test** — change content while `sitemaps` is disabled, re-enable, and assert the served XML reflects the change.
