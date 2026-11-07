# RankKernel 0.1.0 — Final Release Decision

Date: 2026-11-06  
Branch: `release/wporg-0.1.0`  
Production source commit: `5e404efd7c1c7155ef8b65d1f1cf7c8e15a09b05`

## Decision

**READY FOR INDEPENDENT SERVER AUDIT.**

The production audit and the November 6 remediation are complete for the shipped source. All confirmed release-blocking findings are fixed. The exact candidate is available for Server OpenCode's independent ZIP audit.

This is **not** an approval, not a WordPress.org submission, and not authorization to publish or create a release.

## Package identity

| Field | Value |
|---|---|
| ZIP | `rankkernel-0.1.0.zip` |
| Size | 937,797 bytes |
| Files | 240 (239 code+assets plus `languages/rankkernel.pot`) |
| SHA-256 | `45ffccd66e40ab4715a8acdf48c04b291b82235b60f90a36963f0a279d5ed4ed` |
| Manifest SHA-256 | `918cb4b58b18c8253e529f3c818215c97e2bf2f7e77a1263f190a98a5c59a354` |
| Top level | `rankkernel/` only |

The previously submitted/approved ZIP `4edb5beb…` is preserved and superseded for this remediation. It must not be uploaded instead of the candidate.

## Acceptance gates

| Gate | Status | Evidence |
|---|---|---|
| Historical WordPress.org issues accounted for | PASS | Complete issue register; A–N compliance matrix |
| November 6 findings fixed or defended | PASS | F-01 fixed; nine examples fixed; F-10 password-post noindex fixed; F-11 single-site uninstall fixed; no open blocker |
| All current input occurrences accounted for | PASS | 236 expressions; 0 UNREVIEWED/BLOCKED |
| Equivalent input occurrences searched | PASS | 16 shipped input files; full inventory |
| Nonce/capability operations reviewed | PASS | 95 controls inventoried |
| Output contexts reviewed | PASS | 1,244 statements; 0 UNREVIEWED |
| Filesystem/upload paths reviewed | PASS | 41 operations inventoried |
| Callbacks/returned HTML reviewed | PASS | Shortcode/block/filter/schema callbacks |
| WordPress.org guidelines reviewed | PASS | A–N matrix; name/slug, notices, enqueue, external services, paths |
| Regression tests | PASS | PHPUnit 2400 tests / 9216 assertions |
| Plugin Check classified | PASS | 0 errors / 223 warnings on WP 7.1.4 and 6.5.14 |
| No unresolved BLOCKED/UNREVIEWED | PASS | 0 in both inventories |
| No new production regression | PASS | PHPCS exit 0, PHPStan L6 clean, JS 352/0, PHPUnit 2400/9216 |
| Distribution contents correct | PASS | 240 files, `.distignore` applied, no dev/test/docs/reports |
| ZIP manifest/SHA-256 verified | PASS | `45ffccd6…` / `918cb4b5…` |

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
