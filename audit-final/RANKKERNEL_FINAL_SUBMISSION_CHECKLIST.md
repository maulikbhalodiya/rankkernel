# RankKernel 0.1.0 — Final Submission Checklist

Date: 2026-11-05  
Candidate: `rankkernel-0.1.0.zip`  
SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`

## Package

- [x] ZIP is the exact independently approved bytes; not rebuilt after dev-only changes.
- [x] Size 902,973 bytes; 239 files; one top-level `rankkernel/`.
- [x] `unzip -t` clean; no symlinks or path traversal.
- [x] 239/239 packaged files match production source `fb386a4` and final branch `eba5018`.
- [x] Plugin header/readme version `0.1.0`; Stable tag `0.1.0`; Tested up to `7.1`; Requires WP `6.5`; Requires PHP `8.2`; GPLv2+.
- [x] No tests, `.github`, `.audit-prompts`, docs, vendor, audit evidence, or dev files in the ZIP.
- [x] Plugin Check exact ZIP: 0 errors / 223 warnings on WP 7.1.4 and 6.5.14.
- [x] Runtime smoke exact ZIP: 9 admin screens 200, home/sitemap/robots/404 correct on both WP versions.
- [x] Manifest `manifest.txt` and canonical CSV `RANKKERNEL_FINAL_PACKAGE_MANIFEST.csv`, 239 rows, SHA-256 `129887…`.
- [x] `SHA256SUMS` verifies current, last and both manifest copies.
- [x] WordPress.org submission ZIP is produced from the approved production source; no new package audit required for dev-only branch changes.

## WordPress.org submission steps (not performed)

- [ ] Owner confirms the exact ZIP hash above.
- [ ] Owner uploads `rankkernel-0.1.0.zip` to WordPress.org.
- [ ] Owner submits the plugin for review.
- [ ] No GitHub draft publication or release tag until owner approval.

## GitHub draft release (not published, stale assets remain)

- [ ] Owner approves the exact stale-asset deletion list.
- [ ] Upload exact `rankkernel-0.1.0.zip`.
- [ ] Upload `manifest.txt` and corrected `SHA256SUMS`.
- [ ] Retarget draft to `eba5018` (or explain production source `fb386a4` if keeping production identity).
- [ ] Verify downloaded asset hash.
- [ ] Delete stale assets only after owner approval.
- [ ] Do not publish the draft without owner approval.

## Outstanding

1. WordPress.org upload/submission: **not performed**.
2. GitHub draft asset replacement: **prepared locally, not uploaded**.
3. No independent approval claimed for the dev-only workflow/test changes; the distributed ZIP is unchanged and was independently approved.
4. Full multisite network runtime not available; network activation refusal is unit-tested and fails before setup.
