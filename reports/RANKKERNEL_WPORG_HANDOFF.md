# RankKernel WordPress.org Remediation — Audit Handoff

**Date:** 2026-11-04  
**Repository:** `maulikbhalodiya/rankkernel`  
**Branch:** `wporg/remediation-2026-11`  
**HEAD:** `b0ac1c8bf1d34f47a74e90fcc5d5644d0a7d7dfe`  
**PR:** #421 — OPEN, non-draft, base `main`, mergeable, CI `UNSTABLE`  
**Base `main`:** `390ee9a64eb04f45be917d939da6f01f823d956a`  
**Verdict:** **APPROVED WITH NON-BLOCKING FOLLOW-UPS**

## Current state

- PR #421 is the WordPress.org remediation branch. It is 13 commits, 28 files, +711/−32.
- It includes #416 (sitemap `nosniff`), #417 (MetadataBox translations), #419 (AuthorsProvider cache priming), REL-01, P1 breadcrumb sanitization, and P2 schema JSON limits.
- No #414, #415, or #342 content is in #421.
- No merge, close, source-branch push, tag, release publication, or WordPress.org upload was performed by the audit. A reports-only delivery branch `audit/wporg-remediation-2026-11-delivery` contains only the audit files.

## Completed remediation

### P1 — breadcrumb output sanitization

- `src/Modules/Breadcrumbs/functions.php`: final `rankkernel/breadcrumbs` filter output is sanitized with a narrow allowlist, URL policy, real `wp_kses`, CSS custom-property validation, and a fallback.
- `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`: string filter returns are sanitized.
- Getter, template tag, shortcode, and dynamic block all use the sanitized getter/path.
- Real WordPress 6.5.14 and 7.1.4 probes: **17/17 cases PASS** on each.
- Dangerous schemes (`javascript:`, `data:`, `vbscript:`, `file:`), event handlers, injected CSS, and CSS-escape payloads are removed.

### P2 — schema JSON limits

- `src/Admin/SchemaMetabox.php`: `MAX_IMPORT_BYTES = 1048576`, `MAX_JSON_DEPTH = 64`.
- 1 MiB check occurs before `json_decode` for the custom textarea.
- Import path stats the file before reading, re-checks size after read, then decodes at depth 64.
- Invalid/oversized JSON sets `invalid-json`; stored custom schema is preserved.
- Focused tests: **48 tests, 150 assertions, OK**.

### Integrations

- #417: MetadataBox script translations registered. **Low follow-up:** add `wp-i18n` to `rankkernel-analysis-format` dependencies (`MetadataBox.php:83` vs `:835-836`).
- #419: AuthorsProvider primes user caches without changing output. **PASS**.
- #416: sitemap XML/XSL/404 `X-Content-Type-Options: nosniff`; `headers_sent()` guarded. **PASS**.

## Input sanitization

- `RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv` was absent in the repository.
- Independent inventory: **409 occurrences / 16 files**; 229 lines.
- Breakdown: `$_POST` 226, `$_GET` 152, `$_SERVER` 21, `$_FILES` 10; zero `$_REQUEST`/`$_COOKIE`/`$_SESSION`/`$_ENV`.
- High-risk flows to SQL, uploads, redirects, headers, includes, `unserialize`, mail, and remote HTTP were traced. **Zero class-2 (potentially unsafe) flows verified.**
- Honest limits: the CSVs's `classification` column is raw/`not reviewed`; this is not a line-by-line proof of all 409 occurrences. Stored-output follow-up closed C1-C6; C4/C7 are sampled, not exhaustive.
- The audit does **not** claim to reproduce the WordPress.org reviewer's 48 incidences; only the 409/16 inventory and traced flows were independently verified.

## Tests and gates

Independently run in `/tmp/rk-wporg-audit`:

| Check | Result |
|---|---|
| PHP syntax | 347 files, 0 failures |
| `composer validate --strict` | Valid |
| `composer audit --locked` | No advisories |
| PHPStan | 179/179, no errors |
| PHPUnit | 2,375 tests, 9,142 assertions, 8 deprecations, 0 failures/errors |
| JS tests | 352/352 pass |
| Node syntax | 21 files, no errors |
| PHPCS/WPCS | 0 findings |
| P1 focused | 158 tests, 409 assertions, OK |
| P2 focused | 48 tests, 150 assertions, OK |
| Stored-output focused | 724 tests, 2,305 assertions, OK |
| Real-WP P1 probe | 17/17 PASS on WP 6.5.14 and 7.1.4 |
| Plugin Check (correct slug, full worktree) | 23 errors / 265 warnings; all 23 errors and 244 warnings are non-shipped tests/tools/dotfiles; the 20 shipped `DirectDB` warnings were previously audited as false positives. No shipped-runtime error. |

