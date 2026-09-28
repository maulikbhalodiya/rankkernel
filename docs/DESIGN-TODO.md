# RankKernel Design TODO

**Owner:** the design agent. **Maintained continuously.** Update this board as you work: `IN PROGRESS` before starting, `DONE` after finishing, `BLOCKED` if you cannot proceed, `DEFERRED` if it is intentionally not to be worked on.

Every `DONE` entry must record: files changed · branch · commit · PR · tests · visual verification · remaining issues.

**Do not silently skip work.** If you skip something, write down why.

Status legend: `[ ]` todo · `[~]` in progress · `[x]` done · `[!]` blocked · `[-]` deferred

Verified from the repository on 2026-09-28. Page inventory matches `docs/RANKKERNEL-CURRENT-STATE.md` section 8.

---

## DESIGN SYSTEM

Branch: `design/design-system`

* [ ] Global design tokens audit and completion (extend the existing 78 `--rk-*` properties, do not replace them)
* [ ] Typography scale
* [ ] Spacing scale
* [ ] Form controls (text, textarea, select, checkbox, radio, toggle)
* [ ] Buttons (primary, secondary, destructive, disabled, busy)
* [ ] Cards
* [ ] Notices (success, info, warning, error) presentation only, semantics unchanged
* [ ] Tables (header, row states, sticky header, narrow viewport behaviour)
* [ ] Forms (field groups, descriptions, required markers, inline errors)
* [ ] Tabs
* [ ] Drawers / modals
* [ ] Empty states
* [ ] Loading states
* [ ] Responsive rules
* [ ] Accessibility rules (focus ring, contrast both admin themes, reduced motion)
* [ ] Icon usage audit against the subset font (30 ligatures) and the coverage guard test

Note: `rankkernel-ui.css` already provides 50 `rk-ui-*` classes. Prefer extension over replacement, and keep `monitor-admin.css` either independent or bring it into the token system as a **stated** deliberate change.

---

## ADMIN SHELL

Branch: `design/admin-shell`

* [ ] Admin menu presentation (top-level `RankKernel` plus Dashboard, Sitemap, General Settings, Schema, Redirects, 404 Monitor, Instant Indexing)
* [ ] Page header pattern across all 7 screens
* [ ] Navigation between settings sections
* [ ] Content container width and rhythm
* [ ] Responsive shell behaviour

---

## PAGES

Every page below was verified to exist with working backend functionality. Design may proceed without inventing behaviour.

### Dashboard — `rankkernel`

Branch: `design/dashboard`

**Approved design artifact exists:** `docs/designcode/dashboard-page.html`. This page has a real reference, so fidelity matters.

* [ ] Inventory (verify module cards and toggle actions against code)
* [ ] Stitch reference (approved artifact above)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA (cards already carry per-module `aria-label`s, verify and extend)
* [ ] Regression verification (module enable/disable toggles must still save)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Sitemaps — `rankkernel-sitemap`

Branch: `design/sitemaps`

**Approved design artifact exists:** `docs/designcode/sitemap-settings-page.html`. This page has a real reference, so fidelity matters.

* [ ] Inventory (per post type, per taxonomy, author sitemap, include-empty toggle)
* [ ] Stitch reference (approved artifact above)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA
* [ ] Regression verification (inclusion toggles must still save)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Settings — `rankkernel-general`

Branch: `design/settings`

Sections verified present: general, webmaster, social, breadcrumbs, robots, htaccess, llms, advanced.

