# RankKernel — Whole-Plugin Security Audit

Date: 2026-11-05  
Commit: `fc5c6416e9499f3ed93106c1445c9016393d57a8`

## Scope

- All production PHP, JS, CSS, templates, blocks, shortcodes, REST/AJAX handlers, settings, metadata, import/export, redirects, sitemap, schema, 404 monitor, IndexNow and support flows.
- Input inventory: 409 superglobal occurrences (POST 226, GET 152, SERVER 21, FILES 10; no REQUEST/COOKIE/SESSION).
- Output contexts: HTML text/attributes, URLs, JavaScript data, JSON-LD, XML sitemaps, CSS, REST, admin tables, CSV and logs.

## Confirmed findings and resolutions

| ID | Severity | Finding | Resolution |
|---|---|---|---|
| P1-01 | P1 | `rankkernel/breadcrumbs` filter output returned without post-filter HTML sanitization | Fixed with narrow `wp_kses` allowlist, URL policy, style validation/restoration in getter and block |
| P2-01 | P2 | Custom schema JSON textarea had no byte/depth bound | Fixed at 1 MiB / depth 64; tests added |
| P3-03 | P3 | Protocol-relative URL accepted by breadcrumb URL policy | Fixed; regression tests added |
| P3-04 | P3 | Unit tests did not exercise real KSES/Tag Processor style path | Added real WordPress integration test and CI job |

## Reviewed and accepted

- SQL uses `$wpdb->prepare()` for values; identifiers are constants/allowlists; no injection found.
- Nonces and capabilities are present on state-changing admin, REST and AJAX paths.
- File operations: `.htaccess` atomic write/private DB backups; `llms.txt` opt-in and containment; schema upload read-only with size/MIME/depth; support screenshots private temp with cleanup.
- Redirects: destination validation, same-host folding, external host allowlist, regex caps, CSV formula neutralization.
- Importer: identity validation, bounded preview/execution, idempotent retry, source metadata preservation.
- External HTTP: fixed IndexNow endpoint and own-site key check; no SSRF or telemetry.
- Serialization: `unserialize` uses `allowed_classes => false`.

## Evidence and limitations

- Full input inventory: `/home/web-dev-3/Documents/my personal plugin/audit-2026-10/final-reports/RANKKERNEL_WHOLE_PLUGIN_INPUT_MATRIX.csv`.
- The reviewer's complete 48-line scanner list is unavailable; ten examples were traced and all 409 occurrences were inventoried. The inventory is not a claim that every line received an individual manual source-to-sink trace.
- Plugin Check: 0 errors / 223 warnings; 203 template-local prefix warnings and 20 custom-table SQL warnings, reviewed as false positives.
- Independent ZIP audit is still required.

## Verdict

No unresolved confirmed P0/P1/P2 executable vulnerability in this review. **Ready for independent final ZIP audit**, not independent approval or WordPress.org submission.