Inspected CI at `b0ac1c8`: PHP 8.3/8.4 quality jobs pass; Psalm Security Scan pass; Removed Work Guard pass; Psalm pending/queued; AI Code Review reported fail by `gh pr checks` (check-runs API says cancelled) — non-gating opt-in tool. The first Plugin Check run used a temporary slug and produced 1,460 text-domain artifact errors; the correct-slug run is authoritative. A clean-package 223-warning count was not independently reproduced because building a release ZIP was prohibited.

## Outstanding issues / non-blocking follow-ups

1. **P1 test gap (Medium):** repository tests use a `strip_tags` KSES shim and no `WP_HTML_Tag_Processor`; production runtime was verified separately. Add a real-path integration test.
2. **#417 i18n (Low):** add `wp-i18n` to `rankkernel-analysis-format` script dependencies.
3. **P2 boundary tests (Low):** exact 1 MiB acceptance; depth 63/64/65; scalar/null/empty custom.
4. **P1 fallback (Low):** fallback regex handles double-quoted style only; dead on supported WP 6.5+, but document or extend.
5. **CSV hardening (Low):** `escape_cell()` misses leading LF/space; unreachable from normalized stored data.
6. **PR housekeeping:** #420 duplicate of #417; #418 duplicate of #419; #415/#414 independent and need rebase; #342 open/conflicting dependabot bump. Do not merge/close without owner authorization.

No Critical or High findings. No material security issue remains unresolved.

## PR decisions and dependencies

| PR | State | Decision / dependency |
|---|---|---|
| #421 | OPEN, mergeable, UNSTABLE | Audit target; approved with non-blocking follow-ups. Do not merge without owner authorization. |
| #417 | OPEN, CLEAN | Content is in #421; keep open until #421 lands, then close only with authorization. |
| #419 | OPEN, UNSTABLE | Content is in #421; same. |
| #416 | OPEN, UNSTABLE | Content is in #421; same. |
| #420 | OPEN, UNSTABLE | Duplicate/subset of #417; not needed separately. |
| #418 | OPEN, UNSTABLE | Duplicate of #419; not needed separately. |
| #415 | OPEN, UNSTABLE | Independent a11y work; not in #421; rebase/review separately. |
| #414 | OPEN, UNSTABLE | Independent breadcrumb perf; not in #421; rebase and re-review against P1 sanitizer. |
| #342 | OPEN, CONFLICTING | Dependabot workflow bump; not merged; resolve separately. |

## Evidence paths

- Independent audit: `/tmp/rk-audit-reports/RANKKERNEL_WPORG_INDEPENDENT_AUDIT.md`
- Handoff: `/tmp/rk-audit-reports/RANKKERNEL_WPORG_HANDOFF.md`
- Input matrix: `/tmp/rk-audit-reports/RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv`
- Worktree: `/tmp/rk-wporg-audit` (detached at `b0ac1c8`)
- Logs: `/tmp/rk-audit-logs/`
- P1 real-WP probe: `/tmp/rk-audit-probe/breadcrumb.php`
- P1 WP 6.5 log: `/tmp/rk-audit-logs/real-wp65-breadcrumb.log`
- P1 WP 7.1 log: `/tmp/rk-audit-logs/real-wp71-breadcrumb.log`
- Prior release-candidate audit: `/home/ubuntu/rk-audit/wporg-final-review/INDEPENDENT_RELEASE_CANDIDATE_AUDIT.md`

## Delivery links

Reports-only branch: `audit/wporg-remediation-2026-11-delivery`

- Audit report: https://github.com/maulikbhalodiya/rankkernel/blob/audit/wporg-remediation-2026-11-delivery/reports/RANKKERNEL_WPORG_INDEPENDENT_AUDIT.md
- Handoff: https://github.com/maulikbhalodiya/rankkernel/blob/audit/wporg-remediation-2026-11-delivery/reports/RANKKERNEL_WPORG_HANDOFF.md
- Input matrix: https://github.com/maulikbhalodiya/rankkernel/blob/audit/wporg-remediation-2026-11-delivery/reports/RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv

## Release restrictions

- Do **not** publish the draft release, create tags, build/publish a release ZIP, or upload to WordPress.org without explicit owner authorization.
- Do **not** merge or close any PR based on this audit alone.
- Keep all changes read-only until the owner gives release authorization.
- The developer packaging script/byte-identical rebuild, PHP 8.3/8.4 local runtime, multisite, and real Apache/LiteSpeed `.htaccess` interception remain outside this audit's independent runtime coverage.

## Exact next action for Office OpenCode

1. Read this handoff and the independent audit report.
2. If the owner wants to proceed with release: apply the non-blocking follow-ups that are in scope, starting with the #417 `wp-i18n` dependency and the P1 real-path test.
3. Re-run the full gates and Plugin Check on a proper-slug release package.
4. Obtain explicit owner authorization before any merge, tag, release publication, or WordPress.org upload.

No implementation work was performed by the independent auditor.
