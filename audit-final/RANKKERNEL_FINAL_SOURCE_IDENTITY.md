# RankKernel 0.1.0 — Final Source Identity

Date: 2026-11-05

## Branch identity

| Ref | SHA | Status |
|---|---|---|
| `origin/main` | `eba5018e66bb9a3236594535b748cc04fdb7779b` | Identical to release branch |
| `origin/release/wporg-0.1.0` | `eba5018e66bb9a3236594535b748cc04fdb7779b` | Canonical release branch |

## Two identities, one package

| Identity | SHA | Meaning |
|---|---|---|
| Approved production source | `fb386a419d6b32394cc342cf740f8e41a75389bb` | Source of the exact independently approved ZIP |
| Final branch tip | `eba5018e66bb9a3236594535b748cc04fdb7779b` | Adds development-only workflow, prompt and test changes |

The delta `fb386a4..eba5018` is exactly:

- `.audit-prompts/code-quality-conventions.md`
- `.audit-prompts/error-handling-resilience.md`
- `.audit-prompts/performance-efficiency.md`
- `.audit-prompts/security-privacy.md`
- `.github/workflows/ai-audit.yml`
- `.github/workflows/ai-review.yml`
- `.github/workflows/ci.yml`
- `.opencode-reviewer.yml`
- `tests/Unit/PluginTest.php`

Every path is excluded by `.distignore`. No distributed PHP, JS, CSS, readme, license or block metadata changed. The exact approved ZIP was preserved and not rebuilt.

## Package identity

| Field | Value |
|---|---|
| ZIP | `rankkernel-0.1.0.zip` |
| Size | 902,973 bytes |
| Files | 239 |
| Top level | one `rankkernel/` |
| SHA-256 | `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a` |
| Manifest | `manifest.txt` / `RANKKERNEL_FINAL_PACKAGE_MANIFEST.csv` |
| Manifest rows | 239 plus header |
| Manifest SHA-256 | `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000` |
| Previous candidate | `rankkernel-0.1.0-last.zip` — 902,022 bytes — `87d94a56…` |

## Verification

- `unzip -t` clean.
- 239/239 packaged files match both `fb386a4` and `eba5018`.
- No symlinks, traversal, extra files or missing files.
- Plugin version/readme Stable tag `0.1.0`; Requires WP 6.5, PHP 8.2, Tested up to 7.1, GPLv2+.
- Plugin Check on the exact ZIP: 0 errors / 223 warnings on WP 7.1.4 and 6.5.14.
- Exact-ZIP runtime smoke passes on both WordPress versions.
- The exact ZIP is independently approved for the production source; the dev-only branch tip does not require a new package audit.
