# RankKernel Phase 2.3 Breadcrumbs: Research and Architecture Gate

Status: RESEARCH ONLY. No implementation. Awaiting owner approval.
Date: 2026-09-14. Branch: `main` at `f9e6562` (GH-13 merged). Feature: Phase 2.3 Breadcrumbs, the last unimplemented Phase 2 item.
Method: competitor research (Yoast, Rank Math), public source inspected where available, WordPress core reference, and a read-only audit of the existing RankKernel architecture.

Evidence tags: `[DOCS]` official documentation, `[SOURCE]` public source inspected, `[OBSERVED]` rendered or third party behavior, `[INFERRED]` reasoned, `[RKK]` existing RankKernel behavior, `[PROPOSED]` RankKernel design.

Clean room: behavior only. No competitor code, class names, CSS, JS, HTML, or schema copied.

---

## 1. Executive summary

Breadcrumbs are the only remaining Phase 2 feature. The audit confirms RankKernel currently ships only the schema half: `BreadcrumbPiece` emits a `BreadcrumbList` with just home and the current page, and it already exposes a filter seam (`rankkernel/schema/breadcrumb_trail`) explicitly reserved for a future breadcrumbs module. There is no visible trail, no shortcode, no block, and no settings.

Research conclusions:

1. Both Yoast and Rank Math treat breadcrumbs as free. There is no paid breadcrumb tier to match. Differentiation must come from markup quality, correctness, and performance, not from removing a paywall.
2. Both competitors build one shared trail and render it twice: once as visible HTML and once as `BreadcrumbList` JSON-LD. RankKernel already has the JSON-LD half, so the correct design is to build one trail and feed the existing piece, never to emit a second `BreadcrumbList`.
3. Both competitors ship weak accessibility markup. Yoast defaults to a flat `span` sequence; Rank Math uses `nav > p` with no `aria-current` and exposed separators. WordPress core's newer `core/breadcrumbs` block uses the correct `nav > ol > li` with `aria-current="page"` and a CSS variable separator. RankKernel should adopt the core-style accessible markup by default, which is a real improvement.
4. Neither competitor caches breadcrumbs and both regenerate per request. RankKernel's trail build reuses already loaded WordPress objects and needs no caching, so it stays cheap without new storage.
5. RankKernel needs no new database table, no new meta key, and only one small settings option. The existing `MetaPayload` `flags.breadcrumb_title` override already supports per-post or per-term breadcrumb labels.

Recommended scope: a `Breadcrumbs` module (default ON, near zero cost, matching the roadmap) with a context-aware trail builder, an accessible server-rendered renderer, a template tag, a shortcode, a server-rendered block, a settings section, and schema integration through the existing filter so the single `BreadcrumbList` node stays authoritative.

---

## 2. Current RankKernel breadcrumb implementation audit `[RKK]`

