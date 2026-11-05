# RankKernel WordPress.org — Persistent Handoff

Date: 2026-11-05  
Main: `fc5c6416e9499f3ed93106c1445c9016393d57a8`  
Release branch: `release/wporg-0.1.0-final` (same commit)  
Version: `0.1.0`  
Final ZIP SHA-256: `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39`  
Manifest SHA-256: `3c20b8eb73ad1c49414725e0fbce67e9ffc658e9539fe2f3b90bfe89bbfa11d5`

## Completed

- Release baseline, REL-01, five importer GPL tags and `.distignore` are in main.
- P1-01 breadcrumb filter output sanitized after filtering for getter, template tag, shortcode and block.
- Protocol-relative and dangerous URL schemes rejected; custom separator style validated and restored after KSES.
- P2-01 custom schema JSON bounded at 1 MiB and depth 64.
- PR #417, #419 and #416 integrated via #421; duplicates #420, #418 and #422 closed.
- #415, #414 and #342 closed without merging with documented technical reasons. **Zero open PRs.**
- AI reviewer rules updated for callback/filter output, superglobal tracing and upload validation.
- Full production audit completed on the shipped 239-file package: 409 input rows classified (401 executable, 8 comment-only; 16 PASS, 385 accepted, 0 blocked); 1,244 output statements classified (1,237 contextual pass, 7 accepted); 0 new P0/P1/P2 defects.

## Open / deferred

- Nothing release-blocking. Closed without merge:
  - #415 Classic Editor a11y announcements — needs rebase and manual screen-reader verification before any future merge.
  - #414 TrailBuilder memoization — cache-key/multisite/filter-invalidation concerns unresolved; do not merge without tests.
  - #342 tooling-only AI reviewer bump — separate owner decision.
- WordPress.org legal/trademark clearance of the plugin name remains an owner decision; not claimed.

## Verification

- PHPUnit 2375/9150 OK; PHPCS exit 0; PHPStan L6 clean; Psalm + Security pass; JS 352/0; validate/audit clean.
- Plugin Check exact ZIP: 0 errors / 223 warnings (203 template-local prefix, 20 custom-table SQL).
- Breadcrumb KSES integration passes on WP 7.1.4; local WP 6.5.14/7.1.4.
- WP 7.1.4 staging: exact ZIP installed, admin pages load, no console errors, empty `debug.log`.
- Final ZIP: `rankkernel-0.1.0.zip`, 901,122 bytes, SHA-256 `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39`, 239 files.
- Manifest: `MANIFEST.sha256`, 239 lines, SHA-256 `3c20b8eb73ad1c49414725e0fbce67e9ffc658e9539fe2f3b90bfe89bbfa11d5`.

## Deliverables

On `audit/final-fc5c641` under `audit-final/`:

- `RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt`
- `RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv`
- `RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv`
- `RANKKERNEL_PRODUCTION_SECURITY_AUDIT.md`
- `RANKKERNEL_HISTORIC_WPORG_FINDINGS.md`
- `RANKKERNEL_FINAL_TEST_REPORT.md`
- `RANKKERNEL_IMPLEMENTATION_REPORT.md`
- `RANKKERNEL_WPORG_HANDOFF.md`
- `SHA256SUMS`

## Remaining gate

Independent Server OpenCode audit of the exact source commit and exact ZIP checksum is required. No WordPress.org upload, publish, tag, GitHub release or reviewer reply has been performed.

## Next action

Provide the auditor with `main`/release commit `fc5c641`, ZIP SHA-256 `0d891a9f…`, the manifest, the input/output matrices, Plugin Check JSON and test evidence. Resolve any audit findings; rebuild only if source changes, and never reuse an old checksum for a modified package.
