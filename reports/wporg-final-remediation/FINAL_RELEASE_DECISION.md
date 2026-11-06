# RankKernel 0.1.0 — Final Release Decision

Date: 2026-11-06  
Branch: `release/wporg-0.1.0`  
Production source commit: `9e2cecc3ec49241aeaf40e718fc55f1262c4e6d4`

## Decision

**READY FOR INDEPENDENT SERVER AUDIT.**

The production audit and the November 6 remediation are complete for the shipped source. All confirmed release-blocking findings are fixed. The exact candidate is available for Server OpenCode's independent ZIP audit.

This is **not** an approval, not a WordPress.org submission, and not authorization to publish or create a release.

## Package identity

| Field | Value |
|---|---|
| ZIP | `rankkernel-0.1.0-candidate-9e2cecc.zip` |
| Size | 902,481 bytes |
| Files | 239 |
| SHA-256 | `257e546d131fa10eecd444a47802737157b8984018a5c26424e1605b4fc5cb34` |
| Manifest SHA-256 | `3d56ec6c9e0e72d16be427309d2c86d2b0cf593d8f5acdc3d77a58cc32ff4634` |
| Top level | `rankkernel/` only |

The previously submitted/approved ZIP `4edb5beb…` is preserved and superseded for this remediation. It must not be uploaded instead of the candidate.

## Acceptance gates

| Gate | Status | Evidence |
|---|---|---|
| Historical WordPress.org issues accounted for | PASS | Complete issue register; A–N compliance matrix |
| November 6 findings fixed or defended | PASS | F-01 fixed; nine examples fixed; no open blocker |
| All current input occurrences accounted for | PASS | 236 expressions; 0 UNREVIEWED/BLOCKED |
| Equivalent input occurrences searched | PASS | 16 shipped input files; full inventory |
| Nonce/capability operations reviewed | PASS | 95 controls inventoried |
| Output contexts reviewed | PASS | 1,244 statements; 0 UNREVIEWED |
| Filesystem/upload paths reviewed | PASS | 41 operations inventoried |
| Callbacks/returned HTML reviewed | PASS | Shortcode/block/filter/schema callbacks |
| WordPress.org guidelines reviewed | PASS | A–N matrix; name/slug, notices, enqueue, external services, paths |
| Regression tests | PASS | PHPUnit 2394 tests / 9201 assertions |
| Plugin Check classified | PASS | 0 errors / 223 warnings on WP 7.1.4 and 6.5.14 |
| No unresolved BLOCKED/UNREVIEWED | PASS | 0 in both inventories |
| No new production regression | PASS | PHPCS clean, PHPStan L6 clean, JS 352/0 |
| Distribution contents correct | PASS | 239 files, `.distignore` applied, no dev/test/docs/reports |
| ZIP manifest/SHA-256 verified | PASS | `257e546d…` / `3d56ec6c…` |

## Owner decisions still required

1. **Trademark/ownership:** The owner must confirm legal ownership/clearance for the “RankKernel” name and slug. The code cannot prove this.
2. **Independent audit:** Server OpenCode must audit the exact candidate ZIP before any upload.
3. **Upload/publication:** No WordPress.org submission, GitHub release, tag, asset replacement or publication has been performed. Those actions require the owner's explicit authorization after the independent audit.

## Known limitations disclosed

- The exact WordPress.org scanner dump for the 37/48/46 incidences is unavailable; the audit reproduces the examples and inventories every current superglobal occurrence.
- Psalm taint analysis is not installed locally; no local Psalm run is claimed for this commit.
- Real Apache/LiteSpeed behavior was not re-run this pass; earlier Apache 2.4.58 evidence exists.
- Browser-level admin UI rendering beyond WP-CLI activation/init and Plugin Check was not re-run this pass.
- No independent Server approval is claimed.
