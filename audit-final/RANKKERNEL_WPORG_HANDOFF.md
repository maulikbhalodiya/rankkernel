# RankKernel WordPress.org — Handoff

Date: 2026-11-05  
Active branch: `release/wporg-0.1.0` at `eba5018e66bb9a3236594535b748cc04fdb7779b`  
Production source for the approved ZIP: `fb386a419d6b32394cc342cf740f8e41a75389bb`  
Version: `0.1.0`  
Final ZIP: `rankkernel-0.1.0.zip` — 902,973 bytes, 239 files (unchanged; dev-only changes)  
ZIP SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`  
Manifest: `manifest.txt` — 239 rows, SHA-256 `1298873705f453e1bcf8430b0f62bdcf5bf2ed0929f0f98dc993be2e1b7de000`

## Final development round

- Fixed RK-F-15: `PluginTest` now derives the basename from `plugin_basename( RANKKERNEL_FILE )`; alternate-directory run passes 18/18.
- Fixed RK-F-13: removed Psalm `continue-on-error`; added a mandatory SARIF existence/structure gate while preserving `if: always()` SARIF upload.
- Upgraded `.opencode-reviewer.yml` and all four `.audit-prompts/` with occurrence-level source-to-sink evidence requirements.
- Upgraded `ai-review.yml` project context and `ai-audit.yml` to a deterministic weekly 4-category x 2-directory matrix with working manual selection.
- Published fresh 437-row input and 1,244-row output occurrence inventories with 0 UNREVIEWED and 0 BLOCKED.
- Exact CI run 42003340011 at `eba5018` passes all jobs. Development-only changes do not alter the approved ZIP.

## Completed in this round

- Consolidated all 0.1.0 approval code on `release/wporg-0.1.0`; the old `release/wporg-0.1.0-final` was preserved until this branch was verified at `2afb118`, then removed from the active workflow.
- Closed Server findings:
  - RK-F-02 `MetaPayload` pillar/cornerstone strict booleans.
  - RK-F-03 Breadcrumbs block boolean attribute validation.
  - RK-F-04 raw `ModuleRegistry` exception message.
  - RK-F-05 network activation refusal before setup; single-site uninstall documented.
  - RK-F-06 strict `ImportController::release()` `run_id`.
  - RK-F-07 legacy schema shape retained and documented as safe.
- RK-F-08 Plugin Check false positives retained; zero errors.
- RK-F-09 classified matrices/remediation reports published on `audit/final-fc5c641` and pointed to by `docs/audit-final/README.md`.
- RK-F-12/RK-F-13 local Psalm Security taint analysis run: exit 0, no taint errors.
- RK-F-01 new exact candidate prepared; stale GitHub draft assets still await owner approval for replacement/deletion.

## Verification

- PHPUnit 2392 tests / 9197 assertions — OK.
- PHPCS exit 0; PHPStan L6 no errors; JS 352/0; Composer validate/audit clean.
- Psalm Security local: Psalm 5.26.1 taint analysis exit 0, no taint errors; CI run 42003340011 includes the hardened Psalm gate and passes.
- Plugin Check exact ZIP: 0 errors / 223 warnings on WP 7.1.4 and WP 6.5.14.
- Exact-ZIP smoke: 9 admin screens 200 on both WP versions; home/sitemap/robots/404 correct; plugin debug log clean.
- 239/239 packaged files match both `fb386a4` and `eba5018`; development-only files are excluded; 0 missing, 0 extra, 0 mismatches.

## Old branch state

- `release/wporg-0.1.0-final` was at `2afb118`, verified as an ancestor of `release/wporg-0.1.0`, then deleted locally and remotely. Its production commits are preserved in the active branch.
- `main` is at `eba5018`, identical to `release/wporg-0.1.0`.
- `release/wporg-0.1.0` previously pointed at `390ee9a`; it was fast-forwarded to `2afb118`, then received `fb386a4` and `eba5018`.

## GitHub draft release

Draft: `v0.1.0-rc-rel01`. Stale assets still present:

| Asset | Size | SHA-256 | Action |
|---|---:|---|---|
| `rankkernel-0.1.0-release-rel01.zip` | 898,875 | `ee5b82cc6adc4118de30af0edacb187c609bbdd4947a26236bcceeaac1cfe670` | Delete after owner approval |
| `rankkernel-0.1.0.zip` | 901,122 | `0d891a9fc91007a92f4e867dfaaf00acb688ad48a7533e1c5a9db6ecf315bb39` | Delete/replace after owner approval |
| `SHA256SUMS` | 169 | `d2221036f7153657c3818246fecf1cea519f53b794b688052b011ffb50e2b1d2` | Delete/replace after owner approval |

No draft asset was deleted, replaced, retargeted or published in this round. The new candidate is ready locally at `Rankkernel/release-output/rankkernel-0.1.0.zip`.

## Remaining gate

Independent Server OpenCode audit of `release/wporg-0.1.0` at `fb386a4` and ZIP `4edb5beb…` is required. The owner must approve the exact stale-asset deletion list before the draft can be corrected.

No WordPress.org upload, submission, tag, GitHub release publication or independent approval is claimed.
