# RankKernel Design Handoff

**Purpose:** a self-contained brief for continuing RankKernel admin design work from a different computer and a fresh AI session. Read this file completely before touching any design or CSS.

**Audience:** an AI or engineer with no prior context on this project.

**Status:** this document describes what already exists. Nothing here is aspirational unless it is explicitly labelled as proposed.

---

## 1. What RankKernel is

A free, open source WordPress SEO plugin, built to compete with Yoast SEO and Rank Math on architecture, not on upselling.

Non-negotiable product principles. Do not violate these while doing design work:

- Free, with **no Pro edition, no feature gates, no upsells and no licence checks**.
- **No telemetry**, no phone home, and no external request from a page render.
- **No unnecessary database bloat.**
- **WordPress native first.** Prefer WordPress APIs and controls over custom replacements.
- **Performance is a first class requirement.** The plugin markets itself as lightweight.
- **Design must not change functionality.** A visual change is never a reason to alter behaviour.
- **The backend architecture is not a design tool.** Do not restructure PHP to make styling easier.

---

## 2. The one rule that matters most: Stitch is reference, never specification

Design mockups were produced in Stitch. **A Stitch mockup is a visual reference, not a product requirement.**

A mockup may contain, and in this project some did contain:

- placeholder fields
- fake settings
- example quota controls
- example API key fields
- purely visual elements
- functionality that does not exist

**Never implement a feature merely because it appears in a mockup.**

Before implementing any visual element, do this:

1. Inspect the current RankKernel implementation for that area.
2. Confirm the underlying functionality actually exists.
3. Find the real data source for any number shown.
4. Confirm the real backend behaviour that would have to support it.
5. Only then build the UI.

**If a mockup conflicts with verified plugin behaviour, the verified behaviour wins.**

### Known bad mockups, do not copy them

Two early mockups contained mistakes and must not be treated as guidance:

- one drew an **API key field with a copy button** in the Verification key card. The plugin deliberately never renders the key, so this is the opposite of the product.
- another rendered a **partial key filename** plus an invented **quota bar** and **success rate**. None of those exist.

Approved artifacts, kept in the repository, are listed in section 8.

---

## 3. Verify before you claim

This applies to design work as much as code:

- **Do not trust a passing CI as proof that a page looks or behaves correctly.** CI does not see layout.
- **Verify in a real browser.** WordPress admin at roughly 1280px wide and at a narrow width.
- **Check the no JavaScript path.** Several pages work without JavaScript by design.
- **Confirm a claim against the repository, not from memory.** File paths, class names and tokens in this document were read from the repository at the time of writing.
- **If you change a label, check what the number actually is.** Full history, filtered count, and page count are different things and must be labelled as what they are.

---

## 4. Design system: the tokens already exist

**Do not invent new colours or spacing. Use the existing custom properties.**

Location: `assets/css/rankkernel-admin.css`. It holds **78** `--rk-*` custom properties and is the single place raw hex is permitted. Every feature stylesheet must consume tokens and must not define its own palette.

Key token groups, verified present:

| Group | Example tokens |
|---|---|
| Brand and action | `--rk-color-primary`, `--rk-color-primary-hover`, `--rk-color-primary-active`, `--rk-color-primary-soft` |
| Secondary accents | `--rk-color-secondary-1` through `--rk-color-secondary-4`, each with a `-soft` pair |
| Text and chrome | `--rk-color-text`, `--rk-color-text-secondary`, `--rk-color-text-muted`, `--rk-color-text-hint`, `--rk-color-border`, `--rk-color-border-strong`, `--rk-color-surface`, `--rk-color-surface-subtle`, `--rk-color-canvas`, `--rk-color-focus` |
| Shape and depth | `--rk-radius-sm`, `--rk-radius-md`, `--rk-radius-lg`, `--rk-shadow-sm`, `--rk-shadow-md` |
| Spacing, on a 4px base | `--rk-space-1` through `--rk-space-6` |
| Fonts | `--rk-font-sans`, `--rk-font-mono`, `--rk-font-icon` |
| Type scale | `--rk-font-size-xs` through `--rk-font-size-xl`, plus `--rk-line-height-tight/normal/loose` |
| Status badges | `--rk-badge-301-bg` and `-fg` through the redirect codes, plus active, inactive, exact, prefix and regex pairs |
| Content analysis bands | `--rk-score-good`, `--rk-score-improve`, `--rk-score-problem`, `--rk-score-neutral`, each with a `-soft` |

