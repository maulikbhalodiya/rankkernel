# RankKernel — Historic WordPress.org Findings Matrix (final source `fc5c641`)

| Finding | Status | Evidence |
|---|---|---|
| Plugin name/redundant "Free" | VERIFIED | Header/readme `RankKernel SEO & Schema Engine`; no redundant Free in name. |
| Trademark/ownership | OWNER DECISION | No legal clearance claimed. |
| Arbitrary `.htaccess` editor | VERIFIED | Two fixed checkboxes; server-generated block; unknown ids dropped; unrelated content preserved. |
| `<noscript>` inline stylesheet | VERIFIED | Removed; no-JS CSS enqueued and scoped to `html:not(.rk-js)`. |
| Nonces/capabilities | VERIFIED | Admin/REST/AJAX handlers gated; invalid nonce rejected. |
| Input sanitization/validation | VERIFIED (reviewed) | 409-occurrence inventory; cited examples traced; P1/P2 fixed. |
| Output escaping | VERIFIED | PHPCS clean; callback/filter output sanitizer added. |
| JavaScript prefixing | VERIFIED | No `rkRedirects`; `rankkernel*` localized globals. |
| IndexNow disclosure | VERIFIED | Readme Privacy/External Services; fixed endpoint. |
| Hard-coded uploads fallback | VERIFIED | `wp_upload_dir()` primary; respects UPLOADS/WP_CONTENT_DIR. |
| Callback/filter output | FIXED | `sanitize_breadcrumb_html()` after final filter for getter/shortcode/template tag/block. |
| Schema JSON size/depth | FIXED | 1 MiB and depth 64 on textarea and import. |
| Importer GPL headers | VERIFIED | Five importer files each have one `@license GPL-2.0-or-later`. |
| `.distignore` exclusions | VERIFIED | 239-file package; no tests/docs/vendor/audit files. |
| RedirectRepository insert ID | VERIFIED | Captures `$db->insert_id` before `touch()`; live 1/2 IDs. |
| Breadcrumb filter sanitization | FIXED | Protocol-relative/unsafe schemes rejected; real KSES integration test. |
