# RankKernel Current State

**This file is the single source of truth for where RankKernel is right now.**

It supersedes `docs/STATE-CURRENT.md`, which is stale: that file is 403 lines, references PRs #28 and #33, and predates the #99 to #115 work. Do not reconstruct state from memory, from earlier chat, or from `docs/STATE-CURRENT.md`. If any document disagrees with this one, verify against the repository and treat the repository as authoritative.

Everything below was verified against `origin/main` and the live GitHub API on **2026-09-28**. Anything that could not be verified is marked `UNVERIFIED` rather than guessed.

---

## 1. Snapshot

| Item | Value | How verified |
| --- | --- | --- |
| `main` HEAD | `c2ec6df` | `git rev-parse origin/main` |
| Date of this snapshot | 2026-09-28 | session date |
| Plugin version | `0.1.0` | `rankkernel.php` `RANKKERNEL_VERSION` and plugin header |
| PHP tests | **1644 tests / 6842 assertions** | `composer test` on a clean `origin/main` worktree |
| JS tests | **142 / 142 pass** | `composer test:js` |
| PHPCS | **exit 0**, no violations | `composer lint` |
| PHPStan | **[OK] No errors**, level 6 | `composer stan` |
| `node --check` | clean across `assets/js` | `find assets/js -name '*.js' -print0 \| xargs -0 -n1 node --check` |
| Open PRs | **0** | `gh pr list --state open` |
| Open issues | **2**: #111, #82 | `gh issue list --state open` |
| Recently closed | **#104** (owner, 2026-09-28T10:32:47Z, two seconds after #115 merged), #112, #91, #90, #88 | GitHub API timeline |
| Active workers | **0** (all complete) | background task notifications |
| Tracked tree cleanliness | clean in every worktree | `git status --porcelain` |
| Worktrees | 4 (see section 3) | `git worktree list` |

### Environment note

The shared checkout is on branch `GH-88` at `2ee0c5d`. That is an **old** branch, so any path-based inspection inside it reflects stale content. All state in this document was read from `origin/main` via `git show origin/main:<path>` or from a clean `origin/main` worktree. Reproduce with `git worktree add --detach /tmp/x origin/main`.

---

## 2. Architecture Status

Status vocabulary: **COMPLETE**, **PARTIAL**, **IN PROGRESS**, **MISSING**, **DEFERRED**, **BLOCKED**, **NEEDS VERIFICATION**.

Module enable state was verified by checking which modules have a `Module.php` under `src/Modules/` and appear in `ModuleRegistry::MODULES`.

| Subsystem | Status | Evidence and remaining work |
| --- | --- | --- |
| Foundation (bootstrap, autoload, requirements) | COMPLETE | `rankkernel.php`, autoload fixed in #112/#113 (`b20c929`) |
| Bootstrap / module registry | COMPLETE | `src/Plugin.php`, `src/Modules/ModuleManager.php`, `ModuleRegistry` with 14 entries |
| Data layer | COMPLETE | Redirect, monitor, IndexNow and sitemap tables each own a `LogTable`/`Table` class with idempotent creation |
| Metadata | COMPLETE | `src/Modules/Metadata/`, `src/Admin/MetadataBox.php`, `MetadataModule` present |
| Head pipeline | COMPLETE | Metadata output path shipped with the metadata module |
| Metadata output | COMPLETE | Same module; verified by `MetadataBoxTest` and parity fixtures |
| Sitemaps | COMPLETE | `SitemapsModule`, providers for posts, terms, authors, XSL sheet, cache |
| Redirects | COMPLETE | `RedirectsModule`, matcher, repository, CSV import/export, safety precedence |
| 404 monitor | COMPLETE | `MonitorModule` (`src/Modules/Monitor/`), log table, retention, flood guard |
| Schema | COMPLETE | `SchemaModule`, `src/Modules/Schema/Pieces/`, REST write boundary |
| Robots | COMPLETE | `RobotsModule`, robots.txt plus physical llms.txt writer and crawler policy |
| llms / crawlers | COMPLETE | `LlmsRouter`, `LlmsSettings`, `LlmsFileWriter`, `CrawlerPolicy` |
| Content Engine | COMPLETE | `src/Modules/Analysis/`, engine plus Gutenberg editor and classic panel |
| Gutenberg | COMPLETE | `metadata-sidebar.js`, analysis editor bridge, block editor styles |
| Social SEO | COMPLETE | Social section (`sections/social.php`) and OG/Twitter output |
| REST / Headless | **PARTIAL** | The `headless` module is registered but has no `Module.php`. REST routes exist for modules, log and schema. Headless output is not implemented |
| IndexNow / Instant Indexing | COMPLETE | `src/Modules/InstantIndexing/`, log table, REST, AJAX plus no JS fallback, retry |
| Image SEO | **MISSING** | `image-seo` is in `ModuleRegistry` with no `Module.php` and no directory under `src/Modules/` |
| AI | **MISSING** | `ai` is in `ModuleRegistry` with no implementation. Roadmap STEP 3 is the BYO-key AI suite, pending |
| Internal Linking | **MISSING** | No module, no registry entry, no code found |
| Settings Export / Import | **NEEDS VERIFICATION** | Redirects has CSV import/export. A settings level export/import was not located. Do not assume it exists |
| Importer | **DEFERRED** | `importer` is in `ModuleRegistry` with no implementation. Explicitly deferred (see section 6) |
| Admin UI | **PARTIAL** | 7 menu screens plus 2 metaboxes exist and work (section 8). Visual layer is the active design workstream |
| Design system | **PARTIAL** | Tokens and a shared layer exist: `rankkernel-admin.css` (78 `--rk-*` tokens), `rankkernel-ui.css` (50 `rk-ui-*` classes). Two pages have approved designs; the rest are undesigned |

