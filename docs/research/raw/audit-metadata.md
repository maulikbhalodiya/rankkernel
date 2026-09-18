# Metadata Module Audit (code and tests, adversarial)

Repo: `wp-content/plugins/rankkernel` · Version audited `0.1.0` (ROADMAP snapshot line 24) · Audit date 2026-09-16.
Scope: `src/Modules/Metadata/`, registration and gating, `src/Settings/SettingsStore.php`, metadata unit tests, and the claimed state in `docs/ROADMAP.md` and `docs/competitor-analysis/feature-parity-matrix.md`.

## Verdict

The Metadata module is a genuinely working v1 head engine for the flat front-end case, but it is materially smaller than the parity matrix implies and it has several live correctness defects. The single-pass `wp_head@1` renderer, one-query memoized meta read, token replacer with per-context caches, settings-driven title/description templates, webmaster codes and the OG/Twitter chains are real and tested. However, the head pipeline never removes or merges core `rel_canonical` and core `wp_robots`, so every singular page emits two canonical tags, and a per-post canonical override produces two *conflicting* canonicals; every page also carries a core robots tag that RankKernel's robots logic cannot see. Archive contexts resolve post-only tokens against a term or user ID, so `%%title%%`, `%%author%%` and `%%category%%` can resolve to the wrong thing on taxonomy and author archives. `Context::meta()` bypasses `MetaPayload::decodeMetaValue()`, so legacy JSON/serialized rows are silently dropped by the head pipeline even though the decoder exists and is tested. There is no per-post metadata editing surface of any kind (title, description, canonical, robots, OG, Twitter), no metadata editor asset and no custom metadata REST route; the only write surfaces are the Schema metabox (which merges the `schema` subtree only), the core REST meta field registered via `register_meta(show_in_rest)`, and programmatic writes. Per-context templates and extended variables are absent exactly as the roadmap's own micro-gap audit admits. The parity matrix overstates at least four rows (`Auto canonical output`, `Robots meta index...`, `Open Graph output (full set)`, `Twitter username`, plus `Additional social profiles`), calling DONE/PARTIAL what the code does not deliver cleanly or at all.

Update (branch GH-27): the defect claims in this paragraph are fixed and it is a historical record of the pre-fix code. `HeadRenderer` now removes core `rel_canonical` (`HeadRenderer.php:86`) and contributes through the single core `wp_robots` tag (`HeadRenderer.php:92`), so one canonical and one robots tag are emitted; archive contexts resolve `%%title%%`, `%%author%%` and `%%category%%` against the queried term or user (`Context.php:528-543`); `Context::meta()` routes through `MetaPayload::decodeMetaValue()` (`Context.php:115`); and the per-post editing surface now exists as `src/Admin/MetadataBox.php` with its view. Unrelated gaps below remain valid.

## Functionality matrix

