# RankKernel — CURRENT STATE (single source of truth)

**Read this first. Do not reconstruct project state from memory or from earlier chat.**
This file supersedes every earlier handoff section. It is self-contained.

---

## 0. How to resume (do this in order)

1. Read this file.
2. Verify git: `git -C <repo> status --porcelain`, `git log --oneline -3`, `git rev-parse --abbrev-ref HEAD`.
3. Verify the site is up: `curl -s -o /dev/null -w '%{http_code}' http://localhost:10043/` (expect 200).
4. Confirm the tree is clean and `main` is `a90fae1`.
5. Continue from section 6 (merge order). **PR #33 is next.**

Repo path: `/home/web-dev-3/Local Sites/wordpress-test-site/app/public/wp-content/plugins/rankkernel`

---

## 1. Project

**RankKernel** — a 100% free, zero-bloat WordPress SEO plugin, built to beat Yoast and Rank Math
architecturally while shipping their paid features for free.

**Locked identifiers (never change):**
name `RankKernel` · slug `rankkernel` · namespace `RankKernel\` · text domain `rankkernel` ·
hook prefix `rankkernel/` · meta `_rankernel_meta_data` (post), `_rankernel_term_data` (term),
`_rankernel_user_prefs` (user) · REST namespace `rankkernel/v1` · options `rankkernel_settings`,
`rankkernel_modules`, `rankkernel_db_version` · version `0.1.0` · PHP 8.1+ · WP 6.5+ ·
`RANKKERNEL_VERSION` in `rankkernel.php`.

**Product rule (non-negotiable):** 100% free forever. No PRO edition, feature gates, upsells, license
checks, telemetry, or artificial limits.

---

## 2. Git state

| Item | Value |
|---|---|
| `main` | **`a90fae1`** = "Merge pull request #32 from maulikbhalodiya/GH-27" |
| PR #32 / GH-27 | **MERGED** (2026-09-18T06:31:38Z). Do not re-open or re-merge. |
| Branch `GH-27` | Merged. Its work is on `main`. |
| Handoff branch | **`docs/state-current-session2`** (this file lives here, pushed) |
| Remotes | `origin` = `git@github-maulik-repo:maulikbhalodiya/rankkernel.git`; `gh` auth = `maulikbhalodiya` |

**Rule from `CONTRIBUTING.md`:** never push directly to `main`. Branch `GH-<n>` from latest `main`,
open a PR, the owner merges. That is why this handoff sits on its own branch.

Merged history on `main` (newest first): `a90fae1` (PR #32), `7b59024`, `4e13db4`, `c592115`,
`68f75e7`, `943003e`, `b98ea9b`, `fccc454`, `70477dd`, `cafe611`, `3db2258`, `7c08288`, `12585c2`,
`3474987`, `dc6f855`, `9f0e4ce`, `ef3e8c0`, `0c97f3c`, `dee2b7a`, `43de144`, `c66a823`, `44e0b1a`,
`9e4f684`, `7d2cd60`, `01b8994`, `1117dba`, then `7e100f4` (PR #26), `606fc95` (PR #24),
`58f22e9` (PR #21), `5f8c2ab` (PR #20), `417d4ff` (PR #19), `bc2b981` (PR #22), `10d4696` (PR #18),
`d16fb10` (PR #17), `0e4686c` (PR #16), `461e138` (PR #14).

Merge convention: **merge commits** (`gh pr merge <n> --merge`), matching `Merge pull request #NN`.

---

## 3. Environment — verified procedures

LocalWP. RankKernel site **`http://localhost:10043`**; Rank Math reference site
**`http://localhost:10033`**.

- **Always check the site is running first.** A down site returns `ERR_CONNECTION_REFUSED` and no
  `100xx` ports are listening. An earlier session misdiagnosed exactly this as "Playwright keeps
  timing out". The owner starts/stops Local, never you.