**Modules in the registry without an implementation (5):** `importer`, `image-seo`, `gutenberg`, `ai`, `headless`.

Verified by searching for a module class or directory for each, not by grepping the name:

* `importer` — **no `Importer` class and no module directory exist.** The word appears in `src/` only because the Redirects CSV import feature uses that vocabulary (`CsvHandler`, `RedirectRepository`, `Views/redirects.php`) plus the registry and dashboard entries. Do not confuse the redirects CSV importer with an Importer module. There is none.
* `image-seo` — appears only in the registry and the dashboard and REST module lists. No module directory, no class.
* `headless` — appears only in the registry, the dashboard, `ModulesController` and `AnalysisModule`/`AnalysisController` (a `headless` context flag). No headless output module.
* `ai` — registry entry only.
* `gutenberg` — has working editor JavaScript but no `GutenbergModule` class, so treat its registry entry as `NEEDS VERIFICATION` rather than a missing module.

---

## 3. Git / PR State

### Worktrees

| Path | Branch | HEAD | Owner | Purpose |
| --- | --- | --- | --- | --- |
| `…/plugins/rankkernel` | `GH-88` | `2ee0c5d` | shared checkout | Stale branch. Not `main`. Do not treat its files as current |
| `…/plugins/rankkernel-design` | `design/pending` | `c2ec6df` | **design agent** | Dedicated design worktree, based on current `main` |
| `/tmp/opencode/docs` | `docs/state-baseline` | `c2ec6df` | coordinator | This document |
| `/tmp/opencode/vmain` | detached | `a564ecb` | coordinator | Scratch verification worktree, safe to remove |

**Rule:** the engineering worktree and the design worktree must never be edited by the same agent at the same time.

### Branch / PR table

Verified against the GitHub API. Only verified rows are listed.

| Branch | Issue | PR | Status | Owner/Worker | Purpose | Safe to merge? |
| --- | --- | --- | --- | --- | --- | --- |
| `main` | — | — | `c2ec6df` | — | Trunk | n/a |
| `GH-112` | #112 | #113 | **MERGED** `b20c929` | coordinator | Autoload without Composer | Done |
| `GH-104` | #104 | #114 | **MERGED** `a564ecb` | coordinator | Critical error handling | Done |
| `GH-104-importants` | #104 | #115 | **MERGED** `c2ec6df` | coordinator | Important resilience fixes | Done |
| `GH-90` | #90 | #109 | **MERGED** `07f02ff` | coordinator | CSS dependency + settings race | Done |
| `GH-91` | #91 | #110 | **MERGED** `7170449` | coordinator | Security and privacy | Done |
| `GH-88` | #88 | #99 | **MERGED** `b72be63` | coordinator | Instant Indexing log | Done |
| Bot branches (7) | none | #100–#107 | **MERGED** | bots | Auditor/Sentinel/Palette/Bolt fixes | Done |
| `docs/state-baseline` | — | — | in progress | coordinator | This document | After review |
| `design/pending` | — | — | idle | design agent | Design worktree placeholder | Never merge as is |