- `src/Modules/Schema/Pieces/BreadcrumbPiece.php`: builds `BreadcrumbList` with `@type`, `@id` `{base}#breadcrumb`, and `itemListElement` of `ListItem{position, name, item}`. `getId()` is `breadcrumb`. `isNeeded()` returns false when the `schema_breadcrumbs` setting is off, otherwise true only for `queriedType` in `post`, `term`, `archive` (pure `home` is skipped). `build()` produces a two-entry trail: home (site name, `home_url`) and current (`ctx->title()` with a `single_term_title` fallback, `ctx->permalink()`). It contains no hierarchy traversal: no `get_post_ancestors`, no `get_ancestors`, no term parent walk. Its docblock states the full trail belongs to the future breadcrumbs module.
- Filter seam already present: `apply_filters('rankkernel/schema/breadcrumb_trail', $trail, $ctx)` with entries shaped `{name, url}`. This is the integration point a visible trail builder must use.
- `src/Modules/Schema/Pieces/WebpagePiece.php`: adds `breadcrumb: {@id: base#breadcrumb}` link only when type is `post`, `term`, or `archive` and `schema_breadcrumbs` is on.
- `src/Modules/Schema/Generator.php` and `GraphNormalizer.php`: one `@graph`, pieces registered by id, `is_needed` then `build`, then dedupe by `@id` (first wins) and prune dangling internal refs. Filters `rankkernel/schema/disabled`, `rankkernel/schema/needs_{id}`, `rankkernel/schema/piece/{id}`, `rankkernel/schema/graph`.
- `src/Modules/Metadata/Context.php`: immutable, built once per request. `queriedType()` covers preview, feed, search, 404, post, term, home, archive, home-fallback. `queriedId()`, `permalink()` (post, term, archive, home aware, returns empty for unreliable archive cases), `title()`, `siteName()`, `separator()`, `meta()`. No ancestor or taxonomy helpers exist; a breadcrumbs trail builder must add them.
- `src/Modules/Metadata/HeadRenderer.php`: emits the head and fires `rankkernel/head/after_tags` (where SchemaModule renders). Canonical source is `meta.canonical ?: ctx->permalink()`, empty on search and 404. Visible breadcrumbs must use the identical URL source.
- `src/Modules/ModuleRegistry.php`: the `breadcrumbs` id and label are already reserved. `ModuleManager` boots only enabled modules; a disabled module is never instantiated and registers zero hooks.
- Settings patterns: `SettingsStore` (`rankkernel_settings`) and per-module options such as `SitemapSettings` (autoload yes, small frontend option) and `RedirectsSettings` and `MonitorSettings` (autoload no). Module options must not be added to `SettingsStore::ALLOWED_KEYS`.
- Blocks: `FaqBlock` and `HowtoBlock` are server-rendered dynamic blocks with explicit editor assets, `register_block_type(__DIR__ . '/<name>', [...render_callback])`, the central `rankkernel` block category, and `RANKERNEL_VERSION` versioning. No frontend JS.
- Shortcodes: there are currently zero `add_shortcode` calls in the codebase, so a breadcrumbs shortcode would be the first.
- `MetaPayload`: the single meta row `_rankkernel_meta_data` already includes `flags.breadcrumb_title`, sanitized with `sanitize_text_field`, and a per post type taxonomy concept does not exist yet.
- Uninstall: purges `rankkernel_*` options, `_rankkernel_*` meta, and `wp_rankkernel_*` tables by prefix, so a new `rankkernel_breadcrumbs_settings` option is auto-covered.

Do Not Change list (from the audit): the `BreadcrumbPiece` id, `@id`, `itemListElement` shape, and the `rankkernel/schema/breadcrumb_trail` filter name; the `WebpagePiece` breadcrumb link; the `Generator` filter names and first-registration-wins behavior; `GraphNormalizer` dedupe and prune; `Context::permalink()` archive rules and the canonical chain; `ModuleRegistry` id and the `rankkernel_modules` key; the option and meta prefixes; the single meta row rule; `RANKERNEL_VERSION`; the `rankkernel` block category and text domain.

---

## 3. Yoast capability inventory `[DOCS]` `[SOURCE]`

- Indexable driven: a generator builds an ordered crumb list from the current indexable plus stored ancestors plus static ancestors (home, blog page, CPT archive), and the same list feeds both the HTML presenter and the schema `BreadcrumbList`. One trail, two renderings.
- Context coverage: homepage (self only, effectively hidden), blog page as an optional static crumb on singular posts, single post with a primary or deepest term plus its parents, hierarchical pages via `post_parent`, CPT singular with an optional CPT archive crumb and one taxonomy term branch, CPT archives, taxonomy term archive with optional term ancestors, parent and child terms, date archives, author archives, search (a single linked crumb with the query), 404 (a single unlinked crumb, and no `BreadcrumbList` at all), attachments through their parent, and paginated states (a text only `Page N` crumb that the schema deliberately strips).
- Output methods: `yoast_breadcrumb()` (gated by theme support or an option), the `[wpseo_breadcrumb]` shortcode, and a free server-rendered Gutenberg block.
- Settings: enable, separator (default a guillemet), home label, prefix, archive prefix, search prefix, 404 crumb, show blog page, bold last, per post type main taxonomy, per taxonomy parent. No hide on front page setting.
- Schema: `BreadcrumbList` inside the single page graph, `@id` canonical plus a fragment, last item without a URL, positions from one, omitted on 404 and unknown types and when any crumb is broken.
- Filters: `wpseo_breadcrumb_links`, `wpseo_breadcrumb_single_link`, `wpseo_breadcrumb_output`, wrapper and separator filters, and `wpseo_schema_breadcrumb`.
- Accessibility: flat `span` sequence joined by separators in a wrapper, not a list; only `aria-current="page"` on the last item and a `breadcrumb_last` class. Separators are not hidden from assistive tech.
- Performance: no dedicated breadcrumb cache; per request but reuses the indexable and hierarchy repository with one `WHERE IN` hydration; trails degrade in local environments where indexables are not generated.
- Free versus Premium: breadcrumbs are free in both. No premium breadcrumb capability was found.

---

## 4. Rank Math capability inventory `[DOCS]` `[SOURCE]`

