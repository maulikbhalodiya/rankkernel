# RankKernel 0.1.0 — Distribution ZIP File Review

Date: 2026-11-05  
Reviewed ZIP: `/home/web-dev-3/Documents/my personal plugin/Rankkernel/release-output/rankkernel-0.1.0.zip`  
SHA-256: `4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a`  
Size: **902,973 bytes**  
Files: **239**  
Status: **approved ZIP preserved unchanged; no new candidate created**

## Result

| Recommendation | Count |
|---|---:|
| KEEP | **239** |
| EXCLUDE | **0** |
| UNREVIEWED | **0** |

Every packaged file is required for runtime, WordPress.org metadata, licensing, translations or block metadata. No test, GitHub workflow, AI audit prompt, reviewer configuration, development report, static-analysis configuration, composer file, vendor tree or build-only file is present.

## Package composition

| Category | Count | Role |
|---|---:|---|
| Runtime PHP (bootstrap, uninstall, classes, functions, template tags) | 162 | Required |
| Runtime assets (admin/editor JS and CSS, fonts) | 48 | Required |
| Admin view templates | 19 | Required |
| Block registration metadata (`block.json`) | 3 | Required |
| License and font attribution | 4 | Required |
| WordPress.org readme | 1 | Required |
| Optional translation/provenance notes | 2 | Retained, non-runtime |

`RANKKERNEL_DISTRIBUTION_FILE_REVIEW.csv` lists every one of the 239 paths with recommendation, reason and runtime dependency status.

## Runtime dependency verification

- `composer.json` requires only PHP `>=8.2`; there is no runtime package dependency.
- The plugin ships no `vendor/` directory. `rankkernel.php` registers a fallback PSR-4 autoloader mapping `RankKernel\` to `src/`; the optional `vendor/autoload.php` is loaded only when it exists.
- All **158** class/interface/trait files in `src/` resolve to their exact namespace path. Zero missing mappings.
- `uninstall.php` directly requires `src/Modules/Robots/LlmsFileWriter.php` and `src/Modules/Robots/LlmsRouter.php`; both are present.
- All explicit JS/CSS/font/block.json asset references resolve to packaged files. `/sitemap.xsl` is a route served by `XslStylesheet`; the actual `src/Modules/Sitemaps/sitemap.xsl` file is present.
- The three `block.json` files and their editor scripts/styles are present.
- No runtime dependency is missing.

## Licensing, readme and translations

- `LICENSE` is the full GPLv2 text.
- `readme.txt` contains the plugin metadata, installation, privacy, external services, AI disclosure, changelog and upgrade notice.
- The three bundled webfonts carry their full license texts (`OFL-1.1` for Inter and JetBrains Mono, Apache-2.0 for Material Symbols) plus provenance documentation.
- `languages/README.txt` (225 bytes) explains that no local `.mo` files ship and WordPress.org auto-loads translations. It is not runtime code, but it keeps the declared `Domain Path: /languages` directory present and documents the translation policy.

## `.distignore` check

- Tracked files: **540**.
- Expected after `.distignore`: **239**.
- Actual ZIP: **239**.
- Missing from ZIP: **0**.
- Extra in ZIP: **0**.
- No `tests/`, `.github/`, `.audit-prompts/`, `docs/`, `vendor/`, `composer.json`, `composer.lock`, `phpunit.xml`, `phpcs.xml`, `phpstan*`, `AGENTS.md`, `CONTRIBUTING.md`, root `README.md` or `.opencode-reviewer.yml` is present.
- `.distignore` does not exclude any runtime PHP, asset, block metadata, license, readme or translation file.

## Optional documentation files

Two non-runtime documentation files are retained:

1. `assets/fonts/README.md` (8,385 bytes) documents font provenance, subsetting and sources. It supports the font licensing record.
2. `languages/README.txt` (225 bytes) documents the intentional absence of local translation binaries.

Excluding them would require a new candidate ZIP and a fresh independent Server audit for negligible size benefit. Both are useful distribution provenance and neither affects runtime. Recommendation: **KEEP** in the approved package.

## Conclusion

The approved distribution ZIP is already minimal and complete. There are no development-only files to remove. No production file was changed, no new candidate was built, and the approved ZIP remains:

`4edb5beb087c1668c6678f4ccc768dd9432cb8ef2aa5fd70420f5e89e113287a` (902,973 bytes, 239 files).

No WordPress.org upload or GitHub release publication was performed.