- **`MCP error -32001: Request timed out` from Playwright** is usually either the site being down or
  a **blocking `beforeunload` dialog**. Fix: `browser_close` then reopen.
- **WordPress version is 7.1.**
- **Local's bundled PHP CLI is broken** here: `error while loading shared libraries: libtidy.so.5deb1`.
  Use **system PHP 8.3** for any PHP one-liners.
- **System PHP has no `mysqli`/`pdo_mysql`**, so wp-cli is unusable. DB access uses Local's bundled
  client: `~/.config/Local/lightning-services/mysql-8.0.35+4/bin/linux/bin/mysql`, with
  `MYSQL_PWD` exported in the env (never `-p` on the command line), via `--protocol=SOCKET`.
- **MySQL socket:** find it with the glob `~/.config/Local/run/*/mysql/mysqld.sock` and match it to the
  site by port and database. Database `local`, table prefix `wp_`, admin user id `1`. Never record the
  socket path itself in a tracked file.
- **Playwright cannot read files** from its sandbox (`require` and dynamic `import` are both blocked).
  To log in without ever printing a credential, stage a file **outside the repo** in the site webroot
  and `fetch()` it same-origin from the page, then delete it.

**Verified browser login procedure** (site must be running):

```
# 1. save the original hash, then swap in a temp portable phpass hash
#    (WordPress 7.1 REJECTS legacy 32-char MD5 hashes — do not use md5)
read -r -s -p 'temp password: ' pw; printf '\n'
HASH=$(printf '%s' "$pw" | php -r 'require "<site>/wp-includes/class-phpass.php";
  $h = new PasswordHash(8, true); echo $h->HashPassword(stream_get_contents(STDIN));')
# expect a 34-char $P$ hash; validate the prefix before writing
# UPDATE wp_users SET user_pass='<hash>' WHERE ID=1;
# 2. swapping user_pass INVALIDATES the current session (wp-login redirects with reauth=1).
#    That is expected. Log in AFTER the swap.
# 3. RESTORE the original hash when finished, and delete every temp file.
```

---

## 4. Credentials — hard rules

- Tracked source and tracked docs contain **no credentials**. Ever.
- `NEVER store passwords, database credentials, root credentials, temporary passwords, socket paths
  containing sensitive/local environment information, or other secrets in tracked repository files.`
- Local-only ignored location: **`.local-env/`** (currently contains only `README.md`).
  `.gitignore` has `.local-env/`, `*.local-env`, `local-env.md`.
- Do NOT print credentials, put real values in `.env.example`, or expose them in comments, commit
  messages, PR descriptions or test fixtures.
- **Historical exposure:** credentials were previously committed in `3474987` and `12585c2` and remain
  recoverable there. `Do NOT rewrite Git history unless explicitly instructed by the owner.`
- At the end of the last session the hash was **fully restored** (verified equal to the original,
  `$wp$2y$`, 63 chars) and all temp files were deleted. The tracked-tree secret scan returns 0.

---

## 5. Completed work (what is already on `main`)

All foundation, Sitemaps, Schema, Redirects, 404 Monitor, Breadcrumbs, the admin views refactor, the
docs relocation, the 379-row parity matrix, and **GH-27** (metadata editor, Gutenberg sidebar, design
system, engine hardening) are merged.

**Bugs fixed and verified in the most recent session (all on `main`):**

1. **The Social tab's "Edit Snippet" did nothing — the real bug behind the old "wrong modal" report.**
   `PreviewModal` was rendered inside `generalPanel()`, so while the Social tab was displayed that
   subtree was unmounted. The click set the remembered modal tab but nothing appeared, and a later
   click on the General button then opened the modal on the **Social** section. Reproduced 3 times.
   Fixed by rendering the modal from `SidebarBody` via a new `previewModal()` (returns `null` while
   closed). Browser verified on post 53671: Social opens the modal in ~150ms on the Social section,
   General opens the General section, and `%%title%%` inserts into `rk-social-title`.
