# RankKernel Reviewer Handoff (Final, GH-11)

Date: 2026-09-11. Branch: `GH-11` (unpushed, clean working tree). Plugin root: `wp-content/plugins/rankkernel`. Base: `main` at `af9aab8`. This branch holds 65 commits.

## 1. What we need from you

Review this package as a senior WordPress engineer would. Verify what you can verify from code and from the live site, challenge the architecture, and hunt for what we missed. Then reply with a **next prompt** for the build agent: precise, ordered, with acceptance criteria and a clear definition of done. Do not write code yourself.

Constraints your prompt must respect: every change goes on a `GH-<n>` branch; gates are `composer lint`, `composer stan`, `composer test`; nothing merges until the owner verifies manually; the plugin is 100 percent free forever (no PRO gates, no upsells, no telemetry); clean room (behavioral facts only, never competitor source); one version constant `RANKKERNEL_VERSION` in `rankkernel.php` drives every asset URL.

## 2. Project snapshot

RankKernel is a free, open source WordPress SEO plugin (WordPress 6.5+, PHP 8.1+). Repository: `maulikbhalodiya/rankkernel`. The working copy is a LocalWP site whose database holds roughly 30k posts of real content, used as the live test bed. Slug `rankkernel`, text domain `rankkernel`, namespace `RankKernel\`.

Architecture in one paragraph: two phase boot (`plugins_loaded:10` registers core services, `init` boots modules). `Plugin` is a service locator (no DI container). `ModuleManager` gates optional modules, so a disabled module is never instantiated and adds zero hooks. `ModuleEnableMap` performs the single `rankkernel_modules` option read per request. Settings live in one small autoloaded option (`rankkernel_settings`, whitelisted keys, merged defaults). All per object SEO data lives in ONE postmeta row (`_rankkernel_meta_data`), plus one term meta row and one user meta row. No custom tables, no cron. Sitemap caching is validator based (transients or object cache group `rankkernel-sitemaps`). Schema is built from pieces merged into one `@graph` with a normalizer that dedupes `@id`s, prunes dangling references, and strips empty values.

## 3. What shipped in this pass (awaiting owner verification, then PR and merge)

This pass was a correction and quality pass with four workstreams plus two rounds of live bug fixes.

### 3.1 FAQ and HowTo Gutenberg editor rebuild

- FAQ: structured card editor, labeled Question and Answer fields, item badges, empty state with help text, Add FAQ, Remove, Move up and Move down with numbered accessible names, stable collision free row ids with legacy migration, spread based updates, `li` rows inside the list, additive `questionTag` attribute whose empty value follows `titleWrapper` for byte identical legacy output.
- HowTo: structured numbered step cards, Title and Description, image via MediaUpload with preview and alt, Remove and Move controls with numbered labels and focus management, Inspector panels for description, total time, estimated cost, tools, materials, and additive optional attributes (`description`, `totalTime`, `estimatedCost`, `tools`, `materials`, step `alt`, step `id`), `stepTag` follow default.
- Both blocks keep explicit PHP asset registration (tests lock the absence of `editorScript` and `editorStyle` in block.json) and add `supports` plus `example`.

### 3.2 Live bug fixes reported by the owner

- **Answer focus loss (fixed at root cause).** All answer RichTexts rendered `data-wp-block-attribute-key="undefined"`, so Gutenberg keyed all answers to one selection identity and typing in any answer collapsed focus to the first item. Question inputs use TextControl, which is why only answers jumped. Fix: each RichText now carries a stable unique `identifier` (answer rows keyed by row id, titles keyed `title`); HowTo received the same treatment. Proven live in the editor on the owner's real post.
- **Wasted card height (fixed).** Badge, Move up, Move down, and Remove now share one compact header row in both blocks.
- **Double numbering (fixed).** Unordered FAQ lists now render plain bullets with zero number spans. Ordered FAQ lists keep a single number span inside each question heading, so the number inherits the heading size, weight, and color, and the list carries no markers. HowTo frontend had the same double print and is now marker free with a single number per step. Verified live in frontend HTML.

### 3.3 Schema text reduced to plain text

FAQ answers and HowTo step text previously ran through `wp_kses_post`, so JSON-LD carried `<p>` tags (`"text":"\u003Cp\u003Efa1\u003C/p\u003E"`). Both `FaqPiece` and `HowtoPiece` now reduce schema text to plain text: block level tags become spaces so words never merge, tags are stripped, entities are decoded, and whitespace collapses. Frontend rendering keeps its safe HTML separately, so visible formatting and structured text are both correct. Verified live: the FAQ answers now read `fa1`, `fdfsi`, `asoppop` and the HowTo step text `sdsaasdsadsuii`, with no `<` anywhere in the graph.

### 3.4 Single version constant

`RANKKERNEL_VERSION` in `rankkernel.php` is now the single source. `Plugin::version()` reads it (with a safe fallback for unit tests), and every script and style version flows through it: the FAQ and HowTo editor script and stylesheet, the schema metabox script, and the schema settings script. Bumping that one value busts every editor cache at once. The file modification time approach that was briefly added is removed, per the owner's instruction to drive versions from the constant. A test locks the plugin header `Version:` to the same constant so they cannot drift.

### 3.5 WordPress coding standards made enforceable

- `phpcs.xml` previously ran WordPress standards on only two root files and PSR12 on all of `src/` and `tests/`, which hid about 23k findings. It now progressively enforces WordPress Extra on the touched files, with mirrored PSR12 excludes so the two styles never overlap. The enforcement map lives in `docs/coding-standards.md`.
- PSR-4 naming (filenames, camelCase, short arrays) is a deliberate project decision, encoded as scoped exclusions with justification comments.
- Security and i18n first group fixed across Admin and providers: nonce, sanitize, escape, prepared statement review (each hit verified individually, per line ignores carry reasons), and five concatenated i18n fragments corrected.
- MetaPayload FAQ answer and HowTo step text unified on the rich text sanitize path so the same content keeps its formatting regardless of authoring source.

## 4. Live proof (run 2026-09-11 against the Local test site)

- Frontend FAQ unordered: `<ul class="rankkernel-faq-list" role="list" style="list-style-type:disc;">` with zero `rankkernel-faq-number` spans.
- Frontend FAQ ordered: marker free list, one number span inside each question heading.
- Frontend HowTo: `<ol class="rankkernel-howto-list" role="list" style="list-style-type:none;">` with one number span inside each step heading.
- Schema graph: one script block, unique `@id`s, no empty values, cross references resolving, and no HTML tags in any FAQ answer or HowTo step text.
- Editor: clicking and typing in Answer 3 keeps focus and caret in Answer 3; earlier behavior collapsed to Answer 1. Confirmed by driving the real Gutenberg editor.

## 5. How to verify anything yourself

- Gates: `composer lint && composer stan && composer test` (currently 454 tests, 1856 assertions, all green).
- Test files mirror `src/` one to one under `tests/Unit/`, plus `tests/Unit/Support/` doubles.
- Live JSON-LD: fetch any page, extract `<script type="application/ld+json">`, parse it, assert one block, unique `@id`s, no empty values, and no `<` in any `text`, `name`, or `acceptedAnswer` value.
- DB (Local socket): user `root`, password `root`, database `local`. Options of interest: `rankkernel_modules`, `rankkernel_settings`, `rankkernel_db_version`. Post meta key `_rankkernel_meta_data`.
- NEVER start or stop Local services from tooling (datadir conflict risk). The owner manages the app.

## 6. Known limitations (genuine, not roadmap filler)

- There is no JavaScript test harness in the repo, so editor interactions are covered by code plus PHP rendered output tests, not automated browser tests.
- Tools and materials lists have no reorder controls.
- The `ul` versus `ol` FAQ setting is now semantic only, since both are numbered or bulleted by markup rather than list markers.
- The one time key assignment on first load of legacy id less rows can remount a field once before interaction; positional updates keep it content safe.
- Block only keys such as step `alt`, step `id`, and `stepTag` travel in post content and never through meta.
- `MetaPayload` REST schema keeps `additionalProperties: false` on HowTo step items, so future step keys saved through meta would be stripped.
- WooCommerce mapping is covered by seam doubles only; no live store exists in this environment.
- The plugin header `Version:` and `RANKKERNEL_VERSION` are two literals kept in sync by a test; WordPress requires the header string.

## 7. Suggested next investigations (pick what matters)

1. Redirects and 404 monitor phase, applying the same audit first approach.
2. HTML sitemap scope decision (Rank Math ships one, we do not).
3. Video and News sitemaps (both competitors gate or omit, a free tier opportunity).
4. Setup wizard and branded admin (currently functional native UI).
5. Benchmark protocol against Yoast and Rank Math on the 30k post dataset (never run).
6. A JavaScript test harness (Jest or Playwright) so editor interactions get automated coverage.
7. ESLint and stylelint adoption so editor JS and CSS enforce WordPress standards instead of only PHP.

## 8. Standing rules for any build prompt you write

Issue, then `GH-<n>` branch, then gates, then PR titled `GH-<n>: <summary>` with description and `Closes #<n>`, then merge only after the owner verifies manually. Atomic commits with plain subjects ending `(#<n>)`, why bodies, and the `Ultraworked with [Sisyphus](https://github.com/code-yeongyu/oh-my-openagent)` footer. No standalone dash characters used as punctuation anywhere. No new tables, no per property meta rows, no cron, no external requests, no React for frontend output, no new dependencies without justification. Tests for every behavior changed, including rendered output assertions. Never break module gating, MetaPayload sanitize shapes, sitemap validator protocol, rewrite rules, or uninstall purge logic. Any asset or stored version must read `RANKKERNEL_VERSION`.

