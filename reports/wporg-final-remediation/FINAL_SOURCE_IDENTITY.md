# RankKernel 0.1.0 — Final Source Identity

Date: 2026-11-06  
Branch: `release/wporg-0.1.0`  
Production package source commit: `9e2cecc3ec49241aeaf40e718fc55f1262c4e6d4`

## Source and package

| Field | Value |
|---|---|
| Branch | `release/wporg-0.1.0` |
| Production source commit | `9e2cecc3ec49241aeaf40e718fc55f1262c4e6d4` |
| Commit subject | `Harden November 6 input and editor authorization paths` |
| Final ZIP | `/home/web-dev-3/Documents/my personal plugin/Rankkernel/release-output/rankkernel-0.1.0-candidate-9e2cecc.zip` |
| ZIP size | 902,481 bytes |
| ZIP files | 239 |
| ZIP SHA-256 | `257e546d131fa10eecd444a47802737157b8984018a5c26424e1605b4fc5cb34` |
| Manifest | `rankkernel-0.1.0-candidate-9e2cecc-manifest.txt` |
| Manifest SHA-256 | `3d56ec6c9e0e72d16be427309d2c86d2b0cf593d8f5acdc3d77a58cc32ff4634` |
| Top-level directory | `rankkernel/` only |

## Superseded package

The previously submitted ZIP is preserved unchanged:

- File: `rankkernel-0.1.0.zip`
- Size: 902,973 bytes
- SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`
- Source: `fb386a419d6b32394cc342cf740f8e41a75389bb`

It must not be uploaded for this remediation. The new candidate supersedes it because distributed production files changed.

## Packaging verification

- Built with `build_release_zip.py` from the exact commit.
- `.distignore` applied; `reports/` is excluded from the package (audit evidence only).
- 239 files: 181 PHP, 24 JS, 20 CSS, 3 JSON, 5 TXT, 1 MD, 3 WOFF2.
- No development, test, CI, docs, vendor, report or build files.
- `unzip -t` clean.
- The exact ZIP was installed on WordPress 7.1.4 and 6.5.14 and activated without a fatal error; `debug.log` was empty.
- Plugin Check on the exact ZIP: 0 errors / 223 warnings on both versions.

## Branch tip note

The release branch tip after the evidence-report commit contains this file and the other `reports/wporg-final-remediation/` deliverables. Those paths are excluded by `.distignore`; the production package source remains `9e2cecc`.