The design values used in the mockups match these tokens, so there is no gap between design and implementation. Concretely, from the artifacts: page background `#F0F2F5`, card `#FFFFFF`, border `#E2E8F0`, primary `#2563EB`, positive `#0F766E`, warning `#EA580C`, error `#DC2626`, text `#1E293B`.

### Typography

- **Inter** for all UI text.
- **JetBrains Mono** for URLs, HTTP status codes and timestamps.
- **Material Symbols Outlined** for icons.

All three are **self hosted** as variable woff2 files in `assets/fonts/`. No screen may fetch a font from an external host. Licences sit beside the files.

### Icons, read this before adding one

The icon font is a **hand built subset**, not the full Material Symbols family. It currently carries a small number of ligatures chosen for the pages that exist. A ligature name that is not in the subset **renders as its literal text**, which shipped as a real bug twice.

There is a guard test: `tests/js/icon-font-coverage.test.js`. It scans admin views and stylesheets for every requested icon name and asserts each one exists in the shipped font. If you add a new icon, you must add it to the subset following `assets/fonts/README.md`, and the guard will fail the suite until you do.

---

## 5. CSS architecture, real files and the rules

All paths verified in the repository.

### The layers

| File | Role |
|---|---|
| `assets/css/rankkernel-admin.css` | The token layer. Raw hex lives here only. Imported by every feature stylesheet. |
| `assets/css/rankkernel-ui.css` | The shared, opt in component layer, 50 `rk-ui-*` classes, scoped under a `.rk-ui` wrapper. |

### Feature stylesheets, each scoped to one page root

| File | Scope root |
|---|---|
| `assets/css/redirects-admin.css` | `.rk-redirects` |
| `assets/css/instant-indexing-admin.css` | `.rk-instant-indexing` |
| `assets/css/monitor-admin.css` | `.rk-monitor` |
| `assets/css/dashboard-admin.css` | `.rk-dashboard` |
| `assets/css/settings-admin.css` | `.rk-settings` |
| `assets/css/metadata-editor.css` | editor sidebar |
| `assets/css/metadata-classic.css` | classic editor metabox |
| `assets/css/analysis-column.css` | analysis column |

### The rules

1. **Inspect the existing CSS before adding any class.**
2. **Reuse `rk-ui-*` before writing anything new.** If a component already exists, use it.
3. **Do not duplicate styles.** Do not copy one page's component into another page's stylesheet.
4. **Do not create page specific copies of shared components.**
5. **Avoid unnecessary specificity.**
6. **Scope every rule to the page root or to `.rk-ui`** so nothing leaks onto other admin screens.
7. **Preserve WordPress admin compatibility.** Do not fight `.wrap`, `.notice`, or core table styles.
8. **Never promote a page's class names to global.** A promoted top level selector applies every property the page scoped rule does not declare, which visibly changes other pages. This was verified the hard way: promoting `.rk-card-title` would have changed the weight and colour of thirteen Dashboard headings, and promoting `.rk-tabs` would have moved the Redirects tabs and turned them into a scroll container.
9. **No Tailwind. No build step. No new dependency.** Styles are hand written CSS.
10. **No React for simple admin UI.**
11. **Do not add a physical file write for a style concern.**
12. **Use the existing versioning mechanism.** `Plugin::version()` reads the single `RANKKERNEL_VERSION` constant in `rankkernel.php`, and every asset URL is versioned from it. Bump that one value to bust caches.

### Known pre-existing inconsistency, do not "fix" in passing