- One shared trail: `get_crumbs()` feeds both the HTML renderer and the `BreadcrumbList` snippet, so visible and structured data share ordering by construction; a shared filter mutates both and a schema-only filter mutates the entity.
- Context coverage: front page generates nothing (except a paged `Page N`), blog posts index uses the posts page title, single post uses one primary taxonomy term branch, pages walk `post_parent`, CPT singular prepends the CPT archive and one term branch, CPT archive is a single crumb, category and tag and custom taxonomy have their own branches with optional term ancestors, products prepend the shop page, date archives chain year to month to day, author and search and 404 are single crumbs, attachments recurse into the parent, and pagination appends an unlinked `Page N` excluded from schema.
- Settings: enable, separator, show home link, home label, home link, prefix, archive format, search format, 404 label, hide post title, show category ancestors, hide taxonomy name, show blog page, per post type primary taxonomy, and per object breadcrumb title.
- Output methods: PHP function `rank_math_the_breadcrumbs` and `rank_math_get_breadcrumbs`, the `[rank_math_breadcrumb]` shortcode (with Yoast and AIOSEO aliases), theme support, and an Elementor widget. There is no Gutenberg breadcrumbs block; block themes use the shortcode.
- Schema: `BreadcrumbList` from the same crumbs, crumbs with empty URLs (current page, 404, author, date day, taxonomy name label) skipped, so schema typically contains only linked ancestors; omitted on the front page.
- Accessibility: `nav aria-label="breadcrumbs"` with an inner `p`; no `aria-current`, no `ol`/`li`, and separators are exposed as text.
- Performance: no caching at all; the singleton accessor resets its static each call, so visible and schema generation can each rebuild the trail.
- Free versus PRO: the trail, settings, functions, shortcode, and filters are free. Only the Elementor breadcrumbs widget (styling) is PRO.

---

## 5. WordPress and core capability inventory `[DOCS]` `[SOURCE]`

- Context conditionals that only work after the query: `is_front_page`, `is_home`, `is_singular`, `is_page`, `is_single`, `is_post_type_archive`, `is_category`, `is_tag`, `is_tax`, `is_archive`, `is_author`, `is_search`, `is_404`, `is_paged`.
- Object access reusing the already loaded query: `get_queried_object`, `get_queried_object_id`, `get_post_ancestors`, `wp_get_post_parent_id`, `get_ancestors`, `get_the_terms`, `get_post_type_object`, `get_taxonomy`, `is_post_type_hierarchical`, `is_taxonomy_hierarchical`. All are cache backed and need no extra query on a normal singular or archive view.
- `get_term_parents_list` returns pre-rendered HTML and must not be used for structured output.
- Pagination: `paged` for archives, `page` for the static front page and `<!--nextpage-->` splits, `cpage` for comment pages, `get_pagenum_link(1)` for a back to first page link. `paginate_links` is for page navigation, not breadcrumbs.
- Home and blog semantics: `show_on_front`, `page_on_front`, `page_for_posts` determine whether the front page is the blog or a static page, and whether a separate posts page exists. Labels must be derived, not hardcoded.
- Core `core/breadcrumbs` block (WordPress 7.0): dynamic, server rendered, `nav > ol > li`, current item as `<span aria-current="page">`, separator passed as a CSS variable `--separator`, `usesContext`, `supports` for anchor, align, spacing, color, typography, and two filters, `block_core_breadcrumbs_items` and `block_core_breadcrumbs_post_type_settings`. The items filter documents an `allow_html` contract: escape by default, `wp_kses_post` only when explicitly allowed. This is the current best practice reference.
- Server-rendered blocks: register with a `render_callback` or `block.json` `render` file, use `get_block_wrapper_attributes()` on the frontend and never `useBlockProps` in PHP, ship no `viewScript` for a static dynamic block, and declare `usesContext`.
- Shortcodes: `add_shortcode` on init, callback must return not echo, prefix the tag, use `shortcode_atts`, escape output. Shortcodes are appropriate only as a compatibility shim, not as the primary themed layout mechanism.
- Accessibility: `nav` with a translatable `aria-label`, ordered list, `aria-current="page"` on the current item, decorative separators hidden from assistive tech (CSS content or `aria-hidden`), native keyboard behavior.
- Escaping and i18n: `esc_html`, `esc_url`, `esc_attr`, `esc_html__`, `number_format_i18n`, and translators comments for placeholders.
- Multilingual without a module: rely on core objects and URLs that WPML and Polylang already filter, optionally resolve IDs through guarded `wpml_object_id` and `pll_get_post` or `pll_get_term`, and expose filters so multilingual plugins can swap labels and URLs. Include the locale in any cache key if caching is ever added.

