# RankKernel 0.1.0 — All Findings and Remediations

Production source: `57dfc063d022d380d43947972c26f9bd976e5b56`  
Final ZIP: `rankkernel-0.1.0.zip`, 937,587 bytes, 240 files, SHA-256 `f6beba8f830994dc3ef952f7757d7ad079073d5b21787fe64c4265083732bedd`

## Finding F-01 — Metadata editor used an unverified screen post ID

**Severity:** High (object-level authorization / protected metadata exposure)  
**Reported:** November 6, 2026 review, `MetadataBox::currentPostId()`, lines 1684–1697, `$_GET['post']` line 1694.  
**Actual path:** `enqueueAssets()` and `enqueueEditorAssets()` call `localizedState( currentPostId() )`. `localizedState()` reads post meta, resolved title/description, permalink, tokens, featured image alt and REST path. Before the fix, a crafted `post` query value could select another post's data without an `edit_post` check. The screen hook and WordPress editor capability reduce exposure, but the method itself had no exact-post authorization.

**Fix:**

- Added `authorizedPostId()`: rejects non-positive IDs, requires `get_post()` to return the exact post, requires `current_user_can( 'edit_post', $postId )`, and returns `0` otherwise.
- `currentPostId()` now validates both `get_the_ID()` and `$_GET['post']` through that helper.
- `currentPostType()` validates the same raw post ID before reading its type.
- `enqueueEditorAssets()` now refuses non-post-editor screens.
- `localizedState()` revalidates the post ID defensively at entry.
- No GET nonce was added: this is a read-only screen context and the correct boundary is object authorization. A nonce scoped to a write action would break normal editor loading without adding authorization.

**Tests:** `MetadataBoxTest::test_localized_state_rejects_a_post_without_edit_capability`, `MetadataBoxTest::test_current_post_id_rejects_the_get_post_argument_without_capability`; full `MetadataBoxTest` passes (44 tests).

## Finding F-02 — Scanner-visible input sanitization gaps

**Severity:** Defense-in-depth / WordPress.org submission quality  
**Reported:** 46 incidences (October 13/28), 48 (November 3), 37 (November 6). Exact WordPress.org scanner output is unavailable; the repair covers every current superglobal occurrence in the shipped source, not only the email examples.

**Fix:** 236 current superglobal expressions across 16 shipped files were inventoried in `FULL_PLUGIN_INPUT_INVENTORY.csv`. The following boundary sanitizers/validators were applied or verified:

- `sanitize_key()` for action, tab, section, sort, status, match, code, module and directive identifiers.
- `sanitize_text_field()` for plain text, request method, post type and notice/status values.
- `sanitize_textarea_field()` for robots.txt overrides, llms.txt content, schema custom JSON and URL lists.
- `esc_url_raw()` for canonical/social/logo URLs.
- `sanitize_email()` for the support reply address.
- `sanitize_file_name()` plus upload error/MIME/size/path checks for imports and screenshots.
- `map_deep()` with a scalar-safe callback for nested metadata maps and allowlisted settings arrays.
- `(int) sanitize_text_field()` plus positive filtering for IDs and numeric page/count values; negative IDs are rejected, never converted with `absint()`.
- `sanitize_text_field()` + normalization for `REQUEST_URI` in the 404 logger and redirect dispatcher.
- `sanitize_key()` + allowlists for `rankkernel_schema_msg`, `rk_orderby`, `rk_action`, `rk_final`, `rk_chain`, `rk_path`, `rk_return`, `rk_open`, `rk_edit`, `rk_count`, `rk_source`, `rankkernel_indexnow_notice`, `rk_verify_code`.

**Important distinction:** Most of these were already allowlisted/cast/escaped downstream. The fix moves the scanner-visible sanitizer to the boundary and documents the final sink; it does not claim every line was an exploitable vulnerability. The one confirmed authorization defect was F-01.

**Tests:** Full input inventory has 0 `UNREVIEWED` and 0 `BLOCKED`; PHPUnit 2399/9212 passes; PHPCS clean; focused tests cover the nine November 6 examples.

## Finding F-03 — Request-method normalization preserved no case

