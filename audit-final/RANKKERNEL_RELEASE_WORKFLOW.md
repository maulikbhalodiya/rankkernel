# RankKernel — Release Workflow

Date: 2026-11-05  
Applies to: WordPress.org releases and all Office/Server agent work.

## 1. One branch per version

| Branch | Purpose |
|---|---|
| `main` | Stable integration history; keep it consistent with the release branch. |
| `release/wporg-<version>` | The single source of truth for that version's WordPress.org approval work. |
| short-lived fix branch | Only if a PR is required. Delete immediately after merge or close. |
| `audit/<cycle>` | One evidence/report branch per submission cycle. Reports only; never shipped. |

For the current release:

`release/wporg-0.1.0` is the only active 0.1.0 branch.

Do **not** create `release/wporg-0.1.0-review-fixes`, `release/wporg-0.1.0-integration`, `release-candidate/*`, `final`, `final2`, `latest`, or a new branch for each review round.

## 2. While WordPress.org review is pending

1. All reviewer-requested fixes for 0.1.0 are committed to `release/wporg-0.1.0`.
2. Run the full tests and build the candidate from the exact branch commit.
3. Keep the previous candidate as the `-last` ZIP.
4. Update reports, manifest and checksums.
5. Push the release branch. Do not tag, publish or submit until approval.
6. If `main` has no next-version work, fast-forward it to the same commit. If next-version work exists on `main`, cherry-pick only the 0.1.0 review fixes into `release/wporg-0.1.0`; do not merge unrelated work into the release branch.

## 3. After independent approval and owner freeze

1. Freeze `release/wporg-0.1.0`; no unrelated features or refactors.
2. Preserve its approved build and reports.
3. Start the next version on a new branch only when needed: `release/wporg-<next-version>`.
4. Do not create the next-version branch automatically during the current approval task.

## 4. Local candidate rotation

At any time `Rankkernel/release-output/` contains exactly:

```text
rankkernel-0.1.0.zip        # current/latest
rankkernel-0.1.0-last.zip   # previous candidate
manifest.txt
SHA256SUMS
BUILD_INFO.md
```

When a new build is created:

1. Verify the current ZIP and its hash.
2. Delete the old `-last` ZIP only after confirming its identity.
3. Rename current `rankkernel-0.1.0.zip` → `rankkernel-0.1.0-last.zip`.
4. Put the new verified build at `rankkernel-0.1.0.zip`.
5. Regenerate `manifest.txt` and `SHA256SUMS`; run `sha256sum -c SHA256SUMS`.
6. Never create `-last-last`, `final`, `final2`, `latest`, `new`, or per-build output folders.

Full path: `/home/web-dev-3/Documents/my personal plugin/Rankkernel/`.

## 5. Candidate build checklist

1. Confirm branch and HEAD.
2. Confirm working tree clean.
3. Build ZIP deterministically from the exact commit and `.distignore`.
4. Verify 239 packaged files, one top-level `rankkernel/`, plugin header/readme version.
5. Compare every packaged file to `git show <commit>:<path>`.
6. Generate a 239-row manifest and SHA-256.
7. Run PHPUnit, focused tests, PHPCS, PHPStan, Psalm Security, JS tests/syntax, Composer validation/audit, Plugin Check and runtime smoke tests on the exact ZIP.
8. Rotate local candidates.
9. Update reports and evidence branch.
10. Obtain independent Server audit before any WordPress.org upload.

## 6. GitHub release assets

A draft release may hold only the exact verified candidate. Before replacing assets:

1. Show the old asset names, sizes and SHA-256.
2. Get explicit owner approval for the exact deletion list.
3. Upload the new ZIP and corrected `SHA256SUMS`.
4. Retarget the draft to the final source commit and update its body.
5. Verify the downloaded asset hash matches the report.
6. Do not publish the draft until the independent audit and owner approval are complete.

Never leave stale ZIPs or a checksum file that references a missing/mismatched manifest.

## 7. Evidence location

- Release branch: `docs/audit-final/README.md` points to the current evidence.
- Evidence branch: `audit/final-fc5c641` → `audit-final/`.
- Server's independent audit reports on `audit/wporg-0.1.0-final-delivery/reports/` are historical evidence and must remain available.

## 8. Branch hygiene

- Delete a short-lived branch immediately after its PR merges or closes.
- Enable GitHub “Automatically delete head branches”.
- Keep at most one audit branch per submission cycle.
- Delete old release/audit branches only after their unique commits and evidence are preserved.