---

## 6. Competitor comparison matrix

| Capability | Yoast | Rank Math | RankKernel proposal |
|---|---|---|---|
| Visible trail | Yes, free | Yes, free | KEEP, free |
| BreadcrumbList schema | Yes, shared trail | Yes, shared trail | KEEP, already present, feed the same trail |
| Template tag function | `yoast_breadcrumb()` | `rank_math_get/the_breadcrumbs()` | KEEP, `rankkernel_breadcrumbs()` |
| Shortcode | `[wpseo_breadcrumb]` | `[rank_math_breadcrumb]` | KEEP as compatibility shim |
| Gutenberg block | Yes, server rendered | No | IMPROVE, ship a server-rendered block |
| Home label | Yes | Yes | KEEP |
| Separator setting | Yes | Yes | KEEP |
| Show or hide home | Implied | Yes | KEEP |
| Hide on front page | No (caller conditional) | Front page suppressed by default | IMPROVE, explicit setting |
| Show blog page crumb | Yes | Yes | KEEP |
| Term ancestor display | Yes | Yes (off by default) | KEEP, setting |
| Primary taxonomy per post type | Yes | Yes | KEEP, minimal |
| Per object breadcrumb title | Yes (primary term and titles) | Yes | KEEP, reuse existing `flags.breadcrumb_title` |
| Paged `Page N` crumb | Yes, schema strips it | Yes, schema strips it | KEEP, visible only |
| 404 crumb | Yes, schema omitted | Yes, schema omitted | KEEP |
| Search crumb | Yes | Yes | KEEP |
| Accessible `nav > ol > li` and `aria-current` | No (flat spans) | No (`nav > p`, no aria-current) | IMPROVE, ship by default |
| Decorative separators hidden from AT | No | No | IMPROVE |
| Breadcrumb message/navigation schema | No | No | DROP |
| Caching | None | None | CHANGE, none needed, reuse loaded objects |
| Paid breadcrumb tier | None | Elementor widget only | DROP, nothing to free up |
| Automatic theme placement | No | No | DROP, explicit placement |

---

## 7. Proposed RankKernel feature scope `[PROPOSED]`

P0, ship in the module:
- A `Breadcrumbs` module, default ON, id `breadcrumbs`, `dependsOn` metadata, priority after schema, with zero cost when disabled.
- A context-aware trail builder covering home, blog page, static front page, singular post, page and hierarchical pages, CPT singular, CPT archive, category, tag and custom taxonomy archives with hierarchical term ancestors, search, 404, author, date archives, attachments via parent, and paginated archives and paginated singular content.
- One shared trail feeding both the visible renderer and the existing `BreadcrumbPiece` through `rankkernel/schema/breadcrumb_trail`, so there is exactly one `BreadcrumbList`.
- An accessible server-rendered trail: `nav` with a translatable `aria-label`, `ol`/`li`, `aria-current="page"` on the current item, decorative separators hidden from assistive tech, escaped labels and URLs.
- Output methods: a template tag `rankkernel_breadcrumbs()`, the `rankkernel/breadcrumbs` filter, the `[rankkernel_breadcrumbs]` shortcode, and a server-rendered block `rankkernel/breadcrumbs`.
- A settings section: separator, home label, show home, show current, hide on front page, show blog page, show term ancestors.
- Reuse of `flags.breadcrumb_title` for per object labels, falling back to the SEO title, then the object title.

P1:
- A per post type primary taxonomy mapping (which taxonomy supplies the single term branch on singular views), defaulting to the first public taxonomy with terms.
- An items filter matching the core contract, `rankkernel/breadcrumbs/items`, with per item `allow_html` opt in, plus a post type settings filter for taxonomy and term pinning.

P2 or Future:
- Attachment parent policy control.
- Per post type or per taxonomy visibility toggles.
- A widget or block theme pattern.

Explicitly excluded:
- Any second `BreadcrumbList` node.
- Any breadcrumb caching layer or new table (not justified).
- WooCommerce shop crumb handling in this phase.
- A dedicated multilingual module.
- Any admin redesign beyond a settings section.

---

## 8. KEEP, IMPROVE, CHANGE, DROP, FUTURE decisions