| Module | Functionality | Expected Behavior | Current Code | Status | Evidence | Gap |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| Metadata | SEO title output | Emit one document title from payload, else template, else WP default | `HeadRenderer::title()` 91-130, hooked 82; payload literal 104-115, template 118-126 | COMPLETE | `HeadRenderer.php:91-130`; tests `HeadRendererTest::test_title_returns_payload_literal`, `test_title_resolves_tokens_in_payload`, `test_title_returns_wp_default_when_no_payload` | Payload title with no token returned raw, tokens only resolved if present (line 106); no escaping at filter exit, relies on core |
| Metadata | Meta description output | Payload, else template, else excerpt/term desc, else omit | `HeadRenderer::resolveDescription()` 268-308; echo 158-160 | COMPLETE | `HeadRenderer.php:268-308`; tests `test_render_description_omitted_when_empty`, `test_render_escapes_description` | None |
| Metadata | Canonical URL output | Emit one canonical, omit on search/404 | `HeadRenderer::resolveCanonical()` 380-392; echo 172-174 | PARTIAL | `HeadRenderer.php:380-392`; tests `test_render_canonical_escaped`, `test_author_archive_no_home_canonical`, `test_canonical_omitted_on_search` | Core `rel_canonical` not removed (`wp-includes/default-filters.php:363`), duplicate canonical on singular; payload override conflicts with core |
| Metadata | Robots directives output | index/noindex, follow/nofollow, advanced directives | `HeadRenderer::buildRobotsContent()` 317-371; echo 165-167 | PARTIAL | `HeadRenderer.php:317-371`; tests `test_render_omits_robots_for_clean_index_follow`, `test_render_emits_robots_with_noindex`, `test_render_robots_max_snippet` | Core `wp_robots` not merged/removed (`default-filters.php:358`, `robots-template.php:188`), duplicate robots meta; no restrictive-wins |
| Metadata | Open Graph output | Full OG set with image, dimensions, alt | `HeadRenderer::renderOgTags()` 401-487; image chain `Context::resolveOgImageData()` 579-642 | PARTIAL | `HeadRenderer.php:401-487`; `Context.php:579-642`; tests `test_render_og_image_fallback_chain`, `test_og_image_custom_url_no_dims_with_featured`, `test_og_image_image_id_dims_match` | No `og:image:alt`, no `article:published_time`/`modified_time`, no global default image; non-singular gets no image |
| Metadata | Twitter metadata output | card, title, desc, image, site handle | `HeadRenderer::renderTwitterTags()` 495-569 | PARTIAL | `HeadRenderer.php:495-569`; test `test_twitter_title_resolves_tokens_no_leak` | No `twitter:site`/`twitter:creator`; card always emitted even on 404/search |
| Metadata | Webmaster verification | Emit Google/Bing/Yandex/Baidu/Pinterest meta | `HeadRenderer::renderWebmasterTags()` 574-590 | COMPLETE | `HeadRenderer.php:574-590`; tests `test_render_webmaster_tags`, `test_render_baidu_webmaster_tag` | Yandex/Pinterest untested but coded |
| Metadata | Token replacement (builtins) | Resolve site, post, archive, pagination tokens | `TagsReplacer::doReplace()` 72-110; map 75-85 | PARTIAL | `TagsReplacer.php:75-110`; tests `test_custom_token_via_filter_resolves`, `test_unknown_token_stripped` | Only 9 tokens (`title, sitename, sep, excerpt, date, author, category, page, currentdate`); grammar is `%%name%%` only, no `%name%` |
| Metadata | Fallback behaviour | Payload, template, excerpt, WP default chains | title 104-129; description 268-308; og 409-413, 420-424 | COMPLETE | `HeadRenderer.php:104-129`, `268-308`; tests `test_title_returns_wp_default_when_no_payload`, `test_render_description_omitted_when_empty` | None |
| Metadata | Context detection | Classify post, term, home, search, 404, feed, preview, archive | `Context::queriedType()` 187-249 | PARTIAL | `Context.php:187-249` | Term and author archives collapse to `term`/`archive` and later reuse their non-post ID as a post ID (see archive rows); `archive` does not distinguish author/date/post-type in the type itself |
| Metadata | Homepage handling | Home URL canonical, OG website, homepage template | `permalink()` 531-559; og:type 445-449 | PARTIAL | `Context.php:531-559`; tests `test_permalink_home_for_home_type`, `test_posts_page_permalink_returns_page_for_posts` | No homepage-specific title/description/robots template; no homepage OG image |
| Metadata | Singular post | Correct title, desc, canonical, robots, OG | `queriedType` 220-222; `permalink` 420-424; `meta` 106-107 | COMPLETE | `Context.php:220-222`, `420-424`; tests `test_meta_performs_exactly_one_get_post_meta`, `test_og_image_fallback_featured` | Canonical duplication with core (see canonical row) |
| Metadata | Singular page | Same as singular post for `page` type | `queriedType` 220-222 (`is_singular` covers pages) | COMPLETE | `Context.php:220-222` | None specific |
| Metadata | Archives (author/date/post-type) | Correct archive permalink, no home canonical | `permalink()` 436-529 | PARTIAL | `Context.php:436-529`; test `test_permalink_author_archive` | Date and post-type branches untested; archive titles mis-resolve post tokens |
| Metadata | Taxonomies | Term meta read, term link canonical, term description | type 224-234; `meta` 108-110; `permalink` 426-434; desc 293-305 | PARTIAL | `Context.php:108-110`, `426-434`; test `test_meta_term_performs_one_get_term_meta` | No term template, no term editing UI; `%%title%%` uses `get_the_title(term_id)` (bug); term name token absent |
| Metadata | Author archives | Author link canonical and author title | `permalink()` 438-464; `title()` 361-381 | PARTIAL | `Context.php:438-464`; test `test_permalink_author_archive` | `%%title%%`/`%%author%%` resolve against the user ID as if a post ID |
| Metadata | Date archives | Derive year/month/day archive link | `permalink()` 467-505 | PARTIAL | `Context.php:467-505` | No date template; branch untested |
| Metadata | Search | Noindex,follow and omit canonical | type 212-214; robots 325-328; canonical 381-383 | COMPLETE | `HeadRenderer.php:325-328`, `381-383`; test `test_canonical_omitted_on_search` | None |
| Metadata | 404 | Noindex,follow and omit canonical | type 216-218; robots 325-328; canonical 381-383 | COMPLETE | `HeadRenderer.php:325-328`, `381-383` | Code path shared with search; no dedicated 404 render test |
| Metadata | Pagination | Paged-aware hash, page token, page canonical | `hash()` 73-90; `paginated()` 256-274; `resolvePage()` 232-262 | PARTIAL | `Context.php:73-90`; `TagsReplacer.php:232-262` | `%%page%%` output hardcoded English 248/255/258; canonical is base permalink without `/page/N/` |
| Metadata | Noindex handling | Payload noindex respected and propagated to canonical | robots 317-371; canonical 380-392 | PARTIAL | `HeadRenderer.php:317-371`, `380-392`; test `test_render_emits_robots_with_noindex` | Canonical still emitted on payload noindex pages (no `is_indexable()` gate) |
| Metadata | Canonical normalisation | Normalise custom canonical, dedupe | `MetaPayload::sanitize()` 142-144 (esc_url_raw only) | MISSING | `MetaPayload.php:142-144`; `HeadRenderer.php:385-391` | No trailing-slash/case normalisation, no core dedupe |
| Metadata | `_rankkernel_meta_data` key | Register and read single object meta | `MetadataModule::register()` 127-140; `Context::meta()` 106-107 | COMPLETE | `MetadataModule.php:127-140`; `Context.php:106-107`; test `test_meta_performs_exactly_one_get_post_meta` | None |
| Metadata | Single-row storage | One meta row, single=true, one DB read | `register_meta` single 132; `meta()` memo 98-99 | COMPLETE | `MetadataModule.php:132`; `Context.php:97-120`; test `test_meta_performs_exactly_one_get_post_meta` | None |
| Metadata | Save behaviour | Persist full metadata payload | `SchemaMetabox::handleSave()` 605-721 (schema subtree only) | PARTIAL | `SchemaMetabox.php:685-720`; `MetadataModule.php:133-136` | No handler saves title/desc/canonical/robots/og/twitter; only schema subtree via metabox, whole payload via core REST meta |
| Metadata | Update behaviour | Update existing payload without clobbering other keys | `SchemaMetabox::handleSave()` 685-720 merges schema only | PARTIAL | `SchemaMetabox.php:685-720` | Same as save; other payload keys never writable from any UI |
| Metadata | Delete behaviour (runtime) | Delete a post's metadata row | none | MISSING | no `delete_post_meta`/`delete_metadata` for the key in `src/` (grep) | No per-post/per-term metadata delete path or UI |
| Metadata | Delete behaviour (uninstall) | Purge plugin meta on uninstall when opted in | `uninstall.php` 20-60 | COMPLETE | `uninstall.php:20-22` gate, `39`, `48`, `57` prefix deletes | None; purge requires `purge_on_uninstall` |
| Metadata | Defaults | Fill missing keys with defaults | `MetaPayload::defaults()` 44-81; `sanitize()` 130-132 | COMPLETE | `MetaPayload.php:44-81`, `130-132`; test `test_sanitize_fills_missing_keys_from_defaults` | None |
| Metadata | Malformed metadata handling | Fail open on any bad stored shape | `MetaPayload::decodeMetaValue()` 93-120 | PARTIAL | `MetaPayload.php:93-120`; `MetaPayloadDecodeTest` (5 tests) | `Context::meta()` 112-117 does not call the decoder; a JSON/serialized string row becomes `[]` in the head pipeline |
| Metadata | Backward compatibility | Read serialized and JSON legacy rows | `decodeMetaValue()` 93-120 | PARTIAL | `MetaPayload.php:93-120`; tests `test_serialized_payload_decodes`, `test_json_payload_decodes` | Decoder only used by `SchemaMetabox::readPayload()` 1059-1067, not by `Context` |
| Metadata | Term payload key | Register/read `_rankkernel_term_data` | `MetadataModule` 143-156; `Context` 108-110 | PARTIAL | `MetadataModule.php:143-156`; `Context.php:108-110`; test `test_meta_term_performs_one_get_term_meta` | No UI or write path except core REST term meta; no term metabox |
| Metadata | Token parsing | Parse tokens, keep text | `TagsReplacer::replace()` 51-110; regex 99-107 | COMPLETE | `TagsReplacer.php:99-107`; tests `test_custom_token_via_filter_resolves`, `test_unknown_token_stripped` | None |
| Metadata | Unknown tokens | Strip to empty | `doReplace()` 104 | COMPLETE | `TagsReplacer.php:104`; test `test_unknown_token_stripped` | None |
| Metadata | Empty values | Empty token value substitutes empty string | `doReplace()` 101-105 | COMPLETE | `TagsReplacer.php:101-105`; test `test_unknown_token_stripped` covers empty map entries | None |
| Metadata | Fallback values | Token with no data yields empty, template still renders | `resolveToken()` 129-145 `default => ''` | COMPLETE | `TagsReplacer.php:129-145`; `HeadRenderer.php:118-126` | None |
| Metadata | Escaping | Escape tokens at output | head tags `esc_attr`/`esc_url` 159-186, 416-485 | PARTIAL | `HeadRenderer.php:159`, `173`, `416`, `460`; test `test_render_escapes_description` | Title filter output (91-130) is returned unescaped; relies on core `wp_head` title escaping |
| Metadata | Repeated token use | Each occurrence replaced | `preg_replace_callback` global 99-107 | COMPLETE | `TagsReplacer.php:99-107`; test `test_memoization_same_ctx_field_invokes_filter_once` | None |
| Metadata | Memoisation | Same field resolved once per request | `TagsReplacer::memo` 27, 52-60; `Context::resolvedMemo` 44, 130-138 | COMPLETE | `TagsReplacer.php:51-63`; `Context.php:129-141`; tests `test_memoization_same_ctx_field_invokes_filter_once`, `test_memoization_different_field_separate`, `test_token_caching_across_different_fields` | `Context::resolvedMemo` has no clear method (unlike replacer) |
| Metadata | Cache isolation between contexts | Different contexts never share tokens | `resolveToken` keyed by `hash|token` 122-127 | COMPLETE | `TagsReplacer.php:122-127`; test `test_cached_token_values_stay_isolated_between_contexts` | None |
| Metadata | Stale values when context changes | Invalidate when context mutates | `clearMemo()` 290-293 only | PARTIAL | `TagsReplacer.php:290-293` | No invalidation on `save_post`/filter mutation within a request; no test |
| Metadata | Dates and localisation | Localised date and page strings | `resolveDate` 153-163; `resolveCurrentDate` 269-285; `resolvePage` 232-262 | PARTIAL | `TagsReplacer.php:153-163`, `248`, `255`, `258`, `269-285` | `%%page%%` hardcoded `Page %d of %d` (no i18n); no date-format argument support |
| Metadata | Single generation pipeline | One `wp_head` pass emits all tags | `HeadRenderer::render()` 135-191; one hook 81 | COMPLETE | `HeadRenderer.php:81`, `135-191`; test `test_r2_action_fired_once_with_context` | None |
| Metadata | Deterministic output | Stable tag order and values | fixed order 158-186 | PARTIAL | `HeadRenderer.php:158-186` | Ordering stable but `og:locale`/dates vary by environment; no determinism test |
| Metadata | No duplicate tags | No duplicate canonical/robots/og/twitter | internal single emission | BROKEN | `HeadRenderer.php:172-174`, `165-167`; core `default-filters.php:358`, `363` | Core emits its own canonical and robots alongside; no `remove_action`/filter anywhere in `src/` |
| Metadata | Correct escaping (head) | All output escaped | `esc_attr`/`esc_url` throughout 159-186, 401-590 | COMPLETE | `HeadRenderer.php:159-186`, `401-590`; test `test_render_escapes_description` | None |
| Metadata | Hook priority | Render early, title via filter | `wp_head@1`, `pre_get_document_title@10` | COMPLETE | `HeadRenderer.php:81-82` | None |
| Metadata | Core canonical/robots interaction | Merge or replace core behaviour | none | BROKEN | `HeadRenderer.php:80-83`; `wp-includes/default-filters.php:358`, `363`, `280` | Duplicate canonical, conflicting custom canonical, duplicate robots, no restrictive-wins |
| Metadata | Suppression on admin/AJAX/REST | Emit nothing off the front end | only preview/feed guards 136-151 | PARTIAL | `HeadRenderer.php:136-151`; contrast `Redirector.php:118-122`, `Logger.php:186-190` | No `is_admin()`/`wp_doing_ajax()`/`REST_REQUEST` guard; works only because `wp_head` does not fire there; untested |
| Metadata | Per-context templates | Post type, taxonomy, homepage, author, date, search, 404 templates | none beyond global `title_template`/`description_template` | MISSING | `SettingsStore.php:70-71`; `HeadRenderer.php:118`, `276`; ROADMAP §1.7.1 line 331 | Roadmap micro-gap; scheduled STEP 1 |
| Metadata | Post type templates | Per-CPT title/desc template | none | MISSING | no per-type template key in `SettingsStore::ALLOWED_KEYS` 31-54 | Same |
| Metadata | Taxonomy templates | Per-taxonomy template | none | MISSING | `SettingsStore.php:31-54`; ROADMAP §1.7.1 line 331 | Same |
| Metadata | Homepage template | Homepage title/desc/robots template | none | MISSING | `HeadRenderer.php:118`, `276`; ROADMAP §1.7.1 line 331 | Same; matrix row 105 PLANNED |
| Metadata | Author template | Author archive template | none | MISSING | `SettingsStore.php:31-54`; ROADMAP §1.7.1 line 331 | Same |
| Metadata | Date archive template | Date archive template | none | MISSING | `SettingsStore.php:31-54`; ROADMAP §1.7.1 line 331 | Same |
| Metadata | Search template | Search title template | none | MISSING | `HeadRenderer.php:118`; ROADMAP §1.7.1 line 331 | Same; search only forced noindex |
| Metadata | 404 template | 404 title template | none | MISSING | `HeadRenderer.php:118`; ROADMAP §1.7.1 line 331 | Same |
| Metadata | Extended variables | Custom fields, parent title, term name | none | MISSING | `TagsReplacer.php:75-85`; ROADMAP §1.7.1 line 333 | Roadmap micro-gap |
| Metadata | Custom field variables | `%%custom_field%%` style token | none | MISSING | `TagsReplacer.php:75-85` | Same; matrix row 150 |
| Metadata | Parent title | `%%parent_title%%` token | none | MISSING | `TagsReplacer.php:75-85`; ROADMAP §1.7.1 line 333 | Same |
| Metadata | Term name | `%%term%%` token | none | MISSING | `TagsReplacer.php:75-85` | Same; term archives only expose `%%title%%` which mis-resolves |
| Metadata | Page number | Page token | `resolvePage()` 232-262 | PARTIAL | `TagsReplacer.php:232-262` | Exists but hardcoded English and only >1 pages |
| Metadata | Product variables | Woo product title/price tokens | none | MISSING | no product token in `TagsReplacer.php:75-85`; ROADMAP §1.6.1 line 315 | Belongs to STEP 1.6.1 WooCommerce work |
| Metadata | Per-post editing UI | Edit title/desc/canonical/robots/OG/Twitter | none | MISSING | only `SchemaMetabox` registered (`Plugin.php:144-146`); `SchemaMetabox.php:36`, `685-720` schema-only; matrix rows 94-98 | No user-facing write surface |
| Metadata | Custom metadata REST route | `rankkernel/v1` metadata endpoint | none | MISSING | only `SettingsController` and `ModulesController` register routes (`Plugin.php:179-185`) | Headless route is ROADMAP §1.8 line 356 |
| Metadata | Core REST meta exposure | Metadata writable via REST | `register_meta(..., show_in_rest)` | COMPLETE | `MetadataModule.php:133-139`, `149-155` | Exposes `meta` on core endpoints; no dedicated route |
| Metadata | Metadata editor asset | Editor JS for metadata fields | none | MISSING | only `assets/js/schema-metabox.js`, `schema-settings.js`, `redirects-admin.js`, `monitor-admin.js`, `breadcrumbs-admin.js` | No metadata asset |
| Metadata | Module registration/gating | OFF = zero hooks | `isEnabled()` 96-121; `ModuleManager::bootEnabled()` 107-163 | COMPLETE | `MetadataModule.php:96-121`, `178-181`; `Plugin.php:150-151`; test `ModuleManagerTest::test_module_off_boot_never_called_zero_hooks` | None |

