# Yoast SEO — Per-Post / Per-Page Editing Experience (behaviour study)

Research-only study of the observable, user-facing editing workflow of Yoast SEO in the post/page editor. Goal: functional parity for a future RankKernel experience (our own code, UI and branding), not a pixel clone.

## Scope, sources and method

- Installed **Yoast SEO Free 28.4** and **Yoast SEO Premium 27.8** at `wp-content/plugins/wordpress-seo/` and `wp-content/plugins/wordpress-seo-premium/`.
- Admin JS bundles were inspected only to confirm *observable behaviour* (which surfaces exist, field order, live-update and colour semantics of guidance). No source, class names, CSS, HTML or distinctive UI copy is reproduced.
- Official documentation and public posts: the help articles on search/social appearance, snippet templates, snippet variables and the bulk editor; the blog posts on the search-appearance preview, snippet variables and social previews.
- Where a behaviour could be confirmed in both code and docs it is stated plainly. Anything that could not be confirmed is listed under **Unconfirmed** at the end.
- Field labels below are the generic industry names for the inputs (focus keyphrase, SEO title, slug, meta description, canonical URL, meta robots, breadcrumbs title). They are used descriptively, not as copied UI strings.

---

## 1. Where the editing surface lives

Yoast presents two editor surfaces built from the same underlying components:

| Editor | Surface | Name shown to the user | Behaviour |
|---|---|---|---|
| Block editor (Gutenberg) | Plugin **sidebar** (top-right editor sidebar, opened from the Yoast icon in the editor toolbar) | The "Yoast SEO" sidebar | Primary surface in the block editor. A single vertically-scrolling panel of collapsible sections. |
| Block editor (optional) | **Meta box** below the content, registered as block-editor-compatible | The "Yoast SEO" meta box | Still registered; can be surfaced as a bottom panel. Content mirrors the sidebar. |
| Classic editor | **Meta box** below the content | The "Yoast SEO" meta box | Primary surface. Uses a visible tab strip instead of stacked collapsibles. |
| Elementor / page builders | Integration panel | Yoast panel | Same components, embedded in the builder UI. |

The sidebar is registered for all supported post types, and social/schema/advanced sections appear conditionally (see below). The meta box is only rendered when the post type is supported and the user/editor context allows it.

---

## 2. Field order and grouping in the SEO surface

The SEO tab/section is the default open view. Top-to-bottom order the user sees in the block-editor sidebar:

| Order | Group / section | Fields and controls | Default state |
|---|---|---|---|
| 1 | Focus keyphrase | Focus keyphrase input; adjacent area for related keyphrases / premium keyphrase tooling | Open |
| 2 | Search appearance | Google/snippet preview (with desktop/mobile switch) and the editable snippet fields below it | Open |
| 2a | ├ Snippet fields | **SEO title**, **Slug**, **Meta description** (in that order) | — |
| 2b | ├ Length guidance | A thin horizontal progress bar under the SEO title and under the meta description | — |
| 3 | SEO analysis | Keyphrase-based checklist + overall score | Open |
| 4 | Readability analysis | Readability checklist + score (free) | Open |
| 5 | Inclusive language (conditionally enabled) | Checklist + score | Open |
| 6 | Social media appearance (only when Open Graph or X/Twitter sharing is enabled in site settings) | Facebook/Open-Graph fields, then a separate X/Twitter group | Open (X group collapsed) |
| 7 | Schema | Page type, article type | Collapsed |
| 8 | Advanced | Robots/index directives, canonical URL, breadcrumbs title | Collapsed |
| 9 | Cornerstone content | Single "mark as cornerstone" toggle | Visible when the feature is enabled |
| 10 | Premium/insight sections | Prominent words, reading time, estimated SEO performance, internal-linking upsell, AI generator entry points | Varies |

Notes:
- The **slug** field is edited inside the snippet editor and is kept in sync with the WordPress post slug (editing it here also changes the permalink).
- Both free and premium use this grouping. Premium inserts additional sections/callouts (related keyphrases, insights, internal linking, schema extras) but does not change the core order of keyphrase → snippet → analysis.
- In the **classic meta box**, the same sections are split across a tab strip: an SEO tab (keyphrase + snippet + analysis + advanced disclosure), plus Readability, optionally Inclusive language, Schema and Social tabs.