`assets/css/monitor-admin.css` has **no import of the token layer** and uses raw hex, which contradicts the base file's own stated policy. This is recorded, not corrected. It is a deliberate future change, not a drive by cleanup.

---

## 6. JavaScript architecture

- Hand written **classic scripts** against `wp.*` globals. **No bundler, no JSX, no Tailwind.**
- Feature scripts live in `assets/js/`, for example `instant-indexing-admin.js`, `redirects-admin.js`, `settings-admin.js`, `monitor-admin.js`.
- **Write values into the DOM with `textContent`, never `innerHTML`.** A stored URL is user input and must never be parsed as markup.
- **Progressive enhancement only.** The server rendered page must work without JavaScript:
  - `instant-indexing-admin.js` enhances a real `method="get"` filter form and real pagination anchors. If JavaScript is off, the page still filters and pages.
  - `settings-admin.js` swaps sections over the network and falls back to a full page load when the request fails.
- **Guard against races.** Both `analysis-editor.js` (a generation token) and `redirects-admin.js` (an `ajaxActive` flag) demonstrate the established pattern. A slow earlier response must not overwrite a newer one.
- **Accessibility announcements.** There are already **two differently named** helpers in the codebase, which is an unfixed inconsistency rather than a settled convention:
  - `assets/js/monitor-admin.js` uses `rkAnnounce`
  - `assets/js/redirects-admin.js` uses `rankernelAnnounce`
  Both wrap `window.wp.a11y.speak` with the same guard. Before adding a third, check which one the page you are working on should follow, and prefer converging on a single name rather than adding another variant. `tests/js/admin-announce.test.js` pins the spoken text and the literal call sites the WordPress string extractor depends on.
  **Open design decision**, recorded in section 15.
- **REST calls** send the standard WordPress REST nonce in the `X-WP-Nonce` header, localized from `wp_create_nonce( 'wp_rest' )`. Do not hand roll a second nonce model.

---

## 7. The two pages that already have an approved design

### 7.1 Redirects

**Purpose:** manage 301, 302, 307, 410 and 451 redirects, with CSV import and export.

**Artifact:** `docs/designcode/redirection-page.html`, six sections: Notice Examples, Page Header, Editor Form, Redirect Table, Import and Export Card, Redirect Settings Card.

**Structure, top to bottom:**

1. **Page header card.** A title, a count pill, and the primary and secondary actions on the right.
2. **Notice banners.** Five semantic types, each a soft tinted background with a 4px left border, a leading icon, text, and a dismiss control:
   - success, teal
   - error, red
   - chain warning, orange, with a recommended destination
   - loop warning, orange
   - info, blue
3. **Editor form.** Source, destination, type, match mode, and safety feedback inline.
4. **Redirect table.** Columns for the redirect itself, the match type, the status and the actions, with status rendered as pill badges keyed by HTTP code.
5. **Import and export card.** CSV import with a file zone, and export.
6. **Redirect settings card.** Rules per page and related options.

**Implemented safety behaviour the design surfaces:** equivalent redirect detection, cycle detection, chain detection, and destination suggestions. These are real backend behaviours, so the warnings in the design correspond to real checks.

**Interaction patterns:** collapsible cards, a card header that toggles, and a plain text collapse control rather than a styled button.

### 7.2 Instant Indexing

**Purpose:** control the IndexNow integration. Show whether a key is configured, allow key regeneration, submit URLs, and list the submission log.

**Artifact:** `docs/designcode/instant-indexing.html`, six page sections plus a full component sheet.

**Structure, top to bottom:**

1. **Page header card.** Title `Instant Indexing`, a status pill reading `Automatic submission on` or `Automatic submission off`, a one line description, and two controls: `Submit a URL` and a settings icon button.
2. **Notice row.** Success, error, warning and info banners, same anatomy as Redirects.
3. **Stats strip.** Four cards: total submissions, accepted, rejected, rate limited. Each caption states exactly what it counts.
4. **Submit URLs panel.** A textarea, one URL per line, live validation with per line status, and a submit button that is disabled until every URL is valid.
5. **Submission history card.** The dominant element. Search, a source select, status filter tabs carrying real counts, the table, and pagination. This was renamed from "Recent submissions" because the history is now unbounded, see section 9.
6. **Settings and key panel.** One container holding the Verification key group and the Automatic submission group.