## 9. Final numbers for the merged branch

- Tests: 454, assertions: 1856, all passing.
- `composer lint`: clean, exit 0.
- `composer stan`: clean, level 6, no errors.
- `GH-11` was merged into `main` and pushed. `origin/main` is now `2ea6846`. The branch holds 65 commits since the base. Working tree clean.

## 10. Research gate: Redirects and 404 Monitor (research only, awaiting owner approval)

This is the next planned work (blueprint §G, ROADMAP 2.4 and 2.5). The full report is at `docs/research/redirects-404-research-gate.md` (24 sections). No implementation has been created. These are the points to review before any build prompt is written.

### 10.1 How it was researched

Rank Math free and premium, Yoast free and premium, official documentation, public source where available (Rank Math free repo and Yoast free repo were inspected, neither premium source is public and neither was inspected), plus the Redirection plugin and Safe Redirect Manager as third references, plus a read only review of the RankKernel architecture. Every claim in the report is tagged as documentation, source, observed, or inferred. Clean room was kept throughout.

### 10.2 Three findings that change the plan

1. **Rank Math gives away far more than the blueprint assumed.** Regex matching, automatic slug change redirects, and 404 to redirect are all free. Only scheduled activation and expiration, rule categories, CSV import and export, server file sync, and 404 log export are paid. Parity is real work, and our free CSV is a genuine differentiator rather than a small one.
2. **Yoast pushes everything behind Premium and stores it badly.** No redirect manager and no 404 monitor in free. Premium stores rules in autoloaded `wp_options`, which caused a documented scaling incident, and it had a real security incident (Apache directive injection through a redirect endpoint). Server file mode is disabled on multisite.
3. **The existing RankKernel blueprint design is validated and should be kept.** The rival weak points are exactly where our spec is already stronger: cache first with a bounded cold miss versus matching on every request with a serialized `LIKE` prefilter, prune by age and count oldest first versus Rank Math truncating the whole log and destroying history, and no autoloaded rule blob.

