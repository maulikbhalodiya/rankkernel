# RankKernel — Production Security Audit (0.1.0)

Final source: `fb386a419d6b32394cc342cf740f8e41a75389bb`  
Release branch: `release/wporg-0.1.0`  
Production package: `rankkernel-0.1.0.zip`, 902,973 bytes, 239 files  
SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`  
Manifest SHA-256: `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`  
Plugin Check: **0 errors / 223 warnings**

## Scope

The shipped file set was derived from the package itself: 239 files, exactly one `rankkernel/` top-level directory, matching the `.distignore` at the final commit. All shipped PHP, view, block, JS and CSS files were inspected. Tests, CI, docs, audit reports, vendor and development configuration are outside the package. The package was rebuilt after the remediation commit; the prior `fc5c641` ZIP is superseded.

## Server audit follow-up (fb386a4)

The independent Server audit of `2afb118` approved the exact package with non-blocking follow-ups. This source closes the code findings:

| ID | Fix |
|---|---|
| RK-F-02 | `flags.pillar` / `flags.cornerstone` strict `nullableBool(...) ?? false` |
| RK-F-03 | Breadcrumbs block `boolAttribute()` with setting/default fallback |
| RK-F-04 | Raw exception message in `ModuleRegistry::label()` |
| RK-F-05 | Network activation refused before any setup; single-site uninstall documented |
| RK-F-06 | Strict `is_string()` `run_id` guard in `ImportController::release()` |
| RK-F-07 | Legacy schema list retained and documented as safe |

Full table: `RANKKERNEL_FINAL_REMEDIATION_REPORT.md`.

## Remediation performed

Five earlier input validation/sanitization defects were fixed in `5a5a426`; `2afb118` added preview regression coverage. The Server follow-up fixes are in `fb386a4`. No output escaping defect was confirmed.

| ID | Defect | Fix |
|---|---|---|
| IN-01 | Instant Indexing `$_GET` search not unslashed/sanitized early | `wp_unslash()` at consumer; `sanitize_text_field()` in `LogFilters` |
| IN-02 | MetaPayload coerced nested arrays to booleans/attachment IDs | `nullableBool()` + `scalarAbsint()` |
| IN-03 | REST import JSON body arrays became `"Array"`/`1` | strict scalar/numeric checks returning 400 |
| IN-04 | SupportRequest direct array input produced casts/warnings | `is_scalar` guards |
| IN-05 | Analysis keyword sanitizer converted nested arrays to `"Array"` before validation | preserve non-scalars for validation; filter in analyzer |

Full detail and tests: `RANKKERNEL_INPUT_SANITIZATION_REMEDIATION.md`, `RANKKERNEL_OUTPUT_ESCAPING_REMEDIATION.md`, `RANKKERNEL_REMEDIATION_SUMMARY.md`.

## Input occurrence audit

`RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv` preserves all 409 original rows and adds classification/evidence.

| Metric | Count |
|---|---:|
| Original rows | 409 |
| Executable input reads | 401 |
| Comment/docblock/non-executable rows | 8 |
| Outside production package | 0 |
| PASS | 15 |
| FIXED | 4 |
| ACCEPTED_WITH_JUSTIFICATION | 382 |
| BLOCKED | 0 |
| `not reviewed` | **0** |

The four `FIXED` matrix rows cover IN-01 and IN-02. IN-03/04/05 are REST/shared-validator paths outside the original 16-file inventory; they are listed in `RANKKERNEL_REMEDIATION_INPUT_ADDITIONS.csv` (20 rows) and covered by the remediation report and regression tests.

## Output escaping audit

`RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv` inventories 1,244 output statements in shipped PHP files.

| Classification | Count |
|---|---:|
| PASS_CONTEXTUAL | 1,237 |
| ACCEPTED_WITH_JUSTIFICATION | 7 |
| Confirmed defects | 0 |

Contexts checked: HTML text and attributes, URLs, rich HTML, JSON/JSON-LD, XML sitemaps/XSL, CSS custom properties, CSV, REST, AJAX, admin notices/tables, redirects, logs and localized script data. Raw echo statements are static markup, pre-escaped fragments, or encoded JSON/XML/CSV/plain-text response bodies.

## Confirmed defect fixes carried into this source

- P1-01: final `rankkernel/breadcrumbs` filter output is sanitized in getter/shortcode and block paths. KSES allowlist + `WP_HTML_Tag_Processor` URL/style validation; protocol-relative, encoded-control, backslash, `javascript:`, `data:`, `vbscript:` and `file:` inputs rejected. Real KSES integration test added and run on WP 6.5.14/7.1.4.
- P2-01: custom schema JSON bounded to 1 MiB and depth 64; invalid/oversized/deep input keeps stored schema.
- IN-01…IN-05: see remediation table above.
- Earlier release fixes retained: REL-01 insert-ID capture, atomic/private `.htaccess` backups, deactivation block removal, schema migration gating, private support screenshots, nonce/capability dispatch, importer GPL tags, `.distignore`, nosniff sitemap headers, MetadataBox translations, author cache priming.

## Broader review

Reviewed and found no new confirmed P0/P1/P2 defect in: capability/nonce gates, REST permission callbacks, SQL preparation/identifiers, redirect validation, filesystem/upload handling, serialization, SSRF/external requests, logs/privacy, uninstall, cron, i18n and prefixing. Existing Plugin Check warnings remain the documented 203 template-local prefix false positives and 20 custom-table SQL false positives.

## Limitations

- Full 409-row line-by-line runtime execution was not performed; classifications combine source inventory with file/handler-level tracing. The reviewer's exact 48-line scanner output remains unavailable.
- WordPress 6.5.14 was tested with the exact final ZIP in this round: 9 admin screens 200 and public endpoints passed.
- LiteSpeed and real Apache were not part of this production-code pass.
- No independent Server OpenCode approval of the final ZIP is claimed.

## Verdict

**READY FOR SERVER INDEPENDENT AUDIT** of source `fb386a4` and ZIP `4edb5beb…`. Not authorized for WordPress.org upload.
