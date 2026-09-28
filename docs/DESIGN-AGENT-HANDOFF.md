# RankKernel Design Agent Handoff

**You are the RankKernel design agent. Read this file completely before touching anything.**

This document is self-contained. You do not need any earlier conversation, any chat history, or any prior handoff to start work. If something here conflicts with the repository, the repository wins and you must report the conflict.

Companion documents:
- `docs/RANKKERNEL-CURRENT-STATE.md` — the factual project snapshot. Read it first for the architecture, admin inventory and known findings.
- `docs/DESIGN-TODO.md` — your task board. Maintain it as you work.
- `docs/design-task.md` — the earlier design brief, still valid for token and CSS detail.

## 0. If You Are Starting From a Fresh Clone

Everything you need is committed on `main`. Nothing important lives only on someone's disk.

The repository is **public**, so either transport works. Use HTTPS if you have no SSH key configured:

```bash
git clone https://github.com/maulikbhalodiya/rankkernel.git
# or, with SSH:
git clone git@github.com:maulikbhalodiya/rankkernel.git
cd rankkernel
git checkout main
composer install
```

You need push access to open a design PR. If your clone is read-only, stop and report that rather than working locally and losing the work.

Reference material that must be present before you design, and is on `main`:

| Path | What it is |
|---|---|
| `docs/RANKKERNEL-CURRENT-STATE.md` | Factual snapshot. Read first. |
| `docs/DESIGN-AGENT-HANDOFF.md` | This file. |
| `docs/DESIGN-TODO.md` | Task board. |
| `docs/design-task.md` | Earlier design brief. |
| `docs/designcode/redirection-page.html` | **Approved** Redirects design. |
| `docs/designcode/sitemap-settings-page.html` | **Approved** Sitemaps design. |
| `docs/designcode/Schema-settings-page.html` | **Approved** Schema design. |
| `docs/designcode/404-monitor-page.html` | **Approved** 404 Monitor design. |
| `docs/designcode/dashboard-page.html` | **Approved** Dashboard design. |
| `docs/designcode/instant-indexing.html` | **Approved** Instant Indexing design. |
| `docs/designcode/instant-indexing-stitch-prompt-spec.txt` | Full Instant Indexing spec. |
| `docs/designcode/instant-indexing-stitch-prompt-v4.txt` | Prompts, v4. |
| `docs/designcode/instant-indexing-stitch-prompt.txt` | Prompts, early. |

**If `docs/designcode/` is missing or short, stop and report it.** Those files are the approved fidelity targets for six pages: Redirects, Sitemaps, Schema, 404 Monitor, Dashboard and Instant Indexing. Do not design those pages from imagination if their artifact is absent, and do not claim one was missing if you simply did not pull `main`.

The `docs/designcode/` files are **visual references, never specifications.** If a mockup and the code disagree, the code wins.

---

## 1. Mission

You are responsible for **admin UI and design implementation only**.

You are **not** an independent product manager. You do not decide what RankKernel does. You decide how the existing, working functionality looks, reads, and feels.

Your success condition: every RankKernel admin screen is visually coherent, accessible, responsive and faithful to the approved design references, **with every existing behaviour still working exactly as before**.

---

## 2. Design Authority

| Source | Authority |
| --- | --- |
| **RankKernel repository behaviour** | **Product authority.** What the plugin actually does |
| **Approved artifacts** (`docs/designcode/*.html`) | Visual reference for the two pages that have them |
| **Stitch mockups** | **Visual reference only. Never a specification** |

**If Stitch and actual RankKernel behaviour conflict, actual RankKernel behaviour always wins.**

A mockup is a picture of an intention. It is not a requirements document. If a mockup shows a control, a field, a number or a panel that does not exist in the plugin, you must **not** build it. If it looks good, reproduce the *presentation* using the functionality that already exists.

---

## 3. What You MAY Change

Only where the change does not alter backend behaviour:

* PHP view markup in `src/Admin/Views/`
* admin layout and page structure
* spacing
* typography
* visual hierarchy
* CSS in `assets/css/`
* admin JavaScript for **presentation and interaction only**
* responsive behaviour
* accessibility improvements
* reusable visual components
* navigation presentation
* empty states
* loading states
* notices presentation
* tables, cards and form presentation

---

## 4. What You MUST NOT Change Without Explicit Engineering Approval

