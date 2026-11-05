# RankKernel 0.1.0 — Repository and Release Cleanup Proposal

**Status: PROPOSAL ONLY. No file, branch, tag, release asset, or WordPress.org state was deleted, modified, or published.**

- Audit target: `2afb1185d919f03ad4ed74444466db3b44db0786`
- Exact candidate ZIP: `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252`, 902,022 bytes, 239 files
- Repository: `/tmp/rc-audit/repo` (origin `maulikbhalodiya/rankkernel`)
- Cleanup scope: tracked reports/artifacts in Git, plus GitHub release assets attached to the draft release. Local `/tmp` files are outside Git and are not proposed for deletion here.

## 1. Bottom line

- **No tracked repository report is safe to delete without losing unique evidence.** The apparent duplicates are phase-specific snapshots, source material for a consolidated document, or the only in-repo copies of prior audit evidence.
- **The three draft-release assets are stale and should be replaced or deleted only after owner approval.** They do not match `2afb118`; publishing them would ship the wrong bytes.
- **Recommended cleanup order:**
  1. Build/upload the exact candidate ZIP as the release asset.
  2. Retarget the draft release to `2afb118` and update its body.
  3. Replace `SHA256SUMS` with a checksum for the exact ZIP (and a manifest, if the release will publish one).
  4. Only then delete the two stale ZIPs and the stale `SHA256SUMS`.
  5. Keep all tracked audit reports; consolidate overlapping state/handoff documents only with owner approval and without deleting unique content.

## 2. GitHub release assets — exact stale assets

Draft release: `v0.1.0-rc-rel01` (ID `429798579`), target commitish `release-candidate/integrate-0.1.0`, body says source `ab1b62ad027ffc0084b9b1aa5b1eef23993ad2d2`.

| Exact asset path | Size | SHA-256 | Relation to exact candidate | Git tracking | Unique evidence | Recommendation |
|---|---:|---|---|---|---|---|
| `v0.1.0-rc-rel01/rankkernel-0.1.0-release-rel01.zip` | 898,875 | `ee5b82cc6adc4118de30af0edacb187c609bbdd4947a26236bcceeaac1cfe670` | 239 files, one `rankkernel/`; **13 files differ** from `2afb118`; corresponds to older source `ab1b62a` | N/A — GitHub release asset | Historical release-candidate artifact; source commit and exact candidate supersede it | **DELETE AFTER OWNER APPROVAL** (after exact replacement is uploaded) |
| `v0.1.0-rc-rel01/rankkernel-0.1.0.zip` | 901,122 | `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39` | 239 files, one `rankkernel/`; **6 files differ** from `2afb118` | N/A — GitHub release asset | Old candidate; not the exact package | **DELETE AFTER OWNER APPROVAL** (after exact replacement is uploaded) |
| `v0.1.0-rc-rel01/SHA256SUMS` | 169 | `d2221036f7153657c3818246fecf1cea519f53b794b688052b011ffb50e2b1d2` | Lists `0d891a… rankkernel-0.1.0.zip` and `3c20b8… MANIFEST.sha256`; `MANIFEST.sha256` is **not present** among the assets | N/A — GitHub release asset | No unique code; checksum file is inconsistent | **DELETE AFTER OWNER APPROVAL**; replace with checksum for exact ZIP |

### Files differing in the 901,122-byte `rankkernel-0.1.0.zip`

- `src/Admin/InstantIndexingPage.php`
- `src/Modules/InstantIndexing/LogFilters.php`
- `src/Modules/Metadata/MetaPayload.php`
- `src/Rest/AnalysisController.php`
- `src/Rest/ImportController.php`
- `src/Support/SupportRequest.php`

These are exactly the files touched by the five input fixes. Publishing this asset would omit those fixes.

### Files differing in the 898,875-byte `rankkernel-0.1.0-release-rel01.zip`

- `src/Admin/InstantIndexingPage.php`
- `src/Admin/MetadataBox.php`
- `src/Admin/SchemaMetabox.php`
- `src/Modules/Breadcrumbs/blocks/BreadcrumbsBlock.php`
- `src/Modules/Breadcrumbs/functions.php`
- `src/Modules/InstantIndexing/LogFilters.php`
- `src/Modules/Metadata/MetaPayload.php`
- `src/Modules/Sitemaps/Provider/AuthorsProvider.php`
- `src/Modules/Sitemaps/Router.php`
- `src/Modules/Sitemaps/XslStylesheet.php`
- `src/Rest/AnalysisController.php`
- `src/Rest/ImportController.php`
- `src/Support/SupportRequest.php`

### Required replacement

- Upload the exact candidate ZIP (`87d94a…`, 902,022 bytes) as `rankkernel-0.1.0.zip`.
- Retarget the draft release to `2afb1185d919f03ad4ed74444466db3b44db0786` and update the body.
- Generate a new `SHA256SUMS` containing at least:
  - `87d94a568cf3a404f4b84648277e20fc81ed64de8fce5c6064343c9a3c0d8252  rankkernel-0.1.0.zip`
- If a manifest asset is retained, upload the actual manifest and include its checksum; do not reference a missing `MANIFEST.sha256`.
- Do not publish the draft until the asset hash and size match the exact candidate.

## 3. Tracked repository reports and artifacts

All paths below are tracked in Git. `KEEP` means preserve in the repository; it does not mean ship in the WordPress.org ZIP.

