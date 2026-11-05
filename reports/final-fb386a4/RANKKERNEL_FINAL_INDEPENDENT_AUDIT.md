# RankKernel 0.1.0 — Final Independent Verification of Consolidated Release Candidate

**Auditor:** Independent Server OpenCode audit
**Date:** 2026-11-05
**Repository:** `maulikbhalodiya/rankkernel`
**Target branch:** `release/wporg-0.1.0`
**Target commit:** `fb386a419d6b32394cc342cf740f8e41a75389bb`
**Candidate ZIP:** `rankkernel-0.1.0.zip`
**Final verdict:** **APPROVED WITH NON-BLOCKING FOLLOW-UPS**

---

## 1. Exact package identity

The candidate ZIP was obtained from the user attachment and copied to `/tmp/rk-final-audit/new/rankkernel-0.1.0.zip`. The checksum was calculated independently.

| Check | Result |
|---|---|
| SHA-256 | **`4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`** — matches expected |
| Size | **902,973 bytes** — matches expected |
| ZIP integrity | `unzip -t` clean |
| Packaged files | **239** |
| Top-level directory | one `rankkernel/` only |
| Path traversal | none |
| Symlinks | none |
| Uncompressed size | 3,031,075 bytes |
| PHP files | 181 |
| JS/CSS files | 44 |
| Every packaged file matches source | **239/239**, missing 0, mismatched 0, ZIP-extra 0 |
| Canonical manifest | `/tmp/rk-final-audit/new/RANKKERNEL_FINAL_PACKAGE_MANIFEST.csv` — 239 rows + header, SHA-256 **`1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`** |

The canonical CSV manifest hash matches the expected manifest hash exactly. The audit evidence branch also contains `audit-final/RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt`, a `hash  path` text manifest with SHA-256 `b15d8449707dc3003e59fef99eadc7f4302d6b8e24fe378daed1b6852f36fd13`; it lists the same 239 files and hashes. The difference is **format**, not package content.

### Plugin metadata

| Field | Value |
|---|---|
| Plugin Name | RankKernel SEO & Schema Engine |
| Version | 0.1.0 |
| Requires at least | 6.5 |
| Requires PHP | 8.2 |
| License | GPLv2 or later |
| Text Domain | rankkernel |
| Domain Path | /languages |
| readme Stable tag | 0.1.0 |
| readme Tested up to | 7.1 (WordPress 7.1.4 exists and is used in staging/production) |

---

## 2. Source-to-package comparison and consolidation

`release/wporg-0.1.0` and `main` both resolve to `fb386a419d6b32394cc342cf740f8e41a75389bb`. The previous independently approved commit `2afb1185d919f03ad4ed74444466db3b44db0786` is an ancestor of `fb386a4`; the reverse is not true.

`2afb118..fb386a4` changes 12 files, +252/−13, with no production deletions:

- `docs/audit-final/README.md` (not shipped)
- `rankkernel.php`
- `readme.txt`
- `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`
- `src/Modules/Metadata/MetaPayload.php`
- `src/Modules/ModuleRegistry.php`
- `src/Rest/ImportController.php`
- five unit-test files (not shipped)

Only **6 shipped files** differ from `2afb118`. The other **233 packaged files are byte-identical** to the previously audited production package. Prior full-package input, output, and WordPress.org security evidence therefore remains applicable to those 233 files; the six changed files were reviewed independently in this audit.

The source tree contains 540 files; 301 development/test/doc files are intentionally not shipped. No shipped production file is missing from the ZIP.

---

## 3. Five new remediation changes

### 3.1 MetaPayload strict booleans — PASS

`src/Modules/Metadata/MetaPayload.php:285,289` now uses `self::nullableBool( ... ) ?? false` for `flags.pillar` and `flags.cornerstone`. `nullableBool()` accepts only documented boolean shapes and returns null for arrays/objects. Tests cover nested arrays/objects, `'1'`, and `'0'`.

### 3.2 Breadcrumb block strict attributes — PASS

`src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php:174-175` now uses `boolAttribute()`, which accepts booleans, 0/1 integers, and recognized strings, and falls back to the existing setting/default for arrays, objects, and unknown strings. `showOnHomePage` uses the same helper. Tests cover malformed arrays/objects, `'0'`, `'false'`, and malformed `showOnHomePage`.

