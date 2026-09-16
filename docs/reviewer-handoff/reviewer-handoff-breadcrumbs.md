# RankKernel Phase 2.3 Breadcrumbs: Reviewer Handoff

For the reviewing AI. This covers what was built, how it works, what is verified (including live browser/HTTP verification on the real site), and what remains.

Date: 2026-09-15
Binding spec: `docs/research/breadcrumbs-research-gate.md`
Issue: `#15`. Branch: `GH-15`. PR: `#16` (open, not merged). Live site: `http://localhost:10043`

## 1. What we need from you

Review this phase as a senior WordPress and SEO plugin architect. Verify the architecture and the code, then reply with a next prompt that is precise and ordered.

Specifically: confirm the single canonical trail design is correct, that visible and schema output can never diverge, that the context matrix is complete, that module gating and the schema fallback are safe, and that nothing in the existing Schema or Metadata architecture was broken. Then propose the next work.

Constraints for your prompt: keep the `GH-<n>` branch workflow, keep all features free, no new dependencies, clean room, and do not change the existing Schema contracts (`BreadcrumbPiece` id, `@id`, `itemListElement`, `rankkernel/schema/breadcrumb_trail`, the WebPage breadcrumb link, `Generator`, `GraphNormalizer`).

## 2. Status at a glance

| Item | Value |
|---|---|
| Phase | 2.3 Breadcrumbs, the last Phase 2 feature |
| Issue | #15 |
| Branch | GH-15, 13 commits |
| Head commit | `a77a27c` |
| PR | #16, open, not merged |
| Tests | 953 tests, 3336 assertions (phase start was 838 / 3051) |
| PHPCS | 0 errors, 0 warnings |
| PHPStan | level 6, no errors |
| Node | pass on the new editor script |
| Live verification | completed on the real site (section 11) |

## 3. What was built

New namespace `RankKernel\Modules\Breadcrumbs`:

- `Item`: immutable value object with `label`, `url`, `allow_html`, `schema_excluded`, read accessors only.
- `TrailBuilder`: the ONLY place breadcrumb hierarchy and context logic lives. Takes the Metadata `Context` and `BreadcrumbsSettings`, returns a list of `Item`.
- `Renderer`: converts items into accessible HTML only, no context logic.
- `BreadcrumbsSettings`: option `rankkernel_breadcrumbs_settings`, autoload yes.
- `BreadcrumbsModule`: implements `ModuleInterface`, id `breadcrumbs`, `dependsOn` metadata, priority 35, registers the schema adapter in `boot()` only when enabled.
- `functions.php` and `template-tags.php`: the build pipeline and the two global template tags.
- `blocks/BreadcrumbsBlock.php` plus `blocks/breadcrumbs/{block.json, breadcrumbs-editor.js, editor.css}` and `breadcrumbs.css`.

Also: `src/Plugin.php` registers the module, and `src/Admin/SettingsPage.php` gains a Breadcrumbs section.

## 4. Architecture: one canonical trail

```
Context → TrailBuilder → canonical Items
                          ├─ Renderer → visible HTML (nav, ol, li, aria-current)
                          └─ schema adapter → {name, url} → rankkernel/schema/breadcrumb_trail
                                             → existing BreadcrumbPiece → existing single @graph
```

`BreadcrumbPiece` remains the only `BreadcrumbList` emitter. The Breadcrumbs module never emits JSON-LD. When the module is disabled, the filter is never registered and `BreadcrumbPiece` keeps its original home plus current fallback.

## 5. Context matrix (all implemented)

Front page (posts mode and static page), blog posts index (uses the real posts page title, never hardcoded), single post (home, optional blog page, ONE taxonomy branch, then title), pages (home, parent ancestors root first, current), hierarchical CPT (home, optional CPT archive, ancestors, current), non-hierarchical CPT (home, optional CPT archive, one term branch, current), CPT archive (label from the post type object), category, tag, custom taxonomy (taxonomy-name crumb for custom taxonomies, hierarchical ancestors root first), author (display name), date archives (year, month, day), search (unpaged search URL), 404 (unlinked crumb, no schema), attachments (parent trail plus title, or home plus title), and pagination for archives, search, author, date, paginated singular content, and comments. Pagination items are visible only and carry `schema_excluded`.

Primary taxonomy selection: the configured `primary_taxonomy_{post_type}` wins when it names a public taxonomy registered for the type that has terms on the post; otherwise the first public taxonomy with terms.

## 6. Output methods

- Template tags: `rankkernel_breadcrumbs( array $args = [] )` echoes, `rankkernel_get_breadcrumbs( array $args = [] )` returns. Guarded by `function_exists`, loaded only when the module is enabled.
- Shortcode: `[rankkernel_breadcrumbs]` with `separator`, `show_home`, `show_current`. Returns, never echoes. Thin wrapper over the same builder and renderer.
- Block: `rankkernel/breadcrumbs`, dynamic, server rendered, `save` returns null, `usesContext`, `get_block_wrapper_attributes()`, no frontend view script, `rankkernel` category, `RANKERNEL_VERSION` assets. Attributes: showHomeItem, showCurrentItem, showOnHomePage, separator.
- Args supported by the tags and renderer: separator, before, after, wrap_before, wrap_after, show_home, show_current.

Markup: `nav.rk-breadcrumbs` with a translatable `aria-label`, `ol.rk-breadcrumbs-list`, `li` items, links with `esc_url`, the current item as `span aria-current="page"`, and the separator rendered from the `--rk-breadcrumb-separator` CSS custom property, never as an exposed text node. Labels are `esc_html` by default; `allow_html` opts into `wp_kses_post`. No inline style tag, no frontend JavaScript.

