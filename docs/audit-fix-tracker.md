# Audit Fix Tracker — friend audit 2026-10-08, verified against main

> Owner-ordered working file. Both agents (office PC + server) implement from here.
> Rule: one finding = one GitHub issue = one `GH-<n>` branch = one PR. Never push to `main`.
> Gates per PR: `composer lint`, `composer stan`, `composer test`, `composer test:js`, plus
> `node --check` per JS file. Report real outputs. Owner merges.
> Statuses: TODO / DOING(name) / IN-REVIEW(pr) / DONE(pr). Move an item only when the state changes.
> Each item below carries its full context: what breaks, why it breaks (root cause), the exact
> evidence on current main, step-by-step fix instructions, and how to prove it is fixed. Do not
> shortcut the steps; the detail is load-bearing. Short summaries are for the owner only — agents
> work from this file.

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

- [ ] **C-1 (Critical) — any settings save wipes the entire llms.txt and AI-crawler policy.**
  Status: DONE (#243 merged).
  What breaks and why it matters: the General Settings form renders only the currently
  selected section (`src/Admin/Views/settings.php:161` requires only
  `sections/<currentSection>.php`, and the AJAX tab loader swaps that body per tab). But the
  save handler in `src/Admin/SettingsPage.php:693-695` gates on *whether the robots module is
  enabled*, not on *which section was posted*. So `saveLlms()` rebuilds the whole llms.txt
  option from `$_POST` absence (`'enabled' => isset($_POST['rk_llms_enabled'])` becomes false,
  summary/content become empty strings) and `saveRobots()` writes `'crawlers' =>
  $_POST['rk_robots_policy'] ?? []`, which sanitizes back to the full default map. Reproduction
  from the audit, verified against current source: open General → llms.txt tab, enable it, write
  a curated document, Save; then open Webmaster Tools, paste a token, Save — the entire llms.txt
  is gone with no undo, and every AI-crawler Allow/Block decision silently resets to defaults. It
  repeats from Breadcrumbs, Social, Advanced, or General tabs, indefinitely. On a live site with
  the robots module enabled this is unrecoverable loss of hand-written content.
  Evidence on main: `src/Admin/SettingsPage.php:693-695`; `saveLlms()` at `:512`,
  `saveRobots()` at `:452`; `partialRequest()` at `:425` already returns the posted section id
  but the save path ignores it.
  Fix steps: (1) pass the posted section id from `partialRequest()` down into the save path;
  (2) call `saveRobots()` only when the posted section is `robots` and `saveLlms()` only when it
  is `llms`; every other section saves only its own fields; (3) better still, give each section
  its own nonce action so a save is only accepted from the form that owns the data, and reject
  cross-section posts; (4) do NOT change the per-field sanitizers, only the gating.
  Tests required: unit test that saving from Webmaster Tools/Breadcrumbs/Social/General/Advanced
  leaves an existing llms.txt option and robots policy byte-identical; unit test that saving from
  the llms tab still writes; mutation-prove by removing the section gate and watching the new
  test fail. Accept when: the audit's exact repro (curated llms.txt → save from another tab →
  content intact) passes.
