# RankKernel 0.1.0 — Baseline State (2026-11-06)

## Branch and source

- Canonical branch: `release/wporg-0.1.0`
- Baseline HEAD: `eba5018e66bb9a3236594535b748cc04fdb7779b`
- `origin/main`: `eba5018e66bb9a3236594535b748cc04fdb7779b`
- `origin/release/wporg-0.1.0`: `eba5018e66bb9a3236594535b748cc04fdb7779b`
- Working tree at baseline: clean except pre-existing untracked report/doc files, reviewed before any edit.

## Exact WordPress.org-submitted package

The November 5, 2026 submission was downloaded from the URL in the WordPress.org email and verified locally:

- Submitted URL: `https://wordpress.org/plugins/files/2026/10/05_10-32-28_rankkernel-0.1.0.zip`
- Local comparison file: `/tmp/opencode/wporg-submitted-rankkernel.zip`
- Size: 902,973 bytes
- Files: 239
- SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`
- This is byte-for-byte identical to the independently approved ZIP at `/home/web-dev-3/Documents/my personal plugin/Rankkernel/release-output/rankkernel-0.1.0.zip`.

## Source identity of the submitted package

- Production source recorded in `BUILD_INFO.txt` and `FINAL_SOURCE_IDENTITY.md`: `fb386a419d6b32394cc342cf740f8e41a75389bb`.
- The final branch tip `eba5018` differs from `fb386a4` only in distributed-excluded development files (`.audit-prompts/*`, `.github/workflows/*`, `.opencode-reviewer.yml`, `tests/Unit/PluginTest.php`).
- The submitted ZIP does not contain those development files; its 239 files match the packaged production files at both revisions.
- Previous approved ZIP `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252` (source `2afb118`) is preserved as `rankkernel-0.1.0-last.zip`.

## Preservation rules for this remediation

1. Do not overwrite `rankkernel-0.1.0.zip` or `rankkernel-0.1.0-last.zip`.
2. Do not upload to WordPress.org, publish a GitHub release, replace draft assets, create tags, force-push, or delete audit evidence.
3. Work only on `release/wporg-0.1.0`.
4. If any distributed production file changes, build a fresh candidate ZIP, manifest and SHA-256 after all fixes; the existing approved ZIP remains the old baseline.
5. Independent Server review is required for any new candidate. This report does not claim approval.

## Baseline test state

- PHPUnit: 2392 tests, 9197 assertions, OK at `eba5018`.
- Plugin Check on the submitted ZIP: 0 errors / 223 warnings (previously recorded; rerun required for the final candidate).
- Full evidence and command logs will be recorded in `FINAL_TEST_REPORT.md`.

## Superseding final candidate (pointer; baseline above is historical and unchanged)

The baseline above is preserved as history. It is superseded by the final production candidate recorded in `FINAL_SOURCE_IDENTITY.md`: source `57dfc063d022d380d43947972c26f9bd976e5b56`, ZIP `rankkernel-0.1.0.zip` (937,587 bytes, 240 files, SHA-256 `f6beba8f830994dc3ef952f7757d7ad079073d5b21787fe64c4265083732bedd`). See `FINAL_TEST_REPORT.md`, `FINAL_SECURITY_AUDIT.md` and `FINAL_RELEASE_DECISION.md` for the final evidence.