---

## 3. SEO title editing, preview, and template-vs-override

**Editing.** The SEO title is a rich, variable-aware input: it accepts plain text, snippets/variables and emoji. Variables are shown inline as tokens rather than raw placeholder text.

**Preview.** The title is rendered live in the search-appearance preview above/below the field, resolved exactly as it would appear in a result (variables substituted). The preview updates as the user types (input is debounced).

**Length guidance — the coloured bar.** Under the SEO title field there is a thin horizontal progress bar with a small caption indicating it measures the **width of the SEO title**. Its colour communicates the assessment:

| Bar colour | Meaning | Assessment score band |
|---|---|---|
| Green | Good | high band |
| Orange | Needs improvement (borderline: slightly short/long) | middle band |
| Red | Bad (e.g. clearly too long, will be truncated) | low band |

The progress fraction is **pixel-based**: the title is measured as rendered text width against a maximum (approximately 600 px in the inspected version), *not* a raw character count. The title also has a too-short warning.

**How the user knows whether the value is a template value or a manual override.** There is no per-field "template" badge on the input itself. The distinction is communicated by:
- the resolved value being shown in the preview even when the field holds no custom value (i.e. the site-wide template is being used);
- an editor state/notice that the post is currently using **default SEO data**, with an action to start writing custom SEO data (shown as a callout in the sidebar/pre-publish checks);
- clearing the field returning the post to the template value.

The coloured bar communicates **length quality**, not template status. The template/default status is a separate signal.

---

## 4. Meta description editing and length guidance

**Editing.** The meta description field sits below the slug in the snippet editor. It is a multi-line-capable text field that also accepts snippets/variables.

**Guidance.** Under the field there is the same coloured progress bar pattern, captioned to indicate it measures the meta-description length. Guidance is **character-count based** here (not pixel width): the bar fills against a recommended maximum (approximately 156 characters in the inspected version) and shows green/orange/red using the same score bands as the title.

- When empty, the editor shows an encouraging placeholder/prompt to add a description (and the preview makes clear search engines may generate one instead).
- The field is single-column and updates the preview live.

---

## 5. The search-appearance (Google) preview

| Property | Behaviour |
|---|---|
| Where it appears | Inside the Search appearance section of the sidebar/meta box, above the editable snippet fields; also openable as a focus modal via a "Google/search appearance preview" entry point. |
| What it shows | The SEO title, the URL/slug (desktop), and the meta description. The mobile variant additionally shows a favicon and the preview image (featured/fallback). |
| Live update | Yes — title, slug and description changes are reflected as the user types. |
| Device variants | Yes: a desktop/mobile switch (segmented control / radio buttons) with accessible labels describing the current mode. |
| Mobile vs desktop differences | Mobile leads with the site/URL and slug and includes favicon + image; desktop shows the URL/breadcrumb line under the title. |
| Honesty caveat | The UI/docs note that Google may rewrite titles/descriptions, so the preview is indicative. |

---

## 6. Social preview surface (Premium vs Free)

**Networks.** Facebook (Open Graph) and X/Twitter. Other networks (LinkedIn, WhatsApp, Slack, Pinterest, etc.) consume the Open Graph/Facebook data; there is no separate per-network editor for them.

**Fields.** For each network the user can set:
- a social **title**,
- a social **description**,
- a social **image** (select from media library, replace, remove).

**Image override.** Image selection opens the WordPress media library; the chosen image is used for that network. Size/shape warnings are surfaced when the image does not match the recommended aspect/size. Selecting a Facebook/Open-Graph image also feeds X and other networks unless the X-specific image is set — the UI explains this inheritance.

**Differences between Premium and Free (confirmed from packaging and docs).**

| Aspect | Free | Premium |
|---|---|---|
| Social title/description/image fields | Yes (when Open Graph / X sharing is enabled) | Yes |
| Image select/replace/remove + size warnings | Yes | Yes |
| Rendered share **preview card** for Facebook | The dedicated live share-preview surface is provided by the premium bundle | Yes — dedicated Facebook/Open-Graph share preview |
| Rendered share preview for X | Same as above | Yes — dedicated X share preview |
| X inheritance messaging | Present | Present |

