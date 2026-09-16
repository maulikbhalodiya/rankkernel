# RankKernel, Master Build Plan & Progress Tracker

> Single source of truth for the whole build. Updated after every merge.
> Repo: `github.com/maulikbhalodiya/rankkernel` · Working copy = the live plugin folder.
> Every step below has a **Do:** checklist, anyone picking it up knows exactly what to build without asking.

## The loop (every step, fixed)

| # | Step | Owner |
|---|---|---|
| 1 | Create issue (title = step name) | me (automated) |
| 2 | Branch `GH-<issue#>` from main | me |
| 3 | Build + unit tests + gates: `composer lint && composer stan && composer test` | me |
| 4 | PR titled `GH-<n>: <summary>` + description + `Closes #<n>` | me |
| 5 | Merge | me (or you) |
| 6 | This file updated + gates re-run on main | me |

**Status:** ✅ done · 🔨 in progress · ⬜ pending · ⏸️ deferred

## Snapshot

| | |
|---|---|
| Version | 0.1.0 · main @ 606fc95 · 992 tests / 3460 assertions · `phpcs` clean · `phpstan` level 6 clean |
| Parity audit | 379 features catalogued: 78 DONE, 19 PARTIAL, 82 PLANNED, 163 MISSING, 14 EXTERNAL, 23 N/A |
| Done | Phase 0, Phase 1, Phase 2 core (modules 2.1 to 2.5), WordPress Coding Standards compliance |
| Next | STEP 1, Core Functionality and Minimal Admin UI Scaffolding |
| Gate | Feature gap research gate completed 2026-09-15; roadmap reorganised into the 4-step sequence |
| Token | `~/.config/rankkernel/.gh-token` (90d) · pushes via SSH alias `github-maulik-repo` |

---

## Completed

### Phase 0, Research & Planning ✅

| # | Item |
|---|---|
| 0.1 | Yoast free+Premium reverse-engineering audit (14 sections, fact-checked) |
| 0.2 | Rank Math free audit + ~30-claim verification |
| 0.3 | Feature matrix + gap analysis (their weaknesses → our counter-designs) |
| 0.4 | Name collision checks → **RankKernel**; all identifiers locked |
| 0.5 | Architecture blueprint (modules, data layer, head pipeline, sitemaps, redirects, schema, importer, REST, AI, build order) |
| 0.6 | WP.org 2026 compliance doc + clean-room policy doc |

### Phase 1, Foundation ✅

| # | Item | Proof |
|---|---|---|
| 1.1 | Guarded bootstrap, Plugin singleton, uninstall purge (default retain) | gates |
| 1.2 | Module hard gate, OFF = never loaded = zero hooks; enable map read once | zero-hook tests |
| 1.3 | SettingsStore (whitelist, cached defaults), autoload discipline | live-DB check |
| 1.4 | REST `rankkernel/v1`: settings + module toggle (strict booleans) | cap/nonce tests |
| 1.5 | Migrations runner (ledger, failure isolation, idempotent) | failure-path tests |
| 1.6 | Metadata Engine: single meta key (1 query vs their 25 to 45), memoized tokens, single-pass head | ≤1-meta-query test |
| 1.7 | Admin settings page v1 + Settings link on Plugins row (plain WP styles) | PR #3 |
| 1.8 | Author → Maulik Bhalodiya | PR #4 |
| 1.9 | Live-site DB validation via Local socket | manual, all green |

### Phase 2, Technical SEO Engine ✅ (core complete)

> **Phase 2 core is complete. Phase 2 follow-up capability remains** and is scheduled in STEP 1 below.
> The Phase 2 modules that shipped (2.1 to 2.5) are genuinely complete. The metadata/head layer that
> 2.x assumed is only a v1 engine, and robots.txt, AI crawler controls and llms.txt were omitted from
> Phases 2 to 5. See `docs/research/feature-gap-research-gate.md`.

