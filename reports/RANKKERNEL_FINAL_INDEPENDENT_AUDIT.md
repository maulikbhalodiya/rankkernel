# RankKernel 0.1.0 — Final Independent Audit

**Audit date:** 2026-11-05  
**Exact package:** `release/wporg-0.1.0-final` at `2afb1185d919f03ad4ed74444466db3b44db0786`  
**Exact ZIP:** `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`, 902,022 bytes, 239 files

## Verdict

**APPROVED WITH NON-BLOCKING FOLLOW-UPS — for the exact `2afb118` package.**

The exact package is internally consistent, matches the production commit file-for-file, and has no blocking input-validation, output-escaping, or WordPress.org security defect in the reviewed scope. The five reported input fixes are present and verified.

> ⚠️ **Warning — the GitHub draft release is not publishable as-is.** The draft `v0.1.0-rc-rel01` contains stale assets: `rankkernel-0.1.0.zip` is 901,122 bytes / `0d891a…` with six files differing from `2afb118`, and `rankkernel-0.1.0-release-rel01.zip` is 898,875 bytes / `ee5b82…` with 13 differences. Replace the assets with the exact candidate and correct `SHA256SUMS` before publishing. If the question is whether the draft release can be published now, the answer is **NOT APPROVED — FIXES REQUIRED**.

## 1. Scope and method

- Reviewed the exact installable package, not only the 409 superglobal occurrences or the reported output inventory.
- 239 package files: 181 PHP, 44 JS/CSS, plus readme, license, and language metadata.
- Reviewed package headers, readme, all modules, admin views, REST/AJAX, shortcodes/blocks, sitemaps, redirects, 404 monitor, metadata, schema, breadcrumbs, robots/llms, IndexNow, support, importer, migrations, cron, privacy, uninstall, and multisite behavior.
- Independently generated a 409-row input matrix (`RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv`) and a 1,513-line output-candidate inventory (`RANKKERNEL_PRODUCTION_OUTPUT_CANDIDATES.csv`).
- Re-ran focused and full unit tests, inspected the exact-SHA CI, and reviewed Plugin Check warnings and runtime behavior.
- No production source, ZIP, release asset, branch, tag, PR, or WordPress.org state was modified.

## 2. Exact ZIP and source verification — DONE

| Check | Result |
|---|---|
| SHA-256 | `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252` |
| Size | 902,022 bytes |
| Entries | 239 files |
| Top-level | one `rankkernel/` |
| Symlinks / traversal | none |
| `unzip -t` | clean |
| Uncompressed | 3,028,276 bytes |
| PHP / JS+CSS | 181 / 44 |
| Source comparison | every packaged file matches `git show 2afb118:<path>`; zero missing or mismatched |
| Manifest | `RANKKERNEL_FINAL_PACKAGE_MANIFEST.csv` (239 rows) |
| Plugin version | 0.1.0 |
| Requires WP / PHP | 6.5 / 8.2 |
| Text Domain | `rankkernel` |
| readme Stable tag | 0.1.0 |
| readme Tested up to | 7.1 — verified against WordPress 7.1.4 |

The exact candidate is not the GitHub draft asset. The draft asset mismatch is a release-delivery finding, not a defect in the exact ZIP.

## 3. Five reported input fixes — DONE