**Interaction patterns:** the header controls open a **mutually exclusive panel switcher** above the log. Exactly one panel is open at a time, clicking the same control again closes it, and each panel has a plain text `Hide` control in its top right. Opening scrolls the panel into view, respecting `prefers-reduced-motion`.

**Real behaviours the design reflects, all verified in code:**

- the submission log is stored in its own table and keeps **every row until an admin clears it**
- filtering, counting and paging happen **server side in SQL**, and the same query layer serves both the page and the REST route
- the page works without JavaScript
- retry is offered on failed rows and appends a **new** row, leaving the original intact
- the API key is never rendered, only whether one is configured

**Do not invent here:** no quota, no success rate, no historical index rate, no bulk batch mode, no per engine destination column, no export or clipboard action. None of those exist.

---

## 8. Approved artifacts and where they live

| Artifact | Path |
|---|---|
| Redirects design | `docs/designcode/redirection-page.html` |
| Instant Indexing design | `docs/designcode/instant-indexing.html` |
| Instant Indexing prompts, early | `docs/designcode/instant-indexing-stitch-prompt.txt` |
| Instant Indexing prompts, v4 | `docs/designcode/instant-indexing-stitch-prompt-v4.txt` |
| Instant Indexing prompt, full spec | `docs/designcode/instant-indexing-stitch-prompt-spec.txt` |

Both HTML artifacts were searched and contain **no fabricated functionality**: no quota, no success rate, no bulk batch mode, no export or clipboard action, no masked key field, no key filename, no virtual location string.

Note that `docs/` is internal working material. It is not shipped, and the repository convention is that the owner updates it.

---

## 9. Labels must be truthful

The submission log was once a 50 row ring buffer in an options row, which is why the UI said "Recent submissions". That storage is gone. The log now lives in a table and keeps everything until cleared.

Rules that follow from this:

- Do not say **recent**, **last 50**, or anything implying a capped window, because there is no cap.
- The **full history** count, the **filtered** count, and the **current page** count are three different numbers. Label each as what it is.
- Do not invent metrics. Stats must describe real stored rows.

---

## 10. Accessibility expectations

- Every form control has a visible `<label for>` or a `screen-reader-text` label.
- Every icon only control has an `aria-label`.
- Interactive controls are keyboard reachable and show a visible focus state.
- A status change is announced with `role="status"` or `role="alert"` where appropriate.
- Tables use `scope` on header cells.
- **Colour is never the only carrier of meaning.** A status is a labelled pill, not just a colour.
- Respect `prefers-reduced-motion` for scrolling and animation.
- A toggle exposes its state, and a collapsible exposes `aria-expanded` and `aria-controls`.

The announcement helpers are inconsistent today: `monitor-admin.js` uses `rkAnnounce` while `redirects-admin.js` uses `rankernelAnnounce`. Check which one the page you are working on should follow, and prefer converging on a single name rather than adding a third variant. See section 15.

---

## 11. Responsive expectations

- The content area uses the available admin width. Do not cap the page at a narrow fixed width; an earlier cap was removed deliberately.
- Verify at roughly 1280px and at a narrow width.
- The header stacks on small screens rather than overflowing.
- Tables must not overflow horizontally without a deliberate, usable treatment.
- Nothing may overflow horizontally at narrow widths.

No formal breakpoint scale is standardised beyond what individual stylesheets do. **Open design decision.** If you standardise breakpoints, record the decision here.

---

## 12. Workflow for designing one page

Follow this order. Do not redesign every page in one pass.

