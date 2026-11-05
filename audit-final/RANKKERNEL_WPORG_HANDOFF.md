# RankKernel WordPress.org — Persistent Handoff

Date: 2026-11-05  
Main/release commit: `2afb1185d919f03ad4ed74444466db3b44db0786`  
Release branch: `release/wporg-0.1.0-final`  
Version: `0.1.0`  
Final ZIP: `rankkernel-0.1.0.zip` — 902,022 bytes, 239 files  
ZIP SHA-256: `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`  
Manifest SHA-256: `e6ad78e0c97f3f26f34bd2a7de56a8fe10f9778b0aa326f316e54169026b3f87`

## Completed

- Release baseline, REL-01, five importer GPL tags and `.distignore` are in the release branch.
- P1-01 breadcrumb filter output sanitized after filtering for getter, template tag, shortcode and block.
- Protocol-relative and dangerous URL schemes rejected; custom separator style validated and restored after KSES.
- P2-01 custom schema JSON bounded at 1 MiB and depth 64.
- **Final remediation pass fixed five input validation/sanitization defects:** log search unslash/sanitize, MetaPayload array coercion, REST import body params, SupportRequest array validation, Analysis keyword nested arrays. See `RANKKERNEL_REMEDIATION_SUMMARY.md`.
- PR #417, #419 and #416 integrated via #421; duplicates #420, #418 and #422 closed.
- #415, #414 and #342 closed without merging with documented technical reasons. **Zero open PRs.**
- AI reviewer rules updated for callback/filter output, superglobal tracing and upload validation.
- Full production audit completed on the shipped 239-file package: 409 input rows classified (401 executable, 8 comment-only; 15 PASS, 4 FIXED, 382 accepted, 0 blocked); 1,244 output statements classified (1,237 contextual pass, 7 accepted); 0 output defects.

## Open / deferred

- Nothing release-blocking. Closed without merge:
  - #415 Classic Editor a11y announcements — needs rebase and manual screen-reader verification before any future merge.
  - #414 TrailBuilder memoization — cache-key/multisite/filter-invalidation concerns unresolved; do not merge without tests.
  - #342 tooling-only AI reviewer bump — separate owner decision.
- WordPress.org legal/trademark clearance of the plugin name remains an owner decision; not claimed.
- Psalm has not been re-run locally for this remediation; CI Psalm was green on the prior commit.

## Verification

- PHPUnit 2385/9182 OK; PHPCS exit 0; PHPStan L6 clean; JS 352/0; validate/audit clean.
- Plugin Check exact ZIP: 0 errors / 223 warnings (203 template-local prefix, 20 custom-table SQL).
- WP 7.1.4 exact-ZIP smoke: 9 RankKernel admin screens HTTP 200; sitemap 200 XML, robots 200 text, unknown path 404; debug log clean.
- Manifest comparison: 239/239 installed hashes match.

## Deliverables

On `audit/final-fc5c641` under `audit-final/` (existing audit branch; no new branch):

- `RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt`
- `RANKKERNEL_PRODUCTION_INPUT_MATRIX.csv`
- `RANKKERNEL_PRODUCTION_OUTPUT_ESCAPING_MATRIX.csv`
- `RANKKERNEL_PRODUCTION_SECURITY_AUDIT.md`
- `RANKKERNEL_HISTORIC_WPORG_FINDINGS.md`
- `RANKKERNEL_FINAL_TEST_REPORT.md`
- `RANKKERNEL_IMPLEMENTATION_REPORT.md`
- `RANKKERNEL_WPORG_HANDOFF.md`
- `RANKKERNEL_INPUT_SANITIZATION_REMEDIATION.md`
- `RANKKERNEL_OUTPUT_ESCAPING_REMEDIATION.md`
- `RANKKERNEL_REMEDIATION_SUMMARY.md`
- `SHA256SUMS`

## Remaining gate

Independent Server OpenCode audit of the exact source commit `2afb118` and exact ZIP checksum `87d94a56…` is required. No WordPress.org upload, publish, tag, GitHub release or reviewer reply has been performed.

## Next action

Provide the auditor with commit `2afb118`, ZIP SHA-256 `87d94a56…`, the manifest, the input/output matrices, the three remediation reports, Plugin Check JSON and test evidence. Resolve any audit findings; rebuild only if source changes, and never reuse an old checksum for a modified package.