2. **Silent data loss on every sidebar save.** `withMeta()` never emitted `flags`. WordPress applies
   `register_meta`'s `sanitize_callback` to the **submitted value only**, and
   `MetaPayload::sanitize()` seeds `$out = self::defaults()`, so each save reseeded `flags` from its
   defaults and wiped the stored `pillar`, `cornerstone` and `breadcrumb_title`.
   `Breadcrumbs/TrailBuilder.php` reads `flags.breadcrumb_title`, so the loss was user visible.
   Fixed: `withMeta()` now emits `flags` with REST-valid defaults for the three declared properties.
   The Classic editor was never affected because `MetadataBox::handleSave()` merges with the stored
   payload (`$existing` then `mergePosted`).
3. **Schema shape mismatch.** `toRestMeta()` (the boundary `writeMeta()` uses via
   `payload[ META_KEY ] = toRestMeta( next )`) now normalizes `schema` through the existing
   `schemaObject()` helper. **Worth remembering:** this was NOT a save blocker. WordPress
   `rest_is_object()` ends in `return is_array( $maybe_object );`, so an empty array satisfies
   `type: object`. The severity in the bot report was wrong; the type mismatch was real.
4. The SERP preview note now says the preview reflects **draft** values, because `display()` returns
   unsaved local drafts.

Two regression tests were added, **both mutation checked** (they do not match the pre-fix script):
`test_block_editor_payload_sends_flags`, `test_block_editor_payload_normalizes_schema_shape` in
`tests/Unit/MetadataBoxTest.php`.

---

## 6. Merge order and per-PR findings

**OWNER ORDER (updated, follow exactly):**
**#28 → #29 → #30 → #31 → #33 → #34 → #35 → #36**, one at a time, full gates and review each.