- KEEP: free breadcrumbs, one shared trail for HTML and schema, home label, separator, show or hide home, blog page crumb, term ancestors, primary taxonomy per post type, per object title override, paged crumb visible only, 404 and search crumbs, schema omitted on front page and 404.
- IMPROVE: accessible `nav > ol > li` with `aria-current` by default, decorative separators hidden from assistive tech, escaped labels by default with opt in HTML, explicit hide on front page setting, server-rendered Gutenberg block (Rank Math lacks one), no per-request rebuild cost by reusing loaded objects.
- CHANGE: no caching (justified as unnecessary), shortcode shipped only as a compatibility shim rather than the primary method.
- DROP: nothing to un-paywall (breadcrumbs are free in both rivals), breadcrumb navigation schema beyond `BreadcrumbList`, automatic theme placement.
- FUTURE: attachment parent policy, per type visibility toggles, widget or block theme pattern, optional locale keyed caching if a real need appears.

---

## 9. Detailed breadcrumb generation rules `[PROPOSED]`

Dispatch order after the module gate, mirroring core's order for predictability: front page and home, then search, then 404, then archives (post type archive, date, author, taxonomy term), then singular.

- Front page with `show_on_front=posts`: no trail, unless paged, then a `Page N` crumb.
- Static front page: home only, unless paged, then `Page N`.
- Blog posts index: home plus the posts page title (not a hardcoded "Blog").
- Single post: home, optional blog page crumb, then a single taxonomy term branch (primary if set, else the first public taxonomy with terms, using its term with ancestors when hierarchical), then the post title.
- Page: home, then each ancestor root first, then the page title.
- Hierarchical CPT singular: home, optional CPT archive crumb, ancestor chain, then title. Non hierarchical CPT singular: home, optional CPT archive crumb, one term branch, then title.
- CPT archive: home, then the archive label.
- Category, tag, custom taxonomy archive: home, optional blog page crumb for post taxonomies, taxonomy name crumb for custom taxonomies unless hidden, then term ancestors root first when hierarchical, then the term.
- Author archive: home, then the archive label with the author display name.
- Date archive: home, then year to month to day, each labelled with the archive format.
- Search: home, then a single crumb with the search format and the query, linked to the search URL without paged.
- 404: home, then a single unlinked 404 label.
- Attachment: the parent post's trail, then the attachment title. Parentless attachments fall back to home and self.
- Pagination: archives and search and author and date append an unlinked `Page N` when paged is greater than one; paginated singular content appends `Page N` when page is greater than one; comments pagination appends a comments page label. Pagination crumbs are visible only and are excluded from schema.
- Untitled objects fall back to a translatable "(no title)".
- Duplicate crumb collapse: if an item equals the previous item by label and URL, drop the duplicate.
- Homepage URL normalization: use `home_url('/')`, and the canonical source `meta.canonical ?: ctx->permalink()` for the current item.

---

## 10. Output and API proposal `[PROPOSED]`

- Template tag: `rankkernel_breadcrumbs( array $args = [] )` echoes, and `rankkernel_get_breadcrumbs( array $args = [] )` returns the HTML string. Args override settings: separator, before, after, wrap_before, wrap_after, show_home, show_current.
- Filter: `rankkernel/breadcrumbs` filters the final HTML, `rankkernel/breadcrumbs/items` filters the item array with a per item `allow_html` flag matching core's contract, and `rankkernel/breadcrumbs/args` filters the resolved args.
- Schema: the item array feeds `rankkernel/schema/breadcrumb_trail` so the existing `BreadcrumbPiece` remains the only `BreadcrumbList` emitter. The trail builder never emits JSON-LD itself.
- Shortcode: `[rankkernel_breadcrumbs]` with documented attributes (separator, show_home, show_current), implemented as a thin wrapper over the same renderer, escaped output. Documented as a compatibility shim.
- Block: `rankkernel/breadcrumbs`, server rendered, dynamic, `save` returns null, `usesContext` for post id, attributes mirroring core names (showHomeItem, showCurrentItem, showOnHomePage, separator, prefersTaxonomy) for theme compatibility, `get_block_wrapper_attributes()` on the frontend, no view script, category `rankkernel`, versioned with `RANKERNEL_VERSION`.
- Markup, matching the core accessible pattern:
  - `<nav class="rk-breadcrumbs" aria-label="Breadcrumbs">`
  - `<ol>` of `<li>` items; linked items `<a href="esc_url">` with `esc_html` labels; current item `<span aria-current="page">`.
  - Separator rendered through a CSS custom property or an `aria-hidden` decorative element, never as an exposed text node.
- No inline `<style>` output. The separator is exposed as a `--rk-breadcrumb-separator` standard property with a sane default in the scoped stylesheet.

---

## 11. Settings proposal `[PROPOSED]`