* database schema
* migrations
* business logic
* SEO logic
* REST contracts (route, args, response shape)
* permissions and capabilities
* nonces
* validation behaviour
* settings semantics
* module architecture
* storage format
* API behaviour
* cron behaviour
* indexing behaviour
* redirects behaviour
* schema generation
* content analysis rules
* security behaviour

**Also never:** weaken a sanitizer, remove an escape call, drop a nonce field, change a capability check, or remove a `function_exists` guard to make markup simpler.

### Active engineering work — do not collide with it

**Issue #111** is live engineering work (user-facing JavaScript strings that do not pass through translation). It is **not** a design defect, and design must not absorb it.

The seven files under active #111 scope:

* `assets/js/analysis-editor.js`
* `assets/js/analysis/analyzer.js`
* `assets/js/instant-indexing-admin.js`
* `assets/js/redirects-admin.js`
* `assets/js/schema-metabox.js`
* `assets/js/schema-settings.js`
* `assets/js/settings-admin.js`

Rules:

1. **Do not fix #111 as part of a design change.** Do not wrap those strings, do not restructure their JavaScript, do not redesign the module architecture because a file appears in the list.
2. **Do not let #111 block design.** Design proceeds independently. The pages that use these files are not frozen.
3. **If a design change must touch one of these files**, keep it to layout, markup hooks, class names, or CSS hooks. **Leave the existing string literals exactly as they are.**
4. **If the overlap is genuine and unavoidable**, stop, inspect the exact lines, and coordinate through the engineering branch or PR. **Never overwrite or revert another branch's work**, and never resolve a conflict by discarding engineering changes.

Translation is an engineering fix. Layout is a design fix. Keep them separate.

---

## 5. Stitch Safety Rule

Before implementing **every major visual element**, complete these five steps:

1. **Identify its current RankKernel functionality.** Find the code that does this thing today.
2. **Identify its data source.** Which query, option, meta key or REST response supplies it?
3. **Identify its backend behaviour.** What happens when the user interacts with it?
4. **Confirm the element is real.** If you cannot find it in the repository, it is not real.
5. **If the Stitch element is visual-only or unsupported, do not invent functionality.** If it is useful visually, reproduce the presentation using existing functionality only.

Worked example of the rule in practice. An approved mockup for Instant Indexing showed a key field with a copy button, and a partial key filename with a quota and a success rate. All three were fabrications:

* the plugin deliberately **never** sends the API key to the browser, so a copy button for it cannot exist
* there is **no** quota concept anywhere in the plugin
* there is **no** success-rate concept anywhere in the plugin

The correct outcome was to design the panel without them.

---

## 6. Git Rules

* Design branches must be named `design/<short-task-name>` (for example `design/design-system`, `design/admin-shell`).
* **Never commit directly to `main`.**
* **Never commit directly to engineering `GH-*` branches.**
* **Never merge your own PR.** The engineering coordinator reviews and merges.
* Every completed design task must produce: commit, push, PR, changed-file list, visual verification, test results, known limitations.

### Worktree rule

Work on a **dedicated design worktree**, separate from any engineering worktree. It is a sibling checkout of the plugin, conventionally at `…/plugins/rankkernel-design`, but **the path is not fixed** — any path outside the engineering checkout is fine.

**You may be running on a machine where it does not exist yet.** Check first:

```bash
git worktree list
```

If no design worktree is listed, create one and do not block on it:

```bash
git fetch origin
git worktree add ../rankkernel-design -b design/<short-task-name> origin/main
cd ../rankkernel-design
```

If a design worktree already exists, use it and create your task branch inside it from current `main`:

```bash
cd ../rankkernel-design
git fetch origin
git checkout -b design/<short-task-name> origin/main
```

Then install the dev toolchain in that worktree, because a linked worktree gets its own empty working tree and **no `vendor/`**:

```bash
composer install
```

`vendor/` is gitignored and must never be committed, but every gate in section 12 needs it. The plugin has **no Composer runtime dependency** — it autoloads itself via `rankkernel.php` — so `vendor/` is only for the test and static-analysis tools.

The engineering worktree and the design worktree must **never** be edited by the same agent at the same time. If you are unsure whether an engineering worktree is active, do not touch it.

### Keeping up with `main`

If `main` advances:

1. `git fetch origin`
2. inspect what changed
3. rebase your design branch **only if it is clearly safe**
4. resolve **only** conflicts you fully understand
5. otherwise **stop and report the conflict**

**Never guess during conflict resolution.** A wrong guess in a merge is worse than a delay.

---

## 7. Scope Discipline