In short: the *data fields* for social sharing are shared; Premium adds the polished, network-accurate **preview surfaces** ("Social share preview" / "X share preview") that render the card, and premium's editor bundle is what loads the richer preview components in the editor.

---

## 7. Inserting replace variables / snippets

**Insertion control.**
- Each title/description field has an **"insert variable"** affordance (a button adjacent to the field).
- Typing the `%` character in the field also triggers an inline **suggestion dropdown** (searchable list of variables).
- Recommended variables for the current field/context are highlighted; a search box filters the list.
- Inserted variables appear as inline tokens/pills in the field and are resolved in the preview.

**Variables offered** (from the plugin and official variable list):

| Type | Variables |
|---|---|
| Basic | date, title, parent title, archive title, site title, tagline, excerpt, excerpt-only, tag, category, primary category, category description, tag description, term description, term title, search phrase, separator |
| Advanced | post type (singular), post type (plural), modified, ID, author nicename, user description, page number, page total, page number (plain), caption, focus keyphrase, term404, custom field (`cf_…`), custom taxonomy (`ct_…`), custom-taxonomy description |
| Custom | Any custom field / custom taxonomy discovered by name, plus developer-registered variables |
| WooCommerce (add-on) | product category, product tag, product attribute; WooCommerce SEO adds short description, SKU, brand, price |
| Deprecated | current time/date/day/month/year (insertable historically; no longer resolved into the preview) |

The editor resolves context-specific variables per post type/taxonomy (e.g. parent title only for hierarchical content; term variables only on term screens).

---

## 8. Advanced metadata exposure

Advanced metadata lives in a **collapsed "Advanced" disclosure**, rendered inside the SEO panel in the block editor and inside the SEO tab in the classic meta box. It only renders when the user has the advanced-metadata capability and the site-wide "disable advanced meta" setting is off (otherwise it is hidden entirely or replaced by a notice).

| Control | Type | Options / behaviour |
|---|---|---|
| Index directive ("Allow search engines to show this content?") | Select | Default for the post type (shows the current site-wide default), Yes (index), No (noindex) |
| Follow directive ("Should search engines follow links on this content?") | Select | Default/follow, No (nofollow) |
| Meta robots advanced | Multi-select | No Image Index, No Archive, No Snippet |
| Canonical URL | URL text field | Overrides the canonical for this URL |
| Breadcrumbs title | Text field | Title used for this page in breadcrumb trails |
| 301 Redirect | URL field (redirect handling) | Present in the advanced field set; actively managed by Premium's redirect tooling |

A sitewide noindex warning is shown inline when the whole site is set to noindex.

**Schema** is a *separate* collapsed section (not the Advanced disclosure): it exposes a page-type selector and, for article-like content, an article-type selector, each with a "default for this post type" option. It is where structured-data type is chosen; nothing else is hidden there.

---

## 9. Reset / restore-default behaviour

What the user can actually do to "get back to defaults":

- **Clear the field** — emptying the SEO title / meta description / social fields reverts that item to the site-wide template (or to the inherited social/meta fallback). This is the primary per-post reset mechanism.
- **Default options in Advanced/Schema** — the index-directive selector offers a "Default for [post type] (currently: …)" choice, and the schema page-type selector offers a "default for this post type" choice. Choosing these restores the site-wide default.
- **Editor default-state signal** — when no custom value is stored, the editor flags that default SEO data is in use and offers to write custom data; this is the visible counterpart of "this is currently a template value".
- **Site-wide template reset** — the template fields in the settings can be manually reset by entering the documented default variable strings for each context (posts, archives, taxonomies, search, 404, author, date, homepage).

**No dedicated per-field "Reset" button** was found in the installed per-post editor (search and JS inspection) — reset is achieved by clearing the input or choosing a "default" option.

**Free vs Premium difference:** no confirmed behavioural difference in reset/restore was found in the installed versions. (Premium adds the social override fields, which can likewise be cleared to fall back.) See **Unconfirmed**.

---