**Severity:** Implementation correction (would have blocked all admin saves)  
**Cause:** An intermediate remediation used `sanitize_key()` on `$_SERVER['REQUEST_METHOD']`; `sanitize_key()` lowercases, so `'POST'` became `'post'` and all save handlers skipped.  
**Fix:** All seven save entry points use `sanitize_text_field( wp_unslash( ... ) )`, preserving the method while removing tags/control characters.  
**Tests:** `DashboardPageTest`, `SettingsPageTest`, `SitemapSettingsAdminTest`, `MonitorAdminTest`, `RedirectsAdminTest`, `SchemaSettingsAdminTest`, `SupportPageTest` all pass.

## Finding F-04 — Schema type/default case was lowercased

**Severity:** Implementation correction (would have silently disabled schema type selection)  
**Cause:** `sanitize_key()` was used on `rankkernel_schema_type` and `schema_default_<post_type>`, but supported values are case-sensitive schema names such as `Product` and `NewsArticle`.  
**Fix:** Both use `sanitize_text_field()` and are validated against `SchemaTypes::SUPPORTED` / the settings store allowlist.  
**Tests:** `SchemaMetaboxTest::test_save_valid_post_merges_schema_subtree_only`, schema settings save tests.

## Finding F-05 — Negative numeric IDs could be converted to positive IDs

**Severity:** Medium correctness/authorization hardening  
**Risk:** `absint()` turns `-7` into `7`; a forged negative row ID could target a different row if the result were used without a positive check. The original code used `(int)` with `max(0, ...)`; an intermediate remediation introduced `absint()` and was corrected before finalization.  
**Fix:** IDs use `(int) sanitize_text_field()` and positive filtering. Bulk arrays iterate only scalar values and skip non-positive IDs. Social image ID saves `0` for negative/non-numeric input.  
**Tests:** Existing bulk/row tests plus `SettingsPageTest::test_non_numeric_or_negative_image_id_saves_as_zero`; all pass.

## Finding F-06 — No-JS fallback and enqueue compliance

**Status:** Already fixed before this pass; verified again.  
**Evidence:** `AdminStyles::enqueueNojsLayer()` and `enqueueJsFlag()`; `AdminMenu.php:184-185`; no `<style>`, `<link rel="stylesheet">` or `EnqueuedResources` ignores in shipped views.  
**Tests:** `AdminStylesTest`.

## Finding F-07 — `.htaccess` arbitrary directives and unsafe backup fallback

**Status:** Already fixed before this pass; verified again.  
**Evidence:** `HtaccessFile::DIRECTIVES` fixed allowlist; `saveBlock()` generates only server-side strings; backups use non-autoloaded `rankkernel_htaccess_backups`; atomic same-directory temp+rename. No `copy()` fallback beside the root file.  
**Tests:** `HtaccessFileTest`.

## Finding F-08 — Breadcrumb callback/filter output

**Status:** Already fixed before this pass; verified again.  
**Evidence:** `sanitize_breadcrumb_html()` runs after `rankkernel/breadcrumbs`; KSES allowlist plus URL scheme/protocol-relative/backslash/control checks and strict separator style. Shortcode, block and template-tag paths all route through it.  
**Tests:** `BreadcrumbsSecurityTest`, `BreadcrumbsBlockTest`, `BreadcrumbsOutputTest`.

## Finding F-09 — External service disclosure and path resolution

**Status:** Already fixed before this pass; verified again.  
**Evidence:** `readme.txt` Privacy + External Services disclose the support email and IndexNow endpoint/data/timing. `https://www.indexnow.org/documentation` and `https://www.indexnow.org/terms` return HTTP 200; no standalone privacy URL exists and the readme says so. No `wp-content/uploads` hardcoding found.

## Accepted risks / limitations

1. **Trademark/ownership:** The repository contains no documentary proof of trademark ownership or legal clearance for “RankKernel”. This is an owner decision, not a code claim.
2. **WordPress.org scanner output:** The exact 37/48/46 scanner dump is unavailable. The audit reproduces the reported examples and independently inventories every current superglobal occurrence; it does not claim a one-to-one scanner mapping.
3. **Site-root files:** `.htaccess` and opt-in `llms.txt` are written at the site root because those files only function there. Both paths are contained, fail closed, and have explicit backup/delete safeguards.
4. **Plugin Check warnings:** 203 template-local prefix warnings and 20 DirectDB false positives remain; each is classified in `FALSE_POSITIVE_JUSTIFICATIONS.md`. No warnings were suppressed globally.
5. **LiteSpeed/real-Apache:** Not re-run in this final pass; earlier release evidence covers Apache 2.4.58, and the current changes are PHP input/output paths.