1. Read this document completely.
2. Read the actual page: its view template in `src/Admin/Views/`, its page class in `src/Admin/`, its stylesheet, its script.
3. Inspect the real functionality. Find where each displayed value comes from.
4. Inspect the existing CSS and the shared `rk-ui-*` layer.
5. Inspect the Stitch reference for that page, if one exists.
6. List mismatches between the mockup and real functionality. **Discard mockup elements with no real backing.**
7. Define the change as **UI only**.
8. Implement using the existing architecture: tokens, `rk-ui-*`, a page scoped stylesheet, progressive JavaScript.
9. Verify desktop.
10. Verify narrow width.
11. Verify keyboard navigation.
12. Verify screen reader relevant states.
13. Verify responsive states.
14. Verify the no JavaScript path.
15. Run `composer lint`.
16. Run `composer stan`.
17. Run `composer test`.
18. Run `composer test:js`.
19. Visually inspect the page.
20. Review the diff for accidental scope.
21. Merge only after verification.

---

## 13. Commands

```
composer lint      # phpcs, WordPress ruleset
composer stan      # PHPStan level 6
composer test      # PHPUnit
composer test:js   # node --test
find assets/js -name '*.js' -print0 | xargs -0 -n1 node --check
```

Repository conventions: PSR-12 inside `src/`, `declare(strict_types=1)`, namespace `RankKernel`, one class per file, the `rankkernel` text domain, and an `ABSPATH` guard in every PHP file.

**Writing style, enforced in this repository: zero standalone dashes.** No em dash, no en dash, and no hyphen used as a pause, in code, comments, strings or documentation. Hyphens are permitted only inside compound words and identifiers.

**Branch and PR convention:** issue backed work uses a `GH-<number>` branch, a `GH-<number>` prefixed PR title, and a `Closes #<number>` line in the body. Issue free maintenance, including documentation, uses a conventional title with no issue id and does not invent a fake issue.

---

## 14. What is implemented versus designed versus mockup only

### Implemented and shipped

- the Redirects page, including its safety checks
- the 404 Monitor page
- the Dashboard
- the Metadata editor sidebar and classic metabox
- the Schema settings page
- the Sitemap settings page
- the Instant Indexing page, including the submission table, the SQL query layer, the REST read endpoint, the AJAX enhancement, the no JavaScript fallback, and the panel switcher
- the shared `rk-ui-*` component layer and the token layer

### Approved visual direction, applied to the two pages above

- the Redirects and Instant Indexing designs, as described in section 7

### Mockup or prompt only, not a requirement

- any quota, success rate, historical index rate, bulk batch mode, per engine destination, export log, clipboard action, instances counter, protocol version string or cryptographic token claim
- any key field or key filename display
- all other Stitch screens that were never implemented

---

## 15. Open design decisions

These are genuinely undecided and need a human decision before being treated as rules:

1. **Breakpoint scale.** No standardised set exists.
2. **Unifying the older pages.** Redirects, Monitor and Dashboard each define some components locally under a page scope. Whether to migrate those to `rk-ui-*` is open. Doing so requires a deliberate pass, because their local specs differ from each other.
3. **The Monitor token gap.** `monitor-admin.css` does not import the token layer and uses raw hex.
4. **Whether the `rk-ui-*` layer should be adopted by every page,** or only by pages that opt in as they are redesigned.
5. **The announcement helper name.** `monitor-admin.js` uses `rkAnnounce` while `redirects-admin.js` uses `rankernelAnnounce`, and a third page was about to add a third variant. Pick one name and converge, rather than letting each page invent its own.
6. **The announcement helper location.** The Redirects helper sits outside its IIFE while the Monitor one sits inside. Decide the intended scope before copying either.

---

## 16. The short version

- Use the existing tokens, never new colours.
- Use `rk-ui-*`, never a new copy of a shared component.
- Never make a page's class names global.
- Never implement a mockup element with no real functionality behind it.
- Write `textContent`, never `innerHTML`.
- Keep the page working without JavaScript.
- Label counts as what they actually are.
- Test keyboard, screen reader, narrow width, and no JavaScript.
- Run all four gates.
- No standalone dashes.