## Matrix overstatement check

Rows in `docs/competitor-analysis/feature-parity-matrix.md` where the stated status does not match the code.

| Matrix row (line) | Matrix status | Actual status | Why |
| :--- | :--- | :--- | :--- |
| Auto canonical output (120) | DONE | PARTIAL | Core `rel_canonical` still runs (`wp-includes/default-filters.php:363`); the plugin never removes it, so singular pages emit two canonical tags. |
| Custom canonical per object (121) | PARTIAL | PARTIAL (worse than implied) | The override *is* emitted (`HeadRenderer.php:385-389`) but core also emits the default permalink canonical, producing two conflicting canonicals; PARTIAL understates a live conflict. |
| Robots meta index, noindex, follow, nofollow (124) | DONE | PARTIAL | No merge with core `wp_robots` (`default-filters.php:358`, `robots-template.php:188`); duplicate robots tags and no restrictive-wins. |
| Open Graph output (full set) (132) | DONE | PARTIAL | `renderOgTags()` emits title/desc/url/type/image/site_name/locale but no `og:image:alt` and no article time metadata. |
| Twitter username (136) | PARTIAL | MISSING | `renderTwitterTags()` emits no `twitter:site`/`twitter:creator` and no twitter-handle setting exists (`SettingsStore.php:31-54`). |
| Additional social profiles (137) | PARTIAL | MISSING | `social_*` keys are stored (`SettingsStore.php:35-40`) but consumed nowhere in `src/` (grep: zero usages outside SettingsStore/SettingsPage) and there are no settings fields, so no social profile is ever output. |
| Per-post canonical override (97) | PARTIAL | PARTIAL (thin) | Defensible: payload/REST can set it, no UI. Note the conflict with core canonical above. |
| Global title and description templates (102) | DONE | DONE (verified) | `title_template`/`description_template` are real (`SettingsStore.php:70-71`), editable (`Admin/Views/settings.php:65`, `72`) and consumed (`HeadRenderer.php:118`, `276`). Accurate. |
| Custom variables via code filter (114) | DONE | DONE (verified) | `rankkernel/tokens` filter at `TagsReplacer.php:93`; test `test_custom_token_via_filter_resolves`. Accurate. |
| Title separator (115) | DONE | DONE (verified) | `Context::separator()` 401-405; test `test_title_resolves_tokens_in_payload`. Accurate. |
| Token or variable system (110) | PARTIAL | PARTIAL (verified) | Only 9 built-ins (`TagsReplacer.php:75-85`). Accurate. |
| Homepage Open Graph (133) | DONE | DONE (thin) | OG emitted on `home` with `og:type=website` (`HeadRenderer.php:445-449`) but no homepage OG image; defensible, thin. |

