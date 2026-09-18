# Rank Math — Per-Post SEO Editing Experience (UX Study)

Research-only study of the observable, user-facing per-post/page SEO editing workflow in Rank Math SEO (free + Pro), written so RankKernel can reproduce the **workflow** with its own code, UI and branding. Behaviour is described; competitor source was inspected read only to confirm it, and no competitor code, class names, CSS, HTML or UI wording is reused.

- **Date compiled:** 2026-09-16
- **Method:** Official Rank Math Knowledge Base / docs pages + public blog posts, cross-checked against the installed plugin source (read only to confirm behaviour, not copied). Where source and docs disagree or something could not be confirmed, it is flagged in **Unconfirmed**.
- **Key sources:**
  - KB: `on-page-seo` ("Understanding Rank Math's Meta Box Appearing in Single Posts & Pages"), `general-tab`, `advanced-tab`, `meta-box-social-tab`, `seo-meta-tags`, `score-100-in-tests`, `variables-in-seo-title-description`, `how-to-add-twitter-cards`, `open-graph-meta-tags`, `disable-rank-math-meta-box`, `advanced-mode`, `how-to-share-content-on-social-media`.
  - Docs: Rank Math "Filters and Hooks → Gutenberg".
  - Plugin source (behaviour confirmation only): `includes/admin/metabox/*`, `assets/admin/js/gutenberg.js`, `assets/admin/js/rank-math-app.js`, `assets/admin/js/classic.js`, `includes/modules/schema/**`.

---

## 1. Where the SEO editing surface lives (block editor)

Rank Math injects a **Gutenberg Plugin Sidebar** (the right-hand editor sidebar), not a block or a document-settings panel.

| Editor | Surface | How the user opens it | Notes |
|---|---|---|---|
| Block editor (Gutenberg) | Right-hand editor sidebar panel named Rank Math / Rank Math SEO | Click the Rank Math icon in the top toolbar, **or** the three-dot (⋮) options menu → the Plugins list → Rank Math | Default location. A filter can move it to the bottom of the editor, but the default is the sidebar. |
| Classic editor | A draggable meta box titled Rank Math SEO in the normal (below-content) column, with the same tabbed UI | Scroll below the content area | See §12. |
| Elementor | A panel inside Elementor's own SEO/General settings area | Elementor panel | Out of scope here but same tab semantics. |
| Divi | Divi page-settings area | Divi settings | Out of scope here. |

The sidebar is a single mounted application that renders an internal **horizontal tab bar**; the tabs a user sees depend on capability and mode (Easy vs Advanced):

| Tab | Purpose | Shown when |
|---|---|---|
| General | Primary on-page fields + preview + focus keyword + content analysis | Always (if the user has any SEO capability) |
| Advanced | Robots / canonical / breadcrumb / redirect / front-end score | Only when the user has the advanced capability **and** the site is in Advanced Mode |
| Schema | Structured-data builder for the page | When the user has the snippet capability and schema data exists |
| Social | Facebook / Twitter share preview | When the user has the social capability |
| Content AI | AI assistant panel (separate module) | When Content AI is available |

---

## 2. Field order and grouping in the primary (General) area

The General tab is the default and contains, top to bottom:

| Order | Group | What the user sees | Where the editable inputs actually are |
|---|---|---|---|
| 1 | **Search/Social preview card** | A Google-style SERP mock plus a snippet editor button | Read-only preview. The button opens a **snippet editor modal** containing the real fields. |
| 2 | **Focus Keyword** | A tag-style multi-keyword input (first tag = Primary), optional keyword-intent button, optional keyword autosuggest, a content-AI slot, then a pillar content checkbox | Inline, always open |
| 3 | **Content analysis** | Score + accordion of SEO tests grouped by category | Inline (see §11) |
| 4 | Extensibility slots | Third-party/AI add-ons can inject rows | — |

**Important nuance:** in the **block editor sidebar**, the raw Title / Permalink / Description text inputs are **not inline**. The user only sees the preview; editing happens inside the modal opened by the snippet editor. The modal (the snippet editor) is itself a tab panel whose **General** tab holds, in this order:

| Order | Field | Notes |
|---|---|---|
| 1 | Preview (same SERP mock, with Desktop/Mobile toggle) | Read-only, live |
| 2 | **Title** | SEO title; single-line input |
| 3 | **Permalink** | Slug/URL; supports full path editing; disabled for the homepage and some post-type cases |
| 4 | **Description** | Meta description; textarea |

