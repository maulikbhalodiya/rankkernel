# Feature Matrix — Yoast (Free/Premium) vs Rank Math (Free/Pro) vs Our Planned Plugin

> **Legend:** ✅ = verified in this audit's source code · ⚠️ = partially verified · 📄 = documented/marketing claim (source not in repo) · ➖ = not available
>
> **Sources:** [`yoast-audit.md`](./yoast-audit.md) (Yoast free 27.8 + Premium 27.8, fully code-verified) · [`rankmath-audit.md`](./rankmath-audit.md) (Rank Math free 1.0.277.2, code-verified; **Pro source not in repo**, Pro column uses the free code's own gating flags + public documentation)

---

## 1. On-Page & Content Analysis

| Feature | Yoast Free | Yoast Premium | RM Free | RM Pro | Ours (planned) |
|---|---|---|---|---|---|
| Focus keyphrases | ✅ 1 (`_yoast_wpseo_focuskw`) | ✅ up to 5 + synonyms (`_yoast_wpseo_focuskeywords`, `_yoast_wpseo_keywordsynonyms`) | ✅ 5 (`rank_math_focus_keyword` comma list) | 📄 Unlimited | **Free: unlimited** |
| Readability analysis | ✅ client-side JS (Web Worker `yoast-seo-analysis-worker`) | same | ✅ client-side JS (`assets/admin/js/analyzer.js`) | same | Free, client-side, no server round-trip |
| Internal link suggestions | ➖ | ✅ prominent-words TF-IDF (table `wp_yoast_prominent_words`, REST `yoast/v1/prominent_words/*`, `link_suggestions`) | ➖ (link **counter** free; suggestions Pro) | 📄 Advanced insights | **Free: link suggestions** |
| AI title/desc generation | ➖ (AI routes exist in free code: `yoast/v1/ai_generator/*`) | ✅ AI Optimize/Summarize (license-gated, `Payment_Required_Exception`) | ➖ | 📄 Content AI (credit packs) | **Free: BYO-key** (OpenAI/Claude/Gemini, user's own API key) |
| Orphaned / stale content filters | ➖ | ✅ post-list filters | ⚠️ cornerstone content (free) | — | Free |

## 2. Metadata Engine (Titles, Descriptions, Social)

| Feature | Yoast Free | Yoast Premium | RM Free | RM Pro | Ours |
|---|---|---|---|---|---|
| Title/desc templates + replace vars | ✅ (`%%var%%` engine, `inc/class-wpseo-replace-vars.php`) | same | ✅ (Paper + `class-replace-variables.php`) | same | Free — single-pass cached resolver (fixes both vendors' re-resolution waste) |
| OpenGraph output | ✅ full set (locale/type/title/desc/url/site_name/article:*, image) | same | ✅ full set + image dimensions (`og:image:width/height`) | same | Free |
| Twitter cards | ✅ (`twitter:*`, Slack `enable_enhanced_slack_sharing`) | same | ✅ incl. player/app card fields | same | Free |
| Social preview editor | ⚠️ (premium wires `yoast-social-metadata-previews`; free shares JS bundle) | ✅ | ✅ free | same | Free |
| Per-post overrides | ✅ ~25 `_yoast_wpseo_*` keys | + focuskeywords/keywordsynonyms | ✅ ~45 `rank_math_*` keys | same | Free — **single serialized key** (1 row vs 25–45) |
| Header/footer code injection | ➖ (not a Yoast feature) | ➖ | ➖ **verified**: no frontend output in free (PRO feature — re-audit found zero `footer_code` matches and no `general.header_code` keys) | 📄 | Free |

## 3. Technical SEO

| Feature | Yoast Free | Yoast Premium | RM Free | RM Pro | Ours |
|---|---|---|---|---|---|
| XML sitemaps (posts/tax/users) | ✅ (+XSL, images inline, 1000/page) | same | ✅ (same shape, file-based cache) | same | Free — cache **ON** by default (Yoast's is OFF) |
| News / Video sitemaps | 📦 separate paid addons | same | 📄 Pro-locked in free code (`disabled`+`probadge`) | 📄 | Phase-2 candidate |
| HTML sitemap | ➖ | ➖ | ✅ (`Html_Sitemap` class in sitemap module) | same | Free |
| Breadcrumbs | ✅ (+ schema piece) | same | ✅ (+ block) | same | Free — lazy generation (Yoast builds 4–6 queries even when unused) |
| Redirect manager | ➖ | ✅ 301/302/307/410/451 + regex (stored in **options**, not a table) | ✅ same codes + regex + fallback + auto-redirect on slug change (cache table `wp_rank_math_redirections_cache`) | 📄 + CSV import, bulk tools | **Free: full manager** — DB-backed with per-request cache, zero queries when disabled |
| CSV import (redirects) | ➖ | ✅ | ➖ (export-only in free: .htaccess/nginx) | 📄 | Free |
| 404 monitor | ➖ | ➖ (not even in Premium) | ✅ simple/advanced modes, limit-100 truncate | 📄 more detail | **Free** — log + 1-click "fix with redirect" |
| Instant indexing (IndexNow) | ➖ | ✅ (`Index_Now_Ping` in premium integrations) | ✅ free module (api.indexnow.org) | same | Free |
| robots.txt / .htaccess editors | ⚠️ file editor (tools) | same | ✅ | same | Free |
| Site-wide SEO analyzer | ⚠️ (indexation status, workouts) | same | ✅ (local tests + remote `rankmath.com/analyze` API) | same | Free, fully local |

## 4. Schema (Structured Data)

| Feature | Yoast Free | Yoast Premium | RM Free | RM Pro | Ours |
|---|---|---|---|---|---|
| Auto JSON-LD graph | ✅ 10 pieces (Article, WebPage, Breadcrumb, Website, Organization, Person, Author, FAQ, HowTo, Main_Image) | + premium pieces (Publishing Principles, etc.) | ✅ large type set (Article, LocalBusiness, FAQ, HowTo, Product, Recipe, Event, SoftwareApplication…) | same | Free — comparable coverage |
| Multi-schema per page | ✅ via @graph | same | ✅ | same | Free |
| Custom schema builder | ➖ | ➖ | ⚠️ basic (teased: `upgradeable`) | 📄 full builder | **Free: full builder** |
| Schema stored as | indexable table columns + per-block | same | `rank_math_schema_*` post/term/user meta (serialized) | same | Single-key meta |

## 5. Analytics & Integrations

| Feature | Yoast Free | Yoast Premium | RM Free | RM Pro | Ours |
|---|---|---|---|---|---|
| GSC/GA4 dashboard | ➖ (Site Kit bridge) | same | ✅ module (90-day cap: `class-analytics.php:624`) | 📄 full depth | Phase-2 (or Site Kit bridge) |
| Keyword rank tracking | ➖ (Wincher/Semrush REST routes exist in free) | same | ➖ (routes present) | 📄 Rank Tracker | ➖ (out of scope v1) |
| WooCommerce SEO | 📦 paid addon | same | ⚠️ basic free (wc settings in `rank-math-options-general`) | 📄 advanced | Free helper add-on (per blueprint) |
| Local SEO | 📦 paid addon | same | 📄 Pro | 📄 | Phase-2 |
| Importer from competitors | ✅ (`yoast/v1/importing/*`) | ✅ extension importer | ✅ (database-tools converters: Yoast blocks/FAQ/HowTo/TOC/Local) | same | **Free: 1-click Yoast+RM meta migrator** |

## 6. Platform Hygiene (our biggest differentiators)

| Concern | Yoast (verified) | Rank Math (verified) | Ours |
|---|---|---|---|
| Post-meta rows per post | ~25 keys | ~45 keys | **1 key** (`_rankkernel_meta_data`) |
| Autoloaded options | 7 groups, default autoload=yes; `wpseo_taxonomy_meta` single large serialized blob | 4 groups autoload=yes (general/titles/sitemap/instant-indexing) + large `rank-math-options-*` arrays; analytics notice explicitly autoload=no | Minimal option footprint; large data autoload=no |
| Custom tables | 6 (`wp_yoast_indexable` ~40 cols + 5 more) + premium prominent-words | 2 core (redirections, 404) + analytics set + Action Scheduler tables | **0 required for v1** (redirects/404 only if module enabled) |
| Uninstall behavior | **No-op** (`register_uninstall_hook(…, '__return_false')`) — retains everything | Filter-gated, default **retains all data** | Explicit user-choice cleanup, default clean |
| Admin ads/upsells | Notification center, Premium badge global CSS injection, dashboard widgets, menu badge | Pro-notice variants, module CTA boxes, "Go Pro" dashboard widget, plugin action links | **Zero** |
| External requests (frontend) | none verified on frontend ✅ | none verified on frontend ✅ | none (guaranteed) |
| External requests (admin) | my.yoast.com, semrush, tracking.yoast.com, HelpScout, Wincher | api.rankmath.com, mixpanel, rankmath.com analyzer, content-ai | none by default (BYO-key only) |
| Always-on frontend DB cost | indexable read/req + breadcrumb queries (4–6) + replace-var re-resolution (4+) | **redirect lookup 1–2 queries EVERY request** when module on | 1 meta read/req; 0 when modules off |

---

## Priority "We Win Here" List (ranked by market impact)

1. **Free redirect manager + 404 monitor** — Yoast charges (Premium), RM free but with per-request query tax and Pro-gated CSV import.
2. **Unlimited focus keyphrases free** — Yoast 1, RM 5.
3. **Full custom schema builder free** — RM Pro feature.
4. **BYO-key AI** — both vendors monetize AI; we pass API cost through at cost.
5. **Single-key meta + lean storage** — directly attack the documented bloat (25–45 meta rows, autoloaded option blobs, 40-column indexable table).
6. **Zero-query disabled modules** — copy RM's good `can_load_module` pattern, avoid Yoast's always-on DI container.
7. **Clean uninstall with user choice** — both competitors retain data by default.