| ID | Fix | Verification | Status |
|---|---|---|---|
| IN-01 | Instant Indexing search input | `LogFilters.php:123-128` rejects non-strings, truncates to 100, `sanitize_text_field`; `InstantIndexingPage.php:330` unslashes `$_GET`; sink uses `$wpdb->prepare` + `esc_like` + `esc_attr` | **PASS** |
| IN-02 | MetaPayload nested-array coercion | `nullableBool()` and `scalarAbsint()` now protect robots flags, `schema.disabled`, and image IDs; arrays no longer coerce to true/1. Residual: `flags.pillar`/`cornerstone` at `MetaPayload.php:284-290` still use `(bool)`; low, non-security | **PASS with low residual** |
| IN-03 | REST import JSON body types | `ImportController.php:330-441,714-806`: mode/kind/q/run_id require strings; offset/limit numeric and clamped; excludes scalar-only; invalid input returns 400 | **PASS** |
| IN-04 | SupportRequest validation/consent | `SupportRequest.php:156-223`: scalar gates for all text; consent array/object/empty/`'0'` rejected; diagnostics non-scalar coerced to `'0'` | **PASS** |
| IN-05 | Analysis keyword sanitizer | `AnalysisController.php:118-125,165-168`: non-scalars preserved for route validation rejection; `analyze()` double-filters scalars | **PASS** |

Focused tests: **207 tests / 1,507 assertions, OK**. Full unit suite: **2,385 tests / 9,182 assertions, OK** (8 test-only PHP 8.5 `Reflection::setAccessible()` deprecations).

The new breadcrumb KSES test passes when the exact plugin functions are loaded; the earlier local fatal was caused by an older staging plugin, not the exact source. CI at `2afb118` reports **Breadcrumb KSES Integration: success**.

## 4. Full input audit — DONE, no blocking defect

- 409 occurrences / 16 files: POST 226, GET 152, SERVER 21, FILES 10.
- No `$_REQUEST`, `$_COOKIE`, `$_SESSION`, `$_ENV`, `filter_input()`, or `getenv()` in package PHP.
- All high-risk handlers reviewed: redirects/CSV, 404 monitor, support uploads, schema import, metadata, all five REST controllers, settings, Instant Indexing, `.htaccess`, breadcrumbs, schema blocks, sitemaps, robots/llms, importer.
- SQL values use `$wpdb->prepare`/placeholders; ORDER BY is allowlisted; IN lists are integer-cast; LIKE uses `esc_like`.
- No user-controlled include/path; `.htaccess` and `llms.txt` writes are contained and fixed-directive.
- No SSRF: only fixed IndexNow endpoint and same-site key check, `wp_safe_remote_*`.
- `unserialize` only on stored/legacy strings with `allowed_classes => false`.
- Low notes: `MetaPayload` flags bool coercion; `BreadcrumbsBlock` boolean attribute coercion. Neither has a security sink.

**Limitation:** the 409 rows were verified by handler/file pattern, not each row traced individually to a sink. Non-PHP assets, view-by-view echoes, importer mapper internals, sitemap provider internals, and analysis/monitor internals were covered by output/security lanes rather than a separate line-by-line input trace.

## 5. Output escaping — DONE, no blocking defect

- Reviewed all output surfaces: HTML/attributes, URLs, JS/localized scripts, JSON/JSON-LD, XML sitemaps, CSS, admin views, metaboxes, shortcodes, blocks, REST/AJAX, CSV, logs.
- 1,513 candidate output lines across 66 files; all material sinks traced to context-specific escaping or a safe API.
- JSON-LD uses `wp_json_encode` with HEX flags; sitemap XML uses `esc_xml`/`htmlspecialchars`; CSV formula injection is neutralized; redirects use `wp_safe_redirect` + destination validation; admin URLs are fixed/allowlisted.
- No unsafe HTML, attribute injection, JS injection, dangerous URL, malformed JSON/XML, CSS injection, or double-escaping defect found in shipped output.
- Info: `ModuleRegistry.php:96` escapes an exception message inside the exception; `label()` appears unused and there is no user-facing sink. Non-blocking code-quality nit.

## 6. WordPress.org security and guidelines — DONE, no blocking defect

