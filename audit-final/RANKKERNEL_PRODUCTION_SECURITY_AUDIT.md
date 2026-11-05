# RankKernel — Production Security Audit (0.1.0)

Final source: `fc5c6416e9499f3ed93106c1445c9016393d57a8`  
Release branch: `release/wporg-0.1.0-final`  
Production package: `rankkernel-0.1.0.zip`, 901,122 bytes, 239 files  
SHA-256: `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39`  
Manifest SHA-256: `3c20b8eb73ad1c49414725e0fbce67e9ffc658e9539fe2f3b90bfe89bbfa11d5`  
Plugin Check: **0 errors / 223 warnings**

## Scope

The shipped file set was derived from the package itself, not from `src/` alone: 239 files, exactly one `rankkernel/` top-level directory, matching the `.distignore` at `fc5c641`. All shipped PHP, view, block, JS and CSS files were inspected. Tests, CI, docs, audit reports, vendor and development configuration are outside the package.

## Input occurrence audit

`RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv` preserves all 409 original rows and adds classification/evidence.

| Metric | Count |
|---|---:|
| Original rows | 409 |
| Executable input reads | 401 |
| Comment/docblock/non-executable rows | 8 |
| Outside production package | 0 |
| PASS (cited/high-confidence examples) | 16 |
| ACCEPTED_WITH_JUSTIFICATION | 385 |
| FIXED in this workflow | 0 |
| BLOCKED | 0 |

The 16 PASS rows are the ten reviewer-cited examples plus duplicate occurrences at the same expressions. The remaining executable rows are classified `ACCEPTED_WITH_JUSTIFICATION`: each is inside a capability/nonce-gated handler or a read-only routing/display path, with type/shape checks, allowlists, casts or downstream sanitization documented in the per-file evidence column.

Important limitation: the WordPress.org 48-line scanner list is not available. This audit does not claim that the 409-row CSV maps one-to-one to the reviewer's 48 incidences. The CSV is a complete source inventory and classification, not a reproduction of the reviewer's scanner report.

## Output escaping audit

`RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv` inventories 1,244 output statements in shipped PHP files.

| Classification | Count |
|---|---:|
| PASS_CONTEXTUAL | 1,237 |
| ACCEPTED_WITH_JUSTIFICATION | 7 |

Contexts checked: HTML text and attributes, URLs, rich HTML, JSON/JSON-LD, XML sitemaps/XSL, CSS custom properties, CSV, REST, AJAX, admin notices/tables, redirects, logs and localized script data. Raw echo statements are either static markup, pre-escaped fragments, or encoded JSON/XML bodies; dynamic HTML paths use `esc_html`/`esc_attr`/`esc_url`/narrow `wp_kses`.

## Confirmed defect fixes carried into this source

- P1-01: final `rankkernel/breadcrumbs` filter output is sanitized in both getter/shortcode and block paths. KSES allowlist + `WP_HTML_Tag_Processor` URL/style validation; protocol-relative, encoded-control, backslash, `javascript:`, `data:`, `vbscript:` and `file:` inputs rejected. Real KSES integration test added and run on WP 6.5.14/7.1.4.
- P2-01: custom schema JSON bounded to 1 MiB and depth 64; invalid/oversized/deep input keeps stored schema.
- Earlier release fixes retained: REL-01 insert-ID capture, atomic/private `.htaccess` backups, deactivation block removal, schema migration gating, private support screenshots, nonce/capability dispatch, importer GPL tags, `.distignore`, nosniff sitemap headers, MetadataBox translations, author cache priming.

## Broader review

Reviewed and found no new confirmed P0/P1/P2 defect in: capability/nonce gates, REST permission callbacks, SQL preparation/identifiers, redirect validation, filesystem/upload handling, serialization, SSRF/external requests, logs/privacy, uninstall, cron, i18n and prefixing. Existing Plugin Check warnings remain the documented 203 template-local prefix false positives and 20 custom-table SQL false positives.

## Limitations

- Full 409-row line-by-line manual trace was not possible without the reviewer's complete scanner output; classifications are source-inventory plus file/handler-level evidence. Rows are not marked `not reviewed`.
- WP 6.5.14 was verified for the KSES integration and earlier release candidate; the final ZIP was not independently re-run on 6.5.14 in this pass.
- LiteSpeed and real Apache were not part of this production-code pass.
- No independent Server OpenCode approval of the final ZIP is claimed.

## Verdict

**READY FOR SERVER INDEPENDENT AUDIT** of source `fc5c641` and ZIP `0d891a9f…`. Not authorized for WordPress.org upload.