### 3.3 Exception message escaping responsibility — PASS

`src/Modules/ModuleRegistry.php:97` now throws the raw `sprintf( 'Unknown module id: %s', $id )` message with a phpcs justification. The unit test asserts that a raw `<script>` value is not pre-escaped. No production display path calls `ModuleRegistry::label()`; the raw message is therefore not rendered.

### 3.4 Network activation refusal — PASS

`rankkernel_activate( $network_wide = false )` now checks `$network_wide` before any option, cron, or table setup. On network activation it network-deactivates the plugin, returns HTTP 403 with a clear message, and returns without seeding state. `readme.txt` documents that network activation is blocked and uninstall is current-site scoped. `PluginTest` covers the refusal and empty option store.

### 3.5 Import `run_id` validation — PASS

`src/Rest/ImportController.php:872-876` now reads `run_id` only when it is a string, sanitizes it, and rejects non-strings with HTTP 400. Tests cover arrays/objects.

### 3.6 Test portability note

Focused tests initially reported one failure because the checkout basename was `rk-final-src-fb`, while `tests/Unit/PluginTest.php` hardcodes `rankkernel/rankkernel.php`. Re-running in a checkout whose basename is `rankkernel` passed 18/18. This is a test-only portability issue, not a production defect; the test should use `plugin_basename( RANKKERNEL_FILE )` or a fixture directory named `rankkernel`.

---

## 4. Complete previous-finding status (RK-F-01 through RK-F-15)

| ID | Severity | Status | Summary |
|---|---|---|---|
| RK-F-01 | High | **OPEN — release publication only** | Draft `v0.1.0-rc-rel01` assets remain stale: `rankkernel-0.1.0.zip` 901,122 / `0d891a…`; `release-rel01.zip` 898,875 / `ee5b82…`; `SHA256SUMS` stale. Exact candidate `4edb5be…` is not uploaded. Replace assets and regenerate checksums before publishing. Not a code/package defect in the exact candidate. |
| RK-F-02 | Low | **FIXED** | MetaPayload `flags.pillar` / `flags.cornerstone` strict boolean handling. |
| RK-F-03 | Low | **FIXED** | Breadcrumb block `showHomeItem` / `showCurrentItem` / `showOnHomePage` strict boolean handling. |
| RK-F-04 | Info | **FIXED** | ModuleRegistry exception message no longer pre-escaped; raw message with no display path. |
| RK-F-05 | Low | **FIXED** | Network activation refused before setup; readme documents single-site scope. |
| RK-F-06 | Info | **FIXED** | ImportController `run_id` rejects non-strings explicitly. |
| RK-F-07 | Info | **ACCEPTED** | Legacy schema list shape retained deliberately; no unsafe rendering found. |
| RK-F-08 | Info | **VERIFIED FALSE POSITIVES** | Plugin Check: 0 errors, 223 warnings on WP 7.1.4 and 6.5.14. 203 prefix warnings are method-local view variables; 20 DirectDB warnings are prepared/fixed queries. All 20 inspected. |
| RK-F-09 | Low | **RESOLVED — EVIDENCE FORMAT** | Expected manifest `129887…` is the canonical CSV manifest; `b15d844…` is the same 239 files in text format. Exact ZIP unaffected. |
| RK-F-10 | Info | **REJECTED FALSE POSITIVE** | `Tested up to: 7.1` is truthful; WordPress 7.1.4 is in use. |
| RK-F-11 | Info | **CLOSED** | Breadcrumb KSES integration test is real; prior local fatal was an older active staging plugin. |
| RK-F-12 | Info | **CLOSED — CI GREEN** | Exact-commit run `41988654857` at `fb386a4`: WPCS/PHPStan/PHPUnit PHP 8.3 and 8.4 success; Breadcrumb KSES success; Psalm Security success; Removed Work Guard skipped by design on push. |
| RK-F-13 | Info | **CLOSED — PSALM REAL, ZERO TAINT** | Psalm scan ran (1,862 files scanned, 179 analyzed), SARIF uploaded, zero taint findings. Caveat: `continue-on-error: true` weakens the gate; remove it or make SARIF upload blocking. |
| RK-F-14 | Info | **CLOSED — RUNTIME PASS** | Exact ZIP passed smoke tests on WP 7.1.4 and 6.5.14: 239/239 file hashes match, homepage/sitemap/robots 200, redirect 301, 404 logged, 9/9 admin pages 200, metadata/redirect workflows pass, zero RankKernel fatals in debug.log. |
| RK-F-15 | Low | **OPEN — TEST ONLY** | PluginTest hardcodes checkout basename; test portability only. |

