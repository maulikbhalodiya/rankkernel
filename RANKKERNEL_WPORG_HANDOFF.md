# RankKernel — WordPress.org Handoff

Date: 2026-11-05  
Status: remediation verified by independent audit; release packaging not yet approved.

## Current state

| Item | Value |
|---|---|
| Main | `390ee9a64eb04f45be917d939da6f01f823d956a` |
| Remediation branch | `wporg/remediation-2026-11` |
| Audited commit / PR head | `a3fb941290b6e0cf7e11ec997fd687272b71bf7b` |
| PR | #421 — OPEN, mergeable, base `main` |
| Independent audit | **APPROVED WITH NON-BLOCKING FOLLOW-UPS** |

## Completed remediation

- Release baseline `ab1b62a`: REL-01 insert-ID fix, five importer GPL headers, corrected `.distignore`, prior audit remediation.
- P1-01: final `rankkernel/breadcrumbs` filter output sanitized with a narrow `wp_kses` allowlist; URL scheme/control/protocol-relative checks; validated CSS custom property restoration; shortcode, getter/template tag and dynamic block paths covered.
- P2-01: custom schema JSON bounded to 1 MiB and depth 64; invalid/oversized/deep input preserves stored schema.
- P3-03: protocol-relative and backslash/control URLs rejected.
- P3-04: real WordPress KSES/`WP_HTML_Tag_Processor` integration test and CI job.
- PR #417 (MetadataBox translations), #419 (author cache priming), #416 (sitemap/XSL nosniff) are integrated in PR #421.
- Reviewer rules updated in `.opencode-reviewer.yml` and `.coderabbit.yaml`.

## Evidence / tests

- PHPUnit: 2375 tests / 9150 assertions, OK.
- PHPCS: exit 0.
- PHPStan L6: `[OK] No errors`.
- JS: 352 pass / 0 fail; `node --check` clean.
- Composer validate valid; audit no advisories.
- CI on PR #421 at `a3fb941`: PHP 8.3/8.4 gates, Psalm, Psalm Security, Breadcrumb KSES, Removed Work Guard pass.
- Plugin Check on clean 239-file source at WordPress 7.1.4: **0 errors / 223 warnings** (203 template-local prefix + 20 custom-table SQL false positives).
- Real WordPress 7.1.4 shortcode/block filter test: unsafe markup/URLs/styles removed; safe nav and separator style preserved; no debug log.

## Remaining / next action

1. Owner/Office OpenCode must decide whether to merge PR #421 into `main`.
2. After merge, close #417/#420/#419/#418/#422/#416 with explanations.
3. Keep #415/#414 open pending a11y/cache fixes; keep #342 as tooling-only.
4. Do not build a release ZIP until main is merged and the exact ZIP is independently approved.
5. After merge, build a version-specific release branch and ZIP, run Plugin Check/staging, then request independent ZIP verification.

## Audit limitations

- Exact WordPress.org submitted ZIP/Playground hash not recovered; source identity strongly indicated by line mapping, not cryptographic proof.
- Reviewer’s complete 48-line list unavailable; 409 superglobal occurrences inventoried but not all line-by-line traced.
- WordPress 6.5.14 new-sanitizer test was developer-run, not independently re-run.
- Inert script text remains after KSES tag removal; no execution.
- No release ZIP, tag, upload, or WordPress.org submission performed.

## Release restrictions

No WordPress.org upload, publication, or reviewer reply without explicit owner authorization. Do not treat this audit as approval of a ZIP; final ZIP checksum must be independently verified.
