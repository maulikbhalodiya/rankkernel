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
  Status: TODO.
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
  Status: TODO.
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
  Status: TODO.
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
  Status: TODO.
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
  Status: TODO.
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
  Status: TODO.
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
  Status: TODO.
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
  partial ledger);
...[truncated 20887 chars]