* [ ] Inventory of all 8 sections
* [ ] Stitch reference (none found)
* [ ] Implementation, section by section
* [ ] Visual QA
* [ ] Accessibility QA (`aria-describedby` added in #106 must be preserved)
* [ ] Regression verification (every section still saves; the partial swap AJAX still works; failure notices still render)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Schema — `rankkernel-schema`

Branch: `design/schema`

**Approved design artifact exists:** `docs/designcode/Schema-settings-page.html`. This page has a real reference, so fidelity matters.

* [ ] Inventory (organization logo, schema defaults)
* [ ] Stitch reference (approved artifact above)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA (media frame labels)
* [ ] Regression verification (settings still save; media picker still binds after a section swap)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Redirects — `rankkernel-redirects`

Branch: `design/redirects`

**Approved design artifact exists:** `docs/designcode/redirection-page.html`. This page has a real reference, so fidelity matters.

* [ ] Inventory (rule list, add/edit form, CSV import/export, bulk actions, search, filters, pagination)
* [ ] Stitch reference (approved artifact above)
* [ ] Implementation
* [ ] Visual QA against the artifact
* [ ] Accessibility QA
* [ ] Regression verification (all row actions, bulk actions, CSV round trip, filters)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### 404 Monitor — `rankkernel-404`

Branch: `design/404-monitor`

**Approved design artifact exists:** `docs/designcode/404-monitor-page.html`. This page has a real reference, so fidelity matters.

* [ ] Inventory (log table, retention, flood guard, clear log, advanced fields toggle)
* [ ] Stitch reference (approved artifact above)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA
* [ ] Regression verification (clear log, filters, retention settings)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Instant Indexing — `rankkernel-instant-indexing`

Branch: `design/instant-indexing`

**Approved design artifact exists:** `docs/designcode/instant-indexing.html`. This page has a real reference, so fidelity matters.

**Read the behaviour rules before designing:** the key never reaches the browser; there is no quota and no success rate; history is intentionally unbounded until cleared; retry appends a new row; retry is offered on every failure including permanent 4xx.

* [ ] Inventory (key status, verify, manual submit, history log with search, source and status filters, pagination, retry, clear)
* [ ] Stitch reference (approved artifact above)
* [ ] Implementation
* [ ] Visual QA against the artifact
* [ ] Accessibility QA
* [ ] Regression verification (submit, verify, clear, retry, and the AJAX refresh must still rebuild the Actions column)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Content Analysis panel

Branch: `design/content-analysis`

* [ ] Inventory (Gutenberg editor panel plus classic panel)
* [ ] Stitch reference (none found)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA
* [ ] Regression verification (analysis still runs; scores unchanged; the debounced path still works)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Metadata editor panel

Branch: `design/metadata-panel`

* [ ] Inventory (classic and block editor SEO fields)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA
* [ ] Regression verification (fields still save)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

### Schema metabox

Branch: `design/schema-metabox`

* [ ] Inventory (editor schema fields)
* [ ] Implementation
* [ ] Visual QA
* [ ] Accessibility QA (note: this file has known WPCS style drift, coordinate with engineering before editing)
* [ ] Regression verification (schema still saves)
* [ ] PR created
* [ ] Engineering review
* [ ] Merged

---

## BLOCKED

Pages with no backend to design against. **Do not design these. Designing them would require inventing functionality.**

* [!] **Importer** — `importer` is in `ModuleRegistry` with no implementation. Explicitly deferred by the owner.
* [!] **Image SEO** — `image-seo` is in `ModuleRegistry` with no `Module.php` and no module directory.
* [!] **Headless** — registry entry and REST routes exist, but there is no headless output module.
* [!] **Internal Linking** — no module, no registry entry, no code.
* [!] **AI Suite** — registry entry only. Roadmap STEP 3, not started.
* [!] **Settings Export / Import** — `NEEDS VERIFICATION`. Redirects has CSV import/export; a settings level exporter was not located. Do not design an export screen until engineering confirms whether one exists.

---

## DEFERRED

Intentionally not to be worked on. **Do not pick these up.**

* [-] **AI visibility tracking** — needs a third-party data service. Out of scope.
* [-] **Rank tracking** — requires a SERP data service. Out of scope for v1.0.
* [-] **External paid APIs** — roadmap STEP 4, post-v1.0.
* [-] **Batch submissions within one request** — issue #82, deferred at the submission boundary.
* [-] **Author role exclusion UI** — deferred by the roadmap.

---

## ENGINEERING FINDINGS THAT TOUCH DESIGN

Not design work, but visible to users. Do not fix these silently inside a design PR. Raise them with engineering.

* The empty-log preview mockup on Instant Indexing keeps empty action cells. A test pins that shape, and the preview illustrates the empty state.
* Help card copy on Instant Indexing was corrected to no longer claim a retry cannot help.
* JavaScript strings across `analysis-editor.js`, `instant-indexing-admin.js`, `schema-settings.js` and `settings-admin.js` are still hardcoded English and not translatable. Tracked in issue #111. **Translation is an engineering fix, not a design fix.** If you touch those strings for layout, leave the text itself alone.
