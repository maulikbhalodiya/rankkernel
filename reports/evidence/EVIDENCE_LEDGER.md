# RankKernel 0.1.0 final audit — evidence ledger

## Exact candidate
- Branch: `release/wporg-0.1.0-final`
- Source commit: `2afb1185d919f03ad4ed74444466db3b44db0786`
- `main`: same commit (`2afb1185d919f03ad4ed74444466db3b44db0786`)
- Fix commit: `5a5a4260287ebcafba75d82e29c4728d522ae955`
- ZIP: `/tmp/rk-final-audit/expected/rankkernel-0.1.0.zip`
- SHA-256: `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`
- Size: `902,022` bytes
- Entries: `239` files; one top-level `rankkernel/`; no symlinks; no path traversal; `unzip -t` clean.
- Uncompressed size: `3,028,276` bytes.
- Package PHP files: `181`; JS/CSS files: `44`.
- Every packaged file content hash matches `git show 2afb118:<path>`; zero missing/mismatched files.
- Plugin header: RankKernel SEO & Schema Engine, Version 0.1.0, Requires at least 6.5, Requires PHP 8.2, GPLv2+, Text Domain rankkernel, Domain Path /languages.
- readme: Stable tag 0.1.0; Requires 6.5; Tested up to 7.1; Requires PHP 8.2; Privacy, External Services, AI Assistance Disclosure sections present.
- Generated manifest: `/tmp/rk-final-audit/RANKKERNEL_FINAL_PACKAGE_MANIFEST.csv` (239 rows: path, bytes, sha256).

## GitHub release asset discrepancy
- Draft release `v0.1.0-rc-rel01` contains `rankkernel-0.1.0.zip` asset:
  - size `901,122`
  - SHA-256 `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39`
  - GitHub digest matches computed hash.
- The release asset is **not** the exact candidate. It has the same 239 paths but six files differ from source commit `2afb118`:
  - `src/Admin/InstantIndexingPage.php`
  - `src/Modules/InstantIndexing/LogFilters.php`
  - `src/Modules/Metadata/MetaPayload.php`
  - `src/Rest/AnalysisController.php`
  - `src/Rest/ImportController.php`
  - `src/Support/SupportRequest.php`
- The user-provided exact ZIP matches the expected hash/size and all source contents. The draft release asset must not be published as-is; replace it with the exact candidate before publication.
- `SHA256SUMS` asset currently lists the stale `0d891a…` ZIP and a manifest hash.

## Independent inventories
- Input matrix: `/tmp/rk-final-audit/RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv`
  - 409 rows, 16 files; POST 226, GET 152, SERVER 21, FILES 10.
  - No `$_REQUEST`, `$_COOKIE`, `$_SESSION`, `$_ENV`, `filter_input()`, `getenv()` in package PHP.
- Output candidate inventory: `/tmp/rk-final-audit/RANKKERNEL_PRODUCTION_OUTPUT_CANDIDATES.csv`
  - 1,513 candidate lines across 66 files (independent; broader than reported 1,244).

## Static scan highlights
- No `eval`, `assert`, `create_function`, shell execution, `base64_decode`, `gzinflate`, `str_rot13` in production PHP.
- `unserialize()` only at `MetaPayload.php:127`, `RedirectMapper.php:117`, `RankMathMapper.php:275`, all with `allowed_classes => false`; inputs are DB/stored strings.
- File/network sinks limited and guarded: `HtaccessFile`, `LlmsFileWriter`, `CsvHandler`, `SchemaMetabox` import, `SupportDelivery`; no direct user-controlled include/path.
- Headers/redirects use fixed maps or `wp_safe_redirect`; REST routes have `permission_callback`; AJAX is authenticated only (`wp_ajax_`, no `nopriv`).
- No hardcoded secrets found by scan.

## Evidence availability
- The developer-referenced files (`RANKKERNEL_INPUT_SANITIZATION_REMEDIATION.md`, `RANKKERNEL_OUTPUT_ESCAPING_REMEDIATION.md`, `RANKKERNEL_REMEDIATION_SUMMARY.md`, `RANKKERNEL_REMEDIATION_INPUT_ADDITIONS.csv`, `RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv`, `RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv`, `RANKKERNEL_PRODUCTION_SECURITY_AUDIT.md`, `RANKKERNEL_HISTORIC_WPORG_FINDINGS.md`, `RANKKERNEL_FINAL_TEST_REPORT.md`, `RANKKERNEL_IMPLEMENTATION_REPORT.md`, `RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt`, `PLUGIN_CHECK_FINAL.json`) were **not found** in the shared repository, fetched branches, or local uploads.
- Available prior evidence: `origin/audit/wporg-remediation-2026-11-delivery` reports, `/home/ubuntu/rk-audit/wporg-final-review` prior audit files, and prior audit logs under `/tmp`.
- Limitation: the WordPress.org reviewer's original 48-line scanner output and the eleven examples could not be reconstructed from shared evidence; do not claim one-to-one reproduction.

## Active lane results
- Full input audit: completed; no blocking defect; low/info notes F1 (mail Reply-To defence-in-depth), F2 (MetaPayload flags bool coercion), F3 (BreadcrumbsBlock bool coercion). Verified in source; no security sink.
- Five-fix verification, output escaping, WP.org security, exact-ZIP tests/runtime, cleanup evidence: pending.