#### 2.1 XML Sitemaps ✅ (issue #7, PR #8)
**Get:** `sitemap_index.xml` + per-type sitemaps, images inside, XSL stylesheet, cache ON, WP core sitemap takeover.
**Do:**
- Router: rewrite `sitemap_index.xml`, `([^.]+)-sitemap([0-9]+)?\.xml`, `sitemap.xsl`; query vars `rankkernel_sitemap/rankkernel_sitemap_n/rankkernel_sitemap_xsl`; `pre_get_posts` intercept → build → `exit`; disable `redirect_canonical` for sitemap requests; strip theme actions on render
- Providers: per-post-type, per-taxonomy, authors, direct `$wpdb` listing, 1000 entries/page, `lastmod` from `post_modified_gmt`
- XSL: bundled, served via `readfile` + long cache headers
- Cache: object-cache group `rankkernel-sitemaps` + transients; **default ON** (filter `rankkernel/sitemap/enable_cache`); validators (global + per-type) stored in options; invalidation queued on `save_post/edited_terms/user_register`, flushed on `shutdown`
- Takeover: `add_filter('wp_sitemaps_enabled', '__return_false')` + admin notice when ON (blueprint §O1)
- Ping hook point: `do_action('rankkernel/sitemap/ping')` on publish (cache-warm only, never claim engine ping)
- Tests: routing, provider slicing, cache hit = no rebuild, validator invalidation
- Parity #9: robots.txt `Sitemap:` directive (strips the stale core line, skips private blogs)
- Parity #9: noindex exclusion for posts and terms via payload LIKE (authors deferred, no author robots model yet)
- Parity #9: password protected posts excluded from entries and counts
- Parity #9: canonical mismatch dropped PHP side with one batched meta read per page (counts stay approximate)
- Parity #9: plain permalink URL forms plus `Router::sitemapUrl/indexUrl/xslUrl` used by the index builder
- Parity #9: `/sitemap.xml` 301 redirects to the index
- Parity #9: author rules locked in (only public type authors listed, role exclusion deferred to settings UI)
- Parity #9: attachments never listed in sets, entries, or counts
- Parity #9: invalidation on `delete_user/profile_update/clean_term_cache/delete_term` (per taxonomy where the hook provides it)

#### 2.2 Schema / JSON-LD ✅ (issue #11, merge 2ea6846)
**Get:** one `<script type="application/ld+json">` per page, assembled lazily.
**Do:**
- Generator + piece registry; each piece: `is_needed($ctx)` + `build($ctx)`; single `@graph`, emitted in the `rankkernel/head/after_tags` slot
- Pieces: WebPage, Article, Organization/Person (site rep), BreadcrumbList, FAQ, HowTo, Product, Recipe, Event, LocalBusiness
- `@id` interlinking (`isPartOf`/`breadcrumb`), manual overrides from payload `schema` array
- No separate meta rows (RM antipattern), lazy assembly at render
- Tests: lazy is_needed, one tag only, JSON escaping

#### 2.3 Breadcrumbs ✅ (issue #15, branch GH-15)
**Get:** breadcrumb trail + shortcode + block; zero cost when unused.
**Do:**
- Generator: home → CPT archive → taxonomy parents → term → paginated; context-aware (single/archive/search/404)
- Output: filter `rankkernel/breadcrumbs`, shortcode `[rankkernel_breadcrumbs]`, server-rendered block (no JS)
- Settings: separator, home label, hide-on-front-page
- Lazy: only builds when requested or when Schema's BreadcrumbList piece needs it
- Tests: zero queries when not rendered, hierarchy correctness

#### 2.4 Redirects ✅ (issue #12, merge 348e1e3) (default OFF)
**Get:** full redirect manager free (Yoast Premium territory), cache-first cost.
**Do:**
- Migrations create tables ON module enable: `wp_rankkernel_redirects` (source_url_hash UNIQUE, code enum 301/302/307/410/451, regex flag, hits) + `wp_rankkernel_redirects_cache`
- Redirector: `template_redirect` priority 1; guards `is_admin()/wp_doing_ajax()`; cache hit = 0 queries; miss = 1 targeted query + cache warm
- Exact + regex match; hits counter; CSV import + export (free, Yoast Premium-only feature)
- Slug-change watcher: `post_updated` → old vs new slug → auto-301 (setting to disable)
- Admin: list/add/edit/delete, search, hit counts (reuses 4.1 layout if landed, else plain)
- Tests: OFF = zero hooks; hit = 0 queries; miss = 1; regex; CSV round-trip

#### 2.5 404 Monitor ✅ (issue #12, merge 348e1e3) (default OFF)
**Get:** 404 log with sane pruning + 1-click redirect creation.
**Do:**
- Table `wp_rankkernel_404_log` (uri_hash indexed, uri, referer, user_agent, created)
- Capture on 404s; prune by retention days AND count (oldest-first DELETE), never blanket truncate (RM bug fixed)
- Admin list + "create redirect" button per row
- Tests: prune policy, history retained

---