Because the OLDER members now come first, after each of #28, #29 and #30 lands, check whether its newer
counterpart (#33, #34, #35 respectively) is now **already in `main` / redundant**, and if so close it as
superseded instead of merging duplicate functionality twice.

### CONFLICT + OVERLAP MATRIX (verified 2026-09-18 against `main` = `a90fae1`)

**Read this before starting. Both #28 and #33 are `CONFLICTING` / `DIRTY` and cannot be merged as-is.**
They branched before `RedirectCache.php` changed on `main`, so each needs a rebase with conflict
resolution first. Do not attempt to merge either directly.

`main` does **not** contain the memoization work: `src/Modules/Redirects/RedirectCache.php` still calls
`get_option( self::VALIDATOR_OPTION, '' )` inline at lines 96, 121, 169 and 207, and `SitemapCache.php`
has no validator memory. So #28 and #33 are genuine improvements, not redundant.

| PR | Status | Files | Unique value | Overlap |
|---|---|---|---|---|
| **#28** | `CONFLICTING` / `DIRTY`; **no reviews and no inline comments exist** | `src/Modules/Redirects/RedirectCache.php`, `src/Modules/Sitemaps/SitemapCache.php`, `tests/Unit/RedirectsCacheTest.php`, `tests/Unit/SitemapCacheTest.php` | **`SitemapCache` validator memoization (only here)** | `RedirectCache` validator memo (also in #33) |
| **#33** | `CONFLICTING` / `DIRTY` | `src/Modules/Redirects/Normalizer.php`, `src/Modules/Redirects/RedirectCache.php`, `tests/Unit/RedirectsCacheTest.php`, `tests/Unit/RedirectsNormalizerTest.php` | **`Normalizer` homePath memoization (only here)** | `RedirectCache` validator memo (also in #28) |
| #29 / #30 / #31 | `UNKNOWN` when last checked (GitHub still recomputing); re-check with `gh pr view <n> --json mergeable,mergeStateStatus` | | | |

**Never merge duplicate functionality twice.** Because #28 has unique `SitemapCache` work and #33 has
unique `Normalizer` work, the intended path is:

1. Rebase **#28** onto `main`, resolve the `RedirectCache.php` conflicts, run gates, review, merge. That
   lands both `RedirectCache` and `SitemapCache` memoization.
2. **#33** then collapses to `Normalizer` homePath memoization only. Either reduce it to that, or close
   it and open a clean `GH-<n>` branch with just the `Normalizer` change and its test. Carry over the
   `@return void` DocBlock fix for `resetHomePath()` from the finding listed above.
3. #29 and #30 map onto #34 and #35 the same way. Re-check each diff against `main` after every merge,
   and close whichever member becomes redundant.

### PR #28 rebase — exact findings (already attempted once)

Rebasing `origin/bolt/memoize-validator-options-8283178016137129664` onto `main`:
- **Only ONE conflict:** `tests/Unit/RedirectsCacheTest.php`. `src/Modules/Redirects/RedirectCache.php`
  and `src/Modules/Sitemaps/SitemapCache.php` **auto-merge cleanly**. So this is a small job.
- The conflict shape: `main` has two tests there
  (`test_stored_rule_served_from_cache_without_new_database_read`,
  `test_saving_updating_and_deleting_a_rule_invalidates_the_cache`) and the PR adds
  `test_validator_is_cached_in_memory_per_request`. **Keep all three.** The `/**` opener before the
  first marker belongs to `main`'s first test, so the PR's test needs its own `/**` opener and its own
  closing brace. The shared trailing `}` and `}` close the two functions and the class.
- **The PR's added test is BROKEN and must be rewritten, not just merged.** It does
  `$this->options[ RedirectCache::VALIDATOR_OPTION ] = 'mutated_validator_in_db';` but that class has
  **no `$options` property and no `get_option` stub**. It would create a dynamic property and the test
  would pass trivially without proving anything. This is the same weakness CodeRabbit flagged on #33.
- **The real test idiom in this repo is Brain\Monkey.** `setUp()` calls `parent::setUp()` then
  `\Brain\Monkey\setUp()`, and each test stubs WordPress functions itself, e.g.
  `Functions\when( 'get_option' )->justReturn( '' );` or
  `Functions\when( 'get_option' )->alias( static function ( string $key, mixed $value ) use ( &$options ): bool { ... } );`.
  `tests/Unit/SitemapCacheTest.php` lines ~26, ~56, ~127, ~210, ~252 and ~256-294 show the patterns.
- **Correct test for this change:** stub `get_option` with a closure that increments a counter for
  `RedirectCache::VALIDATOR_OPTION`, then assert that several `get()`, `set()` and `getPatterns()`
  calls read it only **once** per instance, and that `invalidate()` clears the memo so the next call
  reads again. That genuinely fails if `getValidator()` is removed. Do the same for `SitemapCache`.
- **Trap to avoid:** if you resolve the conflict with a script, include the lines **before** the first
  `<<<<<<<` marker. Dropping them deletes the `<?php`, the namespace, the class declaration and every
  earlier test. If a resolution goes wrong, `git checkout -m <path>` recreates the conflict markers
  during a rebase; `git rebase --abort` returns to a clean branch.

**PR #28 CodeRabbit status:** no reviews and no inline comments exist on it, so there are no findings to
action. Only treat the review requirement as met once a review actually runs, otherwise say plainly in
the merge decision that none was posted.

Process one PR at a time. **Never work on a later PR while an earlier one is waiting for its required
verification.** After every merge: `git fetch origin`, `git checkout main`, `git pull origin/main`,
verify the tree is clean.

1. ~~PR #32 / GH-27~~ — **DONE, merged as `a90fae1`.**
2. Local `main` pulled — **DONE.**
3. **PR #33 — NEXT.** Branch `bolt/memoize-normalizer-and-redirect-cache-13821019644011060084`,
   head `a8340e5f`. Title: "Bolt: memoize Normalizer homePath and RedirectCache validator per request".
   CodeRabbit has **2 actionable findings**:
   - `Normalizer::resetHomePath()` needs the required `@return void` DocBlock.
   - The RedirectCache test does **not** actually prove validator memoization, because `set()`
     populates memory before `get()` does. Make the test genuinely fail if `getValidator()` starts
     reading the option again on the second stored-payload access. Do not fix it cosmetically.
4. **PR #34.** Branch `palette-a11y-redirects-announcements-77554840381442971`, head `45b69365`.
   **3 findings**: (a) translation strings must be discoverable by WordPress JS i18n extraction;
   (b) the select-all announcement must accurately say only redirects on the **current page** are
   selected/deselected; (c) `rankkernel-redirects-admin` needs proper script translation catalog
   registration if the script uses `wp.i18n`. **Unlike PR #31, `wp.a11y.speak()` and `wp.i18n.__()`
   genuinely ARE used here**, so those dependencies may be legitimately required. Verify before changing.
5. **PR #35.** Branch `fix/sentinel-csv-upload-validation-16009658335559042169`, head `0ab570e4`.
   CodeRabbit reports **no** actionable findings. Verify `is_uploaded_file()`, `wp_check_filetype()`,
   that valid WordPress uploads still work, that invalid/non-uploaded files are rejected, that tests
   cover the security behaviour, and that there is no unrelated CSV import regression.
6. **PR #36.** Branch `auditor/fix-uninstall-transient-purge-4949492321463700969`, head `da87365e`.
   One valid-looking **test-quality** concern: the test stub may not adequately prove every intended
   transient namespace is purged. Strengthen the test so a future regression leaving one namespace
   behind actually fails. **Do not modify production code unless inspection proves it wrong.**
7. **PR #31 — close it.** Branch `auditor/fix-breadcrumbs-script-deps-2157991094099859006`,
   head `c4a75bf9`. `assets/js/breadcrumbs-admin.js` is 40 lines of plain DOM work and uses
   **zero `wp.*` globals**: no `wp.i18n`, no `wp.a11y`, no `__()`, no `speak()`. The current empty
   deps array is correct, so the added `[ 'wp-i18n', 'wp-a11y' ]` is unnecessary. `Do not merge PR #31
   merely because Jules created it.` Close it or reduce it to a no-op.

**Duplicate pairs — never merge both members:**
- #28 and #33 (memoization). #29 and #34 (redirect a11y announcements). #30 and #35 (CSV upload
  validation). The newer ones (#33/#34/#35) appear to **supersede** the older, but this must be
  verified by comparing diffs and ancestry against the updated `main` before deciding.
- Older counterparts still open: #28 `bolt/memoize-validator-options-8283178016137129664`,
  #29 `palette/redirects-a11y-announcements-2884022391265141377`,
  #30 `sentinel/harden-csv-import-upload-validation-11326822520635758999`.

---

## 7. Review tooling and agent config

- **CodeRabbit now excludes `docs/`.** `.coderabbit.yaml` has `path_filters` including
  `"!docs/**"` (with a one-line comment explaining it). Owner instruction: `docs/` is internal working
  material, not shipped plugin code, and must not consume review cycles.
- **`AGENTS.md` exists at the repo root** so automated agents, **including Jules**, treat `docs/` as
  out of scope, and so they use the correct gates and the per-file `node --check` form.
- `readme.txt` keeps its existing `path_instructions`; only `docs/` is excluded.
- **Bot reviews are not automatically correct.** For every finding: inspect the actual code, decide
  whether it is valid, fix only valid issues, skip the rest with a brief stated reason, then test and
  re-review. Two concrete examples from the last session: a Major "save blocker" claim that was
  factually wrong (`rest_is_object` accepts arrays), and a genuine Major data-loss bug the bot found
  correctly. Verify both ways.

---

## 8. Gates

```bash
composer lint                          # phpcs, WordPress ruleset
composer stan                          # PHPStan level 6, src/
composer test                          # PHPUnit
vendor/bin/phpcs --standard=phpcs.xml
for f in assets/js/*.js; do node --check "$f"; done   # one file per run
git diff --check
```

Last full result on `main` before the merge: lint 0, PHPStan level 6 clean,
**1057 tests / 3727 assertions**, phpcs 0, `node --check` clean, `git diff --check` clean.

`composer stan` is authoritative. CodeRabbit reports PHPStan as skipped because of its own bootstrap
limitation; that is not a project failure.

**Report real command output. Never claim a gate passed if it did not run. Never claim browser
verification unless it actually ran.**

---

## 9. Open defect needing an owner decision (not yet filed)

On **Classic Editor** screens the schema UI is **duplicated**. `SchemaMetabox::addBoxes()` returns
early only for `ScreenGuard::isBlockEditorScreen()`, so on Classic screens it registers
`rankernel-schema`, while `MetadataBox`'s Classic view (`src/Admin/Views/metadata-box.php`) **also**
renders schema controls: `rankernel-meta-schema-type`, `rankernel_schema_disabled`, the manual field
row loop, `rankernel_schema_custom`, and `rankernel_schema_import`. Both register when the metadata
module is on (the default, see `src/Plugin.php` around lines 145 and 152).

This needs a **product decision** about which surface owns schema on Classic screens (the
`SchemaMetabox` is the fuller builder with export; the metadata box's Schema tab is a slimmer override
panel), so it was deliberately not changed. **File it as an issue, then implement the owner's choice.**

---

## 10. Locked decisions

1. **Clean-room, Option A** (`docs/clean-room-policy.md` section 9.1). Visual parity means an
   equivalent UX implemented independently, not a 100% clone. Do **not** read competitor JS bundles
   (e.g. `rank-math-app.js`) to reproduce layout or styling. Permitted references only: behavioural
   observation of a running instance, official vendor docs, and importer key-mapping tables.
2. **No competitor code, CSS, markup, icons, class names or UI wording.** A scan under `assets/` and
   `src/` must stay at zero for `rm-icon`, `serp-preview`, `rank-math-`, `edfa-d-f`, `rank_math`.
3. **One metadata source of truth:** `_rankernel_meta_data` via `MetaPayload`. Never a second store.
4. **No build toolchain.** No `package.json`, npm, JSX, Tailwind or CSS-in-JS. Hand-written JS against
   `wp.*` globals, matching `src/Modules/Schema/blocks/faq/faq-editor.js`.
5. **Module hard gating:** metadata off means no metabox and no editor assets.
6. **Editor assets never load on the frontend.**
7. **Default templates** (`SettingsStore::defaults()`, ~line 71): title `%%title%% %%sep%% %%sitename%%`,
   description `%%excerpt%%`, as defaults only so a saved setting always wins.
8. **Design system:** `assets/css/rankkernel-admin.css` is the canonical token layer. Component
   stylesheets consume tokens and contain zero raw hex.
9. **Rank Math on the reference site renders as a META BOX, not a Gutenberg sidebar** (observed live
   on 10033).
10. **A visual shell is not DONE.** Roadmap and parity-matrix rows stay `PARTIAL` until verified.

---

## 11. Known open defects (real, not blockers)

1. `%%title%%` resolved to empty in the editor preview on post 78771 once, while `%%sep%%` and
   `%%sitename%%` resolved. A token audit pass addressed it; **not re-verified in a browser**.
2. Desktop vs Mobile preview may not visibly differ (measured identical widths once,
   `differ: false`, though `aria-pressed` toggled). The measurement selector may have been wrong, so
   this is **unverified rather than confirmed broken**.
3. The site's own icon and a featured image 404 on the local site, degrading the preview's site
   identity. A site asset issue, not a plugin bug.
4. **Owner-made CSS adjustments** live in `12585c2`, including a commented-out `overflow` rule that
   governed the snippet editor dialog's internal scrolling. If dialog content ever exceeds the
   viewport, look there first.

---

## 12. Key files

**Metadata / head engine:** `src/Modules/Metadata/MetaPayload.php` (`restSchema()` ~734+,
`sanitize()` ~130, `defaults()` ~68, `sanitizeSchema()` ~335, `decodeMetaValue()` ~93),
`Context.php` (`meta()` ~115, archive resolvers ~528-543), `HeadRenderer.php` (single-pass `wp_head`@1,
`filterRobots()` most-restrictive merge, unhooks core `rel_canonical`, `buildRobotsContent()` ~317-371,
canonical ~380-392), `TagsReplacer.php`.

**Admin / editor:** `assets/js/metadata-sidebar.js` (the Gutenberg sidebar; `defaultMeta()` ~425,
`withMeta()` ~440, `schemaObject()` ~530, `toRestMeta()` ~522, `writeMeta()` ~1470,
`previewModal()` ~2090, panel builders ~2068+ and ~2375+, `SidebarBody()` return ~2480),
`assets/js/metadata-editor.js` (Classic), `assets/css/rankkernel-admin.css` (**canonical token layer**),
`assets/css/metadata-editor.css`, `assets/css/metadata-classic.css`,
`src/Admin/MetadataBox.php` (register ~310, `addBoxes()` ~324, `enqueueEditorAssets()` ~393,
`handleSave()` merges with existing), `src/Admin/SchemaMetabox.php` (`addBoxes()` ~262,
`register()` ~244), `src/Admin/ScreenGuard.php` (`isBlockEditorScreen()`),
`src/Admin/Views/metadata-box.php`, `src/Plugin.php` (~135-160 admin wiring), `src/Settings/SettingsStore.php`.

**Tests:** `tests/Unit/MetadataBoxTest.php` (contains the JS-from-disk contract tests, including the
two new mutation-checked guards), `MetaPayloadTest.php`, `TagsReplacerTest.php`, `ContextTest.php`.

**Docs (now excluded from review):** this file; `docs/ROADMAP.md` (central tracker, 4-step sequence);
`docs/competitor-analysis/feature-parity-matrix.md` (379 rows); `docs/clean-room-policy.md` (9.1);
`docs/coding-standards.md`; `docs/research/raw/` (audit evidence);
`docs/STATE-gh27.md` (older session record).

---

## 13. Explicit constraints (verbatim)

- `"100% free"`; no `"PRO edition"`, `"feature gates"`, `"upsells"`, `"license checks"`,
  `"telemetry"`, `"artificial limitations"`.
- `"NEVER store passwords, database credentials, root credentials, temporary passwords, socket paths
  containing sensitive/local environment information, or other secrets in tracked repository files."`
- `"Do NOT rewrite Git history right now unless explicitly instructed by the owner."`
- `"Do NOT merge before CodeRabbit review is complete."` / `"Do not merge PR #31 merely because Jules
  created it."`
- `"Do not claim a test passed unless it actually ran."` / `"Do not claim browser verification if the
  browser/tool was unavailable."`
- `"Do not spend time redesigning GH-27. Do not create unrelated refactors. Do not mix branches."`
- `"SPEED UP THE WORK, BUT DO NOT REDUCE VERIFICATION."`
- `"Do NOT copy competitor source code"`, `"proprietary implementation"`, `"proprietary UI text"`, or
  their internal architecture; clean-room per `docs/clean-room-policy.md`.
- `"Do not work on a later PR while an earlier PR is waiting for its required verification/review."`
- Never start or stop Local services from tooling.
- **`docs/` is out of scope for CodeRabbit and for Jules** (owner instruction, now encoded in
  `.coderabbit.yaml` and `AGENTS.md`).
