# Audit Fix Tracker — friend audit 2026-10-08, verified against main

> Owner-ordered working file. Both agents (office PC + server) implement from here.
> Rule: one finding = one GitHub issue = one `GH-<n>` branch = one PR. Never push to `main`.
> Gates per PR: `composer lint`, `composer stan`, `composer test`, `composer test:js`, plus
> `node --check` per JS file. Report real outputs. Owner merges.
> Statuses: TODO / DOING(name) / IN-REVIEW(pr) / DONE(pr). Move an item only when the state changes.

## How we split the work (avoid stepping on each other)

- **PART A — office PC (Muse).** Metadata/SEO output, redirects engine, tables/migrations,
  module state, our own rate limiter, RTL/i18n leftovers.
- **PART B — server agent.** Readme honesty, lifecycle (deactivate/uninstall/cron/backups),
  IndexNow async, UX batch, schema preview + social UI.
- Work inside your own file areas (listed per item). If you must touch shared files
  (`readme.txt`, `rankkernel.php`), rebase on latest `main` before opening the PR.
- VERIFY-ONLY items need no code: confirm on a live/staging site, then mark DONE with evidence.

## PART A — office PC

### Phase A1 — data loss + upgrade path (do first)

- [ ] **C-1 (Critical) — any settings save wipes llms.txt.** Status: TODO.
  Evidence: `src/Admin/SettingsPage.php:693-695` gates on robots-module-on instead of posted
  section; form carries only the active tab. Repro: enable llms.txt with content → save from
  Webmaster Tools tab → content gone.
  Fix: gate `saveRobots()`/`saveLlms()` on the posted section id (`partialRequest()` already
  returns it); give each section its own nonce action. Tests: save from an unrelated tab keeps
  llms.txt + robots intact (unit, both directions). Accept: repro steps no longer destroy data.
- [ ] **C-5 (Critical) — schema changes can never reach existing installs.** Status: TODO.
  Evidence: `exists()` early return + `CREATE TABLE IF NOT EXISTS` in all three
  `LogTable.php` files (`Monitor`, `Redirects`, `InstantIndexing`).
  Fix: remove early return, drop `IF NOT EXISTS` so `dbDelta` diffs, or move creation into
  real `MigrationRunner` migrations. Tests: add-column migration applies on a pre-existing
  table. Accept: version bump with a new column alters old installs.
- [ ] **H-17 (High) — no-op migration + no downgrade path.** Status: TODO.
  Evidence: `src/Plugin.php:127` registers an empty `0.1.0` migration, making the ledger-sync
  fallback unreachable. Fix with C-5: real baseline migration, ledger sync, downgrade notice.
- [ ] **H-14 + QA-1 (High) — `purge_on_uninstall` tri-state destroyed by any save.** Status: TODO.
  Evidence: `src/Admin/SettingsPage.php:687` writes bool on every tab; checkbox lives only on
  Advanced (`Views/sections/advanced.php:71`). Fix: write only when the Advanced section posted
  (same section-gating as C-1; do together). Tests: null survives General-tab save; checkbox
  round-trips. Accept: QA-1 checkbox persists.