### 2.7 Head engine hardening and the metadata editor ⬜ (issue #27, branch GH-27, awaiting manual verification)
**Get:** fix the defects the six module audit found, then ship the final per-post metadata editing surface.
**Do:**
- Canonical: unhook core `rel_canonical` so exactly one canonical is emitted, including when an override is set
- Robots: contribute directives through the single core `wp_robots` tag with most restrictive wins, so no duplicate tag and core plus third party directives survive
- Archive tokens: resolve `%%title%%`, `%%author%%` and `%%category%%` against the queried term or user on term and author archives
- Legacy payloads: route `Context::meta()` through `MetaPayload::decodeMetaValue()`
- Custom schema: never wipe stored custom schema on invalid JSON
- Sitemaps: escape XML text exactly once so an ampersand is not double encoded
- Redirect cache: invalidate only on writes, so the cache first lookup actually happens and no option write occurs per page view
- Metadata editor: Classic Editor meta box plus the PHP side of a Gutenberg sidebar, three tabs, token quick insert limited to backend resolvable tokens, template versus override signalling, per field reset, media library image pick and remove, live SERP preview with desktop and mobile frames and pixel budgets, and a live social unfurl card
- Tests: a regression test per engine fix, plus a contract test that asserts every hook the editor JavaScript queries is actually rendered by the view
**Status:** code complete, 1035 tests and 3628 assertions green, phpcs and phpstan clean. Branch GH-27 is NOT merged and the editor has NOT been verified in a live browser session, so nothing here is marked done yet.

---

## Module micro-gap audit

For each of the six shipping modules: what is DONE, then the outstanding micro-gaps as build items. Every micro-gap below is also carried into STEP 1 so nothing lives only here.

