# Gap & Bottleneck Analysis — Yoast SEO & Rank Math

> Every claim below is code-verified; citations point into the competitor sources:
> `Y:` = `wp-content/plugins/wordpress-seo[-premium]/` · `RM:` = `wp-content/plugins/seo-by-rank-math/`
> Full evidence: [`yoast-audit.md`](./yoast-audit.md) · [`rankmath-audit.md`](./rankmath-audit.md)

---

## 1. Performance Bottlenecks

### Yoast (Free)

| # | Bottleneck | Evidence |
|---|---|---|
| Y1 | **DI container compiled on every frontend request** — Symfony container boots all conditionals/integrations before any HTML decision | `front-end-conditional.php:15-17` (`is_met() = !is_admin()`); whole `src/` architecture wired per request |
| Y2 | **Breadcrumb generator fires 4–6 extra indexable queries per page even when the theme never renders breadcrumbs** — used only for one schema property | `breadcrumbs-generator.php:94-197`; `get_ancestors()` → hierarchy + `where_in` query (`indexable-repository.php:461-488`) |
| Y3 | **Replace-vars engine re-resolves per presenter** — title/meta/OG/Twitter each re-run `replace()`; same `%%var%%` → repeat `get_terms()`/`get_post_meta()` calls, 4+ resolutions per page, no cross-presenter cache | `inc/class-wpseo-replace-vars.php:141-213`; `retrieve_category()` → `get_terms()` (`:336`) |
| Y4 | **Only the home-page indexable is object-cached** (5 min); posts/terms/archives re-query `wp_yoast_indexable` every request | `indexable-repository.php:264-279` (the only `wp_cache` usage) |
| Y5 | **Indexable rebuild landmine** — version mismatch turns a read into write+many-reads mid-request (post meta, term meta, hierarchy) | `upgrade_indexable()` (`indexable-repository.php:779-784`) → `Indexable_Builder::build()` |
| Y6 | **Block parsing on every request** — full `parse_blocks()` of `post_content` (CPU, scales with post length) | `meta-tags-context-memoizer.php:139-140` |
| Y7 | **Sitemap transient cache ships disabled** — `apply_filters('wpseo_enable_xml_sitemap_transient_caching', false)` | `class-sitemaps-cache.php:81-89` |
| Y8 | **Search-engine ping removed (v22)** — only self cache-warm via `wp_remote_get` to own sitemap index | `class-sitemaps-admin.php:66-68` (deprecated no-op); `class-sitemaps.php:486-492` |
| Y9 | **40-column `wp_yoast_indexable` table as central store** — every head render depends on it, incl. social/twitter/OG columns duplicated from postmeta | `src/config/migrations/20171228151840_WpYoastIndexable.php` + column migrations |
| Y10 | **`wpseo_taxonomy_meta` single autoloaded serialized option** — every term's SEO meta in one blob, loaded on every request | `inc/options/class-wpseo-taxonomy-meta.php` (registry `class-wpseo-options.php:27-35`) |
| Y11 | **Dead code in hot path** — `fb:app_id` property computed through presentation layer but never emitted by any presenter | `indexable-presentation.php:45`; no `generate_open_graph_fb_app_id()` exists |

### Rank Math (Free)

| # | Bottleneck | Evidence |
|---|---|---|
| RM1 | **Redirect lookup on EVERY frontend request** — `do_redirection` on `wp` priority 11 → 1 query to `rank_math_redirections_cache`, on miss 1+ to `rank_math_redirections`; also increments `hits` on match | `class-redirections.php:41-45`; `class-redirector.php:224-262`; `class-redirector.php:121-146` |
| RM2 | **Regex matching is a serialized-scan** — `match_redirections` builds `LIKE` over serialized `sources` and can fall back to full active scan | `class-db.php:114-212` |
| RM3 | **All 4 settings groups autoload on every request** — general (huge: 404/links/imageseo/woocommerce folded in), titles (per-PT/per-tax keys), sitemap, instant-indexing | `class-installer.php` `add_option` calls without autoload param → default `yes` |
| RM4 | **Schema stored as multiple serialized `rank_math_schema_*` meta rows per object** — read + merge cost per render, no object-cache layer | `includes/modules/schema/class-db.php` (whereLike `rank_math_schema`) |
| RM5 | **404 monitor truncates the whole table at limit** (default 100) — loses history instead of pruning | `class-db.php:84-103` (`clear_logs` truncate) |
| RM6 | **Sitemap "ping" is not a ping** — `hit_index` only `wp_remote_get`s its own index; discovery relies on robots.txt directive | `class-cache-watcher.php:114-131`; `class-sitemap.php:159-161` |
| RM7 | **Site-wide analyzer phones home** — remote `rankmath.com/analyze` API dependency | seo-analysis module (see rankmath-audit §10) |
| RM8 | **Vendored Action Scheduler** ships for analytics jobs — DB tables + cron footprint even if analytics never enabled | `vendor/woocommerce/action-scheduler` (creation in installer) |

