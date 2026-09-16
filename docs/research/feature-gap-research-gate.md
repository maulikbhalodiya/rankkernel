# RankKernel Feature Gap Research Gate

Status: RESEARCH ONLY. No feature implementation. Awaiting owner approval.
Date: 2026-09-15. Repo: `rankkernel`, main at the GH-13 merge, breadcrumbs on GH-15 / PR #16 (untouched).
Scope: complete clean-room parity audit of Yoast Free/Premium and Rank Math Free/PRO against the current RankKernel build, plus a corrected roadmap.
Evidence tags: `[DOCS]` vendor official docs, `[SOURCE]` public source inspected, `[OBSERVED]` observed behavior, `[INFERRED]` reasoned, `[RKK]` current RankKernel behavior.
Research inputs: five parallel research streams (robots and htaccess; llms.txt and AI; Yoast inventory; Rank Math inventory; metadata and head parity) plus the existing Phase 1 audits in `docs/competitor-analysis/`.

---

## 1. Executive summary

The roadmap was built from a Phase 1 audit that was strong, then never fully converted into phases. Three planning failures followed:

1. **Real product gaps.** llms.txt and AI crawler controls were absent everywhere. Full metadata and head parity (per-context templates, robots directive controls, social override layers, canonical rules) was never scheduled as a phase. Internal linking, HTML sitemap, local SEO, WooCommerce, head cleanup and hreflang were promised in the matrix but had no phase.
2. **Wrong priority.** robots.txt and `.htaccess` were real parity items but sat in Phase 5 "Extras", after admin redesign and benchmarks.
3. **No PRO mapping.** Because RankKernel is free forever, paid competitor features are not a "future PRO phase". They are ordinary roadmap items ranked by value.

Two competitor shifts matter for planning. Yoast now ships llms.txt **free** and gates **Bot Blocker** (GPTBot, CCBot, Google-Extended via robots.txt) behind Premium. Rank Math ships an llms.txt module and lists an "Advanced llms.txt Generator" on its comparison table, and its AI features (Content AI, AI Visibility, AI Link Genius) all require a **paid external account**. RankKernel can deliver the local, deterministic parts of both for free, and must not adopt the paid external services.

The corrected roadmap introduces a Phase 2.6 follow-up block (page head and crawl controls), a Technical SEO and Crawl Signals phase, a Content and Internal Linking phase, a Commerce and Local phase, and keeps AI strictly local and BYO-key.

---

## 2. Current RankKernel capability audit `[RKK]`