Each of Title / Permalink / Description has its own **length/pixel counter** and a **variable-insertion button** (§7). Completing Title and Description is optional — leaving them empty falls back to templates (§3, §4).

---

## 3. SEO title: editing, preview, and inherited-vs-overridden

- **Editing:** the title is edited in the modal's single-line **Title** field, or by editing the WordPress post title (Rank Math reads the WP title as the source value).
- **Preview:** the SERP mock shows the effective title, truncated to ~60 characters and with the focus keyword highlighted.
- **Inheritance signalling (key behaviour):**
  - The input is **empty** when the post has no manual title. Its **placeholder shows the resolved template** for that post type (e.g. a pattern like `%title% %sep% %sitename%`).
  - Therefore an empty field with a greyed placeholder means *inherited from the template*; a typed value means *manually overridden for this post*.
  - There is **no explicit badge/indicator** such as inherited versus custom; the user infers it from the empty field and placeholder.
  - The live preview always shows the resolved value (template when empty, custom when typed), so the user always sees the effective output.
- **Length guidance:** a counter shows character count and an approximate pixel width, with a valid range of roughly **15–60 characters / ~580px**; the indicator turns invalid when outside the range. Guidance text explains this is what appears in the first line of the search result.

---

## 4. Meta description: editing and length guidance

- **Editing:** textarea inside the modal's **Description** field. It supports variables and can be left blank.
- **Fallback chain when blank (documented):** custom description → description template → post excerpt → first paragraph of content. The variable `%excerpt_only%` forces strict use of the excerpt.
- **Length guidance:** a counter shows character count and approximate pixel width, valid roughly **80–160 characters / ~920px**, with an invalid state outside that range. KB guidance states the focus keyword should appear within roughly the first 120–160 characters.
- Character/pixel counters animate as a small progress bar and turn red when the value is too short or too long.

---

## 5. Search snippet preview

The preview is a mocked Google results page shown at the top of the General tab and at the top of the modal.

| Element | Present? | Detail |
|---|---|---|
| Mock search bar | Yes | A disabled search input that displays the current **focus keyword** (or the site name if none), plus a search/mic icon and Google-style menu tabs and a fake result count. |
| Favicon / site icon | Yes | Uses the WordPress site icon, with a fallback placeholder. |
| URL / path | Yes | Displays the permalink (domain + path), truncated (~75 chars). |
| Title | Yes | Truncated ~60 chars, focus keyword highlighted. |
| Description | Yes | Truncated ~160 chars, focus keyword highlighted. |
| Breadcrumb row | No separate row | Breadcrumb path appears only insofar as the permalink shown includes it; there is no dedicated breadcrumb line in the snippet. |
| Rich-result extras | Yes, when applicable | If the page has structured data (e.g. rating), a star-rating row is rendered above the description. |
| Noindex state | Yes | When the page is set to noindex, the preview is visually marked noindex and shows an explanatory overlay pointing the user to the Advanced tab. |
| Live updating | Yes | The preview is driven by the editor data store; typing in the fields updates it immediately. |
| Desktop / mobile variants | Yes | A **Desktop / Mobile** toggle (desktop and mobile icons) lives in the **modal's** preview header and restyles the preview width. The inline sidebar preview does not expose this toggle. |
| Score badge | Partial | The sidebar's preview header shows a numeric SEO score (e.g. a score out of 100); the modal preview header swaps score for the device toggle. |

---

## 6. Social preview surface

The **Social tab** exists as its own meta-box tab, but its content is largely launched through the same snippet-editor modal opened on the **Social** sub-tab.