**Shared:** both plugins register dozens of REST routes and admin integrations unconditionally; both retain all data on uninstall by default (Y: `wp-seo-main.php:151` `__return_false`; RM: `rank_math_clear_data_on_uninstall` filter default false).

## 2. Architectural Weaknesses

| Area | Yoast | Rank Math |
|---|---|---|
| Codebase split | Legacy `inc/` + modern `src/` coexist; sitemaps still in `inc/sitemaps/` while head is in `src/` — two generations of architecture in one plugin | Single `includes/` namespace with god-classes (`Helper`, `Paper`), global function API (`rank_math_get_...`) |
| Dependency graph | Full Symfony DI container to render a `<title>`; presenters/presentation/context/memoizer layers of indirection for ~20 tags | Pro/plugin gating scattered (`probadge`/`upgradeable`/`disabled` flags vs `RANK_MATH_PRO_FILE` constant vs `Helper::is_pro()`) |
| Storage design | Premium redirects in **autoload=false options** — whole redirect set read per request, no queryable index | `rank-math-options-links/404/imageseo/woocommerce` don't exist — unrelated settings folded into `general`; schema as duplicated meta rows |
| Editor integration | `register_meta` with `auth_callback` (clean) but analysis split across REST + admin-ajax (used-keywords) | **No `register_meta` at all** — custom `POST /updateMeta` bulk endpoint bypasses WP meta registration (breaks headless/meta-API expectations) |
| Modules | No true module system — conditionals only; everything boots | Good `can_load_module()` gate (off module = zero instantiation — **the pattern worth copying**), but analytics + Action Scheduler tightly coupled into core |
| Extensibility | Deep filter surface (`wpseo_*`) but requires understanding presenter internals | Good module toggles, but Pro features stubbed as dead UI instead of isolated modules |

## 3. Bloat Areas (admin)

- **Yoast:** `admin-global` script+style enqueued on **all** admin pages, no screen condition (`admin/class-admin.php:279,290`); Premium-badge inline CSS injected globally (`menu-badge-integration.php`); notification center with persistence (`class-yoast-notification-center.php`); 2 dashboard widgets (Yoast + Wincher); Semrush/Wincher/HelpScout/MyYoast/tracking external calls; 40+ `yoast/v1` REST routes incl. workouts/AI/dashboard/introductions.
- **Rank Math:** Pro-notice banner variants + module CTA boxes + "Go Pro" dashboard widget + "Unlock PRO" plugin-action links; setup wizard footprint; analytics module (OAuth + Action Scheduler + DB tables) active by default; content-ai prompts cron; vendored Action Scheduler + donatj/UserAgent libs.
- **Both:** post-list columns (SEO score/readability/cornerstone/inclusive-language), admin-bar menus, bulk editors, import/export tooling always registered.

## 4. What We Build Better (design mandates derived from this audit)

| Audit finding | Our counter-design |
|---|---|
| Y1 DI boot per request | Plain PSR-4 classes + `ModuleManager` registry; no container compile; `plugins_loaded` → `init` two-phase boot |
| Y2 breadcrumbs always computed | Lazy schema-piece generation; breadcrumb computed only when output (block/widget/schema requested) |
| Y3 replace-var re-resolution | One `TagsReplacer::replace()` pass per field per request, memoized by `(context, field)` |
| Y4 single cached indexable | No indexable table; per-post single-key meta read = 1 query, WP meta cache handles repeats |
| Y5 rebuild mid-request | No derived store to rebuild — ever |
| Y7 sitemap cache off | Transient/object-cache sitemap cache **enabled by default** with validator-based invalidation (Yoast's validator design is good — keep it) |
| RM1 per-request redirect query | Redirect module OFF ⇒ zero hooks (RM's `can_load_module` pattern); ON ⇒ cache-first lookup, only match table on miss |
| Y10/RM3 autoload bloat | Flat settings array, single option, autoload=no for large payloads; settings split per module |
| 25–45 meta rows/post | One `_rankkernel_meta_data` row (single `get_post_meta` call) |
| Both: data retained on uninstall | Activation script + explicit "delete all data" uninstall option |
| Both: engine pings absent/removed | Real IndexNow integration free (RM proves demand; Yoast gates it in Premium) |
| Both: AI behind subscriptions | BYO-key client (OpenAI/Anthropic/Gemini) — user pays token cost only |