## 10. How the interface keeps the primary flow simple

The primary flow is deliberately narrow: **keyphrase → title/slug/description with preview → analysis feedback → publish**. Everything non-essential is progressively disclosed:

- **Hidden behind the Advanced disclosure:** index/follow directives, advanced robots directives, canonical URL, breadcrumbs title, redirect. Collapsed by default.
- **Hidden behind the Schema disclosure:** structured-data page/article type. Collapsed by default.
- **Hidden behind the Social section/tab:** per-network title/description/image overrides. Only shown when social sharing is enabled site-wide.
- **Capability-gated:** the whole Advanced set is removed for users without the advanced-metadata capability (or when disabled in settings).
- **Premium-gated:** related keyphrases, insights, internal-link suggestions, AI generation and richer previews appear as upsells/callouts rather than blocking the free flow.
- Analysis checklists sit below the editable fields so a user can ignore them and still complete the snippet.

---

## 11. Content analysis area (positioning only)

Within the same sidebar/meta box, directly under the snippet editor, the plugin renders its analysis as collapsible sections:

- **SEO analysis** — a checklist of keyphrase-based checks, each with a red/orange/green bullet, plus an overall score indicator; explicitly keyphrase-dependent (requires a focus keyphrase).
- **Readability analysis** — a parallel checklist with its own score (free).
- **Inclusive language** — an optional additional analysis (when enabled).

Each is a self-contained, collapsible block with its own score bullet; the overall score is surfaced as a coloured bullet in the section header/tab. This is the exact slot where a future **RankKernel analysis section** belongs: a sibling collapsible block between the snippet editor and the advanced/schema disclosures, using the same bullet+checklist convention.

*(Noted for positioning only; this study does not cover building the analysis logic.)*

---

## 12. Classic editor / meta box equivalent

- **Same components, different shell.** The classic editor shows the Yoast panel as a meta box below the content; the block editor shows it as a plugin sidebar (with the meta box optional). Sections behave identically.
- **Meta box uses a visible tab strip**: SEO, Readability, optionally Inclusive language, Schema, Social. The sidebar instead stacks the same sections as collapsibles in one scroll.
- **Advanced metadata** appears inside the SEO tab as a disclosure (same controls as the sidebar).
- **Differences observed:** the meta box shows editor-specific extras such as a featured-image notice for post types that support thumbnails; the tab strip makes the active area explicit, whereas the sidebar relies on collapsible headers and scroll position.
- **Block editor sidebar specifics:** opened via the Yoast icon in the editor toolbar; the plugin sidebar is the canonical surface, and the block-editor-compatible meta box can still be surfaced at the bottom of the editor.
- **Elementor** gets an equivalent integration panel; the classic editor's visual editor is also customised so marked text renders inside the editor.

---

## 13. Bulk editor (separate surface)

- **Location:** a standalone admin page under the Yoast SEO → Tools area, and also reachable from the Posts/Pages list via a bulk action. It is *not* part of the per-post editor.
- **Layout:**
  - Left-hand navigation for **content type** (posts, pages, custom post types such as products).
  - Top-level tabs for **Search appearance** and **Social appearance**.
  - A data table with selectable rows, search box and status filters (including "needs improvement" filters keyed to missing/over-long metadata).
- **Editable columns:**
  - Search appearance tab: **focus keyphrase**, **SEO title**, **meta description**.
  - Social appearance tab: **social title**, **social description**.
- **Editing model:** inline per-row edit, multi-select for batch edits, explicit save ("save edits"), warnings for unsaved changes, per-row/batch apply-discard, and per-row save confirmation.
- **Assists:** focus-keyphrase visibility, status filters, and (paid plans) AI generation of titles/descriptions with a character-limit warning for over-long titles.
- **Purpose:** quick site-wide metadata cleanup; the product docs explicitly steer deep per-page optimisation back to the individual editor with full feedback.

---

## RankKernel implications

**UI elements we must have for functional parity**