---

## 5. Full production-package security review

### 5.1 Input handling

The independent input inventory contains 409 superglobal occurrences across 16 files (POST 226, GET 152, SERVER 21, FILES 10). No `$_REQUEST`, `$_COOKIE`, `$_SESSION`, `$_ENV`, `filter_input()`, or `getenv()` occurs in shipped PHP.

The full input audit found no blocking defect. All material handlers guard types and array shape, unslash, sanitize, validate domain-specific values, and fail closed. High-risk sinks were traced: SQL, file operations, redirects/headers, mail, `unserialize`, remote HTTP, option/meta writes, and output. The 20 DirectDB warnings were inspected individually and are safe. The only residual notes are non-security robustness items already addressed or accepted (MetaPayload flags, breadcrumb block booleans, legacy schema shape).

### 5.2 Output escaping

The independent output inventory contains 1,515 candidate lines across 65 files, broader than the previously reported 1,244. All contexts were reviewed: HTML text/attributes, URLs, JavaScript/localized data, JSON/JSON-LD, XML sitemaps, CSS, admin views, shortcodes/blocks, REST/AJAX, CSV, logs, and filter returns. No blocking escaping defect was found. The previous output audit's one low note (`ModuleRegistry.php:96` pre-escaped exception) is fixed by RK-F-04.

### 5.3 WordPress.org security and guidelines

No blocking security or guideline issue was found. Authentication/capabilities and nonces cover admin handlers; REST routes have permission callbacks and schemas; AJAX is authenticated only; SQL uses prepared values and allowlisted identifiers; redirects are validated; uploads are probed/sniffed/size-capped; `.htaccess` writes are contained and atomic; serialization uses `allowed_classes => false`; cron payloads are revalidated; privacy/logging is bounded and IP-free; uninstall is explicit and prefix-safe; licensing and external-service disclosures are present. Multisite network activation is now refused (RK-F-05).

### 5.4 Changed-file review

The six shipped changed files were reviewed line-by-line. No new input, output, authentication, SQL, file, redirect, serialization, or privacy defect was introduced. The changes are narrowly scoped to the five findings plus readme text.

---

## 6. Tests and static analysis at `fb386a4`

| Check | Result |
|---|---|
| PHP syntax (changed files) | clean |
| PHPUnit (full suite, path-sensitive worktree named `rankkernel`) | **2,392 tests, 9,197 assertions, 8 test-only deprecations, exit 0** |
| Focused remediation tests | 126 tests; one basename-only failure; rerun `PluginTest` 18/18 pass |
| PHPCS / WPCS | exit 0 |
| PHPStan L6 | 179 files, no errors |
| Composer validate `--strict` | valid |
| Composer audit `--locked` | no advisories |
| JS tests | 352 pass, 0 fail |
| `node --check` | 21 files, exit 0 |
| Psalm / Psalm Security | **PASS** — exact-commit CI scan ran (1,862 files scanned, 179 analyzed), SARIF uploaded, zero taint findings; `continue-on-error: true` is a CI-hardening caveat |

---

## 7. Plugin Check on the exact ZIP

The exact ZIP was installed into staging WordPress 7.1.4 and 6.5.14. Installed files match the extracted package with zero missing/mismatched files; plugin version is 0.1.0; Plugin Check 2.1.0 is active.

| Version | Errors | Warnings |
|---|---|---|
| WP 7.1.4 | **0** | **223** |
| WP 6.5.14 | **0** | **223** |

The warning set is identical on both versions:

- **203** `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` in 18 `src/Admin/Views/**` files. The views have no `global` declarations; variables are method-local `$rk*` values included inside methods. False positives.
- **20** `PluginCheck.Security.DirectDB.UnescapedDBParameter`:
  - 15 sitemap-provider queries using `$wpdb->prepare( ...$args )` argument unpacking with fixed table names and placeholders.
  - 3 fixed `CREATE TABLE` DDL/fallback queries with no user input.
  - 1 prepared redirect hit-counter `UPDATE` with placeholders.
  - 1 `uninstall.php` prefix-scoped `SHOW TABLES LIKE` sweep with a regex-gated table identifier before `DROP TABLE`.