- Auth/capability + nonce checks on every admin save; REST routes have permission callbacks and schemas; only authenticated `wp_ajax_` handlers; no `nopriv`.
- SQL: prepared values, allowlisted identifiers, int-cast IN lists; uninstall `DROP TABLE` names are regex-gated after a prefix-scoped `SHOW TABLES`.
- XSS: all reviewed sinks escaped; no bare user-data echo.
- SSRF/open redirect/uploads/path traversal: fixed endpoints, validated destinations, upload probes/sniffing/size caps, contained `.htaccess` writes, private support temp directory.
- Serialization, cron payloads, privacy/IP redaction, retention, uninstall, migrations, caching, and bundled licensing reviewed.
- External services: support email via `wp_mail`; IndexNow fixed endpoint; no other network/CDN/analytics; Privacy, External Services, and AI Assistance disclosures present.
- Low: network activation is not guarded although readme says network activation is unsupported; uninstall is single-site. Non-blocking review hygiene.
- **Rejected false positive:** the lane’s claim that WordPress 7.1 does not exist is incorrect; staging and production both run WordPress 7.1.4, so `Tested up to: 7.1` is truthful.

## 7. Tests, CI, Plugin Check, runtime — DONE

### 7.1 Exact-ZIP verification

- Re-hashed the exact ZIP after testing: `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`, 902,022 bytes.
- The exact ZIP installs and activates from the ZIP; the installed tree is byte-identical to the expected extract. Header, `RANKKERNEL_VERSION`, and readme Stable tag are all `0.1.0`; Requires WP 6.5 / PHP 8.2; Tested up to 7.1; GPLv2+.

### 7.2 Static checks and tests

| Check | Result |
|---|---|
| PHP syntax | 348 files, 0 failures |
| `composer validate --strict` | valid |
| `composer audit --locked` | no advisories |
| `composer lint` / PHPCS | clean |
| `composer stan` (PHPStan level 6) | no errors (179 files) |
| `composer test` | 2,385 tests / 9,182 assertions, OK; 8 test-only PHP 8.5 deprecations |
| `composer test:js` | 352 pass / 0 fail |
| `node --check` | 21 JS files, 0 failures |
| Psalm / Psalm Security local | **NOT MEASURED** — no local binary and Docker unavailable |

### 7.3 CI at the exact SHA

Check-runs at `2afb1185d919f03ad4ed74444466db3b44db0786`:

- WPCS, PHPStan & PHPUnit (PHP 8.3): **success**
- WPCS, PHPStan & PHPUnit (PHP 8.4): **success**
- Psalm Security Scan: **success**, but the job uses `continue-on-error: true`; its annotations are 10 WordPress-stub noise items in `AdminMenu.php` plus one runner-image notice, with **no taint/SQL findings**. Treat the green check as weak evidence and the annotation content as the substantive result.
- Breadcrumb KSES Integration: **success**
- Removed Work Guard: skipped (push event, not a PR)

### 7.4 Plugin Check on the exact package

- WordPress 7.1.4: **0 errors, 223 warnings**.
- WordPress 6.5.14: **0 errors, 223 warnings**, byte-identical JSON to the 7.1.4 run.
- 203 warnings: `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` in `src/Admin/Views/**`. All 19 view files are `require`d inside methods; there are zero `global` declarations in the views. The `$rk*` variables are local. **False positives.**
- 20 warnings: `PluginCheck.Security.DirectDB.UnescapedDBParameter`. Every flagged line was inspected: 15 sitemap-provider queries use `$wpdb->prepare(...$args)` with placeholders; 3 are fixed `CREATE TABLE` DDL via `dbDelta` plus a no-input fallback; 1 is a prepared `HitCounter` UPDATE; 1 is `uninstall.php`'s prefix-swept, regex-gated table name. **False positives / safe.**
- All 26 flagged files are shipped (`src/...`, `uninstall.php`); no tests/tools/vendor/.github files are in the ZIP.

### 7.5 Runtime smoke tests