- A per-post/per-page SEO surface available both as a **block-editor sidebar** and as a **classic-editor meta box**, built from shared components.
- A **focus keyphrase** input at the top of that surface.
- A **search-appearance group** containing the editable **SEO title**, **slug** (synced to the WP slug) and **meta description** fields, in that order.
- A **live search-result preview** with **desktop and mobile** variants, updating as the user types; mobile variant showing favicon + image, desktop showing URL.
- **Variable-aware rich text inputs** for title/description, with an "insert variable" control, a searchable suggestions dropdown, support for typing `%`, and inline variable tokens resolved in the preview.
- The **full basic + advanced variable set** (including custom fields/taxonomies) exposed per field/context, with recommended variables highlighted.
- **Length guidance bars with green/orange/red semantics**: pixel-width based for the SEO title (max ≈600 px) and character-count based for the meta description (max ≈156 chars), plus a too-short signal.
- A clear **template-vs-override signal**: resolved template value shown in the preview and an editor notice/state when default SEO data is in use, with an action to write custom data.
- **Social surfaces** for Facebook/Open Graph and X/Twitter with title, description and image per network, image select/replace/remove, size warnings, X inheritance messaging, and a **rendered share-preview card**.
- An **Advanced disclosure** containing index directive, follow directive, advanced robots directives (noimageindex/noarchive/nosnippet), canonical URL, breadcrumbs title, and redirect; capability/setting-gated.
- A separate **Schema disclosure** with page-type (and article-type) selectors including a "default for this post type" option.
- **Default/restore affordances**: "default for this post type" options, field-clearing to revert to template, and documented default template strings.
- **Analysis sections** as collapsible blocks with per-check red/orange/green bullets and an overall score, positioned below the snippet editor — giving RankKernel's future analysis a natural home.
- A separate **bulk editor** admin page with content-type navigation, search/social tabs, keyphrase+title+description (and social) columns, inline + batch editing, search/status filters, and save-with-unsaved-change warnings.

**What we should deliberately do differently or more simply**

- **Skip the dual heavyweight surfaces if possible for v1**: one shared component set is fine, but we do not need to replicate every subtle sidebar-vs-metabox layout difference.
- **Make template-vs-override explicit and cheap**: a visible per-field "using template / customised" indicator with a one-click "use default" reset is clearer than Yoast's implicit clear-the-field model, and more discoverable.
- **Give real character and pixel counters** (numbers, not just a colour bar) alongside the bar, so guidance is unambiguous.
- **Prefer a single analysis engine with an extensible checklist** rather than replicating Yoast's separate SEO/readability/inclusive-language machinery; our own analysis hangs in the same slot.
- **Tone down promotional upsells** inside the editing flow; keep premium/AI entry points present but not interleaved into the primary path.
- **Avoid network ambiguity**: if we ever support more than Facebook/X, model networks explicitly rather than relying on Open-Graph inheritance.
- **Simplify the bulk editor to the essentials** (keyphrase, title, description, social title/description; inline + batch; save) without the full AI/tour scaffolding.
- **Self-host all assets and strings**; reuse no competitor markup, class names, CSS or copy.

---

## Unconfirmed

- The **precise free/premium boundary for the social preview card** in this exact version pair (Free 28.4 / Premium 27.8). The free plugin ships social form fields and some preview components, while premium ships the dedicated share-preview surfaces; the marketing pages now say social previews are included in the free plan. Treat the "free = fields only, premium = rendered card" statement as likely but not fully verified.
- Whether the **mobile preview image** is always shown or only when a suitable image exists (it falls back to a configured default/none); exact fallback rules were not fully traced.
- Any **free vs premium difference specifically in reset/restore-default** behaviour — none was found, but absence of evidence is not proof.
- Exact numeric thresholds used by the length assessments beyond the maxima observed (≈600 px title width, ≈156 description characters); the too-short cut-offs were not fully enumerated.
- The exact list/order of premium-only sections (related keyphrases, insights, internal-linking upsell, AI generator) can vary by feature flags, licensing state and plugin version.
- LinkedIn-specific preview details: the plugin exposes Facebook and X surfaces only; LinkedIn consumes Open Graph data. Any LinkedIn-specific preview UI is not part of this plugin's editor.
- Whether the block-editor meta box is **hidden by default** in every theme/plugin combination (it is registered as block-editor-compatible, but visibility depends on user settings/other plugins).