### Closed without merge

| PR | Reason |
| --- | --- |
| #108 | No-op: 2 commits, **0 changed files** (the second reverted the first). It edited only `docs/`, which `AGENTS.md` forbids for agents. Closed with explanation |

### Stale local branches

Many old local branches exist (`GH-11`, `GH-12`, `GH-13`, `GH-15`, `GH-23`, `GH-25`, `GH-27`, `GH-38`, `GH-42`, `GH-44`, `GH-46`, `GH-52`, `GH-54`, `GH-64`, `GH-65`, `GH-7`, `GH-80`). They are merged or abandoned history. **Do not treat them as active.**

---

## 4. Active Workers

**None. All dispatched workers have completed and their work is merged.**

For audit purposes, the workers that ran in the most recent cycle were:

| Worker | Branch | Issue | Task | Files touched | Status | Outcome | Coordinator review |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `GH-104` admin | `GH-104` | #104 | Silent write and false success in `src/Admin/` | 10 admin files + tests | COMPLETE | Merged in #114 | Done and mutation checked |
| `GH-104` modules | `GH-104` | #104 | Fail open in modules, guard text domain boot | 5 module files + tests | COMPLETE | Merged in #114 | Done and mutation checked |
| `GH-104` importants | `GH-104-importants` | #104 | meta_query merge, migration ledger, module isolation | 5 source + 6 test files | COMPLETE | Merged in #115 | Done and mutation checked |

**Do not restart these workers.** Their branches are merged.

---

## 5. Known Findings

Every finding carries its source. An unverified claim is never presented as a confirmed bug.

### Critical

| Finding | Source | Status |
| --- | --- | --- |
| Activation fataled with `Class RankKernel\Settings\SettingsStore not found` when `vendor/` is absent | Issue #112, real deployment report | **FIXED** `b20c929`. Root cause: the only autoloader was Composer's, behind a silent `file_exists` guard, and `vendor/` is gitignored. The plugin has no Composer runtime dependency, so `rankkernel.php` now registers the PSR-4 mapping itself. Regression tested and mutation proven |
| `MetaPayload::sanitize` declared `array $payload` while registered as a `sanitize_callback`, so WordPress passing a scalar threw a `TypeError` | Issue #104, verified at the line | **FIXED** `a564ecb`. Now accepts `mixed` |
| JS user-facing strings never passing through translation (14 findings) | Issue #111, code-quality audit | **OPEN**. See section 7 and the important findings below |

### Important

| Finding | Source | Status |
| --- | --- | --- |
| `AnalysisColumn.php:187` replaced `meta_query` outright, dropping other plugins' filters | Issue #104, verified | **FIXED** `c2ec6df`. Now nested as a sub-clause inside an `AND` group |
| `MigrationRunner.php:130` advanced the ledger only after the loop, so a partial failure re-ran successful migrations | Issue #104, verified | **FIXED** `c2ec6df`. Ledger persists per migration |
| `ModuleManager.php:160` had no isolation, so one throwing module stopped every later module booting | Issue #104, verified | **FIXED** `c2ec6df` |
| `RecalculateCommand.php:112` aborted the whole batch on one bad post | Issue #104, verified | **FIXED** `c2ec6df` |
| `MonitorRepository.php:375` coerced a failed query to `0`, indistinguishable from a real zero | Issue #104, verified | **FIXED** `c2ec6df` |
| `Logger.php:283` stored raw query strings for up to 365 days (PII) | Issue #91, verified | **FIXED** `7170449`. Capped and redacted |
| Default uninstall left the 404 log (URIs, referers, user agents) on disk | Issue #91, verified | **FIXED** `7170449`. Tables and transients now always removed |
| `AuthorsProvider.php:80` enumerated every `wp_users` row into the public sitemap | Issue #91, verified | **FIXED** `7170449`. Scoped to publishing roles |
| `HtaccessFile` allowed a save despite `DISALLOW_FILE_EDIT` / `DISALLOW_FILE_MODS` | PR #107, mutation proven | **FIXED** `63f60cc` |
| Remaining `#104` IMPORTANT and MINOR findings (53 + 44) | Issue #104 | **UNTRACKED.** Issue #104 was **closed by the owner** at `2026-09-28T10:32:47Z`, two seconds after #115 merged. The verified subset was fixed; the remainder has **no open issue** tracking it. See the warning below |