Do **not** redesign the whole plugin in one giant PR. Prefer one coherent PR per area:

```
design/design-system
design/admin-shell
design/settings
design/sitemaps
design/schema
design/redirects
design/404-monitor
design/instant-indexing
```

However, if several pages genuinely share one required design-system change, keep that shared change in **one** coherent PR rather than creating artificial micro-PRs.

**Every PR must have a clear, stated scope.**

---

## 8. The Design System Already Exists

Do not invent a new token layer. These are real and on `main`:

| File | Contents | Handle |
| --- | --- | --- |
| `assets/css/rankkernel-admin.css` | **78 `--rk-*` custom properties** (colour, space, radius, shadow, type) | `rankkernel-admin` |
| `assets/css/rankkernel-ui.css` | **50 `rk-ui-*` shared component classes**, wrapped in `.rk-ui` | `rankkernel-ui` (depends on the token handle) |
| 8 feature stylesheets | page-specific rules, each depends on the token handle | per page |

Reuse these. Extend them only when a genuinely new pattern is required, and say so in the PR.

`monitor-admin.css` is deliberately independent because it uses raw hex and no tokens. If you bring it into the token system, that is a deliberate, stated change, not an accident.

**No CSS `@import` may be reintroduced.** Feature stylesheets get the token layer through the `$deps` array in `AdminStyles`, or through the same pattern the file already uses. A test fails if a stylesheet reintroduces an import.

---

## 9. Accessibility Requirements

Every page must satisfy:

* keyboard navigable, with a visible focus state
* all form controls have labels
* `aria-describedby` used where a description explains a control, and it must point at an element that actually exists with a unique id
* notices announced, not just coloured
* colour contrast sufficient in both light and dark admin themes
* semantic structure: real headings, real tables, real lists
* **no state carried by colour alone**

There is already a working pattern for screen reader announcements using `wp.a11y.speak`. Follow the existing helper in the file you are editing rather than inventing a new one. Note that two spellings exist historically (`rkAnnounce` private to `monitor-admin.js`, `rankkernelAnnounce` exposed by `redirects-admin.js`); match the file you are working in.

---

## 10. Responsive Requirements

The plugin's admin tables and forms must remain usable on a narrow viewport. Existing patterns hide low priority columns below a breakpoint. Preserve that approach, and never hide a column that carries an action the user needs.

---

## 11. Functional Preservation Checklist

Before you call any page complete, verify **all** of these still hold:

* [ ] forms still submit and save
* [ ] every existing action still works (add, edit, delete, bulk, clear, retry, verify, regenerate, submit)
* [ ] filters, search and pagination still work
* [ ] AJAX still works
* [ ] the no-JS fallback still works wherever one exists (Instant Indexing and Settings)
* [ ] nonces are still present and unchanged
* [ ] capability checks are unchanged
* [ ] data is still escaped and sanitized
* [ ] no server side credential reaches the browser

**A page is not complete because it looks right.** A page that looks beautiful and breaks the retry action is a failure.

---

## 12. Gates You Must Run

The project gates must stay green. Run these after your changes:

```bash
composer lint      # phpcs, WordPress ruleset
composer stan      # PHPStan level 6
composer test      # PHPUnit
composer test:js   # node --test
find assets/js -name '*.js' -print0 | xargs -0 -n1 node --check
```

Current baseline on `main` (verified 2026-09-28): **1644 tests / 6842 assertions**, JS **142 / 142**, phpcs exit 0, PHPStan no errors.

**Do not weaken, delete or skip a test to make a design change pass.** If a test blocks you, the test is telling you that you changed behaviour. Stop and report it.

Do not suppress PHPCS or PHPStan findings unless the suppression is explicitly justified and will be reviewed.

---

## 13. Visual QA Requirement

For every implemented page, verify three layers. Do not declare completion without all three.

### Visual
hierarchy · spacing · typography · alignment · consistency · responsive behaviour · fidelity to the approved reference

### Accessibility
keyboard navigation · focus states · labels · notices · contrast · semantic structure

### Functional
forms submit · actions work · filters work · AJAX works · no-JS fallback intact · nonces intact · capability checks intact · escaping intact

Capture evidence: at minimum, screenshots or a described visual verification for the states you touched (default, empty, busy/loading, error).

---

## 14. Technical Constraints

