# RankKernel Current Implementation Handoff

Inspection-only audit of the actual codebase (branch GH-11, unpushed). Every claim verified against code on disk. Generated 2026-09-10.

## 1. Project Status

| Item | Fact |
|---|---|
| Plugin version | **0.1.0** (`rankkernel.php` header; `Plugin::VERSION` const) |
| Plugin name | RankKernel – Free SEO & Schema Engine |
| Author | Maulik Bhalodiya |
| Requires | PHP **8.1+**, WordPress **6.5+** (enforced at boot + activation self-deactivate) |
| Current branch | **GH-11** (local feature branch, **unpushed**; `origin/main` is at `af9aab8`) |
| Git status | Clean tree (verified). Stale local branch `GH-7` still exists locally |
| Latest commit | `8437f51` Fix block asset URLs to derive from plugin root (#11) |
| Tests | **373 tests, 1390 assertions, ALL PASSING** (verified by full run). 45 test files + 5 support doubles. `phpcs` clean, `phpstan` level 6 clean (per repo tooling; not re-run in this inspection) |
| Directory layout | `rankkernel.php`, `uninstall.php`, `composer.json`, `phpcs.xml`, `phpstan.neon`, `phpunit.xml`, `readme.txt`, `README.md`, `CONTRIBUTING.md`, `ROADMAP.md`, `LICENSE`, `src/` (Plugin, Settings, Modules, Admin, Rest, Database), `assets/js/` (2 vanilla JS files), `tests/Unit/` |

**Completed:** bootstrap + hard-gated module system; SettingsStore; REST settings/modules; MigrationRunner; Metadata engine (head tags); XML sitemaps + cache + settings UI + human XSL view; Schema engine (25 pieces) + metabox + FAQ/HowTo blocks + schema settings; uninstall purge; conflict detection notice.

**Partially completed:** Admin design is functional-plain (no branded dashboard; Phase 4 design not done). Gutenberg work limited to 2 vanilla-JS blocks (no React suite). WooCommerce mapping exists behind a seam but Woo is absent here. REST API exists but admin UI does not use it.

**Not implemented (code confirms: zero classes):** breadcrumbs, importer, redirects, 404 monitor, instant-indexing, robots editor, image-seo, gutenberg suite, ai, headless (10 of 13 registry ids). No benchmarks, no WP.org submission artifacts, no branch protection evidence in repo.

**Blueprint alignment:** Yes, the repo follows `docs/architecture/blueprint.md` + `docs/ROADMAP.md` phase structure. Known deliberate deviations are documented in code comments (e.g., lazy evaluation instead of evaluateAll-once, `getMap` array cache, text/plain 404 bodies).

## 2. Architecture

**Bootstrap** — FILE: `rankkernel.php`. Procedural entry, ABSPATH guard, PHP/WP version checks before autoload, activation seeds + conflict detection (Yoast/RankMath/SEOPress), deactivation is a documented no-op. Two boot hooks: `plugins_loaded:10` → `Plugin::getInstance()->registerCoreServices()`; `init` (default priority) → `bootModules()`.

**Main class** — FILE: `src/Plugin.php`, CLASS: `final class Plugin`. Singleton via `getInstance()`. Service locator, explicitly NOT a DI container: `get(string $id): mixed` throws `RuntimeException` on unknown id. `VERSION = '0.1.0'` class const (deliberately used instead of global reads). `registerCoreServices()` builds SettingsStore, ModuleEnableMap, ModuleManager, both REST controllers, MigrationRunner (with `0.1.0` no-op baseline migration), wires admin only under `is_admin()`, registers metadata/schema/sitemaps modules, hooks `init:10` migrations, `evaluateAll()`, `rest_api_init` routes, text domain. `bootModules()` delegates to manager and populates `$this->modules` via `enabledModules()`.

**ModuleManager (hard gate)** — FILE: `src/Modules/ModuleManager.php`, CLASS: `final class ModuleManager`. METHODS: `register()`, `evaluateAll()` (once-flagged; delegates to injected map or per-module `isEnabled()`), `bootEnabled()` (re-evaluates late-registered modules, sorts by `getPriority()`, enforces `dependsOn()` firing `rankkernel/module/force_disabled`, calls `register()` then `boot()` only on enabled), `isOn()` (cached map only), `get()`, `enabledModules()`. HOW INITIALIZED: constructed with ModuleEnableMap in `registerCoreServices`. HOOKS: none directly (called from boot). DEPENDENCIES: ModuleInterface, ModuleEnableMap. CURRENT STATUS: complete, heavily tested.

**ModuleEnableMap** — FILE: `src/Modules/ModuleEnableMap.php`. Performs THE one `get_option('rankkernel_modules')` per request in constructor; normalizes both list (`[0=>'metadata']`) and assoc (`['x'=>true]`) shapes. `isEnabled($id)`, `raw()`, `all()`. STATUS: complete.

**ModuleInterface** — `getId/getName/isEnabled/getPriority/dependsOn/register/boot`. Implemented by MetadataModule, SchemaModule, SitemapsModule only.

**ModuleRegistry** — 13 ids: metadata, sitemaps, schema, breadcrumbs, importer, redirects, `404`, instant-indexing, robots, image-seo, gutenberg, ai, headless. Only the first 3 have code.

**Autoloading** — Composer PSR-4 `RankKernel\` → `src/`, dev `RankKernel\Tests\` → `tests/`. No DI container by design.

**SettingsStore** — `src/Settings/SettingsStore.php`, OPTION `rankkernel_settings`. Whitelisted keys (title/description templates, separator `–`, 5 socials, 5 webmaster codes, site_represents, org_name/logo/sameas, website_search_action, schema_breadcrumbs, schema_author, purge_on_uninstall + dynamic `schema_default_{type}`); merged-over-defaults cached per request; `set()` whitelists; no-op saves return true.

**Metadata engine** — `MetadataModule` (register() does 3 `register_meta` calls; boot() hooks `wp_head:1` render + `pre_get_document_title:10`). `HeadRenderer` single-pass emit. `TagsReplacer` memoized `(hash|field)`. `Context` built once per request (single meta read, memoized). `MetaPayload` defaults/sanitize/REST schema + `decodeMetaValue` + `sanitizeNodeList`.

**Schema engine** — `SchemaModule` (id `schema`, dependsOn `['metadata']`); `Generator` (register/generate, filters `rankkernel/schema/graph`, `piece/{id}`, `needs_{id}`); `PieceInterface::getId/isNeeded(Context)/build`; 25 pieces; render on `rankkernel/head/after_tags:10` emits ONE script tag, skips empty graphs.

**Sitemap engine** — `SitemapsModule` (takeover, robots directive, version-gated flush, ping hook); `Router` (rewrite + intercept, static URL helpers); `IndexBuilder` (1000/page via `entries_per_page` filter, version-injected); 3 Providers (direct `$wpdb`); `SitemapCache` (validator get/getMap/store, shutdown queue, invalidateAll); `SitemapSettings`; `XslStylesheet` + `sitemap.xsl`.

**Admin** — `AdminMenu` (menu + 2 submenus + action links + load-hook wiring); `SettingsPage` (main settings); `SitemapSettingsPage` (4 tabs); `SchemaSettingsPage` (identity/defaults/tools + media picker); `SchemaMetabox` (post editor, classic POST + PRG). Screen-gated assets only: metabox JS on post screens, media picker JS on schema page, block editor JS/CSS via block registration.

**REST** — `SettingsController` (GET/POST `/settings`), `ModulesController` (POST `/modules/{id}`), namespace `rankkernel/v1`, `manage_options` permission callbacks. NOT used by admin UI (classic forms).

**Database/migrations** — `MigrationRunner` with `rankkernel_db_version` ledger (autoload no), idempotent `maybeRun()` on `init:10`. Baseline `0.1.0` no-op. No table migrations exist.

**Cache** — sitemap validator cache (transients `rankkernel_sitemap_*` or object-cache group `rankkernel-sitemaps`); settings merged-array caches; Context memoization; no cron.

**Security** — caps (`manage_options`, `edit_post/object`, `edit_term`, `edit_user`), nonces (`check_admin_referer`, metabox nonce, REST cookie auth), `sanitize_*` on input, `esc_*` on output, `$wpdb->prepare` everywhere, `ABSPATH`/`WP_UNINSTALL_PLUGIN` guards, `wp_kses_post` for rich answers, `allowed_classes:false` unserialize, text/plain 404s. i18n `rankkernel` domain throughout.

**Uninstall** — `uninstall.php`: honors stored `purge_on_uninstall` (unset/false = retain everything, safe default); on purge deletes all `rankkernel_*` options, `_rankkernel_*` post/term/user meta, `wp_rankkernel_*` tables via prepared direct SQL.

## 3. Database and Storage

| KEY | PURPOSE | STRUCTURE | WRITTEN | READ | UPDATED | DELETED | REQUIRED | BLOAT? |
|---|---|---|---|---|---|---|---|---|
| `rankkernel_modules` (opt, autoload yes) | enable map | list or assoc id→bool | activation seed, admin/REST toggles | once/req via EnableMap | on toggle | on purge | yes | no (tiny) |
| `rankkernel_settings` (opt, autoload yes) | global settings | flat array ~20 keys | activation seed, admin/REST | once/req cached | on save | on purge | yes | no (tiny) |
| `rankkernel_sitemap_settings` (opt) | sitemap settings | ~10 keys + dynamic pt_/tax_ | first save (lazy) | per sitemap render cached | on save | on purge | no | no |
| `rankkernel_db_version` (opt, autoload **no**) | migration ledger | version string | activation `0.0.0`, migrations | per request cheap | on migrate | on purge | yes | no |
| `rankkernel_conflict_notice` (opt) | competitor warning | string array | activation only | admin_notices | never cleared automatically | on purge | no | no |
| `rankkernel_sitemap_validator_global` + `rankkernel_sitemap_validator_{set}` (opts) | cache validators | random strings | shutdown flush, save paths, version bumps | per cache read | on invalidation | on purge | only w/ sitemaps | no |
| `rankkernel_sitemap_code_version`, `rankkernel_rewrite_rules_version` (opts, autoload no) | upgrade one-shots | version strings | module boot | per request | per version | on purge | only w/ sitemaps | no |
| `_rankkernel_meta_data` (postmeta) | ALL per-post SEO | single JSON-ish object row | post save/REST/import | once/req memoized | on save | on purge | no | **minimal by design** (1 row vs competitors' 25–45) |
| `_rankkernel_term_data` (termmeta) | per-term SEO | same shape minus post fields | term save | once/archive memoized | on save | on purge | no | minimal |
| `_rankkernel_user_prefs` (usermeta) | per-user prefs | permissive object | future (AI keys) | rarely | on save | on purge | no | no |
| Custom tables | — | **NONE EXIST** (zero required; redirect/404 tables only when those modules ship) | — | — | — | purge drops `wp_rankkernel_*` if present | no | no |
| Transients `_transient_rankkernel_sitemap_*` | sitemap XML + set maps | payload+validators arrays, TTL 0 (validator-gated, never time-expire) | on cache miss | per render | on invalidation | naturally + purge | no | bounded (one per set/page) |
| Object cache | group `rankkernel-sitemaps` | same payloads | same | same | same | eviction | no | no |
| Cron | **NONE** (no scheduled events) | — | — | — | — | — | — | — |
| Rewrite rules | `^sitemap_index\.xml$`, `^([^.]+)-sitemap([0-9]+)?\.xml$`, `^([a-z]+)?-?sitemap\.xsl$` + vars `rankkernel_sitemap/_n/_xsl` → cached `rewrite_rules` option | WP core storage | module boot registration + version-gated `flush_rewrite_rules(false)` + toggle flushes | WP parse | on flush | core-managed | only w/ sitemaps | no |

**Bloat verdict:** exemplary. Two small autoloaded options, one row per object, no tables, no cron, validator-gated (not TTL-churned) transients.

## 4. Modules

Only 3 of 13 registry ids have implementations. Each: registered in `Plugin::registerCoreServices`, enabled via map, **never instantiated when disabled** (verified: `bootEnabled()` `continue`s before `register()/boot()`; zero-hook tests assert `add_action`/`add_filter` never fire).

| Module | Class | Hooks when ON | Assets | REST | Queries | Status |
|---|---|---|---|---|---|---|
| metadata | `MetadataModule` | `wp_head:1`, `pre_get_document_title:10` | none frontend | none | ≤1 meta read/page | complete |
| schema | `SchemaModule` (+FaqBlock/HowtoBlock, Generator, 25 pieces) | `rankkernel/head/after_tags:10`, block category filter | editor JS/CSS for 2 blocks only | none | 0 extra (reuses Context) | complete |
| sitemaps | `SitemapsModule` (+Router/IndexBuilder/3 Providers/Cache/Settings/XSL) | `query_vars`, `pre_get_posts:1`, `redirect_canonical`, `wp_sitemaps_enabled=false`, `robots_txt:1`, `admin_notices`, `transition_post_status`, cache hooks, `shutdown` | none frontend | none | COUNT/listing only on cache miss | complete |

Core (always-on, not gateable): settings, enable map, REST controllers, migrations, AdminMenu, SchemaMetabox. **"Disabled = zero cost" is TRUE as implemented**, with one nuance: `evaluateAll()` runs `isEnabled()` once per registered module at boot (3 cheap map lookups), and modules are *constructed* at `registerCoreServices` before gating (so constructors must stay side-effect free; only `boot()` registers hooks).

## 5. SEO Metadata

Single meta key `_rankkernel_meta_data`, one row per post: `{title, description, canonical, robots{index,follow,noarchive,noimageindex,nosnippet,max_snippet,max_image_preview,max_video_preview}, og{title,description,image,image_id,type}, twitter{card,title,description,image,image_id}, focus_keywords[], schema{...}, flags{pillar,cornerstone,breadcrumb_title}}`. Terms mirror minus post fields.

- Title: `pre_get_document_title` filter (payload literal or token-resolved, else WP default; previews untouched).
- Description chain: payload → settings template (TagsReplacer) → excerpt/term description → omitted.
- Robots: omitted when clean `index,follow`; max-* only when set; search/404 hardcoded noindex.
- Canonical: payload → permalink/term/home; omitted on search/404.
- OG: title/description/url/type/image (+dimensions only from same attachment); Twitter: card/title/description/image with OG fallback chain.
- Webmaster: google-site-verification, msvalidate.01, yandex-verification, p:domain_verify, baidu.
- Feeds: early return, no tags. No frontend CSS/JS.
- Editor UI: classic metabox (type selector with Automatic-resolved label, conditional manual fields, FAQ/HowTo builders REMOVED in favor of blocks, custom JSON, validation, import/export, per-post disable). No Gutenberg sidebar yet.
- Inheritance: payload field → settings template → computed default. No CPT-level meta defaults (only schema-type defaults).
- Duplicate prevention: single renderer, early priority, conflict notice vs competitors.

## 6. XML Sitemaps

- Index `/sitemap_index.xml` (20 sets on test data), per-type `{slug}-sitemap{n}.xml` (1000/page, `entries_per_page` filter), authors, XSL at `/sitemap.xsl?ver=` with namespaced XPath view (counts, back link, image counts).
- Providers: posts/taxonomies/authors, public types only, attachments explicitly excluded, `post_modified_gmt DESC, ID DESC` ordering.
- Exclusions: noindex (`s:5:"index";b:0` serialized LIKE on entries+counts), `post_password=''`, canonical-mismatch (batched PHP filter), exclude post/term/user IDs, excluded roles (capabilities LIKE), empty authors.
- Images: featured + content `<img>` + gallery ids, same-host only, dedupe, cap 100; term/author null.
- Cache ON by default, validator global+per-set, `getMap` for set map (namespaced `xml_`/`map_` keys), invalidation on save_post/edited_terms/delete_term/clean_term_cache/user_register/delete_user/profile_update/settings/modules + code-version bump; transient/object-cache dual store.
- Unknown sets + out-of-range pages → 404 text/plain "Sitemap not found." `/sitemap.xml` → 301 index. Plain permalinks via `rankkernel_sitemap*` params. Core sitemaps disabled + notice. robots.txt `Sitemap:` line replaces stale core line (blog_public respected).
- Takeover notice, `rankkernel/sitemap/ping` cache-warm hook (honest: no engine ping).
- No ETag/Last-Modified headers. No video/news/KML/HTML sitemaps. 30+ dedicated tests.
- Blueprint gaps: none material in §F; video/news were never v1 scope.

## 7. Schema / JSON-LD

**Files:** `SchemaModule.php`, `Generator.php`, `PieceInterface.php`, `SchemaTypes.php` (26 supported + `DEFAULT='Article'` + `defaultForPostType`: post→BlogPosting else Article + `normalize()`), `SchemaHelpers.php`, 25 piece files, `blocks/FaqBlock.php`+`HowtoBlock.php` (+block.json/editor.js/editor.css each), `SchemaMetabox.php`, `SchemaSettingsPage.php`.

**A. Global settings** — No separate master switch (module toggle IS the switch; OFF = no registration, no output). Stored in `rankkernel_settings`: `site_represents` (organization/person), `org_name` (fallback blog name), `org_logo` (URL via media picker), `org_sameas` (array), `website_search_action` (bool), `schema_breadcrumbs`, `schema_author`. No contact-point field. No social profiles beyond sameAs.

**B. CPT defaults** — `schema_default_{post_type}` dynamic keys (validated, invalid dropped); mapping fallback post→BlogPosting, everything→Article. Supported list (26): Article, BlogPosting, NewsArticle, WebPage, FAQPage, HowTo, Product, Recipe, Event, Service, VideoObject, ImageObject, Book, Course, JobPosting, SoftwareApplication, MusicRecording, LocalBusiness, Review, Movie, ClaimReview, Dataset, PodcastEpisode, Carousel, QAPage, ItemList. **No `None` type**; disabling per post is via the separate `disabled` checkbox. NOTE: no CPT named `post` with special handling beyond the mapping; `page`→Article.

**C. Per-post selector** — Metabox "Schema type" dropdown on post edit screens: `Automatic ({Resolved})` (value `""`) + all types. **Hierarchy verified in code**: payload `type` if valid → `schema_default_{type}` setting if valid → `defaultForPostType()` mapping. Saving Automatic stores `""` (never pins). The previous confusion (pinned `Article` beating the default) was by-design precedence + invisible default, both fixed with the visible label. **Inheritance claim is TRUE.**

**D. Generation** — `Generator::generate(Context)` iterates registered pieces, `isNeeded()` gate + `needs_{id}` filter, merges `build()` + `piece/{id}` filter, drops empties, returns `@context` + `@graph`. Disabled payload (`schema.disabled`) → empty graph → `SchemaModule::render` prints nothing. Pieces are plain objects (cheap), all 27 registered in `boot()` regardless of need (correct: registration ≠ work; `isNeeded` gates building). Exactly one `<script type="application/ld+json">` via `rankkernel/head/after_tags:10`, `wp_json_encode` UNESCAPED_SLASHES|UNICODE. @ids: `{home}#organization|#website`, `{permalink}#webpage|#article|#faq|#product|…`, `{author-url}#author`. Breadcrumb piece builds minimal home→current trail + `breadcrumb_trail` filter seam (full trail awaits breadcrumbs module).

**E. Pieces** (all `isNeeded`+`build`, defensive reads, omit-on-invalid):
Organization (always; logo ImageObject; skips on empty name), WebSite (always; SearchAction toggle), WebPage (singular/home/archives; CollectionPage/AboutPage variants; speakable/about/mentions extensions), BreadcrumbList (toggleable; minimal trail), Person (author/singular+archives; skips empty name), Article (post/CPT; BlogPosting vs Article; publisher/author refs), FAQPage (payload rows + `rankkernel/faq` blocks via parse_blocks, merged/deduped/capped, wp_kses answers), HowToPage (same + `rankkernel/howto` blocks), Product (+Woo seam mapping when `WooCommerce` class exists; Offer/aggregateRating validated), Recipe (ISO durations, ingredient/instruction split), Event (status enum map, Place address, offers), Service, VideoObject (all-required-or-silent), Book (ISBN digits), Course, JobPosting, SoftwareApplication, MusicRecording, Movie, ClaimReview, Dataset, PodcastEpisode, Carousel/ItemList (NodeLists capped 50×50), QAPage. LocalBusiness/Review types are in the SUPPORTED list but **have no dedicated piece classes** (partial: selectable but no generator output beyond generic handling — verify before promising).

**F. Manual/custom** — Custom JSON textarea merged raw after sanitize (depth/key caps, non-arrays dropped); import (file → same sanitize path) / export (`admin-post.php` download) in metabox; validation warnings per type in UI; external validator links.

**G. Blocks** — `rankkernel/faq` + `rankkernel/howto`: block.json (explicit editor_script/style handles with full wp-* deps after a 404 incident), vanilla JS editor (stable row ids, change-guarded setAttributes), server `render_callback` (numbered spans, wp_kses answers, list-style hardening, empty→''), schema extracted from block attrs at render. Multiple blocks merge. Data lives in post_content (block delimiters), not meta.

**H. Examples** (from verified live behavior + code; post URL shapes from this repo's test fixtures):
1. Page: single script, graph `[Organization, WebSite, WebPage, BreadcrumbList]` (no Person/Article off-singular).
2. Post: `[Organization, WebSite, WebPage, BreadcrumbList, Person, Article]` with headline/dates/image/publisher/author refs.
3. Post with 2 FAQ rows: adds `FAQPage{mainEntity:[Question×2]}` (7 nodes total, verified live then reverted).

## 8. Admin UI

- `RankKernel` menu (`rankkernel`, dashicons-search, pos 80, manage_options) → main Settings page (templates, separator, socials, webmaster, purge checkbox) + `Sitemap` submenu (`rankkernel-sitemap`: General/Post Types/Taxonomies/Authors tabs, per-type toggles + URLs, items-per-page, images flags, excludes, validators note) + `Schema` submenu (`rankkernel-schema`: Identity/Defaults/Tools, media-picker logo, per-type default selects, toggles, validator links).
- Plugins-row Settings link. Conflict + takeover + save notices. Post metabox (described above) + import/export + validation.
- Assets: `schema-metabox.js` (post screens only: conditional rows, builder row add/remove), `schema-settings.js` (schema page only: media frame), block editor JS/CSS (editor only). **Zero frontend assets.** No React build (blocks are vanilla JS).
- Phase-4-style branded dashboard: NOT implemented (admin is functional-native).

## 9. REST API

- `GET/POST /rankkernel/v1/settings` (whitelisted partial update) and `POST /rankkernel/v1/modules/{id}` (validated id, bool-normalized, takes effect next request); `permission_callback` = `manage_options` (403 otherwise). Registered on `rest_api_init` always (core service).
- **Admin UI does not use REST** (classic POST + load-hook PRG everywhere). No AJAX endpoints.

## 10. Performance

- GOOD: one `rankkernel_modules` read/request; merged settings cached; one meta read/object/request; memoized replacer; sitemap validator cache (rebuild only on invalidation); zero hooks when modules off (verified); screen-gated assets; `$wpdb->prepare` everywhere; direct-ID listings.
- ACCEPTABLE: per-request Context build (cheap); 1000-row sitemap builds with `do_blocks` rendering per post on cache miss (bounded by cache; recursion guarded by `is_main_query` check); JSON encode per request (small graphs).
- POTENTIAL ISSUE: `rankkernel_modules` + `rankkernel_settings` autoload with default `yes` (fine, tiny); sitemap validator options autoload default yes (tiny strings, acceptable); transient payloads with TTL 0 persist until invalidated (bounded count, by design).
- HIGH PRIORITY: none found in code. (Live TTFB/benchmarks not measured; `docs/benchmarks/` does not exist in repo — verify separately.)

## 11. Security and WordPress Compliance

- Caps on every entry (manage_options admin/REST, object caps on meta auth callbacks, edit_post on metabox/export). Nonces on all state changes (settings, metabox, export, REST via cookie auth). Sanitization centralized (SettingsStore whitelist, MetaPayload deep sanitize, SitemapSettings validators, absint ID lists). Escaping on all output (esc_*, ENT_XML1 in XML, wp_kses_post for rich answers). SQL all prepared incl. LIKE via esc_like. `unserialize(..., allowed_classes:false)` with shape guards. Direct-file guards everywhere. No external HTTP calls in code (only user-configured future endpoints). No telemetry. Text domain consistent; RTL/CSS minor. PHP 8.1 typed (readonly, enums, match where used). No deprecated APIs spotted. WPCS+PSR12 enforced via repo config.

## 12. Testing

45 files; per-file test counts (function test_): SchemaBatch4Test 31, SchemaBatch3Test 29, SchemaMetaboxTest 24, SchemaSettingsAdminTest 22, SchemaPiecesTest 22, HeadRendererTest 22, SchemaBatch2Test 19, SitemapSettingsProvidersTest 17, FaqBlockTest 13, ContextTest 12, RouterTest 10, MetaPayloadTest 10, 9-count: SitemapSettingsTest, SitemapSettingsAdminTest, ModuleManagerTest; 8-count: RouterPlainModeTest, IndexBuilderTest; 7-count: SitemapsModuleTest, SettingsStoreTest; 6-count: SettingsPageTest, SchemaGeneratorTest, RestControllersTest, ModuleRegistryTest, MigrationRunnerTest, HowtoBlockTest; 5-count: SitemapRobotsDirectiveTest, SitemapInvalidationTest, SitemapCacheTest, SchemaSettingsTest, SchemaModuleTest, MetaPayloadDecodeTest; 4: TagsReplacerTest, AdminMenuTest; 3: SitemapExclusionsTest; 2: SitemapCanonicalTest, SitemapAuthorsAttachmentsTest, IndexBuilderRouterTest; 1: SitemapSettingsCacheTest, PluginTest. 5 support doubles.

Covered: hard gating (zero-hook assertions), single-read, memoization, cache validator/error paths, 404s, redirects, invalidation hooks, exclusion SQL, image pipeline, all schema pieces incl. gating/validation, import/export, permission/nonce negatives, onboarding seeds. GAPS: block editor JS (untestable in phpunit; covered only via PHP render tests), live-browser rendering (done ad hoc, not automated), WooCommerce mapping (seam doubles only, no store present), multisite, upgrade migrations with real tables, performance benchmarks.

## 13. Git History

On GH-11 (ahead of origin/main@af9aab8): schema core → contract → FAQ/HowTo → commerce/media → Pro types → metabox UI → reviews/fixes → FAQ block → settings submenu → UX round → HowTo block → asset-URL fixes → constant-safety fixes. No uncommitted work (tree clean). Notable: several fix iterations reveal a pattern worth knowing — hand-typed identifier inconsistencies (since normalized programmatically; verify with `grep -c` not eyeballing), and Brain Monkey/Patchwork cross-test stub leakage (documented convention now: stub what you touch; never blanket-`when` alongside strict `expect`).

## 14. Blueprint vs Codebase

IMPLEMENTED: two-phase boot, service locator, hard gate + ModuleEnableMap, single-key meta, register_meta+auth, head pipeline (single pass, memoized replacer), sitemap router/providers/cache-ON/validator/XSL/takeover/robots directive/plain mode/redirects/exclusions/images, schema generator+pieces+metabox+blocks+settings, REST settings/modules, migrations runner, uninstall purge, conflict notice.
PARTIAL: Admin UI (functional, unbranded); breadcrumbs (piece exists, no module/block); Gutenberg (2 vanilla blocks, no React suite); WooCommerce (mapping seam only); import/export (schema-only, no competitor importer); validation (editor warnings + external links, no built-in tester).
NOT IMPLEMENTED: redirects, 404 monitor, instant-indexing, robots editor, image-seo, headless, ai modules (registry-only); News/Video/KML/HTML sitemaps; LocalBusiness full module; onboarding wizard; multisite; benchmarks; WP.org submission artifacts; branch protection (operational, not code).

## 15. Current User-Facing Features

AVAILABLE NOW: install/activate with requirement guards + conflict notice; global settings; per-post SEO meta; automatic + per-post schema types with live JSON-LD; FAQ/HowTo blocks with editor UI; schema settings (identity/defaults/tools); XML sitemaps with human view + per-type/taxonomy/author controls + images + excludes; module toggles via admin or REST; schema import/export; full uninstall purge.
PARTIAL: breadcrumbs (schema-only output); WooCommerce (auto-fill when present, untested live); plain permalinks (implemented + unit tested, limited live proof).
NOT AVAILABLE: redirects, 404 log, IndexNow pings (real), robots.txt editing, image SEO bulk tools, AI features, headless payload, HTML sitemap, branded dashboard, setup wizard.

## 16. Current State Summary

- CURRENT PHASE: Schema phase (blueprint Phase 2 tail), branch GH-11 unpushed.
- CURRENT ISSUE/TASK: #11 (schema module + UX rounds). #9 (sitemaps) merged via PR #10.
- LAST COMPLETED: block asset-URL fix + constant-safety migration (Plugin::VERSION).
- CURRENTLY BUILDING: nothing pending in code; awaiting user verification of GH-11.
- ACTUALLY WORKING: metadata head output, sitemaps (index/type/author/XSL/robots/redirects), schema graph (25 pieces), metabox, both blocks, all three settings screens, REST toggles, uninstall.
- PARTIAL: breadcrumbs output only; Woo seam untested live; plain-permalink lightly proven live.
- BROKEN: nothing known (last fatal fixed; live 200s verified).
- MISSING: 10 modules, branded admin, React Gutenberg suite, importer, benchmarks.
- DO NOT TOUCH (working, heavily tested): ModuleManager/EnableMap gating semantics, MetaPayload sanitize shapes, cache validator protocol, rewrite rules, uninstall purge logic.
- NEEDS REVIEW BEFORE CONTINUING: user verification of GH-11 (editor blocks, metabox UX, logo picker, Automatic labels); branch protection still off; token expiry ~Dec 2026.

## 17. Questions / Ambiguities

1. LocalBusiness and Review are selectable types with no dedicated piece classes. Is generic fallback output acceptable, or must pieces exist before claiming support?
2. `page` post type defaults to Article, but should static front page / posts page emit WebPage only? Current behavior: normal singular flow.
3. WooCommerce mapping has zero live coverage (no store here). Who provides a Woo testbed before claiming support?
4. Plain-permalink mode is unit-tested but minimally proven live. Acceptable, or require a live plain-mode pass?
5. BreadcrumbList currently builds a minimal trail; the dedicated module will replace it via filter. Is the interim output acceptable to users?
6. Transients use TTL 0 (validator-gated, never expire). Orphan risk if validators are ever deleted directly. Acceptable?
7. `schema_default_{type}` keys are unbounded per public type. Any concern with exotic CPT slugs?
8. The `disabled` per-post flag suppresses the entire graph including Organization/WebSite. Is that the desired semantic, or should globals persist?
9. Import accepts any JSON through sanitize. Should there be a schema-version marker for forward compatibility?
10. GH-11 is unpushed with the user's local-only rule. Confirm push → PR → merge sequence and who runs the gates on main before continuing to redirects phase.