> **Warning: the remaining #104 findings are now untracked.**
> Issue #104 was closed by the owner on 2026-09-28. The verified critical and important subset was fixed and merged (#114, #115). The remaining **53 IMPORTANT and 44 MINOR** findings, and the 12 environment-class criticals, are real entries from the audit that **no longer have an open issue**. They were never verified line by line, and the audit list is known to mix real findings with environment non-findings.
>
> Do not assume they are fixed. Do not bulk apply them. If they are to be actioned, they need a fresh issue and the same verify-then-fix treatment used for the subset that shipped. This is a tracking gap, not a claim that the code is clean.

### Minor

| Finding | Source | Status |
| --- | --- | --- |
| `tests/Unit` cross-test static memo leakage in `RedirectRepository` and `Normalizer` | PR #100 / #105 review | **OPEN, low.** Every write path clears it and statics die with the request, so it is latent test fragility, not a production bug |
| Site host check compares host only while the JS also validates the port | #91 security review | **OPEN, low.** No request is ever made to the submitted URL |
| Help card copy and the empty-log preview still say a 4xx retry "will not help" beside a visible Retry button | #90 eligibility fix | **PARTIAL.** The help card was corrected in `2ee0c5d`; the preview mockup keeps empty action cells because a test pins that shape |
| Standalone em dashes in comments in `assets/js/redirects-admin.js` | Issue #111 | **OPEN** |
| WPCS style drift: 4-space indentation and missing inner spacing in `schema-metabox.js` and `schema-settings.js` | Issue #111 | **OPEN** |
| Empty catch blocks in `instant-indexing-admin.js` (lines 533, 1523) | Issue #111 | **OPEN** |

### False Positives / Resolved