### Metadata Engine (metadata)
DONE: single-pass head renderer on wp_head priority 1, meta description hierarchy, robots directives, canonicals, Open Graph, Twitter cards, basic flat lowercase tokens, webmaster verification.
DELIVERED, AWAITING MANUAL BROWSER VERIFICATION (issue #27, branch GH-27, unit tested and contract tested, not yet verified in a live editor session):
- Classic Editor meta box and the PHP side of a Gutenberg sidebar for per-post Title, Description, Canonical, Robots and Social overrides. Three tabs (General, Social, Advanced), token quick insert restricted to backend resolvable tokens, template versus override signalling with a per field reset, and media library pick and remove for the Open Graph and Twitter images.
- Live Google SERP preview with desktop and mobile frames and pixel and character budgets, and a live social unfurl card that falls back to the General values and the default Open Graph image.
ENGINE FIXES DELIVERED (issue #27, all with regression tests): core rel_canonical unhooked so exactly one canonical is emitted, robots merged into the single core wp_robots tag with most restrictive wins, archive contexts resolve %%title%%, %%author%% and %%category%% against the queried term or user, Context::meta routes legacy rows through decodeMetaValue, invalid custom schema JSON no longer wipes stored custom schema, sitemap loc URLs escape exactly once, and the redirect cache is invalidated only on writes so cache first lookups actually happen.
MICRO-GAPS REMAINING:
- Per-context metadata templates for post types, taxonomies, homepage, author, date, search and 404.
- Parameterised token syntax such as token with arguments, and extended built-in variables: custom fields, parent title, term name, page numbers, product fields.
- Global default Open Graph image and a Twitter site and creator handle, since the stored social profile settings are not yet consumed by the head renderer.

### Schema Engine (schema)
DONE: single @graph JSON-LD output, 26 types across 29 pieces, post metabox, custom JSON support.
MICRO-GAPS TO BUILD:
- Term and taxonomy schema metabox.
- Schema display conditions and template manager.
- Blank-canvas custom schema builder UI.

### XML Sitemaps (sitemaps)
DONE: post, taxonomy and author XML sitemaps, inline image parsing, object caching on by default, virtual rewrite routes, core sitemap takeover.
MICRO-GAPS TO BUILD:
- HTML sitemap shortcode and dedicated page output.
- Custom URL injection filter for third-party endpoints.

### Breadcrumbs (breadcrumbs)
DONE: trail builder, accessible aria-current markup, template tags, shortcode, server-rendered block.
MICRO-GAPS TO BUILD:
- Per-post primary term and category selector UI.

### Redirects (redirects)
DONE: six match modes, five HTTP status codes, automatic slug change watcher returning 301, CSV import and export.
MICRO-GAPS TO BUILD:
- Scheduled redirect activation and expiration.

### 404 Monitor (404)
DONE: deduplicated logging, dual pruning by age and count, flood guard, exclusions, one-click redirect creation.
MICRO-GAPS TO BUILD:
- 404 log CSV export.
- Bulk 410 creation from selected log rows.

---

## 4-step build sequence

```
STEP 1  Core Functionality and Minimal Admin UI Scaffolding
  |
  v
STEP 2  Full Testing, QA and v1.0 Core Release Verification
  |
  v
STEP 3  BYO-Key Local AI Suite
  |
  v
STEP 4  External Paid APIs (post v1.0 deferred releases)
```

### STEP 1, Core Functionality and Minimal Admin UI Scaffolding ⬜

This is where every non-AI feature and every micro-gap above is built. The admin UI delivered in this step is minimal and functional, so QA can validate logic, and visual polish is a later pass, not part of Step 1. Every item below keeps its original Do checklist and is grouped under the phase it came from.

#### 1.1 Head Engine parity (from the head and crawl controls phase)

**Get:** the full metadata and head layer, all local and free. Closes the largest genuine parity gap. Source: `docs/research/feature-gap-research-gate.md`.

**Head Engine (extends the `metadata` module):**
- Single presenter ordered head action: Title, Description, Robots, Canonical, Open Graph, Twitter, Schema hook; each presenter `should_render()`, `get_value()`, `render()`, with a rendered flag to prevent duplicates
- Token resolver, one grammar (`%name%` plus optional arguments), covering site, post, term, archive, pagination, search, 404, org and custom field tokens, parameterised list tokens and date format arguments; a documented mapping table for importers (`%%var%%` and `%var%`)
- Per-context template layer: global defaults, per post type, per taxonomy, then per object override
- Meta description omit-by-default with an opt-in per type autogenerate (sentence-boundary trimmed)
- Robots directives: index/noindex, follow/nofollow, noarchive, nosnippet, noimageindex, and `max-snippet`, `max-image-preview`, `max-video-preview`; merge with core `wp_robots` restrictive-wins
- One `is_indexable()` gate drives robots, sitemap inclusion and canonical together
- Canonical builder: omit on noindex and error responses, pagination rules, trailing slash, primary taxonomy preference, per object override, adjacent rel next/prev
- Social builders: minimal Open Graph set with width and height, Twitter tags emitted only when the value differs, documented image fallback order
- Snippet preview REST endpoint with character and pixel budgets (about 600px title, about 920px description), desktop and mobile, plus Facebook and X card previews
- Duplicate prevention: remove core `rel_canonical` and shortlink duplicates; optional opt-in aggressive dedup that buffers `wp_head` and strips foreign title, description, robots, canonical, OG and Twitter tags
- Tests: token grammar, per-context resolution, robots merger, canonical rules, description fallback, social fallback, one tag each, dedup, capability and nonce

#### 1.2 Crawl Signals (extends the reserved `robots` id)

**Do:**
- Virtual robots.txt editor on the `robots_txt` filter: start from `$output`, honour `$public`, never write a physical file
- Directive validation: UTF-8 normalisation, BOM and control character stripping, directive allow-list, absolute `http(s)` sitemap URLs with cross-host warning, size cap, preview, and a "bypassed by a physical file" notice that never deletes the file
- Free AI crawler presets (GPTBot, OAI-SearchBot, ChatGPT-User, ClaudeBot, PerplexityBot, Google-Extended, CCBot, and others verified against live vendor docs at build time), rendered above the `*` group
- llms.txt virtual generator (rewrite plus `Content-Type: text/markdown`, `X-Robots-Tag: noindex`), H1 site name, blockquote summary, H2 sections per post type and taxonomy, item excerpts trimmed and stripped; selection excludes noindex, private, password and attachment content; counts capped; opt-in physical file write that refuses to overwrite an existing file
- Honest UI note that llms.txt is a proposal and that Google Search ignores it
- Consistency warning when llms.txt advertises a URL disallowed in robots.txt
- Storage: options only (`rankkernel_robots_*`, `rankkernel_llms_*`), no tables
- Tests: directive validation and rejection, sitemap line rules, llms selection and excerpt hygiene, injection safety, no per page view cost

#### 1.3 Content & Import (from the content and import phase)

##### 1.3.1 Importer ⬜ (default ON)
**Get:** 1-click migration from Yoast / Rank Math / SEOPress, safe by design.
**Do:**
- Detector: active-plugin check + one COUNT per meta prefix (`_yoast_wpseo_%`, `rank_math_%`, `_seopress_%`)
- Migration map (blueprint §I.2): titles, descriptions, canonical, robots bits, og/twitter, focus keywords, `rank_math_schema_*` rebuild
- Batch processor N=50 with resume; dry-run preview first (counts + samples, zero writes); rollback snapshot in transient; originals kept until confirm
- Options import: curated subset only (templates, social profiles, webmaster codes), never blind-copy option blobs
- Unmapped keys logged, never silently dropped
- Tests: map correctness, batch resume, dry-run writes nothing

##### 1.3.2 Gutenberg Suite ⬜ (default OFF)
**Get:** modern editor sidebar: unlimited focus keywords, readability, live social previews.
**Do:**
- Tooling: `@wordpress/scripts` in `src-js/`, bundles to `assets/build/` (gitignored); no CDN, local assets only
- Sidebar: title/description editors with token hints, unlimited focus keywords, client-side readability checks (no server round-trip), live OG/Twitter previews, per-post schema entry point
- Data flows through the registered REST meta field (`_rankkernel_meta_data` show_in_rest), no custom bulk endpoint
- Enqueue ONLY on block editor screens; zero frontend assets
- Tests: enqueue gating, meta REST round-trip

##### 1.3.3 Content Analysis ⬜ (P1, default ON, local only)
**Get:** the deterministic SEO and readability analysis both competitors ship, with no external service.
**Do:**
- Local checks only: focus keywords (unlimited, free), keyphrase in title, description, URL, intro, headings, distribution, density, content length, image alt coverage, internal and external link counts, paragraph and sentence length, passive voice, transition words, consecutive sentences, subheading distribution, reading time
- SEO and readability scores with pass, improve, problem states
- Title and description length in characters and pixels, advisory only, never blocking
- Client side in the editor with a PHP parity path for headless and REST
- No AI, no remote calls; an optional BYO-key assist lives in STEP 3
- Tests: each check with positive and negative fixtures, score calculation, PHP and JS parity

##### 1.3.4 Image SEO ⬜ (P1, default OFF)
**Get:** the free image SEO Rank Math ships; Yoast gates most of it.
**Do:**
- Auto alt and title via pattern templates with variables and a live preview; frontend attribute filters only
- Missing alt detection report
- Bulk backfill in batches of 50, WP-CLI driven, resumable
- Attachment redirect and image sitemap entries completed (2.1 left these partial)
- Tests: pattern resolution, batch and resume, frontend attribute filter, missing alt report

##### 1.3.5 Internal Linking ⬜ (P1 report, P2 suggestions)
**Get:** orphan and link reporting free, then suggestions.
**Do:**
- One link index table (`wp_rankkernel_links`: post id, target url, anchor, internal flag, nofollow, updated) plus a postmeta cache; populated by a background batch with incremental updates on save, never on the request path
- Orphan report: published and indexable posts with zero incoming internal links, with filters and pagination
- Link counter and broken link report
- Suggestions from token overlap or a keyword map, ranked by title similarity, capped per post; never auto-inserted without explicit approval
- Tests: index correctness, orphan query, suggestion ranking, cap, batch resume

##### 1.3.6 Settings export and import ⬜ (P2)
**Do:** export the RankKernel option set as JSON, import with validation and a dry run, never importing competitor blobs, and a per option whitelist. Tests: round trip, invalid file rejection, whitelist enforcement.

#### 1.4 Admin UI & Design (from the admin UI phase)

Step 1 builds the structure and wiring only: menus, pages, forms and REST keys on plain WordPress styles so QA can validate logic. The branded visual polish is a later pass and is not part of Step 1.

##### 1.4.1 Functional admin shell and pages ⬜
**Get:** the full page set and navigation, the structure, none of the spam.
**Do:**
- Sidebar menu with sub-pages: Dashboard, General, Titles & Meta, Sitemaps, Schema, Breadcrumbs, Tools (redirects/404), Import, AI
- Dashboard page: stat cards (enabled modules, meta rows, redirect count, 404 count, sitemap health) + quick links
- Tabbed sub-pages, consistent form components, clean alert styling (plain WP styles in Step 1)
- Migrate Phase-1 settings page content into the new pages (REST + option keys unchanged)
- **Zero nags/upsells/notification-center, ever** (also keeps PCP happy)
- Tests: menu structure, enqueue gating, options keys stable after migration
- Visual layer: design tokens as CSS vars (colors/spacing/type), a single `admin.css` enqueued ONLY on RankKernel screens, branded header, deferred to the polish pass

##### 1.4.2 Benchmark dev panel ⬜ (WP_DEBUG only)
**Get:** the proof behind our performance claims.
**Do:**
- Hidden submenu when WP_DEBUG: current-request query count, peak memory, TTFB
- WP-CLI seed script (1000 posts / 200 terms) + runner: median over 20 hits vs Yoast/RM installs
- Results → `docs/benchmarks/` table + SVG chart, regenerated per release

#### 1.5 Technical SEO Extras (from the technical SEO extras phase)

##### 1.5.1 Instant Indexing (IndexNow) ⬜ (P1, default OFF)
**Do:** key generation + verification file route; submit on publish/update/delete to api.indexnow.org (Bing/Seznam/Yandex); batch + retry; failure log; off = zero requests. Tests: payload, key verification, off = zero requests.

##### 1.5.2 Head cleanup ⬜ (P1, default OFF)
**Do:** strip generator, shortlink, RSD, WLW, oEmbed, emoji, pingback and powered-by output; feed controls (global, comments, per post type, taxonomy, search, Atom and RDF); REST disallow for unauthenticated requests; internal search cleanup; advanced URL cleanup that strips unknown query parameters with an allow-list (never `utm_*`, `gclid`, registered params) and skips logged in users. Tests: each toggle on and off, allow-list safety, logged in bypass.

##### 1.5.3 Header and footer code injection ⬜ (P2, default OFF)
**Do:** admin only raw head and footer code, gated behind `manage_options` plus `unfiltered_html`, escaped on output where not raw HTML, never executed in admin preview, and off = zero output. Tests: capability gate, output placement, off = no output.

##### 1.5.4 hreflang passthrough ⬜ (P2)
**Do:** filters and passthrough so multilingual plugins can supply alternate URLs; no built-in translation. Tests: filter contract, no output when no provider.

##### 1.5.5 Site analyzer ⬜ (P2, fully local)
**Do:** a local deterministic site scan (robots, sitemap, indexability, titles and descriptions, schema presence, redirect and 404 health) with no remote API and no outbound calls. Tests: each check, no outbound request.

##### 1.5.6 404 advanced export and redirect scheduling ⬜ (P2)
**Do:** 404 log CSV export with a date range; redirect scheduled activation and expiration evaluated at match time (no cron). Tests: export shape, scheduling window boundaries.

##### 1.5.7 `.htaccess` editor ⬜ (P2, optional and gated, recommend omit)
**Do (only if approved):** Apache only, disabled on Nginx and IIS with copyable snippets; `manage_options` plus super admin on multisite; screen hidden when `DISALLOW_FILE_EDIT` is set; nonce plus typed confirmation plus a per session unlock; versioned backups stored outside the web root with one click restore; refusal to touch the managed WordPress marker block; mandatory unified diff preview and second confirmation; never modified on uninstall. Tests: backup, diff, restore, marker protection, unwritable, Nginx detection. **Recommendation: omit entirely and keep redirects at the PHP layer.**

#### 1.6 Commerce, Local, News and Video (from the commerce, local, news and video phase)

##### 1.6.1 WooCommerce SEO, free parity ⬜ (P1, default OFF)
**Do:** product title and description templates with Woo tokens; Product, Review, Offer and Brand schema with GTIN and MPN including variations; gallery aware OG image; hidden and out of stock noindex toggle; product category and tag base stripping; product sitemap inclusion; filter parameter canonical hygiene; product and category SEO settings. Matches the Rank Math free scope. Tests: schema fields, identifier handling, hidden product noindex, sitemap inclusion.

##### 1.6.2 Local SEO, single location ⬜ (P2, default OFF)
**Do:** single location data (name, type from the Schema.org enum, address, phone, opening hours, geo, price range, sameAs) stored in options, emitted as LocalBusiness or Store JSON-LD, plus contact shortcode and map embed; 192 business types. Tests: schema shape, hours spec, capability gate.

##### 1.6.3 News SEO ⬜ (P2, default OFF)
**Do:** News sitemap (48 hour window) plus NewsArticle schema, per article news controls. Tests: window correctness, schema fields, exclusion of non news content.

##### 1.6.4 Video SEO ⬜ (P2, default OFF)
**Do:** detect iframe and video embeds at save, emit VideoObject (name, description, thumbnail, duration, uploadDate) with a manual override field, plus a video sitemap. No remote thumbnail fetching in v1. Tests: detection, schema fields, sitemap inclusion, no outbound request.

#### 1.7 Module micro-gaps (build items from the audit above)

##### 1.7.1 Metadata micro-gaps ⬜
- Block and Classic Editor meta box UI for per-post Title, Description, Robots, Canonical and Social overrides (today only the Schema metabox exists).
- Per-context metadata templates for post types, taxonomies, homepage, author, date, search and 404.
- Client-side SERP snippet preview with pixel guidance, plus visual social card previews.
- Parameterised token syntax such as token with arguments, and extended built-in variables: custom fields, parent title, term name, page numbers, product fields.

##### 1.7.2 Schema micro-gaps ⬜
- Term and taxonomy schema metabox.
- Schema display conditions and template manager.
- Blank-canvas custom schema builder UI.

##### 1.7.3 Sitemaps micro-gaps ⬜
- HTML sitemap shortcode and dedicated page output.
- Custom URL injection filter for third-party endpoints.

##### 1.7.4 Breadcrumbs micro-gaps ⬜
- Per-post primary term and category selector UI.

##### 1.7.5 Redirects micro-gaps ⬜
- Scheduled redirect activation and expiration.

##### 1.7.6 404 micro-gaps ⬜
- 404 log CSV export.
- Bulk 410 creation from selected log rows.

#### 1.8 Headless REST and WPGraphQL ⬜ (P3, default OFF)

**Do:** `GET /rankkernel/v1/seo/{id}` (full payload + computed tags), preview endpoint; register WPGraphQL `seo` fields only when WPGraphQL is active. Tests: payload shape, permission callbacks.

### STEP 2, Full Testing, QA and v1.0 Core Release Verification ⬜

Freeze the v1.0 core candidate once STEP 1 is feature complete. No new features land after the freeze, only fixes.

**Required gates, all green before tagging:**
- `vendor/bin/phpunit`: zero failing tests.
- `vendor/bin/phpcs --standard=phpcs.xml`: zero errors.
- `composer stan`: clean.

**WordPress.org readiness (moved here from the old QA and launch phase):**

| # | Do |
|---|---|
| 8.1 | Plugin Check (PCP) full pass, fix every error (the WP.org approval gate) |
| 8.2 | readme.txt final: description, FAQ, screenshots, changelog; matches header exactly |
| 8.3 | Benchmark protocol vs Yoast/RM (measured medians, never estimates) → publish |
| 8.4 | Original banner 772×250 + icon 128×128 (no trademarks) |
| 8.5 | SVN submission via wp.org account `maulikbhalodiya` + review replies (5 to 14 business days) |
| 8.6 | Tag v1.0.0 · README badges · announcement |

### STEP 3, BYO-Key Local AI Suite ⬜

AI features powered strictly by user supplied API keys. AI titles, meta descriptions, alt text and summaries. Providers: OpenAI, Gemini, Anthropic. Hard rule: no paid SaaS subscription is ever bundled.

Key storage already planned: per-user `_rankkernel_user_prefs` plus site default `rankkernel_ai_keys` (autoload no); keys never logged; client throttle plus a server per-key cap.

**Do (from the AI content the roadmap already holds):**
- `ProviderInterface` (title/description/alt/FAQ-schema) + OpenAI/Anthropic/Gemini providers; **BYO-key**, per-user `_rankkernel_user_prefs` + site default `rankkernel_ai_keys` (autoload no); keys never logged; client throttle + server per-key cap; promise: data leaves only to the chosen provider
- No bundled AI, no Content AI style subscription, no visibility tracking service
- Tests: provider abstraction, rate guard, key redaction, no key = no calls
- Follow-on within this step, still BYO-key only: AI Optimize, AI Summarize and AI Content Planner, strictly read-and-suggest, never auto-write

### STEP 4, External Paid APIs (post v1.0 deferred releases) ⏸️

Defer every paid third party dependency here. This is the complete EXTERNAL list from section 8 of `feature-parity-master.md`, each service named.

| Feature | Service | Why it cannot be free |
|---|---|---|
| Competitor SEO analysis | RankMath.com SEO Analyzer API | The remote analyzer runs on Rank Math servers and needs a paid Rank Math account. |
| Side-by-side SEO comparison | RankMath.com SEO Analyzer API | Same remote endpoint, paid account required. |
| Competitor site SEO audit via MCP | RankMath.com SEO Analyzer API | Same remote endpoint, paid account required. |
| Search intent analysis | Rank Math Content AI | Keyword intent is computed by the paid Content AI service. |
| Google Trends data | Google Trends via Rank Math | Rank Math brokers Trends data through its paid service account. |
| Semrush keyword data and Semrush integration | Semrush | Keyword volume, trend, difficulty and intent come from the paid Semrush API. |
| Wincher rank tracking and Wincher integration | Wincher | Free Wincher is capped; tracking keyphrases at scale needs a paid Wincher subscription. |
| Keyword rank tracker | Rank Math tracked-keyword quota, or Wincher | Rank Math sells tracked-keyword quota by plan, and third-party tracking needs a paid Wincher plan. |
| AI search traffic tracker | Rank Math Content AI and analytics AI-referrer service | Requires the paid Content AI service to classify AI traffic. |
| AI brand visibility tracking | Rank Math AI Visibility, or Yoast AI+ | Paid AI visibility service, sold as part of a paid plan. |
| Generative long-form AI writing | Paid AI provider (OpenAI, Anthropic, Google and similar) | Model calls cost money; RankKernel offers only a BYO-key subset for titles and alt text, never a bundled paid service. |
| Local map, store locator or GPS | Google Maps JavaScript and Embed API with a billing-enabled key | The Maps API requires a billing-enabled Google Cloud project key. |
| Algolia site-search integration | Algolia | Requires a paid Algolia account and index. |

Note on free-but-external services, not counted as EXTERNAL because they are not paid third party services and can be offered free with the user's own credentials: Google Search Console and Google Analytics 4 data (planned via a read-only Site Kit bridge), Google PageSpeed Insights, Google AdSense reporting, Google Indexing API, and any user supplied AI provider key. These need a user account or OAuth grant but not a payment to a vendor, so they are tracked as PLANNED or MISSING rather than EXTERNAL.

---

## Deferred and out of scope

Every entry below is deferred for a stated reason, not for lack of a plan. Anything deferrable only because it depends on an external service is called out as such.

| Item | Why |
|---|---|
| Analytics (GSC/GA4) | Requires an external service and OAuth. Site Kit bridge instead. |
| Content AI style bundled generation | Needs a paid backend or a bundled key, which conflicts with free forever. Ships as BYO-key in STEP 3. |
| AI visibility tracking | Needs a third party data service. Out of scope. |
| Onboarding wizard | Decide during the admin UI work. |
| Multisite network purge | v1 is single site (blueprint §O3). |
| Rank tracking | Requires a SERP data service. Out of scope v1. |
| Remote video thumbnail fetching | Outbound calls and rate limits. Manual thumbnail override in 1.6.4 instead. |
| Nginx and IIS config editing | No portable server config API. Copyable snippets only, see 1.5.7. |
| .htaccess editing (1.5.7) | Recommended to omit. PHP redirect layer already covers the use case. |
| Paid plans and pricing tiers | RankKernel is free forever with no paid tier. |
| Licence activation and enrolment | There is no licence model and no licence code path exists. |
| Multisite licence rules | No licence model to scope. |
| Client site quota per account | No account or quota model. |
| Priority or 24/7 support | Community support is the model for a free plugin. |
| Bulk discounts and agency reseller programme | No paid product to discount or resell. |
| Money-back guarantee and refund policy | No purchase exists. |
| White-labelled email reports | Agency client reporting, not a free-plugin concern. |
| Client management for client sites | Agency multi-client feature. |
| Academy or paid training | External training product, not plugin functionality. |
| Google Docs add-on | External product, not a WordPress plugin. |
| Shopify app | Different platform, not WordPress. |
| Rank Math Vault credential sharing | No vendor support channel requires site credentials. |
| Google data retention and fetch-frequency plan limits | Paid plan limits, meaningless without a plan. |
| rel=next and rel=prev tags | Google deprecated them in 2019, so RankKernel deliberately does not emit them. |
| Watermarked social images | Marketing value only, with no SEO benefit; deliberately excluded. |
| Redirect .htaccess sync | Server coupling and availability lockout risk; the gate recommends omitting it. |
| Indexables table for fast meta output | RankKernel uses a single-key meta row by design and has no derived store to build or rebuild. |
| Zapier automated publishing | Deprecated in Yoast 20.7 and dependent on external accounts. |
| WP Rocket cross-sell | Third-party plugin cross-sell, not functionality. |
| Imagify cross-sell | Third-party plugin cross-sell, not functionality. |
| Notification centre and upsell banners | Product rule: zero nags, upsells and notification centre, ever. |

## Standing rules (never change)

1. Free forever, every feature ships free; no Pro tier, no upsells, no nags.
2. Disabled module = zero cost. Never claim "zero-query" in absolute terms.
3. No competitor code copied, ideas only (clean-room).
4. Git: issue → `GH-<n>` branch → gates → `GH-<n>:` PR (+ description, `Closes #<n>`) → merge; main protected (pending branch-protection setup).
5. Commits authored `maulikbhalodiya`; token rotates every 90 days.
6. Zero-dash writing: no standalone em dashes, en dashes, or hyphen pauses in any project text (docs, commits, PR bodies, UI strings, comments). Clauses are separated by commas or full stops. Hyphens appear only inside compound words and identifiers.
7. Push the feature branch for backup safety whenever work is committed, but merge only after the user verifies manually. No PR is opened and nothing lands on main without the user saying verified or all correct.
8. Same functionality stays on the same issue and branch (fixes and refinements ride the open branch). A new issue and branch start only for a different functionality. Small changes are batched and committed, never pushed, until the user says all correct.
9. Competitor parity first: before building any feature, inventory how Rank Math and Yoast implement it, provide the same functionality as the minimum, then add better or extra only on top. Never ship less than their baseline.