### 10.3 One correction to make

The blueprint says Rank Math free CSV is export only. Current research shows Rank Math CSV import and export is premium. The plan is unaffected and the free CSV differentiator is stronger, but the blueprint and feature matrix wording needs correcting.

### 10.4 Recommended scope for approval

- Redirects: exact, prefix, contains, suffix, and wildcard matching first, then guarded regex last with hard caps against ReDoS. Free CSV import and export. Slug change watcher with a disable setting. Hit counter and last accessed. Native admin CRUD with search, status views, sorting, pagination, and bulk actions.
- 404 Monitor: simple dedupe logging, prune by age and count oldest first, path and keyword exclusions, query parameter handling, flood caps, no IP by default, per row and bulk create redirect.
- Storage: the two redirect tables and the one 404 table already specified in blueprint §D.4, created by migration only when the module is enabled, `autoload=no` settings, uninstall handled by the existing `wp_rankkernel_*` prefix purge.
- Performance promise, stated honestly: zero cost when the module is off, cache first when it is on, at most one targeted query on a cold miss.

### 10.5 Excluded on purpose

Server file sync to `.htaccess` or Nginx, rule categories, multiple source URLs in one row, WooCommerce specifics in this module, REST endpoints in the first release, autoloaded option storage, any full table truncate, IP storage by default, cron based expiry, and regex first matching. Each exclusion has a written reason in the report.

### 10.6 What we want back

A review of the research report, then a next prompt that is precise, ordered, and has acceptance criteria, respecting the standing rules in section 8. Flag anything we got wrong, any scope we missed, and any storage, performance, or security decision you disagree with. Do not write code.
