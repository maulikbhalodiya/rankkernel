# RankKernel 0.1.0 — Repository and Release Cleanup Proposal

**Status:** Proposal only. Nothing has been deleted, replaced, or unpublished.
**Target:** `maulikbhalodiya/rankkernel`, exact candidate `fb386a419d6b32394cc342cf740f8e41a75389bb`, ZIP SHA-256 `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`.

## 1. Release assets

The draft release `v0.1.0-rc-rel01` currently contains stale assets. The exact candidate ZIP is not uploaded.

| Exact asset | Current identity | Purpose | Why obsolete | Replacement | Git tracked | Unique evidence | Recommendation |
|---|---|---|---|---|---|---|---|
| `rankkernel-0.1.0.zip` on draft `v0.1.0-rc-rel01` | 901,122 bytes; SHA-256 `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39` | Draft release download | Six shipped files differ from `fb386a4`; not the exact candidate | Exact ZIP 902,973 bytes / `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a` | No (GitHub release asset) | No; superseded by exact candidate | **DELETE AFTER OWNER APPROVAL**, only after exact replacement is uploaded and verified |
| `rankkernel-0.1.0-release-rel01.zip` on draft `v0.1.0-rc-rel01` | 898,875 bytes; SHA-256 `ee5b82cc6adc4118de30af0edacb187c609bbdd4947a26236bcceeaac1cfe670` | Earlier release-candidate archive | Older build; does not match `fb386a4` | Exact candidate ZIP | No | No; superseded | **DELETE AFTER OWNER APPROVAL**, after exact replacement |
| `SHA256SUMS` on draft `v0.1.0-rc-rel01` | 169 bytes; SHA-256 `d2221036f7153657c3818246fecf1cea519f53b794b688052b011ffb50e2b1d2` | Draft checksum list | Lists the stale ZIP; manifest entry mismatch | Regenerate from exact candidate and canonical manifest | No | No | **REPLACE**, then delete old asset after owner approval |

**Required order:** upload exact `4edb5be…` ZIP → upload/regenerate `SHA256SUMS` → retarget draft to `fb386a4` → verify download hashes → only then delete stale assets with owner approval. Do not publish the draft before this.

Additional verified release-state facts:

- Draft `v0.1.0-rc-rel01` targets the deleted branch `release-candidate/integrate-0.1.0`.
- No tags exist; the draft is untagged.
- The exact candidate `4edb5be…` is not uploaded.
- The draft `SHA256SUMS` references a manifest (`MANIFEST.sha256`) that is not uploaded; it also vouches the stale `0d891a9f…` ZIP.
- `origin/main` and `origin/release/wporg-0.1.0` both resolve to `fb386a4`; `2afb118` is an ancestor.
- `release/wporg-0.1.0-final` and `release-candidate/*` are absent from remote refs; no unique production work was lost in consolidation.
- Audit evidence branches remain: `audit/final-fc5c641`, `audit/wporg-0.1.0-final-delivery`, `audit/wporg-remediation-2026-11-delivery`, plus older audit report branches.

## 2. Repository reports and evidence

No tracked repository report is proposed for deletion. The audit evidence is unique and should remain available.

| Exact path / ref | Purpose | Why it might look obsolete | Replacement | Git tracked | Unique evidence | Recommendation |
|---|---|---|---|---|---|---|
| `audit/final-fc5c641` branch, `audit-final/RANKKERNEL_PRODUCTION_FILE_MANIFEST.txt` | 239-row `hash  path` manifest for the exact ZIP | Different format from the expected CSV manifest | Canonical CSV manifest `129887…` | Yes, on audit branch | Yes — text-format manifest | **KEEP** |
| `audit/final-fc5c641` branch, `audit-final/SHA256SUMS` | Exact ZIP and manifest checksums | May be superseded by final delivery branch | New reports-only branch | Yes, on audit branch | Yes | **KEEP** |
| `audit/final-fc5c641` branch, `audit-final/PLUGIN_CHECK_FINAL.json` | Plugin Check evidence | Warning set now independently re-verified | New evidence logs | Yes, on audit branch | Yes | **KEEP** |
| `audit/final-fc5c641` branch, `audit-final/` remediation/test/security reports | Remediation and test evidence | Some report hashes use the CSV manifest format | New final report | Yes, on audit branch | Yes — historical evidence | **KEEP** |
| `audit/wporg-0.1.0-final-delivery` branch | Prior reports-only delivery for `2afb118` | Prior target, but still valid history | New final reports branch | Yes | Yes — prior audit evidence | **KEEP** |
| `audit/wporg-remediation-2026-11-delivery` branch | Prior remediation handoff and 409-row matrix | Prior target, unclassified matrix | New final report | Yes | Yes — sole shared 409-row matrix | **KEEP** |
| `RANKKERNEL_WPORG_AUDIT_FINDINGS.md` in repo | Working audit ledger | Older findings | New final report | Yes | Yes | **KEEP**; already excluded from ship ZIP by `.distignore` |
| `docs/audit-final/README.md` | Pointer to audit evidence branch | Not shipped | New report | Yes | Yes | **KEEP** |
| `docs/reports/`, `docs/reviewer-handoff/`, `docs/STATE*`, `docs/research/`, `docs/competitor-analysis/` | Development and historical evidence | Some overlap, but each has unique checkpoints | None complete | Yes | Yes | **KEEP**; consolidation only with owner-written merge, not deletion |
| Local `/tmp/rk-final-audit/**`, `/tmp/rc-audit/**`, `/tmp/rk-audit/**` | Audit work products | Temporary | None | No | Varies | **KEEP** for this audit; not repository cleanup |

## 3. Branches

- `release/wporg-0.1.0` and `main` are at `fb386a4`; do not rewrite, force-push, or delete.
- Previous `release/wporg-0.1.0-final` is absent from remote refs; its commit `2afb118` is preserved in history. Do not recreate or delete anything to “restore” it.
- Old release-candidate branches may be absent; their useful commits must remain reachable from `fb386a4` or preserved by refs. Verify before any branch cleanup.
- Audit evidence branches must remain until the owner has archived the final report.

## 4. Test-only cleanup

`tests/Unit/PluginTest.php` hardcodes `rankkernel/rankkernel.php` and fails when the checkout directory basename is not `rankkernel`. This is not a production file and not a release blocker. Recommended correction: use `plugin_basename( RANKKERNEL_FILE )` or a fixture directory named `rankkernel`.

## 5. Explicit non-actions

- No production source file is proposed for deletion.
- No release asset has been deleted or replaced.
- No branch, tag, PR, or WordPress.org state has been changed.
- No GitHub release has been published.
- No report has been merged into `main` or `release/wporg-0.1.0`.

**Approval needed before any deletion:** confirm the exact asset(s) and branch paths above, confirm the exact replacement ZIP hash `4edb5be…` has been uploaded and verified, then approve deletion of the stale draft assets only.
