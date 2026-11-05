# RankKernel — Independent Audit Handoff

Audit date: 2026-11-05  
Target commit: `b0ac1c8bf1d34f47a74e90fcc5d5644d0a7d7dfe`  
Branch: `wporg/remediation-2026-11`  
PR: #421 (base `main` at `390ee9a`)  
Verdict: **APPROVED WITH NON-BLOCKING FOLLOW-UPS**

## Completed remediation

- P1-01 breadcrumb filter output is sanitized after `rankkernel/breadcrumbs` in both getter/template-tag/shortcode and dynamic block paths.
- Narrow KSES allowlist + URL scheme checks + custom-property style validation/restoration.
- P2-01 custom schema JSON is capped at 1 MiB and depth 64 before decode; invalid/oversized/deep input preserves stored schema.
- PR #417, #419 and #416 changes are present in #421.
- AI reviewer rules updated for callback output, filter trust, superglobal tracing, uploads and evidence requirements.

## Outstanding non-blocking items

1. `is_safe_breadcrumb_url()` allows protocol-relative `//host/path`; reject it and add a test.
2. Unit tests mock `wp_kses`; add a real-KSES/Tag-Processor integration test for the custom-property restoration path.
3. Removed script tags leave inert text content; optional cleanup only.

## PR decisions

- #417, #419, #416: included in #421; merge after #421.
- #420, #418, #422: duplicates; close after confirmation.
- #415: hold; rebase after #417 and manually test announcements.
- #414: hold; cache key/fallback and filter-context invalidation need correction.
- #342: tooling-only; keep separate; title/diff version mismatch.

## Tests independently run

- PHPUnit: **2375 tests / 9142 assertions, OK**
- PHPCS: exit 0
- PHPStan L6: `[OK] No errors`
- JS: 352 pass / 0 fail
- Composer validate: valid
- Composer audit: no advisories
- Plugin Check on clean 239-file source: **0 errors / 223 warnings**
- WordPress 7.1.4 staging: P1 unsafe markup stripped; style preserved; `//evil.example/x` survives (non-blocking finding); no `debug.log`

Not independently run: WordPress 6.5 staging, all 223 warnings line-by-line, reviewer’s missing 48-line list. CI on #421: PHP 8.3/8.4 + Psalm pass; AI review pending.

## Evidence paths

- Audit: `/tmp/opencode/audit-b0ac1c8/RANKKERNEL_WPORG_INDEPENDENT_AUDIT.md`
- This handoff: `/tmp/opencode/audit-b0ac1c8/RANKKERNEL_WPORG_HANDOFF.md`
- Developer implementation report (if transferred): `RANKKERNEL_WPORG_REMEDIATION_IMPLEMENTATION.md`
- Developer tracker: `RANKKERNEL_WPORG_REMEDIATION_TRACKER.md`
- Input matrix: `RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv`

## Next action for Office OpenCode

Address the two non-blocking follow-ups (protocol-relative URL rejection and a real-KSES test), keep #421 as the integration PR, then close the duplicate PRs after merge. No new release ZIP, tag, publication or WordPress.org upload is authorized by this audit.

## Release restrictions

No merge, tag, release, upload or source modification was performed by the auditor.