- **WordPress 7.1.4:** home 200; `sitemap_index.xml` 200 valid XML; `sitemap.xml` 301 to index; robots.txt 200; 404 returns 404 and logs; redirect insert returns 301 with query preserved; all tested admin pages including post-edit return 200; `debug.log` 0 bytes.
- **WordPress 6.5.14:** same green result after enabling all modules; `debug.log` has 0 fatals and 0 RankKernel lines (only WP 6.5 core PHP 8.5 deprecation noise). Initial 403s/redirect 404s were caused by the staging site having only 5 of 10 modules enabled; with all modules on, the pages and redirect/404 flows pass. This is a staging configuration difference, not a package defect.
- Both sites were restored to their found state. Staging-only side effects: admin passwords changed to `Smoke-2026-test!` and debug.log contents; no production source or ZIP was modified.

## 8. Evidence reconciliation and limitations

- The available 409-row matrix is 100% `not reviewed`; it contains **zero `FIXED` rows**. The reported “four FIXED rows” matrix was not found in the repository, fetched branches, or local evidence. The five fixes are represented by the fix commit `5a5a426` and regression tests in `2afb118`, not by the available matrix.
- The original WordPress.org reviewer 48-line scanner output and the eleven examples were not found in shared evidence. This audit does not claim one-to-one reproduction of those eleven examples; it verifies the five fix paths and the complete reviewed package instead.
- Developer-referenced remediation reports and matrices were largely unavailable in the shared repository; available prior reports are historical evidence only.
- Static review is not a runtime fuzzer; the exact-ZIP runtime lane covers activation, admin/public smoke tests, and WP 6.5.14 where feasible.
- Docker is unavailable and no local Psalm binary is installed. **Local Psalm/Psalm Security is NOT MEASURED.** The exact-SHA CI job at `2afb118` is green but uses `continue-on-error: true`; its annotations contain no taint/SQL findings (only WordPress-stub noise and a runner notice). The old `fc5c641` result was not reused.
- Runtime smoke tests are representative, not exhaustive: they cover the main public/admin flows, redirects, 404 logging, sitemaps, robots, and activation on both WP 7.1.4 and WP 6.5.14, but not every module combination or multisite network activation.
- The 203 Plugin Check view warnings were verified by include-scope and absence of `global` declarations; the 20 SQL warnings were each inspected at the flagged line. This is a false-positive classification, not a claim that Plugin Check itself is error-free.

## 9. Findings summary

See `RANKKERNEL_FINAL_AUDIT_FINDINGS.csv` for the machine-readable list. The material items are:

1. Stale draft release assets (release-blocking; not an exact-package code defect).
2. `MetaPayload` flags bool coercion (low, non-security).
3. Breadcrumbs block boolean coercion (low, non-security).
4. Multisite activation/uninstall scope (low, non-blocking).
5. `ModuleRegistry` exception escaping (info).
6. Import `release()` run_id cast (info, fail-closed).
7. Legacy MetaPayload schema list shape (info, no unsafe rendering).
8. Plugin Check 223 warnings (verified false positives on the exact ZIP on WP 7.1.4 and WP 6.5.14; 0 errors).
9. Local Psalm/Psalm Security is **NOT MEASURED**; the exact-SHA CI job is green but uses `continue-on-error: true`, with no taint/SQL findings in its annotations.

## 10. Release and cleanup

- Exact candidate: upload `rankkernel-0.1.0.zip` with SHA-256 `87d94a…` and 902,022 bytes.
- Retarget draft `v0.1.0-rc-rel01` to `2afb118`; update body and `SHA256SUMS`.
- Do not publish the draft until the asset hash/size match the exact candidate.
- See `RANKKERNEL_REPOSITORY_CLEANUP_PROPOSAL.md`: no tracked report is safe to delete; only the three stale draft assets are proposed for deletion after owner approval and exact replacement.

## 11. Final verdict

**APPROVED WITH NON-BLOCKING FOLLOW-UPS** for the exact `2afb118` package.

Release gate: **the draft release is not approved for publication until its stale assets are replaced with the exact candidate and the checksum file is corrected.**