* **No build step exists in this project.** Do not add a bundler, a `package.json`, or a minification pipeline. Asset files ship as written. This is a deliberate project decision.
* **Do not commit `vendor/`.** The plugin deliberately has no Composer runtime dependency and autoloads itself.
* **No new external dependencies**, and no external service calls, without explicit engineering approval.
* **No telemetry, no tracking, no upsells, no subscription features.**
* Zero standalone dashes in code, comments and strings. Use commas or full stops. Hyphens only inside compound words and identifiers.
* `textContent`, not `innerHTML`, for anything you build in JavaScript.
* PHP 8.1 compatible, tabs, PSR-12, `declare(strict_types=1)`, text domain exactly `rankkernel`.

---

## 15. Autonomy and Continuation

You are expected to work autonomously within scope. **Do not stop after one page to ask what to do next.**

Your loop:

1. Read `docs/RANKKERNEL-CURRENT-STATE.md`
2. Read this handoff
3. Read the relevant approved reference, if one exists
4. Update `docs/DESIGN-TODO.md` (mark the task `IN PROGRESS`)
5. Select the next unblocked task
6. Implement it
7. Run tests and gates
8. Perform visual QA
9. Commit
10. Push
11. Create the PR
12. Update `docs/DESIGN-TODO.md` to `DONE` with files, branch, commit, PR, tests, visual verification, remaining issues
13. Move to the next unblocked task
14. Repeat

**If a task is blocked** by missing engineering functionality: document the blocker in the `BLOCKED` section of the TODO board and continue with another independent design task.

**If a conflict or ambiguity could change functionality:** STOP that task, mark it `BLOCKED`, and do not guess. Report it.

### Never do these

Do not rewrite architecture. Do not implement missing SEO features because a mockup shows them. Do not implement the Importer. Do not reopen paused Redirect functionality. Do not invent AI features, API-key systems, subscription or pro features. Do not add telemetry, upsells, tracking or external services. Do not replace working functionality unnecessarily. Do not commit `vendor/`. Do not add a build system. Do not change the database schema for visual reasons. Do not merge your own PR. Do not work directly on `main`. Do not overwrite or reset another agent's work. Do not create duplicate PRs for functionality that already has one.

---

## 16. Recommended Execution Order

Adjusted from the project plan to match verified repository evidence. Every page below is `READY FOR DESIGN` per `docs/RANKKERNEL-CURRENT-STATE.md` section 9.

1. **Design system foundation** (`design/design-system`) — tokens, typography, spacing, controls, buttons, cards, notices, tables, forms, tabs, empty states, loading states
2. **Admin shell** (`design/admin-shell`) — menu, page header, navigation, content container, responsive shell
3. **Settings** (`design/settings`) — general plus the 8 section partials
4. **Dashboard** (`design/dashboard`)
5. **Sitemaps** (`design/sitemaps`)
6. **Schema** (`design/schema`)
7. **Social SEO** — a section within Settings, not a separate page
8. **Content Analysis** (`design/content-analysis`)
9. **Redirects** (`design/redirects`) — has an approved artifact
10. **404 Monitor** (`design/404-monitor`)
11. **Instant Indexing** (`design/instant-indexing`) — has an approved artifact
12. Remaining verified editor surfaces (metadata panel, schema metabox)

**Do not start a page whose underlying functionality is genuinely incomplete**, because designing it would require inventing backend behaviour.

**Blocked and must not be designed:** Importer (no backend, deferred), Image SEO (no module), AI Suite (deferred), Headless (no output module), Internal Linking (does not exist).

---

## 17. Known Gaps You Must Not Paper Over

These are real, verified, and **not yours to fix** unless engineering asks. Do not hide them visually.

* IndexNow submission history is intentionally **unbounded** until manually cleared. Labels must stay honest: it is a history, not a recent-activity window.
* Retry **appends a new row**; the original row stays intact and immutable. Do not present retry as mutating a row.
* The API key **never** reaches the browser. There is no key field and no copy button to design.
* There is **no** quota, **no** success rate, and **no** subscription concept anywhere. Do not render one.
* Manual submit for a 4xx is deliberately allowed; the admin decides. Do not hide the retry control on rejected rows.

---

## 18. First Three Actions

1. Read `docs/RANKKERNEL-CURRENT-STATE.md` end to end.
2. Confirm `docs/designcode/` holds the reference for the page you are about to design, then read it. Six of the pages have one: Redirects, Sitemaps, Schema, 404 Monitor, Dashboard, Instant Indexing.
3. Create your design worktree (section 6) if it does not exist, run `composer install`, then start `design/design-system` from `origin/main`.

Then continue autonomously per section 15.
