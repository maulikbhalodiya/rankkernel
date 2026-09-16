# RankKernel — Current Code-Verified Inventory

> Source of truth: plugin repo `wp-content/plugins/rankkernel/`, branch `main`, HEAD `606fc95`
> (`606fc956d15d263944dc0ddd1c2006efaeb2f3ef`, merge PR #24, 2026-09-16).
> Scope: everything RankKernel implements **today**, plus the documented goals and the gaps the
> planning docs already record. Written to be diffed against Rank Math and Yoast to find remaining gaps.
> Rules: every capability claim carries a file reference. Nothing is attributed that is not in the code
> or in the cited planning docs.

## Source documents read for Part 1 and Part 3

| Doc | Path |
|---|---|
| Master build plan / progress tracker | `docs/ROADMAP.md` |
| Architecture blueprint (design spec) | `docs/architecture/blueprint.md` |
| Feature gap research gate | `docs/research/feature-gap-research-gate.md` |
| Competitor feature matrix | `docs/competitor-analysis/feature-matrix.md` |
| Plugin README / readme.txt | `rankkernel/README.md`, `rankkernel/readme.txt` |

---

# PART 1 — DOCUMENTED GOALS AND NON-NEGOTIABLE PRODUCT RULES

## 1.1 Stated product goal and positioning

- **Name / tagline:** "RankKernel — The Free, Zero-Bloat Open-Source SEO Engine for WordPress" (`docs/architecture/blueprint.md:12-14`, `composer.json`).
- **Header description:** "100% free, lightweight SEO with no paywalls or upsell banners, metadata engine, XML sitemaps, schema, breadcrumbs, redirects, 404 monitor and IndexNow. Modules that are off cost zero: no hooks, no queries, no bloat." (`rankkernel.php:4`).
- **Strategic goal:** build the meaningful functionality of **Yoast Free + Premium** and **Rank Math Free + PRO** as a single free plugin, via clean-room parity, never shipping less than their baseline (`ROADMAP.md`, particularly standing rule 9).
- **Four pillars** (`docs/architecture/blueprint.md:24-29`):
  1. No paywalls — every blueprinted feature ships free forever; no "Pro" tier, no credit packs, no upsell banners.
  2. Hard-gated modules — off = never booted = zero hooks, zero cost; on = cache-first.
  3. Single-row metadata + full uninstall purge — one post-meta row, one term-meta row versus competitors' ~25–45 keys.
  4. Open / transparent / zero telemetry — GPLv2-or-later, no external calls except a user-configured AI key or IndexNow.

## 1.2 Hard rules (non-negotiable)

| Rule | Statement | Evidence |
|---|---|---|
| Free forever, no PRO | "Free forever, every feature ships free; no Pro tier, no upsells, no nags." | `ROADMAP.md` |
| No feature gates | Every blueprinted feature is free; paid competitor features are ordinary roadmap items, not a future PRO phase | `docs/research/feature-gap-research-gate.md:17` |
| No licence checks | No licence/PRO code path exists in the repo; all 13 module ids are ungated | `src/Modules/ModuleRegistry.php:24-38` |
| No telemetry | "No telemetry by default"; no Mixpanel, no vendor phone-home | `docs/architecture/blueprint.md:29,636`; `README.md` |
| No artificial limits | Unlimited focus keywords free; full redirect manager free; 404 monitor free | `docs/competitor-analysis/feature-matrix.md:79-87` |
| No upsells/nags | "Zero nags/upsells/notification-center, ever" | `ROADMAP.md`, `docs/architecture/blueprint.md:556` |
| Accuracy rule (binding) | Never claim "zero-query" absolutely. Disabled module = zero hooks; enabled = cache-first, at most one targeted query on a miss | `docs/architecture/blueprint.md:31-33` |
| Clean-room | No competitor code copied, ideas only | `ROADMAP.md` |
| Zero-dash writing | No standalone em/en dashes or hyphen pauses in any project text | `ROADMAP.md` |
| Conflict safety | Detect active Yoast/Rank Math/SEOPress on activation and warn; never auto-disable their output | `rankkernel.php:93-108`, `rankkernel.php:123-141`; `docs/architecture/blueprint.md:651` |

## 1.3 Explicitly out-of-scope or deferred (with stated reason)

From `docs/architecture/blueprint.md:74-81,646-657`, `ROADMAP.md` and `docs/research/feature-gap-research-gate.md:232-236,346-348`:

| Item | Reason |
|---|---|
| Analytics dashboards (GSC/GA4) | Defer to Site Kit bridge; competitors' are paid/capped |
| Keyword rank tracking | Out of scope v1; requires Google account/external service |
| Addon store / marketplace | v1 single plugin, helper add-ons only for niche integrations |
| News / Video sitemaps | Phase-2 candidate only |
| NLP / semantic entity scoring | Not a v1 differentiator |
| Multisite network purge | v1 targets single site |
| Onboarding wizard | Decide during admin redesign |
| Remote/paid AI (Content AI, AI Visibility, AI Link Genius) | EXTERNAL DEPENDENCY: every measured AI/analytics capability at Rank Math requires an account + subscription |
| Social image watermarking | NOT APPROPRIATE (marketing only) |
| `.htaccess` redirect sync / raw editor | Availability-level failure risk, Apache only; recommend omit in v1 |
| Generative writing / paid visibility tracking | External paid subscriptions |
| Tracking scripts, telemetry, upsells | Product fit (zero-telemetry pillar) |

---

# PART 2 — WHAT IS ACTUALLY IMPLEMENTED (CODE VERIFIED)

## 2.1 Module registry, defaults, dependencies, priority

Two distinct things called "the registry" exist. Both are reported to avoid ambiguity.

**(A) `ModuleRegistry::MODULES` — the canonical id → label list, 13 ids** (`src/Modules/ModuleRegistry.php:24-38`):

| id | label |
|---|---|
| metadata | Metadata Engine |
| sitemaps | XML Sitemaps |
| schema | Schema (JSON-LD) |
| breadcrumbs | Breadcrumbs |
| importer | Importer |
| redirects | Redirects |
| 404 | 404 Monitor |
| instant-indexing | Instant Indexing (IndexNow) |
| robots | Robots.txt & .htaccess |
| image-seo | Image SEO |
| gutenberg | Gutenberg Suite |
| ai | AI Suite (BYO Key) |
| headless | Headless |

**(B) Modules actually registered into `ModuleManager`** (`src/Plugin.php:149-171`) — 6 concrete
`ModuleInterface` classes. The remaining 7 ids are reserved in the registry but have **no class and no
directory** (`src/Modules/` contains only Breadcrumbs, Metadata, Monitor, Redirects, Schema, Sitemaps;
`Importer`, `InstantIndexing`, `Robots`, `ImageSeo`, `Gutenberg`, `Ai`, `Headless` are MISSING).

| id | class | priority | dependsOn | default state | evidence |
|---|---|---|---|---|---|
| metadata | `MetadataModule` | 10 | — | **ON** | `MetadataModule.php:60-89` |
| sitemaps | `SitemapsModule` | 20 | — | **ON** | `SitemapsModule.php:65-93` |
| schema | `SchemaModule` | 30 | metadata | **ON** | `SchemaModule.php:139-168` |
| breadcrumbs | `BreadcrumbsModule` | 35 | metadata | **ON** | `BreadcrumbsModule.php:67-96` |
| redirects | `RedirectsModule` | 40 | — | **OFF** | `RedirectsModule.php:69-98` |
| 404 | `MonitorModule` | 50 | — | **OFF** | `MonitorModule.php:61-90` |
| importer | (no class) | — | — | seeded ON but never boots | seed: `rankkernel.php:77`; no dir under `src/Modules/` |

Default state is seeded at activation into the `rankkernel_modules` option as the list
`[metadata, sitemaps, schema, breadcrumbs, importer]` (`rankkernel.php:77-80`). Because `importer` has no
module class, only 4 modules boot by default. All other ids are absent from the seed, so
`ModuleEnableMap::isEnabled()` returns false for them (`src/Modules/ModuleEnableMap.php:62-64`).

## 2.2 Module gating mechanism and what "disabled" costs

- **The gate:** `ModuleManager` evaluates every registered module's enabled state **once** into
  `$enabledMap` (`evaluateAll()`, `src/Modules/ModuleManager.php:81-98`), reading a single
  `get_option('rankkernel_modules')` via the injected `ModuleEnableMap` (`ModuleEnableMap.php:36-54`).
- **Boot order:** registry is sorted ascending by `getPriority()`; only `isOn()` modules run
  `register()` then `boot()` (`ModuleManager.php:107-163`).
- **Dependency enforcement:** if a declared dependency is not on, the module is forced off and
  `do_action('rankkernel/module/force_disabled', $moduleId, $missingDependency)` fires
  (`ModuleManager.php:140-158`). Schema and Breadcrumbs both declare `metadata`.
- **Cost of "off" (code-accurate):** module **objects are constructed** at `plugins_loaded` for all six
  classes (`Plugin.php:149-171`), but a disabled module never has `register()` or `boot()` called, so it
  **registers zero hooks and performs no table work**. E.g. `RedirectsModule::register()` ensures the
  redirect table and is only reached when enabled (`RedirectsModule.php:137-152`); `MonitorModule::register()`
  likewise (`MonitorModule.php:128-137`). The plugin's own tests target this ("OFF = zero hooks"). The
  one unavoidable per-request cost is the single `get_option` for the enable map.
- **Enabled cost:** `isEnabled()` is cached per instance (`$enabledCache`), so no repeated option reads.

## 2.3 Metadata engine

- **Head production:** `MetadataModule::boot()` builds a `HeadRenderer` and boots it (`MetadataModule.php:178-181`).
  `HeadRenderer::boot()` adds `wp_head` at priority **1** (`render`) and filters `pre_get_document_title`
  (`title`) (`src/Modules/Metadata/HeadRenderer.php:80-83`). All tags emit in **one pass** from a single
  `Context` object (`HeadRenderer.php:135-191`).
- **Context:** built once per request, memoizes the single meta read (`get_post_meta`/`get_term_meta` on the
  one payload key) and a stable hash (`src/Modules/Metadata/Context.php:97-120`, `73-90`).
- **Emitted tags** (`HeadRenderer.php`):
  - `meta description` (payload → settings template → excerpt/taxonomy description fallback) `:155-160,268-308`
  - `meta robots` (index/noindex, follow/nofollow, noarchive, noimageindex, nosnippet, max-snippet,
    max-image-preview, max-video-preview; omitted when exactly `index, follow`; search & 404 forced
    `noindex, follow`) `:162-167,317-371`
  - `link rel=canonical` (omitted on search/404; payload → `Context::permalink()`) `:169-174,380-392`
  - Open Graph: `og:title`, `og:description`, `og:url`, `og:type`, `og:image` (+ width/height when the
    image came from an attachment), `og:site_name`, `og:locale` `:401-487`
  - Twitter: `twitter:card` (default `summary_large_image`), `twitter:title`, `twitter:description`,
    `twitter:image` with OG fallback chain `:495-569`
  - Webmaster verification: google, bing (msvalidate.01), yandex, baidu, pinterest `:574-590`
  - `do_action('rankkernel/head/after_tags', $ctx)` slot for Schema `:186`
  - Deliberately **not** emitted: `rel=prev`/`rel=next` (Google deprecated 2019) `:188`; no Slack-specific tags `:190`
- **Token / variable grammar:** `%%[a-z_]+%%` — lowercase letters and underscore only, **no parameter/
  argument support** (`TagsReplacer.php:99-107`). Unknown tokens resolve to empty string; custom tokens can
  be injected via the `rankkernel/tokens` filter (`TagsReplacer.php:93,104`). Memoized per
  `(context_hash|field)` and per `(context_hash|token)` to avoid repeat queries (`TagsReplacer.php:51-63,122-145`).
- **Supported tokens** (built-in map): `title`, `sitename`, `sep`, `excerpt`, `date`, `author`, `category`,
  `page`, `currentdate` (`TagsReplacer.php:75-85`).
- **Per-post / per-term / per-user editing:**
  - Registered meta: post `_rankkernel_meta_data`, term `_rankkernel_term_data`, user `_rankkernel_user_prefs`,
    all `show_in_rest` with `auth_callback` (`MetadataModule.php:126-173`).
  - The **only admin editing surface** is the Schema metabox (`add_meta_box`, `src/Admin/SchemaMetabox.php:243-283`).
    There is **no admin metabox/field for SEO title, meta description, robots, canonical, OG or Twitter**
    per post, and **no term-edit or user-profile UI**; those payload fields are writable only through the
    registered REST meta field or programmatically. (`grep add_meta_box` returns SchemaMetabox only;
    no `edit_term`/`profile_update` form fields exist.)
  - Payload shape/defaults: `src/Modules/Metadata/MetaPayload.php:44-81`; deep sanitize + REST schema
    `:130-274,689-817`.

## 2.4 Open Graph / Twitter / social preview

- OG and Twitter tags are emitted as listed in 2.3. Image fallback chain: payload `og.image` (custom URL,
  id 0) → `og.image_id` → featured image (`Context::resolveOgImageData`, `Context.php:579-642`).
- **Social preview:** none. No live social/SERP preview UI or REST preview endpoint exists.

## 2.5 Schema (JSON-LD)

- **Architecture:** `Generator` consults registered pieces in order, each gated by `isNeeded($ctx)`, then a
  per-piece toggle filter and output filter, then merges into one `@graph`, runs the graph filter and
  `GraphNormalizer` (`src/Modules/Schema/Generator.php:40-161`). Emitted as a single
  `<script type="application/ld+json">` in the `rankkernel/head/after_tags` slot (`SchemaModule.php:216-283`).
- **Piece architecture:** `PieceInterface` with `getId()`, `isNeeded()`, `build()`; 29 pieces registered in
  `SchemaModule::getGenerator()` (`SchemaModule.php:290-329`). Piece files live in `src/Modules/Schema/Pieces/`.
- **29 registered pieces:** Organization, Website, WebPage, Breadcrumb, Person, Article, FAQ, HowTo,
  Product, Recipe, Event, Service, Video, Book, Course, JobPosting, Software, Music, Movie, ClaimReview,
  Dataset, PodcastEpisode, Carousel, QAPage, ItemList, LocalBusiness, Review, ImageObject, CustomJson.
  (The gap-gate doc's "30 pieces" counts the `SchemaHelpers`/interface helpers; the Generator registers 29.)
- **Schema types the plugin can emit** — `SchemaTypes::SUPPORTED` (26 selectable names;
  `src/Modules/Schema/SchemaTypes.php:38-65`), with the authoritative type→piece map at `:97-124`:

| # | Type name | Piece id | Label |
|---|---|---|---|
| 1 | Article | article | Article |
| 2 | BlogPosting | article | Blog Posting |
| 3 | NewsArticle | article | News Article |
| 4 | WebPage | webpage | Web Page |
| 5 | FAQPage | faq | FAQ Page |
| 6 | HowTo | howto | How To |
| 7 | Product | product | Product |
| 8 | Recipe | recipe | Recipe |
| 9 | Event | event | Event |
| 10 | Service | service | Service |
| 11 | VideoObject | videoobject | Video |
| 12 | ImageObject | imageobject | Image |
| 13 | Book | book | Book |
| 14 | Course | course | Course |
| 15 | JobPosting | jobposting | Job Posting |
| 16 | SoftwareApplication | softwareapplication | Software Application |
| 17 | MusicRecording | musicrecording | Music Recording |
| 18 | LocalBusiness | localbusiness | Local Business |
| 19 | Review | review | Review |
| 20 | Movie | movie | Movie |
| 21 | ClaimReview | claimreview | Fact Check |
| 22 | Dataset | dataset | Dataset |
| 23 | PodcastEpisode | podcastepisode | Podcast Episode |
| 24 | Carousel | carousel | Carousel |
| 25 | QAPage | qapage | Question and Answer Page |
| 26 | ItemList | itemlist | Item List |

  Automatic defaults: posts → `BlogPosting`, pages/other → `Article` (`SchemaTypes.php:286-292`).
- **Custom JSON:** `CustomJsonPiece` emits arbitrary JSON from the payload `schema.custom` (JSON-safe
  scalars/arrays, depth ≤5, ≤200 keys; `MetaPayload.php:509-549`). FAQ/HowTo/carousel/items sub-shapes are
  also stored in the single payload (`MetaPayload.php:286-320`).
- **Manual overrides:** the Schema metabox edits type + 65 field keys + FAQ questions + HowTo steps, and
  supports JSON import/export (`src/Admin/SchemaMetabox.php:57-235,881-1058`).
- **FAQ/HowTo blocks** feed the corresponding pieces from post content (`FaqPiece.php:205-294`,
  `HowtoPiece.php:289-370`).

## 2.6 Sitemaps

- **Types:** sitemap index + one set per **public post type** (attachments excluded), one set per **public
  taxonomy**, and an **authors** set (`src/Modules/Sitemaps/Provider/PostsProvider.php`, `TaxonomiesProvider.php`,
  `AuthorsProvider.php`; orchestrated by `IndexBuilder::getSetsWithPageCounts`, `IndexBuilder.php:177-232`).
  Images are emitted inline from featured images (`PostsProvider.php:18-22`).
- **Routing:** rewrite `sitemap_index.xml`, `([^.]+)-sitemap([0-9]+)?.xml`, `([a-z]+)?-?sitemap.xsl`;
  query vars `rankkernel_sitemap`, `rankkernel_sitemap_n`, `rankkernel_sitemap_xsl`; intercept at
  `pre_get_posts` priority 1, strip `wp_footer`, exit (`src/Modules/Sitemaps/Router.php:150-330`).
  `/sitemap.xml` 301-redirects to the index (`Router.php:236-244`). Plain-permalink URL forms supported
  (`Router.php:57-96`).
- **Caching:** ON by default (`rankkernel/sitemap/enable_cache` defaults true), object-cache group
  `rankkernel-sitemaps` with transient fallback, validator-based invalidation (global + per set), queued
  and flushed on `shutdown` (`src/Modules/Sitemaps/SitemapCache.php:60-78,381-406`). Per-page size default
  1000, max 50000 (`SitemapSettings.php:57,213-224`).
- **XSL:** bundled `src/Modules/Sitemaps/sitemap.xsl`, served via `readfile` with 1-year cache headers
  (`XslStylesheet.php:28-56`).
- **robots.txt integration:** appends one `Sitemap:` directive, strips any existing Sitemap lines (removes
  the stale core `wp-sitemap.xml` line), skips private blogs (`SitemapsModule.php:198-220`). Also disables
  core WP sitemaps (`wp_sitemaps_enabled` → false) with an admin notice (`SitemapsModule.php:154,161,225-233`).
- **Exclusions / rules:** `exclude_posts`, `exclude_terms` (ids), noindex posts and terms via serialized
  payload `LIKE` match, password-protected posts excluded, attachments never listed, `include_empty_terms`,
  `authors_sitemap`, `authors_include_empty`, `authors_exclude_roles`, `authors_exclude_users`
  (`SitemapSettings.php:31-50`; `PostsProvider.php:34-38`; `TaxonomiesProvider.php`; `AuthorsProvider.php:38-60`).
  Canonical-mismatch exclusion also handled provider-side.
- **Invalidation hooks:** save_post, edited_terms, delete_term, clean_term_cache, user_register, delete_user,
  profile_update, option updates (`SitemapCache.php:236-247`).
- **HTML sitemap:** **not present** (`grep -i "html sitemap"` returns nothing in `src/`).

## 2.7 Breadcrumbs

- **Builder:** `TrailBuilder` handles front page, blog index, search, 404, term archive, generic archive,
  post-type archive, author archive, date archive (year→month→day), and singular (standard, hierarchical
  with ancestors, attachment) (`src/Modules/Breadcrumbs/TrailBuilder.php:71-1170`). Visibility is applied
  before pagination so a `Page N` crumb is not dropped (`TrailBuilder.php:46-59`).
- **Renderer:** `Renderer` outputs an accessible `<nav class="rk-breadcrumbs" aria-label=...>` with an
  ordered list; ancestors are anchors, current item is a `<span aria-current="page">`; separator travels as
  a CSS custom property (`src/Modules/Breadcrumbs/Renderer.php:40-192`).
- **Template tags:** `rankkernel_breadcrumbs(array $args): void` (echo) and
  `rankkernel_get_breadcrumbs(array $args): string` (return) — global wrappers loaded only when the module
  is on (`template-tags.php:22-51`, `functions.php:31-58`).
- **Shortcode:** `[rankkernel_breadcrumbs]` with `separator`, `show_home`, `show_current` attributes
  (`BreadcrumbsModule.php:157-158,241-298`).
- **Block:** `rankkernel/breadcrumbs`, server-rendered (no JS save) (`blocks/BreadcrumbsBlock.php:86-200`).
- **Settings** (`rankkernel_breadcrumbs_settings`, autoload yes): `separator`, `home_label`, `show_home`,
  `show_current`, `hide_on_front_page`, `show_blog_page`, `show_ancestors`, dynamic
  `primary_taxonomy_{post_type}` (`src/Modules/Breadcrumbs/BreadcrumbsSettings.php:31-60`).
- **Filters:** `rankkernel/breadcrumbs`, `rankkernel/breadcrumbs/args`, `rankkernel/breadcrumbs/items`,
  `rankkernel/breadcrumbs/post_type_settings`, and `rankkernel/schema/breadcrumb_trail` (schema adapter)
  (`functions.php`; `BreadcrumbsModule.php:155,325-350`).
- Breadcrumbs never emit JSON-LD themselves; `BreadcrumbPiece` is the sole BreadcrumbList emitter
  (`BreadcrumbsModule.php:20-28`).

## 2.8 Redirects

- **Matching modes:** `exact`, `prefix`, `contains`, `suffix`, `wildcard`, `regex`
  (`src/Modules/Redirects/Normalizer.php:31`; winner selection in `Matcher.php:96-462`).
- **Codes:** `301`, `302`, `307`, `410`, `451`; `410`/`451` are terminal (no destination)
  (`Normalizer.php:38,45`).
- **Storage:** table `{prefix}rankkernel_redirects` — `match_type` enum, `source_hash CHAR(64)`,
  `source`, `target`, `code` enum, `hits`, `is_active`, `created`, `last_accessed`, unique
  `(match_type, source_hash)` (`src/Modules/Redirects/RedirectTable.php:112-134`). Table is created
  lazily on module enable via `ensureTables()`, not through the migration ledger (`RedirectTable.php:95-152`).
- **Cache:** `RedirectCache` — object-cache group `rankkernel-redirects` with transient fallback,
  positive-hits only (misses never cached), validator option `rankkernel_redirects_validator`, TTL 12h
  (`src/Modules/Redirects/RedirectCache.php:26-52`). There is **no second `_cache` table**; only two
  custom tables exist total.
- **Dispatch:** `Redirector` hooks `template_redirect` priority **1**; guards `is_admin`, AJAX, REST,
  cron, and sitemap requests; opens with a cache lookup, one `Matcher::match` on a cold miss, then caches
  (`src/Modules/Redirects/Redirector.php:99-180`). Destination is validated against an allowed-host list
  (`rankkernel/redirect/allowed_hosts`) (`Redirector.php:263-288`; `DestinationValidator.php:42-172`).
- **Safety:** loop detection (max depth 10, 50 nodes), chain detection (max 5 hops), equivalent-redirect
  and cycle verdicts (`Validator.php:29-487`). Regex is capped at 20 active rules and 200 chars
  (`Matcher.php:35,40`).
- **CSV import/export:** header `source,target,code,match_type,active,hits,last_accessed`; max 2 MB,
  5000 rows, batch 200; import can update existing (`src/Modules/Redirects/CsvHandler.php:18-50,105-573`).
  Wired to the admin page via `rk_action=export` and the `rankkernel_redirect_import` POST marker
  (`src/Admin/RedirectsPage.php:201-213,1373-1430`).
- **Slug-change watcher:** `SlugWatcher` hooks `post_updated` for `post`/`page`, compares old/new slug and
  auto-creates a 301 when changed (`src/Modules/Redirects/SlugWatcher.php:31,84-193`).
- **Hit counter:** coalesced in memory + object cache, single `UPDATE` per touched rule on `shutdown`
  (`HitCounter.php:17-90`).
- **Settings** (`rankkernel_redirects_settings`, autoload no): `preserve_query` (on), `auto_slug_redirect`
  (on), `rules_per_page` (20), `schema_ok` (`RedirectsSettings.php:31-52`).

## 2.9 404 Monitor

- **Logging:** `Logger` hooks `template_redirect` priority **99**; dedupes by `uri_hash` (increments hits),
  skips static assets and probe URLs and sitemap requests, honours an optional response-code override,
  optional advanced fields (referer, user-agent) (`src/Modules/Monitor/Logger.php:34,144-170,177-320`).
- **Storage:** table `{prefix}rankkernel_404_log` — `uri_hash CHAR(64)` unique, `uri`, `hits`, `referer`,
  `user_agent`, `created`, `last_accessed`; created lazily on enable (`src/Modules/Monitor/LogTable.php:112-134`;
  `MonitorRepository.php:68-153`).
- **Pruning:** by age (`retention_days`, batch 500) **and** by count (`max_rows`, oldest-first, batch cap
  500) — never a blanket truncate (`src/Modules/Monitor/Pruner.php:27-109`). Runs via a queued action.
- **Exclusions:** comparators `exact`, `prefix`, `contains`, `suffix`, `wildcard`; max 200 rules, each
  ≤500 chars (`src/Modules/Monitor/Exclusions.php:30`; `MonitorSettings.php:39-45`).
- **Flood guard:** budget 50 new URIs per 300-second window; sets `rankkernel_404_suppressed` when
  triggered (`src/Modules/Monitor/FloodGuard.php:30-120`).
- **Settings** (`rankkernel_404_settings`, autoload no): `advanced_fields` (off), `retention_days` (30),
  `max_rows` (1000), `flood_budget` (50), `flood_window` (300), `ignore_query` (on), `exclusions` ([])
  (`MonitorSettings.php:21-100`). Growth bounds cannot be disabled (clamped).
- **Admin screen:** list + filters + pagination + sort, clear/bulk/row-delete, settings save, near/high
  limit warnings, recent activity, and a **1-click "create redirect"** that links to the Redirects page
  pre-filled (`src/Admin/NotFoundPage.php:39-806`, `453-484`).

## 2.10 Admin screens and exposed settings

| Screen | Slug | Settings exposed | evidence |
|---|---|---|---|
| RankKernel (top-level → Settings) | `rankkernel` | Module on/off for all 13 registry ids (`rankkernel_modules[]`), `title_template`, `description_template`, `separator`, 5 webmaster codes, 5 social fields (stored), `purge_on_uninstall`, breadcrumb separator + home label + appearance/behavior toggles + per-post-type primary taxonomy | `AdminMenu.php:155-181`; `Views/settings.php` inputs; `SettingsPage.php:57-205,205-277` |
| Sitemap | `rankkernel-sitemap` | Tabs general / post-types / taxonomies / authors: `items_per_page`, image options, `exclude_posts`, `exclude_terms`, `include_empty_terms`, per-type `pt_*_sitemap` / `tax_*_sitemap`, authors sitemap + empty + role/user exclusions | `AdminMenu.php:170-181`; `SitemapSettingsPage.php:31,80-441` |
| Schema | `rankkernel-schema` | `site_represents`, `org_name`, `org_logo`, `org_sameas`, `website_search_action`, per-post-type `schema_default_{type}`, `schema_breadcrumbs`, `schema_author` | `AdminMenu.php:190-202`; `SchemaSettingsPage.php:145-259`; `Views/schema-settings.php` |
| Redirects | `rankkernel-redirects` | CRUD list/add/edit/delete, search, status views, sort, `preserve_query`, `auto_slug_redirect`, `rules_per_page`, CSV import/export | `AdminMenu.php:211-223`; `RedirectsPage.php:36-1430` |
| 404 Monitor | `rankkernel-404` | list/filters/pagination, clear, bulk/row delete, `advanced_fields`, `retention_days`, `max_rows`, `flood_budget`, `flood_window`, `ignore_query`, exclusions, 1-click redirect | `AdminMenu.php:232-244`; `NotFoundPage.php:39-806` |

Also: a "Settings" action link is added on the Plugins row (`AdminMenu.php:142-149`). Assets are
screen-gated per page (`enqueueAssets` checks the hook suffix, e.g. `RedirectsPage.php:228-245`).

## 2.11 REST routes

Namespace `rankkernel/v1` (`src/Rest/SettingsController.php:27`, `ModulesController.php:27`).

| Route | Methods | Permission | evidence |
|---|---|---|---|
| `/rankkernel/v1/settings` | GET, POST | `manage_options` | `SettingsController.php:53-71,78-88` |
| `/rankkernel/v1/modules/(?P<id>[a-z0-9-]+)` | POST | `manage_options` | `ModulesController.php:60-78` |

Total: **2 route registrations / 3 method endpoints**. Blueprint-planned `GET /seo/{id}` and
`/preview/{id}` do **not** exist yet (`docs/architecture/blueprint.md:540-546` lists them as future).

## 2.12 Gutenberg blocks

| Block name | Module | Server render | evidence |
|---|---|---|---|
| `rankkernel/faq` | Schema | `FaqBlock::render` | `src/Modules/Schema/blocks/FaqBlock.php:114-119`; `blocks/faq/block.json` |
| `rankkernel/howto` | Schema | `HowtoBlock::render` | `src/Modules/Schema/blocks/HowtoBlock.php:159-164`; `blocks/howto/block.json` |
| `rankkernel/breadcrumbs` | Breadcrumbs | `BreadcrumbsBlock::render` | `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php:130-136` |

Block category: single shared `rankkernel` ("RankKernel", icon `editor-ul`), registered by
`SchemaModule::addCategory` and `BreadcrumbsModule::addCategory` (both idempotent)
(`SchemaModule.php:235-253`; `BreadcrumbsModule.php:212-230`; filter `block_categories_all`).

## 2.13 Template tags, shortcodes, public hooks/filters (extension points)

**Template tags / shortcodes:**
- `rankkernel_breadcrumbs(array $args): void` — `template-tags.php:29`
- `rankkernel_get_breadcrumbs(array $args): string` — `template-tags.php:49`, `functions.php:31`
- `[rankkernel_breadcrumbs]` shortcode — `BreadcrumbsModule.php:158`

**Public actions:**
`rankkernel/module/force_disabled` (`ModuleManager.php:156`),
`rankkernel/head/after_tags` (`HeadRenderer.php:186`),
`rankkernel/sitemap/ping` (`SitemapsModule.php:249`),
`rankkernel/migration/failed` (`MigrationRunner.php:123`),
`rankkernel/redirect/reentry` (`Redirector.php:109`).

**Public filters:**
`rankkernel/tokens` (`TagsReplacer.php:93`),
`rankkernel/schema/disabled`, `rankkernel/schema/needs_{id}`, `rankkernel/schema/piece/{id}`,
`rankkernel/schema/graph`, `rankkernel/schema/breadcrumb_trail` (`Generator.php:79-151`; `BreadcrumbsModule.php:155`),
`rankkernel/sitemap/enable_cache` (`SitemapCache.php:75`),
`rankkernel/sitemap/entries_per_page` (`IndexBuilder.php`),
`rankkernel/breadcrumbs`, `rankkernel/breadcrumbs/args`, `rankkernel/breadcrumbs/items`,
`rankkernel/breadcrumbs/post_type_settings` (`functions.php`; `BreadcrumbsSettings.php`),
`rankkernel/redirect/allowed_hosts` (`Redirector.php:263`).

**Core WP hooks the plugin occupies (when its module is on):** `wp_head`, `pre_get_document_title`,
`robots_txt`, `wp_sitemaps_enabled`, `pre_get_posts`, `query_vars`, `redirect_canonical`,
`transition_post_status`, `save_post`, `edited_terms`, `clean_term_cache`, `delete_term`, `user_register`,
`delete_user`, `profile_update`, `update_option_rankkernel_settings`, `update_option_rankkernel_modules`,
`shutdown`, `template_redirect`, `post_updated`, `add_meta_boxes`, `block_categories_all`, `admin_menu`,
`admin_notices`, `admin_enqueue_scripts`, `rest_api_init` (`src/` grep).

## 2.14 Meta keys, option keys, database tables, autoload

**Meta keys:**

| Key | Object type | Purpose | evidence |
|---|---|---|---|
| `_rankkernel_meta_data` | post | Single JSON payload (title, description, canonical, robots, og, twitter, focus_keywords, schema, flags) | `MetadataModule.php:127-140`; `MetaPayload.php:44-81` |
| `_rankkernel_term_data` | term | Same payload shape for terms | `MetadataModule.php:143-156` |
| `_rankkernel_user_prefs` | user | Per-user prefs (permissive schema) | `MetadataModule.php:159-172` |

**Option keys** (registered/used in `src/`, `uninstall.php`, `rankkernel.php`):

| Option | Autoload | Purpose |
|---|---|---|
| `rankkernel_settings` | yes (default) | Global settings | 
| `rankkernel_modules` | yes | Enabled-module list (the enable map) |
| `rankkernel_db_version` | **no** | Migration ledger |
| `rankkernel_sitemap_settings` | yes | Sitemap settings |
| `rankkernel_breadcrumbs_settings` | yes | Breadcrumb settings |
| `rankkernel_redirects_settings` | **no** | Redirect settings |
| `rankkernel_404_settings` | **no** | 404 monitor settings |
| `rankkernel_sitemap_validator_global`, `rankkernel_sitemap_validator_{set}` | **no** | Sitemap cache validators |
| `rankkernel_sitemap_code_version`, `rankkernel_rewrite_rules_version` | **no** | Cache/rewrite busters |
| `rankkernel_redirects_validator` | **no** | Redirect cache validator |
| `rankkernel_404_suppressed` | — | Flood-guard suppression flag |
| `rankkernel_conflict_notice` | — | Active competitor plugin names |

Autoload discipline: only the small always-needed options autoload; heavy/volatile payloads are written
with `autoload=false` (`SettingsStore.php:172` default yes; `SitemapSettings.php:166` true;
`BreadcrumbsSettings.php:167` true; `RedirectsSettings.php:180` false; `MonitorSettings.php` false;
`MigrationRunner.php:94,131` false; `SitemapCache.php:178-198` false).

**Custom database tables (2):**

| Table | Columns / keys | Created when |
|---|---|---|
| `{prefix}rankkernel_redirects` | id, match_type enum, source_hash CHAR(64), source, target, code enum, hits, is_active, created, last_accessed; UNIQUE(match_type, source_hash), KEY(is_active) | Redirects module enabled (`RedirectTable.php:112-134`) |
| `{prefix}rankkernel_404_log` | id, uri_hash CHAR(64) UNIQUE, uri, hits, referer, user_agent, created, last_accessed; KEY(last_accessed) | 404 module enabled (`LogTable.php:112-134`) |

Both tables are created through `ensureTables()` on the module-enable path, **not** through the
`MigrationRunner` ledger (`RedirectTable.php:18-22`; `LogTable.php`).

## 2.15 Migrations and importer

- **MigrationRunner:** versioned ledger in `rankkernel_db_version`; sorts pending migrations by
  `version_compare`, isolates failures (ledger not advanced past a failure, fires
  `rankkernel/migration/failed`, warns), idempotent by design (`src/Database/Migrations/MigrationRunner.php:28-134`).
  Only one baseline migration is registered: `'0.1.0'` with an empty closure (`src/Plugin.php:121-125`).
  The v1 core is deliberately schema-less; module tables bypass the ledger (see 2.14).
- **Importer:** **not implemented.** The `importer` id is in the registry and seeded ON, but there is no
  `src/Modules/Importer/` directory and no importer class. No detector, migration map, batch processor,
  dry-run or rollback exists (planned in `ROADMAP.md`).
- **Competitor conflict detection** at activation only warns; it does not import (`rankkernel.php:93-141`).

## 2.16 Test suite shape

- **82 PHPUnit test files** in `tests/Unit/` (all `*Test.php`); 992 test methods; **verified run:
  `OK (992 tests, 3460 assertions)`** on this HEAD (`vendor/bin/phpunit`, 3.87s, no DB required —
  Brain Monkey).
- Runs on PHPUnit 10.5 with `failOnRisky`/`failOnWarning` enabled (`phpunit.xml`).
- **Coverage by area** (derived from test filenames):
  - **Metadata/head:** Context, HeadRenderer, TagsReplacer, MetaPayload, MetaPayloadDecode.
  - **Schema:** SchemaPieces, SchemaBatch2/3/4, SchemaGenerator, SchemaModule, SchemaProduction,
    SchemaSettings, SchemaSettingsAdmin, SchemaMetabox, HowtoPiece, FaqBlock, HowtoBlock.
  - **Sitemaps:** IndexBuilder, IndexBuilderRouter, Router, RouterPlainMode, SitemapCache,
    SitemapCanonical, SitemapExclusions, SitemapInvalidation, SitemapRobotsDirective, SitemapSettings,
    SitemapSettingsAdmin, SitemapSettingsCache, SitemapSettingsProviders, SitemapAuthorsAttachments,
    SitemapsModule.
  - **Breadcrumbs:** Item, Settings, TrailBuilder, Renderer (Output), Module, Performance, Security,
    Schema integration.
  - **Redirects:** Matcher, Redirector, Repository, Cache, DispatchCache, Csv, ExportStream, HitCounter,
    Normalizer, Validator, DestinationValidator, SlugWatcher, PatternBound, LoopBoundary, QuerySemantics,
    SafetyPrecedence, Security, Settings, Module, Table, Admin, MonitorGating, MonitorUninstall.
  - **404 Monitor:** Logger, Pruner, FloodGuard, FloodRetention, Exclusions, Repository, Settings, Table,
    Module, Admin.
  - **Platform:** ModuleManager, ModuleRegistry, Plugin, SettingsStore, SettingsPage, AdminMenu,
    RestControllers, MigrationRunner, RedirectsMonitorUninstall.

---

# PART 3 — EXPLICIT GAPS ALREADY KNOWN (recorded in the planning docs, not yet built)

These are already written down elsewhere; listed here so they are not lost. Sources:
`docs/research/feature-gap-research-gate.md` (gaps §2, §13, priorities §16),
`ROADMAP.md` (Phase 3–7 and Deferred), `docs/competitor-analysis/feature-matrix.md`.

**Registry-reserved but unbuilt (module id exists, no code):** `importer`, `instant-indexing`
(IndexNow), `robots` (robots.txt & .htaccess), `image-seo`, `gutenberg`, `ai`, `headless`
(`docs/research/feature-gap-research-gate.md:29`; verified absent from `src/Modules/`).

**Not present at all (no registry id) — the recorded "planning failure" list
(`feature-gap-research-gate.md:31,208-223`):**
1. llms.txt generator + AI crawler controls (GPTBot/CCBot/Google-Extended presets).
2. Full per-context metadata/head parity: per-post-type/tax/author/date/search/404 templates with a
   variable editor and pixel guidance; parameterised tokens; robots directive controls; canonical
   noindex-suppression and pagination rules; next/prev links; description omit-by-default + opt-in
   autogenerate; social template layer + fallback ordering.
3. Snippet preview with pixel guidance (SERP/social previews).
4. Internal linking and orphan reporting.
5. Content analysis and SEO scoring (focus keyword placement/density, readability, multi-keyphrase).
6. HTML sitemap.
7. Local SEO single location (LocalBusiness schema exists as a schema piece; the location settings
   (name/address/phone/hours/geo/map/193 business types) UI does not).
8. WooCommerce free parity (product identifiers, OG price, hidden-product noindex, product sitemap).
9. News sitemap and `NewsArticle` schema; `VideoObject` + video sitemap auto-detect.
10. Header and footer code injection.
11. hreflang passthrough.
12. Head cleanup (generator, shortlink, RSD, WLW, oEmbed, emojis, pingback, powered-by, feeds, REST
    disallow, internal search cleanup, advanced URL cleanup).
13. Site-wide analyzer.
14. Settings export/import; 404 log export.
15. Redirect scheduled activation/expiration.
16. Custom schema builder with conditional display rules and variables (current build has a fixed
    type + field set + custom JSON, not a blank-canvas builder); speakable/Mentions/About/FactCheck/
    Dataset/Podcast extras (some pieces now exist — Dataset/PodcastEpisode/ClaimReview — but the
    builder UI and conditions do not).

**Other recorded gaps/deferrals:**
- `.htaccess` raw editor — recommend omit in v1 (availability risk); robots.txt/`.htaccess` split decision open.
- Aggressive head dedup — recommend opt-in/off by default (currently absent).
- llms.txt physical file — recommend virtual default with opt-in write.
- Link-index table — decision pending (would be the first table beyond redirects and 404).
- Advanced 404 fields/export — partial (advanced-fields opt-in exists; export missing).
- Analytics (GSC/GA4), rank tracking, remote AI — EXTERNAL DEPENDENCY, out of scope for a free product.
- Social image watermarking, `.htaccess` redirect sync — NOT APPROPRIATE (marketing / server coupling).
- Multisite purge, onboarding wizard, News/Video sitemaps — deferred with reasons
  (`ROADMAP.md`).

---

## Appendix — capability status at a glance (code-verified)

| Capability | Status today | Evidence anchor |
|---|---|---|
| Module hard gate + zero-cost off | Implemented | `ModuleManager.php` |
| Single-row post/term meta + registered REST meta | Implemented | `MetadataModule.php` |
| Metadata head (title/desc/robots/canonical/OG/Twitter/webmaster) | Implemented | `HeadRenderer.php` |
| Per-post SEO title/desc/robots/OG admin editing | **Missing** (REST/programmatic only) | only `SchemaMetabox.php` exists |
| Token grammar (parameterised) | Partial: flat `%%lowercase%%` only | `TagsReplacer.php` |
| Schema `@graph`, 26 types, 29 pieces, custom JSON | Implemented | `Generator.php`, `SchemaTypes.php` |
| Schema blank-canvas builder + conditions | Missing | — |
| XML sitemaps (posts/tax/authors/images, cache ON, XSL, robots directive) | Implemented | `Router.php`, `SitemapCache.php` |
| HTML / News / Video / product sitemaps | Missing | — |
| Breadcrumbs (builder, renderer, tags, shortcode, block, settings) | Implemented | `TrailBuilder.php`, `Renderer.php` |
| Redirects (6 match modes, 5 codes, CSV, slug watcher, cache-first) | Implemented (default OFF) | `Matcher.php`, `Redirector.php`, `CsvHandler.php`, `SlugWatcher.php` |
| 404 monitor (dedupe, prune age+count, flood guard, exclusions, 1-click redirect) | Implemented (default OFF) | `Logger.php`, `Pruner.php`, `FloodGuard.php` |
| REST `rankkernel/v1` settings + module toggle | Implemented (2 routes) | `SettingsController.php`, `ModulesController.php` |
| Gutenberg blocks (faq, howto, breadcrumbs) | Implemented | `blocks/*/block.json` |
| Importer | Missing | no `src/Modules/Importer/` |
| Instant Indexing (IndexNow) | Missing | no module |
| robots.txt / `.htaccess` editors | Missing | no module |
| Image SEO, Gutenberg suite, AI (BYO key), Headless | Missing | no modules |
| Content analysis / internal linking / llms.txt | Missing | — |
| Settings export/import, 404 export | Missing | — |
| Tests | 82 files / 992 tests / 3460 assertions, green | `vendor/bin/phpunit` |