One per-module option `rankkernel_breadcrumbs_settings`, autoload yes (small and read on the frontend), following `SitemapSettings` shape with defaults, `get`, `all`, `set`, whitelist, and sanitize. Keys:

- `separator` (string, default a slash, sanitized, HTML stripped).
- `home_label` (string, default `Home`, translatable, `sanitize_text_field`).
- `show_home` (bool, default true).
- `show_current` (bool, default true).
- `hide_on_front_page` (bool, default true).
- `show_blog_page` (bool, default true, only effective when a posts page exists).
- `show_ancestors` (bool, default true, hierarchical term ancestors).
- `primary_taxonomy_{post_type}` (string, P1, optional, defaults to first public taxonomy with terms).

Settings UI: a Breadcrumbs section added to an existing RankKernel settings page as a native form section, no redesign, with capability and nonce, saved through the module settings class, per the existing admin save pattern. The exact host page is an open question (see section 21).

---

## 12. Schema integration proposal `[PROPOSED]`

- The visible trail builder is the single source of trail items and passes them to the existing `rankkernel/schema/breadcrumb_trail` filter, which `BreadcrumbPiece` already consumes. `BreadcrumbPiece` stays the only `BreadcrumbList` emitter; no new node, no duplicate.
- When the breadcrumbs module is off, `BreadcrumbPiece` keeps its current home plus current behavior, so nothing breaks.
- Consistency rules: the schema list is derived from the same items; pagination crumbs are excluded from schema; the 404 crumb is visible only and no `BreadcrumbList` is emitted on 404; the front page emits no `BreadcrumbList` when hidden.
- `@id` relationships must not change: `BreadcrumbPiece` keeps `{base}#breadcrumb`, and `WebpagePiece` keeps linking to it. The trail builder must not alter `@id` values.
- Open decision: whether the last schema item should omit `item` (Yoast parity) or keep its URL (current RankKernel behavior). Both are valid; recommend keeping the current behavior to avoid changing existing output, and revisit if validation suggests otherwise.

---

## 13. Lazy-loading and performance design `[PROPOSED]`

- Module off: not instantiated, zero hooks, and `BreadcrumbPiece` uses its current fallback. Proven by a gate test.
- Module on but breadcrumbs not requested on the page: the trail builder runs only when a renderer is invoked (template tag, shortcode, block) or when the schema piece requests the trail through the filter. No work happens on pages that neither print nor need the trail, except the schema piece's own existing behavior.
- Schema-only usage: the trail builder is invoked by the filter during head render and returns items, without producing visible HTML.
- Reuse, not queries: context and queried objects are already loaded. Hierarchical walks use `get_post_ancestors`, `wp_get_post_parent_id`, `get_ancestors`, and `get_the_terms`, all cache backed. `get_post_type_object` and `get_taxonomy` are registry lookups with no query. Expected extra queries on a normal singular or archive view: zero.
- No breadcrumb caching layer is proposed. The trail is per request and cheap, so a cache would add invalidation risk for no measurable benefit. If ever needed, cache with a `get_locale()` keyed entry and invalidation on post save and term change.
- Expected behavior on the large dataset: constant cost per request independent of site size, scaling with hierarchy depth only.

---

## 14. Security and accessibility design `[PROPOSED]`

Security:
- Escape every label with `esc_html` by default; allow HTML only when a filtered item sets `allow_html` true, then use `wp_kses_post`, matching core's contract.
- Escape every URL with `esc_url` (permawinks, term links, home, pagination links).
- Escape attributes with `esc_attr`. Shortcode attributes sanitized. Block attributes sanitized on render.
- Never output raw prefix or separator HTML from untrusted sources; sanitize the separator setting by stripping tags.
- The `flags.breadcrumb_title` value is already `sanitize_text_field`, escaped again at output.

Accessibility:
- `nav` with a translatable `aria-label` (default `Breadcrumbs`).
- Ordered list semantics; current item `aria-current="page"` on the inner element.
- Separators decorative and hidden from assistive tech (CSS content or `aria-hidden`).
- Visible focus inherits theme link styles; no tabindex on the current item; native keyboard behavior.
- No information conveyed by color alone.

---

## 15. Database and storage decision `[PROPOSED]`

- No new tables, no new meta keys, no transients, no cron.
- One new option `rankkernel_breadcrumbs_settings` (autoload yes, small), following the existing per-module option convention and auto-covered by uninstall prefix purge.
- Per object labels reuse the existing single meta row `_rankkernel_meta_data` `flags.breadcrumb_title`; never a second breadcrumb meta key.
- Per post type taxonomy mapping stores within the same settings option.
Justification: the trail is derived data computed per request from already loaded objects, so there is nothing to persist.

