# RankKernel WordPress.org — Persistent Handoff

Date: 2026-11-05  
Main: `fc5c6416e9499f3ed93106c1445c9016393d57a8`  
Release branch: `release/wporg-0.1.0-final` (same commit)  
Version: `0.1.0`

## Completed

- Release baseline, REL-01, five importer GPL tags and `.distignore` are in main.
- P1-01 breadcrumb filter output sanitized after filtering for getter, template tag, shortcode and block.
- Protocol-relative and dangerous URL schemes rejected; custom separator style validated and restored after KSES.
- P2-01 custom schema JSON bounded at 1 MiB and depth 64.
- PR #417, #419 and #416 integrated via #421; duplicates #420, #418 and #422 closed. #415, #414 and #342 were closed without merging (see below).
- AI reviewer rules updated for callback/filter output, superglobal tracing and upload validation.

## Pull requests

- **Zero open PRs.**
- #415 closed without merge: useful accessibility work, but based on pre-integration main and needs a rebase plus real screen-reader verification; not part of 0.1.0.
- #414 closed without merge: TrailBuilder static cache has unresolved blog-ID fallback, cross-context/filter invalidation and stale-trail risks; not safe for release.
- #342 closed: tooling-only, conflicting, title/version metadata mismatch; current v1.23.1 workflow pin remains unchanged.
- #417/#419/#416 are in main via #421; #420/#418/#422 were duplicates.

## Verification

- PHPUnit 2375/9150 OK; PHPCS exit 0; PHPStan L6 clean; JS 352/0; validate/audit clean.
- Plugin Check exact ZIP: 0 errors / 223 warnings (203 template-local prefix, 20 custom-table SQL).
- Breadcrumb KSES integration passes on WP 7.1.4; local WP 6.5.14/7.1.4.
- Final ZIP: `rankkernel-0.1.0.zip`, 901,122 bytes, SHA-256 `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39`, 239 files.
- Manifest: `MANIFEST.sha256`, 239 lines, SHA-256 `3c20b8eb73ad1c49414725e0fbce67e9ffc658e9539fe2f3b90bfe89bbfa11d5`.

## Remaining gate

Independent Server OpenCode audit of the exact source commit and exact ZIP checksum is required. No WordPress.org upload, publish, tag, or reviewer reply has been performed.

## Next action

Provide the auditor with `main`/release commit `fc5c641`, ZIP SHA-256 `0d891a9f…`, manifest, Plugin Check JSON and test evidence; resolve any audit findings, rebuild only if source changes, then request final checksum-specific approval.