All 20 were inspected in source. None is a user-controlled SQL sink. The 223 warnings are explained false positives/safe patterns, not unreviewed defects.

---

## 8. Runtime smoke tests

**PASS on both WordPress 7.1.4 and 6.5.14.** The exact ZIP was installed on disposable staging (`/home/ubuntu/rk-staging/wp71` and `/home/ubuntu/rk-staging/wp65`); 239/239 installed file hashes match the extracted package with zero missing/mismatched files, and the plugin is active at 0.1.0 on both.

| Check | WP 7.1.4 | WP 6.5.14 |
|---|---|---|
| Homepage | 200, no fatal | 200, no fatal |
| `sitemap_index.xml` / `post-sitemap.xml` | 200, valid XML | 200, valid XML |
| `wp-sitemap.xml` | 404 (RankKernel owns sitemaps) | 404 (RankKernel owns sitemaps) |
| `robots.txt` | 200 (RankKernel AI rules) | 200 (baseline; robots module off as found) |
| Redirect | 301 to `/` with query preserved | 301 to `/` with query preserved |
| 404 probe | logged, hits=1 | logged, hits=1 |
| Admin pages | 9/9 HTTP 200, no fatal/notice/warning/deprecated | 9/9 HTTP 200, no fatal/notice/warning/deprecated |
| Metadata save → head render | pass | pass |
| Redirect insert → 301 → delete → 404 | pass | pass |
| Disable redirects → admin 403 + frontend 404; re-enable → 200 + 301 | pass | pass |
| `debug.log` RankKernel lines / web fatals | 0 / 0 | 0 / 0 |

Environment deprecation noise came from WP-CLI's phar and WordPress core on PHP 8.5, not from RankKernel. Test data was removed and the WP 6.5.14 module list was restored to its as-found five-module state. Runtime evidence: `/tmp/rk-final-audit/new/runtime/RUNTIME-SMOKE-SUMMARY.md` and the `public-*`, `admin-*`, `metadata-workflow`, `module-toggle`, `debug-*-final`, and `hash-*` logs.

---

## 9. Exact-commit CI and repository state

CI run **`41988654857`** was triggered by the push of `fb386a4` to `main`; the release branch was fast-forwarded to the same commit after the run, so the run covers the exact content. It completed successfully.

| Check | Result | Real scan? |
|---|---|---|
| WPCS, PHPStan & PHPUnit (PHP 8.3) | **success** — `composer.json` valid, audit clean, PHPCS 0, PHPStan no errors, PHPUnit `OK (2392 tests, 9197 assertions)` | Yes |
| WPCS, PHPStan & PHPUnit (PHP 8.4) | **success** — same gates | Yes |
| Psalm Security Scan | **success** — 1,862 files scanned, 179 analyzed, SARIF uploaded, **zero taint findings** | Yes |
| Breadcrumb KSES Integration | **success** — WP 7.1.4 + MySQL 8.0; `BREADCRUMB_KSES_INTEGRATION_OK` | Yes |
| Removed Work Guard | skipped by design (`if: pull_request`); this was a push | N/A |
| CodeRabbit suite | queued/null; third-party informational | N/A |

**Psalm caveat:** `ci.yml:140` sets `continue-on-error: true` on the scan step. The scan itself succeeded and the SARIF/code-scanning record has zero taint findings, but a future taint failure would still show green. This is a CI-hardening follow-up, not a defect in this candidate.

### Branch and consolidation state

- `origin/main` and `origin/release/wporg-0.1.0` both resolve to `fb386a4`.
- `2afb118` is an ancestor; `2afb118..fb386a4` is one commit, 12 files, +252/−13, **zero production deletions**.
- `release/wporg-0.1.0-final` and `release-candidate/*` are absent from remote refs; the approved `2afb118` source is preserved in history.
- Audit evidence branches are intact: `audit/final-fc5c641` (`61e1fc8…`), `audit/wporg-0.1.0-final-delivery` (`cac368d…`), `audit/wporg-remediation-2026-11-delivery` (`e313ad0…`), plus `audit/reports-a3fb941` and `audit/reports-b0ac1c8`.
- No tags exist. The draft release targets the deleted branch `release-candidate/integrate-0.1.0`.
- No unique production work was lost in consolidation. Dangling objects from deleted branches were not enumerated; `git fsck --unreachable` remains optional if stronger assurance is required.