| Claim | Verdict | Evidence |
| --- | --- | --- |
| `assets/fonts/README.md:42` "hardcoded high entropy secret" (`com/…oFsI`) | **FALSE POSITIVE**, twice (#90 and #111) | The value is the public Google Fonts URL `fonts.gstatic.com/s/materialsymbolsoutlined/v374/kJEhBvYX7BgnkSrUwT8OhrdQw4oELdPIeeII9v6oFsI.woff2`. A font filename hash is not a credential |
| `NotFoundPage.php:198` "plugins_url fatals without WordPress" | **FALSE POSITIVE** | The guard `if ( ! function_exists( 'plugins_url' ) ) { return; }` is already present at line 192 |
| `InstantIndexingLogView.php:291` "row missing code, id, url" | **FALSE POSITIVE** | `LogQuery::normalizeRow()` builds every key explicitly with `??` defaults, so the premise cannot occur |
| 12 of the 29 `#104` criticals: "fatals in a unit test environment without WordPress loaded" or "with a minimal `$wpdb` stub" | **FALSE POSITIVE class** | That code is reachable only through WordPress hooks (`add_action`, `apply_filters`, `admin_menu`) and never executes without WordPress. It describes the test harness, not shipping code |
| `$table_prefix` undefined in `wp-settings.php` | **NOT A PLUGIN BUG** | Host `wp-config.php` omission, confirmed by the reporter |
| "The site you have requested is not installed" | **NOT A PLUGIN BUG** | Incomplete WordPress installation on the host |
| `dbDelta` cannot parse `CREATE TABLE IF NOT EXISTS`, so custom tables are never created | **REFUTED** | The live database contains `wp_rankkernel_404_log` and `wp_rankkernel_redirects` with correct keys and `AUTO_INCREMENT=107`, created by that exact pattern. A static analysis of core internals was a hypothesis; the database refuted it |

### Needs Verification

| Item | Why it is unverified |
| --- | --- |
| Settings-level Export / Import | Not located in code. Redirects has CSV import/export only. Do not claim a settings exporter exists |
| `gutenberg` module registry entry | Working editor JS exists but no `GutenbergModule` class. Unclear whether the registry entry is intentionally a facade |
| The remaining `#104` IMPORTANT and 44 MINOR findings | Only the verified subset has been actioned. **The issue was closed by the owner**, so the remainder is no longer tracked anywhere. It requires per-line verification before any claim |
| Recurrence of the entropy false positive | Root cause identified: `security-privacy.md` carries the guard, `code-quality-conventions.md` does not, so audits using the latter re-report it |

---

## 6. Deferred Work

Recorded so no worker picks these up by accident.

| Item | Reason | Source |
| --- | --- | --- |
| **Importer** | Registered in `ModuleRegistry` with no implementation. Explicitly deferred | Issue #104 acceptance context, roadmap |
| **Batch submissions within one request (#82)** | Deliberately deferred by the owner. Belongs at the submission boundary, not the storage layer | Issue #82 |
| **AI Suite (BYO key)** | Roadmap STEP 3. Was explicitly excluded from the current scope | Roadmap |
| **External paid APIs** | Roadmap STEP 4, post-v1.0 | Roadmap |
| **AI visibility tracking** | Needs a third-party data service. Out of scope | Roadmap |
| **Rank tracking** | Requires a SERP data service. Out of scope for v1.0 | Roadmap |
| **Author role exclusion to the settings UI** | Deferred by the roadmap | Roadmap |
| **Design polish pass (tokens, branded header)** | Deferred to the design workstream, which is the next phase | Roadmap |

**Do not reopen any of these without an explicit owner instruction.**

---

## 7. Feature Gap Matrix

Ordered by the roadmap's established 4-step sequence, not by preference.

| Feature | Current State | Implemented | Missing | Next Action | Priority |
| --- | --- | --- | --- | --- | --- |
| STEP 1 Core functionality and minimal admin UI scaffolding | IN PROGRESS | 9 modules working, 7 admin screens | Remaining `#104` findings, `#111` i18n gaps | Close the i18n findings, then proceed to design | Next |
| STEP 2 Full testing, QA and v1.0 release verification | NOT STARTED | Strong unit and parity coverage already | Formal QA pass, release verification | After STEP 1 | Later |
| STEP 3 BYO-key local AI suite | DEFERRED | Nothing | Entire AI module | After v1.0 core | Deferred |
| STEP 4 External paid APIs | DEFERRED | Nothing | Entire scope | Post-v1.0 | Deferred |
| Importer | DEFERRED | Nothing | Entire module | Deferred by owner | Deferred |
| Image SEO | MISSING | Nothing | Entire module | Not scheduled | Unknown |
| Headless | PARTIAL | Registry entry, REST routes | Headless output module | Not scheduled | Unknown |
| Internal Linking | MISSING | Nothing | Entire feature | Not scheduled | Unknown |
| Settings Export / Import | NEEDS VERIFICATION | Redirects CSV only | Settings level exporter (if required) | Verify before planning | Unknown |

---

## 8. Admin UI Inventory

Verified from `src/Admin/AdminMenu.php`, `src/Admin/Views/`, and the page classes.

**Top-level menu:** `RankKernel`, slug `rankkernel`, icon `dashicons-search`, position 80, capability `manage_options`.

| # | Menu label | Slug | Page class | View | JS | CSS | Backend functionality | State |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | Dashboard | `rankkernel` | `DashboardPage` | `Views/dashboard.php` | none specific | `dashboard-admin.css` | Module enable/disable cards, links to settings | Working |
| 2 | Sitemap | `rankkernel-sitemap` | `SitemapSettingsPage` | `Views/sitemap-settings.php` | none specific | `settings-admin.css` | Per post type and taxonomy inclusion, author sitemap, include-empty toggle | Working |
| 3 | General Settings | `rankkernel-general` | `SettingsPage` | `Views/settings.php` plus 8 section partials | `settings-admin.js`, `breadcrumbs-admin.js`, `metadata-editor.js` | `settings-admin.css` | Sections: general, webmaster, social, breadcrumbs, robots, htaccess, llms, advanced | Working |
| 4 | Schema | `rankkernel-schema` | `SchemaSettingsPage` | `Views/schema-settings.php` | `schema-settings.js` | `settings-admin.css` | Organization logo, schema defaults | Working |
| 5 | Redirects | `rankkernel-redirects` | `RedirectsPage` | `Views/redirects.php`, `Views/redirects-list.php` | `redirects-admin.js` | `redirects-admin.css` | Add/edit rules, CSV import/export, bulk actions, search, filters | Working |
| 6 | 404 Monitor | `rankkernel-404` | `NotFoundPage` | `Views/not-found.php` | `monitor-admin.js` | `monitor-admin.css` | 404 log, retention, flood guard, clear log, advanced fields | Working |
| 7 | Instant Indexing | `rankkernel-instant-indexing` | `InstantIndexingPage` | `Views/instant-indexing.php` | `instant-indexing-admin.js` | `instant-indexing-admin.css`, `rankkernel-ui.css` | Key management, verify, manual submit, full history log with search, filters, pagination, retry | Working |

### Editor surfaces (not menu pages)

| Surface | Class | View | JS | State |
| --- | --- | --- | --- | --- |
| Classic and block editor SEO panel | `MetadataBox` | `Views/metadata-box.php` | `metadata-editor.js`, `metadata-sidebar.js` | Working |
| Editor schema panel | `SchemaMetabox` | `Views/schema-metabox.php` | `schema-metabox.js` | Working |
| Content analysis panel | Analysis module | n/a | `analysis-editor.js`, `analysis/*.js` | Working |

### CSS architecture (verified)

* `rankkernel-admin.css` — token layer, 78 `--rk-*` custom properties, registered as handle `rankkernel-admin`
* `rankkernel-ui.css` — shared component layer, 50 `rk-ui-*` classes, handle `rankkernel-ui`, depends on the token layer
* 8 feature stylesheets, each depending on the token handle
* `monitor-admin.css` deliberately independent: it uses raw hex and consumes no tokens
* No CSS `@import` remains anywhere (fixed in #109)

### Existing design references

| Artifact | Location | Status |
| --- | --- | --- |
| Design handoff brief | `docs/design-task.md` on `main` | Tracked, 16 sections |
| Redirects approved design | `docs/designcode/redirection-page.html` | **Untracked locally only** |
| Instant Indexing approved design | `docs/designcode/instant-indexing.html` | **Untracked locally only** |
| Instant Indexing Stitch prompts (3) | `docs/designcode/*.txt` | **Untracked locally only** |

**Important:** the `docs/designcode/` artifacts are **not on `main`**. A design agent on another machine will not see them. They must be committed before the design work starts, or the design agent must be given them directly.

### Stitch reference status

`UNVERIFIED as a live link.` The approved artifacts above are local HTML exports. Do not treat any Stitch mockup as a specification. Repository behaviour is authoritative.

---

## 9. Design Readiness

| Page | Readiness | Why |
| --- | --- | --- |
| Design system (tokens, components) | **READY FOR DESIGN** | Tokens and the shared layer already exist and are now correctly dependency loaded |
| Admin shell / menu / navigation | **READY FOR DESIGN** | 7 screens exist behind one top-level menu |
| Dashboard | **READY FOR DESIGN** | Fully working module cards and toggle actions |
| Settings (general, 8 sections) | **READY FOR DESIGN** | All sections have working backend handlers |
| Sitemaps | **READY FOR DESIGN** | Settings screen fully functional |
| Schema settings | **READY FOR DESIGN** | Functional |
| Redirects | **READY FOR DESIGN** | Has an approved design artifact already; the richest screen |
| 404 Monitor | **READY FOR DESIGN** | Functional log, retention, clear |
| Instant Indexing | **READY FOR DESIGN** | Has an approved design artifact; log with search, filters, pagination, retry |
| Content Analysis panel | **READY FOR DESIGN** | Functional editor panel |
| Metadata editor panel | **READY FOR DESIGN** | Functional |
| Schema metabox | **READY FOR DESIGN** | Functional |
| Importer | **DEFERRED** | No backend. Designing it would require inventing functionality |
| Image SEO | **BLOCKED** | No module, no backend, no data source |
| AI Suite | **DEFERRED** | Roadmap STEP 3 |
| Headless | **BLOCKED** | No output module to design against |
| Internal Linking | **BLOCKED** | Does not exist |

**No page is blocked by a missing backend except the four named above.** Every menu screen listed as ready has real, working functionality behind it, so design work can start without inventing behaviour.

---

## 10. Reproduce these numbers

```bash
cd <plugin>
git fetch origin main
git worktree add --detach /tmp/rk-verify origin/main
cp -r vendor /tmp/rk-verify/vendor      # a real copy, not a symlink
cd /tmp/rk-verify
composer lint      # expect exit 0
composer stan      # expect [OK] No errors
composer test      # expect OK (1644 tests, 6842 assertions)
composer test:js   # expect 142 pass, 0 fail
```

If any number differs, the repository is the authority and this document is stale. Update it.