- [ ] **C-5 (Critical) — schema changes can never reach existing installs.**
  Status: DONE (#248 merged).
  What breaks and why it matters: all three module table files (`src/Modules/Monitor/
  LogTable.php`, `src/Modules/Redirects/RedirectTable.php`,
  `src/Modules/InstantIndexing/LogTable.php`) guard table creation with `if (self::exists())
  return true;` placed *before* the `dbDelta()` call, so `dbDelta` — whose entire job is the
  column diff — is skipped on every install that already has the table. Shipping version 0.2.0
  with a new column would silently do nothing everywhere. Second, independent defect in the same
  lines: the DDL reads `CREATE TABLE IF NOT EXISTS`, but core's `dbDelta()` parses the table
  name with `preg_match('|CREATE TABLE ([^ ]*)|')`, so the name resolves to the literal string
  `"IF"` and the query is filed under `$cqueries['IF']`; core's own docblock says it doesn't
  rely on `IF NOT EXISTS`. The code comments claim "idempotent by design" — the claim is false.
  This is the only silent data-correctness defect in the plugin: without it the plugin cannot be
  safely evolved past 0.1.0.
  Evidence on main: early return at `Monitor/LogTable.php:149` (same shape at
  `RedirectTable.php:175`, `InstantIndexing/LogTable.php:138`); `IF NOT EXISTS` at `:165`,
  `:191`, `:154` respectively; `dbDelta()` calls at `:192`, `:219`, `:171`.
  Fix steps: (1) remove the `exists()` early return in all three files so `dbDelta()` always runs
  (it is itself idempotent); (2) drop `IF NOT EXISTS` from all three DDL strings; (3) better
  still, move table creation into real `MigrationRunner` migrations so schema has a ledger;
  (4) keep the `function_exists('dbDelta')` require fallback as-is.
  Tests required: a test that builds the old table shape, runs `ensureTables()`, and asserts the
  new column exists afterwards (all three tables). Accept when: the early return is gone, the DDL
  has no `IF NOT EXISTS`, and the migration test passes.
- [ ] **H-17 (High) — migration baseline is an unconditional no-op; no downgrade path.**
  Status: DONE (#256 merged).
  What breaks and why it matters: `src/Plugin.php:127` registers an empty `0.1.0` migration
  unconditionally, so `MigrationRunner.php:130` (`if ([] === $this->migrations)`) and its entire
  ledger-sync fallback are unreachable dead code. On downgrade (ledger says 0.2.0, plugin is
  0.1.0) `version_compare` yields no pending migrations and the runner silently returns — no
  rollback, no notice, no log. Combined with C-5 there is not even a column to roll back to.
  Evidence on main: `src/Plugin.php:120-131` (comment admits "zero required tables");
  `MigrationRunner.php:126-186`.
  Fix steps: do together with C-5 — (1) make the baseline migration real (creates the three
  tables via the C-5 path); (2) restore the ledger-sync fallback to reachability and cover it
  with a test; (3) define downgrade behavior explicitly (at minimum: admin notice + log entry
  when ledger is newer than code; never silently skip). Tests: downgrade scenario produces the
  notice and leaves data intact.
- [ ] **H-14 + QA-1 (High) — `purge_on_uninstall` tri-state destroyed by any save; checkbox
  cannot be set.**
  Status: TODO.
  What breaks and why it matters: the design is deliberately three-state (`null` = undecided,
  `true`/`false`), and the REST schema honors it (`['type' => ['boolean','null']]`). But the
  admin handler at `src/Admin/SettingsPage.php:687` writes `$partial['purge_on_uninstall'] =
  isset($_POST['purge_on_uninstall'])` unconditionally on every tab, while the checkbox lives
  only on the Advanced tab (`Views/sections/advanced.php:71`). The audit proved live that an
  ordinary General-tab save converts `null` to `false`, so "operator has not decided" becomes
  unreachable from the UI forever after — and QA-1 confirms the checkbox itself does not persist.
  Same root cause family as C-1 (per-tab form, whole-option save), so do it in the same PR.
  Evidence on main: `SettingsPage.php:687` + `:149` (checkbox state read); `advanced.php:71`.
  Fix steps: (1) write `purge_on_uninstall` only when the posted section is Advanced (reuse the
  C-1 section gate); (2) when Advanced posts without the checkbox key present at all, preserve
  the stored value instead of writing false; (3) verify the REST `boolean|null` contract still
  round-trips.
  Tests required: null survives a General-tab save; checked/unchecked round-trips from Advanced;
  mutation-prove by removing the section condition. Accept when: QA-1's checkbox persists and the
  tri-state is reachable again.
- [ ] **H-16 (High) — dashboard toggle can silently disable every other module.**
  Status: DONE (#252 merged).
  What breaks and why it matters: two writers disagree on the option shape.
  `ModulesController.php:183` runs `array_map('strval', $current)` on the raw option, while
  `DashboardPage.php:247` (`enabledIds()`) branches on `is_int($key)`, which never matches an
  associative map's string keys. The audit lane proved it: an assoc-shaped option reads back as
  `[]` through `enabledIds()`, and toggling one module writes just `["404"]` — metadata, schema,
  sitemaps, breadcrumbs, redirects, monitor, IndexNow and robots all vanish with no error shown.
  Evidence on main: both lines above; `ModuleEnableMap` tolerates both shapes on read, which is
  why this stays invisible until a write happens.
  Fix steps: (1) normalize to ONE canonical shape (list of ids) in both writers — normalize on
  read inside each writer before mutating, not only on display; (2) keep `ModuleEnableMap`'s
  tolerance as a safety net; (3) add a guard test using the lane's exact repro (assoc-shaped
  option → toggle one module → all others intact).
  Tests required: the repro as a regression test plus the mirror direction (list-shaped option
  through both writers). Mutation-prove by reverting one writer to the old shape.

### Phase A2 — redirects engine

- [ ] **C-2 (Critical) — activating a redirect skips loop validation; live infinite loops possible.**
  Status: DONE (#249 merged).
  What breaks and why it matters: `Validator::assess_safety()` runs on the add/edit form
  (`RedirectsPage.php:1199`) and in `CsvHandler`, but `set_active()` and `bulk('activate')` in
  `RedirectRepository.php` (`:451`, `:493`) are bare `UPDATE … SET is_active = 1`. Worse,
  `Validator::detect_loop()` deliberately ignores inactive rules, so a cycle hides while one
  edge is off and appears the moment it is switched on. The audit's 6-step repro (`/a→/b`,
  `/c→/a`, deactivate first, add `/b→/c` which validates clean, reactivate) ends in
  `ERR_TOO_MANY_REDIRECTS` because `Redirector`'s only guard is a per-process reentry flag
  while each hop is a fresh browser request. Same via Bulk → Activate.
  Evidence on main: no `assess_safety` call in `set_active`/`bulk` (only call sites are the form
  and CSV); `Validator.php:235-237` skips inactive; `Redirector.php:137-239` per-process flag.
  (Note: `SlugWatcher.php:175` DOES call `assess_safety` — loop screening on auto-create exists;
  the gap is strictly the toggle paths.)
  Fix steps: (1) run `assess_safety()` over the post-toggle active set inside `set_active()` and
  the activate branch of `bulk()`, refusing with the same error surface as the form (or accept an
  explicit `rk_force_unverified`-style override mirroring the form's escape hatch — check what
  the add form names it and reuse the name); (2) independently add a runtime hop cap in
  `Redirector` (refuse hops above a small threshold via the redirect-status filter) as
  defense-in-depth; (3) do NOT change `detect_loop`'s inactive-skip (it is load-bearing for the
  add path) — validate the *resulting* active set instead.
  Tests required: the exact 6-step repro as a regression test (refused at step 5, both single
  and bulk paths); a hop-cap test. Mutation-prove by removing the toggle-time call.
- [ ] **H-1 (High) — SlugWatcher writes redirects with no capability check, any status, no cap.**
  Status: DONE (#255 merged).
  What breaks and why it matters: `handle_post_updated()` (`SlugWatcher.php:119`) hooks
  `post_updated`, which fires for every post write by anyone who can edit — Contributor on own
  drafts, Authors, REST, WP-CLI, importers. The file contains zero `current_user_can` and zero
  `post_status` checks (verified), while every other write path into `wp_rankkernel_redirects`
  sits behind `manage_options` + nonce. The write cap explicitly exempts exact rules
  (`RedirectRepository.php:900`), which is all this watcher creates. Attack path from the audit:
  a Contributor renames a draft slug 5,000 times → 5,000 rows in an admin-gated table with no
  cap and no trail; renaming a published post creates a live public 301 no administrator
  approved; trashing leaves a permanent 301 at a trashed URL.
  Fix steps: (1) `if (!current_user_can('manage_options')) return false` (or the narrowest
  capability the team agrees — ask the owner if unsure, do not invent);
  (2) `post_status === 'publish'` gate before the insert at `:187`; (3) delete the auto-rule on
  un-publish/trash; (4) extend the cap to cover exact rules or add a watcher-specific cap.
  Tests required: contributor-context write refused; draft rename creates nothing; un-publish
  removes the rule; cap enforced at N+1. Mutation-prove each gate.
- [ ] **H-3 (High) — CSV import half-applies with no rollback, no dry-run, no override.**
  Status: DONE (#266 merged).
  What breaks and why it matters: `import_csv()` validates and writes each row inside the loop
  (`CsvHandler.php:228, 573-577`), so rows 1..N-1 commit before row N fails — no transaction, no
  staging, no partial-import ledger, no dry-run parameter at all. Plus `detect_loop()` reports
  `inconclusive` whenever a regex is involved (`Validator.php:263-266`) and the CSV path
  hard-errors those rows, while the admin form has an `rk_force_unverified` override the CSV
  path lacks. Symptom from the audit: one prefix rule `/blog` makes all 500 ordinary imports
  under `/blog` fail with "chain could not be fully verified", and the only recovery is
  deleting the pattern rule the operator was using. Evidence on main: no dry-run/transaction/
  force strings anywhere in `CsvHandler.php` (verified by grep).
  Fix steps: (1) add a dry-run mode that validates everything and writes nothing, reporting per-
  row results; (2) make real imports all-or-nothing (transaction or stage-then-commit with a
  partial ledger); (3) add the force override for `inconclusive` chains, same name and semantics
  as the form's `rk_force_unverified` so operators have one mental model.
  Tests required: dry-run writes zero rows and reports per-row verdicts; a 500-row import with
  one bad row at position 400 leaves zero partial rows; force override imports an
  unverifiable-but-intended chain with an explicit flag recorded. Mutation-prove by removing the
  transaction boundary.
- [ ] **M-8 (Med) — SlugWatcher covers only post/page; CPT renames 404 with no redirect.**
  Status: TODO.
  What breaks and why it matters: `TYPES = ['post','page']` in `SlugWatcher.php:31`, so on a
  CPT-only site (like the friend's — projects/docs are all CPT) renaming any URL creates no
  redirect and the old URL 404s. The feature silently does nothing for the site's entire content.
  Fix steps: cover all public post types (query `get_post_types(['public' => true])` at runtime
  rather than hardcoding, so future CPTs work too). Do with H-1 (same file, same PR).
  Tests required: CPT slug rename creates the exact-rule redirect; renaming back cleans up.

### Phase A3 — metadata / SEO output correctness (do together, one area)

- [ ] **C-3 (Critical) — homepage emits `og:type=article` + article dates.**
  Status: TODO.
  What breaks and why it matters: `Context.php:218` tests `is_singular()` before `:238`
  tests `is_front_page()`, and on a static front page both are true — so `/` emits
  `og:type=article` plus `article:published_time` and `WebPage.datePublished`. Facebook,
  LinkedIn and X unfurl the homepage as a blog post; Google reads conflicting type signals
  for the site root. A plain page (`/about/`) is affected the same way.
  Evidence on main: the ordering above; Yoast/Rank Math emit `website` with no `article:*`.
  Fix steps: test `is_front_page()`/`is_home()` BEFORE `is_singular()` in `queriedType()` —
  one ordering fix. Do NOT touch anything else in the function.
  Tests required: front page type is `home`, output has `og:type=website` and zero `article:*`
  tags; single post still emits `article` with dates. Mutation-prove by restoring the order.
- [ ] **C-4 (Critical) — post-type archives get title = bare site name, all identical.**
  Status: TODO.
  What breaks and why it matters: `Context::title()` (`:537`) branches on term and author
  archives only. `queriedType()` knows CPT archives (`permalink()` even handles them), but the
  title path falls through to `get_the_title($id)`/`single_post_title()` and then `''`, so the
  head layer prints just `%%sitename%%` — every archive shares one keyword-less title and reads
  as near-duplicates. Audit saw `/projects/` titled with the bare site name while its H1 is
  "Work", and `CollectionPage.name` likewise wrong.
  Evidence on main: `title()` body has term + author branches only.
  Fix steps: add an archive branch mirroring `permalink()` — CPT archive resolves via
  `get_post_type_object($pt)->labels->name` (reuse `TrailBuilder::cptArchiveItem()`), date
  archive via the archive title. Keep the empty-title→sitename fallback downstream untouched.
  Tests required: CPT archive title carries the archive name; date archive likewise; term/author
  paths unchanged.
- [ ] **H-4 (High) — no meta description on front page / posts index; tagline never read.**
  Status: TODO.
  What breaks and why it matters: `resolveDescription()` ends at `return ''`, and grep shows
  zero `blogdescription` reads anywhere in `src/`. The homepage — the highest-CTR URL — ships
  no description tag even when the tagline is a perfectly good string. Do with C-3/C-4.
  Fix steps: tagline fallback when no excerpt/content description exists, front page and posts
  index first. Tests: homepage with empty content emits the tagline.
- [ ] **H-5 (High) — homepage title uses the meaningless CMS page title ("Home – brand").**
  Status: TODO.
  What breaks and why it matters: `%%title%%` resolves through `get_the_title(67)` — the
  literal page title "Home", which the author never wrote as a headline. No `%%home_title%%`
  token exists in `SUPPORTED_TOKENS`. Every leading plugin drops `%%sitename%%`-style handling
  on the front page instead.
  Fix steps: add a front-page title template (owner to confirm wording — propose "sitename –
  tagline" default, filterable). Tests: front page title no longer contains the CMS page title.
- [ ] **H-6 (High) — homepage emits a self-referential 2-item BreadcrumbList.**
  Status: DONE (#266 merged).
  What breaks and why it matters: `TrailBuilder::buildFrontPage()` correctly returns `[]`
  (hide-on-front default), but `filterBreadcrumbTrail()` (`BreadcrumbsModule.php:332-333`)
  discards the legitimately empty trail and returns `sanitizeIncoming()` fallback — two ListItems
  pointing at the site root and "Home". The setting is dead for every context with an empty
  trail, contradicting `BreadcrumbPiece`'s own docblock.
  Fix steps: honor legitimately empty trails — emit no `BreadcrumbList` at all when the trail is
  empty (do NOT invent a different fallback). Tests: front page emits zero breadcrumb markup;
  inner pages unchanged.
- [ ] **H-9 (High) — Article nodes ship with no author and no image.**
  Status: TODO (needs live data to fully prove; code shape matches the claim).
  What breaks and why it matters: on sites where `post_author=0`, `PersonPiece::isNeeded()`
  returns false, a `#author` ref is manufactured that resolves to nothing, and
  `pruneDanglingRefs()` drops it silently — the plugin emits a node it knows is incomplete
  (both fields required for Article). Fix steps: stop emitting incomplete nodes (omit the
  `author`/`image` keys the data cannot support rather than emitting dangling refs), AND treat
  the `post_author=0` site data as a data-cleanup task on staging (reassign to a real author).
  Tests: authorless post yields Article without dangling refs; normal post unchanged.
- [ ] **M-4 + M-5 (Med) — twitter card hardcoded large-image with no image; URL-only default
  image silently ignored.**
  Status: TODO.
  What breaks and why it matters: `twitter:card` falls back to `summary_large_image`
  (`HeadRenderer.php:778-785`) while the site has zero `og:image`/`twitter:image` — a large
  player card with no image. Separately the social default image requires an attachment ID too,
  so a plain URL is ignored (`Context.php:828-841`).
  Fix steps: degrade `twitter:card` to `summary` whenever no image resolves; accept a URL-only
  default image. Tests for both branches.

### Phase A4 — sitemaps (do together, one area)

- [ ] **H-7 (High) — authors sitemap advertised in the index but serves an empty urlset.**
  Status: DONE (#266 merged).
  What breaks and why it matters: `getCount()` runs `COUNT(DISTINCT post_author)` with no
  `post_author <> 0` filter, so a site full of author-0 rows reports 1 and the set is admitted
  to the index — then `getEntries()` discards row 0 and returns nothing. Live result: HTTP 200
  with an empty `<urlset>`, advertised from the index. Crawlers learn the index lies.
  Evidence on main: `AuthorsProvider.php:102` count vs `:190-192` entries;
  `authorExclusionClauses()` filters only configured exclusion IDs, never 0 itself.
  Fix steps: exclude `0` in the count query (same predicate as entries, single source — extract
  a shared `author > 0` clause both use). Tests: all-zero-author dataset yields no authors set
  in the index at all; mixed dataset counts exactly the real authors.
- [ ] **H-8 (High) — every sitemap index `lastmod` is "now".**
  Status: TODO.
  What breaks and why it matters: `IndexBuilder.php:330` stamps
  `current_time('mysql', true)` inside the per-sitemap loop, so all index entries carry today
  while children carry real dates. Crawlers are told every file changed on every edit — the
  exact pattern Google warns devalues `lastmod`.
  Fix steps: real per-file lastmod (max child lastmod per set, already available from the
  providers). Tests: index lastmods match child maxima, stable across unrelated edits.
- [ ] **H-10 (High) — sitemap cache never expires and deletions never invalidate it.**
  Status: TODO.
  What breaks and why it matters: `wp_cache_set(..., 0)` + `set_transient(..., 0)` (TTL 0 =
  never expires) and the hook list (`SitemapCache.php:262-272`) has `save_post`, terms, users,
  settings — but no `deleted_post` (WordPress does NOT fire `save_post` on delete). Trashed and
  force-deleted posts keep appearing in the XML indefinitely.
  Fix steps: register `deleted_post` (and `trashed_post` if the team agrees — same handler),
  plus a bounded TTL (propose 24h; state the chosen value in the PR) so no stale payload is
  immortal. Tests: delete flow invalidates; TTL constant asserted.
- [ ] **H-12 (High) — sitemap/redirect cache keys carry no blog id.**
  Status: TODO.
  What breaks and why it matters: `cacheKey()` returns `'xml_' . $set . '_' . $page` with no
  `$wpdb->prefix` and no `get_current_blog_id()`; `LogTable::name()` DOES key by prefix, so the
  omission is an oversight. On a multisite network sharing one Redis/object cache, sites collide
  on identical keys and serve each other's XML. Same shape in
  `RedirectCache::cacheKey()` (`rule_` + sha of path only).
  Fix steps: prefix both key builders with the blog id (and keep the table-prefix behavior
  where it exists). Tests: different blog ids produce different keys; same blog stable.
- [ ] **H-13 (High) — `do_blocks()` executes per post on every sitemap build.**
  Status: TODO.
  What breaks and why it matters: `PostsProvider.php:314` runs the full block renderer to
  extract images — measured ~0.6ms/post, ~0.6s per 1000-post page, far worse with query loops
  or expensive shortcodes, and every publish invalidates all cached pages so it re-runs often.
  Fix steps: extract image URLs without executing block callbacks (parse block markup/attrs
  directly; fall back to rendered only where unavoidable and document where). Tests: output
  image set identical before/after on fixture content; add a perf assertion if the harness
  allows, else record timings in the PR.
- [ ] **H-21 (High) — sitemap toggle off/on never invalidates the sitemap cache.**
  Status: TODO.
  What breaks and why it matters: both toggle paths call only `flush_rewrite_rules(false)`.
  The invalidation hooks register only while the module is ON, so while off nothing bumps the
  validator and the version valve is already satisfied. Sequence: toggle off → publish/delete →
  toggle on → the pre-deletion XML is served indefinitely.
  Fix steps: call `SitemapCache::invalidateAll()` (or the equivalent entry point that exists
  then) from both toggle paths on ANY sitemap-module state change. Tests: off → content change
  → on → fresh XML (no stale URLs).

### Phase A5 — ours + leftovers (office PC)

- [ ] **M-3 (Med) — OUR support rate limiter is dead on object-cache sites. Own it.**
  Status: TODO.
  What breaks and why it matters: `SupportDelivery::retryAfter()` reads
  `_transient_timeout_<key>` from `wp_options`, which a persistent object cache never writes —
  TTL reads 0, the limiter never engages. Admitted honestly; the friend's Redis deployment
  proves it.
  Fix steps: store the expiry INSIDE the transient value (array with count + expires) instead
  of relying on the options-table timeout row; keep the `time()` call (never
  `current_time('timestamp')` — phpcbf rewrites it, see gotchas). Update
  `tests/Unit/SupportDeliveryTest.php` + `SupportPageTest.php` stubs accordingly. Tests:
  rate-cap behavior with the options-table timeout row ABSENT (the Redis shape).
  Mutation-prove the cap as before.
- [ ] **M-2 (Med) — runtime ReDoS guard strictly weaker than save-time guard.**
  Status: TODO. Six of ten catastrophic shapes pass `Matcher::is_catastrophic_pattern()` but
  are refused by `RegexSafety::isUnsafe()`; only `pcre.backtrack_limit` contains it today.
  Fix: align the two predicates and lock them together with the differential test the audit
  describes (each shape must agree).
- [ ] **M-12 (Med) — sitemap pagination unstable under LIMIT/OFFSET + invalidate-per-save.**
  Status: TODO. URLs migrate between pages on every edit. Fix: stable ordering (and keyset
  pagination if the team agrees), or document the approximation explicitly. Do with Phase A4.
- [ ] **M-13 + M-14 (Med) — 404 insert race loses counts; FloodGuard double-writes + dead option.**
  Status: TODO. The read-modify-write insert loses hits under concurrency; the budget check is
  non-atomic, writes Redis AND wp_options on the hot path, and maintains
  `rankkernel_404_suppressed`, which has zero readers. Fix: atomic increments, single store,
  delete the dead option (with an uninstall-cleanup note if it may already exist on sites).
- [ ] **M-15 (Med) — two analysis checks contradict on the same keyword.**
  Status: TODO (`Analyzer.php:486-503`: word-boundary vs raw `mb_strpos`). Fix: ONE boundary
  definition shared by both checks + regression test with the audit's repro.
- [ ] **M-18 (Med) — .htaccess save non-atomic, backups collide within one second.**
  Status: TODO. "Restore on failure" can restore already-corrupt content. Fix: `LOCK_EX` +
  temp-then-rename, unique backup names. NOTE: PART B owns backup *location/pruning* (M-1) —
  same file family, different aspects; rebase before PR, do not merge each other's hunks.
- [ ] **M-19 (Med) — network activation seeds nothing on subsites.** Status: TODO. Fix:
  `wp_initialize_site` hook seeding defaults + tables per site.
- [ ] **M-20 (Med) — CSV export writes formula-guard chars at storage time.** Status: TODO.
  (`CsvHandler.php:364-372`.) Fix: apply the anti-formula rule at EXPORT, store raw values.
- [ ] **M-22 (Med) — attachment N+1 in sitemap providers.** Status: TODO. `_prime_post_caches`
  primes posts, not the attachment IDs they point at. Fix: prime attachment IDs too.
- [ ] **M-24 (Med) — zero RTL support.** Status: TODO (empty grep for `is_rtl`/`[dir=`).
  Fix: RTL pass over the 10 stylesheets with directional declarations; test with an RTL locale.
- [ ] **M-25 (Med) — translations never load.** Status: TODO (`Domain Path` inert without
  `load_plugin_textdomain()`; zero `.pot` for 1,368 strings). Fix: loader call + generate and
  ship the `.pot`.
- [ ] **M-6 + M-7 (Med) — og:url ignores canonical override; pagination canonical.**
  Status: TODO. Fix: honor the per-post canonical in `og:url`; paginated archives
  self-canonical with their page number.
- [ ] **L-1..L-5 triage (Low).** Status: TODO. Read each (dead Redis keys in HitCounter,
  uncapped autoloaded keys in Sitemap/Breadcrumb settings, unbounded LIKE scan in KeywordIndex,
  network activation gaps, silent `0.0.0` version) and either fix cheaply in one batch PR or
  record each as accepted-with-reason. No silent drops — every L item ends DONE with evidence
  or ACCEPTED with a written reason.

## PART B — server agent (personal PC)

- [ ] **C-6 (Critical) — readme lies about data sent to the author; blocks wp.org submission.**
  Status: TODO. Do this one early.
  What breaks and why it matters: `readme.txt:43` states "RankKernel stores its data on your
  own site. Nothing is sent to the plugin author." But the Support screen emails every request
  — subject, message, reply address, always-on site diagnostics (plugin/WP/PHP versions, theme,
  site URL, multisite flag), optional screenshots — to a Gmail inbox
  (`SupportRequest::RECIPIENT`), with a Reply-To to the user. IndexNow disclosure exists
  (`readme.txt:49`); the support email is disclosed nowhere, and there is no External Service
  section. The audit flags Detailed Guideline #9 (dishonesty) and #7 (undocumented external
  contact). A reviewer reading the readme concludes the plugin is hermetic. It is not.
  Evidence on main: `readme.txt:41-49`; `SupportRequest.php:27`;
  `SupportDelivery.php:send()` always attaches `SupportRequest::diagnostics()`.
  Fix steps: (1) rewrite the Privacy section: name the recipient address, list exactly what
  travels (message fields, diagnostics keys, optional screenshots), state the Reply-To behavior
  and that sending happens only on explicit Send; (2) delete the false "Nothing is sent" sentence
  or scope it truthfully to non-support operation; (3) add the External Service section naming
  the support inbox AND the existing IndexNow disclosure side by side; (4) touch nothing else
  in readme.txt (wordpress.org parses its headers strictly — keep `==` sections and order).
  Tests required: none executable — proof is the rendered diff; run `composer lint` anyway since
  the repo lints everything. Accept when: a reviewer can learn the full data flow from the
  readme alone.
- [ ] **H-22 (High) — physical llms.txt in the webroot can never be deleted.**
  Status: TODO.
  What breaks and why it matters: `LlmsFileWriter::write()` refuses to overwrite and there is
  NO `delete()`/`unlink()` anywhere in the writer. Worse, the shadowing warning is registered
  inside the `if (enabled)` branch, so it stops warning the moment the feature is disabled —
  exactly when the stale file matters most. On LiteSpeed (and any static-first stack) the
  webserver serves the leftover file ahead of the virtual route, so `/llms.txt` keeps returning
  the disabled document indefinitely. `uninstall.php` touches no filesystem paths either.
  Fix steps: (1) add a `delete()` to the writer (unlink + clear caches, return bool, never fatal
  on missing file); (2) call it when the feature is disabled AND surface the shadowing warning
  regardless of enabled state; (3) delete on uninstall (extend `uninstall.php`'s filesystem
  handling, which currently has none); (4) keep write()'s refuse-to-overwrite behavior.
  Tests required: disable removes the file; uninstall removes it; missing-file delete returns
  cleanly. Mutation-prove the disable path.
- [ ] **H-23 (High) — deactivation leaves rewrite rules and .htaccess blocks behind.**
  Status: TODO.
  What breaks and why it matters: `rankkernel_deactivate()` (`rankkernel.php:148-151`) is a
  deliberate no-op ("leave all data"), but rewrite rules are not data — `SitemapsModule` and
  `RobotsModule` flush rules on boot, so after deactivation the submitted sitemap URLs hard-404
  instead of cleanly disappearing, and managed `.htaccess` blocks persist with no owner.
  Evidence on main: the empty deactivator; boot-time flushes at `SitemapsModule.php:188`,
  `RobotsModule.php:324`.
  Fix steps: (1) flush rewrite rules on deactivation; (2) decide with the owner first whether
  managed `.htaccess` blocks are removed or left with a notice — do NOT silently strip a
  security-relevant file without his call (ask in the issue if unsure); (3) document whatever
  is intentionally left behind in the readme/FAQ. Tests: option-level test that deactivation
  flushes (mock the flush), plus a note of what stays and why.
- [ ] **H-11 + M-17 + M-21-prune (High/Med) — schedule the 404 prune; the retention promise is
  currently unenforceable.**
  Status: TODO.
  What breaks and why it matters: zero `wp_schedule_event`/`wp_next_scheduled` calls anywhere
  in `src/` or `rankkernel.php` (verified). `Pruner::prune()` is reachable ONLY from
  `Logger.php:309-318`, on `shutdown` after a brand-new distinct 404 row inserts — so
  `retention_days`/`max_rows` are never enforced on a site that stops receiving new distinct
  404s, directly contradicting the readme's "does not grow without limit". Related rot in the
  same area: the prune runs two full `COUNT(*)` scans per new insert (M-17), and the sitemap
  toggle path skips cache invalidation (M-21 is PART A's sitemap item — do NOT touch the
  sitemap side; this item is prune-only).
  Fix steps: (1) schedule a daily prune event with `wp_next_scheduled` idempotency guard;
  (2) register on activation, clear on deactivation (ties into H-23's deactivation work —
  coordinate, don't duplicate); (3) fold the two `COUNT(*)` scans into the delete queries'
  affected-rows or a single cheap check; (4) keep the shutdown-after-insert prune as a
  backstop, not the only path.
  Tests required: event scheduled once (double-activation still one event); deactivation clears
  it; prune enforces both limits on fixture data without new inserts.
- [ ] **M-1 (Med) — .htaccess backups accumulate unbounded in the webroot and are
  web-fetchable.**
  Status: TODO.
  What breaks and why it matters: `src/Admin/HtaccessFile.php:180-181` writes timestamped
  backups (`...rankkernel-backup-<ts>`) next to the live file on every save, never prunes, and
  the audit verified the backup pattern is fetchable while `.htaccess` itself 403s — config
  contents leak over HTTP. NOTE the split with PART A: M-18 (atomic save + unique names) is
  office-PC; THIS item is location + pruning only. Rebase before PR; do not merge each other's
  hunks.
  Fix steps: (1) move backups out of the document root (propose `wp-content/uploads/` guarded
  dir or `wp-content/` private location — state the choice in the PR); (2) prune to the last N
  (propose 5); (3) verify the live `.htaccess` deny posture unchanged.
  Tests required: save thrice → ≤N backups, all outside docroot; old ones pruned.
- [ ] **H-20 (High) — IndexNow auto-submit blocks every post save up to 10 seconds.**
  Status: TODO.
  What breaks and why it matters: `transition_post_status` → synchronous `wp_remote_post`
  with `blocking => true`, `TIMEOUT = 10` (`IndexNowClient.php:37,225-232`), firing on ANY
  publish transition including publish→publish — every re-save, quick-edit, `wp_update_post()`,
  `wp import` stalls inline. Timeouts are logged inline as transient. Separately, 403 is
  classified permanent (`PERMANENT_CODES` includes it) but 403 is exactly what IndexNow returns
  for an unreachable key file — a common transient misconfiguration — and the log offers no
  retry ("Rejected", dead end).
  Fix steps: (1) defer submission out of the save path (cron event or shutdown dispatch —
  propose in the issue, don't silently downgrade delivery guarantees); never drop submissions,
  only defer; (2) reclassify 403 as transient with a retry affordance in the log UI;
  (3) publish→publish re-saves must not resubmit unchanged URLs (dedupe on URL+status).
  Tests required: save returns without HTTP performed inline (deferred job queued); 403 path
  retries; unchanged republish submits nothing.
- [ ] **UX batch (High→Low) — one PR per severity cluster, not one giant PR.**
  Status: TODO.
  Items and evidence (section ADMIN UX of the audit): UX-1 — Instant Indexing Clear Log has no
  consequence confirm (`Views/instant-indexing.php:266`; every other destructive action uses
  `data-rk-confirm` — copy that pattern); UX-5 — every Save button on the sitemap page renders
  a `download` glyph (`sitemap-settings.php:119,241,308,373,453`) — pick the correct glyph from
  the SHIPPED subset only (a JS suite test decodes the woff2 and fails unknown ligatures);
  UX-7 — settings back-button forces `location.reload()` (`settings-admin.js:416-418`) losing
  in-place section state — restore the section client-side on popstate instead; UX-2 — metadata
  modal fallback dialog has `role=dialog`/`aria-modal` but no focus trap, no Escape, focus never
  returns (`metadata-sidebar.js:1582`) — add all three; UX-3/UX-4 — sub-token-floor font sizes
  and off-scale spacing (do NOT delete visual rhythm: extend the `--rk-*` scale to cover the
  needed steps, then migrate declarations onto it); UX-6 — disabled search/Quick Actions explain
  themselves only on hover — give them visible affordances; UX-8 — IndexNow preview rows are
  aria-label-only — add visible labels so sighted users can't mistake them for real submissions.
  Tests required: the repo's JS vm-harness tests for behavior; existing icon/a11y suites must
  stay green (sub-11px text and new glyphs are both tripwires — check the suites, don't assume).
- [ ] **QA-4 + QA-5 (Med) — hardcoded schema preview; social fields with no UI.**
  Status: TODO.
  QA-4: the Schema "JSON-LD Preview" is hardcoded `https://example.com` markup
  (`Views/schema-settings.php:535-552`) — unparseable against the real site and disconnected
  from its identity. Fix: render the preview from the LIVE graph (same builder the frontend
  emits), pretty-printed; empty-state copy when nothing is configured.
  QA-5: `social_facebook/twitter/instagram/linkedin/youtube/pinterest` exist in the option but
  only the default image and X handle are editable. Fix: expose the fields in the UI next to
  the existing social controls — or remove the dead options entirely (ask the owner which;
  do not leave both states half-done).
  Tests required: preview output parses as JSON-LD and matches the frontend graph node-for-node
  on fixture settings; social fields round-trip or are gone with migration cleanup.

## VERIFY-ONLY (no code unless the live check disagrees)

- [ ] **H-15 — VERIFY on staging that the conflict notice is live-accurate.**
  Status: TODO.
  Context: the code on main already re-checks conflicts live on every render, covers
  network-activated plugins, shows on the Plugins screen, and self-deletes the stale option
  (`rankkernel.php:172-227`). The audit's symptom (stale "Rank Math active" forever) should
  already be gone. Verify: install a competitor → notice appears naming it; deactivate →
  notice disappears and the option is cleaned; network-activate on multisite → detected. Mark
  DONE with the three observations, or open a bug issue with the divergent behavior.
- [ ] **QA-8 — VERIFY one real export→import round-trip.**
  Status: TODO.
  Context: both directions use the single shared `HEADER` constant (`CsvHandler.php:35`,
  strict match at `:413`), so our own exports must re-import. Run one real round-trip on
  staging (export 5 rows, re-import the file, confirm 5 rows accepted). Close on success; on
  failure open a bug issue with the exact header mismatch.
- [ ] **H-9 data side — `post_author=0` cleanup on the site.**
  Status: TODO.
  Context: this is site DATA, not plugin code — 142/143 rows attributed to author 0. Reassign
  to a real author on staging (or document why 0 is legitimate there). The code-side half
  (stop emitting incomplete Article nodes) is PART A and proceeds regardless.

## Review checklist (office PC reviews every server PR before owner merges)

1. Branch is `GH-<n>` off latest main, single finding per PR, `Closes #<n>` present.
2. All four gates green in CI **and** reproduced locally for the touched areas.
3. Mutation proof: each new guard deleted → its test fails → restored.
4. No `docs/` changes, no `as any`/`@ts-ignore` equivalents, no icon-subset violations,
   no raw hex in CSS, text domain exact, nonces + capabilities on every write path.
5. No-JS fallbacks preserved where the page had them; `hidden`-attribute + `<noscript>`
   pattern where server pre-hides (see GH-207's tab controller in
   `assets/js/schema-settings.js`).
6. Rebase onto latest main, push, request review; owner merges. Delete the branch after merge
   and tick the item DONE(pr) here (office PC owns tracker ticks — server agents report
   status lines instead, never edit this file).