### Release assets and manifest

- Draft `v0.1.0-rc-rel01` holds only stale assets: `rankkernel-0.1.0.zip` 901,122 / `0d891a…`; `rankkernel-0.1.0-release-rel01.zip` 898,875 / `ee5b82…`; `SHA256SUMS` 169 bytes / `d2221036…`. The exact candidate `4edb5be…` is not uploaded.
- The committed `audit-final/RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt` has SHA-256 `b15d8449…` and is correct for the exact ZIP; `audit-final/SHA256SUMS` is self-consistent.
- Reports cite `129887…` for a local build-output `release-output/manifest.txt` that was never committed. No blob in history hashes to `129887…`. The same 239 rows are present; this is a report/evidence-format inconsistency, not a ZIP mismatch.

---

## 10. Release and cleanup concerns

1. **Do not publish the current draft release as-is.** Replace `rankkernel-0.1.0.zip` with the exact `4edb5be…` candidate, replace/remove the stale `release-rel01` ZIP, regenerate `SHA256SUMS`, and retarget the draft to `fb386a4`. Delete stale assets only after owner approval.
2. **Manifest evidence format:** publish the canonical CSV manifest (`129887…`) alongside the existing text manifest (`b15d844…`), or state the format in the report, so the expected hash is reproducible.
3. **No production source changes are proposed.** No branch deletion, merge, rebase, force-push, tag, or WordPress.org action is proposed.
4. **Reports-only delivery:** publish this audit and findings on a dedicated reports-only branch; do not merge into production.
5. **Test-only portability:** fix `tests/Unit/PluginTest.php` to use `plugin_basename( RANKKERNEL_FILE )` or a fixture directory named `rankkernel`.

---

## 11. Limitations

- The WordPress.org reviewer's original 48-line scanner output and the eleven original examples are not available in shared evidence; one-to-one reproduction is not claimed.
- The previously reported four-`FIXED` matrix is not available; the only shared 409-row matrix is unclassified. The five fixes are verified from the actual commit and tests, not from that matrix.
- Psalm was not run locally (no binary, Docker unavailable); exact-commit CI evidence is used and its continue-on-error status is reported separately.
- Runtime results are limited to the disposable staging environments; no production site was modified.
- The 233 unchanged packaged files rely on the prior independent full-package audits, with byte-identity verified against `2afb118`; the six changed files were reviewed independently here.

---

## 12. Final verdict

**APPROVED WITH NON-BLOCKING FOLLOW-UPS.**

The exact candidate ZIP is independently verified: SHA-256 `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`, 902,973 bytes, 239 files, one top-level `rankkernel/`, no traversal/symlinks, and every packaged file matches source `fb386a419d6b32394cc342cf740f8e41a75389bb`. The canonical 239-row CSV manifest hashes to `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`.

All five new remediation changes are verified in code and tests. The full test suite, PHPCS, PHPStan L6, Composer validation/audit, JS tests, JS syntax, exact-commit CI, Psalm Security, Plugin Check on both WordPress versions, and runtime smoke tests on WordPress 7.1.4 and 6.5.14 pass. No blocking security, package-integrity, or runtime defect remains.

The approval is qualified only by non-blocking follow-ups:

- **RK-F-01:** the GitHub draft release still contains stale assets; upload the exact `4edb5be…` ZIP, regenerate `SHA256SUMS`, retarget the draft to `fb386a4`, and delete the old assets only after owner approval. Do not publish the draft as-is.
- **RK-F-09:** the expected manifest hash is for the canonical CSV format; the committed text manifest is a different format for the same 239 files. Publish the canonical CSV or state the format.
- **RK-F-13:** Psalm is a real zero-taint scan, but `continue-on-error: true` weakens the CI gate; remove it or make the SARIF gate blocking.
- **RK-F-15:** `PluginTest` hardcodes a checkout basename; this is test-only portability, not a shipped defect.

No production source, ZIP, branch, tag, release, or WordPress.org state was modified by this audit. The report is delivered on a reports-only branch.
