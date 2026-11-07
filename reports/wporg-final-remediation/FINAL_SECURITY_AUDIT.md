# RankKernel 0.1.0 — Final Security Audit

Production source: `57dfc063d022d380d43947972c26f9bd976e5b56`  
Branch: `release/wporg-0.1.0`  
Package: `rankkernel-0.1.0.zip`  
Size: 937,587 bytes — 240 files — SHA-256 `f6beba8f830994dc3ef952f7757d7ad079073d5b21787fe64c4265083732bedd`  
Manifest SHA-256: `b0057ec61e3e8b930625319e64ad998e7ff7d6dbfc35d635bd39b76fe68e3ba6`

## Scope and method

The audit used the actual package, not the repository alone. `.distignore` and the deterministic builder were applied first; the candidate contains 240 files, all under a single `rankkernel/` directory. Tests, CI, docs, vendor, developer tooling and `reports/` are excluded.

Evidence was collected at occurrence level:

- `FULL_PLUGIN_INPUT_INVENTORY.csv` — 236 current superglobal expressions in 16 shipped files. 136 `FIXED`, 93 `PASS`, 6 `COMMENT_OR_DOCBLOCK_ONLY`, 1 `ACCEPTED_WITH_REASON`. **0 UNREVIEWED, 0 BLOCKED.**
- `FULL_PLUGIN_OUTPUT_ESCAPING_INVENTORY.csv` — 1,244 output statements. 1,231 `PASS`, 13 `ACCEPTED_WITH_REASON`. **0 UNREVIEWED, 0 BLOCKED.**
- `FULL_PLUGIN_NONCE_CAPABILITY_INVENTORY.csv` — 95 capability/nonce/permission controls.
- `FULL_PLUGIN_SQL_INVENTORY.csv` — 37 `$wpdb` operations, all prepared or fixed-identifier statements.
- `FULL_PLUGIN_FILESYSTEM_INVENTORY.csv` — 41 filesystem operations, including uploads, containment, backups and cleanup.
- `FULL_PLUGIN_REST_AJAX_INVENTORY.csv` — 7 REST routes and 1 authenticated AJAX action.
- `FULL_PLUGIN_CALLBACK_INVENTORY.csv` — shortcode, block, filter, schema and enqueue callbacks.
- `WORDPRESS_ORG_COMPLIANCE_MATRIX.csv` — categories A–N.

## Confirmed finding and resolution

The one confirmed authorization defect was `MetadataBox::currentPostId()`: the screen `post` argument could select another post's metadata/resolved content for `localizedState()` without an exact-post `edit_post` check. The fix adds `authorizedPostId()` (existence + exact ID + `current_user_can( 'edit_post' )`), validates both `get_the_ID()` and `$_GET['post']`, refuses non-post-editor screens, and revalidates inside `localizedState()`. Two regression tests cover the unauthorized path. No GET nonce was added because the context is read-only and object authorization is the correct boundary.

The remaining November 6 examples were mostly scanner-visible sanitization gaps, not confirmed exploits: `rk_orderby`, `rk_action`, `rk_final`, `rankkernel_schema_custom`, `rankkernel_schema_msg`, `REQUEST_URI`, `rk_htaccess_directives`, and `rankkernel_meta_focus_keywords` were already allowlisted, cast, normalized or sanitized downstream. Their boundaries now use the correct sanitizer directly, with array/type checks preserved.

## Control review

### Inputs

- Superglobals are unslashed where WordPress slashes request data and sanitized with the correct type: `sanitize_key` for identifiers/enums, `sanitize_text_field` for plain text, `sanitize_textarea_field` for multiline text/JSON/URL lists, `esc_url_raw` for URLs, `sanitize_email` for email, `sanitize_file_name` for upload names, `(int) sanitize_text_field` for signed IDs, `map_deep` for nested maps.
- Array shape is checked before element access. Unknown keys are dropped. IDs are positive-filtered; negative IDs are never converted with `absint`.
- JSON schema input is capped at 1 MiB and depth 64, decoded with error checks, then normalized by `MetaPayload::sanitize`.
- Uploads validate error, actual `filesize`, MIME/extension, temp path with `is_uploaded_file`/`is_readable`, and private storage.
- `REQUEST_URI` removes NUL/control characters, is normalized, and is never echoed raw.
- REST/AJAX inputs use permission callbacks and allowlists; AJAX uses `check_ajax_referer` plus `manage_options`.

### Authorization and nonces

- Every settings/metadata/redirect/monitor/instant-indexing/support mutation requires a capability and an action-scoped nonce before dispatch.
- `MetadataBox` uses `edit_post`; profile fields use `edit_user`; post/term/user meta auth callbacks use object capabilities; REST routes use permission callbacks.
- The `currentPostId()` GET path is read-only and does not add a nonce; this is documented and tested.

### Outputs

- HTML text/attributes/URLs use `esc_html`/`esc_attr`/`esc_url`; controlled rich HTML uses narrow `wp_kses`/`wp_kses_post`.
- JSON-LD uses `wp_json_encode` with `JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP`; sitemap XML uses `esc_xml`; CSV uses `fputcsv` plus formula-injection escaping; redirects use validated destinations and `wp_safe_redirect`.
- The breadcrumb shortcode, block and `rankkernel/breadcrumbs` filter all pass final HTML through `sanitize_breadcrumb_html()` (KSES allowlist, URL scheme/backslash/protocol-relative checks, strict separator style).
- Raw `echo` statements are static markup, pre-escaped fragments, or encoded JSON/XML/CSV bodies; each is listed in the output inventory.

### SQL, filesystem, external services

- All `$wpdb` calls use `prepare()` for values or fixed/validated plugin-owned table identifiers. Uninstall DROP names are regex-gated/escaped.
- `.htaccess` backups are non-autoloaded DB options; writes use same-directory temp file, byte check, mode preservation and atomic rename. No public backup fallback.
- `.htaccess` directives are a fixed two-entry allowlist; no admin text reaches the file.
- Optional physical `llms.txt` is path-contained, never overwrites an existing file, and deletes only a path the plugin recorded.
- Support screenshots use a private 0700 temp directory, MIME sniffing, actual size check and cleanup on success/failure.
- External services are disclosed: support email and IndexNow. IndexNow documentation and terms URLs return HTTP 200; the terms page contains the privacy section.

## Plugin Check

Exact ZIP, Plugin Check 2.1.0:

- WordPress 7.1.4: **0 errors / 223 warnings**
- WordPress 6.5.14: **0 errors / 223 warnings**

Warnings: 203 included-view local-variable false positives, 20 custom-table prepared-SQL false positives. Every warning is classified in `FALSE_POSITIVE_JUSTIFICATIONS.md`; no blanket suppression was added.

## Accepted risks and limitations

- Trademark/ownership of “RankKernel” cannot be proven from source code; owner decision.
- The exact WordPress.org scanner dump for 37/48/46 counts is unavailable; the audit inventories 236 current expressions and reproduces the reported examples.
- Psalm security taint analysis is not installed in local `vendor`; no local Psalm run is claimed. PHPStan L6, PHPCS, PHPUnit and focused security tests passed.
- Real Apache/LiteSpeed behavior was not re-run this pass; prior release evidence covers Apache 2.4.58.
- No independent Server OpenCode approval is claimed.

## Verdict

**READY FOR INDEPENDENT SERVER AUDIT** of source `57dfc06` and ZIP `f6beba8f…`. Not approved for WordPress.org upload or publication.