- [ ] **H-16 (High) — dashboard toggle can silently disable all modules.** Status: TODO.
  Evidence: `ModulesController.php:183` (`array_map strval`) vs `DashboardPage.php:247`
  (`is_int($key)` branch) disagree on list-vs-map shape. Fix: normalize to one shape in both
  writers (and `ModuleEnableMap` already tolerates both — keep that). Tests: assoc-shaped option
  survives a toggle with all other modules intact (the lane's proven repro). Mutation-prove it.

### Phase A2 — redirects engine

- [ ] **C-2 (Critical) — activating a redirect skips loop validation.** Status: TODO.
  Evidence: `set_active()` + `bulk('activate')` in `RedirectRepository.php` never call
  `Validator::assess_safety()`; `detect_loop` ignores inactive rules, so cycles hide until
  switched on. Fix: validate over the post-toggle active set, refuse (or require explicit
  `rk_force_unverified` override like the add form has); add a runtime hop cap in `Redirector`
  (`wp_redirect_status` filter). Tests: the report's 6-step `/a→/b→/c→/a` repro refused at step 5.
- [ ] **H-1 (High) — SlugWatcher writes with no capability/status gate, no cap.** Status: TODO.
  Evidence: zero `current_user_can`/`post_status` in `SlugWatcher.php`; exact rules bypass the
  pattern cap (`RedirectRepository.php:900`). Fix: `manage_options` gate + publish-only gate,
  delete rules on un-publish, extend cap to exact rules.
- [ ] **H-3 (High) — CSV import half-applies, no dry-run/rollback/override.** Status: TODO.
  Evidence: row loop writes before failure in `CsvHandler.php`; no `rk_force_unverified`
  equivalent. Fix: dry-run mode, all-or-nothing transaction (or staging + ledger), force
  override for unverifiable chains. Tests: 500-row import with one bad row leaves zero partial rows.
- [ ] **M-8 (Med) — SlugWatcher covers only post/page; CPT renames 404.** Status: TODO.
  Evidence: `TYPES = ['post','page']`. Fix: cover all public post types. Do with H-1 (same file).

### Phase A3 — metadata / SEO output correctness

- [ ] **C-3 (Critical) — homepage emits `og:type=article` + dates.** Status: TODO.
  Evidence: `Context.php:218` (`is_singular`) tested before `:238` (`is_front_page`).
  Fix: test `is_front_page()`/`is_home()` first. Tests: front page → `website`, no `article:*`;
  plain page → `website`.
- [ ] **C-4 (Critical) — archives get bare sitename titles.** Status: TODO.
  Evidence: `Context::title()` (`:537`) has no archive branch. Fix: CPT archive →
  `get_post_type_object()->labels->name`, date archive → archive title (reuse
  `TrailBuilder::cptArchiveItem()`). Tests: archive title carries the archive name.
- [ ] **H-4 (High) — no meta description on front/posts index.** Status: TODO.
  Evidence: zero `blogdescription` hits; `resolveDescription()` ends at `''`.
  Fix: tagline fallback. Do with C-3/C-4 (same area).
- [ ] **H-5 (High) — homepage title uses meaningless CMS page title.** Status: TODO.
  Evidence: no `home_title` in `TagsReplacer::SUPPORTED_TOKENS`. Fix: front-page title template.
- [ ] **H-6 (High) — self-referential homepage BreadcrumbList.** Status: TODO.
  Evidence: `filterBreadcrumbTrail()` discards legitimately empty trails (`:332-333`).
  Fix: honor empty trails (emit nothing).
- [ ] **H-9 (High) — Article nodes without author/image.** Status: TODO (needs live data to
  fully prove; code prunes dangling refs silently). Fix: stop emitting incomplete nodes;
  resolves together with the `post_author=0` data cleanup on site.
- [ ] **M-4 + M-5 (Med) — twitter card + default image.** Status: TODO. Fix: degrade
  `twitter:card` to `summary` without image; accept URL-only default image.
- [ ] **M-6 + M-7 (Med) — og:url vs canonical + pagination canonical.** Status: TODO.
  Fix: honor canonical override in `og:url`; paginated archives self-canonical with page number.

### Phase A4 — sitemaps

- [ ] **H-7 (High) — authors sitemap advertised but empty.** Status: TODO.
  Evidence: count includes `post_author=0`, entries drop it. Fix: exclude `0` in the count query.
- [ ] **H-8 (High) — index `lastmod` always "now".** Status: TODO (`IndexBuilder.php:330`).
  Fix: real per-file lastmod.
- [ ] **H-10 (High) — cache TTL 0 + no `deleted_post` hook.** Status: TODO. Fix: add hook,
  bounded TTL.
- [ ] **H-12 (High) — cache keys lack blog id.** Status: TODO (`xml_{set}_{page}`,
  `rule_{sha}`). Fix: prefix with blog id.
- [ ] **H-13 (High) — `do_blocks()` per post per build.** Status: TODO
  (`PostsProvider.php:314`). Fix: extract images without full render.
- [ ] **H-21 (High) — sitemap toggle never invalidates cache.** Status: TODO (only
  `flush_rewrite_rules`, no invalidate call). Fix: `invalidateAll()` on toggle.

### Phase A5 — ours + leftovers

- [ ] **M-3 (Med) — our support rate limiter is dead on object-cache sites.** Status: TODO.
  Evidence: `SupportDelivery.php` reads `_transient_timeout_*` from options, which persistent
  cache never writes. Fix: store expiry inside the transient value. Owned by us — fix honestly.
- [ ] **M-2 (Med) — runtime ReDoS guard weaker than save-time.** Status: TODO. Fix: align
  `Matcher` with `RegexSafety` + differential test locking them together.
- [ ] **M-12 (Med) — sitemap pagination unstable (LIMIT/OFFSET + invalidate-per-save).**
  Status: TODO. Fix: stable ordering/keyset or accept + document.
- [ ] **M-13 + M-14 (Med) — 404 insert race + FloodGuard RMW/double-write/dead option.**
  Status: TODO. Fix: atomic increments, single store, drop the zero-reader option.
- [ ] **M-15 (Med) — two analysis checks contradict.** Status: TODO (`Analyzer.php:486-503`).
  Fix: one boundary definition for both checks + regression test.
- [ ] **M-18 (Med) — .htaccess save non-atomic, backups collide.** Status: TODO. Fix: LOCK_EX +
  temp-then-rename, unique backup names. (Coordinate file area with PART B M-1 — different
  aspects, same file family; rebase before PR.)
- [ ] **M-19 (Med) — subsites get nothing on network activation.** Status: TODO. Fix:
  `wp_initialize_site` hook.
- [ ] **M-20 (Med) — CSV export writes formula chars at storage time.** Status: TODO. Fix: apply
  anti-formula rule at export, not storage.
- [ ] **M-22 (Med) — attachment N+1 in sitemap providers.** Status: TODO. Fix: prime attachment IDs.
- [ ] **M-24 (Med) — zero RTL support.** Status: TODO. Fix: RTL pass over the 10 stylesheets.
- [ ] **M-25 (Med) — translations never load.** Status: TODO. Fix: `load_plugin_textdomain()` +
  generate `.pot` for the 1,368 strings.
- [ ] **M-5-duplicate? no — L-1..L-5 triage.** Status: TODO. Read each LOW (HitCounter dead keys,
  autoloaded option growth, KeywordIndex LIKE scan, network activation gaps, silent `0.0.0`
  version) and either fix cheaply or record as accepted with a reason. No silent drops.

## PART B — server agent (personal PC)

- [ ] **C-6 (Critical) — readme lies about data sent to the author.** Status: TODO.
  Evidence: `readme.txt:43` vs `SupportRequest.php:27` recipient + always-on diagnostics.
  Fix: rewrite Privacy section (recipient, diagnostics, Reply-To, screenshots), delete the false
  sentence, add External Service section. This blocks wp.org submission — do early.
- [ ] **H-22 (High) — physical llms.txt can never be deleted.** Status: TODO (no
  `delete`/`unlink` in writer). Fix: add delete + wire to disable + uninstall cleanup.
- [ ] **H-23 (High) — deactivation leaves rules + .htaccess blocks behind.** Status: TODO
  (deliberate no-op at `rankkernel.php:148-151`). Fix: flush rewrite rules on deactivate.
- [ ] **H-11 + M-17 + M-21-prune (High/Med) — schedule the 404 prune.** Status: TODO (zero cron
  calls in `src/`). Fix: daily event with idempotency guard, register on activation, clear on
  deactivation; fold the `COUNT(*)` scans down while there.
- [ ] **M-1 (Med) — .htaccess backups unbounded + web-fetchable.** Status: TODO
  (`src/Admin/HtaccessFile.php:180-181`). Fix: move out of docroot (or deny) + prune old ones.
- [ ] **H-20 (High) — IndexNow blocks post save up to 10s.** Status: TODO
  (`IndexNowClient.php:37,225`, `blocking=>true`). Fix: async queue (defer, don't drop);
  reclassify 403 as transient with retry affordance in the log UI.
- [ ] **UX batch (High→Low): UX-1 Clear-Log confirm, UX-5 save-button glyph, UX-7 popstate
  reload, UX-2 modal focus trap, UX-3/UX-4 token-scale type/spacing, UX-6 disabled inputs,
  UX-8 preview labels.** Status: TODO. Evidence locations are in the audit §ADMIN UX. Fix each
  per its note; snapshot/icon-subset tests must stay green (subwtest glyphs come from the
  shipped font only).
- [ ] **QA-4 + QA-5 (Med) — hardcoded schema preview + social fields with no UI.** Status: TODO.
  Fix: render the preview from the live graph; expose the social fields or remove the options.

## VERIFY-ONLY (no code unless live check disagrees)

- [ ] **H-15 — VERIFY on staging that the conflict notice is live-accurate.** Status: TODO.
  Code on main already re-checks + self-cleans; confirm the stale-warning symptom is gone.
- [ ] **QA-8 — VERIFY export→import round-trip.** Status: TODO. Code uses one shared HEADER
  const; run one real round-trip and close on success.
- [ ] **H-9 data side — `post_author=0` cleanup on site.** Status: TODO (site data, not code).

## Review checklist (office PC reviews every server PR before owner merges)

1. Branch is `GH-<n>` off latest main, single finding per PR, `Closes #<n>` present.
2. All four gates green in CI **and** reproduced locally for the touched areas.
3. Mutation proof: each new guard deleted → its test fails → restored.
4. No `docs/` changes, no `as any`/`@ts-ignore` equivalents, noico/icon-subset violations,
   no raw hex in CSS, text domain exact, nonces + capabilities on every write path.
5. No-JS fallbacks preserved where the page had them; `hidden`-attribute + `<noscript>`
   pattern where server pre-hides (see GH-207).
6. Rebase onto latest main, push, request review; owner merges. Delete the branch after merge
   and tick the item DONE(pr) here.