**Implemented and merged:** guarded bootstrap and singleton, module gate (`ModuleManager`, `ModuleEnableMap`), `SettingsStore`, REST `rankkernel/v1` (settings plus module toggle), migration runner with ledger, Metadata engine (single `_rankkernel_meta_data` row, `HeadRenderer`, `TagsReplacer`, `MetaPayload`, webmaster verification, Open Graph and Twitter tags, title and description templates), Sitemaps (Router, providers, cache, XSL, robots `Sitemap:` directive, core takeover), Schema (Generator, `GraphNormalizer`, 30 pieces, FAQ and HowTo blocks, metabox), Breadcrumbs (GH-15, PR #16 open), Redirects (GH-12, tables on enable, matcher, validator, CSV, slug watcher), 404 Monitor (GH-12, dedupe logger, flood guard, pruner). WPCS compliant across all source and tests.

**Registry reserved but unbuilt:** `importer`, `instant-indexing`, `robots` (labelled "Robots.txt & .htaccess"), `image-seo`, `gutenberg`, `ai`, `headless`.

**Not present at all (no registry id):** llms.txt and AI crawler controls, a full per-context metadata layer (per post type, taxonomy, author, date, search, 404 templates with a variable editor and pixel guidance), robots directive controls (noarchive, nosnippet, max-snippet, max-image-preview, max-video-preview), social override layers and fallback ordering, internal linking and orphan reporting, content analysis and SEO scoring, HTML sitemap, local SEO, WooCommerce, header and footer code injection, hreflang, head cleanup, site analyzer.

---

## 3. Yoast Free feature inventory `[DOCS]`

Free covers: per-object title and description with snippet preview; global title and description templates per homepage, post type, taxonomy, archive, author, date, search, 404; the `%%var%%` token system (50+ variables); per-object overrides for title, description, canonical, robots, breadcrumb title, schema type; robots meta always emitted with a permissive default plus the advanced directives noimageindex, noarchive, nosnippet; canonical output with per-object override; pagination handling; breadcrumbs plus `BreadcrumbList`; Open Graph and Twitter card output with a default social image and featured-image fallback; a single JSON-LD graph (Organization, WebSite with SearchAction, WebPage, BreadcrumbList, ImageObject, primary entity); XML sitemaps (index, post types, taxonomies, authors, images, 1000 per child, exclusions, noindex exclusion, no manual ping); bulk editor for titles and descriptions (manual, free); single focus keyphrase with the full SEO and readability analysis, inclusive-language check, SEO and readability scores, snippet and SERP preview; image alt assessment, attachment redirect, image sitemap entries; crawl optimisation toggles (strip shortlinks, RSD, WLW, oEmbed, generator, pingback, powered-by, feeds, emojis, REST disallow, internal search cleanup, advanced URL cleanup); robots.txt and `.htaccess` file editor; settings export and import plus import from other SEO plugins; verification tags for Google, Bing, Baidu, Yandex, Pinterest; REST head endpoint for headless; WP-CLI indexables.

No native rank tracking (Wincher integration), no native keyword research (Semrush integration), no 404 monitor, no redirect manager in Free.

---

## 4. Yoast Premium feature inventory `[DOCS]`

Premium adds: separate social title and description templates with live Facebook and X previews; up to 5 keyphrases with synonyms and word forms; AI titles, descriptions, Optimize, Summarize, Content Planner, bulk AI drafts (external service, approval gated); internal link suggestions (prominent-word overlap, real-time in editor) plus orphaned content finder, cornerstone workouts, and stale-cornerstone detection; redirect manager (301, 302, 307, 410, 451, plain and regex, CSV plus `.htaccess` import and export, auto-prompt on slug change, move, delete, 404 driven flow); IndexNow auto-ping; **Bot Blocker for AI crawlers (GPTBot, CCBot, Google-Extended) implemented as robots.txt edits**; llms.txt is documented as Free for everyone, not Premium; schema aggregation endpoint for NLWeb; "significantly more schema types"; bundled Local SEO, News SEO, and Video SEO at no extra cost; WooCommerce SEO as a **separate paid bundle**; a Google Docs seat, Academy, and 24/7 support.

Packaging note: since July 2025 Local, News, and Video are bundled into Premium, so a parity matrix must not treat them as standalone add-ons.

---

## 5. Rank Math Free feature inventory `[DOCS]`

Free covers: per-object title and description with a snippet editor; global templates per post type, taxonomy, archive, homepage, author, date, search, 404 with live preview; the `%var%` token system with parameterised tokens (`%categories(limit=3 & separator=|)%`, `%customfield(name)%`, `%date(F jS, Y)%`, `%count()%`); robots defaults globally, per type and per object (index, nofollow, noarchive, nosnippet, noimageindex) plus advanced robots (snippet length, image preview size, video preview length) and a noindex-empty-archives toggle; canonical automation plus per-object override and adjacent next and prev links; social defaults with per-object overrides, social preview, default share image, Twitter card type, organization profiles; a schema set of roughly 16 to 18 types (Article, Product, Recipe, FAQ, HowTo, Event, JobPosting, LocalBusiness, Person, Service, SoftwareApplication, Video, Book, Course, Music, Restaurant, Review) with 193 local subtypes, FAQ block, validation guidance, and a **single connected graph**; XML sitemaps (index, per type, taxonomies, authors, images, exclusion lists, limits, ping) plus an **HTML sitemap**; local SEO and knowledge graph for a single location (name, address, phone, hours, geo, map, 193 business types); image SEO auto alt and title via patterns with a live preview; image handling; webmaster verification for Google, Bing, Baidu, Yandex, Pinterest; Instant Indexing module (IndexNow); analytics-lite Search Console views; bulk edit table; primary category and primary taxonomy; one-click import from Yoast, AIOSEO, SEOPress, and the Redirection plugin; settings backup and restore; robots.txt and `.htaccess` editors behind Advanced Mode; role manager; breadcrumbs with block; noindex and crawl controls; attachment redirects; URL base stripping; external and image link nofollow controls; RSS prepend and append.

---

## 6. Rank Math PRO feature inventory `[DOCS]`

PRO plus Content AI adds: custom schema builder from a blank canvas, unlimited schemas per object, 840+ type vocabulary, conditional display rules, schema import from a URL, extra presets (Dataset, FactCheck, Podcast, Carousel, Mentions and About, speakable, Q&A), and variables inside schema fields; **advanced llms.txt generator** (listed on the comparison table); News sitemap, Video sitemap with auto-detect and metadata fill, and a podcast RSS module; WooCommerce SEO PRO (identifiers, hidden product noindex, dedicated product content checks, stock status sitemap filter, merchant enrichment, EDD); multi-location local SEO with a location post type and advanced blocks and shortcodes; scheduled activation and expiration for redirects, advanced 404 mode with referer and user agent, `.htaccess` sync, and 404 log export; CSV SEO data import and export; the rank tracker and the full analytics suite (GA4, AdSense, Trends, index status, scheduled email reports) all requiring Google accounts; Content AI (40+ tools, external subscription); AI Visibility (brand tracking, Content AI subscription); AI Link Genius (link suggestions, keyword to URL auto-linking, orphan detection, broken link and redirect chain audit, rollback); MCP agent tools; watermark for social images; affiliate link prefixing with sponsored marking; password-protected noindex toggle; per-post Advanced tab.

**External dependency rule:** every measured AI or analytics capability at Rank Math requires an account and a subscription. For a free-forever product, only the local deterministic equivalents are in scope.

---

## 7. Complete feature parity matrix

Status legend: COMPLETE, PARTIAL, MISSING, EXTERNAL DEPENDENCY, NOT APPROPRIATE, DEFERRED.

| Category | Feature | Yoast Free | Yoast Premium | RM Free | RM PRO | RKK Current | RKK Planned | Priority |
|---|---|---|---|---|---|---|---|---|
| Metadata | Per-object title and description | yes | yes | yes | yes | COMPLETE | keep | P0 done |
| Metadata | Per-context templates (home, type, tax, author, date, search, 404) | yes | yes | yes | yes | PARTIAL (generic templates) | full layer | P0 |
| Metadata | Token system incl. parameterised tokens | yes 50+ | yes | yes 53 | yes | PARTIAL | full grammar | P0 |
| Metadata | Meta description omit-by-default plus opt-in autogenerate | yes | yes | yes | yes | PARTIAL | align | P0 |
| Metadata | Robots directives (noarchive, nosnippet, max-snippet, max-image-preview, max-video-preview) | yes | yes | yes | yes | MISSING | add | P0 |
| Metadata | Canonical rules (noindex suppress, pagination, override, next/prev) | yes | yes | yes | yes | PARTIAL | add rules | P0 |
| Metadata | Snippet preview with pixel guidance | yes | yes | yes | yes | MISSING | add | P1 |
| Social | OG and X output with fallback image | yes | yes | yes | yes | PARTIAL | complete fallback chain | P0 |
| Social | Separate social templates plus previews | no | yes | partial | yes | MISSING | add | P1 |
| Social | Image watermark | no | no | no | yes | NOT APPROPRIATE (marketing) | skip | n/a |
| Schema | Core graph and pieces | yes | yes | yes | yes | COMPLETE | keep | done |
| Schema | Custom schema builder, conditions, variables | no | no | teased | yes | PARTIAL (custom JSON) | builder | P1 |
| Schema | Speakable, Mentions and About, FactCheck, Dataset, Podcast | no | partial | no | yes | MISSING | add | P2 |
| Sitemaps | XML sitemaps, images, cache, core takeover | yes | yes | yes | yes | COMPLETE | keep | done |
| Sitemaps | HTML sitemap | no | no | yes | yes | MISSING | add | P1 |
| Sitemaps | News and Video sitemaps | paid bundle | paid bundle | no | yes | MISSING | add | P2 |
| Sitemaps | Product sitemap | Woo bundle | Woo bundle | yes | yes | MISSING | add | P1 (Woo) |
| robots.txt | Editor (virtual or physical) | yes (physical on create) | same | yes (virtual) | yes plus tester | PARTIAL (sitemap directive only) | virtual editor | P0 |
| robots.txt | AI crawler presets | no | yes (Premium Bot Blocker) | no | no | MISSING | free presets | P0 |
| .htaccess | Raw editor | yes | same | yes | yes plus sync | MISSING | decide (see §8) | P2, gated |
| llms.txt | Generator, auto or manual | yes FREE | yes | yes module | advanced generator | MISSING | virtual generator | P0 |
| Redirects | Manager, regex, CSV, slug watcher | no | yes | yes | yes | COMPLETE | keep | done |
| Redirects | Scheduled activation and expiration | no | no | no | yes | MISSING | add | P2 |
| Redirects | `.htaccess` sync | no | yes | no | yes | NOT APPROPRIATE | skip | n/a |
| 404 | Log, dedupe, prune, redirect from row | no | no | yes | advanced mode | COMPLETE | keep | done |
| 404 | Advanced fields and export | no | no | advanced free | yes | PARTIAL (advanced opt-in) | export | P2 |
| Internal linking | Suggestions, orphan detection, link counter | counter only | suggestions plus orphan workouts | suggestions plus counter | AI Link Genius | MISSING | orphan report then suggestions | P1 then P2 |
| Content analysis | Focus keyword, placement, density, readability, score | yes 1 keyphrase | 5 keyphrases plus AI | yes 5 | unlimited plus Content AI | MISSING | local analyzer | P1 |
| Content analysis | Multi keyphrase | no | yes (5) | yes (5) | unlimited | MISSING | unlimited free | P1 |
| Content analysis | AI writing, optimize, planner | no | yes (external) | no | yes (external) | EXTERNAL DEPENDENCY | BYO-key only | P3 |
| Keyword and search | Rank tracking, analytics dashboards | Wincher account | Wincher | GSC free | PRO tracker plus GA4 | EXTERNAL DEPENDENCY | read-only GSC later | P3 |
| Image SEO | Auto alt and title, patterns, bulk | alt check only | alt check | yes | plus AI bulk | PARTIAL (module reserved) | pattern autofill | P1 |
| Image SEO | Attachment redirect, image sitemap | yes | yes | yes | yes | PARTIAL | complete | P1 |
| WooCommerce | Product schema, identifiers, OG price, analysis | paid bundle | paid bundle | free module | plus PRO | MISSING | free module parity | P1 |
| Local SEO | LocalBusiness schema, single location | paid bundle | paid bundle | free module | multi location | MISSING | single location | P2 |
| Local SEO | Multi location and store locator | paid | paid | no | yes | MISSING | later | P3 |
| News and Video | News sitemap plus schema | paid bundle | paid bundle | free sitemap | plus strict | MISSING | add | P2 |
| News and Video | VideoObject plus video sitemap | paid bundle | paid bundle | yes free | plus auto-detect | MISSING | add | P2 |
| Import and export | Import from Yoast and Rank Math | yes | yes | yes | yes | MISSING (Phase 3.1 planned) | build | P1 |
| Import and export | Settings export and import | yes | yes | yes | yes | MISSING | add | P2 |
| Import and export | Redirect CSV, 404 export | no | yes | import PRO | yes | PARTIAL (redirect CSV done) | 404 export | P2 |
| Webmaster | Verification tags | yes | yes | yes | yes | COMPLETE | keep | done |
| Webmaster | IndexNow instant indexing | no | yes | yes free | yes | MISSING (Phase 5.1 planned) | build | P1 |
| Technical | Head cleanup (generator, shortlink, RSD, oEmbed, emojis, feeds, REST) | yes free | yes | partial free | yes | MISSING | add | P1 |
| Technical | Header and footer code injection | no | no | no | yes | MISSING | add gated | P2 |
| Technical | hreflang | no | no | passthrough | passthrough | MISSING | passthrough plus filters | P2 |
| Technical | Site-wide analyzer | partial | partial | yes free | yes | MISSING | local analyzer | P2 |
| Admin | Dashboard, module toggles, settings IA | yes | yes | yes with wizard | yes | PARTIAL | redesign (Phase 4.1) | P1 |
| AI | llms.txt and AI crawler controls | llms free, blocker Premium | yes | llms free | advanced | MISSING | free local | P0 |
| AI | Generative writing, visibility tracking, agent write tools | Premium or AI+ | yes | Content AI | yes | NOT APPROPRIATE (external paid) | read-only report only | P3 |
| Platform | Single meta row, lean options, zero cost when off, clean uninstall | no | no | no | no | COMPLETE advantage | keep | done |

---

## 8. `.htaccess` research

**Yoast** `[DOCS]`: a raw file editor in Tools, present in Free, no Premium difference documented. When created through the editor it writes a physical file; if the file is unwritable the docs direct the user to server level editing. No backup, no validation, no rollback documented.

**Rank Math** `[DOCS]`: a raw textarea in General Settings behind Advanced Mode with an acknowledgement checkbox and a warning. It automatically keeps one backup as a sibling file in the web root and restore is manual by renaming via FTP. It requires the file to already exist, points users to an external syntax tester, and provides Nginx snippets for Nginx users.

**WordPress core** `[SOURCE]`: `insert_with_markers()` handles the managed `# BEGIN WordPress` block with file locking and marker preservation; `WP_Filesystem()` negotiates direct, SSH or FTP and prompts for credentials; `DISALLOW_FILE_EDIT` is the hardening constant both competitors respect.

**Risks:** availability level failure (a syntax error can white screen the site), single generation backup in a guessable public location, no PHP side Apache syntax validation, Apache only, multisite affects the whole network host, and lockout is possible.

**Recommendation `[INFERRED]`:** **do not ship a raw `.htaccess` editor in v1.** Keep redirect management at the PHP layer, which is server agnostic and reversible. If the owner wants it later, ship it as an opt-in, Apache only, network aware module with: `manage_options` plus super admin on multisite, `DISALLOW_FILE_EDIT` respected by hiding the screen, nonce plus typed confirmation plus a per session unlock, server detection that disables on Nginx and IIS with copyable snippets instead, timestamped versioned backups stored outside the web root with one click restore, refusal to touch the managed WordPress marker block, a mandatory unified diff preview and second confirmation, and never deleting or modifying the file on uninstall.

---

## 9. robots.txt research

**Yoast** `[DOCS]`: editor in Free; writes a physical file when created through the editor, otherwise filters the virtual output and appends the sitemap reference in a marked block. No validation documented. No per site targeting.

**Rank Math** `[DOCS]`: virtual only; it filters the core output and explicitly locks the editor when a physical file exists, telling the user to delete it. Free has a plain textarea plus an external tester link; PRO adds an inline tester with revert. It documents syntax (user agent, allow, disallow, sitemap, comments, blank lines, wildcards, `$`).

**WordPress core** `[SOURCE]`: `do_robots()` serves virtual `text/plain` and exposes the `robots_txt` filter with the `$public` flag; a physical file takes precedence because the web server serves it before PHP runs; core defaults disallow the admin path and allow admin-ajax.

**Search engine reality** `[DOCS]`: Google supports only user-agent, allow, disallow and sitemap; `crawl-delay` is explicitly unsupported; `*` and `$` are honoured; 500 KiB limit; caching up to about a day.

**Recommendation `[INFERRED]`:** **virtual filter only, never write a physical file.** Start from the `$output` passed in, never hard-code core defaults, honour `$public`, and append one absolute sitemap line via `esc_url(home_url())` only when sitemaps are on and the site is public. Storage is one option row (`rankkernel_robots_custom`, `rankkernel_robots_mode`, `rankkernel_robots_sitemap`), no table. Validation: normalise line endings, force UTF-8, strip BOM and control characters, allow-list the directive lines, require absolute `http(s)` sitemap URLs with a cross-host warning, preview, and a "bypassed by a physical file" notice that does not delete the file. Multisite: per site option with an explicit network policy and no default aggregation of sub site sitemaps (the privacy and performance lesson from core and Yoast history).

---

## 10. llms.txt and AI SEO research

**Standard status** `[DOCS]`: llms.txt is a **proposal, not a standard**. Maintainer is Jeremy Howard and AnswerDotAI, current version v2, published September 2024 and revised August 2026. Format: optional BOM, H1 site name, blockquote summary, optional sections, and H2 link lists of `[name](url)` plus optional notes, with a conventional `## Optional` section. There is no RFC, no W3C adoption, and it is deliberately not under `/.well-known/`. The `/llms-full.txt` split and per page `.md` mirrors are conventions, not spec text.

**Google** `[DOCS]`: Google Search **ignores llms.txt and similar AI text files** for ranking and for its generative AI features, and says creating them neither helps nor hurts. Its guidance reframes GEO and AEO as SEO. This must be stated honestly in the UI.

**Yoast** `[DOCS]` `[SOURCE]`: ships llms.txt **free for everyone**, auto or manual selection, preview, physical file at the site root, refreshed weekly by a scheduled action, never overwriting a pre-existing manual file, multisite excluded. Bot Blocker (GPTBot, CCBot, Google-Extended) is **Premium only and implemented as robots.txt edits**. No per page `.md` companion and no AI specific schema.

**Rank Math** `[DOCS]`: ships an llms.txt module with post type and taxonomy checkboxes, a default limit of 100, additional content, and a preview; the comparison table lists an "Advanced llms.txt Generator". No AI specific robots presets were found. Content AI, AI Visibility and AI Link Genius all require a **paid external account**.

**Recommendation `[INFERRED]`:** generate llms.txt as a **virtual response** by default (rewrite plus `Content-Type: text/markdown`, `X-Robots-Tag: noindex`) with an optional physical file write behind a toggle that refuses to overwrite an existing file, since virtual avoids the permission failures both competitors hit and works on multisite and managed hosts. Content: H1 site title, blockquote summary, H2 sections per post type and taxonomy, each item `[title](url): excerpt` with the excerpt trimmed and stripped, selection defaults of latest updated, published within 12 months for posts, capped counts, excluding noindex, private, password and attachment pages. Add a **free AI crawler group editor** for robots.txt with verified tokens at build time, rendered above the `*` group, plus a consistency warning when llms.txt advertises a URL that robots.txt disallows. Ship it under a Technical SEO or Crawl Signals module, all local, no account, no external calls.

---

## 11. Metadata and Head engine research

**Title templates** `[DOCS]`: Yoast uses `%%var%%` with 50+ variables and Rank Math uses `%var%` with about 53, including parameterised and format-argument tokens (`%categories(limit=3 & separator=|)%`, `%date(F jS, Y)%`, `%customfield(name)%`, `%count()%`, `%org_name%`). Both resolve per object, then per type or taxonomy template, then a context default. RankKernel should implement one grammar (recommend `%name%` plus optional arguments) with a documented mapping table for importers.

**Description** `[DOCS]`: Yoast omits the meta description by default rather than auto-generating; Rank Math layers the same way with an optional auto-generate. Adopt omit-by-default with an opt-in per type autogenerate from excerpt or content, sentence-boundary trimmed.

**Robots** `[DOCS]`: both expose index and noindex, follow and nofollow, noarchive, nosnippet, noimageindex, and both emit `max-snippet:-1`, `max-image-preview:large`, `max-video-preview:-1` by default with configurable limits in Rank Math. Assembly is a de-duplicated list with restrictive-wins. RankKernel should merge with core `wp_robots` and never fight it, and should drive robots, sitemap inclusion and canonical from one `is_indexable()` gate.

**Canonical** `[DOCS]`: Yoast has the most rigorous published behaviour; the two rules RankKernel most lacks are **omit canonical on noindex and on error responses**, and pagination and trailing-slash rules, plus adjacent next and prev links. Adopt that behaviour as the spec.

**Social** `[DOCS]`: emit a minimal Yoast equivalent set (og:locale, og:type, og:title, og:url from canonical, og:site_name, conditional og:description, article timestamps and author and publisher, og:image with width and height) plus Twitter card tags that are emitted only when the value differs from Open Graph, and a single image pipeline with a documented fallback order.

**Head assembly and duplicate prevention** `[DOCS]`: both use one head action with ordered presenters and both resolve conflicts by removing the other emitter rather than emitting twice. RankKernel already has a single `HeadRenderer`; extend it with a presenter order, a rendered flag per tag, removal of core `rel_canonical` and shortlink duplicates, and an optional opt-in aggressive dedup that buffers `wp_head` and strips foreign title, description, robots, canonical, Open Graph and Twitter tags.

**Content analysis** `[DOCS]`: both run deterministic local analysis; only their AI layers need external services. A local analyzer (title and description length plus pixels, keyphrase placement and density, headings, alt coverage, link counts, paragraph and sentence statistics, reading time) is in scope and free.

---

## 12. Other missing RankKernel features

- **Internal linking:** Yoast Premium (suggestions, orphan, cornerstone) and Rank Math (suggestions free, AI Link Genius PRO). Clean-room needs a link index populated on save plus a background reindex, an orphan query (`incoming internal equals 0`, indexable, published), and suggestions from token overlap or a keyword map. Storage is one table plus a postmeta cache. Recommend orphan report first, suggestions second, and never auto-insert links without explicit approval.
- **Image SEO:** Rank Math free auto alt and title via patterns with preview; Yoast offers alt assessment only. Recommend pattern based autofill with variables, a live preview, and bulk processing, frontend attribute filters only.
- **WooCommerce:** Rank Math free already provides product schema, identifiers, OG price, hidden product noindex, base stripping and product inclusion in the sitemap; Yoast requires a paid bundle. RankKernel should match the Rank Math free scope.
- **Local SEO:** Rank Math free covers a single location (name, address, phone, hours, geo, map, 192 business types, LocalBusiness schema); multi location is PRO. Recommend single location free, multi location later.
- **News and Video:** News sitemap and `NewsArticle` schema and video detection with `VideoObject` and a video sitemap. Both are paid at Yoast and largely free at Rank Math.
- **Import and export:** one click import from Yoast (`_yoast_wpseo_*`) and Rank Math (`rank_math_*`) plus term and user meta and a curated options snapshot, with dry run first and a backup nag, never auto-deleting competitor data. Settings export and import. Redirect CSV (already done) and 404 log export.
- **Head cleanup:** strip generator, shortlink, RSD, WLW, oEmbed, emojis, pingback and powered-by headers; feed controls; REST disallow; internal search cleanup; advanced URL cleanup with allow-list safeguards.
- **Header and footer code injection:** a Rank Math PRO feature; free to add, gated to `unfiltered_html` plus a capability check.
- **hreflang:** neither plugin manages it; both pass through to multilingual plugins. Provide filters and passthrough.
- **Site analyzer:** a local deterministic site scan (Rank Math free does this, Yoast partially) with no remote API.

---

## 13. Current roadmap gap report

### Already implemented
Bootstrap and module gate, settings, REST, migrations, Metadata engine v1, sitemaps, schema, breadcrumbs, redirects, 404 monitor, WPCS compliance.

### Already planned but unbuilt
Importer (3.1), Gutenberg Suite (3.2), admin redesign (4.1), benchmark panel (4.2), IndexNow (5.1), robots.txt and `.htaccess` (5.2), Image SEO (5.3), AI BYO key (6.1), Headless (6.2), QA and launch (7.x). Deferred: News and Video sitemaps, analytics, onboarding wizard, multisite purge, rank tracking.

### Missing from the roadmap (the planning failure)
1. llms.txt generator and AI crawler controls.
2. Full metadata and head parity: per-context templates, parameterised tokens, robots directives, canonical noindex suppression and pagination, next and prev links, description omit-by-default, snippet preview with pixel guidance, social template layer and fallback ordering.
3. Internal linking and orphan reporting.
4. Content analysis and SEO scoring.
5. HTML sitemap.
6. Local SEO single location.
7. WooCommerce free module parity.
8. News sitemap and VideoObject plus video sitemap.
9. Header and footer code injection.
10. hreflang passthrough.
11. Head cleanup (generator, shortlink, RSD, oEmbed, emojis, feeds, REST).
12. Site analyzer.
13. Settings export and import.
14. 404 advanced export.
15. Redirect scheduled activation and expiration.

### Needs architecture decision
- `.htaccess` raw editor: recommend omit in v1 (availability level failure, Apache only).
- Aggressive head dedup default: recommend opt-in.
- llms.txt physical file: recommend virtual default with an opt-in write.
- Link index storage: one table plus postmeta, batched, off the request path.
- Whether robots.txt and `.htaccess` should stay one module or split (`robots` is safe, `.htaccess` is not).

### External dependency
Rank tracking and analytics dashboards (Google and account), Content AI and AI Visibility and AI Link Genius (paid external), Semrush and Wincher keyword data, remote site analyzers, remote thumbnail fetching.

### Not appropriate for RankKernel
Social image watermarking (marketing), `.htaccess` redirect sync (server coupling and lockout), generative writing and paid visibility tracking (external paid subscriptions), remote site analyzer APIs, tracking scripts, telemetry, upsells.

---

## 14. Recommended RankKernel architecture

- **Crawl Signals module** (extends the reserved `robots` id, or new `crawl`): virtual robots.txt editor plus validation, free AI crawler presets, llms.txt virtual generator plus optional physical write, sitemap directive, consistency checks. Options only, no tables.
- **Head Engine** (extends `metadata`): presenter ordered single head action, token resolver with one grammar, per-context template layer (global, per type, per taxonomy, per object), robots merger with `is_indexable()`, canonical builder, social builders with fallback ordering, snippet preview REST endpoint, optional aggressive dedup.
- **Content Analysis** (new `analysis` id): local deterministic checks in PHP and JS parity, scores, multi keyphrase free.
- **Internal Linking** (new `links` id): link index table, orphan query, suggestion engine, never auto-insert.
- **Commerce and Local** (new ids): WooCommerce free parity, single location local SEO.
- **Media SEO** (the reserved `image-seo`): pattern autofill, bulk, frontend filters.
- **Migration** (Phase 3.1): Yoast and Rank Math importers.
- **IndexNow** (reserved `instant-indexing`).
- **AI** (reserved `ai`): BYO key only, read-only reporting only, external calls only with a user supplied key.
- **Headless** (reserved `headless`): a read-only REST payload.

All modules respect the existing hard gate, keep the single meta row, add tables only where a real workload needs them (redirects, 404, link index), stay lazy, and keep admin assets screen gated.

---

## 15. Recommended future phase structure

- **2.6 Phase 2 follow-up: Head and crawl controls (P0).** Full metadata and head parity plus robots.txt virtual editor and AI crawler presets plus llms.txt. Rationale: this is the largest genuine gap and the cheapest parity win, all local and free.
- **Phase 3, Content, Media and Migration.** Importer, Gutenberg Suite, Content Analysis, Image SEO pattern autofill, Internal Linking orphan report.
- **Phase 4, Admin UI and Design.** Redesign, dashboard, tabbed settings, benchmark panel, settings export and import.
- **Phase 5, Technical SEO Extras.** IndexNow, head cleanup, header and footer injection, hreflang passthrough, site analyzer, 404 export, redirect scheduling, optional gated `.htaccess` if approved.
- **Phase 6, Commerce, Local, News and Video.** WooCommerce free parity, single location local SEO, News sitemap, VideoObject and video sitemap.
- **Phase 7, AI (BYO key) and Headless.** Provider interface, read-only reporting, headless payload.
- **Phase 8, QA and WordPress.org launch.**

---

## 16. P0 to P3 priorities

**P0:** per-context metadata templates and parameterised tokens; robots directives and the `is_indexable()` gate; canonical rules (noindex suppression, pagination, next and prev); description omit-by-default plus opt-in autogenerate; social fallback ordering; virtual robots.txt editor with validation; free AI crawler presets; llms.txt virtual generator.

**P1:** snippet preview with pixel guidance; social template layer and previews; custom schema builder and conditions; HTML sitemap; content analysis and multi keyphrase; image SEO pattern autofill; WooCommerce free parity; IndexNow; head cleanup; importers; admin redesign; 404 export; internal linking orphan report.

**P2:** speakable and Mentions and About and FactCheck and Dataset schema; News sitemap; VideoObject and video sitemap; single location local SEO; header and footer injection; hreflang passthrough; site analyzer; settings export and import; redirect scheduling; optional gated `.htaccess`.

**P3:** multi location and store locator; AI link automation; rank tracking read-only via user credentials; headless payload; AI writing behind a BYO key.

---

## 17. Security requirements

- **robots.txt:** capability `manage_options`, nonce, allow-list of directive lines, absolute sitemap URLs only, UTF-8 normalisation with BOM and control character stripping, size cap well below 500 KiB, and a physical file notice that never deletes the file.
- **`.htaccess` (if ever built):** `DISALLOW_FILE_EDIT` respected, super admin on multisite, typed confirmation, versioned out-of-root backups with one click restore, refusal to touch the managed WordPress block, mandatory diff preview, and never modified on uninstall.
- **Metadata:** escape all output (`esc_attr`, `esc_url`, `esc_html`), sanitise token inputs and strip shortcodes and tags from variables, restrict per-object fields to `edit_post` and `manage_options`, and nonce on save and import.
- **llms.txt:** escape `[]():#` in titles and excerpts, strip shortcodes and oEmbed and scripts, never include private or noindex or password content, and never advertise a URL disallowed in robots.txt.
- **Import:** capability plus nonce, dry run first, backup nag, size caps, MIME checks, and never auto-deleting competitor data.
- **General:** keep the existing `_rankkernel_*` and `wp_rankkernel_*` prefixes so uninstall purge stays correct, and no telemetry or external calls without a user supplied key.

---

## 18. Performance requirements

- robots.txt and llms.txt: option read plus string assembly on their own endpoints only, never per page view, with a short edge cache and content invalidation on save.
- Head engine: lazy token resolution (resolve only tokens actually printed), one head pass, memoised per field per request, and a body-class and query-free presenter loop.
- Link index: populated on save is not acceptable; use a background batch (WP-CLI plus cron) with incremental updates, and cap suggestions per post.
- Content analysis: client side for the editor, no server round trip, no external calls.
- Image SEO: frontend filters only, no extra media meta reads beyond what WordPress already primes, bulk work in batches of 50.
- Every module: zero cost when disabled, no autoloaded blobs, and no new per request queries; state measured query counts rather than absolute claims.

---

## 19. Testing strategy

- Unit: token grammar including parameterised and format-argument tokens; per-context template resolution; robots merger with restrictive-wins; canonical rules including noindex suppression and pagination; description fallback; social fallback ordering; robots.txt directive validation and rejection; llms.txt selection and excerpt hygiene.
- Integration: head output order and duplicate prevention with a competing theme and plugin; one title, one description, one canonical, one robots; the `is_indexable()` gate driving robots, sitemap and canonical consistently.
- Security: token injection, HTML in templates, unsafe URLs, directive injection in robots.txt, llms.txt content injection, capability and nonce failures on every save and import path.
- Performance: query counting for the head, robots and llms endpoints; assert no per page view cost for endpoints; assert zero hooks when a module is disabled.
- Optional and gated: `.htaccess` backup, diff, restore, marker protection, and unwritable and Nginx failure modes.
- Migration: importer mapping correctness, dry run writes nothing, batch resume, and rollback.
- Uninstall: every new option and table removed under the prefix rules.

---

## 20. Migration requirements

One click importers for Yoast (`_yoast_wpseo_*`, taxonomy blobs, curated options) and Rank Math (`rank_math_*`, term and user meta, curated options) plus AIOSEO and SEOPress, with dry run preview counts and samples, batch processing with resume, a rollback snapshot, originals kept until confirm, unmapped keys logged and never silently dropped, redirect CSV import and export (done), and 404 log export. Never auto-delete competitor data; require an explicit cleanup step.

---

## 21. Documentation requirements

Per feature: architecture doc, admin usage doc, developer API doc (hooks, filters, template tags, shortcodes), REST and WP-CLI where applicable, migration doc, security notes, and performance notes. Update `docs/competitor-analysis` with this gate's findings, keep the research gates indexed, and keep the roadmap as the single source of truth.

---

## 22. Updated roadmap

See `ROADMAP.md`, updated in this task. Changes: Phase 2 marked "core implementation complete, follow-up remaining"; a new Phase 2.6 for head and crawl controls; Phase 3 expanded with Content Analysis, Image SEO and Internal Linking; Phase 5 expanded with head cleanup, header and footer injection, hreflang, site analyzer and the optional gated `.htaccess`; a new Phase 6 for Commerce, Local, News and Video; Phase 7 AI is BYO-key and read-only only; the deferred table now records the genuine external dependency items with reasons. Completed history preserved: Phase 0, Phase 1, and the Phase 2 modules that genuinely shipped.

---

## 23. Open architecture decisions

1. Approve the Phase 2.6 scope (head engine parity plus crawl signals) as the next implementation block.
2. Confirm `.htaccess` stays out of v1, or approve the hardened gated module.
3. Confirm the token grammar (`%name%` plus arguments) and the importer mapping table.
4. Confirm whether robots.txt and `.htaccess` stay one module or split.
5. Confirm the internal link index table is acceptable (the first new table beyond redirects and 404).
6. Confirm the aggressive head dedup is opt-in and off by default.
7. Confirm llms.txt is virtual by default with an opt-in physical write.
8. Confirm the AI module remains BYO-key and read-only reporting only.

---

## 24. Explicitly deferred features

Multi location local SEO and store locator; AI link automation with auto insertion; rank tracking and analytics dashboards; newsletter style reporting; social image watermarking; `.htaccess` redirect sync; remote site analyzer; content generation; agent write tooling. Each is deferred with a stated reason (external dependency, marketing value only, availability risk, or product fit).

---

## 25. Final completeness audit

Second pass question: with only this roadmap, would a build agent know RankKernel must eventually provide the meaningful functionality of Yoast Free and Premium and Rank Math Free and PRO? **Yes, with three caveats that are now explicitly recorded:** paid external services (Content AI, AI Visibility, AI Link Genius, external rank and analytics) are marked EXTERNAL DEPENDENCY and out of scope for the free product; watermarking and `.htaccess` sync are marked NOT APPROPRIATE with reasons; and the four owner-identified gaps (robots.txt, `.htaccess`, llms.txt, full metadata) are each assigned a phase and priority. The four are covered as: robots.txt and llms.txt in Phase 2.6 P0, the full metadata and head engine in Phase 2.6 P0, and `.htaccess` as an optional gated item in Phase 5 with a recommendation to omit.

No implementation was performed. No branch, no PR, and the breadcrumbs work on GH-15 and PR #16 was not modified.
