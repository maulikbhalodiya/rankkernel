# RankKernel 0.1.0 — Final Source Identity

Date: 2026-11-06  
Branch: `release/wporg-0.1.0`  
Production package source commit: `5e404efd7c1c7155ef8b65d1f1cf7c8e15a09b05`

## Source and package

| Field | Value |
|---|---|
| Branch | `release/wporg-0.1.0` |
| Production source commit | `5e404efd7c1c7155ef8b65d1f1cf7c8e15a09b05` |
| Commit subject | `Fix password-post noindex and single-site uninstall scope` |
| Final ZIP | `/home/web-dev-3/Documents/my personal plugin/Rankkernel/release-output/rankkernel-0.1.0.zip` |
| ZIP size | 937,797 bytes |
| ZIP files | 240 |
| ZIP SHA-256 | `45ffccd66e40ab4715a8acdf48c04b291b82235b60f90a36963f0a279d5ed4ed` |
| Manifest | `rankkernel-0.1.0-manifest.txt` |
| Manifest SHA-256 | `918cb4b58b18c8253e529f3c818215c97e2bf2f7e77a1263f190a98a5c59a354` |
| Top-level directory | `rankkernel/` only |

## Superseded package

The previously submitted ZIP is preserved unchanged:

- File: `rankkernel-0.1.0.zip`
- Size: 902,973 bytes
- SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`
- Source: `fb386a419d6b32394cc342cf740f8e41a75389bb`

It must not be uploaded for this remediation. The new candidate supersedes it because distributed production files changed.

Previous build kept as `rankkernel-0.1.0-last.zip` (904,565 bytes, source `e310308`).

## Packaging verification

- Built with `build_release_zip.py` from the exact commit.
- `.distignore` applied; `reports/` is excluded from the package (audit evidence only).
- 240 files: 181 PHP, 24 JS, 20 CSS, 3 JSON, 5 TXT, 1 MD, 3 WOFF2, 1 XSL, 1 POT (239 code+assets plus `languages/rankernel.pot`).
- No development, test, CI, docs, vendor, report or build files.
- `unzip -t` clean.
- The exact ZIP was installed on WordPress 7.1.4 and 6.5.14 and activated without a fatal error; `debug.log` was empty.
- Plugin Check on the exact ZIP: 0 errors / 223 warnings on both versions.

## Branch tip note

The release branch tip after the evidence-report commit contains this file and the other `reports/wporg-final-remediation/` deliverables. Those paths are excluded by `.distignore`; the production package source remains `5e404ef`.
