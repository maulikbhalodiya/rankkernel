# RankKernel 0.1.0 — Final Test Report

Source: `96008d2cce1c4c76e628cb35a08352ede8a7e6e9`  
Package: `rankkernel-0.1.0-candidate-96008d2.zip`  
Size: 902,496 bytes  
Files: 239  
SHA-256: `5e8ecd1ae4b26ee7a80cd8a94c8b367d183ba16436932c16adba55ff4ba538f0`  
Manifest SHA-256: `938017bff8b60f7bdb96074e42075bc5e0adf004183bd39714bd54976b20fd35`

## Automated gates

| Gate | Command / environment | Result |
|---|---|---|
| PHPUnit | `php vendor/bin/phpunit --no-coverage` — PHP 8.3.36 | **2394 tests, 9201 assertions, OK** |
| PHPCS/WPCS | `composer lint` | **exit 0, no errors/warnings** |
| PHPStan level 6 | `composer stan` | **`[OK] No errors`** |
| JavaScript tests | `composer test:js` | **352 pass, 0 fail** |
| JavaScript syntax | `node --check assets/js/*.js` | **clean** |
| Shipped PHP syntax | `php -l` on all 181 packaged PHP files | **clean** |
| Composer validation | `composer validate --strict` | **valid** |
| Composer audit | `composer audit --locked` | **no advisories** |
| Plugin Check 2.1.0, WP 7.1.4 | `wp plugin check rankkernel` on exact ZIP | **0 errors / 223 warnings** |
| Plugin Check 2.1.0, WP 6.5.14 | `wp plugin check rankkernel` on exact ZIP | **0 errors / 223 warnings** |

## Plugin Check warning classification

- **203** `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound`: method-scoped variables in included admin view files. PHPCS treats an included file as global scope; the variables are local to the rendering method and are not global. No suppression was added for these; they remain visible and individually classified in `FALSE_POSITIVE_JUSTIFICATIONS.md`.
- **20** `PluginCheck.Security.DirectDB.UnescapedDBParameter`: `$wpdb` calls whose query text is built from fixed plugin-owned table identifiers (or a bound `prepare()` argument array). Values are prepared, table names are fixed/validated, and uninstall DROP identifiers are regex-gated. Each occurrence is listed in `FALSE_POSITIVE_JUSTIFICATIONS.md`.

No error was suppressed with a blanket rule. Ten targeted `phpcs:ignore` annotations remain for scanner limitations that cannot follow a sanitizer through `map_deep()` or for `$_FILES` metadata validated by `SupportDelivery`; each is explained at the line and in the false-positive report.

## Exact-package installation and runtime

| Environment | Action | Result |
|---|---|---|
| WordPress 7.1.4, PHP 8.2.29 | `wp plugin install <exact zip> --force --activate` | installed and activated |
| WordPress 7.1.4, PHP 8.2.29 | `wp plugin deactivate` then `wp plugin activate` | both succeeded |
| WordPress 7.1.4, PHP 8.2.29 | `wp eval` version/init check | `version=0.1.0`, `init_ok` |
| WordPress 6.5.14, PHP 8.2.29 | `wp plugin install <exact zip> --force --activate` | installed and activated |
| WordPress 6.5.14, PHP 8.2.29 | `wp plugin deactivate` then `wp plugin activate` | both succeeded |
| WordPress 6.5.14, PHP 8.2.29 | uninstall + reinstall exact ZIP | uninstall removed plugin; exact ZIP reinstalled and activated |
| Both | `wp-content/debug.log` after activation | absent/empty |

## Test evidence produced by this remediation

- `MetadataBoxTest`: exact-post authorization; unauthorized localized state returns `postId=0`; reflection test for `currentPostId()` rejects `$_GET['post']` without `edit_post`.
- `InstantIndexingPageTest`: action-scoped nonce before dispatch; negative/non-positive retry IDs do not submit.
- `RedirectsAdminTest`, `MonitorAdminTest`, `SettingsPageTest`, `SchemaMetaboxTest`, `SchemaSettingsAdminTest`, `SitemapSettingsAdminTest`, `SupportPageTest`: input/authorization regressions pass after the boundary sanitization changes.
- `BreadcrumbsSecurityTest`, `BreadcrumbsBlockTest`, `BreadcrumbsOutputTest`: final filter/return-value escaping still passes.
- Full input inventory: 236 current superglobal expressions, 16 files, 0 `UNREVIEWED`, 0 `BLOCKED`.
- Full output inventory: 1,244 output statements, 0 `UNREVIEWED`, 0 `BLOCKED`.

## Limitations and not-run evidence

- Psalm is not installed in the local `vendor/bin`; no local Psalm taint run is claimed for this commit. CI evidence from the previous release branch covered Psalm; the current changes were covered by PHPStan L6, PHPCS and focused security tests.
- Real Apache/LiteSpeed web-server behavior was not re-run in this final pass. Earlier release evidence covered Apache 2.4.58; the current changes are PHP input/output paths.
- Browser-level admin UI rendering was not re-run beyond WP-CLI activation/init and Plugin Check; the exact ZIP was installed on both WordPress versions.
- The exact WordPress.org scanner dump for the 37/48/46 counts is unavailable. The audit reproduces the reported examples and inventories every current superglobal occurrence; it does not claim a one-to-one scanner mapping.
- No independent Server OpenCode approval is claimed.
