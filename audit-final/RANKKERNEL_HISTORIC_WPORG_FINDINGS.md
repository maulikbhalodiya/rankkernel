# RankKernel — Historic WordPress.org Findings Status

Source `fc5c641`; package `0d891a9f…`.

| Historical finding | Status | Evidence |
|---|---|---|
| Callback/filter output unescaped | FIXED | Breadcrumb sanitizer after final filter; KSES allowlist + URL/style validation; real KSES integration test; hostile filter tests. |
| Input sanitization/validation, 48 incidences | FIXED / ACCEPTED | 409-row source inventory; reviewer's 10 examples traced; 16 PASS, 385 accepted with file/handler evidence, 8 comment rows, 0 blocked. Full 48-line scanner mapping unavailable. |
| Arbitrary `.htaccess` editor | VERIFIED FIXED | Only two fixed directives; server-generated block; nonce/capability; browser/real Apache tested. |
| `.htaccess` backup in webroot | FIXED | Non-autoloaded `rankkernel_htaccess_backups` option; last 5; no public backup files; uninstall cleanup. |
| `.htaccess` partial write corruption | FIXED | Same-directory temp, byte check, mode preservation, atomic rename; failure tests. |
| Deactivation leaves managed block | FIXED | `removeBlock()` removes only markers; preserves user content; WP-CLI live test. |
| Per-request `dbDelta()` | FIXED | Schema ledger gate; live query counts 0/1/1; tests. |
| Multisite cache fallback to blog 1 | FIXED | Unknown blog ID returns 0 and bypasses static cache; tests. |
| Support screenshots public/uploads | FIXED | Private 0700 temp dir, cleanup on success/failure, stale pruning, `file` key attachment path. |
| Inline noscript styles | FIXED | Scoped enqueued no-JS CSS + `rk-js` head flag; zero EnqueuedResources ignores. |
| JS global `rkRedirects` | FIXED | Prefixed globals/localized objects. |
| IndexNow/external services disclosure | VERIFIED | Readme Privacy/External Services/AI disclosure; fixed endpoint + own-site key check. |
| Hardcoded uploads fallback | FIXED | `wp_upload_dir` primary; `UPLOADS`/`WP_CONTENT_DIR` fallback. |
| Importer GPL headers | FIXED | Five importer mapper files carry `@license GPL-2.0-or-later`. |
| `.distignore` packaging | FIXED | Exact 24-line file; excludes `.distignore`, audit docs, dev/test files; 239-file package verified. |
| REL-01 insert ID | FIXED | Capture `$db->insert_id` before `touch()`; live regression. |
| Schema import size/depth | FIXED | 1 MiB + depth 64. |
| Uninstall/deactivation cleanup | VERIFIED | Managed block removal, cron cleanup, backup option deletion, optional config purge. |
| Plugin name/trademark | OWNER DECISION | Name/slug/text domain compliant; legal clearance not claimed. |

No WordPress.org approval is claimed.
