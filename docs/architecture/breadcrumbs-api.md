# Breadcrumbs API

Template tags, shortcode, filters, block, and settings for the Breadcrumbs module. All hooks are prefixed `rankkernel` and stable. All output is escaped at render time.

## Template tags

Available only when the module is enabled, loaded by `BreadcrumbsModule::boot()`.

1. `rankkernel_breadcrumbs( array $args = [] )` echoes the trail HTML for the current request.
2. `rankkernel_get_breadcrumbs( array $args = [] )` returns the trail HTML, or an empty string when the context yields no trail or the query is unavailable.

Args override the stored settings and sanitize before rendering:

1. `separator` (string, tags stripped, defaults to the stored separator).
2. `before`, `after`, `wrap_before`, `wrap_after` (strings, filtered through `wp_kses_post`).
3. `show_home`, `show_current` (bool, drop the first or last item).
4. `aria_label` (string, tags stripped, default `Breadcrumbs`).

Markup follows the core accessible pattern: `nav.rk-breadcrumbs` with a translatable `aria-label`, an ordered list of items, linked ancestors as anchors, the current item as a `span` with `aria-current="page"`. The separator travels as the `--rk-breadcrumb-separator` CSS custom property consumed by the scoped stylesheet, never as an exposed text node, and no inline `style` tag is emitted beyond that property. The stylesheet enqueues only where breadcrumbs render.

## Shortcode

`[rankkernel_breadcrumbs]` is a compatibility shim over the same builder and renderer. It returns output, never echoes.

1. `separator` (string, sanitized, empty falls back to the setting).
2. `show_home` (boolish, default on).
3. `show_current` (boolish, default on).

Unknown attributes are ignored through `shortcode_atts` defaults. Hostile attribute input is sanitized before it reaches the renderer. Proven by `BreadcrumbsSecurityTest::test_malicious_shortcode_attributes_cannot_inject`.

## Filters

1. `rankkernel/breadcrumbs/items` receives the canonical `Item` array and returns the filtered trail. Array entries shaped with `label` or `name` plus `url` or `item` coerce to `Item`, anything else drops out. Per item `allow_html` opts into `wp_kses_post`, every other label escapes with `esc_html`. URLs escape with `esc_url`.
2. `rankkernel/breadcrumbs/args` receives the resolved display args and returns the filtered map, sanitized again before rendering.
3. `rankkernel/breadcrumbs` receives the final HTML plus the resolved args and returns the filtered string.
4. `rankkernel/breadcrumbs/post_type_settings` receives the resolved per post type configuration as `array{post_type: string, primary_taxonomy: string}` plus the post type slug as the second argument, and returns the same shape. It runs after the stored settings load and before `TrailBuilder` consumes the configuration. Contract: the returned `primary_taxonomy` must name a public taxonomy registered for that post type and existing in the registry; an empty string resets to the fallback (first public taxonomy with terms); anything invalid or malicious is ignored and the stored value applies. Proven by `BreadcrumbsSettingsTest` (receives post type and config, valid change respected, unknown and malicious values ignored, non array ignored) and `BreadcrumbsTrailBuilderTest` (switched term branch, invalid and malicious ignored, visible and schema items identical).
5. `rankkernel/schema/breadcrumb_trail` receives the fallback trail plus the request `Context` and returns `name` plus `url` entries. The breadcrumbs module supplies the canonical trail here, so `BreadcrumbPiece` remains the only `BreadcrumbList` emitter. An empty builder trail keeps the incoming fallback untouched.

## Block

`rankkernel/breadcrumbs` is a dynamic server rendered block with no view script and no frontend JS. Registration mirrors the schema block pattern: explicit editor script and style, `render_callback` delegating to the same `TrailBuilder` and `Renderer`, category `rankkernel`, versioned with `RANKERNEL_VERSION`.

Attributes mirror core names for theme compatibility:

1. `showHomeItem` (bool, maps to `show_home`).
2. `showCurrentItem` (bool, maps to `show_current`).
3. `showOnHomePage` (bool, default false, suppresses front page output).
4. `separator` (string, non empty attribute wins over the setting, sanitized on render).
5. `prefersTaxonomy` (string, reserved for the primary taxonomy preference).

Block attributes sanitize on render and hostile values stay escaped. Proven by `BreadcrumbsSecurityTest::test_malicious_block_attributes_cannot_inject` and `BreadcrumbsOutputTest::test_block_render_sanitizes_separator`.

## Settings option

`rankkernel_breadcrumbs_settings` (autoload yes, small and read on frontend trail builds). Fixed keys plus dynamic `primary_taxonomy_{post_type}` mappings gated by a key pattern and sanitized against the registered public taxonomies for that post type. `separator` holds exactly one canonical value (tags stripped, capped at 10 characters, empty falls back to `/`); the admin chooser offers `BreadcrumbsSettings::SEPARATOR_PRESETS` plus Custom but never stores a second key. Taxonomy mappings for post types without a rendered select are preserved on save, never deleted. Unknown keys are dropped on `set`. The option is covered by the uninstall prefix purge, so no cleanup code is needed. Reuse the per object `flags.breadcrumb_title` inside the single `_rankkernel_meta_data` row for custom labels, never a new meta key.
