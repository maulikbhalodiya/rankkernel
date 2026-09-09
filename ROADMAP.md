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
| Version | 0.1.0-dev · main @ 111 tests / 330 assertions · gates green |
| Done | Phase 0, Phase 1, roadmap #5, sitemaps #7 |
| Next | Phase 2 → Schema (issue #9) |
| Merged | Issues #1 #2 → PRs #3 #4 |
| Token | `~/.config/rankkernel/.gh-token` (90d) · pushes via SSH alias `github-maulik-repo` |

---

## Phase 0, Research & Planning ✅

| # | Item |
|---|---|
| 0.1 | Yoast free+Premium reverse-engineering audit (14 sections, fact-checked) |
| 0.2 | Rank Math free audit + ~30-claim verification |
| 0.3 | Feature matrix + gap analysis (their weaknesses → our counter-designs) |
| 0.4 | Name collision checks → **RankKernel**; all identifiers locked |
| 0.5 | Architecture blueprint (modules, data layer, head pipeline, sitemaps, redirects, schema, importer, REST, AI, build order) |
| 0.6 | WP.org 2026 compliance doc + clean-room policy doc |

## Phase 1, Foundation ✅

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

## Phase 2, Technical SEO Engine 🔨 (current)

### 2.1 XML Sitemaps ✅ (issue #7, PR #8)
**Get:** `sitemap_index.xml` + per-type sitemaps, images inside, XSL stylesheet, cache ON, WP core sitemap takeover.
**Do:**
- Router: rewrite `sitemap_index.xml`, `([^.]+)-sitemap([0-9]+)?\.xml`, `sitemap.xsl`; query vars `sitemap/sitemap_n/xsl`; `pre_get_posts` intercept → build → `exit`; disable `redirect_canonical` for sitemap requests; strip theme actions on render
- Providers: per-post-type, per-taxonomy, authors, direct `$wpdb` listing, 1000 entries/page, `lastmod` from `post_modified_gmt`
- XSL: bundled, served via `readfile` + long cache headers
- Cache: object-cache group `rankkernel-sitemaps` + transients; **default ON** (filter `rankkernel/sitemap/enable_cache`); validators (global + per-type) stored in options; invalidation queued on `save_post/edited_terms/user_register`, flushed on `shutdown`
- Takeover: `add_filter('wp_sitemaps_enabled', '__return_false')` + admin notice when ON (blueprint §O1)
- Ping hook point: `do_action('rankkernel/sitemap/ping')` on publish (cache-warm only, never claim engine ping)
- Tests: routing, provider slicing, cache hit = no rebuild, validator invalidation

### 2.2 Schema / JSON-LD ⬜, next (issue will be #9)
**Get:** one `<script type="application/ld+json">` per page, assembled lazily.
**Do:**
- Generator + piece registry; each piece: `is_needed($ctx)` + `build($ctx)`; single `@graph`, emitted in the `rankkernel/head/after_tags` slot
- Pieces: WebPage, Article, Organization/Person (site rep), BreadcrumbList, FAQ, HowTo, Product, Recipe, Event, LocalBusiness
- `@id` interlinking (`isPartOf`/`breadcrumb`), manual overrides from payload `schema` array
- No separate meta rows (RM antipattern), lazy assembly at render
- Tests: lazy is_needed, one tag only, JSON escaping

### 2.3 Breadcrumbs ⬜
**Get:** breadcrumb trail + shortcode + block; zero cost when unused.
**Do:**
- Generator: home → CPT archive → taxonomy parents → term → paginated; context-aware (single/archive/search/404)
- Output: filter `rankkernel/breadcrumbs`, shortcode `[rankkernel_breadcrumbs]`, server-rendered block (no JS)
- Settings: separator, home label, hide-on-front-page
- Lazy: only builds when requested or when Schema's BreadcrumbList piece needs it
- Tests: zero queries when not rendered, hierarchy correctness

### 2.4 Redirects ⬜ (default OFF)
**Get:** full redirect manager free (Yoast Premium territory), cache-first cost.
**Do:**
- Migrations create tables ON module enable: `wp_rankkernel_redirects` (source_url_hash UNIQUE, code enum 301/302/307/410/451, regex flag, hits) + `wp_rankkernel_redirects_cache`
- Redirector: `template_redirect` priority 1; guards `is_admin()/wp_doing_ajax()`; cache hit = 0 queries; miss = 1 targeted query + cache warm
- Exact + regex match; hits counter; CSV import + export (free, Yoast Premium-only feature)
- Slug-change watcher: `post_updated` → old vs new slug → auto-301 (setting to disable)
- Admin: list/add/edit/delete, search, hit counts (reuses 4.1 layout if landed, else plain)
- Tests: OFF = zero hooks; hit = 0 queries; miss = 1; regex; CSV round-trip

### 2.5 404 Monitor ⬜ (default OFF)
**Get:** 404 log with sane pruning + 1-click redirect creation.
**Do:**
- Table `wp_rankkernel_404_log` (uri_hash indexed, uri, referer, user_agent, created)
- Capture on 404s; prune by retention days AND count (oldest-first DELETE), never blanket truncate (RM bug fixed)
- Admin list + "create redirect" button per row
- Tests: prune policy, history retained

## Phase 3, Content & Import ⬜

### 3.1 Importer ⬜ (default ON)
**Get:** 1-click migration from Yoast / Rank Math / SEOPress, safe by design.
**Do:**
- Detector: active-plugin check + one COUNT per meta prefix (`_yoast_wpseo_%`, `rank_math_%`, `_seopress_%`)
- Migration map (blueprint §I.2): titles, descriptions, canonical, robots bits, og/twitter, focus keywords, `rank_math_schema_*` rebuild
- Batch processor N=50 with resume; dry-run preview first (counts + samples, zero writes); rollback snapshot in transient; originals kept until confirm
- Options import: curated subset only (templates, social profiles, webmaster codes), never blind-copy option blobs
- Unmapped keys logged, never silently dropped
- Tests: map correctness, batch resume, dry-run writes nothing

### 3.2 Gutenberg Suite ⬜ (default OFF)
**Get:** modern editor sidebar: unlimited focus keywords, readability, live social previews.
**Do:**
- Tooling: `@wordpress/scripts` in `src-js/`, bundles to `assets/build/` (gitignored); no CDN, local assets only
- Sidebar: title/description editors with token hints, unlimited focus keywords, client-side readability checks (no server round-trip), live OG/Twitter previews, per-post schema entry point
- Data flows through the registered REST meta field (`_rankkernel_meta_data` show_in_rest), no custom bulk endpoint
- Enqueue ONLY on block editor screens; zero frontend assets
- Tests: enqueue gating, meta REST round-trip

## Phase 4, Admin UI & Design ⬜

### 4.1 RankMath/Yoast-class admin redesign ⬜
**Get:** branded dashboard + sidebar layout, the design, none of the spam.
**Do:**
- Design tokens as CSS vars (colors/spacing/type); single `admin.css`, enqueued ONLY on RankKernel screens
- Sidebar menu with sub-pages: Dashboard, General, Titles & Meta, Sitemaps, Schema, Breadcrumbs, Tools (redirects/404), Import, AI
- Dashboard page: stat cards (enabled modules, meta rows, redirect count, 404 count, sitemap health) + quick links
- Branded header, tabbed sub-pages, consistent form components, clean alert styling
- Migrate Phase-1 settings page content into the new pages (REST + option keys unchanged)
- **Zero nags/upsells/notification-center, ever** (design polish only; also keeps PCP happy)
- Tests: menu structure, enqueue gating, options keys stable after migration

### 4.2 Benchmark dev panel ⬜ (WP_DEBUG only)
**Get:** the proof behind our performance claims.
**Do:**
- Hidden submenu when WP_DEBUG: current-request query count, peak memory, TTFB
- WP-CLI seed script (1000 posts / 200 terms) + runner: median over 20 hits vs Yoast/RM installs
- Results → `docs/benchmarks/` table + SVG chart, regenerated per release

## Phase 5, Extras ⬜

### 5.1 Instant Indexing (IndexNow) ⬜ (default OFF)
**Do:** key generation + verification file route; submit on publish/update/delete to api.indexnow.org (Bing/Seznam/Yandex); batch + retry; failure log; no pings when module off. Tests: payload, key verification, off = zero requests.

### 5.2 Robots.txt & .htaccess editors ⬜ (default OFF)
**Do:** virtual robots.txt via rewrite + filter; .htaccess editor with **backup-first** (`rankkernel_robots_backup`, autoload no) + validation + restore-on-failure. Tests: backup→write→restore path.

### 5.3 Image SEO ⬜ (default OFF)
**Do:** bulk alt/title autofill (batch 50) with dynamic tags; attachment filters for frontend attributes; no frontend assets. Tests: batch, tag resolution.

## Phase 6, AI & Headless ⬜

### 6.1 AI Suite ⬜ (default OFF)
**Do:** `ProviderInterface` (title/description/alt/FAQ-schema) + OpenAI/Anthropic/Gemini providers; **BYO-key**, per-user `_rankkernel_user_prefs` + site default `rankkernel_ai_keys` (autoload no); keys never logged; client throttle + server per-key cap; promise: data leaves only to the chosen provider. Tests: provider abstraction, rate guard, key redaction.

### 6.2 Headless ⬜ (default OFF)
**Do:** `GET /rankkernel/v1/seo/{id}` (full payload + computed tags), preview endpoint; register WPGraphQL `seo` fields only when WPGraphQL is active. Tests: payload shape, permission callbacks.

## Phase 7, QA & WordPress.org Launch ⬜

| # | Do |
|---|---|
| 7.1 | Plugin Check (PCP) full pass, fix every error (the WP.org approval gate) |
| 7.2 | readme.txt final: description, FAQ, screenshots, changelog; matches header exactly |
| 7.3 | Benchmark protocol vs Yoast/RM (measured medians, never estimates) → publish |
| 7.4 | Original banner 772×250 + icon 128×128 (no trademarks) |
| 7.5 | SVN submission via wp.org account `maulikbhalodiya` + review replies (5 to 14 business days) |
| 7.6 | Tag v1.0.0 · README badges · announcement |

## Deferred ⏸️

| Item | Why |
|---|---|
| News/Video sitemaps | post-v1 candidate |
| Analytics (GSC/GA4) | Site Kit bridge instead |
| Onboarding wizard | decide during 4.1 |
| Multisite network purge | v1 = single site (blueprint §O3) |
| Rank tracking | out of scope v1 |

## Standing rules (never change)

1. Free forever, every feature ships free; no Pro tier, no upsells, no nags.
2. Disabled module = zero cost. Never claim "zero-query" in absolute terms.
3. No competitor code copied, ideas only (clean-room).
4. Git: issue → `GH-<n>` branch → gates → `GH-<n>:` PR (+ description, `Closes #<n>`) → merge; main protected (pending branch-protection setup).
5. Commits authored `maulikbhalodiya`; token rotates every 90 days.
6. Zero-dash writing: no standalone em dashes, en dashes, or hyphen pauses in any project text (docs, commits, PR bodies, UI strings, comments). Clauses are separated by commas or full stops. Hyphens appear only inside compound words and identifiers.
7. Push only after the user says verified. Until then, changes are committed locally on the feature branch without merging.
8. Same functionality stays on the same issue and branch (fixes and refinements ride the open branch). A new issue and branch start only for a different functionality. Small changes are batched and committed, never pushed, until the user says all correct.
