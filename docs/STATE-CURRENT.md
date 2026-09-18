# RankKernel — CURRENT STATE (durable handoff)

This file is the source of truth for the next OpenCode session. Do not reconstruct project state
from memory. Read this file, then verify git state, then continue from **Next action**.

Last updated: 2026-09-17, at the end of a long GH-27 session.

> **Note on HEAD:** the commit recorded below was HEAD at the moment this handoff was written. This
> handoff was then committed, which advances HEAD by one commit. **Always run `git log --oneline -5`
> first and trust git over this file.**

---

## 1. Current branch and HEAD

- Branch: **GH-27**
- HEAD when this file was written: **`3db2258`** (verify with git; see the note above)
- Pushed to `origin/GH-27`: **yes**
- Base: `main`. GH-27 was branched from `7e100f4` (after PR #26 was merged).

### Commits on GH-27 (newest first)

| Commit | Content |
|---|---|
| `3db2258` | Keep local credentials in an ignored file; tracked tree confirmed clean |
| `7c08288` | Address the CodeRabbit review (mbstring guard, credential redaction, token labels, metabox order, doc fixes) |
| `12585c2` | Preserve owner visual adjustments to the metadata stylesheet |
| `3474987` | Stop an empty max-snippet from blocking the save; resolve tokens |
| `dc6f855` | Clean-room decision log; settle the visual parity question |
| `9f0e4ce` | Resolve template tokens in the preview so raw placeholders never render |
| `ef3e8c0` | Trim the General tab, widen the token selector, rebuild the snippet editor layout |
| `0c97f3c` | Make the snippet editor an editing workspace; complete the social workflow |
| `dee2b7a` | Make the Gutenberg sidebar render; remove the duplicate schema panel |
| `43de144` | Design system tokens, UI, metabox hide, default templates |
| `c66a823` | Four tab sidebar plus matching Classic panel |
| `44e0b1a` | Docs: roadmap 2.7 and parity matrix PARTIAL rows |
| `9e4f684` | JS to view hook contract reconciliation plus contract test |
| `7d2cd60` | Metadata editor WIP (honest record of the JS/view mismatch) |
| `01b8994` | Seven engine fixes from the six module audit |
| `1117dba` | Six module audit (311 rows) plus competitor editor UX studies |

## 2. Working tree status

- **Clean** as of `3db2258`.
- No uncommitted changes. No stashes.

## 3. Current PR and its status

**PR #32 — "GH-27: metadata editor, Gutenberg sidebar, design system and engine hardening"**

- State: **OPEN**, `CLEAN`, `MERGEABLE`
- Files: 42 changed, roughly +11,759 / −103
- `Closes #27`

### PR #32 verification status — read this carefully

| Item | Status |
|---|---|
| Final commit | `3db2258` (pushed) |
| Gates | **All green** (see section 6) |
| CodeRabbit | Reported **21 findings on an OLDER commit**. All valid findings were fixed. A fresh re-review of `3db2258` was **requested twice** (`@coderabbitai review`), and **the review timestamp has not advanced**. |
| **CodeRabbit approval** | **MUST NOT be described as CodeRabbit-approved.** The condition "CodeRabbit has no actionable issues on the current commit" is NOT established. |
| Browser verification | **NOT COMPLETED.** See section 4. |
| Merged | **NO** |

## 4. What has NOT been completed (do not claim these)

1. **Browser verification of Social Media Preview → Edit Snippet.** Not executed.
2. **Browser verification of token insertion** (click, insert into the correct field, counter and preview
   update, save, reload, persistence). Not executed.
3. Playwright/MCP **timed out repeatedly** this session (`MCP error -32001: Request timed out`), and twice
   earlier was interrupted by `beforeunload` dialogs and a stale-session drop. Three browser attempts
   total failed at the moment of verification.
4. The **owner has manually confirmed** the main editor functionality works. Automated browser
   verification must still not be falsely claimed.
5. PRs **#33, #34, #35, #36** — not started (correctly deferred; see merge order).
6. PRs **#28, #29, #30** — not reviewed, not closed.
7. **PR #31** — verdict reached, not actioned.

**Strong recommendation for the next session:** restart the Playwright MCP session cleanly before
attempting any browser gate. In this session the runtime check went unperformed across three rounds
while every static gate stayed green, which is exactly how the non-rendering sidebar survived two
earlier review rounds.

## 5. Exact blockers and next action

### Blockers

1. **CodeRabbit will not re-review `3db2258`.** Its timestamp has not advanced across two explicit
   requests. Most likely fix: close and reopen PR #32, or push an empty/re-push commit to wake it.
2. **Browser tooling unusable at the moment of verification.** Restart the Playwright MCP session.
3. **Context window full** in the session that produced this file.

### Exact next action (in order)

1. Read this file, then `git status`, `git log --oneline -3`, `gh pr view 32 --json state,mergeable,headRefOid`.
2. Restart the Playwright MCP session.
3. Perform the two browser verifications in section 4 (Edit Snippet does NOT open the wrong modal;
   token insertion is functional).
4. Wake CodeRabbit on PR #32 (close/reopen or re-push), then inspect its findings on `3db2258`.
5. Fix only valid findings, re-run all gates, push.
6. **Merge PR #32** once the current commit has been reviewed and the browser checks pass.
7. `git fetch origin && git checkout main && git pull origin/main`, verify the merge is present and the
   tree is clean.
8. Continue with the merge order in section 9.

## 6. Verification and gate results (at `3db2258`)

```text
composer lint                        PASS  (exit 0)
composer stan                        PASS  (level 6, no errors)
composer test                        PASS  (1055 tests, 3726 assertions)
vendor/bin/phpcs --standard=phpcs.xml PASS  (exit 0)
node --check assets/js/*.js          PASS  (all editor scripts parse)
git status                           clean
```

Verified in earlier rounds by live browser inspection on post ID 53671 and post ID 78771:

- The sidebar renders: one complementary area, four tabs, correct `aria-selected` and `aria-label`,
  no horizontal clipping.
- The block editor Meta Boxes area contains **no** RankKernel panel (`ScreenGuard::isBlockEditorScreen()`).
- All four tabs render real content; the schema type list is present in the sidebar.
- The Social tab shows Facebook/Twitter switching, image selection and the 1200x630 / 600x315 guidance.
- Edit Snippet opens a workspace titled "Edit Snippet" containing two editable fields
  (`rk-modal-title`, `rk-modal-description`) plus a counter and the device toggle.
- Frontend on a published page: **exactly one** canonical tag, and one robots tag whose content merged
  core's `max-image-preview:large` with RankKernel's directives.

## 7. Important decisions already made

1. **Clean-room, Option A (owner chose this).** Visual parity targets an equivalent user experience
   implemented independently. A 100 percent visually identical clone is out of scope. Competitor JS
   bundles must **not** be read to reproduce layout or styling. Competitor references are permitted in
   exactly three shapes: behavioural observation of a running instance, official vendor documentation,
   and importer key-mapping tables. Recorded in `docs/clean-room-policy.md` **section 9.1 Decision log**.
2. **Do not copy competitor code, CSS, markup, icons, class names or UI wording.** A scan must keep
   finding **zero** for `rm-icon`, `serp-preview`, `rank-math-`, `edfa-d-f` and `rank_math` under
   `assets/` and `src/`. It is currently zero.
3. **One metadata source of truth:** `_rankkernel_meta_data` via `MetaPayload`. Never a second store.
4. **No build toolchain.** No package.json, no npm, no JSX. Hand-written JS against `wp.*` globals,
   matching `src/Modules/Schema/blocks/faq/faq-editor.js`. No Tailwind, no CSS-in-JS, no framework.
5. **Module hard gating:** metadata off means no metabox and no editor assets.
6. **Editor assets never load on the frontend.**
7. **Default global templates:** `%%title%% %%sep%% %%sitename%%` and `%%excerpt%%`, as defaults only so
   a saved setting always wins.
8. **Design system:** `assets/css/rankkernel-admin.css` is the canonical token layer (41 tokens).
   Component stylesheets consume it and contain zero raw hex.
9. **Rank Math on the reference site renders as a META BOX, not a Gutenberg sidebar.** Observed live on
   `10033`. Its preview shows the RESOLVED title and a chars+px readout such as `51 / 60 (445px / 580px)`.
10. **A visual shell is not DONE.** Roadmap and parity matrix rows stay `PARTIAL` until the feature is
    genuinely verified.

## 8. Credentials handling rules

**Rule going forward:** tracked source and tracked docs contain **no credentials**. Credentials live only
in ignored local files.

- Ignored local location: **`.local-env/`**
- `.gitignore` contains: `.local-env/`, `*.local-env`, `local-env.md`
- `.local-env/README.md` explains what belongs there.
- `docs/STATE-gh27.md` points at that file and records no values.

**Never do:** print credentials; put real values in `.env.example`; commit credentials; put credentials
in comments, commit messages, PR descriptions or test fixtures.

### Historical credential exposure

- Credentials were previously committed in tracked documentation. They are now **removed from the
  working tree**, and a scan confirms no literal credential remains.
- They remain **recoverable from old commits `3474987` and `12585c2`**, and one pre-existing credential
  from an earlier session existed in `docs/reviewer-handoff/reviewer-handoff.md`.
- **Do NOT rewrite git history unless the owner explicitly instructs it.** Remediation is the owner's call.
- Scan result at `3db2258`: zero hits for the temporary verification password, the database socket path,
  the database user/password and the root credential. Every remaining `password` match is a legitimate
  WordPress concept ("password protected posts") or an instruction that describes how to obtain a value
  without recording it. `api_key` matches are competitor option names in research documents.

## 9. Open PRs and duplicate/superseded considerations

All currently open PRs:

| PR | Title |
|---|---|
| #28 | Bolt: Memoize non-autoloaded validator options per request |
| #29 | Palette: Add dynamic screen reader announcements for Redirects |
| #30 | Sentinel: Harden CSV import upload validation in RedirectsPage |
| #31 | Auditor: Fix script dependency declaration in SettingsPage |
| #32 | **GH-27: metadata editor, Gutenberg sidebar, design system and engine hardening** |
| #33 | Bolt: memoize Normalizer homePath and RedirectCache validator per request |
| #34 | Palette: Add live screen reader announcements for redirect actions |
| #35 | Sentinel: Enforce upload probe and file type validation on CSV import |
| #36 | Auditor: Fix transient option purge in uninstall.php |

### Duplicate relationships — DO NOT merge both members of a pair

- **#28 and #33** — related memoization work.
- **#29 and #34** — related redirect accessibility announcement work.
- **#30 and #35** — related CSV upload validation work.

The newer PRs (#33, #34, #35) appear to **supersede** the older versions, but this **must be verified by
comparing their diffs and ancestry against the updated `main`** before deciding. Never merge duplicate
functionality twice.

### PR #31 — verdict reached

`assets/js/breadcrumbs-admin.js` is 40 lines of real DOM work (`addEventListener` ×2, `getElementById`,
`querySelectorAll`) and uses **zero `wp.*` globals**. It does **not** use `wp.i18n`, `wp.a11y`, `__()` or
`speak()`. Current code registers it with an empty deps array, which is correct.

**Therefore the added `[ 'wp-i18n', 'wp-a11y' ]` dependencies in PR #31 are unnecessary. Do NOT merge
#31 unless new inspection proves otherwise. Likely action: close it as unnecessary, or reduce to a
no-op.**

This is unlike **#34**, where `wp.a11y.speak()` and `wp.i18n.__()` genuinely are used, so those
dependencies may legitimately be required there.

## 10. Merge order

1. Resolve **PR #32** (browser verification, CodeRabbit re-review, then merge).
2. `git fetch origin`, `git checkout main`, `git pull origin/main`; verify the merge is present and the
   tree is clean.
3. **PR #33** — CodeRabbit has 2 actionable findings: `Normalizer::resetHomePath()` needs a `@return void`
   DocBlock, and the RedirectCache test does not actually prove validator memoization because `set()`
   populates memory before `get()`. The test must genuinely fail if `getValidator()` starts reading the
   option again on the second stored-payload access. Do not fix cosmetically.
4. **PR #34** — CodeRabbit has 3 findings: translation strings must be discoverable by WordPress JS i18n
   extraction; the select-all announcement must say that only redirects on the CURRENT PAGE are
   selected/deselected; and `rankkernel-redirects-admin` needs proper script translation catalog
   registration if the script uses `wp.i18n`. Verify before changing.
5. **PR #35** — CodeRabbit reports **no** actionable findings. Verify `is_uploaded_file()` behaviour,
   `wp_check_filetype()` validation, that valid WordPress uploads still work, that invalid or
   non-uploaded files are rejected, that tests cover the security behaviour, and that no unrelated CSV
   import regression exists.
6. **PR #36** — CodeRabbit raised one valid-looking test-quality concern: the test stub may not adequately
   validate that every intended transient namespace is purged. Strengthen the test so a future
   regression leaving one namespace behind actually fails. **Do not modify production code unless
   inspection proves production behaviour is incorrect.**
7. **Resolve or close PR #31** per section 9.
8. **Handle #28 / #29 / #30 only after** comparing them with #33 / #34 / #35 against the updated `main`.

Never merge several PRs blindly as a batch. Do not work on a later PR while an earlier one is waiting
for its required verification or review.

## 11. Required process for every PR

1. Inspect the actual branch and diff.
2. Compare against current `main`.
3. Read the CodeRabbit findings.
4. Verify whether each finding applies to the current commit.
5. Fix **only** valid issues.
6. Run the project gates: `composer lint`, `composer stan`, `composer test`,
   `vendor/bin/phpcs --standard=phpcs.xml`, plus `node --check` for JS.
7. Do not blindly trust bot recommendations. Jules is an implementation agent; CodeRabbit is a reviewer.
   **Neither is automatically correct.** Actual code → actual behaviour → project tests → static
   analysis → review → merge.
8. Do not claim browser verification unless it actually ran.
9. Do not merge until the required verification is satisfied.
10. Keep the working tree clean.
11. Use `composer stan` as the authoritative PHPStan result. CodeRabbit reports PHPStan as skipped
    because of its own bootstrap configuration limitation; that is not a project failure.

## 12. Important files and paths

**Metadata and head engine**
- `src/Modules/Metadata/MetaPayload.php` — the payload contract, `restSchema()`, `sanitize()`, `nullableInt()`
- `src/Modules/Metadata/Context.php` — per-request context, `queriedObject()`, `isAuthorArchive()`, `termName()`, `authorName()`
- `src/Modules/Metadata/HeadRenderer.php` — single-pass `wp_head` @1; `filterRobots()` most-restrictive merge; unhooks core `rel_canonical`
- `src/Modules/Metadata/TagsReplacer.php` — token resolution with per-context caches

**Admin and editor**
- `assets/js/metadata-sidebar.js` — the Gutenberg sidebar (primary surface; large file)
- `assets/js/metadata-editor.js` — Classic metabox behaviour
- `assets/css/rankkernel-admin.css` — **canonical design token layer**
- `assets/css/metadata-editor.css`, `assets/css/metadata-classic.css` — consume tokens, zero raw hex
- `src/Admin/MetadataBox.php`, `src/Admin/SchemaMetabox.php`, `src/Admin/ScreenGuard.php`
- `src/Admin/Views/metadata-box.php` — Classic view
- `src/Settings/SettingsStore.php` — default templates

**Tests**
- `tests/Unit/MetadataBoxTest.php` — includes the JS to view hook contract test (reads the editor scripts
  from disk and asserts every hook they query exists in the rendered view; verified by mutation)

**Docs**
- **`docs/STATE-CURRENT.md`** — this file, the durable handoff
- `docs/STATE-gh27.md` — longer GH-27 session record (points at `.local-env/` for local values)
- `docs/ROADMAP.md` — central progress tracker, four step sequence
- `docs/competitor-analysis/feature-parity-matrix.md` — 379 rows, verdict vocabulary DONE / PARTIAL /
  PLANNED / MISSING / EXTERNAL / N/A
- `docs/clean-room-policy.md` — section 9.1 is the decision log
- `docs/research/raw/` — the audit and UX evidence base

## 13. Known open defects (not blockers, but real)

1. **`%%title%%` resolved to empty** in the editor preview on post ID 78771 in one earlier round, while
   `%%sep%%` and `%%sitename%%` resolved. A token audit pass addressed it; **not re-verified in a browser**.
2. **Desktop versus Mobile preview may not visibly differ** — measured identical widths once
   (`differ: false`) though `aria-pressed` toggled correctly. The measurement selector may have been
   wrong, so this is **unverified rather than confirmed broken**.
3. The site's own icon and a featured image 404 on the local site, degrading the preview's site identity.
   A site asset issue, not a plugin bug.
4. **Owner-made manual CSS adjustments** are committed in `12585c2`, including one commented-out
   `overflow` rule that governed the snippet editor dialog's internal scrolling. If dialog content ever
   exceeds the viewport, look there first.