No matrix row understates reality for a metadata capability that exists in code; all mismatches are overstatements.

## Gap classification

### P0 (correctness or security)
- Core `rel_canonical` never removed while the plugin emits its own canonical (`HeadRenderer.php:172-174`; core `wp-includes/default-filters.php:363`), and a payload override yields two conflicting canonicals. Wrong signals on every singular page with an override.
- Core `wp_robots` never removed or merged (`HeadRenderer.php:165-167`; core `default-filters.php:358`), so robots directives are duplicated and RankKernel cannot implement restrictive-wins. Correctness.
- Archive contexts reuse a term/user ID as a post ID: `Context::title()` calls `get_the_title(term_id)` (361-381) and `TagsReplacer::{resolveAuthor,resolveCategory}` call `get_post_field('post_author'|'get_the_category', term/user id)` (171-224). Taxonomy and author archive titles/tokens can be wrong. Correctness.
- `Context::meta()` bypasses `MetaPayload::decodeMetaValue()` (112-117 vs `MetaPayload.php:93-120`), so legacy JSON/serialized payload rows are silently dropped by the head pipeline even though the decoder exists and is tested. Correctness/data loss.

### P1 (needed to call the existing module complete)
- Canonical emitted on payload-noindex pages and paginated pages canonicalise to the base URL (`HeadRenderer.php:380-392`). Contradicts the roadmap's own "omit on noindex" rule (ROADMAP §1.1 line 198).
- No per-post metadata editing UI (title/description/canonical/robots/OG/Twitter); roadmap §1.7.1 line 330 calls this a metadata micro-gap. Module is unusable for normal users without it.
- Per-context templates (post type, taxonomy, homepage, author, date, search, 404) absent; roadmap §1.7.1 line 331.
- Extended variables (custom fields, parent title, term name) absent; roadmap §1.7.1 line 333.
- No global default OG image and no `og:image:alt` (`HeadRenderer.php:456-472`); social sharing falls back only to featured images on singular.
- No `twitter:site`/`twitter:creator` output and no handle setting (`HeadRenderer.php:495-569`; `SettingsStore.php:31-54`).
- Social profile settings are stored but never consumed; they produce no output (`SettingsStore.php:35-40`, zero usages).
- `%%page%%` is hardcoded English (`TagsReplacer.php:248`, `255`, `258`). i18n correctness.
- Parameterised token syntax and the `%name%` grammar are absent (`TagsReplacer.php:99`); roadmap §1.1 line 193.