---

## 16. Edge-case behavior matrix `[PROPOSED]`

| Case | Behavior |
|---|---|
| Front page, posts mode | No trail; paged appends `Page N` |
| Front page, static page | Home only; paged appends `Page N` |
| Blog posts index | Home plus posts page title |
| Single post | Home, optional blog page, one term branch with ancestors, title |
| Hierarchical page | Home, ancestor chain root first, title |
| CPT singular, non hierarchical | Home, optional CPT archive, one term branch, title |
| CPT singular, hierarchical | Home, optional CPT archive, ancestor chain, title |
| CPT archive | Home plus archive label |
| Category or tag archive | Home, optional blog page, term ancestors when hierarchical, term |
| Custom taxonomy archive | Home, taxonomy name crumb unless hidden, term ancestors, term |
| Author archive | Home plus archive label with author name |
| Date archive | Home plus year, month, day chain |
| Search | Home plus search format with query, linked |
| 404 | Home plus unlinked 404 label; no schema node |
| Attachment with parent | Parent trail then attachment title |
| Attachment without parent | Home plus attachment title |
| Paged archive | Append unlinked `Page N`, excluded from schema |
| Paginated singular | Append unlinked `Page N`, excluded from schema |
| Untitled object | Translatable "(no title)" |
| Duplicate consecutive items | Collapsed |
| Homepage URL | `home_url('/')`, canonical for current item |
| Missing ancestor | Skipped, and if that breaks the chain, the trail continues from home |
| Invalid context | Return empty string, no fatal |
| Filtered custom items | Escaped by default, `allow_html` opt in |

---

## 17. Architecture and class responsibility proposal `[PROPOSED]`

New namespace `RankKernel\Modules\Breadcrumbs`:
- `BreadcrumbsModule` implements `ModuleInterface`: id `breadcrumbs`, name `Breadcrumbs`, `dependsOn` metadata, priority after schema, `register()` wires the settings class with no hooks, `boot()` registers the shortcode, block, filters, and the schema trail filter.
- `Item` (small value object): label, url, `allow_html`, optional schema exclusion flag. Immutable.
- `TrailBuilder`: context-aware trail construction from `Context` and WordPress objects; the only place hierarchy logic lives; returns an array of `Item`; pure and unit testable.
- `Renderer`: converts items to accessible HTML with escaping and the separator property; no side effects.
- `BreadcrumbsSettings`: the settings option class, matching `SitemapSettings`.
- Block integration: `blocks/breadcrumbs/block.json`, an editor script and editor style, a `Block` class or method with `render_callback` delegating to `TrailBuilder` and `Renderer`, registered centrally like `FaqBlock`.
- Shortcode and template tag: thin wrappers over the same builder and renderer.
- Schema integration: one filter callback that maps items to the `{name, url}` shape and hands them to `rankkernel/schema/breadcrumb_trail`.

Data flow: request context, TrailBuilder, Items, then either Renderer to HTML or the schema filter to `BreadcrumbPiece`. Output flow: template tag, shortcode, or block all call the same builder and renderer. Settings flow: `BreadcrumbsSettings` is read by the builder and renderer; saved through the admin pattern.

---

## 18. Hook, filter, shortcode, and block proposal `[PROPOSED]`

- Output filter: `rankkernel/breadcrumbs` (final HTML).
- Items filter: `rankkernel/breadcrumbs/items` (item array, per item `allow_html`).
- Args filter: `rankkernel/breadcrumbs/args`.
- Post type settings filter: `rankkernel/breadcrumbs/post_type_settings` (P1, taxonomy and term pinning), mirroring core's `block_core_breadcrumbs_post_type_settings`.
- Schema handoff: use the existing `rankkernel/schema/breadcrumb_trail` filter, do not rename it.
- Shortcode: `[rankkernel_breadcrumbs separator="/" show_home="1" show_current="1"]`.
- Template tags: `rankkernel_breadcrumbs()`, `rankkernel_get_breadcrumbs()`.
- Block: `rankkernel/breadcrumbs`, server rendered, attributes showHomeItem, showCurrentItem, showOnHomePage, separator, prefersTaxonomy.
- All hooks prefixed `rankkernel/` or `rankkernel_` and documented.

---

## 19. Testing specification `[PROPOSED]`