| Exact path | Branch / ref | Purpose | Replacement / supersession | Unique evidence | Recommendation |
|---|---|---|---|---|---|
| `RANKKERNEL_WPORG_AUDIT_FINDINGS.md` | `origin/main` (and historical branches) | Working audit ledger, RK-001…; says do-not-ship | Later independent audits supersede its verdict, but not all historical detail | Only in-repo working audit ledger; `.distignore:24` excludes it from the ZIP | **KEEP** |
| `docs/reports/browser-qa-gh12.md` | `origin/main` | GH-12 manual browser QA table | None in repo | Live LocalWP QA record | **KEEP** |
| `docs/reports/redirects-validation-hardening.md` | `origin/main` | GH-12 redirect validation/chain hardening handoff | None in repo | Equivalence/loop/chain semantics | **KEEP** |
| `docs/reports/wpcs-compliance-gh13.md` | `origin/main` | GH-13 WPCS compliance report | None in repo | Violation counts and exception rationale | **KEEP** |
| `reports/RANKKERNEL_WPORG_INDEPENDENT_AUDIT.md` | `origin/audit/wporg-remediation-2026-11-delivery` | Independent audit of `b0ac1c8` / PR #421 | Superseded for the final verdict by the new final audit; still historical evidence | Only in-repo copy of prior independent audit | **KEEP** (historical) |
| `reports/RANKKERNEL_WPORG_HANDOFF.md` | `origin/audit/wporg-remediation-2026-11-delivery` | Remediation handoff and PR decision table | Superseded for final delivery; historical | Only in-repo handoff | **KEEP** (historical) |
| `reports/RANKKERNEL_INPUT_OCCURRENCE_MATRIX.csv` | `origin/audit/wporg-remediation-2026-11-delivery` | 409-row input inventory; all rows `not reviewed` | No replacement; new audit has its own matrix | Generation source for prior handoff | **KEEP** (raw inventory; not a proof of review) |
| `docs/STATE.md`, `docs/STATE-CURRENT.md`, `docs/STATE-gh27.md`, `docs/RANKKERNEL-CURRENT-STATE.md` | `origin/main` | Overlapping session-state/resume files | Each describes a different HEAD/checkpoint; no single file fully subsumes the others | Distinct phase checkpoints | **KEEP**; optionally consolidate with owner-written merge |
| `docs/reviewer-handoff/reviewer-handoff.md` + `reviewer-handoff-breadcrumbs.md` + `reviewer-handoff-redirects-404.md` + `reviewer-handoff-gh12-verification.md` | `origin/main` | GH-11 base handoff plus three topical follow-ups | Base plus topical files; no single consolidated replacement | Distinct review snapshots | **KEEP**; optionally add an index |
| `docs/competitor-analysis/feature-matrix.md`, `feature-parity-matrix.md`, `feature-parity-master.md`, `gap-analysis.md`, `rankmath-audit.md`, `yoast-audit.md`, `README.md` | `origin/main` | Competitor parity research | `feature-parity-master.md` consolidates, but cites the raw matrices | Raw matrices contain rows not carried into the master verbatim | **KEEP**; do not delete sources |
| `docs/research/raw/*` (16 files), `docs/research/*-gate.md` (4 files) | `origin/main` | Raw RankMath/Yoast/audit research and gates | Master parity document | Clean-room behavioral evidence base | **KEEP** |
| `docs/designcode/*` (11 files) | `origin/main` | Design reference mockups and prompts | Design phase complete; no replacement | Visual design provenance | **KEEP** |
| `docs/architecture/*` (13 files) | `origin/main` | Current architecture documentation | N/A | Active development documentation | **KEEP** |
| `.audit-prompts/*` (4 files), `tools/guard-large-deletions.sh` | `origin/main` | Active audit conventions and PR #183 deletion guard | N/A | Active tooling | **KEEP** |

No tracked ZIP, `SHA256SUMS`, `PLUGIN_CHECK*.json`, `MANIFEST.sha256`, or release-evidence directory was found in the repository or fetched branches. Those artifacts live outside Git and are not repository cleanup candidates.

## 4. Consolidation candidates — no deletion proposed

These are optional documentation improvements, not cleanup deletions:

- **State family:** merge `docs/STATE.md`, `docs/STATE-CURRENT.md`, `docs/STATE-gh27.md`, and `docs/RANKKERNEL-CURRENT-STATE.md` into one owner-approved current-state document, preserving the historical files or archiving them together.
- **Reviewer handoffs:** add a single index that points to the base GH-11 handoff and the three topical handoffs; do not delete the originals.
- **Competitor research:** keep `feature-parity-master.md` as the entry point and retain the raw matrices and research files as provenance.
- **Prior audit reports:** keep the `b0ac1c8` audit and handoff as historical evidence; the new final audit is the current verdict for `2afb118`.

## 5. Exact deletion list awaiting owner approval

Only the following assets are proposed for deletion, and only after the exact candidate and corrected checksum are in place:

1. GitHub draft release `v0.1.0-rc-rel01` asset: `rankkernel-0.1.0-release-rel01.zip` (`ee5b82…`, 898,875 bytes).
2. GitHub draft release `v0.1.0-rc-rel01` asset: `rankkernel-0.1.0.zip` (`0d891a…`, 901,122 bytes).
3. GitHub draft release `v0.1.0-rc-rel01` asset: `SHA256SUMS` (`d22210…`, 169 bytes).

**No tracked repository file is on the deletion list.** No production source, test, docs, branch, tag, or WordPress.org state is proposed for deletion.

## 6. Approval record

- Owner approval required before any asset deletion or release edit.
- The exact candidate must remain available after cleanup; it is the only asset that matches `2afb118`.
- This proposal does not authorize publishing the draft release or submitting to WordPress.org.
