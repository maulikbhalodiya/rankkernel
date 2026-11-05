# RankKernel 0.1.0 — Final Security Occurrence Inventory

Date: 2026-11-05  
Production source: `fb386a419d6b32394cc342cf740f8e41a75389bb`  
Final ZIP: `rankkernel-0.1.0.zip` — 902,973 bytes — `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`

## 1. Input occurrences

Source files: `src/`, `assets/`, `rankkernel.php`, `uninstall.php`, REST controllers, admin handlers, upload paths, blocks, shortcodes, filters, repositories, redirect handling, logger and scheduled jobs.

| Metric | Count |
|---|---:|
| Total occurrence records | **437** |
| Original 409 superglobal occurrences | 409 |
| Additional REST / validator / lifecycle / downstream rows | 28 |
| PASS | 15 |
| FIXED | 31 |
| ACCEPTED_WITH_REASON | 383 |
| COMMENT_OR_DOCBLOCK_ONLY | 8 |
| BLOCKED | **0** |
| UNREVIEWED | **0** |

Original superglobal split: POST 226, GET 152, SERVER 21, FILES 10. No `$_REQUEST`, `$_COOKIE`, `$_SESSION`, `$_ENV`, `filter_input()` or `getenv()` in shipped PHP.

Each record in `RANKKERNEL_FINAL_INPUT_OCCURRENCES.csv` carries:

`file`, `line`, `column`, `superglobal`, `token`, `source_line`, `handler`, `data_type`, `validation`, `sanitization`, `transformation`, `final_sink`, `escaping`, `capability_nonce`, `verdict`, `evidence`, `test_reference`.

Evidence is tied to the exact source line and containing handler. Presence-only routing/checkbox rows state that no raw value is read. FIXED rows cite the fix commit and regression test. No row uses `not reviewed`.

## 2. Output occurrences

| Metric | Count |
|---|---:|
| Output statements | **1,244** |
| PASS | 1,237 |
| ACCEPTED_WITH_REASON | 7 |
| BLOCKED | **0** |
| UNREVIEWED | **0** |

`RANKKERNEL_FINAL_OUTPUT_OCCURRENCES.csv` records file, line, exact statement, context, protection, verdict, evidence and test/evidence reference.

Contexts covered: HTML text, HTML attributes, URLs, rich HTML, JSON/JSON-LD, XML sitemaps, CSS, CSV, REST/AJAX, admin notices/tables, redirects, logs, plain-text responses and localized script data. The seven accepted entries are encoded JSON/XML/CSV/plain-text response bodies or pre-escaped fragments, each with the exact statement and protection.

## 3. Unreviewed / blocked

**None.** Every record has an individual verdict and exact line. There are no `BLOCKED` or `UNREVIEWED` rows.

## 4. Plugin Check

Exact ZIP, Plugin Check 2.1.0:

| WordPress | Errors | Warnings |
|---|---:|---:|
| 7.1.4 | 0 | 223 |
| 6.5.14 | 0 | 223 |

- 203 `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound`: method-local view variables in included admin views; no `global` declarations.
- 20 `PluginCheck.Security.DirectDB.UnescapedDBParameter`: prepared sitemap queries, fixed DDL, prepared hit-counter update, and prefix-scoped regex-gated uninstall table name.

All 223 were re-reviewed against the changed code; no new category and no real warning was suppressed.

## 5. Test references

- Full suite: `composer test` — 2,392 tests / 9,197 assertions, OK.
- PHPCS: `composer lint` — exit 0.
- PHPStan L6: `composer stan` — no errors.
- JS: `composer test:js` — 352 pass / 0 fail; `node --check` clean.
- Exact-ZIP runtime: WP 7.1.4 and WP 6.5.14, 9 admin screens 200 on both, public sitemap/robots/404 flows correct, no RankKernel debug lines.
- Per-occurrence test references are in the CSV. Rows without a dedicated unit test are marked as covered by exact-ZIP Plugin Check and runtime smoke evidence rather than claiming a unit test that does not exist.

## 6. Methodology and limitations

- The 409-row base inventory is the historical superglobal inventory, reclassified line by line with the containing handler and the actual guard/sanitizer tokens extracted from that handler.
- The 28 additional rows cover normalized downstream metadata, block attributes, REST release validation and activation lifecycle values that are not superglobal reads.
- The output inventory is an extraction of actual `echo`/`print`/`printf`/`var_export` statements from the shipped PHP files; each statement was classified with its context and protection.
- Static review is not a runtime fuzzer. The exact-ZIP runtime smoke tests cover representative admin/public, metadata, redirect, sitemap, robots, 404 and activation flows, not every module combination.
- The historical WordPress.org 48-line scanner output is unavailable; this inventory does not claim a one-to-one reproduction of it. It covers the complete current production source and the historical eleven examples.