Unit:
- TrailBuilder per context: home, blog page, static front page, single post with primary and with fallback taxonomy, hierarchical page, CPT singular non hierarchical and hierarchical, CPT archive, category with ancestors, custom taxonomy with and without taxonomy name crumb, author, date, search, 404, attachment with and without parent.
- Pagination: paged archive, paginated singular, comments paging; pagination crumb excluded from schema.
- Untitled fallback, duplicate collapse, missing ancestor handling, invalid context returns empty.
- Renderer: escaping of labels and URLs, `aria-current` placement, separator decorative, allow_html opt in uses `wp_kses_post`, no raw output.
- Settings: defaults, sanitize, whitelist, autoload behavior.
- Module gate: disabled means zero hooks and the piece falls back to home plus current.

Integration and rendering:
- Template tag echoes and returns correctly; shortcode with attributes; block render callback output shape.
- Filter tests: `rankkernel/breadcrumbs`, `items`, `args`, and the schema handoff.
- Schema integration: exactly one `BreadcrumbList` in the graph, `@id` unchanged, `WebpagePiece` link intact, pagination crumb absent from schema, 404 no node.

Performance:
- Zero extra queries on a typical singular and archive view using a counting fake `$wpdb`.
- Rendering not triggered when the trail is not requested, except the schema filter path.

Security:
- Malicious label in a term name is escaped; filtered `allow_html` false cannot inject; `esc_url` neutralizes `javascript:`.

Multilingual:
- Placeholder test that guarded `wpml_object_id` and `pll_get_post` are called only when present.

---

## 20. Documentation changes `[PROPOSED]`

- `ROADMAP.md`: mark 2.3 done with the issue and PR, and correct 2.2, 2.4, 2.5 to done reflecting shipped reality.
- `docs/architecture/breadcrumbs.md`: generation rules, contexts, settings, schema integration, lazy behavior.
- `docs/architecture/breadcrumbs-api.md`: template tags, filters, shortcode, block, attributes.
- `docs/architecture/breadcrumbs-plan.md`: this gate, once approved, as the binding spec.
- `docs/coding-standards.md`: no change expected (module follows the finalized standard).
- Update the FAQ/HowTo style user documentation only if a user guide is added later.

---

## 21. Risks and unresolved questions

Risks:
1. Coexistence with the WordPress core `core/breadcrumbs` block and theme breadcrumbs, which can cause duplicate visible trails and duplicate `BreadcrumbList`. Mitigation: document disabling one, and never emit a second `BreadcrumbList`.
2. Multilingual correctness without a module. Mitigation: rely on filtered core objects and expose filters; add guarded translation calls; include locale in any future cache key.
3. Module default state. The roadmap marks breadcrumbs default ON, which differs from the heavier default OFF modules, but it is near zero cost, so ON is consistent with the roadmap. Confirm.
4. Schema last item behavior (omit URL or keep). Recommend keep current to avoid output change.

Unresolved questions for owner approval:
1. Settings host page: add a Breadcrumbs section to an existing settings page, or create a small dedicated `rankkernel-breadcrumbs` submenu?
2. Should breadcrumbs be default ON as the roadmap states, or default OFF until verified?
3. Include the P1 per post type primary taxonomy mapping in the first implementation, or defer it?
4. Confirm the settings keys and defaults in section 11.
5. Confirm the schema last item decision (keep current URL behavior).
6. Confirm the module id `breadcrumbs` and the single new option name `rankkernel_breadcrumbs_settings`.

---

## 22. Final implementation checklist

1. Approve this specification (or amend it).
2. Create the implementation issue and a `GH-<n>` branch.
3. Add `BreadcrumbsSettings` with the approved keys and defaults.
4. Add `Item` and `TrailBuilder` with all contexts and edge cases.
5. Add `Renderer` with accessible markup and escaping.
6. Add schema handoff feeding `rankkernel/schema/breadcrumb_trail`, verifying exactly one `BreadcrumbList`.
7. Add the template tags and the shortcode.
8. Add the server-rendered `rankkernel/breadcrumbs` block with editor assets only.
9. Add the settings section to the approved host page.
10. Register `BreadcrumbsModule` in `Plugin.php` and confirm the module gate and zero cost when off.
11. Write the full test matrix from section 19.
12. Run `composer lint` (phpcs), `composer stan`, `composer test`, and node checks; all green.
13. Live and browser verification of the contexts on the real site.
14. Update documentation including the ROADMAP corrections.
15. Commit, push the branch, open the PR, and wait for CodeRabbit and owner review. Do not merge.

---

End of research report. No implementation performed. No code, migration, admin UI, block, shortcode, or tests created. Awaiting owner approval of this specification.