### P2 (UX or admin improvement)
- Snippet/SERP preview and social card previews (`HeadRenderer` has none); roadmap §1.7.1 line 332.
- Bulk edit of titles/descriptions; matrix row 101.
- No settings UI for social profiles or a Twitter handle (`Admin/SettingsPage.php` has no social fields).
- No per-post metadata delete/edit UI.

### P3 (belongs to a later roadmap step)
- Product/WooCommerce tokens and variables; ROADMAP §1.6.1 lines 315-316.
- Headless metadata REST route and WPGraphQL fields; ROADMAP §1.8 line 356.
- Custom verification/head meta tags; ROADMAP §1.5.3 lines 298-299 and matrix row 145.
- Noindex password-protected pages and paginated subpages; ROADMAP §1.1 line 196 and matrix rows 129-130.
- Per-type/taxonomy/author/date robots defaults; matrix row 126 PARTIAL and ROADMAP §1.1 line 196.

## Tests that would be needed

- Core canonical removal: assert exactly one `rel="canonical"` on a singular page when a payload override is set, and that no default canonical is emitted alongside it.
- Core robots merge: assert exactly one robots meta on a noindex page and that core `noindex`/`max-image-preview` survives a RankKernel render (restrictive-wins).
- Archive token resolution: `%%title%%`, `%%author%%`, `%%category%%` on a category/tag archive must return the term context, not a colliding post ID.
- Author archive token resolution: `%%author%%` on an author archive returns the author display name, not a colliding post's author.
- 404 render: dedicated test asserting no canonical, `noindex, follow`, and zero unexpected OG/Twitter leakage.
- Legacy payload decode: `Context::meta()` must surface a JSON or serialized `_rankkernel_meta_data` row (mirror `MetaPayloadDecodeTest`).
- Canonical pagination and noindex: canonical includes `/page/N/`; canonical omitted when payload robots `index=false`.
- `%%page%%` i18n: assert localised string, not literal `Page N of M`.
- Escaping of the title filter result.
- `max_image_preview` / `max_video_preview` / `noimageindex` / `noarchive` emission (only `max_snippet` is currently tested).
- Admin/AJAX/REST suppression: assert `render()` not hooked or emits nothing when `is_admin()`, `wp_doing_ajax()` or `REST_REQUEST`.
- Social profile output: a test proving `social_*` settings reach output (currently none).
- Uninstall purge gating on `purge_on_uninstall` true/false and prefix coverage (no test found for `uninstall.php`).
- Runtime metadata delete/update path (no test exists because no path exists).
- Determinism: fixed tag set and order for a fixed context.