## 7. Settings

Option `rankkernel_breadcrumbs_settings` (autoload yes): `separator` (`/`), `home_label` (`Home`), `show_home`, `show_current`, `hide_on_front_page`, `show_blog_page`, `show_ancestors`, and `primary_taxonomy_{post_type}` per public post type. The UI is a native section on the existing settings page using the existing capability, nonce, sanitize, and post-redirect-get pattern.

## 8. Schema integration

The adapter builds the canonical trail, drops `schema_excluded` (pagination) items, maps the rest to `{name, url}`, and returns them from `rankkernel/schema/breadcrumb_trail`. Verified live: exactly one `BreadcrumbList` in a single `@graph`, unchanged `@id` (`{base}#breadcrumb`), unchanged WebPage reference, pagination excluded, 404 omitted, front page behavior per settings, no duplicate node. The schema final item keeps its URL (current RankKernel behavior preserved).

## 9. Performance

No new tables, meta keys, transients, object-cache breadcrumb storage, or cron. Hierarchy uses WordPress APIs and already loaded objects (`get_queried_object`, `get_post_ancestors`, `wp_get_post_parent_id`, `get_ancestors`, `get_the_terms`, `get_post_type_object`, `get_taxonomy`). Query-counting tests show a representative single post and term archive add no breadcrumb-specific query on warm caches. Documentation states that cold caches can still query inside core; no absolute claim is made.

## 10. Security

11 tests cover: script and markup labels escaped, filtered labels escaped without `allow_html`, `allow_html` filtered through `wp_kses_post`, `javascript:` schemes neutralized by `esc_url`, hostile shortcode and block attributes escaped, settings separator HTML stripped, and no attribute or element breakout.

## 11. Live verification on the real site (port 10043, module enabled)

Verified over HTTP against the real WordPress install:

- Single post schema: exactly one `BreadcrumbList`; trail `Home → Blog → Real Estate → Why Your Real Estate Website Is Slow (And How to Fix IDX Lag)`; correct `@id`.
- Term archive `/industry/real-estate/`: `Home → Industry → Real Estate`, one node.
- Search: no `BreadcrumbList` (the existing piece gates on post, term, archive; search behavior unchanged).
- 404: no `BreadcrumbList`.
- Paged archive `/blog/page/2/`: `Home → Blog` with the `Page N` crumb excluded from schema.
- Visible output via a temporary post containing the block and the shortcode: `nav.rk-breadcrumbs` with `aria-label="Breadcrumbs"`, `style="--rk-breadcrumb-separator:/;"`, `ol.rk-breadcrumbs-list`, `li`, a link for Home, and the current item as `<span aria-current="page">`. The separator is a CSS custom property, not a text node.
- Disabled module: setting `rankkernel_modules` without `breadcrumbs` produced the fallback trail `Qrolic Technologies → post title`, proving the existing fallback is intact and the module adds nothing when off. Re-enabling restored the full trail.
- Every page checked emitted exactly one `BreadcrumbList`.

The temporary post used for the visible check was deleted afterward, and the module list was restored to its original value.

## 12. Tests

953 tests, 3336 assertions (up from 838 / 3051 at phase start, so plus 115 tests and plus 285 assertions). Coverage: TrailBuilder per context and edges, settings, schema integration, security, performance, renderer, API, shortcode, block, and module gating.

## 13. Files changed

New: `src/Modules/Breadcrumbs/{Item,TrailBuilder,Renderer,BreadcrumbsSettings,BreadcrumbsModule}.php`, `functions.php`, `template-tags.php`, `breadcrumbs.css`, `blocks/BreadcrumbsBlock.php`, `blocks/breadcrumbs/{block.json,breadcrumbs-editor.js,editor.css}`, the breadcrumbs tests, `docs/architecture/breadcrumbs.md`, `docs/architecture/breadcrumbs-api.md`.
Modified: `src/Plugin.php`, `src/Admin/SettingsPage.php`, `ROADMAP.md`. 26 files changed, 23 new, about +7611 / -9.

## 14. Known limitations and deviations

1. `rankkernel/breadcrumbs/post_type_settings` (listed in the spec) is NOT implemented. Primary taxonomy selection is driven only by the settings option. This is a genuine deviation and a candidate for the next change.
2. Search has no `BreadcrumbList` because the existing piece gates on post, term, and archive. This is existing behavior, unchanged by this phase; flagged so it is a conscious decision, not an oversight.
3. The multilingual guarded-call placeholder was not added.
4. Performance proof covers warm representative views; cold caches can still query inside core.
5. `ROADMAP.md` Phase 2 header and Snapshot block were left as-is.
6. The task asked to extend a "WordPress Extra include map plus PSR12 exclude list" in `phpcs.xml`; that layout was removed by GH-13, which made `WordPress` enforcement global over all source and tests with only four documented naming exceptions. New files are already enforced, so `phpcs.xml` was intentionally unchanged.

## 15. Suggested next investigations

1. Add `rankkernel/breadcrumbs/post_type_settings` with tests if the extension point is wanted.
2. Decide whether search and front page should carry a `BreadcrumbList` (currently no, matching existing gating).
3. Add the multilingual guarded compatibility hooks.
4. Consider a widget or block theme pattern later.
5. Address CodeRabbit findings on PR #16.

## 16. Standing rules for the next prompt

Stay on a `GH-<n>` branch, keep `composer lint`, `composer stan`, `composer test`, and node checks green, report exact test numbers, keep everything free with no telemetry or upsells, clean room, no new dependencies, do not change the existing Schema contracts, and do not merge without owner approval.
