# RankKernel 0.1.0 — Final Source Identity

Date: 2026-11-06  
Branch: `release/wporg-0.1.0`  
Production package source commit: `96008d2cce1c4c76e628cb35a08352ede8a7e6e9`

## Source and package

| Field | Value |
|---|---|
| Branch | `release/wporg-0.1.0` |
| Production source commit | `96008d2cce1c4c76e628cb35a08352ede8a7e6e9` |
| Commit subject | `Harden November 6 input and editor authorization paths` |
| Final ZIP | `/home/web-dev-3/Documents/my personal plugin/Rankkernel/release-output/rankkernel-0.1.0-candidate-96008d2.zip` |
| ZIP size | 902,496 bytes |
| ZIP files | 239 |
| ZIP SHA-256 | `5e8ecd1ae4b26ee7a80cd8a94c8b367d183ba16436932c16adba55ff4ba538f0` |
| Manifest | `rankkernel-0.1.0-candidate-96008d2-manifest.txt` |
| Manifest SHA-256 | `938017bff8b60f7bdb96074e42075bc5e0adf004183bd39714bd54976b20fd35` |
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

The release branch tip after the evidence-report commit contains this file and the other `reports/wporg-final-remediation/` deliverables. Those paths are excluded by `.distignore`; the production package source remains `96008d2`.
