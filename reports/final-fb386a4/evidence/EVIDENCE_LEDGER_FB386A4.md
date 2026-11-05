# RankKernel 0.1.0 — Evidence ledger for `fb386a4`

## Exact target

- Repository: `maulikbhalodiya/rankkernel`
- Branch: `release/wporg-0.1.0`
- Commit: `fb386a419d6b32394cc342cf740f8e41a75389bb`
- Previous approved commit: `2afb1185d919f03ad4ed74444466db3b44db0786` (ancestor)
- Candidate ZIP: `/tmp/rk-final-audit/new/rankkernel-0.1.0.zip`
- SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`
- Size: `902,973` bytes
- Files: `239`; one top-level `rankkernel/`; no traversal; no symlinks; `unzip -t` clean
- Uncompressed: `3,031,075` bytes; 181 PHP; 44 JS/CSS
- Source match: all 239 packaged files match `git show fb386a4:<path>`; 0 missing, 0 mismatched, 0 extra
- Canonical CSV manifest: `RANKKERNEL_FINAL_PACKAGE_MANIFEST.csv`, SHA-256 `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`
- Committed text manifest: `audit-final/RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt`, SHA-256 `b15d8449707dc3003e59fef99eadc7f4302d6b8e24fe378daed1b6852f36fd13`; same 239 files/hashes

## Package delta from previous approved `2afb118`

- 233 of 239 packaged files byte-identical.
- 6 shipped files changed:
  - `rankkernel.php`
  - `readme.txt`
  - `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`
  - `src/Modules/Metadata/MetaPayload.php`
  - `src/Modules/ModuleRegistry.php`
  - `src/Rest/ImportController.php`
- Source diff: 12 files, +252/−13, zero production deletions.
- Other six source changes are tests/docs, not shipped.
- 301 source files intentionally not shipped.

## Five remediation changes

1. MetaPayload `flags.pillar` / `flags.cornerstone` use `nullableBool(...) ?? false`; arrays/objects rejected. Tests pass.
2. Breadcrumb block booleans use `boolAttribute()` with fallback; malformed arrays/objects rejected. Tests pass.
3. ModuleRegistry throws raw exception message; no display path; test asserts no pre-escaping.
4. Network activation refused before setup with `deactivate_plugins(..., true)` and 403; readme documents single-site scope; test passes in `rankkernel` checkout.
5. Import `run_id` requires string; non-strings rejected with 400; test passes.

## Tests and checks

- PHPUnit full suite: 2,392 tests, 9,197 assertions, 8 test-only deprecations, exit 0.
- Focused tests: 126 tests; one basename-only failure; `PluginTest` rerun 18/18 pass in a `rankkernel` checkout.
- PHPCS: exit 0.
- PHPStan L6: 179 files, no errors.
- Composer validate `--strict`: valid; audit `--locked`: no advisories.
- JS tests: 352 pass; `node --check`: 21 files clean.
- CI run `41988654857` at `fb386a4`: WPCS/PHPStan/PHPUnit PHP 8.3 and 8.4 success; Breadcrumb KSES success; Psalm Security success; Removed Work Guard skipped by design.
- Psalm: real scan (1,862 files scanned, 179 analyzed), SARIF uploaded, zero taint findings; `continue-on-error: true` remains a CI-hardening gap.
- Plugin Check exact ZIP: WP 7.1.4 and 6.5.14 both **0 errors, 223 warnings**; warning sets identical.
  - 203 PrefixAllGlobals in 18 Admin/Views files: method-local `$rk*` variables, no globals; false positives.
  - 20 DirectDB: 15 prepared sitemap provider queries; 4 fixed DDL/prepared table queries; 1 prefix-scoped regex-gated uninstall DROP; all inspected safe.
- Runtime smoke: **PASS on WP 7.1.4 and 6.5.14.** Exact ZIP installed with 239/239 file hashes matching; homepage/sitemap/robots 200; redirect 301; 404 logged; 9/9 admin pages 200; metadata and redirect workflows pass; `debug.log` 0 RankKernel lines / 0 web fatals on both. Evidence: `/tmp/rk-final-audit/new/runtime/RUNTIME-SMOKE-SUMMARY.md`.

## Release and repository state

- `origin/main` and `origin/release/wporg-0.1.0` both at `fb386a4`.
- `2afb118` is an ancestor; previous approved source preserved.
- `release/wporg-0.1.0-final` and `release-candidate/*` absent from remote refs.
- Audit evidence branches intact: `audit/final-fc5c641`, `audit/wporg-0.1.0-final-delivery`, `audit/wporg-remediation-2026-11-delivery`, plus older audit report branches.
- Draft `v0.1.0-rc-rel01` targets deleted `release-candidate/integrate-0.1.0` and holds stale assets only:
  - `rankkernel-0.1.0.zip` 901,122 / `0d891a9f…`
  - `rankkernel-0.1.0-release-rel01.zip` 898,875 / `ee5b82cc…`
  - `SHA256SUMS` 169 bytes / `d2221036…`
- Exact candidate `4edb5be…` is not uploaded. Do not publish draft until replaced.
- Manifest evidence inconsistency: reports cite `129887…` for a local `release-output/manifest.txt` never committed; committed text manifest is `b15d844…`; exact ZIP unaffected.

## Key paths

- Exact ZIP: `/tmp/rk-final-audit/new/rankkernel-0.1.0.zip`
- Extracted package: `/tmp/rk-final-audit/new/extracted/rankkernel`
- Source: `/tmp/rk-final-src-fb`; path-sensitive worktree `/tmp/rk-final-audit/src/rankkernel`
- Logs: `/tmp/rk-final-audit/new-full-checks.log`, `/tmp/rk-final-audit/new/pc-wp71.json`, `/tmp/rk-final-audit/new/pc-wp65.json`
- Findings: `/tmp/rk-final-audit/new/RANKKERNEL_FINAL_AUDIT_FINDINGS.csv`
- Report: `/tmp/rk-final-audit/new/RANKKERNEL_FINAL_INDEPENDENT_AUDIT.md`
- Cleanup proposal: `/tmp/rk-final-audit/new/RANKKERNEL_REPOSITORY_CLEANUP_PROPOSAL.md`