- **Networks:** Facebook and Twitter/X only (no LinkedIn, WhatsApp, etc. in the editor preview).
- **Top-level Social tab content:** a short explanation plus a button that opens the modal directly on the Social view. (The modal's own tab bar also has a Social entry.)
- **Inside the Social view:** a two-tab panel — **Facebook** (default) and **Twitter**.

| Field | Facebook | Twitter | Notes |
|---|---|---|---|
| Share image | Yes | Yes (unless the reuse Facebook data toggle is on) | Upload / replace / remove; recommended size ~1200×630; warns if the image is smaller than the ~600×315 minimum. Falls back to featured image, then first image, then global default OG image. |
| Title | Yes | Yes | Placeholder is the SEO title from the General tab; typing overrides it for social only. |
| Description | Yes | Yes | Placeholder is the SEO description; Twitter clips long descriptions in its preview. |
| Author / handle | Yes (author name shown) | Yes (Twitter handle) | Preview decoration. |
| Reuse Facebook data | — | Yes | When on (default), Twitter reuses Facebook's title/description/image; turning it off exposes independent Twitter fields. |
| Card Type | — | Yes | Summary, Summary with Large Image, App, Player — selecting App/Player reveals additional app- and player-specific fields. |
| Icon overlay / watermark | Yes | Yes | Default icon overlays available; custom watermark selection is a Pro feature; can be disabled per post. The KB notes the option appears under an icon overlay control. |
| Live preview | Yes | Yes | Renders a realistic Facebook/Twitter post card using the current values, with `swap-preview` behaviour. |

---

## 7. Dynamic variables (tokens)

- **Insertion control:** each variable-capable field (Title, Description, and schema fields) has a small **chevron button** on the right edge. Clicking it opens a **dropdown panel** containing:
  - a **search box** that filters the token list as the user types,
  - a scrollable **list of tokens**, each showing the token **name** and a short **description**.
  - Clicking an entry **appends the token** (e.g. `%title%`) to the field at the cursor/end. There is no modal variable picker and no drag/drop; it is an inline dropdown.
- **Token syntax:** percent-delimited, e.g. `%title%`, `%sep%`, `%sitename%`, `%excerpt%`; parameterised tokens exist for dates, categories/tags, custom fields, counters and product fields.
- **Roughly which tokens are offered (≈53):** separator; search query; counter; file name; site title/description; current date/day/month/year/time (and advanced time format); organization name/logo/URL; post title and parent title; excerpt and excerpt-only; URL; thumbnail; published/modified dates (with formats); post category / categories (and advanced) / tag / tags (and advanced); current term / term description / custom term / custom term description; author ID / name / description; post ID; focus keyword and focus keywords; custom field (advanced); page / page number / total pages; post-type singular & plural; group name/description (BuddyPress); WooCommerce price / SKU / short description / brand. Pro adds random word, image alt and image title. Custom variables can be registered by developers.
- **Exclusions:** the same field-type excludes its own tokens (e.g. the title field does not offer title/description tokens).

---

## 8. Advanced metadata (canonical, robots, index directives)

All of this lives in the **Advanced tab**, which is a *separate tab*, not mixed into the primary General fields. Order within the Advanced tab (top to bottom):

| Order | Control | Behaviour |
|---|---|---|
| 1 | **Robots Meta** (checkbox group) | Index, No Index, Nofollow, No Archive, No Image Index, No Snippet. Index/No Index are mutually exclusive; each has an inline help tooltip explaining the effect. |
| 2 | **Advanced Robots Meta** | Max Snippet (number, default -1 = unlimited), Max Video Preview (number seconds), Max Image Preview (Large / Standard / None). Each row has a checkbox enabling it plus the value control. |
| 3 | Googlebot-News index | Pro / news-sitemap only. |
| 4 | **Canonical URL** | URL field; placeholder is the current permalink; explains that it tells crawlers the main page when duplicate content exists. |
| 5 | **Breadcrumb Title** | Only shown when breadcrumbs are enabled site-wide. |
| 6 | **Redirect** | Toggle, then Redirection Type (301/302/307/410/451) and Destination URL (destination hidden for 410/451). Only when the user has the redirection capability and the module is active. |
| 7 | **Show SEO Score on Front-end** | Toggle, only when the score-enabled setting is on. |

The **Advanced tab itself is hidden entirely** for users without the advanced capability and for sites in **Easy Mode** — beginners never see robots/canonical controls. A noindex notice in the General preview tells them where it can be changed.

---

## 9. Reset / restore default behaviour

- There is **no dedicated per-field reset or restore template button** on the snippet fields. The observable mechanism is:
  - **Clear the field** → the value reverts to the inherited template/default, the placeholder (the template) becomes visible again, and the live preview immediately shows the template output.
  - For social fields, the placeholder is the General SEO title/description and behaves the same way (empty = inherit).
- **Remove-image buttons** exist for social share images (remove returns to the featured-image / default OG fallback).
- Disabled states are used instead of resets where an action is not allowed (e.g. the homepage permalink field is disabled with an explanatory note).
- Advanced robots values have implicit defaults (unlimited snippet, unlimited video, large image) applied when a directive is turned off.

---

## 10. Beginner simplicity vs advanced toggles

| Mechanism | What it hides/shows |
|---|---|
| **Easy Mode vs Advanced Mode** | In Easy Mode the entire **Advanced tab** (robots, canonical, breadcrumb, redirect, front-end score) is removed from the sidebar, so the beginner only sees General + Social (+ Schema where relevant). |
| **Capability gating** | Each tab/section is individually gated by capability (general / advanced / snippet / social / analysis / analytics / content-AI); users only get the surfaces they need. |
| **Preview-first General tab** | The primary tab leads with a visual SERP preview and a single **Edit Snippet** button; the raw title/permalink/description inputs are tucked inside a modal so the default view stays visual and uncluttered. |
| **Optional fields** | Title and Description may be left empty; the plugin fills them from templates/excerpt/content, so a beginner can publish without touching them. |
| **Progressive disclosure inside controls** | Advanced Robots rows and the Twitter card-type's app/player fields only appear when their enabling checkbox/toggle is switched on; redirection destination hides for 410/451. |
| **Schema tab gating** | Only shown when the user has the snippet capability and the page has schema data. |

---

## 11. Content analysis area (placement only)

For RankKernel planning, the analysis lives **inside the General tab**, directly under the Focus Keyword group, and only renders when the user has the analysis capability.

Observable shape (enough to know where a RankKernel analysis section would sit):

- A **score** value (X / 100) and a colour band (red / yellow / green) — the score is also surfaced in the preview header when the sidebar preview is shown.
- A set of **collapsible groups**, each rendered as an accordion row with a title and a status badge that reads either all good or a count of errors:
  - **Basic SEO**
  - **Additional**
  - **Title Readability**
  - **Content Readability**
- Inside each group, individual **tests** appear as list rows with a pass/warn/fail icon, an explanation sentence, and a read more link to the relevant KB. Some tests show a partial score.
- Tests are **keyword-aware**: selecting a different focus-keyword tag filters the tests/results to that keyword; the primary keyword drives certain tests (title/description/URL placement).
- There is a highlight in editor affordance for some content tests.
- In RankKernel terms: this is a **self-contained section rendered as the sibling/child of the Focus Keyword group inside the primary tab**, not a separate tab.

---

## 12. Classic editor equivalent

| Aspect | Block editor | Classic editor |
|---|---|---|
| Surface | Right-hand sidebar (`PluginSidebar`), hidden behind the toolbar icon / options menu | Native **draggable meta box** titled Rank Math SEO in the normal column, **below the content editor** |
| Default visibility | Collapsed until the user opens the Rank Math panel | Always rendered as a boxed panel on the edit screen |
| Tab set | General, Advanced, Schema, Social | Same tabs |
| Primary fields | Inside the snippet editor modal | Inside the snippet editor modal (same app) |
| Link Suggestions | In-sidebar panel | A **separate side meta box** with link suggestions in the Classic editor |
| Extra | — | When the Gutenberg sidebar integration is disabled by filter, the same meta box is rendered at the bottom of the block editor too |
| Content AI | Always in the right sidebar of the block editor | Inline in the classic meta box |

Implementation note (behaviour): when the block editor is active, the plugin registers its classic meta box with a flag that tells Gutenberg not to render the native meta box (the JS sidebar replaces it). Elementor/Divi have their own panels.

---

## RankKernel implications

### UI elements required for functional parity

- **A per-post SEO surface in the block editor sidebar** registered as a plugin sidebar reachable from the top toolbar **and** the three-dot Plugins menu, plus a **native meta box fallback for the Classic editor** below the content.
- **Tabbed structure**: a primary General tab, a separate Advanced tab, a Social tab, and a Schema entry — each independently capability-gated, with the Advanced tab hidden entirely in Easy Mode.
- **A visual SERP snippet preview at the top of the primary tab**, showing favicon, URL/path, title, description, with focus-keyword highlighting, truncation at the documented limits, a keyword in a mock search bar, and a clear noindex state overlay.
- **A single snippet editor entry point** that opens a modal containing Title, Permalink and Description inputs in that order, each with a **live character/pixel counter** and an invalid state, plus a **Desktop/Mobile preview toggle** in the modal.
- **Template inheritance signalling**: fields empty by default, placeholder = resolved template, and a live preview that switches between template and custom output as the user types/clears.
- **A focus-keyword input** supporting a primary (first, marked) keyword and secondary keywords, plus a Pillar Content checkbox and a per-keyword analysis filter.
- **A social sub-tab pair (Facebook and Twitter)** with image upload/replace/remove, title, description, an optional reuse Facebook data toggle for Twitter, a card-type selector, and realistic live share-card previews.
- **A token-insertion control on every variable-capable field**: a chevron button opening a searchable dropdown listing token name + description, appending `%token%` on click.
- **Content-analysis section inside the primary tab beneath the focus keyword**: score + colour band + collapsible groups with all good or issue count badges and per-test rows.
- **Capability + Easy/Advanced-mode gating** so the primary flow stays visual and short by default.
- **Clear-field-to-restore-inheritance** semantics (no dedicated reset button needed) and disabled states with explanatory notes where editing is not allowed.

### Things to deliberately do differently / more simply

- **Keep the primary fields always editable inline** — Rank Math hides Title/Permalink/Description behind a snippet editor modal. We can show them directly in the panel (fields + counters) with the preview above, removing a click and a modal. This is the single biggest workflow simplification we can make without losing parity.
- **Show a small inherited or custom chip** next to Title and Description instead of relying on the user noticing an empty field with a placeholder. This makes override state explicit and is more discoverable than Rank Math's convention.
- **Reduce the mock-search-bar chrome** (Google menu tabs, fake result counts, mic icon) to a clean, brand-neutral preview: favicon, URL, title, description + desktop/mobile toggle. Parity of information, much less imitation.
- **One social preview pane with a platform switch** rather than nested Facebook/Twitter sub-tabs inside a modal inside a tab; keep image/title/description visible and swap the card frame per network.
- **Collapse robots/canonical into a single "Advanced" section with plain-language presets** (e.g. a primary index/noindex switch plus an expandable extra directives area) instead of six flat checkboxes plus three more number/select controls.
- **Prefer human-readable token labels** (e.g. post title) with the raw token on hover, and keep the token list grouped by category in the dropdown — same capability, clearer scanning than a single flat list.
- **Design the analysis section as an independently replaceable module** so RankKernel can evolve its own scoring without coupling it to the field UI (Rank Math's analysis is entangled with the same store/preview).
- **Skip Pro-targeted upsell clutter** (rating requests, upgrade modals, keyword-count CTAs) in the default editing surface; expose extensions in a single place.

---

## Unconfirmed

- **Exact tab order in the UI.** Source builds the tab list as General → Advanced → Social and injects Schema at index 2 (between Advanced and Social), giving General → Advanced → Schema → Social. The KB screenshots suggest a General/Advanced/Schema/Social ordering, but the visible order can vary with capabilities and which Pro modules are active. Treat the order as "General first, Advanced second, Schema and Social after" rather than fixed.
- **Whether the inline sidebar preview ever exposes a desktop/mobile toggle.** Source shows the toggle only in the modal; the sidebar preview is rendered without it. Not independently verified in a live installation here.
- **Precise rendered pixel-width thresholds** for the title/description length indicators (the field metadata uses ~580px for title and ~920px for description, with 15–60 and 80–160 char ranges). These are display heuristics and may be tuned between plugin versions.
- **Exact set and ordering of tokens shown in the dropdown.** The KB list (~53 tokens) and the source confirm the general set; the live dropdown also includes locale/vertical-dependent tokens (WooCommerce, BuddyPress) that only appear when those contexts apply.
- **Whether any Rank Math surface offers a true "reset to template" button.** None was found for the snippet fields; only "clear the field" and social "remove image". A schema-specific reset may exist inside the Schema builder but was not confirmed.
- **Elementor/Divi field ordering.** Their panels reuse the same components, but the panel layout differs and was not studied in detail here.
- **Any block-editor pre-publish-checklist SEO integration.** Rank Math publishes social-share actions after publish; a native pre-publish SEO checklist was not confirmed from the sources.
