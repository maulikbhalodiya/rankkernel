# Breadcrumbs Architecture

How the Breadcrumbs module (id `breadcrumbs`, default on) turns the current request into one shared trail for visible HTML and schema output.

## Module gating

`BreadcrumbsModule::register()` seeds `rankkernel_breadcrumbs_settings` with no hooks. `BreadcrumbsModule::boot()` wires the schema trail filter, the shortcode shim, the server rendered block, and the scoped stylesheet. Both methods return immediately when the module is disabled, so a disabled module means zero hooks and `BreadcrumbPiece` keeps its home plus current fallback. Proven by `BreadcrumbsModuleTest::test_disabled_module_registers_zero_hooks` and `::test_disabled_module_keeps_fallback_piece_behavior`.

## Data flow

```text
Request context (Metadata Context, built once per request)
        |
TrailBuilder::build (context aware, cache backed lookups only)
        |
Item list (label, url, allow_html flag, schema exclusion flag)
        |
visible path: items filter, Renderer, HTML filter
        |
schema path: schema trail filter, BreadcrumbPiece, single BreadcrumbList
```

`TrailBuilder` is the only place hierarchy and context logic lives. `Renderer` converts items to accessible HTML with no side effects. `Item` is an immutable value object carrying a label, a URL, an `allow_html` opt in, and a `schema_excluded` flag for visible only crumbs. The module never emits JSON-LD itself. `BreadcrumbPiece` stays the only `BreadcrumbList` emitter.

## Generation rules

Dispatch mirrors core conditional order: front page and blog index, then search, then 404, then term and other archives, then singular.

1. Front page in posts mode shows no trail, paged appends a visible only `Page N` crumb.
2. Static front page shows home only unless `hide_on_front_page` suppresses it, paged appends `Page N`.
3. Blog posts index shows home plus the configured posts page title, never a hardcoded label.
4. Single post shows home, an optional blog page crumb, one taxonomy term branch with ancestors, then the title.
5. Page shows home plus the ancestor chain root first, then the title.
6. Hierarchical custom post type singular shows home, an optional archive crumb, the ancestor chain, then the title. Non hierarchical types show one term branch instead of ancestors.
7. Post type archive shows home plus the archive label from the post type object.
8. Category and tag archives show home, an optional blog page crumb, term ancestors root first when hierarchical, then the term.
9. Custom taxonomy archives add a taxonomy name crumb unless hidden, then ancestors, then the term.
10. Author archives show home plus the author display name.
11. Date archives show home plus the year to month to day chain.
12. Search shows home plus a linked search crumb, paged appends `Page N`.
13. 404 shows home plus an unlinked label, with no schema node.
14. Attachments reuse the parent trail then the attachment title, parentless attachments fall back to home plus the title.
15. Paged archives and paginated singular content append an unlinked `Page N` crumb that is always excluded from schema, as are comments pages.
16. Untitled objects fall back to a translatable placeholder, duplicate consecutive items collapse, missing ancestors are skipped, invalid contexts return an empty trail.

The primary taxonomy mapping (`primary_taxonomy_{post_type}`) selects the single term branch on singular views, falling back to the first public taxonomy with terms.

## Settings

One per module option, `rankkernel_breadcrumbs_settings`, autoload yes, following the `SitemapSettings` shape with defaults, `get`, `all`, `set`, whitelist, and sanitize. Per object labels reuse the existing single meta row `flags.breadcrumb_title`, never a second breadcrumb meta key.

1. `separator` (string, default `/`, tags stripped).
2. `home_label` (string, default `Home`, sanitized text).
3. `show_home` (bool, default true).
4. `show_current` (bool, default true).
5. `hide_on_front_page` (bool, default true).
6. `show_blog_page` (bool, default true, effective only when a posts page exists).
7. `show_ancestors` (bool, default true, hierarchical term ancestors).
8. `primary_taxonomy_{post_type}` (string, optional, sanitized against the registered public taxonomies for that post type).

The settings UI is a Breadcrumbs section on the existing settings page, saved through the module settings class with the established capability and nonce pattern. Proven by `BreadcrumbsOutputTest::test_settings_section_renders_fields` and `::test_settings_save_persists_breadcrumbs`.

## Schema integration

The visible builder is the single source of trail items. `BreadcrumbsModule::filterBreadcrumbTrail` maps them onto the existing `rankkernel/schema/breadcrumb_trail` shape (`name` plus `url`), drops visible only items, and keeps the incoming fallback untouched when the builder yields nothing. Consistency rules:

1. Exactly one `BreadcrumbList` appears in the graph, first wins dedupe guards against duplicates.
2. The `@id` shape is unchanged (`{base}#breadcrumb`), matching the fallback piece output.
3. The `WebPage` breadcrumb reference is unchanged and resolves to the emitted node.
4. Visible and schema trails share ordering from the same canonical items.
5. Pagination crumbs never reach schema, 404 emits no node, the hidden front page emits no node.

Proven by `BreadcrumbsSchemaIntegrationTest` (exactly one node, unchanged `@id`, resolving reference, shared items, pagination visible only, 404 omitted, front page following settings, duplicate keeping first).

## Performance behavior

Measured with a counting fake `wpdb` on a representative single post and term archive, the build issues zero breadcrumb specific database calls. Hierarchy walks use `get_post_ancestors`, `wp_get_post_parent_id`, `get_ancestors`, and `get_the_terms`, all cache backed, while type and taxonomy lookups are registry reads. Rendering runs only when requested: the template tag, the shortcode, the block, or the schema adapter during head render. Module off means no class work and zero hooks. No breadcrumb caching layer exists by design, the trail is per request and cheap, so a cache would add invalidation risk for no measured benefit.

What is not claimed: cold object caches can still query inside WordPress core itself, and large hierarchies scale with depth. The claim is bounded to breadcrumb specific calls on warm representative views. Proven by `BreadcrumbsPerformanceTest` across single post, term archive, register, template tag, shortcode, block, and schema adapter paths.

## Extension points

1. `rankkernel/breadcrumbs/items` filters the item array with per item `allow_html` semantics, matching the core contract. Escaped by default, `wp_kses_post` only on explicit opt in.
2. `rankkernel/breadcrumbs/args` filters the resolved display args, sanitized again before rendering.
3. `rankkernel/breadcrumbs` filters the final HTML.
4. `rankkernel/schema/breadcrumb_trail` receives the same canonical items for schema use. Third parties add schema nodes through `Generator::register()`, not new hooks.

The full call signatures live in `breadcrumbs-api.md`.
