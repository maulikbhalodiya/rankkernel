# RankKernel 0.1.0 — Final Test Report

Date: 2026-11-07  
Source: `5e404efd7c1c7155ef8b65d1f1cf7c8e15a09b05`  
Package: `rankkernel-0.1.0.zip`  
Size: 937,797 bytes  
Files: 240 (239 code+assets plus `languages/rankkernel.pot`)  
SHA-256: `45ffccd66e40ab4715a8acdf48c04b291b82235b60f90a36963f0a279d5ed4ed`  
Manifest: `rankkernel-0.1.0-manifest.txt`  
Manifest SHA-256: `918cb4b58b18c8253e529f3c818215c97e2bf2f7e77a1263f190a98a5c59a354`

## Automated gates

| Gate | Command / environment | Result |
|---|---|---|
| PHPUnit | `php vendor/bin/phpunit --no-coverage` — PHP 8.3.36 | **2400 tests, 9216 assertions, OK** |
| PHPCS/WPCS | `composer lint` | **exit 0, no errors/warnings** |
| PHPStan level 6 | `composer stan` | **`[OK] No errors`** |
| JavaScript tests | `composer test:js` | **352 pass, 0 fail** |
| JavaScript syntax | `node --check` on each file in `assets/js/*.js` | **clean** |
| Shipped PHP syntax | `php -l` on the installed copy of every shipped PHP file | **clean** |
| Composer validation | `composer validate --strict` | **valid** |
| Composer audit | `composer audit --locked` | **no advisories** |
| Plugin Check 2.1.0, WP 7.1.4 | `wp plugin check rankkernel` on exact final ZIP | **0 errors / 223 warnings** |
| Plugin Check 2.1.0, WP 6.5.14 | `wp plugin check rankkernel` on exact final ZIP | **0 errors / 223 warnings** |

## Plugin Check warning classification

- **203** `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound`: method-scoped variables in included admin view files. PHPCS analyzes an included file as if it were global scope. The variables are local to the rendering method at runtime, are not PHP globals, and do not collide with another plugin. A prior pass reported 204; the 204th was an unprefixed transient variable since renamed to `$rankkernel_sites`. No suppression was added for these; each is individually classified in `FALSE_POSITIVE_JUSTIFICATIONS.md`.
- **20** `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `$wpdb` calls whose query text is built from fixed plugin-owned table identifiers (or a bound `prepare()` argument array). Values are prepared, table names are fixed/validated, and uninstall DROP identifiers are regex-gated. Each occurrence is listed in `FALSE_POSITIVE_JUSTIFICATIONS.md`.

No error was suppressed with a blanket rule. Ten targeted `phpcs:ignore` annotations remain for scanner limitations that cannot follow a sanitizer through `map_deep()` or for `$_FILES` metadata validated by `SupportDelivery`; each is explained at the line and in the false-positive report.

## Exact-package installation and runtime

Staging databases `rk_wporg71` and `rk_wporg65` were recreated for this pass (the prior `/tmp` staging had been cleared). Runtimes: `/tmp/opencode/wp71` (WordPress 7.1.4, PHP 8.2.29) and `/tmp/opencode/wp65` (WordPress 6.5.14, PHP 8.2.29).

| Environment | Action | Result |
|---|---|---|
| WordPress 7.1.4, PHP 8.2.29 (`/tmp/opencode/wp71`, DB `rk_wporg71`) | `wp plugin install <exact ZIP> --force --activate` | installed and activated |
| WordPress 7.1.4, PHP 8.2.29 | `wp plugin deactivate` then `wp plugin activate` | both succeeded |
| WordPress 7.1.4, PHP 8.2.29 | `wp eval` version/init check | `version=0.1.0`, `init_ok` |
| WordPress 6.5.14, PHP 8.2.29 (`/tmp/opencode/wp65`, DB `rk_wporg65`) | `wp plugin install <exact ZIP> --force --activate` | installed and activated |
| WordPress 6.5.14, PHP 8.2.29 | `wp plugin deactivate` then `wp plugin activate` | both succeeded |
| WordPress 6.5.14, PHP 8.2.29 | `wp eval` version/init check | `version=0.1.0`, `init_ok` |
| WordPress 6.5.14 | `wp plugin uninstall` then check tables | uninstall removed the plugin; no `rankkernel` tables left |
| WordPress 6.5.14 | reinstall exact ZIP + activate after uninstall | reinstalled and activated |
| Both | `wp-content/debug.log` after activation | absent/empty (no output) |

## Test evidence produced by this remediation

- `MetadataBoxTest`: exact-post authorization; unauthorized localized state returns `postId=0`; reflection test for `currentPostId()` rejects `$_GET['post']` without `edit_post`.
- `InstantIndexingPageTest`: action-scoped nonce before dispatch; negative/non-positive retry IDs do not submit.
- `RedirectsAdminTest`, `MonitorAdminTest`, `SettingsPageTest`, `SitemapSettingsAdminTest`, `MonitorLoggerTest`, `SchemaMetaboxTest`, `SchemaSettingsAdminTest`, `SupportPageTest`: input/authorization regressions pass after the boundary sanitization changes.
- `BreadcrumbsSecurityTest`, `BreadcrumbsBlockTest`, `BreadcrumbsOutputTest`: final filter/return-value escaping still passes.
- New coverage since the prior candidate: robots save success true-path including the inner-edit path; exclusion `map_deep`/`is_scalar` filtering (`rk_excl_comparator`, `rk_excl_value`); cron guard; wildcard capability caps; log redaction; IndexNow guards; import-lock user attribution; atomic `llms.txt` write; single-site uninstall scope (F-11); canonical host handling including scheme-without-host rejection; password gate plus password-protected-post noindex (F-10, `HeadRendererTest::test_password_protected_post_forces_noindex`).
- Regression matrix: 24 rows (`RT-01` through `RT-24`); new `RT-23` covers the password-post noindex regression and new `RT-24` covers the single-site uninstall regression.
- Full input inventory: 236 current superglobal expressions, 16 files, 0 `UNREVIEWED`, 0 `BLOCKED` (136 `FIXED`, 93 `PASS`, 6 `COMMENT_OR_DOCBLOCK_ONLY`, 1 `ACCEPTED_WITH_REASON`).
- Full output inventory: 1,244 output statements, 0 `UNREVIEWED`, 0 `BLOCKED` (1,231 `PASS`, 13 `ACCEPTED_WITH_REASON`).

## Limitations and not-run evidence

- The exact WordPress.org scanner dump for the 37/48/46 counts is unavailable. The audit reproduces the reported examples and inventories every current superglobal occurrence; it does not claim a one-to-one scanner mapping.
- Psalm is not installed locally, so no local Psalm run is claimed. Static analysis for this candidate is PHPStan level 6, PHPCS/WPCS and the focused security tests.
- Real Apache/LiteSpeed web-server behavior was not re-run in this final pass. Earlier release evidence covered Apache 2.4.58; the current changes are PHP input/output paths plus the password-post noindex and single-site uninstall scope fixes.
- Browser-level admin UI rendering beyond the WP-CLI activation/init smoke test was not re-run. The exact ZIP was installed, activated, deactivated, reactivated, uninstalled (WP 6.5) and reinstalled on both WordPress versions.
- No independent Server approval is claimed. This report is evidence for independent audit, not an approval and not a WordPress.org readiness claim beyond the evidence above.
