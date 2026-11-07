# RankKernel 0.1.0 — Final Source Identity

Date: 2026-11-06  
Branch: `release/wporg-0.1.0`  
Production package source commit: `57dfc063d022d380d43947972c26f9bd976e5b56`

## Source and package

| Field | Value |
|---|---|
| Branch | `release/wporg-0.1.0` |
| Production source commit | `57dfc063d022d380d43947972c26f9bd976e5b56` |
| Commit subject | `Add languages/rankkernel.pot translation template` |
| Final ZIP | `/home/web-dev-3/Documents/my personal plugin/Rankkernel/release-output/rankkernel-0.1.0.zip` |
| ZIP size | 937,587 bytes |
| ZIP files | 240 |
| ZIP SHA-256 | `f6beba8f830994dc3ef952f7757d7ad079073d5b21787fe64c4265083732bedd` |
| Manifest | `rankkernel-0.1.0-manifest.txt` |
| Manifest SHA-256 | `b0057ec61e3e8b930625319e64ad998e7ff7d6dbfc35d635bd39b76fe68e3ba6` |
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
- 240 files: 181 PHP, 24 JS, 20 CSS, 3 JSON, 5 TXT, 1 MD, 3 WOFF2, 1 XSL, 1 POT (239 code+assets plus `languages/rankernel.pot`).
- No development, test, CI, docs, vendor, report or build files.
- `unzip -t` clean.
- The exact ZIP was installed on WordPress 7.1.4 and 6.5.14 and activated without a fatal error; `debug.log` was empty.
- Plugin Check on the exact ZIP: 0 errors / 223 warnings on both versions.

## Branch tip note

The release branch tip after the evidence-report commit contains this file and the other `reports/wporg-final-remediation/` deliverables. Those paths are excluded by `.distignore`; the production package source remains `57dfc06`.
